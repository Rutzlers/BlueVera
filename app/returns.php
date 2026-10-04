<?php
if(setting('return_requests_version')!=='1')transaction(function(){
 if(setting('return_requests_version')==='1')return;
 db()->exec("CREATE TABLE return_requests(id INTEGER PRIMARY KEY AUTOINCREMENT,order_id TEXT NOT NULL REFERENCES orders(id),status TEXT NOT NULL DEFAULT 'pending',reason TEXT NOT NULL,created TEXT NOT NULL,decided TEXT,reply TEXT NOT NULL DEFAULT '')");
 db()->exec("CREATE UNIQUE INDEX one_open_return ON return_requests(order_id) WHERE status IN ('pending','approved')");
 setsetting('return_requests_version','1');
});
function canRequestReturn(array $o): bool {return in_array($o['status'],['pending','confirmed','paid','preparing','shipped','in_transit','delivered'],true);}
function returnLabel(string $status): string {return ['pending'=>'Admin onayı bekleniyor','approved'=>'İade talebi onaylandı','rejected'=>'İade talebi reddedildi'][$status]??$status;}
function returnActions(string $action): bool {
 if($action==='return_request'){
  rate('return-request',8);$id=field('order_id',40);$key=field('order_key',64);$reason=field('reason',2000);
  transaction(function()use($id,$key,$reason){
   $o=query('SELECT * FROM orders WHERE id=?',[$id])->fetch();
   if(!$o||!hash_equals($o['secret'],$key))throw new RuntimeException('Sipariş doğrulanamadı. Takip sayfasından tekrar giriş yapın.');
   if(!canRequestReturn($o))throw new RuntimeException('Bu sipariş için yeni iade talebi açılamıyor. Mağazayla iletişime geçebilirsiniz.');
   if(query("SELECT id FROM return_requests WHERE order_id=? AND status IN ('pending','approved')",[$id])->fetchColumn())return;
   query('INSERT INTO return_requests(order_id,reason,created) VALUES(?,?,?)',[$id,$reason,date('Y-m-d H:i:s')]);$requestId=db()->lastInsertId();recordOrderStatus($id,'return_requested','return:'.$requestId.':requested');
   $body='Takip kodu: '.$o['tracking_code']."\nİade talebiniz kaydedildi. Admin incelemesi bekleniyor.\nAçıklama: ".$reason."\nBu talep para iadesi veya otomatik sipariş iptali değildir.";
   queueMail('return:'.$requestId.':created:customer',$o['email'],shopName().' iade talebi',$body);
   queueMail('return:'.$requestId.':created:owner',setting('support_email'),shopName().' yeni iade talebi',$body);
  });flash('İade talebiniz alındı. Admin onayı bekleniyor.');go('order',['id'=>$id,'key'=>$key]);
 }
 if($action==='return_decide'){
  requireadmin();$id=(int)($_POST['request_id']??0);$decision=field('decision',20);$reply=field('reply',2000);
  if(!in_array($decision,['approved','rejected'],true)||mb_strlen($reply)<5)throw new RuntimeException('Kararı ve müşteriye iletilecek açıklamayı yazın.');
  transaction(function()use($id,$decision,$reply){
   $r=query('SELECT * FROM return_requests WHERE id=?',[$id])->fetch();if(!$r||$r['status']!=='pending')throw new RuntimeException('Bu talep daha önce değerlendirilmiş veya bulunamadı.');
   $o=query('SELECT * FROM orders WHERE id=?',[$r['order_id']])->fetch();
   query('UPDATE return_requests SET status=?,reply=?,decided=? WHERE id=?',[$decision,$reply,date('Y-m-d H:i:s'),$id]);
   recordOrderStatus($o['id'],'return_'.$decision,'return:'.$id.':decision');
   queueMail('return:'.$id.':decision',$o['email'],shopName().' iade talebi sonucu','Takip kodu: '.$o['tracking_code']."\n".returnLabel($decision)."\n".$reply."\nOnay, para iadesinin tamamlandığı anlamına gelmez. Güncel sipariş durumunu takip sayfasından görebilirsiniz.");
  });flash('Karar kaydedildi ve müşterinin takip ekranına yansıtıldı. Sipariş durumu ve para iadesi ayrıca yönetilir.');go('admin/returns');
 }
 return false;
}

function returnProgress(array $o): ?array {
 $approved=query("SELECT id FROM return_requests WHERE order_id=? AND status='approved' LIMIT 1",[$o['id']])->fetchColumn();
 if(!$approved&&!in_array($o['status'],['refund_pending','returned','refunded'],true))return null;
 $steps=['approved'=>'İade talebi onaylandı','returned'=>'Ürün geri alındı','refunded'=>'Para iadesi tamamlandı'];
 $current=in_array($o['status'],['returned','refunded'],true)?$o['status']:'approved';
 if(!$approved)$steps['approved']='İade işlemi başlatıldı';
 return ['steps'=>$steps,'current'=>$current,'title'=>$steps[$current]];
}

// Bring previously created return requests into the same chronological history.
if(setting('return_history_version')!=='1')transaction(function(){
 if(setting('return_history_version')==='1')return;
 foreach(query('SELECT * FROM return_requests ORDER BY id')->fetchAll() as $r){
  $events=[['return_requested',$r['created'],'return:'.$r['id'].':requested']];
  if($r['decided']&&$r['status']!=='pending')$events[]=['return_'.$r['status'],$r['decided'],'return:'.$r['id'].':decision'];
  foreach($events as [$status,$created,$source])if(!query('SELECT id FROM order_events WHERE order_id=? AND source=?',[$r['order_id'],$source])->fetchColumn())query('INSERT INTO order_events(order_id,status,created,source) VALUES(?,?,?,?)',[$r['order_id'],$status,$created,$source]);
 }
 setsetting('return_history_version','1');
});
function orderHistoryLabel(array $event): string {
 $labels=['return_requested'=>'İade işlemleri — Talep oluşturuldu, onay bekleniyor','return_approved'=>'İade işlemleri — Talep onaylandı','return_rejected'=>'İade işlemleri — Talep reddedildi','refund_pending'=>'İade işlemleri — Para iadesi bekleniyor','returned'=>'İade işlemleri — Ürün geri alındı','refunded'=>'İade işlemleri — Para iadesi tamamlandı'];
 return $labels[$event['status']]??orderStatuses()[$event['status']]??$event['status'];
}
