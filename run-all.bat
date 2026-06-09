@echo off
chcp 65001 >nul
REM ============================================================
REM  OpenDoter — запуск API и сайта в двух отдельных окнах
REM ============================================================
setlocal
cd /d "%~dp0"

echo Запуск API и сайта OpenDoter...
start "OpenDoter API"  cmd /k ""%~dp0run-api.bat""
start "OpenDoter Site" cmd /k ""%~dp0run-site.bat""

REM Даём API и сайту пару секунд подняться, затем открываем браузер.
timeout /t 3 >nul
start "" http://localhost:8000

echo.
echo Готово. Сайт: http://localhost:8000 , API: http://localhost:8080
