<?php
declare(strict_types=1);
$root=dirname(__DIR__);$run=$root.'/test-output/run-'.date('Ymd-His').'-'.bin2hex(random_bytes(3));mkdir($run,0700,true);
putenv('BLUEVERA_STORAGE='.$run);require $root.'/app/bootstrap.php';require $root.'/app/payments.php';
query('UPDATE products SET active=1 WHERE demo=1');
$checks=[];
function ok(bool $condition,string $name):void{global $checks;if(!$condition)throw new RuntimeException('FAILED: '.$name);$checks[]=$name;}
$token=bin2hex(random_bytes(24));file_put_contents($run.'/install-token.txt',$token);
$desc=[0=>['pipe','r'],1=>['file',$run.'/server.log','a'],2=>['file',$run.'/server.log','a']];
putenv('BLUEVERA_BOT_MIN=0');
$proc=proc_open([PHP_BINARY,'-S','127.0.0.1:8098','-t',$root.'/public'],$desc,$pipes,$root);
if(!is_resource($proc))throw new RuntimeException('Test server failed');
$base='http://127.0.0.1:8098/';$cookie=$run.'/cookies.txt';
function request(string $path,array $post=null,bool $cookies=true):array{global $base,$cookie;static $client; if(!$client){$client=curl_init();curl_setopt($client,CURLOPT_COOKIEFILE,'');} $c=$cookies?$client:curl_init(); curl_setopt($c,CURLOPT_URL,$base.$path); curl_setopt($c,CURLOPT_HTTPGET,true); $opts=[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>10,CURLOPT_FOLLOWLOCATION=>false];if($post!==null){$opts[CURLOPT_POST]=true;$opts[CURLOPT_POSTFIELDS]=count(array_filter($post,fn($v)=>$v instanceof CURLFile))?$post:http_build_query($post);}curl_setopt_array($c,$opts);$body=curl_exec($c);$code=curl_getinfo($c,CURLINFO_HTTP_CODE);$redirect=curl_getinfo($c,CURLINFO_REDIRECT_URL);if(!$cookies)curl_close($c);return [$code,(string)$body,$redirect];}
function csrfFrom(string $html):string{preg_match('/name="csrf" value="([a-f0-9]+)"/',$html,$m);return $m[1]??'';}
function post(string $action,array $data=[],string $page='cart'):array{$r=request('index.php');return request('index.php?page='.$page,['action'=>$action,'csrf'=>csrfFrom($r[1])]+$data);}
try{
 for($i=0;$i<20;$i++){usleep(100000);if(request('index.php')[0]===200)break;}
 setsetting('admin_email','stock@example.test');setsetting('admin_password',password_hash('StockPassword123!',PASSWORD_DEFAULT));
 $r=post('admin_login',['email'=>'stock@example.test','password'=>'StockPassword123!','ft'=>botToken()],'admin/login');ok($r[0]===302,'Stock test admin login');
 $data=['name'=>'Stock Test','category'=>'Kulaklıklar','description'=>'Synthetic stock test','price'=>'100','stock'=>'8','active'=>1];
 $r=post('product_save',$data,'admin/edit');ok($r[0]===302,'Create simple product');$id=query("SELECT id FROM products WHERE name='Stock Test'")->fetchColumn();ok((int)query('SELECT stock FROM products WHERE id=?',[$id])->fetchColumn()===8,'New stock saved');
 $data['id']=$id;$data['stock']='25';$r=post('product_save',$data,'admin/edit');ok($r[0]===302&&(int)query('SELECT stock FROM products WHERE id=?',[$id])->fetchColumn()===25,'Existing stock edited');
 $data['variants']='Mavi 5';$r=post('product_save',$data,'admin/edit');ok($r[0]===302&&(int)query('SELECT stock FROM products WHERE id=?',[$id])->fetchColumn()===5,'Human friendly variant stock');
 $data['variants']="Mavi | 9\nSiyah | 2";$r=post('product_save',$data,'admin/edit');ok($r[0]===302&&(int)query('SELECT stock FROM products WHERE id=?',[$id])->fetchColumn()===11,'Variant stock edited and summed');
 foreach(['5','Mavi','Mavi |','Mavi | -1'] as $invalid){$data['variants']=$invalid;$r=post('product_save',$data,'admin/edit');ok($r[0]===200&&(int)query('SELECT stock FROM products WHERE id=?',[$id])->fetchColumn()===11,'Invalid variant does not wipe stock');}
 $data['variants']='';$data['stock']='0';$r=post('product_save',$data,'admin/edit');ok($r[0]===302&&(int)query('SELECT stock FROM products WHERE id=?',[$id])->fetchColumn()===0,'Explicit zero supported');
 unset($data['id']);$data['name']='New variant test';$data['variants']='Mavi 7';$r=post('product_save',$data,'admin/edit');ok($r[0]===302&&(int)query("SELECT stock FROM products WHERE name='New variant test'")->fetchColumn()===7,'New variant product stock saved');
 echo 'PASS: '.count($checks).' stock checks'.PHP_EOL;
}finally{proc_terminate($proc);proc_close($proc);}
