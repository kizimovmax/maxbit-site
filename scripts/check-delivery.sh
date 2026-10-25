#!/usr/bin/env bash
# Проверка адаптера доставки на локальном приёмнике. Реальный Битрикс24 не участвует.
# Уровень: интеграционный — настоящий WordPress (WP-CLI), настоящие HTTP-вызовы к приёмнику, настоящий WP-Cron.
set -uo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
WP="php $ROOT/vendor/wp-cli.phar --path=$ROOT/site --allow-root"
LOG="$ROOT/reports/test-logs/check-delivery_$(date -u +%Y%m%dT%H%M%SZ).log"; mkdir -p "$(dirname "$LOG")"
exec > >(tee "$LOG") 2>&1
echo "# check-delivery $(date -u +%FT%TZ) PHP=$(php -r 'echo PHP_VERSION;') WP=$($WP core version) receiver=127.0.0.1:8099"
php -S 127.0.0.1:8099 "$ROOT/scripts/test-receiver.php" >/dev/null 2>&1 & R=$!
trap 'kill $R 2>/dev/null' EXIT
sleep 1
: > "$ROOT/logs/receiver.log"
$WP eval 'global $wpdb; $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE \"mb_ctr_%\" OR option_name LIKE \"mb_lock_%\"");' >/dev/null 2>&1
PASS=0; FAIL=0
ok(){ PASS=$((PASS+1)); echo "PASS  $1"; }; fail(){ FAIL=$((FAIL+1)); echo "FAIL  $1"; }
RUN=$(date +%s)

