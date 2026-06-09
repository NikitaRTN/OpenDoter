@echo off
chcp 65001 >nul
REM ============================================================
REM  OpenDoter — главный сайт (PHP) на http://localhost:8000
REM ============================================================
setlocal
cd /d "%~dp0"

where php >nul 2>nul
if errorlevel 1 (
    echo [ОШИБКА] PHP не найден в PATH.
    echo Установите PHP 8.1+ и добавьте его в PATH.
    pause
    exit /b 1
)

if "%SITE_PORT%"=="" set SITE_PORT=8000

echo.
echo   OpenDoter (сайт)  ^-^>  http://localhost:%SITE_PORT%
echo   Остановка: Ctrl+C
echo.
php -S 0.0.0.0:%SITE_PORT% index.php
