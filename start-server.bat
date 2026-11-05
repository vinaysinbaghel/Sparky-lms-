@echo off
title Sparky LMS Local Server

echo ===================================================
echo Starting Sparky LMS Localhost Environment
echo ===================================================

:: Check and start MariaDB if not running
tasklist /FI "IMAGENAME eq mysqld.exe" 2>NUL | find /I /N "mysqld.exe">NUL
if "%ERRORLEVEL%"=="0" (
    echo [OK] MariaDB is already running.
) else (
    echo Starting MariaDB...
    start "" /B "C:\Users\vinay.baghel\AppData\Local\Programs\mariadb\bin\mysqld.exe" --defaults-file="C:\Users\vinay.baghel\AppData\Local\Programs\mariadb\data\my.ini"
    timeout /t 2 /nobreak >nul
    echo [OK] MariaDB started.
)

:: Check and start PHP server with router.php
netstat -ano | findstr :8000 | findstr LISTENING >nul
if "%ERRORLEVEL%"=="0" (
    echo [OK] Server is listening on http://localhost:8000
) else (
    echo Starting PHP development server with media streaming router...
    cd /d "%~dp0"
    start "Sparky LMS Server" "C:\Users\vinay.baghel\AppData\Local\Programs\php83\php.exe" -S localhost:8000 router.php
    timeout /t 2 /nobreak >nul
    echo [OK] PHP server started on http://localhost:8000
)

echo.
echo ===================================================
echo Server is running at: http://localhost:8000
echo ===================================================
start http://localhost:8000
pause
