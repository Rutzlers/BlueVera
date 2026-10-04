<?php
function safetyActions(string $action): bool {
 if($action==='social_settings'){
  requireadmin();$values=[];foreach(['google','apple'] as $p){$values[$p.'_client_id']=field($p.'_client_id',255);$secret=field($p.'_client_secret',5000);if($secret!=='')$values[$p.'_client_secret']=$secret;$values[$p.'_login_enabled']=isset($_POST[$p.'_login_enabled'])?'1':'0';if($values[$p.'_login_enabled']==='1'&&(!seoBase()||!$values[$p.'_client_id']||!($secret?:setting($p.'_client_secret'))))throw new RuntimeException('Önce HTTPS site adresini ve sağlayıcı bilgilerini tamamlayın.');}
  transaction(function()use($values){foreach(['google','apple'] as $p)if(($values[$p.'_login_enabled']??'0')!==setting($p.'_login_enabled','0')){setsetting('legal_ready','0');setsetting('sales_enabled','0');}foreach($values as $k=>$v)setsetting($k,$v);});flash('Google / Apple giriş ayarları kaydedildi.');go('admin/security');
 }

 if($action==='password_change'){
  rate('password-change',10);$isadmin=admin();$u=!empty($_SESSION['user'])?query('SELECT * FROM users WHERE id=?',[$_SESSION['user']])->fetch():false;
  if(!$isadmin&&!$u)throw new RuntimeException('Önce giriş yapın.');
  $hash=$isadmin?setting('admin_password'):$u['password'];$new=field('new_password',200);
  if(!password_verify(field('current_password',200),$hash)||strlen($new)<12||$new!==field('confirm_password',200))throw new RuntimeException('Mevcut şifreyi ve en az 12 karakterli eşleşen yeni şifreleri kontrol edin.');
  $hash=password_hash($new,PASSWORD_DEFAULT);$email=$isadmin?setting('admin_email'):$u['email'];
  transaction(function()use($isadmin,$u,$hash,$email){if($isadmin)setsetting('admin_password',$hash);else query('UPDATE users SET password=? WHERE id=?',[$hash,$u['id']]);query('DELETE FROM reset_tokens WHERE email=?',[$email]);});
  session_regenerate_id(true);if($isadmin)$_SESSION['admin_revision']=$hash;else $_SESSION['user_revision']=$hash;
  flash('Şifreniz değiştirildi. Diğer oturumlar geçersiz kılındı.');go($isadmin?'admin/security':'account');
 }
 if($action==='password_forgot'){
  rate('password-reset',5);$email=strtolower(field('email',100));
  if(filter_var($email,FILTER_VALIDATE_EMAIL)&&seoBase()&&setting('mail_verified')==='1'&&($email===strtolower(setting('admin_email'))||query('SELECT id FROM users WHERE email=?',[$email])->fetchColumn())){
   $token=bin2hex(random_bytes(32));transaction(function()use($email,$token){query('DELETE FROM reset_tokens WHERE email=? OR expires<?',[$email,time()]);query('INSERT INTO reset_tokens VALUES(?,?,?)',[hash('sha256',$token),$email,time()+1800]);queueMail('reset:'.hash('sha256',$token),$email,shopName().' şifre yenileme','Bu bağlantı 30 dakika geçerlidir: '.seoAbsolute(url('reset',['token'=>$token]))."\nTalebi siz yapmadıysanız bu iletiyi yok sayabilirsiniz.");});
  }
  flash('Uygun bir hesap varsa yenileme bağlantısı gönderim sırasına alındı.');go('forgot');
 }
 if($action==='password_reset'){
  rate('password-reset-use',10);$token=field('token',64);$new=field('new_password',200);
  if(!preg_match('/^[a-f0-9]{64}$/',$token)||strlen($new)<12||$new!==field('confirm_password',200))throw new RuntimeException('Geçerli bağlantı ve en az 12 karakterli eşleşen şifreler gerekli.');
  transaction(function()use($token,$new){$r=query('SELECT * FROM reset_tokens WHERE token_hash=? AND expires>?',[hash('sha256',$token),time()])->fetch();if(!$r)throw new RuntimeException('Bağlantının süresi dolmuş veya kullanılmış.');$hash=password_hash($new,PASSWORD_DEFAULT);if($r['email']===strtolower(setting('admin_email')))setsetting('admin_password',$hash);else query('UPDATE users SET password=? WHERE email=?',[$hash,$r['email']]);query('DELETE FROM reset_tokens WHERE email=?',[$r['email']]);});
  $_SESSION=[];session_regenerate_id(true);$_SESSION['csrf']=bin2hex(random_bytes(32));flash('Şifreniz yenilendi. Yeni şifrenizle giriş yapabilirsiniz.');go('login');
 }
 if($action==='mail_test'){
  requireadmin();if(!filter_var(setting('mail_from'),FILTER_VALIDATE_EMAIL))throw new RuntimeException('Önce Mağaza ve ödeme ekranına gönderen e-postayı girin ve hosting göndericisini kurun.');rate('mail-test',5);queueMail('mail-test:'.bin2hex(random_bytes(8)),setting('admin_email'),shopName().' teslim testi','Bu mesajı aldıysanız yönetim panelindeki e-posta teslim onayını işaretleyebilirsiniz.');flash('Test iletisi sıraya alındı. Zamanlanmış gönderici çalıştığında gönderilecek.');go('admin/security');
 }
 return false;
}
