#!/usr/bin/env bash
# Целостность приёма обращений. Уровень: интеграционный (настоящий WordPress через WP-CLI + HTTP к локальному серверу).
set -uo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
WP="php $ROOT/vendor/wp-cli.phar --path=$ROOT/site --allow-root"
PORT="${MAXBIT_CHECK_PORT:-8089}"; BASE="http://127.0.0.1:$PORT"
LOG="$ROOT/reports/test-logs/check-leads_$(date -u +%Y%m%dT%H%M%SZ).log"; mkdir -p "$(dirname "$LOG")"
exec > >(tee "$LOG") 2>&1
echo "# check-leads $(date -u +%FT%TZ) PHP=$(php -r 'echo PHP_VERSION;') WP=$($WP core version) workers=8 (настоящая конкуренция: PHP_CLI_SERVER_WORKERS)"
PHP_CLI_SERVER_WORKERS=8 php -S "127.0.0.1:$PORT" -t "$ROOT/site" "$ROOT/scripts/router.php" >/tmp/mb_srv_leads.log 2>&1 & SRV=$!
trap 'kill $SRV 2>/dev/null' EXIT
sleep 1.5
PASS=0; FAIL=0
ok(){ PASS=$((PASS+1)); echo "PASS  $1"; }; fail(){ FAIL=$((FAIL+1)); echo "FAIL  $1"; }
RUN=$(date +%s)
reset_rl(){ $WP eval 'global $wpdb; $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE \"mb_ctr_%\" OR option_name LIKE \"mb_lock_%\"");' >/dev/null 2>&1; }
count_leads(){ $WP post list --post_type=mb_lead --post_status=private --format=count 2>/dev/null; }
count_drafts(){ $WP post list --post_type=mb_lead --post_status=draft --format=count 2>/dev/null; }

