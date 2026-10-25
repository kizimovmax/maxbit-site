#!/usr/bin/env bash
# Основные HTTP-проверки локальной сборки MAXBIT. Уровень: интеграционный (настоящий WordPress + встроенный сервер PHP).
# Не заменяет браузерные проверки (вёрстка, клавиатура, Web Vitals) — они отмечаются отдельно.
set -uo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
PORT="${MAXBIT_CHECK_PORT:-8089}"; BASE="http://127.0.0.1:$PORT"
WP="php $ROOT/vendor/wp-cli.phar --path=$ROOT/site --allow-root"
LOG="$ROOT/reports/test-logs/check_$(date -u +%Y%m%dT%H%M%SZ).log"; mkdir -p "$(dirname "$LOG")"
exec > >(tee "$LOG") 2>&1
echo "# check.sh $(date -u +%FT%TZ) PHP=$(php -r 'echo PHP_VERSION;') WP=$($WP core version) theme=$($WP theme list --status=active --field=name) env=local"
PASS=0; FAIL=0
ok()   { PASS=$((PASS+1)); echo "PASS  $1"; }
fail() { FAIL=$((FAIL+1)); echo "FAIL  $1"; }
check(){ if eval "$2"; then ok "$1"; else fail "$1"; fi; }
code() { curl -s -o /tmp/mb_body -w '%{http_code}' "${@:2}" "$BASE$1"; }
start_server(){ MAXBIT_ENV="${1:-local}" php -S "127.0.0.1:$PORT" -t "$ROOT/site" "$ROOT/scripts/router.php" >/tmp/mb_srv.log 2>&1 & SRV=$!; sleep 1.5; }
stop_server(){ kill $SRV 2>/dev/null; wait $SRV 2>/dev/null; }
trap 'stop_server' EXIT
login(){ # $1 user $2 pass $3 cookiejar
  curl -s -c "$3" -b "$3" -o /dev/null "$BASE/wp-login.php"
  curl -s -c "$3" -b "$3" -o /dev/null -X POST "$BASE/wp-login.php" --data-urlencode "log=$1" --data-urlencode "pwd=$2" --data-urlencode "wp-submit=Log In" --data-urlencode "testcookie=1" --data-urlencode "redirect_to=$BASE/wp-admin/"
}

# --- синтетические записи для матрицы доступа (создаются один раз, помечены _mb_test) ---
ADMIN_PW=$(cat "$ROOT/logs/admin-password.local.txt")
mkuser(){ $WP user get "$1" --field=ID >/dev/null 2>&1 || $WP user create "$1" "$1@localhost.test" --role="$2" --user_pass="$3" >/dev/null 2>&1; }
mkuser mb_editor editor "Editor-Pass-Local-1"
mkuser mb_subscriber subscriber "Subscriber-Pass-Local-1"
mkpost(){ # $1 seedkey $2 type $3 status $4 ok $5 sample $6 title → id
  ID=$($WP post list --post_type="$2" --post_status=any --meta_key=_mb_test --meta_value="$1" --field=ID 2>/dev/null | head -1)
  if [ -z "$ID" ]; then
    ID=$($WP post create --post_type="$2" --post_status="$3" --post_title="$6" --post_name="syn-$1" --post_content="Синтетический материал для проверки доступа ($1)." --post_excerpt="Синтетика $1" --porcelain)
    $WP post meta set "$ID" _mb_test "$1" >/dev/null; $WP post meta set "$ID" _mb_public_ok "$4" >/dev/null; [ "$5" = 1 ] && $WP post meta set "$ID" _mb_is_sample 1 >/dev/null
  fi
  echo "$ID"
}
S_PUB_NOOK=$(mkpost sol-pub-nook mb_solution publish 0 0 "SYN Решение publish без допуска")
S_PUB_OK=$(mkpost sol-pub-ok mb_solution publish 1 0 "SYN Решение publish с допуском")
S_DRAFT=$(mkpost sol-draft mb_solution draft 0 0 "SYN Решение draft")
P_PUB_NOOK=$(mkpost prj-pub-nook mb_project publish 0 0 "SYN Проект publish без допуска")
P_SAMPLE=$(mkpost prj-sample mb_project publish 1 1 "SYN Проект образец с допуском")
slug(){ $WP post get "$1" --field=post_name; }

