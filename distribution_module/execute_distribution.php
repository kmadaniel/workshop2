<?php
session_start();
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['volunteer_id']) || !isset($_SESSION['volunteer_name'])) {
    header("Location: volunteer_distribution.php");
    exit;
}

$volunteer_id = $_SESSION['volunteer_id'];
$volunteer_name = $_SESSION['volunteer_name'];

$database = new Database();
$db = $database->getConnection();

// Input validation
$distribution_id = filter_var($_GET['distribution_id'] ?? null, FILTER_VALIDATE_INT);
if (!$distribution_id || $distribution_id <= 0) {
    header("Location: volunteer_distribution.php?error=invalid_distribution");
    exit;
}

$error = '';
$success = '';
$victim_data = null;
$needs_data = [];
$distribution_stats = null;
$personal_stats = null;
$tracking_updates = [];

// Define upload directory
$upload_dir = 'uploads/signatures/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// API URLs
$VOLUNTEER_API_URL = 'http://10.147.17.30:8000/api_volunteer.php';
$DISASTER_API_URL = 'http://10.147.17.116:8000/disaster.php';
$VICTIM_API_URL = 'http://10.147.17.116:8000/victim.php';
$NEEDS_API_URL = 'http://10.147.17.116:8000/needs.php';

/* ========================================
   API FETCH FUNCTIONS
======================================== */
function fetchFromAPI($url, $id = null) {
    try {
        $full_url = $id ? $url . '?id=' . $id : $url;
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $full_url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_FAILONERROR => false
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        
        // FIX: Properly close cURL resource
        if (is_resource($ch)) {
            curl_close($ch);
        }
        
        if ($response === false) {
            error_log("cURL Error for $full_url: $error");
            return null;
        }
        
        if ($httpCode !== 200) {
            error_log("HTTP Error $httpCode for $full_url");
            return null;
        }
        
        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("JSON decode error for $full_url: " . json_last_error_msg());
            return null;
        }
        
        return $data;
    } catch (Exception $e) {
        error_log("Exception fetching from API $url: " . $e->getMessage());
        return null;
    }
}

function fetchAllFromAPI($url) {
    try {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_FAILONERROR => false
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        
        // FIX: Properly close cURL resource
        if (is_resource($ch)) {
            curl_close($ch);
        }
        
        if ($response === false) {
            error_log("cURL Error for $url: $error");
            return ['success' => false, 'data' => [], 'error' => $error];
        }
        
        if ($httpCode !== 200) {
            error_log("HTTP Error $httpCode for $url");
            return ['success' => false, 'data' => [], 'error' => "HTTP $httpCode"];
        }
        
        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("JSON decode error for $url: " . json_last_error_msg());
            return ['success' => false, 'data' => [], 'error' => 'Invalid JSON'];
        }
        
        if (isset($data['data'])) {
            $items = $data['data'];
        } elseif (isset($data['needs'])) {
            $items = $data['needs'];
        } elseif (isset($data['victims'])) {
            $items = $data['victims'];
        } elseif (isset($data['volunteers'])) {
            $items = $data['volunteers'];
        } elseif (isset($data['disasters'])) {
            $items = $data['disasters'];
        } else {
            $items = is_array($data) ? $data : [];
        }
        
        return ['success' => true, 'data' => $items, 'count' => count($items)];
    } catch (Exception $e) {
        error_log("Exception fetching all from API $url: " . $e->getMessage());
        return ['success' => false, 'data' => [], 'error' => $e->getMessage()];
    }
}

