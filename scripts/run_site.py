"""Launch the PHP site, avoiding occupied or Windows-reserved ports."""
import argparse
import os
from pathlib import Path
import shutil
import socket
import subprocess
import sys
import time
import urllib.error
import urllib.request
import webbrowser

ROOT = Path(__file__).resolve().parent.parent


def choose_port(value=None):
    if value is not None:
        try:
            port = int(value)
        except ValueError:
            raise ValueError("SITE_PORT must be an integer between 1 and 65535")
        if not 1 <= port <= 65535:
            raise ValueError("SITE_PORT must be between 1 and 65535")
        candidates = [port]
    else:
        candidates = range(8000, 8200)
    for port in candidates:
        with socket.socket(socket.AF_INET, socket.SOCK_STREAM) as listener:
            # Windows must not reuse a port occupied by another service.
            if hasattr(socket, "SO_EXCLUSIVEADDRUSE"):
                listener.setsockopt(socket.SOL_SOCKET, socket.SO_EXCLUSIVEADDRUSE, 1)
            try:
                listener.bind(("127.0.0.1", port))
            except OSError:
                continue
            return port
    raise RuntimeError("No available site port. Set SITE_PORT to a free port; do not stop unknown services.")


def wait_ready(process, url, timeout=15):
    # Local requests must not go through a configured HTTP proxy.
    opener = urllib.request.build_opener(urllib.request.ProxyHandler({}))
    deadline = time.monotonic() + timeout
    while time.monotonic() < deadline:
        if process.poll() is not None:
            raise RuntimeError("PHP exited before the site started. Check the error above.")
        try:
            with opener.open(url, timeout=1) as response:
                if response.status == 200 and response.headers.get("X-OpenDoter-Service") == "site":
                    return
        except (OSError, urllib.error.URLError):
            pass
        time.sleep(0.2)
    raise RuntimeError("The site did not become ready. Browser was not opened.")


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--open-browser", action="store_true")
    args = parser.parse_args()
    php = shutil.which("php")
    if not php:
        print("ERROR: PHP 8.1+ was not found in PATH.", file=sys.stderr)
        return 1
    process = None
    try:
        configured = os.environ.get("SITE_PORT") or None
        port = choose_port(configured)
        url = f"http://127.0.0.1:{port}/"
        if configured is None and port != 8000:
            print(f"Port 8000 is unavailable; using {port} instead.", flush=True)
        process = subprocess.Popen([php, "-S", f"127.0.0.1:{port}", "-t", str(ROOT), str(ROOT / "index.php")], cwd=ROOT)
        wait_ready(process, url)
        print(f"OpenDoter site: {url} (Ctrl+C to stop)", flush=True)
        if args.open_browser:
            webbrowser.open(url)
        return process.wait()
    except KeyboardInterrupt:
        return 0
    except (OSError, ValueError, RuntimeError) as exc:
        print(f"ERROR: {exc}", file=sys.stderr)
        return 1
    finally:
        if process is not None and process.poll() is None:
            process.terminate()
            try:
                process.wait(timeout=5)
            except subprocess.TimeoutExpired:
                process.kill()
                process.wait()


if __name__ == "__main__":
    sys.exit(main())
