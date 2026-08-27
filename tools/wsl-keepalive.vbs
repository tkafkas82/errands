' Launch the WSL keep-alive with no console window.
'
' wsl.exe always opens a console; run through wscript with window style 0 so
' nothing flashes on screen at logon. Installed as a Scheduled Task by
' install-keepalive.cmd.

Dim sh
Set sh = CreateObject("WScript.Shell")

sh.Run "wsl.exe -d Ubuntu -e bash -lc ""'/mnt/c/Users/PC/Desktop/bat files/Errands/tools/keepalive.sh'""", 0, False
