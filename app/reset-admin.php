<?php
// Yönetici şifresini unuttuysanız (SSH/terminal erişimi gerekir):
//   php app/reset-admin.php "yeni-sifre-en-az-12-karakter"
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
define('PUBLIC_DIR',dirname(__DIR__).'/public');
require __DIR__.'/bootstrap.php';
$pass=$argv[1]??'';
if($pass===''){fwrite(STDOUT,'Yeni yönetici şifresi (en az 12 karakter): ');$pass=trim((string)fgets(STDIN));}
if(strlen($pass)<12){fwrite(STDERR,"Şifre en az 12 karakter olmalı.\n");exit(1);}
if(setting('admin_email')===''){fwrite(STDERR,"Yönetici hesabı henüz kurulmamış. Önce install.php ile kurulum yapın.\n");exit(1);}
setsetting('admin_password',password_hash($pass,PASSWORD_DEFAULT));
echo "Yönetici şifresi güncellendi: ".setting('admin_email')."\n";
