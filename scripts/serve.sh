#!/usr/bin/env bash
# Локальный сервер PHP. Только для разработки, не для публичного размещения.
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
PORT="${MAXBIT_PORT:-8080}"
echo "MAXBIT local: http://localhost:$PORT  (Ctrl+C — остановить)"
exec php -S "127.0.0.1:$PORT" -t "$ROOT/site" "$ROOT/scripts/router.php"