mklead() { # $1 mode → echo post_id
  MAXBIT_DELIVERY_TEST_URL="http://127.0.0.1:8099/?mode=$1" $WP eval "
    global \$wpdb; \$wpdb->query(\"DELETE FROM {\$wpdb->options} WHERE option_name LIKE 'mb_ctr_rl_%'\"); // тест: сброс лимита CLI-адреса
    \$r = maxbit_lead_process(array('name'=>'Доставка $1','contact'=>'d$1-$RUN-'.mt_rand().'@example.test','message'=>'m$1 $RUN '.mt_rand(),'topic'=>'general','consent'=>'1','token'=>maxbit_lead_token_check_bypass(),'client_key'=>'k-deliv-$1-$RUN-'.mt_rand()));
    if (empty(\$r['ok'])) { echo 'ERR:'.\$r['code']; exit; }
    \$ids = get_posts(array('post_type'=>'mb_lead','post_status'=>'private','numberposts'=>1,'fields'=>'ids','meta_key'=>'_mb_request_id','meta_value'=>\$r['request_id']));
    echo \$ids[0];" 2>/dev/null
}
st(){ $WP post meta get "$1" _mb_delivery_status 2>/dev/null; }
at(){ $WP post meta get "$1" _mb_delivery_attempts 2>/dev/null; }
er(){ $WP post meta get "$1" _mb_delivery_last_error 2>/dev/null; }
rid(){ $WP post meta get "$1" _mb_request_id 2>/dev/null; }

echo "== Матрица ответов приёмника (только строгий контракт даёт delivered) =="
for m in ok:delivered fail:retry slow:retry badjson:retry okfalse:retry resultfalse:retry resultempty:retry errorresult:retry wrongid:retry; do
  mode=${m%%:*}; exp=${m##*:}; ID=$(mklead $mode); S=$(st "$ID")
  if [ "$S" = "$exp" ] && [ "$(at "$ID")" = "1" ]; then ok "$mode → $S (1 попытка), запись #$ID сохранена, ошибка='$(er "$ID")'"; else fail "$mode → $S попыток $(at "$ID") (ожидалось $exp/1), #$ID"; fi
done
echo "== Протокол bitrix24 (контракт ответа REST Битрикс24) =="
for m in b24ok:'' b24err:not_confirmed b24zero:not_confirmed; do
  mode=${m%%:*}; exp=${m##*:}
  RES=$($WP eval "echo maxbit_delivery_confirm(200, file_get_contents('http://127.0.0.1:8099/?mode=$mode'), 'MB-X', 'bitrix24');" 2>/dev/null)
  [ "$RES" = "$exp" ] && ok "bitrix24 $mode → '$RES'" || fail "bitrix24 $mode → '$RES' (ожидалось '$exp')"
done

echo "== Терминальный delivered: повтор не делает сетевой вызов и не меняет статус =="
ID=$(mklead ok); N1=$(grep -c "request_id=$(rid $ID)" "$ROOT/logs/receiver.log")
MAXBIT_DELIVERY_TEST_URL="http://127.0.0.1:8099/?mode=fail" $WP eval "maxbit_deliver_lead($ID); maxbit_deliver_lead($ID); maxbit_deliver_lead($ID); maxbit_deliver_lead($ID);" >/dev/null 2>&1
N2=$(grep -c "request_id=$(rid $ID)" "$ROOT/logs/receiver.log")
[ "$(st $ID)" = delivered ] && [ "$(at $ID)" = 1 ] && [ "$N1" = "$N2" ] && ok "после успеха 4 повтора: delivered, попыток 1, сетевых вызовов не добавилось ($N1)" || fail "терминальность: статус $(st $ID), попыток $(at $ID), вызовов $N1→$N2"
[ "$($WP eval "echo wp_next_scheduled('maxbit_delivery_retry', array($ID)) ? 'yes' : 'no';" 2>/dev/null)" = no ] && ok "ожидающих повторов для доставленной заявки #$ID нет" || fail "остались задания повтора для #$ID"

echo "== Сбой → восстановление приёмника → настоящий WP-Cron → delivered =="
ID=$(mklead fail)
[ "$(st $ID)" = retry ] && ok "первая попытка: retry, ошибка '$(er $ID)'" || fail "ожидался retry"
[ "$($WP eval "echo wp_next_scheduled('maxbit_delivery_retry', array($ID)) ? 'yes' : 'no';" 2>/dev/null)" = yes ] && ok "в WP-Cron запланировано задание повтора для #$ID" || fail "нет задания повтора в cron"
MAXBIT_DELIVERY_TEST_URL="http://127.0.0.1:8099/?mode=ok" $WP cron event run maxbit_delivery_retry >/dev/null 2>&1
[ "$(st $ID)" = delivered ] && [ "$(at $ID)" = 2 ] && ok "cron-повтор доставил: delivered, попыток 2" || fail "после cron: $(st $ID), попыток $(at $ID)"
CNT=$(grep -c "request_id=$(rid $ID)" "$ROOT/logs/receiver.log")
[ "$CNT" = 2 ] && ok "приёмник получил один и тот же request_id $(rid $ID) во всех попытках ($CNT)" || fail "request_id в журнале приёмника: $CNT записей"

echo "== Три неудачи → failed, повторы сняты, авто-переотправки нет =="
ID=$(mklead fail); MAXBIT_DELIVERY_TEST_URL="http://127.0.0.1:8099/?mode=fail" $WP cron event run maxbit_delivery_retry >/dev/null 2>&1; MAXBIT_DELIVERY_TEST_URL="http://127.0.0.1:8099/?mode=fail" $WP eval "maxbit_deliver_lead($ID);" >/dev/null 2>&1
[ "$(st $ID)" = failed ] && [ "$(at $ID)" = 3 ] && ok "после 3 попыток failed; запись #$ID сохранена" || fail "ожидался failed/3, получено $(st $ID)/$(at $ID)"
MAXBIT_DELIVERY_TEST_URL="http://127.0.0.1:8099/?mode=ok" $WP eval "maxbit_deliver_lead($ID);" >/dev/null 2>&1
[ "$(st $ID)" = failed ] && ok "failed не переотправляется автоматически (ручной пересмотр — отдельная процедура)" || fail "failed изменился"

echo "== Два параллельных обработчика одной заявки → один сетевой вызов =="
ID=$(mklead fail); : > "$ROOT/logs/receiver.log"
( MAXBIT_DELIVERY_TEST_URL="http://127.0.0.1:8099/?mode=slow" $WP eval "maxbit_deliver_lead($ID);" >/dev/null 2>&1 & sleep 1; MAXBIT_DELIVERY_TEST_URL="http://127.0.0.1:8099/?mode=ok" $WP eval "maxbit_deliver_lead($ID);" >/dev/null 2>&1; wait )
CNT=$(grep -c "request_id=$(rid $ID)" "$ROOT/logs/receiver.log")
[ "$CNT" = 1 ] && ok "второй обработчик отклонён блокировкой: 1 сетевой вызов" || fail "сетевых вызовов: $CNT"

echo "ИТОГО: PASS=$PASS FAIL=$FAIL"; [ $FAIL = 0 ]
