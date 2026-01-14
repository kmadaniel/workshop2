<?php
// export_to_sql.php - EXPORT DATABASE AS SQL INSERT STATEMENTS

echo "========================================\n";
echo "📊 DATABASE EXPORT TO SQL FORMAT\n";
echo "========================================\n";
echo "Time: " . date('H:i:s') . "\n\n";

$host = "localhost";
$user = "yanadb";
$pass = "yana123";
$db   = "UserManagement";

// Connect
$conn = sqlsrv_connect($host, [
    "Database" => $db,
    "Uid" => $user,
    "PWD" => $pass,
    "CharacterSet" => "UTF-8"
]);

if (!$conn) {
    die("❌ Cannot connect to database\n");
}

echo "✅ Connected to: $db\n\n";

// Target folder for SQL file
$exportFolder = "C:\\Users\\User\\Desktop\\workshop2\\backups\\";
if (!file_exists($exportFolder)) {
    mkdir($exportFolder, 0777, true);
}

$sqlFileName = "UserManagement_Export_" . date('Ymd_His') . ".sql";
$sqlFilePath = $exportFolder . $sqlFileName;

// Open file for writing
$file = fopen($sqlFilePath, 'w');
if (!$file) {
    die("❌ Cannot create SQL file: $sqlFilePath\n");
}

echo "📝 Creating SQL file: $sqlFileName\n\n";

// Write SQL header
fwrite($file, "-- ========================================\n");
fwrite($file, "-- UserManagement Database Export\n");
fwrite($file, "-- Export Date: " . date('Y-m-d H:i:s') . "\n");
fwrite($file, "-- Database: $db\n");
fwrite($file, "-- ========================================\n\n");
fwrite($file, "USE [$db];\nGO\n\n");

// 1. GET ALL TABLE NAMES
echo "📋 Getting table list...\n";
$tablesQuery = "SELECT TABLE_NAME 
                FROM INFORMATION_SCHEMA.TABLES 
                WHERE TABLE_TYPE = 'BASE TABLE' 
                AND TABLE_CATALOG = '$db'
                ORDER BY TABLE_NAME";

$tablesStmt = sqlsrv_query($conn, $tablesQuery);
$tables = [];

if ($tablesStmt) {
    while ($row = sqlsrv_fetch_array($tablesStmt, SQLSRV_FETCH_ASSOC)) {
        $tables[] = $row['TABLE_NAME'];
    }
    echo "✅ Found " . count($tables) . " tables\n";
} else {
    echo "❌ Cannot get table list\n";
}

$totalRecords = 0;

