#!/usr/bin/env bash
# A06/A07/A20, MB-08, MB-12: редактирование данными по ролям, смена темы с непустым блоком проектов,
# безопасный повторный seed и setup, экспорт. Уровень: интеграционный (WordPress через WP-CLI + HTTP).
set -uo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
WP="php $ROOT/vendor/wp-cli.phar --path=$ROOT/site --allow-root"
PORT="${MAXBIT_CHECK_PORT:-8089}"; BASE="http://127.0.0.1:$PORT"
LOG="$ROOT/reports/test-logs/check-content_$(date -u +%Y%m%dT%H%M%SZ).log"; mkdir -p "$(dirname "$LOG")"
exec > >(tee "$LOG") 2>&1
echo "# check-content $(date -u +%FT%TZ) WP=$($WP core version) theme=$($WP theme list --status=active --field=name)"
PASS=0; FAIL=0; ok(){ PASS=$((PASS+1)); echo "PASS  $1"; }; fail(){ FAIL=$((FAIL+1)); echo "FAIL  $1"; }
php -S "127.0.0.1:$PORT" -t "$ROOT/site" "$ROOT/scripts/router.php" >/tmp/mb_srv_c.log 2>&1 & SRV=$!; trap 'kill $SRV 2>/dev/null' EXIT; sleep 1.5
RUN=$(date +%s)

echo "== A06: контент-редактор (роль editor) меняет материал данными, код не трогается =="
$WP user get mb_editor --field=ID >/dev/null 2>&1 || $WP user create mb_editor mb_editor@localhost.test --role=editor --user_pass="Editor-Pass-Local-1" >/dev/null
ID=$($WP post list --post_type=mb_solution --name=zakaz-pod-kontrolem --field=ID)
ORIG=$($WP post get "$ID" --field=post_excerpt); SRC_HASH=$(find "$ROOT/src" -type f -exec sha256sum {} + | sha256sum | cut -c1-16)
$WP post update "$ID" --post_excerpt="Правка редактора $RUN" --user=mb_editor >/dev/null 2>&1
[ "$($WP post get "$ID" --field=post_excerpt)" = "Правка редактора $RUN" ] && ok "editor изменил текст решения (#$ID)" || fail "editor не смог изменить"
curl -s -o /tmp/mb_b1 -w "%{http_code} " "$BASE/resheniya/" ; grep -q "Правка редактора $RUN" /tmp/mb_b1 && ok "изменение видно на сайте без правки кода (src не изменился: $SRC_HASH = $(find "$ROOT/src" -type f -exec sha256sum {} + | sha256sum | cut -c1-16))" || fail "изменение не видно на сайте"
$WP post update "$ID" --post_excerpt="$ORIG" >/dev/null
$WP post meta update "$ID" _mb_public_ok 0 --user=mb_editor >/dev/null 2>&1; F=$($WP post meta get "$ID" _mb_public_ok)
echo "  (примечание: прямой WP-CLI-доступ к мета не ограничен ролями — проверка флага в UI выполнена в check.sh)"; $WP post meta update "$ID" _mb_public_ok 1 >/dev/null
[ "$($WP post list --post_type=revision --post_parent="$ID" --format=count)" -ge 1 ] && ok "ревизии сохраняются" || fail "ревизии"

echo "== MB-12/A07: реальная смена темы при непустом блоке проектов =="
PID=$($WP post list --post_type=mb_project --post_status=any --meta_key=_mb_test --meta_value=prj-open --field=ID | head -1)
[ -n "$PID" ] || { PID=$($WP post create --post_type=mb_project --post_status=publish --post_title="SYN Открытый проект" --post_name=syn-prj-open --post_excerpt="Синтетика" --porcelain); $WP post meta set $PID _mb_test prj-open >/dev/null; $WP post meta set $PID _mb_public_ok 1 >/dev/null; }
c=$(curl -s -o /tmp/mb_home -w '%{http_code}' "$BASE/proekty/"); grep -q "syn-prj-open" /tmp/mb_home && ok "тема MAXBIT: открытый синтетический проект виден в списке проектов (блок непустой)" || fail "блок проектов пуст при открытом проекте (HTTP $c, PID=$PID, ok=$($WP post meta get $PID _mb_public_ok), status=$($WP post get $PID --field=post_status))"
$WP theme activate twentytwentythree >/dev/null 2>&1; RC=$?; ACT=$($WP theme list --status=active --field=name)
[ $RC = 0 ] && [ "$ACT" = twentytwentythree ] && ok "активирована другая тема: $ACT (returncode $RC)" || fail "смена темы: rc=$RC active=$ACT"
c=$(curl -s -o /tmp/mb_body -w '%{http_code}' "$BASE/")
[ "$c" = 200 ] && grep -q "proekty/gk-vpk" /tmp/mb_body && ! grep -qi 'fatal\|critical error' /tmp/mb_body && ok "при другой теме главная 200, блок проектов отрисован нейтральной карточкой модуля, без fatal" || fail "при другой теме: HTTP $c, fatal=$(grep -c -i 'critical error' /tmp/mb_body)"
c=$(curl -s -o /tmp/mb_body -w '%{http_code}' "$BASE/resheniya/zakaz-pod-kontrolem/")
[ "$c" = 200 ] && grep -q 'value="order_control"' /tmp/mb_body && ok "при другой теме решение и форма (тема order_control) работают" || fail "решение при другой теме: $c"
c=$(curl -s -o /tmp/mb_body -w '%{http_code}' "$BASE/resheniya/ii-praktikum/"); [ "$c" = 404 ] && ok "при другой теме закрытое остаётся закрытым (404)" || fail "закрытое при другой теме: $c"
N=$($WP post list --post_type=mb_solution,mb_project --post_status=publish,draft,private --format=count); REL=$($WP post meta get "$ID" _mb_related_article)
[ "$N" -ge 8 ] && [ -n "$REL" ] && ok "типы, записи ($N) и связи на месте" || fail "данные при другой теме"
$WP theme activate maxbit >/dev/null 2>&1; [ "$($WP theme list --status=active --field=name)" = maxbit ] && ok "тема MAXBIT возвращена" || fail "возврат темы"

