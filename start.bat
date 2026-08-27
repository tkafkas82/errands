@echo off
REM Start the local ERRANDS WordPress site. Docker runs inside WSL Ubuntu.
REM The real work is in up.sh, which waits for the database and makes sure
REM the web container has a live connection to it before opening the browser.
setlocal

set PROJECT=/mnt/c/Users/PC/Desktop/bat files/Errands

echo Starting the ERRANDS stack...
echo.

wsl -d Ubuntu -e bash -lc "cd '%PROJECT%' && ./up.sh"

if errorlevel 1 (
    echo.
    echo Failed to start. Is the Ubuntu WSL distro available and Docker running inside it?
    pause
    exit /b 1
)

echo   Admin login: admin / errands
echo.

start "" http://localhost:8080
endlocal
