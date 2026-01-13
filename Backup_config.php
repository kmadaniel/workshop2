<?php

/**
 * Backup Database Function
 * * @param string $host      Database Host (e.g., 'localhost' or '10.147.xx.xx')
 * @param string $user      Database User
 * @param string $pass      Database Password
 * @param string $dbname    Database Name
 * @param string $dbType    Type of DB: 'mysql', 'pgsql', 'mssql'
 * @param string $outputDir Directory to save the backup
 * * @return string Result message
 */
function backupDatabase($host, $user, $pass, $dbname, $dbType = 'mysql', $outputDir = './backups/') {
    
    // Ensure backup directory exists
    if (!is_dir($outputDir)) {
        mkdir($outputDir, 0755, true);
    }

    $timestamp = date("Y-m-d_H-i-s");
    $filename = $outputDir . $dbname . "_backup_" . $timestamp . ".sql";
    
    $command = "";

    switch (strtolower($dbType)) {
        case 'mysql':
            // MySQL Dump Command
            // Usage: mysqldump -h [host] -u [user] -p[pass] [dbname] > [file]
            // Note: No space between -p and password
            $command = "mysqldump -h " . escapeshellarg($host) . " -u " . escapeshellarg($user) . " -p" . escapeshellarg($pass) . " " . escapeshellarg($dbname) . " > " . escapeshellarg($filename);
            break;

        case 'postgresql':
        case 'pgsql':
            // PostgreSQL Dump Command
            // Usage: PGPASSWORD='pass' pg_dump -h [host] -U [user] -d [dbname] -f [file]
            // Note: Setting PGPASSWORD environment variable inline
            $command = "PGPASSWORD=" . escapeshellarg($pass) . " pg_dump -h " . escapeshellarg($host) . " -U " . escapeshellarg($user) . " -d " . escapeshellarg($dbname) . " -f " . escapeshellarg($filename);
            break;

        case 'mssql':
        case 'sqlserver':
            // Microsoft SQL Server Backup Command via sqlcmd
            // Usage: sqlcmd -S [host] -U [user] -P [pass] -Q "BACKUP DATABASE [dbname] TO DISK='[file]'"
            // Note: MSSQL usually saves to the server's local disk, not the client running the script.
            // If running remotely, you usually dump to a remote path or use a different tool (like mssql-scripter).
            // This command assumes the PHP script has write access to the server's disk or shared path.
            
            // For a PHP-generated .sql script (structure+data), 'mssql-scripter' (Python tool) is preferred if installed.
            // Below is the standard T-SQL backup command:
            $query = "BACKUP DATABASE [" . $dbname . "] TO DISK='" . $filename . "'";
            $command = "sqlcmd -S " . escapeshellarg($host) . " -U " . escapeshellarg($user) . " -P " . escapeshellarg($pass) . " -Q " . escapeshellarg($query);
            break;

        default:
            return "Error: Unsupported database type '$dbType'.";
    }

    // Execute the command
    $output = [];
    $returnVar = null;
    
    // Security Note: exec() can be dangerous. Ensure inputs are sanitized.
    exec($command, $output, $returnVar);

    if ($returnVar === 0) {
        return "Success: Backup created at " . $filename;
    } else {
        return "Error: Backup failed. Return code: " . $returnVar;
    }
}

// --- Example Usage (Commented Out) ---
// echo backupDatabase('localhost', 'root', 'password123', 'relief_db', 'mysql');
// echo backupDatabase('10.147.17.154', 'postgres', 'secret', 'distribution_db', 'pgsql');

?>