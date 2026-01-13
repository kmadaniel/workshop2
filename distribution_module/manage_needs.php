<?php
// ========================================
// MANAGE NEEDS - APPROVAL SYSTEM WITH API INTEGRATION
// manage_needs.php - WITH "APPROVE ALL" BUTTONS
// ========================================

require_once 'config.php';

// Increase execution time for bulk operations
set_time_limit(300);

$database = new Database();
$db = $database->getConnection();

$error = '';
$success = '';
$disasters = [];
$victims = [];
$needs = [];
$statistics = [];

// API Configuration
$DISASTER_API_URL = 'http://10.147.17.116:8000/disaster.php';
$VICTIM_API_URL = 'http://10.147.17.116:8000/victim.php';
$NEEDS_API_URL = 'http://10.147.17.116:8000/needs.php';

// Pagination settings
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$per_page = 50;
$offset = ($page - 1) * $per_page;

// Create cache directory
if (!is_dir(__DIR__ . '/cache')) {
    mkdir(__DIR__ . '/cache', 0777, true);
}

/* ----------------------------------------
   OPTIMIZED API FETCH FUNCTIONS
---------------------------------------- */
function fetchDataFromAPI($url, $timeout = 10) {
    $cache_key = 'api_cache_' . md5($url);
    $cache_file = __DIR__ . '/cache/' . $cache_key . '.json';
    $cache_time = 300;
    
    if (file_exists($cache_file) && (time() - filemtime($cache_file)) < $cache_time) {
        $cached_data = file_get_contents($cache_file);
        $data = json_decode($cached_data, true);
        if ($data !== null) {
            return ['success' => true, 'data' => $data, 'cached' => true];
        }
    }
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($response === false || $http_code != 200) {
        if (file_exists($cache_file)) {
            $cached_data = file_get_contents($cache_file);
            $data = json_decode($cached_data, true);
            if ($data !== null) {
                return ['success' => true, 'data' => $data, 'cached' => true];
            }
        }
        return ['success' => false, 'error' => "HTTP $http_code"];
    }
    
    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return ['success' => false, 'error' => 'Invalid JSON'];
    }
    
    file_put_contents($cache_file, $response);
    return ['success' => true, 'data' => $data];
}

/* ----------------------------------------
   CREATE TABLES IF NOT EXISTS
---------------------------------------- */
$create_processed_disasters_table = "
    CREATE TABLE IF NOT EXISTS processed_disasters (
        processed_id INT PRIMARY KEY AUTO_INCREMENT,
        disaster_id INT NOT NULL,
        processed_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        processed_by VARCHAR(100),
        notes TEXT,
        UNIQUE KEY unique_disaster (disaster_id),
        INDEX idx_disaster (disaster_id)
    ) ENGINE=InnoDB;
";

if (!$db->query($create_processed_disasters_table)) {
    error_log("Failed to create processed_disasters table: " . $db->error);
}

