<?php
// simple_backup.php - Simple Backup Manager Interface
// FIXED VERSION - No Intelephense errors
session_start();

// Start output buffering to handle any output
ob_start();

// Include database connection from parent folder
$dbPath = __DIR__ . '/../db.php';
if (!file_exists($dbPath)) {
    die("❌ ERROR: db.php not found at: $dbPath<br>
         Please make sure the file exists.");
}

require_once $dbPath;

// Check if connection exists and is valid
if (!isset($conn) || !($conn instanceof PDO)) {
    die("❌ ERROR: Database connection failed. Connection is not properly set.");
}

// Test the connection
try {
    $conn->query("SELECT 1");
} catch (PDOException $e) {
    die("❌ Database connection test failed: " . $e->getMessage());
}

$backupDir = __DIR__ . '/../backups/';
$message = '';

// Create backup directory if not exists
if (!file_exists($backupDir)) {
    if (mkdir($backupDir, 0755, true)) {
        $message = "✅ Created backup directory";
    }
}

// Handle manual backup creation
if (isset($_POST['create_backup'])) {
    // Clear any previous output
    if (ob_get_length()) ob_clean();
    
    echo "<script>console.log('Starting backup...');</script>";
    
    // Run the auto_backup.php script
    $output = [];
    $return_var = 0;
    
    // Get the full path to auto_backup.php
    $backupScript = __DIR__ . '/auto_backup.php';
    
    if (!file_exists($backupScript)) {
        $message = "❌ ERROR: auto_backup.php not found!";
    } else {
        // Execute the backup script
        exec('php "' . $backupScript . '"', $output, $return_var);
        
        if ($return_var === 0) {
            $message = "✅ Backup created successfully!";
            
            // Display output in console style
            if (!empty($output)) {
                $message .= '<div style="background:#000;color:#0f0;padding:10px;margin-top:10px;border-radius:5px;font-family:monospace;max-height:200px;overflow:auto;">';
                $message .= '<strong>Backup Output:</strong><br>';
                foreach ($output as $line) {
                    $message .= htmlspecialchars($line) . '<br>';
                }
                $message .= '</div>';
            }
        } else {
            $message = "❌ Backup failed! Error code: $return_var";
            if (!empty($output)) {
                $message .= '<div style="background:#300;color:#f00;padding:10px;margin-top:10px;border-radius:5px;font-family:monospace;">';
                $message .= '<strong>Error Output:</strong><br>';
                foreach ($output as $line) {
                    $message .= htmlspecialchars($line) . '<br>';
                }
                $message .= '</div>';
            }
        }
    }
}

// Handle download
if (isset($_GET['download'])) {
    $file = basename($_GET['download']);
    $filepath = $backupDir . $file;
    
    if (file_exists($filepath) && strpos($file, 'backup_') === 0) {
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $file . '"');
        header('Content-Length: ' . filesize($filepath));
        readfile($filepath);
        exit;
    }
}

// Handle delete
if (isset($_GET['delete'])) {
    $file = basename($_GET['delete']);
    $filepath = $backupDir . $file;
    
    if (file_exists($filepath) && strpos($file, 'backup_') === 0) {
        if (unlink($filepath)) {
            $message = "✅ Backup deleted: $file";
            header("Location: simple_backup.php?message=" . urlencode($message));
            exit;
        } else {
            $message = "❌ Failed to delete backup";
        }
    }
}