/* ========================================
   FORM SUBMISSION HANDLERS
======================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_tracking'])) {
        $current_location = trim($_POST['current_location'] ?? '');
        $tracking_notes = trim($_POST['tracking_notes'] ?? '');
        $estimated_arrival = trim($_POST['estimated_arrival'] ?? '');
        $status_update = trim($_POST['status_update'] ?? 'in_transit');
        
        if (!empty($current_location)) {
            try {
                $victim_id_for_tracking = $_POST['victim_id'] ?? 0;
                
                $tracking_query = "INSERT INTO distribution_tracking (distribution_id, volunteer_id, victim_id, current_location, tracking_notes, estimated_arrival, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
                $stmt = $db->prepare($tracking_query);
                $stmt->bind_param("iiissss", $distribution_id, $volunteer_id, $victim_id_for_tracking, $current_location, $tracking_notes, $estimated_arrival, $status_update);
                $stmt->execute();
                $stmt->close();
                
                $distribution_volunteer_status = 'Active';
                if ($status_update == 'departed') {
                    $distribution_volunteer_status = 'In Progress';
                } elseif ($status_update == 'arrived') {
                    $distribution_volunteer_status = 'Arrived';
                } elseif ($status_update == 'delayed') {
                    $distribution_volunteer_status = 'Delayed';
                }
                
                $update_volunteer_status_query = "UPDATE distribution_volunteer SET status = ? WHERE distribution_id = ? AND volunteer_id = ?";
                $stmt = $db->prepare($update_volunteer_status_query);
                $stmt->bind_param("sii", $distribution_volunteer_status, $distribution_id, $volunteer_id);
                $stmt->execute();
                $stmt->close();
                
                $success = "Tracking status updated successfully!";
                
                header("Location: execute_distribution.php?distribution_id=$distribution_id&victim_id=$victim_id_for_tracking&success=tracking_updated");
                exit;
                
            } catch (Exception $e) {
                $error = "Error updating tracking: " . $e->getMessage();
            }
        } else {
            $error = "Please enter your current location.";
        }
    }
    
    if (isset($_POST['prepare_distribution'])) {
        $selected_items = $_POST['distributed_items'] ?? [];
        $victim_id = $_POST['victim_id'] ?? 0;
        
        if (!empty($selected_items) && $victim_id) {
            try {
                $db->begin_transaction();
                
                foreach ($selected_items as $need_id) {
                    $check_query = "SELECT id FROM distribution_log WHERE need_id = ? AND victim_id = ? AND distribution_id = ? AND volunteer_id = ?";
                    $stmt = $db->prepare($check_query);
                    $stmt->bind_param("siii", $need_id, $victim_id, $distribution_id, $volunteer_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $exists = $result->num_rows > 0;
                    $stmt->close();
                    
                    if ($exists) {
                        $update_query = "UPDATE distribution_log SET status = 'in_transit' WHERE need_id = ? AND victim_id = ? AND distribution_id = ? AND volunteer_id = ?";
                        $stmt = $db->prepare($update_query);
                        $stmt->bind_param("siii", $need_id, $victim_id, $distribution_id, $volunteer_id);
                    } else {
                        $insert_query = "INSERT INTO distribution_log (distribution_id, volunteer_id, victim_id, need_id, status, quantity_distributed, created_at) VALUES (?, ?, ?, ?, 'in_transit', 1, NOW())";
                        $stmt = $db->prepare($insert_query);
                        $stmt->bind_param("iiis", $distribution_id, $volunteer_id, $victim_id, $need_id);
                    }
                    
                    $stmt->execute();
                    $stmt->close();
                }
                
                $db->commit();
                
                $update_items_query = "UPDATE distribution_items SET status = 'Dispatched' WHERE distribution_id = ? AND victim_id = ?";
                $stmt = $db->prepare($update_items_query);
                $stmt->bind_param("ii", $distribution_id, $victim_id);
                $stmt->execute();
                $stmt->close();
                
                $initial_tracking_query = "INSERT INTO distribution_tracking (distribution_id, volunteer_id, victim_id, current_location, tracking_notes, status, created_at) VALUES (?, ?, ?, 'Distribution Center', 'Items loaded and ready for delivery', 'departed', NOW())";
                $stmt = $db->prepare($initial_tracking_query);
                $stmt->bind_param("iii", $distribution_id, $volunteer_id, $victim_id);
                $stmt->execute();
                $stmt->close();
                
                header("Location: execute_distribution.php?distribution_id=$distribution_id&victim_id=$victim_id&success=prepared");
                exit;
                
            } catch (Exception $e) {
                if (isset($db) && method_exists($db, 'rollback')) {
                    $db->rollback();
                }
                $error = "Error preparing items: " . $e->getMessage();
            }
        } else {
            $error = "Please select at least one item to prepare for delivery.";
        }
    }
    
    if (isset($_POST['complete_delivery'])) {
        $selected_items = $_POST['delivered_items'] ?? [];
        $victim_id = $_POST['victim_id'] ?? 0;
        $delivery_remarks = $_POST['delivery_remarks'] ?? '';
        
        $signature_image_path = null;
        $upload_errors = [];
        
        if (isset($_FILES['signature_image']) && $_FILES['signature_image']['error'] !== UPLOAD_ERR_NO_FILE) {
            $file = $_FILES['signature_image'];
            
            if ($file['error'] !== UPLOAD_ERR_OK) {
                $upload_errors[] = getUploadErrorMessage($file['error']);
            } else {
                $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
                $file_type = mime_content_type($file['tmp_name']);
                
                if (!in_array($file_type, $allowed_types)) {
                    $upload_errors[] = "Only JPG, PNG, GIF, and WebP images are allowed.";
                }
                
                $max_size = 5 * 1024 * 1024;
                if ($file['size'] > $max_size) {
                    $upload_errors[] = "File size must be less than 5MB.";
                }
                
                if (empty($upload_errors)) {
                    $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                    $filename = 'signature_' . $distribution_id . '_' . $victim_id . '_' . time() . '.' . $file_extension;
                    $target_path = $upload_dir . $filename;
                    
                    if (move_uploaded_file($file['tmp_name'], $target_path)) {
                        $signature_image_path = $target_path;
                    } else {
                        $upload_errors[] = "Failed to upload image. Please try again.";
                    }
                }
            }
        }
        
        if (empty($selected_items)) {
            $error = "Please select at least one item to mark as delivered.";
        } elseif (!$signature_image_path) {
            if (empty($upload_errors)) {
                $error = "Please upload a recipient signature/image as confirmation.";
            } else {
                $error = implode(" ", $upload_errors);
            }
        } else {
            try {
                $db->begin_transaction();
                
                foreach ($selected_items as $need_id) {
                    $check_query = "SELECT id FROM distribution_log WHERE need_id = ? AND victim_id = ? AND distribution_id = ? AND volunteer_id = ?";
                    $stmt = $db->prepare($check_query);
                    $stmt->bind_param("siii", $need_id, $victim_id, $distribution_id, $volunteer_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $exists = $result->num_rows > 0;
                    $stmt->close();
                    
                    if ($exists) {
                        $update_query = "UPDATE distribution_log SET status = 'completed', remarks = ?, signature_url = ? WHERE need_id = ? AND victim_id = ? AND distribution_id = ? AND volunteer_id = ?";
                        $stmt = $db->prepare($update_query);
                        $stmt->bind_param("sssiii", $delivery_remarks, $signature_image_path, $need_id, $victim_id, $distribution_id, $volunteer_id);
                    } else {
                        $insert_query = "INSERT INTO distribution_log (distribution_id, volunteer_id, victim_id, need_id, status, quantity_distributed, remarks, signature_url, created_at) VALUES (?, ?, ?, ?, 'completed', 1, ?, ?, NOW())";
                        $stmt = $db->prepare($insert_query);
                        $stmt->bind_param("iiisss", $distribution_id, $volunteer_id, $victim_id, $need_id, $delivery_remarks, $signature_image_path);
                    }
                    
                    $stmt->execute();
                    $stmt->close();
                }
                
                $db->commit();
                
                $update_items_query = "UPDATE distribution_items SET status = 'Delivered' WHERE distribution_id = ? AND victim_id = ?";
                $stmt = $db->prepare($update_items_query);
                $stmt->bind_param("ii", $distribution_id, $victim_id);
                $stmt->execute();
                $stmt->close();
                
                header("Location: execute_distribution.php?distribution_id=$distribution_id&victim_id=$victim_id&success=delivered");
                exit;
                
            } catch (Exception $e) {
                if (isset($db) && method_exists($db, 'rollback')) {
                    $db->rollback();
                }
                if ($signature_image_path && file_exists($signature_image_path)) {
                    unlink($signature_image_path);
                }
                $error = "Error completing delivery: " . $e->getMessage();
            }
        }
    }
}

function getUploadErrorMessage($error_code) {
    switch ($error_code) {
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return 'The uploaded file exceeds the maximum file size.';
        case UPLOAD_ERR_PARTIAL:
            return 'The file was only partially uploaded.';
        case UPLOAD_ERR_NO_FILE:
            return 'No file was uploaded.';
        case UPLOAD_ERR_NO_TMP_DIR:
            return 'Missing temporary folder.';
        case UPLOAD_ERR_CANT_WRITE:
            return 'Failed to write file to disk.';
        case UPLOAD_ERR_EXTENSION:
            return 'A PHP extension stopped the file upload.';
        default:
            return 'Unknown upload error.';
    }
}

/* ========================================
   CHECK VOLUNTEER ASSIGNMENT
======================================== */
$check_assignment = "
    SELECT dv.*, d.* 
    FROM distribution_volunteer dv
    JOIN distribution d ON dv.distribution_id = d.distribution_id
    WHERE dv.distribution_id = ? 
    AND dv.volunteer_id = ?
    AND dv.status IN ('Assigned', 'Active', 'In Progress', 'Arrived', 'Delayed', 'Completed')
    LIMIT 1
";

$stmt = $db->prepare($check_assignment);
$stmt->bind_param("ii", $distribution_id, $volunteer_id);
$stmt->execute();
$result = $stmt->get_result();
$assignment = $result->fetch_assoc();
$stmt->close();

if (!$assignment) {
    die("Error: You are not assigned to this distribution or assignment not found.");
}

/* ========================================
   FETCH DATA FROM APIS
======================================== */
// Get disaster details
$disaster_details = null;
if (isset($assignment['disaster_id']) && $assignment['disaster_id']) {
    $all_disasters = fetchAllFromAPI($DISASTER_API_URL);
    if ($all_disasters['success'] && !empty($all_disasters['data'])) {
        foreach ($all_disasters['data'] as $disaster) {
            $disaster_id_from_api = $disaster['disaster_id'] ?? 
                                   $disaster['Disaster_ID'] ?? 
                                   $disaster['id'] ?? 0;
            if (intval($disaster_id_from_api) == $assignment['disaster_id']) {
                $disaster_details = $disaster;
                break;
            }
        }
    }
}

