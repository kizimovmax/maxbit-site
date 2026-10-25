#!/usr/bin/env python3
"""Отправка ZIP темы/модуля в точку обновления MAXBIT (REST /maxbit/v1/update).
Локально: python3 scripts/deploy-package.py --base http://localhost:8080 --cookie-jar /tmp/c --nonce N --type plugin dist/maxbit-core-0.3.0.zip
Для dev через браузер: --emit-js печатает JS, который выполняется на странице админки (cookie + wpApiSettings.nonce).
Один запрос (модуль ≥ 0.3.2): --fetch-url https://.../maxbit-theme-0.5.0.zip --emit-js — сервер скачает пакет сам и сверит SHA-256."""
import argparse, base64, hashlib, json, sys, uuid, urllib.request

ap = argparse.ArgumentParser()
ap.add_argument('zip'); ap.add_argument('--type', required=True, choices=['plugin', 'theme'])
ap.add_argument('--base', default='http://localhost:8080'); ap.add_argument('--nonce', default='')
ap.add_argument('--cookie', default=''); ap.add_argument('--seed', action='store_true')
ap.add_argument('--chunk', type=int, default=96 * 1024); ap.add_argument('--emit-js', action='store_true')
ap.add_argument('--fetch-url', default='', help='https-ссылка на этот же ZIP: вместо кусков — один запрос /update/fetch (модуль ≥ 0.3.2)')
a = ap.parse_args()
data = open(a.zip, 'rb').read(); sha = hashlib.sha256(data).hexdigest(); uid = 'u' + uuid.uuid4().hex[:12]
if a.fetch_url:
    body = {"url": a.fetch_url, "type": a.type, "sha256": sha, "seed": "1" if a.seed else "0"}
    if a.emit_js:
        print("(async()=>{const h={'Content-Type':'application/json','X-WP-Nonce':wpApiSettings.nonce};"
              "const r=await fetch('/wp-json/maxbit/v1/update/fetch',{method:'POST',headers:h,credentials:'same-origin',body:JSON.stringify(%s)});return r.status+' '+await r.text();})()" % json.dumps(body))
        sys.exit(0)
    req = urllib.request.Request(a.base + '/wp-json/maxbit/v1/update/fetch', data=json.dumps(body).encode(), headers={'Content-Type': 'application/json', 'X-WP-Nonce': a.nonce, 'Cookie': a.cookie}, method='POST')
    try:
        with urllib.request.urlopen(req) as r: print(json.dumps(json.loads(r.read()), ensure_ascii=False, indent=1))
    except urllib.error.HTTPError as e:
        sys.exit(f"HTTP {e.code} fetch: {e.read().decode(errors='replace')[:1500]}")
    sys.exit(0)
chunks = [base64.b64encode(data[i:i + a.chunk]).decode() for i in range(0, len(data), a.chunk)]
if a.emit_js:
    js = {"id": uid, "type": a.type, "sha256": sha, "chunks": chunks, "seed": "1" if a.seed else "0"}
    print("(async()=>{const P=%s;const h={'Content-Type':'application/json','X-WP-Nonce':wpApiSettings.nonce};"
          "for(let i=0;i<P.chunks.length;i++){const r=await fetch('/wp-json/maxbit/v1/update/upload',{method:'POST',headers:h,credentials:'same-origin',body:JSON.stringify({id:P.id,index:i,total:P.chunks.length,data:P.chunks[i]})});if(!r.ok){return 'upload '+i+' failed '+r.status+' '+await r.text();}}"
          "const r2=await fetch('/wp-json/maxbit/v1/update/apply',{method:'POST',headers:h,credentials:'same-origin',body:JSON.stringify({id:P.id,type:P.type,sha256:P.sha256,seed:P.seed})});return r2.status+' '+await r2.text();})()" % json.dumps(js))
    sys.exit(0)
def post(path, body):
    req = urllib.request.Request(a.base + path, data=json.dumps(body).encode(), headers={'Content-Type': 'application/json', 'X-WP-Nonce': a.nonce, 'Cookie': a.cookie}, method='POST')
    try:
        with urllib.request.urlopen(req) as r: return json.loads(r.read())
    except urllib.error.HTTPError as e:
        sys.exit(f"HTTP {e.code} {path}: {e.read().decode(errors='replace')[:1500]}")
for i, c in enumerate(chunks):
    res = post('/wp-json/maxbit/v1/update/upload', {"id": uid, "index": i, "total": len(chunks), "data": c})
print(json.dumps(post('/wp-json/maxbit/v1/update/apply', {"id": uid, "type": a.type, "sha256": sha, "seed": "1" if a.seed else "0"}), ensure_ascii=False, indent=1))
