@echo off
REM Dev only: server in this console, open browser (no Deck Host).
cd /d "%~dp0machina_sys"
start "" "http://127.0.0.1:43111/"
python server.py
if errorlevel 1 pause
