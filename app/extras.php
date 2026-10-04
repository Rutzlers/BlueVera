<?php
// BlueVera ek işlevler: gerçek IP, hukuki metin kontrolü, e-posta bildirimi, görsel temizliği.
function clientIp(): string {
 $ip=(string)($_SERVER['REMOTE_ADDR']??'');
 if(setting('trust_proxy')==='1'){
  foreach(['HTTP_CF_CONNECTING_IP','HTTP_X_FORWARDED_FOR'] as $h){
   $v=trim(explode(',',(string)($_SERVER[$h]??''))[0]);
   if($v!==''&&filter_var($v,FILTER_VALIDATE_IP))return $v;
  }
 }
 return $ip;
}
function legalTitles(): array {return ['Gizlilik Politikası','KVKK','Mesafeli Satış','İade ve Değişim','Çerez Politikası'];}
function legalMissing(): array {
 $missing=[];
 foreach(legalTitles() as $t){$v=trim(setting('legal_'.$t));if($v===''||str_starts_with($v,'Bu sayfanın içeriği mağaza sahibi tarafından henüz hazırlanmadı'))$missing[]=$t;}
 return $missing;
}
function removeOrphanImage(string $path): void {
 if(!preg_match('#^uploads/[a-f0-9]{32}\.(jpg|png|webp)$#',$path))return;
 if(query('SELECT 1 FROM products WHERE image=?',[$path])->fetchColumn()||query('SELECT 1 FROM product_images WHERE path=?',[$path])->fetchColumn()||setting('shop_logo')===$path)return;
 $file=PUBLIC_DIR.'/'.$path;if(is_file($file))@unlink($file);
}
function mailSafe(string $to,string $subject,string $body): bool {
 $from=setting('support_email');
 if(!function_exists('mail')||!filter_var($to,FILTER_VALIDATE_EMAIL)||!filter_var($from,FILTER_VALIDATE_EMAIL))return false;
 $name=preg_replace('/[\r\n"]+/',' ',shopName());
 $headers=['From: =?UTF-8?B?'.base64_encode($name).'?= <'.$from.'>','Reply-To: '.$from,'MIME-Version: 1.0','Content-Type: text/plain; charset=UTF-8','Content-Transfer-Encoding: 8bit'];
 try{return (bool)@mail($to,'=?UTF-8?B?'.base64_encode(preg_replace('/[\r\n]+/',' ',$subject)).'?=',str_replace(["\r\n","\r"],"\n",$body),implode("\r\n",$headers));}
 catch(Throwable $ex){error_log('mail: '.$ex->getMessage());return false;}
}
function orderMailBody(array $o,string $intro,bool $contract): string {
 $methods=['bank'=>'Havale / EFT','cod'=>'Kapıda ödeme','card'=>'Kart (PayTR)'];
 $lines=[$intro,'','Sipariş no: '.$o['id'],'Sipariş takip kodu: '.$o['tracking_code'],''];
 foreach(json_decode($o['items'],true) as $p)$lines[]='- '.$p['name'].' x '.$p['qty'].' — '.money($p['price']*$p['qty']);
 $lines[]='Kargo: '.money($o['shipping']);$lines[]='Toplam: '.money($o['total']);$lines[]='Ödeme yöntemi: '.($methods[$o['method']]??$o['method']);
 if($o['method']==='bank'&&$o['status']==='pending'){$lines[]='';$lines[]='Havale bilgileri: '.setting('bank_name').' · '.setting('bank_owner').' · '.setting('iban');$lines[]='Açıklamaya sipariş numaranızı yazın. Ödeme süresi 48 saattir.';}
 if($o['tracking'])$lines[]='Kargo bilgisi: '.$o['tracking'];
 $lines[]='';$lines[]='Teslimat bilgileri:';$lines[]=$o['name'];$lines[]=$o['address'];$lines[]=$o['phone'];
 $base=rtrim(setting('site_url'),'/');if($base!=='')$lines[]='';if($base!=='')$lines[]='Sipariş durumu: '.$base.'/'.url('order',['id'=>$o['id'],'key'=>$o['secret']]);
 $lines[]='';$lines[]='Satıcı: '.setting('company');$lines[]=setting('address');$lines[]='İletişim: '.setting('support_email').(setting('phone')!==''?' · '.setting('phone'):'');
 if($contract)foreach(['Mesafeli Satış','İade ve Değişim'] as $t){$lines[]='';$lines[]='=== '.$t.' ===';$lines[]=setting('legal_'.$t);}
 return implode("\n",$lines);
}
function notifyOrder(string $id,string $event): void {
 try{
  $o=query('SELECT * FROM orders WHERE id=?',[$id])->fetch();if(!$o)return;
  $admin=setting('support_email');$summary=function(string $head)use($o){return orderMailBody($o,$head,false);};
  if($event==='created'){
   mailSafe($o['email'],'Siparişiniz alındı — '.$o['id'],orderMailBody($o,'Merhaba '.$o['name'].', siparişiniz alındı. Bu e-posta mesafeli satış sözleşmesi ve iade koşullarının bir kopyasını içerir.',true));
   mailSafe($admin,'Yeni sipariş — '.$o['id'],$summary('Yeni sipariş geldi. Yönetim panelinden takip edebilirsiniz.'));
  }elseif($event==='paid'){
   if($o['status']==='paid')mailSafe($o['email'],'Ödemeniz alındı — '.$o['id'],orderMailBody($o,'Merhaba '.$o['name'].', kart ödemeniz alındı. Bu e-posta mesafeli satış sözleşmesi ve iade koşullarının bir kopyasını içerir.',true));
   mailSafe($admin,($o['status']==='paid'?'Kart ödemesi alındı — ':($o['status']==='review'?'MANUEL İNCELEME gerekiyor — ':'Test ödemesi — ')).$o['id'],$summary($o['status']==='paid'?'Kart ödemesi onaylandı. Siparişi hazırlayabilirsiniz.':($o['status']==='review'?'Ödeme alındı fakat stok rezervasyonu sona ermişti. Panelden kontrol edin, sevk etmeyin.':'Bu bir test ödemesidir. Sevk etmeyin.')));
  }elseif($event==='shipped'){
   mailSafe($o['email'],'Siparişiniz kargoya verildi — '.$o['id'],orderMailBody($o,'Merhaba '.$o['name'].', siparişiniz kargoya verildi.',false));
  }
 }catch(Throwable $ex){error_log('notify: '.$ex->getMessage());}
}

