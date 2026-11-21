@echo off
title Activate Hotel POS Server on Apache2.4 (Port 6808)

net session >nul 2>&1
if %errorlevel% neq 0 (
  echo Requesting Administrator permission to restart Apache2.4 and allow Port 6808...
  powershell -NoProfile -ExecutionPolicy Bypass -Command "Start-Process -FilePath '%~f0' -Verb RunAs"
  exit /b
)

echo ================================================================
echo  Royal Hotel POS - Apache2.4 Multi-User LAN Server (Port 6808)
echo  (Driving School Portal on Port 6707 stays untouched!)
echo ================================================================
echo.

echo [1/4] Checking Apache configuration syntax...
pushd C:\xampp\apache\bin
httpd.exe -t
if %errorlevel% neq 0 (
  echo ERROR: Apache syntax error!
  popd
  pause
  exit /b 1
)
popd

echo.
echo [2/4] Restarting Apache2.4 Windows Service...
net stop Apache2.4 >nul 2>&1
timeout /t 1 /nobreak >nul
net start Apache2.4
if %errorlevel% neq 0 (
  echo ERROR: Could not start Apache2.4!
  pause
  exit /b 1
)

echo.
echo [3/4] Allowing TCP Port 6808 in Windows Firewall...
powershell -NoProfile -ExecutionPolicy Bypass -Command ^
  "Remove-NetFirewallRule -DisplayName 'Royal Hotel POS (Port 6808)' -ErrorAction SilentlyContinue; " ^
  "New-NetFirewallRule -DisplayName 'Royal Hotel POS (Port 6808)' -Direction Inbound -Protocol TCP -LocalPort 6808 -Action Allow -Profile Private,Domain,Public | Out-Null; " ^
  "Write-Host 'Firewall rule added for Port 6808.' -ForegroundColor Green"

echo.
echo [4/4] Verifying BOTH Hotel POS (6808) and Driving School (6707)...
timeout /t 2 /nobreak >nul
powershell -NoProfile -ExecutionPolicy Bypass -Command ^
  "$ip = (Get-NetIPAddress -AddressFamily IPv4 | Where-Object { $_.InterfaceAlias -like '*Wi-Fi*' -and $_.IPAddress -notlike '169.254.*' } | Select-Object -First 1 -ExpandProperty IPAddress); " ^
  "foreach ($port in @(6808, 6707)) { " ^
  "  try { $r = Invoke-WebRequest -Uri ('http://127.0.0.1:' + $port + '/up') -UseBasicParsing -ErrorAction Stop; Write-Host ('OK HTTP ' + $r.StatusCode + ' on Port ' + $port) -ForegroundColor Green } catch { Write-Host ('FAIL Port ' + $port + ': ' + $_.Exception.Message) -ForegroundColor Red } " ^
  "}; " ^
  "Write-Host ''; " ^
  "Write-Host 'Hotel POS Laptop URL : http://127.0.0.1:6808' -ForegroundColor Cyan; " ^
  "if ($ip) { Write-Host ('Hotel POS Waiter/Mobile URL : http://' + $ip + ':6808') -ForegroundColor Cyan }; " ^
  "Write-Host 'Demo PINs -> Owner: 1111 | Cashier: 2222 | Kitchen: 3333 | Waiter: 4444' -ForegroundColor Yellow"

echo.
pause
