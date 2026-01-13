<?php
// Working Database Backup System for Workshop 2
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database Configuration - MATCHES YOUR CONFIG.PHP
$host = "localhost";
$user = "root";
$pass = "Frero@2950";
$db = "distribution";

// Create backups directory if not exists
$backupDir = __DIR__ . '/backups/';
if (!file_exists($backupDir)) {
    mkdir($backupDir, 0755, true);
}

$message = "";
$messageType = "";

// Handle backup creation
if (isset($_POST['create_backup'])) {
    try {
        $conn = new mysqli($host, $user, $pass, $db);
        
        if ($conn->connect_error) {
            throw new Exception("Connection failed: " . $conn->connect_error);
        }
        
        // Get all tables
        $tables = array();
        $result = $conn->query("SHOW TABLES");
        while ($row = $result->fetch_row()) {
            $tables[] = $row[0];
        }
        
        if (empty($tables)) {
            throw new Exception("No tables found in database '$db'");
        }
        
        $sqlContent = "-- Database Backup\n";
        $sqlContent .= "-- Date: " . date('Y-m-d H:i:s') . "\n";
        $sqlContent .= "-- Database: $db\n";
        $sqlContent .= "-- Tables: " . count($tables) . "\n\n";
        $sqlContent .= "SET FOREIGN_KEY_CHECKS=0;\n\n";
        
        // Loop through tables
        foreach ($tables as $table) {
            // Drop table if exists
            $sqlContent .= "-- Table: $table\n";
            $sqlContent .= "DROP TABLE IF EXISTS `$table`;\n\n";
            
            // Get create table statement
            $result = $conn->query("SHOW CREATE TABLE `$table`");
            $row = $result->fetch_row();
            $sqlContent .= $row[1] . ";\n\n";
            
            // Get table data
            $result = $conn->query("SELECT * FROM `$table`");
            $numColumns = $result->field_count;
            
            if ($result->num_rows > 0) {
                $sqlContent .= "-- Data for table `$table`\n";
                
                while ($row = $result->fetch_row()) {
                    $sqlContent .= "INSERT INTO `$table` VALUES(";
                    for ($j = 0; $j < $numColumns; $j++) {
                        if (isset($row[$j])) {
                            $value = addslashes($row[$j]);
                            $value = str_replace("\n", "\\n", $value);
                            $sqlContent .= '"' . $value . '"';
                        } else {
                            $sqlContent .= 'NULL';
                        }
                        if ($j < ($numColumns - 1)) {
                            $sqlContent .= ',';
                        }
                    }
                    $sqlContent .= ");\n";
                }
                $sqlContent .= "\n";
            }
        }
        
        $sqlContent .= "SET FOREIGN_KEY_CHECKS=1;\n";
        
        // Save backup file
        $backupFile = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
        $backupPath = $backupDir . $backupFile;
        
        if (file_put_contents($backupPath, $sqlContent)) {
            $fileSize = filesize($backupPath);
            $message = "✅ Backup created successfully!<br>";
            $message .= "📁 File: <strong>$backupFile</strong><br>";
            $message .= "📊 Size: <strong>" . round($fileSize / 1024, 2) . " KB</strong><br>";
            $message .= "📍 Location: <code>$backupPath</code><br>";
            $message .= "📋 Tables backed up: <strong>" . count($tables) . "</strong>";
            $messageType = "success";
        } else {
            throw new Exception("Failed to write backup file");
        }
        
        $conn->close();
        
    } catch (Exception $e) {
        $message = "❌ Error: " . $e->getMessage();
        $messageType = "error";
    }
}

// Handle restore
if (isset($_POST['restore_backup']) && isset($_POST['backup_file'])) {
    try {
        $backupFile = $backupDir . basename($_POST['backup_file']);
        
        if (!file_exists($backupFile)) {
            throw new Exception("Backup file not found!");
        }
        
        $conn = new mysqli($host, $user, $pass, $db);
        
        if ($conn->connect_error) {
            throw new Exception("Connection failed: " . $conn->connect_error);
        }
        
        // Read SQL file
        $sql = file_get_contents($backupFile);
        
        // Execute multi-query
        if ($conn->multi_query($sql)) {
            do {
                // Store first result set
                if ($result = $conn->store_result()) {
                    $result->free();
                }
            } while ($conn->next_result());
        }
        
        if ($conn->error) {
            throw new Exception("Restore error: " . $conn->error);
        }
        
        $conn->close();
        
        $message = "✅ Database restored successfully from: <strong>" . basename($_POST['backup_file']) . "</strong>";
        $messageType = "success";
        
    } catch (Exception $e) {
        $message = "❌ Restore Error: " . $e->getMessage();
        $messageType = "error";
    }
}

