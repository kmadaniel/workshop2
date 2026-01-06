<?php
// ========================================
// UPDATE DISTRIBUTION STATUS - COMPLETE FIXED VERSION
// ========================================

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set a longer execution time for this script
set_time_limit(60); // 60 seconds

require_once 'config.php';

$database = new Database();
$db = $database->getConnection();

$distribution_id = $_GET['id'] ?? null;

if (!$distribution_id) {
    header("Location: distribution_main.php");
    exit;
}

// First, let's check if the distribution exists at all
$check_query = "SELECT * FROM distribution WHERE distribution_id = ?";
$check_stmt = $db->prepare($check_query);
$check_stmt->bind_param("i", $distribution_id);
$check_stmt->execute();
$check_result = $check_stmt->get_result();
$basic_distribution = $check_result->fetch_assoc();
$check_stmt->close();

if (!$basic_distribution) {
    echo "<div class='container'><div class='alert alert-danger'>Distribution #$distribution_id not found in database!</div></div>";
    exit;
}

// Get distribution details
$query = "SELECT * FROM distribution WHERE distribution_id = ?";

$stmt = $db->prepare($query);
$stmt->bind_param("i", $distribution_id);
$stmt->execute();
$result = $stmt->get_result();
$distribution = $result->fetch_assoc();
$stmt->close();

// ============ Get victims from distribution_items table ============
$victims_query = "
    SELECT 
        di.*
    FROM distribution_items di
    WHERE di.distribution_id = ?
    ORDER BY di.item_id
";

$victims_stmt = $db->prepare($victims_query);
$victims_stmt->bind_param("i", $distribution_id);
$victims_stmt->execute();
$victims_result = $victims_stmt->get_result();
$distribution_victims = $victims_result->fetch_all(MYSQLI_ASSOC);
$victims_stmt->close();

// ============ Get resource allocations from distribution_resources table ============
$resources_query = "
    SELECT 
        dr.*
    FROM distribution_resources dr
    WHERE dr.distribution_id = ?
    ORDER BY dr.allocation_id
";

$resources_stmt = $db->prepare($resources_query);
$resources_stmt->bind_param("i", $distribution_id);
$resources_stmt->execute();
$resources_result = $resources_stmt->get_result();
$distribution_resources = $resources_result->fetch_all(MYSQLI_ASSOC);
$resources_stmt->close();

// Calculate totals from ACTUAL distribution data
$total_families = count($distribution_victims);
$total_resources_allocated = array_sum(array_column($distribution_resources, 'quantity_allocated'));

// Calculate total quantity from resources
$total_quantity_needed = 0;
foreach ($distribution_resources as $resource) {
    $total_quantity_needed += $resource['quantity_allocated'];
}

// ============ Get assigned volunteers ============
$volunteers_query = "
    SELECT 
        dv.*
    FROM distribution_volunteer dv
    WHERE dv.distribution_id = ?
    ORDER BY dv.assigned_timestamp DESC
";

$volunteers_stmt = $db->prepare($volunteers_query);
$volunteers_stmt->bind_param("i", $distribution_id);
$volunteers_stmt->execute();
$volunteers_result = $volunteers_stmt->get_result();
$assigned_volunteers_raw = $volunteers_result->fetch_all(MYSQLI_ASSOC);
$volunteers_stmt->close();

// Handle status update
$success = $error = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_status'])) {
    $new_status = $_POST['status'] ?? '';
    $quantity_received = $_POST['quantity_received'] ?? $basic_distribution['quantity_sent'];
    $status_notes = $_POST['status_notes'] ?? '';
    
    try {
        // Start transaction
        $db->begin_transaction();

        // Update distribution status
        $update_query = "
            UPDATE distribution 
            SET status = ?, 
                quantity_sent = ?, 
                comments = CONCAT(IFNULL(comments, ''), '\n\nStatus Update (', DATE_FORMAT(NOW(), '%Y-%m-d %H:%i:%s'), '): ', ?)
            WHERE distribution_id = ?
        ";
        $update_stmt = $db->prepare($update_query);
        $update_stmt->bind_param("sisi", $new_status, $quantity_received, $status_notes, $distribution_id);
        
        if (!$update_stmt->execute()) {
            throw new Exception("Error updating distribution status: " . $update_stmt->error);
        }
        $update_stmt->close();

        // If status is Completed, update distribution_items and distribution_resources
        if ($new_status === 'Completed') {
            // Update distribution_items status to 'Delivered'
            $items_update_query = "
                UPDATE distribution_items 
                SET status = 'Delivered' 
                WHERE distribution_id = ?
            ";
            $items_update_stmt = $db->prepare($items_update_query);
            $items_update_stmt->bind_param("i", $distribution_id);
            
            if (!$items_update_stmt->execute()) {
                throw new Exception("Error updating distribution items: " . $items_update_stmt->error);
            }
            $items_update_stmt->close();

            // Update distribution_resources with distributed quantity
            $resources_update_query = "
                UPDATE distribution_resources 
                SET quantity_distributed = quantity_allocated 
                WHERE distribution_id = ?
            ";
            $resources_update_stmt = $db->prepare($resources_update_query);
            $resources_stmt->bind_param("i", $distribution_id);
            $resources_update_stmt->execute();
            $resources_update_stmt->close();
        }

        // Commit transaction
        $db->commit();
        
        $success = "✅ Distribution status updated successfully to: " . $new_status;
        
        // Refresh distribution data
        $stmt = $db->prepare($query);
        $stmt->bind_param("i", $distribution_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $distribution = $result->fetch_assoc();
        $stmt->close();
        
        // Refresh other data
        $victims_stmt = $db->prepare($victims_query);
        $victims_stmt->bind_param("i", $distribution_id);
        $victims_stmt->execute();
        $victims_result = $victims_stmt->get_result();
        $distribution_victims = $victims_result->fetch_all(MYSQLI_ASSOC);
        $victims_stmt->close();

        $resources_stmt = $db->prepare($resources_query);
        $resources_stmt->bind_param("i", $distribution_id);
        $resources_stmt->execute();
        $resources_result = $resources_stmt->get_result();
        $distribution_resources = $resources_result->fetch_all(MYSQLI_ASSOC);
        $resources_stmt->close();

        // Recalculate totals
        $total_families = count($distribution_victims);
        $total_resources_allocated = array_sum(array_column($distribution_resources, 'quantity_allocated'));
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $db->rollback();
        $error = "❌ Error updating status: " . $e->getMessage();
    }
}

