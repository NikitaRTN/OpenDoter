@echo off
chcp 65001 >nul
setlocal
cd /d "%~dp0"
where python >nul 2>nul
if errorlevel 1 (
    echo [ERROR] Python 3.10+ was not found in PATH.
    pause
    exit /b 1
)
python "%~dp0scripts\run_site.py" %*
exit /b %errorlevel%
