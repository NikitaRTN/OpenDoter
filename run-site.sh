#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")"
if ! command -v python3 >/dev/null 2>&1; then
  echo "ERROR: Python 3.10+ was not found in PATH." >&2
  exit 1
fi
exec python3 scripts/run_site.py "$@"
