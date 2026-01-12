@echo off
echo ====================================================================
echo SETTING UP BACKUP SYSTEM FOR WORKSHOP2 PROJECT
echo ====================================================================
echo.

REM Step 1: Create backups directory
echo [1/5] Creating backups directory...
if not exist "backups" mkdir backups
echo   ✓ Backups directory created

REM Step 2: Create Windows backup directories
echo [2/5] Creating Windows automated backup directories...
if not exist "C:\MySQL_Backups" mkdir C:\MySQL_Backups
if not exist "C:\MySQL_Backups\distribution" mkdir C:\MySQL_Backups\distribution
if not exist "C:\MySQL_Backups\distribution\daily" mkdir C:\MySQL_Backups\distribution\daily
if not exist "C:\MySQL_Backups\distribution\weekly" mkdir C:\MySQL_Backups\distribution\weekly
if not exist "C:\MySQL_Backups\distribution\monthly" mkdir C:\MySQL_Backups\distribution\monthly
if not exist "C:\MySQL_Backups\distribution\tables" mkdir C:\MySQL_Backups\distribution\tables
echo   ✓ Windows backup directories created

REM Step 3: Test web backup (open in browser)
echo [3/5] Opening web backup interface...
start http://10.147.17.154:8000/database_backup.php
echo   ✓ Browser opened

REM Step 4: Test database connection
echo [4/5] Testing database connection...
"C:\Program Files\MySQL\MySQL Server 9.5\bin\mysql.exe" -u root -pFrero@2950 -e "SELECT 'Connection successful!' AS Status; SHOW DATABASES LIKE 'distribution';" 2>nul
if %errorlevel% equ 0 (
    echo   ✓ Database connection successful
) else (
    echo   ✗ Database connection failed - check MySQL is running
)

REM Step 5: Display next steps
echo.
echo [5/5] Setup complete!
echo.
echo ====================================================================
echo NEXT STEPS:
echo ====================================================================
echo.
echo 1. WEB INTERFACE (Just created):
echo    URL: http://10.147.17.154:8000/database_backup.php
echo    Location: C:\xampp\htdocs\workshop2\database_backup.php
echo    Backups: C:\xampp\htdocs\workshop2\backups\
echo.
echo 2. AUTOMATED SYSTEM (For project submission):
echo    Setup: Run C:\MySQL_Backups\setup_scheduled_tasks.bat
echo    Location: C:\MySQL_Backups\
echo.
echo 3. TEST THE WEB INTERFACE:
echo    - Click "Create Backup Now"
echo    - Go to "Backup History" tab
echo    - Try restoring a backup
echo.
echo ====================================================================
pause