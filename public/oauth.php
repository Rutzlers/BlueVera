<?php
require dirname(__DIR__).'/app/bootstrap.php';
header('X-Robots-Tag: noindex, nofollow');
try{
 $p=(string)($_GET['provider']??'');$cfg=socialProvider($p);
 if(!socialReady($p))throw new RuntimeException('Bu giriş yöntemi henüz etkin değil.');
 rate('social-login',30);
 if(isset($_POST['start'])){
  checkcsrf();$state=bin2hex(random_bytes(32));$binder=bin2hex(random_bytes(32));$nonce=bin2hex(random_bytes(32));$verifier=bin2hex(random_bytes(32));
  query('DELETE FROM social_flows WHERE expires<?',[time()]);query('INSERT INTO social_flows VALUES(?,?,?,?,?,?)',[hash('sha256',$state),hash('sha256',$binder),$p,$nonce,$verifier,time()+600]);
  setcookie('BV_OAUTH_'.$p,$binder,['expires'=>time()+600,'path'=>'/','secure'=>true,'httponly'=>true,'samesite'=>'None']);
  $args=['client_id'=>setting($p.'_client_id'),'redirect_uri'=>socialRedirect($p),'response_type'=>'code','scope'=>$cfg['scope'],'state'=>$state,'nonce'=>$nonce];
  if($p==='apple')$args['response_mode']='form_post';else{$args['code_challenge']=rtrim(strtr(base64_encode(hash('sha256',$verifier,true)),'+/','-_'),'=');$args['code_challenge_method']='S256';}
  header('Location: '.$cfg['auth'].'?'.http_build_query($args));exit;
 }
 $response=$p==='apple'?$_POST:$_GET;$state=$response['state']??'';$binder=$_COOKIE['BV_OAUTH_'.$p]??'';
 if(!is_string($state)||!preg_match('/^[a-f0-9]{64}$/',$state)||!is_string($binder)||!preg_match('/^[a-f0-9]{64}$/',$binder))throw new RuntimeException('Giriş oturumu doğrulanamadı.');
 $flow=socialFlow($state,$binder,$p);setcookie('BV_OAUTH_'.$p,'',['expires'=>time()-3600,'path'=>'/','secure'=>true,'httponly'=>true,'samesite'=>'None']);
 if(isset($response['error']))throw new RuntimeException('Giriş iptal edildi.');$code=$response['code']??'';if(!is_string($code)||!$code||strlen($code)>4096)throw new RuntimeException('Giriş kodu geçersiz.');
 $args=['client_id'=>setting($p.'_client_id'),'client_secret'=>setting($p.'_client_secret'),'code'=>$code,'grant_type'=>'authorization_code','redirect_uri'=>socialRedirect($p)];if($p==='google')$args['code_verifier']=$flow['verifier'];
 $token=socialJson($cfg['token'],$args);$keys=socialJson($cfg['keys']);$claims=socialVerify((string)($token['id_token']??''),$keys['keys']??[],$p,setting($p.'_client_id'),$flow['nonce']);$u=socialUser($p,$claims);
 session_regenerate_id(true);unset($_SESSION['admin'],$_SESSION['admin_time'],$_SESSION['admin_revision']);$_SESSION['user']=$u['id'];$_SESSION['user_revision']=$u['password'];$_SESSION['csrf']=bin2hex(random_bytes(32));go('account');
}catch(Throwable $ex){flash($ex instanceof RuntimeException?$ex->getMessage():'Giriş tamamlanamadı. Lütfen tekrar deneyin.');go('login');}
