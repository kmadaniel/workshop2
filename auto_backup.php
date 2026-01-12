<?php
require_once 'config.php';

// Create backup directory
$backupDir = __DIR__ . '/backups/';
if (!file_exists($backupDir)) {
    mkdir($backupDir, 0755, true);
}

// Connect to database
$conn = new mysqli("localhost", "root", "Frero@2950", "distribution");

// Get all tables
$tables = array();
$result = $conn->query("SHOW TABLES");
while ($row = $result->fetch_row()) {
    $tables[] = $row[0];
}

$sqlContent = "-- Automated Backup - " . date('Y-m-d H:i:s') . "\n\n";

foreach ($tables as $table) {
    $result = $conn->query("SHOW CREATE TABLE `$table`");
    $row = $result->fetch_row();
    $sqlContent .= "DROP TABLE IF EXISTS `$table`;\n";
    $sqlContent .= $row[1] . ";\n\n";
    
    $result = $conn->query("SELECT * FROM `$table`");
    while ($row = $result->fetch_row()) {
        $sqlContent .= "INSERT INTO `$table` VALUES(";
        foreach ($row as $key => $value) {
            $sqlContent .= isset($value) ? '"' . addslashes($value) . '"' : 'NULL';
            if ($key < count($row) - 1) $sqlContent .= ',';
        }
        $sqlContent .= ");\n";
    }
}

// Save backup
$filename = 'auto_backup_' . date('Y-m-d_H-i-s') . '.sql';
file_put_contents($backupDir . $filename, $sqlContent);

// Log
$log = date('Y-m-d H:i:s') . " - Automated backup created: $filename\n";
file_put_contents(__DIR__ . '/backup_log.txt', $log, FILE_APPEND);

echo "Automated backup completed: $filename";
?>