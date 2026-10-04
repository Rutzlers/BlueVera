from pathlib import Path
import subprocess, shutil, re, html, urllib.parse, json, sqlite3, os
root=Path.cwd(); out=root/'dist'/'verablue'; out.mkdir(exist_ok=True)
private=root/'test-output'/'html-export';private.mkdir(exist_ok=True)
with sqlite3.connect(root/'storage/store.sqlite') as src, sqlite3.connect(private/'store.sqlite') as dst: src.backup(dst)
# Sanitize only the isolated export database; never export customer or payment data.
with sqlite3.connect(private/'store.sqlite') as db:
 for table in ['order_events','orders','users','attempts']: db.execute('DELETE FROM '+table)
 for key in ['admin_email','admin_password','merchant_id','merchant_key','merchant_salt','bank_name','bank_owner','iban','company','address','return_address','phone','support_email','site_url']:
  db.execute('UPDATE settings SET value=? WHERE name=?',('',key))
 db.execute("UPDATE settings SET value='demo@example.test' WHERE name='admin_email'")
 product=db.execute('SELECT name,price FROM products ORDER BY id LIMIT 1').fetchone() or ('Örnek ürün',100000)
 for i,status in enumerate(['pending','preparing','shipped','in_transit','delivered']):
  db.execute('INSERT INTO orders(id,secret,name,email,phone,address,items,total,shipping,method,status,created,expires,request_key,tracking_code) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
   ('DEMO'+str(i+1),'preview','Örnek Müşteri','demo@example.test','0000000000','Örnek teslimat adresi · Demo',json.dumps([{'name':product[0],'price':product[1],'qty':1}]),product[1],0,'bank',status,'2026-09-30 12:00:00',0,'preview'+str(i),str(482731+i)))
env=os.environ.copy();env['BLUEVERA_STORAGE']=str(private)
php='C:/xampp/php/php.exe'
def render(script,*args):
 r=subprocess.run([php,str(root/script),*args],env=env,capture_output=True)
 if r.returncode or b'Fatal error' in r.stdout: raise RuntimeError(r.stderr.decode(errors='replace'))
 return r.stdout.decode('utf-8-sig')
shutil.copytree(root/'public/assets',out/'assets',dirs_exist_ok=True)
(out/'theme.css').write_text(render(Path('public/theme.php')),encoding='utf-8')
routes={'page=':'index.html','page=admin':'admin.html'}; queue=['page=','page=admin']; pages={}
def canonical(url):
 q=urllib.parse.parse_qsl(urllib.parse.urlsplit(html.unescape(url)).query,keep_blank_values=True)
 return urllib.parse.urlencode(sorted(q))
def link(m):
 u=html.unescape(m.group(1))
 if not u.startswith('index.php'): return m.group(0)
 q=canonical(u)
 if q not in routes: routes[q]='sayfa-'+str(len(routes))+'.html';queue.append(q)
 return 'href="'+routes[q]+'"'
while queue:
 q=queue.pop(0);s=render(Path('tests/render-preview.php'),q)
 s=re.sub(r'href="(index\.php[^\"]*)"',link,s)
 s=s.replace('href="theme.php"','href="theme.css"')
 s=re.sub(r'<input[^>]+name="(?:csrf|request_key)"[^>]*>','',s)
 s=s.replace('<div class="auth-panel">','<div class="auth-panel"><p><a class="button dark" href="admin.html">Admin panelini şifresiz incele ↗</a></p>')
 s=s.replace('</body>','<script src="preview.js"></script></body>')
 s=s.replace('<body>','<body><div style="padding:10px;text-align:center;background:#173f3b;color:white;font:14px Arial">HTML önizleme · İşlemler demo amaçlıdır. &nbsp; <a href="admin.html" style="color:white;text-decoration:underline;font-weight:bold">Admin panelini incele ↗</a></div>')
 for asset in re.findall(r'(?:src|href)="(uploads/[^\"]+)"',s):
  src=root/'public'/asset
  if src.is_file(): (out/asset).parent.mkdir(parents=True,exist_ok=True);shutil.copy2(src,out/asset)
 pages[q]=s
 (out/routes[q]).write_text(s,encoding='utf-8')
js='const routes='+json.dumps(routes,ensure_ascii=False)+';\n'+r'''
function route(params){const entries=[...params.entries()].filter(([k,v])=>v!==''||k==='page').sort(([a],[b])=>a.localeCompare(b));const key=new URLSearchParams(entries).toString();return routes[key]||routes['page=catalog']||'index.html';}
document.addEventListener('submit',e=>{e.preventDefault();const f=e.target,d=new FormData(f),a=d.get('action');if(f.method.toLowerCase()==='get'){if(d.get('q')||d.get('sort')){location.href=routes['page=catalog'];return;}location.href=route(new URLSearchParams(d));return;}if(a==='logout'){location.href='index.html';return;}if(a==='order_update'){const card=f.closest('.order-admin');card.querySelector('.status').textContent=f.querySelector('select').selectedOptions[0].textContent;alert('Örnek sipariş durumu bu ekranda değiştirildi. Gerçek sipariş etkilenmez; sayfa yenilendiğinde sıfırlanır.');return;}if(a==='cart'){location.href=routes['page=cart'];return;}alert('Bu dosya tasarım incelemesi için hazırlanmış HTML önizlemesidir. Bu işlem canlı PHP sitede kullanılabilir.');});
'''
(out/'preview.js').write_text(js,encoding='utf-8')
(out/'ONCE-OKU.txt').write_text('BLUEVERA HTML ONIZLEME\n\nindex.html dosyasina cift tiklayarak acin. Kurulum gerekmez.\nKlasorun tamamini veya ZIP dosyasini paylasin.\nAna sayfa, kategoriler, urun detaylari ve bilgi sayfalari gezilebilir.\nAdmin panelini incele baglantisindan veya admin.html dosyasindan sifresiz yonetim onizlemesine girilir.\nUrun, kategori, logo, renk, odeme ve bilgi sayfasi formlari incelenebilir; kayit yapilmaz.\nSiparisler tamamen ornektir. Durum degisikligi yalnizca acik ekranda gosterilir.\nSepet ve teslimat sayfalari ornek bir urunle gosterilir.\nArama ve siralama tum urunler sayfasina yonlendirir.\nGercek odeme, siparis, hesap girisi ve yonetim islemleri bu HTML kopyasinda calismaz.\n',encoding='utf-8')
for path in out.glob('*.html'):
 for u in re.findall(r'(?:href|src)="([^\"]+)"',path.read_text(encoding='utf-8')):
  if ':' not in u and not u.startswith('#') and not (out/u).is_file():raise RuntimeError('Missing local link: '+u)
shutil.make_archive(str(root/'dist'/'BlueVera-HTML-Onizleme'),'zip',out)
print(f'Validated {len(pages)} HTML pages and their local links/assets.')
