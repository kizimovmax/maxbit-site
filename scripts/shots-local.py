#!/usr/bin/env python3
"""Полностраничные снимки локального сайта (Playwright/Chromium) для визуальной проверки темы.
Использование: python3 scripts/shots-local.py [out_dir] [width ...]   (по умолчанию reports/shots-local, 1440 и 390)
"""
import sys, os, asyncio
from playwright.async_api import async_playwright

BASE = os.environ.get('MAXBIT_BASE', 'http://127.0.0.1:8080')
PAGES = [
    ('home', '/'), ('resheniya', '/resheniya/'), ('bitrix24', '/bitrix24/'), ('kak-rabotaem', '/kak-rabotaem/'),
    ('o-kompanii', '/o-kompanii/'), ('osnovatel', '/osnovatel/'), ('zakaz', '/resheniya/zakaz-pod-kontrolem/'),
    ('b24-proizv', '/tekhnologii/bitrix24-dlya-proizvodstva/'), ('integraciya', '/resheniya/integraciya-bitrix24-i-1c/'),
    ('proekty', '/proekty/'), ('praktika', '/baza-znaniy/'), ('chek-list', '/baza-znaniy/chek-list-peredacha-zakaza-v-proizvodstvo/'),
    ('kartochka', '/baza-znaniy/kartochka-proizvodstvennogo-zakaza/'), ('gk-vpk', '/proekty/gk-vpk/'), ('lentochka', '/proekty/lentochka/'),
    ('paritet', '/proekty/paritet-alyans/'), ('avtospeccentr', '/proekty/avtospeccentr/'), ('cupol365', '/proekty/cupol365/'),
    ('helper-med', '/proekty/helper-med/'), ('ngsk', '/proekty/ngsk/'), ('404', '/net-takoy/'),
] + [(n, u) for n, u in (('kp', os.environ.get('MAXBIT_KP', '')), ('prezentaciya', os.environ.get('MAXBIT_PRES', ''))) if u]

async def main():
    out = sys.argv[1] if len(sys.argv) > 1 else 'reports/shots-local'
    widths = [int(w) for w in sys.argv[2:]] or [1440, 390]
    only = os.environ.get('MAXBIT_ONLY')
    os.makedirs(out, exist_ok=True)
    async with async_playwright() as p:
        b = await p.chromium.launch()
        for w in widths:
            ctx = await b.new_context(viewport={'width': w, 'height': 900 if w > 600 else 844}, device_scale_factor=1,
                                      is_mobile=w < 600, has_touch=w < 600)
            page = await ctx.new_page()
            if not os.environ.get('MAXBIT_COOKIE_BANNER'):  # баннер cookie снимаем отдельно; по умолчанию считаем выбор сделанным
                await page.add_init_script("try{localStorage.setItem('mb_consent',JSON.stringify({v:1,analytics:false,at:Date.now()}))}catch(e){}")
            for name, path in PAGES:
                if only and name not in only.split(','):
                    continue
                await page.goto(BASE + path, wait_until='networkidle')
                await page.evaluate('document.fonts.ready')
                # 0.6.0: секции появляются при прокрутке — прокручиваем страницу до конца, чтобы снимок был полным
                await page.evaluate('''async () => { const h = document.body.scrollHeight; for (let y = 0; y < h; y += 500) { window.scrollTo({ top: y, behavior: 'instant' }); await new Promise(r => setTimeout(r, 60)); } window.scrollTo({ top: 0, behavior: 'instant' }); }''')
                await page.wait_for_timeout(700)
                await page.screenshot(path=f'{out}/{name}-{w}.png', full_page=True)
                print('ok', name, w)
            if os.environ.get('MAXBIT_MENU') and w < 600:
                await page.goto(BASE + '/', wait_until='networkidle')
                await page.click('.mb-nav__toggle')
                await page.wait_for_timeout(300)
                await page.screenshot(path=f'{out}/home-menu-{w}.png', full_page=False)
            await ctx.close()
        await b.close()

asyncio.run(main())
