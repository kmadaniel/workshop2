<?php
// auto_backup.php - COMPLETE WORKING VERSION
// Save to: C:\workshop\workshop2\admin\auto_backup.php

// Display all errors
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Starting PostgreSQL backup...\n";
echo "Time: " . date('Y-m-d H:i:s') . "\n\n";

// 1. Include database connection
$dbPath = __DIR__ . '/../db.php';
if (!file_exists($dbPath)) {
    die("❌ ERROR: db.php not found at: $dbPath\n");
}

require_once $dbPath;

if (!isset($conn)) {
    die("❌ ERROR: Database connection failed. \$conn is not set.\n");
}

// Test connection
try {
    $conn->query("SELECT 1");
    echo "✅ Database connection successful\n";
} catch (Exception $e) {
    die("❌ Database connection failed: " . $e->getMessage() . "\n");
}

// 2. Configuration
$backupDir = __DIR__ . '/../backups/';
$keepDays = 7;
$maxBackups = 20;

// 3. Create backup directory
if (!file_exists($backupDir)) {
    if (!mkdir($backupDir, 0755, true)) {
        die("❌ ERROR: Could not create backup directory: $backupDir\n");
    }
    echo "✅ Created backup directory: $backupDir\n";
} else {
    echo "✅ Backup directory exists: $backupDir\n";
}

// 4. Get all tables (PostgreSQL)
try {
    $tables = array();
    echo "Fetching tables from PostgreSQL...\n";
    
    $result = $conn->query("SELECT tablename FROM pg_tables WHERE schemaname = 'public' ORDER BY tablename");
    
    if (!$result) {
        die("❌ Error executing table query\n");
    }
    
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        $tables[] = $row['tablename'];
    }
    
    if (empty($tables)) {
        die("❌ No tables found in database.\n");
    }
    
    echo "✅ Found " . count($tables) . " tables: " . implode(', ', $tables) . "\n\n";
} catch (Exception $e) {
    die("❌ Error getting tables: " . $e->getMessage() . "\n");
}

// 5. Start building SQL content
$sqlContent = "-- PostgreSQL Database Backup\n";
$sqlContent .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
$sqlContent .= "-- Database: victimdisaster\n";
$sqlContent .= "-- Total Tables: " . count($tables) . "\n\n";

// Disable triggers for data insertion
$sqlContent .= "SET session_replication_role = 'replica';\n\n";

$totalRows = 0;
$tableCount = 0;

