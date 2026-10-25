#!/usr/bin/env python3
"""Собирает одностраничную демо-версию (все HTML-снимки в одном файле с навигацией по #/пути) для просмотра в браузере
без сервера. Не заменяет работающий WordPress: форма и админка в демо не работают."""
import re, html, os, sys
root=os.path.dirname(os.path.dirname(os.path.abspath(__file__))); snap=os.path.join(root,'reports/html-snapshots')
pages=[('/', '01-glavnaya.html','Главная'),('/resheniya/','02-resheniya.html','Решения'),('/resheniya/zakaz-pod-kontrolem/','03-zakaz-pod-kontrolem.html','Заказ под контролем'),
('/tekhnologii/bitrix24-dlya-proizvodstva/','04-bitrix24.html','Битрикс24'),('/kak-rabotaem/','05-kak-rabotaem.html','Как работаем'),('/o-kompanii/','06-o-kompanii.html','О компании'),
('/praktika/','07-praktika.html','Практика'),('/praktika/kartochka-proizvodstvennogo-zakaza/','08-statya.html','Статья'),('/proekty/','09-proekty-pusto.html','Проекты'),('/404/','10-404.html','404'),
('/resheniya/ii-praktikum/','11-ii-praktikum-AUTH-PREVIEW.html','ИИ-Практикум (закрытый просмотр)'),('/proekty/obrazets-struktury-keysa/','12-obrazets-keysa-AUTH-PREVIEW.html','Образец кейса (закрытый просмотр)'),('/glavnaya-auth/','13-glavnaya-AUTH-PREVIEW.html','Главная (закрытый просмотр)')]
css=open(os.path.join(root,'src/themes/maxbit/assets/style.css')).read()
def body_of(s):
    b=re.search(r'<body[^>]*>(.*)</body>',s,re.S).group(1)
    b=re.sub(r'<div id="wpadminbar".*?</div>\s*</div>\s*</div>','',b,flags=re.S)
    b=re.sub(r'<script[^>]*>.*?</script>','',b,flags=re.S); b=re.sub(r'<link[^>]*>','',b)
    b=re.sub(r'href="\./\?post_type=mb_solution&(?:amp;|#038;)?p=11&(?:amp;|#038;)?preview=true"','href="#/resheniya/ii-praktikum/"',b)
    b=re.sub(r'href="\./#contact"','href="#/" data-cta="contact"',b)
    b=re.sub(r'href="\./([^"#?]*)"',lambda m:'href="#/'+m.group(1)+'"',b); b=b.replace('href="./"','href="#/"'); b=re.sub(r'href="\./\?[^"]*"','href="#/"',b)
    b=re.sub(r'href="/([a-z0-9/-]*)/"',r'href="#/\1/"',b)
    b=b.replace('href="#/tekhnologii/"','href="#/tekhnologii/bitrix24-dlya-proizvodstva/"')
    b=re.sub(r'href="http://127\.0\.0\.1:\d+/wp-admin[^"]*"','href="#/"',b); b=re.sub(r'href="http://127\.0\.0\.1:\d+/?"','href="#/"',b)
    b=re.sub(r'<a class="mb-skip"[^>]*>.*?</a>','',b,flags=re.S)
    return b
first=open(os.path.join(snap,pages[0][1])).read(); head_styles=''
sections=[]
for path,f,title in pages:
    s=open(os.path.join(snap,f)).read(); hs=''.join(re.findall(r'<style id="[^"]*">.*?</style>',s,re.S))
    if len(hs)>len(head_styles): head_styles=hs
    banner='' if 'AUTH' not in f else '<div class="demo-auth">Так эту страницу видит авторизованный проверяющий. Анонимному посетителю она недоступна (404).</div>'
    sections.append(f'<section class="demo-page" data-path="{path}" data-title="{html.escape(title)}" hidden>{banner}{body_of(s)}</section>')
