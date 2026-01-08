<?php
// ========================================
// MANAGE NEEDS - APPROVAL SYSTEM WITH API INTEGRATION
// manage_needs.php - UPDATED TO SEND TO YOUR API
// ========================================

require_once 'config.php';

// Increase execution time for API calls
set_time_limit(60);

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

// YOUR API to send status updates to your friend
$YOUR_API_URL = 'http://10.147.17.154:8000/distribution_module/api_victim_approve.php';

// Function to send status update to YOUR API
function sendStatusToYourAPI($victim_id, $disaster_id, $status) {
    global $YOUR_API_URL;
    
    // Prepare the data to send
    $data = [
        'victim_id' => $victim_id,
        'disaster_id' => $disaster_id,
        'approval_status' => $status,
        'approved_at' => date('Y-m-d H:i:s')
    ];
    
    // Initialize cURL
    $ch = curl_init();
    
    // Set cURL options
    curl_setopt($ch, CURLOPT_URL, $YOUR_API_URL);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    // Execute and get response
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    
    curl_close($ch);
    
    // Log the attempt
    error_log("Sending to YOUR API: Victim $victim_id, Disaster $disaster_id, Status $status");
    error_log("API Response Code: $httpCode");
    error_log("API Response: $response");
    
    return [
        'success' => ($httpCode >= 200 && $httpCode < 300),
        'http_code' => $httpCode,
        'response' => $response,
        'error' => $error
    ];
}

// Create a table to track processed disasters if it doesn't exist
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
   FETCH ALL DATA FROM FRIEND'S API
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

$disasterApiResult = fetchDataFromAPI($DISASTER_API_URL);
$victimApiResult = fetchDataFromAPI($VICTIM_API_URL);
$needsApiResult = fetchDataFromAPI($NEEDS_API_URL);

/* ----------------------------------------
   MARK DISASTER AS PROCESSED AFTER APPROVALS
---------------------------------------- */
function markDisasterAsProcessed($db, $disaster_id, $notes = '') {
    try {
        $query = "INSERT INTO processed_disasters (disaster_id, notes, processed_date) 
                  VALUES (?, ?, NOW())
                  ON DUPLICATE KEY UPDATE 
                  processed_date = NOW(), 
                  notes = COALESCE(?, notes)";
        
        $stmt = $db->prepare($query);
        $stmt->bind_param("iss", $disaster_id, $notes, $notes);
        $result = $stmt->execute();
        $stmt->close();
        
        return $result;
    } catch (Exception $e) {
        error_log("Error marking disaster as processed: " . $e->getMessage());
        return false;
    }
}

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
        error_log("Error checking if disaster is processed: " . $e->getMessage());
        return false;
    }
}

/* ----------------------------------------
   HANDLE STATUS UPDATES - MUST BE AT THE TOP!
---------------------------------------- */
// Handle single status update via GET - MUST BE BEFORE ANY OUTPUT
if (isset($_GET['update_status']) && isset($_GET['victim_id']) && isset($_GET['new_status']) && isset($_GET['disaster_id'])) {
    $victim_id = intval($_GET['victim_id']);
    $new_status = $_GET['new_status'];
    $selected_disaster_id = intval($_GET['disaster_id']);
    $selected_status_filter = $_GET['status_filter'] ?? 'Pending';
    
    try {
        $check_query = "SELECT approval_id FROM victim_approvals WHERE victim_id = ? AND disaster_id = ?";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->bind_param("ii", $victim_id, $selected_disaster_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $update_query = "UPDATE victim_approvals SET approval_status = ?, approved_at = NOW() WHERE victim_id = ? AND disaster_id = ?";
            $stmt = $db->prepare($update_query);
            $stmt->bind_param("sii", $new_status, $victim_id, $selected_disaster_id);
        } else {
            $update_query = "INSERT INTO victim_approvals (victim_id, disaster_id, approval_status, approved_at) VALUES (?, ?, ?, NOW())";
            $stmt = $db->prepare($update_query);
            $stmt->bind_param("iis", $victim_id, $selected_disaster_id, $new_status);
        }
        
        if ($stmt->execute()) {
            // SEND STATUS UPDATE TO YOUR API (for your friend to get)
            $apiResult = sendStatusToYourAPI($victim_id, $selected_disaster_id, $new_status);
            
            if ($apiResult['success']) {
                $success = "✅ Victim #$victim_id status updated to $new_status and sent to your API!";
            } else {
                $success = "✅ Victim #$victim_id status updated to $new_status (but failed to send to API: " . $apiResult['error'] . ")";
            }
            
            // Check if all victims are approved and mark disaster as processed
            checkAndMarkDisasterAsProcessed($db, $selected_disaster_id);
        } else {
            $error = "Failed to update victim status: " . $stmt->error;
        }
        
        $check_stmt->close();
        $stmt->close();
        
        // Redirect back with success message
        header("Location: ?disaster_id=$selected_disaster_id&status_filter=$selected_status_filter&success=" . urlencode($success));
        exit();
        
    } catch (Exception $e) {
        $error = "Error updating victim: " . $e->getMessage();
    }
}

