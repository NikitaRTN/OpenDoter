@echo off
chcp 65001 >nul
REM ============================================================
REM  OpenDoter — локальный API (Python) на http://localhost:8080
REM ============================================================
setlocal
cd /d "%~dp0parser-master"

where python >nul 2>nul
if errorlevel 1 (
    echo [ОШИБКА] Python не найден в PATH.
    echo Установите Python 3.10+ и добавьте его в PATH.
    pause
    exit /b 1
)

if "%PORT%"=="" set PORT=8080

echo.
echo   OpenDoter API  ^-^>  http://localhost:%PORT%
echo   Остановка: Ctrl+C
echo.
python server.py
