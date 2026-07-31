@echo off
cd /d "%~dp0"

REM Registers a Windows scheduled task that runs start.bat (silently - no
REM pause, no confirmation window) whenever you log in, so Omni POS is
REM always up without having to double-click start.bat yourself. Docker
REM Desktop itself should also have its own "Start Docker Desktop when you
REM log in" setting turned on (Docker Desktop > Settings > General) so it's
REM ready by the time this task runs.

set TASK_NAME=Omni POS Autostart

schtasks /create /tn "%TASK_NAME%" /tr "\"%~dp0start.bat\" /silent" /sc onlogon /rl limited /f
if errorlevel 1 (
    echo.
    echo Failed to create the scheduled task - see the error above.
    pause
    exit /b 1
)

echo.
echo Done. Omni POS will now start automatically the next time you log in.
echo Run disable-autostart.bat any time to undo this.
pause
