<?php
// ========================================
// VIEW DISTRIBUTION DETAILS - FIXED VERSION
// ========================================

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';

// Start session for user data
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Sample user data
$current_user = [
    'name' => $_SESSION['user_name'] ?? 'Admin User',
    'role' => $_SESSION['user_role'] ?? 'System Administrator',
    'avatar' => $_SESSION['user_avatar'] ?? 'AU',
    'email' => $_SESSION['user_email'] ?? 'admin@disasterrelief.org'
];

$database = new Database();
$db = $database->getConnection();

$distribution_id = $_GET['id'] ?? null;

if (!$distribution_id) {
    header("Location: distribution_main.php");
    exit;
}

// API Configuration
$DISASTER_API_URL = 'http://10.147.17.116:8000/disaster.php';
$VICTIM_API_URL = 'http://10.147.17.116:8000/victim.php';
$NEEDS_API_URL = 'http://10.147.17.116:8000/needs.php';

/* ----------------------------------------
   FETCH DATA FROM APIS
---------------------------------------- */
function fetchDataFromAPI($url, $timeout = 5) {
    $context = stream_context_create([
        'http' => [
            'timeout' => $timeout,
            'header' => "Accept: application/json\r\n"
        ],
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
        ]
    ]);
    
    try {
        $response = @file_get_contents($url, false, $context);
        if ($response === FALSE) {
            return ['success' => false, 'error' => 'Server not responding: ' . $url];
        }
        
        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['success' => false, 'error' => 'Invalid JSON: ' . json_last_error_msg()];
        }
        
        return ['success' => true, 'data' => $data];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

// Fetch API data
$disasterApiResult = fetchDataFromAPI($DISASTER_API_URL);
$victimApiResult = fetchDataFromAPI($VICTIM_API_URL);
$needsApiResult = fetchDataFromAPI($NEEDS_API_URL);

/* ----------------------------------------
   GET DISTRIBUTION BASIC DETAILS
---------------------------------------- */
$query = "
    SELECT 
        d.*
    FROM distribution d
    WHERE d.distribution_id = ?
";

error_log("Fetching distribution ID: " . $distribution_id);

$stmt = $db->prepare($query);
if (!$stmt) {
    die("Prepare failed: " . $db->error);
}
$stmt->bind_param("i", $distribution_id);
$stmt->execute();
$result = $stmt->get_result();
$distribution = $result->fetch_assoc();
$stmt->close();

if (!$distribution) {
    echo "<div class='container'><div class='alert alert-danger'>Distribution not found!</div></div>";
    exit;
}

error_log("Distribution found: " . json_encode($distribution));

// Get disaster details from API
$disaster_info = null;
if ($disasterApiResult['success'] && is_array($disasterApiResult['data'])) {
    $apiDisasters = $disasterApiResult['data'];
    $disaster_id_from_distribution = $distribution['disaster_id'];
    
    foreach ($apiDisasters as $apiDisaster) {
        // Check multiple possible field names for disaster ID
        $apiDisasterId = null;
        $possibleFields = ['disaster_id', 'Disaster_ID', 'disasterId', 'DisasterID', 'id'];
        
        foreach ($possibleFields as $field) {
            if (isset($apiDisaster[$field]) && intval($apiDisaster[$field]) == $disaster_id_from_distribution) {
                $apiDisasterId = intval($apiDisaster[$field]);
                break;
            }
        }
        
        if ($apiDisasterId == $disaster_id_from_distribution) {
            $disaster_info = [
                'disaster_id' => $apiDisasterId,
                'Disaster_Name' => $apiDisaster['disaster_name'] ?? 
                                $apiDisaster['Disaster_Name'] ?? 
                                $apiDisaster['disasterName'] ?? 
                                'Unknown',
                'Disaster_Type' => $apiDisaster['disaster_type'] ?? 
                                 $apiDisaster['Disaster_Type'] ?? 
                                 $apiDisaster['disasterType'] ?? 
                                 $apiDisaster['severity'] ?? 
                                 $apiDisaster['Severity'] ?? 
                                 'Unknown',
                'Disaster_Date' => $apiDisaster['disaster_date'] ?? 
                                 $apiDisaster['Disaster_Date'] ?? 
                                 $apiDisaster['date'] ?? 
                                 'Unknown',
                'Severity_level' => $apiDisaster['severity'] ?? 
                                  $apiDisaster['Severity'] ?? 
                                  $apiDisaster['severity_level'] ?? 
                                  'Medium',
                'Location' => $apiDisaster['district'] ?? 
                            $apiDisaster['District'] ?? 
                            $apiDisaster['location'] ?? 
                            $apiDisaster['Location'] ?? 
                            'Unknown',
                'Description' => $apiDisaster['description'] ?? ''
            ];
            break;
        }
    }
}

/* ----------------------------------------
   GET VICTIMS FROM DISTRIBUTION_ITEMS TABLE
---------------------------------------- */
$victims_query = "
    SELECT 
        di.*
    FROM distribution_items di
    WHERE di.distribution_id = ?
    ORDER BY di.item_id
";

error_log("Fetching victims for distribution ID: " . $distribution_id);

$victims_stmt = $db->prepare($victims_query);
if (!$victims_stmt) {
    die("Prepare failed for victims: " . $db->error);
}
$victims_stmt->bind_param("i", $distribution_id);
$victims_stmt->execute();
$victims_result = $victims_stmt->get_result();
$distribution_victim_ids = $victims_result->fetch_all(MYSQLI_ASSOC);
$victims_stmt->close();

error_log("Found " . count($distribution_victim_ids) . " victim IDs in distribution_items");

