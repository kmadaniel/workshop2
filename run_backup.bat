@echo off
echo ========================================
echo Starting Automated Database Backup
echo ========================================
cd C:\xampp\htdocs\workshop2
C:\xampp\php-8.4.14\php.exe -f auto_backup.php
echo ========================================
echo Backup Completed!
echo ========================================
timeout /t 5