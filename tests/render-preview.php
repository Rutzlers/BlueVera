<?php
$_SERVER['REQUEST_METHOD']='GET';
parse_str($argv[1]??'',$_GET);
define('PUBLIC_DIR',dirname(__DIR__).'/public');
require dirname(__DIR__).'/app/bootstrap.php';
$page=(string)($_GET['page']??'');
$_SESSION=[];
$_SESSION['csrf']='preview';
if(in_array($page,['cart','checkout'])){ $id=query('SELECT id FROM products WHERE active=1 ORDER BY id LIMIT 1')->fetchColumn();if($id)$_SESSION['cart']=[(int)$id=>1]; }
require ROOT.'/app/views.php';
