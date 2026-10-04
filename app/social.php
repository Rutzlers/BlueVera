<?php
function socialProvider(string $p): array {
 $all=['google'=>['auth'=>'https://accounts.google.com/o/oauth2/v2/auth','token'=>'https://oauth2.googleapis.com/token','keys'=>'https://www.googleapis.com/oauth2/v3/certs','issuers'=>['https://accounts.google.com','accounts.google.com'],'scope'=>'openid email profile'], 'apple'=>['auth'=>'https://appleid.apple.com/auth/authorize','token'=>'https://appleid.apple.com/auth/token','keys'=>'https://appleid.apple.com/auth/keys','issuers'=>['https://appleid.apple.com'],'scope'=>'name email']];
 if(!isset($all[$p]))throw new RuntimeException('Giriş sağlayıcısı geçersiz.');return $all[$p];
}
function socialReady(string $p): bool {return seoBase()!==''&&setting($p.'_login_enabled')==='1'&&setting($p.'_client_id')!==''&&setting($p.'_client_secret')!=='';}
function socialRedirect(string $p): string {return seoBase().'/oauth.php?provider='.$p;}
function socialJson(string $url,?array $post=null): array {
 $ch=curl_init($url);$body='';curl_setopt_array($ch,[CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_TIMEOUT=>20,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_WRITEFUNCTION=>function($ch,$data)use(&$body){if(strlen($body)+strlen($data)>262144)return 0;$body.=$data;return strlen($data);}]);
 if($post!==null)curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>http_build_query($post)]);
 $ok=curl_exec($ch);$status=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);$json=json_decode($body,true);
 if(!$ok||$status!==200||!is_array($json))throw new RuntimeException('Giriş servisi yanıt vermedi. Lütfen tekrar deneyin.');return $json;
}
function socialB64(string $s): string {$r=base64_decode(strtr($s,'-_','+/'),true);if($r===false)throw new RuntimeException('Geçersiz kimlik yanıtı.');return $r;}
function socialDer(int $tag,string $value): string {$n=strlen($value);$len=$n<128?chr($n):chr(128+strlen(ltrim(pack('N',$n),"\0"))).ltrim(pack('N',$n),"\0");return chr($tag).$len.$value;}
function socialPem(array $key): string {
 $parts='';foreach(['n','e'] as $field){$v=ltrim(socialB64($key[$field]??''),"\0");if($v==='')throw new RuntimeException('Geçersiz doğrulama anahtarı.');if(ord($v[0])>127)$v="\0".$v;$parts.=socialDer(2,$v);}
 $rsa=socialDer(48,$parts);$der=socialDer(48,hex2bin('300d06092a864886f70d0101010500').socialDer(3,"\0".$rsa));return "-----BEGIN PUBLIC KEY-----\n".chunk_split(base64_encode($der),64,"\n")."-----END PUBLIC KEY-----\n";
}
function socialVerify(string $jwt,array $keys,string $provider,string $client,string $nonce): array {
 $parts=explode('.',$jwt);if(count($parts)!==3)throw new RuntimeException('Geçersiz kimlik yanıtı.');
 $h=json_decode(socialB64($parts[0]),true);$c=json_decode(socialB64($parts[1]),true);
 if(!is_array($h)||!is_array($c)||($h['alg']??'')!=='RS256'||!is_string($h['kid']??null))throw new RuntimeException('Geçersiz kimlik imzası.');
 $valid=false;foreach($keys as $k)if(($k['kid']??'')===$h['kid']&&($k['kty']??'')==='RSA'&&($k['use']??'sig')==='sig'&&($k['alg']??'RS256')==='RS256'){$valid=openssl_verify($parts[0].'.'.$parts[1],socialB64($parts[2]),socialPem($k),OPENSSL_ALGO_SHA256)===1;break;}
 if(!$valid||!in_array($c['iss']??'',socialProvider($provider)['issuers'],true)||($c['aud']??'')!==$client||!is_numeric($c['exp']??null)||(int)$c['exp']<=time()||!is_numeric($c['iat']??null)||(int)$c['iat']>time()+60||!is_string($c['nonce']??null)||!hash_equals($nonce,$c['nonce'])||!is_string($c['sub']??null)||$c['sub']==='')throw new RuntimeException('Kimlik doğrulanamadı.');
 if(isset($c['azp'])&&$c['azp']!==$client)throw new RuntimeException('Kimlik alıcısı eşleşmedi.');return $c;
}
function socialUser(string $provider,array $c): array {
 return transaction(function()use($provider,$c){
  $known=query('SELECT u.* FROM social_accounts s JOIN users u ON u.id=s.user_id WHERE s.provider=? AND s.subject=?',[$provider,$c['sub']])->fetch();if($known)return $known;
  $email=strtolower((string)($c['email']??''));if(!filter_var($email,FILTER_VALIDATE_EMAIL)||!in_array($c['email_verified']??false,[true,'true'],true))throw new RuntimeException('Doğrulanmış e-posta paylaşılmadı. E-posta ile kayıt olabilirsiniz.');
  if($email===strtolower(setting('admin_email'))||query('SELECT id FROM users WHERE email=?',[$email])->fetchColumn())throw new RuntimeException('Bu e-posta kayıtlı. Güvenliğiniz için mevcut e-posta ve şifrenizle giriş yapın; gerekirse şifremi unuttum bağlantısını kullanın.');
  $name=mb_substr(trim((string)($c['name']??'')),0,60)?:'Müşteri';query('INSERT INTO users(name,email,password) VALUES(?,?,?)',[$name,$email,password_hash(bin2hex(random_bytes(32)),PASSWORD_DEFAULT)]);$id=db()->lastInsertId();query('INSERT INTO social_accounts(provider,subject,user_id) VALUES(?,?,?)',[$provider,$c['sub'],$id]);return query('SELECT * FROM users WHERE id=?',[$id])->fetch();
 });
}
function socialFlow(string $state,string $binder,string $provider): array {
 return transaction(function()use($state,$binder,$provider){$f=query('SELECT * FROM social_flows WHERE state_hash=?',[hash('sha256',$state)])->fetch();if(!$f||$f['expires']<time()||$f['provider']!==$provider||!hash_equals($f['binder_hash'],hash('sha256',$binder)))throw new RuntimeException('Giriş oturumu geçersiz veya süresi dolmuş.');query('DELETE FROM social_flows WHERE state_hash=?',[$f['state_hash']]);return $f;});
}
if(setting('social_version')!=='1')transaction(function(){
 if(setting('social_version')==='1')return;
 db()->exec('CREATE TABLE social_accounts(provider TEXT NOT NULL,subject TEXT NOT NULL,user_id INTEGER NOT NULL REFERENCES users(id),PRIMARY KEY(provider,subject))');
 db()->exec('CREATE TABLE social_flows(state_hash TEXT PRIMARY KEY,binder_hash TEXT NOT NULL,provider TEXT NOT NULL,nonce TEXT NOT NULL,verifier TEXT NOT NULL,expires INTEGER NOT NULL)');setsetting('social_version','1');
});
