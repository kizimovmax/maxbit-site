# CHANGES — BUILD2 → 2.1 (полный diff: reports/diff/*.diff)

Изменённые (MOD) / новые (NEW):
- MOD src/plugins/maxbit-core/includes/leads.php — целостность записи с откатом, атомарные блокировка/счётчик, ключ+отпечаток, перевыдача токена «возрастом», очистка оборванных черновиков (MB-03, MB-04, MB-14)
- MOD src/plugins/maxbit-core/includes/delivery.php — строгий контракт подтверждения (test/bitrix24), терминальный delivered, блокировка, снятие повторов (MB-05, MB-06)
- MOD src/plugins/maxbit-core/includes/access.php — вторичные запросы, REST-поиск, oEmbed, REST/attachment для вложений, users-провайдер sitemap, sitemap только при blog_public, attachment 404 до redirect_canonical (MB-01, MB-02, MB-09, MB-11)
- MOD src/plugins/maxbit-core/includes/seo.php — robots по совокупности условий, canonical с пагинацией, title с номером страницы, lang=ru-RU (MB-09, MB-10, MB-15)
- MOD src/plugins/maxbit-core/includes/form.php — нейтральный рендер карточек, серверный одноразовый чек для безJS, сообщения по режиму, aged-токен, статус busy (MB-12, MB-13, MB-14)
- MOD src/plugins/maxbit-core/includes/meta.php — флаг допуска только администратору (MB-16/A27)
- MOD src/plugins/maxbit-core/assets/form.js — AbortController/тайм-аут, восстановление токена, закрытие меню (MB-14)
- MOD src/themes/maxbit/functions.php — CTA по контексту, фильтр maxbit_render_card (MB-12, MB-14)
- MOD src/themes/maxbit/header.php — wordmark на узких экранах, noscript-меню (MB-14, MB-15)
- MOD src/themes/maxbit/assets/style.css — сетка широких секций, поля, переключение логотипа, пагинация (MB-10, MB-15)
- MOD src/themes/maxbit/{archive-mb_solution,archive-mb_project,home,index}.php — пагинация (MB-10)
- MOD scripts/router.php — статика через симлинки с MIME, запрет php вне точек входа (MB-07)
- MOD scripts/setup.sh — соли только в локальный файл 600, проверка неизменности wp-config, симлинки/db.php, настройки только при первой установке (MB-17, MB-08)
- MOD scripts/seed.php — без удаления по slug; образцы ядра в корзину только при первом наполнении; настройки владельца не сбрасываются (MB-08)
- MOD scripts/test-receiver.php — отрицательные ответы, received=request_id (MB-05)
- MOD scripts/check.sh, check-delivery.sh, check-content.sh; NEW scripts/check-leads.sh, check-fresh.sh, snapshots.sh, build-demo.py (MB-16)
- MOD site/wp-config.php — WP_HOME по localhost-порту, переопределения из окружения для проверок, отказ старта без солей вне local (MB-17)
Удалённых файлов нет.

# CHANGES — 2.1 → 2.2 (08.10.2026)
- MOD src/plugins/maxbit-core/maxbit-core.php — версия 0.2.0; MAXBIT_ENV из настройки (`staging` по умолчанию, `production` только константой), `MAXBIT_ENV_FROM_CONFIG`, `maxbit_env()`, отключение почты вне production (`pre_wp_mail`), подключение includes/seed.php
- NEW src/plugins/maxbit-core/includes/seed.php — наполнение внутри модуля: функция `maxbit_seed_run()`, страница «MAXBIT → Наполнение», команда `wp maxbit seed`; кандидаты без имён клиентов; функции с префиксами `maxbit_seed_*`/`maxbit_sb_*`
- MOD src/plugins/maxbit-core/includes/settings.php — блок «Среда стенда» (staging/local, почта), расширенная таблица состояния (индексирование, пермалинки, версии, наполнение)
- MOD src/plugins/maxbit-core/includes/leads.php — XFF за локальным/частным прокси (nginx→Apache) и `MAXBIT_TRUSTED_PROXY`
- MOD src/themes/maxbit/functions.php, header.php, single.php, single-mb_project.php, style.css — версия 0.2.0; обёртки `maxbit_theme_form/_project_fields/_can_preview/_env` вместо одноимённых заглушек (исправление Cannot redeclare); уведомление администратору при неактивном модуле; плашка «Тестовый стенд (staging)»
- MOD scripts/seed.php — тонкая обёртка над `maxbit_seed_run()`
- MOD scripts/check.sh — проверка db.php только при его наличии (MySQL-установка)
- NEW scripts/build-dist.sh — сборка ZIP и архива исходников с проверкой содержимого
- NEW README_DEPLOY_WEBHOST1.md
