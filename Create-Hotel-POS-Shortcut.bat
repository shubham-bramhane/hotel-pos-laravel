@echo off
title Create Desktop Shortcut - Royal Hotel POS
set "PROJECT_DIR=%~dp0"
set "TARGET=%PROJECT_DIR%Open-Hotel-POS-App.vbs"

powershell -NoProfile -ExecutionPolicy Bypass -Command ^
  "$ws = New-Object -ComObject WScript.Shell; " ^
  "$desktop = [Environment]::GetFolderPath('Desktop'); " ^
  "$lnk = $ws.CreateShortcut((Join-Path $desktop 'Royal Hotel POS.lnk')); " ^
  "$lnk.TargetPath = 'wscript.exe'; " ^
  "$lnk.Arguments = '\"%TARGET%\"'; " ^
  "$lnk.WorkingDirectory = '%PROJECT_DIR%'; " ^
  "$lnk.Description = 'Open Royal Hotel LAN POS (Port 6808)'; " ^
  "$lnk.IconLocation = 'shell32.dll,21'; " ^
  "$lnk.Save(); " ^
  "Write-Host 'Desktop shortcut created: Royal Hotel POS.lnk'"

pause