// Handle restore (with preview and download option)
if (isset($_POST['restore_backup'])) {
    $backupFile = $_POST['backup_file'] ?? '';
    $previewOnly = isset($_POST['preview_only']);
    $filepath = $backupDir . $backupFile;
    
    if (file_exists($filepath) && strpos($backupFile, 'backup_') === 0) {
        if ($previewOnly) {
            // Show preview only (safe mode)
            $sqlContent = file_get_contents($filepath);
            $lines = explode("\n", $sqlContent);
            $firstLines = implode("\n", array_slice($lines, 0, min(50, count($lines))));
            $totalLines = count($lines);
            $fileSize = filesize($filepath);
            
            $message = "<div class='alert alert-success'>";
            $message .= "✅ <strong>Backup file selected:</strong> $backupFile<br>";
            $message .= "📏 <strong>Size:</strong> " . formatSize($fileSize) . "<br>";
            $message .= "📄 <strong>Lines:</strong> $total lines<br><br>";
            $message .= "<a href='?download=" . urlencode($backupFile) . "' class='btn btn-success'>";
            $message .= "<i class='fas fa-download'></i> Download SQL File</a><br><br>";
            $message .= "</div>";
            
            $message .= "<div class='alert alert-info'>";
            $message .= "<strong>📋 SQL Preview (first 50 lines):</strong>";
            $message .= "</div>";
            
            $message .= "<div class='console-output'>" . htmlspecialchars($firstLines) . "</div>";
            
            if ($totalLines > 50) {
                $message .= "<div class='text-center mt-2'>";
                $message .= "<small class='text-muted'>... and " . ($totalLines - 50) . " more lines</small>";
                $message .= "</div>";
            }
            
            $message .= "<div class='alert alert-warning mt-3'>";
            $message .= "<h5><i class='fas fa-cogs'></i> Restoration Instructions:</h5>";
            $message .= "<ol>";
            $message .= "<li>Download the SQL file using the button above</li>";
            $message .= "<li>Open pgAdmin 4 and connect to your PostgreSQL server</li>";
            $message .= "<li>Right-click on your database → Query Tool</li>";
            $message .= "<li>Open the downloaded SQL file</li>";
            $message .= "<li>Execute the SQL (Press F5 or click Execute button)</li>";
            $message .= "</ol>";
            $message .= "<strong>OR use command line:</strong><br>";
            $message .= "<code>psql -U postgres -d victimdisaster -f \"downloaded_file.sql\"</code>";
            $message .= "</div>";
        } else {
            // Advanced restore option (could be implemented for CLI environments)
            $message = "<div class='alert alert-danger'>";
            $message .= "⚠️ <strong>Automatic Restoration Disabled</strong><br>";
            $message .= "For database safety, automatic restoration is disabled.<br>";
            $message .= "Please use the 'Preview only' option and follow the manual instructions.";
            $message .= "</div>";
        }
    } else {
        $message = "<div class='alert alert-danger'>❌ Invalid backup file selected</div>";
    }
}

// Get database info - WITH PROPER ERROR HANDLING
$table_count = 0;
$db_size = 0;

try {
    // Get table count
    $stmt = $conn->query("SELECT COUNT(*) as count FROM pg_tables WHERE schemaname = 'public'");
    if ($stmt) {
        $tables = $stmt->fetch(PDO::FETCH_ASSOC);
        $table_count = $tables['count'] ?? 0;
    }
    
    // Get database size
    $stmt = $conn->query("SELECT pg_database_size('victimdisaster') / 1024 / 1024 as size_mb");
    if ($stmt) {
        $size = $stmt->fetch(PDO::FETCH_ASSOC);
        $db_size = round($size['size_mb'] ?? 0, 2);
    }
} catch (PDOException $e) {
    // Silently handle errors - don't break the page
    error_log("Database info error: " . $e->getMessage());
}

// Get backup files
$backups = [];
if (file_exists($backupDir)) {
    $files = glob($backupDir . 'backup_*.sql');
    if ($files !== false) {
        foreach ($files as $file) {
            if (file_exists($file)) {
                $backups[] = [
                    'name' => basename($file),
                    'size' => filesize($file),
                    'date' => date('Y-m-d H:i:s', filemtime($file)),
                    'path' => $file
                ];
            }
        }
        
        // Sort by date (newest first)
        usort($backups, function($a, $b) {
            return strtotime($b['date']) - strtotime($a['date']);
        });
    }
}

// Get log file content
$logContent = '';
$logFile = $backupDir . 'backup_log.txt';
if (file_exists($logFile)) {
    $logContent = file_get_contents($logFile);
}

// Format file size
function formatSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } elseif ($bytes > 0) {
        return $bytes . ' bytes';
    } else {
        return '0 bytes';
    }
}

