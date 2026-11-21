@echo off
title Setup Nightly Cloud Sync Task - Royal Hotel POS (01:00 AM)

net session >nul 2>&1
if %errorlevel% neq 0 (
  echo Requesting Administrator permission to register Windows Scheduled Task...
  powershell -NoProfile -ExecutionPolicy Bypass -Command "Start-Process -FilePath '%~f0' -Verb RunAs"
  exit /b
)

set "PROJECT_DIR=%~dp0"
set "PHP_BIN=C:\xampp\php\php.exe"
if not exist "%PHP_BIN%" (
  for /f "delims=" %%i in ('where php 2^>nul') do set "PHP_BIN=%%i"
)

echo ================================================================
echo  Royal Hotel POS - Nightly 25%% Cloud AI Sync Scheduler (01:00 AM)
echo ================================================================
echo PHP Binary  : %PHP_BIN%
echo Project Dir : %PROJECT_DIR%
echo.

schtasks /Create /F /TN "RoyalHotelPOS_NightlyCloudSync" ^
  /TR "\"%PHP_BIN%\" \"%PROJECT_DIR%artisan\" pos:sync-cloud --days=3 --ai" ^
  /SC DAILY /ST 01:00 /RL HIGHEST

if %errorlevel% equ 0 (
  echo.
  echo SUCCESS! Nightly Cloud Sync scheduled every day at 01:00 AM.
  echo You can test immediately by running: php artisan pos:sync-cloud --days=7 --ai --manual
) else (
  echo.
  echo ERROR: Failed to create Windows Scheduled Task.
)

echo.
pause
