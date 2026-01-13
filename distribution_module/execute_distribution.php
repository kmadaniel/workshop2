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
$shelter_data = null;
$shelter_victims = [];
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
$NGO_API_URL = 'http://10.147.17.30:8000/api_ngo.php';

// ========================================
// CRITICAL: GET DISTRIBUTION PLAN DATA
// ========================================
try {
    // Get distribution details
    $distribution_query = "
        SELECT 
            d.*,
            dr.resource_name,
            dr.quantity_allocated,
            dr.is_special_request,
            di.victim_id,
            va.disaster_id
        FROM distribution d
        LEFT JOIN distribution_resources dr ON d.distribution_id = dr.distribution_id
        LEFT JOIN distribution_items di ON d.distribution_id = di.distribution_id
        LEFT JOIN victim_approvals va ON di.victim_id = va.victim_id
        WHERE d.distribution_id = ?
        ORDER BY dr.is_special_request, dr.resource_name
    ";
    
    $stmt = $db->prepare($distribution_query);
    $stmt->bind_param("i", $distribution_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $distribution_details = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    if (empty($distribution_details)) {
        die("Error: Distribution plan not found.");
    }
    
    // Get first row for basic info
    $distribution_info = $distribution_details[0];
    $distribution_date = $distribution_info['date'] ?? '';
    $coordinator_name = $distribution_info['coordinator_name'] ?? '';
    $coordinator_contact = $distribution_info['coordinator_contact'] ?? '';
    $disaster_id = $distribution_info['disaster_id'] ?? 0;
    $estimated_duration = $distribution_info['estimated_duration'] ?? 0;
    $volunteers_needed = $distribution_info['volunteers_needed'] ?? 0;
    $comments = $distribution_info['comments'] ?? '';
    
    // Parse distribution date and time
    if ($distribution_date) {
        $distribution_date_obj = new DateTime($distribution_date);
        $distribution_date_formatted = $distribution_date_obj->format('Y-m-d');
        $distribution_time_formatted = $distribution_date_obj->format('H:i');
    }
    
    // Get unique victim IDs from this distribution
    $victim_ids = [];
    foreach ($distribution_details as $row) {
        if ($row['victim_id'] && !in_array($row['victim_id'], $victim_ids)) {
            $victim_ids[] = $row['victim_id'];
        }
    }
    
} catch (Exception $e) {
    die("Error loading distribution plan: " . $e->getMessage());
}

// Fix missing columns in tables
$tables_to_fix = [
    'distribution_tracking' => [
        'column' => 'shelter_name',
        'definition' => 'VARCHAR(255) AFTER volunteer_id'
    ],
    'distribution_log' => [
        'column' => 'shelter_name',
        'definition' => 'VARCHAR(255) AFTER volunteer_id'
    ]
];

foreach ($tables_to_fix as $table => $config) {
    $column_name = $config['column'];
    $check_column = "SHOW COLUMNS FROM $table LIKE '$column_name'";
    $result = $db->query($check_column);
    if ($result && $result->num_rows == 0) {
        $add_column = "ALTER TABLE $table ADD COLUMN $column_name {$config['definition']}";
        $db->query($add_column);
    }
    if ($result) {
        $result->close();
    }
}

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
        
        if ($ch) {
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
        
        if ($ch) {
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
   GEOLOCATION FUNCTIONS FOR SHELTERS
======================================== */
function getShelterCoordinates($shelter_name, $district) {
    // Default Melaka coordinates
    $coordinates = [
        'lat' => 2.1896,
        'lng' => 102.2501
    ];
    
    // Common shelters in Melaka with approximate coordinates
    $shelter_locations = [
        'Stadium Hang Jebat' => ['lat' => 2.2564, 'lng' => 102.2778],
        'Melaka Tengah Emergency Shelter 1' => ['lat' => 2.2000, 'lng' => 102.2500],
        'Sekolah Kebangsaan Seri Pengkalan' => ['lat' => 2.2136, 'lng' => 102.2544],
        'Dewan Serbaguna Masjid Tanah' => ['lat' => 2.3500, 'lng' => 102.1000],
        'Surau Al-Ikhlas' => ['lat' => 2.1800, 'lng' => 102.2700],
        'Balai Raya Kampung Bukit Katil' => ['lat' => 2.2300, 'lng' => 102.2900],
        'Dewan Orang Ramai Alor Gajah' => ['lat' => 2.3800, 'lng' => 102.2100],
        'Sekolah Kebangsaan Telok Mas' => ['lat' => 2.1200, 'lng' => 102.3300],
        'Dewan Masyarakat Jasin' => ['lat' => 2.3100, 'lng' => 102.4300],
        'Surau An-Nur' => ['lat' => 2.1900, 'lng' => 102.2300],
    ];
    
    // Check if shelter exists in our list
    foreach ($shelter_locations as $name => $coords) {
        if (stripos($shelter_name, $name) !== false) {
            return $coords;
        }
    }
    
    // Generate unique coordinates based on shelter name hash
    $hash = md5(strtolower($shelter_name . $district));
    $lat_offset = (hexdec(substr($hash, 0, 8)) % 1000) / 10000;
    $lng_offset = (hexdec(substr($hash, 8, 8)) % 1000) / 10000;
    
    $coordinates['lat'] += $lat_offset;
    $coordinates['lng'] += $lng_offset;
    
    return $coordinates;
}

// Create shelter coordinates table if not exists
$create_shelter_table = "
    CREATE TABLE IF NOT EXISTS shelter_coordinates (
        id INT PRIMARY KEY AUTO_INCREMENT,
        shelter_name VARCHAR(255) NOT NULL,
        district VARCHAR(100),
        latitude DECIMAL(10, 8),
        longitude DECIMAL(11, 8),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_shelter (shelter_name),
        INDEX (shelter_name)
    )
";
$db->query($create_shelter_table);

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
   FORM SUBMISSION HANDLERS FOR SHELTERS
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
            AND shelter_name = ?
            AND status = 'in_transit'
        ";
        
        $stmt = $db->prepare($check_dispatched_query);
        $shelter_name_for_check = $_POST['shelter_name'] ?? '';
        $stmt->bind_param("iis", $distribution_id, $volunteer_id, $shelter_name_for_check);
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
                (distribution_id, volunteer_id, shelter_name, current_location, tracking_notes, status, created_at) 
                VALUES (?, ?, ?, ?, ?, 'in_transit', NOW())
            ";
            $stmt = $db->prepare($update_tracking);
            $shelter_name_param = $_POST['shelter_name'] ?? '';
            $stmt->bind_param("iisss", $distribution_id, $volunteer_id, $shelter_name_param, $current_location, $tracking_notes);
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
        AND shelter_name = ?
        AND status = 'in_transit'
    ";
    
    $stmt = $db->prepare($check_dispatched_query);
    $shelter_name_for_check = $_POST['shelter_name'] ?? '';
    $stmt->bind_param("iis", $distribution_id, $volunteer_id, $shelter_name_for_check);
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
        
        // FIX: Validate and sanitize status using exact ENUM values from database
        $allowed_statuses = ['pending', 'departed', 'in_transit', 'arrived', 'delayed', 'completed', 'cancelled', 'scheduled', 'in_progress'];
        $status_update = strtolower(trim($status_update));
        
        // Remove any non-alphabet characters except underscore
        $status_update = preg_replace('/[^a-z_]/', '', $status_update);
        
        // If status is empty or not valid, default to 'in_transit'
        if (empty($status_update) || !in_array($status_update, $allowed_statuses)) {
            $status_update = 'in_transit';
        }
        
        // DEBUG: Log the status
        error_log("Final status to insert: " . $status_update);
        
        if (!empty($current_location)) {
            try {
                $shelter_name_for_tracking = $_POST['shelter_name'] ?? '';
                
                // FIXED QUERY: Use prepared statement with correct status
                $tracking_query = "INSERT INTO distribution_tracking 
                    (distribution_id, volunteer_id, shelter_name, current_location, 
                     tracking_notes, estimated_arrival, status, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
                
                $stmt = $db->prepare($tracking_query);
                
                // Bind parameters with correct types
                $stmt->bind_param("iisssss", 
                    $distribution_id, 
                    $volunteer_id, 
                    $shelter_name_for_tracking, 
                    $current_location, 
                    $tracking_notes, 
                    $estimated_arrival, 
                    $status_update
                );
                
                if (!$stmt->execute()) {
                    throw new Exception("Database error: " . $stmt->error);
                }
                
                $tracking_id = $stmt->insert_id;
                $stmt->close();
                    
                    // Also save to live tracking if coordinates are provided
                    if ($latitude != 0 && $longitude != 0) {
                        $live_tracking_query = "INSERT INTO live_tracking (volunteer_id, distribution_id, latitude, longitude, accuracy, recorded_at) VALUES (?, ?, ?, ?, 10, NOW())";
                        $stmt = $db->prepare($live_tracking_query);
                        $stmt->bind_param("iidd", $volunteer_id, $distribution_id, $latitude, $longitude);
                        $stmt->execute();
                        $stmt->close();
                    }
                    
$distribution_volunteer_status = 'Active';  // Default

if ($status_update == 'departed' || $status_update == 'in_transit' || $status_update == 'arrived' || $status_update == 'delayed') {
    $distribution_volunteer_status = 'Active';  // Keep as Active while in transit
} elseif ($status_update == 'completed') {
    $distribution_volunteer_status = 'Completed';  // ✓ Valid ENUM value
} elseif ($status_update == 'cancelled') {
    $distribution_volunteer_status = 'Cancelled';  // ✓ Valid ENUM value
} elseif ($status_update == 'pending' || $status_update == 'scheduled') {
    $distribution_volunteer_status = 'Assigned';  // ✓ Valid ENUM value
}

$update_volunteer_status_query = "UPDATE distribution_volunteer SET status = ? WHERE distribution_id = ? AND volunteer_id = ?";
$stmt = $db->prepare($update_volunteer_status_query);
$stmt->bind_param("sii", $distribution_volunteer_status, $distribution_id, $volunteer_id);
$stmt->execute();
$stmt->close();
                    
                    $success = "Tracking status updated successfully! (Status: " . ucfirst(str_replace('_', ' ', $status_update)) . ")";
                    
                    header("Location: execute_distribution.php?distribution_id=$distribution_id&shelter_name=" . urlencode($shelter_name_for_tracking) . "&success=tracking_updated");
                    exit;
                    
                } catch (Exception $e) {
                    $error = "Error updating tracking: " . $e->getMessage();
                }
            } else {
                $error = "Please enter your current location.";
            }
        }
    }
    
    if (isset($_POST['prepare_distribution'])) {
        $selected_items = $_POST['distributed_items'] ?? [];
        $shelter_name_post = $_POST['shelter_name'] ?? '';
        $selected_family_ids = isset($_POST['selected_family_ids']) ? explode(',', $_POST['selected_family_ids']) : [];
        
        if (!empty($selected_items) && $shelter_name_post && !empty($selected_family_ids)) {
            try {
                $db->begin_transaction();
                
                // Process each selected item
                foreach ($selected_items as $item_data) {
                    // Parse the item data (it's in format: need_id|victim_id)
                    $parts = explode('|', $item_data);
                    $need_id = intval($parts[0] ?? 0);
                    $victim_id = intval($parts[1] ?? 0);
                    
                    // Only process if victim is in selected families
                    if ($need_id > 0 && $victim_id > 0 && in_array($victim_id, $selected_family_ids)) {
                        $check_query = "SELECT id FROM distribution_log WHERE need_id = ? AND victim_id = ? AND shelter_name = ? AND distribution_id = ? AND volunteer_id = ?";
                        $stmt = $db->prepare($check_query);
                        $stmt->bind_param("iisii", $need_id, $victim_id, $shelter_name_post, $distribution_id, $volunteer_id);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        $exists = $result->num_rows > 0;
                        $stmt->close();
                        
                        if ($exists) {
                            $update_query = "UPDATE distribution_log SET status = 'in_transit' WHERE need_id = ? AND victim_id = ? AND shelter_name = ? AND distribution_id = ? AND volunteer_id = ?";
                            $stmt = $db->prepare($update_query);
                            $stmt->bind_param("iisii", $need_id, $victim_id, $shelter_name_post, $distribution_id, $volunteer_id);
                        } else {
                            // FIXED: Now binding 6 parameters for 6 values
                            $insert_query = "INSERT INTO distribution_log (distribution_id, volunteer_id, shelter_name, victim_id, need_id, status, quantity_distributed, distributed_at) VALUES (?, ?, ?, ?, ?, 'in_transit', 1, NOW())";
                            $stmt = $db->prepare($insert_query);
                            $stmt->bind_param("iisii", $distribution_id, $volunteer_id, $shelter_name_post, $victim_id, $need_id);
                        }
                        
                        $stmt->execute();
                        $stmt->close();
                    }
                }
                
                $db->commit();
                
                // Get NGO details for the departure location
                $ngo_address = "Distribution Center";
                if (isset($ngo_details['Address'])) {
                    $ngo_address = $ngo_details['Address'] . " (" . $ngo_details['NGOName'] . ")";
                }
                
                $initial_tracking_query = "INSERT INTO distribution_tracking (distribution_id, volunteer_id, shelter_name, current_location, tracking_notes, status, created_at) VALUES (?, ?, ?, ?, 'Items loaded and ready for delivery to shelter', 'departed', NOW())";
                $stmt = $db->prepare($initial_tracking_query);
                $stmt->bind_param("iiss", $distribution_id, $volunteer_id, $shelter_name_post, $ngo_address);
                $stmt->execute();
                $stmt->close();
                
                header("Location: execute_distribution.php?distribution_id=$distribution_id&shelter_name=" . urlencode($shelter_name_post) . "&success=prepared");
                exit;
                
            } catch (Exception $e) {
                if (isset($db) && method_exists($db, 'rollback')) {
                    $db->rollback();
                }
                $error = "Error preparing items: " . $e->getMessage();
            }
        } else {
            $error = "Please select at least one family and items to prepare for delivery.";
        }
    }
    
    if (isset($_POST['complete_delivery'])) {
        $selected_items = $_POST['delivered_items'] ?? [];
        $shelter_name_post = $_POST['shelter_name'] ?? '';
        $delivery_remarks = $_POST['delivery_remarks'] ?? '';
        $selected_family_ids = isset($_POST['selected_family_ids']) ? explode(',', $_POST['selected_family_ids']) : [];
        
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
                    $filename = 'signature_' . $distribution_id . '_' . md5($shelter_name_post) . '_' . time() . '.' . $file_extension;
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
                $error = "Please upload a shelter coordinator signature/image as confirmation.";
            } else {
                $error = implode(" ", $upload_errors);
            }
        } elseif (empty($selected_family_ids)) {
            $error = "No families selected for delivery.";
        } else {
            try {
                $db->begin_transaction();
                
                // Process each selected item
                foreach ($selected_items as $item_data) {
                    // Parse the item data (it's in format: need_id|victim_id)
                    $parts = explode('|', $item_data);
                    $need_id = intval($parts[0] ?? 0);
                    $victim_id = intval($parts[1] ?? 0);
                    
                    // Only process if victim is in selected families
                    if ($need_id > 0 && $victim_id > 0 && in_array($victim_id, $selected_family_ids)) {
                        $check_query = "SELECT id FROM distribution_log WHERE need_id = ? AND victim_id = ? AND shelter_name = ? AND distribution_id = ? AND volunteer_id = ?";
                        $stmt = $db->prepare($check_query);
                        $stmt->bind_param("iisii", $need_id, $victim_id, $shelter_name_post, $distribution_id, $volunteer_id);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        $exists = $result->num_rows > 0;
                        $stmt->close();
                        
                        if ($exists) {
                            $update_query = "UPDATE distribution_log SET status = 'completed', remarks = ?, signature_url = ? WHERE need_id = ? AND victim_id = ? AND shelter_name = ? AND distribution_id = ? AND volunteer_id = ?";
                            $stmt = $db->prepare($update_query);
                            $stmt->bind_param("ssiisii", $delivery_remarks, $signature_image_path, $need_id, $victim_id, $shelter_name_post, $distribution_id, $volunteer_id);
                        } else {
                            $insert_query = "INSERT INTO distribution_log (distribution_id, volunteer_id, shelter_name, victim_id, need_id, status, quantity_distributed, remarks, signature_url, distributed_at) VALUES (?, ?, ?, ?, ?, 'completed', 1, ?, ?, NOW())";
                            $stmt = $db->prepare($insert_query);
                            $stmt->bind_param("iisiisss", $distribution_id, $volunteer_id, $shelter_name_post, $victim_id, $need_id, $delivery_remarks, $signature_image_path);
                        }
                        
                        $stmt->execute();
                        $stmt->close();
                    }
                }
                
                $db->commit();
                
                header("Location: execute_distribution.php?distribution_id=$distribution_id&shelter_name=" . urlencode($shelter_name_post) . "&success=delivered");
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
   CHECK VOLUNTEER ASSIGNMENT (FINAL CORRECTED VERSION)
======================================== */
$check_assignment = "
    SELECT dv.*, d.* 
    FROM distribution_volunteer dv
    JOIN distribution d ON dv.distribution_id = d.distribution_id
    WHERE dv.distribution_id = ? 
    AND dv.volunteer_id = ?
    AND dv.status IN ('Assigned', 'Active', 'Completed')  -- Only statuses that exist in your ENUM
    LIMIT 1
";

$stmt = $db->prepare($check_assignment);
$stmt->bind_param("ii", $distribution_id, $volunteer_id);
$stmt->execute();
$result = $stmt->get_result();
$assignment = $result->fetch_assoc();
$stmt->close();

if (!$assignment) {
    // If not assigned, auto-assign the volunteer
    $auto_assign_query = "
        INSERT INTO distribution_volunteer (
            distribution_id, 
            volunteer_id, 
            status, 
            assigned_timestamp
        ) VALUES (?, ?, 'Assigned', NOW())
    ";
    
    try {
        $stmt = $db->prepare($auto_assign_query);
        $stmt->bind_param("ii", $distribution_id, $volunteer_id);
        $stmt->execute();
        $stmt->close();
        
        // Re-check assignment
        $stmt = $db->prepare($check_assignment);
        $stmt->bind_param("ii", $distribution_id, $volunteer_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $assignment = $result->fetch_assoc();
        $stmt->close();
        
    } catch (Exception $e) {
        error_log("Auto-assign failed: " . $e->getMessage());
        // Check if it's a duplicate entry error
        if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
            // Volunteer already assigned, just fetch the assignment
            $stmt = $db->prepare($check_assignment);
            $stmt->bind_param("ii", $distribution_id, $volunteer_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $assignment = $result->fetch_assoc();
            $stmt->close();
        } else {
            $error = "Warning: Database error during assignment: " . $e->getMessage();
        }
    }
    
    if (!$assignment) {
        // If still not assigned, create a minimal assignment object
        $assignment = [
            'status' => 'Assigned',
            'distribution_id' => $distribution_id,
            'volunteer_id' => $volunteer_id
        ];
    }
}

// Update volunteer status to 'Active' if 'Assigned'
if ($assignment && $assignment['status'] == 'Assigned') {
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
   HELPER FUNCTIONS FOR SHELTERS
======================================== */
function categorizeResource($resource_name) {
    $resource = strtolower($resource_name);
    
    if (strpos($resource, 'baby') !== false || 
        strpos($resource, 'diaper') !== false || 
        strpos($resource, 'formula') !== false ||
        strpos($resource, 'bottle') !== false) {
        return 'Baby Care';
    } elseif (strpos($resource, 'medicine') !== false || 
              strpos($resource, 'first aid') !== false || 
              strpos($resource, 'bandage') !== false ||
              strpos($resource, 'thermometer') !== false) {
        return 'Medical';
    } elseif (strpos($resource, 'food') !== false || 
              strpos($resource, 'rice') !== false || 
              strpos($resource, 'water') !== false ||
              strpos($resource, 'canned') !== false) {
        return 'Food & Water';
    } elseif (strpos($resource, 'blanket') !== false || 
              strpos($resource, 'tent') !== false || 
              strpos($resource, 'mat') !== false ||
              strpos($resource, 'clothing') !== false) {
        return 'Shelter & Clothing';
    } else {
        return 'Other';
    }
}

function getNeedStatus($need_id, $victim_id, $shelter_name, $distribution_id, $volunteer_id, $db) {
    if (!$need_id || !$victim_id) {
        return 'pending';
    }
    
    $check_log_query = "
        SELECT status 
        FROM distribution_log 
        WHERE need_id = ? 
        AND victim_id = ?
        AND shelter_name = ?
        AND distribution_id = ?
        AND volunteer_id = ?
        ORDER BY created_at DESC 
        LIMIT 1
    ";
    
    $log_status = null;
    $stmt = $db->prepare($check_log_query);
    $stmt->bind_param("iisii", $need_id, $victim_id, $shelter_name, $distribution_id, $volunteer_id);
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
    
    return $need_status;
}

/* ========================================
   HANDLE SHELTER SELECTION
======================================== */
$shelter_name = $_GET['shelter_name'] ?? '';

// Get all needs from API
$needs_result = fetchAllFromAPI($NEEDS_API_URL);
$api_needs = $needs_result['data'] ?? [];

// Get all victims from API
$all_victims = fetchAllFromAPI($VICTIM_API_URL);

// Get assigned families for this distribution
$assigned_families = [];
$assigned_family_ids = [];

// Get victim details for those in our distribution
if ($all_victims['success'] && !empty($all_victims['data']) && !empty($victim_ids)) {
    foreach ($all_victims['data'] as $victim) {
        $victimId = intval($victim['victim_id'] ?? 0);
        if (in_array($victimId, $victim_ids)) {
            $assigned_families[] = [
                'victim_id' => $victimId,
                'full_name' => $victim['full_name'] ?? 'Unknown',
                'selected_shelter' => $victim['selected_shelter'] ?? 'Unknown',
                'family_members' => $victim['family_members'] ?? 1,
                'has_baby' => isset($victim['has_baby']) && $victim['has_baby'] == 't',
                'has_elderly' => isset($victim['has_elderly']) && $victim['has_elderly'] == 't',
                'has_disabled' => isset($victim['has_disabled']) && $victim['has_disabled'] == 't',
                'special_request' => $victim['special_request'] ?? ''
            ];
            $assigned_family_ids[] = $victimId;
        }
    }
}

// Group families by shelter
$families_by_shelter = [];
foreach ($assigned_families as $family) {
    $shelter = $family['selected_shelter'];
    if (!isset($families_by_shelter[$shelter])) {
        $families_by_shelter[$shelter] = [];
    }
    $families_by_shelter[$shelter][] = $family;
}

// If shelter not selected, use first shelter with families
if (!$shelter_name && !empty($families_by_shelter)) {
    $first_shelter = array_key_first($families_by_shelter);
    header("Location: execute_distribution.php?distribution_id=$distribution_id&shelter_name=" . urlencode($first_shelter));
    exit;
}

if ($shelter_name) {
    // Get families in selected shelter
    $shelter_victims = $families_by_shelter[$shelter_name] ?? [];
    
    // Get shelter coordinates
    $shelter_coordinates = getShelterCoordinates($shelter_name, $disaster_details['district'] ?? 'Melaka');
    
    // Store shelter coordinates in database
    $check_shelter_query = "SELECT * FROM shelter_coordinates WHERE shelter_name = ?";
    $stmt = $db->prepare($check_shelter_query);
    $stmt->bind_param("s", $shelter_name);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 0) {
        $insert_shelter_query = "INSERT INTO shelter_coordinates (shelter_name, district, latitude, longitude) VALUES (?, ?, ?, ?)";
        $stmt = $db->prepare($insert_shelter_query);
        $district = $disaster_details['district'] ?? 'Melaka';
        $stmt->bind_param("ssdd", $shelter_name, $district, $shelter_coordinates['lat'], $shelter_coordinates['lng']);
        $stmt->execute();
    }
    $stmt->close();
    
    // Get needs for victims in this shelter
    if (!empty($shelter_victims) && !empty($api_needs)) {
        $shelter_victim_ids = array_column($shelter_victims, 'victim_id');
        
        foreach ($shelter_victim_ids as $victim_id) {
            // Find needs for this victim
            foreach ($api_needs as $api_need) {
                $need_victim_id = $api_need['victim_id'] ?? 0;
                
                if (intval($need_victim_id) == $victim_id) {
                    $resource_name = $api_need['temp_resource_name'] ?? $api_need['ResourceName'] ?? $api_need['resource_name'] ?? 'Resource';
                    
                    // Get victim info
                    $victim_info = array_filter($shelter_victims, function($v) use ($victim_id) {
                        return $v['victim_id'] == $victim_id;
                    });
                    $victim_info = reset($victim_info);
                    
                    $needs_data[] = [
                        'need_id' => $api_need['need_id'] ?? 0,
                        'victim_id' => $victim_id,
                        'resource_name' => $resource_name,
                        'quantity_needed' => $api_need['quantity_needed'] ?? 1,
                        'unit' => 'units',
                        'type' => categorizeResource($resource_name),
                        'priority' => $api_need['priority'] ?? 'Medium',
                        'need_status' => getNeedStatus($api_need['need_id'] ?? 0, $victim_id, $shelter_name, $distribution_id, $volunteer_id, $db),
                        'api_status' => $api_need['Status'] ?? $api_need['status'] ?? 'Pending',
                        'victim_name' => $victim_info['full_name'] ?? 'Unknown',
                        'family_members' => $victim_info['family_members'] ?? 1,
                        'has_baby' => $victim_info['has_baby'] ?? false,
                        'has_elderly' => $victim_info['has_elderly'] ?? false,
                        'has_disabled' => $victim_info['has_disabled'] ?? false,
                        'special_request' => $victim_info['special_request'] ?? ''
                    ];
                }
            }
        }
    }
    
    // Group needs by type
    $grouped_needs = [];
    foreach ($needs_data as $need) {
        $type = $need['type'] ?? 'Other';
        if (!isset($grouped_needs[$type])) {
            $grouped_needs[$type] = [];
        }
        $grouped_needs[$type][] = $need;
    }
    
    // Prepare shelter data for display
    $shelter_data = [
        'name' => $shelter_name,
        'latitude' => $shelter_coordinates['lat'],
        'longitude' => $shelter_coordinates['lng'],
        'district' => $disaster_details['district'] ?? 'Melaka',
        'total_families' => count($shelter_victims),
        'total_people' => array_sum(array_column($shelter_victims, 'family_members')),
        'total_needs' => count($needs_data),
        'fulfilled_needs' => 0,
        'in_transit_needs' => 0
    ];
    
    foreach ($needs_data as $need) {
        if ($need['need_status'] == 'fulfilled') {
            $shelter_data['fulfilled_needs']++;
        } elseif ($need['need_status'] == 'in_transit') {
            $shelter_data['in_transit_needs']++;
        }
    }
    
} else {
    $error = "No shelters with assigned victims found for this distribution.";
}

/* ========================================
   CHECK IF ITEMS HAVE BEEN DISPATCHED FOR TRACKING
======================================== */
$check_dispatched_query = "
    SELECT COUNT(*) as dispatched_count 
    FROM distribution_log 
    WHERE distribution_id = ? 
    AND volunteer_id = ?
    AND shelter_name = ?
    AND status = 'in_transit'
";

$stmt = $db->prepare($check_dispatched_query);
$stmt->bind_param("iis", $distribution_id, $volunteer_id, $shelter_name);
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
    AND (shelter_name = ? OR shelter_name IS NULL OR shelter_name = '')
    ORDER BY created_at DESC
    LIMIT 10
";

$stmt = $db->prepare($tracking_query);
$stmt->bind_param("iis", $distribution_id, $volunteer_id, $shelter_name);
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

// Check if current shelter has items in transit
$has_in_transit_items = false;
if (!empty($needs_data)) {
    foreach ($needs_data as $need) {
        if ($need['need_status'] == 'in_transit') {
            $has_in_transit_items = true;
            break;
        }
    }
}

// Check for pending items
$has_pending_items = false;
if (!empty($needs_data)) {
    foreach ($needs_data as $need) {
        if ($need['need_status'] == 'pending') {
            $has_pending_items = true;
            break;
        }
    }
}

/* ========================================
   GET STATISTICS FOR SHELTERS
======================================== */
$stats_query = "
SELECT 
    COUNT(DISTINCT shelter_name) as total_shelters,
    COUNT(DISTINCT CASE WHEN status = 'completed' THEN shelter_name END) as completed_shelters,
    COUNT(DISTINCT CASE WHEN status = 'in_transit' THEN shelter_name END) as in_transit_shelters,
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
if ($distribution_stats && $distribution_stats['total_shelters'] > 0) {
    $completed_weight = $distribution_stats['completed_shelters'] * 1.0;
    $in_transit_weight = $distribution_stats['in_transit_shelters'] * 0.7;
    $total_weight = $completed_weight + $in_transit_weight;
    $max_possible = $distribution_stats['total_shelters'] * 1.0;
    $progress = ($total_weight / $max_possible) * 100;
}

$personal_stats_query = "
SELECT 
    COUNT(DISTINCT CASE WHEN status = 'completed' THEN shelter_name END) as my_completed_shelters,
    COUNT(DISTINCT CASE WHEN status = 'in_transit' THEN shelter_name END) as my_in_transit_shelters,
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
            --shelter: #9b59b6;
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
        
        .badge-shelter {
            background: #e8d3f2;
            color: #8e44ad;
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
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
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
        
        .victim-info {
            background: #e8f4fc;
            padding: 10px;
            border-radius: 6px;
            margin-top: 10px;
            border-left: 3px solid var(--info);
        }
        
        /* Shelter Info */
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
            border-left: 4px solid var(--shelter);
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
        
        /* Family Card Styles */
        .family-card {
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            padding: 15px;
            background: #f8f9fa;
            cursor: pointer;
        }
        
        .family-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.1);
        }
        
        .family-card.selected {
            border-color: var(--primary);
            background: linear-gradient(135deg, rgba(67, 97, 238, 0.05), rgba(114, 9, 183, 0.05));
            box-shadow: 0 5px 15px rgba(67, 97, 238, 0.1);
        }
        
        .family-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 3px;
            background: transparent;
            transition: var(--transition);
        }
        
        .family-card.selected::before {
            background: var(--primary);
        }
        
        /* Family Counter Badge */
        .family-counter {
            background: var(--primary);
            color: white;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
            margin-left: 10px;
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
        
        /* Ensure items section is visible when shown */
        #items-section[style*="display: block"] {
            display: block !important;
        }
        
        .item-card[style*="display: block"] {
            display: block !important;
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
                    
                    <?php if ($distribution_date): ?>
                    <div style="color: var(--info); margin-bottom: 5px;">
                        <i class="fas fa-calendar"></i>
                        Scheduled: <?php echo htmlspecialchars($distribution_date_formatted ?? ''); ?> at <?php echo htmlspecialchars($distribution_time_formatted ?? ''); ?>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($coordinator_name): ?>
                    <div style="color: var(--success); margin-bottom: 5px;">
                        <i class="fas fa-user-tie"></i>
                        Coordinator: <?php echo htmlspecialchars($coordinator_name); ?>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($estimated_duration): ?>
                    <div style="color: var(--warning);">
                        <i class="fas fa-clock"></i>
                        Estimated Duration: <?php echo $estimated_duration; ?> hours
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Shelter Selector -->
                <?php if ($shelter_name): ?>
                <div style="margin-left: auto;">
                    <div style="background: var(--shelter); color: white; padding: 10px 20px; border-radius: 8px; display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-home"></i>
                        <div>
                            <strong>Current Shelter:</strong>
                            <div><?php echo htmlspecialchars($shelter_name); ?></div>
                            <div style="font-size: 0.8rem; opacity: 0.9;">
                                <?php echo $shelter_data['total_families'] ?? 0; ?> assigned families
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Distribution Plan Summary -->
            <?php if (!empty($distribution_details) && !empty($victim_ids)): ?>
            <div style="margin-top: 20px; padding-top: 15px; border-top: 1px solid #eee;">
                <div style="display: flex; gap: 20px; flex-wrap: wrap;">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <div style="background: var(--primary); color: white; width: 30px; height: 30px; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                            <i class="fas fa-users"></i>
                        </div>
                        <div>
                            <div style="font-size: 0.9rem; color: var(--gray);">Total Families</div>
                            <div style="font-weight: bold; color: var(--dark);"><?php echo count($victim_ids); ?></div>
                        </div>
                    </div>
                    
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <div style="background: var(--info); color: white; width: 30px; height: 30px; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                            <i class="fas fa-box"></i>
                        </div>
                        <div>
                            <div style="font-size: 0.9rem; color: var(--gray);">Resource Types</div>
                            <div style="font-weight: bold; color: var(--dark);">
                                <?php 
                                $resource_count = 0;
                                $unique_resources = [];
                                foreach ($distribution_details as $row) {
                                    if ($row['resource_name'] && !in_array($row['resource_name'], $unique_resources)) {
                                        $unique_resources[] = $row['resource_name'];
                                        $resource_count++;
                                    }
                                }
                                echo $resource_count;
                                ?>
                            </div>
                        </div>
                    </div>
                    
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <div style="background: var(--success); color: white; width: 30px; height: 30px; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                            <i class="fas fa-map-marker-alt"></i>
                        </div>
                        <div>
                            <div style="font-size: 0.9rem; color: var(--gray);">Shelters</div>
                            <div style="font-weight: bold; color: var(--dark);"><?php echo count($families_by_shelter); ?></div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
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
        
        <!-- Stats Grid - UPDATED FOR SHELTERS -->
        <div class="stats-grid">
            <div class="stat-card total">
                <div class="stat-icon">
                    <i class="fas fa-home"></i>
                </div>
                <div class="stat-value"><?php echo $distribution_stats['total_shelters'] ?? 0; ?></div>
                <div class="stat-label">Total Shelters</div>
            </div>
            
            <div class="stat-card transit">
                <div class="stat-icon">
                    <i class="fas fa-truck-moving"></i>
                </div>
                <div class="stat-value"><?php echo $distribution_stats['in_transit_shelters'] ?? 0; ?></div>
                <div class="stat-label">In Transit</div>
            </div>
            
            <div class="stat-card delivered">
                <div class="stat-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-value"><?php echo $distribution_stats['completed_shelters'] ?? 0; ?></div>
                <div class="stat-label">Delivered</div>
            </div>
            
            <div class="stat-card your">
                <div class="stat-icon">
                    <i class="fas fa-user-check"></i>
                </div>
                <div class="stat-value">
                    <?php echo $personal_stats['my_completed_shelters'] ?? 0; ?>/<?php echo ($personal_stats['my_completed_shelters'] ?? 0) + ($personal_stats['my_in_transit_shelters'] ?? 0); ?>
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
                            Items prepared for delivery to shelter! Status: Dispatched
                        <?php elseif ($_GET['success'] == 'delivered'): ?>
                            Delivery to shelter completed successfully!
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
                    <p><strong>This confirms you have physically loaded the items and are leaving for the shelter.</strong></p>
                </div>
            </div>
            
            <div class="dispatch-steps">
                <p style="margin-bottom: 15px; font-weight: 600; color: var(--dark);">
                    <i class="fas fa-list-ol"></i> Follow these steps:
                </p>
                <div style="display: flex; gap: 20px; flex-wrap: wrap;">
                    <div style="flex: 1; min-width: 250px;">
                        <div class="step-number">1</div>
                        <span style="font-weight: 600; color: var(--dark);">Select families and their items below</span>
                        <p style="color: var(--gray); margin-top: 5px; font-size: 0.9rem;">
                            Choose which families to deliver to first
                        </p>
                    </div>
                    <div style="flex: 1; min-width: 250px;">
                        <div class="step-number">2</div>
                        <span style="font-weight: 600; color: var(--dark);">Click "Mark as Dispatched"</span>
                        <p style="color: var(--gray); margin-top: 5px; font-size: 0.9rem;">
                            Confirm you're leaving for the shelter
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
                <a href="#family-selection-section" class="btn btn-warning" style="padding: 10px 20px;">
                    <i class="fas fa-arrow-down"></i>
                    Go to Family Selection
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
                1. Your <strong>starting location</strong> is your assigned NGO: <strong><?php echo htmlspecialchars($ngo_details['NGOName'] ?? 'Distribution Center'); ?></strong><br>
                2. The <strong>purple marker</strong> shows the shelter location: <strong><?php echo htmlspecialchars($shelter_name); ?></strong><br>
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
                    <input type="hidden" name="shelter_name" value="<?php echo htmlspecialchars($shelter_name); ?>">
                    <input type="hidden" name="latitude" id="formLatitude" value="<?php echo $live_location['latitude'] ?? ($ngo_coordinates['lat'] ?? 2.1896); ?>">
                    <input type="hidden" name="longitude" id="formLongitude" value="<?php echo $live_location['longitude'] ?? ($ngo_coordinates['lng'] ?? 102.2501); ?>">
                    
                    <!-- Step 1: Status -->
                    <div class="form-step">
                        <div class="step-title">
                            <i class="fas fa-flag"></i>
                            Step 1: What's your status?
                        </div>
                        <div class="step-description">
                            Select your current delivery status to the shelter
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
                                <span class="status-label">Arrived at Shelter</span>
                            </button>
                            <button type="button" class="status-btn" data-status="delayed" onclick="selectStatus('delayed', this)">
                                <i class="fas fa-exclamation-triangle status-icon"></i>
                                <span class="status-label">Delayed</span>
                            </button>
                            <button type="button" class="status-btn" data-status="completed" onclick="selectStatus('completed', this)">
                                <i class="fas fa-check-double status-icon"></i>
                                <span class="status-label">Completed</span>
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
                                Drag the marker, search for a location above, OR type your location here.
                            </div>
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
        
        <!-- Shelter Families Selection Section - ONLY SHOW IF ITEMS NOT DISPATCHED -->
        <?php if ($shelter_data && !$items_dispatched && $has_pending_items): ?>
        <div class="simple-form" style="border-color: var(--info); margin-top: 30px;" id="family-selection-section">
            <div class="step-title" style="border-color: var(--info);">
                <i class="fas fa-users"></i>
                Step 1: Select Assigned Families to Deliver To
                <span style="font-size: 0.9rem; color: var(--gray); margin-left: 15px;">
                    (You have <?php echo count($shelter_victims); ?> assigned families in this shelter)
                </span>
            </div>
            
            <div class="alert alert-info">
                <div class="alert-icon">
                    <i class="fas fa-info-circle"></i>
                </div>
                <div>
                    <strong>Select assigned families to deliver to:</strong> Choose one or more families to see their specific needs.
                    Items will only be shown for selected families below.
                </div>
            </div>
            
            <div id="familySelectionContainer">
                <?php if (empty($shelter_victims)): ?>
                <div style="text-align: center; padding: 40px 20px; background: #f8f9fa; border-radius: 8px;">
                    <div style="font-size: 3rem; color: var(--info); margin-bottom: 15px;">
                        <i class="fas fa-users-slash"></i>
                    </div>
                    <h3 style="color: var(--dark); margin-bottom: 10px;">No Assigned Families</h3>
                    <p style="color: var(--gray); margin-bottom: 25px; max-width: 600px; margin-left: auto; margin-right: auto;">
                        You don't have any families assigned to you in <strong><?php echo htmlspecialchars($shelter_name); ?></strong> 
                        for this distribution. Please contact your coordinator if you believe this is an error.
                    </p>
                </div>
                <?php else: ?>
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 15px; margin-top: 15px;">
                    <?php 
                    $victim_counter = 0;
                    $total_assigned_families = count($shelter_victims);
                    foreach ($shelter_victims as $victim): 
                        $family_id = 'family_' . $victim_counter;
                        $victim_counter++;
                    ?>
                    <div class="family-card" onclick="toggleFamilySelection('<?php echo $family_id; ?>')"
                         id="<?php echo $family_id; ?>_card">
                        <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 10px;">
                            <div>
                                <div style="font-weight: 600; color: var(--dark); font-size: 1.1rem;">
                                    <?php echo htmlspecialchars($victim['full_name']); ?>
                                </div>
                                <div style="font-size: 0.9rem; color: var(--gray); margin-top: 5px;">
                                    <i class="fas fa-user-friends"></i> <?php echo $victim['family_members']; ?> family members
                                </div>
                            </div>
                            <input type="checkbox" 
                                   name="selected_families[]" 
                                   value="<?php echo $victim['victim_id']; ?>"
                                   id="<?php echo $family_id; ?>"
                                   style="transform: scale(1.3); accent-color: var(--primary);"
                                   onchange="updateFamilyCard('<?php echo $family_id; ?>')">
                        </div>
                        
                        <!-- Family Special Needs Indicators -->
                        <div style="display: flex; gap: 8px; margin-top: 10px; flex-wrap: wrap;">
                            <?php if ($victim['has_baby']): ?>
                            <span class="badge-shelter" style="font-size: 0.8rem; padding: 3px 8px;">
                                <i class="fas fa-baby"></i> Has Baby
                            </span>
                            <?php endif; ?>
                            <?php if ($victim['has_elderly']): ?>
                            <span class="badge-shelter" style="font-size: 0.8rem; padding: 3px 8px;">
                                <i class="fas fa-wheelchair"></i> Has Elderly
                            </span>
                            <?php endif; ?>
                            <?php if ($victim['has_disabled']): ?>
                            <span class="badge-shelter" style="font-size: 0.8rem; padding: 3px 8px;">
                                <i class="fas fa-universal-access"></i> Has Disabled
                            </span>
                            <?php endif; ?>
                            <?php if (!empty($victim['special_request'])): ?>
                            <span class="badge-shelter" style="font-size: 0.8rem; padding: 3px 8px;">
                                <i class="fas fa-star"></i> Special Needs
                            </span>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Family Needs Count -->
                        <?php 
                        $family_needs_count = 0;
                        $family_pending_needs = 0;
                        $family_in_transit_needs = 0;
                        $family_completed_needs = 0;
                        
                        if (!empty($needs_data)) {
                            foreach ($needs_data as $need) {
                                if ($need['victim_id'] == $victim['victim_id']) {
                                    $family_needs_count++;
                                    if ($need['need_status'] == 'pending') {
                                        $family_pending_needs++;
                                    } elseif ($need['need_status'] == 'in_transit') {
                                        $family_in_transit_needs++;
                                    } elseif ($need['need_status'] == 'fulfilled') {
                                        $family_completed_needs++;
                                    }
                                }
                            }
                        }
                        ?>
                        <div style="margin-top: 15px; padding-top: 10px; border-top: 1px solid #e0e0e0;">
                            <div style="font-size: 0.9rem; color: var(--dark); margin-bottom: 5px;">
                                <i class="fas fa-boxes"></i> 
                                <strong><?php echo $family_needs_count; ?></strong> total items
                            </div>
                            <?php if ($family_pending_needs > 0): ?>
                            <div style="font-size: 0.85rem; color: var(--warning);">
                                <i class="fas fa-clock"></i> <?php echo $family_pending_needs; ?> pending
                            </div>
                            <?php endif; ?>
                            <?php if ($family_in_transit_needs > 0): ?>
                            <div style="font-size: 0.85rem; color: var(--info);">
                                <i class="fas fa-truck-moving"></i> <?php echo $family_in_transit_needs; ?> in transit
                            </div>
                            <?php endif; ?>
                            <?php if ($family_completed_needs > 0): ?>
                            <div style="font-size: 0.85rem; color: var(--success);">
                                <i class="fas fa-check-circle"></i> <?php echo $family_completed_needs; ?> delivered
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <?php if ($total_assigned_families > 0): ?>
                <div style="display: flex; gap: 15px; margin-top: 25px; flex-wrap: wrap;">
                    <button type="button" class="btn btn-outline" onclick="selectAllFamilies()">
                        <i class="fas fa-check-double"></i>
                        Select All Families (<?php echo $total_assigned_families; ?>)
                    </button>
                    <button type="button" class="btn btn-primary" onclick="viewSelectedFamiliesItems()" id="viewItemsBtn">
                        <i class="fas fa-eye"></i>
                        View Items for Selected Families
                    </button>
                </div>
                <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Items Management Section (Initially Hidden) - ONLY SHOW IF ITEMS NOT DISPATCHED -->
        <?php if ($shelter_data && $has_pending_items && !$items_dispatched && !empty($shelter_victims)): ?>
        <div class="simple-form" style="margin-top: 30px; border-color: var(--warning); display: none;" id="items-section">
            <div class="step-title" style="border-color: var(--warning);">
                <i class="fas fa-boxes"></i>
                Items for Selected Assigned Families
                <span style="font-size: 0.9rem; color: var(--gray); margin-left: 15px;">
                    (<span id="selectedFamiliesCount">0</span> families selected, <span id="selectedItemsCount">0</span> items)
                </span>
                <button type="button" class="btn btn-outline" onclick="resetFamilyFilter()" style="margin-left: auto; padding: 8px 15px; font-size: 0.9rem;">
                    <i class="fas fa-times"></i> Reset Filter
                </button>
            </div>
            
            <?php if ($has_pending_items): ?>
            <div class="alert alert-info">
                <div class="alert-icon">
                    <i class="fas fa-info-circle"></i>
                </div>
                <div>
                    <strong>Ready to deliver to selected assigned families?</strong> Select items to mark as dispatched when you're ready to leave for <strong><?php echo htmlspecialchars($shelter_name); ?></strong>.
                    <br><strong style="color: var(--warning);">This must be done before using live tracking.</strong>
                </div>
            </div>
            
            <form method="POST" id="prepareForm">
                <input type="hidden" name="prepare_distribution" value="1">
                <input type="hidden" name="shelter_name" value="<?php echo htmlspecialchars($shelter_name); ?>">
                <input type="hidden" name="selected_family_ids" id="selectedFamilyIds" value="">
                
                <div class="item-grid">
                    <?php foreach ($needs_data as $need): 
                        if ($need['need_status'] == 'pending'):
                            $item_value = $need['need_id'] . '|' . $need['victim_id'];
                    ?>
                    <div class="item-card" 
                         onclick="toggleItemSelection(this, '<?php echo htmlspecialchars($item_value); ?>')"
                         id="item-<?php echo htmlspecialchars($item_value); ?>"
                         data-family-id="<?php echo $need['victim_id']; ?>"
                         style="display: none;">
                        <div class="item-header">
                            <div class="item-name">
                                <?php echo htmlspecialchars($need['resource_name']); ?>
                                <?php if ($need['priority'] == 'High'): ?>
                                <span class="badge-pending" style="background: #f8d7da; color: #721c24; margin-left: 5px;">HIGH</span>
                                <?php endif; ?>
                            </div>
                            <input type="checkbox" 
                                   name="distributed_items[]" 
                                   value="<?php echo htmlspecialchars($item_value); ?>"
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
                        <div class="victim-info">
                            <div style="font-size: 0.9rem; color: var(--dark);">
                                <i class="fas fa-user"></i> <?php echo htmlspecialchars($need['victim_name']); ?>
                                <span style="font-size: 0.8rem; color: var(--gray); margin-left: 10px;">
                                    (Family ID: <?php echo $need['victim_id']; ?>)
                                </span>
                            </div>
                            <div style="font-size: 0.8rem; color: var(--gray); margin-top: 5px;">
                                Family: <?php echo $need['family_members']; ?> members
                                <?php if (!empty($need['special_request'])): ?>
                                <span class="badge-shelter" style="margin-left: 5px;">Special Needs</span>
                                <?php endif; ?>
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
                    <button type="submit" class="btn btn-warning" onclick="return prepareSelectedFamiliesItems()">
                        <i class="fas fa-truck-loading"></i>
                        Mark as Dispatched for Selected Families
                    </button>
                </div>
            </form>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <!-- Delivery Completion Section - SHOW WHEN ITEMS ARE IN TRANSIT -->
        <?php if ($shelter_data && $has_in_transit_items && $items_dispatched): ?>
        <div class="simple-form" style="border-color: var(--success); margin-top: 30px;" id="delivery-completion-section">
            <div class="step-title" style="border-color: var(--success);">
                <i class="fas fa-check-circle"></i>
                Complete Delivery to Shelter
            </div>
            
            <div class="alert alert-success">
                <div class="alert-icon">
                    <i class="fas fa-info-circle"></i>
                </div>
                <div>
                    <strong>Arrived at shelter?</strong> Mark items as delivered and get shelter coordinator confirmation.
                    You have <strong><?php echo $dispatched_count; ?> items</strong> in transit for <strong><?php echo htmlspecialchars($shelter_name); ?></strong>.
                </div>
            </div>
            
            <form method="POST" id="completeForm" enctype="multipart/form-data">
                <input type="hidden" name="complete_delivery" value="1">
                <input type="hidden" name="shelter_name" value="<?php echo htmlspecialchars($shelter_name); ?>">
                <input type="hidden" name="selected_family_ids" id="selectedFamilyIdsComplete" value="">
                
                <div class="item-grid">
                    <?php 
                    $in_transit_items_count = 0;
                    foreach ($needs_data as $need): 
                        if ($need['need_status'] == 'in_transit'):
                            $in_transit_items_count++;
                            $item_value = $need['need_id'] . '|' . $need['victim_id'];
                    ?>
                    <div class="item-card" style="border-color: var(--info); background: #e8f4fc;"
                         id="delivery-<?php echo htmlspecialchars($item_value); ?>"
                         data-family-id="<?php echo $need['victim_id']; ?>">
                        <div class="item-header">
                            <div class="item-name">
                                <?php echo htmlspecialchars($need['resource_name']); ?>
                                <?php if ($need['priority'] == 'High'): ?>
                                <span class="badge-pending" style="background: #f8d7da; color: #721c24; margin-left: 5px;">HIGH</span>
                                <?php endif; ?>
                            </div>
                            <input type="checkbox" 
                                   name="delivered_items[]" 
                                   value="<?php echo htmlspecialchars($item_value); ?>"
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
                        <div class="victim-info">
                            <div style="font-size: 0.9rem; color: var(--dark);">
                                <i class="fas fa-user"></i> <?php echo htmlspecialchars($need['victim_name']); ?>
                            </div>
                            <div style="font-size: 0.8rem; color: var(--gray); margin-top: 5px;">
                                Family: <?php echo $need['family_members']; ?> members
                                <?php if (!empty($need['special_request'])): ?>
                                <span class="badge-shelter" style="margin-left: 5px;">Special Needs</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div style="margin-top: 15px;">
                            <span class="badge-in-transit">In Transit</span>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php endforeach; ?>
                </div>
                
                <?php if ($in_transit_items_count > 0): ?>
                <!-- DELIVERY NOTES -->
                <div class="form-group" style="margin-top: 30px;">
                    <label class="form-label" for="delivery_remarks">
                        <i class="fas fa-comment-alt"></i>
                        Delivery Notes (Optional)
                    </label>
                    <textarea id="delivery_remarks" name="delivery_remarks" class="form-control form-control-textarea" 
                              placeholder="Any notes about the shelter delivery... (e.g., Shelter coordinator name, special instructions, observations)"></textarea>
                </div>
                
                <!-- SHELTER COORDINATOR CONFIRMATION -->
                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-file-signature"></i>
                        Shelter Coordinator Confirmation
                    </label>
                    <div class="upload-area" onclick="document.getElementById('signature_image').click()">
                        <i class="fas fa-cloud-upload-alt"></i>
                        <p style="font-weight: 600; margin-bottom: 5px;">Upload Shelter Coordinator Signature/Photo</p>
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
                    <button type="submit" class="btn btn-success" onclick="return completeDelivery()">
                        <i class="fas fa-check-circle"></i>
                        Mark as Delivered to Shelter
                    </button>
                </div>
                <?php else: ?>
                <div style="text-align: center; padding: 40px 20px; background: #f8f9fa; border-radius: 8px;">
                    <div style="font-size: 3rem; color: var(--success); margin-bottom: 15px;">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <h3 style="color: var(--dark); margin-bottom: 10px;">No Items in Transit</h3>
                    <p style="color: var(--gray); margin-bottom: 25px; max-width: 600px; margin-left: auto; margin-right: auto;">
                        All items for <strong><?php echo htmlspecialchars($shelter_name); ?></strong> have been processed.
                        There are no items currently in transit.
                    </p>
                </div>
                <?php endif; ?>
            </form>
        </div>
        <?php elseif ($shelter_data && !$has_pending_items && !$has_in_transit_items): ?>
        <div class="simple-form" style="border-color: var(--success); text-align: center; padding: 50px 30px;">
            <div style="font-size: 4rem; color: var(--success); margin-bottom: 20px;">
                <i class="fas fa-check-circle"></i>
            </div>
            <h3 style="color: var(--dark); margin-bottom: 15px;">All Items Delivered to Shelter!</h3>
            <p style="color: var(--gray); margin-bottom: 25px; max-width: 600px; margin-left: auto; margin-right: auto;">
                All relief items for your assigned families in <strong><?php echo htmlspecialchars($shelter_name); ?></strong> have been delivered successfully. 
                <?php echo $shelter_data['total_families']; ?> assigned families have received assistance. Thank you for your service!
            </p>
            <a href="execute_distribution.php?distribution_id=<?php echo $distribution_id; ?>" class="btn btn-primary" style="padding: 12px 30px;">
                <i class="fas fa-home"></i>
                Select Another Shelter
            </a>
        </div>
        <?php endif; ?>
    </div>

    <!-- Leaflet JS -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
            integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo="
            crossorigin=""></script>
    
    <script>
        // Global variables for shelters
        let map = null;
        let volunteerMarker = null;
        let shelterMarker = null;
        let ngoMarker = null;
        let selectedLat = <?php echo $live_location['latitude'] ?? ($ngo_coordinates['lat'] ?? ($shelter_data['latitude'] ?? 2.1896)); ?>;
        let selectedLng = <?php echo $live_location['longitude'] ?? ($ngo_coordinates['lng'] ?? ($shelter_data['longitude'] ?? 102.2501)); ?>;
        let currentLocationName = "";
        let searchTimeout = null;
        let itemsDispatched = <?php echo $items_dispatched ? 'true' : 'false'; ?>;
        let hasPendingItems = <?php echo $has_pending_items ? 'true' : 'false'; ?>;
        let selectedFamilies = new Set();
        
        // NGO coordinates
        const ngoLat = <?php echo $ngo_coordinates['lat'] ?? 2.1896; ?>;
        const ngoLng = <?php echo $ngo_coordinates['lng'] ?? 102.2501; ?>;
        const ngoName = "<?php echo addslashes($ngo_details['NGOName'] ?? 'Distribution Center'); ?>";
        const ngoAddress = "<?php echo addslashes($ngo_details['Address'] ?? 'Distribution Center'); ?>";
        
        // Shelter coordinates
        <?php if ($shelter_data): ?>
        const shelterLat = <?php echo $shelter_data['latitude']; ?>;
        const shelterLng = <?php echo $shelter_data['longitude']; ?>;
        const shelterName = "<?php echo addslashes($shelter_name); ?>";
        const shelterDistrict = "<?php echo addslashes($shelter_data['district'] ?? 'Melaka'); ?>";
        const totalShelterFamilies = <?php echo $shelter_data['total_families'] ?? 0; ?>;
        <?php endif; ?>
        
        // Distribution info
        const distributionDate = "<?php echo $distribution_date_formatted ?? ''; ?>";
        const distributionTime = "<?php echo $distribution_time_formatted ?? ''; ?>";
        const coordinatorName = "<?php echo addslashes($coordinator_name); ?>";
        const totalDistributionFamilies = <?php echo count($victim_ids) ?? 0; ?>;

        // Initialize Map for shelters
        function initMap() {
            console.log("Initializing map...");
            const defaultCenter = [2.1896, 102.2501];
            map = L.map('liveMap').setView(defaultCenter, 13);
            
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
            
            // Add shelter marker if available
            <?php if ($shelter_data): ?>
            shelterMarker = L.marker([shelterLat, shelterLng], {
                icon: L.divIcon({
                    className: 'shelter-marker',
                    html: '<div style="background: #9b59b6; color: white; border-radius: 50%; width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; font-size: 18px; border: 3px solid white; box-shadow: 0 2px 5px rgba(0,0,0,0.3);"><i class="fas fa-home"></i></div>',
                    iconSize: [40, 40],
                    iconAnchor: [20, 20]
                })
            }).addTo(map);
            
            shelterMarker.bindPopup(`
                <div style="font-weight: bold; margin-bottom: 5px;">
                    <i class="fas fa-home"></i> ${shelterName}
                </div>
                <div style="font-size: 12px; color: #666;">
                    <i class="fas fa-map-marker-alt"></i> ${shelterDistrict}
                </div>
                <div style="font-size: 12px; color: #666;">
                    <i class="fas fa-users"></i> ${totalShelterFamilies} assigned families
                </div>
                <div style="margin-top: 10px;">
                    <span style="background: #9b59b6; color: white; padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: bold;">SHELTER DESTINATION</span>
                </div>
            `);
            <?php endif; ?>
            
            // Create initial volunteer marker (DRAGGABLE)
            volunteerMarker = L.marker([selectedLat, selectedLng], {
                draggable: itemsDispatched,
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
                    
                    document.getElementById('formLatitude').value = selectedLat;
                    document.getElementById('formLongitude').value = selectedLng;
                    
                    await getLocationNameFromOSM(selectedLat, selectedLng);
                    
                    document.getElementById('locationLoading').style.display = 'none';
                    showNotification('Location updated!', 'success');
                    
                    await sendLiveLocationUpdate(selectedLat, selectedLng);
                });
            } else {
                volunteerMarker.bindPopup(`
                    <div style="font-weight: bold; margin-bottom: 5px;">
                        <i class="fas fa-user"></i> Your Location
                    </div>
                    <div style="font-size: 12px; color: #666;">
                        <i class="fas fa-lock"></i> Marker locked until items dispatched
                    </div>
                `);
            }
            
            if (itemsDispatched) {
                volunteerMarker.bindPopup(`
                    <div style="font-weight: bold; margin-bottom: 5px;">
                        <i class="fas fa-user"></i> Your Location
                    </div>
                    <div style="font-size: 12px; color: #666;">
                        <i class="fas fa-map-marker-alt"></i> Drag me to your current location
                    </div>
                `);
            }
            
            // Fit bounds to show all markers
            const bounds = L.latLngBounds(
                [selectedLat, selectedLng],
                [ngoLat, ngoLng]
            );
            
            <?php if ($shelter_data): ?>
            bounds.extend([shelterLat, shelterLng]);
            <?php endif; ?>
            
            map.fitBounds(bounds, { padding: [50, 50] });
            
            // Initialize location display with NGO address
            updateLocationDisplay(`${ngoName} - ${ngoAddress}`, ngoLat, ngoLng);
            document.getElementById('current_location').value = `${ngoName} - ${ngoAddress}`;
            
            // Set up real-time sync
            const locationInput = document.getElementById('current_location');
            if (locationInput) {
                locationInput.addEventListener('input', function() {
                    updateLocationDisplayFromInput(this.value);
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
            
            console.log("Map initialized successfully!");
        }
        
        // ========================================
        // FAMILY SELECTION FUNCTIONS
        // ========================================
        
        function toggleFamilySelection(familyId) {
            const checkbox = document.getElementById(familyId);
            if (checkbox) {
                checkbox.checked = !checkbox.checked;
                updateFamilyCard(familyId);
            }
        }
        
        function updateFamilyCard(familyId) {
            const checkbox = document.getElementById(familyId);
            const card = document.getElementById(familyId + '_card');
            
            if (!checkbox || !card) {
                return;
            }
            
            if (checkbox.checked) {
                card.classList.add('selected');
                selectedFamilies.add(familyId);
            } else {
                card.classList.remove('selected');
                selectedFamilies.delete(familyId);
            }
        }
        
        function selectAllFamilies() {
            const checkboxes = document.querySelectorAll('input[name="selected_families[]"]');
            
            let selectedCount = 0;
            
            checkboxes.forEach(cb => {
                if (!cb.checked) {
                    cb.checked = true;
                    selectedCount++;
                    updateFamilyCard(cb.id);
                }
            });
            
            if (selectedCount > 0) {
                showNotification(`Selected all ${selectedCount} assigned families`, 'success');
            } else {
                showNotification('All assigned families are already selected', 'info');
            }
        }
        
        function viewSelectedFamiliesItems() {
            // Get selected families
            const checkboxes = document.querySelectorAll('input[name="selected_families[]"]:checked');
            const selectedFamilyIds = Array.from(checkboxes).map(cb => cb.value);
            
            if (selectedFamilyIds.length === 0) {
                showNotification('Please select at least one family first', 'error');
                return;
            }
            
            // First, show all items
            const allItems = document.querySelectorAll('.item-card');
            
            if (allItems.length === 0) {
                showNotification('No items found for any families in this shelter', 'warning');
                return;
            }
            
            // Hide all items first
            allItems.forEach(card => {
                card.style.display = 'none';
            });
            
            // Show only items for selected families
            let itemCount = 0;
            selectedFamilyIds.forEach(familyId => {
                const familyItems = document.querySelectorAll('.item-card[data-family-id="' + familyId + '"]');
                
                familyItems.forEach(card => {
                    card.style.display = 'block';
                    itemCount++;
                });
            });
            
            if (itemCount === 0) {
                showNotification('No items found for the selected families', 'warning');
                return;
            }
            
            // Update display
            const selectedFamiliesCount = document.getElementById('selectedFamiliesCount');
            const selectedItemsCount = document.getElementById('selectedItemsCount');
            
            if (selectedFamiliesCount) {
                selectedFamiliesCount.textContent = selectedFamilyIds.length;
            }
            if (selectedItemsCount) {
                selectedItemsCount.textContent = itemCount;
            }
            
            // Store selected family IDs for form submission
            const selectedFamilyIdsInput = document.getElementById('selectedFamilyIds');
            const selectedFamilyIdsCompleteInput = document.getElementById('selectedFamilyIdsComplete');
            
            if (selectedFamilyIdsInput) {
                selectedFamilyIdsInput.value = selectedFamilyIds.join(',');
            }
            if (selectedFamilyIdsCompleteInput) {
                selectedFamilyIdsCompleteInput.value = selectedFamilyIds.join(',');
            }
            
            // Show the items section
            const itemsSection = document.getElementById('items-section');
            if (itemsSection) {
                if (itemsSection.style.display === 'none') {
                    itemsSection.style.display = 'block';
                }
                itemsSection.scrollIntoView({ behavior: 'smooth' });
            }
            
            showNotification(`Showing ${itemCount} items for ${selectedFamilyIds.length} selected families`, 'success');
        }
        
        function resetFamilyFilter() {
            // Show all items
            const allItems = document.querySelectorAll('.item-card');
            allItems.forEach(card => {
                card.style.display = 'none';
            });
            
            // Uncheck all family checkboxes
            const checkboxes = document.querySelectorAll('input[name="selected_families[]"]');
            checkboxes.forEach(cb => {
                cb.checked = false;
                updateFamilyCard(cb.id);
            });
            
            selectedFamilies.clear();
            
            const itemsSection = document.getElementById('items-section');
            if (itemsSection) {
                itemsSection.style.display = 'none';
            }
            
            const selectedFamiliesCount = document.getElementById('selectedFamiliesCount');
            const selectedItemsCount = document.getElementById('selectedItemsCount');
            
            if (selectedFamiliesCount) {
                selectedFamiliesCount.textContent = '0';
            }
            if (selectedItemsCount) {
                selectedItemsCount.textContent = '0';
            }
            
            const selectedFamilyIdsInput = document.getElementById('selectedFamilyIds');
            const selectedFamilyIdsCompleteInput = document.getElementById('selectedFamilyIdsComplete');
            
            if (selectedFamilyIdsInput) {
                selectedFamilyIdsInput.value = '';
            }
            if (selectedFamilyIdsCompleteInput) {
                selectedFamilyIdsCompleteInput.value = '';
            }
            
            showNotification('Family filter reset. Please select families to view items.', 'info');
        }
        
        // ========================================
        // ITEM SELECTION FUNCTIONS
        // ========================================
        
        function toggleItemSelection(card, itemValue) {
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
        
        // ========================================
        // DELIVERY SELECTION FUNCTIONS
        // ========================================
        
        function toggleDeliverySelection(card, itemValue) {
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
        
        // ========================================
        // FORM SUBMISSION FUNCTIONS
        // ========================================
        
        function prepareSelectedFamiliesItems() {
            const familyCheckboxes = document.querySelectorAll('input[name="selected_families[]"]:checked');
            const selectedFamilyIds = Array.from(familyCheckboxes).map(cb => cb.value);
            
            if (selectedFamilyIds.length === 0) {
                showNotification('Please select at least one assigned family first', 'error');
                return false;
            }
            
            const itemCheckboxes = document.querySelectorAll('#prepareForm input[name="distributed_items[]"]:checked');
            if (itemCheckboxes.length === 0) {
                showNotification('Please select at least one item to dispatch', 'error');
                return false;
            }
            
            // Set the selected assigned family IDs in the form
            document.getElementById('selectedFamilyIds').value = selectedFamilyIds.join(',');
            
            // Show confirmation with details
            const confirmMsg = `Mark ${itemCheckboxes.length} items for ${selectedFamilyIds.length} selected assigned families as dispatched?`;
            
            if (confirm(confirmMsg)) {
                return true;
            }
            
            return false;
        }

        function completeDelivery() {
            const itemCheckboxes = document.querySelectorAll('#completeForm input[name="delivered_items[]"]:checked');
            const fileInput = document.getElementById('signature_image');
            
            if (itemCheckboxes.length === 0) {
                showNotification('Please select at least one item to mark as delivered', 'error');
                return false;
            }
            
            if (!fileInput.files || fileInput.files.length === 0) {
                showNotification('Please upload a shelter coordinator signature/image as confirmation', 'error');
                return false;
            }
            
            const confirmMsg = `Mark ${itemCheckboxes.length} items as delivered to ${shelterName}?`;
            
            if (confirm(confirmMsg)) {
                return true;
            }
            
            return false;
        }
        
        // ========================================
        // SIGNATURE IMAGE PREVIEW
        // ========================================
        
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
        
        // ========================================
        // MAP AND TRACKING FUNCTIONS
        // ========================================
        
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
                formData.append('shelter_name', '<?php echo addslashes($shelter_name); ?>');
                
                const response = await fetch('', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (!data.success) {
                    if (data.requires_dispatch) {
                        showNotification(data.error, 'error');
                        document.getElementById('family-selection-section').scrollIntoView({ behavior: 'smooth' });
                    }
                    return false;
                }
                
                return true;
                
            } catch (error) {
                console.error('Error sending live location:', error);
                return false;
            }
        }
        
        function checkTrackingAllowed() {
            if (!itemsDispatched && hasPendingItems) {
                showNotification('Please mark items as dispatched first before updating tracking', 'error');
                document.getElementById('family-selection-section').scrollIntoView({ behavior: 'smooth' });
                return false;
            }
            return true;
        }
        
        async function searchLocation(searchQuery = null) {
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
                
                const resultsContainer = document.getElementById('searchResults');
                resultsContainer.innerHTML = '';
                resultsContainer.style.display = 'none';
                
                const url = `https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query)}&limit=10&countrycodes=my`;
                
                const response = await fetch(url, {
                    headers: {
                        'User-Agent': 'DisasterReliefSystem/1.0',
                        'Accept': 'application/json',
                        'Accept-Language': 'en'
                    }
                });
                
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                
                const data = await response.json();
                
                if (data && data.length > 0) {
                    const malaysiaResults = data.filter(result => {
                        const displayName = result.display_name || '';
                        return displayName.toLowerCase().includes('malaysia') || 
                               displayName.toLowerCase().includes('melaka') ||
                               displayName.toLowerCase().includes('malacca');
                    });
                    
                    if (malaysiaResults.length > 0) {
                        resultsContainer.innerHTML = '';
                        malaysiaResults.forEach(result => {
                            const resultItem = document.createElement('div');
                            resultItem.className = 'search-result-item';
                            
                            let displayName = result.display_name || 'Unnamed location';
                            if (displayName.length > 80) {
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
                            `;
                            
                            resultItem.addEventListener('click', () => {
                                selectSearchResult(result);
                            });
                            
                            resultsContainer.appendChild(resultItem);
                        });
                        
                        resultsContainer.style.display = 'block';
                        document.getElementById('locationLoading').style.display = 'none';
                        document.getElementById('currentLocationText').textContent = 'Found approximate location(s).';
                        showNotification('Found approximate location(s)', 'warning');
                    }
                } else {
                    document.getElementById('locationLoading').style.display = 'none';
                    document.getElementById('currentLocationText').textContent = 'No results found. Try a different search term.';
                    showNotification('No locations found.', 'warning');
                }
                
            } catch (error) {
                console.error('Error searching location:', error);
                document.getElementById('locationLoading').style.display = 'none';
                document.getElementById('currentLocationText').textContent = 'Search service unavailable. Please drag the marker instead.';
                showNotification('Search service temporarily unavailable.', 'error');
            }
        }
        
        function selectSearchResult(result) {
            if (!checkTrackingAllowed()) {
                return;
            }
            
            selectedLat = parseFloat(result.lat);
            selectedLng = parseFloat(result.lon);
            
            volunteerMarker.setLatLng([selectedLat, selectedLng]);
            
            document.getElementById('formLatitude').value = selectedLat;
            document.getElementById('formLongitude').value = selectedLng;
            
            const locationName = result.display_name || `Coordinates: ${selectedLat.toFixed(6)}, ${selectedLng.toFixed(6)}`;
            
            document.getElementById('current_location').value = locationName;
            
            // Check proximity to shelter
            <?php if ($shelter_data): ?>
            const distanceToShelter = Math.sqrt(Math.pow(selectedLat - shelterLat, 2) + Math.pow(selectedLng - shelterLng, 2));
            if (distanceToShelter < 0.001) {
                document.getElementById('statusUpdate').value = 'arrived';
                document.querySelectorAll('.status-btn').forEach(btn => {
                    btn.classList.remove('active');
                    if (btn.dataset.status === 'arrived') {
                        btn.classList.add('active');
                    }
                });
                
                const shelterLocationName = `At ${shelterName} - Shelter Location`;
                document.getElementById('current_location').value = shelterLocationName;
                document.getElementById('currentLocationText').textContent = shelterLocationName;
                
                showNotification('You have arrived at the shelter!', 'success');
            }
            <?php endif; ?>
            
            // Check proximity to NGO
            const distanceToNGO = Math.sqrt(Math.pow(selectedLat - ngoLat, 2) + Math.pow(selectedLng - ngoLng, 2));
            if (distanceToNGO < 0.001) {
                document.getElementById('statusUpdate').value = 'departed';
                document.querySelectorAll('.status-btn').forEach(btn => {
                    btn.classList.remove('active');
                    if (btn.dataset.status === 'departed') {
                        btn.classList.add('active');
                    }
                });
                
                const ngoLocationName = `${ngoName} - Starting Point`;
                document.getElementById('current_location').value = ngoLocationName;
                document.getElementById('currentLocationText').textContent = ngoLocationName;
                
                showNotification('You are at the starting point', 'info');
            }
            
            document.getElementById('currentLocationTitle').textContent = "Your Location";
            document.getElementById('currentLocationText').textContent = locationName;
            document.getElementById('currentLocationText').textContent += ` (${selectedLat.toFixed(6)}, ${selectedLng.toFixed(6)})`;
            
            map.setView([selectedLat, selectedLng], 15);
            document.getElementById('searchResults').style.display = 'none';
            document.getElementById('locationSearch').value = '';
            
            sendLiveLocationUpdate(selectedLat, selectedLng);
            
            setTimeout(() => {
                getRefinedLocationName(selectedLat, selectedLng, locationName);
            }, 500);
            
            showNotification(`Location set`, 'success');
        }
        
        async function getRefinedLocationName(lat, lng, originalName) {
            try {
                const url = `https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&zoom=16`;
                
                const response = await fetch(url, {
                    headers: {
                        'User-Agent': 'DisasterReliefSystem/1.0',
                        'Accept': 'application/json'
                    }
                });
                
                if (!response.ok) {
                    return originalName;
                }
                
                const data = await response.json();
                
                if (data && data.display_name) {
                    let refinedName = data.display_name;
                    
                    // Check proximity to shelter
                    <?php if ($shelter_data): ?>
                    const distanceToShelter = Math.sqrt(Math.pow(lat - shelterLat, 2) + Math.pow(lng - shelterLng, 2));
                    if (distanceToShelter < 0.001) {
                        refinedName = `At ${shelterName} - Shelter Location`;
                        document.getElementById('statusUpdate').value = 'arrived';
                        document.querySelectorAll('.status-btn').forEach(btn => {
                            btn.classList.remove('active');
                            if (btn.dataset.status === 'arrived') {
                                btn.classList.add('active');
                            }
                        });
                        
                        document.getElementById('current_location').value = refinedName;
                        document.getElementById('currentLocationText').textContent = refinedName + ` (${lat.toFixed(6)}, ${lng.toFixed(6)})`;
                        return refinedName;
                    }
                    <?php endif; ?>
                    
                    // Check proximity to NGO
                    const distanceToNGO = Math.sqrt(Math.pow(lat - ngoLat, 2) + Math.pow(lng - ngoLng, 2));
                    if (distanceToNGO < 0.001) {
                        refinedName = `${ngoName} - ${ngoAddress}`;
                        document.getElementById('statusUpdate').value = 'departed';
                        document.querySelectorAll('.status-btn').forEach(btn => {
                            btn.classList.remove('active');
                            if (btn.dataset.status === 'departed') {
                                btn.classList.add('active');
                            }
                        });
                        
                        document.getElementById('current_location').value = refinedName;
                        document.getElementById('currentLocationText').textContent = refinedName + ` (${lat.toFixed(6)}, ${lng.toFixed(6)})`;
                        return refinedName;
                    }
                    
                    // Only update if significantly better
                    if (shouldUpdateName(originalName, refinedName)) {
                        const address = data.address || {};
                        if (refinedName.length > 80) {
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
                            
                            if (refinedName && !refinedName.toLowerCase().includes('malaysia')) {
                                refinedName += ', Malaysia';
                            }
                        }
                        
                        document.getElementById('current_location').value = refinedName;
                        document.getElementById('currentLocationText').textContent = refinedName + ` (${lat.toFixed(6)}, ${lng.toFixed(6)})`;
                        
                        showNotification('Location name refined', 'info');
                    }
                    
                    return refinedName;
                }
                
                return originalName;
                
            } catch (error) {
                console.error('Error in reverse geocoding:', error);
                return originalName;
            }
        }
        
        function shouldUpdateName(originalName, refinedName) {
            const originalLower = originalName.toLowerCase();
            const refinedLower = refinedName.toLowerCase();
            
            if (refinedName.length < originalName.length * 0.7) {
                return true;
            }
            
            if (originalName.startsWith('Coordinates:')) {
                return true;
            }
            
            const keyWords = ['universiti', 'teknikal', 'melaka', 'malaysia', 'campus', 'utem', 'jalan', 'street', 'road'];
            const containsKeyWords = keyWords.some(word => 
                originalLower.includes(word) && refinedLower.includes(word)
            );
            
            return containsKeyWords;
        }
        
        function updateLocationDisplay(locationName = "", lat = null, lng = null) {
            const locationText = document.getElementById('currentLocationText');
            const locationTitle = document.getElementById('currentLocationTitle');
            
            if (locationName) {
                locationTitle.textContent = "Your Location";
                let displayText = locationName;
                if (lat && lng) {
                    displayText += ` (${lat.toFixed(6)}, ${lng.toFixed(6)})`;
                }
                locationText.textContent = displayText;
                document.getElementById('current_location').value = locationName;
            } else if (selectedLat && selectedLng) {
                locationTitle.textContent = "Your Current Location";
                locationText.textContent = "Drag the marker on the map to set your location";
            }
        }
        
        function updateLocationDisplayFromInput(inputValue) {
            const locationText = document.getElementById('currentLocationText');
            const locationTitle = document.getElementById('currentLocationTitle');
            
            if (inputValue && inputValue.trim() !== '') {
                locationTitle.textContent = "Your Location";
                locationText.textContent = inputValue;
                
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
        
        async function getLocationNameFromOSM(lat, lng) {
            try {
                document.getElementById('locationLoading').style.display = 'block';
                
                const url = `https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&zoom=16`;
                
                const response = await fetch(url, {
                    headers: {
                        'User-Agent': 'DisasterReliefSystem/1.0',
                        'Accept': 'application/json'
                    }
                });
                
                if (!response.ok) {
                    throw new Error('API unavailable');
                }
                
                const data = await response.json();
                
                let locationName = '';
                
                if (data && data.display_name) {
                    locationName = data.display_name;
                    
                    // Check proximity to shelter
                    <?php if ($shelter_data): ?>
                    const distanceToShelter = Math.sqrt(Math.pow(lat - shelterLat, 2) + Math.pow(lng - shelterLng, 2));
                    if (distanceToShelter < 0.001) {
                        locationName = `At ${shelterName} - Shelter Location`;
                        document.getElementById('statusUpdate').value = 'arrived';
                        document.querySelectorAll('.status-btn').forEach(btn => {
                            btn.classList.remove('active');
                            if (btn.dataset.status === 'arrived') {
                                btn.classList.add('active');
                            }
                        });
                    }
                    <?php endif; ?>
                    
                    // Check proximity to NGO
                    const distanceToNGO = Math.sqrt(Math.pow(lat - ngoLat, 2) + Math.pow(lng - ngoLng, 2));
                    if (distanceToNGO < 0.001) {
                        locationName = `${ngoName} - ${ngoAddress}`;
                        document.getElementById('statusUpdate').value = 'departed';
                        document.querySelectorAll('.status-btn').forEach(btn => {
                            btn.classList.remove('active');
                            if (btn.dataset.status === 'departed') {
                                btn.classList.add('active');
                            }
                        });
                    }
                    
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
                        
                        if (locationName && !locationName.toLowerCase().includes('malaysia')) {
                            locationName += ', Malaysia';
                        }
                    }
                } else {
                    locationName = `Coordinates: ${lat.toFixed(6)}, ${lng.toFixed(6)}`;
                }
                
                currentLocationName = locationName;
                document.getElementById('current_location').value = locationName;
                
                document.getElementById('currentLocationText').textContent = locationName;
                document.getElementById('currentLocationText').textContent += ` (${lat.toFixed(6)}, ${lng.toFixed(6)})`;
                
                document.getElementById('locationLoading').style.display = 'none';
                
                return locationName;
                
            } catch (error) {
                console.error('Error in reverse geocoding:', error);
                const fallbackName = `Coordinates: ${lat.toFixed(6)}, ${lng.toFixed(6)}`;
                currentLocationName = fallbackName;
                document.getElementById('current_location').value = fallbackName;
                document.getElementById('currentLocationText').textContent = fallbackName;
                document.getElementById('locationLoading').style.display = 'none';
                return fallbackName;
            }
        }

function selectStatus(status, element) {
    if (!checkTrackingAllowed()) {
        return;
    }
    
    document.querySelectorAll('.status-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    
    element.classList.add('active');
    
    // Clean the status - ensure it matches valid values
    let cleanStatus = status.toLowerCase().trim();
    
    // Map button labels to exact database ENUM values
    const statusMap = {
        'departed': 'departed',
        'in_transit': 'in_transit',
        'arrived': 'arrived',
        'delayed': 'delayed',
        'completed': 'completed',
        'cancelled': 'cancelled',
        'on the way': 'in_transit',
        'just departed': 'departed',
        'arrived at shelter': 'arrived',
        'in_progress': 'in_progress',
        'pending': 'pending',
        'scheduled': 'scheduled'
    };
    
    // Get the exact value
    let exactStatus = statusMap[cleanStatus] || 'in_transit';
    
    document.getElementById('statusUpdate').value = exactStatus;
    
    // Auto-fill location input based on status
    const locationInput = document.getElementById('current_location');
    if (exactStatus === 'arrived' && locationInput.value === '') {
        <?php if ($shelter_data): ?>
        locationInput.value = `At ${shelterName} - Shelter Location`;
        updateLocationDisplayFromInput(locationInput.value);
        <?php endif; ?>
    } else if (exactStatus === 'departed' && locationInput.value === '') {
        locationInput.value = `${ngoName} - ${ngoAddress}`;
        updateLocationDisplayFromInput(locationInput.value);
    }
    
    showNotification('Status set to: ' + getStatusDisplayText(exactStatus), 'success');
}

function getStatusDisplayText(status) {
    const displayMap = {
        'pending': 'Pending',
        'departed': 'Just Departed',
        'in_transit': 'On The Way',
        'arrived': 'Arrived at Shelter',
        'delayed': 'Delayed',
        'completed': 'Completed',
        'cancelled': 'Cancelled',
        'scheduled': 'Scheduled',
        'in_progress': 'In Progress'
    };
    return displayMap[status] || status.replace('_', ' ');
}
        
        // Show notification
        function showNotification(message, type = 'info') {
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
            
            setTimeout(() => {
                notification.style.animation = 'slideOut 0.3s ease';
                setTimeout(() => {
                    if (notification.parentNode) {
                        notification.parentNode.removeChild(notification);
                    }
                }, 300);
            }, 3000);
        }
        
        // ========================================
        // FORM VALIDATION
        // ========================================
        
        // Form validation
        document.getElementById('trackingForm')?.addEventListener('submit', function(e) {
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
            return true;
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
                showNotification('Please upload a shelter coordinator signature/image as confirmation', 'error');
                return false;
            }
            
            return true;
        });
        
        // ========================================
        // INITIALIZATION
        // ========================================
        
        // Initialize when page loads
        document.addEventListener('DOMContentLoaded', function() {
            console.log("=== PAGE LOADED ===");
            
            // Initialize map
            initMap();
            
            console.log("=== INITIALIZATION COMPLETE ===");
        });
        
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