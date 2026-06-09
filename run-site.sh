#!/usr/bin/env bash
# OpenDoter — главный сайт (PHP) на http://localhost:8000
set -euo pipefail
cd "$(dirname "$0")"

if ! command -v php >/dev/null 2>&1; then
  echo "[ОШИБКА] php не найден в PATH. Установите PHP 8.1+." >&2
  exit 1
fi

PORT="${SITE_PORT:-8000}"
echo "  OpenDoter (сайт)  ->  http://localhost:${PORT}   (Ctrl+C для остановки)"
exec php -S 127.0.0.1:"${PORT}" index.php