/* ----------------------------------------
   GET VICTIM DETAILS FROM API
---------------------------------------- */
$distribution_victims = [];
if ($victimApiResult['success'] && is_array($victimApiResult['data']) && !empty($distribution_victim_ids)) {
    $apiVictims = $victimApiResult['data'];
    
    foreach ($distribution_victim_ids as $victim_item) {
        $victimId = $victim_item['victim_id'];
        $found = false;
        
        error_log("Looking for victim ID: $victimId in API data");
        
        foreach ($apiVictims as $apiVictim) {
            // Check multiple possible field names for victim ID
            $apiVictimId = null;
            $possibleFields = ['victim_id', 'Victim_ID', 'victimId', 'VictimID', 'id'];
            
            foreach ($possibleFields as $field) {
                if (isset($apiVictim[$field]) && intval($apiVictim[$field]) == $victimId) {
                    $apiVictimId = intval($apiVictim[$field]);
                    break;
                }
            }
            
            if ($apiVictimId === $victimId) {
                $found = true;
                
                // Get approval info from local table
                $approval_info = ['approval_status' => 'Approved', 'approved_at' => null];
                if (!empty($distribution['disaster_id'])) {
                    $approval_query = "
                        SELECT approval_status, approved_at 
                        FROM victim_approvals 
                        WHERE victim_id = ? AND disaster_id = ?
                    ";
                    $approval_stmt = $db->prepare($approval_query);
                    if ($approval_stmt) {
                        $approval_stmt->bind_param("ii", $victimId, $distribution['disaster_id']);
                        $approval_stmt->execute();
                        $approval_result = $approval_stmt->get_result();
                        if ($approval_result->num_rows > 0) {
                            $approval_info = $approval_result->fetch_assoc();
                        }
                        $approval_stmt->close();
                    }
                }
                
                // Get needs for this victim
                $needsInfo = [
                    'has_baby' => false,
                    'has_elderly' => false,
                    'has_disabled' => false,
                    'priority' => 'Medium'
                ];
                
                if ($needsApiResult['success'] && is_array($needsApiResult['data'])) {
                    foreach ($needsApiResult['data'] as $need) {
                        $needVictimId = null;
                        $needFields = ['victim_id', 'Victim_ID', 'victimId', 'VictimID', 'id'];
                        
                        foreach ($needFields as $field) {
                            if (isset($need[$field]) && intval($need[$field]) == $victimId) {
                                $needVictimId = intval($need[$field]);
                                break;
                            }
                        }
                        
                        if ($needVictimId == $victimId) {
                            $needsInfo = [
                                'has_baby' => $need['has_baby'] ?? $need['Has_Baby'] ?? $need['hasBaby'] ?? false,
                                'has_elderly' => $need['has_elderly'] ?? $need['Has_Elderly'] ?? $need['hasElderly'] ?? false,
                                'has_disabled' => $need['has_disabled'] ?? $need['Has_Disabled'] ?? $need['hasDisabled'] ?? false,
                                'priority' => $need['priority'] ?? $need['Priority'] ?? 'Medium'
                            ];
                            break;
                        }
                    }
                }
                
                $distribution_victims[] = array_merge($victim_item, [
                    'victim_id' => $victimId,
                    'full_name' => $apiVictim['full_name'] ?? 
                                 $apiVictim['Full_Name'] ?? 
                                 $apiVictim['fullName'] ?? 
                                 $apiVictim['name'] ?? 'Unknown',
                    'ic_number' => $apiVictim['ic_number'] ?? 
                                  $apiVictim['IC_Number'] ?? 
                                  $apiVictim['icNumber'] ?? 'N/A',
                    'email' => $apiVictim['email'] ?? 
                              $apiVictim['Email'] ?? 'N/A',
                    'phone' => $apiVictim['phone'] ?? 
                              $apiVictim['Phone'] ?? '',
                    'address' => $apiVictim['address'] ?? 
                                $apiVictim['Address'] ?? 'Unknown',
                    'city' => $apiVictim['city'] ?? 
                             $apiVictim['City'] ?? '',
                    'postal_code' => $apiVictim['postal_code'] ?? 
                                   $apiVictim['Postal_Code'] ?? 
                                   $apiVictim['postalCode'] ?? '',
                    'district' => $apiVictim['district'] ?? 
                                 $apiVictim['District'] ?? 'N/A',
                    'family_members' => $apiVictim['family_members'] ?? 
                                      $apiVictim['Family_Members'] ?? 
                                      $apiVictim['familyMembers'] ?? 1,
                    'has_baby' => $needsInfo['has_baby'],
                    'has_elderly' => $needsInfo['has_elderly'],
                    'has_disabled' => $needsInfo['has_disabled'],
                    'priority' => $needsInfo['priority'],
                    'special_request' => $apiVictim['special_request'] ?? 
                                       $apiVictim['Special_Request'] ?? 
                                       $apiVictim['specialRequest'] ?? '',
                    'approval_status' => $approval_info['approval_status'] ?? 'Approved',
                    'approved_at' => $approval_info['approved_at'] ?? null
                ]);
                break;
            }
        }
        
        if (!$found) {
            error_log("Victim ID $victimId not found in API data");
            // Add a placeholder if victim not found in API
            $distribution_victims[] = array_merge($victim_item, [
                'victim_id' => $victimId,
                'full_name' => 'Victim #' . $victimId . ' (API data unavailable)',
                'ic_number' => 'N/A',
                'email' => 'N/A',
                'phone' => '',
                'address' => 'Address not available',
                'city' => '',
                'postal_code' => '',
                'district' => 'Unknown',
                'family_members' => 1,
                'has_baby' => false,
                'has_elderly' => false,
                'has_disabled' => false,
                'priority' => 'Medium',
                'special_request' => '',
                'approval_status' => 'Approved',
                'approved_at' => null,
                'api_missing' => true
            ]);
        }
    }
} else {
    error_log("Cannot fetch victims: API failed or no victim IDs");
}

error_log("Total victims prepared for display: " . count($distribution_victims));

/* ----------------------------------------
   GET RESOURCE ALLOCATIONS
---------------------------------------- */
$resources_query = "
    SELECT 
        dr.*
    FROM distribution_resources dr
    WHERE dr.distribution_id = ?
    ORDER BY dr.allocation_id
";

$resources_stmt = $db->prepare($resources_query);
if (!$resources_stmt) {
    die("Prepare failed for resources: " . $db->error);
}
$resources_stmt->bind_param("i", $distribution_id);
$resources_stmt->execute();
$resources_result = $resources_stmt->get_result();
$distribution_resources = $resources_result->fetch_all(MYSQLI_ASSOC);
$resources_stmt->close();

/* ----------------------------------------
   GET RESOURCE DETAILS
---------------------------------------- */
$resources_summary = [];
$local_resources = [];

