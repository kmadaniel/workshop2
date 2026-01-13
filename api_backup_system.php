<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

include_once 'report_config.php';

// --- CONFIGURATION ---
// Path to backup directory
$BACKUP_DIR = "/home/imran/Documents/Workshop-mere/workshop2/Backups";
$SCHEDULE_FILE = $BACKUP_DIR . "/schedule.json"; // File to store the schedule setting

// Database Credentials
// Ideally, these should come from report_config.php, but hardcoding for shell commands here based on your setup.
$DB_HOST = "localhost";
$DB_NAME = "Reports";
$DB_USER = "postgres";
$DB_PASS = "123123"; 

$action = $_GET['action'] ?? '';

// Ensure backup directory exists
if (!is_dir($BACKUP_DIR)) {
    if (!mkdir($BACKUP_DIR, 0777, true)) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Cannot create backup directory. Check permissions.']);
        exit;
    }
}

// --- HELPER FUNCTIONS ---

function getSchedule() {
    global $SCHEDULE_FILE;
    if (file_exists($SCHEDULE_FILE)) {
        $content = file_get_contents($SCHEDULE_FILE);
        $data = json_decode($content, true);
        return $data['schedule'] ?? 'Manual';
    }
    return 'Manual';
}

function setSchedule($type) {
    global $SCHEDULE_FILE;
    $data = [
        'schedule' => $type, 
        'last_updated' => date('Y-m-d H:i:s')
    ];
    file_put_contents($SCHEDULE_FILE, json_encode($data));
}

// --- API ROUTES ---

if ($action === 'list') {
    // List all .dump files
    $files = glob($BACKUP_DIR . "/*.dump");
    $backups = [];
    
    foreach ($files as $file) {
        $backups[] = [
            'name' => basename($file),
            'date' => date("Y-m-d H:i:s", filemtime($file)),
            'size' => round(filesize($file) / 1024 / 1024, 2) . ' MB'
        ];
    }
    
    // Sort by date (newest first)
    usort($backups, function($a, $b) {
        return strtotime($b['date']) - strtotime($a['date']);
    });
    
    echo json_encode($backups);
}
elseif ($action === 'get_schedule') {
    // Return the current schedule setting
    echo json_encode(['schedule' => getSchedule()]);
}
elseif ($action === 'set_schedule') {
    // Save the schedule setting
    $input = json_decode(file_get_contents("php://input"), true);
    $type = $input['schedule'] ?? 'Manual';
    
    setSchedule($type);
    
    echo json_encode(['status' => 'success', 'schedule' => $type]);
}
elseif ($action === 'backup') {
    $type = $_GET['type'] ?? 'Manual'; 
    $date = date("Y-m-d_H-i-s");
    $filename = "{$type}_Backup_{$DB_NAME}_{$date}.dump";
    $filepath = "{$BACKUP_DIR}/{$filename}";
    
    // Verify write permissions
    if (!is_writable($BACKUP_DIR)) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => "Directory not writable: $BACKUP_DIR"]);
        exit;
    }
    
    // Construct command
    $cmd = "PGPASSWORD='{$DB_PASS}' pg_dump -U {$DB_USER} -h {$DB_HOST} -Fc {$DB_NAME} > \"{$filepath}\" 2>&1";
    
    exec($cmd, $output, $return_var);
    
    if ($return_var === 0) {
        echo json_encode(['status' => 'success', 'message' => 'Backup created successfully', 'file' => $filename]);
    } else {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Backup failed', 'debug' => $output]);
    }
}
elseif ($action === 'restore') {
    $data = json_decode(file_get_contents("php://input"), true);
    // Security: Use basename to prevent directory traversal attacks (e.g. ../../)
    $filename = basename($data['filename'] ?? '');
    $filepath = "{$BACKUP_DIR}/{$filename}";
    
    if (!$filename || !file_exists($filepath)) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'File not found']);
        exit;
    }
    
    $env = "PGPASSWORD='{$DB_PASS}'";
    
    // 1. Terminate existing connections to allow drop
    $kill_cmd = "$env psql -U {$DB_USER} -h {$DB_HOST} -d postgres -c \"SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname = '{$DB_NAME}' AND pid <> pg_backend_pid();\"";
    exec($kill_cmd);
    
    // 2. Drop DB
    $drop_cmd = "$env dropdb -U {$DB_USER} -h {$DB_HOST} --if-exists {$DB_NAME}";
    exec($drop_cmd);
    
    // 3. Create DB
    $create_cmd = "$env createdb -U {$DB_USER} -h {$DB_HOST} {$DB_NAME}";
    exec($create_cmd);
    
    // 4. Restore
    $restore_cmd = "$env pg_restore -U {$DB_USER} -h {$DB_HOST} -d {$DB_NAME} \"{$filepath}\" 2>&1";
    exec($restore_cmd, $output, $return_var);
    
    if ($return_var === 0) {
        echo json_encode(['status' => 'success', 'message' => 'Database restored successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Restore failed', 'debug' => $output]);
    }
}
else {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
}
?>