// 2. EXPORT EACH TABLE
foreach ($tables as $table) {
    echo "\n📊 Exporting table: $table\n";
    
    // Get column information
    $columnsQuery = "SELECT COLUMN_NAME, DATA_TYPE 
                    FROM INFORMATION_SCHEMA.COLUMNS 
                    WHERE TABLE_NAME = ? 
                    AND TABLE_CATALOG = '$db'
                    ORDER BY ORDINAL_POSITION";
    
    $params = array($table);
    $columnsStmt = sqlsrv_query($conn, $columnsQuery, $params);
    
    $columns = [];
    if ($columnsStmt) {
        while ($col = sqlsrv_fetch_array($columnsStmt, SQLSRV_FETCH_ASSOC)) {
            $columns[] = $col;
        }
    }
    
    // Write table header to file
    fwrite($file, "\n-- ========================================\n");
    fwrite($file, "-- Table: $table\n");
    fwrite($file, "-- ========================================\n\n");
    
    // Get data from table
    $dataQuery = "SELECT * FROM [$table]";
    $dataStmt = sqlsrv_query($conn, $dataQuery);
    
    if ($dataStmt) {
        $rowCount = 0;
        
        // Write DELETE and INSERT statements
        fwrite($file, "-- DELETE existing data\n");
        fwrite($file, "DELETE FROM [$table];\nGO\n\n");
        fwrite($file, "-- INSERT new data\n");
        
        while ($row = sqlsrv_fetch_array($dataStmt, SQLSRV_FETCH_ASSOC)) {
            $rowCount++;
            
            // Build column names
            $colNames = [];
            foreach ($columns as $col) {
                $colNames[] = $col['COLUMN_NAME'];
            }
            $colList = "[" . implode("], [", $colNames) . "]";
            
            // Build values
            $values = [];
            foreach ($columns as $col) {
                $colName = $col['COLUMN_NAME'];
                $value = $row[$colName];
                
                if ($value === null) {
                    $values[] = "NULL";
                } else {
                    $dataType = $col['DATA_TYPE'];
                    
                    // Handle different data types
                    if (in_array($dataType, ['int', 'bigint', 'smallint', 'tinyint', 'bit', 'decimal', 'numeric', 'float', 'real'])) {
                        $values[] = $value;
                    } elseif ($dataType == 'datetime' || $dataType == 'datetime2' || $dataType == 'date' || $dataType == 'smalldatetime') {
                        // Format date properly
                        if ($value instanceof DateTime) {
                            $values[] = "'" . $value->format('Y-m-d H:i:s') . "'";
                        } else {
                            $values[] = "'" . date('Y-m-d H:i:s', strtotime($value)) . "'";
                        }
                    } elseif ($dataType == 'time') {
                        $values[] = "'" . $value . "'";
                    } else {
                        // String types - escape quotes
                        $escaped = str_replace("'", "''", $value);
                        $values[] = "'" . $escaped . "'";
                    }
                }
            }
            
            $valueList = implode(", ", $values);
            
            // Write INSERT statement
            fwrite($file, "INSERT INTO [$table] ($colList) VALUES ($valueList);\n");
            
            // Show progress
            if ($rowCount % 100 == 0) {
                echo "  Processed $rowCount rows...\n";
            }
        }
        
        fwrite($file, "GO\n\n");
        echo "  ✅ Exported $rowCount rows\n";
        $totalRecords += $rowCount;
        
        sqlsrv_free_stmt($dataStmt);
    } else {
        fwrite($file, "-- No data in table or error reading data\n");
        echo "  ⚠ No data or error\n";
    }
}

// 3. ADD STORED PROCEDURES (optional)
echo "\n📦 Exporting stored procedures...\n";
fwrite($file, "\n-- ========================================\n");
fwrite($file, "-- Stored Procedures\n");
fwrite($file, "-- ========================================\n\n");

$procsQuery = "SELECT name, OBJECT_DEFINITION(OBJECT_ID) AS definition 
              FROM sys.procedures 
              WHERE type = 'P' 
              ORDER BY name";

$procsStmt = sqlsrv_query($conn, $procsQuery);
if ($procsStmt) {
    $procCount = 0;
    while ($proc = sqlsrv_fetch_array($procsStmt, SQLSRV_FETCH_ASSOC)) {
        if (!empty($proc['definition'])) {
            fwrite($file, "-- Procedure: " . $proc['name'] . "\n");
            fwrite($file, $proc['definition'] . "\nGO\n\n");
            $procCount++;
        }
    }
    echo "✅ Exported $procCount stored procedures\n";
}

// Close file
fclose($file);

// 4. DISPLAY SUMMARY
echo "\n========================================\n";
echo "🎉 EXPORT COMPLETED SUCCESSFULLY!\n";
echo "========================================\n";
echo "File: $sqlFileName\n";
echo "Location: $exportFolder\n";
echo "Tables exported: " . count($tables) . "\n";
echo "Total records: $totalRecords\n";
echo "File size: " . round(filesize($sqlFilePath)/1024, 2) . " KB\n";

// Show preview of the file
echo "\n📄 File Preview (first 20 lines):\n";
echo "----------------------------------\n";

$previewLines = file($sqlFilePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
for ($i = 0; $i < min(20, count($previewLines)); $i++) {
    echo $previewLines[$i] . "\n";
}

echo "\n========================================\n";
echo "✅ Ready to import with SQL Server Management Studio\n";
echo "========================================\n";

sqlsrv_close($conn);

echo "\nPress Enter to open the file...";
fgets(STDIN);

// Open the SQL file
shell_exec("notepad.exe \"$sqlFilePath\"");
?>