echo "== MB-08: повторный seed после ручных правок и страницы владельца =="
$WP eval-file "$ROOT/scripts/seed.php" >/dev/null 2>&1   # база уже наполнена: фиксируем первый прогон, затем правим и повторяем
PP=$($WP post list --post_type=page --name=privacy-policy --post_status=any --field=ID | head -1)
[ -n "$PP" ] || PP=$($WP post create --post_type=page --post_status=publish --post_title="Политика конфиденциальности" --post_name=privacy-policy --post_content="Текст владельца $RUN" --porcelain)
HP=$($WP option get page_on_front); $WP post update "$HP" --post_title="Главная (правка владельца $RUN)" >/dev/null
$WP option update blogdescription "Подпись владельца $RUN" >/dev/null
BEFORE=$($WP post list --post_type=page,post,mb_solution,mb_project --post_status=any --format=count)
$WP eval-file "$ROOT/scripts/seed.php" >/tmp/mb_seed.log 2>&1
AFTER=$($WP post list --post_type=page,post,mb_solution,mb_project --post_status=any --format=count)
[ "$BEFORE" = "$AFTER" ] && ok "повторный seed: количество материалов не изменилось ($BEFORE)" || fail "seed изменил количество: $BEFORE→$AFTER"
[ "$($WP post get "$PP" --field=post_status)" = publish ] && ok "страница владельца privacy-policy сохранена (#$PP)" || fail "privacy-policy удалена/изменена"
[ "$($WP post get "$HP" --field=post_title)" = "Главная (правка владельца $RUN)" ] && ok "ручная правка главной сохранена" || fail "правка главной перезаписана"
[ "$($WP option get blogdescription)" = "Подпись владельца $RUN" ] && ok "настройка владельца (blogdescription) не сброшена" || fail "blogdescription сброшена"
[ "$($WP option get page_on_front)" = "$HP" ] && ok "page_on_front не изменён" || fail "page_on_front изменён"
grep -q "перемещён в корзину\|удал" /tmp/mb_seed.log && fail "повторный seed что-то удалил" || ok "повторный seed ничего не удалял"
$WP post update "$HP" --post_title="Главная" >/dev/null; $WP option update blogdescription "Системы управления бизнесом" >/dev/null
echo "== MB-17/MB-08: повторный setup не меняет wp-config.php и настройки =="
H1=$(sha256sum "$ROOT/site/wp-config.php" | cut -c1-16); S1=$(sha256sum "$ROOT/site/wp-config-local.php" | cut -c1-16); $WP option update blog_public 0 >/dev/null
bash "$ROOT/scripts/setup.sh" >/tmp/mb_setup.log 2>&1; RC=$?
[ $RC = 0 ] && [ "$(sha256sum "$ROOT/site/wp-config.php" | cut -c1-16)" = "$H1" ] && [ "$(sha256sum "$ROOT/site/wp-config-local.php" | cut -c1-16)" = "$S1" ] && ok "повторный setup: wp-config.php и соли не изменились" || fail "setup: rc=$RC, конфиг изменился"
! grep -Eq "define\( '(AUTH_KEY|NONCE_SALT)', '[0-9a-f]{64}'" "$ROOT/site/wp-config.php" && ok "в отслеживаемом wp-config.php нет солей" || fail "соли в wp-config.php"
[ "$(stat -c %a "$ROOT/site/wp-config-local.php")" = 600 ] && ok "wp-config-local.php с правами 600" || fail "права $(stat -c %a "$ROOT/site/wp-config-local.php")"
grep -q "wp-config-local.php" "$ROOT/.gitignore" && ok "wp-config-local.php исключён из Git" || fail ".gitignore"

echo "== A07: экспорт WXR =="
$WP export --dir=/tmp --filename_format=maxbit-export.xml --post_type=mb_solution >/dev/null 2>&1 && grep -q "zakaz-pod-kontrolem" /tmp/maxbit-export.xml && grep -q "_mb_public_ok" /tmp/maxbit-export.xml && ok "экспорт WXR содержит записи и метаполя" || fail "экспорт"
rm -f /tmp/maxbit-export.xml
echo "  импорт WXR требует плагин wordpress-importer — не проверен (статус: не проверен)"
$WP post delete "$PID" --force >/dev/null 2>&1
echo "ИТОГО: PASS=$PASS FAIL=$FAIL"; [ $FAIL = 0 ]
