<?php
define('PUBLIC_DIR',__DIR__);
require dirname(__DIR__).'/app/bootstrap.php';
require ROOT.'/app/payments.php';
require ROOT.'/app/actions.php';
$page=(string)($_GET['page']??'');
if($page==='account'&&admin())go('admin');
if(str_starts_with($page,'admin')&&$page!=='admin/login')requireadmin();
try{expireOrders();actions();}catch(RuntimeException $ex){$error=$ex->getMessage();}catch(Throwable $ex){error_log($ex->getMessage());$error='İşlem tamamlanamadı. Lütfen tekrar deneyin.';}
require ROOT.'/app/views.php';