echo "== MB-03: инъекция отказа сохранения каждого обязательного поля =="
for field in _mb_request_id _mb_name _mb_contact _mb_topic _mb_fingerprint POST; do
  reset_rl; BEFORE=$(count_leads); DB=$(count_drafts)
  RES=$($WP eval "
    if ('$field' === 'POST') { add_filter('wp_insert_post_empty_content', '__return_true'); }
    else { add_filter('maxbit_lead_meta_value', function(\$v, \$k){ return \$k === '$field' ? false : \$v; }, 10, 2); }
    \$r = maxbit_lead_process(array('name'=>'Отказ $field','contact'=>'f-$RUN-$field@example.test','message'=>'x','topic'=>'general','consent'=>'1','token'=>maxbit_lead_token_check_bypass(),'client_key'=>'k-fail-$RUN-$field'));
    echo \$r['code'];" 2>/dev/null)
  AFTER=$(count_leads); DA=$(count_drafts)
  [ "$RES" = storage ] && [ "$BEFORE" = "$AFTER" ] && [ "$DB" = "$DA" ] && ok "отказ на $field → code=storage, записей не прибавилось ($BEFORE), незавершённых черновиков нет" || fail "отказ на $field → code=$RES, записей $BEFORE→$AFTER, черновиков $DB→$DA"
done
reset_rl
RES=$($WP eval "\$r = maxbit_lead_process(array('name'=>'После восстановления','contact'=>'rec-$RUN@example.test','topic'=>'general','consent'=>'1','token'=>maxbit_lead_token_check_bypass(),'client_key'=>'k-rec-$RUN')); echo \$r['code'];" 2>/dev/null)
[ "$RES" = accepted ] && ok "после «восстановления» хранилища тот же посетитель принимается (accepted)" || fail "после восстановления: $RES"
echo "== событие maxbit_lead_stored не срабатывает при отказе =="
reset_rl
RES=$($WP eval "
  \$fired = 0; add_action('maxbit_lead_stored', function() use (&\$fired){ \$fired++; });
  add_filter('maxbit_lead_meta_value', function(\$v, \$k){ return \$k === '_mb_contact' ? false : \$v; }, 10, 2);
  maxbit_lead_process(array('name'=>'Событие','contact'=>'ev-$RUN@example.test','topic'=>'general','consent'=>'1','token'=>maxbit_lead_token_check_bypass()));
  echo \$fired;" 2>/dev/null)
[ "$RES" = 0 ] && ok "событие не вызвано при неполной записи" || fail "событие вызвано $RES раз"

echo "== MB-04: тот же client_key, другое содержимое → новая запись, ничего не потеряно =="
reset_rl
R1=$($WP eval "\$r=maxbit_lead_process(array('name'=>'Ключ','contact'=>'key-$RUN@example.test','message'=>'первая задача','topic'=>'general','consent'=>'1','token'=>maxbit_lead_token_check_bypass(),'client_key'=>'k-same-$RUN')); echo \$r['code'].' '.\$r['request_id'];" 2>/dev/null)
R2=$($WP eval "\$r=maxbit_lead_process(array('name'=>'Ключ','contact'=>'key-$RUN@example.test','message'=>'ИЗМЕНЁННАЯ задача','topic'=>'ai_practicum','consent'=>'1','token'=>maxbit_lead_token_check_bypass(),'client_key'=>'k-same-$RUN')); echo \$r['code'].' '.\$r['request_id'];" 2>/dev/null)
R3=$($WP eval "\$r=maxbit_lead_process(array('name'=>'Ключ','contact'=>'key-$RUN@example.test','message'=>'первая задача','topic'=>'general','consent'=>'1','token'=>maxbit_lead_token_check_bypass(),'client_key'=>'k-same-$RUN')); echo \$r['code'].' '.\$r['request_id'];" 2>/dev/null)
ID2=$($WP post list --post_type=mb_lead --post_status=private --meta_key=_mb_request_id --meta_value="${R2#* }" --field=ID 2>/dev/null)
[ "${R1%% *}" = accepted ] && [ "${R2%% *}" = accepted ] && [ "${R1#* }" != "${R2#* }" ] && [ "$($WP post meta get $ID2 _mb_topic)" = ai_practicum ] && [ "$($WP post meta get $ID2 _mb_message)" = "ИЗМЕНЁННАЯ задача" ] && ok "изменённое содержимое сохранено отдельной записью с темой ai_practicum (#$ID2)" || fail "R1=$R1 R2=$R2 topic=$($WP post meta get $ID2 _mb_topic 2>/dev/null)"
[ "${R3%% *}" = duplicate ] && [ "${R3#* }" = "${R1#* }" ] && ok "повтор исходного содержимого с тем же ключом → duplicate, прежний request_id" || fail "R3=$R3 (ожидался duplicate ${R1#* })"

echo "== MB-04: 15 действительно параллельных HTTP-повторов одной заявки → одна запись =="
reset_rl
TOKEN=$(curl -s "$BASE/wp-json/maxbit/v1/lead/token" | php -r 'echo json_decode(stream_get_contents(STDIN))->token;')
BEFORE=$(count_leads)
seq 1 15 | xargs -P 15 -I{} curl -s -o /tmp/mb_par_{}.json -H 'Content-Type: application/json' -X POST "$BASE/wp-json/maxbit/v1/lead" -d "{\"name\":\"Параллель\",\"contact\":\"par-$RUN@example.test\",\"message\":\"одно и то же\",\"topic\":\"general\",\"token\":\"$TOKEN\",\"consent\":\"1\",\"client_key\":\"k-par-$RUN\"}"
AFTER=$(count_leads)
IDS=$(cat /tmp/mb_par_*.json | php -r '$ids=[];$codes=[];while($l=fgets(STDIN)){foreach(explode("}{",$l) as $c){}} $all=file_get_contents("php://stdin");' 2>/dev/null; for f in /tmp/mb_par_*.json; do php -r '$j=json_decode(file_get_contents($argv[1]),true); echo ($j["request_id"]??"none"),":",($j["code"]??"err"),"\n";' "$f"; done)
UNIQ=$(echo "$IDS" | cut -d: -f1 | grep -v none | sort -u | wc -l); ACC=$(echo "$IDS" | grep -c ':accepted'); DUP=$(echo "$IDS" | grep -c ':duplicate'); BUSY=$(echo "$IDS" | grep -c ':busy')
[ $((AFTER-BEFORE)) = 1 ] && [ "$UNIQ" = 1 ] && [ "$ACC" = 1 ] && ok "15 параллельных повторов: +1 запись, один request_id, accepted=1 duplicate=$DUP busy=$BUSY" || fail "параллель: записей +$((AFTER-BEFORE)), уникальных id $UNIQ, accepted=$ACC duplicate=$DUP busy=$BUSY"
rm -f /tmp/mb_par_*.json
echo "== разные законные заявки параллельно сохраняются все =="
reset_rl; BEFORE=$(count_leads)
seq 1 5 | xargs -P 5 -I{} curl -s -o /dev/null -H 'Content-Type: application/json' -X POST "$BASE/wp-json/maxbit/v1/lead" -d "{\"name\":\"Разные {}\",\"contact\":\"diff{}-$RUN@example.test\",\"topic\":\"general\",\"token\":\"$TOKEN\",\"consent\":\"1\",\"client_key\":\"k-diff-{}-$RUN\"}"
AFTER=$(count_leads); [ $((AFTER-BEFORE)) = 5 ] && ok "5 разных параллельных заявок → +5 записей" || fail "разные заявки: +$((AFTER-BEFORE))"

echo "== лимит частоты под параллельной нагрузкой (атомарный счётчик) =="
reset_rl
seq 1 12 | xargs -P 12 -I{} curl -s -o /tmp/mb_rl_{}.txt -w '%{http_code}\n' -H 'Content-Type: application/json' -X POST "$BASE/wp-json/maxbit/v1/lead" -d "{\"name\":\"Лимит {}\",\"contact\":\"rl{}-$RUN@example.test\",\"topic\":\"general\",\"token\":\"$TOKEN\",\"consent\":\"1\",\"client_key\":\"k-rl-{}-$RUN\"}" > /tmp/mb_rl_codes.txt
N429=$(grep -c 429 /tmp/mb_rl_codes.txt); N200=$(grep -c 200 /tmp/mb_rl_codes.txt)
[ "$N200" = 5 ] && [ "$N429" = 7 ] && ok "12 параллельных → ровно 5 принято, 7 отклонено 429" || fail "лимит: 200=$N200 429=$N429"
rm -f /tmp/mb_rl_*.txt

echo "ИТОГО: PASS=$PASS FAIL=$FAIL"; [ $FAIL = 0 ]