// ---- Bot koruması (görünmez): bal küpü alanı + imzalı zaman damgası ----
function botSecret(): string {$s=setting('bot_secret');if($s==='')throw new RuntimeException('Bot anahtarı hazır değil.');return $s;}
function botToken(?int $ts=null): string {$ts??=time();return $ts.'.'.hash_hmac('sha256',(string)$ts,botSecret());}
function botFields(): string {return '<input class="hp" name="hp_field" tabindex="-1" autocomplete="off" aria-hidden="true"><input type="hidden" name="ft" value="'.e(botToken()).'">';}
function botcheck(): void {
 $fail='Form doğrulanamadı. Sayfayı yenileyip tekrar deneyin.';
 if((string)($_POST['hp_field']??'')!=='')throw new RuntimeException($fail);
 $parts=explode('.',(string)($_POST['ft']??''));
 if(count($parts)!==2||!ctype_digit($parts[0])||!hash_equals(hash_hmac('sha256',$parts[0],botSecret()),$parts[1]))throw new RuntimeException($fail);
 $age=time()-(int)$parts[0];$min=getenv('BLUEVERA_BOT_MIN')!==false?(int)getenv('BLUEVERA_BOT_MIN'):2;
 if($age<$min)throw new RuntimeException('Lütfen birkaç saniye bekleyip tekrar gönderin.');
 if($age>86400)throw new RuntimeException('Sayfanın süresi doldu. Yenileyip tekrar deneyin.');
}

// ---- Kullanıcı jetonları: şifre sıfırlama ve e-posta doğrulama ----
function createUserToken(int $userId,string $kind,int $ttl): string {
 $raw=bin2hex(random_bytes(32));
 query('DELETE FROM user_tokens WHERE user_id=? AND kind=?',[$userId,$kind]);
 query('INSERT INTO user_tokens(user_id,kind,token_hash,expires) VALUES(?,?,?,?)',[$userId,$kind,hash('sha256',$raw),time()+$ttl]);
 return $raw;
}
function findUserToken(string $raw,string $kind): array|false {
 if(!preg_match('/^[a-f0-9]{64}$/',$raw))return false;
 query('DELETE FROM user_tokens WHERE expires < ?',[time()]);
 return query('SELECT * FROM user_tokens WHERE token_hash=? AND kind=?',[hash('sha256',$raw),$kind])->fetch();
}
function siteLink(string $route,array $params): string {$base=rtrim(setting('site_url'),'/');return $base===''?'':$base.'/'.url($route,$params);}
function sendVerification(int $userId,string $email,string $name): bool {
 $link=siteLink('verify',['token'=>createUserToken($userId,'verify',604800)]);
 if($link==='')return false;
 return mailSafe($email,'E-posta adresinizi doğrulayın',"Merhaba $name,\n\nE-posta adresinizi doğrulamak için bu bağlantıyı açın (7 gün geçerlidir):\n$link\n\nBu hesabı siz oluşturmadıysanız bu e-postayı yok sayabilirsiniz.");
}
function sendPasswordReset(array $user): bool {
 $link=siteLink('reset',['token'=>createUserToken((int)$user['id'],'reset',3600)]);
 if($link==='')return false;
 return mailSafe($user['email'],'Şifre sıfırlama bağlantınız',"Merhaba {$user['name']},\n\nŞifrenizi yenilemek için bu bağlantıyı açın (1 saat geçerlidir):\n$link\n\nBu isteği siz yapmadıysanız bu e-postayı yok sayın; şifreniz değişmez.");
}

