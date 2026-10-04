<?php
require dirname(__DIR__).'/app/bootstrap.php';
if(setting('admin_email')){http_response_code(403);exit('Kurulum tamamlandı. Yönetim paneli: index.php?page=admin/login');}
$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 try{
  checkcsrf();rate('install',10);
  $expected=is_file(STORE.'/install-token.txt')?trim(file_get_contents(STORE.'/install-token.txt')):'';
  if(strlen($expected)<32||!hash_equals($expected,field('token',100)))throw new RuntimeException('Kurulum anahtarı geçersiz. storage/install-token.txt dosyasındaki anahtarı kullanın.');
  $email=strtolower(field('email',100));$password=field('password',200);
  if(!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($password)<12)throw new RuntimeException('Geçerli e-posta ve en az 12 karakterli bir şifre girin.');
  if(!hash_equals($password,field('confirm',200)))throw new RuntimeException('Şifreler eşleşmiyor.');
  transaction(function()use($email,$password){if(setting('admin_email'))throw new RuntimeException('Kurulum zaten tamamlandı.');setsetting('admin_email',$email);setsetting('admin_password',password_hash($password,PASSWORD_DEFAULT));});
  session_regenerate_id(true);flash('Kurulum tamamlandı. Yönetici hesabınızla giriş yapabilirsiniz.');go('admin/login');
 }catch(RuntimeException $ex){$error=$ex->getMessage();}catch(Throwable $ex){error_log($ex->getMessage());$error='Kurulum tamamlanamadı. Klasör yazma izinlerini kontrol edin.';}
}
?><!doctype html><html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>BlueVera · İlk kurulum</title><link rel="icon" href="assets/favicon.svg"><link rel="stylesheet" href="assets/style.css"></head><body><main class="wrap"><section class="auth-panel"><a class="brand" href="index.php"><img src="assets/favicon.svg" alt=""><span>BLUE<span>VERA</span></span></a><h1>Mağazana hoş geldin.</h1><p>Yönetici hesabını oluştur. Kurulum anahtarını paketindeki <strong>storage/install-token.txt</strong> dosyasından alabilirsin.</p><?php if($error):?><div class="notice error" role="alert"><?=e($error)?></div><?php endif?><form method="post" class="form-grid"><?=csrf()?><label>Kurulum anahtarı<input name="token" required autocomplete="off"></label><label>Yönetici e-postası<input name="email" type="email" required autocomplete="username"></label><label>Şifre (en az 12 karakter)<input name="password" type="password" minlength="12" required autocomplete="new-password"></label><label>Şifre tekrar<input name="confirm" type="password" minlength="12" required autocomplete="new-password"></label><button class="button dark">Mağazamı kur ↗</button></form><p>Hesap oluşturulduğunda kurulum otomatik kilitlenir.</p></section></main></body></html>