// Get local resources first
$local_resources_query = "SELECT resource_id, name, type, unit FROM resource";
$local_resources_result = $db->query($local_resources_query);
if ($local_resources_result) {
    while ($row = $local_resources_result->fetch_assoc()) {
        $local_resources[$row['resource_id']] = $row;
    }
    $local_resources_result->free();
}

// Group resources from distribution_resources
foreach ($distribution_resources as $resource) {
    $resource_id = $resource['resource_id'];
    
    // Try to get resource details
    $resource_name = 'Unknown Resource';
    $resource_type = 'General';
    $resource_unit = 'units';
    
    // Check local resources first
    if (isset($local_resources[$resource_id])) {
        $resource_name = $local_resources[$resource_id]['name'];
        $resource_type = $local_resources[$resource_id]['type'];
        $resource_unit = $local_resources[$resource_id]['unit'];
    } 
    // Then check needs API
    else if ($needsApiResult['success'] && is_array($needsApiResult['data'])) {
        foreach ($needsApiResult['data'] as $need) {
            $needResourceId = null;
            $resourceFields = ['resource_id', 'Resource_ID', 'resourceId', 'ResourceID', 'id'];
            
            foreach ($resourceFields as $field) {
                if (isset($need[$field]) && intval($need[$field]) == $resource_id) {
                    $needResourceId = intval($need[$field]);
                    break;
                }
            }
            
            if ($needResourceId == $resource_id) {
                $resource_name = $need['resource_name'] ?? $need['Resource_Name'] ?? $need['resourceName'] ?? 'Unknown Resource';
                $resource_type = $need['resource_type'] ?? $need['Resource_Type'] ?? $need['resourceType'] ?? 'General';
                $resource_unit = $need['unit'] ?? $need['Unit'] ?? 'units';
                break;
            }
        }
    }
    
    if (!isset($resources_summary[$resource_id])) {
        $resources_summary[$resource_id] = [
            'name' => $resource_name,
            'type' => $resource_type,
            'unit' => $resource_unit,
            'total_quantity' => 0,
            'allocated' => 0,
            'distributed' => 0
        ];
    }
    $resources_summary[$resource_id]['total_quantity'] += $resource['quantity_allocated'];
    $resources_summary[$resource_id]['allocated'] = $resource['quantity_allocated'];
    $resources_summary[$resource_id]['distributed'] = $resource['quantity_distributed'];
}

/* ----------------------------------------
   GET ASSIGNED VOLUNTEERS - SIMPLIFIED VERSION
---------------------------------------- */
$assigned_volunteers = [];

// Check if distribution_volunteer table exists
$check_table_query = "SHOW TABLES LIKE 'distribution_volunteer'";
$table_result = $db->query($check_table_query);

if ($table_result && $table_result->num_rows > 0) {
    // Table exists, fetch volunteers
    $volunteers_query = "
        SELECT 
            dv.*
        FROM distribution_volunteer dv
        WHERE dv.distribution_id = ?
        ORDER BY dv.assigned_timestamp DESC
    ";
    
    $volunteers_stmt = $db->prepare($volunteers_query);
    if ($volunteers_stmt) {
        $volunteers_stmt->bind_param("i", $distribution_id);
        $volunteers_stmt->execute();
        $volunteers_result = $volunteers_stmt->get_result();
        $assigned_volunteers_raw = $volunteers_result->fetch_all(MYSQLI_ASSOC);
        $volunteers_stmt->close();
        
        // Format volunteer data
        foreach ($assigned_volunteers_raw as $volunteer) {
            $assigned_volunteers[] = [
                'volunteer_id' => $volunteer['volunteer_id'],
                'volunteer_name' => 'Volunteer #' . $volunteer['volunteer_id'],
                'volunteer_role' => 'Volunteer',
                'status' => $volunteer['status'] ?? 'Active',
                'assigned_timestamp' => $volunteer['assigned_timestamp'] ?? date('Y-m-d H:i:s')
            ];
        }
    }
} else {
    // Table doesn't exist, show sample data or empty
    error_log("distribution_volunteer table doesn't exist");
}

/* ----------------------------------------
   CALCULATE STATISTICS
---------------------------------------- */
$total_families = count($distribution_victims);
$total_resources_allocated = array_sum(array_column($distribution_resources, 'quantity_allocated'));

/* ----------------------------------------
   PARSE PLAN DETAILS FROM COMMENTS
---------------------------------------- */
$plan_details = [];
if ($distribution['comments']) {
    $lines = explode("\n", $distribution['comments']);
    foreach ($lines as $line) {
        $line = trim($line);
        if (strpos($line, 'Location:') !== false) {
            $plan_details['location'] = trim(str_replace('Location:', '', $line));
        }
        if (strpos($line, 'Coordinator:') !== false) {
            $plan_details['coordinator'] = trim(str_replace('Coordinator:', '', $line));
        }
        if (strpos($line, 'Estimated Duration:') !== false) {
            $plan_details['duration'] = trim(str_replace('Estimated Duration:', '', $line));
        }
        if (strpos($line, 'Volunteers Needed:') !== false) {
            $plan_details['volunteers_needed'] = trim(str_replace('Volunteers Needed:', '', $line));
        }
        // Parse resource allocations from comments
        if (strpos($line, '- ') === 0 && strpos($line, ':') !== false) {
            $parts = explode(':', substr($line, 2));
            if (count($parts) >= 2) {
                if (!isset($plan_details['resources'])) {
                    $plan_details['resources'] = [];
                }
                $plan_details['resources'][] = [
                    'name' => trim($parts[0]),
                    'amount' => trim($parts[1])
                ];
            }
        }
    }
}

// If location is not in comments, get it from distribution table
if (empty($plan_details['location']) && !empty($distribution['location'])) {
    $plan_details['location'] = $distribution['location'];
}

// If coordinator is not in comments, get it from distribution table
if (empty($plan_details['coordinator']) && !empty($distribution['coordinator_name'])) {
    $plan_details['coordinator'] = $distribution['coordinator_name'];
    if (!empty($distribution['coordinator_contact'])) {
        $plan_details['coordinator'] .= ' (' . $distribution['coordinator_contact'] . ')';
    }
}

