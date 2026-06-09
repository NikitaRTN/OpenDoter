#!/usr/bin/env bash
# OpenDoter — локальный API (Python) на http://localhost:8080
set -euo pipefail
cd "$(dirname "$0")/parser-master"

if ! command -v python3 >/dev/null 2>&1; then
  echo "[ОШИБКА] python3 не найден в PATH. Установите Python 3.10+." >&2
  exit 1
fi

export PORT="${PORT:-8080}"
echo "  OpenDoter API  ->  http://localhost:${PORT}   (Ctrl+C для остановки)"
exec python3 server.py