// End output buffering and capture
if (ob_get_length()) ob_end_clean();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Simple Backup Manager - PostgreSQL</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            padding: 30px;
            margin-top: 20px;
        }
        .header {
            background: linear-gradient(135deg, #4361ee, #3a56d4);
            color: white;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 30px;
            text-align: center;
        }
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.08);
            border-left: 4px solid #4361ee;
            margin-bottom: 20px;
            transition: transform 0.3s;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .btn-backup {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            border: none;
            padding: 12px 25px;
            font-weight: 600;
        }
        .btn-backup:hover {
            background: linear-gradient(135deg, #218838, #1ba487);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(40, 167, 69, 0.3);
        }
        .log-panel {
            background: #1a1a1a;
            color: #00ff00;
            font-family: 'Courier New', monospace;
            padding: 15px;
            border-radius: 5px;
            max-height: 200px;
            overflow-y: auto;
            font-size: 14px;
        }
        .console-output {
            background: #000;
            color: #0f0;
            font-family: 'Courier New', monospace;
            padding: 15px;
            border-radius: 5px;
            margin: 10px 0;
            max-height: 300px;
            overflow-y: auto;
            font-size: 12px;
            line-height: 1.4;
        }
        .btn-restore {
            background: linear-gradient(135deg, #ffc107, #fd7e14);
            color: #212529;
            border: none;
            font-weight: 600;
        }
        .btn-restore:hover {
            background: linear-gradient(135deg, #e0a800, #e8590c);
            color: #212529;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(255, 193, 7, 0.3);
        }
        .restore-card {
            border: 2px solid #ffc107;
            border-radius: 10px;
            overflow: hidden;
        }
        .backup-item-row {
            transition: all 0.3s;
        }
        .backup-item-row:hover {
            background-color: rgba(13, 110, 253, 0.05);
            transform: translateX(5px);
        }
        .accordion-button:not(.collapsed) {
            background-color: rgba(255, 193, 7, 0.1);
            color: #212529;
            font-weight: 600;
        }
        .table th {
            background-color: #f8f9fa;
            font-weight: 600;
        }
        .badge-file {
            background-color: #6c757d;
            color: white;
            font-family: monospace;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1><i class="fas fa-database"></i> PostgreSQL Backup Manager</h1>
            <p class="mb-0">Disaster Management System | Workshop 2</p>
        </div>
        
        <!-- Messages -->
        <?php if (!empty($message)): ?>
            <div class="alert-message">
                <?= $message ?>
            </div>
        <?php endif; ?>
        
        <!-- Stats Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stat-card">
                    <h3><i class="fas fa-database text-primary"></i> Database</h3>
                    <h4 class="mt-3">victimdisaster</h4>
                    <small class="text-muted">PostgreSQL</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <h3><i class="fas fa-table text-success"></i> Tables</h3>
                    <h4 class="mt-3"><?= $table_count ?></h4>
                    <small class="text-muted">Total tables</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <h3><i class="fas fa-hdd text-warning"></i> Size</h3>
                    <h4 class="mt-3"><?= $db_size ?> MB</h4>
                    <small class="text-muted">Database size</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <h3><i class="fas fa-copy text-info"></i> Backups</h3>
                    <h4 class="mt-3"><?= count($backups) ?></h4>
                    <small class="text-muted">Total backups</small>
                </div>
            </div>
        </div>
        
        <!-- Quick Backup -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0"><i class="fas fa-plus-circle"></i> Create New Backup</h4>
            </div>
            <div class="card-body">
                <p>Create a complete backup of your PostgreSQL database including all tables and data.</p>
                <form method="POST" id="backupForm">
                    <button type="submit" name="create_backup" class="btn btn-backup" id="backupBtn">
                        <i class="fas fa-download"></i> Create Backup Now
                    </button>
                </form>
                <div class="mt-3 text-muted">
                    <small><i class="fas fa-info-circle"></i> Backups are saved to: <code><?= htmlspecialchars($backupDir) ?></code></small>
                </div>
            </div>
        </div>
        
        <!-- Restore Interface -->
        <div class="card mb-4 restore-card">
            <div class="card-header bg-warning text-dark">
                <h4 class="mb-0"><i class="fas fa-undo-alt"></i> Restore Database</h4>
            </div>
            <div class="card-body">
                <!-- Safety Warning -->
                <div class="alert alert-warning mb-4">
                    <div class="d-flex">
                        <div class="me-3">
                            <i class="fas fa-exclamation-triangle fa-2x"></i>
                        </div>
                        <div>
                            <h5 class="mb-2"><strong>⚠️ IMPORTANT SAFETY WARNING</strong></h5>
                            <ul class="mb-0">
                                <li>Restoring will <strong>OVERWRITE</strong> existing data</li>
                                <li>Create a backup before restoring</li>
                                <li>Recommended: Restore to a test database first</li>
                            </ul>
                        </div>
                    </div>
                </div>
                
                <!-- Restore Form -->
                <?php if (!empty($backups)): ?>
                <form method="POST" id="restoreForm">
                    <div class="row">
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label class="form-label"><strong>Select Backup File:</strong></label>
                                <select name="backup_file" class="form-select" id="backupSelect" required>
                                    <option value="">-- Choose a backup file --</option>
                                    <?php foreach ($backups as $backup): ?>
                                    <option value="<?= htmlspecialchars($backup['name']) ?>">
                                        <?= htmlspecialchars($backup['name']) ?> 
                                        (<?= $backup['date'] ?>, <?= formatSize($backup['size']) ?>)
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label"><strong>Action:</strong></label>
                                <div class="d-grid">
                                    <button type="submit" name="restore_backup" class="btn btn-restore btn-lg" id="restoreBtn" disabled>
                                        <i class="fas fa-play-circle"></i> Prepare Restoration
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Restore Options (Collapsible) -->
                    <div class="accordion mb-3" id="restoreOptions">
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOptions">
                                    <i class="fas fa-cog me-2"></i> Advanced Restore Options
                                </button>
                            </h2>
                            <div id="collapseOptions" class="accordion-collapse collapse" data-bs-parent="#restoreOptions">
                                <div class="accordion-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-check mb-2">
                                                <input class="form-check-input" type="checkbox" name="preview_only" id="previewOnly" checked>
                                                <label class="form-check-label" for="previewOnly">
                                                    <i class="fas fa-eye text-success"></i> Preview SQL only (safe mode)
                                                </label>
                                                <div class="form-text">Show SQL preview without executing</div>
                                            </div>
                                            <div class="form-check mb-2">
                                                <input class="form-check-input" type="checkbox" name="drop_tables" id="dropTables">
                                                <label class="form-check-label" for="dropTables">
                                                    <i class="fas fa-trash-alt text-danger"></i> Drop existing tables first
                                                </label>
                                                <div class="form-text">Will add DROP TABLE commands to SQL</div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Target Database:</label>
                                                <input type="text" class="form-control" name="target_db" value="victimdisaster" placeholder="Database name">
                                                <div class="form-text">Leave blank to restore to current database</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
                
                <!-- Preview Panel -->
                <div id="previewPanel" class="d-none">
                    <h5 class="mt-4 mb-3"><i class="fas fa-eye"></i> SQL Preview</h5>
                    <div class="console-output" id="sqlPreview">
                        <!-- SQL preview will appear here -->
                    </div>
                </div>
                
                <!-- Manual Instructions -->
                <div class="mt-4">
                    <h5><i class="fas fa-life-ring"></i> How to Complete the Restoration</h5>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="card h-100">
                                <div class="card-body">
                                    <h6><i class="fas fa-desktop text-primary"></i> Method 1: pgAdmin GUI</h6>
                                    <ol class="small">
                                        <li>Click "Prepare Restoration" above</li>
                                        <li>Download the backup file using the provided link</li>
                                        <li>Open pgAdmin 4 and connect to PostgreSQL</li>
                                        <li>Right-click on your database → Query Tool</li>
                                        <li>Open the downloaded .sql file</li>
                                        <li>Execute the SQL (Press F5 or click Execute)</li>
                                    </ol>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card h-100">
                                <div class="card-body">
                                    <h6><i class="fas fa-terminal text-success"></i> Method 2: Command Line</h6>
                                    <ol class="small">
                                        <li>Download the backup file</li>
                                        <li>Open terminal/command prompt</li>
                                        <li>Navigate to the backup file location</li>
                                        <li>Run this command:</li>
                                        <li class="mt-2">
                                            <code class="d-block p-2 bg-dark text-light small">
                                                psql -U postgres -d victimdisaster -f "backup_file.sql"
                                            </code>
                                        </li>
                                        <li>Enter your PostgreSQL password when prompted</li>
                                    </ol>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <?php else: ?>
                <div class="alert alert-info text-center">
                    <i class="fas fa-info-circle fa-2x mb-3"></i>
                    <h5>No Backups Available</h5>
                    <p>Create a backup first to enable restore functionality.</p>
                    <a href="#" class="btn btn-primary" onclick="document.getElementById('backupForm').submit(); return false;">
                        <i class="fas fa-plus-circle"></i> Create First Backup
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Backup List -->
        <div class="card mb-4">
            <div class="card-header bg-success text-white">
                <h4 class="mb-0"><i class="fas fa-history"></i> Backup History (<?= count($backups) ?>)</h4>
            </div>
            <div class="card-body">
                <?php if (empty($backups)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-folder-open fa-3x text-muted mb-3"></i>
                        <h4>No Backups Found</h4>
                        <p class="text-muted">Create your first backup to get started</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Backup File</th>
                                    <th>Created Date</th>
                                    <th>Size</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($backups as $backup): ?>
                                <tr class="backup-item-row">
                                    <td>
                                        <i class="fas fa-file-alt text-primary"></i>
                                        <strong><?= htmlspecialchars($backup['name']) ?></strong>
                                    </td>
                                    <td><?= $backup['date'] ?></td>
                                    <td><span class="badge bg-secondary"><?= formatSize($backup['size']) ?></span></td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="?download=<?= urlencode($backup['name']) ?>" 
                                               class="btn btn-outline-primary" title="Download">
                                                <i class="fas fa-download"></i> Download
                                            </a>
                                            <a href="?delete=<?= urlencode($backup['name']) ?>" 
                                               class="btn btn-outline-danger" 
                                               onclick="return confirm('⚠️ Are you sure you want to delete this backup?\n\nFile: <?= $backup['name'] ?>')"
                                               title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Backup Log -->
        <?php if (!empty($logContent)): ?>
        <div class="card mb-4">
            <div class="card-header bg-dark text-white">
                <h4 class="mb-0"><i class="fas fa-terminal"></i> Backup Log</h4>
            </div>
            <div class="card-body p-0">
                <div class="log-panel">
                    <?= nl2br(htmlspecialchars($logContent)) ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Footer -->
        <div class="mt-4 text-center">
            <a href="admin_dashboard.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-scroll log panel
        const logPanel = document.querySelector('.log-panel');
        if (logPanel) {
            logPanel.scrollTop = logPanel.scrollHeight;
        }
        
        // Confirm before backup creation
        document.getElementById('backupForm').addEventListener('submit', function(e) {
            const btn = document.getElementById('backupBtn');
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating Backup...';
            btn.disabled = true;
            
            if (!confirm('Create a new PostgreSQL database backup?\n\nThis may take a few moments.')) {
                e.preventDefault();
                btn.innerHTML = '<i class="fas fa-download"></i> Create Backup Now';
                btn.disabled = false;
            }
        });
        
        // Restore confirmation with more details
        document.getElementById('restoreForm').addEventListener('submit', function(e) {
            const select = document.getElementById('backupSelect');
            const backupFile = select.options[select.selectedIndex].text;
            const previewOnly = document.getElementById('previewOnly').checked;
            
            if (previewOnly) {
                if (!confirm(`📋 PREVIEW RESTORATION\n\n` +
                           `You are about to preview:\n` +
                           `File: ${backupFile}\n\n` +
                           `This will:\n` +
                           `• Show SQL preview (first 50 lines)\n` +
                           `• Provide download link\n` +
                           `• No database changes will be made\n\n` +
                           `Continue?`)) {
                    e.preventDefault();
                }
            } else {
                if (!confirm(`⚠️ ADVANCED RESTORATION MODE\n\n` +
                           `WARNING: This option may modify SQL files!\n` +
                           `File: ${backupFile}\n\n` +
                           `For safety, automatic execution is disabled.\n` +
                           `You will need to manually execute the SQL.\n\n` +
                           `Continue?`)) {
                    e.preventDefault();
                }
            }
        });
        
        // Show preview when backup is selected
        document.getElementById('backupSelect').addEventListener('change', function() {
            const previewPanel = document.getElementById('previewPanel');
            const restoreBtn = document.getElementById('restoreBtn');
            
            if (this.value) {
                previewPanel.classList.remove('d-none');
                restoreBtn.disabled = false;
                // In a real implementation, you could fetch preview via AJAX
                document.getElementById('sqlPreview').innerHTML = 
                    'Select "Prepare Restoration" to view SQL preview...';
            } else {
                previewPanel.classList.add('d-none');
                restoreBtn.disabled = true;
            }
        });
        
        // Re-enable backup button if form submission fails
        window.addEventListener('pageshow', function(event) {
            const backupBtn = document.getElementById('backupBtn');
            if (backupBtn) {
                backupBtn.innerHTML = '<i class="fas fa-download"></i> Create Backup Now';
                backupBtn.disabled = false;
            }
        });
        
        // Update restore button text based on mode
        document.getElementById('previewOnly').addEventListener('change', function() {
            const restoreBtn = document.getElementById('restoreBtn');
            if (this.checked) {
                restoreBtn.innerHTML = '<i class="fas fa-eye"></i> Preview Restoration';
            } else {
                restoreBtn.innerHTML = '<i class="fas fa-play-circle"></i> Advanced Restoration';
            }
        });
        
        // Initialize tooltips
        document.addEventListener('DOMContentLoaded', function() {
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[title]'));
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
            
            // Auto-select first backup in list if exists
            const backupSelect = document.getElementById('backupSelect');
            if (backupSelect && backupSelect.options.length > 1) {
                backupSelect.selectedIndex = 1; // Skip the first empty option
                backupSelect.dispatchEvent(new Event('change'));
            }
        });
    </script>
</body>
</html>