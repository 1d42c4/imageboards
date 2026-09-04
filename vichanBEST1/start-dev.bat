@echo off
set PATH=C:\php;C:\Program Files\PostgreSQL\18\bin;%PATH%
cd /d "%~dp0"

if not exist .installed (
  echo Running CLI installer...
  php tools\install_cli.php
)

echo.
echo Serving http://127.0.0.1:8080/
echo Home:   http://127.0.0.1:8080/
echo Board:  http://127.0.0.1:8080/b/
echo Catalog:http://127.0.0.1:8080/b/catalog.html
echo Mod:    http://127.0.0.1:8080/mod.php  (admin / password)
echo.
php -S 127.0.0.1:8080 router.php
