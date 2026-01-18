@echo off
REM WhatsApp Notification Cron Job
REM Path ke PHP Laragon
SET PHP_PATH=C:\laragon\bin\php\php-8.4.1-nts-Win32-vs17-x64\php.exe

REM Path ke script cron
SET SCRIPT_PATH=C:\laragon\www\slims\admin\modules\wa_notification\cron.php

REM Path ke log file
SET LOG_PATH=C:\laragon\www\slims\admin\modules\wa_notification\cron.log

REM Jalankan script dan simpan output ke log
echo ======================================== >> %LOG_PATH%
echo [%date% %time%] Running notification... >> %LOG_PATH%
echo ======================================== >> %LOG_PATH%

"%PHP_PATH%" "%SCRIPT_PATH%" >> "%LOG_PATH%" 2>&1

echo. >> %LOG_PATH%