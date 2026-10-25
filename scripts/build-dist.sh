#!/usr/bin/env bash
# Сборка установочных ZIP темы и модуля + полного архива исходников. Проверяет отсутствие секретов и лишних файлов.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"; cd "$ROOT"
TV=$(grep -m1 "^Version:" src/themes/maxbit/style.css | awk '{print $2}'); PV=$(grep -m1 "Version:" src/plugins/maxbit-core/maxbit-core.php | awk '{print $3}')
mkdir -p dist; rm -f dist/*.zip dist/SHA256SUMS.txt
( cd src/themes  && zip -qr "../../dist/maxbit-theme-$TV.zip" maxbit -x "*/.DS_Store" "*/Thumbs.db" )
( cd src/plugins && zip -qr "../../dist/maxbit-core-$PV.zip" maxbit-core -x "*/.DS_Store" "*/Thumbs.db" )
# Полные исходники для проверки: без базы, секретов, uploads, ядра WordPress и внутренних источников.
zip -qr "dist/MAXBIT_site_src.zip" README.md README_RUN.md README_DEPLOY_WEBHOST1.md REVIEW_REPORT.md REVIEW_RESPONSE_MB.md OPEN_ITEMS.md CURRENT_STATE.md .gitignore src scripts docs reference reports site/wp-config.php dist/SHA256SUMS.txt \
  -x "reports/html-snapshots/assets/*" "reports/visual-02/*" "reports/screenshots/*" "reports/shots-local/*" "*/.git/*" "*.sqlite" "*.log" 2>/dev/null || true
# Проверки содержимого установочных ZIP
for z in dist/maxbit-theme-*.zip dist/maxbit-core-*.zip; do
  if unzip -l "$z" | awk '{print $4}' | grep -qiE "\.git|\.log$|\.sqlite|password|wp-config|sources_private|AI_PRACTICUM|docs/|reports/|\.env$|node_modules"; then echo "ОШИБКА: лишние файлы в $z"; exit 1; fi
  if unzip -p "$z" | grep -qE "[0-9a-f]{64}|webhook\.php|bitrix24\.ru/rest/[0-9]+/[a-z0-9]{8,}|ym\([0-9]{5,}"; then echo "ОШИБКА: подозрительное содержимое в $z"; exit 1; fi
  # Имена клиентов — из локального файла scripts/.private-names (не в репозитории); без файла проверка пропускается
  if [ -f scripts/.private-names ] && unzip -p "$z" | grep -qFf scripts/.private-names; then echo "ОШИБКА: имя клиента внутри $z"; exit 1; fi
done
( cd dist && sha256sum *.zip > SHA256SUMS.txt )
# пересобрать src-архив, чтобы включить SHA256SUMS
zip -q -u dist/MAXBIT_site_src.zip dist/SHA256SUMS.txt >/dev/null 2>&1 || true
echo "Собрано:"; ls -la dist; cat dist/SHA256SUMS.txt
