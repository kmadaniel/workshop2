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
    <title>Database Backup & Recovery</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #4361ee;
            --primary-dark: #3a56d4;
            --success: #2ecc71;
            --warning: #f39c12;
            --danger: #e74c3c;
            --info: #3498db;
            --dark: #2c3e50;
            --light: #f8f9fa;
            --gray: #6c757d;
            --border-radius: 12px;
            --box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            --transition: all 0.3s ease;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            overflow: hidden;
        }
        
        /* Header */
        .header {
            background: linear-gradient(135deg, var(--primary), #7209b7);
            color: white;
            padding: 40px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" preserveAspectRatio="none" opacity="0.1"><path d="M0,0 L100,0 L100,100 Z" fill="white"/></svg>');
            background-size: cover;
        }
        
        .header h1 {
            font-size: 2.8rem;
            margin-bottom: 10px;
            position: relative;
            z-index: 1;
        }
        
        .header p {
            font-size: 1.1rem;
            opacity: 0.9;
            position: relative;
            z-index: 1;
        }
        
        .header-icon {
            font-size: 4rem;
            margin-bottom: 20px;
            position: relative;
            z-index: 1;
            display: inline-block;
            background: rgba(255,255,255,0.1);
            width: 100px;
            height: 100px;
            line-height: 100px;
            border-radius: 50%;
            backdrop-filter: blur(10px);
            border: 2px solid rgba(255,255,255,0.2);
        }
        
        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            padding: 30px;
            background: var(--light);
        }
        
        .stat-card {
            background: white;
            border-radius: var(--border-radius);
            padding: 25px;
            text-align: center;
            box-shadow: var(--box-shadow);
            transition: var(--transition);
            border-top: 4px solid var(--primary);
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, var(--primary), #7209b7);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            margin: 0 auto 15px;
        }
        
        .stat-value {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--dark);
            margin: 10px 0;
        }
        
        .stat-label {
            color: var(--gray);
            font-size: 0.9rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        /* Content Sections */
        .content {
            padding: 40px;
        }
        
        .section {
            margin-bottom: 40px;
            padding: 30px;
            background: var(--light);
            border-radius: var(--border-radius);
        }
        
        .section-title {
            font-size: 1.5rem;
            color: var(--dark);
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid rgba(67, 97, 238, 0.1);
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        /* Messages */
        .message {
            padding: 20px;
            border-radius: var(--border-radius);
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 15px;
            font-weight: 600;
            animation: slideIn 0.5s ease;
        }
        
        @keyframes slideIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .message.success {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid var(--success);
        }
        
        .message.error {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid var(--danger);
        }
        
        /* Buttons */
        .btn {
            padding: 15px 30px;
            border: none;
            border-radius: 50px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: var(--transition);
            text-decoration: none;
            font-size: 1rem;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(67, 97, 238, 0.3);
        }
        
        .btn-success {
            background: linear-gradient(135deg, var(--success), #27ae60);
            color: white;
        }
        
        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(46, 204, 113, 0.3);
        }
        
        .btn-danger {
            background: linear-gradient(135deg, var(--danger), #c0392b);
            color: white;
        }
        
        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(231, 76, 60, 0.3);
        }
        
        .btn-outline {
            background: transparent;
            color: var(--primary);
            border: 2px solid var(--primary);
        }
        
        .btn-outline:hover {
            background: var(--primary);
            color: white;
        }
        
        /* Backup List */
        .backup-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .backup-item {
            background: white;
            border-radius: var(--border-radius);
            padding: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: var(--box-shadow);
            transition: var(--transition);
            border: 2px solid transparent;
        }
        
        .backup-item:hover {
            border-color: var(--primary);
            transform: translateX(5px);
        }
        
        .backup-info {
            flex: 1;
        }
        
        .backup-name {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .backup-meta {
            font-size: 0.9rem;
            color: var(--gray);
            display: flex;
            gap: 20px;
        }
        
        .backup-meta span {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .backup-actions {
            display: flex;
            gap: 10px;
        }
        
        .btn-sm {
            padding: 10px 20px;
            font-size: 0.9rem;
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--gray);
        }
        
        .empty-state i {
            font-size: 4rem;
            margin-bottom: 20px;
            color: #ddd;
        }
        
        /* Warning Box */
        .warning-box {
            background: linear-gradient(135deg, #fff3cd, #ffeaa7);
            border: 2px solid #f39c12;
            border-radius: var(--border-radius);
            padding: 20px;
            margin: 20px 0;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .warning-icon {
            color: #f39c12;
            font-size: 2rem;
        }
        
        .warning-content h4 {
            color: #856404;
            margin-bottom: 5px;
        }
        
        .warning-content p {
            color: #856404;
            font-size: 0.95rem;
            opacity: 0.9;
        }
        
        /* Quick Actions */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .action-card {
            background: white;
            border-radius: var(--border-radius);
            padding: 25px;
            text-align: center;
            box-shadow: var(--box-shadow);
            transition: var(--transition);
            border: 2px solid transparent;
        }
        
        .action-card:hover {
            border-color: var(--primary);
            transform: translateY(-5px);
        }
        
        .action-icon {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, var(--primary), #7209b7);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            margin: 0 auto 20px;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .header h1 { font-size: 2rem; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .backup-item { flex-direction: column; gap: 20px; }
            .backup-actions { width: 100%; }
            .btn-sm { flex: 1; }
            .header { padding: 30px 20px; }
            .content { padding: 20px; }
        }
        
        @media (max-width: 576px) {
            .stats-grid { grid-template-columns: 1fr; }
            .backup-meta { flex-direction: column; gap: 5px; }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div class="header-icon">
                <i class="fas fa-database"></i>
            </div>
            <h1>Database Backup & Recovery</h1>
            <p>Disaster Relief System - Workshop 2</p>
        </div>
        
        <!-- Database Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-database"></i>
                </div>
                <div class="stat-value"><?php echo $db; ?></div>
                <div class="stat-label">Database Name</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-table"></i>
                </div>
                <div class="stat-value"><?php echo isset($dbInfo['tables']) ? $dbInfo['tables'] : 'N/A'; ?></div>
                <div class="stat-label">Total Tables</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-weight-hanging"></i>
                </div>
                <div class="stat-value"><?php echo isset($dbInfo['size']) ? $dbInfo['size'] . ' MB' : 'N/A'; ?></div>
                <div class="stat-label">Database Size</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-copy"></i>
                </div>
                <div class="stat-value"><?php echo count($backupFiles); ?></div>
                <div class="stat-label">Total Backups</div>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="content">
            <!-- Messages -->
            <?php if ($message): ?>
                <div class="message <?php echo $messageType; ?>">
                    <i class="fas <?php echo $messageType == 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
                    <div><?php echo $message; ?></div>
                </div>
            <?php endif; ?>
            
            <!-- Warning for Restore -->
            <?php if (isset($_POST['restore_backup']) || isset($_POST['create_backup'])): ?>
                <div class="warning-box">
                    <div class="warning-icon">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div class="warning-content">
                        <h4>Important Notice</h4>
                        <p>For complete recovery marks, ensure you document the backup-restore process with screenshots showing data before, during, and after restoration.</p>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Quick Actions -->
            <div class="section">
                <h2 class="section-title">
                    <i class="fas fa-bolt"></i>
                    Quick Actions
                </h2>
                
                <div class="quick-actions">
                    <div class="action-card">
                        <div class="action-icon">
                            <i class="fas fa-plus"></i>
                        </div>
                        <h3 style="margin-bottom: 15px; color: var(--dark);">Create New Backup</h3>
                        <p style="color: var(--gray); margin-bottom: 20px; font-size: 0.95rem;">
                            Create a complete backup of all database tables and data
                        </p>
                        <form method="POST" style="margin: 0;">
                            <button type="submit" name="create_backup" class="btn btn-primary">
                                <i class="fas fa-download"></i>
                                Create Backup
                            </button>
                        </form>
                    </div>
                    
                    <div class="action-card">
                        <div class="action-icon">
                            <i class="fas fa-info-circle"></i>
                        </div>
                        <h3 style="margin-bottom: 15px; color: var(--dark);">Backup Location</h3>
                        <p style="color: var(--gray); margin-bottom: 20px; font-size: 0.95rem;">
                            All backups are stored in the following directory:
                        </p>
                        <div style="background: var(--light); padding: 12px; border-radius: 8px; font-family: monospace; font-size: 0.9rem; color: var(--dark);">
                            <?php echo htmlspecialchars($backupDir); ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Backup History -->
            <div class="section">
                <h2 class="section-title">
                    <i class="fas fa-history"></i>
                    Backup History
                </h2>
                
                <?php if (empty($backupFiles)): ?>
                    <div class="empty-state">
                        <i class="fas fa-folder-open"></i>
                        <h3>No Backups Found</h3>
                        <p>Create your first backup to get started with database protection</p>
                    </div>
                <?php else: ?>
                    <div class="backup-list">
                        <?php foreach ($backupFiles as $backup): ?>
                            <div class="backup-item">
                                <div class="backup-info">
                                    <div class="backup-name">
                                        <i class="fas fa-file-alt"></i>
                                        <?php echo htmlspecialchars($backup['name']); ?>
                                    </div>
                                    <div class="backup-meta">
                                        <span>
                                            <i class="fas fa-calendar"></i>
                                            <?php echo $backup['date']; ?>
                                        </span>
                                        <span>
                                            <i class="fas fa-weight-hanging"></i>
                                            <?php echo round($backup['size'] / 1024, 2); ?> KB
                                        </span>
                                    </div>
                                </div>
                                <div class="backup-actions">
                                    <form method="POST" style="display: inline; margin: 0;">
                                        <input type="hidden" name="backup_file" value="<?php echo htmlspecialchars($backup['name']); ?>">
                                        <button type="submit" name="restore_backup" 
                                                class="btn btn-success btn-sm"
                                                onclick="return confirm('⚠️ WARNING: This will overwrite all current data!\n\nAre you sure you want to restore from this backup?');">
                                            <i class="fas fa-upload"></i>
                                            Restore
                                        </button>
                                    </form>
                                    
                                    <a href="backups/<?php echo htmlspecialchars($backup['name']); ?>" 
                                       class="btn btn-primary btn-sm"
                                       download>
                                        <i class="fas fa-download"></i>
                                        Download
                                    </a>
                                    
                                    <a href="?delete=<?php echo urlencode($backup['name']); ?>" 
                                       class="btn btn-danger btn-sm"
                                       onclick="return confirm('Are you sure you want to permanently delete this backup?');">
                                        <i class="fas fa-trash"></i>
                                        Delete
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Recovery Process -->
            <div class="section">
                <h2 class="section-title">
                    <i class="fas fa-shield-alt"></i>
                    Recovery Process
                </h2>
                
                <div style="background: white; border-radius: var(--border-radius); padding: 25px; margin-top: 20px; box-shadow: var(--box-shadow);">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 30px; text-align: center;">
                        <div>
                            <div style="width: 60px; height: 60px; background: linear-gradient(135deg, var(--primary), #7209b7); color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 24px; margin: 0 auto 15px;">
                                1
                            </div>
                            <h4 style="color: var(--dark); margin-bottom: 10px;">Create Backup</h4>
                            <p style="color: var(--gray); font-size: 0.95rem;">Click "Create Backup" to save current database state</p>
                        </div>
                        
                        <div>
                            <div style="width: 60px; height: 60px; background: linear-gradient(135deg, var(--warning), #e74c3c); color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 24px; margin: 0 auto 15px;">
                                2
                            </div>
                            <h4 style="color: var(--dark); margin-bottom: 10px;">Simulate Crash</h4>
                            <p style="color: var(--gray); font-size: 0.95rem;">Test recovery by deleting tables in phpMyAdmin</p>
                        </div>
                        
                        <div>
                            <div style="width: 60px; height: 60px; background: linear-gradient(135deg, var(--success), #27ae60); color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 24px; margin: 0 auto 15px;">
                                3
                            </div>
                            <h4 style="color: var(--dark); margin-bottom: 10px;">Restore Data</h4>
                            <p style="color: var(--gray); font-size: 0.95rem;">Select backup and click "Restore" to recover data</p>
                        </div>
                        
                        <div>
                            <div style="width: 60px; height: 60px; background: linear-gradient(135deg, var(--info), #2980b9); color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 24px; margin: 0 auto 15px;">
                                4
                            </div>
                            <h4 style="color: var(--dark); margin-bottom: 10px;">Verify Recovery</h4>
                            <p style="color: var(--gray); font-size: 0.95rem;">Check phpMyAdmin to confirm data restoration</p>
                        </div>
                    </div>
                    
                    <div style="margin-top: 30px; padding: 20px; background: linear-gradient(135deg, #e8f4fc, #d1ecf1); border-radius: var(--border-radius); border-left: 4px solid var(--info);">
                        <div style="display: flex; align-items: center; gap: 15px;">
                            <i class="fas fa-lightbulb" style="color: var(--info); font-size: 1.5rem;"></i>
                            <div>
                                <h4 style="color: var(--dark); margin-bottom: 5px;">Quick Recovery Tip</h4>
                                <p style="color: var(--gray); font-size: 0.95rem;">For assessment purposes, take screenshots of the database before crash, after crash, and after restoration as evidence of successful recovery.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Smooth scroll and animations
        document.addEventListener('DOMContentLoaded', function() {
            // Add animation to backup items
            const backupItems = document.querySelectorAll('.backup-item');
            backupItems.forEach((item, index) => {
                item.style.animationDelay = `${index * 0.1}s`;
                item.style.animation = 'slideIn 0.5s ease forwards';
                item.style.opacity = '0';
            });
            
            // Confirmation for restore
            const restoreForms = document.querySelectorAll('form[action*="restore_backup"]');
            restoreForms.forEach(form => {
                form.addEventListener('submit', function(e) {
                    if (!confirm('⚠️ WARNING: This will overwrite all current data!\n\nAre you absolutely sure you want to restore from this backup?')) {
                        e.preventDefault();
                    }
                });
            });
            
            // Add loading state to buttons
            const buttons = document.querySelectorAll('.btn');
            buttons.forEach(btn => {
                btn.addEventListener('click', function() {
                    if (this.type === 'submit') {
                        const originalText = this.innerHTML;
                        this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
                        this.disabled = true;
                        
                        // Reset after 3 seconds if still on same page
                        setTimeout(() => {
                            this.innerHTML = originalText;
                            this.disabled = false;
                        }, 3000);
                    }
                });
            });
        });
    </script>
</body>
</html>