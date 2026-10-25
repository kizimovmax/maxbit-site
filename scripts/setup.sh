#!/usr/bin/env bash
# Первичная установка локальной копии WordPress (SQLite). Повторный запуск безопасен:
# уже установленный сайт не переустанавливается, данные не трогаются.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
SITE="$ROOT/site"
WP="php $ROOT/vendor/wp-cli.phar --path=$SITE --allow-root"
URL="${MAXBIT_URL:-http://localhost:8080}"

# Тема и модуль живут в src/ и подключаются симлинками (правки — только в src/).
mkdir -p "$SITE/wp-content/themes" "$SITE/wp-content/plugins" "$ROOT/logs"
[ -e "$SITE/wp-content/themes/maxbit" ] || ln -s ../../../src/themes/maxbit "$SITE/wp-content/themes/maxbit"
[ -e "$SITE/wp-content/plugins/maxbit-core" ] || ln -s ../../../src/plugins/maxbit-core "$SITE/wp-content/plugins/maxbit-core"
if [ ! -f "$SITE/wp-content/db.php" ] && [ -f "$SITE/wp-content/plugins/sqlite-database-integration/db.copy" ]; then
  sed -e "s#{SQLITE_IMPLEMENTATION_FOLDER_PATH}#__DIR__ . '/plugins/sqlite-database-integration'#" \
      -e "s#{SQLITE_PLUGIN}#sqlite-database-integration/load.php#" \
      "$SITE/wp-content/plugins/sqlite-database-integration/db.copy" > "$SITE/wp-content/db.php"
  sed -i "s#'__DIR__ . '/plugins/sqlite-database-integration''#__DIR__ . '/plugins/sqlite-database-integration'#" "$SITE/wp-content/db.php"
fi

CONFIG_HASH_BEFORE="$(sha256sum "$SITE/wp-config.php" | cut -d' ' -f1)"
if [ ! -f "$SITE/wp-config-local.php" ]; then
  # Единственное место генерации солей — исключённый из Git локальный файл. Основной wp-config.php не меняется.
  TMP="$(mktemp "$SITE/.wp-config-local.XXXXXX")"
  php -r '
    $keys=["AUTH_KEY","SECURE_AUTH_KEY","LOGGED_IN_KEY","NONCE_KEY","AUTH_SALT","SECURE_AUTH_SALT","LOGGED_IN_SALT","NONCE_SALT"];
    $out="<?php\n// Локальные соли и секреты. Файл вне Git (см. .gitignore). Не копировать в отчёты.\n";
    foreach($keys as $k){$out.="define( \x27$k\x27, \x27".bin2hex(random_bytes(32))."\x27 );\n";}
    file_put_contents($argv[1],$out);' "$TMP"
  chmod 600 "$TMP"
  mv "$TMP" "$SITE/wp-config-local.php"
  echo "Созданы локальные соли: site/wp-config-local.php (права 600)"
fi
chmod 600 "$SITE/wp-config-local.php"
CONFIG_HASH_AFTER="$(sha256sum "$SITE/wp-config.php" | cut -d' ' -f1)"
[ "$CONFIG_HASH_BEFORE" = "$CONFIG_HASH_AFTER" ] || { echo "ОШИБКА: wp-config.php изменился при установке"; exit 1; }
grep -Eq "define\( '(AUTH_KEY|NONCE_SALT)', '[0-9a-f]{64}'" "$SITE/wp-config.php" && { echo "ОШИБКА: в wp-config.php обнаружены соли"; exit 1; }

if ! $WP core is-installed >/dev/null 2>&1; then
  ADMIN_PASS="$(php -r 'echo bin2hex(random_bytes(12));')"
  $WP core install --url="$URL" --title="MAXBIT" \
    --admin_user="maxbit_admin" --admin_password="$ADMIN_PASS" \
    --admin_email="admin@localhost.test" --skip-email >/dev/null
  echo "$ADMIN_PASS" > "$ROOT/logs/admin-password.local.txt"
  chmod 600 "$ROOT/logs/admin-password.local.txt"
  echo "WordPress установлен. Логин maxbit_admin, пароль в logs/admin-password.local.txt (файл вне Git)."
fi

$WP plugin activate maxbit-core >/dev/null
$WP theme activate maxbit >/dev/null
if [ -z "$($WP option get maxbit_setup_done 2>/dev/null)" ] && [ -z "$($WP option get maxbit_seed_done 2>/dev/null)" ]; then
  # Начальные настройки — только при первой установке; повторный setup их не сбрасывает.
  $WP option update blogdescription "Системы управления бизнесом" >/dev/null
  $WP option update timezone_string "Europe/Moscow" >/dev/null
  $WP option update permalink_structure "/%postname%/" >/dev/null
  $WP option update blog_public 0 >/dev/null          # локально: не индексировать
  $WP option update default_comment_status closed >/dev/null
  $WP option update date_format "d.m.Y" >/dev/null
  $WP option update maxbit_setup_done "$(date -u +%FT%TZ)" >/dev/null
fi
$WP rewrite flush --hard >/dev/null 2>&1 || $WP rewrite flush >/dev/null
echo "Готово. Запуск: scripts/serve.sh"