foreach ($tables as $table) {
    $tableCount++;
    echo "[$tableCount/" . count($tables) . "] Processing table: $table\n";
    
    // Add table header
    $sqlContent .= "--\n-- Table: \"$table\"\n--\n";
    
    // Get table structure - SIMPLE VERSION
    try {
        // First, get basic column information
        $columns = array();
        $colResult = $conn->query("
            SELECT 
                column_name,
                data_type,
                is_nullable,
                column_default
            FROM information_schema.columns
            WHERE table_name = '$table'
            ORDER BY ordinal_position
        ");
        
        $columnDefs = array();
        while ($col = $colResult->fetch(PDO::FETCH_ASSOC)) {
            $type = strtoupper($col['data_type']);
            $nullable = ($col['is_nullable'] == 'YES') ? '' : ' NOT NULL';
            $default = '';
            
            if ($col['column_default']) {
                $default = ' DEFAULT ' . $col['column_default'];
            }
            
            $columnDefs[] = '"' . $col['column_name'] . '" ' . $type . $nullable . $default;
        }
        
        // Get primary key
        $pkResult = $conn->query("
            SELECT c.column_name
            FROM information_schema.table_constraints tc
            JOIN information_schema.constraint_column_usage AS ccu USING (constraint_schema, constraint_name)
            JOIN information_schema.columns AS c ON c.table_schema = tc.constraint_schema
                AND tc.table_name = c.table_name AND ccu.column_name = c.column_name
            WHERE constraint_type = 'PRIMARY KEY' AND tc.table_name = '$table'
        ");
        
        $primaryKeys = array();
        while ($pk = $pkResult->fetch(PDO::FETCH_ASSOC)) {
            $primaryKeys[] = $pk['column_name'];
        }
        
        // Build CREATE TABLE statement
        $createSql = "CREATE TABLE \"$table\" (\n    ";
        $createSql .= implode(",\n    ", $columnDefs);
        
        if (!empty($primaryKeys)) {
            $createSql .= ",\n    PRIMARY KEY (\"" . implode('", "', $primaryKeys) . "\")";
        }
        
        $createSql .= "\n);";
        
        $sqlContent .= "DROP TABLE IF EXISTS \"$table\" CASCADE;\n";
        $sqlContent .= $createSql . "\n\n";
        
    } catch (Exception $e) {
        $sqlContent .= "-- Error getting structure for $table: " . $e->getMessage() . "\n\n";
        continue;
    }
    
    // Get table data
    try {
        $result = $conn->query("SELECT * FROM \"$table\"");
        $rowCount = 0;
        
        // Get column names for this table
        $colInfo = $conn->query("SELECT column_name FROM information_schema.columns WHERE table_name = '$table' ORDER BY ordinal_position");
        $columns = array();
        while ($col = $colInfo->fetch(PDO::FETCH_ASSOC)) {
            $columns[] = $col['column_name'];
        }
        
        while ($data = $result->fetch(PDO::FETCH_ASSOC)) {
            $values = array();
            
            foreach ($columns as $column) {
                $value = isset($data[$column]) ? $data[$column] : null;
                
                if ($value === null) {
                    $values[] = 'NULL';
                } else {
                    // Escape for PostgreSQL
                    $escaped_value = str_replace("'", "''", (string)$value);
                    $values[] = "'" . $escaped_value . "'";
                }
            }
            
            $sqlContent .= 'INSERT INTO "' . $table . '" ("' . implode('", "', $columns) . '") VALUES (' . implode(', ', $values) . ");\n";
            $rowCount++;
            $totalRows++;
        }
        
        if ($rowCount > 0) {
            $sqlContent .= "-- Total rows inserted: $rowCount\n\n";
        } else {
            $sqlContent .= "-- Table is empty (0 rows)\n\n";
        }
        
        echo "   ↳ Rows: $rowCount\n";
        
    } catch (Exception $e) {
        $sqlContent .= "-- Error getting data from $table: " . $e->getMessage() . "\n\n";
        echo "   ↳ Error: " . $e->getMessage() . "\n";
    }
}

// Re-enable triggers
$sqlContent .= "\n-- Re-enable triggers\n";
$sqlContent .= "SET session_replication_role = 'origin';\n";

// 6. Save backup
$timestamp = time();
$filename = 'backup_' . date('Y-m-d_His', $timestamp) . '.sql';
$filepath = $backupDir . $filename;

echo "\n📝 Writing backup file: $filename\n";

if (file_put_contents($filepath, $sqlContent)) {
    $fileSize = filesize($filepath);
    
    echo "\n✅✅✅ BACKUP COMPLETED SUCCESSFULLY!\n";
    echo "=========================================\n";
    echo "📁 File: $filename\n";
    echo "📊 Tables: " . count($tables) . "\n";
    echo "📝 Total rows: $totalRows\n";
    echo "💾 File size: " . formatBytes($fileSize) . "\n";
    echo "📍 Location: $filepath\n";
    echo "=========================================\n";
    
    // 7. Log to text file
    $logMessage = date('Y-m-d H:i:s') . " | Backup: $filename | Tables: " . count($tables) . " | Rows: $totalRows | Size: " . formatBytes($fileSize) . "\n";
    file_put_contents($backupDir . 'backup_log.txt', $logMessage, FILE_APPEND);
    
    // 8. Clean up old backups
    echo "\n🧹 Cleaning up old backups...\n";
    $deletedCount = cleanupOldBackups($backupDir, $keepDays, $maxBackups);
    
    if ($deletedCount > 0) {
        echo "🗑️  Deleted $deletedCount old backup(s)\n";
    } else {
        echo "✅ No old backups to delete\n";
    }
    
    // Show current backups
    echo "\n📋 Current backups in folder:\n";
    $backups = glob($backupDir . 'backup_*.sql');
    if (!empty($backups)) {
        foreach ($backups as $backup) {
            echo "   • " . basename($backup) . " (" . formatBytes(filesize($backup)) . ")\n";
        }
    }
    
} else {
    die("\n❌ ERROR: Failed to write backup file: $filepath\n");
}

// Helper functions
function cleanupOldBackups($backupDir, $keepDays, $maxBackups) {
    $files = glob($backupDir . 'backup_*.sql');
    $deletedCount = 0;
    
    if (empty($files)) {
        return 0;
    }
    
    // Sort by modification time (oldest first)
    usort($files, function($a, $b) {
        return filemtime($a) - filemtime($b);
    });
    
    $currentTime = time();
    $cutoffTime = $currentTime - ($keepDays * 24 * 60 * 60);
    
    // Delete backups older than $keepDays
    foreach ($files as $file) {
        if (filemtime($file) < $cutoffTime) {
            if (unlink($file)) {
                $deletedCount++;
            }
        }
    }
    
    // Keep only $maxBackups most recent
    $files = glob($backupDir . 'backup_*.sql');
    usort($files, function($a, $b) {
        return filemtime($b) - filemtime($a);
    });
    
    if (count($files) > $maxBackups) {
        for ($i = $maxBackups; $i < count($files); $i++) {
            if (file_exists($files[$i])) {
                if (unlink($files[$i])) {
                    $deletedCount++;
                }
            }
        }
    }
    
    return $deletedCount;
}

function formatBytes($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}

// Close database connection
$conn = null;
echo "\n✅ Backup process finished at " . date('H:i:s') . "\n";
?>