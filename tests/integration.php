<?php
declare(strict_types=1);
$root=dirname(__DIR__);$run=$root.'/test-output/run-'.date('Ymd-His').'-'.bin2hex(random_bytes(3));mkdir($run,0700,true);
putenv('BLUEVERA_STORAGE='.$run);require $root.'/app/bootstrap.php';require $root.'/app/payments.php';
query('UPDATE products SET active=1 WHERE demo=1');
$checks=[];
function ok(bool $condition,string $name):void{global $checks;if(!$condition)throw new RuntimeException('FAILED: '.$name);$checks[]=$name;}
$token=bin2hex(random_bytes(24));file_put_contents($run.'/install-token.txt',$token);
$desc=[0=>['pipe','r'],1=>['file',$run.'/server.log','a'],2=>['file',$run.'/server.log','a']];
$proc=proc_open([PHP_BINARY,'-S','127.0.0.1:8098','-t',$root.'/public'],$desc,$pipes,$root);
if(!is_resource($proc))throw new RuntimeException('Test server failed');
$base='http://127.0.0.1:8098/';$cookie=$run.'/cookies.txt';
function request(string $path,array $post=null,bool $cookies=true):array{global $base,$cookie;static $client; if(!$client){$client=curl_init();curl_setopt($client,CURLOPT_COOKIEFILE,'');} $c=$cookies?$client:curl_init(); curl_setopt($c,CURLOPT_URL,$base.$path); curl_setopt($c,CURLOPT_HTTPGET,true); $opts=[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>10,CURLOPT_FOLLOWLOCATION=>false];if($post!==null){$opts[CURLOPT_POST]=true;$opts[CURLOPT_POSTFIELDS]=count(array_filter($post,fn($v)=>$v instanceof CURLFile))?$post:http_build_query($post);}curl_setopt_array($c,$opts);$body=curl_exec($c);$code=curl_getinfo($c,CURLINFO_HTTP_CODE);$redirect=curl_getinfo($c,CURLINFO_REDIRECT_URL);if(!$cookies)curl_close($c);return [$code,(string)$body,$redirect];}
function csrfFrom(string $html):string{preg_match('/name="csrf" value="([a-f0-9]+)"/',$html,$m);return $m[1]??'';}
function post(string $action,array $data=[],string $page='cart'):array{$r=request('index.php');return request('index.php?page='.$page,['action'=>$action,'csrf'=>csrfFrom($r[1])]+$data);}
try{
 for($i=0;$i<20;$i++){usleep(100000);$r=request('index.php');if($r[0]===200)break;}
 ok($r[0]===200&&str_contains($r[1],'Hayatına iyi gelen'),'Home renders');
 ok(request('index.php?page=admin')[0]===302,'Admin protected');
 $r=request('install.php');$csrf=csrfFrom($r[1]);
 $r=request('install.php',['csrf'=>$csrf,'token'=>'wrong','email'=>'owner@example.test','password'=>'TestPassword123!','confirm'=>'TestPassword123!']);ok(str_contains($r[1],'Kurulum anahtarı geçersiz'),'Install token required');
 $r=request('install.php',['csrf'=>$csrf,'token'=>$token,'email'=>'owner@example.test','password'=>'TestPassword123!','confirm'=>'TestPassword123!']);ok($r[0]===302,'Install succeeds');ok(request('install.php')[0]===403,'Installer locked');
 ok(password_verify('TestPassword123!',setting('admin_password')),'Admin password hashed');
 $r=request('index.php?page=cart',['action'=>'cart','csrf'=>'invalid','id'=>1,'qty'=>1]);ok(str_contains($r[1],'Oturum doğrulanamadı'),'CSRF rejected');
 $r=post('cart',['id'=>1,'qty'=>2]);ok($r[0]===302,'Cart add');$r=request('index.php?page=cart');ok(str_contains($r[1],'4.998,00'),'Cart total server calculated');
 $r=post('cart',['id'=>1,'qty'=>999]);ok(str_contains($r[1],'stok bulunmuyor'),'Overstock rejected');
 $r=post('admin_login',['email'=>'owner@example.test','password'=>'wrong'],'admin/login');ok(str_contains($r[1],'şifre hatalı'),'Wrong login rejected');
 $r=post('admin_login',['email'=>'owner@example.test','password'=>'TestPassword123!'],'admin/login');ok($r[0]===302,'Admin login');
 ok(request('index.php?page=admin/products')[0]===200,'Admin products render');
 $r=post('category_save',['name'=>'Test Category'],'admin/categories');ok($r[0]===302,'Create category');
 $r=post('product_save',['vat_rate'=>'20','name'=>'Test Product','category'=>'Test Category','description'=>'Test description','price'=>'100.00','old_price'=>'120.00','stock'=>3,'active'=>1],'admin/edit');ok($r[0]===302,'Product creation');
 $pid=(int)query("SELECT id FROM products WHERE name='Test Product'")->fetchColumn();ok($pid>0,'Product persisted');
 $upload=tempnam(sys_get_temp_dir(),'bv-test-');file_put_contents($upload,base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aH4sAAAAASUVORK5CYII='));
 try{$r=post('product_save',['vat_rate'=>'20','id'=>$pid,'name'=>'Test Product','category'=>'Test Category','description'=>'Uploaded product','price'=>'100.00','stock'=>3,'active'=>1,'image'=>new CURLFile($upload,'image/png','product.png')],'admin/edit');ok($r[0]===302,'Product image upload');$uploaded=query('SELECT image FROM products WHERE id=?',[$pid])->fetchColumn();ok(str_starts_with($uploaded,'uploads/')&&is_file($root.'/public/'.$uploaded),'Uploaded image persisted');if(str_starts_with($uploaded,'uploads/')&&is_file($root.'/public/'.$uploaded))unlink($root.'/public/'.$uploaded);query("UPDATE products SET image='assets/placeholder.svg' WHERE id=?",[$pid]);
 file_put_contents($upload,'<?php echo "bad";');$r=post('product_save',['vat_rate'=>'20','id'=>$pid,'name'=>'Test Product','category'=>'Test Category','description'=>'Invalid image','price'=>'100.00','stock'=>3,'active'=>1,'image'=>new CURLFile($upload,'image/png','fake.png')],'admin/edit');ok(str_contains($r[1],'görsel seçin'),'Disguised executable upload rejected');}finally{unlink($upload);}
 $r=post('product_save',['vat_rate'=>'20','id'=>$pid,'name'=>'Updated Product','category'=>'Test Category','description'=>'<script>alert(1)</script>','price'=>'100.00','stock'=>3,'active'=>1],'admin/edit');ok($r[0]===302,'Product edit');
 $r=request('index.php?page=product&id='.$pid);ok(str_contains($r[1],'&lt;script&gt;')&&!str_contains($r[1],'<script>alert(1)'),'Product HTML escaped');
 $r=post('product_hide',['id'=>$pid],'admin/products');ok($r[0]===302&&request('index.php?page=product&id='.$pid)[0]===404,'Product unpublish');query('UPDATE products SET active=1 WHERE id=?',[$pid]);
 post('cart',['id'=>1,'qty'=>0]);post('cart',['id'=>$pid,'qty'=>2]);
 $r=request('index.php?page=checkout');preg_match('/name="request_key" value="([a-f0-9]+)"/',$r[1],$m);$req=$m[1];$csrf=csrfFrom($r[1]);
 $payload=['action'=>'checkout','csrf'=>$csrf,'request_key'=>$req,'name'=>'Test Customer','email'=>'customer@example.test','phone'=>'05550000000','address'=>'Test Mahallesi Test Sokak No 1 Istanbul','method'=>'bank','terms'=>'1'];
 $r=request('index.php?page=checkout',$payload);ok(str_contains($r[1],'tanıtım modunda'),'Sales disabled enforced');
 foreach(['sales_enabled'=>'1','bank_enabled'=>'1','bank_owner'=>'Test','iban'=>'TEST IBAN','company'=>'Test Company','address'=>'Test address','phone'=>'05550000000','support_email'=>'support@example.test','return_carrier'=>'Test Carrier','delivery_days'=>'3-5 iş günü','mail_from'=>'mail@example.test','mail_verified'=>'1','legal_ready'=>'1'] as $k=>$v)setsetting($k,$v);
 $r=request('index.php?page=checkout',$payload);ok($r[0]===302,'Checkout creates order');
 $o=query('SELECT * FROM orders WHERE request_key=?',[$req])->fetch();ok($o&&(int)$o['total']===27990,'Trusted prices and shipping');ok((bool)preg_match('/^[0-9]{6}$/',$o['tracking_code']),'Unique tracking code generated');$r=post('track',['order_id'=>$o['tracking_code'],'email'=>'customer@example.test'],'track');ok($r[0]===302,'Lookup by tracking code');$r=post('track',['order_id'=>$o['tracking_code'],'email'=>'other@example.test'],'track');ok(str_contains($r[1],'eşleşmedi'),'Tracking code still requires matching email');ok((int)query('SELECT stock FROM products WHERE id=?',[$pid])->fetchColumn()===1,'Stock reserved');
 $r=request('index.php?page=checkout',$payload);ok($r[0]===302&&(int)query('SELECT COUNT(*) FROM orders')->fetchColumn()===1,'Checkout retry idempotent');
 ok(request('index.php?page=order&id='.$o['id'].'&key=wrong')[0]===404,'Order secret enforced');
 post('cart',['id'=>$pid,'qty'=>1]);$r=request('index.php?page=checkout');preg_match('/name="request_key" value="([a-f0-9]+)"/',$r[1],$m);ok($m[1]!==$req,'Next basket gets new checkout key');
 query('UPDATE orders SET expires=? WHERE id=?',[time()-1,$o['id']]);request('index.php');ok((int)query('SELECT stock FROM products WHERE id=?',[$pid])->fetchColumn()===3,'Expired stock released');request('index.php');ok((int)query('SELECT stock FROM products WHERE id=?',[$pid])->fetchColumn()===3,'Expiry release idempotent');
 setsetting('merchant_key','test-secret');setsetting('merchant_salt','test-salt');
 query("UPDATE orders SET method='card',status='pending',released=0,expires=?,test_mode=0 WHERE id=?",[time()+3600,$o['id']]);query('UPDATE products SET stock=1 WHERE id=?',[$pid]);
 $cb=['merchant_oid'=>$o['id'],'status'=>'success','total_amount'=>(string)$o['total'],'hash'=>'forged'];$r=request('callback.php',$cb,false);ok($r[0]===400,'Forged payment rejected');
 $cb['total_amount']='1';$cb['hash']=base64_encode(hash_hmac('sha256',$o['id'].'test-salt'.'success'.'1','test-secret',true));ok(request('callback.php',$cb,false)[0]===400,'Signed wrong amount rejected');
 $cb['total_amount']=(string)$o['total'];$cb['hash']=base64_encode(hash_hmac('sha256',$o['id'].'test-salt'.'success'.$o['total'],'test-secret',true));$r=request('callback.php',$cb,false);ok($r[0]===200&&$r[1]==='OK','Valid callback accepted');ok(query('SELECT status FROM orders WHERE id=?',[$o['id']])->fetchColumn()==='paid','Callback confirms payment');request('callback.php',$cb,false);ok((int)query('SELECT stock FROM products WHERE id=?',[$pid])->fetchColumn()===1,'Duplicate callback does not mutate stock');
 $r=post('order_update',['id'=>$o['id'],'status'=>'preparing'],'admin/orders');ok($r[0]===302,'Prepare paid order');request('callback.php',$cb,false);ok(query('SELECT status FROM orders WHERE id=?',[$o['id']])->fetchColumn()==='preparing','Duplicate callback preserves preparing status');
 $r=post('order_update',['id'=>$o['id'],'status'=>'shipped','tracking'=>''],'admin/orders');ok(str_contains($r[1],'takip numarası girin'),'Shipment requires carrier tracking');
 foreach(['shipped','in_transit','delivered'] as $stage){$r=post('order_update',['id'=>$o['id'],'status'=>$stage,'tracking'=>'Test Kargo 123456'],'admin/orders');ok($r[0]===302,'Delivery stage '.$stage);if($stage==='in_transit'){request('callback.php',$cb,false);ok(query('SELECT status FROM orders WHERE id=?',[$o['id']])->fetchColumn()==='in_transit','Duplicate callback preserves transit');}}
 $r=request('index.php?page=order&id='.$o['id'].'&key='.$o['secret']);ok(str_contains($r[1],'Durum geçmişi')&&str_contains($r[1],'Yolda')&&str_contains($r[1],$o['tracking_code']),'Customer sees code and delivery progress');
 ok((int)query("SELECT COUNT(*) FROM order_events WHERE order_id=? AND source='admin'",[$o['id']])->fetchColumn()===4,'Delivery history saved once per change');
 $r=post('order_update',['id'=>$o['id'],'status'=>'preparing'],'admin/orders');ok(str_contains($r[1],'izin verilmiyor'),'Delivered order cannot move backward');
 query("UPDATE orders SET status='expired',released=1 WHERE id=?",[$o['id']]);request('callback.php',$cb,false);ok(query('SELECT status FROM orders WHERE id=?',[$o['id']])->fetchColumn()==='review','Late payment enters review');
 query("UPDATE orders SET status='pending',released=0,test_mode=1 WHERE id=?",[$o['id']]);request('callback.php',$cb,false);ok(query('SELECT status FROM orders WHERE id=?',[$o['id']])->fetchColumn()==='test_paid','Test payment never becomes live sale');ok((int)query('SELECT stock FROM products WHERE id=?',[$pid])->fetchColumn()===3,'Test payment releases stock');
 query("UPDATE orders SET status='pending',released=0,test_mode=0 WHERE id=?",[$o['id']]);query('UPDATE products SET stock=1 WHERE id=?',[$pid]);$cb['status']='failed';$cb['hash']=base64_encode(hash_hmac('sha256',$o['id'].'test-salt'.'failed'.$o['total'],'test-secret',true));request('callback.php',$cb,false);request('callback.php',$cb,false);ok((int)query('SELECT stock FROM products WHERE id=?',[$pid])->fetchColumn()===3,'Failed payment releases stock once');
 $r=post('register',['name'=>'Example Member','email'=>'member@example.test','password'=>'MemberPassword123!'],'register');ok($r[0]===302,'Member registration');$r=request('index.php?page=account');ok(str_contains($r[1],'Example Member'),'Account renders');
 $r=post('track',['order_id'=>$o['id'],'email'=>'wrong@example.test'],'track');ok(str_contains($r[1],'eşleşmedi'),'Tracking email required');$r=post('track',['order_id'=>$o['id'],'email'=>'customer@example.test'],'track');ok($r[0]===302,'Order tracking works');
 $r=post('login',['email'=>'owner@example.test','password'=>'TestPassword123!'],'account');ok($r[0]===302&&str_contains($r[2],'page=admin'),'Hesabim admin login');ok(request('index.php?page=account')[0]===302,'Admin account redirects');
 $r=post('order_update',['id'=>$o['id'],'status'=>'paid'],'admin/orders');ok(str_contains($r[1],'izin verilmiyor'),'Manual card confirmation blocked');
 $r=request('index.php?page=admin/edit');ok(str_contains($r[1],'<select name="category"')&&str_contains($r[1],'Kulaklıklar'),'Category dropdown renders');
 $r=post('category_save',['name'=>'Test Category'],'admin/categories');ok(str_contains($r[1],'zaten mevcut'),'Duplicate category rejected');
 $categoryId=query("SELECT id FROM categories WHERE name='Test Category'")->fetchColumn();$r=post('category_save',['id'=>$categoryId,'name'=>'Renamed Category'],'admin/categories');ok($r[0]===302&&query('SELECT category FROM products WHERE id=?',[$pid])->fetchColumn()==='Renamed Category','Category rename updates products');
 $branding=['shop_name'=>'Example Store'];foreach(themeDefaults() as $k=>$v)$branding['color_'.$k]=$v;$branding['color_teal']='#123456';$r=post('branding',$branding,'admin/branding');ok($r[0]===302,'Branding saved');$r=request('index.php');ok(str_contains($r[1],'Example Store —')&&str_contains($r[1],'theme.php'),'Brand name applied');ok(str_contains(request('theme.php')[1],'--teal:#123456'),'Theme saved and rendered');
 $branding['color_teal']='invalid';$r=post('branding',$branding,'admin/branding');ok(str_contains($r[1],'Geçerli bir renk'),'Invalid theme color rejected');
 $r=request('index.php?page=info&title='.urlencode('İade ve Değişim'));ok(str_contains($r[1],'14 gün')&&str_contains($r[1],'Ayıplı ürünler'),'Return policy populated');
 $digits=preg_replace('/[^0-9]/','',$o['tracking_code']);foreach([$digits,'bv'.$digits,'BV-'.substr($digits,0,3).'-'.substr($digits,3)] as $input){$r=post('track',['order_id'=>$input,'email'=>'customer@example.test'],'track');ok($r[0]===302,'Human friendly tracking input '.$input);}
 $long='TK-ABCDE-01234-FABCD-56789';query('UPDATE orders SET tracking_code=? WHERE id=?',[$long,$o['id']]);setsetting('short_tracking_version','0');request('index.php');$updated=query('SELECT * FROM orders WHERE id=?',[$o['id']])->fetch();ok((bool)preg_match('/^[0-9]{6}$/',$updated['tracking_code'])&&$updated['previous_tracking_code']===$long,'Legacy tracking migrated with alias');$r=post('track',['order_id'=>$long,'email'=>'customer@example.test'],'track');ok($r[0]===302,'Old long tracking code remains valid');

 ok(str_contains(query('SELECT contract_snapshot FROM orders WHERE id=?',[$o['id']])->fetchColumn(),'ÖN BİLGİLENDİRME'),'Order contract snapshot stored');
 ok((int)query('SELECT COUNT(*) FROM mail_queue WHERE dedupe LIKE ?',[$o['id'].':%'])->fetchColumn()>=2,'Transactional notifications queued');
 $originalLegal=setting('legal_KVKK');setsetting('legal_KVKK','Bu sayfa henüz hazırlanmadı');ok(count(launchProblems())>0,'Placeholder blocks launch even with checkbox');setsetting('legal_KVKK',$originalLegal);
 query("UPDATE orders SET method='bank',status='expired',released=1 WHERE id=?",[$o['id']]);
 $r=post('order_update',['id'=>$o['id'],'status'=>'review','note'=>'Geç havale kontrolü'],'admin/orders');ok($r[0]===302,'Expired bank order can enter review');
 query('UPDATE products SET stock=0 WHERE id=?',[$pid]);$r=post('order_update',['id'=>$o['id'],'status'=>'paid'],'admin/orders');ok(str_contains($r[1],'Stok yetersiz'),'Late bank payment cannot oversell');
 query('UPDATE products SET stock=3 WHERE id=?',[$pid]);$r=post('order_update',['id'=>$o['id'],'status'=>'paid'],'admin/orders');ok($r[0]===302&&(int)query('SELECT stock FROM products WHERE id=?',[$pid])->fetchColumn()===1,'Late bank payment reserves stock atomically');
 $r=post('order_update',['id'=>$o['id'],'status'=>'refund_pending','note'=>'Cayma bildirimi'],'admin/orders');ok($r[0]===302,'Paid bank order enters refund flow');
 $r=post('order_update',['id'=>$o['id'],'status'=>'returned','note'=>'Ürün kontrol edildi','restock'=>'1'],'admin/orders');ok($r[0]===302&&(int)query('SELECT stock FROM products WHERE id=?',[$pid])->fetchColumn()===3,'Return optionally restores stock');
 $r=post('order_update',['id'=>$o['id'],'status'=>'refunded','note'=>'Banka iade referansı TEST'],'admin/orders');ok($r[0]===302,'Refund completion recorded');
 $r=post('order_update',['id'=>$o['id'],'status'=>'returned','note'=>'tekrar','restock'=>'1'],'admin/orders');ok((int)query('SELECT stock FROM products WHERE id=?',[$pid])->fetchColumn()===3,'Return cannot restore stock twice');

 query("UPDATE orders SET status='confirmed' WHERE id=?",[$o['id']]);
 post('logout');
 $orderPage='order&id='.$o['id'].'&key='.$o['secret'];
 $r=post('return_request',['order_id'=>$o['id'],'order_key'=>'wrong','reason'=>'Test'],$orderPage);ok(str_contains($r[1],'doğrulanamadı'),'Return requires order access secret');
 $r=post('return_request',['order_id'=>$o['id'],'order_key'=>$o['secret'],'reason'=>'Vazgeçtim <script>alert(1)</script>'],$orderPage);ok($r[0]===302,'Customer submits return for received order');
 $returnId=query('SELECT id FROM return_requests WHERE order_id=?',[$o['id']])->fetchColumn();
 $r=post('return_request',['order_id'=>$o['id'],'order_key'=>$o['secret'],'reason'=>'Tekrar'],$orderPage);ok((int)query('SELECT COUNT(*) FROM return_requests')->fetchColumn()===1,'Repeated return submit is idempotent');
 $r=request('index.php?page='.$orderPage);ok(str_contains($r[1],'Admin onayı bekleniyor')&&str_contains($r[1],'&lt;script&gt;'),'Pending return visible and escaped');
 $r=post('return_decide',['request_id'=>$returnId,'decision'=>'approved','reply'=>'Onaylandı'],'login');ok($r[0]===302&&query('SELECT status FROM return_requests WHERE id=?',[$returnId])->fetchColumn()==='pending','Customer cannot approve own return');
 post('login',['email'=>'owner@example.test','password'=>'TestPassword123!'],'account');
 $r=post('return_decide',['request_id'=>$returnId,'decision'=>'rejected','reply'=>''],'admin/returns');ok(str_contains($r[1],'açıklamayı yazın'),'Decision requires customer explanation');
 $r=post('return_decide',['request_id'=>$returnId,'decision'=>'rejected','reply'=>'Ek bilgi ile yeniden iletin.'],'admin/returns');ok($r[0]===302,'Admin rejection recorded');
 query("UPDATE orders SET status='delivered' WHERE id=?",[$o['id']]);
 $r=post('return_request',['order_id'=>$o['id'],'order_key'=>$o['secret'],'reason'=>''],'order&id='.$o['id'].'&key='.$o['secret']);ok($r[0]===302,'Delivered return can be submitted without reason');
 $returnId=query('SELECT MAX(id) FROM return_requests')->fetchColumn();
 $r=post('return_decide',['request_id'=>$returnId,'decision'=>'approved','reply'=>'İade adresine belirtilen taşıyıcıyla gönderin.'],'admin/returns');ok($r[0]===302,'Admin approval recorded');
 ok(query('SELECT status FROM orders WHERE id=?',[$o['id']])->fetchColumn()==='delivered','Approval does not fabricate refund or change fulfilment');
 $r=request('index.php?page='.$orderPage);ok(str_contains($r[1],'İade talebi onaylandı')&&str_contains($r[1],'belirtilen taşıyıcıyla'),'Customer sees approval and instructions');
 $r=post('return_decide',['request_id'=>$returnId,'decision'=>'rejected','reply'=>'Tekrar karar'],'admin/returns');ok(str_contains($r[1],'daha önce'),'Decision cannot be overwritten');

 $r=request('index.php?page='.$orderPage);preg_match('/<ol class="delivery-steps">(.*?)<\/ol>/s',$r[1],$returnSteps);ok(str_contains($returnSteps[1],'İade talebi onaylandı')&&!str_contains($returnSteps[1],'Sipariş alındı'),'Approved return replaces delivery steps');
 foreach(['returned','refunded'] as $state){query('UPDATE orders SET status=? WHERE id=?',[$state,$o['id']]);$progress=returnProgress(query('SELECT * FROM orders WHERE id=?',[$o['id']])->fetch());ok($progress['current']===$state,'Return progress follows '.$state);}

 $r=request('index.php?page='.$orderPage);ok(str_contains($r[1],'İade işlemleri — Talep oluşturuldu')&&str_contains($r[1],'İade işlemleri — Talep onaylandı')&&str_contains($r[1],'İade işlemleri — Talep reddedildi'),'History includes request and both decisions');
 $historyCount=(int)query("SELECT COUNT(*) FROM order_events WHERE status IN ('return_requested','return_approved','return_rejected') AND order_id=?",[$o['id']])->fetchColumn();ok($historyCount===4,'Return history recorded once per action');
 setsetting('return_history_version','0');request('index.php');ok((int)query("SELECT COUNT(*) FROM order_events WHERE status IN ('return_requested','return_approved','return_rejected') AND order_id=?",[$o['id']])->fetchColumn()===$historyCount,'History migration does not duplicate events');
 ok((int)query("SELECT COUNT(*) FROM mail_queue WHERE dedupe LIKE 'return:%'")->fetchColumn()===6,'Return notifications queued once');
 query("UPDATE orders SET status='refunded' WHERE id=?",[$o['id']]);
 $r=post('password_change',['current_password'=>'wrong','new_password'=>'NewAdminPassword123!','confirm_password'=>'NewAdminPassword123!'],'admin/security');ok(str_contains($r[1],'Mevcut şifreyi'),'Password change requires current password');
 $r=post('password_change',['current_password'=>'TestPassword123!','new_password'=>'NewAdminPassword123!','confirm_password'=>'NewAdminPassword123!'],'admin/security');ok($r[0]===302&&password_verify('NewAdminPassword123!',setting('admin_password')),'Admin password change works');
 setsetting('site_url','https://example.test');$r=post('password_forgot',['email'=>'member@example.test'],'forgot');ok($r[0]===302,'Reset request generic response');
 $message=query("SELECT body FROM mail_queue WHERE dedupe LIKE 'reset:%' ORDER BY id DESC LIMIT 1")->fetchColumn();preg_match('/token=([a-f0-9]{64})/',$message,$resetMatch);ok(!empty($resetMatch[1]),'Reset link queued');
 $resetToken=$resetMatch[1];$r=post('password_reset',['token'=>$resetToken,'new_password'=>'ResetMemberPass123!','confirm_password'=>'ResetMemberPass123!'],'reset');ok($r[0]===302&&password_verify('ResetMemberPass123!',query('SELECT password FROM users WHERE email=?',['member@example.test'])->fetchColumn()),'Single use reset updates password');
 $r=post('password_reset',['token'=>$resetToken,'new_password'=>'AnotherMemberPass123!','confirm_password'=>'AnotherMemberPass123!'],'reset');ok(str_contains($r[1],'kullanılmış'),'Reset token cannot be replayed');
 $_SERVER['REMOTE_ADDR']='198.51.100.1';$_SERVER['HTTP_CF_CONNECTING_IP']='203.0.113.2';ok(clientIp()==='198.51.100.1','Untrusted forwarding header ignored');putenv('BLUEVERA_TRUSTED_PROXIES=198.51.100.1');ok(clientIp()==='203.0.113.2','Explicit trusted proxy supported');putenv('BLUEVERA_TRUSTED_PROXIES');
 post('logout');post('login',['email'=>'member@example.test','password'=>'ResetMemberPass123!'],'account');ok(request('index.php?page=admin')[0]===302,'Customer has no admin access');

 ok(vatAmount(['price'=>12000,'qty'=>2,'vat_rate'=>20])===4000,'Inclusive VAT arithmetic');
 ok(json_decode(query('SELECT invoice_json FROM orders WHERE id=?',[$o['id']])->fetchColumn(),true)['type']==='personal','Invoice data retained');
 $line=json_decode(query('SELECT items FROM orders WHERE id=?',[$o['id']])->fetchColumn(),true)[0];ok(isset($line['vat_rate'])&&!isset($line['description']),'Order line stores only required fields');
 setsetting('card_enabled','0');try{paytrToken($o);ok(false,'Disabled card token');}catch(RuntimeException $ex){ok(str_contains($ex->getMessage(),'kapalı'),'Disabled cards cannot start payment');}
 post('cart',['id'=>$pid,'qty'=>1]);$r=request('index.php?page=checkout');ok(str_contains($r[1],'Ön bilgilendirme')&&str_contains($r[1],'Ödeme yükümlülüğüyle'),'Precontract and explicit payment button');
 ok(!str_contains(file_get_contents($root.'/public/assets/style.css'),'fonts.googleapis.com'),'No external font request');
 require_once $root.'/app/contrast.php';foreach(['#fffefa','#000000','#888888','#ffdd00'] as $bg)ok(contrastRatio(readableColor('#999999',$bg),$bg)>=4.5,'Readable text contrast '.$bg);
 file_put_contents($root.'/test-output/results.txt',implode("\n",$checks)."\nPASS: ".count($checks)." checks\n");
 echo 'PASS: '.count($checks)." integration checks\n";
}finally{proc_terminate($proc);proc_close($proc);}