start_server local
echo "== MB-07 ресурсы по настоящим ссылкам страницы =="
code / >/dev/null
CSS=$(grep -o "href=['\"][^'\"]*themes/maxbit/assets/style.css[^'\"]*['\"]" /tmp/mb_body | head -1 | sed "s/href=['\"]//;s/['\"]$//"); JS=$(grep -o "src=['\"][^'\"]*maxbit-core/assets/form.js[^'\"]*['\"]" /tmp/mb_body | head -1 | sed "s/src=['\"]//;s/['\"]$//")
c=$(curl -s -o /tmp/mb_css -w '%{http_code} %{content_type}' "$CSS"); check "style.css по ссылке из HTML → 200 text/css (${c})" "echo '$c' | grep -q '^200 text/css'"
check "style.css: контрольная сумма совпадает с исходником" "[ \"\$(sha256sum /tmp/mb_css | cut -c1-64)\" = \"\$(sha256sum $ROOT/src/themes/maxbit/assets/style.css | cut -c1-64)\" ]"
c=$(curl -s -o /tmp/mb_js -w '%{http_code} %{content_type}' "$JS"); check "form.js по ссылке из HTML → 200 javascript (${c})" "echo '$c' | grep -q '^200 application/javascript'"
check "form.js: контрольная сумма совпадает" "[ \"\$(sha256sum /tmp/mb_js | cut -c1-64)\" = \"\$(sha256sum $ROOT/src/plugins/maxbit-core/assets/form.js | cut -c1-64)\" ]"
c=$(code /wp-content/themes/maxbit/assets/brand/favicon.svg); check "favicon.svg → 200" "[ $c = 200 ]"
c=$(code "/wp-content/themes/maxbit/assets/../functions.php"); check "выход за assets (..) не отдаёт php" "[ $c != 200 ] || ! grep -q '<?php' /tmp/mb_body"
if [ -f "$ROOT/site/wp-content/db.php" ]; then c=$(code /wp-content/db.php); check "прямой запуск db.php → 404" "[ $c = 404 ]"; else echo "  INFO  db.php отсутствует (MySQL-установка) — проверка не нужна"; fi
c=$(code /wp-config-local.php); check "wp-config-local.php не читается (нет секретов в теле)" "! grep -q 'define' /tmp/mb_body"

echo "== A03 бренд =="
check "favicon.svg совпадает с reference" "[ \"\$(sha256sum $ROOT/src/themes/maxbit/assets/brand/favicon.svg | cut -c1-64)\" = \"\$(sha256sum $ROOT/reference/brand/favicon.svg | cut -c1-64)\" ]"
for f in MAXBIT_compact_light MAXBIT_compact_dark MAXBIT_full_light MAXBIT_full_dark; do
  check "логотип v2 $f байт-в-байт из MAXBIT_logo_FINAL_v2" "[ \"\$(sha256sum $ROOT/src/themes/maxbit/assets/brand/v2/$f.svg | cut -c1-64)\" = \"\$(sha256sum $ROOT/reference/brand/v2/$f.svg | cut -c1-64)\" ]"
done
check "старых файлов логотипа в теме нет" "! ls $ROOT/src/themes/maxbit/assets/brand/maxbit-*.svg >/dev/null 2>&1"
code / >/dev/null; check "шапка: компактный логотип v2 (светлый фон) + подпись текстом; подвал — версия для тёмного фона" "grep -q 'mb-logo__full' /tmp/mb_body && grep -q 'mb-logo__mark' /tmp/mb_body && grep -q 'X-blue-chevron' /tmp/mb_body && [ \$(grep -c 'id=\"X-layer-order\"' /tmp/mb_body) -ge 3 ]"
check "первый экран: иллюстрация WebP через picture, без lazy, с размерами" "grep -q 'mb-hero__art' /tmp/mb_body && grep -q 'hero-processes-1380.webp' /tmp/mb_body && grep -q 'width=\"1380\" height=\"700\"' /tmp/mb_body && ! grep -q 'hero-processes[^>]*loading=\"lazy\"' /tmp/mb_body"
check "H1 главной не изменился фильтром иллюстрации" "grep -q 'Наводим порядок в заказах и процессах производственных компаний</h1>' /tmp/mb_body"
check "MB-15: lang=\"ru-RU\"" "grep -q '<html lang=\"ru-RU\"' /tmp/mb_body"

echo "== A26 внутренний источник =="
check "sources_private не в site/" "! find $ROOT/site -iname '*AI_PRACTICUM_ORIGINAL*' | grep -q ."
check "в src нет цен исходника" "! grep -rq '350–450\|550–700\|30–60 тыс' $ROOT/src"