/* ----------------------------------------
   CHECK AND MARK DISASTER AS PROCESSED IF ALL VICTIMS APPROVED
---------------------------------------- */
function checkAndMarkDisasterAsProcessed($db, $disaster_id) {
    try {
        // Get total victims for this disaster from API
        global $victimApiResult;
        $total_victims = 0;
        
        if ($victimApiResult['success'] && is_array($victimApiResult['data'])) {
            foreach ($victimApiResult['data'] as $victim) {
                $victimDisasterId = $victim['disaster_id'] ?? 
                                   $victim['Disaster_ID'] ?? 
                                   $victim['disasterId'] ?? 
                                   $victim['DisasterID'] ?? 0;
                if (intval($victimDisasterId) == $disaster_id) {
                    $total_victims++;
                }
            }
        }
        
        // Get approved count from local database (only those not yet distributed)
        $approved_query = "
            SELECT COUNT(DISTINCT va.victim_id) as approved_count 
            FROM victim_approvals va
            LEFT JOIN distribution_items di ON va.victim_id = di.victim_id 
                AND di.status IN ('Scheduled', 'Dispatched', 'Delivered')
            WHERE va.disaster_id = ? 
                AND va.approval_status = 'Approved'
                AND di.victim_id IS NULL
        ";
        
        $stmt = $db->prepare($approved_query);
        $stmt->bind_param("i", $disaster_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $approved_not_distributed = $row['approved_count'];
        $stmt->close();
        
        // Get total approved (including distributed)
        $total_approved_query = "SELECT COUNT(*) as total_approved FROM victim_approvals 
                                WHERE disaster_id = ? AND approval_status = 'Approved'";
        $stmt2 = $db->prepare($total_approved_query);
        $stmt2->bind_param("i", $disaster_id);
        $stmt2->execute();
        $result2 = $stmt2->get_result();
        $row2 = $result2->fetch_assoc();
        $total_approved = $row2['total_approved'];
        $stmt2->close();
        
        // If all victims are approved AND all approved victims have been distributed, mark as processed
        if ($total_victims > 0 && $total_approved >= $total_victims && $approved_not_distributed == 0) {
            markDisasterAsProcessed($db, $disaster_id, "All $total_approved victims approved and distributed. Disaster processed.");
            return true;
        }
        
        return false;
    } catch (Exception $e) {
        error_log("Error checking disaster status: " . $e->getMessage());
        return false;
    }
}

// Handle bulk actions (POST method) - MUST BE BEFORE ANY OUTPUT
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action']) && isset($_POST['selected_victims'])) {
    $action = $_POST['bulk_action'];
    $selected_victims = $_POST['selected_victims'];
    $selected_disaster_id = intval($_POST['disaster_id'] ?? 0);
    $selected_status_filter = $_POST['status_filter'] ?? 'Pending';
    
    if (!empty($selected_victims) && $selected_disaster_id > 0) {
        $new_status = ($action === 'approve') ? 'Approved' : 'Rejected';
        $successCount = 0;
        $apiSuccessCount = 0;
        $apiFailCount = 0;
        
        try {
            // Update approval status in local database
            foreach ($selected_victims as $victim_id) {
                $victim_id = intval($victim_id);
                
                // Check if approval record exists
                $check_query = "SELECT approval_id FROM victim_approvals WHERE victim_id = ? AND disaster_id = ?";
                $check_stmt = $db->prepare($check_query);
                $check_stmt->bind_param("ii", $victim_id, $selected_disaster_id);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();
                
                if ($check_result->num_rows > 0) {
                    // Update existing approval
                    $update_query = "UPDATE victim_approvals SET approval_status = ?, approved_at = NOW() WHERE victim_id = ? AND disaster_id = ?";
                    $update_stmt = $db->prepare($update_query);
                    $update_stmt->bind_param("sii", $new_status, $victim_id, $selected_disaster_id);
                    $update_stmt->execute();
                    $update_stmt->close();
                } else {
                    // Insert new approval
                    $insert_query = "INSERT INTO victim_approvals (victim_id, disaster_id, approval_status, approved_at) VALUES (?, ?, ?, NOW())";
                    $insert_stmt = $db->prepare($insert_query);
                    $insert_stmt->bind_param("iis", $victim_id, $selected_disaster_id, $new_status);
                    $insert_stmt->execute();
                    $insert_stmt->close();
                }
                
                $check_stmt->close();
                $successCount++;
                
                // Send to YOUR API
                $apiResult = sendStatusToYourAPI($victim_id, $selected_disaster_id, $new_status);
                if ($apiResult['success']) {
                    $apiSuccessCount++;
                } else {
                    $apiFailCount++;
                }
            }
            
            $success = "✅ Successfully updated $successCount victim(s) to $new_status";
            if ($apiSuccessCount > 0) {
                $success .= " ($apiSuccessCount sent to API)";
            }
            if ($apiFailCount > 0) {
                $success .= " ($apiFailCount failed to send to API)";
            }
            
            // Check if all victims are approved and mark disaster as processed
            if ($action === 'approve') {
                checkAndMarkDisasterAsProcessed($db, $selected_disaster_id);
            }
            
            header("Location: ?disaster_id=$selected_disaster_id&status_filter=$selected_status_filter&success=" . urlencode($success));
            exit();
            
        } catch (Exception $e) {
            $error = "Error updating victims: " . $e->getMessage();
        }
    }
}

/* ----------------------------------------
   PROCESS DATA WITH NEEDS API INTEGRATION - UPDATED FOR CONSISTENCY
---------------------------------------- */
$selected_disaster_id = intval($_GET['disaster_id'] ?? 0);
$selected_status_filter = $_GET['status_filter'] ?? 'Pending';

// Check for success message in URL - AFTER header redirects
if (isset($_GET['success'])) {
    $success = urldecode($_GET['success']);
}

// FIRST, load ALL victims and needs to create a complete mapping
$allApiVictims = [];
$allApiNeeds = [];
$victimNeedsMap = [];

// Load victims from victim API
if ($victimApiResult['success'] && is_array($victimApiResult['data'])) {
    $allApiVictims = $victimApiResult['data'];
}

// Load needs from needs API
if ($needsApiResult['success'] && isset($needsApiResult['data']['data']) && is_array($needsApiResult['data']['data'])) {
    $allApiNeeds = $needsApiResult['data']['data'];
    
    // First, map needs to victims
    foreach ($allApiNeeds as $need) {
        $victimId = $need['victim_id'] ?? 0;
        
        if ($victimId > 0) {
            if (!isset($victimNeedsMap[$victimId])) {
                $victimNeedsMap[$victimId] = [];
            }
            
            $victimNeedsMap[$victimId][] = [
                'need_id' => $need['need_id'] ?? 0,
                'resource_name' => $need['temp_resource_name'] ?? 'Unknown Resource',
                'quantity_needed' => $need['quantity_needed'] ?? '0',
                'priority' => $need['priority'] ?? 'Medium',
                'status' => $need['status'] ?? 'Pending',
                'has_baby' => isset($need['has_baby']) ? (bool)$need['has_baby'] : false,
                'has_elderly' => isset($need['has_elderly']) ? (bool)$need['has_elderly'] : false,
                'has_disabled' => isset($need['has_disabled']) ? (bool)$need['has_disabled'] : false
            ];
        }
    }
}

