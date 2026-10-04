<?php
// CLI only: cron runs this file every minute. No browser endpoint or SMTP credentials.
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/bootstrap.php';
$from=setting('mail_from');if(!filter_var($from,FILTER_VALIDATE_EMAIL)||preg_match('/[\r\n]/',$from))exit("Configure mail_from first.\n");
$lock=fopen(STORE.'/mail-worker.lock','c');if(!$lock||!flock($lock,LOCK_EX|LOCK_NB))exit;
query("DELETE FROM mail_queue WHERE dedupe LIKE 'reset:%' AND sent IS NULL AND created<?",[time()-1800]);
foreach(query('SELECT * FROM mail_queue WHERE sent IS NULL AND attempts<5 ORDER BY id LIMIT 20')->fetchAll() as $m){
 $subject='=?UTF-8?B?'.base64_encode($m['subject']).'?=';
 $headers=['From'=>$from,'MIME-Version'=>'1.0','Content-Type'=>'text/plain; charset=UTF-8'];
 $ok=mail($m['recipient'],$subject,$m['body'],$headers);
 query('UPDATE mail_queue SET sent=?,attempts=attempts+1,last_error=? WHERE id=?',[$ok?time():null,$ok?'':'Sunucu iletiyi kabul etmedi',$m['id']]);
}
query('DELETE FROM reset_tokens WHERE expires<?',[time()]);
query('DELETE FROM mail_queue WHERE sent IS NOT NULL AND sent<?',[time()-30*86400]);
foreach(glob(STORE.'/sessions/sess_*')?:[] as $path)if(is_file($path)&&filemtime($path)<time()-7200){$h=@fopen($path,'r+');if($h){if(flock($h,LOCK_EX|LOCK_NB)){if(filemtime($path)<time()-7200)@unlink($path);flock($h,LOCK_UN);}fclose($h);}}
echo "Queue processed; server acceptance is not proof of inbox delivery.\n";