echo "== A04/A05 публичные страницы (аноним) =="
for u in / /resheniya/ /resheniya/zakaz-pod-kontrolem/ /tekhnologii/bitrix24-dlya-proizvodstva/ /kak-rabotaem/ /o-kompanii/ /praktika/ /praktika/kartochka-proizvodstvennogo-zakaza/ /proekty/; do
  c=$(code "$u"); check "$u → 200 и один H1" "[ $c = 200 ] && [ \$(grep -o '<h1' /tmp/mb_body | wc -l) = 1 ]"
done
c=$(code /tekhnologii/); check "/tekhnologii/ → 301" "[ $c = 301 ]"
c=$(code /net-takoy-stranicy/); check "404 отдаётся" "[ $c = 404 ]"
code / >/dev/null
check "главная: canonical, description, noindex (local), JSON-LD Organization" "grep -q 'rel=\"canonical\"' /tmp/mb_body && grep -q 'name=\"description\"' /tmp/mb_body && grep -q 'noindex' /tmp/mb_body && grep -q '\"Organization\"' /tmp/mb_body"
check "главная: нет Offer/Rating/SoftwareApplication" "! grep -q 'AggregateRating\|SoftwareApplication\|\"Offer\"' /tmp/mb_body"
check "главная: пометка LOCAL_TEST у формы" "grep -q 'Тестовая версия' /tmp/mb_body"
check "главная: нет ключей/секретов" "! grep -qi 'webhook\|api_key\|secret' /tmp/mb_body"
check "главная: нет oEmbed-обнаружения" "! grep -q 'oembed' /tmp/mb_body"
check "главная анонимно: нет имён кандидатов и пункта «Проекты»" "{ [ ! -f $ROOT/scripts/.private-names ] || ! grep -qFf $ROOT/scripts/.private-names /tmp/mb_body; } && ! grep -q '>Проекты<' /tmp/mb_body"

echo "== MB-01 матрица доступа: аноним × 5 синтетических состояний × каналы =="
anon_closed(){ # $1 id $2 label — все каналы должны быть закрыты
  local id=$1 s; s=$(slug $id)
  c=$(code "/resheniya/$s/"); [ $c = 404 ] || c=$(code "/proekty/$s/")
  check "аноним $2: HTML по slug → 404" "[ $c = 404 ]"
  c=$(code "/?p=$id"); check "аноним $2: ?p=ID → 404" "[ $c = 404 ]"
  for t in mb_solution mb_project; do c=$(code "/wp-json/wp/v2/$t/$id"); check "аноним $2: REST $t/$id → 404" "[ $c = 404 ]"; done
  code "/wp-json/wp/v2/search?search=SYN&subtype=any" >/dev/null; check "аноним $2: REST-поиск (any) не содержит" "! grep -q \"\\\"id\\\":$id,\" /tmp/mb_body"
  code "/wp-json/wp/v2/search?search=SYN&subtype=mb_solution" >/dev/null; check "аноним $2: REST-поиск (subtype) не содержит" "! grep -q \"\\\"id\\\":$id,\" /tmp/mb_body"
  c=$(code "/wp-json/oembed/1.0/embed?url=$BASE/?p=$id"); check "аноним $2: oEmbed → не 200 с данными" "[ $c != 200 ] || ! grep -q 'title' /tmp/mb_body"
  code "/?s=SYN" >/dev/null; check "аноним $2: поиск HTML не содержит" "! grep -q \"$s\" /tmp/mb_body"
  code /feed/ >/dev/null; check "аноним $2: RSS не содержит" "! grep -q \"$s\" /tmp/mb_body"
  code /resheniya/ >/dev/null; code2=$(cat /tmp/mb_body); code /proekty/ >/dev/null; check "аноним $2: списки не содержат" "! echo \"\$code2\" | grep -q \"$s\" && ! grep -q \"$s\" /tmp/mb_body"
}
anon_closed $S_PUB_NOOK "publish+ok=0 (решение)"
anon_closed $S_DRAFT "draft (решение)"
anon_closed $P_PUB_NOOK "publish+ok=0 (проект)"
anon_closed $P_SAMPLE "publish+ok=1+образец (проект)"
s=$(slug $S_PUB_OK); c=$(code "/resheniya/$s/"); check "аноним publish+ok=1: HTML → 200" "[ $c = 200 ]"
c=$(code "/wp-json/wp/v2/mb_solution/$S_PUB_OK"); check "аноним publish+ok=1: REST → 200" "[ $c = 200 ]"
code /resheniya/ >/dev/null; check "аноним publish+ok=1: в списке решений" "grep -q \"$s\" /tmp/mb_body"
c=$(code /wp-json/wp/v2/mb_lead); check "REST заявки → 404" "[ $c = 404 ]"
c=$(code /wp-json/wp/v2/users); check "REST пользователи анонимно → 404" "[ $c = 404 ]"
c=$(curl -s -L -o /tmp/mb_body -w '%{http_code}' "$BASE/?author=1"); check "страница автора → 404 (после редиректа)" "[ $c = 404 ]"
c=$(curl -s -o /tmp/mb_body -w '%{http_code}' -X POST "$BASE/xmlrpc.php" -d '<methodCall><methodName>system.listMethods</methodName></methodCall>'); check "xmlrpc → 403" "[ $c = 403 ]"
c=$(code /wp-sitemap.xml); check "sitemap в local → 404" "[ $c = 404 ]"
code /robots.txt >/dev/null; check "robots local: Disallow /" "grep -q 'Disallow: /$' /tmp/mb_body"
echo "== снятие допуска: ранее открытое становится закрытым =="
$WP post meta set $S_PUB_OK _mb_public_ok 0 >/dev/null
c=$(code "/resheniya/$(slug $S_PUB_OK)/"); check "после снятия допуска: HTML → 404" "[ $c = 404 ]"
c=$(code "/wp-json/wp/v2/mb_solution/$S_PUB_OK"); check "после снятия допуска: REST → 404" "[ $c = 404 ]"
$WP post meta set $S_PUB_OK _mb_public_ok 1 >/dev/null

