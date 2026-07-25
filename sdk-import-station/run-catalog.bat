@echo off
setlocal
cd /d "%~dp0"
set "GLASS_LOGS_ROOT=C:\ALICE_BOX\.glass-logs"
echo Building catalog from glass-logs into work\
echo OT: %GLASS_LOGS_ROOT%\ot\conversations.json
echo NT: %GLASS_LOGS_ROOT%\nt
python tools\tree_core_catalog.py
if errorlevel 1 (
  echo catalog build failed
  exit /b 1
)
echo Done. catalog + glass extracts under work\
exit /b 0
