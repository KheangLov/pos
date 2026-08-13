@echo off
setlocal enabledelayedexpansion
cd /d "%~dp0"

echo ================================================
echo   Starting Omni POS
echo ================================================
echo.

call :ensure_env

call :ensure_docker
if errorlevel 1 exit /b 1

call :ensure_certs

echo Starting containers - app, reverb, queue, scheduler, caddy, postgres, redis, elasticsearch, minio (vite builds assets once and exits)...
docker compose up -d
if errorlevel 1 (
    echo.
    echo Failed to start containers - see the output above for details.
    pause
    exit /b 1
)
echo.

call :wait_for_app
call :ensure_app_key
call :wait_for_assets

echo Running database migrations...
docker compose exec -T laravel.test php artisan migrate --force

docker compose exec -T laravel.test php artisan app:ensure-seeded

echo Ensuring the MinIO image bucket exists...
docker compose exec -T laravel.test php artisan app:ensure-minio-bucket

echo Caching config/routes/views for faster local performance...
docker compose exec -T laravel.test php artisan optimize
docker compose exec -T laravel.test php artisan filament:optimize

REM Must run AFTER optimize: blade-icons' own icons:cache step (bundled into
REM optimize) under-counts icons on the Windows bind mount, which 500s any page
REM using an affected icon. See App\Console\Commands\CacheIcons.
docker compose exec -T laravel.test php artisan app:icons-cache

echo.
echo ================================================
echo   Omni POS is running!
echo.
echo   App:      http://localhost:8000
echo   Admin:    http://localhost:8000/admin
echo   Login:    admin@pos.test / password
echo.
echo   For phones (eMenu QR codes, camera barcode scanning): one-time
echo   setup via docker\certs\generate-and-trust.ps1 - see README.md's
echo   "Phone setup" section.
echo.
echo   Stop it any time by running stop.bat
echo ================================================
echo.

start "" "http://localhost:8000/admin"
if /i not "%~1"=="/silent" pause
exit /b 0

:ensure_env
REM First run on a fresh install (or fresh installer deployment) - no .env
REM yet. Reverb credentials in .env.example are fixed local-only values
REM (fine since Reverb here is never exposed past localhost); APP_KEY still
REM needs real generation, done below once the app container is healthy.
if not exist ".env" (
    echo Creating .env from .env.example...
    copy /y ".env.example" ".env" >nul
)
exit /b 0

:ensure_certs
REM caddy (TLS termination for phones - Phase 7) needs a cert to exist
REM before its container can start at all. Generates one scoped to
REM localhost/127.0.0.1/this machine's LAN IP if missing; safe to re-run
REM any time the LAN IP changes. Does NOT install the CA into any trust
REM store - Windows requires a manual click for that, see README.md.
if not exist "docker\certs\server-cert.pem" (
    echo Generating a local TLS certificate for phone/LAN access...
    powershell -ExecutionPolicy Bypass -File "docker\certs\generate-and-trust.ps1"
)
exit /b 0

:ensure_app_key
set APP_KEY_VALUE=
for /f "tokens=1,* delims==" %%a in ('findstr /b "APP_KEY=" .env') do set APP_KEY_VALUE=%%b
if "!APP_KEY_VALUE!"=="" (
    echo Generating application encryption key...
    docker compose exec -T laravel.test php artisan key:generate --force
)
exit /b 0

:ensure_docker
docker info >nul 2>&1
if not errorlevel 1 exit /b 0

echo Docker is not running yet - starting Docker Desktop...
set "DOCKER_DESKTOP=%ProgramFiles%\Docker\Docker\Docker Desktop.exe"
if not exist "%DOCKER_DESKTOP%" (
    echo   Could not find Docker Desktop at "%DOCKER_DESKTOP%".
    echo   Please start Docker Desktop manually, then re-run this script.
    pause
    exit /b 1
)
start "" "%DOCKER_DESKTOP%"

echo Waiting for Docker to come online - this can take a minute...
set DOCKER_WAIT=0

:ensure_docker_loop
timeout /t 3 >nul
set /a DOCKER_WAIT+=3
docker info >nul 2>&1
if not errorlevel 1 goto :ensure_docker_ready
if !DOCKER_WAIT! GEQ 120 (
    echo   Docker did not start within 2 minutes. Please check Docker Desktop and try again.
    pause
    exit /b 1
)
goto :ensure_docker_loop

:ensure_docker_ready
echo Docker is ready.
echo.
exit /b 0

:wait_for_app
echo Waiting for the app to become healthy...
set APP_WAIT=0

:wait_for_app_loop
for /f "tokens=*" %%i in ('docker inspect -f "{{.State.Health.Status}}" pos-laravel.test-1 2^>nul') do set HEALTH=%%i
if "!HEALTH!"=="healthy" goto :wait_for_app_done
timeout /t 3 >nul
set /a APP_WAIT+=3
if !APP_WAIT! GEQ 180 (
    echo   App is taking longer than expected - check logs with: docker compose logs laravel.test
    goto :wait_for_app_done
)
goto :wait_for_app_loop

:wait_for_app_done
echo App is up.
echo.
exit /b 0

:wait_for_assets
REM Stale from older dev-server runs - the vite service now builds once and
REM exits, so a leftover hot file would wrongly point Laravel at :5173.
if exist "public\hot" del /f /q "public\hot"

echo Building frontend assets - this can take a minute on first run...
set ASSET_WAIT=0

:wait_for_assets_loop
for /f "tokens=*" %%i in ('docker inspect -f "{{.State.Status}}" pos-vite-1 2^>nul') do set VITE_STATUS=%%i
if "!VITE_STATUS!"=="exited" goto :wait_for_assets_done
timeout /t 3 >nul
set /a ASSET_WAIT+=3
if !ASSET_WAIT! GEQ 180 (
    echo   Asset build is taking longer than expected - check logs with: docker compose logs vite
    goto :wait_for_assets_done
)
goto :wait_for_assets_loop

:wait_for_assets_done
for /f "tokens=*" %%i in ('docker inspect -f "{{.State.ExitCode}}" pos-vite-1 2^>nul') do set VITE_EXIT=%%i
if not "!VITE_EXIT!"=="0" (
    echo   Asset build failed - check logs with: docker compose logs vite
) else (
    echo Assets built.
)
echo.
exit /b 0