echo "== MB-02 вложения закрытого проекта: REST media и attachment-страница закрыты; физический файл — ограничение =="
php -r '$im=imagecreatetruecolor(8,8); imagepng($im,"/tmp/mb_syn.png");'
ATT=$($WP post list --post_type=attachment --post_status=any --post_parent=$P_PUB_NOOK --field=ID | head -1)
[ -n "$ATT" ] || ATT=$($WP media import /tmp/mb_syn.png --post_id=$P_PUB_NOOK --title="SYN вложение" --porcelain 2>/dev/null)
c=$(code "/wp-json/wp/v2/media/$ATT"); check "аноним: REST media вложения закрытого проекта → 404" "[ $c = 404 ]"
c=$(curl -s -L -o /tmp/mb_body -w '%{http_code}' "$BASE/?attachment_id=$ATT"); check "аноним: attachment-страница → 404" "[ $c = 404 ]"
code "/wp-json/wp/v2/media?parent=$P_PUB_NOOK" >/dev/null; check "аноним: REST media по parent не содержит вложения" "! grep -q \"\\\"id\\\":$ATT,\" /tmp/mb_body"
FURL=$($WP eval "echo wp_get_attachment_url($ATT);" | sed "s#http://localhost:8080#$BASE#")
c=$(curl -s -o /dev/null -w '%{http_code}' "$FURL"); echo "  INFO  физический файл uploads отдаётся веб-сервером напрямую: HTTP $c — известное ограничение (OPEN_ITEMS: закрытые файлы в медиатеку не загружать)"