// Helper function to safely output data
function safe_output($data, $default = '') {
    return htmlspecialchars($data ?? $default);
}

// ============ IMPROVED API FETCH FUNCTION ============
function fetchDataWithDebug($url, $timeout = 5) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'User-Agent: DisasterReliefSystem/1.0'
        ]
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($response === false) {
        return [
            'success' => false,
            'error' => "CURL Error: $error",
            'http_code' => $httpCode
        ];
    }
    
    $data = json_decode($response, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        return [
            'success' => false,
            'error' => 'Invalid JSON: ' . json_last_error_msg(),
            'raw_response' => substr($response, 0, 200),
            'http_code' => $httpCode
        ];
    }
    
    return [
        'success' => true,
        'data' => $data,
        'http_code' => $httpCode
    ];
}

// ============ API URLs ============
$DISASTER_API_URL = 'http://10.147.17.116:8000/disaster.php';
$VICTIM_API_URL = 'http://10.147.17.116:8000/victim.php';
$VOLUNTEER_API_URL = 'http://10.147.17.30:8000/api_volunteer.php';

// Initialize variables
$disaster_name = "Unknown Disaster";
$disaster_location = "Unknown";
$victim_names_by_id = [];
$volunteer_names_by_id = [];
$assigned_volunteers = [];

// Track API status
$disaster_api_status = false;
$victim_api_status = false;
$volunteer_api_status = false;

$api_debug_info = [];

// Get disaster ID from distribution
$disaster_id = $distribution['disaster_id'] ?? 0;

// DEBUG: Get and display raw disaster API response
$disaster_api_result = fetchDataWithDebug($DISASTER_API_URL);
$api_debug_info['disaster'] = $disaster_api_result;

if ($disaster_api_result['success']) {
    $disaster_api_status = true;
    
    // Check different response formats
    $disaster_data = $disaster_api_result['data'];
    
    // Debug: Log what we got
    error_log("Disaster API Response: " . print_r($disaster_data, true));
    
    // Check if data is an array
    if (is_array($disaster_data)) {
        // Try to find disaster by ID
        foreach ($disaster_data as $item) {
            if (!is_array($item)) continue;
            
            // Try multiple possible ID field names
            $possible_id_fields = ['disaster_id', 'Disaster_ID', 'disasterId', 'DisasterID', 'id'];
            $found_id = null;
            
            foreach ($possible_id_fields as $field) {
                if (isset($item[$field]) && is_numeric($item[$field])) {
                    $found_id = intval($item[$field]);
                    break;
                }
            }
            
            if ($found_id && $found_id == $disaster_id) {
                // Try multiple possible name fields
                $possible_name_fields = ['disaster_name', 'Disaster_Name', 'disasterName', 'name', 'title', 'Name'];
                $possible_location_fields = ['location', 'Location', 'district', 'District', 'area', 'Area', 'city', 'City'];
                
                foreach ($possible_name_fields as $field) {
                    if (isset($item[$field]) && !empty($item[$field])) {
                        $disaster_name = htmlspecialchars($item[$field]);
                        break;
                    }
                }
                
                foreach ($possible_location_fields as $field) {
                    if (isset($item[$field]) && !empty($item[$field])) {
                        $disaster_location = htmlspecialchars($item[$field]);
                        break;
                    }
                }
                
                // If still not found, use first non-empty string value
                if ($disaster_name == "Unknown Disaster") {
                    foreach ($item as $key => $value) {
                        if (is_string($value) && !empty($value) && $key !== 'disaster_id' && $key !== 'id') {
                            $disaster_name = htmlspecialchars($value);
                            break;
                        }
                    }
                }
                
                error_log("Found disaster: ID=$disaster_id, Name=$disaster_name, Location=$disaster_location");
                break;
            }
        }
        
        // If still not found, use first item as fallback
        if ($disaster_name == "Unknown Disaster" && !empty($disaster_data[0]) && is_array($disaster_data[0])) {
            $first_item = $disaster_data[0];
            foreach ($first_item as $key => $value) {
                if (is_string($value) && !empty($value) && $key !== 'disaster_id' && $key !== 'id') {
                    $disaster_name = "Disaster: " . htmlspecialchars($value);
                    break;
                }
            }
        }
    }
} else {
    error_log("Disaster API failed: " . $disaster_api_result['error']);
}

// Get victim data
$victim_api_result = fetchDataWithDebug($VICTIM_API_URL);
$api_debug_info['victim'] = $victim_api_result;