// ---- Veritabanı sürümü: doğrulama alanı ve jeton tablosu ----
if(setting('backend_v2')!=='1')transaction(function(){
 if(setting('backend_v2')==='1')return;
 if(!in_array('verified',array_column(query('PRAGMA table_info(users)')->fetchAll(),'name'),true))db()->exec('ALTER TABLE users ADD COLUMN verified INTEGER NOT NULL DEFAULT 0');
 db()->exec('CREATE TABLE IF NOT EXISTS user_tokens(id INTEGER PRIMARY KEY AUTOINCREMENT,user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,kind TEXT NOT NULL,token_hash TEXT NOT NULL UNIQUE,expires INTEGER NOT NULL)');
 if(setting('bot_secret')==='')setsetting('bot_secret',bin2hex(random_bytes(32)));
 setsetting('backend_v2','1');
});

// ---- Ürün seçenekleri, ek görseller, adres alanları ----
function turkeyProvinces(): array {return ['Adana','Adıyaman','Afyonkarahisar','Ağrı','Aksaray','Amasya','Ankara','Antalya','Ardahan','Artvin','Aydın','Balıkesir','Bartın','Batman','Bayburt','Bilecik','Bingöl','Bitlis','Bolu','Burdur','Bursa','Çanakkale','Çankırı','Çorum','Denizli','Diyarbakır','Düzce','Edirne','Elazığ','Erzincan','Erzurum','Eskişehir','Gaziantep','Giresun','Gümüşhane','Hakkâri','Hatay','Iğdır','Isparta','İstanbul','İzmir','Kahramanmaraş','Karabük','Karaman','Kars','Kastamonu','Kayseri','Kilis','Kırıkkale','Kırklareli','Kırşehir','Kocaeli','Konya','Kütahya','Malatya','Manisa','Mardin','Mersin','Muğla','Muş','Nevşehir','Niğde','Ordu','Osmaniye','Rize','Sakarya','Samsun','Siirt','Sinop','Sivas','Şanlıurfa','Şırnak','Tekirdağ','Tokat','Trabzon','Tunceli','Uşak','Van','Yalova','Yozgat','Zonguldak'];}
function saveUploadedImage(array $f): string {
 if(($f['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK||($f['size']??0)>5*1024*1024)throw new RuntimeException('Görsel en fazla 5 MB olabilir.');
 $type=(new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);$ext=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'][$type]??null;
 if(!$ext||!getimagesize($f['tmp_name']))throw new RuntimeException('JPG, PNG veya WebP görsel seçin.');
 $path='uploads/'.bin2hex(random_bytes(16)).'.'.$ext;
 if(!is_uploaded_file($f['tmp_name'])||!move_uploaded_file($f['tmp_name'],PUBLIC_DIR.'/'.$path))throw new RuntimeException('Görsel yüklenemedi.');
 return $path;
}
function hasVariants(int $pid): bool {return (bool)query('SELECT 1 FROM product_variants WHERE product_id=? LIMIT 1',[$pid])->fetchColumn();}
function productVariants(int $pid): array {return query('SELECT * FROM product_variants WHERE product_id=? ORDER BY position,id',[$pid])->fetchAll();}
function productGallery(int $pid): array {return query('SELECT * FROM product_images WHERE product_id=? ORDER BY position,id',[$pid])->fetchAll();}
function parseVariantLines(string $text): array {
 $out=[];$seen=[];
 foreach(preg_split('/\R/u',trim($text))?:[] as $line){
  $line=trim($line);if($line==='')continue;
  if(str_contains($line,'|')){$parts=array_map('trim',explode('|',$line));if(count($parts)!==2)throw new RuntimeException('Her seçenek için ad ve stok girin: Mavi | 5.');$name=$parts[0];$stock=$parts[1];}elseif(preg_match('/^(.+?)\s+(\d+)$/u',$line,$m)){$name=trim($m[1]);$stock=$m[2];}else throw new RuntimeException('Seçenek stoğu eksik: '.$line.'. Örneğin Mavi | 5 yazın. Yalnız stok sayısı için yukarıdaki Stok adedi alanını kullanın.');
  if(mb_strlen($name)>40)throw new RuntimeException('Seçenek adı en fazla 40 karakter olabilir.');
  if($name===''||!ctype_digit($stock)||(int)$stock>100000)throw new RuntimeException('Seçenekleri "Ad | stok" biçiminde yazın (örneğin: Mavi | 5).');
  $k=mb_strtolower($name);if(isset($seen[$k]))throw new RuntimeException('Aynı seçenek adı iki kez yazılmış: '.$name);$seen[$k]=1;
  $out[]=['name'=>$name,'stock'=>(int)$stock];
 }
 if(count($out)>30)throw new RuntimeException('En fazla 30 seçenek tanımlanabilir.');
 return $out;
}
function syncProductStock(int $pid): void {
 if(hasVariants($pid))query('UPDATE products SET stock=(SELECT COALESCE(SUM(stock),0) FROM product_variants WHERE product_id=?) WHERE id=?',[$pid,$pid]);
}
function saveVariants(int $pid,array $variants): void {
 $existing=[];foreach(productVariants($pid) as $v)$existing[mb_strtolower($v['name'])]=$v;
 $keep=[];
 foreach($variants as $i=>$v){
  $k=mb_strtolower($v['name']);
  if(isset($existing[$k])){query('UPDATE product_variants SET name=?,stock=?,position=? WHERE id=?',[$v['name'],$v['stock'],$i,$existing[$k]['id']]);$keep[]=(int)$existing[$k]['id'];}
  else{query('INSERT INTO product_variants(product_id,name,stock,position) VALUES(?,?,?,?)',[$pid,$v['name'],$v['stock'],$i]);$keep[]=(int)db()->lastInsertId();}
 }
 foreach($existing as $v)if(!in_array((int)$v['id'],$keep,true))query('DELETE FROM product_variants WHERE id=?',[$v['id']]);
 syncProductStock($pid);
}
function reserveItemStock(array $it): void {
 $qty=(int)$it['qty'];$vid=(int)($it['variant_id']??0);$label=(string)($it['name']??'Ürün');
 if($vid>0){
  if(query('UPDATE product_variants SET stock=stock-? WHERE id=? AND product_id=? AND stock>=?',[$qty,$vid,$it['id'],$qty])->rowCount()!==1)throw new RuntimeException($label.' için yeterli stok yok.');
  syncProductStock((int)$it['id']);return;
 }
 if(query('UPDATE products SET stock=stock-? WHERE id=? AND stock>=?',[$qty,$it['id'],$qty])->rowCount()!==1)throw new RuntimeException($label.' için yeterli stok yok.');
}
function restoreItemStock(array $it): void {
 $qty=(int)$it['qty'];$vid=(int)($it['variant_id']??0);
 if($vid>0){query('UPDATE product_variants SET stock=stock+? WHERE id=?',[$qty,$vid]);syncProductStock((int)$it['id']);return;}
 query('UPDATE products SET stock=stock+? WHERE id=?',[$qty,$it['id']]);
}
function itemAvailable(array $it): int {
 $vid=(int)($it['variant_id']??0);
 if($vid>0){$s=query('SELECT stock FROM product_variants WHERE id=? AND product_id=?',[$vid,$it['id']])->fetchColumn();return $s===false?0:(int)$s;}
 $s=query('SELECT stock FROM products WHERE id=?',[$it['id']])->fetchColumn();return $s===false?0:(int)$s;
}

if(setting('frontend_v3')!=='1')transaction(function(){
 if(setting('frontend_v3')==='1')return;
 $cols=fn(string $t)=>array_column(query('PRAGMA table_info('.$t.')')->fetchAll(),'name');
 if(!in_array('variant_label',$cols('products'),true))db()->exec("ALTER TABLE products ADD COLUMN variant_label TEXT NOT NULL DEFAULT ''");
 foreach(['city','district','postal'] as $c)if(!in_array($c,$cols('orders'),true))db()->exec("ALTER TABLE orders ADD COLUMN $c TEXT NOT NULL DEFAULT ''");
 db()->exec('CREATE TABLE IF NOT EXISTS product_variants(id INTEGER PRIMARY KEY AUTOINCREMENT,product_id INTEGER NOT NULL REFERENCES products(id) ON DELETE CASCADE,name TEXT NOT NULL,stock INTEGER NOT NULL DEFAULT 0 CHECK(stock>=0),position INTEGER NOT NULL DEFAULT 0)');
 db()->exec('CREATE TABLE IF NOT EXISTS product_images(id INTEGER PRIMARY KEY AUTOINCREMENT,product_id INTEGER NOT NULL REFERENCES products(id) ON DELETE CASCADE,path TEXT NOT NULL,position INTEGER NOT NULL DEFAULT 0)');
 db()->exec('CREATE INDEX IF NOT EXISTS idx_variants_product ON product_variants(product_id)');db()->exec('CREATE INDEX IF NOT EXISTS idx_images_product ON product_images(product_id)');
 setsetting('frontend_v3','1');
});
