# Установочные пакеты MAXBIT

Готовые ZIP темы `maxbit` и модуля `maxbit-core`. Ставятся на dev одним запросом через точку обновления модуля
(`POST /wp-json/maxbit/v1/update/fetch {url, sha256, type}`) по прямой ссылке вида
`https://raw.githubusercontent.com/kizimovmax/maxbit-site/main/packages/<файл>.zip`.
Контрольные суммы — в `SHA256SUMS.txt`. Предыдущие версии хранятся для отката.

## 26.10.2026 — «Основа 1.0»
- `maxbit-core-0.4.1.zip` — модуль: таксономии, самодиагностика и материалы по ссылке, скрытая страница цен, cookie-согласие, og-генерация, настройки ботов/каналов. Ставить первым.
- `maxbit-theme-0.6.0.zip` — тема «Графит» (влиты правки ТЗ 25.10). После обоих — `update/seed` и правка главной `scripts/graphite-0.6.0-home.js`. Порядок — reports/graphite-0.6.0/REPORT_OSNOVA_1.0.md.
