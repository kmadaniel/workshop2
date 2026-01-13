<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

include_once 'report_config.php';

// --- CONFIGURATION ---
// Using the path you provided. 
// NOTE: Ensure the web server (www-data) has WRITE permissions to this folder!
$BACKUP_DIR = "/home/imran/Documents/Workshop-mere/workshop2/Backups";
$SCHEDULE_FILE = __DIR__ . '/backup_schedule_setting.txt';

// Instantiate DB connection to get credentials
$database = new Database();
$DB_HOST = "localhost";
$DB_NAME = "Reports"; 
$DB_USER = "postgres";
$DB_PASS = "123123"; 

$action = $_GET['action'] ?? '';

// Ensure backup directory exists
if (!is_dir($BACKUP_DIR)) {
    mkdir($BACKUP_DIR, 0777, true);
}

if ($action === 'list') {
    // List all .dump files
    $files = glob($BACKUP_DIR . "/*.dump");
    $backups = [];
    
    foreach ($files as $file) {
        $backups[] = [
            'name' => basename($file),
            'date' => date("Y-m-d H:i:s", filemtime($file)),
            'size' => round(filesize($file) / 1024 / 1024, 2) . ' MB',
            'path' => $file
        ];
    }
    
    // Sort by date new to old
    usort($backups, function($a, $b) {
        return strtotime($b['date']) - strtotime($a['date']);
    });
    
    echo json_encode($backups);
}
elseif ($action === 'backup') {
    $type = $_GET['type'] ?? 'Manual'; // Daily, Weekly, Monthly
    $date = date("Y-m-d_H-i-s");
    $filename = "{$type}_Backup_{$DB_NAME}_{$date}.dump";
    $filepath = "{$BACKUP_DIR}/{$filename}";
    
    // Command: PGPASSWORD=... pg_dump ...
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
    $data = json_decode(file_get_contents("php://input"));
    $filename = $data->filename ?? '';
    $filepath = "{$BACKUP_DIR}/{$filename}";
    
    if (!file_exists($filepath)) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'File not found']);
        exit;
    }
    
    // 1. Terminate connections
    $kill_cmd = "PGPASSWORD='{$DB_PASS}' psql -U {$DB_USER} -h {$DB_HOST} -d postgres -c \"SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname = '{$DB_NAME}' AND pid <> pg_backend_pid();\"";
    exec($kill_cmd);
    
    // 2. Drop DB
    $drop_cmd = "PGPASSWORD='{$DB_PASS}' dropdb -U {$DB_USER} -h {$DB_HOST} --if-exists {$DB_NAME}";
    exec($drop_cmd);
    
    // 3. Create DB
    $create_cmd = "PGPASSWORD='{$DB_PASS}' createdb -U {$DB_USER} -h {$DB_HOST} {$DB_NAME}";
    exec($create_cmd);
    
    // 4. Restore
    $restore_cmd = "PGPASSWORD='{$DB_PASS}' pg_restore -U {$DB_USER} -h {$DB_HOST} -d {$DB_NAME} \"{$filepath}\" 2>&1";
    exec($restore_cmd, $output, $return_var);
    
    if ($return_var === 0) {
        echo json_encode(['status' => 'success', 'message' => 'Database restored successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Restore failed', 'debug' => $output]);
    }
}
elseif ($action === 'delete') {
    $data = json_decode(file_get_contents("php://input"));
    $filename = $data->filename ?? '';
    $filename = basename($filename); 
    $filepath = "{$BACKUP_DIR}/{$filename}";
    
    if (file_exists($filepath) && is_file($filepath)) {
        if (unlink($filepath)) {
            echo json_encode(['status' => 'success', 'message' => 'File deleted successfully']);
        } else {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Failed to delete file']);
        }
    } else {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'File not found']);
    }
}
elseif ($action === 'get_schedule') {
    $schedule = file_exists($SCHEDULE_FILE) ? trim(file_get_contents($SCHEDULE_FILE)) : 'Daily';
    
    $files = glob($BACKUP_DIR . "/{$schedule}_Backup_*.dump");
    $latest_time = 0;
    foreach ($files as $f) {
        $t = filemtime($f);
        if ($t > $latest_time) $latest_time = $t;
    }
    
    $next_run = "Pending";
    if ($latest_time > 0) {
        $interval = 0;
        if ($schedule === 'Daily') $interval = 86400; 
        elseif ($schedule === 'Weekly') $interval = 604800; 
        elseif ($schedule === 'Monthly') $interval = 2592000; 
        
        $next_time = $latest_time + $interval;
        
        if ($next_time < time()) {
            $next_run = "Overdue (Should run soon)";
        } else {
            $next_run = date("F j, Y, g:i a", $next_time);
        }
    } else {
        $next_run = "Not yet run";
    }

    echo json_encode(['schedule' => $schedule, 'next_run' => $next_run]);
}
elseif ($action === 'set_schedule') {
    $data = json_decode(file_get_contents("php://input"));
    $schedule = $data->schedule ?? 'Daily';
    
    if (file_put_contents($SCHEDULE_FILE, $schedule) !== false) {
        echo json_encode(['status' => 'success', 'message' => "Schedule updated to {$schedule}"]);
    } else {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Failed to save schedule setting']);
    }
}
?>