echo "== роли: подписчик (без edit_posts) видит то же, что аноним; редактор и администратор — закрытое =="
login mb_subscriber "Subscriber-Pass-Local-1" /tmp/mb_c_sub
c=$(curl -s -b /tmp/mb_c_sub -o /tmp/mb_body -w '%{http_code}' "$BASE/resheniya/$(slug $S_PUB_NOOK)/"); check "подписчик: publish+ok=0 → 404" "[ $c = 404 ]"
c=$(curl -s -b /tmp/mb_c_sub -o /tmp/mb_body -w '%{http_code}' "$BASE/wp-json/wp/v2/mb_solution/$S_PUB_NOOK"); check "подписчик: REST закрытого → 404" "[ $c = 404 ]"
login mb_editor "Editor-Pass-Local-1" /tmp/mb_c_ed
c=$(curl -s -b /tmp/mb_c_ed -o /tmp/mb_body -w '%{http_code}' "$BASE/resheniya/$(slug $S_PUB_NOOK)/"); check "редактор: publish+ok=0 → 200 с плашкой «Закрытый просмотр»" "[ $c = 200 ] && grep -q 'Закрытый просмотр' /tmp/mb_body"
c=$(curl -s -b /tmp/mb_c_ed -o /tmp/mb_body -w '%{http_code}' "$BASE/?post_type=mb_solution&p=11&preview=true"); check "редактор: предпросмотр черновика ИИ-Практикума → 200" "[ $c = 200 ] && grep -q 'ИИ-проводник' /tmp/mb_body"
c=$(curl -s -b /tmp/mb_c_ed -o /tmp/mb_body -w '%{http_code}' "$BASE/wp-admin/post.php?post=11&action=edit"); check "редактор: открывает редактор ИИ-страницы (A06)" "[ $c = 200 ] && grep -q 'block-editor' /tmp/mb_body"
check "редактор: флажок допуска отсутствует в его редакторе (управляет только администратор)" "! grep -q 'name=\"mb_public_ok\"' /tmp/mb_body && grep -q 'Не допущено к публичному показу' /tmp/mb_body"
c=$(curl -s -b /tmp/mb_c_ed -o /tmp/mb_body -w '%{http_code}' "$BASE/wp-admin/edit.php?post_type=mb_lead"); check "редактор: список обращений недоступен" "[ $c != 200 ] || ! grep -q 'MB-' /tmp/mb_body"
login maxbit_admin "$ADMIN_PW" /tmp/mb_c_adm
c=$(curl -s -b /tmp/mb_c_adm -o /tmp/mb_body -w '%{http_code}' "$BASE/wp-admin/edit.php?post_type=mb_lead"); check "администратор: список обращений доступен" "[ $c = 200 ] && grep -q 'Обращения' /tmp/mb_body && ! grep -q 'Извините, вам не разрешено\|not allowed' /tmp/mb_body"
c=$(curl -s -b /tmp/mb_c_adm -o /tmp/mb_body -w '%{http_code}' "$BASE/"); check "v0.3: администратор видит служебные заметки (проекты без допуска), ИИ-блока на главной нет" "grep -q 'mb-editor-note' /tmp/mb_body && ! grep -q 'ii-praktikum' /tmp/mb_body"
code / >/dev/null; check "v0.3: аноним не видит служебных заметок и пустых разделов" "! grep -q 'mb-editor-note\|Служебная заметка' /tmp/mb_body"
code /o-kompanii/ >/dev/null; check "v0.3: «О компании» анонимно без пустых заголовков «Компетенции»/«Проекты»" "! grep -q 'Компетенции и подтверждения' /tmp/mb_body && grep -q 'Команда и руководство' /tmp/mb_body"
for u in /resheniya/integraciya-bitrix24-i-1c/ /osnovatel/ /praktika/chek-list-peredacha-zakaza-v-proizvodstvo/; do c=$(code "$u"); check "v0.3: $u → 200 и один H1" "[ $c = 200 ] && [ \$(grep -o '<h1' /tmp/mb_body | wc -l) = 1 ]"; done
c=$(code /wp-content/plugins/maxbit-core/assets/files/chek-list-peredacha-zakaza-v-proizvodstvo.pdf); check "v0.3: чек-лист PDF отдаётся свободно" "[ $c = 200 ]"
code / >/dev/null; check "v0.3: подпись бренда в шапке, старой подписи нет" "grep -q 'Ваша команда цифрового развития' /tmp/mb_body && ! grep -q 'Системы управления бизнесом' /tmp/mb_body"
check "v0.3: главная — H1 про порядок в заказах и процессах, условный пример помечен" "grep -q 'Наводим порядок в заказах' /tmp/mb_body && grep -q 'Условный пример' /tmp/mb_body && ! grep -q 'Инструменты — под задачу' /tmp/mb_body"
c=$(curl -s -b /tmp/mb_c_adm -o /tmp/mb_body -w '%{http_code}' "$BASE/proekty/obrazets-struktury-keysa/"); check "администратор: учебный образец → 200 и помечен" "[ $c = 200 ] && grep -q 'Учебный образец' /tmp/mb_body"

