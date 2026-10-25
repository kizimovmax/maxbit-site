#!/usr/bin/env bash
# Чистая установка из текущего дерева в отдельный каталог: setup → seed → seed повторно → все проверки.
# Заодно: образцы ядра уходят в корзину только при первом наполнении; wp-config.php не меняется.
set -uo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"; T="$(mktemp -d /tmp/maxbit-fresh.XXXXXX)"
LOG="$ROOT/reports/test-logs/check-fresh_$(date -u +%Y%m%dT%H%M%SZ).log"; mkdir -p "$(dirname "$LOG")"; exec > >(tee "$LOG") 2>&1
echo "# check-fresh $(date -u +%FT%TZ) dir=$T"
mkdir -p "$T/site/wp-content" "$T/vendor" "$T/logs" "$T/reports"
cp -r "$ROOT/src" "$ROOT/scripts" "$ROOT/reference" "$ROOT/.gitignore" "$T/"; cp "$ROOT/vendor/wp-cli.phar" "$T/vendor/"
# ядро и SQLite-плагин — копия текущих (без базы, без локальных секретов, без симлинков)
( cd "$ROOT/site" && find . -maxdepth 1 ! -name . ! -name wp-content ! -name wp-config-local.php -exec cp -r {} "$T/site/" \; )
mkdir -p "$T/site/wp-content/plugins"; cp -r "$ROOT/site/wp-content/plugins/sqlite-database-integration" "$T/site/wp-content/plugins/sqlite-database-integration"
mkdir -p "$T/site/wp-content/themes"; cp -r "$ROOT/site/wp-content/themes/twentytwentythree" "$T/site/wp-content/themes/" 2>/dev/null
cp "$ROOT/site/wp-content/index.php" "$T/site/wp-content/" 2>/dev/null; mkdir -p "$T/site/wp-content/database"; echo "Deny from all" > "$T/site/wp-content/database/.htaccess"
cd "$T"; WP="php $T/vendor/wp-cli.phar --path=$T/site --allow-root"; PASS=0; FAIL=0; ok(){ PASS=$((PASS+1)); echo "PASS  $1"; }; fail(){ FAIL=$((FAIL+1)); echo "FAIL  $1"; }
H0=$(sha256sum site/wp-config.php | cut -c1-16)
bash scripts/setup.sh >/tmp/mb_fresh_setup.log 2>&1 && ok "setup.sh на чистой копии завершился без ошибок" || { fail "setup.sh: $(tail -2 /tmp/mb_fresh_setup.log)"; }
[ "$(sha256sum site/wp-config.php | cut -c1-16)" = "$H0" ] && ok "wp-config.php не изменён установкой" || fail "wp-config.php изменён"
[ -L site/wp-content/themes/maxbit ] && [ -L site/wp-content/plugins/maxbit-core ] && ok "симлинки темы и модуля созданы setup.sh" || fail "симлинки"
$WP post get 2 --field=post_name 2>/dev/null | grep -q sample-page && ok "перед seed есть образец ядра sample-page (#2)" || echo "  INFO  sample-page отсутствует до seed"
$WP eval-file scripts/seed.php >/tmp/mb_fresh_seed.log 2>&1 && ok "seed.php первый запуск: $(grep -c 'создано' /tmp/mb_fresh_seed.log) материалов создано" || fail "seed"
[ "$($WP post get 2 --field=post_status 2>/dev/null)" = trash ] && ok "образец ядра #2 в корзине (не удалён безвозвратно)" || fail "образец ядра: $($WP post get 2 --field=post_status 2>/dev/null)"
$WP eval-file scripts/seed.php >/tmp/mb_fresh_seed2.log 2>&1; grep -q "создано" /tmp/mb_fresh_seed2.log && fail "повторный seed что-то создал" || ok "повторный seed ничего не создал и не удалил"
[ "$($WP option get permalink_structure)" = "/praktika/%postname%/" ] && ok "пермалинки /praktika/%postname%/" || fail "пермалинки: $($WP option get permalink_structure)"
MAXBIT_CHECK_PORT=8093 bash scripts/check.sh > /tmp/mb_fresh_check.log 2>&1; tail -1 /tmp/mb_fresh_check.log | grep -q "FAIL=0" && ok "check.sh на чистой копии: $(tail -1 /tmp/mb_fresh_check.log)" || fail "check.sh: $(grep FAIL /tmp/mb_fresh_check.log | head -3)"
MAXBIT_CHECK_PORT=8093 bash scripts/check-leads.sh > /tmp/mb_fresh_leads.log 2>&1; tail -1 /tmp/mb_fresh_leads.log | grep -q "FAIL=0" && ok "check-leads.sh: $(tail -1 /tmp/mb_fresh_leads.log)" || fail "check-leads: $(grep FAIL /tmp/mb_fresh_leads.log | head -3)"
bash scripts/check-delivery.sh > /tmp/mb_fresh_deliv.log 2>&1; tail -1 /tmp/mb_fresh_deliv.log | grep -q "FAIL=0" && ok "check-delivery.sh: $(tail -1 /tmp/mb_fresh_deliv.log)" || fail "check-delivery: $(grep FAIL /tmp/mb_fresh_deliv.log | head -3)"
MAXBIT_CHECK_PORT=8093 bash scripts/check-content.sh > /tmp/mb_fresh_content.log 2>&1; tail -1 /tmp/mb_fresh_content.log | grep -q "FAIL=0" && ok "check-content.sh: $(tail -1 /tmp/mb_fresh_content.log)" || fail "check-content: $(grep FAIL /tmp/mb_fresh_content.log | head -3)"
echo "ИТОГО: PASS=$PASS FAIL=$FAIL (каталог $T)"; [ $FAIL = 0 ]
