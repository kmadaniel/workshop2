<?php
// get_auto_backups.php
session_start();
include "db.php";

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    die('Access denied');
}

$backupDir = __DIR__ . '/backups/';

// Get all auto backup files
$files = glob($backupDir . 'auto_backup_*.sql');
$autoBackups = array();

if (!empty($files)) {
    // Sort by modification time (newest first)
    usort($files, function($a, $b) {
        return filemtime($b) - filemtime($a);
    });
    
    // Get only the 10 most recent
    $files = array_slice($files, 0, 10);
    
    foreach ($files as $file) {
        $filename = basename($file);
        $fileSize = filesize($file);
        $modified = filemtime($file);
        $created = date('Y-m-d H:i:s', $modified);
        $age = time() - $modified;
        
        $autoBackups[] = array(
            'name' => $filename,
            'size' => $fileSize,
            'size_formatted' => formatBytes($fileSize),
            'created' => $created,
            'modified' => $modified,
            'age_days' => floor($age / (60 * 60 * 24)),
            'age_hours' => floor($age / (60 * 60))
        );
    }
}

function formatBytes($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}
?>

<?php if (empty($autoBackups)): ?>
    <div style="text-align: center; padding: 30px; color: #666;">
        <i class="fas fa-folder-open" style="font-size: 32px; margin-bottom: 10px; color: #ccc;"></i>
        <p>No auto backup files found yet.</p>
        <p><small>Run your first automated backup using the button above.</small></p>
    </div>
<?php else: ?>
    <div class="table-responsive" style="max-height: 300px; overflow-y: auto;">
        <table style="width: 100%; font-size: 13px;">
            <thead>
                <tr style="background: #f8f9fa;">
                    <th style="padding: 10px; text-align: left;">File Name</th>
                    <th style="padding: 10px; text-align: center;">Size</th>
                    <th style="padding: 10px; text-align: center;">Created</th>
                    <th style="padding: 10px; text-align: center;">Age</th>
                    <th style="padding: 10px; text-align: center;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($autoBackups as $backup): ?>
                <tr style="border-bottom: 1px solid #eee;">
                    <td style="padding: 10px;">
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <i class="fas fa-file-code" style="color: #007bff;"></i>
                            <div>
                                <div style="font-weight: 500;"><?= htmlspecialchars($backup['name']) ?></div>
                                <small style="color: #666; font-size: 11px;">
                                    <i class="fas fa-database"></i> Auto generated
                                </small>
                            </div>
                        </div>
                    </td>
                    <td style="padding: 10px; text-align: center;">
                        <span class="badge" style="background: #e3f2fd; color: #007bff;">
                            <?= $backup['size_formatted'] ?>
                        </span>
                    </td>
                    <td style="padding: 10px; text-align: center; color: #666;">
                        <?= date('d M Y', strtotime($backup['created'])) ?><br>
                        <small><?= date('H:i', strtotime($backup['created'])) ?></small>
                    </td>
                    <td style="padding: 10px; text-align: center;">
                        <?php if ($backup['age_days'] > 0): ?>
                            <span style="color: <?= $backup['age_days'] > 7 ? '#f44336' : '#4caf50' ?>;">
                                <?= $backup['age_days'] ?> day(s)
                            </span>
                        <?php else: ?>
                            <span style="color: #2e7d32;">
                                <?= $backup['age_hours'] ?> hour(s)
                            </span>
                        <?php endif; ?>
                    </td>
                    <td style="padding: 10px; text-align: center;">
                        <div style="display: flex; gap: 5px; justify-content: center;">
                            <a href="backups/<?= htmlspecialchars($backup['name']) ?>" 
                               class="btn btn-xs" 
                               style="background: #e3f2fd; color: #2196F3; padding: 4px 8px;"
                               download>
                                <i class="fas fa-download"></i>
                            </a>
                            <button onclick="restoreAutoBackup('<?= htmlspecialchars($backup['name']) ?>')" 
                                    class="btn btn-xs" 
                                    style="background: #fff3e0; color: #ff9800; padding: 4px 8px;"
                                    title="Restore this backup">
                                <i class="fas fa-history"></i>
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div style="margin-top: 10px; text-align: center; font-size: 12px; color: #666;">
        <i class="fas fa-info-circle"></i> Showing <?= count($autoBackups) ?> most recent auto backups
    </div>
<?php endif; ?>

<script>
function restoreAutoBackup(filename) {
    if (confirm(`⚠️ WARNING: This will OVERWRITE your current database with backup:\n\n${filename}\n\nAre you sure you want to restore?`)) {
        // Show loading
        const statusDiv = document.getElementById('autoBackupStatus');
        statusDiv.innerHTML = `
            <div style="background: #fff3e0; color: #e65100; padding: 10px; border-radius: 5px;">
                <i class="fas fa-spinner fa-spin"></i> Restoring backup: ${filename}...
            </div>
        `;
        
        // Submit restore form
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'admin_dashboard.php';
        form.style.display = 'none';
        
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'restore_file';
        input.value = filename;
        form.appendChild(input);
        
        const submitInput = document.createElement('input');
        submitInput.type = 'hidden';
        submitInput.name = 'restore_database';
        submitInput.value = '1';
        form.appendChild(submitInput);
        
        document.body.appendChild(form);
        form.submit();
    }
}
</script>