// Load disasters with consistent statistics calculation
if ($disasterApiResult['success'] && is_array($disasterApiResult['data'])) {
    foreach ($disasterApiResult['data'] as $apiDisaster) {
        $disaster_id = $apiDisaster['disaster_id'] ?? 
                      $apiDisaster['Disaster_ID'] ?? 
                      $apiDisaster['disasterId'] ?? 
                      $apiDisaster['DisasterID'] ??
                      $apiDisaster['id'] ?? null;
        
        if ($disaster_id) {
            $disaster_id = intval($disaster_id);
            
            // Check if disaster is already processed
            $is_processed = isDisasterProcessed($db, $disaster_id);
            
            // Count victims for this disaster from victim API (with needs mapping)
            $victimCount = 0;
            $needCount = 0;
            $victimNeedsData = []; // Store victim data for this disaster
            
            foreach ($allApiVictims as $index => $victim) {
                if (!is_array($victim)) continue;
                
                $victimDisasterId = $victim['disaster_id'] ?? 
                                   $victim['Disaster_ID'] ?? 
                                   $victim['disasterId'] ?? 
                                   $victim['DisasterID'] ?? 0;
                $victimDisasterId = intval($victimDisasterId);
                
                if ($victimDisasterId == $disaster_id) {
                    $victim_id = $victim['victim_id'] ?? 
                                $victim['Victim_ID'] ?? 
                                $victim['victimId'] ?? 
                                $victim['VictimID'] ??
                                $victim['id'] ?? ($index + 1);
                    $victim_id = intval($victim_id);
                    
                    // Get needs for this victim
                    $victim['needs'] = $victimNeedsMap[$victim_id] ?? [];
                    $victim['victim_id'] = $victim_id;
                    $victimCount++;
                    
                    // Count needs
                    $needCount += count($victim['needs']);
                    
                    // Store for later approval calculation
                    $victimNeedsData[$victim_id] = $victim;
                }
            }
            
            // Now calculate approval statistics CONSISTENTLY with the victim table
            $pending = 0;
            $approved = 0;
            $rejected = 0;
            $approved_not_distributed = 0;
            $approved_distributed = 0;
            
            foreach ($victimNeedsData as $victim_id => $victim) {
                if ($victim_id > 0) {
                    // Get approval status and check if already distributed
                    $approval_query = "
                        SELECT 
                            va.approval_status,
                            CASE WHEN di.victim_id IS NOT NULL THEN 1 ELSE 0 END as is_distributed
                        FROM victim_approvals va
                        LEFT JOIN distribution_items di ON va.victim_id = di.victim_id 
                            AND di.status IN ('Scheduled', 'Dispatched', 'Delivered')
                        WHERE va.victim_id = ? AND va.disaster_id = ?
                    ";
                    $approval_stmt = $db->prepare($approval_query);
                    $approval_stmt->bind_param("ii", $victim_id, $disaster_id);
                    $approval_stmt->execute();
                    $approval_result = $approval_stmt->get_result();
                    
                    if ($approval_result->num_rows > 0) {
                        $approval = $approval_result->fetch_assoc();
                        $approval_status = $approval['approval_status'];
                        $is_distributed = $approval['is_distributed'];
                    } else {
                        $approval_status = 'Pending';
                        $is_distributed = 0;
                    }
                    $approval_stmt->close();
                    
                    // Count based on status
                    if ($approval_status === 'Pending') {
                        $pending++;
                    } elseif ($approval_status === 'Approved') {
                        $approved++;
                        if ($is_distributed) {
                            $approved_distributed++;
                        } else {
                            $approved_not_distributed++;
                        }
                    } elseif ($approval_status === 'Rejected') {
                        $rejected++;
                    }
                } else {
                    $pending++;
                }
            }
            
            // Get processed date if exists
            $processed_date = null;
            $processed_notes = null;
            if ($is_processed) {
                $processed_query = "SELECT processed_date, notes FROM processed_disasters WHERE disaster_id = ?";
                $processed_stmt = $db->prepare($processed_query);
                $processed_stmt->bind_param("i", $disaster_id);
                $processed_stmt->execute();
                $processed_result = $processed_stmt->get_result();
                if ($processed_result->num_rows > 0) {
                    $processed_data = $processed_result->fetch_assoc();
                    $processed_date = $processed_data['processed_date'];
                    $processed_notes = $processed_data['notes'];
                }
                $processed_stmt->close();
            }
            
            $disasters[] = [
                'disaster_id' => $disaster_id,
                'Disaster_Name' => $apiDisaster['disaster_name'] ?? $apiDisaster['Disaster_Name'] ?? $apiDisaster['disasterName'] ?? 'Unknown',
                'Location' => $apiDisaster['district'] ?? $apiDisaster['District'] ?? 'Unknown',
                'Disaster_Type' => $apiDisaster['severity'] ?? $apiDisaster['Severity'] ?? 'Unknown',
                'description' => $apiDisaster['description'] ?? '',
                'status' => $apiDisaster['status'] ?? 'Unknown',
                'total_needs' => $victimCount, // Use victim count from API
                'pending' => $pending,
                'approved' => $approved,
                'rejected' => $rejected,
                'approved_not_distributed' => $approved_not_distributed,
                'approved_distributed' => $approved_distributed,
                'api_victim_count' => $victimCount,
                'api_need_count' => $needCount,
                'is_processed' => $is_processed,
                'processed_date' => $processed_date,
                'processed_notes' => $processed_notes
            ];
        }
    }
}

