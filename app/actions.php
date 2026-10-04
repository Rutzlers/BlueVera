<?php
function actions(): void {
 if($_SERVER['REQUEST_METHOD']!=='POST')return;
 checkcsrf();$action=field('action',40);
 if(in_array($action,['login','register','admin_login','forgot','reset','checkout','track','resend_verify'],true))botcheck();
 if($action==='cart'){$id=(int)($_POST['id']??0);$p=query('SELECT * FROM products WHERE id=? AND active=1',[$id])->fetch();if(!$p)throw new RuntimeException('Ürün bulunamadı.');$key=(string)$id;$stock=(int)$p['stock'];$variants=productVariants($id);if($variants){$vid=(int)($_POST['variant']??0);$v=null;foreach($variants as $x)if((int)$x['id']===$vid)$v=$x;if(!$v)throw new RuntimeException('Lütfen sepete eklemeden önce '.mb_strtolower($p['variant_label']?:'seçenek').' seçin.');$key=$id.'-'.$vid;$stock=(int)$v['stock'];}$qty=(int)($_POST['qty']??1);if(isset($_POST['add']))$qty+=($_SESSION['cart'][$key]??0);if($qty<0||$qty>min(99,$stock))throw new RuntimeException('İstenen miktarda stok bulunmuyor.');if($qty===0)unset($_SESSION['cart'][$key]);else $_SESSION['cart'][$key]=$qty;unset($_SESSION['checkout_key']);flash($qty?'Sepetiniz güncellendi.':'Ürün sepetten çıkarıldı.');go('cart');}
 if($action==='logout'){unset($_SESSION['admin'],$_SESSION['user']);session_regenerate_id(true);go();}
 if($action==='login'||$action==='register'||$action==='admin_login'){
 rate('login',15);$email=strtolower(field('email',100));$pass=field('password',200);
 if($action==='admin_login'||($action==='login'&&$email===strtolower(setting('admin_email')))){if($email!==strtolower(setting('admin_email'))||!password_verify($pass,setting('admin_password')))throw new RuntimeException('E-posta veya şifre hatalı.');session_regenerate_id(true);unset($_SESSION['user']);$_SESSION['admin']=true;$_SESSION['admin_time']=time();go('admin');}
 if($action==='register'){$name=field('name',60);if(!$name||!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($pass)<12)throw new RuntimeException('Adınızı, geçerli bir e-posta ve en az 12 karakterli şifre girin.');if(query('SELECT id FROM users WHERE email=?',[$email])->fetch())throw new RuntimeException('Bu e-posta ile kayıt oluşturulamıyor. Giriş yapmayı deneyin.');query('INSERT INTO users(name,email,password) VALUES(?,?,?)',[$name,$email,password_hash($pass,PASSWORD_DEFAULT)]);sendVerification((int)db()->lastInsertId(),$email,$name);}
 $u=query('SELECT * FROM users WHERE email=?',[$email])->fetch();if(!$u||!password_verify($pass,$u['password']))throw new RuntimeException('E-posta veya şifre hatalı.');session_regenerate_id(true);unset($_SESSION['admin'],$_SESSION['admin_time']);$_SESSION['user']=$u['id'];go('account');
 }
 if($action==='checkout'){
 rate('checkout',20);if(setting('sales_enabled')!=='1')throw new RuntimeException('Mağaza şu anda tanıtım modunda. Sipariş alımı henüz açılmadı.');if(legalMissing())throw new RuntimeException('Mağaza yasal bilgilendirme metinlerini henüz tamamlamadığı için sipariş alınamıyor.');
 $request=field('request_key',64);if(!hash_equals($_SESSION['checkout_key']??'', $request))throw new RuntimeException('Sipariş oturumunuz yenilendi. Sayfayı yenileyin.');
 $old=query('SELECT * FROM orders WHERE request_key=?',[$request])->fetch();if($old)go('order',['id'=>$old['id'],'key'=>$old['secret']]);
 $name=field('name',60);$email=strtolower(field('email',100));$phone=field('phone',20);$street=field('address',300);$city=field('city',40);$district=field('district',60);$postal=field('postal',5);$method=field('method',10);
 if(!in_array($city,turkeyProvinces(),true)||mb_strlen($district)<2||mb_strlen($street)<10||($postal!==''&&!preg_match('/^\d{5}$/',$postal)))throw new RuntimeException('Açık adres, il, ilçe ve (varsa) 5 haneli posta kodunu eksiksiz doldurun.');
 $address=$street.', '.$district.' / '.$city.($postal!==''?' '.$postal:'');
 if(!$name||!filter_var($email,FILTER_VALIDATE_EMAIL)||!preg_match('/^[+0-9 ()-]{10,20}$/',$phone)||mb_strlen($address)<15||empty($_POST['terms']))throw new RuntimeException('Teslimat bilgilerini eksiksiz doldurun ve satış koşullarını onaylayın.');
 if(!in_array($method,['bank','cod','card'],true)||setting($method.'_enabled')!=='1')throw new RuntimeException('Bu ödeme yöntemi kullanılamıyor.');
 $o=transaction(function()use($name,$email,$phone,$address,$method,$request,$city,$district,$postal){$items=basket();if(!$items)throw new RuntimeException('Sepetiniz boş.');if(in_array($method,['bank','card'],true)&&(int)query("SELECT COUNT(*) FROM orders WHERE status='pending' AND method IN ('bank','card') AND (email=? OR phone=?)",[$email,$phone])->fetchColumn()>=3)throw new RuntimeException('Ödemesi bekleyen siparişleriniz var. Önce onları tamamlayın veya süresinin dolmasını bekleyin.');foreach($items as $p){if($p['demo'])throw new RuntimeException('Örnek ürünler satın alınamaz. Mağaza sahibinin gerçek ürün eklemesi gerekiyor.');if($p['qty']<1||$p['qty']>$p['stock'])throw new RuntimeException($p['name'].' için yeterli stok yok.');reserveItemStock(['id'=>$p['id'],'variant_id'=>$p['variant_id'],'qty'=>$p['qty'],'name'=>$p['name'].($p['variant']!==''?' ('.$p['variant'].')':'')]);}
 [$sub,$ship,$total]=totals($items);$id='BV'.date('ymd').strtoupper(bin2hex(random_bytes(5)));$secret=bin2hex(random_bytes(24));$test=$method==='card'?(int)setting('paytr_test','1'):0;
 query('INSERT INTO orders(id,secret,user_id,name,email,phone,address,items,total,shipping,method,status,created,expires,test_mode,request_key,city,district,postal) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',[$id,$secret,$_SESSION['user']??null,$name,$email,$phone,$address,json_encode(array_map(fn($p)=>['id'=>$p['id'],'variant_id'=>$p['variant_id'],'name'=>$p['name'].($p['variant']!==''?' ('.$p['variant'].')':''),'price'=>$p['price'],'qty'=>$p['qty']],$items),JSON_UNESCAPED_UNICODE),$total,$ship,$method,$method==='cod'?'confirmed':'pending',date('Y-m-d H:i:s'),time()+($method==='card'?3600:172800),$test,$request,$city,$district,$postal]);query('UPDATE orders SET tracking_code=? WHERE id=?',[newTrackingCode(),$id]);recordOrderStatus($id,$method==='cod'?'confirmed':'pending','checkout');return ['id'=>$id,'secret'=>$secret];});$_SESSION['cart']=[];if($method!=='card')notifyOrder($o['id'],'created');go('order',['id'=>$o['id'],'key'=>$o['secret']]);
 }
 if($action==='track') {rate('track',15);$o=query('SELECT * FROM orders WHERE (id=? OR tracking_code=? OR previous_tracking_code=?) AND email=?',[normalizeTrackingCode(field('order_id',40)),normalizeTrackingCode(field('order_id',40)),normalizeTrackingCode(field('order_id',40)),strtolower(field('email',100))])->fetch();if(!$o)throw new RuntimeException('Sipariş numarası ve e-posta eşleşmedi.');go('order',['id'=>$o['id'],'key'=>$o['secret']]);}
 if($action==='forgot'){
  rate('forgot',5);$email=strtolower(field('email',100));
  if(filter_var($email,FILTER_VALIDATE_EMAIL)){$u=query('SELECT * FROM users WHERE email=?',[$email])->fetch();if($u&&!sendPasswordReset($u))error_log('Şifre sıfırlama e-postası gönderilemedi (site adresi veya mail ayarını kontrol edin).');}
  flash('Bu e-posta ile kayıtlı bir hesap varsa şifre sıfırlama bağlantısı gönderildi. Gelen kutunuzu ve spam klasörünüzü kontrol edin.');go('forgot');
 }
 if($action==='reset'){
  rate('reset',10);$t=findUserToken(field('token',80),'reset');if(!$t)throw new RuntimeException('Bu bağlantı geçersiz veya süresi dolmuş. Yeni bir sıfırlama isteyin.');
  $new=field('new_password',200);if(strlen($new)<12)throw new RuntimeException('Şifre en az 12 karakter olmalı.');if($new!==field('confirm_password',200))throw new RuntimeException('Şifreler eşleşmiyor.');
  transaction(function()use($t,$new){query('UPDATE users SET password=?,verified=1 WHERE id=?',[password_hash($new,PASSWORD_DEFAULT),$t['user_id']]);query('DELETE FROM user_tokens WHERE user_id=?',[$t['user_id']]);});
  flash('Şifreniz güncellendi. Yeni şifrenizle giriş yapabilirsiniz.');go('login');
 }
 if($action==='resend_verify'){
  if(empty($_SESSION['user']))go('login');rate('resend',5);$u=query('SELECT * FROM users WHERE id=?',[$_SESSION['user']])->fetch();
  if($u&&!$u['verified']){flash(sendVerification((int)$u['id'],$u['email'],$u['name'])?'Doğrulama bağlantısı e-posta adresinize gönderildi.':'E-posta şu anda gönderilemedi. Daha sonra tekrar deneyin.');}
  go('account');
 }
 requireadmin();
 if($action==='backup'){
  rate('backup',5);ignore_user_abort(true);foreach(glob(STORE.'/backup-*.sqlite')?:[] as $stale)if(filemtime($stale)<time()-600)@unlink($stale);$tmp=STORE.'/backup-'.bin2hex(random_bytes(6)).'.sqlite';register_shutdown_function(function()use($tmp){if(is_file($tmp))@unlink($tmp);});
  transaction(function()use($tmp){if(!copy(STORE.'/store.sqlite',$tmp))throw new RuntimeException('Yedek oluşturulamadı.');});
  $copy=new PDO('sqlite:'.$tmp);$copy->exec('DELETE FROM attempts');$copy=null;
  header('Content-Type: application/octet-stream');header('Content-Disposition: attachment; filename="bluevera-yedek-'.date('Ymd-His').'.sqlite"');header('Content-Length: '.filesize($tmp));readfile($tmp);@unlink($tmp);exit;
 }
 if($action==='product_delete'){
  $id=(int)($_POST['id']??0);$p=query('SELECT * FROM products WHERE id=?',[$id])->fetch();if(!$p)throw new RuntimeException('Ürün bulunamadı.');
  if(query('SELECT 1 FROM orders WHERE instr(items,?)>0 LIMIT 1',['"id":'.$id.','])->fetchColumn())throw new RuntimeException('Bu ürün siparişlerde kullanıldığı için silinemez. Vitrinden kaldırabilirsiniz.');
  $paths=array_column(productGallery($id),'path');query('DELETE FROM products WHERE id=?',[$id]);removeOrphanImage($p['image']);foreach($paths as $gp)removeOrphanImage($gp);flash('Ürün silindi.');go('admin/products');
 }
 if($action==='category_save'){
 $name=field('name',60);$id=(int)($_POST['id']??0);if(!$name)throw new RuntimeException('Kategori adı girin.');
 transaction(function()use($id,$name){if(query('SELECT id FROM categories WHERE name=? AND id<>?',[$name,$id])->fetch())throw new RuntimeException('Bu kategori zaten mevcut.');if($id){$old=query('SELECT name FROM categories WHERE id=?',[$id])->fetchColumn();if($old===false)throw new RuntimeException('Kategori bulunamadı.');query('UPDATE categories SET name=? WHERE id=?',[$name,$id]);query('UPDATE products SET category=? WHERE category=?',[$name,$old]);}else query('INSERT INTO categories(name) VALUES(?)',[$name]);});flash('Kategori kaydedildi.');go('admin/categories');
 }
 if($action==='branding'){
 $name=field('shop_name',60);if(!$name)throw new RuntimeException('Mağaza adı boş olamaz.');$colors=[];foreach(themeDefaults() as $key=>$default){$v=field('color_'.$key,7);if(!preg_match('/^#[0-9a-fA-F]{6}$/',$v))throw new RuntimeException('Geçerli bir renk seçin.');$colors['color_'.$key]=$v;}
 $logo=uploadedImage('logo',isset($_POST['reset_logo'])?'assets/favicon.svg':shopLogo());transaction(function()use($name,$logo,$colors){setsetting('shop_name',$name);setsetting('shop_logo',$logo);foreach($colors as $k=>$v)setsetting($k,$v);});flash('Mağaza görünümü güncellendi.');go('admin/branding');
 }
 if($action==='product_save'){
 $id=(int)($_POST['id']??0);$old=$id?query('SELECT * FROM products WHERE id=?',[$id])->fetch():false;
 $name=field('name',140);$cat=field('category',60);$desc=field('description',10000);$price=priceinput('price');$was=priceinput('old_price');$stock=filter_var($_POST['stock']??'',FILTER_VALIDATE_INT,['options'=>['min_range'=>0,'max_range'=>100000]]);
 if(!query('SELECT id FROM categories WHERE name=?',[$cat])->fetch())throw new RuntimeException('Listeden bir kategori seçin. Yeni kategoriyi Kategoriler sayfasından ekleyebilirsiniz.');
 if(!$name||!$cat||!$desc||$price<1)throw new RuntimeException('Ürün adı, kategori, açıklama, fiyat ve stok gerekli.');
 $variants=parseVariantLines((string)($_POST['variants']??''));$label=$variants?(field('variant_label',30)?:'Seçenek'):'';if($variants)$stock=array_sum(array_column($variants,'stock'));elseif($stock===false)throw new RuntimeException('Stok adedini 0 ile 100000 arasında tam sayı girin.');
 $img=$old['image']??'assets/placeholder.svg';
 $moved=[];$newGallery=[];if(isset($_FILES['image'])&&$_FILES['image']['error']!==UPLOAD_ERR_NO_FILE){$img=$moved[]=saveUploadedImage($_FILES['image']);}
 $gf=$_FILES['gallery']??null;if($gf&&is_array($gf['name'])){foreach(array_keys($gf['name']) as $gi){if(($gf['error'][$gi]??UPLOAD_ERR_NO_FILE)===UPLOAD_ERR_NO_FILE)continue;try{$gp=saveUploadedImage(['name'=>$gf['name'][$gi],'tmp_name'=>$gf['tmp_name'][$gi],'error'=>$gf['error'][$gi],'size'=>$gf['size'][$gi]]);}catch(Throwable $ex){foreach($moved as $mv)@unlink(PUBLIC_DIR.'/'.$mv);throw $ex;}$moved[]=$newGallery[]=$gp;}}
 $args=[$name,$cat,$desc,$price,$was,$stock,$img,isset($_POST['active'])?1:0,isset($_POST['demo'])?1:0,$label];
 try{$dropped=transaction(function()use($id,$args,$variants,$newGallery){
  if($id){$args[]=$id;query('UPDATE products SET name=?,category=?,description=?,price=?,old_price=?,stock=?,image=?,active=?,demo=?,variant_label=? WHERE id=?',$args);}else{query('INSERT INTO products(name,category,description,price,old_price,stock,image,active,demo,variant_label) VALUES(?,?,?,?,?,?,?,?,?,?)',$args);$id=(int)db()->lastInsertId();}
  $dropped=[];foreach(array_filter(array_map('intval',(array)($_POST['del_img']??[]))) as $di){$row=query('SELECT * FROM product_images WHERE id=? AND product_id=?',[$di,$id])->fetch();if($row){query('DELETE FROM product_images WHERE id=?',[$di]);$dropped[]=$row['path'];}}
  $pos=(int)query('SELECT COALESCE(MAX(position),0) FROM product_images WHERE product_id=?',[$id])->fetchColumn();foreach($newGallery as $gp)query('INSERT INTO product_images(product_id,path,position) VALUES(?,?,?)',[$id,$gp,++$pos]);
  if((int)query('SELECT COUNT(*) FROM product_images WHERE product_id=?',[$id])->fetchColumn()>8)throw new RuntimeException('Bir ürüne en fazla 8 ek görsel eklenebilir.');
  if($variants)saveVariants($id,$variants);else query('DELETE FROM product_variants WHERE product_id=?',[$id]);
  return $dropped;});}catch(Throwable $ex){foreach($moved as $mv)@unlink(PUBLIC_DIR.'/'.$mv);throw $ex;}
 foreach($dropped as $dp)removeOrphanImage($dp);if($old&&$old['image']!==$img)removeOrphanImage($old['image']);flash('Ürün kaydedildi.');go('admin/products');
 }
 if($action==='product_hide'){query('UPDATE products SET active=0 WHERE id=?',[(int)($_POST['id']??0)]);flash('Ürün vitrinden kaldırıldı. Düzenleyerek tekrar yayınlayabilirsiniz.');go('admin/products');}
 if($action==='settings'){
 $keys=['support_email','phone','company','address','return_address','return_carrier','iban','bank_name','bank_owner','site_url','merchant_id','merchant_key','merchant_salt'];
 $values=[];foreach($keys as $k){$v=field($k,500);if(in_array($k,['merchant_key','merchant_salt'])&&$v==='')continue;$values[$k]=$v;}
 if($values['site_url']!==''&&!filter_var($values['site_url'],FILTER_VALIDATE_URL))throw new RuntimeException('Geçerli bir site adresi girin.');
 foreach(['sales_enabled','bank_enabled','cod_enabled','card_enabled','paytr_test','legal_ready','trust_proxy'] as $k)$values[$k]=isset($_POST[$k])?'1':'0';
 $values['shipping']=(string)priceinput('shipping');$values['free_shipping']=(string)priceinput('free_shipping');
 if($values['bank_enabled']==='1'&&(!$values['iban']||!$values['bank_owner']))throw new RuntimeException('Havale için IBAN ve hesap sahibi girin.');
 if($values['card_enabled']==='1'&&(!$values['merchant_id']||!($values['merchant_key']??setting('merchant_key'))||!($values['merchant_salt']??setting('merchant_salt'))||!str_starts_with($values['site_url'],'https://')))throw new RuntimeException('Kart ödemesi için mağaza bilgileri ve HTTPS site adresi gerekli.');
 if($values['sales_enabled']==='1'&&(!$values['company']||!$values['address']||!filter_var($values['support_email'],FILTER_VALIDATE_EMAIL)||$values['legal_ready']!=='1'||($values['bank_enabled']!=='1'&&$values['cod_enabled']!=='1'&&$values['card_enabled']!=='1')))throw new RuntimeException('Satışa açmak için işletme/iletişim bilgilerini, metinleri ve en az bir ödeme yöntemini tamamlayın.');
 if($values['sales_enabled']==='1'&&($miss=legalMissing()))throw new RuntimeException('Satışa açmadan önce şu sayfaların içeriğini yazın: '.implode(', ',$miss).'.');
 transaction(function()use($values){foreach($values as $k=>$v)setsetting($k,$v);});flash('Mağaza ayarları kaydedildi.');go('admin/settings');
 }
 if($action==='legal'){foreach(['Gizlilik Politikası','KVKK','Mesafeli Satış','İade ve Değişim','Çerez Politikası'] as $i=>$t)setsetting('legal_'.$t,field('legal'.$i,30000));flash('Sayfa içerikleri kaydedildi.');go('admin/legal');}
 if($action==='admin_password'){rate('adminpw',10);$cur=field('current_password',200);$new=field('new_password',200);$conf=field('confirm_password',200);if(!password_verify($cur,setting('admin_password')))throw new RuntimeException('Mevcut şifre hatalı.');if(strlen($new)<12)throw new RuntimeException('Yeni şifre en az 12 karakter olmalı.');if($new!==$conf)throw new RuntimeException('Yeni şifreler eşleşmiyor.');setsetting('admin_password',password_hash($new,PASSWORD_DEFAULT));session_regenerate_id(true);$_SESSION['admin']=true;$_SESSION['admin_time']=time();flash('Yönetici şifresi değiştirildi.');go('admin/settings');}
 if($action==='order_update'){
 $done=transaction(function(){$o=query('SELECT * FROM orders WHERE id=?',[field('id',40)])->fetch();if(!$o)throw new RuntimeException('Sipariş bulunamadı.');$s=field('status',20);$next=orderNext($o);if(!in_array($s,$next,true))throw new RuntimeException('Bu durum değişikliğine izin verilmiyor. Kart ödemesi yalnızca ödeme kuruluşundan onaylanır.');$track=field('tracking',100);if(in_array($s,['shipped','in_transit','delivered'],true)&&!$track)throw new RuntimeException('Kargo firması ve takip numarası girin.');if($s==='paid'&&in_array($o['status'],['expired','review'],true)&&$o['released']){foreach(json_decode($o['items'],true) as $it){if(itemAvailable($it)<(int)$it['qty'])throw new RuntimeException($it['name'].' için yeterli stok yok. Önce stok ekleyin, sonra siparişi onaylayın.');}foreach(json_decode($o['items'],true) as $it)reserveItemStock($it);query('UPDATE orders SET released=0 WHERE id=?',[$o['id']]);}if($s==='cancelled'||($s==='refunded'&&in_array($o['status'],['paid','preparing'],true)))releaseOrder($o);query('UPDATE orders SET tracking=? WHERE id=?',[$track,$o['id']]);changeOrderStatus($o['id'],$s,'admin');return ['id'=>$o['id'],'status'=>$s];});if($done['status']==='shipped')notifyOrder($done['id'],'shipped');flash('Sipariş güncellendi.');go('admin/orders');
 }
}
function priceinput(string $key): int {$v=str_replace(',','.',field($key,20));if($v==='')return 0;if(!preg_match('/^\d{1,8}(\.\d{1,2})?$/',$v))throw new RuntimeException('Fiyatı 2499.90 biçiminde girin.');return (int)round((float)$v*100);}
