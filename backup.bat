@echo off
echo ========================================
echo DATABASE BACKUP SYSTEM
echo ========================================
echo Date: %date%
echo Time: %time%
echo ========================================

cd /d "C:\Users\User\Desktop\workshop2"

echo Running backup script...
php database_backup.php

set EXIT_CODE=%errorlevel%

echo.
echo ========================================
if %EXIT_CODE% EQU 0 (
    echo ✅ BACKUP SUCCESSFUL
    echo Backup completed at: %time%
) else (
    echo ❌ BACKUP FAILED
    echo Error code: %EXIT_CODE%
)
echo ========================================

pause