/* ----------------------------------------
   HELPER FUNCTIONS
---------------------------------------- */
function isDisasterProcessed($db, $disaster_id) {
    try {
        $query = "SELECT processed_id FROM processed_disasters WHERE disaster_id = ?";
        $stmt = $db->prepare($query);
        $stmt->bind_param("i", $disaster_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $is_processed = $result->num_rows > 0;
        $stmt->close();
        return $is_processed;
    } catch (Exception $e) {
        return false;
    }
}

/* ----------------------------------------
   HANDLE "APPROVE ALL" / "REJECT ALL" / "PENDING ALL" ACTIONS
---------------------------------------- */
if (isset($_GET['action_all'])) {
    $action_all = $_GET['action_all'];
    $selected_disaster_id = intval($_GET['disaster_id'] ?? 0);
    $selected_status_filter = $_GET['status_filter'] ?? 'Pending';
    $page = intval($_GET['page'] ?? 1);
    
    if ($selected_disaster_id > 0 && in_array($action_all, ['approve_all', 'reject_all', 'pending_all'])) {
        $new_status = 'Pending';
        if ($action_all === 'approve_all') $new_status = 'Approved';
        if ($action_all === 'reject_all') $new_status = 'Rejected';
        
        // Get ALL victim IDs for this disaster from the API
        $victimApiResult = fetchDataFromAPI($VICTIM_API_URL);
        $allVictimIds = [];
        
        if ($victimApiResult['success'] && is_array($victimApiResult['data'])) {
            foreach ($victimApiResult['data'] as $victim) {
                $victimDisasterId = intval($victim['disaster_id'] ?? 0);
                $victim_id = intval($victim['victim_id'] ?? 0);
                
                if ($victimDisasterId == $selected_disaster_id && $victim_id > 0) {
                    $allVictimIds[] = $victim_id;
                }
            }
        }
        
        if (!empty($allVictimIds)) {
            $start_time = microtime(true);
            
            try {
                // Use batch insert/update for efficiency
                $db->begin_transaction();
                
                // Process in chunks of 100
                $chunks = array_chunk($allVictimIds, 100);
                $successCount = 0;
                
                foreach ($chunks as $chunk) {
                    $placeholders = [];
                    $values = [];
                    
                    foreach ($chunk as $victim_id) {
                        $victim_id = intval($victim_id);
                        $placeholders[] = '(?, ?, ?, NOW())';
                        $values[] = $victim_id;
                        $values[] = $selected_disaster_id;
                        $values[] = $new_status;
                    }
                    
                    $query = "INSERT INTO victim_approvals (victim_id, disaster_id, approval_status, approved_at) 
                              VALUES " . implode(', ', $placeholders) . "
                              ON DUPLICATE KEY UPDATE 
                              approval_status = VALUES(approval_status),
                              approved_at = VALUES(approved_at)";
                    
                    $stmt = $db->prepare($query);
                    $types = str_repeat('iis', count($chunk));
                    $stmt->bind_param($types, ...$values);
                    
                    if ($stmt->execute()) {
                        $successCount += count($chunk);
                    }
                    
                    $stmt->close();
                }
                
                $db->commit();
                
                $total_time = round(microtime(true) - $start_time, 2);
                
                $action_text = ucfirst(str_replace('_all', '', $action_all));
                $success = "✅ Successfully set ALL $successCount victims to $new_status in {$total_time}s";
                
                header("Location: ?disaster_id=$selected_disaster_id&status_filter=$selected_status_filter&page=$page&success=" . urlencode($success));
                exit();
                
            } catch (Exception $e) {
                $db->rollback();
                $error = "Error updating victims: " . $e->getMessage();
            }
        }
    }
}

/* ----------------------------------------
   HANDLE BULK ACTIONS (selected victims)
---------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action'])) {
    $action = $_POST['bulk_action'];
    $selected_disaster_id = intval($_POST['disaster_id'] ?? 0);
    $selected_status_filter = $_POST['status_filter'] ?? 'Pending';
    $page = intval($_POST['page'] ?? 1);
    
    if ($selected_disaster_id > 0) {
        $new_status = ($action === 'approve') ? 'Approved' : 'Rejected';
        $selected_victims = $_POST['selected_victims'] ?? [];
        
        if (!empty($selected_victims)) {
            $start_time = microtime(true);
            
            try {
                $db->begin_transaction();
                
                $chunks = array_chunk($selected_victims, 100);
                $successCount = 0;
                
                foreach ($chunks as $chunk) {
                    $placeholders = [];
                    $values = [];
                    
                    foreach ($chunk as $victim_id) {
                        $victim_id = intval($victim_id);
                        $placeholders[] = '(?, ?, ?, NOW())';
                        $values[] = $victim_id;
                        $values[] = $selected_disaster_id;
                        $values[] = $new_status;
                    }
                    
                    $query = "INSERT INTO victim_approvals (victim_id, disaster_id, approval_status, approved_at) 
                              VALUES " . implode(', ', $placeholders) . "
                              ON DUPLICATE KEY UPDATE 
                              approval_status = VALUES(approval_status),
                              approved_at = VALUES(approved_at)";
                    
                    $stmt = $db->prepare($query);
                    $types = str_repeat('iis', count($chunk));
                    $stmt->bind_param($types, ...$values);
                    
                    if ($stmt->execute()) {
                        $successCount += count($chunk);
                    }
                    
                    $stmt->close();
                }
                
                $db->commit();
                
                $total_time = round(microtime(true) - $start_time, 2);
                $success = "✅ Successfully updated $successCount victim(s) to $new_status in {$total_time}s";
                
                header("Location: ?disaster_id=$selected_disaster_id&status_filter=$selected_status_filter&page=$page&success=" . urlencode($success));
                exit();
                
            } catch (Exception $e) {
                $db->rollback();
                $error = "Error updating victims: " . $e->getMessage();
            }
        }
    }
}

/* ----------------------------------------
   FETCH DATA FROM APIS
---------------------------------------- */
if (isset($_GET['clear_cache'])) {
    $files = glob(__DIR__ . '/cache/*.json');
    foreach ($files as $file) {
        if (is_file($file)) {
            unlink($file);
        }
    }
    $success = "Cache cleared successfully";
}

$disasterApiResult = fetchDataFromAPI($DISASTER_API_URL);
$victimApiResult = fetchDataFromAPI($VICTIM_API_URL);
$needsApiResult = fetchDataFromAPI($NEEDS_API_URL);

// Initialize arrays
$allApiVictims = [];
$allApiNeeds = [];
$allApiDisasters = [];
$victimNeedsMap = [];

// Process data
if ($disasterApiResult['success'] && is_array($disasterApiResult['data'])) {
    $allApiDisasters = $disasterApiResult['data'];
}

if ($victimApiResult['success'] && is_array($victimApiResult['data'])) {
    $allApiVictims = $victimApiResult['data'];
}

if ($needsApiResult['success']) {
    if (isset($needsApiResult['data']['data']) && is_array($needsApiResult['data']['data'])) {
        $allApiNeeds = $needsApiResult['data']['data'];
    } elseif (is_array($needsApiResult['data'])) {
        $allApiNeeds = $needsApiResult['data'];
    }
    
    foreach ($allApiNeeds as $need) {
        $victimId = intval($need['victim_id'] ?? 0);
        if ($victimId > 0) {
            if (!isset($victimNeedsMap[$victimId])) {
                $victimNeedsMap[$victimId] = [];
            }
            
            $victimNeedsMap[$victimId][] = [
                'resource_name' => $need['temp_resource_name'] ?? 'Unknown Resource',
                'quantity_needed' => $need['quantity_needed'] ?? '0',
                'priority' => $need['priority'] ?? 'Medium'
            ];
        }
    }
}

/* ----------------------------------------
   PROCESS DISASTERS
---------------------------------------- */
$selected_disaster_id = intval($_GET['disaster_id'] ?? 0);
$selected_status_filter = $_GET['status_filter'] ?? 'Pending';

if (isset($_GET['success'])) {
    $success = urldecode($_GET['success']);
}

foreach ($allApiDisasters as $apiDisaster) {
    $disaster_id = intval($apiDisaster['disaster_id'] ?? 0);
    
    if ($disaster_id > 0) {
        $is_processed = isDisasterProcessed($db, $disaster_id);
        
        // Count victims for this disaster
        $victimCount = 0;
        foreach ($allApiVictims as $victim) {
            $victimDisasterId = intval($victim['disaster_id'] ?? 0);
            if ($victimDisasterId == $disaster_id) {
                $victimCount++;
            }
        }
        
        // Get approval statistics
        $pending = 0;
        $approved = 0;
        $rejected = 0;
        
        $query = "SELECT approval_status, COUNT(*) as count 
                  FROM victim_approvals 
                  WHERE disaster_id = ? 
                  GROUP BY approval_status";
        $stmt = $db->prepare($query);
        $stmt->bind_param("i", $disaster_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            if ($row['approval_status'] === 'Pending') {
                $pending = $row['count'];
            } elseif ($row['approval_status'] === 'Approved') {
                $approved = $row['count'];
            } elseif ($row['approval_status'] === 'Rejected') {
                $rejected = $row['count'];
            }
        }
        $stmt->close();
        
        $disasters[] = [
            'disaster_id' => $disaster_id,
            'Disaster_Name' => $apiDisaster['disaster_name'] ?? 'Unknown',
            'Location' => $apiDisaster['district'] ?? 'Unknown',
            'Disaster_Type' => $apiDisaster['severity'] ?? 'Unknown',
            'api_victim_count' => $victimCount,
            'pending' => $pending,
            'approved' => $approved,
            'rejected' => $rejected,
            'is_processed' => $is_processed
        ];
    }
}

/* ----------------------------------------
   LOAD VICTIMS WITH PAGINATION
---------------------------------------- */
if ($selected_disaster_id > 0) {
    $disasterVictims = [];
    
    // Find victims for this disaster
    foreach ($allApiVictims as $victim) {
        $victimDisasterId = intval($victim['disaster_id'] ?? 0);
        
        if ($victimDisasterId == $selected_disaster_id) {
            $victim_id = intval($victim['victim_id'] ?? 0);
            
            if ($victim_id > 0) {
                $victim['needs'] = $victimNeedsMap[$victim_id] ?? [];
                $victim['victim_id'] = $victim_id;
                
                // Calculate priority
                $highPriorityCount = 0;
                foreach ($victim['needs'] as $need) {
                    if ($need['priority'] === 'High') {
                        $highPriorityCount++;
                    }
                }
                
                $victim['priority'] = $highPriorityCount > 0 ? 'High' : 'Medium';
                $victim['needs_count'] = count($victim['needs']);
                
                $disasterVictims[] = $victim;
            }
        }
    }
    
    // Get total count
    $total_victims = count($disasterVictims);
    $total_pages = ceil($total_victims / $per_page);
    
    // Apply status filter
    if ($selected_status_filter !== 'all') {
        $filteredVictims = [];
        foreach ($disasterVictims as $victim) {
            $victim_id = $victim['victim_id'] ?? 0;
            
            $query = "SELECT approval_status FROM victim_approvals WHERE victim_id = ? AND disaster_id = ?";
            $stmt = $db->prepare($query);
            $stmt->bind_param("ii", $victim_id, $selected_disaster_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $approval_status = $row['approval_status'];
            } else {
                $approval_status = 'Pending';
            }
            $stmt->close();
            
            $victim['approval_status'] = $approval_status;
            
            if ($approval_status === $selected_status_filter) {
                $filteredVictims[] = $victim;
            }
        }
        
        $disasterVictims = $filteredVictims;
        $total_filtered_victims = count($disasterVictims);
        $total_pages = ceil($total_filtered_victims / $per_page);
    }
    
    // Apply pagination
    $paginatedVictims = array_slice($disasterVictims, $offset, $per_page);
    
    // Get approval statuses
    foreach ($paginatedVictims as &$victim) {
        $victim_id = $victim['victim_id'] ?? 0;
        
        if ($victim_id > 0) {
            $query = "SELECT approval_status FROM victim_approvals WHERE victim_id = ? AND disaster_id = ?";
            $stmt = $db->prepare($query);
            $stmt->bind_param("ii", $victim_id, $selected_disaster_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $victim['approval_status'] = $row['approval_status'];
            } else {
                $victim['approval_status'] = 'Pending';
            }
            $stmt->close();
        }
    }
    
    $needs = $paginatedVictims;
    
    // Get statistics
    $pending = 0;
    $approved = 0;
    $rejected = 0;
    
    $query = "SELECT approval_status, COUNT(*) as count 
              FROM victim_approvals 
              WHERE disaster_id = ? 
              GROUP BY approval_status";
    $stmt = $db->prepare($query);
    $stmt->bind_param("i", $selected_disaster_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        if ($row['approval_status'] === 'Pending') {
            $pending = $row['count'];
        } elseif ($row['approval_status'] === 'Approved') {
            $approved = $row['count'];
        } elseif ($row['approval_status'] === 'Rejected') {
            $rejected = $row['count'];
        }
    }
    $stmt->close();
    
    $statistics = [
        'total' => $total_victims,
        'pending' => $pending,
        'approved' => $approved,
        'rejected' => $rejected,
        'total_pages' => $total_pages,
        'current_page' => $page,
        'per_page' => $per_page
    ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Victims - Disaster Relief Distribution System</title>
    <link rel="stylesheet" href="../css/needs.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .performance-warning {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        #selection-counter {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #3498db;
            color: white;
            padding: 10px 15px;
            border-radius: 20px;
            font-weight: bold;
            z-index: 1000;
            box-shadow: 0 4px 10px rgba(0,0,0,0.2);
            display: none;
        }
        
        .large-selection-warning {
            background: #ffeaa7;
            border: 2px solid #f39c12;
            border-radius: 8px;
            padding: 15px;
            margin: 10px 0;
            text-align: center;
            font-weight: bold;
            display: none;
        }
        
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255,255,255,0.95);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            display: none;
            flex-direction: column;
        }
        
        .progress-bar {
            width: 300px;
            height: 20px;
            background: #eee;
            border-radius: 10px;
            margin-top: 20px;
            overflow: hidden;
        }
        
        .progress-fill {
            height: 100%;
            background: #3498db;
            width: 0%;
            transition: width 0.3s ease;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            margin: 20px 0;
            gap: 10px;
        }
        
        .page-info {
            margin: 0 15px;
            font-weight: 600;
        }
        
        /* NEW: Bulk Action Buttons Container */
        .bulk-all-actions {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin: 15px 0;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            border: 1px solid #dee2e6;
        }
        
        .bulk-all-btn {
            padding: 10px 20px;
            border-radius: 6px;
            border: none;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }
        
        .bulk-all-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .bulk-approve-all {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
        }
        
        .bulk-reject-all {
            background: linear-gradient(135deg, #dc3545, #fd7e14);
            color: white;
        }
        
        .bulk-pending-all {
            background: linear-gradient(135deg, #6c757d, #adb5bd);
            color: white;
        }
    </style>
</head>
<body>
    <!-- Selection Counter -->
    <div id="selection-counter">
        <i class="fas fa-check-square"></i> <span id="selected-count">0</span> selected
    </div>
    
    <!-- Large Selection Warning -->
    <div id="large-selection-warning" class="large-selection-warning">
        <i class="fas fa-exclamation-triangle"></i>
        <span id="warning-message"></span>
    </div>

    <!-- Loading Overlay -->
    <div id="loadingOverlay" class="loading-overlay">
        <div class="loading-content" style="text-align: center;">
            <div class="loading-spinner" style="
                border: 5px solid #f3f3f3;
                border-top: 5px solid #3498db;
                border-radius: 50%;
                width: 50px;
                height: 50px;
                animation: spin 1s linear infinite;
                margin: 0 auto 20px;
            "></div>
            <h3 id="loadingTitle">Processing...</h3>
            <p id="loadingMessage"></p>
            <div class="progress-bar">
                <div class="progress-fill" id="progressFill"></div>
            </div>
            <p><small id="loadingETA"></small></p>
        </div>
    </div>

    <div class="container">
        <!-- Performance Warning -->
        <div class="performance-warning">
            <i class="fas fa-exclamation-triangle"></i>
            <div>
                <strong>Bulk Action Notice:</strong> Use the buttons below to approve/reject all victims at once.
                <a href="?clear_cache=1&disaster_id=<?php echo $selected_disaster_id; ?>" style="margin-left: 10px; color: #3498db;">
                    <i class="fas fa-sync-alt"></i> Clear Cache
                </a>
            </div>
        </div>

        <!-- Page Header -->
        <div class="header">
            <div class="header-content">
                <h1><i class="fas fa-clipboard-check"></i> Manage Victim Requests</h1>
                <p>Approve/reject victim requests with needs assessment</p>
            </div>
            <div class="header-actions">
                <a href="distribution_main.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>
        </div>

        <!-- Success/Error Messages -->
        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <!-- Disaster Selection -->
        <div class="card-3d" style="margin-bottom: 25px;">
            <h2><i class="fas fa-exclamation-triangle"></i> Select Disaster</h2>
            
            <?php if (empty($disasters)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">📭</div>
                    <h3>No disasters found</h3>
                </div>
            <?php else: ?>
                <div class="table-container" style="max-height: 400px;">
                    <table class="needs-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Disaster Name</th>
                                <th>District</th>
                                <th>Severity</th>
                                <th>Victims</th>
                                <th>Pending</th>
                                <th>Approved</th>
                                <th>Rejected</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($disasters as $disaster): ?>
                            <tr>
                                <td><strong>#<?php echo $disaster['disaster_id']; ?></strong></td>
                                <td><?php echo htmlspecialchars($disaster['Disaster_Name']); ?></td>
                                <td><?php echo htmlspecialchars($disaster['Location']); ?></td>
                                <td>
                                    <span class="badge-<?php echo strtolower($disaster['Disaster_Type']); ?>">
                                        <?php echo htmlspecialchars($disaster['Disaster_Type']); ?>
                                    </span>
                                </td>
                                <td><strong><?php echo $disaster['api_victim_count']; ?></strong></td>
                                <td>
                                    <?php if ($disaster['pending'] > 0): ?>
                                        <span class="badge-pending"><?php echo $disaster['pending']; ?></span>
                                    <?php else: ?>
                                        <span style="color: #bdc3c7;">0</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($disaster['approved'] > 0): ?>
                                        <span class="badge-approved"><?php echo $disaster['approved']; ?></span>
                                    <?php else: ?>
                                        <span style="color: #bdc3c7;">0</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($disaster['rejected'] > 0): ?>
                                        <span class="badge-rejected"><?php echo $disaster['rejected']; ?></span>
                                    <?php else: ?>
                                        <span style="color: #bdc3c7;">0</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="?disaster_id=<?php echo $disaster['disaster_id']; ?>" 
                                       class="btn-sm btn-primary" style="text-decoration: none;">
                                        <i class="fas fa-edit"></i> Manage
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($selected_disaster_id && isset($statistics)): ?>
        <!-- Selected Disaster Info -->
        <?php $selected_disaster_info = null;
        foreach ($disasters as $d) {
            if ($d['disaster_id'] == $selected_disaster_id) {
                $selected_disaster_info = $d;
                break;
            }
        } ?>
        
        <?php if ($selected_disaster_info): ?>
        <div class="card-3d" style="margin-bottom: 20px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
            <h3 style="color: white; margin-bottom: 15px;">
                <i class="fas fa-info-circle"></i> Disaster #<?php echo $selected_disaster_id; ?> - <?php echo htmlspecialchars($selected_disaster_info['Disaster_Name']); ?>
            </h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                <div>
                    <div style="opacity: 0.9; font-size: 0.9em;">District</div>
                    <div style="font-weight: 600; font-size: 1.1em;"><?php echo htmlspecialchars($selected_disaster_info['Location']); ?></div>
                </div>
                <div>
                    <div style="opacity: 0.9; font-size: 0.9em;">Total Victims</div>
                    <div style="font-weight: 600; font-size: 1.1em;"><?php echo $statistics['total']; ?></div>
                </div>
                <div>
                    <div style="opacity: 0.9; font-size: 0.9em;">Page</div>
                    <div style="font-weight: 600; font-size: 1.1em;">
                        <?php echo $statistics['current_page']; ?> of <?php echo $statistics['total_pages']; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card total">
                <div class="stat-label">Total Victims</div>
                <div class="stat-value"><?php echo $statistics['total']; ?></div>
            </div>
            <div class="stat-card pending">
                <div class="stat-label"><i class="fas fa-clock"></i> Pending</div>
                <div class="stat-value"><?php echo $statistics['pending']; ?></div>
            </div>
            <div class="stat-card approved">
                <div class="stat-label"><i class="fas fa-check-circle"></i> Approved</div>
                <div class="stat-value"><?php echo $statistics['approved']; ?></div>
            </div>
            <div class="stat-card rejected">
                <div class="stat-label"><i class="fas fa-times-circle"></i> Rejected</div>
                <div class="stat-value"><?php echo $statistics['rejected']; ?></div>
            </div>
        </div>

        <!-- NEW: BULK ACTION BUTTONS FOR ALL VICTIMS -->
        <div class="bulk-all-actions">
            <button type="button" class="bulk-all-btn bulk-approve-all" 
                    onclick="approveAll()">
                <i class="fas fa-check-double"></i> Approve All <?php echo $statistics['total']; ?> Victims
            </button>
            
            <button type="button" class="bulk-all-btn bulk-reject-all" 
                    onclick="rejectAll()">
                <i class="fas fa-times-circle"></i> Reject All <?php echo $statistics['total']; ?> Victims
            </button>
            
            <button type="button" class="bulk-all-btn bulk-pending-all" 
                    onclick="pendingAll()">
                <i class="fas fa-clock"></i> Set All <?php echo $statistics['total']; ?> to Pending
            </button>
        </div>

        <!-- Filters -->
        <div class="filter-bar">
            <form method="GET" id="filter-form">
                <input type="hidden" name="disaster_id" value="<?php echo $selected_disaster_id; ?>">
                <input type="hidden" name="page" value="1">
                
                <div class="filter-grid">
                    <div class="form-group">
                        <label class="form-label">Disaster</label>
                        <select class="form-control" name="disaster_id" onchange="this.form.submit()">
                            <?php foreach ($disasters as $disaster): ?>
                            <option value="<?php echo $disaster['disaster_id']; ?>"
                                    <?php echo $selected_disaster_id == $disaster['disaster_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($disaster['Disaster_Name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Status Filter</label>
                        <select class="form-control" name="status_filter" onchange="this.form.submit()">
                            <option value="all" <?php echo $selected_status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                            <option value="Pending" <?php echo $selected_status_filter === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="Approved" <?php echo $selected_status_filter === 'Approved' ? 'selected' : ''; ?>>Approved</option>
                            <option value="Rejected" <?php echo $selected_status_filter === 'Rejected' ? 'selected' : ''; ?>>Rejected</option>
                        </select>
                    </div>
                </div>
            </form>
        </div>

        <!-- Bulk Actions Form (for selected victims on current page) -->
        <form method="POST" id="bulk-action-form" onsubmit="return handleBulkSubmit(this)">
            <input type="hidden" name="disaster_id" value="<?php echo $selected_disaster_id; ?>">
            <input type="hidden" name="status_filter" value="<?php echo $selected_status_filter; ?>">
            <input type="hidden" name="page" value="<?php echo $page; ?>">
            
            <div class="bulk-actions-bar" id="bulk-actions-bar">
                <div>
                    <strong><i class="fas fa-check-square"></i> 
                    <span id="selected-count-text">0</span> victim(s) selected on this page</strong>
                </div>
                <div style="display: flex; gap: 10px;">
                    <button type="submit" name="bulk_action" value="approve" 
                            class="btn btn-success"
                            id="bulk-approve-btn">
                        <i class="fas fa-check"></i> Approve Selected
                    </button>
                    <button type="submit" name="bulk_action" value="reject" 
                            class="btn btn-danger"
                            id="bulk-reject-btn">
                        <i class="fas fa-times"></i> Reject Selected
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="clearSelection()">
                        <i class="fas fa-times"></i> Clear Selection
                    </button>
                </div>
            </div>

            <!-- Victims Table -->
            <div class="card-3d">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h2 style="margin: 0;">
                        <i class="fas fa-users"></i> Victim Requests (Page <?php echo $statistics['current_page']; ?> of <?php echo $statistics['total_pages']; ?>)
                        <span style="font-size: 0.7em; color: #7f8c8d;">
                            (<?php echo count($needs); ?> victims on this page)
                        </span>
                    </h2>
                    <?php if (count($needs) > 0): ?>
                    <div>
                        <label style="display: flex; align-items: center; font-weight: 600; gap: 10px;">
                            <input type="checkbox" id="select-all" style="transform: scale(1.2);">
                            Select All on This Page (<?php echo count($needs); ?> victims)
                        </label>
                    </div>
                    <?php endif; ?>
                </div>
                
                <?php if (empty($needs)): ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">📭</div>
                        <h3>No victims found</h3>
                        <p>
                            <?php if ($selected_status_filter !== 'all'): ?>
                                No <?php echo strtolower($selected_status_filter); ?> victims on this page.
                            <?php endif; ?>
                        </p>
                    </div>
                <?php else: ?>
                    <div class="table-container">
                        <table class="needs-table">
                            <thead>
                                <tr>
                                    <th style="width: 50px;">
                                        <input type="checkbox" id="select-all-header">
                                    </th>
                                    <th>ID</th>
                                    <th>Victim Info</th>
                                    <th>Contact</th>
                                    <th>Location</th>
                                    <th>Needs</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($needs as $victim): ?>
                                <?php 
                                $victim_id = intval($victim['victim_id'] ?? 0);
                                $full_name = $victim['full_name'] ?? 'Unknown';
                                $ic_number = $victim['ic_number'] ?? 'N/A';
                                $email = $victim['email'] ?? 'N/A';
                                $phone = $victim['phone'] ?? '';
                                $address = $victim['address'] ?? 'Unknown';
                                $district = $victim['district'] ?? 'N/A';
                                $family_members = intval($victim['family_members'] ?? 1);
                                $approval_status = $victim['approval_status'] ?? 'Pending';
                                $needs_list = $victim['needs'] ?? [];
                                $priority = $victim['priority'] ?? 'Medium';
                                $needs_count = $victim['needs_count'] ?? 0;
                                ?>
                                <tr>
                                    <td>
                                        <input type="checkbox" name="selected_victims[]" 
                                               value="<?php echo $victim_id; ?>"
                                               class="need-checkbox">
                                    </td>
                                    <td><strong>#<?php echo $victim_id; ?></strong></td>
                                    <td>
                                        <div style="font-weight: 600;"><?php echo htmlspecialchars($full_name); ?></div>
                                        <div style="font-size: 0.85em; color: #7f8c8d;">
                                            <i class="fas fa-id-card"></i> <?php echo htmlspecialchars($ic_number); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div style="font-size: 0.9em;">
                                            <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($email); ?>
                                        </div>
                                        <?php if (!empty($phone)): ?>
                                        <div style="font-size: 0.9em; margin-top: 3px;">
                                            <i class="fas fa-phone"></i> <?php echo htmlspecialchars($phone); ?>
                                        </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div style="font-weight: 500;"><?php echo htmlspecialchars($address); ?></div>
                                        <div style="font-size: 0.85em; color: #7f8c8d;">
                                            <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($district); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="needs-summary">
                                            <span class="badge-<?php echo strtolower($priority); ?>">
                                                <?php echo $priority; ?> Priority
                                            </span>
                                            <?php if ($needs_count > 0): ?>
                                                <span class="needs-count-badge">
                                                    <?php echo $needs_count; ?> needs
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge-<?php echo strtolower($approval_status); ?>">
                                            <?php echo $approval_status; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <?php if ($approval_status !== 'Approved'): ?>
                                                <button type="button" class="btn-sm btn-approve" 
                                                        onclick="updateStatus(<?php echo $victim_id; ?>, 'Approved')"
                                                        title="Approve">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                            <?php endif; ?>
                                            
                                            <?php if ($approval_status !== 'Rejected'): ?>
                                                <button type="button" class="btn-sm btn-reject" 
                                                        onclick="updateStatus(<?php echo $victim_id; ?>, 'Rejected')"
                                                        title="Reject">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            <?php endif; ?>
                                            
                                            <?php if ($approval_status !== 'Pending'): ?>
                                                <button type="button" class="btn-sm btn-pending" 
                                                        onclick="updateStatus(<?php echo $victim_id; ?>, 'Pending')"
                                                        title="Set to Pending">
                                                    <i class="fas fa-clock"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
                
                <!-- Pagination -->
                <?php if ($statistics['total_pages'] > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?disaster_id=<?php echo $selected_disaster_id; ?>&status_filter=<?php echo $selected_status_filter; ?>&page=1" 
                           class="btn btn-sm btn-secondary">
                            <i class="fas fa-angle-double-left"></i> First
                        </a>
                        <a href="?disaster_id=<?php echo $selected_disaster_id; ?>&status_filter=<?php echo $selected_status_filter; ?>&page=<?php echo $page - 1; ?>" 
                           class="btn btn-sm btn-secondary">
                            <i class="fas fa-angle-left"></i> Previous
                        </a>
                    <?php endif; ?>
                    
                    <span class="page-info">
                        Page <?php echo $page; ?> of <?php echo $statistics['total_pages']; ?>
                    </span>
                    
                    <?php if ($page < $statistics['total_pages']): ?>
                        <a href="?disaster_id=<?php echo $selected_disaster_id; ?>&status_filter=<?php echo $selected_status_filter; ?>&page=<?php echo $page + 1; ?>" 
                           class="btn btn-sm btn-secondary">
                            Next <i class="fas fa-angle-right"></i>
                        </a>
                        <a href="?disaster_id=<?php echo $selected_disaster_id; ?>&status_filter=<?php echo $selected_status_filter; ?>&page=<?php echo $statistics['total_pages']; ?>" 
                           class="btn btn-sm btn-secondary">
                            Last <i class="fas fa-angle-double-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </form>
        <?php endif; ?>
    </div>

    <script>
        // Update selection counters
        function updateSelectionCounters() {
            const checkboxes = document.querySelectorAll('.need-checkbox:checked');
            const selectedCount = checkboxes.length;
            
            document.getElementById('selected-count').textContent = selectedCount;
            document.getElementById('selected-count-text').textContent = selectedCount;
            
            const counter = document.getElementById('selection-counter');
            if (selectedCount > 0) {
                counter.style.display = 'block';
            } else {
                counter.style.display = 'none';
            }
            
            // Show warning for large selections
            const warning = document.getElementById('large-selection-warning');
            if (selectedCount > 20) {
                warning.style.display = 'block';
                document.getElementById('warning-message').textContent = 
                    `You have selected ${selectedCount} victims. This may take time to process.`;
            } else {
                warning.style.display = 'none';
            }
        }
        
        // Select all checkboxes on current page
        document.getElementById('select-all')?.addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.need-checkbox');
            const isChecked = this.checked;
            
            checkboxes.forEach(checkbox => {
                checkbox.checked = isChecked;
            });
            
            const headerCheckbox = document.getElementById('select-all-header');
            if (headerCheckbox) headerCheckbox.checked = isChecked;
            
            updateSelectionCounters();
        });
        
        document.getElementById('select-all-header')?.addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.need-checkbox');
            const isChecked = this.checked;
            
            checkboxes.forEach(checkbox => {
                checkbox.checked = isChecked;
            });
            
            const mainCheckbox = document.getElementById('select-all');
            if (mainCheckbox) mainCheckbox.checked = isChecked;
            
            updateSelectionCounters();
        });
        
        // Update when individual checkboxes change
        document.querySelectorAll('.need-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', updateSelectionCounters);
        });
        
        function clearSelection() {
            document.querySelectorAll('.need-checkbox').forEach(checkbox => {
                checkbox.checked = false;
            });
            const selectAll = document.getElementById('select-all');
            const selectAllHeader = document.getElementById('select-all-header');
            if (selectAll) selectAll.checked = false;
            if (selectAllHeader) selectAllHeader.checked = false;
            updateSelectionCounters();
        }
        
        function updateStatus(victimId, newStatus) {
            if (confirm(`Change victim #${victimId} status to '${newStatus}'?`)) {
                const url = new URL(window.location.href);
                url.searchParams.set('update_status', '1');
                url.searchParams.set('victim_id', victimId);
                url.searchParams.set('new_status', newStatus);
                url.searchParams.set('disaster_id', <?php echo $selected_disaster_id; ?>);
                url.searchParams.set('status_filter', '<?php echo $selected_status_filter; ?>');
                url.searchParams.set('page', <?php echo $page; ?>);
                window.location.href = url.toString();
            }
        }
        
        // NEW: Functions to handle "Approve All", "Reject All", "Pending All"
        function approveAll() {
            if (confirm(`Approve ALL <?php echo $statistics['total']; ?> victims?\nThis may take a few seconds.`)) {
                showLoadingOverlay(`Approving <?php echo $statistics['total']; ?> victims...`, <?php echo $statistics['total']; ?>);
                setTimeout(() => {
                    window.location.href = `?disaster_id=<?php echo $selected_disaster_id; ?>&status_filter=<?php echo $selected_status_filter; ?>&page=<?php echo $page; ?>&action_all=approve_all`;
                }, 500);
            }
        }
        
        function rejectAll() {
            if (confirm(`Reject ALL <?php echo $statistics['total']; ?> victims?\nThis may take a few seconds.`)) {
                showLoadingOverlay(`Rejecting <?php echo $statistics['total']; ?> victims...`, <?php echo $statistics['total']; ?>);
                setTimeout(() => {
                    window.location.href = `?disaster_id=<?php echo $selected_disaster_id; ?>&status_filter=<?php echo $selected_status_filter; ?>&page=<?php echo $page; ?>&action_all=reject_all`;
                }, 500);
            }
        }
        
        function pendingAll() {
            if (confirm(`Set ALL <?php echo $statistics['total']; ?> victims to Pending?\nThis may take a few seconds.`)) {
                showLoadingOverlay(`Setting <?php echo $statistics['total']; ?> victims to Pending...`, <?php echo $statistics['total']; ?>);
                setTimeout(() => {
                    window.location.href = `?disaster_id=<?php echo $selected_disaster_id; ?>&status_filter=<?php echo $selected_status_filter; ?>&page=<?php echo $page; ?>&action_all=pending_all`;
                }, 500);
            }
        }
        
        // Handle bulk form submission for current page
        function handleBulkSubmit(form) {
            const checkboxes = document.querySelectorAll('.need-checkbox:checked');
            const selectedCount = checkboxes.length;
            
            if (selectedCount === 0) {
                alert('Please select at least one victim.');
                return false;
            }
            
            const action = form.querySelector('button[type="submit"]:focus')?.value || 'approve';
            const actionText = action === 'approve' ? 'Approve' : 'Reject';
            
            if (selectedCount > 20) {
                const confirmed = confirm(
                    `${actionText} ${selectedCount} selected victim(s)?\n` +
                    `This may take a few seconds to process.`
                );
                if (!confirmed) {
                    return false;
                }
                
                showLoadingOverlay(`${actionText}ing ${selectedCount} victims...`, selectedCount);
            } else {
                if (!confirm(`${actionText} ${selectedCount} selected victim(s)?`)) {
                    return false;
                }
            }
            
            return true;
        }
        
        function showLoadingOverlay(message, selectedCount) {
            const overlay = document.getElementById('loadingOverlay');
            const title = document.getElementById('loadingTitle');
            const msg = document.getElementById('loadingMessage');
            const eta = document.getElementById('loadingETA');
            
            title.textContent = 'Processing...';
            msg.textContent = message;
            eta.textContent = `Estimated time: ${Math.ceil(selectedCount * 0.05)} seconds`;
            
            overlay.style.display = 'flex';
            
            startProgressAnimation(selectedCount);
        }
        
        function startProgressAnimation(selectedCount) {
            const progressFill = document.getElementById('progressFill');
            let progress = 0;
            const estimatedSeconds = Math.max(2, Math.ceil(selectedCount * 0.05));
            const totalTime = estimatedSeconds * 1000;
            
            const interval = setInterval(() => {
                progress += 1;
                progressFill.style.width = progress + '%';
                
                if (progress >= 90) {
                    clearInterval(interval);
                }
            }, totalTime / 100);
        }
        
        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            updateSelectionCounters();
            
            // Auto-hide success messages
            setTimeout(() => {
                const successMsg = document.querySelector('.alert-success');
                if (successMsg) {
                    successMsg.style.opacity = '0';
                    successMsg.style.transition = 'opacity 0.5s ease';
                    setTimeout(() => {
                        if (successMsg.parentNode) {
                            successMsg.remove();
                        }
                    }, 500);
                }
            }, 5000);
        });
        
        // Add CSS animation for spinner
        const style = document.createElement('style');
        style.textContent = `
            @keyframes spin {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>