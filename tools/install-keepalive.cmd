@echo off
REM Install the logon hook that starts the Ubuntu distro and the ERRANDS stack.
REM
REM Needed because vmIdleTimeout=-1 in %USERPROFILE%\.wslconfig stops WSL from
REM killing a *running* VM, but nothing starts the distro after a Windows
REM restart. This hook boots it at logon and holds a session open.
REM
REM Uses the per-user Startup folder rather than a Scheduled Task, because
REM schtasks /Create needs Administrator and this does not. Run once; re-running
REM just overwrites the stub.

setlocal

set STARTUP=%APPDATA%\Microsoft\Windows\Start Menu\Programs\Startup
set STUB=%STARTUP%\ErrandsWSLKeepAlive.vbs
set TARGET=%~dp0wsl-keepalive.vbs

if not exist "%TARGET%" (
    echo Cannot find "%TARGET%".
    pause
    exit /b 1
)

echo Installing logon hook...
echo   startup stub: %STUB%
echo   runs:         %TARGET%
echo.

REM The stub only forwards to the script in the project, so edits there take
REM effect without reinstalling.
>  "%STUB%" echo ' Starts the ERRANDS WSL keep-alive at logon. Installed by
>> "%STUB%" echo ' Errands\tools\install-keepalive.cmd — delete this file to disable.
>> "%STUB%" echo CreateObject("WScript.Shell").Run "wscript.exe ""%TARGET%""", 0, False

if not exist "%STUB%" (
    echo Failed to write the startup stub.
    pause
    exit /b 1
)

echo Installed.
echo.
echo   Start it now without logging out:
echo       wscript.exe "%TARGET%"
echo.
echo   Disable:
echo       del "%STUB%"
echo.
echo   Check it is holding the distro:
echo       wsl -d Ubuntu -e pgrep -af errands-keepalive
echo.

endlocal
