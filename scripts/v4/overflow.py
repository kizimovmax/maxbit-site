"""Горизонтальная прокрутка страницы на ширинах 320/390/736/1024/1440: scrollWidth документа > ширины окна."""
import asyncio, sys
from playwright.async_api import async_playwright
BASE = sys.argv[1]
PATHS = sys.argv[2:]
async def main():
    async with async_playwright() as p:
        b = await p.chromium.launch()
        bad = 0
        for w in (320, 390, 736, 1024, 1440):
            ctx = await b.new_context(viewport={'width': w, 'height': 800}, is_mobile=w < 600, has_touch=w < 600)
            pg = await ctx.new_page()
            for path in PATHS:
                await pg.goto(BASE + path, wait_until='domcontentloaded')
                sw = await pg.evaluate('document.documentElement.scrollWidth')
                if sw > w + 1:
                    bad += 1
                    culprit = await pg.evaluate('''(w)=>{let out=[];document.querySelectorAll('body *').forEach(e=>{const r=e.getBoundingClientRect();if(r.right>w+1&&r.width<4000)out.push(e.tagName+'.'+(e.className||'').toString().slice(0,40)+' '+Math.round(r.right))});return out.slice(0,4)}''', w)
                    print('OVERFLOW', w, path, sw, culprit)
            await ctx.close()
        print('overflow pages:', bad)
        await b.close()
asyncio.run(main())
