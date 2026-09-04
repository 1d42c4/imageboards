@echo off
setlocal

set "PHP_EXE=C:\php\php.exe"
set "HOST=127.0.0.1"
set "PORT=8000"

cd /d "%~dp0"

if not exist "%PHP_EXE%" (
    echo ERROR: PHP was not found at "%PHP_EXE%".
    echo Update PHP_EXE in this file if PHP is installed elsewhere.
    pause
    exit /b 1
)

echo Starting TinyIB at http://%HOST%:%PORT%/imgboard.php
echo Press Ctrl+C in this window to stop the server.
echo.

start "" /b powershell.exe -NoProfile -WindowStyle Hidden -Command "Start-Sleep -Milliseconds 750; Start-Process 'http://%HOST%:%PORT%/imgboard.php'"
"%PHP_EXE%" -S "%HOST%:%PORT%" -t "%~dp0."

set "EXIT_CODE=%ERRORLEVEL%"
if not "%EXIT_CODE%"=="0" (
    echo.
    echo PHP stopped with exit code %EXIT_CODE%.
    pause
)

exit /b %EXIT_CODE%
