@echo off
setlocal EnableExtensions
title Chessboard Lite Launcher

set "HOST=127.0.0.1"
set "PORT=8000"
set "APP_DIR=%~dp0"

rem The launcher is optional and only works from the project root.
if exist "%APP_DIR%public\router.php" goto app_found

echo.
echo  ERROR: Chessboard Lite was not found beside this launcher.
echo  Keep start-windows.bat beside README.md and the public folder.
echo.
pause
exit /b 1

:app_found
cd /d "%APP_DIR%"
if errorlevel 1 (
    echo.
    echo  ERROR: Could not open the app folder:
    echo  %APP_DIR%
    echo.
    pause
    exit /b 1
)

set "PHP_EXE="
for /f "delims=" %%P in ('where php.exe 2^>nul') do if not defined PHP_EXE set "PHP_EXE=%%P"
if defined PHP_EXE goto php_found

if exist "C:\php\php.exe" set "PHP_EXE=C:\php\php.exe"
if defined PHP_EXE goto php_found

echo.
echo  ERROR: php.exe was not found.
echo.
echo  Add PHP to your Windows PATH, or install it at C:\php\php.exe.
echo  Use php.exe, not php-cgi.exe.
echo.
pause
exit /b 1

:php_found
"%PHP_EXE%" -r "exit(PHP_VERSION_ID >= 80400 && extension_loaded('pdo_sqlite') && extension_loaded('mbstring') && extension_loaded('fileinfo') && extension_loaded('gd') && extension_loaded('session') && extension_loaded('json') ? 0 : 1);" >nul 2>&1
if errorlevel 1 (
    echo.
    echo  ERROR: Chessboard Lite needs PHP 8.4 or newer with:
    echo  PDO_SQLite, mbstring, fileinfo, GD, session, and JSON.
    echo.
    echo  PHP being checked:
    echo  %PHP_EXE%
    echo.
    echo  Run "%PHP_EXE%" -v and "%PHP_EXE%" -m to inspect it.
    echo.
    pause
    exit /b 1
)

netstat -ano | findstr ":%PORT%" | findstr "LISTENING" >nul
if not errorlevel 1 (
    echo.
    echo  ERROR: Port %PORT% is already in use.
    echo.
    echo  Stop the existing server with Ctrl+C, then run this file again.
    echo  You can also change PORT near the top of this batch file.
    echo.
    pause
    exit /b 1
)

echo.
echo  Starting Chessboard Lite...
echo.
echo  App:     %APP_DIR%
echo  PHP:     %PHP_EXE%
echo  Address: http://%HOST%:%PORT%/
echo.
echo  The browser will open automatically.
echo  Keep this window open and press Ctrl+C here to stop the server.
echo.

start "" /min powershell.exe -NoProfile -WindowStyle Hidden -Command "Start-Sleep -Milliseconds 900; Start-Process 'http://%HOST%:%PORT%/'"
"%PHP_EXE%" -S %HOST%:%PORT% -t public public\router.php
set "SERVER_EXIT=%ERRORLEVEL%"

echo.
echo  Chessboard Lite server stopped.
if not "%SERVER_EXIT%"=="0" echo  PHP exited with code %SERVER_EXIT%.
echo.
pause
exit /b %SERVER_EXIT%
