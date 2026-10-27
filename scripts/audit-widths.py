#!/usr/bin/env python3
"""Проверка переполнения по ширине и базовых метрик на контрольных ширинах (локальный сайт)."""
import asyncio, os, sys
from playwright.async_api import async_playwright
BASE=os.environ.get('MAXBIT_BASE','http://127.0.0.1:8080')
PAGES=['/','/resheniya/','/resheniya/zakaz-pod-kontrolem/','/resheniya/integraciya-bitrix24-i-1c/','/tekhnologii/bitrix24-dlya-proizvodstva/','/kak-rabotaem/','/baza-znaniy/','/baza-znaniy/chek-list-peredacha-zakaza-v-proizvodstvo/','/baza-znaniy/kartochka-proizvodstvennogo-zakaza/','/o-kompanii/','/osnovatel/','/proekty/','/net-takoy/']
WIDTHS=[int(w) for w in (sys.argv[1:] or ['320','360','390','768','1024','1280','1440'])]
async def main():
    bad=0
    async with async_playwright() as p:
        b=await p.chromium.launch()
        for w in WIDTHS:
            ctx=await b.new_context(viewport={'width':w,'height':900 if w>600 else 800},is_mobile=w<600,has_touch=w<600)
            pg=await ctx.new_page()
            for u in PAGES:
                await pg.goto(BASE+u, wait_until='networkidle')
                r=await pg.evaluate('''(w)=>{const sw=document.documentElement.scrollWidth;const over=[...document.querySelectorAll("body *")].filter(e=>{const r=e.getBoundingClientRect();return r.right>w+1&&r.width>0}).slice(0,3).map(e=>e.tagName.toLowerCase()+"."+String(e.className).split(" ")[0]);const small=[...document.querySelectorAll("p,li,a,span,small,label")].filter(e=>{const s=getComputedStyle(e);return e.textContent.trim()&&parseFloat(s.fontSize)<11&&s.display!=="none"}).length;return {sw,over,small}}''', w)
                flag='' if r['sw']<=w and not r['over'] else '  <-- ПЕРЕПОЛНЕНИЕ'
                if flag: bad+=1
                print(f"{w:5} {u:55} scrollWidth={r['sw']:5} small<11px={r['small']:3}{flag} {r['over'] if flag else ''}")
            await ctx.close()
        await b.close()
    print('ПЕРЕПОЛНЕНИЙ:', bad)
asyncio.run(main())