/* ----------------------------------------
   LOAD VICTIMS WITH NEEDS FOR SELECTED DISASTER
---------------------------------------- */
if ($selected_disaster_id > 0) {
    $disasterVictims = [];
    $victimNeedsData = [];
    
    // Find victims for this disaster
    foreach ($allApiVictims as $index => $victim) {
        if (!is_array($victim)) continue;
        
        $victimDisasterId = $victim['disaster_id'] ?? 
                           $victim['Disaster_ID'] ?? 
                           $victim['disasterId'] ?? 
                           $victim['DisasterID'] ?? 0;
        $victimDisasterId = intval($victimDisasterId);
        
        if ($victimDisasterId == $selected_disaster_id) {
            $victim_id = $victim['victim_id'] ?? 
                        $victim['Victim_ID'] ?? 
                        $victim['victimId'] ?? 
                        $victim['VictimID'] ??
                        $victim['id'] ?? ($index + 1);
            $victim_id = intval($victim_id);
            
            // Get needs for this victim
            $victim['needs'] = $victimNeedsMap[$victim_id] ?? [];
            $victim['victim_id'] = $victim_id;
            
            // Calculate priority based on needs
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
    
    // Process approval statuses with distribution check
    foreach ($disasterVictims as &$victim) {
        $victim_id = $victim['victim_id'] ?? 0;
        
        if ($victim_id > 0) {
            // Get approval status and check if already distributed
            $approval_query = "
                SELECT 
                    va.approval_status,
                    CASE WHEN di.victim_id IS NOT NULL THEN 1 ELSE 0 END as is_distributed
                FROM victim_approvals va
                LEFT JOIN distribution_items di ON va.victim_id = di.victim_id 
                    AND di.status IN ('Scheduled', 'Dispatched', 'Delivered')
                WHERE va.victim_id = ? AND va.disaster_id = ?
            ";
            $approval_stmt = $db->prepare($approval_query);
            $approval_stmt->bind_param("ii", $victim_id, $selected_disaster_id);
            $approval_stmt->execute();
            $approval_result = $approval_stmt->get_result();
            
            if ($approval_result->num_rows > 0) {
                $approval = $approval_result->fetch_assoc();
                $victim['approval_status'] = $approval['approval_status'];
                $victim['is_distributed'] = $approval['is_distributed'];
            } else {
                $victim['approval_status'] = 'Pending';
                $victim['is_distributed'] = 0;
                
                // Auto-insert as Pending
                $insert_query = "INSERT INTO victim_approvals (victim_id, disaster_id, approval_status) VALUES (?, ?, 'Pending')";
                $insert_stmt = $db->prepare($insert_query);
                $insert_stmt->bind_param("ii", $victim_id, $selected_disaster_id);
                $insert_stmt->execute();
                $insert_stmt->close();
            }
            
            $approval_stmt->close();
        } else {
            $victim['approval_status'] = 'Pending';
            $victim['is_distributed'] = 0;
        }
    }
    
    // Filter by status
    if ($selected_status_filter !== 'all') {
        $disasterVictims = array_filter($disasterVictims, function($victim) use ($selected_status_filter) {
            return ($victim['approval_status'] ?? 'Pending') === $selected_status_filter;
        });
    }
    
    $needs = array_values($disasterVictims);
    
    // Get statistics (including distribution status) - Count ALL victims from API
    $total_api_victims = 0;
    $pending = 0;
    $approved = 0;
    $rejected = 0;
    $approved_not_distributed = 0;
    $approved_distributed = 0;
    
    foreach ($disasterVictims as $victim) {
        $victim_id = $victim['victim_id'] ?? 0;
        $approval_status = $victim['approval_status'] ?? 'Pending';
        $is_distributed = $victim['is_distributed'] ?? 0;
        
        $total_api_victims++;
        
        if ($approval_status === 'Pending') {
            $pending++;
        } elseif ($approval_status === 'Approved') {
            $approved++;
            if ($is_distributed) {
                $approved_distributed++;
            } else {
                $approved_not_distributed++;
            }
        } elseif ($approval_status === 'Rejected') {
            $rejected++;
        }
    }
    
    $statistics = [
        'total' => $total_api_victims, 
        'pending' => $pending, 
        'approved' => $approved, 
        'rejected' => $rejected,
        'approved_not_distributed' => $approved_not_distributed,
        'approved_distributed' => $approved_distributed
    ];
    
    // Check if disaster is processed
    $selected_disaster_processed = isDisasterProcessed($db, $selected_disaster_id);
}

// Check database table exists
$table_exists = false;
$table_check = $db->query("SHOW TABLES LIKE 'victim_approvals'");
if ($table_check->num_rows > 0) {
    $table_exists = true;
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
        /* Add all your existing CSS styles here */
        /* ... (keep all your existing CSS styles) ... */
        
        /* Add new styles for distribution status */
        .badge-distributed {
            background: #6c757d;
            color: white;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 0.7em;
            font-weight: 600;
            margin-left: 5px;
        }
        
        .badge-not-distributed {
            background: #28a745;
            color: white;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 0.7em;
            font-weight: 600;
            margin-left: 5px;
        }
        
        .distribution-status {
            font-size: 0.8em;
            color: #6c757d;
            margin-top: 3px;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Debug API Status Section -->
        <div class="api-status-section">
            <h3><i class="fas fa-bug"></i> API Status</h3>
            
            <div style='display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin-top: 15px;'>
                <!-- Disaster API -->
                <div style='background: white; padding: 15px; border-radius: 8px; border: 1px solid #ddd;'>
                    <h4><i class="fas fa-exclamation-triangle"></i> Disaster API</h4>
                    <p>Status: <?php echo ($disasterApiResult['success'] ? '✅ Connected' : '❌ Failed'); ?></p>
                    <?php if ($disasterApiResult['success']): ?>
                        <?php $disasterCount = is_array($disasterApiResult['data']) ? count($disasterApiResult['data']) : 'N/A'; ?>
                        <p>Items: <strong><?php echo $disasterCount; ?></strong></p>
                    <?php endif; ?>
                </div>
                
                <!-- Victim API -->
                <div style='background: white; padding: 15px; border-radius: 8px; border: 1px solid #ddd;'>
                    <h4><i class="fas fa-users"></i> Victim API</h4>
                    <p>Status: <?php echo ($victimApiResult['success'] ? '✅ Connected' : '❌ Failed'); ?></p>
                    <?php if ($victimApiResult['success']): ?>
                        <?php $victimCount = is_array($victimApiResult['data']) ? count($victimApiResult['data']) : 'N/A'; ?>
                        <p>Items: <strong><?php echo $victimCount; ?></strong></p>
                    <?php endif; ?>
                </div>
                
                <!-- Needs API -->
                <div style='background: white; padding: 15px; border-radius: 8px; border: 1px solid #ddd;'>
                    <h4><i class="fas fa-heart"></i> Needs API</h4>
                    <p>Status: <?php echo ($needsApiResult['success'] ? '✅ Connected' : '❌ Failed'); ?></p>
                    <?php if ($needsApiResult['success'] && isset($needsApiResult['data']['summary'])): ?>
                        <p>Total Needs: <strong><?php echo $needsApiResult['data']['summary']['total_needs'] ?? 0; ?></strong></p>
                        <p>Unique Victims: <strong><?php echo $needsApiResult['data']['summary']['unique_victims'] ?? 0; ?></strong></p>
                        <p>High Priority: <strong><?php echo $needsApiResult['data']['summary']['high_priority'] ?? 0; ?></strong></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Page Header -->
        <div class="header">
            <div class="header-content">
                <h1><i class="fas fa-clipboard-check"></i> Manage Victim Requests</h1>
                <p>Approve/reject victim requests with needs assessment</p>
                <div class="schema-info">
                    <strong><i class="fas fa-database"></i> Data Integration:</strong><br>
                    <small>Combining Victim data with Needs assessment for better decision making</small>
                </div>
            </div>
            <div class="header-actions">
                <a href="distribution_main.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
                <?php if ($selected_disaster_id && isset($statistics) && $statistics['approved_not_distributed'] > 0): ?>
                    <a href="create_distribution_plan.php?disaster_id=<?php echo $selected_disaster_id; ?>" 
                       class="btn btn-success">
                        <i class="fas fa-plus-circle"></i> Create Distribution Plan
                        <span class="badge-approved" style="margin-left: 10px;">
                            <?php echo $statistics['approved_not_distributed']; ?> available
                        </span>
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Data Source Info -->
        <div class="data-source-info">
            <strong><i class="fas fa-info-circle"></i> Data Integration:</strong>
            <div style="margin-top: 5px;">
                • <strong>Victim data</strong> from victim.php (personal information)<br>
                • <strong>Needs data</strong> from needs.php (resources & priorities)<br>
                • <strong>Disaster data</strong> from disaster.php (context)<br>
                • Total victims with needs: <strong><?php echo count($needs); ?></strong> for selected disaster
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
        
        <?php if (!$table_exists): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle"></i> 
                <strong>Database table missing!</strong> The 'victim_approvals' table does not exist.
                <a href="create_table.php" style="margin-left: 10px;" class="btn btn-sm btn-primary">
                    <i class="fas fa-plus"></i> Create Table
                </a>
            </div>
        <?php endif; ?>

        <!-- Disaster Selection Section -->
        <div class="card-3d" style="margin-bottom: 25px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                <div>
                    <h2><i class="fas fa-exclamation-triangle"></i> Select Disaster</h2>
                    <p>Choose a disaster to manage victim requests with needs assessment</p>
                </div>
                <div style="background: #e8f5e9; color: #2e7d32; padding: 10px 20px; border-radius: 8px; font-weight: 600;">
                    <i class="fas fa-database"></i> Integrated Victim + Needs Data
                </div>
            </div>
            
            <?php if (empty($disasters)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">📭</div>
                    <h3>No disasters found</h3>
                    <p>No active disasters in the external system.</p>
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
                                <th>Status</th>
                                <th>API Victims</th>
                                <th>API Needs</th>
                                <th>Pending</th>
                                <th>Approved</th>
                                <th>Rejected</th>
                                <th>Distribution Status</th>
                                <th>Processed</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($disasters as $disaster): ?>
                            <?php 
                            $is_complete = $disaster['api_victim_count'] > 0 && 
                                         $disaster['approved'] >= $disaster['api_victim_count'];
                            ?>
                            <tr style="<?php echo $disaster['is_processed'] ? 'background-color: #f8f9fa;' : ''; ?>">
                                <td><strong>#<?php echo $disaster['disaster_id']; ?></strong></td>
                                <td>
                                    <div style="font-weight: 600;"><?php echo htmlspecialchars($disaster['Disaster_Name']); ?></div>
                                    <?php if (!empty($disaster['description'])): ?>
                                    <div style="font-size: 0.85em; color: #7f8c8d;">
                                        <?php echo htmlspecialchars(substr($disaster['description'], 0, 50)); ?>...
                                    </div>
                                    <?php endif; ?>
                                    <?php if ($disaster['is_processed'] && $disaster['processed_date']): ?>
                                    <div style="font-size: 0.75em; color: #6c757d; margin-top: 3px;">
                                        <i class="fas fa-calendar-check"></i> 
                                        Processed: <?php echo date('M d, Y', strtotime($disaster['processed_date'])); ?>
                                    </div>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($disaster['Location']); ?></td>
                                <td>
                                    <span class="badge-<?php echo strtolower($disaster['Disaster_Type']); ?>">
                                        <?php echo htmlspecialchars($disaster['Disaster_Type']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge-<?php echo strtolower($disaster['status']); ?>">
                                        <?php echo htmlspecialchars($disaster['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <strong><?php echo $disaster['api_victim_count']; ?></strong> victims
                                </td>
                                <td>
                                    <?php if ($disaster['api_need_count'] > 0): ?>
                                        <span class="needs-count-badge"><?php echo $disaster['api_need_count']; ?> needs</span>
                                    <?php else: ?>
                                        <span style="color: #bdc3c7;">0</span>
                                    <?php endif; ?>
                                </td>
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
                                        <div style="font-size: 0.75em; color: #28a745;">
                                            <?php echo $disaster['approved_not_distributed']; ?> available
                                        </div>
                                        <?php if ($is_complete): ?>
                                            <br><small style="color: #28a745; font-size: 0.7em;">✓ Complete</small>
                                        <?php endif; ?>
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
                                    <?php if ($disaster['approved'] > 0): ?>
                                        <div style="font-size: 0.85em;">
                                            <div><?php echo $disaster['approved_not_distributed']; ?> not distributed</div>
                                            <div><?php echo $disaster['approved_distributed']; ?> distributed</div>
                                        </div>
                                    <?php else: ?>
                                        <span style="color: #bdc3c7;">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($disaster['is_processed']): ?>
                                        <span class="badge-processed">
                                            <i class="fas fa-check"></i> Processed
                                        </span>
                                    <?php else: ?>
                                        <span style="color: #bdc3c7;">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($disaster['is_processed']): ?>
                                        <button class="btn-sm btn-secondary" 
                                                onclick="showProcessedInfo(<?php echo $disaster['disaster_id']; ?>)"
                                                title="View Processed Details">
                                            <i class="fas fa-eye"></i> View
                                        </button>
                                    <?php else: ?>
                                        <a href="?disaster_id=<?php echo $disaster['disaster_id']; ?>" 
                                           class="btn-sm btn-primary" style="text-decoration: none;">
                                            <i class="fas fa-edit"></i> Manage
                                        </a>
                                    <?php endif; ?>
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
        <?php 
        $selected_disaster_info = null;
        foreach ($disasters as $d) {
            if ($d['disaster_id'] == $selected_disaster_id) {
                $selected_disaster_info = $d;
                break;
            }
        }
        ?>
        
        <?php if ($selected_disaster_info): ?>
        <div class="card-3d" style="margin-bottom: 20px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
            <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                <div>
                    <h3 style="color: white; margin-bottom: 15px;">
                        <i class="fas fa-info-circle"></i> Disaster #<?php echo $selected_disaster_id; ?> - <?php echo htmlspecialchars($selected_disaster_info['Disaster_Name']); ?>
                    </h3>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                        <div>
                            <div style="opacity: 0.9; font-size: 0.9em;">District</div>
                            <div style="font-weight: 600; font-size: 1.1em;"><?php echo htmlspecialchars($selected_disaster_info['Location']); ?></div>
                        </div>
                        <div>
                            <div style="opacity: 0.9; font-size: 0.9em;">Severity</div>
                            <div style="font-weight: 600; font-size: 1.1em;"><?php echo htmlspecialchars($selected_disaster_info['Disaster_Type']); ?></div>
                        </div>
                        <div>
                            <div style="opacity: 0.9; font-size: 0.9em;">Status</div>
                            <div style="font-weight: 600; font-size: 1.1em;"><?php echo htmlspecialchars($selected_disaster_info['status']); ?></div>
                        </div>
                        <div>
                            <div style="opacity: 0.9; font-size: 0.9em;">Needs in API</div>
                            <div style="font-weight: 600; font-size: 1.1em;"><?php echo $selected_disaster_info['api_need_count']; ?> needs</div>
                        </div>
                    </div>
                </div>
                
                <?php if (isset($selected_disaster_processed) && $selected_disaster_processed): ?>
                <div style="background: rgba(255,255,255,0.2); padding: 15px; border-radius: 8px; min-width: 200px;">
                    <div style="font-weight: bold; margin-bottom: 5px;">
                        <i class="fas fa-check-circle"></i> Disaster Processed
                    </div>
                    <?php if ($selected_disaster_info['processed_date']): ?>
                    <div style="font-size: 0.9em;">
                        Date: <?php echo date('M d, Y H:i', strtotime($selected_disaster_info['processed_date'])); ?>
                    </div>
                    <?php endif; ?>
                    <?php if ($selected_disaster_info['processed_notes']): ?>
                    <div style="font-size: 0.85em; margin-top: 5px;">
                        <?php echo htmlspecialchars($selected_disaster_info['processed_notes']); ?>
                    </div>
                    <?php endif; ?>
                    <button type="button" class="btn btn-sm btn-warning" style="margin-top: 10px;"
                            onclick="resetProcessedStatus(<?php echo $selected_disaster_id; ?>)">
                        <i class="fas fa-redo"></i> Reset Status
                    </button>
                </div>
                <?php endif; ?>
            </div>
            
            <?php 
            $total_victims = $selected_disaster_info['api_victim_count'];
            $approved = $selected_disaster_info['approved'];
            $approved_not_distributed = $selected_disaster_info['approved_not_distributed'];
            $is_complete = $total_victims > 0 && $approved >= $total_victims && $approved_not_distributed == 0;
            ?>
            
            <div class="disaster-status-row" style="margin-top: 15px; background: rgba(255,255,255,0.1);">
                <div style="flex-grow: 1;">
                    <div style="font-weight: 600; margin-bottom: 5px;">Distribution Status</div>
                    <div style="display: flex; gap: 15px; font-size: 0.9em;">
                        <div>
                            <i class="fas fa-check-circle" style="color: #28a745;"></i>
                            Approved: <strong><?php echo $approved; ?></strong>
                        </div>
                        <div>
                            <i class="fas fa-box-open" style="color: #17a2b8;"></i>
                            Available for distribution: <strong><?php echo $approved_not_distributed; ?></strong>
                        </div>
                        <div>
                            <i class="fas fa-truck" style="color: #6c757d;"></i>
                            Already distributed: <strong><?php echo $selected_disaster_info['approved_distributed']; ?></strong>
                        </div>
                    </div>
                    <?php if ($approved_not_distributed > 0): ?>
                    <div style="font-size: 0.9em; margin-top: 5px; color: #90ee90;">
                        <i class="fas fa-info-circle"></i>
                        <?php echo $approved_not_distributed; ?> victims ready for distribution plan
                    </div>
                    <?php endif; ?>
                </div>
                
                <?php if ($approved_not_distributed > 0): ?>
                <div>
                    <a href="create_distribution_plan.php?disaster_id=<?php echo $selected_disaster_id; ?>" 
                       class="btn btn-success">
                        <i class="fas fa-plus-circle"></i> Create Distribution Plan
                    </a>
                </div>
                <?php elseif ($is_complete && !$selected_disaster_processed): ?>
                <div>
                    <button type="button" class="btn btn-success" 
                            onclick="markAsProcessed(<?php echo $selected_disaster_id; ?>)">
                        <i class="fas fa-check-double"></i> Mark as Processed
                    </button>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card total">
                <div class="stat-label">Total Victims</div>
                <div class="stat-value"><?php echo $selected_disaster_info['api_victim_count']; ?></div>
            </div>
            <div class="stat-card pending">
                <div class="stat-label"><i class="fas fa-clock"></i> Pending</div>
                <div class="stat-value"><?php echo $statistics['pending']; ?></div>
            </div>
            <div class="stat-card approved">
                <div class="stat-label"><i class="fas fa-check-circle"></i> Approved</div>
                <div class="stat-value"><?php echo $statistics['approved']; ?></div>
                <?php if ($selected_disaster_info['api_victim_count'] > 0): ?>
                <div style="font-size: 0.8em; color: #28a745; margin-top: 5px;">
                    <?php echo round(($statistics['approved'] / $selected_disaster_info['api_victim_count']) * 100, 1); ?>% approved
                </div>
                <?php endif; ?>
            </div>
            <div class="stat-card rejected">
                <div class="stat-label"><i class="fas fa-times-circle"></i> Rejected</div>
                <div class="stat-value"><?php echo $statistics['rejected']; ?></div>
            </div>
            <div class="stat-card" style="border-top: 4px solid #17a2b8;">
                <div class="stat-label"><i class="fas fa-box-open"></i> Available</div>
                <div class="stat-value"><?php echo $statistics['approved_not_distributed']; ?></div>
                <div style="font-size: 0.8em; color: #17a2b8; margin-top: 5px;">
                    Ready for distribution
                </div>
            </div>
            <?php if ($selected_disaster_processed): ?>
            <div class="stat-card processed">
                <div class="stat-label"><i class="fas fa-check-double"></i> Processed</div>
                <div class="stat-value">✓</div>
                <div style="font-size: 0.8em; color: #6c757d; margin-top: 5px;">
                    All distributed
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Warning if no victims available for distribution -->
        <?php if ($statistics['approved_not_distributed'] == 0 && $statistics['approved'] > 0): ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i>
            <div style="flex-grow: 1;">
                <strong>All approved victims have been distributed!</strong>
                <div style="margin-top: 5px;">
                    <?php echo $statistics['approved_distributed']; ?> victims already distributed.
                    <?php if (!$selected_disaster_processed): ?>
                    <button type="button" class="btn btn-success btn-sm" 
                            onclick="markAsProcessed(<?php echo $selected_disaster_id; ?>)"
                            style="margin-left: 10px;">
                        <i class="fas fa-check-double"></i> Mark as Processed
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php elseif ($statistics['approved_not_distributed'] > 0): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <div style="flex-grow: 1;">
                <strong><?php echo $statistics['approved_not_distributed']; ?> victims available for distribution!</strong>
                <div style="margin-top: 5px;">
                    <a href="create_distribution_plan.php?disaster_id=<?php echo $selected_disaster_id; ?>" 
                       class="btn btn-success">
                        <i class="fas fa-plus-circle"></i> Create Distribution Plan
                    </a>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Filters -->
        <div class="filter-bar">
            <form method="GET" id="filter-form">
                <input type="hidden" name="disaster_id" value="<?php echo $selected_disaster_id; ?>">
                
                <div class="filter-grid">
                    <div class="form-group">
                        <label class="form-label">Disaster</label>
                        <select class="form-control" name="disaster_id" onchange="this.form.submit()">
                            <?php foreach ($disasters as $disaster): ?>
                            <option value="<?php echo $disaster['disaster_id']; ?>"
                                    <?php echo $selected_disaster_id == $disaster['disaster_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($disaster['Disaster_Name']); ?>
                                <?php if ($disaster['is_processed']): ?> (Processed)<?php endif; ?>
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
                    <div class="form-group">
                        <button type="button" class="btn btn-secondary" 
                                onclick="window.location.href='?disaster_id=<?php echo $selected_disaster_id; ?>'">
                            <i class="fas fa-times"></i> Clear Filters
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Bulk Actions Form -->
        <form method="POST" id="bulk-action-form">
            <input type="hidden" name="disaster_id" value="<?php echo $selected_disaster_id; ?>">
            <input type="hidden" name="status_filter" value="<?php echo $selected_status_filter; ?>">
            
            <div class="bulk-actions-bar" id="bulk-actions-bar">
                <div>
                    <strong><i class="fas fa-check-square"></i> 
                    <span id="selected-count">0</span> victim(s) selected</strong>
                </div>
                <div style="display: flex; gap: 10px;">
                    <button type="submit" name="bulk_action" value="approve" 
                            class="btn btn-success"
                            onclick="return confirm('Approve selected victims?')">
                        <i class="fas fa-check"></i> Approve Selected
                    </button>
                    <button type="submit" name="bulk_action" value="reject" 
                            class="btn btn-danger"
                            onclick="return confirm('Reject selected victims?')">
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
                        <i class="fas fa-users"></i> Victim Requests with Needs Assessment
                        <span style="font-size: 0.7em; color: #7f8c8d;">
                            (<?php echo count($needs); ?> victims)
                        </span>
                    </h2>
                    <?php if (count($needs) > 0): ?>
                    <div>
                        <label style="display: flex; align-items: center; font-weight: 600; gap: 10px;">
                            <input type="checkbox" id="select-all" style="transform: scale(1.2);">
                            Select All (<?php echo count($needs); ?> victims)
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
                                No <?php echo strtolower($selected_status_filter); ?> victims for disaster #<?php echo $selected_disaster_id; ?>.
                            <?php else: ?>
                                No victim requests found for disaster #<?php echo $selected_disaster_id; ?>.
                            <?php endif; ?>
                        </p>
                        <div style="margin-top: 15px;">
                            <a href="?disaster_id=<?php echo $selected_disaster_id; ?>" class="btn btn-secondary">
                                <i class="fas fa-times"></i> Clear Filter
                            </a>
                        </div>
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
                                    <th>Needs & Priorities</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($needs as $victim): ?>
                                <?php 
                                // Get victim info with multiple field name options
                                $victim_id = $victim['victim_id'] ?? 
                                           $victim['Victim_ID'] ?? 
                                           $victim['victimId'] ?? 
                                           $victim['VictimID'] ??
                                           $victim['id'] ?? 0;
                                
                                $full_name = $victim['full_name'] ?? 
                                           $victim['Full_Name'] ?? 
                                           $victim['fullName'] ?? 
                                           $victim['name'] ?? 'Unknown';
                                
                                $ic_number = $victim['ic_number'] ?? 
                                           $victim['IC_Number'] ?? 
                                           $victim['icNumber'] ?? 
                                           $victim['identification'] ?? 'N/A';
                                
                                $email = $victim['email'] ?? 
                                       $victim['Email'] ?? 'N/A';
                                
                                $phone = $victim['phone'] ?? 
                                       $victim['Phone'] ?? '';
                                
                                $address = $victim['address'] ?? 
                                         $victim['Address'] ?? 'Unknown';
                                
                                $city = $victim['city'] ?? 
                                       $victim['City'] ?? '';
                                
                                $postal_code = $victim['postal_code'] ?? 
                                             $victim['Postal_Code'] ?? 
                                             $victim['postalCode'] ?? '';
                                
                                $district = $victim['district'] ?? 
                                          $victim['District'] ?? 'N/A';
                                
                                $family_members = $victim['family_members'] ?? 
                                                $victim['Family_Members'] ?? 
                                                $victim['familyMembers'] ?? 1;
                                
                                $has_baby = $victim['has_baby'] ?? 
                                          $victim['Has_Baby'] ?? 
                                          $victim['hasBaby'] ?? false;
                                
                                $has_elderly = $victim['has_elderly'] ?? 
                                             $victim['Has_Elderly'] ?? 
                                             $victim['hasElderly'] ?? false;
                                
                                $has_disabled = $victim['has_disabled'] ?? 
                                              $victim['Has_Disabled'] ?? 
                                              $victim['hasDisabled'] ?? false;
                                
                                $special_request = $victim['special_request'] ?? 
                                                 $victim['Special_Request'] ?? 
                                                 $victim['specialRequest'] ?? '';
                                
                                $approval_status = $victim['approval_status'] ?? 'Pending';
                                $is_distributed = $victim['is_distributed'] ?? 0;
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
                                        <div style="font-size: 0.85em; color: #7f8c8d; margin-top: 2px;">
                                            <i class="fas fa-users"></i> <?php echo $family_members; ?> family members
                                        </div>
                                        <?php if ($approval_status === 'Approved'): ?>
                                        <div class="distribution-status">
                                            <?php if ($is_distributed): ?>
                                                <span class="badge-distributed">
                                                    <i class="fas fa-truck"></i> Distributed
                                                </span>
                                            <?php else: ?>
                                                <span class="badge-not-distributed">
                                                    <i class="fas fa-box-open"></i> Available
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <?php endif; ?>
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
                                        <div style="font-size: 0.85em; color: #7f8c8d; margin-top: 3px;">
                                            <?php echo htmlspecialchars($city); ?>
                                            <?php if (!empty($postal_code)): ?>(<?php echo htmlspecialchars($postal_code); ?>)<?php endif; ?>
                                        </div>
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
                                        
                                        <?php if (!empty($needs_list)): ?>
                                            <div class="needs-container">
                                                <?php foreach ($needs_list as $need): ?>
                                                    <div class="need-item">
                                                        <span class="need-resource">
                                                            <?php echo htmlspecialchars($need['resource_name']); ?>
                                                        </span>
                                                        <span class="need-quantity">
                                                            <?php echo $need['quantity_needed']; ?>
                                                        </span>
                                                        <span class="need-priority priority-<?php echo strtolower($need['priority']); ?>">
                                                            <?php echo $need['priority']; ?>
                                                        </span>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php else: ?>
                                            <div style="color: #bdc3c7; font-size: 0.9em; margin-top: 5px;">
                                                No specific needs listed
                                            </div>
                                        <?php endif; ?>
                                        
                                        <!-- Special needs badges -->
                                        <div style="margin-top: 8px;">
                                            <?php if ($has_baby): ?>
                                                <span class="special-needs-badge badge-baby">
                                                    <i class="fas fa-baby"></i> Baby
                                                </span>
                                            <?php endif; ?>
                                            <?php if ($has_elderly): ?>
                                                <span class="special-needs-badge badge-elderly">
                                                    <i class="fas fa-walking-cane"></i> Elderly
                                                </span>
                                            <?php endif; ?>
                                            <?php if ($has_disabled): ?>
                                                <span class="special-needs-badge badge-disabled">
                                                    <i class="fas fa-wheelchair"></i> Disabled
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge-<?php echo strtolower($approval_status); ?>">
                                            <?php echo $approval_status; ?>
                                        </span>
                                        <?php if ($approval_status === 'Approved' && $is_distributed): ?>
                                        <div style="font-size: 0.75em; color: #6c757d; margin-top: 3px;">
                                            Already in distribution
                                        </div>
                                        <?php endif; ?>
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
                    
                    <?php if ($statistics['approved_not_distributed'] > 0): ?>
                        <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #dee2e6; display: flex; justify-content: space-between;">
                            <a href="create_distribution_plan.php?disaster_id=<?php echo $selected_disaster_id; ?>" 
                               class="btn btn-success">
                                <i class="fas fa-plus-circle"></i> Create Distribution Plan
                                <span class="badge-approved" style="margin-left: 10px;">
                                    <?php echo $statistics['approved_not_distributed']; ?> available victims
                                </span>
                            </a>
                            <a href="distribution_main.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> Back to Dashboard
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="alert" style="background: #fff3cd; color: #856404; margin-top: 20px;">
                            <i class="fas fa-exclamation-triangle"></i>
                            <strong>No victims available for distribution.</strong>
                            <?php if ($statistics['approved'] > 0): ?>
                                All <?php echo $statistics['approved']; ?> approved victims have been distributed.
                            <?php else: ?>
                                Approve at least one victim to create a distribution plan.
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </form>
        <?php endif; ?>
    </div>

    <script>
        // Select all checkboxes
        document.getElementById('select-all')?.addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.need-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
            const headerCheckbox = document.getElementById('select-all-header');
            if (headerCheckbox) headerCheckbox.checked = this.checked;
            updateBulkActionsBar();
        });
        
        document.getElementById('select-all-header')?.addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.need-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
            const mainCheckbox = document.getElementById('select-all');
            if (mainCheckbox) mainCheckbox.checked = this.checked;
            updateBulkActionsBar();
        });
        
        // Update bulk actions bar when checkboxes change
        document.querySelectorAll('.need-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', updateBulkActionsBar);
        });
        
        function updateBulkActionsBar() {
            const checkedBoxes = document.querySelectorAll('.need-checkbox:checked');
            const count = checkedBoxes.length;
            const bulkBar = document.getElementById('bulk-actions-bar');
            const countSpan = document.getElementById('selected-count');
            
            if (count > 0) {
                bulkBar.classList.add('active');
                countSpan.textContent = count;
            } else {
                bulkBar.classList.remove('active');
            }
        }
        
        function clearSelection() {
            document.querySelectorAll('.need-checkbox').forEach(checkbox => {
                checkbox.checked = false;
            });
            const selectAll = document.getElementById('select-all');
            const selectAllHeader = document.getElementById('select-all-header');
            if (selectAll) selectAll.checked = false;
            if (selectAllHeader) selectAllHeader.checked = false;
            updateBulkActionsBar();
        }
        
        function updateStatus(victimId, newStatus) {
            if (confirm(`Change victim #${victimId} status to '${newStatus}'?`)) {
                const url = new URL(window.location.href);
                url.searchParams.set('update_status', '1');
                url.searchParams.set('victim_id', victimId);
                url.searchParams.set('new_status', newStatus);
                url.searchParams.set('disaster_id', <?php echo $selected_disaster_id; ?>);
                url.searchParams.set('status_filter', '<?php echo $selected_status_filter; ?>');
                window.location.href = url.toString();
            }
        }
        
        function markAsProcessed(disasterId) {
            if (confirm('Mark this disaster as processed? It will not appear in the distribution creation page.')) {
                const url = new URL(window.location.href);
                url.searchParams.set('mark_processed', '1');
                url.searchParams.set('disaster_id', disasterId);
                window.location.href = url.toString();
            }
        }
        
        function resetProcessedStatus(disasterId) {
            if (confirm('Reset the processed status for this disaster? It will appear again in distribution creation.')) {
                const url = new URL(window.location.href);
                url.searchParams.set('reset_processed', '1');
                url.searchParams.set('disaster_id', disasterId);
                window.location.href = url.toString();
            }
        }
        
        function showProcessedInfo(disasterId) {
            // Find the disaster data and show in alert
            const disasterName = document.querySelector(`tr td:first-child strong[data-id="${disasterId}"]`)?.closest('tr')?.querySelector('td:nth-child(2) div:first-child')?.textContent || 'Disaster #' + disasterId;
            alert(`Disaster "${disasterName}" has been marked as processed and will not appear in distribution creation.\n\nTo reset this status, click the "Manage" button and use the "Reset Status" option.`);
        }
        
        // Initialize bulk actions bar state
        document.addEventListener('DOMContentLoaded', function() {
            updateBulkActionsBar();
            
            // Auto-hide success messages after 5 seconds
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
    </script>
</body>
</html>