if ($victim_api_result['success']) {
    $victim_api_status = true;
    $victim_data = $victim_api_result['data'];
    
    if (is_array($victim_data)) {
        foreach ($victim_data as $victim) {
            if (!is_array($victim)) continue;
            
            // Get victim ID
            $victim_id = null;
            $possible_id_fields = ['victim_id', 'Victim_ID', 'victimId', 'VictimID', 'id'];
            
            foreach ($possible_id_fields as $field) {
                if (isset($victim[$field]) && is_numeric($victim[$field])) {
                    $victim_id = intval($victim[$field]);
                    break;
                }
            }
            
            if ($victim_id) {
                // Get victim name
                $victim_name = 'Unknown';
                $possible_name_fields = ['full_name', 'Full_Name', 'fullName', 'name', 'victim_name', 'Name'];
                
                foreach ($possible_name_fields as $field) {
                    if (isset($victim[$field]) && !empty($victim[$field])) {
                        $victim_name = htmlspecialchars($victim[$field]);
                        break;
                    }
                }
                
                $victim_names_by_id[$victim_id] = $victim_name;
            }
        }
    }
}

// Get volunteer data
$volunteer_api_result = fetchDataWithDebug($VOLUNTEER_API_URL);
$api_debug_info['volunteer'] = $volunteer_api_result;

if ($volunteer_api_result['success']) {
    $volunteer_api_status = true;
    $volunteer_data = $volunteer_api_result['data'];
    
    if (is_array($volunteer_data)) {
        foreach ($volunteer_data as $volunteer) {
            if (!is_array($volunteer)) continue;
            
            $volunteer_id = $volunteer['volunteer_id'] ?? 
                          $volunteer['Volunteer_ID'] ?? 
                          $volunteer['id'] ?? null;
            
            if ($volunteer_id) {
                $volunteer_name = $volunteer['name'] ?? 
                                $volunteer['full_name'] ?? 
                                $volunteer['Full_Name'] ?? 
                                "Volunteer #" . $volunteer_id;
                $volunteer_role = $volunteer['role'] ?? 
                                $volunteer['Role'] ?? 
                                'Volunteer';
                $volunteer_names_by_id[$volunteer_id] = [
                    'name' => $volunteer_name,
                    'role' => $volunteer_role
                ];
            }
        }
    }
}

// ============ Process assigned volunteers with API data ============
foreach ($assigned_volunteers_raw as $volunteer) {
    $volunteer_id = $volunteer['volunteer_id'];
    
    if (isset($volunteer_names_by_id[$volunteer_id])) {
        $volunteer_info = $volunteer_names_by_id[$volunteer_id];
        $assigned_volunteers[] = [
            'volunteer_id' => $volunteer_id,
            'volunteer_name' => $volunteer_info['name'],
            'volunteer_role' => $volunteer_info['role'],
            'status' => $volunteer['status'] ?? 'Active',
            'assigned_timestamp' => $volunteer['assigned_timestamp'] ?? date('Y-m-d H:i:s')
        ];
    } else {
        $assigned_volunteers[] = [
            'volunteer_id' => $volunteer_id,
            'volunteer_name' => "Volunteer #" . $volunteer_id,
            'volunteer_role' => 'Unknown',
            'status' => $volunteer['status'] ?? 'Active',
            'assigned_timestamp' => $volunteer['assigned_timestamp'] ?? date('Y-m-d H:i:s')
        ];
    }
}

// ============ Get victim names for this distribution ============
$distribution_victim_names = [];
foreach ($distribution_victims as $victim) {
    $victim_id = $victim['victim_id'];
    $distribution_victim_names[$victim_id] = $victim_names_by_id[$victim_id] ?? "Victim #" . $victim_id;
}

// Get current status for CSS classes
$current_status = $distribution['status'] ?? 'Planned';
$status_class = 'status-' . strtolower($current_status);

// Map your statuses to example statuses for progress tracking
$status_map = [
    'Planned' => 'planned',
    'Assigned' => 'assigned', 
    'In Transit' => 'active',
    'Delivered' => 'active',
    'Completed' => 'completed',
    'Cancelled' => 'cancelled'
];

$current_status_mapped = $status_map[$current_status] ?? 'planned';

// Calculate progress percentage
$progress_percentage = 0;
switch ($current_status_mapped) {
    case 'planned': $progress_percentage = 25; break;
    case 'assigned': $progress_percentage = 50; break;
    case 'active': $progress_percentage = 75; break;
    case 'completed': $progress_percentage = 100; break;
    default: $progress_percentage = 0;
}

