Option Explicit

' Royal Hotel POS - Standalone Desktop App Launcher (Port 6808)
' Double-click to open directly like an .exe desktop software.

Dim fso, shell, appUrl, chrome64, chrome32, edge64, edge32, browserPath
Set fso = CreateObject("Scripting.FileSystemObject")
Set shell = CreateObject("WScript.Shell")

appUrl = "http://127.0.0.1:6808"

chrome64 = "C:\Program Files\Google\Chrome\Application\chrome.exe"
chrome32 = "C:\Program Files (x86)\Google\Chrome\Application\chrome.exe"
edge64 = "C:\Program Files\Microsoft\Edge\Application\msedge.exe"
edge32 = "C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe"

browserPath = ""
If fso.FileExists(chrome64) Then
  browserPath = chrome64
ElseIf fso.FileExists(chrome32) Then
  browserPath = chrome32
ElseIf fso.FileExists(edge64) Then
  browserPath = edge64
ElseIf fso.FileExists(edge32) Then
  browserPath = edge32
End If

If browserPath <> "" Then
  shell.Run Chr(34) & browserPath & Chr(34) & " --app=" & appUrl & " --new-window", 1, False
Else
  shell.Run appUrl, 1, False
End If