echo "== A21/A23/A25 ИИ-страница (редактор) =="
curl -s -b /tmp/mb_c_ed -o /tmp/mb_body "$BASE/?post_type=mb_solution&p=11&preview=true"
check "ИИ-страница: H1, проводник, примеры, FAQ, CTA, форма с темой ai_practicum" "grep -q 'ИИ-Практикум для производственных компаний' /tmp/mb_body && grep -q 'Примеры задач' /tmp/mb_body && grep -q 'Ответы на вопросы' /tmp/mb_body && grep -q 'Обсудить применение ИИ' /tmp/mb_body && grep -q 'value=\"ai_practicum\"' /tmp/mb_body"
check "ИИ-страница: нет цен/гарантий/сертификатов/набора" "! grep -q 'тыс. ₽\|сертифик\|без утечек\|бесплатн\|240 ч\|набор открыт\|места ограничены' /tmp/mb_body"
check "MB-14: кнопка шапки на ИИ-странице ведёт к форме этой страницы (#contact), не на главную" "grep -q 'mb-nav__cta\" href=\"#contact\"' /tmp/mb_body"
rm -f /tmp/mb_c_sub /tmp/mb_c_ed /tmp/mb_c_adm

echo "== A10/A11 форма по HTTP =="
$WP eval 'global $wpdb; $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE \"mb_ctr_%\" OR option_name LIKE \"mb_lock_%\"");' >/dev/null 2>&1
RUN=$(date +%s)
TOKEN=$(curl -s "$BASE/wp-json/maxbit/v1/lead/token" | php -r 'echo json_decode(stream_get_contents(STDIN))->token;')
post(){ curl -s -o /tmp/mb_body -w '%{http_code}' -H 'Content-Type: application/json' -X POST "$BASE/wp-json/maxbit/v1/lead" -d "$1"; }
c=$(post "{\"name\":\"Тест\",\"contact\":\"+7 (900) 000-00-01\",\"message\":\"синтетика $RUN\",\"topic\":\"general\",\"token\":\"$TOKEN\",\"client_key\":\"k-$RUN-1\",\"consent\":\"1\"}")
RID=$(php -r 'echo json_decode(file_get_contents("/tmp/mb_body"))->request_id ?? "";')
check "приём: 200 accepted, request_id=$RID" "[ $c = 200 ] && grep -q '\"accepted\"' /tmp/mb_body && [ -n \"$RID\" ]"
LID=$($WP post list --post_type=mb_lead --post_status=private --meta_key=_mb_request_id --meta_value="$RID" --field=ID)
check "приём: запись #$LID существует, контакт и имя сохранены" "[ -n \"$LID\" ] && [ \"\$($WP post meta get $LID _mb_contact)\" = '+7 (900) 000-00-01' ] && [ \"\$($WP post meta get $LID _mb_name)\" = 'Тест' ]"
c=$(post "{\"name\":\"Т\",\"contact\":\"x\",\"topic\":\"general\",\"token\":\"$TOKEN\"}"); check "валидация → 422 с полями name/contact" "[ $c = 422 ] && grep -q '\"name\"' /tmp/mb_body && grep -q '\"contact\"' /tmp/mb_body"
c=$(post "{\"name\":\"Тест\",\"contact\":\"t@example.test\",\"topic\":\"hack\",\"token\":\"$TOKEN\"}"); check "недопустимая тема → 422" "[ $c = 422 ] && grep -q '\"topic\"' /tmp/mb_body"
c=$(post "{\"name\":\"Тест\",\"contact\":\"a@example.test\",\"topic\":\"general\",\"token\":\"1000000000.00000000000000000000000000000000\"}"); check "поддельный токен → 422 token_invalid" "[ $c = 422 ] && grep -q token_invalid /tmp/mb_body"
FRESH=$($WP eval 'echo maxbit_lead_token(0);'); c=$(post "{\"name\":\"Тест\",\"contact\":\"a@example.test\",\"topic\":\"general\",\"token\":\"$FRESH\"}"); check "слишком быстрая отправка → too_fast" "grep -q too_fast /tmp/mb_body"
OLD=$($WP eval 'echo maxbit_lead_token(13*3600);'); c=$(post "{\"name\":\"Тест\",\"contact\":\"a@example.test\",\"topic\":\"ai_practicum\",\"token\":\"$OLD\"}")
NEWT=$(php -r 'echo json_decode(file_get_contents("/tmp/mb_body"))->new_token ?? "";'); check "истёкший токен → token_expired + новый токен" "grep -q token_expired /tmp/mb_body && [ -n \"$NEWT\" ]"
c=$(post "{\"name\":\"Тест ИИ\",\"contact\":\"ai-$RUN@example.test\",\"topic\":\"ai_practicum\",\"token\":\"$NEWT\",\"client_key\":\"k-$RUN-ai\",\"consent\":\"1\"}")
check "MB-14: немедленный повтор с новым токеном принят (не too_fast), тема ai_practicum сохранена" "[ $c = 200 ] && grep -q accepted /tmp/mb_body && [ \"\$($WP post list --post_type=mb_lead --post_status=private --meta_key=_mb_client_key --meta_value=k-$RUN-ai --field=ID | xargs -I{} $WP post meta get {} _mb_topic)\" = ai_practicum ]"
c=$(post "{\"name\":\"Бот\",\"contact\":\"bot@example.test\",\"topic\":\"general\",\"token\":\"$TOKEN\",\"website\":\"http://spam\"}"); check "honeypot → 422 spam" "[ $c = 422 ] && grep -q spam /tmp/mb_body"
c=$(post "{\"name\":\"Без согласия\",\"contact\":\"nc-$RUN@example.test\",\"topic\":\"general\",\"token\":\"$TOKEN\",\"client_key\":\"k-$RUN-nc\"}"); check "152-ФЗ: без согласия → 422 с полем consent" "[ $c = 422 ] && grep -q '\"consent\"' /tmp/mb_body"
check "152-ФЗ: у принятой заявки записаны согласие, версия и время" "[ \"\$($WP eval '\$p=get_posts(array(\"post_type\"=>\"mb_lead\",\"post_status\"=>\"private\",\"numberposts\"=>1,\"meta_key\"=>\"_mb_client_key\",\"meta_value\"=>\"k-'$RUN'-1\")); echo get_post_meta(\$p[0]->ID,\"_mb_consent\",true).\"|\".get_post_meta(\$p[0]->ID,\"_mb_consent_version\",true).\"|\".substr(get_post_meta(\$p[0]->ID,\"_mb_consent_at\",true),0,4);')\" = \"1|$(grep -o "MAXBIT_CONSENT_VERSION', '[^']*" $ROOT/src/plugins/maxbit-core/includes/leads.php | cut -d"'" -f3)|$(date -u +%Y)\" ]"
code / >/dev/null; check "форма: чекбокс согласия не отмечен по умолчанию и ссылается на согласие и политику" "grep -q 'name=\"consent\"' /tmp/mb_body && ! grep -q 'name=\"consent\"[^>]*checked' /tmp/mb_body && grep -q 'soglasie-na-obrabotku-personalnyh-dannyh' /tmp/mb_body && grep -q 'politika-obrabotki-personalnyh-dannyh' /tmp/mb_body"
code /politika-obrabotki-personalnyh-dannyh/ >/dev/null; check "страница политики ПДн → 200, реквизиты подставляются шорткодом" "grep -q 'Политика обработки персональных данных' /tmp/mb_body && grep -q 'mb-placeholder\|ИНН' /tmp/mb_body"
check "в журнале отладки нет контактов" "! grep -q 'example.test\|900) 000' $ROOT/logs/debug.log 2>/dev/null"

echo "== MB-13 безJS: серверный чек, подделка URL, сохранение полей при ошибке =="
$WP eval 'global $wpdb; $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE \"mb_ctr_%\"");' >/dev/null 2>&1
c=$(code "/kak-rabotaem/?mbr=ok&mbc=accepted&mbr=MB-NOT-SAVED"); check "подделанный query не показывает подтверждение" "! grep -q 'Обращение записано' /tmp/mb_body"
c=$(code "/kak-rabotaem/?mbr=000000000000000000000000"); check "несуществующий чек → без сообщения" "! grep -q 'mb-notice' /tmp/mb_body"
LOC=$(curl -s -o /dev/null -w '%{redirect_url}' -X POST "$BASE/wp-admin/admin-post.php" --data-urlencode "action=maxbit_lead" --data-urlencode "name=БезJS" --data-urlencode "contact=nojs-$RUN@example.test" --data-urlencode "message=задача без js" --data-urlencode "topic=general" --data-urlencode "consent=1" --data-urlencode "token=$TOKEN" --data-urlencode "redirect=/kak-rabotaem/#contact")
check "безJS успех: редирект с чеком, без PII в URL" "echo \"$LOC\" | grep -q 'mbr=[a-f0-9]\{24\}' && ! echo \"$LOC\" | grep -q 'nojs'"
c=$(curl -s -o /tmp/mb_body -w '%{http_code}' "$LOC"); check "безJS успех: страница показывает номер MB-… (проверенный результат)" "grep -q 'Обращение записано. Номер: MB-' /tmp/mb_body"
c=$(curl -s -o /tmp/mb_body -w '%{http_code}' "$LOC"); check "чек одноразовый: повторное открытие без сообщения и без новой записи" "! grep -q 'Обращение записано' /tmp/mb_body"
LOC=$(curl -s -o /dev/null -w '%{redirect_url}' -X POST "$BASE/wp-admin/admin-post.php" --data-urlencode "action=maxbit_lead" --data-urlencode "name=Ошибка" --data-urlencode "contact=x" --data-urlencode "message=текст остаётся" --data-urlencode "topic=general" --data-urlencode "token=$TOKEN" --data-urlencode "redirect=/kak-rabotaem/#contact")
curl -s -D /tmp/mb_hdr -o /tmp/mb_body "$LOC"; check "безJS ошибка: имя и текст сохранены в форме, ошибка у поля контакта" "grep -q 'value=\"Ошибка\"' /tmp/mb_body && grep -q 'текст остаётся' /tmp/mb_body && grep -q 'Укажите телефон' /tmp/mb_body"
check "безJS ошибка: ответ с чеком не кешируется" "grep -qi 'no-cache\|no-store' /tmp/mb_hdr"

echo "== A12 внешние сервисы =="
check "адаптер доставки выключен (статус disabled у обращений)" "$WP post list --post_type=mb_lead --post_status=private --meta_key=_mb_delivery_status --meta_value=disabled --format=count | grep -qv '^0$'"
check "в исходниках нет реальных вебхуков/счётчиков" "! grep -rqE 'bitrix24\.ru/rest/[0-9]+/[a-z0-9]{8,}|ym\([0-9]{5,}|googletagmanager' $ROOT/src"
stop_server

echo "== MB-09/MB-11 SEO по средам: production × blog_public =="
$WP option update blog_public 0 >/dev/null
start_server production
code / >/dev/null; check "production + blog_public=0: noindex остаётся" "grep -q 'noindex' /tmp/mb_body"
c=$(code /wp-sitemap.xml); check "production + blog_public=0: sitemap 404" "[ $c = 404 ]"
code /robots.txt >/dev/null; check "production + blog_public=0: robots Disallow /" "grep -q 'Disallow: /$' /tmp/mb_body"
stop_server
$WP option update blog_public 1 >/dev/null
start_server production
code / >/dev/null; check "production + blog_public=1: главная index,follow" "grep -q 'max-image-preview' /tmp/mb_body && ! grep -q 'noindex' /tmp/mb_body"
code "/resheniya/$(slug $S_PUB_NOOK)/" >/dev/null; c=$(code "/resheniya/$(slug $S_PUB_NOOK)/"); check "production: publish+ok=0 → 404" "[ $c = 404 ]"
c=$(code /wp-sitemap.xml); check "production + blog_public=1: sitemap index 200" "[ $c = 200 ]"
check "sitemap: нет провайдера users" "! grep -q 'users' /tmp/mb_body"
c=$(code /wp-sitemap-users-1.xml); check "sitemap авторов → 404" "[ $c = 404 ]"
code /wp-sitemap-posts-mb_solution-1.xml >/dev/null; check "sitemap решений: допущенное есть, недопущенное и черновик — нет" "grep -q \"$(slug $S_PUB_OK)\" /tmp/mb_body && ! grep -q \"$(slug $S_PUB_NOOK)\|$(slug $S_DRAFT)\" /tmp/mb_body"
c=$(code /wp-sitemap-posts-mb_project-1.xml); check "sitemap проектов: образец отсутствует" "[ $c = 404 ] || ! grep -q 'obrazets\|$(slug $P_SAMPLE)' /tmp/mb_body"
code /wp-sitemap-posts-page-1.xml >/dev/null; check "sitemap страниц: служебная «Технологии» (noindex) отсутствует" "! grep -q 'tekhnologii/<' /tmp/mb_body"
code /robots.txt >/dev/null; check "production robots: Sitemap: указан" "grep -q '^Sitemap:' /tmp/mb_body"
code "/praktika/page/2/" >/dev/null; c=$(code "/praktika/page/2/"); check "MB-10: несуществующая страница пагинации → 404" "[ $c = 404 ]"
code /praktika/ >/dev/null; check "MB-10: canonical первой страницы списка — сама страница" "grep -q 'rel=\"canonical\" href=\"[^\"]*/praktika/\"' /tmp/mb_body"
stop_server
$WP option update blog_public 0 >/dev/null
start_server local
code / >/dev/null; check "возврат в local: noindex" "grep -q 'noindex' /tmp/mb_body"
stop_server

echo
echo "ИТОГО: PASS=$PASS FAIL=$FAIL"
[ $FAIL = 0 ]
