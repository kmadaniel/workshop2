<?php
// ========================================
// VIEW DISTRIBUTION DETAILS - USER FRIENDLY VERSION
// Now shows resource names and volunteer names instead of IDs
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
$VOLUNTEER_API_URL = 'http://10.147.17.30:8000/api_volunteer.php';

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
$volunteerApiResult = fetchDataFromAPI($VOLUNTEER_API_URL);

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

// Get disaster details from API
$disaster_info = null;
if ($disasterApiResult['success'] && isset($disasterApiResult['data']) && is_array($disasterApiResult['data'])) {
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

$victims_stmt = $db->prepare($victims_query);
if (!$victims_stmt) {
    die("Prepare failed for victims: " . $db->error);
}
$victims_stmt->bind_param("i", $distribution_id);
$victims_stmt->execute();
$victims_result = $victims_stmt->get_result();
$distribution_victim_ids = $victims_result->fetch_all(MYSQLI_ASSOC);
$victims_stmt->close();

/* ----------------------------------------
   GET VICTIM DETAILS FROM API
---------------------------------------- */
$distribution_victims = [];
if ($victimApiResult['success'] && isset($victimApiResult['data']) && is_array($victimApiResult['data']) && !empty($distribution_victim_ids)) {
    $apiVictims = $victimApiResult['data'];
    
    foreach ($distribution_victim_ids as $victim_item) {
        $victimId = $victim_item['victim_id'];
        $found = false;
        
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
                
                if ($needsApiResult['success'] && isset($needsApiResult['data']) && is_array($needsApiResult['data'])) {
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
}

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

// Group resources from distribution_resources
foreach ($distribution_resources as $resource) {
    $resource_id = $resource['resource_id'];
    $resource_name = 'Unknown Resource';
    $resource_type = 'General';
    $resource_unit = 'units';
    
    // Try to get resource details from needs API
    if ($needsApiResult['success'] && isset($needsApiResult['data']) && is_array($needsApiResult['data'])) {
        // Check if data is in a nested array structure
        $apiNeeds = $needsApiResult['data'];
        if (isset($apiNeeds['data']) && is_array($apiNeeds['data'])) {
            $apiNeeds = $apiNeeds['data'];
        }
        
        foreach ($apiNeeds as $need) {
            // Check multiple possible field names for resource ID
            $apiResourceId = null;
            $possibleFields = ['resource_id', 'Resource_ID', 'resourceId', 'ResourceID', 'id'];
            
            foreach ($possibleFields as $field) {
                if (isset($need[$field]) && intval($need[$field]) == $resource_id) {
                    $apiResourceId = intval($need[$field]);
                    break;
                }
            }
            
            if ($apiResourceId == $resource_id) {
                // Get resource name from various possible fields
                $resource_name = $need['temp_resource_name'] ?? 
                               $need['resource_name'] ?? 
                               $need['Resource_Name'] ?? 
                               $need['resourceName'] ?? 
                               'Unknown Resource';
                $resource_name = trim($resource_name);
                
                // Determine resource type based on name
                $lowerName = strtolower($resource_name);
                if (strpos($lowerName, 'disinfectant') !== false || 
                    strpos($lowerName, 'sanitizer') !== false ||
                    strpos($lowerName, 'alcohol') !== false ||
                    strpos($lowerName, 'bandage') !== false ||
                    strpos($lowerName, 'gauze') !== false ||
                    strpos($lowerName, 'syringe') !== false ||
                    strpos($lowerName, 'medicine') !== false ||
                    strpos($lowerName, 'first aid') !== false) {
                    $resource_type = 'Medical';
                    $resource_unit = 'units';
                } elseif (strpos($lowerName, 'blanket') !== false ||
                         strpos($lowerName, 'towel') !== false ||
                         strpos($lowerName, 'cloth') !== false ||
                         strpos($lowerName, 'shirt') !== false ||
                         strpos($lowerName, 'pant') !== false) {
                    $resource_type = 'Clothing';
                    $resource_unit = 'pieces';
                } elseif (strpos($lowerName, 'rice') !== false ||
                         strpos($lowerName, 'water') !== false ||
                         strpos($lowerName, 'food') !== false ||
                         strpos($lowerName, 'noodle') !== false) {
                    $resource_type = 'Food';
                    $resource_unit = 'units';
                }
                break;
            }
        }
    }
    
    // If still unknown, create a better name
    if ($resource_name == 'Unknown Resource') {
        $resource_name = 'Resource #' . $resource_id;
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
    $resources_summary[$resource_id]['distributed'] = $resource['quantity_distributed'] ?? 0;
}

/* ----------------------------------------
   GET ASSIGNED VOLUNTEERS
---------------------------------------- */
$assigned_volunteers = [];

// Create a mapping of volunteer ID to volunteer name
$volunteerMap = [];
if ($volunteerApiResult['success'] && isset($volunteerApiResult['data']) && is_array($volunteerApiResult['data'])) {
    $apiVolunteers = $volunteerApiResult['data'];
    
    // Check if data is nested
    if (isset($apiVolunteers['volunteers']) && is_array($apiVolunteers['volunteers'])) {
        $apiVolunteers = $apiVolunteers['volunteers'];
    } elseif (isset($apiVolunteers['data']) && is_array($apiVolunteers['data'])) {
        $apiVolunteers = $apiVolunteers['data'];
    }
    
    foreach ($apiVolunteers as $volunteer) {
        // Extract volunteer ID
        $volunteerId = $volunteer['VolunteerID'] ?? $volunteer['volunteer_id'] ?? $volunteer['id'] ?? null;
        $volunteerName = $volunteer['FullName'] ?? $volunteer['fullName'] ?? $volunteer['name'] ?? null;
        
        if ($volunteerId && $volunteerName) {
            $volunteerMap[intval($volunteerId)] = [
                'name' => $volunteerName,
                'phone' => $volunteer['Phone'] ?? $volunteer['phone'] ?? '',
                'email' => $volunteer['Email'] ?? $volunteer['email'] ?? '',
                'role' => $volunteer['SkillCategory'] ?? $volunteer['skill_category'] ?? $volunteer['Role'] ?? $volunteer['role'] ?? 'Volunteer',
                'ngo' => $volunteer['AssignedNGO'] ?? $volunteer['assignedNGO'] ?? $volunteer['ngo'] ?? 'Various'
            ];
        }
    }
}

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
        
        // Format volunteer data with names from API
        foreach ($assigned_volunteers_raw as $volunteer) {
            $volunteerId = $volunteer['volunteer_id'];
            $volunteerInfo = $volunteerMap[$volunteerId] ?? null;
            
            $assigned_volunteers[] = [
                'volunteer_id' => $volunteerId,
                'volunteer_name' => $volunteerInfo['name'] ?? ('Volunteer #' . $volunteerId),
                'phone' => $volunteerInfo['phone'] ?? '',
                'email' => $volunteerInfo['email'] ?? '',
                'volunteer_role' => $volunteer['role'] ?? ($volunteerInfo['role'] ?? 'Volunteer'),
                'ngo' => $volunteerInfo['ngo'] ?? 'Various',
                'status' => $volunteer['status'] ?? 'Active',
                'assigned_timestamp' => $volunteer['assigned_timestamp'] ?? date('Y-m-d H:i:s')
            ];
        }
    }
}

/* ----------------------------------------
   CALCULATE STATISTICS
---------------------------------------- */
$total_families = count($distribution_victims);
$total_resources_allocated = 0;
if (!empty($distribution_resources)) {
    $total_resources_allocated = array_sum(array_column($distribution_resources, 'quantity_allocated'));
}

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
    <title>Distribution #<?php echo $distribution_id; ?> - Details | Disaster Relief System</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/main.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* ===== BASE STYLES ===== */
        :root {
            --primary: #3498db;
            --secondary: #2c3e50;
            --success: #27ae60;
            --warning: #f39c12;
            --danger: #e74c3c;
            --light: #f8f9fa;
            --dark: #343a40;
            --gray: #6c757d;
            --light-gray: #e9ecef;
            --border-radius: 12px;
            --box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            --transition: all 0.3s ease;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #e4e8f0 100%);
            color: var(--dark);
            line-height: 1.6;
            min-height: 100vh;
        }
        
        .main-content {
            padding: 20px;
            min-height: 100vh;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }
        
        /* ===== HEADER ===== */
        .page-header {
            background: linear-gradient(135deg, var(--primary) 0%, #2980b9 100%);
            color: white;
            padding: 30px;
            border-radius: var(--border-radius);
            margin-bottom: 30px;
            box-shadow: var(--box-shadow);
            position: relative;
            overflow: hidden;
        }
        
        .page-header::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 300px;
            height: 300px;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
            transform: translate(100px, -100px);
        }
        
        .page-header::after {
            content: '';
            position: absolute;
            bottom: -50px;
            left: -50px;
            width: 200px;
            height: 200px;
            background: rgba(255,255,255,0.05);
            border-radius: 50%;
        }
        
        .header-content {
            position: relative;
            z-index: 2;
        }
        
        .header-content h1 {
            font-size: 2.2rem;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .header-content h1 i {
            background: rgba(255,255,255,0.2);
            padding: 12px;
            border-radius: 12px;
            font-size: 1.5rem;
        }
        
        .header-subtitle {
            font-size: 1.1rem;
            opacity: 0.9;
            margin-bottom: 20px;
        }
        
        .header-actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }
        
        /* ===== CARDS ===== */
        .card {
            background: white;
            border-radius: var(--border-radius);
            padding: 25px;
            box-shadow: var(--box-shadow);
            margin-bottom: 25px;
            border: 1px solid var(--light-gray);
            transition: var(--transition);
        }
        
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--light-gray);
        }
        
        .card-header h2 {
            font-size: 1.5rem;
            color: var(--secondary);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .card-header h2 i {
            color: var(--primary);
        }
        
        /* ===== STATUS BADGES ===== */
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
        
        .status-planned { background: var(--warning); color: white; }
        .status-scheduled { background: #3498db; color: white; }
        .status-active { background: var(--success); color: white; }
        .status-completed { background: #9b59b6; color: white; }
        .status-cancelled { background: var(--danger); color: white; }
        
        .severity-badge {
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        
        .severity-low { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .severity-medium { background: #fff3cd; color: #856404; border: 1px solid #ffeaa7; }
        .severity-high { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        
        /* ===== BUTTONS ===== */
        .btn {
            padding: 12px 24px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.95rem;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            transition: var(--transition);
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        
        .btn-primary {
            background: var(--primary);
            color: white;
        }
        
        .btn-secondary {
            background: var(--gray);
            color: white;
        }
        
        .btn-success {
            background: var(--success);
            color: white;
        }
        
        .btn-warning {
            background: var(--warning);
            color: white;
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
        
        /* ===== STATS GRID ===== */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 30px 0;
        }
        
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: var(--border-radius);
            text-align: center;
            box-shadow: var(--box-shadow);
            transition: var(--transition);
            border-top: 4px solid var(--primary);
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-card i {
            font-size: 2.5rem;
            color: var(--primary);
            margin-bottom: 15px;
        }
        
        .stat-number {
            font-size: 2.8rem;
            font-weight: 700;
            color: var(--secondary);
            line-height: 1;
            margin: 10px 0;
        }
        
        .stat-label {
            color: var(--gray);
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        /* ===== INFO GRIDS ===== */
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .info-item {
            background: var(--light);
            padding: 20px;
            border-radius: 10px;
            border-left: 4px solid var(--primary);
        }
        
        .info-item label {
            display: block;
            color: var(--gray);
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }
        
        .info-item .value {
            font-size: 1.1rem;
            color: var(--secondary);
            font-weight: 500;
        }
        
        /* ===== RESOURCE CARDS ===== */
        .resource-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
        }
        
        .resource-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            border: 1px solid var(--light-gray);
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }
        
        .resource-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.1);
            border-color: var(--primary);
        }
        
        .resource-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, var(--primary), #9b59b6);
        }
        
        .resource-name {
            font-weight: 600;
            color: var(--secondary);
            font-size: 1.1rem;
            margin-bottom: 5px;
        }
        
        .resource-meta {
            color: var(--gray);
            font-size: 0.9rem;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .resource-type {
            background: var(--light-gray);
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
        }
        
        .resource-stats {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            margin-top: 15px;
        }
        
        .resource-amount {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--primary);
        }
        
        .resource-unit {
            font-size: 0.9rem;
            color: var(--gray);
        }
        
        /* ===== VICTIM CARDS ===== */
        .victim-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 20px;
        }
        
        .victim-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            border: 1px solid var(--light-gray);
            transition: var(--transition);
        }
        
        .victim-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.1);
        }
        
        .victim-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--light-gray);
        }
        
        .victim-avatar {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, var(--primary), #9b59b6);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 1.5rem;
        }
        
        .victim-info h3 {
            font-size: 1.1rem;
            color: var(--secondary);
            margin-bottom: 5px;
        }
        
        .priority-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .priority-high { background: #f8d7da; color: #721c24; }
        .priority-medium { background: #fff3cd; color: #856404; }
        .priority-low { background: #d1ecf1; color: #0c5460; }
        
        .victim-details {
            font-size: 0.9rem;
            color: var(--gray);
        }
        
        .victim-details p {
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .victim-details i {
            width: 20px;
            color: var(--primary);
        }
        
        .special-needs {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-top: 15px;
        }
        
        .needs-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .needs-baby { background: #fff3cd; color: #856404; }
        .needs-elderly { background: #d1ecf1; color: #0c5460; }
        .needs-disabled { background: #f8d7da; color: #721c24; }
        
        /* ===== VOLUNTEER CARDS ===== */
        .volunteer-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
        }
        
        .volunteer-card {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 12px;
            padding: 20px;
            border: 1px solid var(--light-gray);
            transition: var(--transition);
        }
        
        .volunteer-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.1);
        }
        
        .volunteer-avatar {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, var(--primary), #3498db);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 1.2rem;
            margin-right: 15px;
        }
        
        .volunteer-role {
            background: var(--primary);
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        /* ===== PROGRESS BARS ===== */
        .progress-container {
            margin: 20px 0;
        }
        
        .progress-label {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-size: 0.9rem;
            color: var(--gray);
        }
        
        .progress-bar {
            height: 10px;
            background: var(--light-gray);
            border-radius: 5px;
            overflow: hidden;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--primary), #9b59b6);
            border-radius: 5px;
            transition: width 0.5s ease;
        }
        
        /* ===== ALERTS ===== */
        .alert {
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 15px;
            border-left: 4px solid;
        }
        
        .alert i {
            font-size: 1.5rem;
        }
        
        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
            border-left-color: #3498db;
        }
        
        .alert-warning {
            background: #fff3cd;
            color: #856404;
            border-left-color: #ffc107;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border-left-color: #28a745;
        }
        
        /* ===== API STATUS ===== */
        .api-status-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }
        
        .api-status {
            padding: 15px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            gap: 12px;
            background: white;
            box-shadow: var(--box-shadow);
        }
        
        .api-status i {
            font-size: 1.5rem;
        }
        
        .api-connected i { color: var(--success); }
        .api-disconnected i { color: var(--danger); }
        
        .api-status-content h4 {
            font-size: 0.9rem;
            margin-bottom: 5px;
        }
        
        .api-status-content p {
            font-size: 0.8rem;
            color: var(--gray);
        }
        
        /* ===== EMPTY STATES ===== */
        .empty-state {
            text-align: center;
            padding: 50px 20px;
            color: var(--gray);
        }
        
        .empty-state i {
            font-size: 4rem;
            color: var(--light-gray);
            margin-bottom: 20px;
        }
        
        .empty-state h3 {
            font-size: 1.3rem;
            margin-bottom: 10px;
            color: var(--secondary);
        }
        
        /* ===== RESPONSIVE ===== */
        @media (max-width: 768px) {
            .container {
                padding: 15px;
            }
            
            .page-header {
                padding: 20px;
            }
            
            .header-content h1 {
                font-size: 1.8rem;
            }
            
            .stats-grid,
            .info-grid,
            .resource-grid,
            .victim-grid,
            .volunteer-grid {
                grid-template-columns: 1fr;
            }
            
            .header-actions {
                flex-direction: column;
                align-items: stretch;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
        }
        
        /* ===== ANIMATIONS ===== */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .fade-in {
            animation: fadeIn 0.5s ease-out;
        }
        
        /* ===== LOADING SCREEN ===== */
        .loading-screen {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            transition: opacity 0.5s ease;
        }
        
        .loading-content {
            text-align: center;
        }
        
        .loading-spinner {
            width: 60px;
            height: 60px;
            border: 4px solid var(--light-gray);
            border-top-color: var(--primary);
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 20px;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <!-- Loading Screen -->
    <div id="loading-screen" class="loading-screen">
        <div class="loading-content">
            <div class="loading-spinner"></div>
            <h3>Loading Distribution Details</h3>
            <p>Please wait while we gather all the information...</p>
        </div>
    </div>

    <!-- Main Content Area -->
    <main class="main-content" id="mainContent">
        <div class="container">
            <!-- API Status -->
            <div class="api-status-container">
                <div class="api-status <?php echo $disasterApiResult['success'] ? 'api-connected' : 'api-disconnected'; ?>">
                    <i class="fas <?php echo $disasterApiResult['success'] ? 'fa-check-circle' : 'fa-times-circle'; ?>"></i>
                    <div class="api-status-content">
                        <h4>Disaster API</h4>
                        <p><?php echo $disasterApiResult['success'] ? 'Connected' : 'Disconnected'; ?></p>
                    </div>
                </div>
                
                <div class="api-status <?php echo $victimApiResult['success'] ? 'api-connected' : 'api-disconnected'; ?>">
                    <i class="fas <?php echo $victimApiResult['success'] ? 'fa-check-circle' : 'fa-times-circle'; ?>"></i>
                    <div class="api-status-content">
                        <h4>Victim API</h4>
                        <p><?php echo $victimApiResult['success'] ? 'Connected' : 'Disconnected'; ?></p>
                    </div>
                </div>
                
                <div class="api-status <?php echo $needsApiResult['success'] ? 'api-connected' : 'api-disconnected'; ?>">
                    <i class="fas <?php echo $needsApiResult['success'] ? 'fa-check-circle' : 'fa-times-circle'; ?>"></i>
                    <div class="api-status-content">
                        <h4>Needs API</h4>
                        <p><?php echo $needsApiResult['success'] ? 'Connected' : 'Disconnected'; ?></p>
                    </div>
                </div>
                
                <div class="api-status <?php echo $volunteerApiResult['success'] ? 'api-connected' : 'api-disconnected'; ?>">
                    <i class="fas <?php echo $volunteerApiResult['success'] ? 'fa-check-circle' : 'fa-times-circle'; ?>"></i>
                    <div class="api-status-content">
                        <h4>Volunteer API</h4>
                        <p><?php echo $volunteerApiResult['success'] ? 'Connected' : 'Disconnected'; ?></p>
                    </div>
                </div>
            </div>

            <!-- Page Header -->
            <div class="page-header fade-in">
                <div class="header-content">
                    <h1>
                        <i class="fas fa-box-open"></i>
                        Distribution Plan Details
                    </h1>
                    <p class="header-subtitle">
                        <strong>ID:</strong> DIST<?php echo str_pad($distribution_id, 3, '0', STR_PAD_LEFT); ?>
                        • <strong>Status:</strong> <?php echo ucfirst($distribution['status'] ?? 'Unknown'); ?>
                        • <strong>Date:</strong> <?php echo date('F j, Y', strtotime($distribution['date'] ?? 'now')); ?>
                    </p>
                    
                    <div class="header-actions">
                        <?php if (($distribution['status'] ?? '') == 'Planning' || ($distribution['status'] ?? '') == 'Scheduled'): ?>
                            <a href="assign_volunteer.php?distribution_id=<?php echo $distribution_id; ?>" class="btn btn-success">
                                <i class="fas fa-users"></i> Assign Volunteers
                            </a>
                        <?php endif; ?>
                        <a href="distribution_main.php" class="btn btn-outline">
                            <i class="fas fa-arrow-left"></i> Back to Dashboard
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

            <!-- Quick Stats -->
            <div class="stats-grid fade-in">
                <div class="stat-card">
                    <i class="fas fa-users"></i>
                    <div class="stat-number"><?php echo $total_families; ?></div>
                    <div class="stat-label">Families Assisted</div>
                </div>
                
                <div class="stat-card">
                    <i class="fas fa-boxes"></i>
                    <div class="stat-number"><?php echo count($resources_summary); ?></div>
                    <div class="stat-label">Resource Types</div>
                </div>
                
                <div class="stat-card">
                    <i class="fas fa-cubes"></i>
                    <div class="stat-number"><?php echo $total_resources_allocated; ?></div>
                    <div class="stat-label">Total Resources</div>
                </div>
                
                <div class="stat-card">
                    <i class="fas fa-hands-helping"></i>
                    <div class="stat-number"><?php echo count($assigned_volunteers); ?></div>
                    <div class="stat-label">Volunteers</div>
                </div>
            </div>

            <!-- Main Information Grid -->
            <div class="info-grid fade-in">
                <!-- Distribution Overview -->
                <div class="card">
                    <div class="card-header">
                        <h2><i class="fas fa-info-circle"></i> Distribution Overview</h2>
                        <span class="status-badge status-<?php echo strtolower($distribution['status'] ?? 'unknown'); ?>">
                            <?php echo ucfirst($distribution['status'] ?? 'Unknown'); ?>
                        </span>
                    </div>
                    
                    <div class="info-grid">
                        <div class="info-item">
                            <label>Distribution ID</label>
                            <div class="value">DIST<?php echo str_pad($distribution['distribution_id'], 3, '0', STR_PAD_LEFT); ?></div>
                        </div>
                        
                        <div class="info-item">
                            <label>Distribution Date</label>
                            <div class="value"><?php echo date('F j, Y g:i A', strtotime($distribution['date'] ?? 'now')); ?></div>
                        </div>
                        
                        <?php if (!empty($plan_details['location'])): ?>
                        <div class="info-item">
                            <label>Location</label>
                            <div class="value"><?php echo htmlspecialchars($plan_details['location']); ?></div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($plan_details['coordinator'])): ?>
                        <div class="info-item">
                            <label>Coordinator</label>
                            <div class="value"><?php echo htmlspecialchars($plan_details['coordinator']); ?></div>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <?php if (!empty($plan_details['duration']) || !empty($plan_details['volunteers_needed'])): ?>
                    <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid var(--light-gray);">
                        <div class="info-grid">
                            <?php if (!empty($plan_details['duration'])): ?>
                            <div class="info-item">
                                <label><i class="fas fa-clock"></i> Estimated Duration</label>
                                <div class="value"><?php echo htmlspecialchars($plan_details['duration']); ?></div>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($plan_details['volunteers_needed'])): ?>
                            <div class="info-item">
                                <label><i class="fas fa-users"></i> Volunteers Needed</label>
                                <div class="value"><?php echo htmlspecialchars($plan_details['volunteers_needed']); ?></div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Disaster Information -->
                <div class="card">
                    <div class="card-header">
                        <h2><i class="fas fa-exclamation-triangle"></i> Disaster Information</h2>
                        <?php if ($disaster_info && !empty($disaster_info['Severity_level'])): ?>
                        <?php 
                        $severity = strtolower($disaster_info['Severity_level']);
                        ?>
                        <span class="severity-badge severity-<?php echo $severity; ?>">
                            <?php echo htmlspecialchars($disaster_info['Severity_level']); ?>
                        </span>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($disaster_info): ?>
                    <div class="info-grid">
                        <div class="info-item" style="grid-column: span 2;">
                            <label>Disaster Name</label>
                            <div class="value" style="font-size: 1.2rem; font-weight: 600;">
                                <?php echo htmlspecialchars($disaster_info['Disaster_Name']); ?>
                            </div>
                        </div>
                        
                        <div class="info-item">
                            <label>Type</label>
                            <div class="value"><?php echo htmlspecialchars($disaster_info['Disaster_Type']); ?></div>
                        </div>
                        
                        <div class="info-item">
                            <label>Date</label>
                            <div class="value"><?php echo date('F j, Y', strtotime($disaster_info['Disaster_Date'])); ?></div>
                        </div>
                        
                        <div class="info-item">
                            <label>Location</label>
                            <div class="value"><?php echo htmlspecialchars($disaster_info['Location']); ?></div>
                        </div>
                        
                        <?php if (!empty($disaster_info['Description'])): ?>
                        <div class="info-item" style="grid-column: span 2;">
                            <label>Description</label>
                            <div class="value"><?php echo htmlspecialchars($disaster_info['Description']); ?></div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php else: ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        <div>
                            <h4>Disaster Information Unavailable</h4>
                            <p>Could not fetch disaster details from the API. The disaster might have been removed or the API is temporarily unavailable.</p>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Resources Section -->
            <div class="card fade-in">
                <div class="card-header">
                    <h2><i class="fas fa-boxes"></i> Allocated Resources</h2>
                    <span style="background: var(--light-gray); padding: 6px 16px; border-radius: 20px; font-size: 0.9rem; font-weight: 600;">
                        <?php echo count($resources_summary); ?> types • <?php echo $total_resources_allocated; ?> units
                    </span>
                </div>
                
                <?php if (!empty($resources_summary)): ?>
                <div class="resource-grid">
                    <?php foreach ($resources_summary as $resource_id => $resource): 
                        $progress = $resource['allocated'] > 0 ? ($resource['distributed'] / $resource['allocated']) * 100 : 0;
                    ?>
                    <div class="resource-card">
                        <div class="resource-name"><?php echo htmlspecialchars($resource['name']); ?></div>
                        <div class="resource-meta">
                            <span class="resource-type"><?php echo htmlspecialchars($resource['type']); ?></span>
                            <span class="resource-unit"><?php echo htmlspecialchars($resource['unit']); ?></span>
                        </div>
                        
                        <div class="progress-container">
                            <div class="progress-label">
                                <span>Allocated: <?php echo $resource['allocated']; ?></span>
                                <span>Distributed: <?php echo $resource['distributed']; ?></span>
                            </div>
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: <?php echo $progress; ?>%"></div>
                            </div>
                        </div>
                        
                        <div class="resource-stats">
                            <div>
                                <div class="resource-amount"><?php echo $resource['allocated']; ?></div>
                                <div class="resource-unit">total allocated</div>
                            </div>
                            <?php if ($resource['distributed'] > 0): ?>
                            <div style="text-align: right;">
                                <div style="color: var(--success); font-weight: 600; font-size: 1.1rem;">
                                    <?php echo $resource['distributed']; ?> distributed
                                </div>
                                <div style="color: var(--gray); font-size: 0.8rem;">
                                    <?php echo round($progress, 1); ?>% complete
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-box-open"></i>
                    <h3>No Resources Allocated</h3>
                    <p>This distribution plan doesn't have any resources allocated yet.</p>
                    <a href="edit_distribution.php?id=<?php echo $distribution_id; ?>" class="btn btn-primary" style="margin-top: 20px;">
                        <i class="fas fa-edit"></i> Add Resources
                    </a>
                </div>
                <?php endif; ?>
            </div>

            <!-- Families Section -->
            <div class="card fade-in">
                <div class="card-header">
                    <h2><i class="fas fa-users"></i> Beneficiary Families</h2>
                    <span style="background: var(--light-gray); padding: 6px 16px; border-radius: 20px; font-size: 0.9rem; font-weight: 600;">
                        <?php echo $total_families; ?> families
                    </span>
                </div>
                
                <?php if (!empty($distribution_victims)): ?>
                <div class="victim-grid">
                    <?php foreach ($distribution_victims as $victim): 
                        $first_letter = substr($victim['full_name'] ?? 'V', 0, 1);
                        $priority = strtolower($victim['priority'] ?? 'medium');
                    ?>
                    <div class="victim-card">
                        <div class="victim-header">
                            <div class="victim-avatar"><?php echo $first_letter; ?></div>
                            <div class="victim-info">
                                <h3><?php echo htmlspecialchars($victim['full_name'] ?? 'Victim #' . ($victim['victim_id'] ?? 'Unknown')); ?></h3>
                                <div>
                                    <span class="priority-badge priority-<?php echo $priority; ?>">
                                        <?php echo $victim['priority'] ?? 'Medium'; ?> Priority
                                    </span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="victim-details">
                            <?php if (!empty($victim['ic_number'])): ?>
                            <p><i class="fas fa-id-card"></i> IC: <?php echo htmlspecialchars($victim['ic_number']); ?></p>
                            <?php endif; ?>
                            
                            <?php if (!empty($victim['district'])): ?>
                            <p><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($victim['district']); ?></p>
                            <?php endif; ?>
                            
                            <?php if (!empty($victim['phone'])): ?>
                            <p><i class="fas fa-phone"></i> <?php echo htmlspecialchars($victim['phone']); ?></p>
                            <?php endif; ?>
                            
                            <p><i class="fas fa-home"></i> Family Members: <?php echo $victim['family_members']; ?></p>
                            
                            <?php if ($victim['has_baby'] || $victim['has_elderly'] || $victim['has_disabled']): ?>
                            <div class="special-needs">
                                <?php if ($victim['has_baby']): ?>
                                <span class="needs-badge needs-baby"><i class="fas fa-baby"></i> Has Baby</span>
                                <?php endif; ?>
                                <?php if ($victim['has_elderly']): ?>
                                <span class="needs-badge needs-elderly"><i class="fas fa-walking-cane"></i> Has Elderly</span>
                                <?php endif; ?>
                                <?php if ($victim['has_disabled']): ?>
                                <span class="needs-badge needs-disabled"><i class="fas fa-wheelchair"></i> Has Disabled</span>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($victim['approved_at'])): ?>
                            <p style="margin-top: 10px; color: var(--success); font-size: 0.85rem;">
                                <i class="fas fa-check-circle"></i> Approved on <?php echo date('M j, Y', strtotime($victim['approved_at'])); ?>
                            </p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-users-slash"></i>
                    <h3>No Families Selected</h3>
                    <p>This distribution plan doesn't have any families assigned yet.</p>
                    <a href="edit_distribution.php?id=<?php echo $distribution_id; ?>" class="btn btn-primary" style="margin-top: 20px;">
                        <i class="fas fa-edit"></i> Add Families
                    </a>
                </div>
                <?php endif; ?>
            </div>

            <!-- Volunteers Section -->
            <?php if (!empty($assigned_volunteers)): ?>
            <div class="card fade-in">
                <div class="card-header">
                    <h2><i class="fas fa-hands-helping"></i> Assigned Volunteers</h2>
                    <span style="background: var(--light-gray); padding: 6px 16px; border-radius: 20px; font-size: 0.9rem; font-weight: 600;">
                        <?php echo count($assigned_volunteers); ?> volunteers
                    </span>
                </div>
                
                <div class="volunteer-grid">
                    <?php foreach ($assigned_volunteers as $volunteer): 
                        $first_letter = substr($volunteer['volunteer_name'] ?? 'V', 0, 1);
                    ?>
                    <div class="volunteer-card">
                        <div style="display: flex; align-items: center; margin-bottom: 15px;">
                            <div class="volunteer-avatar"><?php echo $first_letter; ?></div>
                            <div style="flex-grow: 1;">
                                <h3 style="margin-bottom: 5px;"><?php echo htmlspecialchars($volunteer['volunteer_name']); ?></h3>
                                <span class="volunteer-role"><?php echo htmlspecialchars($volunteer['volunteer_role']); ?></span>
                            </div>
                        </div>
                        
                        <div style="font-size: 0.9rem; color: var(--gray);">
                            <?php if (!empty($volunteer['phone'])): ?>
                            <p style="margin-bottom: 5px;"><i class="fas fa-phone"></i> <?php echo htmlspecialchars($volunteer['phone']); ?></p>
                            <?php endif; ?>
                            
                            <?php if (!empty($volunteer['ngo']) && $volunteer['ngo'] !== 'Various'): ?>
                            <p style="margin-bottom: 5px;"><i class="fas fa-hands-helping"></i> <?php echo htmlspecialchars($volunteer['ngo']); ?></p>
                            <?php endif; ?>
                            
                            <?php if (!empty($volunteer['assigned_timestamp'])): ?>
                            <p style="font-size: 0.8rem; margin-top: 10px;">
                                <i class="fas fa-calendar-alt"></i> Assigned: <?php echo date('M j, Y', strtotime($volunteer['assigned_timestamp'])); ?>
                            </p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Footer Actions -->
            <div class="card fade-in" style="text-align: center; margin-top: 30px;">
                <h3 style="margin-bottom: 20px; color: var(--secondary);">Need to make changes?</h3>
                <div style="display: flex; justify-content: center; gap: 15px; flex-wrap: wrap;">
                    <a href="edit_distribution.php?id=<?php echo $distribution_id; ?>" class="btn btn-outline">
                        <i class="fas fa-edit"></i> Edit Distribution
                    </a>
                    <a href="distribution_main.php" class="btn btn-secondary">
                        <i class="fas fa-chart-bar"></i> View All Distributions
                    </a>
                    <a href="create_distribution_plan.php" class="btn btn-primary">
                        <i class="fas fa-plus-circle"></i> Create New Plan
                    </a>
                </div>
                
                <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid var(--light-gray);">
                    <p style="color: var(--gray); font-size: 0.9rem;">
                        <i class="fas fa-info-circle"></i> Distribution ID: DIST<?php echo str_pad($distribution_id, 3, '0', STR_PAD_LEFT); ?> 
                        • Created: <?php echo date('F j, Y', strtotime($distribution['date'] ?? 'now')); ?>
                        <?php if (!empty($distribution['last_updated'])): ?>
                        • Last Updated: <?php echo date('F j, Y', strtotime($distribution['last_updated'])); ?>
                        <?php endif; ?>
                    </p>
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
            }, 500);
        });

        // Add smooth scrolling for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const targetId = this.getAttribute('href');
                if(targetId === '#') return;
                
                const targetElement = document.querySelector(targetId);
                if(targetElement) {
                    window.scrollTo({
                        top: targetElement.offsetTop - 100,
                        behavior: 'smooth'
                    });
                }
            });
        });

        // Add hover effects for cards
        document.querySelectorAll('.card, .stat-card, .resource-card, .victim-card, .volunteer-card').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transition = 'all 0.3s ease';
            });
        });

        // Print functionality
        function printPage() {
            const originalContent = document.body.innerHTML;
            const printContent = document.querySelector('.container').innerHTML;
            
            document.body.innerHTML = `
                <html>
                    <head>
                        <title>Distribution Report - DIST<?php echo str_pad($distribution_id, 3, '0', STR_PAD_LEFT); ?></title>
                        <style>
                            body { font-family: Arial, sans-serif; padding: 20px; }
                            .page-header { background: #f0f0f0; padding: 20px; margin-bottom: 20px; }
                            .card { border: 1px solid #ddd; padding: 15px; margin-bottom: 20px; }
                            .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin: 20px 0; }
                            @media print {
                                .btn { display: none; }
                                .no-print { display: none; }
                            }
                        </style>
                    </head>
                    <body>
                        <div class="page-header">
                            <h1>Distribution Report - DIST<?php echo str_pad($distribution_id, 3, '0', STR_PAD_LEFT); ?></h1>
                            <p>Generated on: <?php echo date('F j, Y g:i A'); ?></p>
                        </div>
                        ${printContent}
                    </body>
                </html>
            `;
            
            window.print();
            document.body.innerHTML = originalContent;
            location.reload();
        }
    </script>
</body>
</html>