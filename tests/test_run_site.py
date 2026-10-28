import importlib.util
from pathlib import Path
import unittest
from unittest.mock import MagicMock, patch

spec = importlib.util.spec_from_file_location("run_site", Path(__file__).resolve().parents[1] / "scripts" / "run_site.py")
launcher = importlib.util.module_from_spec(spec)
spec.loader.exec_module(launcher)


class LauncherTests(unittest.TestCase):
    def test_invalid_port(self):
        for value in ("abc", "0", "65536"):
            with self.subTest(value=value), self.assertRaises(ValueError):
                launcher.choose_port(value)

    def test_reserved_default_falls_back(self):
        busy, free = MagicMock(), MagicMock()
        busy.__enter__.return_value.bind.side_effect = PermissionError("reserved")
        with patch.object(launcher.socket, "socket", side_effect=[busy, free]):
            self.assertEqual(launcher.choose_port(), 8001)

    def test_explicit_busy_port_fails(self):
        busy = MagicMock()
        busy.__enter__.return_value.bind.side_effect = OSError("busy")
        with patch.object(launcher.socket, "socket", return_value=busy):
            with self.assertRaises(RuntimeError):
                launcher.choose_port("8000")

    def test_dead_php_is_not_ready(self):
        process = MagicMock()
        process.poll.return_value = 1
        with self.assertRaises(RuntimeError):
            launcher.wait_ready(process, "http://127.0.0.1:8000/")

    def test_wrong_service_is_not_ready(self):
        process = MagicMock()
        process.poll.side_effect = [None, 1]
        response = MagicMock()
        response.status = 200
        response.headers = {"X-OpenDoter-Service": "other"}
        opener = MagicMock()
        opener.open.return_value.__enter__.return_value = response
        with patch.object(launcher.urllib.request, "build_opener", return_value=opener), patch.object(launcher.time, "sleep"):
            with self.assertRaises(RuntimeError):
                launcher.wait_ready(process, "http://127.0.0.1:8000/")


if __name__ == "__main__":
    unittest.main()
