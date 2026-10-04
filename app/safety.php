<?php
function legalTitles(): array {return ['Gizlilik Politikası','KVKK','Mesafeli Satış','İade ve Değişim','Çerez Politikası'];}
function launchProblems(?array $values=null): array {
 $get=function($k)use($values){return $values[$k]??setting($k);};$issues=[];
 foreach(legalTitles() as $t){$v=setting('legal_'.$t);if(mb_strlen(trim($v))<300||preg_match('/henüz hazırlanmadı|\[DOLDUR|örnek metin|buraya yaz/iu',$v))$issues[]=$t.' tamamlanmamış';}
 foreach(['company'=>'Ticari unvan','address'=>'İşletme adresi','phone'=>'Telefon','return_carrier'=>'İade taşıyıcısı','delivery_days'=>'Teslimat süresi'] as $k=>$label)if(!trim($get($k)))$issues[]=$label.' eksik';
 if(!filter_var($get('support_email'),FILTER_VALIDATE_EMAIL))$issues[]='Destek e-postası eksik';
 if(!filter_var($get('mail_from'),FILTER_VALIDATE_EMAIL)||$get('mail_verified')!=='1')$issues[]='E-posta teslim testi tamamlanmamış';
 if($get('legal_ready')!=='1')$issues[]='Hukuk metinlerini inceleyip onaylayın';
 if(query('SELECT id FROM products WHERE active=1 AND demo=0 AND vat_rate IS NULL LIMIT 1')->fetchColumn())$issues[]='Yayındaki ürünlerin KDV oranlarını tamamlayın';
 return $issues;
}
function assertLaunchReady(): void {if($p=launchProblems())throw new RuntimeException('Satış henüz hazır değil: '.implode('; ',$p));}
function clientIp(): string {
 $ip=$_SERVER['REMOTE_ADDR']??'127.0.0.1';
 $trusted=array_filter(array_map('trim',explode(',',getenv('BLUEVERA_TRUSTED_PROXIES')?:'')));
 if(in_array($ip,$trusted,true)){$candidate=$_SERVER['HTTP_CF_CONNECTING_IP']??'';if(filter_var($candidate,FILTER_VALIDATE_IP))return $candidate;}
 return filter_var($ip,FILTER_VALIDATE_IP)?$ip:'127.0.0.1';
}
function queueMail(string $key,string $recipient,string $subject,string $body): void {
 if(!filter_var($recipient,FILTER_VALIDATE_EMAIL))return;
 query('INSERT OR IGNORE INTO mail_queue(dedupe,recipient,subject,body,created) VALUES(?,?,?,?,?)',[$key,$recipient,$subject,$body,time()]);
}
function queueOrderMail(string $id): void {
 $o=query('SELECT * FROM orders WHERE id=?',[$id])->fetch();if(!$o)return;
 $event=query('SELECT MAX(id) FROM order_events WHERE order_id=?',[$id])->fetchColumn();
 $body='Sipariş: '.$id."\nTakip kodu: ".$o['tracking_code']."\nDurum: ".(orderStatuses()[$o['status']]??$o['status'])."\nToplam: ".money($o['total'])."\n\n".$o['contract_snapshot'];
 queueMail($id.':'.$event.':customer',$o['email'],shopName().' sipariş bilgisi',$body);
 queueMail($id.':'.$event.':owner',setting('support_email'),shopName().' sipariş bildirimi',$body);
}
function precontract(array $items): string {
 [$sub,$ship,$total]=totals($items);$text="ÖN BİLGİLENDİRME\nSatıcı: ".setting('company')."\nAdres: ".setting('address')."\nTelefon: ".setting('phone')."\nE-posta: ".setting('support_email')."\n";
 foreach($items as $p)$text.=$p['name'].' × '.$p['qty'].' — '.money($p['price']*$p['qty'])." (vergiler dahil)\n";
 $vat=0;foreach($items as $p)if($p['vat_rate']!==null)$vat+=vatAmount($p);$text.='Ürün bedellerine dahil KDV toplamı: '.money($vat)."\n";
 return $text.'Kargo: '.money($ship).' | Ödenecek toplam: '.money($total)."\nTeslimat: ".setting('delivery_days','Henüz belirtilmedi')."\nİade taşıyıcısı: ".setting('return_carrier')."\nİade adresi: ".(setting('return_address')?:setting('address'))."\n\n".setting('legal_İade ve Değişim')."\n\nMESAFELİ SATIŞ SÖZLEŞMESİ\n".setting('legal_Mesafeli Satış');
}
function pageRows(string $table,string $order,string $where='1=1',array $args=[]): array {
 $n=max(1,(int)($_GET['p']??1));$count=(int)query("SELECT COUNT(*) FROM $table WHERE $where",$args)->fetchColumn();$pages=max(1,(int)ceil($count/24));$n=min($n,$pages);
 $rows=query("SELECT * FROM $table WHERE $where ORDER BY $order LIMIT 24 OFFSET ".(($n-1)*24),$args)->fetchAll();return [$rows,$n,$pages];
}
function pageLinks(string $route,int $n,int $pages): void {if($pages<2)return;echo '<nav class="filters" aria-label="Sayfalar">';foreach(array_unique([1,max(1,$n-1),$n,min($pages,$n+1),$pages]) as $i)echo '<a class="button" href="'.e(url($route,array_merge(array_diff_key($_GET,['page'=>1]),['p'=>$i]))).'">'.$i.'</a>';echo '</nav>';}
if(setting('safety_version')!=='1')transaction(function(){
 if(setting('safety_version')==='1')return;
 db()->exec("ALTER TABLE orders ADD COLUMN contract_snapshot TEXT NOT NULL DEFAULT ''");
 db()->exec("CREATE TABLE mail_queue(id INTEGER PRIMARY KEY AUTOINCREMENT,dedupe TEXT UNIQUE NOT NULL,recipient TEXT NOT NULL,subject TEXT NOT NULL,body TEXT NOT NULL,created INTEGER NOT NULL,sent INTEGER,attempts INTEGER NOT NULL DEFAULT 0,last_error TEXT NOT NULL DEFAULT '')");
 db()->exec('CREATE TABLE reset_tokens(token_hash TEXT PRIMARY KEY,email TEXT NOT NULL,expires INTEGER NOT NULL)');
 db()->exec('CREATE INDEX orders_expiry ON orders(status,expires)');
 setsetting('legal_ready','0');setsetting('sales_enabled','0');setsetting('card_enabled','0');setsetting('paytr_test','1');
 query('UPDATE products SET active=0 WHERE demo=1');
 setsetting('safety_version','1');
});

require ROOT.'/app/legal-defaults.php';