// If duration is not in comments, get it from distribution table
if (empty($plan_details['duration']) && !empty($distribution['estimated_duration'])) {
    $plan_details['duration'] = $distribution['estimated_duration'] . ' hours';
}

// If volunteers needed is not in comments, get it from distribution table
if (empty($plan_details['volunteers_needed']) && !empty($distribution['volunteers_needed'])) {
    $plan_details['volunteers_needed'] = $distribution['volunteers_needed'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Distribution #<?php echo $distribution_id; ?> - Details</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/main.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .victim-group {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border: 1px solid rgba(0,0,0,0.05);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .victim-group:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
            border-color: #3498db;
        }
        
        .victim-group::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, #667eea, #764ba2);
        }
        
        .victim-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 2px solid #3498db;
        }
        
        .resource-summary-card {
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            border: 1px solid rgba(0,0,0,0.05);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 15px;
            transition: all 0.3s ease;
        }
        
        .resource-summary-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        
        .plan-detail-box {
            background: linear-gradient(135deg, #e8f4f8 0%, #d1ecf1 100%);
            border-left: 4px solid #3498db;
            padding: 15px;
            margin: 10px 0;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        
        .plan-detail-box:hover {
            transform: translateX(5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }
        
        .victim-header h3 {
            font-size: 1.2rem;
            color: #2c3e50;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .victim-header h3::before {
            content: '👤';
        }
        
        .priority-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 15px;
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 600;
            color: white;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .priority-badge::before {
            content: '';
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: currentColor;
            animation: pulse 2s infinite;
        }
        
        .badge-urgent {
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
        }
        
        .badge-high {
            background: linear-gradient(135deg, #f39c12 0%, #d35400 100%);
        }
        
        .badge-medium {
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
        }
        
        .badge-low {
            background: linear-gradient(135deg, #2ecc71 0%, #27ae60 100%);
        }
        
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 20px;
            border-radius: 50px;
            font-size: 0.9rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .status-badge.large {
            padding: 10px 25px;
            font-size: 1rem;
        }
        
        .status-badge::before {
            content: '';
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: currentColor;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.4; }
        }
        
        .status-planned {
            background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%);
            color: white;
        }
        
        .status-assigned {
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            color: white;
        }
        
        .status-active {
            background: linear-gradient(135deg, #2ecc71 0%, #27ae60 100%);
            color: white;
        }
        
        .status-completed {
            background: linear-gradient(135deg, #9b59b6 0%, #8e44ad 100%);
            color: white;
        }
        
        .status-cancelled {
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            color: white;
        }
        
        .severity-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 20px;
            border-radius: 50px;
            font-weight: 600;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .severity-badge::before {
            content: '';
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: currentColor;
        }
        
        .severity-low {
            background: rgba(46, 204, 113, 0.1);
            color: #27ae60;
            border: 1px solid rgba(46, 204, 113, 0.3);
        }
        
        .severity-medium {
            background: rgba(243, 156, 18, 0.1);
            color: #f39c12;
            border: 1px solid rgba(243, 156, 18, 0.3);
        }
        
        .severity-high {
            background: rgba(231, 76, 60, 0.1);
            color: #e74c3c;
            border: 1px solid rgba(231, 76, 60, 0.3);
        }
        
        .severity-critical {
            background: rgba(192, 57, 43, 0.1);
            color: #c0392b;
            border: 1px solid rgba(192, 57, 43, 0.3);
        }
        
        .badge-count {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 8px 20px;
            border-radius: 50px;
            font-weight: 600;
            font-size: 0.9rem;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-right: 15px;
        }
        
        .badge-count::before {
            content: '🎯';
        }
        
        .progress-bar-container {
            width: 100%;
            background: #e9ecef;
            border-radius: 10px;
            margin: 20px 0;
            overflow: hidden;
        }
        
        .progress-bar {
            height: 20px;
            border-radius: 10px;
            text-align: center;
            line-height: 20px;
            color: white;
            font-weight: bold;
            transition: width 0.5s ease;
        }
        
        .progress-planned { background: linear-gradient(90deg, #f39c12, #e67e22); }
        .progress-assigned { background: linear-gradient(90deg, #3498db, #2980b9); }
        .progress-active { background: linear-gradient(90deg, #2ecc71, #27ae60); }
        .progress-completed { background: linear-gradient(90deg, #9b59b6, #8e44ad); }
        
        .qr-section {
            background: white;
            padding: 20px;
            border-radius: 12px;
            text-align: center;
            margin-top: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }
        
        .qr-code {
            width: 150px;
            height: 150px;
            margin: 15px auto;
            background: #f8f9fa;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            color: #667eea;
        }
        
        .special-needs-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.75em;
            font-weight: 600;
            margin: 2px;
        }
        
        .badge-baby { background: #fff3cd; color: #856404; }
        .badge-elderly { background: #d1ecf1; color: #0c5460; }
        .badge-disabled { background: #f8d7da; color: #721c24; }
        
        .api-missing {
            background: #fff3cd;
            color: #856404;
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 0.9em;
            margin-top: 10px;
            border-left: 3px solid #ffc107;
        }
        
        @media (max-width: 768px) {
            .victim-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
        }
        
        @media print {
            .btn, .action-buttons { 
                display: none !important; 
            }
            
            .card-3d {
                box-shadow: none !important;
                border: 1px solid #ddd !important;
            }
        }
        
        .main-content {
            padding: 20px;
            background: #f8f9fa;
            min-height: 100vh;
        }
        
        .api-status {
            padding: 10px 15px;
            border-radius: 5px;
            margin: 10px 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .api-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .api-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .grid-2 {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .grid-3 {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 15px;
        }
        
        .grid-4 {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 30px 0;
        }
        
        .card-3d {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
            margin-bottom: 25px;
            border: 1px solid #e1e1e1;
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f2f5;
        }
        
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .alert-warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        
        .btn {
            padding: 10px 20px;
            border-radius: 5px;
            border: none;
            cursor: pointer;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            font-size: 0.95em;
        }
        
        .btn-success {
            background: #27ae60;
            color: white;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-primary {
            background: #3498db;
            color: white;
        }
        
        .btn-warning {
            background: #ffc107;
            color: #212529;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            transition: transform 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-label {
            color: #7f8c8d;
            font-size: 0.9em;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 10px;
        }
        
        .stat-number {
            font-size: 2.2em;
            font-weight: 700;
            color: #2c3e50;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 12px;
            margin-bottom: 30px;
        }
        
        .header-actions {
            display: flex;
            gap: 10px;
        }
        
        .fade-in {
            animation: fadeIn 0.8s ease-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>
    <!-- Loading Screen -->
    <div id="loading-screen" class="loading-screen">
        <div class="loading-content">
            <div class="loading-spinner"></div>
            <h3>Loading Distribution Details</h3>
            <p>Please wait while we load distribution data...</p>
        </div>
    </div>

    <!-- Main Content Area -->
    <main class="main-content" id="mainContent">
        <div class="container">
            <!-- API Status -->
            <div class="api-status <?php echo $disasterApiResult['success'] ? 'api-success' : 'api-error'; ?>">
                <i class="fas <?php echo $disasterApiResult['success'] ? 'fa-check-circle' : 'fa-times-circle'; ?>"></i>
                <div>
                    <strong>Disaster API:</strong> 
                    <?php echo $disasterApiResult['success'] ? 'Connected' : 'Failed'; ?>
                    <?php if ($disasterApiResult['success'] && is_array($disasterApiResult['data'])): ?>
                        <br><small><?php echo count($disasterApiResult['data']); ?> disasters loaded</small>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="api-status <?php echo $victimApiResult['success'] ? 'api-success' : 'api-error'; ?>">
                <i class="fas <?php echo $victimApiResult['success'] ? 'fa-check-circle' : 'fa-times-circle'; ?>"></i>
                <div>
                    <strong>Victim API:</strong> 
                    <?php echo $victimApiResult['success'] ? 'Connected' : 'Failed'; ?>
                    <?php if ($victimApiResult['success'] && is_array($victimApiResult['data'])): ?>
                        <br><small><?php echo count($victimApiResult['data']); ?> victims loaded</small>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="api-status <?php echo $needsApiResult['success'] ? 'api-success' : 'api-error'; ?>">
                <i class="fas <?php echo $needsApiResult['success'] ? 'fa-check-circle' : 'fa-times-circle'; ?>"></i>
                <div>
                    <strong>Needs API:</strong> 
                    <?php echo $needsApiResult['success'] ? 'Connected' : 'Failed'; ?>
                    <?php if ($needsApiResult['success'] && is_array($needsApiResult['data'])): ?>
                        <br><small><?php echo count($needsApiResult['data']); ?> needs loaded</small>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Debug information -->
            <div style="background: #f8f9fa; padding: 10px; margin-bottom: 20px; border-radius: 5px; border: 1px solid #ddd;">
                <strong>Debug Info:</strong> 
                Distribution ID: <?php echo $distribution_id; ?> | 
                Disaster ID: <?php echo $distribution['disaster_id'] ?? 'N/A'; ?> | 
                Found <?php echo $total_families; ?> families | 
                Found <?php echo count($resources_summary); ?> resource types
            </div>

            <!-- Enhanced Header with Gradient -->
            <div class="header fade-in">
                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 20px;">
                    <div>
                        <h1 style="margin: 0; display: flex; align-items: center; gap: 15px;">
                            <span style="background: rgba(255,255,255,0.2); padding: 10px; border-radius: 12px;">📦</span>
                            Distribution Plan Details
                        </h1>
                        <p style="margin: 10px 0 0 0; opacity: 0.9; font-size: 1.1rem;">
                            <strong>ID:</strong> DIST<?php echo str_pad($distribution_id, 3, '0', STR_PAD_LEFT); ?>
                            • <strong>Status:</strong> <?php echo ucfirst($distribution['status'] ?? 'Unknown'); ?>
                            • <strong>Date:</strong> <?php echo date('F j, Y', strtotime($distribution['date'] ?? 'now')); ?>
                        </p>
                    </div>
                    <div class="header-actions">
                        <?php if (($distribution['status'] ?? '') == 'Planning' || ($distribution['status'] ?? '') == 'Scheduled'): ?>
                            <a href="assign_volunteer.php?distribution_id=<?php echo $distribution_id; ?>" class="btn btn-success">
                                <i class="fas fa-users"></i> Assign Volunteers
                            </a>
                        <?php endif; ?>
                        <a href="distribution_main.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Dashboard
                        </a>
                    </div>
                </div>
            </div>

            <!-- Main Information Grid -->
            <div class="grid-2">
                <!-- Distribution Overview Card -->
                <div class="card-3d">
                    <div class="section-header">
                        <h2 style="display: flex; align-items: center; gap: 10px;">
                            <i class="fas fa-chart-bar"></i> Distribution Overview
                        </h2>
                    </div>
                    
                    <div class="overview-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 20px;">
                        <div class="overview-item">
                            <label style="font-weight: 600; color: #7f8c8d; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.5px;">Distribution ID</label>
                            <div class="value" style="font-size: 1.5rem; font-weight: 700; color: #2c3e50;">
                                DIST<?php echo str_pad($distribution['distribution_id'], 3, '0', STR_PAD_LEFT); ?>
                            </div>
                        </div>
                        <div class="overview-item">
                            <label style="font-weight: 600; color: #7f8c8d; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.5px;">Status</label>
                            <div class="value">
                                <?php 
                                $status = $distribution['status'] ?? 'Unknown';
                                $statusClass = 'status-' . strtolower($status);
                                ?>
                                <span class="status-badge <?php echo $statusClass; ?>">
                                    <?php echo ucfirst($status); ?>
                                </span>
                            </div>
                        </div>
                        <div class="overview-item">
                            <label style="font-weight: 600; color: #7f8c8d; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.5px;">Distribution Date</label>
                            <div class="value" style="font-size: 1.2rem; color: #2c3e50; font-weight: 500;">
                                <?php echo date('F j, Y g:i A', strtotime($distribution['date'] ?? 'now')); ?>
                            </div>
                        </div>
                        <div class="overview-item">
                            <label style="font-weight: 600; color: #7f8c8d; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.5px;">Total Families</label>
                            <div class="value" style="font-size: 1.5rem; font-weight: 700; color: #3498db;">
                                <?php echo $total_families; ?> <span style="font-size: 1rem; color: #7f8c8d;">families</span>
                            </div>
                        </div>
                        <div class="overview-item">
                            <label style="font-weight: 600; color: #7f8c8d; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.5px;">Resource Types</label>
                            <div class="value" style="font-size: 1.5rem; font-weight: 700; color: #2ecc71;">
                                <?php echo count($resources_summary); ?> <span style="font-size: 1rem; color: #7f8c8d;">types</span>
                            </div>
                        </div>
                        <div class="overview-item">
                            <label style="font-weight: 600; color: #7f8c8d; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.5px;">Total Resources</label>
                            <div class="value" style="font-size: 1.5rem; font-weight: 700; color: #9b59b6;">
                                <?php echo $total_resources_allocated; ?> <span style="font-size: 1rem; color: #7f8c8d;">units</span>
                            </div>
                        </div>
                    </div>

                    <?php if (!empty($plan_details)): ?>
                    <div style="margin-top: 25px; padding-top: 20px; border-top: 2px solid #ecf0f1;">
                        <h3 style="font-size: 1.2em; margin-bottom: 15px; color: #2c3e50; display: flex; align-items: center; gap: 10px;">
                            <i class="fas fa-clipboard-list"></i> Plan Details
                        </h3>
                        <?php if (isset($plan_details['location'])): ?>
                        <div class="plan-detail-box">
                            <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 5px;">
                                <i class="fas fa-map-marker-alt"></i>
                                <strong style="color: #2c3e50;">Location:</strong>
                            </div>
                            <div style="color: #34495e; padding-left: 30px;">
                                <?php echo htmlspecialchars($plan_details['location']); ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (isset($plan_details['coordinator'])): ?>
                        <div class="plan-detail-box">
                            <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 5px;">
                                <i class="fas fa-user-tie"></i>
                                <strong style="color: #2c3e50;">Coordinator:</strong>
                            </div>
                            <div style="color: #34495e; padding-left: 30px;">
                                <?php echo htmlspecialchars($plan_details['coordinator']); ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-top: 15px;">
                            <?php if (isset($plan_details['duration'])): ?>
                            <div class="plan-detail-box">
                                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 5px;">
                                    <i class="fas fa-clock"></i>
                                    <strong style="color: #2c3e50;">Duration:</strong>
                                </div>
                                <div style="color: #34495e; padding-left: 30px;">
                                    <?php echo htmlspecialchars($plan_details['duration']); ?>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (isset($plan_details['volunteers_needed'])): ?>
                            <div class="plan-detail-box">
                                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 5px;">
                                    <i class="fas fa-users"></i>
                                    <strong style="color: #2c3e50;">Volunteers Needed:</strong>
                                </div>
                                <div style="color: #34495e; padding-left: 30px;">
                                    <?php echo htmlspecialchars($plan_details['volunteers_needed']); ?>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Disaster Information Card -->
                <div class="card-3d">
                    <div class="section-header">
                        <h2 style="display: flex; align-items: center; gap: 10px;">
                            <i class="fas fa-exclamation-triangle"></i> Disaster Information
                        </h2>
                        <?php if ($disaster_info && !empty($disaster_info['Severity_level'])): ?>
                        <?php 
                        $severity = strtolower($disaster_info['Severity_level']);
                        $severityClass = 'severity-' . $severity;
                        ?>
                        <span class="severity-badge <?php echo $severityClass; ?>">
                            <?php echo htmlspecialchars($disaster_info['Severity_level']); ?> Severity
                        </span>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($disaster_info): ?>
                    <div class="info-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
                        <?php if (!empty($disaster_info['Disaster_Name'])): ?>
                        <div class="info-item full-width">
                            <label style="font-weight: 600; color: #7f8c8d; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.5px;">Disaster Name</label>
                            <div class="value" style="font-size: 1.3rem; color: #2c3e50; font-weight: 600;">
                                <?php echo htmlspecialchars($disaster_info['Disaster_Name']); ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($disaster_info['Disaster_Type'])): ?>
                        <div class="info-item">
                            <label style="font-weight: 600; color: #7f8c8d; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.5px;">Type</label>
                            <div class="value" style="font-size: 1.1rem; color: #2c3e50; font-weight: 500;">
                                <?php echo htmlspecialchars($disaster_info['Disaster_Type']); ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($disaster_info['Severity_level'])): ?>
                        <div class="info-item">
                            <label style="font-weight: 600; color: #7f8c8d; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.5px;">Severity</label>
                            <div class="value">
                                <span class="severity-badge severity-<?php echo strtolower($disaster_info['Severity_level']); ?>">
                                    <?php echo htmlspecialchars($disaster_info['Severity_level']); ?>
                                </span>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($disaster_info['Disaster_Date'])): ?>
                        <div class="info-item">
                            <label style="font-weight: 600; color: #7f8c8d; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.5px;">Date</label>
                            <div class="value" style="font-size: 1.1rem; color: #2c3e50; font-weight: 500;">
                                <?php echo date('F j, Y', strtotime($disaster_info['Disaster_Date'])); ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($disaster_info['Location'])): ?>
                        <div class="info-item">
                            <label style="font-weight: 600; color: #7f8c8d; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.5px;">Location</label>
                            <div class="value" style="font-size: 1.1rem; color: #2c3e50; font-weight: 500;">
                                <?php echo htmlspecialchars($disaster_info['Location']); ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($disaster_info['Description'])): ?>
                        <div class="info-item full-width">
                            <label style="font-weight: 600; color: #7f8c8d; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.5px;">Description</label>
                            <div class="value" style="font-size: 1rem; color: #34495e; font-weight: 400; margin-top: 5px;">
                                <?php echo htmlspecialchars($disaster_info['Description']); ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php else: ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong>Disaster information not available</strong>
                        <p>Could not fetch disaster details from API.</p>
                        <p>Disaster ID: <?php echo $distribution['disaster_id'] ?? 'Unknown'; ?></p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Resources Summary Card -->
            <?php if (!empty($resources_summary)): ?>
            <div class="card-3d">
                <div class="section-header">
                    <h2 style="display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-boxes"></i> Resources Allocated
                        <span style="background: #e3f2fd; color: #1976d2; padding: 5px 15px; border-radius: 20px; font-size: 0.9rem; font-weight: 600;">
                            <?php echo count($resources_summary); ?> types • <?php echo $total_resources_allocated; ?> units
                        </span>
                    </h2>
                </div>
                
                <div class="grid-3">
                    <?php foreach ($resources_summary as $resource_id => $resource): ?>
                    <div class="resource-summary-card">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <div style="font-weight: 600; color: #2c3e50; font-size: 1.1em;">
                                    <?php echo htmlspecialchars($resource['name']); ?>
                                </div>
                                <div style="color: #7f8c8d; font-size: 0.9em; margin-top: 5px;">
                                    <span style="background: #f0f4f8; padding: 3px 10px; border-radius: 12px;">
                                        <?php echo htmlspecialchars($resource['type']); ?>
                                    </span>
                                </div>
                            </div>
                            <div style="text-align: right;">
                                <div style="font-size: 1.8em; font-weight: bold; color: #3498db;">
                                    <?php echo $resource['allocated']; ?>
                                </div>
                                <div style="font-size: 0.9em; color: #7f8c8d; margin-top: 5px;">
                                    <?php echo htmlspecialchars($resource['unit']); ?>
                                </div>
                                <?php if ($resource['distributed'] > 0): ?>
                                <div style="font-size: 0.8em; color: #27ae60; margin-top: 3px;">
                                    <i class="fas fa-check"></i> <?php echo $resource['distributed']; ?> distributed
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php if ($resource['distributed'] < $resource['allocated'] && $resource['distributed'] > 0): ?>
                        <div style="margin-top: 10px;">
                            <div style="height: 5px; background: #e9ecef; border-radius: 3px; overflow: hidden;">
                                <div style="height: 100%; width: <?php echo ($resource['distributed'] / $resource['allocated']) * 100; ?>%; background: #27ae60; border-radius: 3px;"></div>
                            </div>
                            <div style="font-size: 0.8em; color: #7f8c8d; margin-top: 5px; text-align: center;">
                                <?php echo round(($resource['distributed'] / $resource['allocated']) * 100, 1); ?>% distributed
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php else: ?>
            <div class="card-3d">
                <div class="alert alert-warning">
                    <h3><i class="fas fa-exclamation-triangle"></i> No Resources Allocated</h3>
                    <p>This distribution plan doesn't have any resources allocated yet.</p>
                    <a href="edit_distribution.php?id=<?php echo $distribution_id; ?>" class="btn btn-warning">
                        <i class="fas fa-edit"></i> Add Resources
                    </a>
                </div>
            </div>
            <?php endif; ?>

            <!-- Families Card -->
            <?php if (!empty($distribution_victims)): ?>
            <div class="card-3d">
                <div class="section-header">
                    <h2 style="display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-users"></i> Families in This Distribution
                        <span style="background: #ffeaa7; color: #856404; padding: 5px 15px; border-radius: 20px; font-size: 0.9rem; font-weight: 600;">
                            <?php echo $total_families; ?> families
                        </span>
                    </h2>
                </div>
                
                <div class="grid-3">
                    <?php foreach ($distribution_victims as $victim): ?>
                    <div class="victim-group">
                        <div class="victim-header">
                            <div style="flex-grow: 1;">
                                <h3>
                                    <?php echo htmlspecialchars($victim['full_name'] ?? 'Victim #' . ($victim['victim_id'] ?? 'Unknown')); ?>
                                    <?php if (!empty($victim['priority'])): ?>
                                    <?php 
                                    $priorityClass = 'badge-' . strtolower($victim['priority']);
                                    ?>
                                    <span class="priority-badge <?php echo $priorityClass; ?>" style="font-size: 0.7em; margin-left: 10px;">
                                        <?php echo $victim['priority']; ?> Priority
                                    </span>
                                    <?php endif; ?>
                                </h3>
                                <div style="font-size: 0.9em; color: #7f8c8d; margin-top: 5px;">
                                    <?php if (!empty($victim['ic_number'])): ?>
                                    <div style="margin-bottom: 3px;">
                                        <strong>IC:</strong> <?php echo htmlspecialchars($victim['ic_number']); ?>
                                    </div>
                                    <?php endif; ?>
                                    <div style="margin-bottom: 3px;">
                                        <strong>Status:</strong> 
                                        <span style="background: #d4edda; color: #155724; padding: 2px 8px; border-radius: 12px; font-size: 0.85em;">
                                            <?php echo $victim['status'] ?? 'Scheduled'; ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div style="margin-top: 10px;">
                            <div style="font-size: 0.9em; color: #5d6d7e;">
                                <?php 
                                $address_parts = [];
                                if (!empty($victim['address'])) $address_parts[] = $victim['address'];
                                if (!empty($victim['city'])) $address_parts[] = $victim['city'];
                                if (!empty($victim['postal_code'])) $address_parts[] = $victim['postal_code'];
                                if (!empty($victim['district'])) $address_parts[] = $victim['district'];
                                
                                if (!empty($address_parts)): ?>
                                <div style="margin-bottom: 5px;">
                                    <strong>Address:</strong> <?php echo htmlspecialchars(implode(', ', $address_parts)); ?>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($victim['phone'])): ?>
                                <div style="margin-bottom: 5px;">
                                    <strong>Phone:</strong> <?php echo htmlspecialchars($victim['phone']); ?>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($victim['email'])): ?>
                                <div style="margin-bottom: 5px;">
                                    <strong>Email:</strong> <?php echo htmlspecialchars($victim['email']); ?>
                                </div>
                                <?php endif; ?>
                                
                                <div style="margin-top: 10px;">
                                    <?php if ($victim['has_baby']): ?>
                                        <span class="special-needs-badge badge-baby">
                                            <i class="fas fa-baby"></i> Has Baby
                                        </span>
                                    <?php endif; ?>
                                    <?php if ($victim['has_elderly']): ?>
                                        <span class="special-needs-badge badge-elderly">
                                            <i class="fas fa-walking-cane"></i> Has Elderly
                                        </span>
                                    <?php endif; ?>
                                    <?php if ($victim['has_disabled']): ?>
                                        <span class="special-needs-badge badge-disabled">
                                            <i class="fas fa-wheelchair"></i> Has Disabled
                                        </span>
                                    <?php endif; ?>
                                    <?php if (!$victim['has_baby'] && !$victim['has_elderly'] && !$victim['has_disabled']): ?>
                                        <span style="color: #bdc3c7; font-size: 0.9em;">No special needs</span>
                                    <?php endif; ?>
                                </div>
                                
                                <?php if (!empty($victim['family_members'])): ?>
                                <div style="margin-top: 8px;">
                                    <strong>Family Members:</strong> <?php echo $victim['family_members']; ?>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($victim['approved_at'])): ?>
                                <div style="margin-top: 8px; font-size: 0.85em; color: #27ae60;">
                                    <i class="fas fa-check-circle"></i> Approved on: <?php echo date('M j, Y', strtotime($victim['approved_at'])); ?>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($victim['api_missing'])): ?>
                                <div class="api-missing">
                                    <i class="fas fa-exclamation-triangle"></i> Victim data not available in API
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php else: ?>
            <div class="card-3d">
                <div class="alert alert-warning">
                    <h3><i class="fas fa-exclamation-triangle"></i> No Families Selected</h3>
                    <p>This distribution plan doesn't have any families assigned yet.</p>
                    <a href="edit_distribution.php?id=<?php echo $distribution_id; ?>" class="btn btn-warning">
                        <i class="fas fa-edit"></i> Add Families
                    </a>
                </div>
            </div>
            <?php endif; ?>

            <!-- Volunteers Card -->
            <?php if (!empty($assigned_volunteers)): ?>
            <div class="card-3d">
                <div class="section-header">
                    <h2 style="display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-hands-helping"></i> Assigned Volunteers
                        <span style="background: #d4edda; color: #155724; padding: 5px 15px; border-radius: 20px; font-size: 0.9rem; font-weight: 600;">
                            <?php echo count($assigned_volunteers); ?> volunteers
                        </span>
                    </h2>
                </div>
                
                <div class="grid-3">
                    <?php foreach ($assigned_volunteers as $volunteer): ?>
                    <div class="victim-group" style="background: linear-gradient(135deg, #f0f8ff 0%, #e6f7ff 100%);">
                        <div style="display: flex; align-items: center; gap: 15px;">
                            <div style="width: 50px; height: 50px; background: #3498db; color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 1.2em;">
                                <?php echo substr($volunteer['volunteer_name'] ?? 'V', 0, 1); ?>
                            </div>
                            <div style="flex-grow: 1;">
                                <div style="font-weight: 600; color: #2c3e50; font-size: 1.1em;">
                                    <?php echo htmlspecialchars($volunteer['volunteer_name'] ?? 'Unknown Volunteer'); ?>
                                </div>
                                <div style="font-size: 0.9em; color: #7f8c8d; margin-top: 3px;">
                                    <span style="background: #e3f2fd; color: #1976d2; padding: 2px 8px; border-radius: 12px; font-size: 0.85em;">
                                        <?php echo htmlspecialchars($volunteer['volunteer_role'] ?? 'Volunteer'); ?>
                                    </span>
                                </div>
                                <?php if (!empty($volunteer['assigned_timestamp'])): ?>
                                <div style="font-size: 0.8em; color: #95a5a6; margin-top: 5px;">
                                    <i class="fas fa-calendar-alt"></i> Assigned: <?php echo date('M j, Y', strtotime($volunteer['assigned_timestamp'])); ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Quick Stats Cards -->
            <div class="grid-4">
                <div class="stat-card" style="border-left: 4px solid #667eea;">
                    <div class="stat-label">Total Families</div>
                    <div class="stat-number"><?php echo $total_families; ?></div>
                    <div style="font-size: 0.9em; color: #7f8c8d;">Families Assisted</div>
                </div>
                <div class="stat-card" style="border-left: 4px solid #2ecc71;">
                    <div class="stat-label">Resource Types</div>
                    <div class="stat-number"><?php echo count($resources_summary); ?></div>
                    <div style="font-size: 0.9em; color: #7f8c8d;">Different Resources</div>
                </div>
                <div class="stat-card" style="border-left: 4px solid #9b59b6;">
                    <div class="stat-label">Total Resources</div>
                    <div class="stat-number"><?php echo $total_resources_allocated; ?></div>
                    <div style="font-size: 0.9em; color: #7f8c8d;">Units Allocated</div>
                </div>
                <div class="stat-card" style="border-left: 4px solid #f39c12;">
                    <div class="stat-label">Volunteers</div>
                    <div class="stat-number"><?php echo count($assigned_volunteers); ?></div>
                    <div style="font-size: 0.9em; color: #7f8c8d;">Assigned</div>
                </div>
            </div>

            <!-- Footer Actions -->
            <div class="card-3d" style="margin-top: 30px; text-align: center;">
                <div style="display: flex; justify-content: center; gap: 15px; flex-wrap: wrap;">
                    <a href="distribution_main.php" class="btn btn-secondary">
                        <i class="fas fa-chart-bar"></i> Back to Dashboard
                    </a>
                    <a href="create_distribution_plan.php" class="btn btn-primary">
                        <i class="fas fa-plus-circle"></i> Create New Distribution
                    </a>
                    <?php if (($distribution['status'] ?? '') == 'Scheduled' && !empty($assigned_volunteers)): ?>
                        <a href="execute_distribution.php?id=<?php echo $distribution_id; ?>" class="btn btn-success">
                            <i class="fas fa-play-circle"></i> Start Distribution
                        </a>
                    <?php endif; ?>
                    <button onclick="window.print()" class="btn btn-warning">
                        <i class="fas fa-print"></i> Print Report
                    </button>
                </div>
            </div>
        </div>
    </main>

    <script>
        // Loading screen
        window.addEventListener('load', function() {
            setTimeout(() => {
                const loadingScreen = document.getElementById('loading-screen');
                if (loadingScreen) {
                    loadingScreen.style.opacity = '0';
                    setTimeout(() => {
                        loadingScreen.style.display = 'none';
                    }, 500);
                }
            }, 800);
        });
    </script>
</body>
</html>