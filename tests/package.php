<?php
declare(strict_types=1);
$root=dirname(__DIR__);$out=$root.'/dist';if(!is_dir($out))mkdir($out,0755,true);
$path=$out.'/BlueVera-Hosting.zip';$zip=new ZipArchive();if($zip->open($path,ZipArchive::CREATE|ZipArchive::OVERWRITE)!==true)throw new RuntimeException('Archive could not be created');
foreach(['app','public'] as $dir){$it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/'.$dir,FilesystemIterator::SKIP_DOTS));foreach($it as $file){if(!$file->isFile())continue;$relative=str_replace('\\','/',substr($file->getPathname(),strlen($root)+1));if(str_starts_with($relative,'public/uploads/')&&$file->getFilename()!=='.htaccess')continue;$zip->addFile($file->getPathname(),$relative);}}
foreach(['.htaccess','KURULUM.md','ASSET-SOURCES.md','BASLAT.bat'] as $file)$zip->addFile($root.'/'.$file,$file);
$zip->addFile($root.'/storage/.htaccess','storage/.htaccess');$zip->addFromString('storage/install-token.txt',bin2hex(random_bytes(32)));
$zip->close();$verify=new ZipArchive();$verify->open($path);for($i=0;$i<$verify->numFiles;$i++){if(preg_match('/\.(sqlite(?:-wal|-shm)?|db|log|env|bak)$/i',$verify->getNameIndex($i))||preg_match('~(?:^|/)(?:sessions|test-output|tests)/~',$verify->getNameIndex($i)))throw new RuntimeException('Unexpected private runtime file');}
if($verify->locateName('public/index.php')===false||$verify->locateName('storage/install-token.txt')===false)throw new RuntimeException('Incomplete archive');
echo 'Hosting archive verified: '.$verify->numFiles.' files, '.filesize($path).' bytes'.PHP_EOL;$verify->close();
$source=new ZipArchive();$source->open($path);$update=new ZipArchive();$update->open($out.'/BlueVera-Guncelleme.zip',ZipArchive::CREATE|ZipArchive::OVERWRITE);
for($i=0;$i<$source->numFiles;$i++){$name=$source->getNameIndex($i);if(str_starts_with($name,'storage/')||str_starts_with($name,'public/uploads/')||$name==='public/install.php')continue;$update->addFromString($name,$source->getFromIndex($i));}$update->close();$source->close();echo "Update archive created without database, uploads or installer.\n";
