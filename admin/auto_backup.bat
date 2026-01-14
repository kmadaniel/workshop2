@echo off
title PostgreSQL Auto Backup - Fixed Version
color 0A

echo ============================================
echo  POSTGRESQL DATABASE BACKUP
echo  Real Date: %date% 
echo  Real Time: %time%
echo ============================================
echo.

set "PHP_PATH=C:\Program Files\php-8.4.14\php.exe"
set "BACKUP_SCRIPT=auto_backup.php"
set "WORK_DIR=C:\workshop\workshop2\admin"

echo [CONFIG]
echo PHP Path: "%PHP_PATH%"
echo Script: %BACKUP_SCRIPT%
echo Working Dir: %WORK_DIR%
echo.

echo [VERIFICATION]
REM Check if PHP exists
if not exist "%PHP_PATH%" (
    echo ❌ ERROR: PHP not found!
    echo Path: %PHP_PATH%
    echo.
    echo Please update PHP_PATH in this batch file to your actual PHP location.
    pause
    exit /b 1
)

REM Go to working directory
cd /d "%WORK_DIR%"
echo Current directory: %cd%

REM Check if backup script exists
if not exist "%BACKUP_SCRIPT%" (
    echo ❌ ERROR: %BACKUP_SCRIPT% not found!
    echo.
    dir /b *.php
    pause
    exit /b 1
)

echo ✅ All files verified
echo.

echo [BACKUP EXECUTION]
echo Starting PostgreSQL backup process...
echo ============================================
echo.

"%PHP_PATH%" "%BACKUP_SCRIPT%"

set EXIT_CODE=%errorlevel%
echo.
echo ============================================
echo Exit code: %EXIT_CODE%

if %EXIT_CODE% equ 0 (
    echo ✅✅✅ BACKUP SUCCESSFUL!
    echo.
    
    REM Show backup files
    if exist "..\backups\backup_*.sql" (
        echo 📁 Backup files created in: ..\backups\
        echo.
        dir /b "..\backups\backup_*.sql"
    )
) else (
    echo ❌❌❌ BACKUP FAILED!
    echo Error code: %EXIT_CODE%
)

echo.
echo ============================================
echo  Process completed at %time%
echo ============================================
echo.
pause