// Get unit for display (use default)
$resource_unit = 'units';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Distribution Status - Disaster Relief System</title>
    <style>
        <?php
        // Include the CSS from the example
        if (file_exists('../css/update.css')) {
            echo file_get_contents('../css/update.css');
        } else {
            // Fallback CSS if update.css doesn't exist
            ?>
            /* Fallback basic styles */
            .container { max-width: 1200px; margin: 0 auto; padding: 20px; }
            .card { background: white; border-radius: 10px; padding: 20px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
            .alert { padding: 16px 20px; border-radius: 10px; margin-bottom: 20px; }
            .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
            .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
            .alert-warning { background: #fff3cd; color: #856404; border: 1px solid #ffeaa7; }
            .alert-info { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
            .btn { padding: 10px 20px; border-radius: 5px; border: none; cursor: pointer; font-weight: bold; }
            .btn-update { background: #3498db; color: white; }
            .form-control { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; }
            <?php
        }
        ?>
        
        /* Additional styles for your PHP integration */
        .alert {
            padding: 16px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            border-left: 4px solid;
            animation: slideDown 0.4s ease;
        }
        
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .alert-success {
            background-color: rgba(16, 185, 129, 0.1);
            border-color: #10b981;
            color: #065f46;
        }
        
        .alert-error {
            background-color: rgba(239, 68, 68, 0.1);
            border-color: #ef4444;
            color: #991b1b;
        }
        
        .debug-panel {
            background: rgba(0,0,0,0.1);
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-family: monospace;
            font-size: 12px;
        }
        
        .data-row {
            display: flex;
            gap: 10px;
            margin-bottom: 5px;
        }
        
        .data-label {
            font-weight: bold;
            color: #4b5563;
        }
        
        /* Status badge overrides for your status system */
        .status-badge-large.status-planned {
            background: linear-gradient(135deg, #f39c12, #e67e22);
            color: white;
        }
        
        .status-badge-large.status-assigned {
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
        }
        
        .status-badge-large.status-active {
            background: linear-gradient(135deg, #2ecc71, #27ae60);
            color: white;
        }
        
        .status-badge-large.status-completed {
            background: linear-gradient(135deg, #9b59b6, #8e44ad);
            color: white;
        }
        
        .status-badge-large.status-cancelled {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
            color: white;
        }
        
        /* Status guide badges */
        .status-guide-badge.status-planned {
            background: linear-gradient(135deg, #f39c12, #e67e22);
            color: white;
        }
        
        .status-guide-badge.status-assigned {
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
        }
        
        .status-guide-badge.status-active {
            background: linear-gradient(135deg, #2ecc71, #27ae60);
            color: white;
        }
        
        .status-guide-badge.status-completed {
            background: linear-gradient(135deg, #9b59b6, #8e44ad);
            color: white;
        }
        
        .status-guide-badge.status-cancelled {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
            color: white;
        }
        
        /* Custom styles */
        .breadcrumb {
            margin-bottom: 20px;
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }
        
        .breadcrumb a {
            color: #3498db;
            text-decoration: none;
        }
        
        .breadcrumb-current {
            color: #666;
        }
        
        .page-header {
            margin-bottom: 30px;
        }
        
        .page-header-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .page-title-section h1 {
            margin: 0;
            color: #2c3e50;
        }
        
        .page-subtitle {
            color: #7f8c8d;
            margin: 5px 0 0 0;
        }
        
        .distribution-id-badge {
            background: #3498db;
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: bold;
        }
        
        .quick-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }
        
        .quick-stat-item {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
        }
        
        .quick-stat-label {
            font-size: 0.9em;
            color: #6c757d;
            margin-bottom: 5px;
        }
        
        .quick-stat-value {
            font-size: 1.2em;
            font-weight: bold;
            color: #2c3e50;
        }
        
        .main-grid {
            display: grid;
            grid-template-columns: 300px 1fr;
            gap: 20px;
        }
        
        
        .action-buttons-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
            margin-top: 20px;
        }
        
        .btn {
            padding: 10px 15px;
            border-radius: 5px;
            border: none;
            cursor: pointer;
            text-align: center;
            text-decoration: none;
            font-weight: bold;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        .btn-primary {
            background: #3498db;
            color: white;
        }
        
        .btn-success {
            background: #27ae60;
            color: white;
        }
        
        .btn-outline {
            background: white;
            color: #3498db;
            border: 1px solid #3498db;
        }
        
        .btn-update {
            background: #9b59b6;
            color: white;
            padding: 12px 24px;
            font-size: 1.1em;
            width: 100%;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #2c3e50;
        }
        
        .required-star {
            color: #e74c3c;
        }
        
        .form-control-icon {
            position: relative;
        }
        
        .input-icon {
            position: absolute;
            left: 10px;
            top: 50%;
            transform: translateY(-50%);
        }
        
        .form-control-icon select,
        .form-control-icon input {
            padding-left: 35px;
        }
        
        .volunteers-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .volunteers-table th,
        .volunteers-table td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        .volunteers-table th {
            background: #f8f9fa;
            font-weight: bold;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px 20px;
        }
        
        .empty-icon {
            font-size: 3em;
            margin-bottom: 15px;
        }
        
        .timeline-badge {
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 0.8em;
            font-weight: bold;
        }
        
        .badge-completed {
            background: #27ae60;
            color: white;
        }
        
        .badge-active {
            background: #3498db;
            color: white;
        }
        
        .badge-pending {
            background: #f39c12;
            color: white;
        }
        
        .api-status {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            margin-left: 5px;
        }
        
        .api-online {
            background-color: #27ae60;
        }
        
        .api-offline {
            background-color: #e74c3c;
        }
        
        .api-warning {
            background-color: #f39c12;
        }
        
        /* API Debug Panel */
        .api-debug-panel {
            background: #f8f9fa;
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 20px;
            font-family: monospace;
            font-size: 12px;
            max-height: 200px;
            overflow-y: auto;
        }
        
        .api-debug-title {
            font-weight: bold;
            margin-bottom: 10px;
            color: #666;
        }
        
        .api-debug-item {
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .api-debug-item:last-child {
            border-bottom: none;
        }
        
        .api-success { color: #27ae60; font-weight: bold; }
        .api-error { color: #e74c3c; font-weight: bold; }
        
        .raw-response {
            background: #2c3e50;
            color: #ecf0f1;
            padding: 10px;
            border-radius: 5px;
            margin-top: 10px;
            font-family: monospace;
            font-size: 11px;
            white-space: pre-wrap;
            max-height: 150px;
            overflow-y: auto;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- API Debug Panel -->
        <div class="api-debug-panel">
            <div class="api-debug-title">API Connection Status:</div>
            
            <div class="api-debug-item">
                <strong>Disaster API:</strong> 
                <span class="<?php echo $disaster_api_status ? 'api-success' : 'api-error'; ?>">
                    <?php echo $disaster_api_status ? '✅ Connected' : '❌ Failed'; ?>
                </span>
                <?php if (!$disaster_api_status): ?>
                    <div>Error: <?php echo htmlspecialchars($api_debug_info['disaster']['error'] ?? 'Unknown error'); ?></div>
                    <?php if (isset($api_debug_info['disaster']['raw_response'])): ?>
                        <div class="raw-response">
                            <?php echo htmlspecialchars($api_debug_info['disaster']['raw_response']); ?>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div>Found: <strong><?php echo $disaster_name; ?></strong> in <strong><?php echo $disaster_location; ?></strong></div>
                    <div>Looking for Disaster ID: <?php echo $disaster_id; ?></div>
                <?php endif; ?>
            </div>
            
            <div class="api-debug-item">
                <strong>Victim API:</strong> 
                <span class="<?php echo $victim_api_status ? 'api-success' : 'api-error'; ?>">
                    <?php echo $victim_api_status ? '✅ Connected' : '❌ Failed'; ?>
                </span>
                <?php if ($victim_api_status): ?>
                    <div>Loaded <?php echo count($victim_names_by_id); ?> victim names</div>
                <?php endif; ?>
            </div>
            
            <div class="api-debug-item">
                <strong>Volunteer API:</strong> 
                <span class="<?php echo $volunteer_api_status ? 'api-success' : 'api-error'; ?>">
                    <?php echo $volunteer_api_status ? '✅ Connected' : '❌ Failed'; ?>
                </span>
            </div>
        </div>

        <!-- Alert Messages -->
        <?php if (!empty($success)): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <?php if (!empty($error)): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <!-- Breadcrumb Navigation -->
        <nav class="breadcrumb">
            <a href="distribution_main.php">🏠 Dashboard</a>
            <span class="breadcrumb-separator">›</span>
            <a href="distribution_main.php">📦 Distributions</a>
            <span class="breadcrumb-separator">›</span>
            <span class="breadcrumb-current">Update Status</span>
        </nav>

        <!-- Page Header -->
        <header class="page-header">
            <div class="page-header-top">
                <div class="page-title-section">
                    <h1>
                        <span>📝</span>
                        Update Distribution Status
                    </h1>
                    <p class="page-subtitle">
                        <span>📍</span>
                        Distribution ID: #<?php echo $distribution_id; ?> - 
                        <?php echo $total_families; ?> family(ies) - 
                        Total allocated: <?php echo $total_quantity_needed; ?> 
                        <?php echo $resource_unit; ?>
                        <span class="api-status <?php 
                            if ($disaster_api_status && $victim_api_status && $volunteer_api_status) echo 'api-online';
                            elseif (!$disaster_api_status && !$victim_api_status && !$volunteer_api_status) echo 'api-offline';
                            else echo 'api-warning';
                        ?>" title="
                            <?php
                            $statuses = [];
                            if ($disaster_api_status) $statuses[] = 'Disaster API: Online';
                            else $statuses[] = 'Disaster API: Offline';
                            if ($victim_api_status) $statuses[] = 'Victim API: Online';
                            else $statuses[] = 'Victim API: Offline';
                            if ($volunteer_api_status) $statuses[] = 'Volunteer API: Online';
                            else $statuses[] = 'Volunteer API: Offline';
                            echo implode(', ', $statuses);
                            ?>
                        "></span>
                    </p>
                </div>
                <div class="distribution-id-badge">
                    #<?php echo $distribution_id; ?>
                </div>
            </div>

            <!-- Quick Stats -->
            <div class="quick-stats">
                <div class="quick-stat-item">
                    <div class="quick-stat-label">Disaster</div>
                    <div class="quick-stat-value"><?php echo safe_output($disaster_name); ?></div>
                </div>
                <div class="quick-stat-item">
                    <div class="quick-stat-label">Families</div>
                    <div class="quick-stat-value"><?php echo $total_families; ?></div>
                </div>
                <div class="quick-stat-item">
                    <div class="quick-stat-label">Resources Allocated</div>
                    <div class="quick-stat-value">
                        <?php echo $total_quantity_needed; ?> 
                        <?php echo $resource_unit; ?>
                    </div>
                </div>
                <div class="quick-stat-item">
                    <div class="quick-stat-label">Quantity Sent</div>
                    <div class="quick-stat-value">
                        <?php echo safe_output($basic_distribution['quantity_sent'] ?? '0'); ?> 
                        <?php echo $resource_unit; ?>
                    </div>
                </div>
            </div>
        </header>

        <!-- Main Grid Layout -->
        <div class="main-grid">
            <!-- Sidebar -->
            <aside class="sidebar">
                <!-- Distribution Overview -->
                <div class="card">
                    <div class="card-header">
                        📊 Distribution Overview
                    </div>
                    <div class="card-body">
                        <div class="distribution-overview">
                            <!-- Current Status -->
                            <div class="current-status-display">
                                <div class="status-label-small">Current Status</div>
                                <div class="status-badge-large <?php echo 'status-' . strtolower($current_status_mapped); ?>">
                                    <?php echo safe_output($current_status); ?>
                                </div>
                            </div>

                            <!-- Basic Information -->
                            <div class="overview-section">
                                <div class="section-title">Basic Information</div>
                                <div class="info-item">
                                    <span class="info-label">Distribution ID</span>
                                    <span class="info-value">#<?php echo $distribution_id; ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Created Date</span>
                                    <span class="info-value"><?php echo date('M d, Y', strtotime($distribution['date'] ?? $basic_distribution['date'] ?? 'now')); ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Status</span>
                                    <span class="info-value"><?php echo safe_output($current_status); ?></span>
                                </div>
                            </div>

                            <!-- Disaster Details -->
                            <div class="overview-section">
                                <div class="section-title">Disaster Details</div>
                                <div class="info-item">
                                    <span class="info-label">Disaster</span>
                                    <span class="info-value"><?php echo safe_output($disaster_name); ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Location</span>
                                    <span class="info-value"><?php echo safe_output($disaster_location); ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Disaster ID</span>
                                    <span class="info-value">#<?php echo $distribution['disaster_id'] ?? 'N/A'; ?></span>
                                </div>
                            </div>

                            <!-- Resource Details -->
                            <div class="overview-section">
                                <div class="section-title">Resource Details</div>
                                <div class="info-item">
                                    <span class="info-label">Resources Allocated</span>
                                    <span class="info-value"><?php echo count($distribution_resources); ?> types</span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Total Allocated</span>
                                    <span class="info-value">
                                        <?php echo $total_quantity_needed; ?> 
                                        <?php echo $resource_unit; ?>
                                    </span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Quantity Sent</span>
                                    <span class="info-value">
                                        <?php echo safe_output($basic_distribution['quantity_sent'] ?? '0'); ?> 
                                        <?php echo $resource_unit; ?>
                                    </span>
                                </div>
                            </div>

                            <!-- Families Assigned -->
                            <?php if (!empty($distribution_victims)): ?>
                            <div class="overview-section">
                                <div class="section-title">Families Assigned (<?php echo $total_families; ?>)</div>
                                <?php $index = 1; ?>
                                <?php foreach ($distribution_victim_names as $victim_id => $victim_name): ?>
                                <div class="info-item" style="border-bottom: 1px solid #eee; padding-bottom: 10px; margin-bottom: 10px;">
                                    <div style="display: flex; justify-content: space-between; width: 100%;">
                                        <div>
                                            <div style="font-weight: 600; color: #333; margin-bottom: 3px;">
                                                <?php echo ($index++) . '. ' . safe_output($victim_name); ?>
                                            </div>
                                            <?php 
                                            // Find the victim status from distribution_victims array
                                            $victim_status = 'Scheduled';
                                            foreach ($distribution_victims as $victim) {
                                                if ($victim['victim_id'] == $victim_id) {
                                                    $victim_status = $victim['status'] ?? 'Scheduled';
                                                    break;
                                                }
                                            }
                                            ?>
                                            <div style="font-size: 0.8rem; color: #666;">
                                                Status: <?php echo safe_output($victim_status); ?>
                                            </div>
                                        </div>
                                        <div style="text-align: right;">
                                            <div style="font-size: 0.8rem; color: #666;">
                                                ID: <?php echo $victim_id; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- Action Buttons -->
                        <div class="action-buttons-grid">
                            <a href="view_distribution.php?id=<?php echo $distribution_id; ?>" class="btn btn-primary">
                                👁️ View Details
                            </a>
                            <a href="assign_volunteer.php?distribution_id=<?php echo $distribution_id; ?>" class="btn btn-success">
                                👥 Assign
                            </a>
                            <button class="btn btn-outline" onclick="window.print()">
                                🖨️ Print
                            </button>
                            <a href="distribution_main.php" class="btn btn-outline">
                                📊 Dashboard
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Progress Tracker -->
                <div class="card">
                    <div class="card-header">
                        📈 Progress Tracker
                    </div>
                    <div class="card-body">
                        <div class="progress-tracker">
                            <div class="progress-bar-wrapper">
                                <div class="progress-fill" id="progressFill" style="width: <?php echo $progress_percentage; ?>%;"></div>
                            </div>
                            <div class="progress-steps">
                                <div class="progress-step <?php echo $current_status_mapped === 'planned' ? 'active' : ($progress_percentage >= 25 ? 'completed' : ''); ?>" data-status="planned">
                                    <div class="step-circle">1</div>
                                    <div class="step-label">Planned</div>
                                </div>
                                <div class="progress-step <?php echo $current_status_mapped === 'assigned' ? 'active' : ($progress_percentage >= 50 ? 'completed' : ''); ?>" data-status="assigned">
                                    <div class="step-circle">2</div>
                                    <div class="step-label">Assigned</div>
                                </div>
                                <div class="progress-step <?php echo $current_status_mapped === 'active' ? 'active' : ($progress_percentage >= 75 ? 'completed' : ''); ?>" data-status="active">
                                    <div class="step-circle">3</div>
                                    <div class="step-label">Active</div>
                                </div>
                                <div class="progress-step <?php echo $current_status_mapped === 'completed' ? 'active' : ($progress_percentage >= 100 ? 'completed' : ''); ?>" data-status="completed">
                                    <div class="step-circle">✓</div>
                                    <div class="step-label">Completed</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </aside>

            <!-- Main Content -->
            <main class="main-content">
                <!-- Update Status Form -->
                <div class="update-form-section">
                    <div class="card">
                        <div class="card-header">
                            🔄 Update Distribution Status
                        </div>
                        <div class="card-body">
                            <form id="updateForm" method="POST">
                                <input type="hidden" name="update_status" value="1">
                                
                                <div class="form-group">
                                    <label class="form-label">
                                        New Status <span class="required-star">*</span>
                                    </label>
                                    <div class="form-control-icon">
                                        <span class="input-icon">🏷️</span>
                                        <select class="form-control" id="statusSelect" name="status" required>
                                            <option value="">Select new status...</option>
                                            <option value="Planned" <?php echo ($current_status === 'Planned') ? 'selected' : ''; ?>>📋 Planned</option>
                                            <option value="Assigned" <?php echo ($current_status === 'Assigned') ? 'selected' : ''; ?>>👥 Assigned</option>
                                            <option value="In Transit" <?php echo ($current_status === 'In Transit') ? 'selected' : ''; ?>>🚚 In Transit</option>
                                            <option value="Delivered" <?php echo ($current_status === 'Delivered') ? 'selected' : ''; ?>>📦 Delivered</option>
                                            <option value="Completed" <?php echo ($current_status === 'Completed') ? 'selected' : ''; ?>>✅ Completed</option>
                                            <option value="Cancelled" <?php echo ($current_status === 'Cancelled') ? 'selected' : ''; ?>>❌ Cancelled</option>
                                        </select>
                                    </div>
                                    <small class="form-text">Select the new status for this distribution</small>
                                </div>

                                <div class="form-group" id="quantityGroup" style="display: <?php echo ($current_status === 'Completed') ? 'block' : 'none'; ?>;">
                                    <label class="form-label">
                                        Quantity Sent <span class="required-star">*</span>
                                    </label>
                                    <div class="form-control-icon">
                                        <span class="input-icon">📦</span>
                                        <input type="number" class="form-control" id="quantityInput" name="quantity_received"
                                               placeholder="Enter quantity sent" min="0" max="<?php echo $total_quantity_needed; ?>"
                                               value="<?php echo $distribution['quantity_sent'] ?? $total_quantity_needed; ?>">
                                    </div>
                                    <small class="form-text">
                                        Actual quantity sent (max: <?php echo $total_quantity_needed; ?> 
                                        <?php echo $resource_unit; ?>)
                                    </small>
                                </div>

                                <div class="form-group">
                                    <label class="form-label">Status Notes</label>
                                    <textarea class="form-control" id="statusNotes" name="status_notes" rows="4" 
                                              placeholder="Add any notes about this status update..."><?php echo safe_output($_POST['status_notes'] ?? ''); ?></textarea>
                                    <small class="form-text">Optional: Add details or comments about the status change</small>
                                </div>

                                <button type="submit" class="btn btn-update">
                                    <span id="btnText">🔄 Update Status</span>
                                </button>
                            </form>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            📚 Status Reference Guide
                        </div>
                        <div class="card-body">
                            <div class="status-guide">
                                <div class="status-guide-title">Status Meanings</div>
                                <div class="status-guide-item">
                                    <div class="status-guide-badge status-planned">Planned</div>
                                    <div class="status-guide-text">Distribution is being planned and organized</div>
                                </div>
                                <div class="status-guide-item">
                                    <div class="status-guide-badge status-assigned">Assigned</div>
                                    <div class="status-guide-text">Volunteers have been assigned to this distribution</div>
                                </div>
                                <div class="status-guide-item">
                                    <div class="status-guide-badge status-active">In Transit/Delivered</div>
                                    <div class="status-guide-text">Distribution is in progress or delivered</div>
                                </div>
                                <div class="status-guide-item">
                                    <div class="status-guide-badge status-completed">Completed</div>
                                    <div class="status-guide-text">Distribution finished successfully</div>
                                </div>
                                <div class="status-guide-item">
                                    <div class="status-guide-badge status-cancelled">Cancelled</div>
                                    <div class="status-guide-text">Distribution has been cancelled</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Assigned Volunteers -->
                <div class="card">
                    <div class="card-header">
                        👥 Assigned Volunteers (<?php echo count($assigned_volunteers); ?>)
                    </div>
                    <div class="card-body">
                        <?php if (count($assigned_volunteers) > 0): ?>
                            <table class="volunteers-table">
                                <thead>
                                    <tr>
                                        <th>Volunteer</th>
                                        <th>Role</th>
                                        <th>Assignment Status</th>
                                        <th>Assigned On</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($assigned_volunteers as $volunteer): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo safe_output($volunteer['volunteer_name']); ?></strong>
                                        </td>
                                        <td><?php echo safe_output($volunteer['volunteer_role']); ?></td>
                                        <td>
                                            <?php 
                                            $status_text = $volunteer['status'] ?? 'Active';
                                            $status_class = strtolower($status_text);
                                            ?>
                                            <span class="timeline-badge badge-<?php echo $status_class; ?>">
                                                <?php echo $status_text; ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('M j, Y g:i A', strtotime($volunteer['assigned_timestamp'] ?? 'now')); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <div class="empty-state">
                                <div class="empty-icon">👥</div>
                                <h3>No Volunteers Assigned</h3>
                                <p>No volunteers are currently assigned to this distribution. Assign volunteers to proceed with the distribution.</p>
                                <a href="assign_volunteer.php?distribution_id=<?php echo $distribution_id; ?>" class="btn btn-success">
                                    👥 Assign Volunteers
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Status History Timeline -->
                <div class="card">
                    <div class="card-header">
                        📋 Status History
                    </div>
                    <div class="card-body">
                        <div class="timeline-wrapper">
                            <div class="timeline">
                                <?php
                                // Define statuses in order
                                $statuses_in_order = ['Planned', 'Assigned', 'In Transit', 'Delivered', 'Completed'];
                                $current_status_index = array_search($current_status, $statuses_in_order);
                                if ($current_status_index === false) {
                                    $current_status_index = 0;
                                }
                                
                                foreach ($statuses_in_order as $index => $status):
                                    $is_completed = $index < $current_status_index;
                                    $is_active = $index === $current_status_index;
                                ?>
                                <div class="timeline-item <?php echo $is_completed ? 'completed' : ($is_active ? 'active' : ''); ?>">
                                    <div class="timeline-marker"></div>
                                    <div class="timeline-content">
                                        <span class="timeline-status"><?php echo $status; ?></span>
                                        <span class="timeline-badge <?php echo $is_completed ? 'badge-completed' : ($is_active ? 'badge-active' : 'badge-pending'); ?>">
                                            <?php 
                                            if ($is_completed) echo '✓ Completed';
                                            elseif ($is_active) echo '⏳ In Progress';
                                            else echo '○ Pending';
                                            ?>
                                        </span>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
        // Status Select Handler
        const statusSelect = document.getElementById('statusSelect');
        const quantityGroup = document.getElementById('quantityGroup');
        const quantityInput = document.getElementById('quantityInput');
        const progressFill = document.getElementById('progressFill');
        const progressSteps = document.querySelectorAll('.progress-step');
        const updateForm = document.getElementById('updateForm');
        const btnText = document.getElementById('btnText');

        // Get quantities from PHP
        const maxQuantityAllocated = <?php echo $total_quantity_needed; ?>;
        const currentQuantitySent = <?php echo $basic_distribution['quantity_sent'] ?? $total_quantity_needed; ?>;

        // Initialize quantity input with current sent value
        quantityInput.value = currentQuantitySent;

        statusSelect.addEventListener('change', function() {
            const status = this.value;

            // Show/hide quantity field for completed status
            if (status === 'Completed') {
                quantityGroup.style.display = 'block';
                quantityInput.required = true;
            } else {
                quantityGroup.style.display = 'none';
                quantityInput.required = false;
            }

            // Update progress bar and steps
            updateProgress(status);

            // Show notification preview
            if (status) {
                showNotification('Status Preview', getStatusDescription(status), 'info');
            }
        });

        function updateProgress(status) {
            // Reset all steps
            progressSteps.forEach(step => {
                step.classList.remove('active', 'completed');
            });

            const statusMap = {
                'Planned': { width: '25%', activeIndex: 0 },
                'Assigned': { width: '50%', activeIndex: 1 },
                'In Transit': { width: '75%', activeIndex: 2 },
                'Delivered': { width: '75%', activeIndex: 2 },
                'Completed': { width: '100%', activeIndex: 3 }
            };

            if (statusMap[status]) {
                const { width, activeIndex } = statusMap[status];
                progressFill.style.width = width;

                // Mark completed steps
                for (let i = 0; i < activeIndex; i++) {
                    progressSteps[i].classList.add('completed');
                }

                // Mark active step
                if (progressSteps[activeIndex]) {
                    progressSteps[activeIndex].classList.add('active');
                }
            }
        }

        function getStatusDescription(status) {
            const descriptions = {
                'Planned': 'Distribution is being planned and organized',
                'Assigned': 'Volunteers have been assigned to this distribution',
                'In Transit': 'Distribution is currently in transit',
                'Delivered': 'Distribution has been delivered',
                'Completed': 'Distribution has been successfully completed',
                'Cancelled': 'Distribution has been cancelled'
            };
            return descriptions[status] || '';
        }

        // Form Submission
        updateForm.addEventListener('submit', function(e) {
            const status = statusSelect.value;
            const quantity = parseInt(quantityInput.value) || 0;

            if (!status) {
                e.preventDefault();
                showNotification('Error', 'Please select a new status', 'error');
                return;
            }

            if (status === 'Completed' && !quantityInput.value) {
                e.preventDefault();
                showNotification('Error', 'Please enter the quantity sent', 'error');
                return;
            }

            if (status === 'Completed' && quantity > maxQuantityAllocated) {
                e.preventDefault();
                showNotification('Error', `Quantity sent (${quantity}) cannot exceed quantity allocated (${maxQuantityAllocated})`, 'error');
                return;
            }

            // Add warning if quantity is less than total allocated
            if (status === 'Completed' && quantity < maxQuantityAllocated) {
                if (!confirm(`Warning: Quantity sent (${quantity}) is less than quantity allocated (${maxQuantityAllocated}). Some families may not receive enough. Are you sure you want to continue?`)) {
                    e.preventDefault();
                    return;
                }
            }

            // Show loading state
            const originalText = btnText.innerHTML;
            btnText.innerHTML = '<span class="loading-spinner"></span> Updating...';
            document.querySelector('.btn-update').disabled = true;
        });

        // Notification System
        function showNotification(title, message, type = 'info') {
            const notification = document.createElement('div');
            notification.className = `alert alert-${type}`;
            notification.style.position = 'fixed';
            notification.style.top = '20px';
            notification.style.right = '20px';
            notification.style.zIndex = '1000';
            notification.style.maxWidth = '400px';
            notification.style.boxShadow = '0 4px 12px rgba(0,0,0,0.15)';

            const icons = {
                success: '✅',
                error: '❌',
                warning: '⚠️',
                info: 'ℹ️'
            };

            notification.innerHTML = `
                <div style="display: flex; align-items: center; gap: 10px;">
                    <div style="font-size: 1.2em;">${icons[type]}</div>
                    <div>
                        <div style="font-weight: bold; margin-bottom: 2px;">${title}</div>
                        <div style="font-size: 0.9em;">${message}</div>
                    </div>
                </div>
            `;

            document.body.appendChild(notification);

            // Remove after 3 seconds
            setTimeout(() => {
                notification.style.opacity = '0';
                notification.style.transition = 'opacity 0.3s ease';
                setTimeout(() => {
                    if (notification.parentNode) {
                        notification.remove();
                    }
                }, 300);
            }, 3000);
        }

        // Real-time validation for quantity
        quantityInput.addEventListener('input', function() {
            const value = parseInt(this.value) || 0;

            if (value > maxQuantityAllocated) {
                this.style.borderColor = '#e74c3c';
                showNotification('Warning', `Quantity cannot exceed ${maxQuantityAllocated}`, 'warning');
            } else if (value < maxQuantityAllocated) {
                this.style.borderColor = '#f39c12';
                showNotification('Notice', `Quantity is less than allocated amount (${maxQuantityAllocated})`, 'warning');
            } else {
                this.style.borderColor = '#27ae60';
            }
        });

        // Initialize on load
        document.addEventListener('DOMContentLoaded', function() {
            // Focus on status select
            statusSelect.focus();

            // Initialize progress based on current status
            updateProgress('<?php echo $current_status; ?>');
            
            // Initialize quantity field visibility
            if (statusSelect.value === 'Completed') {
                quantityGroup.style.display = 'block';
                quantityInput.required = true;
            }
        });
    </script>
</body>
</html>