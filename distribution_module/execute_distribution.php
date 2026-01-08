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
$NGO_API_URL = 'http://10.147.17.30:8000/api_ngo.php'; // NGO API

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
        } elseif (isset($data['ngos'])) {
            $items = $data['ngos'];
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
   GEOLOCATION FUNCTIONS FOR LIVE TRACKING
======================================== */
function getCoordinatesFromAddress($address) {
    // Default Melaka coordinates
    $coordinates = [
        'lat' => 2.1896,
        'lng' => 102.2501
    ];
    
    // Generate unique coordinates based on address hash
    $hash = md5(strtolower($address));
    $lat_offset = (hexdec(substr($hash, 0, 8)) % 1000) / 10000;
    $lng_offset = (hexdec(substr($hash, 8, 8)) % 1000) / 10000;
    
    $coordinates['lat'] += $lat_offset;
    $coordinates['lng'] += $lng_offset;
    
    return $coordinates;
}

function getVictimCoordinates($victim_id, $address) {
    global $db;
    
    $check_query = "SELECT latitude, longitude FROM victim_coordinates WHERE victim_id = ?";
    $stmt = $db->prepare($check_query);
    $stmt->bind_param("i", $victim_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $stmt->close();
        return ['lat' => $row['latitude'], 'lng' => $row['longitude']];
    }
    $stmt->close();
    
    // Generate and store new coordinates
    $coordinates = getCoordinatesFromAddress($address);
    
    $insert_query = "INSERT INTO victim_coordinates (victim_id, latitude, longitude) VALUES (?, ?, ?)";
    $stmt = $db->prepare($insert_query);
    $stmt->bind_param("idd", $victim_id, $coordinates['lat'], $coordinates['lng']);
    $stmt->execute();
    $stmt->close();
    
    return $coordinates;
}

// Create coordinates table if not exists
$create_coordinates_table = "
    CREATE TABLE IF NOT EXISTS victim_coordinates (
        id INT PRIMARY KEY AUTO_INCREMENT,
        victim_id INT NOT NULL,
        latitude DECIMAL(10, 8),
        longitude DECIMAL(11, 8),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_victim (victim_id),
        INDEX (victim_id)
    )
";
$db->query($create_coordinates_table);

// Create live tracking table
$create_live_tracking_table = "
    CREATE TABLE IF NOT EXISTS live_tracking (
        id INT PRIMARY KEY AUTO_INCREMENT,
        volunteer_id INT NOT NULL,
        distribution_id INT NOT NULL,
        latitude DECIMAL(10, 8),
        longitude DECIMAL(11, 8),
        accuracy FLOAT,
        altitude FLOAT,
        heading FLOAT,
        speed FLOAT,
        battery_level INT,
        is_moving BOOLEAN,
        recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (volunteer_id, distribution_id),
        INDEX (recorded_at)
    )
";
$db->query($create_live_tracking_table);

/* ========================================
   FORM SUBMISSION HANDLERS
======================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle live location update via AJAX
    if (isset($_POST['update_live_location'])) {
        header('Content-Type: application/json');
        
        // Check if items have been dispatched first
        $check_dispatched_query = "
            SELECT COUNT(*) as dispatched_count 
            FROM distribution_log 
            WHERE distribution_id = ? 
            AND volunteer_id = ?
            AND victim_id = ?
            AND status = 'in_transit'
        ";
        
        $stmt = $db->prepare($check_dispatched_query);
        $victim_id_for_check = $_POST['victim_id'] ?? $victim_id;
        $stmt->bind_param("iii", $distribution_id, $volunteer_id, $victim_id_for_check);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $dispatched_count = $row['dispatched_count'] ?? 0;
        $stmt->close();
        
        if ($dispatched_count == 0) {
            echo json_encode([
                'success' => false,
                'error' => 'You must mark items as dispatched first before using live tracking. Please use the "Mark as Dispatched" button below.',
                'requires_dispatch' => true
            ]);
            exit;
        }
        
        $lat = floatval($_POST['latitude'] ?? 0);
        $lng = floatval($_POST['longitude'] ?? 0);
        $accuracy = floatval($_POST['accuracy'] ?? 0);
        $battery = intval($_POST['battery_level'] ?? 0);
        $is_moving = isset($_POST['is_moving']) ? 1 : 0;
        
        try {
            $insert_tracking = "
                INSERT INTO live_tracking 
                (volunteer_id, distribution_id, latitude, longitude, accuracy, battery_level, is_moving, recorded_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ";
            $stmt = $db->prepare($insert_tracking);
            $stmt->bind_param("iidddii", $volunteer_id, $distribution_id, $lat, $lng, $accuracy, $battery, $is_moving);
            $stmt->execute();
            $tracking_id = $stmt->insert_id;
            $stmt->close();
            
            // Also update tracking history
            $current_location = "Lat: " . round($lat, 6) . ", Lng: " . round($lng, 6);
            $tracking_notes = "Live GPS tracking - Accuracy: " . round($accuracy, 1) . "m";
            
            $update_tracking = "
                INSERT INTO distribution_tracking 
                (distribution_id, volunteer_id, victim_id, current_location, tracking_notes, status, created_at) 
                VALUES (?, ?, ?, ?, ?, 'in_transit', NOW())
            ";
            $stmt = $db->prepare($update_tracking);
            $victim_id_param = isset($victim_id) ? $victim_id : 0;
            $stmt->bind_param("iiiss", $distribution_id, $volunteer_id, $victim_id_param, $current_location, $tracking_notes);
            $stmt->execute();
            $stmt->close();
            
            echo json_encode([
                'success' => true,
                'tracking_id' => $tracking_id,
                'message' => 'Location updated'
            ]);
            exit;
            
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
            exit;
        }
    }
    
    if (isset($_POST['update_tracking'])) {
        // Check if items have been dispatched first
        $check_dispatched_query = "
            SELECT COUNT(*) as dispatched_count 
            FROM distribution_log 
            WHERE distribution_id = ? 
            AND volunteer_id = ?
            AND victim_id = ?
            AND status = 'in_transit'
        ";
        
        $stmt = $db->prepare($check_dispatched_query);
        $victim_id_for_check = $_POST['victim_id'] ?? $victim_id;
        $stmt->bind_param("iii", $distribution_id, $volunteer_id, $victim_id_for_check);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $dispatched_count = $row['dispatched_count'] ?? 0;
        $stmt->close();
        
        if ($dispatched_count == 0) {
            $error = "You must mark items as dispatched first before updating tracking. Please use the 'Mark as Dispatched' button below.";
        } else {
            $current_location = trim($_POST['current_location'] ?? '');
            $tracking_notes = trim($_POST['tracking_notes'] ?? '');
            $estimated_arrival = trim($_POST['estimated_arrival'] ?? '');
            $status_update = trim($_POST['status_update'] ?? 'in_transit');
            $latitude = floatval($_POST['latitude'] ?? 0);
            $longitude = floatval($_POST['longitude'] ?? 0);
            
            if (!empty($current_location)) {
                try {
                    $victim_id_for_tracking = $_POST['victim_id'] ?? 0;
                    
                    // VALIDATE AND SANITIZE STATUS - Only allow valid ENUM values from database
                    $valid_statuses = ['pending', 'departed', 'in_transit', 'arrived', 'delayed', 'completed', 'cancelled', 'scheduled', 'in_progress'];
                    $db_status = 'in_transit'; // Default
                    
                    // Check if the submitted status is valid
                    if (in_array($status_update, $valid_statuses)) {
                        $db_status = $status_update;
                    } else {
                        // If invalid, default to 'in_transit'
                        $db_status = 'in_transit';
                    }
                    
                    $tracking_query = "INSERT INTO distribution_tracking (distribution_id, volunteer_id, victim_id, current_location, tracking_notes, estimated_arrival, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
                    $stmt = $db->prepare($tracking_query);
                    $stmt->bind_param("iiissss", $distribution_id, $volunteer_id, $victim_id_for_tracking, $current_location, $tracking_notes, $estimated_arrival, $db_status);
                    $stmt->execute();
                    $stmt->close();
                    
                    // Also save to live tracking if coordinates are provided
                    if ($latitude != 0 && $longitude != 0) {
                        $live_tracking_query = "INSERT INTO live_tracking (volunteer_id, distribution_id, latitude, longitude, accuracy, recorded_at) VALUES (?, ?, ?, ?, 10, NOW())";
                        $stmt = $db->prepare($live_tracking_query);
                        $stmt->bind_param("iidd", $volunteer_id, $distribution_id, $latitude, $longitude);
                        $stmt->execute();
                        $stmt->close();
                    }
                    
                    // Update volunteer status in distribution_volunteer table
                    // This table uses different status values, so we need to map them
                    $distribution_volunteer_status = 'Active'; // Default for distribution_volunteer table
                    
                    // Map tracking status to volunteer status
                    if ($db_status == 'departed' || $db_status == 'in_progress') {
                        $distribution_volunteer_status = 'In Progress';
                    } elseif ($db_status == 'arrived') {
                        $distribution_volunteer_status = 'Arrived';
                    } elseif ($db_status == 'delayed') {
                        $distribution_volunteer_status = 'Delayed';
                    } elseif ($db_status == 'completed') {
                        $distribution_volunteer_status = 'Completed';
                    } elseif ($db_status == 'cancelled') {
                        $distribution_volunteer_status = 'Cancelled';
                    }
                    // Note: 'pending', 'scheduled', 'in_transit' keep default 'Active'
                    
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
                    // Log the actual error for debugging
                    error_log("Tracking update error: " . $e->getMessage());
                    error_log("Attempted status value: " . $status_update);
                    error_log("Using status value: " . $db_status);
                }
            } else {
                $error = "Please enter your current location.";
            }
        }
    }
    
    if (isset($_POST['prepare_distribution'])) {
        $selected_items = $_POST['distributed_items'] ?? [];
        $victim_id_post = $_POST['victim_id'] ?? 0;
        
        if (!empty($selected_items) && $victim_id_post) {
            try {
                $db->begin_transaction();
                
                foreach ($selected_items as $need_id) {
                    $check_query = "SELECT id FROM distribution_log WHERE need_id = ? AND victim_id = ? AND distribution_id = ? AND volunteer_id = ?";
                    $stmt = $db->prepare($check_query);
                    $stmt->bind_param("siii", $need_id, $victim_id_post, $distribution_id, $volunteer_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $exists = $result->num_rows > 0;
                    $stmt->close();
                    
                    if ($exists) {
                        $update_query = "UPDATE distribution_log SET status = 'in_transit' WHERE need_id = ? AND victim_id = ? AND distribution_id = ? AND volunteer_id = ?";
                        $stmt = $db->prepare($update_query);
                        $stmt->bind_param("siii", $need_id, $victim_id_post, $distribution_id, $volunteer_id);
                    } else {
                        $insert_query = "INSERT INTO distribution_log (distribution_id, volunteer_id, victim_id, need_id, status, quantity_distributed, created_at) VALUES (?, ?, ?, ?, 'in_transit', 1, NOW())";
                        $stmt = $db->prepare($insert_query);
                        $stmt->bind_param("iiis", $distribution_id, $volunteer_id, $victim_id_post, $need_id);
                    }
                    
                    $stmt->execute();
                    $stmt->close();
                }
                
                $db->commit();
                
                $update_items_query = "UPDATE distribution_items SET status = 'Dispatched' WHERE distribution_id = ? AND victim_id = ?";
                $stmt = $db->prepare($update_items_query);
                $stmt->bind_param("ii", $distribution_id, $victim_id_post);
                $stmt->execute();
                $stmt->close();
                
                // Get NGO details for the departure location
                $ngo_address = "Distribution Center"; // Default
                if (isset($ngo_details['Address'])) {
                    $ngo_address = $ngo_details['Address'] . " (" . $ngo_details['NGOName'] . ")";
                }
                
                $initial_tracking_query = "INSERT INTO distribution_tracking (distribution_id, volunteer_id, victim_id, current_location, tracking_notes, status, created_at) VALUES (?, ?, ?, ?, 'Items loaded and ready for delivery', 'departed', NOW())";
                $stmt = $db->prepare($initial_tracking_query);
                $stmt->bind_param("iiis", $distribution_id, $volunteer_id, $victim_id_post, $ngo_address);
                $stmt->execute();
                $stmt->close();
                
                header("Location: execute_distribution.php?distribution_id=$distribution_id&victim_id=$victim_id_post&success=prepared");
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
        $victim_id_post = $_POST['victim_id'] ?? 0;
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
                    $filename = 'signature_' . $distribution_id . '_' . $victim_id_post . '_' . time() . '.' . $file_extension;
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
                    $stmt->bind_param("siii", $need_id, $victim_id_post, $distribution_id, $volunteer_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $exists = $result->num_rows > 0;
                    $stmt->close();
                    
                    if ($exists) {
                        $update_query = "UPDATE distribution_log SET status = 'completed', remarks = ?, signature_url = ? WHERE need_id = ? AND victim_id = ? AND distribution_id = ? AND volunteer_id = ?";
                        $stmt = $db->prepare($update_query);
                        $stmt->bind_param("sssiii", $delivery_remarks, $signature_image_path, $need_id, $victim_id_post, $distribution_id, $volunteer_id);
                    } else {
                        $insert_query = "INSERT INTO distribution_log (distribution_id, volunteer_id, victim_id, need_id, status, quantity_distributed, remarks, signature_url, created_at) VALUES (?, ?, ?, ?, 'completed', 1, ?, ?, NOW())";
                        $stmt = $db->prepare($insert_query);
                        $stmt->bind_param("iiisss", $distribution_id, $volunteer_id, $victim_id_post, $need_id, $delivery_remarks, $signature_image_path);
                    }
                    
                    $stmt->execute();
                    $stmt->close();
                }
                
                $db->commit();
                
                $update_items_query = "UPDATE distribution_items SET status = 'Delivered' WHERE distribution_id = ? AND victim_id = ?";
                $stmt = $db->prepare($update_items_query);
                $stmt->bind_param("ii", $distribution_id, $victim_id_post);
                $stmt->execute();
                $stmt->close();
                
                header("Location: execute_distribution.php?distribution_id=$distribution_id&victim_id=$victim_id_post&success=delivered");
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

// Get NGO details for this volunteer
$ngo_details = null;
if ($volunteer_details && isset($volunteer_details['AssignedNGO'])) {
    $all_ngos = fetchAllFromAPI($NGO_API_URL);
    if ($all_ngos['success'] && !empty($all_ngos['data'])) {
        foreach ($all_ngos['data'] as $ngo) {
            $ngo_id_from_api = $ngo['NGOID'] ?? 
                              $ngo['NGO_ID'] ?? 
                              $ngo['id'] ?? 0;
            if (intval($ngo_id_from_api) == $volunteer_details['AssignedNGO']) {
                $ngo_details = $ngo;
                break;
            }
        }
    }
}

// Get NGO coordinates for map (starting point)
$ngo_coordinates = null;
$ngo_address_for_display = "Distribution Center";
if ($ngo_details) {
    $ngo_address_for_display = $ngo_details['NGOName'] . " - " . $ngo_details['Address'];
    $ngo_coordinates = getCoordinatesFromAddress($ngo_details['Address']);
}

// Update volunteer status to 'Active' if 'Assigned'
if ($assignment['status'] == 'Assigned') {
    $update_status = "UPDATE distribution_volunteer SET status = 'Active' WHERE distribution_id = ? AND volunteer_id = ?";
    $stmt = $db->prepare($update_status);
    $stmt->bind_param("ii", $distribution_id, $volunteer_id);
    $stmt->execute();
    $stmt->close();
    
    $update_distribution_status = "UPDATE distribution SET status = 'in_transit' WHERE distribution_id = ?";
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
        
        // Get victim coordinates for map
        $victim_coordinates = getVictimCoordinates($victim_id, $victim_data['address']);
        $victim_data['latitude'] = $victim_coordinates['lat'];
        $victim_data['longitude'] = $victim_coordinates['lng'];
        
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
   CHECK IF ITEMS HAVE BEEN DISPATCHED FOR TRACKING
======================================== */
$check_dispatched_query = "
    SELECT COUNT(*) as dispatched_count 
    FROM distribution_log 
    WHERE distribution_id = ? 
    AND volunteer_id = ?
    AND victim_id = ?
    AND status = 'in_transit'
";

$stmt = $db->prepare($check_dispatched_query);
$stmt->bind_param("iii", $distribution_id, $volunteer_id, $victim_id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$items_dispatched = ($row['dispatched_count'] ?? 0) > 0;
$dispatched_count = $row['dispatched_count'] ?? 0;
$stmt->close();

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

// Get latest live tracking data
$live_tracking_query = "
    SELECT * FROM live_tracking 
    WHERE volunteer_id = ? 
    AND distribution_id = ?
    ORDER BY recorded_at DESC
    LIMIT 1
";

$stmt = $db->prepare($live_tracking_query);
$stmt->bind_param("ii", $volunteer_id, $distribution_id);
$stmt->execute();
$result = $stmt->get_result();
$live_location = $result->fetch_assoc();
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
    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
          integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY="
          crossorigin=""/>
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
        
        /* Dispatch Warning */
        .dispatch-warning {
            background: linear-gradient(135deg, #ffeaa7, #fdcb6e);
            border: 3px solid #e17055;
            border-radius: var(--border-radius);
            padding: 25px;
            margin: 25px 0;
            box-shadow: var(--shadow);
            animation: pulse-warning 2s infinite;
        }
        
        .dispatch-warning-content {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .warning-icon {
            width: 70px;
            height: 70px;
            background: #e17055;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            flex-shrink: 0;
        }
        
        .warning-text h3 {
            color: #d63031;
            margin-bottom: 10px;
            font-size: 1.4rem;
        }
        
        .warning-text p {
            color: #636e72;
            line-height: 1.6;
        }
        
        .dispatch-steps {
            background: white;
            padding: 20px;
            border-radius: 8px;
            border-left: 4px solid #00b894;
            margin-top: 20px;
        }
        
        .step-number {
            display: inline-block;
            width: 30px;
            height: 30px;
            background: #00b894;
            color: white;
            border-radius: 50%;
            text-align: center;
            line-height: 30px;
            margin-right: 10px;
            font-weight: bold;
        }
        
        /* Map Tracking Section */
        .map-tracking-section {
            background: white;
            padding: 30px;
            border-radius: var(--border-radius);
            margin: 30px 0;
            box-shadow: var(--shadow);
        }
        
        .tracking-disabled {
            opacity: 0.6;
            position: relative;
            pointer-events: none;
        }
        
        .tracking-disabled::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255,255,255,0.8);
            z-index: 10;
            border-radius: var(--border-radius);
        }
        
        .disabled-message {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            padding: 20px 30px;
            border-radius: 8px;
            border: 3px solid var(--warning);
            text-align: center;
            z-index: 11;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            animation: pulse-gentle 2s infinite;
        }
        
        .tracking-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        /* Map Container */
        .map-container {
            height: 400px;
            border-radius: var(--border-radius);
            overflow: hidden;
            margin: 20px 0;
            position: relative;
            border: 3px solid var(--primary);
        }
        
        #liveMap {
            height: 100%;
            width: 100%;
        }
        
        .map-instructions {
            background: rgba(255, 255, 255, 0.95);
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
            border-left: 4px solid var(--info);
            font-size: 0.95rem;
        }
        
        /* Location Display */
        .location-display {
            background: var(--light);
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            border: 2px solid var(--primary);
        }
        
        .location-text {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .location-text i {
            color: var(--primary);
            font-size: 1.5rem;
        }
        
        .location-description {
            color: var(--gray);
            font-size: 0.95rem;
            line-height: 1.5;
        }
        
        /* Location Search Box */
        .location-search-container {
            margin: 15px 0;
            position: relative;
        }
        
        .location-search-box {
            width: 100%;
            padding: 12px 45px 12px 15px;
            border: 2px solid var(--primary);
            border-radius: 8px;
            font-size: 1rem;
            transition: var(--transition);
        }
        
        .location-search-box:focus {
            outline: none;
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.1);
        }
        
        .location-search-button {
            position: absolute;
            right: 5px;
            top: 5px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 6px;
            padding: 8px 12px;
            cursor: pointer;
            transition: var(--transition);
        }
        
        .location-search-button:hover {
            background: var(--primary-light);
        }
        
        /* Search Results */
        .search-results {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 2px solid var(--primary);
            border-radius: 0 0 8px 8px;
            max-height: 200px;
            overflow-y: auto;
            z-index: 1000;
            display: none;
        }
        
        .search-result-item {
            padding: 10px 15px;
            cursor: pointer;
            transition: var(--transition);
            border-bottom: 1px solid #eee;
        }
        
        .search-result-item:hover {
            background: var(--light);
        }
        
        .search-result-item:last-child {
            border-bottom: none;
        }
        
        /* Simple Form */
        .simple-form {
            background: white;
            padding: 25px;
            border-radius: var(--border-radius);
            margin: 25px 0;
            box-shadow: var(--shadow);
            border: 2px solid var(--info);
        }
        
        .form-step {
            margin-bottom: 30px;
        }
        
        .step-title {
            color: var(--dark);
            font-size: 1.3rem;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .step-title i {
            color: var(--primary);
            font-size: 1.5rem;
        }
        
        .step-description {
            color: var(--gray);
            font-size: 0.95rem;
            margin-bottom: 20px;
            line-height: 1.5;
        }
        
        /* Status Buttons */
        .status-buttons {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 10px;
            margin: 20px 0;
        }
        
        .status-btn {
            background: white;
            border: 2px solid var(--primary);
            color: var(--primary);
            padding: 15px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
            text-align: center;
        }
        
        .status-btn:hover {
            background: var(--primary-light);
            color: white;
            transform: translateY(-2px);
        }
        
        .status-btn.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }
        
        .status-icon {
            font-size: 1.5rem;
        }
        
        .status-label {
            font-size: 0.9rem;
        }
        
        /* Form Controls */
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
        
        .form-control-textarea {
            min-height: 100px;
            resize: vertical;
        }
        
        /* Location Suggestions */
        .location-suggestions {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 10px;
            margin: 15px 0;
        }
        
        .location-suggestion {
            background: #f8f9fa;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            padding: 12px;
            cursor: pointer;
            transition: var(--transition);
            text-align: center;
            font-weight: 500;
        }
        
        .location-suggestion:hover {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
            transform: translateY(-2px);
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
        
        .btn-warning:hover {
            background: #e67e22;
            transform: translateY(-2px);
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
        
        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
            border-left: 4px solid var(--info);
        }
        
        .alert-icon {
            font-size: 1.5rem;
        }
        
        /* Item Cards */
        .item-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }
        
        .item-card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            border: 2px solid #e0e0e0;
            cursor: pointer;
            transition: var(--transition);
        }
        
        .item-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .item-card.selected {
            border-color: var(--primary);
            background: linear-gradient(135deg, rgba(67, 97, 238, 0.05), rgba(114, 9, 183, 0.05));
        }
        
        .item-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 15px;
        }
        
        .item-name {
            font-weight: 600;
            color: var(--dark);
            font-size: 1.1rem;
        }
        
        .item-details {
            display: flex;
            gap: 15px;
            font-size: 0.9rem;
            color: var(--gray);
            margin-bottom: 15px;
        }
        
        .item-detail {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        /* Victim Info */
        .victim-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin: 25px 0;
        }
        
        .info-item {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            border-left: 4px solid var(--primary);
        }
        
        .info-label {
            color: var(--gray);
            font-size: 0.9rem;
            font-weight: 600;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .info-value {
            color: var(--dark);
            font-size: 1.1rem;
            font-weight: 600;
        }
        
        /* Upload Area */
        .upload-area {
            border: 2px dashed var(--primary);
            border-radius: 8px;
            padding: 30px;
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
        
        /* Loading Spinner */
        .loading-spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255,255,255,0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 1s ease-in-out infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        /* Animations */
        @keyframes pulse-warning {
            0% {
                box-shadow: 0 10px 30px rgba(225, 112, 85, 0.3);
            }
            50% {
                box-shadow: 0 15px 40px rgba(225, 112, 85, 0.5);
            }
            100% {
                box-shadow: 0 10px 30px rgba(225, 112, 85, 0.3);
            }
        }
        
        @keyframes pulse-gentle {
            0% {
                transform: translate(-50%, -50%) scale(1);
            }
            50% {
                transform: translate(-50%, -50%) scale(1.02);
            }
            100% {
                transform: translate(-50%, -50%) scale(1);
            }
        }
        
        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        @keyframes slideOut {
            from {
                transform: translateX(0);
                opacity: 1;
            }
            to {
                transform: translateX(100%);
                opacity: 0;
            }
        }
        
        /* Map Marker Animation */
        .volunteer-marker {
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% {
                box-shadow: 0 0 0 0 rgba(67, 97, 238, 0.7);
            }
            70% {
                box-shadow: 0 0 0 10px rgba(67, 97, 238, 0);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(67, 97, 238, 0);
            }
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
            
            .map-container {
                height: 300px;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .status-buttons {
                grid-template-columns: 1fr;
            }
            
            .location-suggestions {
                grid-template-columns: 1fr;
            }
            
            .item-grid {
                grid-template-columns: 1fr;
            }
            
            .victim-info-grid {
                grid-template-columns: 1fr;
            }
            
            .dispatch-warning-content {
                flex-direction: column;
                text-align: center;
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
                    <i class="fas fa-truck"></i>
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
                    <?php if ($ngo_details): ?>
                    <div style="color: var(--info); margin-top: 5px;">
                        <i class="fas fa-hands-helping"></i>
                        <?php echo htmlspecialchars($ngo_details['NGOName'] ?? 'NGO'); ?>
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
                            Items prepared for delivery! Status: Dispatched
                        <?php elseif ($_GET['success'] == 'delivered'): ?>
                            Delivery completed successfully!
                        <?php elseif ($_GET['success'] == 'tracking_updated'): ?>
                            Tracking status updated successfully!
                        <?php endif; ?>
                    </p>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Dispatch Warning (if items not dispatched yet) -->
        <?php if ($has_pending_items && !$items_dispatched): ?>
        <div class="dispatch-warning">
            <div class="dispatch-warning-content">
                <div class="warning-icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="warning-text">
                    <h3>⚠️ ACTION REQUIRED: Dispatch Items First</h3>
                    <p>Before you can use live tracking or update your location status, you must mark items as dispatched.</p>
                    <p><strong>This confirms you have physically loaded the items and are leaving the distribution center.</strong></p>
                </div>
            </div>
            
            <div class="dispatch-steps">
                <p style="margin-bottom: 15px; font-weight: 600; color: var(--dark);">
                    <i class="fas fa-list-ol"></i> Follow these steps:
                </p>
                <div style="display: flex; gap: 20px; flex-wrap: wrap;">
                    <div style="flex: 1; min-width: 250px;">
                        <div class="step-number">1</div>
                        <span style="font-weight: 600; color: var(--dark);">Select items below</span>
                        <p style="color: var(--gray); margin-top: 5px; font-size: 0.9rem;">
                            Check all items you have loaded
                        </p>
                    </div>
                    <div style="flex: 1; min-width: 250px;">
                        <div class="step-number">2</div>
                        <span style="font-weight: 600; color: var(--dark);">Click "Mark as Dispatched"</span>
                        <p style="color: var(--gray); margin-top: 5px; font-size: 0.9rem;">
                            Confirm you're leaving with items
                        </p>
                    </div>
                    <div style="flex: 1; min-width: 250px;">
                        <div class="step-number">3</div>
                        <span style="font-weight: 600; color: var(--dark);">Use live tracking</span>
                        <p style="color: var(--gray); margin-top: 5px; font-size: 0.9rem;">
                            Update your location on the map
                        </p>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Map Tracking Section -->
        <div class="map-tracking-section <?php echo (!$items_dispatched && $has_pending_items) ? 'tracking-disabled' : ''; ?>">
            <?php if (!$items_dispatched && $has_pending_items): ?>
            <div class="disabled-message">
                <div style="font-size: 3rem; color: var(--warning); margin-bottom: 15px;">
                    <i class="fas fa-lock"></i>
                </div>
                <h3 style="color: var(--dark); margin-bottom: 10px;">Live Tracking Locked</h3>
                <p style="color: var(--gray); margin-bottom: 20px; max-width: 300px;">
                    Please mark items as dispatched first to unlock live tracking features.
                </p>
                <a href="#items-section" class="btn btn-warning" style="padding: 10px 20px;">
                    <i class="fas fa-arrow-down"></i>
                    Go to Items Section
                </a>
            </div>
            <?php endif; ?>
            
            <div class="tracking-header">
                <div style="font-size: 2rem; color: var(--primary);">
                    <i class="fas fa-map-marked-alt"></i>
                </div>
                <div>
                    <h2 style="color: var(--dark); margin-bottom: 5px;">Live Tracking Map</h2>
                    <p style="color: var(--gray);">Drag your marker to update location OR search for locations</p>
                </div>
            </div>
            
            <div class="map-instructions">
                <i class="fas fa-info-circle" style="color: var(--info); margin-right: 10px;"></i>
                <strong>Important:</strong><br>
                1. Your <strong>starting location</strong> is set to your assigned NGO: <strong><?php echo htmlspecialchars($ngo_details['NGOName'] ?? 'Distribution Center'); ?></strong><br>
                2. The <strong>red marker</strong> shows the victim's delivery location<br>
                3. <strong>Drag the blue marker</strong> or <strong>search</strong> to update your current location
            </div>
            
            <!-- Location Search Box -->
            <div class="location-search-container">
                <input type="text" 
                       id="locationSearch" 
                       class="location-search-box" 
                       placeholder="Search for any location (e.g., University Teknikal Malaysia Melaka, Ayer Keroh, etc.)">
                <button type="button" class="location-search-button" onclick="searchLocation()">
                    <i class="fas fa-search"></i> Search
                </button>
                <div class="search-results" id="searchResults"></div>
            </div>
            
            <!-- Interactive Map -->
            <div class="map-container">
                <div id="liveMap"></div>
            </div>
            
            <!-- Current Location Display -->
            <div class="location-display">
                <div class="location-text">
                    <i class="fas fa-map-marker-alt"></i>
                    <span id="currentLocationTitle">Your Current Location</span>
                </div>
                <div class="location-description" id="currentLocationText">
                    <?php if ($ngo_details): ?>
                    Starting from: <?php echo htmlspecialchars($ngo_details['NGOName']); ?> - <?php echo htmlspecialchars($ngo_details['Address']); ?>
                    <?php else: ?>
                    Drag the marker on the map or search for a location above
                    <?php endif; ?>
                </div>
                <div style="margin-top: 10px; display: none;" id="locationLoading">
                    <span class="loading-spinner" style="border-top-color: var(--primary);"></span>
                    <span style="margin-left: 10px; color: var(--info); font-size: 0.9rem;">Detecting location name...</span>
                </div>
            </div>
            
            <!-- Simple Tracking Form -->
            <div class="simple-form">
                <form method="POST" id="trackingForm">
                    <input type="hidden" name="update_tracking" value="1">
                    <input type="hidden" name="victim_id" value="<?php echo $victim_id; ?>">
                    <input type="hidden" name="latitude" id="formLatitude" value="<?php echo $live_location['latitude'] ?? ($ngo_coordinates['lat'] ?? 2.1896); ?>">
                    <input type="hidden" name="longitude" id="formLongitude" value="<?php echo $live_location['longitude'] ?? ($ngo_coordinates['lng'] ?? 102.2501); ?>">
                    
                    <!-- Step 1: Status -->
                    <div class="form-step">
                        <div class="step-title">
                            <i class="fas fa-flag"></i>
                            Step 1: What's your status?
                        </div>
                        <div class="step-description">
                            Select your current delivery status
                        </div>
                        
                        <div class="status-buttons">
                            <button type="button" class="status-btn" data-status="departed" onclick="selectStatus('departed', this)">
                                <i class="fas fa-flag-checkered status-icon"></i>
                                <span class="status-label">Just Departed</span>
                            </button>
                            <button type="button" class="status-btn active" data-status="in_transit" onclick="selectStatus('in_transit', this)">
                                <i class="fas fa-truck-moving status-icon"></i>
                                <span class="status-label">On The Way</span>
                            </button>
                            <button type="button" class="status-btn" data-status="arrived" onclick="selectStatus('arrived', this)">
                                <i class="fas fa-check-circle status-icon"></i>
                                <span class="status-label">Arrived</span>
                            </button>
                            <button type="button" class="status-btn" data-status="delayed" onclick="selectStatus('delayed', this)">
                                <i class="fas fa-exclamation-triangle status-icon"></i>
                                <span class="status-label">Delayed</span>
                            </button>
                        </div>
                        <input type="hidden" name="status_update" id="statusUpdate" value="in_transit">
                    </div>
                    
                    <!-- Step 2: Location Details -->
                    <div class="form-step">
                        <div class="step-title">
                            <i class="fas fa-map-marker-alt"></i>
                            Step 2: Where are you now?
                        </div>
                        <div class="step-description">
                            Your location is auto-filled from the map using real location names. You can edit it below.
                            <strong>Changes here will update the display above.</strong>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="current_location">
                                <i class="fas fa-location-dot"></i>
                                Current Location
                            </label>
                            <input type="text" id="current_location" name="current_location" class="form-control" 
                                   placeholder="e.g., <?php echo htmlspecialchars($ngo_details['Address'] ?? 'NGO Address'); ?>" 
                                   required>
                            <div style="font-size: 0.85rem; color: var(--gray); margin-top: 5px;">
                                Drag the marker, search for a location above, OR type your location here. The display above will update in real-time.
                            </div>
                        </div>
                    
                    <!-- Step 3: Additional Info -->
                    <div class="form-step">
                        <div class="step-title">
                            <i class="fas fa-info-circle"></i>
                            Step 3: Additional Information (Optional)
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="estimated_arrival">
                                <i class="fas fa-clock"></i>
                                Estimated Arrival Time
                            </label>
                            <input type="text" id="estimated_arrival" name="estimated_arrival" class="form-control" 
                                   placeholder="e.g., 30 minutes, 2:30 PM, In about an hour">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="tracking_notes">
                                <i class="fas fa-sticky-note"></i>
                                Notes
                            </label>
                            <textarea id="tracking_notes" name="tracking_notes" class="form-control form-control-textarea" 
                                      placeholder="Any additional information about your journey... (e.g., Road conditions, weather, any issues encountered)"></textarea>
                        </div>
                    </div>
                    
                    <!-- Submit Button -->
                    <div style="text-align: center; margin-top: 30px;">
                        <button type="submit" class="btn btn-primary" style="padding: 15px 40px; font-size: 1.1rem;"
                                <?php echo (!$items_dispatched && $has_pending_items) ? 'disabled' : ''; ?>>
                            <i class="fas fa-paper-plane"></i>
                            <?php echo (!$items_dispatched && $has_pending_items) ? 'Mark Items as Dispatched First' : 'Update My Location & Status'; ?>
                        </button>
                        
                        <?php if (!$items_dispatched && $has_pending_items): ?>
                        <div style="margin-top: 10px; color: var(--warning); font-size: 0.9rem;">
                            <i class="fas fa-exclamation-circle"></i>
                            Please mark items as dispatched below before using tracking
                        </div>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Victim Information -->
        <?php if ($victim_data): ?>
        <div class="simple-form" style="border-color: var(--success);">
            <div class="step-title" style="border-color: var(--success);">
                <i class="fas fa-home"></i>
                Delivery Destination
            </div>
            
            <div class="victim-info-grid">
                <div class="info-item">
                    <div class="info-label">
                        <i class="fas fa-user"></i>
                        Victim Name
                    </div>
                    <div class="info-value">
                        <?php echo htmlspecialchars($victim_data['name']); ?>
                    </div>
                </div>
                
                <div class="info-item">
                    <div class="info-label">
                        <i class="fas fa-map-marker-alt"></i>
                        Delivery Address
                    </div>
                    <div class="info-value">
                        <?php echo htmlspecialchars($victim_data['address']); ?>
                    </div>
                </div>
                
                <div class="info-item">
                    <div class="info-label">
                        <i class="fas fa-users"></i>
                        Family Size
                    </div>
                    <div class="info-value">
                        <?php echo $victim_data['family_size']; ?> people
                    </div>
                </div>
                
                <div class="info-item">
                    <div class="info-label">
                        <i class="fas fa-phone"></i>
                        Contact Number
                    </div>
                    <div class="info-value">
                        <?php echo htmlspecialchars($victim_data['contact_number']); ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Items Management Section -->
        <?php if ($victim_data && ($has_pending_items || $has_in_transit_items)): ?>
        <div class="simple-form" style="margin-top: 30px; border-color: var(--warning);" id="items-section">
            <div class="step-title" style="border-color: var(--warning);">
                <i class="fas fa-boxes"></i>
                Items Management
            </div>
            
            <?php if ($has_pending_items): ?>
            <div class="alert alert-info">
                <div class="alert-icon">
                    <i class="fas fa-info-circle"></i>
                </div>
                <div>
                    <strong>Ready to deliver?</strong> Select items to mark as dispatched when you're ready to leave the <strong><?php echo htmlspecialchars($ngo_details['NGOName'] ?? 'Distribution Center'); ?></strong>.
                    <?php if (!$items_dispatched): ?>
                    <br><strong style="color: var(--warning);">This must be done before using live tracking.</strong>
                    <?php endif; ?>
                </div>
            </div>
            
            <form method="POST" id="prepareForm">
                <input type="hidden" name="prepare_distribution" value="1">
                <input type="hidden" name="victim_id" value="<?php echo $victim_id; ?>">
                
                <div class="item-grid">
                    <?php foreach ($needs_data as $need): 
                        if ($need['need_status'] == 'pending'):
                    ?>
                    <div class="item-card" 
                         onclick="toggleItemSelection(this, '<?php echo htmlspecialchars($need['need_id']); ?>')"
                         id="item-<?php echo htmlspecialchars($need['need_id']); ?>">
                        <div class="item-header">
                            <div class="item-name">
                                <?php echo htmlspecialchars($need['resource_name']); ?>
                            </div>
                            <input type="checkbox" 
                                   name="distributed_items[]" 
                                   value="<?php echo htmlspecialchars($need['need_id']); ?>"
                                   style="transform: scale(1.3); accent-color: var(--primary);"
                                   onchange="updateItemCard(this)">
                        </div>
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
                            <span class="badge-pending">Ready for Dispatch</span>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php endforeach; ?>
                </div>
                
                <div style="display: flex; gap: 15px; margin-top: 25px; flex-wrap: wrap;">
                    <button type="button" class="btn btn-outline" onclick="selectAllItems()">
                        <i class="fas fa-check-double"></i>
                        Select All Items
                    </button>
                    <button type="submit" class="btn btn-warning">
                        <i class="fas fa-truck-loading"></i>
                        Mark as Dispatched (Leaving Now)
                    </button>
                </div>
            </form>
            <?php endif; ?>
            
            <?php if ($has_in_transit_items): ?>
            <div style="margin-top: 40px; padding-top: 30px; border-top: 2px solid var(--info);">
                <div class="step-title" style="border-color: var(--info); color: var(--info);">
                    <i class="fas fa-check-circle"></i>
                    Complete Delivery
                </div>
                <div class="alert alert-info">
                    <div class="alert-icon">
                        <i class="fas fa-info-circle"></i>
                    </div>
                    <div>
                        <strong>Arrived at destination?</strong> Mark items as delivered and get recipient confirmation.
                    </div>
                </div>
                
                <form method="POST" id="completeForm" enctype="multipart/form-data">
                    <input type="hidden" name="complete_delivery" value="1">
                    <input type="hidden" name="victim_id" value="<?php echo $victim_id; ?>">
                    
                    <div class="item-grid">
                        <?php foreach ($needs_data as $need): 
                            if ($need['need_status'] == 'in_transit'):
                        ?>
                        <div class="item-card" style="border-color: var(--info); background: #e8f4fc;"
                             onclick="toggleDeliverySelection(this, '<?php echo htmlspecialchars($need['need_id']); ?>')"
                             id="delivery-<?php echo htmlspecialchars($need['need_id']); ?>">
                            <div class="item-header">
                                <div class="item-name">
                                    <?php echo htmlspecialchars($need['resource_name']); ?>
                                </div>
                                <input type="checkbox" 
                                       name="delivered_items[]" 
                                       value="<?php echo htmlspecialchars($need['need_id']); ?>"
                                       style="transform: scale(1.3); accent-color: var(--success);"
                                       onchange="updateDeliveryCard(this)">
                            </div>
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
                                <span class="badge-in-transit">In Transit</span>
                            </div>
                        </div>
                        <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- DELIVERY NOTES -->
                    <div class="form-group" style="margin-top: 30px;">
                        <label class="form-label" for="delivery_remarks">
                            <i class="fas fa-comment-alt"></i>
                            Delivery Notes (Optional)
                        </label>
                        <textarea id="delivery_remarks" name="delivery_remarks" class="form-control form-control-textarea" 
                                  placeholder="Any notes about the delivery... (e.g., Recipient's condition, special instructions, observations)"></textarea>
                    </div>
                    
                    <!-- RECIPIENT CONFIRMATION -->
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-file-signature"></i>
                            Recipient Confirmation
                        </label>
                        <div class="upload-area" onclick="document.getElementById('signature_image').click()">
                            <i class="fas fa-cloud-upload-alt"></i>
                            <p style="font-weight: 600; margin-bottom: 5px;">Upload Recipient Signature/Photo</p>
                            <p style="font-size: 0.9rem; color: var(--gray);">
                                Click to upload or drag and drop<br>
                                Max 5MB • JPG, PNG, GIF, WebP
                            </p>
                        </div>
                        
                        <input type="file" 
                               name="signature_image" 
                               id="signature_image" 
                               accept="image/*"
                               style="display: none;"
                               onchange="previewSignatureImage(this)">
                        
                        <div class="image-preview" id="signaturePreview">
                            <span style="color: var(--gray);">No image selected</span>
                        </div>
                        
                        <div id="uploadError" style="color: var(--danger); font-size: 0.9rem; margin-top: 10px;"></div>
                    </div>
                    
                    <div style="display: flex; gap: 15px; margin-top: 25px; flex-wrap: wrap;">
                        <button type="button" class="btn btn-outline" onclick="selectAllDeliveryItems()">
                            <i class="fas fa-check-double"></i>
                            Select All Items
                        </button>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-check-circle"></i>
                            Mark as Delivered
                        </button>
                    </div>
                </form>
            </div>
            <?php endif; ?>
        </div>
        <?php elseif ($victim_data && !$has_pending_items && !$has_in_transit_items): ?>
        <div class="simple-form" style="border-color: var(--success); text-align: center; padding: 50px 30px;">
            <div style="font-size: 4rem; color: var(--success); margin-bottom: 20px;">
                <i class="fas fa-check-circle"></i>
            </div>
            <h3 style="color: var(--dark); margin-bottom: 15px;">All Items Delivered!</h3>
            <p style="color: var(--gray); margin-bottom: 25px; max-width: 600px; margin-left: auto; margin-right: auto;">
                All relief items for this family have been delivered successfully. Thank you for your service!
            </p>
            <a href="execute_distribution.php?distribution_id=<?php echo $distribution_id; ?>&victim_id=<?php echo $victim_id + 1; ?>" class="btn btn-primary" style="padding: 12px 30px;">
                <i class="fas fa-arrow-right"></i>
                Next Victim
            </a>
        </div>
        <?php endif; ?>
    </div>

    <!-- Leaflet JS -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
            integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo="
            crossorigin=""></script>
    
    <script>
        // Global variables
        let map = null;
        let volunteerMarker = null;
        let victimMarker = null;
        let ngoMarker = null;
        let selectedLat = <?php echo $live_location['latitude'] ?? ($ngo_coordinates['lat'] ?? ($victim_data['latitude'] ?? 2.1896)); ?>;
        let selectedLng = <?php echo $live_location['longitude'] ?? ($ngo_coordinates['lng'] ?? ($victim_data['longitude'] ?? 102.2501)); ?>;
        let currentLocationName = "";
        let searchTimeout = null;
        let itemsDispatched = <?php echo $items_dispatched ? 'true' : 'false'; ?>;
        let hasPendingItems = <?php echo $has_pending_items ? 'true' : 'false'; ?>;
        
        // NGO coordinates
        const ngoLat = <?php echo $ngo_coordinates['lat'] ?? 2.1896; ?>;
        const ngoLng = <?php echo $ngo_coordinates['lng'] ?? 102.2501; ?>;
        const ngoName = "<?php echo addslashes($ngo_details['NGOName'] ?? 'Distribution Center'); ?>";
        const ngoAddress = "<?php echo addslashes($ngo_details['Address'] ?? 'Distribution Center'); ?>";
        
        // Initialize Map
        function initMap() {
            // Default center (Melaka)
            const defaultCenter = [2.1896, 102.2501];
            
            // Create map
            map = L.map('liveMap').setView(defaultCenter, 13);
            
            // Add OpenStreetMap tiles
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
                maxZoom: 19
            }).addTo(map);
            
            // Add NGO marker (starting point) - GREEN marker
            ngoMarker = L.marker([ngoLat, ngoLng], {
                icon: L.divIcon({
                    className: 'ngo-marker',
                    html: '<div style="background: #2ecc71; color: white; border-radius: 50%; width: 35px; height: 35px; display: flex; align-items: center; justify-content: center; font-size: 18px; border: 3px solid white; box-shadow: 0 2px 5px rgba(0,0,0,0.3);"><i class="fas fa-hands-helping"></i></div>',
                    iconSize: [35, 35],
                    iconAnchor: [17.5, 17.5]
                })
            }).addTo(map);
            
            ngoMarker.bindPopup(`
                <div style="font-weight: bold; margin-bottom: 5px;">
                    <i class="fas fa-hands-helping"></i> ${ngoName}
                </div>
                <div style="font-size: 12px; color: #666;">
                    <i class="fas fa-map-marker-alt"></i> ${ngoAddress}
                </div>
                <div style="margin-top: 10px;">
                    <span style="background: #2ecc71; color: white; padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: bold;">STARTING POINT</span>
                </div>
            `);
            
            // Add victim marker if available
            <?php if ($victim_data): ?>
            const victimLat = <?php echo $victim_data['latitude']; ?>;
            const victimLng = <?php echo $victim_data['longitude']; ?>;
            
            victimMarker = L.marker([victimLat, victimLng], {
                icon: L.divIcon({
                    className: 'victim-marker',
                    html: '<div style="background: #e74c3c; color: white; border-radius: 50%; width: 35px; height: 35px; display: flex; align-items: center; justify-content: center; font-size: 18px; border: 3px solid white; box-shadow: 0 2px 5px rgba(0,0,0,0.3);"><i class="fas fa-home"></i></div>',
                    iconSize: [35, 35],
                    iconAnchor: [17.5, 17.5]
                })
            }).addTo(map);
            
            victimMarker.bindPopup(`
                <div style="font-weight: bold; margin-bottom: 5px;">
                    <i class="fas fa-home"></i> <?php echo htmlspecialchars($victim_data['name']); ?>
                </div>
                <div style="font-size: 12px; color: #666;">
                    <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($victim_data['address']); ?>
                </div>
                <div style="font-size: 12px; color: #666;">
                    <i class="fas fa-users"></i> Family: <?php echo $victim_data['family_size']; ?> people
                </div>
                <div style="margin-top: 10px;">
                    <span style="background: #e74c3c; color: white; padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: bold;">DESTINATION</span>
                </div>
            `);
            <?php endif; ?>
            
            // Create initial volunteer marker (DRAGGABLE)
            volunteerMarker = L.marker([selectedLat, selectedLng], {
                draggable: itemsDispatched, // Only draggable if items dispatched
                icon: L.divIcon({
                    className: 'volunteer-marker',
                    html: '<div style="background: #4361ee; color: white; border-radius: 50%; width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; font-size: 20px; border: 3px solid white; box-shadow: 0 2px 5px rgba(0,0,0,0.3);"><i class="fas fa-user"></i></div>',
                    iconSize: [40, 40],
                    iconAnchor: [20, 20]
                })
            }).addTo(map);
            
            // Add event listener for dragging if items dispatched
            if (itemsDispatched) {
                volunteerMarker.on('dragstart', function(event) {
                    document.getElementById('locationLoading').style.display = 'block';
                    document.getElementById('currentLocationText').textContent = 'Updating location...';
                });
                
                volunteerMarker.on('dragend', async function(event) {
                    const marker = event.target;
                    const position = marker.getLatLng();
                    selectedLat = position.lat;
                    selectedLng = position.lng;
                    
                    // Update form fields
                    document.getElementById('formLatitude').value = selectedLat;
                    document.getElementById('formLongitude').value = selectedLng;
                    
                    // Get location name from OpenStreetMap Nominatim API
                    await getLocationNameFromOSM(selectedLat, selectedLng);
                    
                    document.getElementById('locationLoading').style.display = 'none';
                    showNotification('Location updated! Real location name detected', 'success');
                    
                    // Send live location update to server
                    await sendLiveLocationUpdate(selectedLat, selectedLng);
                });
            } else {
                // Add popup explaining why marker is not draggable
                volunteerMarker.bindPopup(`
                    <div style="font-weight: bold; margin-bottom: 5px;">
                        <i class="fas fa-user"></i> Your Location
                    </div>
                    <div style="font-size: 12px; color: #666;">
                        <i class="fas fa-lock"></i> Marker locked until items dispatched
                    </div>
                    <div style="margin-top: 10px; font-size: 11px; color: #e17055;">
                        <i class="fas fa-exclamation-triangle"></i> Please mark items as dispatched below first
                    </div>
                `);
            }
            
            // Bind popup to volunteer marker
            if (itemsDispatched) {
                volunteerMarker.bindPopup(`
                    <div style="font-weight: bold; margin-bottom: 5px;">
                        <i class="fas fa-user"></i> Your Location
                    </div>
                    <div style="font-size: 12px; color: #666;">
                        <i class="fas fa-map-marker-alt"></i> Drag me to your current location
                    </div>
                    <div style="margin-top: 10px; font-size: 11px; color: #4361ee;">
                        <i class="fas fa-info-circle"></i> Release to set location
                    </div>
                `);
            }
            
            // Fit bounds to show all markers
            const bounds = L.latLngBounds(
                [selectedLat, selectedLng],
                [ngoLat, ngoLng]
            );
            
            <?php if ($victim_data): ?>
            bounds.extend([victimLat, victimLng]);
            <?php endif; ?>
            
            map.fitBounds(bounds, { padding: [50, 50] });
            
            // Initialize location display with NGO address
            updateLocationDisplay(`${ngoName} - ${ngoAddress}`, ngoLat, ngoLng);
            
            // Also set the form field with NGO address
            document.getElementById('current_location').value = `${ngoName} - ${ngoAddress}`;
            
            // Set up real-time sync between input field and location display
            const locationInput = document.getElementById('current_location');
            if (locationInput) {
                locationInput.addEventListener('input', function() {
                    updateLocationDisplayFromInput(this.value);
                });
                
                // Also update on paste
                locationInput.addEventListener('paste', function() {
                    setTimeout(() => {
                        updateLocationDisplayFromInput(this.value);
                    }, 0);
                });
            }
            
            // Set up search box functionality
            const searchInput = document.getElementById('locationSearch');
            if (searchInput) {
                searchInput.addEventListener('input', function() {
                    clearTimeout(searchTimeout);
                    searchTimeout = setTimeout(() => {
                        if (this.value.length > 2) {
                            searchLocation(this.value);
                        }
                    }, 500);
                });
                
                searchInput.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        searchLocation(this.value);
                    }
                });
            }
        }
        
        // Send live location update to server
        async function sendLiveLocationUpdate(lat, lng) {
            if (!itemsDispatched && hasPendingItems) {
                showNotification('Please mark items as dispatched first before using live tracking', 'error');
                return false;
            }
            
            try {
                const formData = new FormData();
                formData.append('update_live_location', '1');
                formData.append('latitude', lat);
                formData.append('longitude', lng);
                formData.append('accuracy', 10);
                formData.append('battery_level', 80);
                formData.append('victim_id', <?php echo $victim_id; ?>);
                
                const response = await fetch('', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (!data.success) {
                    if (data.requires_dispatch) {
                        showNotification(data.error, 'error');
                        // Scroll to items section
                        document.getElementById('items-section').scrollIntoView({ behavior: 'smooth' });
                    }
                    return false;
                }
                
                return true;
                
            } catch (error) {
                console.error('Error sending live location:', error);
                return false;
            }
        }
        
        // Check if tracking is allowed
        function checkTrackingAllowed() {
            if (!itemsDispatched && hasPendingItems) {
                showNotification('Please mark items as dispatched first before updating tracking', 'error');
                document.getElementById('items-section').scrollIntoView({ behavior: 'smooth' });
                return false;
            }
            return true;
        }
        
        // Search for location using OpenStreetMap Nominatim API
        async function searchLocation(searchQuery = null) {
            // Check if tracking is allowed
            if (!checkTrackingAllowed()) {
                return;
            }
            
            const searchInput = document.getElementById('locationSearch');
            const query = searchQuery || searchInput.value;
            
            if (!query || query.trim().length < 3) {
                showNotification('Please enter at least 3 characters to search', 'warning');
                return;
            }
            
            try {
                document.getElementById('locationLoading').style.display = 'block';
                document.getElementById('currentLocationText').textContent = 'Searching for location...';
                
                // Clear previous results
                const resultsContainer = document.getElementById('searchResults');
                resultsContainer.innerHTML = '';
                resultsContainer.style.display = 'none';
                
                // Use OpenStreetMap Nominatim API for forward geocoding
                // Using simpler query format for better results
                const url = `https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query)}&limit=10&countrycodes=my`;
                
                const response = await fetch(url, {
                    headers: {
                        'User-Agent': 'DisasterReliefSystem/1.0 (volunteertracking@example.com)',
                        'Accept': 'application/json',
                        'Accept-Language': 'en'
                    }
                });
                
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                
                const data = await response.json();
                
                if (data && data.length > 0) {
                    // Filter for Malaysia results
                    const malaysiaResults = data.filter(result => {
                        // Check if result is in Malaysia
                        const displayName = result.display_name || '';
                        return displayName.toLowerCase().includes('malaysia') || 
                               displayName.toLowerCase().includes('melaka') ||
                               displayName.toLowerCase().includes('malacca');
                    });
                    
                    if (malaysiaResults.length > 0) {
                        // Show search results
                        resultsContainer.innerHTML = '';
                        malaysiaResults.forEach(result => {
                            const resultItem = document.createElement('div');
                            resultItem.className = 'search-result-item';
                            
                            // Build a better display name
                            let displayName = result.display_name || 'Unnamed location';
                            if (displayName.length > 80) {
                                // Try to extract just the main parts
                                const parts = displayName.split(',');
                                if (parts.length > 2) {
                                    displayName = parts.slice(0, 3).join(', ') + '...';
                                } else {
                                    displayName = displayName.substring(0, 80) + '...';
                                }
                            }
                            
                            resultItem.innerHTML = `
                                <div style="font-weight: 500;">${displayName}</div>
                                <div style="font-size: 0.8rem; color: var(--gray);">
                                    ${parseFloat(result.lat).toFixed(6)}, ${parseFloat(result.lon).toFixed(6)}
                                    ${result.type ? ` • ${result.type}` : ''}
                                </div>
                            `;
                            
                            resultItem.addEventListener('click', () => {
                                selectSearchResult(result);
                            });
                            
                            resultsContainer.appendChild(resultItem);
                        });
                        
                        resultsContainer.style.display = 'block';
                        document.getElementById('locationLoading').style.display = 'none';
                        document.getElementById('currentLocationText').textContent = `Found ${malaysiaResults.length} result(s). Click one to select.`;
                        showNotification(`Found ${malaysiaResults.length} location(s)`, 'success');
                    } else {
                        // If no Malaysia results, show all results but mark as approximate
                        resultsContainer.innerHTML = '';
                        data.slice(0, 5).forEach(result => {
                            const resultItem = document.createElement('div');
                            resultItem.className = 'search-result-item';
                            
                            let displayName = result.display_name || 'Unnamed location';
                            if (displayName.length > 80) {
                                displayName = displayName.substring(0, 80) + '...';
                            }
                            
                            resultItem.innerHTML = `
                                <div style="font-weight: 500;">${displayName}</div>
                                <div style="font-size: 0.8rem; color: var(--gray);">
                                    ${parseFloat(result.lat).toFixed(6)}, ${parseFloat(result.lon).toFixed(6)}
                                </div>
                                <div style="font-size: 0.7rem; color: var(--warning); margin-top: 5px;">
                                    <i class="fas fa-info-circle"></i> May not be in Malaysia
                                </div>
                            `;
                            
                            resultItem.addEventListener('click', () => {
                                selectSearchResult(result);
                            });
                            
                            resultsContainer.appendChild(resultItem);
                        });
                        
                        resultsContainer.style.display = 'block';
                        document.getElementById('locationLoading').style.display = 'none';
                        document.getElementById('currentLocationText').textContent = 'Found approximate location(s). Click to select.';
                        showNotification('Found approximate location(s). Some may not be in Malaysia.', 'warning');
                    }
                } else {
                    document.getElementById('locationLoading').style.display = 'none';
                    document.getElementById('currentLocationText').textContent = 'No results found. Try a different search term.';
                    showNotification('No locations found. Try a different search term.', 'warning');
                }
                
            } catch (error) {
                console.error('Error searching location:', error);
                document.getElementById('locationLoading').style.display = 'none';
                document.getElementById('currentLocationText').textContent = 'Search service unavailable. Please drag the marker instead.';
                showNotification('Search service temporarily unavailable. Please drag the marker to set location.', 'error');
            }
        }
        
        // Select a search result
        function selectSearchResult(result) {
            // Check if tracking is allowed
            if (!checkTrackingAllowed()) {
                return;
            }
            
            selectedLat = parseFloat(result.lat);
            selectedLng = parseFloat(result.lon);
            
            // Move the volunteer marker
            volunteerMarker.setLatLng([selectedLat, selectedLng]);
            
            // Update form fields
            document.getElementById('formLatitude').value = selectedLat;
            document.getElementById('formLongitude').value = selectedLng;
            
            // Get the display name from the search result (not reverse geocoding)
            const locationName = result.display_name || `Coordinates: ${selectedLat.toFixed(6)}, ${selectedLng.toFixed(6)}`;
            
            // Update location input and display IMMEDIATELY with search result name
            document.getElementById('current_location').value = locationName;
            
            // Update display with the EXACT search result name
            document.getElementById('currentLocationTitle').textContent = "Your Location";
            document.getElementById('currentLocationText').textContent = locationName;
            document.getElementById('currentLocationText').textContent += ` (${selectedLat.toFixed(6)}, ${selectedLng.toFixed(6)})`;
            
            // Center map on selected location
            map.setView([selectedLat, selectedLng], 15);
            
            // Hide search results
            document.getElementById('searchResults').style.display = 'none';
            document.getElementById('locationSearch').value = '';
            
            // Send live location update
            sendLiveLocationUpdate(selectedLat, selectedLng);
            
            // Update display immediately, then optionally refine with reverse geocoding in background
            // (but keep the original search name)
            setTimeout(() => {
                getRefinedLocationName(selectedLat, selectedLng, locationName);
            }, 500);
            
            showNotification(`Location set to: ${locationName.substring(0, 50)}${locationName.length > 50 ? '...' : ''}`, 'success');
        }
        
        // Get refined location name (in background, doesn't change display if similar)
        async function getRefinedLocationName(lat, lng, originalName) {
            try {
                // Use OpenStreetMap Nominatim API for reverse geocoding
                const url = `https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&zoom=16`;
                
                const response = await fetch(url, {
                    headers: {
                        'User-Agent': 'DisasterReliefSystem/1.0',
                        'Accept': 'application/json'
                    }
                });
                
                if (!response.ok) {
                    return originalName; // Keep original name if API fails
                }
                
                const data = await response.json();
                
                if (data && data.display_name) {
                    let refinedName = data.display_name;
                    
                    // Check if near victim location
                    <?php if ($victim_data): ?>
                    const victimLat = <?php echo $victim_data['latitude']; ?>;
                    const victimLng = <?php echo $victim_data['longitude']; ?>;
                    
                    const distance = Math.sqrt(Math.pow(lat - victimLat, 2) + Math.pow(lng - victimLng, 2));
                    if (distance < 0.001) { // Very close to victim
                        refinedName = `At <?php echo htmlspecialchars($victim_data['name']); ?>'s location - <?php echo htmlspecialchars($victim_data['address']); ?>`;
                        document.getElementById('statusUpdate').value = 'arrived';
                        document.querySelectorAll('.status-btn').forEach(btn => {
                            btn.classList.remove('active');
                            if (btn.dataset.status === 'arrived') {
                                btn.classList.add('active');
                            }
                        });
                        
                        // Update form field with victim location
                        document.getElementById('current_location').value = refinedName;
                        document.getElementById('currentLocationText').textContent = refinedName + ` (${lat.toFixed(6)}, ${lng.toFixed(6)})`;
                        return refinedName;
                    }
                    <?php endif; ?>
                    
                    // Check if back at NGO location
                    const ngoDistance = Math.sqrt(Math.pow(lat - ngoLat, 2) + Math.pow(lng - ngoLng, 2));
                    if (ngoDistance < 0.001) { // Very close to NGO
                        refinedName = `${ngoName} - ${ngoAddress}`;
                        document.getElementById('statusUpdate').value = 'departed';
                        document.querySelectorAll('.status-btn').forEach(btn => {
                            btn.classList.remove('active');
                            if (btn.dataset.status === 'departed') {
                                btn.classList.add('active');
                            }
                        });
                        
                        // Update form field with NGO location
                        document.getElementById('current_location').value = refinedName;
                        document.getElementById('currentLocationText').textContent = refinedName + ` (${lat.toFixed(6)}, ${lng.toFixed(6)})`;
                        return refinedName;
                    }
                    
                    // Only update if the refined name is significantly better/shorter
                    // AND contains similar keywords to original
                    if (shouldUpdateName(originalName, refinedName)) {
                        // Create a shorter, more readable version
                        if (refinedName.length > 80) {
                            const address = data.address || {};
                            if (address.road && address.city) {
                                refinedName = `${address.road}, ${address.city}`;
                            } else if (address.road && address.town) {
                                refinedName = `${address.road}, ${address.town}`;
                            } else if (address.city) {
                                refinedName = address.city;
                            } else if (address.town) {
                                refinedName = address.town;
                            } else if (address.village) {
                                refinedName = address.village;
                            }
                            
                            // Add Malaysia if not already included
                            if (refinedName && !refinedName.toLowerCase().includes('malaysia')) {
                                refinedName += ', Malaysia';
                            }
                        }
                        
                        // Update form field with refined name
                        document.getElementById('current_location').value = refinedName;
                        document.getElementById('currentLocationText').textContent = refinedName + ` (${lat.toFixed(6)}, ${lng.toFixed(6)})`;
                        
                        // Show notification about refinement
                        showNotification('Location name refined for better accuracy', 'info');
                    }
                    
                    return refinedName;
                }
                
                return originalName; // Keep original name if no refined name
                
            } catch (error) {
                console.error('Error in reverse geocoding:', error);
                return originalName; // Keep original name on error
            }
        }
        
        // Helper function to decide if we should update the name
        function shouldUpdateName(originalName, refinedName) {
            const originalLower = originalName.toLowerCase();
            const refinedLower = refinedName.toLowerCase();
            
            // If refined name is significantly shorter (more than 30% reduction)
            if (refinedName.length < originalName.length * 0.7) {
                return true;
            }
            
            // If original name is just coordinates, definitely update
            if (originalName.startsWith('Coordinates:')) {
                return true;
            }
            
            // Check if refined name contains key location words from original
            const keyWords = ['universiti', 'teknikal', 'melaka', 'malaysia', 'campus', 'utem', 'jalan', 'street', 'road'];
            const containsKeyWords = keyWords.some(word => 
                originalLower.includes(word) && refinedLower.includes(word)
            );
            
            // Only update if refined name contains similar key words
            return containsKeyWords;
        }
        
        // Update location display from map coordinates
        function updateLocationDisplay(locationName = "", lat = null, lng = null) {
            const locationText = document.getElementById('currentLocationText');
            const locationTitle = document.getElementById('currentLocationTitle');
            
            if (locationName) {
                locationTitle.textContent = "Your Location";
                
                // Always show the EXACT location name provided
                let displayText = locationName;
                if (lat && lng) {
                    displayText += ` (${lat.toFixed(6)}, ${lng.toFixed(6)})`;
                }
                locationText.textContent = displayText;
                
                // Also update the form field
                document.getElementById('current_location').value = locationName;
            } else if (selectedLat && selectedLng) {
                locationTitle.textContent = "Your Current Location";
                locationText.textContent = "Drag the marker on the map to set your location";
            }
        }
        
        // Update location display from input field
        function updateLocationDisplayFromInput(inputValue) {
            const locationText = document.getElementById('currentLocationText');
            const locationTitle = document.getElementById('currentLocationTitle');
            
            if (inputValue && inputValue.trim() !== '') {
                locationTitle.textContent = "Your Location";
                locationText.textContent = inputValue;
                
                // Update the hidden fields if we have coordinates
                if (selectedLat && selectedLng) {
                    locationText.textContent += ` (${selectedLat.toFixed(6)}, ${selectedLng.toFixed(6)})`;
                }
            } else {
                if (selectedLat && selectedLng) {
                    locationTitle.textContent = "Your Current Location";
                    locationText.textContent = "Drag the marker on the map to set your location";
                }
            }
        }
        
        // Get location name from OpenStreetMap Nominatim API (reverse geocoding)
        async function getLocationNameFromOSM(lat, lng) {
            try {
                // Show loading state
                document.getElementById('locationLoading').style.display = 'block';
                
                // Use OpenStreetMap Nominatim API for reverse geocoding
                const url = `https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&zoom=16`;
                
                const response = await fetch(url, {
                    headers: {
                        'User-Agent': 'DisasterReliefSystem/1.0',
                        'Accept': 'application/json'
                    }
                });
                
                if (!response.ok) {
                    // If API fails, use coordinates
                    throw new Error('API unavailable');
                }
                
                const data = await response.json();
                
                let locationName = '';
                
                if (data && data.display_name) {
                    locationName = data.display_name;
                    
                    // Check if near victim location
                    <?php if ($victim_data): ?>
                    const victimLat = <?php echo $victim_data['latitude']; ?>;
                    const victimLng = <?php echo $victim_data['longitude']; ?>;
                    
                    const distance = Math.sqrt(Math.pow(lat - victimLat, 2) + Math.pow(lng - victimLng, 2));
                    if (distance < 0.001) { // Very close to victim
                        locationName = `At <?php echo htmlspecialchars($victim_data['name']); ?>'s location - <?php echo htmlspecialchars($victim_data['address']); ?>`;
                        document.getElementById('statusUpdate').value = 'arrived';
                        document.querySelectorAll('.status-btn').forEach(btn => {
                            btn.classList.remove('active');
                            if (btn.dataset.status === 'arrived') {
                                btn.classList.add('active');
                            }
                        });
                    }
                    <?php endif; ?>
                    
                    // Check if back at NGO location
                    const ngoDistance = Math.sqrt(Math.pow(lat - ngoLat, 2) + Math.pow(lng - ngoLng, 2));
                    if (ngoDistance < 0.001) { // Very close to NGO
                        locationName = `${ngoName} - ${ngoAddress}`;
                        document.getElementById('statusUpdate').value = 'departed';
                        document.querySelectorAll('.status-btn').forEach(btn => {
                            btn.classList.remove('active');
                            if (btn.dataset.status === 'departed') {
                                btn.classList.add('active');
                            }
                        });
                    }
                    
                    // Create a shorter, more readable version
                    if (locationName.length > 80) {
                        const address = data.address || {};
                        if (address.road && address.city) {
                            locationName = `${address.road}, ${address.city}`;
                        } else if (address.road && address.town) {
                            locationName = `${address.road}, ${address.town}`;
                        } else if (address.city) {
                            locationName = address.city;
                        } else if (address.town) {
                            locationName = address.town;
                        } else if (address.village) {
                            locationName = address.village;
                        } else if (address.county) {
                            locationName = address.county;
                        } else if (address.state) {
                            locationName = address.state;
                        }
                        
                        // Add Malaysia if not already included
                        if (locationName && !locationName.toLowerCase().includes('malaysia')) {
                            locationName += ', Malaysia';
                        }
                    }
                } else {
                    locationName = `Coordinates: ${lat.toFixed(6)}, ${lng.toFixed(6)}`;
                }
                
                currentLocationName = locationName;
                document.getElementById('current_location').value = locationName;
                
                // Update the display with reverse geocoded name
                document.getElementById('currentLocationText').textContent = locationName;
                document.getElementById('currentLocationText').textContent += ` (${lat.toFixed(6)}, ${lng.toFixed(6)})`;
                
                document.getElementById('locationLoading').style.display = 'none';
                
                return locationName;
                
            } catch (error) {
                console.error('Error in reverse geocoding:', error);
                
                // Fallback to coordinates
                const fallbackName = `Coordinates: ${lat.toFixed(6)}, ${lng.toFixed(6)}`;
                currentLocationName = fallbackName;
                document.getElementById('current_location').value = fallbackName;
                
                // Update display
                document.getElementById('currentLocationText').textContent = fallbackName;
                
                document.getElementById('locationLoading').style.display = 'none';
                
                return fallbackName;
            }
        }
        
        // Select status
        function selectStatus(status, element) {
            // Check if tracking is allowed
            if (!checkTrackingAllowed()) {
                return;
            }
            
            // Remove active class from all buttons
            document.querySelectorAll('.status-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            
            // Add active class to clicked button
            element.classList.add('active');
            
            // Update hidden input
            document.getElementById('statusUpdate').value = status;
            
            // Auto-fill location input based on status
            const locationInput = document.getElementById('current_location');
            if (status === 'arrived' && locationInput.value === '') {
                <?php if ($victim_data): ?>
                locationInput.value = "At <?php echo htmlspecialchars($victim_data['name']); ?>'s location - <?php echo htmlspecialchars($victim_data['address']); ?>";
                updateLocationDisplayFromInput(locationInput.value);
                <?php endif; ?>
            } else if (status === 'departed' && locationInput.value === '') {
                locationInput.value = `${ngoName} - ${ngoAddress}`;
                updateLocationDisplayFromInput(locationInput.value);
            }
            
            showNotification('Status set to: ' + getStatusText(status), 'success');
        }
        
        function getStatusText(status) {
            switch(status) {
                case 'departed': return 'Just Departed';
                case 'in_transit': return 'On The Way';
                case 'arrived': return 'Arrived at Location';
                case 'delayed': return 'Delayed';
                default: return status;
            }
        }
        
        // Show notification
        function showNotification(message, type = 'info') {
            // Create notification element
            const notification = document.createElement('div');
            notification.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                padding: 15px 20px;
                background: ${type === 'error' ? '#f8d7da' : type === 'success' ? '#d4edda' : type === 'warning' ? '#fff3cd' : '#d1ecf1'};
                color: ${type === 'error' ? '#721c24' : type === 'success' ? '#155724' : type === 'warning' ? '#856404' : '#0c5460'};
                border-radius: 8px;
                border-left: 4px solid ${type === 'error' ? '#e74c3c' : type === 'success' ? '#2ecc71' : type === 'warning' ? '#ffc107' : '#3498db'};
                box-shadow: 0 5px 15px rgba(0,0,0,0.1);
                z-index: 10000;
                display: flex;
                align-items: center;
                gap: 10px;
                max-width: 400px;
                animation: slideIn 0.3s ease;
            `;
            
            notification.innerHTML = `
                <i class="fas ${type === 'error' ? 'fa-exclamation-circle' : type === 'success' ? 'fa-check-circle' : type === 'warning' ? 'fa-exclamation-triangle' : 'fa-info-circle'}"></i>
                <span>${message}</span>
            `;
            
            document.body.appendChild(notification);
            
            // Remove after 3 seconds
            setTimeout(() => {
                notification.style.animation = 'slideOut 0.3s ease';
                setTimeout(() => {
                    if (notification.parentNode) {
                        notification.parentNode.removeChild(notification);
                    }
                }, 300);
            }, 3000);
        }
        
        // Item selection functions
        function toggleItemSelection(card, needId) {
            const checkbox = card.querySelector('input[type="checkbox"]');
            checkbox.checked = !checkbox.checked;
            updateItemCard(checkbox);
        }
        
        function updateItemCard(checkbox) {
            const card = checkbox.closest('.item-card');
            if (checkbox.checked) {
                card.classList.add('selected');
            } else {
                card.classList.remove('selected');
            }
        }
        
        function selectAllItems() {
            const checkboxes = document.querySelectorAll('#prepareForm input[type="checkbox"]');
            let selectedCount = 0;
            
            checkboxes.forEach(cb => {
                if (!cb.checked) {
                    cb.checked = true;
                    selectedCount++;
                    updateItemCard(cb);
                }
            });
            
            if (selectedCount > 0) {
                showNotification(`Selected ${selectedCount} items for dispatch`, 'success');
            } else {
                showNotification('All items are already selected', 'info');
            }
        }
        
        // Delivery selection functions
        function toggleDeliverySelection(card, needId) {
            const checkbox = card.querySelector('input[type="checkbox"]');
            checkbox.checked = !checkbox.checked;
            updateDeliveryCard(checkbox);
        }
        
        function updateDeliveryCard(checkbox) {
            const card = checkbox.closest('.item-card');
            if (checkbox.checked) {
                card.classList.add('selected');
            } else {
                card.classList.remove('selected');
            }
        }
        
        function selectAllDeliveryItems() {
            const checkboxes = document.querySelectorAll('#completeForm input[name="delivered_items[]"]');
            let selectedCount = 0;
            
            checkboxes.forEach(cb => {
                if (!cb.checked) {
                    cb.checked = true;
                    selectedCount++;
                    updateDeliveryCard(cb);
                }
            });
            
            if (selectedCount > 0) {
                showNotification(`Selected ${selectedCount} items for delivery completion`, 'success');
            } else {
                showNotification('All items are already selected', 'info');
            }
        }
        
        // Preview signature image
        function previewSignatureImage(input) {
            const preview = document.getElementById('signaturePreview');
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
                    preview.innerHTML = `<img src="${e.target.result}" style="width: 100%; height: 100%; object-fit: cover;">`;
                }
                reader.readAsDataURL(file);
            } else {
                preview.innerHTML = '<span style="color: var(--gray);">No image selected</span>';
                errorDiv.textContent = '';
            }
        }
        
        // Form validation
        document.getElementById('trackingForm')?.addEventListener('submit', function(e) {
            // Check if tracking is allowed
            if (!checkTrackingAllowed()) {
                e.preventDefault();
                return false;
            }
            
            const location = document.getElementById('current_location').value.trim();
            
            if (!location) {
                e.preventDefault();
                showNotification('Please enter your current location', 'error');
                document.getElementById('current_location').focus();
                return false;
            }
            
            return true;
        });
        
        document.getElementById('prepareForm')?.addEventListener('submit', function(e) {
            const checked = document.querySelectorAll('#prepareForm input[type="checkbox"]:checked');
            if (checked.length === 0) {
                e.preventDefault();
                showNotification('Please select at least one item to prepare for delivery', 'error');
                return false;
            }
            return confirm(`Mark ${checked.length} items as Dispatched? This means you're leaving the ${ngoName} now.`);
        });
        
        document.getElementById('completeForm')?.addEventListener('submit', function(e) {
            const checked = document.querySelectorAll('#completeForm input[name="delivered_items[]"]:checked');
            const fileInput = document.getElementById('signature_image');
            
            if (checked.length === 0) {
                e.preventDefault();
                showNotification('Please select at least one item to mark as delivered', 'error');
                return false;
            }
            
            if (!fileInput.files || fileInput.files.length === 0) {
                e.preventDefault();
                showNotification('Please upload a recipient signature/image as confirmation', 'error');
                return false;
            }
            
            return confirm(`Mark ${checked.length} items as Delivered? This will complete the delivery for this family.`);
        });
        
        // Initialize map when page loads
        document.addEventListener('DOMContentLoaded', initMap);
        
        // Click outside to close search results
        document.addEventListener('click', function(e) {
            const searchResults = document.getElementById('searchResults');
            const searchInput = document.getElementById('locationSearch');
            
            if (searchResults && searchInput && 
                !searchResults.contains(e.target) && 
                !searchInput.contains(e.target)) {
                searchResults.style.display = 'none';
            }
        });
        
        // Drag and drop for file upload
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
                        previewSignatureImage(fileInput);
                    }
                }
            }
        });
    </script>
</body>
</html>