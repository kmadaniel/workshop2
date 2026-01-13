<?php
session_start();
require_once 'config.php';

// Initialize variables
$error = '';
$success = '';
$volunteer_info = null;
$upcoming_assignments = [];
$past_assignments = [];
$stats = [
    'total_families_helped' => 0,
    'total_items_distributed' => 0,
    'total_hours_volunteered' => 0,
    'active_assignments' => 0
];

$database = new Database();
$db = $database->getConnection();

// Create necessary tables if they don't exist
$create_cancellations_table = "
    CREATE TABLE IF NOT EXISTS assignment_cancellations (
        id INT PRIMARY KEY AUTO_INCREMENT,
        distribution_id INT NOT NULL,
        volunteer_id INT NOT NULL,
        reason TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (distribution_id),
        INDEX (volunteer_id),
        INDEX (created_at)
    )
";

$db->query($create_cancellations_table);

// Check if user is logged in
if (!isset($_SESSION['volunteer_id'])) {
    // Check for demo login
    if (isset($_POST['volunteer_id'])) {
        $volunteer_id = intval($_POST['volunteer_id']);
        if ($volunteer_id > 0) {
            $_SESSION['volunteer_id'] = $volunteer_id;
            $_SESSION['volunteer_name'] = "Demo Volunteer #" . $volunteer_id;
            $_SESSION['volunteer_email'] = "volunteer" . $volunteer_id . "@demo.com";
            $_SESSION['demo_mode'] = true;
        }
    }
    
    // If still not logged in, redirect to gateway
    if (!isset($_SESSION['volunteer_id'])) {
        header("Location: login_gateway.php");
        exit;
    }
}

$volunteer_id = $_SESSION['volunteer_id'];
$API_URL = 'http://10.147.17.30:8000/api_volunteer.php';
$DISASTER_API_URL = 'http://10.147.17.116:8000/disaster.php';

/* ----------------------------------------
   API HELPER FUNCTIONS - IMPROVED
---------------------------------------- */
function fetchDataFromAPI($url, $params = []) {
    $context = stream_context_create([
        'http' => [
            'timeout' => 10,
            'header' => "Accept: application/json\r\n" .
                       "User-Agent: DisasterReliefSystem/1.0\r\n"
        ],
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
        ]
    ]);
    
    try {
        $full_url = $url;
        if (!empty($params)) {
            $full_url .= '?' . http_build_query($params);
        }
        
        $response = @file_get_contents($full_url, false, $context);
        if ($response === FALSE) {
            // Try cURL as fallback
            $response = fetchWithCURL($full_url);
            if ($response === FALSE) {
                return ['success' => false, 'error' => 'API server not responding: ' . $full_url];
            }
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

function fetchWithCURL($url) {
    if (!function_exists('curl_init')) {
        return false;
    }
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false
    ]);
    
    $response = curl_exec($ch);
    $error = curl_error($ch);
    
    // Use proper resource check before closing
    if (is_resource($ch)) {
        curl_close($ch);
    }
    
    return $response !== false ? $response : false;
}

function fetchSingleFromAPI($url, $id = null) {
    $params = [];
    if ($id) {
        $params['id'] = $id;
    }
    
    $result = fetchDataFromAPI($url, $params);
    if ($result['success']) {
        return $result['data'];
    }
    return null;
}

function fetchAllFromAPI($url) {
    $result = fetchDataFromAPI($url);
    if (!$result['success']) {
        return ['success' => false, 'error' => $result['error'] ?? 'Unknown error fetching data'];
    }
    
    $data = $result['data'];
    
    // Handle different response formats
    if (isset($data['data']) && is_array($data['data'])) {
        $items = $data['data'];
    } elseif (isset($data['volunteers']) && is_array($data['volunteers'])) {
        $items = $data['volunteers'];
    } elseif (isset($data['disasters']) && is_array($data['disasters'])) {
        $items = $data['disasters'];
    } else {
        $items = is_array($data) ? $data : [$data];
    }
    
    return ['success' => true, 'data' => $items];
}

/* ========================================
   DISASTER INFORMATION FETCHING - IMPROVED
======================================== */
function getDisasterInfo($disaster_id) {
    global $DISASTER_API_URL;
    
    if (!$disaster_id) {
        return null;
    }
    
    try {
        // Try fetching specific disaster first
        $disaster_result = fetchDataFromAPI($DISASTER_API_URL, ['disaster_id' => $disaster_id]);
        
        if ($disaster_result['success']) {
            $disaster_data = $disaster_result['data'];
            
            // Handle different response formats
            if (isset($disaster_data['Disaster_Name'])) {
                return $disaster_data;
            } elseif (isset($disaster_data['disaster_name'])) {
                return $disaster_data;
            } elseif (isset($disaster_data['name'])) {
                return $disaster_data;
            } elseif (isset($disaster_data['data']) && is_array($disaster_data['data'])) {
                foreach ($disaster_data['data'] as $item) {
                    $item_id = $item['disaster_id'] ?? $item['id'] ?? null;
                    if ($item_id == $disaster_id) {
                        return $item;
                    }
                }
            } elseif (is_array($disaster_data)) {
                foreach ($disaster_data as $item) {
                    $item_id = $item['disaster_id'] ?? $item['id'] ?? null;
                    if ($item_id == $disaster_id) {
                        return $item;
                    }
                }
            }
        }
        
        // Fallback: Try to get all disasters and find the right one
        $all_disasters_result = fetchAllFromAPI($DISASTER_API_URL);
        if ($all_disasters_result['success']) {
            $all_disasters = $all_disasters_result['data'];
            
            foreach ($all_disasters as $disaster) {
                $disaster_id_from_api = $disaster['disaster_id'] ?? 
                                      $disaster['Disaster_ID'] ?? 
                                      $disaster['id'] ?? 0;
                if (intval($disaster_id_from_api) == $disaster_id) {
                    return $disaster;
                }
            }
        }
    } catch (Exception $e) {
        error_log("Error fetching disaster info for ID {$disaster_id}: " . $e->getMessage());
    }
    
    return null;
}

/* ----------------------------------------
   GET VOLUNTEER NAME FROM DATABASE - FALLBACK
---------------------------------------- */
function getVolunteerNameFromDB($volunteer_id, $db) {
    try {
        $query = "SELECT full_name, first_name, last_name, name FROM volunteers WHERE volunteer_id = ?";
        $stmt = $db->prepare($query);
        if ($stmt) {
            $stmt->bind_param("i", $volunteer_id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                // Try different field names
                if (!empty($row['full_name'])) {
                    return $row['full_name'];
                } elseif (!empty($row['first_name']) && !empty($row['last_name'])) {
                    return $row['first_name'] . ' ' . $row['last_name'];
                } elseif (!empty($row['name'])) {
                    return $row['name'];
                }
            }
            $stmt->close();
        }
    } catch (Exception $e) {
        error_log("Error fetching volunteer name from DB: " . $e->getMessage());
    }
    return null;
}

/* ----------------------------------------
   CHECK FOR NEW ASSIGNMENT NOTIFICATIONS
---------------------------------------- */
$new_assignment_notifications = [];
$show_notification_badge = false;

// Check localStorage via JavaScript will handle this, but also check database
try {
    if (is_object($db)) {
        // Create volunteer_alerts table if it doesn't exist
        $create_alerts_table = "
            CREATE TABLE IF NOT EXISTS volunteer_alerts (
                id INT PRIMARY KEY AUTO_INCREMENT,
                volunteer_id INT NOT NULL,
                distribution_id INT NOT NULL,
                message TEXT NOT NULL,
                is_read BOOLEAN DEFAULT FALSE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX (volunteer_id),
                INDEX (is_read)
            )
        ";
        $db->query($create_alerts_table);
        
        // Create status_changes table if it doesn't exist
        $create_status_changes_table = "
            CREATE TABLE IF NOT EXISTS status_changes (
                id INT PRIMARY KEY AUTO_INCREMENT,
                distribution_id INT NOT NULL,
                old_status VARCHAR(50),
                new_status VARCHAR(50),
                changed_by VARCHAR(50),
                reason TEXT,
                changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX (distribution_id),
                INDEX (changed_at)
            )
        ";
        $db->query($create_status_changes_table);
        
        $notification_query = "SELECT * FROM volunteer_alerts 
                              WHERE volunteer_id = ? AND is_read = 0 
                              AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
                              ORDER BY created_at DESC LIMIT 5";
        $stmt = $db->prepare($notification_query);
        if ($stmt) {
            $stmt->bind_param("i", $volunteer_id);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $new_assignment_notifications[] = $row;
            }
            $stmt->close();
            
            if (!empty($new_assignment_notifications)) {
                $show_notification_badge = true;
                
                // Mark as read after showing
                $mark_read_query = "UPDATE volunteer_alerts SET is_read = 1 WHERE volunteer_id = ? AND is_read = 0";
                $mark_stmt = $db->prepare($mark_read_query);
                if ($mark_stmt) {
                    $mark_stmt->bind_param("i", $volunteer_id);
                    $mark_stmt->execute();
                    $mark_stmt->close();
                }
            }
        }
    }
} catch (Exception $e) {
    // Silently continue
}

/* ----------------------------------------
   GET VOLUNTEER INFORMATION FROM API - IMPROVED
---------------------------------------- */
try {
    // Fetch all volunteers from API to find our specific volunteer
    $apiResult = fetchAllFromAPI($API_URL);
    
    if (!$apiResult['success']) {
        throw new Exception($apiResult['error'] ?? 'Unknown API error');
    }
    
    $all_volunteers = $apiResult['data'];
    
    // Find the specific volunteer by ID
    $foundVolunteer = null;
    foreach ($all_volunteers as $volunteer) {
        // Try different field names for volunteer ID
        $apiVolunteerId = $volunteer['VolunteerID'] ?? 
                         $volunteer['volunteer_id'] ?? 
                         $volunteer['id'] ?? 
                         $volunteer['Volunteer_ID'] ?? null;
        
        // Convert to integer and compare
        $apiVolunteerIdInt = intval($apiVolunteerId);
        
        if ($apiVolunteerIdInt === $volunteer_id) {
            $foundVolunteer = $volunteer;
            break;
        }
    }
    
    if (!$foundVolunteer) {
        // If not found in the array, try fetching by specific ID
        $specificResult = fetchDataFromAPI($API_URL, ['id' => $volunteer_id]);
        if ($specificResult['success']) {
            $foundVolunteer = $specificResult['data'];
        } else {
            // Fallback: Get from database if API fails
            $db_query = "SELECT * FROM volunteers WHERE volunteer_id = ?";
            $stmt = $db->prepare($db_query);
            if ($stmt) {
                $stmt->bind_param("i", $volunteer_id);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($row = $result->fetch_assoc()) {
                    $foundVolunteer = $row;
                }
                $stmt->close();
            }
            
            if (!$foundVolunteer) {
                throw new Exception("Volunteer ID $volunteer_id not found!");
            }
        }
    }
    
    // Map API fields to our expected format
    $volunteer_info = [
        'volunteer_id' => $volunteer_id,
        'name' => $foundVolunteer['FullName'] ?? 
                 $foundVolunteer['full_name'] ?? 
                 $foundVolunteer['name'] ?? 
                 ($foundVolunteer['FirstName'] ?? '') . ' ' . ($foundVolunteer['LastName'] ?? '') ?: 
                 'Volunteer ' . $volunteer_id,
        'email' => $foundVolunteer['Email'] ?? 
                  $foundVolunteer['email'] ?? '',
        'phone' => $foundVolunteer['Phone'] ?? 
                  $foundVolunteer['phone'] ?? '',
        'address' => $foundVolunteer['Address'] ?? 
                    $foundVolunteer['address'] ?? '',
        'ngo_affiliation' => $foundVolunteer['AssignedNGO'] ?? 
                            $foundVolunteer['ngo_affiliation'] ?? 
                            $foundVolunteer['NGO_Affiliation'] ?? '',
        'skill_category' => $foundVolunteer['SkillCategory'] ?? 
                           $foundVolunteer['skill_category'] ?? 
                           'Volunteer',
        'status' => $foundVolunteer['Status'] ?? 
                   $foundVolunteer['status'] ?? 
                   'Active',
        'role' => 'Volunteer'
    ];
    
    // After mapping API fields, check if name is still generic
    if (strpos($volunteer_info['name'], 'Volunteer ') === 0 || 
        strpos($volunteer_info['name'], 'Demo Volunteer') === 0 ||
        empty(trim($volunteer_info['name'])) ||
        $volunteer_info['name'] == 'Volunteer ' . $volunteer_id) {
        
        // Try to get name from database
        $db_name = getVolunteerNameFromDB($volunteer_id, $db);
        if ($db_name) {
            $volunteer_info['name'] = $db_name;
        } else {
            // Use session name if available
            if (!empty($_SESSION['volunteer_name']) && 
                !strpos($_SESSION['volunteer_name'], 'Demo Volunteer') === 0) {
                $volunteer_info['name'] = $_SESSION['volunteer_name'];
            }
        }
    }
    
} catch (Exception $e) {
    $error = "Error loading volunteer information: " . $e->getMessage();
    
    // Fallback to session data if API fails
    $volunteer_info = [
        'volunteer_id' => $volunteer_id,
        'name' => $_SESSION['volunteer_name'] ?? 'Volunteer',
        'email' => $_SESSION['volunteer_email'] ?? '',
        'role' => 'Volunteer',
        'status' => 'Active'
    ];
}

