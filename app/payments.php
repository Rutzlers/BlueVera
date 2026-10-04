<?php
function paytrToken(array $o): string {
 $id=setting('merchant_id');$key=setting('merchant_key');$salt=setting('merchant_salt');
 if(!$id||!$key||!$salt)throw new RuntimeException('Kart ödemesi henüz yapılandırılmadı.');
 $base=rtrim(setting('site_url'),'/');if(!str_starts_with($base,'https://'))throw new RuntimeException('Kart ödemesi için HTTPS site adresi tanımlanmalıdır.');
 $lines=[];foreach(json_decode($o['items'],true) as $p)$lines[]=[$p['name'],number_format($p['price']/100,2,'.',''),$p['qty']];if($o['shipping'])$lines[]=['Kargo',number_format($o['shipping']/100,2,'.',''),1];
 $b=base64_encode(json_encode($lines,JSON_UNESCAPED_UNICODE));$ip=clientIp();$test=(string)$o['test_mode'];
 $hash=$id.$ip.$o['id'].$o['email'].$o['total'].$b.'1'.'0'.'TL'.$test;
 $fields=['merchant_id'=>$id,'user_ip'=>$ip,'merchant_oid'=>$o['id'],'email'=>$o['email'],'payment_amount'=>$o['total'],'user_basket'=>$b,'no_installment'=>1,'max_installment'=>0,'currency'=>'TL','test_mode'=>$test,'paytr_token'=>base64_encode(hash_hmac('sha256',$hash.$salt,$key,true)),'user_name'=>$o['name'],'user_address'=>$o['address'],'user_phone'=>$o['phone'],'merchant_ok_url'=>$base.'/'.url('order',['id'=>$o['id'],'key'=>$o['secret']]),'merchant_fail_url'=>$base.'/'.url('order',['id'=>$o['id'],'key'=>$o['secret']]),'timeout_limit'=>30,'debug_on'=>0,'lang'=>'tr'];
 $ch=curl_init('https://www.paytr.com/odeme/api/get-token');curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>http_build_query($fields),CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_TIMEOUT=>25,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2]);$body=curl_exec($ch);$code=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);$r=json_decode((string)$body,true);
 if($code!==200||($r['status']??'')!=='success'||!preg_match('/^[a-zA-Z0-9]+$/',$r['token']??''))throw new RuntimeException('Ödeme kuruluşuna bağlanılamadı. Siparişiniz kaydedildi; daha sonra bu sayfadan tekrar deneyebilirsiniz.');return $r['token'];
}
function handleCallback(array $p): void {
 $key=setting('merchant_key');$salt=setting('merchant_salt');if(!$key||!$salt)throw new RuntimeException('Not configured');
 foreach(['merchant_oid','status','total_amount','hash'] as $k)if(!isset($p[$k])||!is_string($p[$k]))throw new RuntimeException('Missing field');
 if(!in_array($p['status'],['success','failed'],true)||!ctype_digit($p['total_amount']))throw new RuntimeException('Invalid data');
 $hash=base64_encode(hash_hmac('sha256',$p['merchant_oid'].$salt.$p['status'].$p['total_amount'],$key,true));if(!hash_equals($hash,$p['hash']))throw new RuntimeException('Invalid signature');
 $notify=null;transaction(function()use($p,&$notify){$o=query('SELECT * FROM orders WHERE id=?',[$p['merchant_oid']])->fetch();if(!$o||$o['method']!=='card')throw new RuntimeException('Unknown order');if(in_array($o['status'],['paid','preparing','shipped','in_transit','delivered','test_paid','review','failed'],true))return;
 if($p['status']==='success'){
 if((int)$p['total_amount']!==(int)$o['total'])throw new RuntimeException('Amount mismatch');
 $status=$o['released']?'review':($o['test_mode']?'test_paid':'paid');
 if($o['test_mode']&&!$o['released'])releaseOrder($o);
 changeOrderStatus($o['id'],$status,'payment');$notify=$o['id'];
 }else{releaseOrder($o);changeOrderStatus($o['id'],'failed','payment');}});if($notify)notifyOrder($notify,'paid');
}
