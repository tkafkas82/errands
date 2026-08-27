@echo off
REM Start the local ERRANDS WordPress site. Docker runs inside WSL Ubuntu.
setlocal

set PROJECT=/mnt/c/Users/PC/Desktop/bat files/Errands

echo Starting the ERRANDS stack...
wsl -d Ubuntu -e bash -lc "cd '%PROJECT%' && docker compose up -d"

if errorlevel 1 (
    echo.
    echo Failed to start. Is the Ubuntu WSL distro available and Docker running inside it?
    pause
    exit /b 1
)

echo.
echo   Site   http://localhost:8080
echo   Admin  http://localhost:8080/wp-admin   (admin / errands)
echo.

start "" http://localhost:8080
endlocal
