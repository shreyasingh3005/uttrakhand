@echo off
REM import_db.bat
REM Helper to import database/all.sql into MySQL (XAMPP).

SETLOCAL ENABLEDELAYEDEXPANSION

SET "DEFAULT_MYSQL_EXE=C:\xampp\mysql\bin\mysql.exe"
SET "SCRIPT_DIR=%~dp0"
SET "SQL_FILE=%SCRIPT_DIR%..\database\all.sql"

echo === abhi: Database import helper ===

if not exist "%SQL_FILE%" (
    echo ERROR: SQL file not found: %SQL_FILE%
    pause
    exit /b 1
)

if exist "%DEFAULT_MYSQL_EXE%" (
    set "MYSQL_EXE=%DEFAULT_MYSQL_EXE%"
) else (
    set /p MYSQL_EXE="Path to mysql.exe (full path) [Press Enter for default %DEFAULT_MYSQL_EXE%]: "
    if "%MYSQL_EXE%"=="" set "MYSQL_EXE=%DEFAULT_MYSQL_EXE%"
)

if not exist "%MYSQL_EXE%" (
    echo ERROR: mysql executable not found: %MYSQL_EXE%
    pause
    exit /b 1
)

set /p DB_HOST="MySQL host [localhost]: "
if "%DB_HOST%"=="" set "DB_HOST=localhost"
set /p DB_USER="MySQL user [root]: "
if "%DB_USER%"=="" set "DB_USER=root"
set /p DB_PASS="MySQL password (leave blank for empty): "

echo.
echo Importing %SQL_FILE% into MySQL on %DB_HOST% as %DB_USER%...

if "%DB_PASS%"=="" (
    "%MYSQL_EXE%" -u "%DB_USER%" -h "%DB_HOST%" < "%SQL_FILE%"
) else (
    "%MYSQL_EXE%" -u "%DB_USER%" -p"%DB_PASS%" -h "%DB_HOST%" < "%SQL_FILE%"
)

if %ERRORLEVEL% EQU 0 (
    echo Import completed successfully.
) else (
    echo Import failed with exit code %ERRORLEVEL%.
)

pause
