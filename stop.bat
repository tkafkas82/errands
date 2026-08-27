@echo off
REM Stop the local ERRANDS WordPress site. Data is kept in Docker volumes.
setlocal

set PROJECT=/mnt/c/Users/PC/Desktop/bat files/Errands

echo Stopping the ERRANDS stack...
wsl -d Ubuntu -e bash -lc "cd '%PROJECT%' && docker compose stop"

echo.
echo Stopped. Database and WordPress core are preserved in Docker volumes.
echo Run start.bat to bring it back up.
echo.
endlocal
