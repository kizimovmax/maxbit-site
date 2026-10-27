#!/usr/bin/env bash
# Быстрая проверка 20 страниц v4 + закрытых маршрутов: код ответа, число H1, title, PHP-предупреждения.
BASE="${1:-http://127.0.0.1:8080}"
for u in / /resheniya/ /bitrix24/ /kak-rabotaem/ /o-kompanii/ /osnovatel/ /resheniya/zakaz-pod-kontrolem/ /tekhnologii/bitrix24-dlya-proizvodstva/ /resheniya/integraciya-bitrix24-i-1c/ /proekty/ /baza-znaniy/ /baza-znaniy/chek-list-peredacha-zakaza-v-proizvodstvo/ /baza-znaniy/kartochka-proizvodstvennogo-zakaza/ /proekty/gk-vpk/ /proekty/lentochka/ /proekty/paritet-alyans/ /proekty/avtospeccentr/ /proekty/cupol365/ /proekty/helper-med/ /proekty/ngsk/ /materialy/ /materialy/kp/ /materialy/kp/AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA/ /price/c8b105118e0b78aade81/; do
  c=$(curl -s -o /tmp/mb_smoke.html -w '%{http_code}' "$BASE$u")
  t=$(grep -o '<title>[^<]*' /tmp/mb_smoke.html | head -1 | cut -c8-95)
  w=$(grep -ciE "<b>(warning|fatal error|notice|deprecated)</b>" /tmp/mb_smoke.html)
  h=$(grep -o '<h1' /tmp/mb_smoke.html | wc -l)
  echo "$c h1=$h warn=$w $u | $t"
done
