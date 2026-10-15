@echo off
REM Kutumb Realty — starts the local dev database + PHP server.
REM Site will be at http://localhost:8000/index.php
REM Admin panel at http://localhost:8000/admin/login.php

set MARIADB_DIR=C:\Users\Nishant\devstack\mariadb
set PHP_DIR=C:\Users\Nishant\devstack\php

echo Starting MariaDB...
start "Kutumb MariaDB" "%MARIADB_DIR%\bin\mysqld.exe" --datadir="%MARIADB_DIR%\data" --port=3306 --console

timeout /t 3 /nobreak > nul

echo Starting PHP server...
cd /d "%~dp0"
"%PHP_DIR%\php.exe" -c "%PHP_DIR%\php.ini" -S localhost:8000

pause
