@echo off
setlocal
cd /d "%~dp0"

set "GLASS_LOGS_ROOT=C:\ALICE_BOX\.glass-logs"
set "PHP=C:\xampp\php\php.exe"
set "PORT=43101"

if not exist "%PHP%" (
  echo PHP not found at %PHP%
  exit /b 1
)

if not exist "work\catalog.json" (
  echo No catalog yet. Building OT+NT catalog + glass extracts...
  echo This can take several minutes on first run.
  call "%~dp0run-catalog.bat"
  if errorlevel 1 exit /b 1
)

echo.
echo  sdk-import-station  http://127.0.0.1:%PORT%/
echo  glass: %GLASS_LOGS_ROOT%
echo  work:  %cd%\work
echo.
"%PHP%" -S 127.0.0.1:%PORT% -t public public\router.php
