@echo off
setlocal enabledelayedexpansion
cd /d "%~dp0"

echo ================================================
echo   Starting Omni POS (production)
echo ================================================
echo.

if not exist ".env.production" (
    echo   .env.production is missing.
    echo.
    echo   Copy .env.production.example to .env.production and fill in every
    echo   value marked CHANGE ME before starting.
    echo.
    pause
    exit /b 1
)

call :ensure_docker
if errorlevel 1 exit /b 1

call :machine_id

echo Starting containers...
docker compose -f compose.prod.yaml --env-file .env.production up -d
if errorlevel 1 (
    echo.
    echo Failed to start - see the output above.
    pause
    exit /b 1
)
echo.

call :wait_for_app

echo Ensuring the image storage bucket exists...
docker compose -f compose.prod.yaml --env-file .env.production exec -T app php artisan app:ensure-minio-bucket

echo.
echo Licence status:
docker compose -f compose.prod.yaml --env-file .env.production exec -T app php artisan license:show

echo.
echo ================================================
echo   Omni POS is running.
echo.
echo   Open the address set as APP_URL in .env.production
echo   Admin panel: ^<APP_URL^>/admin
echo.
echo   Stop it with stop-prod.bat
echo ================================================
echo.

if /i not "%~1"=="/silent" pause
exit /b 0

:machine_id
REM Hardware fingerprint for licence binding. MachineGuid is written by Windows
REM at install time; Win32_ComputerSystemProduct.UUID comes from the SMBIOS of
REM the physical machine. Hashing both together ties a licence to this host,
REM not merely to this copy of the application.
set LICENSE_MACHINE_ID=
REM Deliberately pipe-free: a `|` in this expression has to survive cmd's
REM parser, the for /f backquote block and PowerShell's, and escaping it
REM correctly in all three is a trap. BitConverter avoids the question.
for /f "usebackq delims=" %%i in (`powershell -NoProfile -ExecutionPolicy Bypass -Command "$g = (Get-ItemProperty 'HKLM:\SOFTWARE\Microsoft\Cryptography' -Name MachineGuid).MachineGuid; $u = (Get-CimInstance -ClassName Win32_ComputerSystemProduct).UUID; $sha = [System.Security.Cryptography.SHA256]::Create(); $b = $sha.ComputeHash([System.Text.Encoding]::UTF8.GetBytes($g + ':' + $u)); [System.BitConverter]::ToString($b).Replace('-','').ToLower()"`) do set LICENSE_MACHINE_ID=%%i

if "!LICENSE_MACHINE_ID!"=="" (
    echo   Warning: could not read this machine's hardware identifiers.
    echo   The licence will fall back to install-scoped binding.
    echo.
) else (
    echo Machine fingerprint resolved.
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
    echo   Docker did not start within 2 minutes.
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
set HEALTH=
for /f "tokens=*" %%i in ('docker compose -f compose.prod.yaml --env-file .env.production ps -q app 2^>nul') do set APP_CID=%%i
if not "!APP_CID!"=="" (
    for /f "tokens=*" %%i in ('docker inspect -f "{{.State.Health.Status}}" !APP_CID! 2^>nul') do set HEALTH=%%i
)
if "!HEALTH!"=="healthy" goto :wait_for_app_done
timeout /t 3 >nul
set /a APP_WAIT+=3
if !APP_WAIT! GEQ 180 (
    echo   App is taking longer than expected - check: docker compose -f compose.prod.yaml logs app
    goto :wait_for_app_done
)
goto :wait_for_app_loop

:wait_for_app_done
echo App is up.
echo.
exit /b 0