nav=''.join(f'<option value="{p}">{html.escape(t)}</option>' for p,_,t in pages if p!='/404/')
out=f'''<!DOCTYPE html><html lang="ru-RU"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>MAXBIT — демо сборки сайта (просмотр без сервера)</title><meta name="robots" content="noindex,nofollow">
<style>:root {{ box-sizing:border-box; padding-top:env(safe-area-inset-top,0px); padding-bottom:env(safe-area-inset-bottom,0px); }} html,body {{ background:#fff; color:#0F172A; }}
{css}
.demo-bar {{ position:sticky; top:0; z-index:200; background:#0F172A; color:#fff; font:14px/1.4 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Arial,sans-serif; padding:8px 16px; display:flex; flex-wrap:wrap; gap:8px 16px; align-items:center; }}
.demo-bar select {{ font:inherit; padding:4px 8px; border-radius:6px; }} .demo-bar b {{ color:#93C5FD; }}
.demo-auth {{ background:#FEF3C7; color:#78350F; padding:8px 16px; text-align:center; font-size:14px; }} .mb-header {{ top:46px; }}
.demo-notice {{ background:#DCFCE7; color:#166534; padding:12px 14px; border-radius:8px; margin-bottom:16px; }}
@media (prefers-color-scheme: dark) {{ :root:not([data-theme="light"]) html, :root:not([data-theme="light"]) body {{ background:#fff; color:#0F172A; }} }}
</style>{head_styles}</head><body>
<div class="demo-bar"><span><b>Демо-просмотр</b> сборки MAXBIT — статическая копия страниц WordPress. Форма и админка здесь не работают.</span>
<label>Страница: <select id="demo-nav">{nav}</select></label></div>
{''.join(sections)}
<script>
(function(){{
  var scrollTarget=''; var pages=[].slice.call(document.querySelectorAll('.demo-page')); var sel=document.getElementById('demo-nav');
  function show(path){{ var found=null; pages.forEach(function(p){{ var on=p.getAttribute('data-path')===path; p.hidden=!on; if(on) found=p; }});
    if(!found){{ pages.forEach(function(p){{ p.hidden=p.getAttribute('data-path')!=='/404/'; }}); }} else {{ sel.value=path; document.title=found.getAttribute('data-title')+' — MAXBIT демо'; }}
    document.body.classList.remove('nav-open'); window.scrollTo(0,0);
    if(scrollTarget){{ var el=found&&found.querySelector('#'+scrollTarget); if(el) setTimeout(function(){{el.scrollIntoView();}},50); scrollTarget=''; }} }}
  function route(){{ var h=location.hash; show(h.indexOf('#/')===0? h.slice(1) : '/'); }}
  window.addEventListener('hashchange',route); route(); sel.addEventListener('change',function(){{ location.hash='#'+sel.value; }});
  document.addEventListener('click',function(e){{
    var t=e.target.closest('.mb-nav__toggle'); if(t){{ var open=document.body.classList.toggle('nav-open'); t.setAttribute('aria-expanded',open?'true':'false'); return; }}
    var a=e.target.closest('a'); if(!a) return; var href=a.getAttribute('href')||'';
    if(a.getAttribute('data-cta')==='contact' && href==='#/'){{ scrollTarget='contact'; if(location.hash==='#/'||location.hash===''){{ e.preventDefault(); route(); }} return; }}
    if(href.charAt(0)==='#' && href.indexOf('#/')!==0){{ e.preventDefault(); var pg=a.closest('.demo-page'); var el=pg&&pg.querySelector(href); if(el) el.scrollIntoView({{behavior:'smooth'}}); document.body.classList.remove('nav-open'); }}
  }});
  document.addEventListener('submit',function(e){{ var f=e.target.closest('.mb-form__form'); if(!f) return; e.preventDefault();
    f.querySelector('.mb-form__status').innerHTML='<div class="demo-notice">Демо-версия: отправка отключена. В рабочей сборке форма сохраняет обращение на сервере и показывает номер вида MB-260930-XXXXXX только после записи.</div>'; }});
}})();
</script></body></html>'''
dst=sys.argv[1] if len(sys.argv)>1 else os.path.join(root,'reports/maxbit-demo.html')
open(dst,'w').write(out); print(dst, len(out)//1024, 'KB')
