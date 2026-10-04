<?php
$folder=dirname(__DIR__).'/test-output/social-'.bin2hex(random_bytes(6));putenv('BLUEVERA_STORAGE='.$folder);require dirname(__DIR__).'/app/bootstrap.php';
$count=0;function checkSocial($ok,$label){global $count;if(!$ok)throw new RuntimeException($label);$count++;}
function rejectSocial(callable $fn,string $label){try{$fn();}catch(RuntimeException $e){checkSocial(true,$label);return;}throw new RuntimeException('Accepted invalid '.$label);}
function b64test($v){return rtrim(strtr(base64_encode($v),'+/','-_'),'=');}
$key=openssl_pkey_new(['private_key_bits'=>2048,'private_key_type'=>OPENSSL_KEYTYPE_RSA,'config'=>'C:/xampp/apache/conf/openssl.cnf']);if(!$key)throw new RuntimeException('Unable to generate test key');$d=openssl_pkey_get_details($key);$jwks=[['kty'=>'RSA','kid'=>'test','n'=>b64test($d['rsa']['n']),'e'=>b64test($d['rsa']['e'])]];
function signTest($claims,$header=['alg'=>'RS256','kid'=>'test']){global $key;$data=b64test(json_encode($header)).'.'.b64test(json_encode($claims));openssl_sign($data,$signature,$key,OPENSSL_ALGO_SHA256);return $data.'.'.b64test($signature);}
$c=['iss'=>'https://accounts.google.com','aud'=>'client','sub'=>'subject','exp'=>time()+300,'iat'=>time(),'nonce'=>'nonce','email'=>'member@example.test','email_verified'=>true,'name'=>'Example'];
checkSocial(socialVerify(signTest($c),$jwks,'google','client','nonce')['sub']==='subject','Valid signed Google token');$apple=$c;$apple['iss']='https://appleid.apple.com';$apple['email_verified']='true';checkSocial(socialVerify(signTest($apple),$jwks,'apple','client','nonce')['sub']==='subject','Valid signed Apple token');
foreach(['iss'=>'https://evil.test','aud'=>'other','exp'=>time()-1,'iat'=>time()+1000,'nonce'=>'wrong','sub'=>'','azp'=>'other'] as $k=>$v){$bad=$c;$bad[$k]=$v;rejectSocial(fn()=>socialVerify(signTest($bad),$jwks,'google','client','nonce'),$k);}
rejectSocial(fn()=>socialVerify(signTest($c,['alg'=>'none','kid'=>'test']),$jwks,'google','client','nonce'),'algorithm');
$token=signTest($c);$segments=explode('.',$token);$segments[2]=b64test(str_repeat('x',256));rejectSocial(fn()=>socialVerify(implode('.',$segments),$jwks,'google','client','nonce'),'signature');
$u=socialUser('google',$c);checkSocial($u['email']===$c['email'],'New social account');checkSocial(socialUser('google',$c)['id']===$u['id'],'Repeat social account');checkSocial((int)query('SELECT COUNT(*) FROM users')->fetchColumn()===1,'No duplicate user');
$other=$c;$other['sub']='different';rejectSocial(fn()=>socialUser('google',$other),'Existing email not auto linked');$other['email']='new@example.test';$other['email_verified']=false;rejectSocial(fn()=>socialUser('google',$other),'Unverified email');
setsetting('admin_email','admin@example.test');$other['email']='admin@example.test';$other['email_verified']=true;rejectSocial(fn()=>socialUser('google',$other),'Admin cannot be social registered');
query('INSERT INTO social_flows VALUES(?,?,?,?,?,?)',[hash('sha256','state'),hash('sha256','binder'),'apple','nonce','verifier',time()+100]);
rejectSocial(fn()=>socialFlow('state','wrong','apple'),'Browser binding');rejectSocial(fn()=>socialFlow('state','binder','google'),'Provider binding');checkSocial(socialFlow('state','binder','apple')['nonce']==='nonce','Bound callback consumed');rejectSocial(fn()=>socialFlow('state','binder','apple'),'Replay');checkSocial(!socialReady('google'),'Unconfigured social provider hidden');
echo "PASS: $count social sign-in checks\n";
