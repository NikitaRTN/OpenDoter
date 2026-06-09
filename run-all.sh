#!/usr/bin/env bash
# OpenDoter — запуск API и сайта вместе (API в фоне, сайт на переднем плане).
set -euo pipefail
cd "$(dirname "$0")"

"./run-api.sh" &
API_PID=$!
trap 'kill "$API_PID" 2>/dev/null || true' EXIT INT TERM

sleep 1
"./run-site.sh"
