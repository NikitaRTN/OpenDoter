@echo off
chcp 65001 >nul
setlocal
cd /d "%~dp0"
echo Starting OpenDoter API and site...
start "OpenDoter API" cmd /k ""%~dp0run-api.bat""
start "OpenDoter Site" cmd /k ""%~dp0run-site.bat" --open-browser"
echo The site window will show its actual URL and open it after startup.
