@echo off
title TinyIB / PHP Dev Server
cd /d C:\tinyib1
echo.
echo  PHP Dev Server starting...
echo  Open: http://localhost:8000/imgboard.php
echo.
echo  Press Ctrl+C to stop the server
echo.
C:\php\php.exe -S localhost:8000
pause