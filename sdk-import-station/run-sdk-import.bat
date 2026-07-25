@echo off
REM sdk-import-station ROM — open in The Deck Host
cd /d "%~dp0"
set "GLASS_LOGS_ROOT=C:\ALICE_BOX\.glass-logs"
python run-in-deck-host.py
if errorlevel 1 pause
