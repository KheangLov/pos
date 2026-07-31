@echo off
cd /d "%~dp0"

REM Clear the cache start.bat built (config/routes/views) so the next
REM start.bat run always rebuilds it fresh against current code - avoids
REM ever serving stale cached config/routes after edits.
docker compose exec -T laravel.test php artisan optimize:clear >nul 2>&1

echo Stopping Omni POS containers...
docker compose down

echo.
echo Stopped. Your data is preserved (run start.bat to resume).
if /i not "%~1"=="/silent" pause