// Get volunteer details
$volunteer_details = null;
$all_volunteers = fetchAllFromAPI($VOLUNTEER_API_URL);
if ($all_volunteers['success'] && !empty($all_volunteers['data'])) {
    foreach ($all_volunteers['data'] as $volunteer) {
        $volunteer_id_from_api = $volunteer['volunteer_id'] ?? 
                                $volunteer['Volunteer_ID'] ?? 
                                $volunteer['VolunteerID'] ?? 
                                $volunteer['id'] ?? 0;
        if (intval($volunteer_id_from_api) == $volunteer_id) {
            $volunteer_details = $volunteer;
            break;
        }
    }
}

// Update volunteer status to 'Active' if 'Assigned'
if ($assignment['status'] == 'Assigned') {
    $update_status = "UPDATE distribution_volunteer SET status = 'Active' WHERE distribution_id = ? AND volunteer_id = ?";
    $stmt = $db->prepare($update_status);
    $stmt->bind_param("ii", $distribution_id, $volunteer_id);
    $stmt->execute();
    $stmt->close();
    
    $update_distribution_status = "UPDATE distribution SET status = 'In Transit' WHERE distribution_id = ?";
    $stmt = $db->prepare($update_distribution_status);
    $stmt->bind_param("i", $distribution_id);
    $stmt->execute();
    $stmt->close();
}

/* ========================================
   HANDLE VICTIM SELECTION
======================================== */
$victim_id = filter_var($_GET['victim_id'] ?? null, FILTER_VALIDATE_INT);

if ($victim_id) {
    $victim_details = null;
    
    $all_victims = fetchAllFromAPI($VICTIM_API_URL);
    if ($all_victims['success'] && !empty($all_victims['data'])) {
        foreach ($all_victims['data'] as $victim) {
            $victim_id_from_api = $victim['victim_id'] ?? 
                                 $victim['Victim_ID'] ?? 
                                 $victim['VictimID'] ?? 
                                 $victim['id'] ?? 0;
            if (intval($victim_id_from_api) == $victim_id) {
                $victim_details = $victim;
                break;
            }
        }
    }
    
    if (!$victim_details) {
        $victim_details = fetchFromAPI($VICTIM_API_URL, $victim_id);
    }
    
    if ($victim_details) {
        $victim_data = [
            'victim_id' => $victim_id,
            'name' => $victim_details['FullName'] ?? 
                     $victim_details['full_name'] ?? 
                     $victim_details['name'] ?? 
                     $victim_details['victim_name'] ?? 'Unknown Victim',
            'address' => $victim_details['Address'] ?? 
                        $victim_details['address'] ?? 
                        $victim_details['location'] ?? 'N/A',
            'family_size' => $victim_details['FamilyMembers'] ?? 
                            $victim_details['family_members'] ?? 
                            $victim_details['family_size'] ?? 1,
            'contact_number' => $victim_details['ContactNumber'] ?? 
                               $victim_details['contact_number'] ?? 
                               $victim_details['phone'] ?? 
                               $victim_details['Phone'] ?? 'N/A'
        ];
        
        $needs_result = fetchAllFromAPI($NEEDS_API_URL);
        $api_needs = $needs_result['data'] ?? [];
        
        $victim_needs = [];
        foreach ($api_needs as $api_need) {
            $need_victim_id = $api_need['victim_id'] ?? 
                             $api_need['Victim_ID'] ?? 
                             $api_need['VictimID'] ?? 0;
            
            if (intval($need_victim_id) == $victim_id) {
                $victim_needs[] = $api_need;
            }
        }
        
        $needs_data = [];
        foreach ($victim_needs as $api_need) {
            $resource_name = $api_need['ResourceName'] ?? 
                            $api_need['resource_name'] ?? 
                            $api_need['item_name'] ?? 
                            $api_need['ItemName'] ?? 'Resource';
            
            $quantity = $api_need['QuantityNeeded'] ?? 
                       $api_need['quantity_needed'] ?? 
                       $api_need['quantity'] ?? 1;
            
            $unit = $api_need['Unit'] ?? 
                   $api_need['unit'] ?? 'units';
            
            $type = $api_need['Type'] ?? 
                   $api_need['type'] ?? 
                   $api_need['Category'] ?? 
                   $api_need['category'] ?? 'Other';
            
            $need_id_value = $api_need['NeedID'] ?? 
                            $api_need['need_id'] ?? 
                            $api_need['id'] ?? 
                            'need_' . $victim_id . '_' . uniqid();
            
            $check_log_query = "
                SELECT status 
                FROM distribution_log 
                WHERE need_id = ? 
                AND victim_id = ?
                AND distribution_id = ?
                AND volunteer_id = ?
                ORDER BY created_at DESC 
                LIMIT 1
            ";
            
            $log_status = null;
            $stmt = $db->prepare($check_log_query);
            $stmt->bind_param("siii", $need_id_value, $victim_id, $distribution_id, $volunteer_id);
            $stmt->execute();
            $log_result = $stmt->get_result();
            if ($log_row = $log_result->fetch_assoc()) {
                $log_status = $log_row['status'];
            }
            $stmt->close();
            
            $need_status = 'pending';
            if ($log_status == 'completed') {
                $need_status = 'fulfilled';
            } elseif ($log_status == 'in_transit') {
                $need_status = 'in_transit';
            }
            
            $needs_data[] = [
                'need_id' => $need_id_value,
                'resource_name' => $resource_name,
                'quantity_needed' => $quantity,
                'unit' => $unit,
                'type' => $type,
                'need_status' => $need_status,
                'api_status' => $api_need['Status'] ?? $api_need['status'] ?? 'Pending',
                'raw_api_data' => $api_need
            ];
        }
        
        $total_needs = count($needs_data);
        $fulfilled_needs = 0;
        $in_transit_needs = 0;
        
        foreach ($needs_data as $need) {
            if ($need['need_status'] == 'fulfilled') {
                $fulfilled_needs++;
            } elseif ($need['need_status'] == 'in_transit') {
                $in_transit_needs++;
            }
        }
        
        $victim_data['total_needs'] = $total_needs;
        $victim_data['fulfilled_needs'] = $fulfilled_needs;
        $victim_data['in_transit_needs'] = $in_transit_needs;
        
        $grouped_needs = [];
        foreach ($needs_data as $need) {
            $type = $need['type'] ?? 'Other';
            if (!isset($grouped_needs[$type])) {
                $grouped_needs[$type] = [];
            }
            $grouped_needs[$type][] = $need;
        }
        
    } else {
        $error = "Unable to load victim details from API. Please check connectivity.";
    }
} else {
    $victims_from_items = [];
    
    $items_query = "
        SELECT DISTINCT victim_id
        FROM distribution_items 
        WHERE distribution_id = ? 
        AND status IN ('Scheduled', 'Dispatched')
        ORDER BY victim_id ASC
        LIMIT 10
    ";
    
    $stmt = $db->prepare($items_query);
    if ($stmt) {
        $stmt->bind_param("i", $distribution_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $victims_from_items[] = $row['victim_id'];
        }
        $stmt->close();
    }
    
    if (!empty($victims_from_items)) {
        $victim_id = $victims_from_items[0];
        header("Location: execute_distribution.php?distribution_id=$distribution_id&victim_id=$victim_id");
        exit;
    } else {
        $error = "No victims assigned to this distribution yet.";
    }
}

