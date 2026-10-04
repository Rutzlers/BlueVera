<?php
declare(strict_types=1);
date_default_timezone_set('Europe/Istanbul');
ini_set('display_errors', '0');
define('ROOT', dirname(__DIR__));
define('STORE', getenv('BLUEVERA_STORAGE') ?: ROOT . '/storage');
if (!is_dir(STORE)) mkdir(STORE, 0700, true);
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');
header("Content-Security-Policy: default-src 'self'; script-src 'self'".((($_GET['page']??'')==='pay')?' https://www.paytr.com':'')."; img-src 'self' data:; style-src 'self' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; frame-src https://www.paytr.com; form-action 'self'; base-uri 'self'; object-src 'none'; frame-ancestors 'self'");
header(in_array((string)($_GET['page']??''),['','catalog','product','info'],true)&&($_SERVER['REQUEST_METHOD']??'GET')==='GET'?'Cache-Control: private, no-cache':'Cache-Control: no-store');
ini_set('session.use_strict_mode', '1');
ini_set('session.gc_maxlifetime', '604800');ini_set('session.gc_probability', '1');ini_set('session.gc_divisor', '100');
// Keep this store's sessions isolated from other applications on shared hosting.
if(!is_dir(STORE.'/sessions'))mkdir(STORE.'/sessions',0700,true);
session_save_path(STORE.'/sessions');
session_name('BLUEVERASESSID');
session_set_cookie_params(['lifetime'=>604800,'httponly'=>true,'secure'=>!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off','samesite'=>'Lax','path'=>'/']);
session_cache_limiter('');session_start();
$_SESSION['csrf'] ??= bin2hex(random_bytes(32));
function db(): PDO { static $db; if (!$db) { $db=new PDO('sqlite:'.STORE.'/store.sqlite'); $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION); $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE,PDO::FETCH_ASSOC); $db->exec('PRAGMA foreign_keys=ON; PRAGMA busy_timeout=5000;'); } return $db; }
function query(string $sql,array $args=[]): PDOStatement { $s=db()->prepare($sql); $s->execute($args); return $s; }
function e($v): string { return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8'); }
function money($v): string {return number_format((int)$v/100,2,',','.').' ₺';}
function setting(string $key,string $default=''): string { $s=query('SELECT value FROM settings WHERE name=?',[$key])->fetchColumn();return $s===false?$default:(string)$s; }
function setsetting(string $key,string $value): void {query('INSERT INTO settings(name,value) VALUES(?,?) ON CONFLICT(name) DO UPDATE SET value=excluded.value',[$key,$value]);}
function url(string $route='',array $params=[]): string {if($route==='product'&&!empty($params['id'])){$n=query('SELECT name FROM products WHERE id=?',[(int)$params['id']])->fetchColumn();if($n)$params['urun']=seoSlug($n);}return 'index.php?'.http_build_query(['page'=>$route]+$params);}
function go(string $route='',array $params=[]): void {header('Location: '.url($route,$params));exit;}
function csrf(): string { return '<input type="hidden" name="csrf" value="'.e($_SESSION['csrf']).'">'; }
function checkcsrf(): void { if (!hash_equals($_SESSION['csrf'],(string)($_POST['csrf']??''))) throw new RuntimeException('Oturum doğrulanamadı. Sayfayı yenileyip tekrar deneyin.'); }
function flash(string $s): void {$_SESSION['flash']=$s;}
function admin(): bool {return !empty($_SESSION['admin']) && (time()-($_SESSION['admin_time']??0)<3600);}
function requireadmin(): void {if(!admin())go('admin/login'); $_SESSION['admin_time']=time();}
function field(string $key,int $max=255): string { $v=trim((string)($_POST[$key]??''));if(mb_strlen($v)>$max)throw new RuntimeException('Girilen bilgi çok uzun: '.$key);return $v; }
function rate(string $key,int $limit=10): void { $key=hash('sha256',$key.'|'.clientIp());query('DELETE FROM attempts WHERE expires < ?',[time()]);query('INSERT INTO attempts(id,hits,expires) VALUES(?,1,?) ON CONFLICT(id) DO UPDATE SET hits=hits+1',[$key,time()+900]);if((int)query('SELECT hits FROM attempts WHERE id=?',[$key])->fetchColumn()>$limit)throw new RuntimeException('Çok fazla deneme yapıldı. 15 dakika sonra tekrar deneyin.'); }
function transaction(callable $fn) {db()->exec('BEGIN IMMEDIATE');try{$v=$fn();db()->exec('COMMIT');return $v;}catch(Throwable $ex){db()->exec('ROLLBACK');throw $ex;}}
function installed(): bool {return (bool)query("SELECT name FROM sqlite_master WHERE type='table' AND name='settings'")->fetchColumn();}
if(!installed()) { require ROOT.'/app/schema.php'; transaction(function(){if(!installed())initialize();}); }
require ROOT.'/app/customization.php';
require ROOT.'/app/orders.php';
require ROOT.'/app/extras.php';
require ROOT.'/app/seo.php';
function basket(): array {
 $items=[];
 foreach(($_SESSION['cart']??[]) as $key=>$qty){
  [$pid,$vid]=array_map('intval',array_pad(explode('-',(string)$key),2,0));
  $p=query('SELECT * FROM products WHERE id=? AND active=1',[$pid])->fetch();if(!$p)continue;
  $p['variant_id']=0;$p['variant']='';
  if(hasVariants($pid)){$v=$vid?query('SELECT * FROM product_variants WHERE id=? AND product_id=?',[$vid,$pid])->fetch():false;if(!$v)continue;$p['variant_id']=(int)$v['id'];$p['variant']=$v['name'];$p['stock']=(int)$v['stock'];}
  $p['key']=(string)$key;$p['qty']=(int)$qty;$items[]=$p;
 }
 return $items;
}
function totals(array $items): array {$subtotal=0;foreach($items as $p)$subtotal+=$p['price']*$p['qty'];$shipping=$subtotal>=(int)setting('free_shipping','200000')?0:(int)setting('shipping','7990');return [$subtotal,$items?$shipping:0,$subtotal+($items?$shipping:0)];}
function releaseOrder(array $o): void {if(!$o['released']){foreach(json_decode($o['items'],true) as $p)restoreItemStock($p);query('UPDATE orders SET released=1 WHERE id=?',[$o['id']]);}}
function expireOrders(): void {transaction(function(){foreach(query("SELECT * FROM orders WHERE status='pending' AND expires < ?",[time()])->fetchAll() as $o){releaseOrder($o);changeOrderStatus($o['id'],'expired','system');}});}