/* ----------------------------------------
   CHECK VOLUNTEER STATUS - IMPORTANT
   If volunteer is inactive in API, don't show assignments
---------------------------------------- */
$is_volunteer_active = true;
if (isset($volunteer_info['status']) && 
    (strtolower($volunteer_info['status']) === 'inactive' || 
     strtolower($volunteer_info['status']) === 'suspended' ||
     strtolower($volunteer_info['status']) === 'pending')) {
    $is_volunteer_active = false;
    $error = "Your volunteer account is currently <strong>{$volunteer_info['status']}</strong>. You cannot access distribution assignments.";
}

/* ----------------------------------------
   GET VOLUNTEER'S DISTRIBUTION ASSIGNMENTS
   WITH LATEST TRACKING STATUS - IMPROVED
---------------------------------------- */
if ($volunteer_info && $is_volunteer_active) {
    try {
        // Note: Using your existing distribution_volunteer table structure
        // Your table has: id, volunteer_id, distribution_id, role, status, assigned_timestamp, completed_at
        
        // Get active assignments from distribution_volunteer table (excluding cancelled)
        $active_query = "
            SELECT 
                dv.*,
                d.*,
                dt.status as latest_tracking_status,
                dt.current_location as latest_location,
                dt.created_at as last_update,
                (SELECT status FROM distribution_tracking 
                 WHERE distribution_id = dv.distribution_id 
                 AND volunteer_id = dv.volunteer_id
                 ORDER BY created_at DESC LIMIT 1) as distribution_tracking_status
            FROM distribution_volunteer dv
            JOIN distribution d ON dv.distribution_id = d.distribution_id
            LEFT JOIN (
                SELECT distribution_id, volunteer_id, status, current_location, created_at
                FROM distribution_tracking 
                WHERE (distribution_id, volunteer_id, created_at) IN (
                    SELECT distribution_id, volunteer_id, MAX(created_at)
                    FROM distribution_tracking
                    WHERE volunteer_id = ?
                    GROUP BY distribution_id, volunteer_id
                )
            ) dt ON dv.distribution_id = dt.distribution_id AND dv.volunteer_id = dt.volunteer_id
            WHERE dv.volunteer_id = ? 
            AND dv.status IN ('Assigned', 'Active', 'In Progress', 'In Transit', 'Arrived', 'Delayed', 'Completed')
            AND dv.distribution_id NOT IN (
                SELECT distribution_id FROM assignment_cancellations WHERE volunteer_id = dv.volunteer_id
            )
            ORDER BY 
                CASE 
                    WHEN dv.status = 'Active' THEN 1
                    WHEN dv.status = 'In Progress' THEN 2
                    WHEN dv.status = 'In Transit' THEN 3
                    WHEN dv.status = 'Arrived' THEN 4
                    WHEN dv.status = 'Delayed' THEN 5
                    WHEN dv.status = 'Completed' THEN 6
                    WHEN dv.status = 'Assigned' THEN 7
                    ELSE 8
                END,
                d.date ASC
        ";
        
        $stmt = $db->prepare($active_query);
        if ($stmt) {
            $stmt->bind_param("ii", $volunteer_id, $volunteer_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $raw_assignments = $result->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
            
            // Fetch disaster information for each assignment
            foreach ($raw_assignments as $assignment) {
                $disaster_info = null;
                if (isset($assignment['disaster_id']) && $assignment['disaster_id']) {
                    $disaster_info = getDisasterInfo($assignment['disaster_id']);
                }
                
                // Determine disaster name and location
                $disaster_name = 'Unknown Disaster';
                $disaster_location = 'Unknown Location';
                
                if ($disaster_info) {
                    $disaster_name = $disaster_info['Disaster_Name'] ?? 
                                   $disaster_info['disaster_name'] ?? 
                                   $disaster_info['name'] ?? 
                                   'Disaster';
                    $disaster_location = $disaster_info['Location'] ?? 
                                       $disaster_info['location'] ?? 
                                       'Unknown Location';
                } else {
                    // Fallback: Get disaster name from distribution table if available
                    if (!empty($assignment['disaster_name'])) {
                        $disaster_name = $assignment['disaster_name'];
                    }
                    if (!empty($assignment['location'])) {
                        $disaster_location = $assignment['location'];
                    }
                }
                
                // ========================================
                // UPDATED: Get needs count from distribution_log with FIXED QUERY
                // ========================================
                $needs_count = 0;
                $victims_count = 0;

                if ($assignment['distribution_id']) {
                    try {
                        // First check if the table exists and has data
                        $table_check = $db->query("SHOW TABLES LIKE 'distribution_log'");
                        if ($table_check && $table_check->num_rows > 0) {
                            // Check if need_id column exists
                            $column_check = $db->query("SHOW COLUMNS FROM distribution_log LIKE 'need_id'");
                            if ($column_check && $column_check->num_rows > 0) {
                                // Updated query to include all statuses - FIXED
                                $log_query = "
                                    SELECT 
                                        COUNT(DISTINCT need_id) as total_needs, 
                                        COUNT(DISTINCT victim_id) as total_victims
                                    FROM distribution_log 
                                    WHERE distribution_id = ? 
                                    AND volunteer_id = ?
                                    AND status IN ('in_transit', 'completed', 'partial', 'skipped')
                                ";
                                
                                $log_stmt = $db->prepare($log_query);
                                if ($log_stmt) {
                                    $log_stmt->bind_param("ii", $assignment['distribution_id'], $volunteer_id);
                                    $log_stmt->execute();
                                    $log_result = $log_stmt->get_result();
                                    if ($log_row = $log_result->fetch_assoc()) {
                                        $needs_count = $log_row['total_needs'] ?? 0;
                                        $victims_count = $log_row['total_victims'] ?? 0;
                                        
                                        // Debug logging
                                        error_log("DEBUG - distribution_log query results: Needs=" . $needs_count . ", Victims=" . $victims_count . 
                                                " for distribution " . $assignment['distribution_id'] . ", volunteer " . $volunteer_id);
                                    }
                                    $log_stmt->close();
                                }
                            } else {
                                error_log("DEBUG - need_id column not found in distribution_log table");
                                
                                // Alternative query without need_id
                                $log_query = "
                                    SELECT 
                                        COUNT(DISTINCT victim_id) as total_victims
                                    FROM distribution_log 
                                    WHERE distribution_id = ? 
                                    AND volunteer_id = ?
                                    AND status IN ('in_transit', 'completed', 'partial', 'skipped')
                                ";
                                
                                $log_stmt = $db->prepare($log_query);
                                if ($log_stmt) {
                                    $log_stmt->bind_param("ii", $assignment['distribution_id'], $volunteer_id);
                                    $log_stmt->execute();
                                    $log_result = $log_stmt->get_result();
                                    if ($log_row = $log_result->fetch_assoc()) {
                                        $victims_count = $log_row['total_victims'] ?? 0;
                                        // For needs count, we'll use victims count as fallback
                                        $needs_count = $victims_count;
                                        
                                        error_log("DEBUG - Fallback query (no need_id): Victims=" . $victims_count);
                                    }
                                    $log_stmt->close();
                                }
                            }
                        }
                        
                        // ========================================
                        // FALLBACK: If no data in distribution_log, check distribution_items
                        // ========================================
                        if ($needs_count == 0 && $victims_count == 0) {
                            // First check if distribution_items table exists
                            $items_table_check = $db->query("SHOW TABLES LIKE 'distribution_items'");
                            if ($items_table_check && $items_table_check->num_rows > 0) {
                                $items_query = "
                                    SELECT 
                                        COUNT(DISTINCT victim_id) as total_victims
                                    FROM distribution_items 
                                    WHERE distribution_id = ?
                                ";
                                $items_stmt = $db->prepare($items_query);
                                if ($items_stmt) {
                                    $items_stmt->bind_param("i", $assignment['distribution_id']);
                                    $items_stmt->execute();
                                    $items_result = $items_stmt->get_result();
                                    if ($items_row = $items_result->fetch_assoc()) {
                                        $victims_count = $items_row['total_victims'] ?? 0;
                                        $needs_count = $victims_count;
                                        error_log("DEBUG - Fallback to distribution_items: Victims=" . $victims_count);
                                    }
                                    $items_stmt->close();
                                }
                            } else {
                                // Fallback to distribution table if distribution_items doesn't exist
                                $fallback_query = "
                                    SELECT 
                                        COUNT(*) as total_items
                                    FROM distribution 
                                    WHERE distribution_id = ?
                                ";
                                $fallback_stmt = $db->prepare($fallback_query);
                                if ($fallback_stmt) {
                                    $fallback_stmt->bind_param("i", $assignment['distribution_id']);
                                    $fallback_stmt->execute();
                                    $fallback_result = $fallback_stmt->get_result();
                                    if ($fallback_row = $fallback_result->fetch_assoc()) {
                                        $needs_count = $fallback_row['total_items'] ?? 1;
                                        $victims_count = $fallback_row['total_items'] ?? 1;
                                        error_log("DEBUG - Fallback to distribution table: Items=" . $needs_count);
                                    }
                                    $fallback_stmt->close();
                                }
                            }
                        }
                        
                    } catch (Exception $e) {
                        error_log("Error fetching distribution log data: " . $e->getMessage());
                        // Set default values to prevent page break
                        $needs_count = 1;
                        $victims_count = 1;
                    }
                }
                
                // Format last update time
                $last_update_formatted = '';
                $time_ago = '';
                if (!empty($assignment['last_update'])) {
                    $last_update = new DateTime($assignment['last_update']);
                    $now = new DateTime();
                    $interval = $last_update->diff($now);
                    
                    if ($interval->y > 0) {
                        $time_ago = $interval->y . ' year' . ($interval->y > 1 ? 's' : '') . ' ago';
                    } elseif ($interval->m > 0) {
                        $time_ago = $interval->m . ' month' . ($interval->m > 1 ? 's' : '') . ' ago';
                    } elseif ($interval->d > 0) {
                        $time_ago = $interval->d . ' day' . ($interval->d > 1 ? 's' : '') . ' ago';
                    } elseif ($interval->h > 0) {
                        $time_ago = $interval->h . ' hour' . ($interval->h > 1 ? 's' : '') . ' ago';
                    } elseif ($interval->i > 0) {
                        $time_ago = $interval->i . ' minute' . ($interval->i > 1 ? 's' : '') . ' ago';
                    } else {
                        $time_ago = 'Just now';
                    }
                    
                    $last_update_formatted = date('h:i A', strtotime($assignment['last_update'])) . ' (' . $time_ago . ')';
                }
                
                // Get volunteer's current status for this distribution
                $volunteer_status = $assignment['status'] ?? 'Assigned';
                
                // Determine button text based on status
                $button_text = 'Start Distribution';
                if ($volunteer_status == 'Active' || $volunteer_status == 'In Progress') {
                    $button_text = 'Continue Distribution';
                } elseif ($volunteer_status == 'In Transit' || $volunteer_status == 'Arrived' || $volunteer_status == 'Delayed') {
                    $button_text = 'Resume Distribution';
                } elseif ($volunteer_status == 'Completed') {
                    $button_text = 'View Completed';
                }
                
                $upcoming_assignments[] = [
                    'distribution_id' => $assignment['distribution_id'],
                    'date' => $assignment['date'],
                    'status' => $volunteer_status, // Use volunteer's status
                    'latest_tracking_status' => $assignment['latest_tracking_status'] ?? $assignment['distribution_tracking_status'] ?? null,
                    'latest_location' => $assignment['latest_location'] ?? null,
                    'last_update' => $last_update_formatted,
                    'time_ago' => $time_ago,
                    'role' => $assignment['role'] ?? 'Volunteer',
                    'Disaster_Name' => $disaster_name,
                    'disaster_location' => $disaster_location,
                    'total_victims' => $victims_count,
                    'total_needs' => $needs_count,
                    'disaster_id' => $assignment['disaster_id'],
                    'distribution_status' => $assignment['status'] ?? 'In Transit',
                    'distribution_location' => $assignment['location'] ?? 'N/A',
                    'button_text' => $button_text
                ];
            }
        }
        
    } catch (Exception $e) {
        $error .= "<br>Error loading active assignments: " . $e->getMessage();
    }
    
    // Get completed assignments - ONLY ACTUALLY COMPLETED (excluding cancelled)
    try {
        $completed_query = "
            SELECT 
                dv.*,
                d.*
            FROM distribution_volunteer dv
            JOIN distribution d ON dv.distribution_id = d.distribution_id
            WHERE dv.volunteer_id = ? 
            AND dv.status = 'Completed'  -- Only show actually completed
            AND dv.distribution_id NOT IN (
                SELECT distribution_id FROM assignment_cancellations WHERE volunteer_id = dv.volunteer_id
            )
            GROUP BY dv.distribution_id, dv.volunteer_id
            ORDER BY d.date DESC
            LIMIT 10
        ";
        
        $stmt = $db->prepare($completed_query);
        if ($stmt) {
            $stmt->bind_param("i", $volunteer_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $raw_completed = $result->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
            
            // Process completed assignments
            foreach ($raw_completed as $assignment) {
                $disaster_info = null;
                if (isset($assignment['disaster_id']) && $assignment['disaster_id']) {
                    $disaster_info = getDisasterInfo($assignment['disaster_id']);
                }
                
                // Get distribution log stats
                $victims_helped = 0;
                $items_distributed = 0;
                
                try {
                    // Check if distribution_log table exists
                    $table_check = $db->query("SHOW TABLES LIKE 'distribution_log'");
                    if ($table_check && $table_check->num_rows > 0) {
                        // Check if need_id column exists
                        $column_check = $db->query("SHOW COLUMNS FROM distribution_log LIKE 'need_id'");
                        if ($column_check && $column_check->num_rows > 0) {
                            $stats_query = "
                                SELECT 
                                    COUNT(DISTINCT victim_id) as victims_helped,
                                    COUNT(DISTINCT need_id) as items_distributed
                                FROM distribution_log
                                WHERE distribution_id = ? AND volunteer_id = ?
                            ";
                        } else {
                            $stats_query = "
                                SELECT 
                                    COUNT(DISTINCT victim_id) as victims_helped
                                FROM distribution_log
                                WHERE distribution_id = ? AND volunteer_id = ?
                            ";
                        }
                        
                        $stmt = $db->prepare($stats_query);
                        if ($stmt) {
                            $stmt->bind_param("ii", $assignment['distribution_id'], $volunteer_id);
                            $stmt->execute();
                            $stats_result = $stmt->get_result();
                            $log_stats = $stats_result->fetch_assoc();
                            $stmt->close();
                            
                            $victims_helped = $log_stats['victims_helped'] ?? 0;
                            $items_distributed = $log_stats['items_distributed'] ?? $victims_helped;
                        }
                    }
                } catch (Exception $e) {
                    error_log("Error fetching completed assignment stats: " . $e->getMessage());
                }
                
                $past_assignments[] = [
                    'distribution_id' => $assignment['distribution_id'],
                    'date' => $assignment['date'],
                    'status' => 'Completed',
                    'Disaster_Name' => $disaster_info['Disaster_Name'] ?? 
                                     $disaster_info['name'] ?? 
                                     $assignment['disaster_name'] ?? 
                                     'Unknown Disaster',
                    'disaster_location' => $disaster_info['Location'] ?? 
                                         $disaster_info['location'] ?? 
                                         $assignment['location'] ?? 
                                         'Unknown Location',
                    'victims_helped' => $victims_helped,
                    'items_distributed' => $items_distributed,
                    'disaster_id' => $assignment['disaster_id']
                ];
            }
        }
        
    } catch (Exception $e) {
        // Silently continue if distribution_log doesn't exist yet
        error_log("Error loading completed assignments: " . $e->getMessage());
    }
}

/* ----------------------------------------
   GET STATISTICS
---------------------------------------- */
if ($volunteer_info && $is_volunteer_active) {
    $stats['active_assignments'] = count($upcoming_assignments);
    
    try {
        // Check if distribution_log table exists
        $table_check = $db->query("SHOW TABLES LIKE 'distribution_log'");
        if ($table_check && $table_check->num_rows > 0) {
            // Check if need_id column exists
            $column_check = $db->query("SHOW COLUMNS FROM distribution_log LIKE 'need_id'");
            if ($column_check && $column_check->num_rows > 0) {
                $stats_query = "
                    SELECT 
                        COUNT(DISTINCT victim_id) as total_families_helped,
                        COUNT(DISTINCT need_id) as total_items_distributed
                    FROM distribution_log
                    WHERE volunteer_id = ?
                    AND distribution_id NOT IN (
                        SELECT distribution_id FROM assignment_cancellations WHERE volunteer_id = ?
                    )
                ";
            } else {
                // Fallback query without need_id
                $stats_query = "
                    SELECT 
                        COUNT(DISTINCT victim_id) as total_families_helped
                    FROM distribution_log
                    WHERE volunteer_id = ?
                    AND distribution_id NOT IN (
                        SELECT distribution_id FROM assignment_cancellations WHERE volunteer_id = ?
                    )
                ";
            }
            
            $stmt = $db->prepare($stats_query);
            if ($stmt) {
                $stmt->bind_param("ii", $volunteer_id, $volunteer_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $stats_data = $result->fetch_assoc();
                $stmt->close();
                
                if ($stats_data) {
                    $stats['total_families_helped'] = $stats_data['total_families_helped'] ?? 0;
                    $stats['total_items_distributed'] = $stats_data['total_items_distributed'] ?? $stats['total_families_helped'];
                }
            }
        }
        
    } catch (Exception $e) {
        // Silently continue
        error_log("Error loading statistics: " . $e->getMessage());
    }

    $stats['total_hours_volunteered'] = count($past_assignments) * 3;
}

/* ----------------------------------------
   HANDLE ACTION REQUESTS - UPDATED
---------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $distribution_id = $_POST['distribution_id'] ?? 0;
    $action = $_POST['action'] ?? '';
    $reason = $_POST['reason'] ?? '';
    
    // Handle logout
    if ($action === 'logout') {
        session_destroy();
        header("Location: http://10.147.17.30:8000/login.php");
        exit;
    }
    
    // Handle notification dismissal
    if ($action === 'dismiss_notification') {
        $notification_id = $_POST['notification_id'] ?? 0;
        if ($notification_id && is_object($db)) {
            try {
                $dismiss_query = "UPDATE volunteer_alerts SET is_read = 1 WHERE id = ? AND volunteer_id = ?";
                $stmt = $db->prepare($dismiss_query);
                if ($stmt) {
                    $stmt->bind_param("ii", $notification_id, $volunteer_id);
                    $stmt->execute();
                    $stmt->close();
                }
            } catch (Exception $e) {
                error_log("Error dismissing notification: " . $e->getMessage());
            }
        }
        exit;
    }
    
    // Handle start distribution - FIXED: Pass status to execute_distribution.php
    if ($action === 'start_distribution') {
        if ($distribution_id) {
            // Get current status for this distribution
            $status_query = "SELECT status FROM distribution_volunteer WHERE distribution_id = ? AND volunteer_id = ?";
            $stmt = $db->prepare($status_query);
            if ($stmt) {
                $stmt->bind_param("ii", $distribution_id, $volunteer_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $row = $result->fetch_assoc();
                $stmt->close();
                
                $current_status = $row['status'] ?? 'Assigned';
                
                // Update to Active if it's still Assigned
                if ($current_status == 'Assigned') {
                    $update_query = "UPDATE distribution_volunteer SET status = 'Active' WHERE distribution_id = ? AND volunteer_id = ?";
                    $update_stmt = $db->prepare($update_query);
                    if ($update_stmt) {
                        $update_stmt->bind_param("ii", $distribution_id, $volunteer_id);
                        $update_stmt->execute();
                        $update_stmt->close();
                        $current_status = 'Active';
                    }
                }
                
                // Redirect to execute_distribution.php with the current status
                header("Location: execute_distribution.php?distribution_id=" . $distribution_id . "&status=" . urlencode($current_status));
                exit;
            } else {
                header("Location: execute_distribution.php?distribution_id=" . $distribution_id);
                exit;
            }
        }
    }
    
    // Handle assignment confirmation
    elseif ($action === 'confirm_assignment') {
        try {
            $db->begin_transaction();
            
            $distribution_id = $_POST['distribution_id'] ?? 0;
            $volunteer_id = $_SESSION['volunteer_id'];
            
            if (!$distribution_id || !$volunteer_id) {
                throw new Exception("Missing distribution ID or volunteer ID");
            }
            
            // 1. Update distribution_volunteer status to 'Active'
            $update_query = "UPDATE distribution_volunteer SET status = 'Active' WHERE distribution_id = ? AND volunteer_id = ?";
            $stmt = $db->prepare($update_query);
            if ($stmt) {
                $stmt->bind_param("ii", $distribution_id, $volunteer_id);
                if (!$stmt->execute()) {
                    throw new Exception("Failed to update volunteer status: " . $stmt->error);
                }
                $stmt->close();
            } else {
                throw new Exception("Failed to prepare update query");
            }
            
            // 2. Check if this distribution needs status update in main distribution table
            $check_distribution_query = "SELECT status FROM distribution WHERE distribution_id = ?";
            $check_stmt = $db->prepare($check_distribution_query);
            $current_distribution_status = '';
            if ($check_stmt) {
                $check_stmt->bind_param("i", $distribution_id);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();
                if ($check_row = $check_result->fetch_assoc()) {
                    $current_distribution_status = $check_row['status'] ?? '';
                }
                $check_stmt->close();
            }
            
            // If distribution is still in 'Assigned' status, update it to 'In Progress'
            if ($current_distribution_status === 'Assigned') {
                $update_dist_query = "UPDATE distribution SET status = 'In Progress' WHERE distribution_id = ?";
                $dist_stmt = $db->prepare($update_dist_query);
                if ($dist_stmt) {
                    $dist_stmt->bind_param("i", $distribution_id);
                    $dist_stmt->execute();
                    $dist_stmt->close();
                    
                    // Log the status change if table exists
                    $new_status = 'In Progress';
                    $table_check = $db->query("SHOW TABLES LIKE 'status_changes'");
                    if ($table_check && $table_check->num_rows > 0) {
                        $log_change_query = "INSERT INTO status_changes (distribution_id, old_status, new_status, changed_by, reason) VALUES (?, ?, ?, 'volunteer', 'Volunteer confirmed assignment')";
                        $log_change_stmt = $db->prepare($log_change_query);
                        if ($log_change_stmt) {
                            $log_change_stmt->bind_param("iss", $distribution_id, $current_distribution_status, $new_status);
                            $log_change_stmt->execute();
                            $log_change_stmt->close();
                        }
                    }
                }
            }
            
            // 3. Create volunteer alert/notification if table exists
            $alert_message = "You have confirmed your assignment for Distribution #{$distribution_id}. You can now start the distribution when ready.";
            $table_check = $db->query("SHOW TABLES LIKE 'volunteer_alerts'");
            if ($table_check && $table_check->num_rows > 0) {
                $alert_query = "INSERT INTO volunteer_alerts (volunteer_id, distribution_id, message) VALUES (?, ?, ?)";
                $alert_stmt = $db->prepare($alert_query);
                if ($alert_stmt) {
                    $alert_stmt->bind_param("iis", $volunteer_id, $distribution_id, $alert_message);
                    $alert_stmt->execute();
                    $alert_stmt->close();
                }
            }
            
            $db->commit();
            
            // Refresh page to show updated status
            header("Location: volunteer_distribution.php?success=confirmed&distribution_id=" . $distribution_id);
            exit;
            
        } catch (Exception $e) {
            if (isset($db) && is_object($db) && method_exists($db, 'rollback')) {
                $db->rollback();
            }
            $error = "Error confirming assignment: " . $e->getMessage();
        }
    }
    // Handle assignment cancellation - COMPLETE CLEANUP VERSION
    elseif ($action === 'cancel_assignment') {
        try {
            $db->begin_transaction();
            
            $distribution_id = $_POST['distribution_id'] ?? 0;
            $volunteer_id = $_SESSION['volunteer_id'];
            $reason = $_POST['reason'] ?? '';
            
            if (!$distribution_id || !$volunteer_id) {
                throw new Exception("Missing distribution ID or volunteer ID");
            }
            
            // 1. Remove from distribution_volunteer
            $delete_query = "DELETE FROM distribution_volunteer WHERE distribution_id = ? AND volunteer_id = ?";
            $delete_stmt = $db->prepare($delete_query);
            if ($delete_stmt) {
                $delete_stmt->bind_param("ii", $distribution_id, $volunteer_id);
                $delete_stmt->execute();
                $delete_stmt->close();
            }
            
            // 2. Update distribution_items if table exists - FIXED: Removed updated_at reference
            $table_check = $db->query("SHOW TABLES LIKE 'distribution_items'");
            if ($table_check && $table_check->num_rows > 0) {
                // Check if assigned_volunteer_id column exists
                $column_check = $db->query("SHOW COLUMNS FROM distribution_items LIKE 'assigned_volunteer_id'");
                if ($column_check && $column_check->num_rows > 0) {
                    $update_items = "UPDATE distribution_items SET assigned_volunteer_id = NULL WHERE distribution_id = ? AND assigned_volunteer_id = ?";
                    $update_stmt = $db->prepare($update_items);
                    if ($update_stmt) {
                        $update_stmt->bind_param("ii", $distribution_id, $volunteer_id);
                        $update_stmt->execute();
                        $update_stmt->close();
                    }
                }
            }
            
            // 3. CLEANUP: Remove volunteer from distribution_log for this distribution
            $log_cleanup_query = "DELETE FROM distribution_log WHERE distribution_id = ? AND volunteer_id = ?";
            $log_stmt = $db->prepare($log_cleanup_query);
            if ($log_stmt) {
                $log_stmt->bind_param("ii", $distribution_id, $volunteer_id);
                $log_stmt->execute();
                $log_stmt->close();
            }
            
            // 4. CLEANUP: Remove volunteer from distribution_tracking for this distribution
            $tracking_cleanup_query = "DELETE FROM distribution_tracking WHERE distribution_id = ? AND volunteer_id = ?";
            $tracking_stmt = $db->prepare($tracking_cleanup_query);
            if ($tracking_stmt) {
                $tracking_stmt->bind_param("ii", $distribution_id, $volunteer_id);
                $tracking_stmt->execute();
                $tracking_stmt->close();
            }
            
            // 5. CHECK REMAINING VOLUNTEERS AND UPDATE DISTRIBUTION STATUS
            $remaining_volunteers = 0;
            $check_volunteers_query = "SELECT COUNT(*) as remaining_count FROM distribution_volunteer WHERE distribution_id = ? AND status != 'Cancelled'";
            $check_stmt = $db->prepare($check_volunteers_query);
            if ($check_stmt) {
                $check_stmt->bind_param("i", $distribution_id);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();
                $check_row = $check_result->fetch_assoc();
                $check_stmt->close();
                $remaining_volunteers = $check_row['remaining_count'] ?? 0;
            }
            
            // If no volunteers remain, update distribution status
            if ($remaining_volunteers == 0) {
                // Check current status
                $current_status_query = "SELECT status FROM distribution WHERE distribution_id = ?";
                $status_stmt = $db->prepare($current_status_query);
                $current_status = '';
                if ($status_stmt) {
                    $status_stmt->bind_param("i", $distribution_id);
                    $status_stmt->execute();
                    $status_result = $status_stmt->get_result();
                    $status_row = $status_result->fetch_assoc();
                    $status_stmt->close();
                    $current_status = $status_row['status'] ?? '';
                }
                
                // Only update if it's currently in a volunteer-assigned state
                $volunteer_states = ['Assigned', 'In Transit', 'Active', 'In Progress'];
                if (in_array($current_status, $volunteer_states)) {
                    $update_dist_query = "UPDATE distribution SET status = 'Volunteer Needed' WHERE distribution_id = ?";
                    $dist_stmt = $db->prepare($update_dist_query);
                    if ($dist_stmt) {
                        $dist_stmt->bind_param("i", $distribution_id);
                        $dist_stmt->execute();
                        $dist_stmt->close();
                        
                        // Log the status change if table exists
                        $new_status = 'Volunteer Needed';
                        $table_check = $db->query("SHOW TABLES LIKE 'status_changes'");
                        if ($table_check && $table_check->num_rows > 0) {
                            $log_change_query = "INSERT INTO status_changes (distribution_id, old_status, new_status, changed_by, reason) VALUES (?, ?, ?, 'system', 'All volunteers declined/cancelled')";
                            $log_change_stmt = $db->prepare($log_change_query);
                            if ($log_change_stmt) {
                                $log_change_stmt->bind_param("iss", $distribution_id, $current_status, $new_status);
                                $log_change_stmt->execute();
                                $log_change_stmt->close();
                            }
                        }
                    }
                }
            }
            
            // 6. Log the cancellation for tracking
            $log_query = "INSERT INTO assignment_cancellations (distribution_id, volunteer_id, reason) VALUES (?, ?, ?)";
            $log_stmt = $db->prepare($log_query);
            if ($log_stmt) {
                $log_stmt->bind_param("iis", $distribution_id, $volunteer_id, $reason);
                $log_stmt->execute();
                $log_stmt->close();
            }
            
            $db->commit();
            
            // Show appropriate message
            if ($remaining_volunteers == 0) {
                $success_msg = "Assignment cancelled. You were the last volunteer, so distribution status has been updated to 'Volunteer Needed'.";
            } else {
                $success_msg = "Assignment cancelled successfully. " . $remaining_volunteers . " volunteer(s) remain assigned.";
            }
            
            header("Location: volunteer_distribution.php?success=cancelled&msg=" . urlencode($success_msg) . "&distribution_id=" . $distribution_id);
            exit;
            
        } catch (Exception $e) {
            if (isset($db) && is_object($db) && method_exists($db, 'rollback')) {
                $db->rollback();
            }
            $error = "Error cancelling assignment: " . $e->getMessage();
        }
    }
}

// Check for success messages
if (isset($_GET['success'])) {
    if ($_GET['success'] == 'confirmed') {
        $distribution_id = $_GET['distribution_id'] ?? 0;
        $success = "Assignment confirmed successfully! You can now start the distribution.";
        if ($distribution_id) {
            $success .= " (Distribution #" . $distribution_id . ")";
        }
    }
    elseif ($_GET['success'] == 'cancelled') {
        $success = isset($_GET['msg']) ? htmlspecialchars($_GET['msg']) : "Assignment cancelled successfully. You have been removed from this assignment.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Volunteer Dashboard - JKM Melaka</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', system-ui, sans-serif;
        }
        
        body {
            background-color: #f5f7fa;
            color: #333;
            line-height: 1.6;
        }
        
        .dashboard-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #e1e5eb;
        }
        
        .welcome-section h1 {
            color: #2c3e50;
            font-size: 28px;
            margin-bottom: 5px;
        }
        
        .welcome-section p {
            color: #7f8c8d;
            font-size: 16px;
        }
        
        .date-time-section {
            text-align: right;
        }
        
        .date-time-section .date {
            font-size: 18px;
            font-weight: 600;
            color: #2c3e50;
        }
        
        .date-time-section .time {
            font-size: 24px;
            font-weight: 700;
            color: #3498db;
            margin-top: 5px;
        }
        
        .stats-section {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            padding: 25px;
            text-align: center;
            transition: transform 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-card.active {
            border-top: 5px solid #3498db;
        }
        
        .stat-card.families {
            border-top: 5px solid #2ecc71;
        }
        
        .stat-card.items {
            border-top: 5px solid #9b59b6;
        }
        
        .stat-card.hours {
            border-top: 5px solid #f39c12;
        }
        
        .stat-number {
            font-size: 42px;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .stat-card.active .stat-number {
            color: #3498db;
        }
        
        .stat-card.families .stat-number {
            color: #2ecc71;
        }
        
        .stat-card.items .stat-number {
            color: #9b59b6;
        }
        
        .stat-card.hours .stat-number {
            color: #f39c12;
        }
        
        .stat-label {
            color: #7f8c8d;
            font-size: 16px;
            font-weight: 500;
        }
        
        .profile-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            padding: 25px;
            margin-bottom: 20px;
        }
        
        .profile-header {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .profile-icon {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            color: white;
            font-size: 30px;
        }
        
        .profile-info h2 {
            color: #2c3e50;
            font-size: 22px;
            margin-bottom: 5px;
        }
        
        .profile-info p {
            color: #7f8c8d;
            font-size: 15px;
        }
        
        .volunteer-id {
            display: inline-block;
            background-color: #f0f7ff;
            color: #3498db;
            padding: 6px 12px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 14px;
            margin-bottom: 15px;
        }
        
        .contact-info {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #eee;
        }
        
        .contact-item {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .contact-item i {
            color: #3498db;
            width: 20px;
            margin-right: 10px;
        }
        
        .quick-actions {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            padding: 25px;
        }
        
        .quick-actions h3 {
            color: #2c3e50;
            font-size: 18px;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .action-btn {
            display: flex;
            align-items: center;
            width: 100%;
            padding: 12px 15px;
            margin-bottom: 10px;
            background-color: #f8fafc;
            border: 1px solid #e1e5eb;
            border-radius: 8px;
            color: #2c3e50;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .action-btn i {
            margin-right: 10px;
            color: #3498db;
        }
        
        .action-btn:hover {
            background-color: #3498db;
            color: white;
            border-color: #3498db;
        }
        
        .action-btn:hover i {
            color: white;
        }
        
        .assignments-section {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            padding: 30px;
            margin-top: 30px;
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }
        
        .section-header h2 {
            color: #2c3e50;
            font-size: 24px;
        }
        
        .section-header .badge {
            background: #e1f5fe;
            color: #0288d1;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
        }
        
        .assignments-list {
            min-height: 200px;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #95a5a6;
        }
        
        .empty-state i {
            font-size: 60px;
            margin-bottom: 15px;
            color: #ecf0f1;
        }
        
        .empty-state h3 {
            font-size: 22px;
            margin-bottom: 10px;
            color: #7f8c8d;
        }
        
        .assignment-card {
            background-color: #f8fafc;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            border-left: 5px solid #3498db;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .assignment-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.08);
        }
        
        .assignment-card.active {
            border-left-color: #3498db;
        }
        
        .assignment-card.completed {
            border-left-color: #2ecc71;
        }
        
        .assignment-card.pending {
            border-left-color: #f39c12;
        }
        
        .assignment-card.in-progress {
            border-left-color: #e67e22;
        }
        
        .assignment-card.arrived {
            border-left-color: #27ae60;
        }
        
        .assignment-card.delayed {
            border-left-color: #e74c3c;
        }
        
        .assignment-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }
        
        .assignment-title {
            font-size: 20px;
            color: #2c3e50;
            font-weight: 600;
        }
        
        .assignment-status {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
        }
        
        .status-assigned {
            background-color: #e1f5fe;
            color: #0288d1;
        }
        
        .status-active {
            background-color: #e8f5e9;
            color: #2e7d32;
        }
        
        .status-pending {
            background-color: #fff3e0;
            color: #ef6c00;
        }
        
        .status-completed {
            background-color: #e8f5e9;
            color: #2e7d32;
        }
        
        .status-in_progress {
            background-color: #ffe0b2;
            color: #e65100;
        }
        
        .status-arrived {
            background-color: #c8e6c9;
            color: #1b5e20;
        }
        
        .status-delayed {
            background-color: #ffcdd2;
            color: #b71c1c;
        }
        
        /* TRACKING STATUS BADGES */
        .status-departed {
            background-color: #e1f5fe;
            color: #0288d1;
        }
        
        .status-in_transit {
            background-color: #fff3e0;
            color: #ef6c00;
        }
        
        .status-arrived {
            background-color: #e8f5e9;
            color: #2e7d32;
        }
        
        .status-delayed {
            background-color: #ffebee;
            color: #c62828;
        }
        
        .assignment-details {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .detail-item {
            display: flex;
            align-items: center;
        }
        
        .detail-item i {
            color: #3498db;
            width: 20px;
            margin-right: 10px;
        }
        
        .detail-label {
            font-weight: 600;
            color: #7f8c8d;
            margin-right: 5px;
        }
        
        .assignment-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .action-button {
            padding: 8px 16px;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            border: none;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .start-btn {
            background-color: #9b59b6;
            color: white;
        }
        
        .start-btn:hover {
            background-color: #8e44ad;
        }
        
        .confirm-btn {
            background-color: #2ecc71;
            color: white;
        }
        
        .confirm-btn:hover {
            background-color: #27ae60;
        }
        
        .cancel-btn {
            background-color: #e74c3c;
            color: white;
        }
        
        .cancel-btn:hover {
            background-color: #c0392b;
        }
        
        .details-btn {
            background-color: #3498db;
            color: white;
        }
        
        .details-btn:hover {
            background-color: #2980b9;
        }
        
        .view-btn {
            background-color: #95a5a6;
            color: white;
        }
        
        .view-btn:hover {
            background-color: #7f8c8d;
        }
        
        .tracking-btn {
            background-color: #e67e22;
            color: white;
        }
        
        .tracking-btn:hover {
            background-color: #d35400;
        }
        
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border-left: 5px solid #2ecc71;
        }
        
        .alert-error {
            background-color: #fde8e8;
            color: #c53030;
            border-left: 5px solid #e74c3c;
        }
        
        .api-status {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            margin-left: 10px;
            vertical-align: middle;
        }
        
        .api-status.connected {
            background-color: #d4edda;
            color: #155724;
        }
        
        .api-status.disconnected {
            background-color: #fde8e8;
            color: #c53030;
        }
        
        .role-badge {
            display: inline-block;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
            margin-top: 5px;
        }
        
        .skill-badge {
            display: inline-block;
            background: linear-gradient(135deg, #2ecc71 0%, #27ae60 100%);
            color: white;
            padding: 3px 10px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: 600;
            margin-top: 5px;
            margin-left: 5px;
        }
        
        .logout-btn {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #e74c3c;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 5px;
            z-index: 1000;
        }
        
        .logout-btn:hover {
            background: #c0392b;
        }
        
        .status-indicator {
            margin-bottom: 15px;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 6px;
            border-left: 4px solid #3498db;
        }
        
        .live-status {
            font-size: 12px;
            color: #3498db;
            display: flex;
            align-items: center;
            gap: 5px;
            margin-top: 5px;
        }
        
        .live-status i {
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }
        
        /* NEW: NOTIFICATION STYLES */
        .notification-badge {
            position: fixed;
            top: 80px;
            right: 20px;
            background: #3498db;
            color: white;
            padding: 15px 20px;
            border-radius: 8px;
            z-index: 1000;
            box-shadow: 0 4px 12px rgba(52, 152, 219, 0.3);
            animation: slideIn 0.5s ease;
            max-width: 400px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .notification-badge .close-btn {
            background: #e74c3c;
            color: white;
            border: none;
            border-radius: 3px;
            padding: 2px 8px;
            font-size: 12px;
            cursor: pointer;
            margin-left: 10px;
        }
        
        .notification-content {
            display: flex;
            align-items: center;
        }
        
        .notification-icon {
            margin-right: 10px;
            font-size: 20px;
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
        
        /* NEW: BROADCAST CHANNEL STYLES */
        .broadcast-message {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: #2ecc71;
            color: white;
            padding: 15px 20px;
            border-radius: 8px;
            z-index: 1000;
            box-shadow: 0 4px 12px rgba(46, 204, 113, 0.3);
            animation: slideInUp 0.5s ease;
            max-width: 400px;
            display: none;
        }
        
        @keyframes slideInUp {
            from {
                transform: translateY(100%);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }
        
        .notification-counter {
            position: absolute;
            top: -8px;
            right: -8px;
            background: #e74c3c;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: bold;
        }
        
        .notification-bell {
            position: fixed;
            top: 20px;
            left: 20px;
            background: #3498db;
            color: white;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            z-index: 1000;
            box-shadow: 0 4px 12px rgba(52, 152, 219, 0.3);
        }
        
        .notification-panel {
            position: fixed;
            top: 80px;
            left: 20px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
            z-index: 1000;
            width: 350px;
            max-height: 400px;
            overflow-y: auto;
            display: none;
        }
        
        .notification-panel-header {
            padding: 15px;
            background: #3498db;
            color: white;
            border-radius: 8px 8px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .notification-item {
            padding: 15px;
            border-bottom: 1px solid #eee;
            cursor: pointer;
            transition: background 0.3s;
        }
        
        .notification-item:hover {
            background: #f8f9fa;
        }
        
        .notification-item.unread {
            background: #e8f4fc;
        }
        
        .notification-time {
            font-size: 12px;
            color: #95a5a6;
            margin-top: 5px;
        }
        
        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 2000;
            align-items: center;
            justify-content: center;
        }
        
        .modal-content {
            background: white;
            border-radius: 12px;
            padding: 30px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        }
        
        .modal-header {
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }
        
        .modal-header h3 {
            color: #2c3e50;
            font-size: 20px;
            margin: 0;
        }
        
        .modal-body {
            margin-bottom: 25px;
        }
        
        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
        
        .modal-cancel-btn {
            background-color: #e74c3c;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
        }
        
        .modal-cancel-btn:hover {
            background-color: #c0392b;
        }
        
        .modal-close-btn {
            background-color: #95a5a6;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
        }
        
        .modal-close-btn:hover {
            background-color: #7f8c8d;
        }
        
        .reason-select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 6px;
            margin-bottom: 15px;
            font-size: 14px;
        }
        
        .reason-textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 6px;
            margin-bottom: 15px;
            font-size: 14px;
            min-height: 100px;
            resize: vertical;
        }
        
        /* Add new status badges for volunteer statuses */
        .status-in_transit {
            background-color: #fff3e0;
            color: #ef6c00;
        }
        
        .status-in_progress {
            background-color: #ffe0b2;
            color: #e65100;
        }
        
        .status-departed {
            background-color: #e1f5fe;
            color: #0288d1;
        }
        
        .status-arrived {
            background-color: #c8e6c9;
            color: #1b5e20;
        }
        
        .status-delayed {
            background-color: #ffcdd2;
            color: #b71c1c;
        }
        
        /* Side-by-side assignments layout */
        .assignments-grid-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .assignments-grid-container .assignments-section {
            margin: 0;
        }
        
        /* Compact assignment cards */
        .compact-assignment {
            padding: 15px;
            margin-bottom: 15px;
        }
        
        .compact-assignment .assignment-header {
            margin-bottom: 10px;
        }
        
        .compact-assignment .assignment-title {
            font-size: 16px;
            margin-bottom: 5px;
        }
        
        .compact-assignment .assignment-details {
            grid-template-columns: 1fr;
            gap: 8px;
            margin-bottom: 10px;
        }
        
        .compact-assignment .detail-item {
            font-size: 13px;
        }
        
        .compact-assignment .detail-item i {
            font-size: 12px;
            width: 16px;
        }
        
        .compact-assignment .assignment-actions {
            gap: 5px;
        }
        
        .compact-assignment .action-button {
            padding: 6px 12px;
            font-size: 12px;
        }
        
        @media (max-width: 992px) {
            .stats-section {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .assignments-grid-container {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 768px) {
            .dashboard-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .date-time-section {
                text-align: left;
                margin-top: 15px;
            }
            
            .assignment-details {
                grid-template-columns: 1fr;
            }
            
            .notification-badge {
                left: 20px;
                right: 20px;
                max-width: none;
            }
        }
        
        @media (max-width: 576px) {
            .stats-section {
                grid-template-columns: 1fr;
            }
            
            .assignment-actions {
                flex-direction: column;
            }
            
            .action-button {
                width: 100%;
                justify-content: center;
            }
            
            .modal-content {
                width: 95%;
                padding: 20px;
            }
            
            .modal-footer {
                flex-direction: column;
            }
        }
        
        /* Add new status badge for Volunteer Needed */
        .badge-volunteer-needed {
            background-color: #ff7675;
            color: white;
        }
        
        /* Inactive volunteer warning */
        .inactive-warning {
            background: #fff3cd;
            border-left: 5px solid #ffc107;
            padding: 20px;
            margin-bottom: 20px;
            border-radius: 8px;
        }
        
        .inactive-warning i {
            color: #ffc107;
            font-size: 24px;
            margin-right: 10px;
        }
    </style>
</head>
<body>
    <!-- Logout Button -->
    <form method="POST" style="display: inline;">
        <input type="hidden" name="action" value="logout">
        <button type="submit" class="logout-btn">
            <i class="fas fa-sign-out-alt"></i> Logout
        </button>
    </form>
    
    <!-- Notification Bell -->
    <?php if ($is_volunteer_active): ?>
    <div class="notification-bell" id="notificationBell" onclick="toggleNotificationPanel()">
        <i class="fas fa-bell"></i>
        <?php if ($show_notification_badge): ?>
            <span class="notification-counter" id="notificationCounter"><?php echo count($new_assignment_notifications); ?></span>
        <?php endif; ?>
    </div>
    
    <!-- Notification Panel -->
    <div class="notification-panel" id="notificationPanel">
        <div class="notification-panel-header">
            <h4 style="margin: 0; font-size: 16px;"><i class="fas fa-bell"></i> Notifications</h4>
            <button onclick="clearAllNotifications()" style="background: none; border: none; color: white; cursor: pointer;">
                <i class="fas fa-trash"></i> Clear All
            </button>
        </div>
        <div id="notificationList">
            <?php if (!empty($new_assignment_notifications)): ?>
                <?php foreach ($new_assignment_notifications as $notification): ?>
                <div class="notification-item unread" onclick="viewNotification(<?php echo $notification['distribution_id'] ?? 0; ?>)">
                    <div style="font-weight: bold; color: #2c3e50;">
                        <i class="fas fa-user-check"></i> New Assignment
                    </div>
                    <div style="font-size: 14px; margin-top: 5px;">
                        <?php echo htmlspecialchars($notification['message'] ?? 'New distribution assignment'); ?>
                    </div>
                    <div class="notification-time">
                        <?php echo date('M j, g:i A', strtotime($notification['created_at'])); ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="notification-item" style="text-align: center; color: #95a5a6;">
                    <i class="fas fa-bell-slash" style="font-size: 24px; margin-bottom: 10px;"></i>
                    <div>No new notifications</div>
                </div>
            <?php endif; ?>
            <div class="notification-item" style="text-align: center; color: #95a5a6; font-size: 12px; padding: 10px;">
                Last checked: <?php echo date('h:i A'); ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Cancel Assignment Modal -->
    <?php if ($is_volunteer_active): ?>
    <div class="modal" id="cancelModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-times-circle"></i> Cancel Assignment</h3>
            </div>
            <div class="modal-body">
                <p id="modalMessage">Are you sure you want to cancel this assignment?</p>
                <div id="reasonSection">
                    <label for="cancelReason">Reason for cancellation:</label>
                    <select id="cancelReason" class="reason-select" onchange="toggleCustomReason()">
                        <option value="">Select a reason</option>
                        <option value="unavailable">I'm unavailable</option>
                        <option value="schedule_conflict">Schedule conflict</option>
                        <option value="too_far">Location is too far</option>
                        <option value="skills_mismatch">Skills don't match requirements</option>
                        <option value="personal_reasons">Personal reasons</option>
                        <option value="other">Other</option>
                    </select>
                    <div id="customReasonSection" style="display: none;">
                        <textarea id="customReason" class="reason-textarea" placeholder="Please specify your reason..."></textarea>
                    </div>
                </div>
                <p style="color: #e74c3c; font-size: 14px; margin-top: 10px;">
                    <i class="fas fa-exclamation-triangle"></i> Note: You will be completely removed from this assignment and your assigned needs will be freed up for other volunteers.
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="modal-close-btn" onclick="closeCancelModal()">
                    <i class="fas fa-times"></i> Close
                </button>
                <button type="button" class="modal-cancel-btn" onclick="submitCancellation()">
                    <i class="fas fa-check"></i> Yes, Cancel Assignment
                </button>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Broadcast Message Area -->
    <div class="broadcast-message" id="broadcastMessage"></div>

    <div class="dashboard-container">
        <!-- Header Section -->
        <div class="dashboard-header">
            <div class="welcome-section">
                <h1>Volunteer Dashboard</h1>
                <p>JKM Melaka - Disaster Relief Distribution System
                    <?php if (isset($apiResult['success']) && $apiResult['success']): ?>
                        <span class="api-status connected">API Connected</span>
                    <?php else: ?>
                        <span class="api-status disconnected">API Disconnected</span>
                    <?php endif; ?>
                </p>
            </div>
            
            <div class="date-time-section">
                <div class="date" id="current-date">Loading...</div>
                <div class="time" id="current-time">Loading...</div>
            </div>
        </div>
        
        <!-- Error/Success Messages -->
        <?php if (!empty($error)): ?>
            <div class="alert alert-error">
                <strong>Error:</strong> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($success)): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>
        
        <!-- API Debug Info -->
        <?php if (isset($apiResult) && !$apiResult['success']): ?>
            <div class="alert alert-error">
                <strong>API Error:</strong> <?php echo isset($apiResult['error']) ? htmlspecialchars($apiResult['error']) : 'Unknown error'; ?>
                <br><small>API URL: <?php echo $API_URL; ?></small>
            </div>
        <?php endif; ?>
        
        <!-- Inactive Volunteer Warning -->
        <?php if (!$is_volunteer_active && $volunteer_info): ?>
        <div class="inactive-warning">
            <div style="display: flex; align-items: center;">
                <i class="fas fa-exclamation-triangle"></i>
                <div>
                    <h3 style="margin: 0; color: #856404;">Account Status: <?php echo $volunteer_info['status']; ?></h3>
                    <p style="margin: 5px 0 0 0; color: #856404;">
                        Your volunteer account is currently <?php echo strtolower($volunteer_info['status']); ?>. 
                        You cannot access distribution assignments. Please contact your coordinator to reactivate your account.
                    </p>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if ($is_volunteer_active): ?>
        <!-- Stats Cards -->
        <div class="stats-section">
            <div class="stat-card active">
                <div class="stat-number"><?php echo $stats['active_assignments']; ?></div>
                <div class="stat-label">Active Assignments</div>
            </div>
            
            <div class="stat-card families">
                <div class="stat-number"><?php echo $stats['total_families_helped']; ?></div>
                <div class="stat-label">Families Helped</div>
            </div>
            
            <div class="stat-card items">
                <div class="stat-number"><?php echo $stats['total_items_distributed']; ?></div>
                <div class="stat-label">Items Distributed</div>
            </div>
            
            <div class="stat-card hours">
                <div class="stat-number"><?php echo $stats['total_hours_volunteered']; ?></div>
                <div class="stat-label">Hours Volunteered</div>
            </div>
        </div>
        
        <!-- Profile & Assignments Layout -->
        <div style="display: grid; grid-template-columns: 300px 1fr; gap: 30px;">
            <!-- Left Column: Profile & Quick Actions -->
            <div>
                <div class="profile-card">
                    <div class="profile-header">
                        <div class="profile-icon">
                            <i class="fas fa-user-check"></i>
                        </div>
                        <div class="profile-info">
                            <?php if ($volunteer_info): ?>
                                <h2><?php echo htmlspecialchars($volunteer_info['name']); ?></h2>
                                <div class="role-badge">Volunteer</div>
                                <?php if (!empty($volunteer_info['skill_category'])): ?>
                                    <div class="skill-badge"><?php echo htmlspecialchars($volunteer_info['skill_category']); ?></div>
                                <?php endif; ?>
                            <?php else: ?>
                                <h2>Volunteer</h2>
                                <div class="role-badge">Volunteer</div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="volunteer-id">ID: VOL<?php echo str_pad($volunteer_id, 4, '0', STR_PAD_LEFT); ?></div>
                    
                    <?php if ($volunteer_info): ?>
                    <div class="contact-info">
                        <?php if (!empty($volunteer_info['email'])): ?>
                        <div class="contact-item">
                            <i class="fas fa-envelope"></i>
                            <span><?php echo htmlspecialchars($volunteer_info['email']); ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($volunteer_info['phone'])): ?>
                        <div class="contact-item">
                            <i class="fas fa-phone"></i>
                            <span><?php echo htmlspecialchars($volunteer_info['phone']); ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($volunteer_info['ngo_affiliation'])): ?>
                        <div class="contact-item">
                            <i class="fas fa-hands-helping"></i>
                            <span><?php echo htmlspecialchars($volunteer_info['ngo_affiliation']); ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <div class="contact-item">
                            <i class="fas fa-user-circle"></i>
                            <span>Status: <strong style="color: 
                                <?php 
                                $status = strtolower($volunteer_info['status']);
                                if ($status == 'active') echo '#2ecc71';
                                elseif ($status == 'inactive') echo '#e74c3c';
                                elseif ($status == 'pending') echo '#f39c12';
                                else echo '#3498db';
                                ?>"><?php echo htmlspecialchars($volunteer_info['status']); ?></strong></span>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="quick-actions">
                    <h3>Quick Actions</h3>
                    <button class="action-btn" onclick="window.open('http://10.147.17.30:8000/volunteer_dashboard.php?volunteer_id=<?php echo $_SESSION['volunteer_id']; ?>', '_blank')">
                        <i class="fas fa-external-link-alt"></i> Back to Main Dashboard 
                    </button>
                    <button class="action-btn" onclick="window.open('http://10.147.17.30:8000/settings.php?volunteer_id=<?php echo $_SESSION['volunteer_id']; ?>', '_blank')">
                        <i class="fas fa-cog"></i> Settings & Profile
                    </button>
                </div>
            </div>
            
            <!-- Right Column: Assignments -->
            <div>
                <!-- Main Assignments Container with Side-by-Side Layout -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 30px;">
                    
                    <!-- Left: Active Assignments -->
                    <div class="assignments-section" style="margin: 0;">
                        <div class="section-header">
                            <h2>Active Distributions</h2>
                            <span class="badge"><?php echo count($upcoming_assignments); ?></span>
                        </div>
                        
                        <div class="assignments-list" style="min-height: 300px;">
                            <?php if (empty($upcoming_assignments)): ?>
                                <div class="empty-state">
                                    <i class="fas fa-clipboard-list"></i>
                                    <h3>No Active Assignments</h3>
                                    <p>You don't have any active distribution assignments.</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($upcoming_assignments as $assignment): 
                                    $status_class = strtolower(str_replace(' ', '_', $assignment['status']));
                                ?>
                                <div class="assignment-card <?php echo $status_class; ?>">
                                    <div class="assignment-header">
                                        <h3 class="assignment-title">
                                            <?php echo htmlspecialchars($assignment['Disaster_Name']); ?>
                                        </h3>
                                        <div style="display: flex; flex-direction: column; align-items: flex-end;">
                                            <span class="assignment-status status-<?php echo $status_class; ?>">
                                                <?php echo $assignment['status']; ?>
                                            </span>
                                            
                                            <?php if (!empty($assignment['latest_tracking_status'])): ?>
                                            <div style="margin-top: 5px; display: flex; align-items: center;">
                                                <span class="assignment-status status-<?php echo strtolower($assignment['latest_tracking_status']); ?>" 
                                                      style="font-size: 11px; padding: 2px 8px;">
                                                    <i class="fas fa-map-marker-alt" style="margin-right: 3px;"></i>
                                                    <?php echo ucfirst(str_replace('_', ' ', $assignment['latest_tracking_status'])); ?>
                                                </span>
                                                <?php if (!empty($assignment['last_update'])): ?>
                                                <span style="font-size: 10px; color: #7f8c8d; margin-left: 5px;">
                                                    <?php echo $assignment['last_update']; ?>
                                                </span>
                                                <?php endif; ?>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <div class="assignment-details" style="grid-template-columns: 1fr;">
                                        <div class="detail-item">
                                            <i class="fas fa-hashtag"></i>
                                            <span class="detail-label">ID:</span>
                                            <span>DIST<?php echo str_pad($assignment['distribution_id'], 6, '0', STR_PAD_LEFT); ?></span>
                                        </div>
                                        <div class="detail-item">
                                            <i class="fas fa-calendar"></i>
                                            <span class="detail-label">Date:</span>
                                            <span><?php echo date('d/m/Y', strtotime($assignment['date'])); ?></span>
                                        </div>
                                        <div class="detail-item">
                                            <i class="fas fa-map-marker-alt"></i>
                                            <span class="detail-label">Location:</span>
                                            <span>
                                                <?php if (!empty($assignment['latest_location'])): ?>
                                                    <?php echo htmlspecialchars($assignment['latest_location']); ?>
                                                <?php else: ?>
                                                    <?php echo htmlspecialchars($assignment['distribution_location']); ?>
                                                <?php endif; ?>
                                            </span>
                                        </div>
                                        <div class="detail-item">
                                            <i class="fas fa-users"></i>
                                            <span class="detail-label">Families:</span>
                                            <span><?php echo $assignment['total_victims']; ?></span>
                                        </div>
                                        <div class="detail-item">
                                            <i class="fas fa-box"></i>
                                            <span class="detail-label">Items:</span>
                                            <span><?php echo $assignment['total_needs']; ?></span>
                                        </div>
                                    </div>
                                    
                                    <div class="assignment-actions">
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="distribution_id" value="<?php echo $assignment['distribution_id']; ?>">
                                            <input type="hidden" name="action" value="start_distribution">
                                            <button type="submit" class="action-button start-btn">
                                                <i class="fas fa-play-circle"></i> 
                                                <?php echo $assignment['button_text']; ?>
                                            </button>
                                        </form>
                                        
                                        <?php if (isset($assignment['status']) && $assignment['status'] === 'Assigned'): ?>
                                            <button type="button" class="action-button confirm-btn" 
                                                    onclick="if(confirm('Are you sure you want to confirm this assignment?')) { document.getElementById('confirm-form-<?php echo $assignment['distribution_id']; ?>').submit(); }">
                                                <i class="fas fa-check"></i> Confirm
                                            </button>
                                            <form id="confirm-form-<?php echo $assignment['distribution_id']; ?>" method="POST" style="display: none;">
                                                <input type="hidden" name="distribution_id" value="<?php echo $assignment['distribution_id']; ?>">
                                                <input type="hidden" name="action" value="confirm_assignment">
                                            </form>
                                            
                                            <button class="action-button cancel-btn" onclick="showCancelModal(<?php echo $assignment['distribution_id']; ?>, '<?php echo htmlspecialchars($assignment['Disaster_Name']); ?>')">
                                                <i class="fas fa-times"></i> Decline
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Right: Completed Distributions -->
                    <div class="assignments-section" style="margin: 0;">
                        <div class="section-header">
                            <h2>Completed Distributions</h2>
                            <span class="badge"><?php echo count($past_assignments); ?></span>
                        </div>
                        
                        <div class="assignments-list" style="min-height: 300px;">
                            <?php if (empty($past_assignments)): ?>
                                <div class="empty-state">
                                    <i class="fas fa-check-circle"></i>
                                    <h3>No Completed Distributions</h3>
                                    <p>Your completed distributions will appear here.</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($past_assignments as $assignment): ?>
                                <div class="assignment-card completed">
                                    <div class="assignment-header">
                                        <h3 class="assignment-title">
                                            <?php echo htmlspecialchars($assignment['Disaster_Name']); ?>
                                        </h3>
                                        <span class="assignment-status status-completed">Completed</span>
                                    </div>
                                    
                                    <div class="assignment-details" style="grid-template-columns: 1fr;">
                                        <div class="detail-item">
                                            <i class="fas fa-hashtag"></i>
                                            <span class="detail-label">ID:</span>
                                            <span>DIST<?php echo str_pad($assignment['distribution_id'], 6, '0', STR_PAD_LEFT); ?></span>
                                        </div>
                                        <div class="detail-item">
                                            <i class="fas fa-calendar"></i>
                                            <span class="detail-label">Date:</span>
                                            <span><?php echo date('d/m/Y', strtotime($assignment['date'])); ?></span>
                                        </div>
                                        <div class="detail-item">
                                            <i class="fas fa-users"></i>
                                            <span class="detail-label">Helped:</span>
                                            <span><?php echo $assignment['victims_helped'] ?? 0; ?> families</span>
                                        </div>
                                        <div class="detail-item">
                                            <i class="fas fa-box"></i>
                                            <span class="detail-label">Distributed:</span>
                                            <span><?php echo $assignment['items_distributed'] ?? 0; ?> items</span>
                                        </div>
                                        <div class="detail-item">
                                            <i class="fas fa-map-marker-alt"></i>
                                            <span class="detail-label">Location:</span>
                                            <span><?php echo htmlspecialchars($assignment['disaster_location']); ?></span>
                                        </div>
                                    </div>
                                    
                                    <div class="assignment-actions">
                                        <button class="action-button view-btn" onclick="viewDistributionReport(<?php echo $assignment['distribution_id']; ?>)">
                                            <i class="fas fa-chart-bar"></i> Report
                                        </button>
                                        <button class="action-button details-btn" onclick="viewAssignmentDetails(<?php echo $assignment['distribution_id']; ?>)">
                                            <i class="fas fa-info-circle"></i> Details
                                        </button>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Tracking Section (if there are active assignments) -->
                <?php if (!empty($upcoming_assignments)): ?>
                <div class="assignments-section" style="margin-top: 0;">
                    <div class="section-header">
                        <h2>Real-time Tracking</h2>
                        <span class="badge">Live Updates</span>
                    </div>
                    
                    <div class="assignments-list">
                        <?php foreach ($upcoming_assignments as $assignment): 
                            if (!empty($assignment['latest_tracking_status'])): ?>
                            <div class="assignment-card" style="border-left-color: 
                                <?php 
                                if ($assignment['latest_tracking_status'] == 'departed') echo '#3498db';
                                elseif ($assignment['latest_tracking_status'] == 'in_transit') echo '#e67e22';
                                elseif ($assignment['latest_tracking_status'] == 'arrived') echo '#27ae60';
                                elseif ($assignment['latest_tracking_status'] == 'delayed') echo '#e74c3c';
                                else echo '#7f8c8d';
                                ?>;">
                                <div class="assignment-header">
                                    <h3 class="assignment-title">
                                        <?php echo htmlspecialchars($assignment['Disaster_Name']); ?>
                                        <span style="font-size: 14px; color: #7f8c8d; font-weight: normal;">
                                            (ID: DIST<?php echo str_pad($assignment['distribution_id'], 6, '0', STR_PAD_LEFT); ?>)
                                        </span>
                                    </h3>
                                    <div>
                                        <span class="assignment-status status-<?php echo strtolower($assignment['latest_tracking_status']); ?>">
                                            <i class="fas fa-map-marker-alt"></i>
                                            <?php echo ucfirst(str_replace('_', ' ', $assignment['latest_tracking_status'])); ?>
                                        </span>
                                        <?php if (!empty($assignment['last_update'])): ?>
                                        <div style="font-size: 11px; color: #95a5a6; margin-top: 5px;">
                                            <i class="fas fa-clock"></i> <?php echo $assignment['last_update']; ?>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="status-indicator" style="margin: 0; padding: 10px; background: #f8f9fa;">
                                    <div style="display: flex; align-items: center; justify-content: space-between;">
                                        <div>
                                            <strong>Latest Location:</strong> 
                                            <span style="color: #2c3e50; font-weight: 600;">
                                                <?php echo htmlspecialchars($assignment['latest_location'] ?? $assignment['distribution_location']); ?>
                                            </span>
                                        </div>
                                        <div class="live-status">
                                            <i class="fas fa-sync-alt"></i> Real-time tracking
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="assignment-actions">
                                    <button class="action-button tracking-btn" onclick="viewTrackingHistory(<?php echo $assignment['distribution_id']; ?>)">
                                        <i class="fas fa-map-marked-alt"></i> View Tracking History
                                    </button>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="distribution_id" value="<?php echo $assignment['distribution_id']; ?>">
                                        <input type="hidden" name="action" value="start_distribution">
                                        <button type="submit" class="action-button start-btn">
                                            <i class="fas fa-play-circle"></i> Continue Distribution
                                        </button>
                                    </form>
                                </div>
                            </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script>
        // Update current date and time
        function updateDateTime() {
            const now = new Date();
            
            const options = { 
                weekday: 'long', 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric' 
            };
            const dateString = now.toLocaleDateString('en-US', options);
            
            let hours = now.getHours();
            let minutes = now.getMinutes();
            let seconds = now.getSeconds();
            const ampm = hours >= 12 ? 'PM' : 'AM';
            
            hours = hours % 12;
            hours = hours ? hours : 12;
            minutes = minutes < 10 ? '0' + minutes : minutes;
            seconds = seconds < 10 ? '0' + seconds : seconds;
            
            const timeString = `${hours}:${minutes}:${seconds} ${ampm}`;
            
            document.getElementById('current-date').textContent = dateString;
            document.getElementById('current-time').textContent = timeString;
        }
        
        updateDateTime();
        setInterval(updateDateTime, 1000);
        
        function viewAssignmentDetails(distributionId) {
            window.location.href = 'view_distribution.php?id=' + distributionId;
        }
        
        function viewDistributionReport(distributionId) {
            window.location.href = 'distribution_report.php?id=' + distributionId;
        }
        
        function viewTrackingHistory(distributionId) {
            window.location.href = 'execute_distribution.php?distribution_id=' + distributionId;
        }
        
        // NOTIFICATION SYSTEM
        let notificationCount = <?php echo count($new_assignment_notifications); ?>;
        
        function toggleNotificationPanel() {
            const panel = document.getElementById('notificationPanel');
            panel.style.display = panel.style.display === 'block' ? 'none' : 'block';
            
            // Reset counter when panel is opened
            if (panel.style.display === 'block') {
                notificationCount = 0;
                updateNotificationCounter();
            }
        }
        
        function updateNotificationCounter() {
            const counter = document.getElementById('notificationCounter');
            if (counter) {
                counter.textContent = notificationCount;
                counter.style.display = notificationCount > 0 ? 'flex' : 'none';
            }
        }
        
        function clearAllNotifications() {
            // Send AJAX request to clear notifications
            fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=dismiss_notification&notification_id=all'
            });
            
            // Clear UI
            document.getElementById('notificationList').innerHTML = `
                <div class="notification-item" style="text-align: center; color: #95a5a6;">
                    <i class="fas fa-bell-slash" style="font-size: 24px; margin-bottom: 10px;"></i>
                    <div>No new notifications</div>
                </div>
                <div class="notification-item" style="text-align: center; color: #95a5a6; font-size: 12px; padding: 10px;">
                    Last checked: ${new Date().toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}
                </div>
            `;
            
            notificationCount = 0;
            updateNotificationCounter();
        }
        
        function viewNotification(distributionId) {
            if (distributionId && distributionId > 0) {
                window.location.href = 'execute_distribution.php?distribution_id=' + distributionId;
            }
        }
        
        // Check for new assignments from localStorage
        function checkForNewAssignments() {
            const notification = localStorage.getItem('volunteer_assignment_notification');
            if (notification) {
                const data = JSON.parse(notification);
                const now = new Date();
                const notificationTime = new Date(data.timestamp);
                const hoursDiff = (now - notificationTime) / (1000 * 60 * 60);
                
                // Only show notifications from last 24 hours
                if (hoursDiff < 24) {
                    showNewAssignmentNotification(data);
                    localStorage.removeItem('volunteer_assignment_notification');
                }
            }
            
            // Also check via AJAX
            fetch('check_new_assignments.php?volunteer_id=<?php echo $volunteer_id; ?>')
                .then(response => response.json())
                .then(data => {
                    if (data.new_assignments > 0) {
                        showBroadcastMessage(`You have ${data.new_assignments} new assignment(s)!`);
                        notificationCount += data.new_assignments;
                        updateNotificationCounter();
                    }
                });
        }
        
        function showNewAssignmentNotification(data) {
            const notification = document.createElement('div');
            notification.className = 'notification-badge';
            notification.innerHTML = `
                <div class="notification-content">
                    <div class="notification-icon">
                        <i class="fas fa-user-check"></i>
                    </div>
                    <div>
                        <strong>New Assignment!</strong>
                        <div style="font-size: 12px; margin-top: 5px;">
                            ${data.volunteer_count} volunteer(s) assigned to Distribution ${data.distribution_id}
                            ${data.disaster_name ? `<br>Disaster: ${data.disaster_name}` : ''}
                        </div>
                    </div>
                </div>
                <button class="close-btn" onclick="this.parentElement.remove()">
                    <i class="fas fa-times"></i>
                </button>
            `;
            document.body.appendChild(notification);
            
            // Auto-remove after 10 seconds
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.style.animation = 'slideOut 0.5s ease';
                    setTimeout(() => notification.parentNode.removeChild(notification), 500);
                }
            }, 10000);
            
            // Play notification sound
            playNotificationSound();
            
            // Update counter
            notificationCount++;
            updateNotificationCounter();
        }
        
        function showBroadcastMessage(message) {
            const broadcast = document.getElementById('broadcastMessage');
            broadcast.innerHTML = `<i class="fas fa-broadcast-tower"></i> ${message}`;
            broadcast.style.display = 'block';
            
            // Auto-hide after 5 seconds
            setTimeout(() => {
                broadcast.style.display = 'none';
            }, 5000);
        }
        
        function playNotificationSound() {
            try {
                const audioContext = new (window.AudioContext || window.webkitAudioContext)();
                const oscillator = audioContext.createOscillator();
                const gainNode = audioContext.createGain();
                
                oscillator.connect(gainNode);
                gainNode.connect(audioContext.destination);
                
                oscillator.frequency.value = 800;
                oscillator.type = 'sine';
                
                gainNode.gain.setValueAtTime(0, audioContext.currentTime);
                gainNode.gain.linearRampToValueAtTime(0.3, audioContext.currentTime + 0.1);
                gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.5);
                
                oscillator.start(audioContext.currentTime);
                oscillator.stop(audioContext.currentTime + 0.5);
            } catch (e) {
                console.log('Audio not supported');
            }
        }
        
        // Request notification permission
        function requestNotificationPermission() {
            if ('Notification' in window && Notification.permission === 'default') {
                Notification.requestPermission();
            }
        }
        
        // Show desktop notification
        function showDesktopNotification(title, body) {
            if ('Notification' in window && Notification.permission === 'granted') {
                new Notification(title, {
                    body: body,
                    icon: 'https://cdn-icons-png.flaticon.com/512/3067/3067259.png'
                });
            }
        }
        
        // BROADCAST CHANNEL for real-time notifications
        if (typeof BroadcastChannel !== 'undefined') {
            const channel = new BroadcastChannel('volunteer_notifications');
            channel.onmessage = function(event) {
                if (event.data.type === 'new_assignment') {
                    // Show notification
                    showNewAssignmentNotification(event.data);
                    
                    // Show desktop notification
                    showDesktopNotification(
                        'New Distribution Assignment',
                        `You have been assigned to Distribution #${event.data.distribution_id}`
                    );
                }
            };
        }
        
        // CANCEL ASSIGNMENT FUNCTIONALITY
        let currentCancelDistributionId = null;
        let currentDisasterName = null;
        
        function showCancelModal(distributionId, disasterName) {
            currentCancelDistributionId = distributionId;
            currentDisasterName = disasterName;
            
            const modal = document.getElementById('cancelModal');
            const modalMessage = document.getElementById('modalMessage');
            
            modalMessage.innerHTML = `Are you sure you want to cancel your assignment for:<br><strong>${disasterName}</strong> (Distribution #${distributionId})?`;
            
            // Add warning about complete removal
            const warningDiv = document.createElement('div');
            warningDiv.innerHTML = `
                <div style="background: #fff3cd; padding: 10px; border-radius: 6px; margin-top: 10px; border-left: 4px solid #ffc107;">
                    <strong><i class="fas fa-exclamation-triangle"></i> Important:</strong>
                    <ul style="margin: 5px 0 0 20px; padding: 0; font-size: 13px;">
                        <li>You will be completely removed from this assignment</li>
                        <li>All assigned families/items will be freed up for other volunteers</li>
                        <li>This assignment will disappear from your dashboard</li>
                        <li>Coordinator will be notified to assign new volunteers</li>
                    </ul>
                </div>
            `;
            modalMessage.parentNode.insertBefore(warningDiv, modalMessage.nextSibling);
            
            // Reset form
            document.getElementById('cancelReason').value = '';
            document.getElementById('customReason').value = '';
            document.getElementById('customReasonSection').style.display = 'none';
            
            modal.style.display = 'flex';
        }
        
        function closeCancelModal() {
            const modal = document.getElementById('cancelModal');
            modal.style.display = 'none';
            currentCancelDistributionId = null;
            currentDisasterName = null;
        }
        
        function toggleCustomReason() {
            const reason = document.getElementById('cancelReason').value;
            const customReasonSection = document.getElementById('customReasonSection');
            
            if (reason === 'other') {
                customReasonSection.style.display = 'block';
            } else {
                customReasonSection.style.display = 'none';
            }
        }
        
        function submitCancellation() {
            if (!currentCancelDistributionId) return;
            
            const reason = document.getElementById('cancelReason').value;
            const customReason = document.getElementById('customReason').value;
            const finalReason = reason === 'other' ? customReason : reason;
            
            if (!reason) {
                alert('Please select a reason for cancellation.');
                return;
            }
            
            if (reason === 'other' && !customReason.trim()) {
                alert('Please specify your reason for cancellation.');
                return;
            }
            
            // Create form and submit
            const form = document.createElement('form');
            form.method = 'POST';
            form.style.display = 'none';
            
            const distributionIdInput = document.createElement('input');
            distributionIdInput.type = 'hidden';
            distributionIdInput.name = 'distribution_id';
            distributionIdInput.value = currentCancelDistributionId;
            
            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'cancel_assignment';
            
            const reasonInput = document.createElement('input');
            reasonInput.type = 'hidden';
            reasonInput.name = 'reason';
            reasonInput.value = finalReason;
            
            form.appendChild(distributionIdInput);
            form.appendChild(actionInput);
            form.appendChild(reasonInput);
            
            document.body.appendChild(form);
            form.submit();
        }
        
        // Auto-refresh every 30 seconds to get latest tracking updates
        let autoRefreshInterval = setInterval(() => {
            // Only refresh if there are active assignments
            if (document.querySelector('.assignment-card.active') || document.querySelector('.assignment-card.in-progress')) {
                console.log('Auto-refreshing for latest tracking updates...');
                checkForNewAssignments();
                
                // Partial refresh for tracking updates
                fetch('get_latest_tracking.php?volunteer_id=<?php echo $volunteer_id; ?>')
                    .then(response => response.json())
                    .then(data => {
                        if (data.updated) {
                            // Update tracking status in UI
                            const trackingElements = document.querySelectorAll('.live-status');
                            trackingElements.forEach(el => {
                                el.innerHTML = '<i class="fas fa-sync-alt fa-spin"></i> Updated just now';
                                setTimeout(() => {
                                    el.innerHTML = '<i class="fas fa-sync-alt"></i> Real-time tracking';
                                }, 3000);
                            });
                        }
                    });
            }
        }, 30000); // 30 seconds
        
        // Check for new assignments on page load
        document.addEventListener('DOMContentLoaded', function() {
            <?php if ($is_volunteer_active): ?>
            checkForNewAssignments();
            requestNotificationPermission();
            
            // Check if there's a success parameter in URL
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('success')) {
                // Show success message if needed
                if (urlParams.get('success') === 'confirmed') {
                    showBroadcastMessage('Assignment confirmed successfully!');
                } else if (urlParams.get('success') === 'cancelled') {
                    showBroadcastMessage('Assignment cancelled. You have been removed from this assignment.');
                }
            }
            
            // Listen for click outside notification panel to close it
            document.addEventListener('click', function(event) {
                const panel = document.getElementById('notificationPanel');
                const bell = document.getElementById('notificationBell');
                if (panel.style.display === 'block' && 
                    !panel.contains(event.target) && 
                    !bell.contains(event.target)) {
                    panel.style.display = 'none';
                }
            });
            
            // Listen for click outside modal to close it
            document.addEventListener('click', function(event) {
                const modal = document.getElementById('cancelModal');
                if (modal.style.display === 'flex' && 
                    event.target === modal) {
                    closeCancelModal();
                }
            });
            
            // Listen for escape key to close modal
            document.addEventListener('keydown', function(event) {
                const modal = document.getElementById('cancelModal');
                if (event.key === 'Escape' && modal.style.display === 'flex') {
                    closeCancelModal();
                }
            });
            <?php endif; ?>
        });
        
        // Stop auto-refresh when page is not visible
        document.addEventListener('visibilitychange', function() {
            if (document.hidden) {
                clearInterval(autoRefreshInterval);
            } else {
                autoRefreshInterval = setInterval(() => {
                    if (document.querySelector('.assignment-card.active') || document.querySelector('.assignment-card.in-progress')) {
                        checkForNewAssignments();
                    }
                }, 30000);
            }
        });
    </script>
</body>
</html>