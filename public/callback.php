<?php
require dirname(__DIR__).'/app/bootstrap.php';
require ROOT.'/app/payments.php';
header('Content-Type: text/plain; charset=utf-8');
if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);exit('POST required');}
try{handleCallback($_POST);echo 'OK';}catch(Throwable $e){http_response_code(400);echo 'Invalid callback';}