/* ========================================
   GET TRACKING UPDATES
======================================== */
$tracking_query = "
    SELECT * FROM distribution_tracking 
    WHERE distribution_id = ? 
    AND volunteer_id = ?
    AND (victim_id = ? OR victim_id = 0 OR victim_id IS NULL)
    ORDER BY created_at DESC
    LIMIT 10
";

$stmt = $db->prepare($tracking_query);
$stmt->bind_param("iii", $distribution_id, $volunteer_id, $victim_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $tracking_updates[] = $row;
}
$stmt->close();

// Check if current victim has items in transit
$has_in_transit_items = false;
if (!empty($needs_data)) {
    foreach ($needs_data as $need) {
        if ($need['need_status'] == 'in_transit') {
            $has_in_transit_items = true;
            break;
        }
    }
}

/* ========================================
   GET STATISTICS
======================================== */
$stats_query = "
SELECT 
    COUNT(DISTINCT victim_id) as total_families,
    COUNT(DISTINCT CASE WHEN status = 'completed' THEN victim_id END) as completed_families,
    COUNT(DISTINCT CASE WHEN status = 'in_transit' THEN victim_id END) as in_transit_families,
    COUNT(DISTINCT need_id) as total_needs,
    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as fulfilled_needs,
    SUM(CASE WHEN status = 'in_transit' THEN 1 ELSE 0 END) as in_transit_needs,
    SUM(quantity_distributed) as distributed_quantity
FROM distribution_log
WHERE distribution_id = ?
";

$stmt = $db->prepare($stats_query);
$stmt->bind_param("i", $distribution_id);
$stmt->execute();
$stats_result = $stmt->get_result();
$distribution_stats = $stats_result->fetch_assoc();
$stmt->close();

$progress = 0;
if ($distribution_stats && $distribution_stats['total_families'] > 0) {
    $completed_weight = $distribution_stats['completed_families'] * 1.0;
    $in_transit_weight = $distribution_stats['in_transit_families'] * 0.7;
    $total_weight = $completed_weight + $in_transit_weight;
    $max_possible = $distribution_stats['total_families'] * 1.0;
    $progress = ($total_weight / $max_possible) * 100;
}

$personal_stats_query = "
SELECT 
    COUNT(DISTINCT CASE WHEN status = 'completed' THEN victim_id END) as my_completed_victims,
    COUNT(DISTINCT CASE WHEN status = 'in_transit' THEN victim_id END) as my_in_transit_victims,
    COUNT(DISTINCT need_id) as my_fulfilled_needs,
    SUM(quantity_distributed) as my_distributed_quantity
FROM distribution_log
WHERE distribution_id = ?
AND volunteer_id = ?
";

$stmt = $db->prepare($personal_stats_query);
$stmt->bind_param("ii", $distribution_id, $volunteer_id);
$stmt->execute();
$result = $stmt->get_result();
$personal_stats = $result->fetch_assoc();
$stmt->close();

$has_pending_items = false;
$has_in_transit_items = false;
if (!empty($needs_data)) {
    foreach ($needs_data as $need) {
        if ($need['need_status'] == 'pending') {
            $has_pending_items = true;
        }
        if ($need['need_status'] == 'in_transit') {
            $has_in_transit_items = true;
        }
    }
}

