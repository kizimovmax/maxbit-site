#!/usr/bin/env bash
# Полный сброс ЛОКАЛЬНОЙ базы (SQLite) и повторная установка + наполнение. Удаляет все локальные данные, включая тестовые обращения.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
read -r -p "Удалить локальную базу и пересоздать сайт? [y/N] " a; [ "${a:-N}" = "y" ] || exit 1
rm -f "$ROOT/site/wp-content/database/.ht.sqlite" "$ROOT/logs/admin-password.local.txt"
bash "$ROOT/scripts/setup.sh"
php "$ROOT/vendor/wp-cli.phar" --path="$ROOT/site" --allow-root eval-file "$ROOT/scripts/seed.php"
