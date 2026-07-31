@echo off

set TASK_NAME=Omni POS Autostart

schtasks /delete /tn "%TASK_NAME%" /f
if errorlevel 1 (
    echo.
    echo No autostart task was found - nothing to remove.
) else (
    echo.
    echo Done. Omni POS will no longer start automatically at login.
)
pause
