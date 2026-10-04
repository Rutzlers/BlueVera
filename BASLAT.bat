@echo off
cd /d "%~dp0"
if not exist "C:\xampp\php\php.exe" (
 echo XAMPP PHP bulunamadi. C:\xampp\php\php.exe yolunu kontrol edin.
 pause
 exit /b 1
)
echo BlueVera: http://127.0.0.1:8097
echo Ilk kurulum: http://127.0.0.1:8097/install.php
echo Pencereyi kapatmak onizlemeyi durdurur.
"C:\xampp\php\php.exe" -S 127.0.0.1:8097 -t public
