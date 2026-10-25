#!/usr/bin/env bash
# Статические HTML-снимки страниц работающего WordPress (curl). Это НЕ снимки экрана: вёрстку они не доказывают.
set -uo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"; OUT="$ROOT/reports/html-snapshots"; PORT="${MAXBIT_CHECK_PORT:-8089}"; B="http://127.0.0.1:$PORT"
mkdir -p "$OUT/assets"; cp "$ROOT/src/themes/maxbit/assets/style.css" "$ROOT/src/plugins/maxbit-core/assets/form.js" "$OUT/assets/"
php -S "127.0.0.1:$PORT" -t "$ROOT/site" "$ROOT/scripts/router.php" >/dev/null 2>&1 & SRV=$!; trap 'kill $SRV 2>/dev/null' EXIT; sleep 1.5
PW=$(cat "$ROOT/logs/admin-password.local.txt"); J=/tmp/mb_snap_c
curl -s -c $J -b $J -o /dev/null "$B/wp-login.php"; curl -s -c $J -b $J -o /dev/null -X POST "$B/wp-login.php" --data-urlencode "log=maxbit_admin" --data-urlencode "pwd=$PW" --data-urlencode "wp-submit=Log In" --data-urlencode "testcookie=1"
snap(){ curl -s $3 "$B$1" | sed -e "s#$B/wp-content/themes/maxbit/assets/style.css?ver=[0-9.]*#assets/style.css#" -e "s#$B/wp-content/plugins/maxbit-core/assets/form.js?ver=[0-9.]*#assets/form.js#" -e "s#href=\"$B/#href=\"./#g" > "$OUT/$2.html"; }
snap / 01-glavnaya ""; snap /resheniya/ 02-resheniya ""; snap /resheniya/zakaz-pod-kontrolem/ 03-zakaz-pod-kontrolem ""; snap /tekhnologii/bitrix24-dlya-proizvodstva/ 04-bitrix24 ""; snap /kak-rabotaem/ 05-kak-rabotaem ""; snap /o-kompanii/ 06-o-kompanii ""; snap /praktika/ 07-praktika ""; snap /praktika/kartochka-proizvodstvennogo-zakaza/ 08-statya ""; snap /proekty/ 09-proekty-pusto ""; snap /net-takoy/ 10-404 ""
snap "/?post_type=mb_solution&p=11&preview=true" 11-ii-praktikum-AUTH-PREVIEW "-b $J"; snap /proekty/obrazets-struktury-keysa/ 12-obrazets-keysa-AUTH-PREVIEW "-b $J"; snap / 13-glavnaya-AUTH-PREVIEW "-b $J"
rm -f $J; ls "$OUT" | wc -l
