@echo off
setlocal EnableExtensions DisableDelayedExpansion
cd /d "%~dp0"
set "VICHAN_PHP="
if exist "%~dp0php\php.exe" set "VICHAN_PHP=%~dp0php\php.exe"
if not defined VICHAN_PHP if exist "C:\php\php.exe" set "VICHAN_PHP=C:\php\php.exe"
if not defined VICHAN_PHP for %%P in (php.exe) do set "VICHAN_PHP=%%~$PATH:P"
if not defined VICHAN_PHP (
  echo PHP 8.5.10 or a newer PHP 8.5 patch release was not found.
  echo Install PHP under C:\php or place a php folder beside this launcher.
  pause
  exit /b 1
)
if not exist "%~dp0public\post.php" (
  echo Keep this launcher inside the Vichan Modern folder.
  pause
  exit /b 1
)
"%VICHAN_PHP%" "%~dp0bin\install.php"
if errorlevel 1 (
  echo Startup failed. Check your PHP extensions and configuration.
  pause
  exit /b 1
)
echo.
echo Vichan Modern: http://127.0.0.1:8088/
echo Moderator area: http://127.0.0.1:8088/mod.php
echo First-login details: var\first-login.txt
echo Keep this window open. Press Ctrl+C to stop the server.
echo.
if not defined VICHAN_NO_BROWSER start "" "http://127.0.0.1:8088/"
"%VICHAN_PHP%" -d upload_max_filesize=32M -d post_max_size=136M -d max_file_uploads=4 -d memory_limit=256M -d max_execution_time=60 -S 127.0.0.1:8088 -t "%~dp0public" "%~dp0router.php"
pause