$progress = min(max($progress, 0), 100);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Execute Distribution - Disaster Relief System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #4361ee;
            --primary-light: #6c8eff;
            --secondary: #7209b7;
            --success: #2ecc71;
            --warning: #f39c12;
            --danger: #e74c3c;
            --info: #3498db;
            --light: #f8f9fa;
            --dark: #2c3e50;
            --gray: #6c757d;
            --border-radius: 12px;
            --shadow: 0 10px 30px rgba(0,0,0,0.08);
            --transition: all 0.3s ease;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            padding: 20px;
            color: #333;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
        }
        
        /* Header */
        .header {
            background: white;
            padding: 25px 30px;
            border-radius: var(--border-radius);
            margin-bottom: 25px;
            box-shadow: var(--shadow);
            border-left: 5px solid var(--primary);
        }
        
        .header-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .page-title {
            color: var(--dark);
            font-size: 2rem;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .page-title i {
            color: var(--primary);
        }
        
        .btn-back {
            background: var(--light);
            color: var(--dark);
            padding: 12px 25px;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: var(--transition);
            border: 2px solid transparent;
        }
        
        .btn-back:hover {
            background: white;
            border-color: var(--primary);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        /* Progress Section */
        .progress-section {
            background: white;
            padding: 25px;
            border-radius: var(--border-radius);
            margin-bottom: 25px;
            box-shadow: var(--shadow);
        }
        
        .progress-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .progress-bar-container {
            height: 12px;
            background: #e0e0e0;
            border-radius: 6px;
            overflow: hidden;
            margin: 15px 0;
        }
        
        .progress-bar-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--info), var(--success));
            border-radius: 6px;
            transition: width 0.8s ease;
        }
        
        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin: 30px 0;
        }
        
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            text-align: center;
            border-top: 4px solid var(--primary);
            transition: var(--transition);
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-card.total {
            border-top-color: var(--primary);
        }
        
        .stat-card.transit {
            border-top-color: var(--info);
        }
        
        .stat-card.delivered {
            border-top-color: var(--success);
        }
        
        .stat-card.your {
            border-top-color: var(--warning);
        }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
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
        
        /* Victim Profile */
        .victim-profile-card {
            background: white;
            padding: 30px;
            border-radius: var(--border-radius);
            margin: 30px 0;
            box-shadow: var(--shadow);
            border-left: 5px solid var(--success);
        }
        
        .victim-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            flex-wrap: wrap;
            gap: 20px;
        }
        
        .victim-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin: 25px 0;
        }
        
        .info-item {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .info-label {
            color: var(--gray);
            font-size: 0.9rem;
            font-weight: 600;
        }
        
        .info-value {
            color: var(--dark);
            font-size: 1.1rem;
            font-weight: 600;
        }
        
        .info-icon {
            color: var(--primary);
            font-size: 1.2rem;
        }
        
        /* Tracking Section */
        .tracking-section {
            background: white;
            padding: 30px;
            border-radius: var(--border-radius);
            margin: 30px 0;
            box-shadow: var(--shadow);
        }
        
        .tracking-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .tracking-timeline {
            margin: 25px 0;
            position: relative;
            padding-left: 40px;
        }
        
        .tracking-timeline::before {
            content: '';
            position: absolute;
            left: 15px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: linear-gradient(to bottom, var(--primary), var(--success));
        }
        
        .tracking-update {
            display: flex;
            margin-bottom: 25px;
            position: relative;
        }
        
        .tracking-dot {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: white;
            border: 3px solid var(--primary);
            position: absolute;
            left: -37px;
            z-index: 2;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .tracking-dot i {
            color: var(--primary);
            font-size: 12px;
        }
        
        .tracking-content {
            flex: 1;
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            border-left: 4px solid var(--primary);
        }
        
        .tracking-location {
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 8px;
            font-size: 1.1rem;
        }
        
        .tracking-time {
            font-size: 0.85rem;
            color: var(--gray);
            margin-top: 10px;
        }
        
        /* Items Management */
        .items-section {
            background: white;
            padding: 30px;
            border-radius: var(--border-radius);
            margin: 30px 0;
            box-shadow: var(--shadow);
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .section-title {
            color: var(--dark);
            font-size: 1.5rem;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .items-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin: 25px 0;
        }
        
        .item-card {
            background: #f8f9fa;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            padding: 20px;
            cursor: pointer;
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }
        
        .item-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        
        .item-card.selected {
            border-color: var(--primary);
            background: linear-gradient(135deg, rgba(67, 97, 238, 0.05), rgba(114, 9, 183, 0.05));
        }
        
        .item-card.in-transit {
            border-color: var(--info);
            background: linear-gradient(135deg, rgba(52, 152, 219, 0.05), rgba(41, 128, 185, 0.05));
        }
        
        .item-card.fulfilled {
            border-color: var(--success);
            background: linear-gradient(135deg, rgba(46, 204, 113, 0.05), rgba(39, 174, 96, 0.05));
        }
        
        .item-checkbox {
            position: absolute;
            top: 15px;
            right: 15px;
            transform: scale(1.3);
            accent-color: var(--primary);
        }
        
        .item-name {
            font-weight: 600;
            color: var(--dark);
            font-size: 1.1rem;
            margin-bottom: 10px;
        }
        
        .item-details {
            display: flex;
            gap: 15px;
            margin: 10px 0;
            font-size: 0.9rem;
        }
        
        .item-detail {
            display: flex;
            align-items: center;
            gap: 5px;
            color: var(--gray);
        }
        
        /* Forms */
        .form-container {
            background: white;
            padding: 30px;
            border-radius: var(--border-radius);
            margin: 30px 0;
            box-shadow: var(--shadow);
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 1rem;
            transition: var(--transition);
        }
        
        .form-control:focus {
            border-color: var(--primary);
            outline: none;
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.1);
        }
        
        /* Buttons */
        .btn {
            padding: 14px 28px;
            border: none;
            border-radius: 50px;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: var(--transition);
            text-decoration: none;
        }
        
        .btn-primary {
            background: var(--primary);
            color: white;
        }
        
        .btn-primary:hover {
            background: var(--primary-light);
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(67, 97, 238, 0.3);
        }
        
        .btn-success {
            background: var(--success);
            color: white;
        }
        
        .btn-success:hover {
            background: #27ae60;
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(46, 204, 113, 0.3);
        }
        
        .btn-warning {
            background: var(--warning);
            color: white;
        }
        
        .btn-info {
            background: var(--info);
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
        
        /* Upload Area */
        .upload-area {
            border: 2px dashed var(--primary);
            border-radius: 8px;
            padding: 40px 20px;
            text-align: center;
            cursor: pointer;
            transition: var(--transition);
            margin: 20px 0;
        }
        
        .upload-area:hover {
            background: #f8f9fa;
            border-color: var(--success);
        }
        
        .upload-area i {
            font-size: 2.5rem;
            color: var(--primary);
            margin-bottom: 15px;
        }
        
        .image-preview {
            width: 200px;
            height: 150px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            margin: 15px auto;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .image-preview img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }
        
        /* Navigation */
        .victim-nav {
            display: flex;
            justify-content: space-between;
            margin: 30px 0;
            gap: 15px;
        }
        
        .nav-btn {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 24px;
        }
        
        /* Status Badges */
        .status-badge {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .badge-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .badge-in-transit {
            background: #d1ecf1;
            color: #0c5460;
        }
        
        .badge-delivered {
            background: #d4edda;
            color: #155724;
        }
        
        /* Messages */
        .alert {
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            display: flex;
            align-items: flex-start;
            gap: 15px;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid #e74c3c;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid #2ecc71;
        }
        
        .alert-icon {
            font-size: 1.5rem;
        }
        
        /* Empty States */
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
        
        /* Responsive */
        @media (max-width: 768px) {
            .container {
                padding: 10px;
            }
            
            .header-top {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .page-title {
                font-size: 1.6rem;
            }
            
            .items-grid {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .victim-nav {
                flex-direction: column;
            }
            
            .tracking-timeline {
                padding-left: 30px;
            }
            
            .tracking-timeline::before {
                left: 10px;
            }
            
            .tracking-dot {
                left: -32px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div class="header-top">
                <h1 class="page-title">
                    <i class="fas fa-truck-loading"></i>
                    Execute Distribution #DIST<?php echo str_pad($distribution_id, 7, '0', STR_PAD_LEFT); ?>
                </h1>
                <a href="http://10.147.17.30:8000/volunteer_dashboard.php" class="btn btn-back">
                    <i class="fas fa-arrow-left"></i>
                    Back to Dashboard
                </a>
            </div>
            
            <div style="display: flex; align-items: center; gap: 20px; flex-wrap: wrap;">
                <div>
                    <h3 style="color: var(--dark); margin-bottom: 8px;">
                        <i class="fas fa-user-circle"></i>
                        <?php echo htmlspecialchars($volunteer_details['FullName'] ?? $volunteer_details['fullName'] ?? $volunteer_name); ?>
                    </h3>
                    <?php if ($disaster_details): ?>
                    <div style="color: var(--gray);">
                        <i class="fas fa-exclamation-triangle"></i>
                        <?php echo htmlspecialchars($disaster_details['Disaster_Name'] ?? $disaster_details['name'] ?? 'Disaster'); ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Progress Section -->
        <div class="progress-section">
            <div class="progress-header">
                <h2 style="color: var(--dark);">
                    <i class="fas fa-chart-line"></i>
                    Distribution Progress
                </h2>
                <div style="font-size: 1.2rem; font-weight: 600; color: var(--primary);">
                    <?php echo round($progress, 1); ?>% Complete
                </div>
            </div>
            
            <div class="progress-bar-container">
                <div class="progress-bar-fill" style="width: <?php echo $progress; ?>%;"></div>
            </div>
            
            <div style="display: flex; justify-content: space-between; font-size: 0.9rem; color: var(--gray);">
                <span>Start</span>
                <span>In Progress</span>
                <span>Complete</span>
            </div>
        </div>
        
        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card total">
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-value"><?php echo $distribution_stats['total_families'] ?? 0; ?></div>
                <div class="stat-label">Total Families</div>
            </div>
            
            <div class="stat-card transit">
                <div class="stat-icon">
                    <i class="fas fa-truck-moving"></i>
                </div>
                <div class="stat-value"><?php echo $distribution_stats['in_transit_families'] ?? 0; ?></div>
                <div class="stat-label">In Transit</div>
            </div>
            
            <div class="stat-card delivered">
                <div class="stat-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-value"><?php echo $distribution_stats['completed_families'] ?? 0; ?></div>
                <div class="stat-label">Delivered</div>
            </div>
            
            <div class="stat-card your">
                <div class="stat-icon">
                    <i class="fas fa-user-check"></i>
                </div>
                <div class="stat-value">
                    <?php echo $personal_stats['my_completed_victims'] ?? 0; ?>/<?php echo ($personal_stats['my_completed_victims'] ?? 0) + ($personal_stats['my_in_transit_victims'] ?? 0); ?>
                </div>
                <div class="stat-label">Your Progress</div>
            </div>
        </div>
        
        <!-- Messages -->
        <?php if ($error): ?>
            <div class="alert alert-error">
                <div class="alert-icon">
                    <i class="fas fa-exclamation-circle"></i>
                </div>
                <div>
                    <strong>Error</strong>
                    <p><?php echo $error; ?></p>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success">
                <div class="alert-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div>
                    <strong>Success!</strong>
                    <p>
                        <?php if ($_GET['success'] == 'prepared'): ?>
                            Items prepared for delivery! Status: Dispatched (70%)
                        <?php elseif ($_GET['success'] == 'delivered'): ?>
                            Delivery completed successfully! Status: Delivered (100%)
                        <?php elseif ($_GET['success'] == 'tracking_updated'): ?>
                            Tracking status updated successfully!
                        <?php endif; ?>
                    </p>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Victim Navigation -->
        <?php if ($victim_data): ?>
        <div class="victim-nav">
            <a href="execute_distribution.php?distribution_id=<?php echo $distribution_id; ?>&victim_id=<?php echo max(1, $victim_id - 1); ?>" class="btn btn-outline nav-btn">
                <i class="fas fa-arrow-left"></i>
                Previous Victim
            </a>
            <a href="execute_distribution.php?distribution_id=<?php echo $distribution_id; ?>&victim_id=<?php echo $victim_id + 1; ?>" class="btn btn-outline nav-btn">
                Next Victim
                <i class="fas fa-arrow-right"></i>
            </a>
        </div>
        <?php endif; ?>
        
        <!-- Victim Profile -->
        <?php if ($victim_data): ?>
        <div class="victim-profile-card">
            <div class="victim-header">
                <div>
                    <h2 style="color: var(--dark); margin-bottom: 10px;">
                        <i class="fas fa-home"></i>
                        <?php echo htmlspecialchars($victim_data['name']); ?>
                    </h2>
                    <div style="color: var(--gray);">
                        Victim ID: V<?php echo str_pad($victim_data['victim_id'], 6, '0', STR_PAD_LEFT); ?>
                    </div>
                </div>
                
                <div style="display: flex; gap: 10px;">
                    <span class="status-badge badge-pending">
                        <?php echo $victim_data['total_needs'] - $victim_data['fulfilled_needs'] - $victim_data['in_transit_needs']; ?> Pending
                    </span>
                    <span class="status-badge badge-in-transit">
                        <?php echo $victim_data['in_transit_needs']; ?> In Transit
                    </span>
                    <span class="status-badge badge-delivered">
                        <?php echo $victim_data['fulfilled_needs']; ?> Delivered
                    </span>
                </div>
            </div>
            
            <div class="victim-info-grid">
                <div class="info-item">
                    <div class="info-label">
                        <i class="fas fa-map-marker-alt info-icon"></i>
                        Address
                    </div>
                    <div class="info-value"><?php echo htmlspecialchars($victim_data['address']); ?></div>
                </div>
                
                <div class="info-item">
                    <div class="info-label">
                        <i class="fas fa-users info-icon"></i>
                        Family Size
                    </div>
                    <div class="info-value"><?php echo $victim_data['family_size']; ?> people</div>
                </div>
                
                <div class="info-item">
                    <div class="info-label">
                        <i class="fas fa-phone info-icon"></i>
                        Contact Number
                    </div>
                    <div class="info-value"><?php echo htmlspecialchars($victim_data['contact_number']); ?></div>
                </div>
                
                <div class="info-item">
                    <div class="info-label">
                        <i class="fas fa-boxes info-icon"></i>
                        Total Needs
                    </div>
                    <div class="info-value"><?php echo $victim_data['total_needs']; ?> items</div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Tracking Section -->
        <div class="tracking-section">
            <div class="tracking-header">
                <div style="font-size: 2rem; color: var(--primary);">
                    <i class="fas fa-map-marked-alt"></i>
                </div>
                <div>
                    <h2 style="color: var(--dark); margin-bottom: 5px;">Live Tracking</h2>
                    <p style="color: var(--gray);">Update your location during delivery</p>
                </div>
            </div>
            
            <?php if (!empty($tracking_updates)): ?>
            <div class="tracking-timeline">
                <?php foreach ($tracking_updates as $update): ?>
                <div class="tracking-update">
                    <div class="tracking-dot">
                        <?php 
                        $icon = 'fa-truck';
                        if ($update['status'] == 'departed') $icon = 'fa-flag-checkered';
                        if ($update['status'] == 'arrived') $icon = 'fa-check-circle';
                        if ($update['status'] == 'delayed') $icon = 'fa-exclamation-triangle';
                        ?>
                        <i class="fas <?php echo $icon; ?>"></i>
                    </div>
                    <div class="tracking-content">
                        <div class="tracking-location">
                            <?php echo htmlspecialchars($update['current_location']); ?>
                        </div>
                        <?php if (!empty($update['tracking_notes'])): ?>
                        <p style="margin: 10px 0; color: var(--gray);">
                            <?php echo htmlspecialchars($update['tracking_notes']); ?>
                        </p>
                        <?php endif; ?>
                        <?php if (!empty($update['estimated_arrival'])): ?>
                        <div style="color: var(--info); margin: 8px 0;">
                            <i class="fas fa-clock"></i>
                            ETA: <?php echo htmlspecialchars($update['estimated_arrival']); ?>
                        </div>
                        <?php endif; ?>
                        <div class="tracking-time">
                            <i class="far fa-clock"></i>
                            <?php echo date('M j, g:i A', strtotime($update['created_at'])); ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-map-marked-alt"></i>
                <h3 style="color: var(--dark); margin-bottom: 15px;">No tracking updates yet</h3>
                <p style="color: var(--gray);">Start by preparing items for delivery to begin tracking.</p>
            </div>
            <?php endif; ?>
            
            <?php if ($has_in_transit_items || !empty($tracking_updates)): ?>
            <div class="form-container" style="margin-top: 30px;">
                <h3 style="color: var(--dark); margin-bottom: 25px;">
                    <i class="fas fa-edit"></i>
                    Update Your Location
                </h3>
                <form method="POST">
                    <input type="hidden" name="update_tracking" value="1">
                    <input type="hidden" name="victim_id" value="<?php echo $victim_id; ?>">
                    
                    <div class="form-group">
                        <label class="form-label" for="current_location">
                            <i class="fas fa-map-marker-alt"></i>
                            Current Location
                        </label>
                        <input type="text" id="current_location" name="current_location" class="form-control" 
                               placeholder="e.g., Main Street, Highway 101, Near Central Market" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="status_update">
                            <i class="fas fa-flag"></i>
                            Status Update
                        </label>
                        <select id="status_update" name="status_update" class="form-control">
                            <option value="departed">Departed</option>
                            <option value="in_transit" selected>In Transit</option>
                            <option value="arrived">Arrived at Location</option>
                            <option value="delayed">Delayed</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="estimated_arrival">
                            <i class="fas fa-clock"></i>
                            Estimated Arrival (Optional)
                        </label>
                        <input type="text" id="estimated_arrival" name="estimated_arrival" class="form-control" 
                               placeholder="e.g., 30 minutes, 2:30 PM, Tomorrow morning">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="tracking_notes">
                            <i class="fas fa-sticky-note"></i>
                            Notes (Optional)
                        </label>
                        <textarea id="tracking_notes" name="tracking_notes" class="form-control" rows="3" 
                                  placeholder="e.g., Traffic is heavy, Taking alternative route..."></textarea>
                    </div>
                    
                    <button type="submit" class="btn btn-info">
                        <i class="fas fa-save"></i>
                        Update Tracking
                    </button>
                </form>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Items Management -->
        <?php if ($victim_data): ?>
        <div class="items-section">
            <div class="section-header">
                <h2 class="section-title">
                    <i class="fas fa-box-open"></i>
                    Items Management
                </h2>
                <div style="color: var(--gray);">
                    <?php echo $victim_data['total_needs']; ?> total items
                </div>
            </div>
            
            <?php if ($has_pending_items): ?>
            <!-- Prepare Items Form -->
            <div class="form-container">
                <h3 style="color: var(--dark); margin-bottom: 25px;">
                    <i class="fas fa-truck-loading"></i>
                    Prepare Items for Delivery
                </h3>
                <form method="POST" id="prepareForm">
                    <input type="hidden" name="prepare_distribution" value="1">
                    <input type="hidden" name="victim_id" value="<?php echo $victim_id; ?>">
                    
                    <p style="color: var(--gray); margin-bottom: 25px;">
                        Select items to mark as dispatched (70% complete)
                    </p>
                    
                    <div class="items-grid">
                        <?php foreach ($needs_data as $need): 
                            if ($need['need_status'] == 'pending'):
                        ?>
                        <div class="item-card" 
                             onclick="toggleNeed('prepare', '<?php echo addslashes($need['need_id']); ?>')"
                             id="card-<?php echo addslashes($need['need_id']); ?>">
                            
                            <input type="checkbox" 
                                   name="distributed_items[]" 
                                   value="<?php echo htmlspecialchars($need['need_id']); ?>"
                                   class="item-checkbox"
                                   id="checkbox-<?php echo addslashes($need['need_id']); ?>">
                            
                            <div class="item-name"><?php echo htmlspecialchars($need['resource_name']); ?></div>
                            
                            <div class="item-details">
                                <div class="item-detail">
                                    <i class="fas fa-balance-scale"></i>
                                    <?php echo $need['quantity_needed']; ?> <?php echo $need['unit']; ?>
                                </div>
                                <div class="item-detail">
                                    <i class="fas fa-tag"></i>
                                    <?php echo htmlspecialchars($need['type']); ?>
                                </div>
                            </div>
                            
                            <div style="margin-top: 15px;">
                                <span class="status-badge badge-pending">Pending</span>
                            </div>
                        </div>
                        <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                    
                    <div style="display: flex; gap: 15px; margin-top: 30px;">
                        <button type="button" class="btn btn-outline" onclick="selectAll('prepare')">
                            <i class="fas fa-check-double"></i>
                            Select All
                        </button>
                        <button type="submit" class="btn btn-warning">
                            <i class="fas fa-truck-loading"></i>
                            Mark as Dispatched (70%)
                        </button>
                    </div>
                </form>
            </div>
            <?php endif; ?>
            
            <?php if ($has_in_transit_items): ?>
            <!-- Complete Delivery Form -->
            <div class="form-container" style="border-left: 5px solid var(--info);">
                <h3 style="color: var(--dark); margin-bottom: 25px;">
                    <i class="fas fa-check-circle"></i>
                    Complete Delivery
                </h3>
                <form method="POST" id="completeForm" enctype="multipart/form-data">
                    <input type="hidden" name="complete_delivery" value="1">
                    <input type="hidden" name="victim_id" value="<?php echo $victim_id; ?>">
                    
                    <p style="color: var(--gray); margin-bottom: 25px;">
                        Select items to mark as delivered (100% complete)
                    </p>
                    
                    <div class="items-grid">
                        <?php foreach ($needs_data as $need): 
                            if ($need['need_status'] == 'in_transit'):
                        ?>
                        <div class="item-card in-transit"
                             onclick="toggleNeed('deliver', '<?php echo addslashes($need['need_id']); ?>')"
                             id="deliver-card-<?php echo addslashes($need['need_id']); ?>">
                            
                            <input type="checkbox" 
                                   name="delivered_items[]" 
                                   value="<?php echo htmlspecialchars($need['need_id']); ?>"
                                   class="item-checkbox"
                                   id="checkbox-deliver-<?php echo addslashes($need['need_id']); ?>">
                            
                            <div class="item-name"><?php echo htmlspecialchars($need['resource_name']); ?></div>
                            
                            <div class="item-details">
                                <div class="item-detail">
                                    <i class="fas fa-balance-scale"></i>
                                    <?php echo $need['quantity_needed']; ?> <?php echo $need['unit']; ?>
                                </div>
                                <div class="item-detail">
                                    <i class="fas fa-tag"></i>
                                    <?php echo htmlspecialchars($need['type']); ?>
                                </div>
                            </div>
                            
                            <div style="margin-top: 15px;">
                                <span class="status-badge badge-in-transit">In Transit</span>
                            </div>
                        </div>
                        <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="form-group" style="margin-top: 30px;">
                        <label class="form-label" for="delivery_remarks">
                            <i class="fas fa-comment-alt"></i>
                            Delivery Remarks (Optional)
                        </label>
                        <textarea id="delivery_remarks" name="delivery_remarks" class="form-control" rows="3" 
                                  placeholder="Any notes about the delivery..."></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-file-signature"></i>
                            Recipient Confirmation
                        </label>
                        <p style="color: var(--gray); margin-bottom: 15px; font-size: 0.9rem;">
                            Upload a photo of the delivered items or recipient's signature for confirmation.
                            Max size: 5MB. Allowed formats: JPG, PNG, GIF, WebP.
                        </p>
                        
                        <div class="upload-area" onclick="document.getElementById('signature_image').click()">
                            <i class="fas fa-cloud-upload-alt"></i>
                            <p>Click to upload image or drag and drop</p>
                            <p style="font-size: 0.8rem; color: var(--gray); margin-top: 10px;">
                                Max 5MB • JPG, PNG, GIF, WebP
                            </p>
                        </div>
                        
                        <input type="file" 
                               name="signature_image" 
                               id="signature_image" 
                               accept="image/*"
                               style="display: none;"
                               onchange="previewImage(this)">
                        
                        <div class="image-preview" id="imagePreview">
                            <span style="color: var(--gray);">No image selected</span>
                        </div>
                        
                        <div id="uploadError" style="color: var(--danger); font-size: 0.9rem; margin-top: 10px;"></div>
                    </div>
                    
                    <div style="display: flex; gap: 15px; margin-top: 30px;">
                        <button type="button" class="btn btn-outline" onclick="selectAll('deliver')">
                            <i class="fas fa-check-double"></i>
                            Select All
                        </button>
                        <button type="submit" class="btn btn-success" onclick="return validateCompleteForm()">
                            <i class="fas fa-check-circle"></i>
                            Mark as Delivered (100%)
                        </button>
                    </div>
                </form>
            </div>
            <?php endif; ?>
            
            <?php if (!$has_pending_items && !$has_in_transit_items): ?>
            <!-- All Items Completed -->
            <div class="empty-state">
                <i class="fas fa-check-circle" style="color: var(--success); font-size: 4rem;"></i>
                <h3 style="color: var(--dark); margin: 20px 0;">All Items Delivered</h3>
                <p style="color: var(--gray); margin-bottom: 30px;">
                    All items for this victim have been delivered successfully.
                </p>
                <a href="execute_distribution.php?distribution_id=<?php echo $distribution_id; ?>" class="btn btn-primary">
                    <i class="fas fa-arrow-right"></i>
                    Next Victim
                </a>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <?php if (!$victim_data): ?>
        <!-- No Victim Selected -->
        <div class="empty-state">
            <i class="fas fa-user-plus"></i>
            <h3 style="color: var(--dark); margin: 20px 0;">No Victim Selected</h3>
            <p style="color: var(--gray); margin-bottom: 30px;">
                No victims have been assigned to this distribution yet.
            </p>
            <a href="http://10.147.17.30:8000/volunteer_dashboard.php" class="btn btn-primary">
                <i class="fas fa-arrow-left"></i>
                Back to Dashboard
            </a>
        </div>
        <?php endif; ?>
    </div>

    <script>
        // Toggle need selection
        function toggleNeed(type, needId) {
            const card = document.getElementById((type === 'prepare' ? 'card-' : 'deliver-card-') + needId);
            const checkbox = document.getElementById((type === 'prepare' ? 'checkbox-' : 'checkbox-deliver-') + needId);
            
            if (card && checkbox) {
                checkbox.checked = !checkbox.checked;
                card.classList.toggle('selected', checkbox.checked);
                
                // Update UI feedback
                if (checkbox.checked) {
                    card.style.transform = 'translateY(-3px)';
                } else {
                    card.style.transform = 'translateY(0)';
                }
            }
        }
        
        // Select all items
        function selectAll(type) {
            const checkboxes = document.querySelectorAll('input[name="' + (type === 'prepare' ? 'distributed_items[]' : 'delivered_items[]') + '"]');
            let selectedCount = 0;
            
            checkboxes.forEach(cb => {
                if (!cb.checked) {
                    cb.checked = true;
                    selectedCount++;
                    const card = document.getElementById((type === 'prepare' ? 'card-' : 'deliver-card-') + cb.value.replace(/[^\w\s]/gi, ''));
                    if (card) {
                        card.classList.add('selected');
                        card.style.transform = 'translateY(-3px)';
                    }
                }
            });
            
            if (selectedCount > 0) {
                alert(`Selected ${selectedCount} items`);
            } else {
                alert('All items are already selected');
            }
        }
        
        // Image preview function
        function previewImage(input) {
            const preview = document.getElementById('imagePreview');
            const errorDiv = document.getElementById('uploadError');
            
            if (input.files && input.files[0]) {
                const file = input.files[0];
                
                // Validate file size
                if (file.size > 5 * 1024 * 1024) {
                    errorDiv.textContent = 'File size must be less than 5MB.';
                    input.value = '';
                    preview.innerHTML = '<span style="color: var(--gray);">No image selected</span>';
                    return;
                }
                
                // Validate file type
                const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
                if (!allowedTypes.includes(file.type)) {
                    errorDiv.textContent = 'Only JPG, PNG, GIF, and WebP images are allowed.';
                    input.value = '';
                    preview.innerHTML = '<span style="color: var(--gray);">No image selected</span>';
                    return;
                }
                
                errorDiv.textContent = '';
                
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.innerHTML = '<img src="' + e.target.result + '" alt="Preview">';
                }
                reader.readAsDataURL(file);
            } else {
                preview.innerHTML = '<span style="color: var(--gray);">No image selected</span>';
                errorDiv.textContent = '';
            }
        }
        
        // Form validation
        function validateCompleteForm() {
            const checkboxes = document.querySelectorAll('input[name="delivered_items[]"]:checked');
            const fileInput = document.getElementById('signature_image');
            
            if (checkboxes.length === 0) {
                alert('Please select at least one item to mark as delivered.');
                return false;
            }
            
            if (!fileInput.files || fileInput.files.length === 0) {
                alert('Please upload a recipient signature/image as confirmation.');
                return false;
            }
            
            const file = fileInput.files[0];
            
            if (file.size > 5 * 1024 * 1024) {
                alert('File size must be less than 5MB.');
                return false;
            }
            
            const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
            if (!allowedTypes.includes(file.type)) {
                alert('Only JPG, PNG, GIF, and WebP images are allowed.');
                return false;
            }
            
            return confirm(`Mark ${checkboxes.length} items as Delivered (100% complete)?`);
        }
        
        // Form submission handlers
        document.getElementById('prepareForm')?.addEventListener('submit', function(e) {
            const checked = document.querySelectorAll('input[name="distributed_items[]"]:checked');
            if (checked.length === 0) {
                e.preventDefault();
                alert('Please select at least one item to prepare for delivery');
                return false;
            }
            return confirm(`Mark ${checked.length} items as Dispatched (70% complete)?`);
        });
        
        // Drag and drop functionality
        document.addEventListener('DOMContentLoaded', function() {
            const uploadArea = document.querySelector('.upload-area');
            const fileInput = document.getElementById('signature_image');
            
            if (uploadArea && fileInput) {
                ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                    uploadArea.addEventListener(eventName, preventDefaults, false);
                });
                
                function preventDefaults(e) {
                    e.preventDefault();
                    e.stopPropagation();
                }
                
                ['dragenter', 'dragover'].forEach(eventName => {
                    uploadArea.addEventListener(eventName, highlight, false);
                });
                
                ['dragleave', 'drop'].forEach(eventName => {
                    uploadArea.addEventListener(eventName, unhighlight, false);
                });
                
                function highlight() {
                    uploadArea.style.background = '#e8f4fc';
                    uploadArea.style.borderColor = 'var(--success)';
                }
                
                function unhighlight() {
                    uploadArea.style.background = '';
                    uploadArea.style.borderColor = 'var(--primary)';
                }
                
                uploadArea.addEventListener('drop', handleDrop, false);
                
                function handleDrop(e) {
                    const dt = e.dataTransfer;
                    const files = dt.files;
                    
                    if (files.length > 0) {
                        fileInput.files = files;
                        previewImage(fileInput);
                    }
                }
            }
            
            // Auto-refresh every 30 seconds if on victim page with items in transit
            setInterval(() => {
                if (window.location.href.indexOf('victim_id=') > -1 && <?php echo $has_in_transit_items ? 'true' : 'false'; ?>) {
                    console.log('Auto-refresh at ' + new Date().toLocaleTimeString());
                }
            }, 30000);
        });
        
        // Add click effects to cards
        document.querySelectorAll('.item-card').forEach(card => {
            card.addEventListener('click', function(e) {
                if (!e.target.classList.contains('item-checkbox')) {
                    this.style.transform = 'translateY(-3px)';
                    setTimeout(() => {
                        if (!this.classList.contains('selected')) {
                            this.style.transform = 'translateY(0)';
                        }
                    }, 300);
                }
            });
        });
    </script>
</body>
</html>