@echo off
REM setup_abhi_vhost.bat
REM Run this script as Administrator to add a hosts entry and VirtualHost for abhi.local

SETLOCAL ENABLEDELAYEDEXPANSION

REM Configuration - adjust if your XAMPP is installed elsewhere
SET HOSTNAME=abhi.local
SET HOSTS_FILE=C:\Windows\System32\drivers\etc\hosts
SET VHOSTS_FILE=C:\xampp\apache\conf\extra\httpd-vhosts.conf
SET PROJECT_DIR=C:\xampp\htdocs\abhi

echo This script will attempt to add %HOSTNAME% -> 127.0.0.1 to %HOSTS_FILE%
echo and append a VirtualHost to %VHOSTS_FILE% pointing to %PROJECT_DIR%.
echo You must run this script with Administrator privileges.
pause

REM Add hosts entry if missing
findstr /R /C:"\b%HOSTNAME%\b" "%HOSTS_FILE%" >nul 2>&1
IF %ERRORLEVEL%==0 (
    echo Hosts entry for %HOSTNAME% already exists in %HOSTS_FILE%
) ELSE (
    echo Adding hosts entry...
    echo 127.0.0.1    %HOSTNAME%>> "%HOSTS_FILE%"
    echo Added.
)

REM Ensure vhosts file exists
if not exist "%VHOSTS_FILE%" (
    echo ERROR: Cannot find %VHOSTS_FILE%. Is XAMPP installed in C:\xampp ?
    pause
    exit /b 1
)

REM Check if our vhost already present
findstr /I /C:"ServerName %HOSTNAME%" "%VHOSTS_FILE%" >nul 2>&1
IF %ERRORLEVEL%==0 (
    echo VirtualHost for %HOSTNAME% already present in %VHOSTS_FILE%
) ELSE (
    echo Appending VirtualHost to %VHOSTS_FILE%...
    >> "%VHOSTS_FILE%" echo.
    >> "%VHOSTS_FILE%" echo # VirtualHost for %HOSTNAME% - added by setup_abhi_vhost.bat
    >> "%VHOSTS_FILE%" echo ^<VirtualHost *:80^>
    >> "%VHOSTS_FILE%" echo     ServerName %HOSTNAME%
    >> "%VHOSTS_FILE%" echo     DocumentRoot "%PROJECT_DIR%"
    >> "%VHOSTS_FILE%" echo     ^<Directory "%PROJECT_DIR%"^>
    >> "%VHOSTS_FILE%" echo         Options Indexes FollowSymLinks Includes ExecCGI
    >> "%VHOSTS_FILE%" echo         Require all granted
    >> "%VHOSTS_FILE%" echo     ^</Directory^>
    >> "%VHOSTS_FILE%" echo     ErrorLog "logs/%HOSTNAME%-error.log"
    >> "%VHOSTS_FILE%" echo     CustomLog "logs/%HOSTNAME%-access.log" common
    >> "%VHOSTS_FILE%" echo ^</VirtualHost^>
    echo Appended.
)

REM Restart Apache
if exist "C:\xampp\apache\bin\httpd.exe" (
    echo Restarting Apache via httpd.exe -k restart
    "C:\xampp\apache\bin\httpd.exe" -k restart
    IF %ERRORLEVEL%==0 (
        echo Apache restarted successfully.
    ) ELSE (
        echo Failed to restart Apache via httpd.exe. Please restart Apache from XAMPP Control Panel.
    )
) ELSE (
    echo Apache executable not found at C:\xampp\apache\bin\httpd.exe. Please restart Apache manually.
)

echo Done. You can now open http://%HOSTNAME%/ in your browser.
pause
