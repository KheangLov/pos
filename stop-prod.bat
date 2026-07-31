@echo off
setlocal
cd /d "%~dp0"

echo Stopping Omni POS (production)...

REM `down` without -v: containers go away, named volumes (database, uploaded
REM images, search indexes, the installed licence) are kept.
docker compose -f compose.prod.yaml --env-file .env.production down

echo.
echo Stopped. All data is preserved - start-prod.bat picks up where you left off.
echo.

if /i not "%~1"=="/silent" pause
exit /b 0
