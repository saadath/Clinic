@echo off
set "PATH=%LOCALAPPDATA%\ClinicTools;%LOCALAPPDATA%\ClinicTools\php;%PATH%"
cd /d "%~dp0.."
call composer check-platform-reqs
if errorlevel 1 exit /b 1
php artisan test
if errorlevel 1 exit /b 1
call npm.cmd run build
