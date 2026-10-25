#!/usr/bin/env python3
"""Собирает SVG темы в CSS-переменные внутри assets/style.css между маркерами ICONS:BEGIN / ICONS:END.
- assets/icons/**/*.svg  → --i-<путь-с-дефисами>  (монохромные маски: цвет задаёт CSS через mask + background)
- assets/img/*.svg       → --img-<имя>            (цветные схемы и паттерны: background-image как есть)
Запускать после правки SVG. Логотипы (assets/brand) не трогает — они выводятся инлайном из файлов."""
import glob, os, re, sys

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
A = os.path.join(ROOT, 'src/themes/maxbit/assets')
CSS = os.path.join(A, 'style.css')

def enc(path):
    s = open(path, encoding='utf-8').read().strip().replace('"', "'")
    s = re.sub(r'<\?xml[^>]*\?>', '', s)
    s = re.sub(r'\s+', ' ', s).strip()
    return s.replace('<', '%3C').replace('>', '%3E').replace('#', '%23')

lines = []
for f in sorted(glob.glob(os.path.join(A, 'icons', '**', '*.svg'), recursive=True)):
    name = os.path.relpath(f, os.path.join(A, 'icons'))[:-4].replace('/', '-')
    lines.append('  --i-%s: url("data:image/svg+xml,%s");' % (name, enc(f)))
for f in sorted(glob.glob(os.path.join(A, 'img', '*.svg'))):
    name = os.path.basename(f)[:-4]
    lines.append('  --img-%s: url("data:image/svg+xml,%s");' % (name, enc(f)))
block = '/* ICONS:BEGIN */\n' + '\n'.join(lines) + '\n/* ICONS:END */'
css = open(CSS, encoding='utf-8').read()
new = re.sub(r'/\* ICONS:BEGIN \*/.*?/\* ICONS:END \*/', lambda m: block, css, flags=re.S)
if new == css and block not in css:
    sys.exit('маркеры ICONS не найдены в style.css')
open(CSS, 'w', encoding='utf-8').write(new)
print('переменных:', len(lines), '; style.css:', len(new.encode()), 'байт')
