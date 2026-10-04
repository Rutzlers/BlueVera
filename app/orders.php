<?php
function orderStatuses(): array {return ['pending'=>'Ödeme bekleniyor','confirmed'=>'Sipariş alındı','paid'=>'Ödeme alındı','preparing'=>'Hazırlanıyor','shipped'=>'Kargoya verildi','in_transit'=>'Yolda','delivered'=>'Teslim edildi','cancelled'=>'İptal edildi','expired'=>'Ödeme süresi doldu','failed'=>'Ödeme başarısız','test_paid'=>'Test ödemesi — sevk etmeyin','review'=>'Manuel inceleme — sevk etmeyin','refunded'=>'İade edildi'];}
function newTrackingCode(): string {
 for($digits=6;$digits<=9;$digits++)for($attempt=0;$attempt<100;$attempt++){
 $code=(string)random_int(10**($digits-1),10**$digits-1);
 if(!query('SELECT id FROM orders WHERE tracking_code=?',[$code])->fetchColumn())return $code;
 }throw new RuntimeException('Takip kodu oluşturulamadı. Lütfen tekrar deneyin.');
}
function normalizeTrackingCode(string $value): string {
 $value=strtoupper(preg_replace('/[\s-]+/u','',$value));
 if(preg_match('/^(?:BV)?([0-9]{6,9})$/',$value,$m))return $m[1];
 if(preg_match('/^TK([A-F0-9]{20})$/',$value,$m))return 'TK-'.implode('-',str_split($m[1],5));
 return $value;
}
function orderNext(array $o): array {
 $allowed=['pending'=>['cancelled'],'confirmed'=>['preparing','shipped','cancelled'],'paid'=>['preparing','shipped','refunded'],'preparing'=>['shipped','refunded'],'shipped'=>['in_transit','delivered','refunded'],'in_transit'=>['delivered','refunded'],'delivered'=>['refunded'],'review'=>$o['test_mode']?[]:['paid','refunded']];
 if($o['method']==='bank'&&$o['status']==='expired')$allowed['expired']=['paid'];
 if($o['method']==='bank'&&$o['status']==='pending')$allowed['pending'][]='paid';
 if($o['method']==='cod'&&$o['status']==='preparing')$allowed['preparing'][]='cancelled';
 return $allowed[$o['status']]??[];
}
function recordOrderStatus(string $id,string $status,string $source): void {
 query('INSERT INTO order_events(order_id,status,created,source) VALUES(?,?,?,?)',[$id,$status,date('Y-m-d H:i:s'),$source]);
}
function changeOrderStatus(string $id,string $status,string $source): void {
 $old=query('SELECT status FROM orders WHERE id=?',[$id])->fetchColumn();
 if($old!==$status){query('UPDATE orders SET status=? WHERE id=?',[$status,$id]);recordOrderStatus($id,$status,$source);}
}
if(setting('order_tracking_version')!=='1')transaction(function(){
 if(setting('order_tracking_version')==='1')return;
 $columns=query('PRAGMA table_info(orders)')->fetchAll();if(!in_array('tracking_code',array_column($columns,'name'),true))db()->exec("ALTER TABLE orders ADD COLUMN tracking_code TEXT NOT NULL DEFAULT ''");
 db()->exec('CREATE TABLE IF NOT EXISTS order_events(id INTEGER PRIMARY KEY AUTOINCREMENT,order_id TEXT NOT NULL REFERENCES orders(id),status TEXT NOT NULL,created TEXT NOT NULL,source TEXT NOT NULL)');
 foreach(query("SELECT id,status FROM orders WHERE tracking_code='' ")->fetchAll() as $o){query('UPDATE orders SET tracking_code=? WHERE id=?',[newTrackingCode(),$o['id']]);recordOrderStatus($o['id'],$o['status'],'migration');}
 db()->exec('CREATE UNIQUE INDEX IF NOT EXISTS unique_tracking_code ON orders(tracking_code)');
 setsetting('order_tracking_version','1');
});
if(setting('short_tracking_version')!=='1')transaction(function(){
 if(setting('short_tracking_version')==='1')return;
 if(!in_array('previous_tracking_code',array_column(query('PRAGMA table_info(orders)')->fetchAll(),'name'),true))db()->exec('ALTER TABLE orders ADD COLUMN previous_tracking_code TEXT');
 foreach(query("SELECT id,tracking_code FROM orders WHERE tracking_code LIKE 'TK-%'")->fetchAll() as $o)query('UPDATE orders SET previous_tracking_code=?,tracking_code=? WHERE id=?',[$o['tracking_code'],newTrackingCode(),$o['id']]);
 db()->exec('CREATE UNIQUE INDEX IF NOT EXISTS previous_tracking_code_unique ON orders(previous_tracking_code)');
 setsetting('short_tracking_version','1');
});
if(setting('numeric_tracking_version')!=='1')transaction(function(){
 if(setting('numeric_tracking_version')==='1')return;
 foreach(query("SELECT id,tracking_code FROM orders WHERE tracking_code LIKE 'BV-%'")->fetchAll() as $o)query('UPDATE orders SET tracking_code=? WHERE id=?',[normalizeTrackingCode($o['tracking_code']),$o['id']]);
 setsetting('numeric_tracking_version','1');
});