// Get list of backups
$backupFiles = array();
if (file_exists($backupDir)) {
    $files = glob($backupDir . 'backup_*.sql');
    foreach ($files as $file) {
        $backupFiles[] = array(
            'name' => basename($file),
            'size' => filesize($file),
            'date' => date('Y-m-d H:i:s', filemtime($file))
        );
    }
    usort($backupFiles, function($a, $b) {
        return strcmp($b['date'], $a['date']);
    });
}

// Delete backup
if (isset($_GET['delete']) && !empty($_GET['delete'])) {
    $fileToDelete = $backupDir . basename($_GET['delete']);
    if (file_exists($fileToDelete)) {
        if (unlink($fileToDelete)) {
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        }
    }
}

// Get database info
$dbInfo = array();
try {
    $conn = new mysqli($host, $user, $pass, $db);
    if (!$conn->connect_error) {
        $result = $conn->query("SELECT COUNT(*) as table_count FROM information_schema.tables WHERE table_schema = '$db'");
        $row = $result->fetch_assoc();
        $dbInfo['tables'] = $row['table_count'];
        
        $result = $conn->query("SELECT SUM(data_length + index_length) / 1024 / 1024 as size FROM information_schema.tables WHERE table_schema = '$db'");
        $row = $result->fetch_assoc();
        $dbInfo['size'] = round($row['size'], 2);
        
        $conn->close();
    }
} catch (Exception $e) {
    $dbInfo['error'] = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Backup & Recovery System</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px;
            min-height: 100vh;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }
        h1 {
            color: #333;
            margin-bottom: 10px;
            font-size: 2.5em;
            text-align: center;
        }
        .subtitle {
            color: #666;
            margin-bottom: 30px;
            text-align: center;
            font-size: 1.1em;
        }
        .db-info {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
            display: flex;
            justify-content: space-around;
            text-align: center;
        }
        .db-info div {
            flex: 1;
        }
        .db-info .label {
            color: #666;
            font-size: 0.9em;
            margin-bottom: 5px;
        }
        .db-info .value {
            color: #667eea;
            font-size: 2em;
            font-weight: bold;
        }
        .message {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid;
        }
        .message.success {
            background: #d4edda;
            border-color: #28a745;
            color: #155724;
        }
        .message.error {
            background: #f8d7da;
            border-color: #dc3545;
            color: #721c24;
        }
        .section {
            margin-bottom: 40px;
            padding: 30px;
            background: #f8f9fa;
            border-radius: 10px;
        }
        .section h2 {
            color: #333;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 3px solid #667eea;
        }
        .btn {
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-block;
            text-decoration: none;
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        .btn-danger:hover {
            background: #c82333;
            transform: translateY(-2px);
        }
        .btn-success {
            background: #28a745;
            color: white;
        }
        .btn-success:hover {
            background: #218838;
            transform: translateY(-2px);
        }
        .backup-list {
            margin-top: 20px;
        }
        .backup-item {
            background: white;
            padding: 20px;
            margin-bottom: 15px;
            border-radius: 8px;
            border-left: 4px solid #667eea;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .backup-info {
            flex: 1;
        }
        .backup-name {
            font-weight: bold;
            color: #333;
            margin-bottom: 5px;
            font-size: 1.1em;
        }
        .backup-meta {
            color: #666;
            font-size: 0.9em;
        }
        .backup-actions {
            display: flex;
            gap: 10px;
        }
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #999;
        }
        .empty-state i {
            font-size: 3em;
            margin-bottom: 15px;
        }
        form {
            margin: 20px 0;
        }
        .confirm-text {
            background: #fff3cd;
            border: 1px solid #ffc107;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            color: #856404;
        }
        .btn-group {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
        }
        code {
            background: #f4f4f4;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: monospace;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🗄️ Database Backup & Recovery</h1>
        <p class="subtitle">Database Administration System - Workshop 2</p>
        
        <!-- Database Info -->
        <div class="db-info">
            <div>
                <div class="label">Database Name</div>
                <div class="value" style="font-size: 1.5em;"><?php echo $db; ?></div>
            </div>
            <div>
                <div class="label">Total Tables</div>
                <div class="value"><?php echo isset($dbInfo['tables']) ? $dbInfo['tables'] : 'N/A'; ?></div>
            </div>
            <div>
                <div class="label">Database Size</div>
                <div class="value"><?php echo isset($dbInfo['size']) ? $dbInfo['size'] . ' MB' : 'N/A'; ?></div>
            </div>
            <div>
                <div class="label">Total Backups</div>
                <div class="value"><?php echo count($backupFiles); ?></div>
            </div>
        </div>
        
        <?php if ($message): ?>
            <div class="message <?php echo $messageType; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>
        
        <!-- Create Backup Section -->
        <div class="section">
            <h2>📥 Create New Backup</h2>
            <p style="margin-bottom: 20px; color: #666;">
                Create a complete backup of your database including all tables and data.
            </p>
            <form method="POST" action="">
                <button type="submit" name="create_backup" class="btn btn-primary" style="font-size: 18px; padding: 15px 40px;">
                    ⬇️ Create Backup Now
                </button>
            </form>
            <p style="margin-top: 15px; color: #666; font-size: 0.9em;">
                ℹ️ Backup will be saved to: <code><?php echo $backupDir; ?></code>
            </p>
        </div>
        
        <!-- Backup History -->
        <div class="section">
            <h2>📜 Backup History</h2>
            
            <?php if (empty($backupFiles)): ?>
                <div class="empty-state">
                    <div style="font-size: 3em;">📁</div>
                    <h3>No Backups Found</h3>
                    <p>Create your first backup to get started</p>
                </div>
            <?php else: ?>
                <div class="backup-list">
                    <?php foreach ($backupFiles as $backup): ?>
                        <div class="backup-item">
                            <div class="backup-info">
                                <div class="backup-name">📄 <?php echo htmlspecialchars($backup['name']); ?></div>
                                <div class="backup-meta">
                                    📅 Created: <?php echo $backup['date']; ?> | 
                                    💾 Size: <?php echo round($backup['size'] / 1024, 2); ?> KB
                                </div>
                            </div>
                            <div class="backup-actions">
                                <form method="POST" style="display: inline; margin: 0;">
                                    <input type="hidden" name="backup_file" value="<?php echo htmlspecialchars($backup['name']); ?>">
                                    <button type="submit" name="restore_backup" class="btn btn-success" 
                                            onclick="return confirm('⚠️ WARNING: This will overwrite all current data!\n\nAre you sure you want to restore from this backup?');">
                                        ⬆️ Restore
                                    </button>
                                </form>
                                <a href="backups/<?php echo htmlspecialchars($backup['name']); ?>" 
                                   class="btn btn-primary" download>
                                    💾 Download
                                </a>
                                <a href="?delete=<?php echo urlencode($backup['name']); ?>" 
                                   class="btn btn-danger"
                                   onclick="return confirm('Are you sure you want to delete this backup?');">
                                    🗑️ Delete
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Instructions -->
        <div class="section">
            <h2>📋 Instructions for Full Marks</h2>
            <h3 style="margin-top: 20px; color: #667eea;">To Score Full Marks (15/15) for Recovery:</h3>
            <ol style="line-height: 2; margin-left: 20px; color: #333;">
                <li><strong>Create a backup</strong> - Click "Create Backup Now" button above</li>
                <li><strong>Take screenshot</strong> - Show your database has data (phpMyAdmin)</li>
                <li><strong>Crash the database</strong> - Go to phpMyAdmin and run: <code>DROP TABLE table_name;</code></li>
                <li><strong>Take screenshot</strong> - Show the table is missing/crashed</li>
                <li><strong>Restore backup</strong> - Click "Restore" button on any backup above</li>
                <li><strong>Take screenshot</strong> - Show data is recovered successfully</li>
                <li><strong>Verify data integrity</strong> - Check that all data matches the original</li>
            </ol>
            
            <h3 style="margin-top: 30px; color: #667eea;">Evidence Required:</h3>
            <ul style="line-height: 2; margin-left: 20px; color: #333;">
                <li>✅ Screenshot: Before crash (tables with data)</li>
                <li>✅ Screenshot: After crash (missing/broken tables)</li>
                <li>✅ Screenshot: Restore process (this page)</li>
                <li>✅ Screenshot: After restore (recovered data)</li>
            </ul>
        </div>
    </div>
</body>
</html>