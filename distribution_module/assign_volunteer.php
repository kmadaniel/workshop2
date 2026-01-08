<?php
// ========================================
// ASSIGN VOLUNTEERS TO DISTRIBUTION
// Directly uses external API data without local volunteer table
// ========================================

require_once 'config.php';

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Start session early for assignment tracking
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$database = new Database();
$db = $database->getConnection();

// Check if database connection is valid
if (!$db || !is_object($db)) {
    die("Database connection failed. Please check your configuration.");
}

// API URLs
$VOLUNTEER_API_URL = 'http://10.147.17.30:8000/api_volunteer.php';
$NGO_API_URL = 'http://10.147.17.30:8000/api_ngo.php';
$VICTIM_API_URL = 'http://10.147.17.116:8000/victim.php'; // Victim API for families count

$distribution_id = $_GET['distribution_id'] ?? null;
$error = '';
$success = '';

// If no distribution_id in URL, redirect to distribution_main.php
if (!$distribution_id) {
    header("Location: distribution_main.php");
    exit;
}

// Initialize variables
$all_volunteers = [];
$available_volunteers = [];
$assigned_volunteers = [];
$distribution = null;
$ngos = ['All']; // Start with All option
$families_count = 0;
$victims_data = [];
$all_victims = [];

// Store NGO data for mapping
$ngo_data = [];
$ngo_id_to_name = []; // Map NGO ID to NGO Name
$ngo_name_to_id = []; // Map NGO Name to NGO ID

/* ========================================
   FUNCTIONS FOR CANCELLATION HANDLING
======================================== */

/**
 * Free up needs from cancelled volunteers
 */
function freeUpCancelledVolunteerNeeds($db, $distribution_id, $volunteer_id) {
    try {
        // Check if this volunteer had any assigned needs
        $check_needs_query = "
            SELECT COUNT(*) as assigned_needs 
            FROM distribution_log 
            WHERE distribution_id = ? 
            AND volunteer_id = ? 
            AND status IN ('in_transit', 'assigned')
        ";
        
        $check_stmt = $db->prepare($check_needs_query);
        if ($check_stmt) {
            $check_stmt->bind_param("ii", $distribution_id, $volunteer_id);
            $check_stmt->execute();
            $result = $check_stmt->get_result();
            $row = $result->fetch_assoc();
            $assigned_needs = $row['assigned_needs'] ?? 0;
            $check_stmt->close();
            
            if ($assigned_needs > 0) {
                // Free up these needs by setting status to 'available' and volunteer_id to NULL
                $free_needs_query = "
                    UPDATE distribution_log 
                    SET status = 'available', 
                        volunteer_id = NULL,
                        updated_at = NOW()
                    WHERE distribution_id = ? 
                    AND volunteer_id = ?
                    AND status IN ('in_transit', 'assigned')
                ";
                
                $free_stmt = $db->prepare($free_needs_query);
                if ($free_stmt) {
                    $free_stmt->bind_param("ii", $distribution_id, $volunteer_id);
                    $free_stmt->execute();
                    $freed_count = $free_stmt->affected_rows;
                    $free_stmt->close();
                    
                    error_log("Freed $freed_count needs from cancelled volunteer $volunteer_id for distribution $distribution_id");
                    return $freed_count;
                }
            }
        }
        return 0;
    } catch (Exception $e) {
        error_log("Error freeing up cancelled volunteer needs: " . $e->getMessage());
        return 0;
    }
}

/**
 * Cleanup cancelled volunteer assignments
 */
function cleanupCancelledVolunteers($db, $distribution_id = null) {
    try {
        $where_clause = $distribution_id ? "WHERE distribution_id = ?" : "";
        $params = $distribution_id ? [$distribution_id] : [];
        
        $query = "
            SELECT dv.distribution_id, dv.volunteer_id 
            FROM distribution_volunteer dv
            WHERE dv.status IN ('Completed')  -- Completed status is treated as cancelled for cleanup
            " . ($distribution_id ? "AND dv.distribution_id = ?" : "") . "
            AND EXISTS (
                SELECT 1 FROM distribution_log dl 
                WHERE dl.distribution_id = dv.distribution_id 
                AND dl.volunteer_id = dv.volunteer_id
                AND dl.status IN ('in_transit', 'assigned')
            )
        ";
        
        $stmt = $db->prepare($query);
        if ($stmt) {
            if ($distribution_id) {
                $stmt->bind_param("i", $distribution_id);
            }
            $stmt->execute();
            $result = $stmt->get_result();
            $cancelled_assignments = $result->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
            
            foreach ($cancelled_assignments as $assignment) {
                freeUpCancelledVolunteerNeeds($db, $assignment['distribution_id'], $assignment['volunteer_id']);
            }
            
            return count($cancelled_assignments);
        }
        return 0;
    } catch (Exception $e) {
        error_log("Error in cleanupCancelledVolunteers: " . $e->getMessage());
        return 0;
    }
}

/* ========================================
   CHECK VOLUNTEER AVAILABILITY FOR DATE
   NEW FUNCTION: Checks if volunteer is already assigned on the same date
======================================== */
function checkVolunteerDateAvailability($db, $volunteer_id, $distribution_date, $exclude_distribution_id = null) {
    try {
        // Check if volunteer is already assigned to another distribution on the same date
        $query = "
            SELECT dv.distribution_id, d.date, d.status as dist_status, dv.status as volunteer_status
            FROM distribution_volunteer dv
            JOIN distribution d ON dv.distribution_id = d.distribution_id
            WHERE dv.volunteer_id = ?
            AND d.date = ?
            AND dv.status NOT IN ('Completed', 'Cancelled')
            AND d.status NOT IN ('Completed', 'Cancelled')
        ";
        
        // Exclude current distribution if editing
        if ($exclude_distribution_id) {
            $query .= " AND dv.distribution_id != ?";
        }
        
        $stmt = $db->prepare($query);
        
        if ($exclude_distribution_id) {
            $stmt->bind_param("isi", $volunteer_id, $distribution_date, $exclude_distribution_id);
        } else {
            $stmt->bind_param("is", $volunteer_id, $distribution_date);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        $existing_assignments = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        if (count($existing_assignments) > 0) {
            $conflicting_ids = array_column($existing_assignments, 'distribution_id');
            return [
                'available' => false,
                'reason' => 'Volunteer is already assigned to distribution(s) #' . implode(', #', $conflicting_ids) . ' on ' . $distribution_date,
                'conflicting_distributions' => $existing_assignments
            ];
        }
        
        return [
            'available' => true,
            'reason' => 'Volunteer is available for this date'
        ];
        
    } catch (Exception $e) {
        error_log("Error checking volunteer date availability: " . $e->getMessage());
        return [
            'available' => false,
            'reason' => 'Error checking availability: ' . $e->getMessage()
        ];
    }
}

/* ========================================
   SYNC FUNCTION: DISTRIBUTION_ITEMS TO DISTRIBUTION_LOG
   FIXED VERSION WITH CORRECT STATUS
======================================== */
function syncDistributionItemsToLog($db, $distribution_id, $volunteer_id) {
    try {
        // Check if distribution_items table exists and has data
        $check_items_query = "SHOW TABLES LIKE 'distribution_items'";
        $check_result = $db->query($check_items_query);
        
        if (!$check_result || $check_result->num_rows == 0) {
            error_log("distribution_items table doesn't exist");
            return false;
        }
        
        // Get victims and needs from distribution_items
        $items_query = "
            SELECT di.victim_id, di.need_id 
            FROM distribution_items di
            WHERE di.distribution_id = ?
            GROUP BY di.victim_id, di.need_id
        ";
        
        $stmt = $db->prepare($items_query);
        if (!$stmt) {
            error_log("Failed to prepare items query: " . $db->error);
            return false;
        }
        
        $stmt->bind_param("i", $distribution_id);
        if (!$stmt->execute()) {
            error_log("Failed to execute items query: " . $stmt->error);
            $stmt->close();
            return false;
        }
        
        $result = $stmt->get_result();
        $items = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        if (empty($items)) {
            error_log("No items found in distribution_items for distribution_id: " . $distribution_id);
            return false;
        }
        
        // Insert items into distribution_log
        $inserted_count = 0;
        foreach ($items as $item) {
            $victim_id = $item['victim_id'] ?? 0;
            $need_id = $item['need_id'] ?? 0;
            
            if ($victim_id > 0 && $need_id > 0) {
                // Check if already exists in distribution_log
                $check_exist_query = "
                    SELECT id FROM distribution_log 
                    WHERE distribution_id = ? 
                    AND volunteer_id = ? 
                    AND victim_id = ? 
                    AND need_id = ?
                ";
                
                $check_stmt = $db->prepare($check_exist_query);
                if ($check_stmt) {
                    $check_stmt->bind_param("iiii", $distribution_id, $volunteer_id, $victim_id, $need_id);
                    $check_stmt->execute();
                    $check_stmt->store_result();
                    $exists = ($check_stmt->num_rows > 0);
                    $check_stmt->close();
                    
                    if (!$exists) {
                        // Insert into distribution_log with 'in_transit' status
                        $insert_query = "
                            INSERT INTO distribution_log 
                            (distribution_id, volunteer_id, victim_id, need_id, status) 
                            VALUES (?, ?, ?, ?, 'in_transit')
                        ";
                        
                        $insert_stmt = $db->prepare($insert_query);
                        if ($insert_stmt) {
                            $insert_stmt->bind_param("iiii", $distribution_id, $volunteer_id, $victim_id, $need_id);
                            if ($insert_stmt->execute()) {
                                $inserted_count++;
                                error_log("SUCCESS: Inserted into distribution_log - distribution=$distribution_id, volunteer=$volunteer_id, victim=$victim_id, need=$need_id");
                            } else {
                                error_log("ERROR: Failed to insert into distribution_log: " . $insert_stmt->error);
                            }
                            $insert_stmt->close();
                        } else {
                            error_log("ERROR: Failed to prepare insert statement: " . $db->error);
                        }
                    } else {
                        error_log("INFO: Already exists in distribution_log - distribution=$distribution_id, volunteer=$volunteer_id, victim=$victim_id, need=$need_id");
                    }
                }
            }
        }
        
        error_log("SYNC COMPLETE: Synced " . $inserted_count . " items to distribution_log for distribution $distribution_id, volunteer $volunteer_id");
        return $inserted_count > 0;
        
    } catch (Exception $e) {
        error_log("CRITICAL ERROR syncing distribution items to log: " . $e->getMessage());
        return false;
    }
}

/* ========================================
   ALERT SERVICE CLASS
   Simplified - Just handles assignment logging
======================================== */
class AlertService {
    private $db;
    
    public function __construct($database_connection = null) {
        // Store the database connection
        $this->db = $database_connection;
    }
    
    /**
     * Create alert for volunteer when assigned
     */
    public function createVolunteerAlert($volunteer_id, $distribution_id, $role) {
        $message = $this->generateAlertMessage($distribution_id, $role);
        $alert_type = 'assignment';
        
        try {
            // Check if we have a valid database connection
            if ($this->db && is_object($this->db)) {
                // Insert alert into volunteer_alerts table
                $query = "INSERT INTO volunteer_alerts 
                         (volunteer_id, distribution_id, alert_type, message, is_read, created_at) 
                         VALUES (?, ?, ?, ?, 0, NOW())";
                
                // Use $this->db instead of $db
                $stmt = $this->db->prepare($query);
                if ($stmt) {
                    $stmt->bind_param("iiss", $volunteer_id, $distribution_id, $alert_type, $message);
                    if ($stmt->execute()) {
                        $alert_id = $stmt->insert_id;
                        $stmt->close();
                        
                        return [
                            'success' => true,
                            'alert_id' => $alert_id,
                            'message' => 'Volunteer alert created successfully'
                        ];
                    } else {
                        error_log("Error executing alert query: " . $this->db->error);
                    }
                } else {
                    error_log("Error preparing alert query: " . $this->db->error);
                }
            } else {
                error_log("Database connection not available for alert creation");
            }
        } catch (Exception $e) {
            // Log error but continue
            error_log("Error creating volunteer alert: " . $e->getMessage());
        }
        
        return ['success' => false];
    }
    
    /**
     * Generate alert message for volunteer
     */
    private function generateAlertMessage($distribution_id, $role) {
        return "You have been assigned to a new distribution task (ID: DIST" . str_pad($distribution_id, 7, '0', STR_PAD_LEFT) . ") as a {$role}. Please check your dashboard for details.";
    }
}

/* ========================================
   API FETCH FUNCTIONS - IMPROVED
======================================== */
function fetchFromAPI($url, $params = []) {
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
        
        // Try file_get_contents first
        $response = @file_get_contents($full_url, false, $context);
        
        // Fallback to cURL if needed
        if ($response === FALSE) {
            error_log("file_get_contents failed, trying cURL...");
            $response = fetchWithCURL($full_url);
        }
        
        if ($response === FALSE) {
            $error = error_get_last();
            error_log("API request FAILED for {$full_url}: " . ($error['message'] ?? 'Unknown error'));
            return ['success' => false, 'error' => 'API server not responding'];
        }
        
        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("JSON decode error: " . json_last_error_msg());
            return ['success' => false, 'error' => 'Invalid JSON: ' . json_last_error_msg()];
        }
        
        return ['success' => true, 'data' => $data];
    } catch (Exception $e) {
        error_log("Exception fetching from API {$url}: " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

// cURL fallback function
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
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    curl_close($ch);
    
    if ($response === false) {
        error_log("cURL error: {$error}, HTTP Code: {$httpCode}");
        return false;
    }
    
    return $response;
}

// Create alert service instance with database connection
$alert_service = new AlertService($db);

/* ========================================
   FETCH NGOS FROM EXTERNAL API FIRST
   (We need this to map NGO IDs to names)
======================================== */
try {
    // Fetch NGOs from external API
    $ngo_api_result = fetchFromAPI($NGO_API_URL);
    
    if ($ngo_api_result['success']) {
        $api_ngos = $ngo_api_result['data'];
        
        // Check if data is nested
        if (isset($api_ngos['ngos']) && is_array($api_ngos['ngos'])) {
            $api_ngos = $api_ngos['ngos'];
        } elseif (isset($api_ngos['data']) && is_array($api_ngos['data'])) {
            $api_ngos = $api_ngos['data'];
        }
        
        // Store NGO data and create mapping arrays
        $ngo_data = $api_ngos;
        
        // Create mapping arrays
        foreach ($ngo_data as $ngo) {
            $ngo_id = $ngo['NGOID'] ?? $ngo['id'] ?? null;
            $ngo_name = $ngo['NGOName'] ?? $ngo['ngoName'] ?? $ngo['name'] ?? 'Unknown NGO';
            
            if ($ngo_id && $ngo_name != 'Unknown NGO') {
                $ngo_id_to_name[$ngo_id] = $ngo_name;
                $ngo_name_to_id[$ngo_name] = $ngo_id;
                
                // Add to filter list
                if (!in_array($ngo_name, $ngos)) {
                    $ngos[] = $ngo_name;
                }
            }
        }
        
        error_log("DEBUG: Loaded " . count($ngo_id_to_name) . " NGOs for mapping");
        
    } else {
        error_log("Failed to fetch NGOs: " . ($ngo_api_result['error'] ?? 'Unknown error'));
    }
} catch (Exception $e) {
    error_log("Error fetching NGOs: " . $e->getMessage());
    // Keep existing NGO list
}

/* ========================================
   FETCH VOLUNTEERS FROM EXTERNAL API
======================================== */
try {
    // Fetch volunteers from external API
    $volunteer_api_result = fetchFromAPI($VOLUNTEER_API_URL);
    
    if ($volunteer_api_result['success']) {
        $api_volunteers = $volunteer_api_result['data'];
        
        // Check if data is nested
        if (isset($api_volunteers['volunteers']) && is_array($api_volunteers['volunteers'])) {
            $api_volunteers = $api_volunteers['volunteers'];
        } elseif (isset($api_volunteers['data']) && is_array($api_volunteers['data'])) {
            $api_volunteers = $api_volunteers['data'];
        }
        
        // Debug: Log all volunteers found
        error_log("DEBUG: Total volunteers from API: " . count($api_volunteers));
        
        foreach ($api_volunteers as $api_vol) {
            // Extract volunteer ID - EXTERNAL ID from external database
            $external_volunteer_id = $api_vol['VolunteerID'] ?? $api_vol['volunteer_id'] ?? $api_vol['id'] ?? 0;
            $name = $api_vol['FullName'] ?? $api_vol['fullName'] ?? $api_vol['name'] ?? 'Unknown Volunteer';
            
            // Get skill category from API - This is the volunteer's selected role/skill
            $skill_category = $api_vol['SkillCategory'] ?? $api_vol['skill_category'] ?? $api_vol['Role'] ?? $api_vol['role'] ?? 'Volunteer';
            
            // Get NGO ID from API (this is a number like 20)
            $assigned_ngo_id = $api_vol['AssignedNGO'] ?? $api_vol['assignedNGO'] ?? $api_vol['ngo'] ?? null;
            
            // Get volunteer status - IMPORTANT: Check status from API
            $status_from_api = $api_vol['Status'] ?? 'active';
            $original_status_lower = strtolower($status_from_api);
            
            // Determine availability status based on API status
            if ($original_status_lower === 'inactive' || $original_status_lower === 'suspended') {
                $availability_status = 'inactive';
            } elseif ($original_status_lower === 'new' || $original_status_lower === 'pending') {
                $availability_status = 'new';
            } else {
                $availability_status = 'active'; // active, available, etc.
            }
            
            // Convert NGO ID to NGO Name using our mapping
            $ngo_affiliation = 'Various'; // Default
            if ($assigned_ngo_id && isset($ngo_id_to_name[$assigned_ngo_id])) {
                $ngo_affiliation = $ngo_id_to_name[$assigned_ngo_id];
            } elseif (is_string($assigned_ngo_id)) {
                // If it's already a string (NGO name), use it directly
                $ngo_affiliation = $assigned_ngo_id;
            }
            
            // Only add if we have valid data
            if ($external_volunteer_id && $name != 'Unknown Volunteer') {
                $volunteer = [
                    'volunteer_id' => $external_volunteer_id,
                    'name' => $name,
                    'phone' => $api_vol['Phone'] ?? $api_vol['phone'] ?? '',
                    'email' => $api_vol['Email'] ?? $api_vol['email'] ?? '',
                    'role' => $skill_category, // Use skill category from API as role
                    'skill_category' => $skill_category, // Store skill category separately
                    'availability_status' => $availability_status,
                    'original_status' => $status_from_api,
                    'ngo_affiliation' => $ngo_affiliation,
                    'ngo_id' => $assigned_ngo_id, // Keep the original NGO ID
                    'date_availability' => 'pending' // Will be checked later
                ];
                
                $all_volunteers[] = $volunteer;
                
                // Add NGO to list if not already there (using NGO name)
                if (!empty($ngo_affiliation) && !in_array($ngo_affiliation, $ngos)) {
                    $ngos[] = $ngo_affiliation;
                }
                
                // Debug specific volunteers
                if (in_array($external_volunteer_id, [23, 24])) {
                    error_log("DEBUG: Loaded volunteer $external_volunteer_id ($name) - Status: $status_from_api, Availability: $availability_status");
                }
            }
        }
        
        if (empty($all_volunteers)) {
            throw new Exception("No volunteers found in API response");
        }
        
        error_log("DEBUG: Loaded " . count($all_volunteers) . " volunteers");
        
    } else {
        throw new Exception("Failed to fetch volunteers from API: " . ($volunteer_api_result['error'] ?? 'Unknown error'));
    }
} catch (Exception $e) {
    error_log("Error fetching volunteers from API: " . $e->getMessage());
    $error = "Unable to load volunteers from external system. Please try again later.";
}

/* ========================================
   MAIN APPLICATION CODE CONTINUES
======================================== */

// Check for removal success
if (isset($_GET['removed']) && $_GET['removed'] == '1') {
    $success = "✅ Volunteer has been successfully removed from the assignment.";
}

// Run cleanup for cancelled volunteers
if ($distribution_id) {
    $cleaned = cleanupCancelledVolunteers($db, $distribution_id);
    if ($cleaned > 0) {
        error_log("Cleaned up $cleaned cancelled volunteer assignments for distribution $distribution_id");
    }
}

/* ----------------------------------------
   GET DISTRIBUTION PLAN DETAILS
   FIXED: Get distribution details including disaster_id
---------------------------------------- */
if ($distribution_id) {
    try {
        $query = "SELECT * FROM distribution WHERE distribution_id = ?";
        
        $stmt = $db->prepare($query);
        if (!$stmt) {
            throw new Exception("Database error: " . $db->error);
        }
        
        $stmt->bind_param("i", $distribution_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $distribution = $result->fetch_assoc();
        $stmt->close();
        
        if (!$distribution) {
            throw new Exception("Distribution plan not found!");
        }
        
        // Debug: Show distribution data
        error_log("DEBUG: Distribution data loaded - Date: " . ($distribution['date'] ?? 'Not set') . ", Disaster ID: " . ($distribution['disaster_id'] ?? 'Not set'));
        
    } catch (Exception $e) {
        $error = $e->getMessage();
        $distribution = null;
    }
}

/* ========================================
   FETCH ALL VICTIMS FROM API - ENHANCED WITH LOCATION EXTRACTION
======================================== */
try {
    // Fetch victims from external API with improved error handling
    $victim_api_result = fetchFromAPI($VICTIM_API_URL);
    
    if ($victim_api_result['success']) {
        $api_victims = $victim_api_result['data'];
        
        // Check if data is nested
        if (isset($api_victims['victims']) && is_array($api_victims['victims'])) {
            $api_victims = $api_victims['victims'];
        } elseif (isset($api_victims['data']) && is_array($api_victims['data'])) {
            $api_victims = $api_victims['data'];
        }
        
        // Store all victims data
        $all_victims = $api_victims;
        
        error_log("DEBUG: Found " . count($all_victims) . " total victims from API");
        
    } else {
        error_log("Failed to fetch victims from API: " . ($victim_api_result['error'] ?? 'Unknown error'));
        $error .= "<br>Unable to load victim data from external system.";
    }
} catch (Exception $e) {
    error_log("Error fetching victims from API: " . $e->getMessage());
    $error .= "<br>Note: Unable to load victim data from external system.";
}

/* ========================================
   CHECK VOLUNTEER DATE AVAILABILITY
   NEW: Check each volunteer's availability for the distribution date
======================================== */
if ($distribution && !empty($all_volunteers) && isset($distribution['date'])) {
    try {
        $distribution_date = $distribution['date'];
        $date_availability_stats = [
            'available' => 0,
            'date_conflict' => 0,
            'inactive' => 0,
            'new' => 0
        ];
        
        // Check each volunteer's availability for this date
        foreach ($all_volunteers as &$volunteer) {
            $volunteer_id = $volunteer['volunteer_id'];
            $availability_status = $volunteer['availability_status'] ?? 'active';
            
            // First check if volunteer is active in the system
            if ($availability_status === 'inactive') {
                // Volunteer is inactive in the system (from API status)
                $volunteer['date_availability'] = 'inactive';
                $volunteer['availability_reason'] = 'Volunteer is inactive in system';
                $date_availability_stats['inactive']++;
                continue;
            }
            
            // Check date availability for active volunteers
            $availability_result = checkVolunteerDateAvailability(
                $db, 
                $volunteer_id, 
                $distribution_date, 
                $distribution_id // Exclude current distribution
            );
            
            $is_available_for_date = $availability_result['available'];
            
            // Determine final availability
            if ($is_available_for_date) {
                $volunteer['date_availability'] = 'available';
                $volunteer['availability_reason'] = 'Available for ' . $distribution_date;
                $date_availability_stats['available']++;
                
                // Debug specific volunteers
                if (in_array($volunteer_id, [23, 24])) {
                    error_log("DEBUG: Volunteer $volunteer_id is AVAILABLE for date $distribution_date");
                }
            } else {
                $volunteer['date_availability'] = 'date_conflict';
                $volunteer['availability_reason'] = $availability_result['reason'];
                $volunteer['conflicting_distributions'] = $availability_result['conflicting_distributions'] ?? [];
                $date_availability_stats['date_conflict']++;
                
                // Debug specific volunteers
                if (in_array($volunteer_id, [23, 24])) {
                    error_log("DEBUG: Volunteer $volunteer_id has DATE CONFLICT for $distribution_date - Reason: " . $availability_result['reason']);
                }
            }
        }
        
        unset($volunteer); // Break reference
        
        error_log("DATE AVAILABILITY STATS for {$distribution_date}: " . json_encode($date_availability_stats));
        
    } catch (Exception $e) {
        error_log("Error checking volunteer date availability: " . $e->getMessage());
        $error .= "<br>Note: Unable to check volunteer availability for selected date.";
    }
}

/* ========================================
   GET FAMILIES ASSIGNED TO THIS DISTRIBUTION
======================================== */
if ($distribution_id && is_object($db) && !empty($all_victims)) {
    try {
        // Check if distribution_items table exists
        $table_check = $db->query("SHOW TABLES LIKE 'distribution_items'");
        $has_distribution_items_table = ($table_check && $table_check->num_rows > 0);
        
        if ($has_distribution_items_table) {
            // Get victims assigned to this distribution from distribution_items table
            $assigned_victims_query = "
                SELECT di.victim_id
                FROM distribution_items di
                WHERE di.distribution_id = ?
            ";
            
            $stmt = $db->prepare($assigned_victims_query);
            if ($stmt) {
                $stmt->bind_param("i", $distribution_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $assigned_victim_ids = [];
                while ($row = $result->fetch_assoc()) {
                    $assigned_victim_ids[] = $row['victim_id'];
                }
                $stmt->close();
                
                $families_count = count($assigned_victim_ids);
                
                error_log("DEBUG: Found " . $families_count . " families assigned to distribution " . $distribution_id);
                
                // Get full victim details from API for assigned families
                if (!empty($assigned_victim_ids) && !empty($all_victims)) {
                    foreach ($all_victims as $victim) {
                        $victim_id = $victim['victim_id'] ?? $victim['id'] ?? null;
                        if ($victim_id && in_array($victim_id, $assigned_victim_ids)) {
                            
                            // Enhanced location extraction
                            $victim_address = $victim['address'] ?? 
                                            $victim['Address'] ?? 
                                            $victim['full_address'] ?? 
                                            $victim['Full_Address'] ?? 
                                            $victim['location'] ?? 
                                            $victim['Location'] ?? '';
                            
                            $victim_city = $victim['city'] ?? 
                                         $victim['City'] ?? 
                                         $victim['district'] ?? 
                                         $victim['District'] ?? '';
                                         
                            $victim_postal = $victim['postal_code'] ?? 
                                           $victim['Postal_Code'] ?? 
                                           $victim['postalCode'] ?? 
                                           $victim['zip_code'] ?? 
                                           $victim['Zip_Code'] ?? '';
                            
                            // Construct full location address
                            if (!empty($victim_address)) {
                                $victim_location = $victim_address;
                                if (!empty($victim_city)) {
                                    $victim_location .= ', ' . $victim_city;
                                }
                                if (!empty($victim_postal)) {
                                    $victim_location .= ' ' . $victim_postal;
                                }
                            } else {
                                $victim_location = $victim['location'] ?? 
                                                 $victim['Location'] ?? 
                                                 $victim['residence'] ?? 
                                                 $victim['Residence'] ?? 
                                                 'Location not specified';
                            }
                            
                            // Enhanced victim data with location
                            $victims_data[] = [
                                'victim_id' => $victim_id,
                                'full_name' => $victim['full_name'] ?? 
                                             $victim['Full_Name'] ?? 
                                             $victim['fullName'] ?? 
                                             $victim['name'] ?? 'Unknown',
                                'ic_number' => $victim['ic_number'] ?? 
                                              $victim['IC_Number'] ?? 
                                              $victim['icNumber'] ?? 'N/A',
                                'address' => $victim_location,
                                'family_members' => $victim['family_members'] ?? 
                                                  $victim['Family_Members'] ?? 
                                                  $victim['familyMembers'] ?? 1,
                                'phone' => $victim['phone'] ?? 
                                          $victim['Phone'] ?? '',
                                'has_elderly' => isset($victim['has_elderly']) ? $victim['has_elderly'] : 
                                               (isset($victim['HasElderly']) ? $victim['HasElderly'] : false),
                                'has_baby' => isset($victim['has_baby']) ? $victim['has_baby'] : 
                                            (isset($victim['HasBaby']) ? $victim['HasBaby'] : false),
                                'has_disabled' => isset($victim['has_disabled']) ? $victim['has_disabled'] : 
                                                 (isset($victim['HasDisabled']) ? $victim['HasDisabled'] : false),
                            ];
                        }
                    }
                    
                    // Reindex array
                    $victims_data = array_values($victims_data);
                    
                    error_log("DEBUG: Found " . count($victims_data) . " matching victim records in API");
                    
                }
            }
        }
        
    } catch (Exception $e) {
        error_log("Error getting assigned families: " . $e->getMessage());
        $error .= "<br>Note: Unable to load assigned families.";
    }
}

/* ----------------------------------------
   GET AVAILABLE VOLUNTEERS
   Now with date availability check
---------------------------------------- */
if ($distribution && is_object($db) && !empty($all_volunteers)) {
    try {
        // Filter out already assigned volunteers
        if ($distribution_id) {
            $assigned_query = "SELECT volunteer_id, status FROM distribution_volunteer WHERE distribution_id = ?";
            $stmt = $db->prepare($assigned_query);
            if ($stmt) {
                $stmt->bind_param("i", $distribution_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $assigned_vols = $result->fetch_all(MYSQLI_ASSOC);
                $stmt->close();
                
                $assigned_ids = [];
                $cancelled_volunteers = [];
                
                foreach ($assigned_vols as $assigned) {
                    $status = $assigned['status'];
                    // Check if volunteer is completed (treated as cancelled for cleanup)
                    if ($status === 'Completed') {
                        $cancelled_volunteers[] = $assigned['volunteer_id'];
                        // Free up their needs
                        freeUpCancelledVolunteerNeeds($db, $distribution_id, $assigned['volunteer_id']);
                    } else {
                        $assigned_ids[] = $assigned['volunteer_id'];
                    }
                }
                
                // Log cancelled volunteers
                if (!empty($cancelled_volunteers)) {
                    error_log("Found completed volunteers for distribution $distribution_id: " . implode(', ', $cancelled_volunteers));
                }
            }
        } else {
            $assigned_ids = [];
        }
        
        // Build available volunteers list from API data with date availability check
        foreach ($all_volunteers as $volunteer) {
            // Skip if already assigned to this distribution
            if (in_array($volunteer['volunteer_id'], $assigned_ids)) {
                continue;
            }
            
            // Debug specific volunteers
            if (in_array($volunteer['volunteer_id'], [23, 24])) {
                error_log("DEBUG: Processing volunteer " . $volunteer['volunteer_id'] . 
                         " - Date availability: " . ($volunteer['date_availability'] ?? 'not set') .
                         " - Availability status: " . ($volunteer['availability_status'] ?? 'not set'));
            }
            
            // All volunteers (including date conflicts and inactive) go to available_volunteers
            // The availability will be indicated by CSS classes
            $available_volunteers[] = $volunteer;
        }
        
        error_log("DEBUG: Total available volunteers: " . count($available_volunteers));
        
    } catch (Exception $e) {
        $error .= "<br>Error processing volunteers: " . $e->getMessage();
    }
}

/* ----------------------------------------
   GET CURRENTLY ASSIGNED VOLUNTEERS
   Matching external volunteer IDs
---------------------------------------- */
if ($distribution && is_object($db) && $distribution_id && !empty($all_volunteers)) {
    try {
        // Get assigned volunteer IDs from our database (these are EXTERNAL IDs)
        $assigned_ids_query = "SELECT volunteer_id, role, status FROM distribution_volunteer WHERE distribution_id = ? AND status != 'Completed'";
        $stmt = $db->prepare($assigned_ids_query);
        if (!$stmt) {
            throw new Exception("Failed to prepare assigned query: " . $db->error);
        }
        
        $stmt->bind_param("i", $distribution_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $assignments_db = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        // Get volunteer details from our all_volunteers array
        $assigned_volunteers = [];
        foreach ($assignments_db as $assignment) {
            $external_volunteer_id = $assignment['volunteer_id'];
            
            // Find volunteer in our all_volunteers array
            $volunteer_found = null;
            foreach ($all_volunteers as $vol) {
                if ($vol['volunteer_id'] == $external_volunteer_id) {
                    $volunteer_found = $vol;
                    break;
                }
            }
            
            if ($volunteer_found) {
                // Use the role from distribution_volunteer (assigned role) 
                // but keep skill_category from API
                $assigned_volunteers[] = array_merge($volunteer_found, [
                    'assigned_role' => $assignment['role'], // Role assigned in this distribution
                    'skill_category' => $volunteer_found['skill_category'], // Original skill from API
                    'status' => $assignment['status']
                ]);
            }
        }
        
        // Sort by name
        usort($assigned_volunteers, function($a, $b) {
            return strcmp($a['name'], $b['name']);
        });
        
    } catch (Exception $e) {
        $error .= "<br>Error loading assigned volunteers: " . $e->getMessage();
    }
}

/* ----------------------------------------
   FORM SUBMISSION: ASSIGN VOLUNTEERS WITH ALERTS
   WITH DATE AVAILABILITY CHECK
---------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_volunteers']) && $db && is_object($db)) {
    $selected_volunteers = $_POST['selected_volunteers'] ?? [];
    $roles = $_POST['roles'] ?? [];
    
    try {
        if (empty($selected_volunteers)) {
            throw new Exception("Please select at least one volunteer.");
        }
        
        if (empty($all_volunteers)) {
            throw new Exception("No volunteer data available from external system.");
        }
        
        // Get distribution date for validation
        $dist_date = $distribution['date'] ?? null;
        if (!$dist_date) {
            throw new Exception("Distribution date not found. Cannot validate volunteer availability.");
        }
        
        $db->begin_transaction();
        $assignment_results = [];
        $validation_errors = [];
        
        foreach ($selected_volunteers as $external_volunteer_id) {
            // Get volunteer from API data
            $volunteer = null;
            foreach ($all_volunteers as $vol) {
                if ($vol['volunteer_id'] == $external_volunteer_id) {
                    $volunteer = $vol;
                    break;
                }
            }
            
            if (!$volunteer) {
                $validation_errors[] = "Volunteer $external_volunteer_id not found in API data";
                continue;
            }
            
            // Check if volunteer is active (from API status)
            $volunteer_status = strtolower($volunteer['original_status'] ?? 'active');
            if ($volunteer_status === 'inactive' || $volunteer_status === 'suspended') {
                $validation_errors[] = "Volunteer {$volunteer['name']} is {$volunteer_status} in system";
                continue;
            }
            
            // Check date availability
            $availability_result = checkVolunteerDateAvailability($db, $external_volunteer_id, $dist_date, $distribution_id);
            if (!$availability_result['available']) {
                $validation_errors[] = "Volunteer {$volunteer['name']} is already assigned to another distribution on {$dist_date}";
                continue;
            }
            
            // Use assigned role if provided, otherwise use volunteer's skill category
            $role = $roles[$external_volunteer_id] ?? ($volunteer['skill_category'] ?? 'Volunteer');
            
            // Check if volunteer already assigned to THIS distribution
            $check_query = "SELECT id FROM distribution_volunteer WHERE distribution_id = ? AND volunteer_id = ?";
            $check_stmt = $db->prepare($check_query);
            if (!$check_stmt) {
                throw new Exception("Prepare failed for check query: " . $db->error);
            }
            
            $check_stmt->bind_param("ii", $distribution_id, $external_volunteer_id);
            if (!$check_stmt->execute()) {
                throw new Exception("Execute failed for check query: " . $check_stmt->error);
            }
            
            $check_stmt->store_result();
            $already_assigned = ($check_stmt->num_rows > 0);
            $check_stmt->close();
            
            if (!$already_assigned) {
                // Assign volunteer to distribution using EXTERNAL ID
                $assign_query = "INSERT INTO distribution_volunteer (distribution_id, volunteer_id, role, status) VALUES (?, ?, ?, 'Assigned')";
                $assign_stmt = $db->prepare($assign_query);
                if (!$assign_stmt) {
                    throw new Exception("Prepare failed for assign query: " . $db->error);
                }
                
                $assign_stmt->bind_param("iis", $distribution_id, $external_volunteer_id, $role);
                if (!$assign_stmt->execute()) {
                    throw new Exception("Execute failed for assign query: " . $assign_stmt->error);
                }
                $assign_stmt->close();
                
                // Sync distribution items to log
                $sync_result = syncDistributionItemsToLog($db, $distribution_id, $external_volunteer_id);
                error_log("SYNC RESULT for volunteer $external_volunteer_id: " . ($sync_result ? 'SUCCESS' : 'FAILED'));
                
                // Create alert for volunteer
                $alert_result = $alert_service->createVolunteerAlert($external_volunteer_id, $distribution_id, $role);
                $assignment_results[$external_volunteer_id] = [
                    'volunteer_id' => $external_volunteer_id,
                    'role' => $role,
                    'alert_created' => $alert_result['success'] ?? false,
                    'items_synced' => $sync_result
                ];
            }
        }
        
        if (!empty($validation_errors)) {
            $db->rollback();
            throw new Exception("Some volunteers cannot be assigned:<br>" . implode("<br>", $validation_errors));
        }
        
        if (empty($assignment_results)) {
            $db->rollback();
            throw new Exception("No volunteers were successfully assigned.");
        }
        
        // Update distribution status
        $update_dist_query = "UPDATE distribution SET status = 'Assigned' WHERE distribution_id = ?";
        $update_dist_stmt = $db->prepare($update_dist_query);
        if (!$update_dist_stmt) {
            throw new Exception("Prepare failed for update distribution query: " . $db->error);
        }
        
        $update_dist_stmt->bind_param("i", $distribution_id);
        if (!$update_dist_stmt->execute()) {
            throw new Exception("Execute failed for update distribution query: " . $update_dist_stmt->error);
        }
        $update_dist_stmt->close();
        
        $db->commit();
        
        // Store results in session for success modal
        $_SESSION['assignment_success'] = count($selected_volunteers);
        $_SESSION['distribution_id'] = $distribution_id;
        $_SESSION['sync_results'] = $assignment_results;
        
        header("Location: assign_volunteer.php?distribution_id={$distribution_id}&success=1");
        exit;
        
    } catch (Exception $e) {
        if (isset($db) && is_object($db) && method_exists($db, 'rollback')) {
            @$db->rollback();
        }
        $error = "Error: " . $e->getMessage();
    }
}

/* ----------------------------------------
   REMOVE VOLUNTEER FROM ASSIGNMENT
   Using external volunteer IDs
---------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_volunteer'])) {
    $remove_volunteer_id = $_POST['volunteer_id'] ?? null;
    
    if ($remove_volunteer_id && $distribution_id && $db && is_object($db)) {
        try {
            $db->begin_transaction();
            
            // Remove from distribution_volunteer table
            $remove_query = "DELETE FROM distribution_volunteer WHERE distribution_id = ? AND volunteer_id = ?";
            $remove_stmt = $db->prepare($remove_query);
            if (!$remove_stmt) {
                throw new Exception("Failed to prepare remove query: " . $db->error);
            }
            
            $remove_stmt->bind_param("ii", $distribution_id, $remove_volunteer_id);
            if (!$remove_stmt->execute()) {
                throw new Exception("Failed to execute remove query: " . $remove_stmt->error);
            }
            $remove_stmt->close();
            
            // Remove from distribution_log table
            $remove_log_query = "DELETE FROM distribution_log WHERE distribution_id = ? AND volunteer_id = ?";
            $remove_log_stmt = $db->prepare($remove_log_query);
            if ($remove_log_stmt) {
                $remove_log_stmt->bind_param("ii", $distribution_id, $remove_volunteer_id);
                $remove_log_stmt->execute();
                $remove_log_stmt->close();
                error_log("Removed distribution_log entries for volunteer $remove_volunteer_id, distribution $distribution_id");
            }
            
            // Remove from distribution_items table using assigned_volunteer_id
            $remove_items_query = "
                DELETE FROM distribution_items 
                WHERE distribution_id = ? 
                AND assigned_volunteer_id = ?
            ";
            
            $remove_items_stmt = $db->prepare($remove_items_query);
            if ($remove_items_stmt) {
                $remove_items_stmt->bind_param("ii", $distribution_id, $remove_volunteer_id);
                $remove_items_stmt->execute();
                $removed_items = $remove_items_stmt->affected_rows;
                $remove_items_stmt->close();
                error_log("Removed $removed_items items from distribution_items for volunteer $remove_volunteer_id");
            }
            
            // Check if any volunteers are still assigned
            $check_remaining_query = "SELECT COUNT(*) as count FROM distribution_volunteer WHERE distribution_id = ?";
            $check_stmt = $db->prepare($check_remaining_query);
            if (!$check_stmt) {
                throw new Exception("Failed to prepare check remaining query: " . $db->error);
            }
            
            $check_stmt->bind_param("i", $distribution_id);
            if (!$check_stmt->execute()) {
                throw new Exception("Failed to execute check remaining query: " . $check_stmt->error);
            }
            
            $result = $check_stmt->get_result();
            $row = $result->fetch_assoc();
            $remaining_count = $row ? $row['count'] : 0;
            $check_stmt->close();
            
            // Update distribution status if no volunteers left
            if ($remaining_count == 0) {
                $update_dist_query = "UPDATE distribution SET status = 'Planning' WHERE distribution_id = ?";
                $update_dist_stmt = $db->prepare($update_dist_query);
                if (!$update_dist_stmt) {
                    throw new Exception("Failed to prepare update distribution query: " . $db->error);
                }
                
                $update_dist_stmt->bind_param("i", $distribution_id);
                if (!$update_dist_stmt->execute()) {
                    throw new Exception("Failed to execute update distribution query: " . $update_dist_stmt->error);
                }
                $update_dist_stmt->close();
            }
            
            $db->commit();
            
            // Redirect to refresh the page
            header("Location: assign_volunteer.php?distribution_id={$distribution_id}&removed=1");
            exit;
            
        } catch (Exception $e) {
            if (isset($db) && is_object($db) && method_exists($db, 'rollback')) {
                $db->rollback();
            }
            $error = "Error removing volunteer: " . $e->getMessage();
        }
    }
}

// Check for success parameter
$show_success_modal = isset($_GET['success']) && $_GET['success'] == '1' && isset($_SESSION['assignment_success']);

// Calculate total family members
$total_family_members = 0;
if (!empty($victims_data)) {
    foreach ($victims_data as $victim) {
        if (isset($victim['family_members']) && is_numeric($victim['family_members'])) {
            $total_family_members += intval($victim['family_members']);
        }
    }
}

// Get unique NGO names from available volunteers for the filter dropdown
$unique_ngo_names = [];
if (!empty($available_volunteers)) {
    foreach ($available_volunteers as $volunteer) {
        $ngo_name = $volunteer['ngo_affiliation'] ?? 'Various';
        if (!in_array($ngo_name, $unique_ngo_names)) {
            $unique_ngo_names[] = $ngo_name;
        }
    }
    sort($unique_ngo_names);
}

// Count available and unavailable volunteers
$active_available_volunteers = array_filter($available_volunteers, function($volunteer) {
    $date_availability = $volunteer['date_availability'] ?? '';
    $api_status = strtolower($volunteer['original_status'] ?? 'active');
    $is_system_active = !($api_status === 'inactive' || $api_status === 'suspended');
    return ($date_availability === 'available' && $is_system_active);
});

$date_unavailable_volunteers = array_filter($available_volunteers, function($volunteer) {
    $date_availability = $volunteer['date_availability'] ?? '';
    $api_status = strtolower($volunteer['original_status'] ?? 'active');
    $is_system_active = !($api_status === 'inactive' || $api_status === 'suspended');
    return ($date_availability === 'date_conflict' && $is_system_active);
});

$inactive_volunteers = array_filter($available_volunteers, function($volunteer) {
    $api_status = strtolower($volunteer['original_status'] ?? 'active');
    return ($api_status === 'inactive' || $api_status === 'suspended');
});

// Debug: Log specific volunteers
error_log("=== VOLUNTEER STATUS SUMMARY ===");
error_log("Total volunteers loaded: " . count($all_volunteers));
error_log("Active & available: " . count($active_available_volunteers));
error_log("Date conflicts: " . count($date_unavailable_volunteers));
error_log("Inactive: " . count($inactive_volunteers));

foreach ($all_volunteers as $vol) {
    if (in_array($vol['volunteer_id'], [23, 24])) {
        error_log("Volunteer " . $vol['volunteer_id'] . " (" . $vol['name'] . "):");
        error_log("  - API Status: " . ($vol['original_status'] ?? 'N/A'));
        error_log("  - Availability Status: " . ($vol['availability_status'] ?? 'N/A'));
        error_log("  - Date Availability: " . ($vol['date_availability'] ?? 'N/A'));
        error_log("  - Reason: " . ($vol['availability_reason'] ?? 'N/A'));
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assign Volunteers - Disaster Relief System</title>
    <link rel="stylesheet" href="../css/assign.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Modern Redesign */
        :root {
            --primary: #4361ee;
            --primary-dark: #3a56d4;
            --secondary: #7209b7;
            --success: #2ecc71;
            --warning: #f39c12;
            --danger: #e74c3c;
            --light: #f8f9fa;
            --dark: #2c3e50;
            --gray: #6c757d;
            --light-gray: #e9ecef;
            --border-radius: 12px;
            --box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            --transition: all 0.3s ease;
        }
        
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }
        
        /* Success Modal */
        .success-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.8);
            z-index: 10000;
            align-items: center;
            justify-content: center;
            animation: fadeIn 0.3s;
        }
        
        .success-modal-content {
            background: white;
            border-radius: var(--border-radius);
            padding: 40px;
            max-width: 600px;
            width: 90%;
            text-align: center;
            animation: slideUp 0.4s;
            box-shadow: var(--box-shadow);
        }
        
        .success-icon {
            font-size: 60px;
            color: var(--success);
            margin-bottom: 20px;
        }
        
        /* Cards */
        .glass-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: var(--border-radius);
            padding: 30px;
            margin-bottom: 25px;
            box-shadow: var(--box-shadow);
            border: 1px solid rgba(255, 255, 255, 0.2);
            animation: slideInUp 0.5s ease;
        }
        
        @keyframes slideInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* Header Section */
        .header-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .back-btn {
            background: var(--light);
            color: var(--dark);
            border: none;
            padding: 12px 24px;
            border-radius: 50px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            transition: var(--transition);
        }
        
        .back-btn:hover {
            background: white;
            transform: translateX(-5px);
        }
        
        .page-title {
            color: white;
            font-size: 2.2rem;
            margin: 0;
            text-shadow: 0 2px 10px rgba(0,0,0,0.2);
        }
        
        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin: 30px 0;
        }
        
        .stat-card {
            background: white;
            border-radius: var(--border-radius);
            padding: 25px;
            text-align: center;
            box-shadow: var(--box-shadow);
            transition: var(--transition);
            border-top: 4px solid var(--primary);
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-card.volunteers { border-color: var(--primary); }
        .stat-card.assigned { border-color: var(--success); }
        .stat-card.available { border-color: var(--success); }
        .stat-card.date-conflict { border-color: #ff9f43; }
        .stat-card.inactive { border-color: var(--danger); }
        .stat-card.families { border-color: #2ecc71; }
        
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
        
        /* Date Availability Badge */
        .date-availability-badge {
            position: absolute;
            top: 10px;
            right: -25px;
            padding: 5px 25px;
            transform: rotate(45deg);
            font-size: 11px;
            font-weight: bold;
            z-index: 1;
        }
        
        .date-available {
            background: #2ecc71;
            color: white;
        }
        
        .date-unavailable {
            background: #e74c3c;
            color: white;
        }
        
        .date-conflict {
            background: #ff9f43;
            color: white;
        }
        
        /* Availability Info Tooltip */
        .availability-info {
            position: relative;
            display: inline-block;
            cursor: help;
            border-bottom: 1px dotted var(--gray);
        }
        
        .availability-tooltip {
            visibility: hidden;
            width: 300px;
            background-color: var(--dark);
            color: white;
            text-align: left;
            border-radius: 6px;
            padding: 15px;
            position: absolute;
            z-index: 1000;
            bottom: 125%;
            left: 50%;
            margin-left: -150px;
            opacity: 0;
            transition: opacity 0.3s;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
        }
        
        .availability-info:hover .availability-tooltip {
            visibility: visible;
            opacity: 1;
        }
        
        .availability-tooltip::after {
            content: "";
            position: absolute;
            top: 100%;
            left: 50%;
            margin-left: -5px;
            border-width: 5px;
            border-style: solid;
            border-color: var(--dark) transparent transparent transparent;
        }
        
        /* Volunteer Cards */
        .volunteer-card {
            background: white;
            border-radius: var(--border-radius);
            padding: 25px;
            box-shadow: var(--box-shadow);
            transition: var(--transition);
            border: 2px solid transparent;
            position: relative;
            overflow: hidden;
            min-height: 350px;
            display: flex;
            flex-direction: column;
        }
        
        .volunteer-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0,0,0,0.12);
        }
        
        .volunteer-card.selected {
            border-color: var(--primary);
            background: linear-gradient(135deg, rgba(67, 97, 238, 0.05), rgba(114, 9, 183, 0.05));
        }
        
        .volunteer-card.date-available {
            border-left: 4px solid #2ecc71;
        }
        
        .volunteer-card.date-conflict {
            border-left: 4px solid #ff9f43;
            background: linear-gradient(135deg, rgba(255, 159, 67, 0.05), rgba(255, 159, 67, 0.1));
        }
        
        .volunteer-card.inactive {
            border-left: 4px solid #e74c3c;
            background: linear-gradient(135deg, rgba(231, 76, 60, 0.05), rgba(231, 76, 60, 0.1));
        }
        
        .volunteer-checkbox {
            position: absolute;
            top: 20px;
            right: 20px;
            width: 22px;
            height: 22px;
            cursor: pointer;
            z-index: 10;
        }
        
        .volunteer-checkbox:disabled {
            cursor: not-allowed;
            opacity: 0.6;
        }
        
        .volunteer-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
            margin-top: 10px;
        }
        
        .volunteer-avatar {
            width: 70px;
            height: 70px;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            font-weight: bold;
        }
        
        .volunteer-card.date-available .volunteer-avatar {
            background: linear-gradient(135deg, #2ecc71, #27ae60);
        }
        
        .volunteer-card.date-conflict .volunteer-avatar {
            background: linear-gradient(135deg, #ff9f43, #e67e22);
        }
        
        .volunteer-card.inactive .volunteer-avatar {
            background: linear-gradient(135deg, #95a5a6, #7f8c8d);
        }
        
        .volunteer-info {
            flex: 1;
        }
        
        .volunteer-name {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--dark);
            margin: 0 0 5px 0;
        }
        
        .volunteer-id {
            font-size: 0.85rem;
            color: var(--gray);
            background: var(--light-gray);
            padding: 3px 10px;
            border-radius: 20px;
            display: inline-block;
        }
        
        .volunteer-details {
            display: flex;
            flex-direction: column;
            gap: 8px;
            flex: 1;
        }
        
        .detail-item {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.9rem;
            color: var(--gray);
        }
        
        .detail-item i {
            width: 20px;
            color: var(--primary);
        }
        
        .skill-badge {
            display: inline-block;
            padding: 6px 15px;
            background: var(--light);
            color: var(--primary);
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            margin-right: 8px;
            margin-top: auto;
        }
        
        /* Role Selection */
        .role-selection {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid var(--light-gray);
        }
        
        /* Warning Message */
        .warning-message {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .warning-message i {
            color: #ffc107;
            font-size: 24px;
        }
        
        /* Filter Section */
        .filter-section {
            background: white;
            border-radius: var(--border-radius);
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: var(--box-shadow);
        }
        
        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .filter-label {
            font-weight: 600;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .filter-select {
            padding: 12px 16px;
            border: 2px solid var(--light-gray);
            border-radius: 8px;
            font-size: 14px;
            transition: var(--transition);
            background: white;
        }
        
        .filter-select:focus {
            border-color: var(--primary);
            outline: none;
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.1);
        }
        
        /* Volunteers Grid */
        .volunteers-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
            margin: 25px 0;
        }
        
        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 15px;
            margin-top: 30px;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 14px 28px;
            border: none;
            border-radius: 50px;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            display: flex;
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
            background: var(--primary-dark);
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
        
        .btn-danger {
            background: var(--danger);
            color: white;
        }
        
        .btn-outline {
            background: transparent;
            color: var(--primary);
            border: 2px solid var(--primary);
        }
        
        /* Checkbox custom styling */
        .checkbox-container {
            position: relative;
            padding-left: 35px;
            margin-bottom: 12px;
            cursor: pointer;
            font-size: 16px;
            user-select: none;
        }
        
        .checkbox-container input {
            position: absolute;
            opacity: 0;
            cursor: pointer;
            height: 0;
            width: 0;
        }
        
        .checkmark {
            position: absolute;
            top: 0;
            left: 0;
            height: 25px;
            width: 25px;
            background-color: #eee;
            border-radius: 4px;
        }
        
        .checkbox-container:hover input ~ .checkmark {
            background-color: #ccc;
        }
        
        .checkbox-container input:checked ~ .checkmark {
            background-color: var(--primary);
        }
        
        .checkmark:after {
            content: "";
            position: absolute;
            display: none;
        }
        
        .checkbox-container input:checked ~ .checkmark:after {
            display: block;
        }
        
        .checkbox-container .checkmark:after {
            left: 9px;
            top: 5px;
            width: 5px;
            height: 10px;
            border: solid white;
            border-width: 0 3px 3px 0;
            transform: rotate(45deg);
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .container {
                padding: 10px;
            }
            
            .page-title {
                font-size: 1.8rem;
            }
            
            .volunteers-container {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- Success Modal -->
    <?php if ($show_success_modal): 
        $sync_results = $_SESSION['sync_results'] ?? [];
        $successful_syncs = 0;
        foreach ($sync_results as $result) {
            if ($result['items_synced'] ?? false) {
                $successful_syncs++;
            }
        }
    ?>
    <div class="success-modal" id="successModal" style="display: flex;">
        <div class="success-modal-content">
            <div class="success-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <h2 style="color: var(--dark); margin-bottom: 10px;">✅ Assignment Successful!</h2>
            
            <div style="text-align: center; margin: 20px 0;">
                <p style="font-size: 18px; margin-bottom: 15px;">
                    <strong><?php echo $_SESSION['assignment_success']; ?> volunteer(s)</strong> have been assigned to this distribution.
                </p>
                
                <div style="background: #e8f4fc; padding: 15px; border-radius: 8px; margin: 15px 0; border-left: 4px solid var(--primary);">
                    <p style="margin: 0 0 10px 0; font-weight: bold;">
                        <i class="fas fa-sync-alt"></i> Data Sync Status
                    </p>
                    <ul style="text-align: left; margin: 10px 0 0 20px; padding: 0; font-size: 14px;">
                        <li>✅ Volunteers assigned to distribution</li>
                        <li>✅ Alerts created for volunteers</li>
                        <li><?php echo $successful_syncs > 0 ? '✅' : '⚠️'; ?> Families & items synced to volunteer logs: <?php echo $successful_syncs; ?>/<?php echo count($sync_results); ?> volunteers</li>
                        <li>✅ Distribution status updated to <strong>Assigned</strong></li>
                    </ul>
                </div>
            </div>
            
            <div style="display: flex; gap: 15px; justify-content: center; margin-top: 30px; flex-wrap: wrap;">
                <a href="assign_volunteer.php?distribution_id=<?php echo $distribution_id; ?>" class="btn btn-primary">
                    <i class="fas fa-users"></i> View Assigned Volunteers
                </a>
                <button onclick="closeSuccessModal()" class="btn btn-outline">
                    <i class="fas fa-times"></i> Close
                </button>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="container">
        <!-- Header Section -->
        <div class="header-section">
            <a href="distribution_main.php" class="back-btn">
                <i class="fas fa-arrow-left"></i>
                Back to Dashboard
            </a>
            <h1 class="page-title">
                <i class="fas fa-user-plus"></i> Assign Volunteers
            </h1>
        </div>

        <!-- Distribution Info -->
        <?php if ($distribution): ?>
        <div class="glass-card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 20px;">
                <div>
                    <h2 style="margin: 0 0 10px 0; font-size: 1.8rem;">
                        <i class="fas fa-box-open"></i> 
                        Distribution #DIST<?php echo str_pad($distribution_id, 7, '0', STR_PAD_LEFT); ?>
                    </h2>
                    <div style="display: flex; align-items: center; gap: 20px; flex-wrap: wrap;">
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <i class="fas fa-map-marker-alt"></i>
                            <span><?php echo htmlspecialchars($distribution['location'] ?? 'N/A'); ?></span>
                        </div>
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <i class="far fa-calendar"></i>
                            <span><strong><?php echo date('d/m/Y', strtotime($distribution['date'])); ?></strong></span>
                        </div>
                    </div>
                </div>
                <div style="background: rgba(255,255,255,0.2); padding: 8px 20px; border-radius: 50px; font-weight: 600;">
                    Status: <?php echo $distribution['status']; ?>
                </div>
            </div>
            
            <!-- Date Availability Warning -->
            <?php if (!empty($date_unavailable_volunteers)): ?>
            <div class="warning-message" style="background: rgba(255,255,255,0.2); border-left: 4px solid #ffc107; margin-top: 20px;">
                <i class="fas fa-exclamation-triangle"></i>
                <div>
                    <strong>Note:</strong> <?php echo count($date_unavailable_volunteers); ?> volunteers are already assigned to another distribution on <?php echo date('d/m/Y', strtotime($distribution['date'])); ?>.
                    <br>They are marked as <span style="background: #ff9f43; color: white; padding: 2px 8px; border-radius: 4px;">Date Conflict</span> and cannot be selected.
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Quick Stats -->
        <div class="stats-grid">
            <div class="stat-card volunteers">
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-value"><?php echo count($all_volunteers); ?></div>
                <div class="stat-label">Total Volunteers</div>
            </div>
            
            <div class="stat-card assigned">
                <div class="stat-icon">
                    <i class="fas fa-user-check"></i>
                </div>
                <div class="stat-value"><?php echo count($assigned_volunteers); ?></div>
                <div class="stat-label">Assigned</div>
            </div>
            
            <div class="stat-card available">
                <div class="stat-icon">
                    <i class="fas fa-user-check"></i>
                </div>
                <div class="stat-value"><?php echo count($active_available_volunteers); ?></div>
                <div class="stat-label">Available for <?php echo date('d/m/Y', strtotime($distribution['date'] ?? 'this date')); ?></div>
            </div>
            
            <div class="stat-card date-conflict">
                <div class="stat-icon">
                    <i class="fas fa-calendar-times"></i>
                </div>
                <div class="stat-value"><?php echo count($date_unavailable_volunteers); ?></div>
                <div class="stat-label">Date Conflict</div>
            </div>
            
            <div class="stat-card inactive">
                <div class="stat-icon">
                    <i class="fas fa-user-slash"></i>
                </div>
                <div class="stat-value"><?php echo count($inactive_volunteers); ?></div>
                <div class="stat-label">Inactive in System</div>
            </div>
            
            <div class="stat-card families">
                <div class="stat-icon">
                    <i class="fas fa-home"></i>
                </div>
                <div class="stat-value"><?php echo $families_count; ?></div>
                <div class="stat-label">Families Assigned</div>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="glass-card" style="border-left: 4px solid var(--danger);">
                <div style="display: flex; align-items: center; gap: 15px;">
                    <div style="color: var(--danger); font-size: 24px;">
                        <i class="fas fa-exclamation-circle"></i>
                    </div>
                    <div>
                        <h4 style="margin: 0 0 5px 0; color: var(--dark);">Error</h4>
                        <p style="margin: 0; color: var(--gray);"><?php echo $error; ?></p>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="glass-card" style="border-left: 4px solid var(--success);">
                <div style="display: flex; align-items: center; gap: 15px;">
                    <div style="color: var(--success); font-size: 24px;">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div>
                        <h4 style="margin: 0 0 5px 0; color: var(--dark);">Success</h4>
                        <p style="margin: 0; color: var(--gray);"><?php echo $success; ?></p>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($distribution): ?>
            <!-- Main Assignment Section -->
            <div class="glass-card">
                <div style="margin-bottom: 25px;">
                    <h2 style="margin: 0 0 10px 0; color: var(--dark);">
                        <i class="fas fa-user-plus"></i> Select Volunteers to Assign
                    </h2>
                    <p style="color: var(--gray); margin: 0;">
                        Choose volunteers from the list below to assign them to this distribution on <strong><?php echo date('d/m/Y', strtotime($distribution['date'])); ?></strong>.
                    </p>
                    
                    <!-- Legend -->
                    <div style="display: flex; gap: 15px; margin-top: 20px; flex-wrap: wrap;">
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <div style="width: 20px; height: 20px; background: #2ecc71; border-radius: 4px;"></div>
                            <span style="font-size: 0.9rem;">Available for this date</span>
                        </div>
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <div style="width: 20px; height: 20px; background: #ff9f43; border-radius: 4px;"></div>
                            <span style="font-size: 0.9rem;">Already assigned on <?php echo date('d/m/Y', strtotime($distribution['date'])); ?></span>
                        </div>
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <div style="width: 20px; height: 20px; background: #e74c3c; border-radius: 4px;"></div>
                            <span style="font-size: 0.9rem;">Inactive in system</span>
                        </div>
                    </div>
                </div>

                <?php if (empty($all_volunteers)): ?>
                    <div class="empty-state">
                        <div class="empty-icon">
                            <i class="fas fa-user-slash"></i>
                        </div>
                        <h3 style="color: var(--dark);">No Volunteers Available</h3>
                        <p style="color: var(--gray); margin-bottom: 30px;">
                            Unable to load volunteers from external system. Please check your connection.
                        </p>
                        <a href="assign_volunteer.php?distribution_id=<?php echo $distribution_id; ?>" class="btn btn-primary">
                            <i class="fas fa-sync-alt"></i> Retry
                        </a>
                    </div>
                <?php else: ?>
                    <!-- Filter Section -->
                    <div class="filter-section">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                            <h4 style="margin: 0; color: var(--dark);">
                                <i class="fas fa-filter"></i> Filter Volunteers
                            </h4>
                            <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                                <input type="checkbox" id="select-all-available-volunteers" style="width: 18px; height: 18px; cursor: pointer;">
                                <span style="font-weight: 600; color: var(--dark);">Select All Available</span>
                            </label>
                        </div>
                        
                        <div class="filter-grid">
                            <div class="filter-group">
                                <label class="filter-label">
                                    <i class="fas fa-hands-helping"></i> NGO
                                </label>
                                <select class="filter-select" id="ngo_filter">
                                    <option value="all">All NGOs</option>
                                    <?php 
                                    foreach ($unique_ngo_names as $ngo_name): 
                                        $filter_value = preg_replace('/[^a-z0-9]/i', '_', strtolower($ngo_name));
                                    ?>
                                        <option value="<?php echo htmlspecialchars($filter_value); ?>">
                                            <?php echo htmlspecialchars($ngo_name); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="filter-group">
                                <label class="filter-label">
                                    <i class="fas fa-calendar-check"></i> Date Availability
                                </label>
                                <select class="filter-select" id="availability_filter">
                                    <option value="all">All Volunteers</option>
                                    <option value="available">Available for <?php echo date('d/m/Y', strtotime($distribution['date'])); ?></option>
                                    <option value="date_conflict">Date Conflict</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                            </div>
                            
                            <div class="filter-group">
                                <label class="filter-label">
                                    <i class="fas fa-tools"></i> Skill Category
                                </label>
                                <select class="filter-select" id="skill_filter">
                                    <option value="all">All Skills</option>
                                    <?php 
                                    $skill_categories = [];
                                    foreach ($available_volunteers as $volunteer) {
                                        $skill = $volunteer['skill_category'] ?? 'Volunteer';
                                        if (!in_array($skill, $skill_categories)) {
                                            $skill_categories[] = $skill;
                                        }
                                    }
                                    sort($skill_categories);
                                    foreach ($skill_categories as $skill): 
                                        $filter_value = preg_replace('/[^a-z0-9]/i', '_', strtolower($skill));
                                    ?>
                                        <option value="<?php echo htmlspecialchars($filter_value); ?>">
                                            <?php echo htmlspecialchars($skill); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <!-- Filter Results Counter -->
                        <div id="filter-results" style="margin-top: 15px; padding: 10px 15px; background: #f8f9fa; border-radius: 8px; display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <span style="font-weight: 600; color: var(--dark);">
                                    <i class="fas fa-users"></i>
                                    <span id="visible-count"><?php echo count($active_available_volunteers); ?></span> of <?php echo count($available_volunteers); ?> volunteers shown
                                    (<span id="available-count"><?php echo count($active_available_volunteers); ?></span> available for <?php echo date('d/m/Y', strtotime($distribution['date'])); ?>)
                                </span>
                            </div>
                            <button type="button" onclick="clearFilters()" style="background: transparent; border: 1px solid var(--gray); color: var(--gray); padding: 5px 15px; border-radius: 5px; cursor: pointer; font-size: 0.9rem;">
                                <i class="fas fa-times"></i> Clear Filters
                            </button>
                        </div>
                    </div>

                    <form method="POST" id="assign-volunteers-form">
                        <input type="hidden" name="assign_volunteers" value="1">
                        
                        <!-- Volunteers Grid -->
                        <div class="volunteers-container" id="volunteers-container">
                            <?php if (empty($available_volunteers)): ?>
                                <div class="empty-state">
                                    <div class="empty-icon">
                                        <i class="fas fa-user-check"></i>
                                    </div>
                                    <h3 style="color: var(--dark);">No Volunteers Available</h3>
                                    <p style="color: var(--gray);">
                                        All volunteers are already assigned to this distribution or unavailable for this date.
                                    </p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($available_volunteers as $volunteer): 
                                    $volunteer_initial = strtoupper(substr($volunteer['name'], 0, 1));
                                    
                                    // Determine volunteer status
                                    $date_availability = $volunteer['date_availability'] ?? 'pending';
                                    $is_available = ($date_availability === 'available');
                                    $is_date_conflict = ($date_availability === 'date_conflict');
                                    $is_inactive = ($date_availability === 'inactive');
                                    
                                    // Check if volunteer is active in system (from API status)
                                    $api_status = strtolower($volunteer['original_status'] ?? 'active');
                                    $is_system_active = !($api_status === 'inactive' || $api_status === 'suspended');
                                    
                                    // Volunteer is selectable ONLY if:
                                    // 1. Available for this date (no date conflict)
                                    // 2. Active in system
                                    $is_selectable = $is_available && $is_system_active;
                                    
                                    // Create filter values
                                    $ngo_filter_value = preg_replace('/[^a-z0-9]/i', '_', strtolower($volunteer['ngo_affiliation'] ?? 'various'));
                                    $skill_filter_value = preg_replace('/[^a-z0-9]/i', '_', strtolower($volunteer['skill_category'] ?? 'volunteer'));
                                    $default_role = $volunteer['skill_category'] ?? 'Volunteer';
                                    
                                    // Availability reason
                                    $availability_reason = $volunteer['availability_reason'] ?? 'Unknown';
                                    
                                    // Tooltip text for disabled checkboxes
                                    $tooltip_text = '';
                                    if (!$is_selectable) {
                                        if (!$is_system_active) {
                                            $tooltip_text = "Volunteer is " . ($volunteer['original_status'] ?? 'inactive') . " in the system";
                                        } elseif ($is_date_conflict) {
                                            $tooltip_text = htmlspecialchars($availability_reason);
                                        } elseif ($is_inactive) {
                                            $tooltip_text = "Volunteer is inactive in system";
                                        }
                                    }
                                ?>
                                <div class="volunteer-card 
                                    <?php echo $is_available ? 'date-available' : ''; ?>
                                    <?php echo $is_date_conflict ? 'date-conflict' : ''; ?>
                                    <?php echo $is_inactive ? 'inactive' : ''; ?>"
                                     data-ngo="<?php echo $ngo_filter_value; ?>"
                                     data-availability="<?php echo $date_availability; ?>"
                                     data-skill="<?php echo $skill_filter_value; ?>"
                                     data-selectable="<?php echo $is_selectable ? 'true' : 'false'; ?>">
                                    
                                    <!-- Availability Badge -->
                                    <?php if ($is_available && $is_system_active): ?>
                                        <div class="date-availability-badge date-available">
                                            AVAILABLE
                                        </div>
                                    <?php elseif ($is_date_conflict): ?>
                                        <div class="date-availability-badge date-conflict">
                                            DATE CONFLICT
                                        </div>
                                    <?php elseif (!$is_system_active): ?>
                                        <div class="date-availability-badge date-unavailable">
                                            <?php echo strtoupper($volunteer['original_status'] ?? 'INACTIVE'); ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <!-- Checkbox - Only enabled if selectable -->
                                    <input type="checkbox" 
                                           name="selected_volunteers[]" 
                                           value="<?php echo $volunteer['volunteer_id']; ?>" 
                                           class="volunteer-checkbox"
                                           data-volunteer-id="<?php echo $volunteer['volunteer_id']; ?>"
                                           <?php echo $is_selectable ? '' : 'disabled'; ?>
                                           <?php if (!$is_selectable && !empty($tooltip_text)): ?>
                                           title="<?php echo $tooltip_text; ?>"
                                           <?php endif; ?>>
                                    
                                    <div class="volunteer-header">
                                        <div class="volunteer-avatar 
                                            <?php echo $is_selectable ? 'date-available' : ''; ?>
                                            <?php echo $is_date_conflict ? 'date-conflict' : ''; ?>
                                            <?php echo !$is_system_active ? 'inactive' : ''; ?>">
                                            <?php echo $volunteer_initial; ?>
                                        </div>
                                        <div class="volunteer-info">
                                            <h3 class="volunteer-name"><?php echo htmlspecialchars($volunteer['name']); ?></h3>
                                            <span class="volunteer-id">ID: <?php echo $volunteer['volunteer_id']; ?></span>
                                        </div>
                                    </div>
                                    
                                    <div class="volunteer-details">
                                        <div class="detail-item">
                                            <i class="fas fa-phone"></i>
                                            <span><?php echo htmlspecialchars($volunteer['phone'] ?? 'N/A'); ?></span>
                                        </div>
                                        <?php if (!empty($volunteer['email'])): ?>
                                        <div class="detail-item">
                                            <i class="fas fa-envelope"></i>
                                            <span><?php echo htmlspecialchars($volunteer['email']); ?></span>
                                        </div>
                                        <?php endif; ?>
                                        <div class="detail-item">
                                            <i class="fas fa-hands-helping"></i>
                                            <span><?php echo htmlspecialchars($volunteer['ngo_affiliation'] ?? 'Various'); ?></span>
                                        </div>
                                        
                                        <!-- API Status -->
                                        <div class="detail-item">
                                            <i class="fas fa-info-circle"></i>
                                            <span>
                                                System Status: 
                                                <span style="font-weight: 600; color: <?php 
                                                    $status_color = '#2ecc71'; // default green
                                                    $api_status = strtolower($volunteer['original_status'] ?? '');
                                                    if ($api_status === 'inactive' || $api_status === 'suspended') {
                                                        $status_color = '#e74c3c'; // red
                                                    } elseif ($api_status === 'new' || $api_status === 'pending') {
                                                        $status_color = '#f39c12'; // orange
                                                    }
                                                    echo $status_color;
                                                ?>;">
                                                    <?php echo ucfirst($volunteer['original_status'] ?? 'Unknown'); ?>
                                                </span>
                                            </span>
                                        </div>
                                        
                                        <!-- Availability Info -->
                                        <?php if (!$is_selectable): ?>
                                        <div class="detail-item">
                                            <i class="fas fa-exclamation-triangle"></i>
                                            <span class="availability-info">
                                                <?php echo htmlspecialchars(substr($tooltip_text, 0, 50)); ?>...
                                                <?php if ($is_date_conflict && !empty($volunteer['conflicting_distributions'])): ?>
                                                <div class="availability-tooltip">
                                                    <strong>Already assigned to:</strong><br>
                                                    <?php foreach ($volunteer['conflicting_distributions'] as $conflict): ?>
                                                    • Distribution #<?php echo $conflict['distribution_id']; ?> (<?php echo $conflict['volunteer_status'] ?? 'Assigned'; ?>)<br>
                                                    <?php endforeach; ?>
                                                </div>
                                                <?php endif; ?>
                                            </span>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div style="margin-top: auto;">
                                        <span class="skill-badge">
                                            <i class="fas fa-tools"></i> <?php echo htmlspecialchars($volunteer['skill_category'] ?? 'Volunteer'); ?>
                                        </span>
                                    </div>
                                    
                                    <!-- Role Selection - Only shown if selectable -->
                                    <?php if ($is_selectable): ?>
                                    <div class="role-selection" id="role-selection-<?php echo $volunteer['volunteer_id']; ?>" style="display: none; margin-top: 20px;">
                                        <label style="font-weight: 600; color: var(--dark); margin-bottom: 10px; display: block;">
                                            <i class="fas fa-user-tag"></i> Assign Role
                                        </label>
                                        <input type="text" 
                                               name="roles[<?php echo $volunteer['volunteer_id']; ?>]" 
                                               value="<?php echo htmlspecialchars($default_role); ?>"
                                               placeholder="Enter role for this volunteer"
                                               style="width: 100%; padding: 10px 15px; border: 2px solid var(--light-gray); border-radius: 8px; font-size: 14px;">
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        
                        <!-- No Results Message -->
                        <div id="no-results-message" style="display: none; text-align: center; padding: 40px 20px; background: white; border-radius: var(--border-radius);">
                            <div style="font-size: 4rem; color: var(--light-gray); margin-bottom: 20px;">
                                <i class="fas fa-search"></i>
                            </div>
                            <h3 style="color: var(--dark);">No Volunteers Found</h3>
                            <p style="color: var(--gray); margin-bottom: 20px;">
                                No volunteers match your filter criteria. Try adjusting your filters.
                            </p>
                            <button type="button" onclick="clearFilters()" class="btn btn-primary">
                                <i class="fas fa-times"></i> Clear All Filters
                            </button>
                        </div>
                        
                        <!-- Action Buttons -->
                        <div class="action-buttons">
                            <button type="submit" class="btn btn-success" id="assign-button" disabled>
                                <i class="fas fa-user-check"></i> 
                                Assign 
                                <span id="selected-count">0</span> Volunteers
                            </button>
                            <a href="distribution_main.php" class="btn btn-outline">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                        </div>
                    </form>
                <?php endif; ?>
            </div>

            <!-- Assigned Volunteers Section -->
            <?php if (!empty($assigned_volunteers)): ?>
            <div class="glass-card">
                <div style="margin-bottom: 25px;">
                    <h2 style="margin: 0 0 10px 0; color: var(--dark);">
                        <i class="fas fa-user-check"></i> Currently Assigned Volunteers
                        <span style="background: var(--primary); color: white; padding: 5px 15px; border-radius: 20px; font-size: 0.9rem; margin-left: 10px;">
                            <?php echo count($assigned_volunteers); ?>
                        </span>
                    </h2>
                    <p style="color: var(--gray); margin: 0;">
                        These volunteers are already assigned to this distribution on <?php echo date('d/m/Y', strtotime($distribution['date'])); ?>
                    </p>
                </div>
                
                <div style="overflow-x: auto;">
                    <table class="data-table" style="width: 100%; border-collapse: separate; border-spacing: 0;">
                        <thead>
                            <tr style="background: linear-gradient(135deg, var(--primary), var(--secondary)); color: white;">
                                <th style="padding: 15px; text-align: left;">Volunteer</th>
                                <th style="padding: 15px; text-align: left;">Contact</th>
                                <th style="padding: 15px; text-align: left;">NGO</th>
                                <th style="padding: 15px; text-align: left;">Assigned Role</th>
                                <th style="padding: 15px; text-align: left;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($assigned_volunteers as $assignment): ?>
                            <tr style="border-bottom: 1px solid var(--light-gray);">
                                <td style="padding: 15px;">
                                    <div style="display: flex; align-items: center; gap: 12px;">
                                        <div style="width: 40px; height: 40px; background: linear-gradient(135deg, var(--primary), var(--secondary)); color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold;">
                                            <?php echo strtoupper(substr($assignment['name'], 0, 1)); ?>
                                        </div>
                                        <div>
                                            <div style="font-weight: 600; color: var(--dark);"><?php echo htmlspecialchars($assignment['name']); ?></div>
                                            <div style="font-size: 0.85rem; color: var(--gray);">ID: <?php echo $assignment['volunteer_id']; ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td style="padding: 15px;">
                                    <div style="font-size: 0.9rem;">
                                        <div><?php echo htmlspecialchars($assignment['phone']); ?></div>
                                        <?php if (!empty($assignment['email'])): ?>
                                        <div style="color: var(--gray); font-size: 0.85rem;"><?php echo htmlspecialchars($assignment['email']); ?></div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td style="padding: 15px;">
                                    <span style="background: var(--light-gray); padding: 5px 12px; border-radius: 20px; font-size: 0.85rem;">
                                        <?php echo htmlspecialchars($assignment['ngo_affiliation'] ?? 'Various'); ?>
                                    </span>
                                </td>
                                <td style="padding: 15px;">
                                    <span style="background: var(--light); color: var(--primary); padding: 5px 12px; border-radius: 20px; font-size: 0.85rem; font-weight: 600;">
                                        <?php echo $assignment['assigned_role']; ?>
                                    </span>
                                </td>
                                <td style="padding: 15px;">
                                    <button type="button" class="btn btn-danger" onclick="removeVolunteer(<?php echo $assignment['volunteer_id']; ?>)" style="padding: 8px 16px; font-size: 0.9rem;">
                                        <i class="fas fa-user-times"></i> Remove
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

            <!-- Assigned Families Section -->
            <?php if (!empty($victims_data)): ?>
            <div class="glass-card">
                <div style="margin-bottom: 25px;">
                    <h2 style="margin: 0 0 10px 0; color: var(--dark);">
                        <i class="fas fa-home"></i> Assigned Families
                        <span style="background: #2ecc71; color: white; padding: 5px 15px; border-radius: 20px; font-size: 0.9rem; margin-left: 10px;">
                            <?php echo $families_count; ?> families
                        </span>
                    </h2>
                </div>
                
                <div style="overflow-x: auto;">
                    <table class="data-table" style="width: 100%; border-collapse: separate; border-spacing: 0;">
                        <thead>
                            <tr style="background: linear-gradient(135deg, #2ecc71, #27ae60); color: white;">
                                <th style="padding: 15px; text-align: left;">Family Head</th>
                                <th style="padding: 15px; text-align: left;">Contact</th>
                                <th style="padding: 15px; text-align: left;">Address</th>
                                <th style="padding: 15px; text-align: left;">Family Members</th>
                                <th style="padding: 15px; text-align: left;">Special Needs</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($victims_data as $victim): ?>
                            <tr style="border-bottom: 1px solid var(--light-gray);">
                                <td style="padding: 15px;">
                                    <div style="display: flex; align-items: center; gap: 12px;">
                                        <div style="width: 40px; height: 40px; background: #2ecc71; color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold;">
                                            <?php echo strtoupper(substr($victim['full_name'], 0, 1)); ?>
                                        </div>
                                        <div>
                                            <div style="font-weight: 600; color: var(--dark);"><?php echo htmlspecialchars($victim['full_name']); ?></div>
                                            <div style="font-size: 0.85rem; color: var(--gray);">IC: <?php echo htmlspecialchars($victim['ic_number']); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td style="padding: 15px;">
                                    <div style="font-size: 0.9rem;">
                                        <?php if (!empty($victim['phone'])): ?>
                                        <div><?php echo htmlspecialchars($victim['phone']); ?></div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td style="padding: 15px; max-width: 300px;">
                                    <div style="font-size: 0.9rem;"><?php echo htmlspecialchars($victim['address']); ?></div>
                                </td>
                                <td style="padding: 15px;">
                                    <span style="background: #e3f2fd; color: #1976d2; padding: 5px 12px; border-radius: 20px; font-weight: 600;">
                                        <?php echo htmlspecialchars($victim['family_members']); ?> members
                                    </span>
                                </td>
                                <td style="padding: 15px;">
                                    <div style="display: flex; gap: 5px; flex-wrap: wrap;">
                                        <?php if ($victim['has_elderly'] == true || $victim['has_elderly'] == 't' || $victim['has_elderly'] == '1'): ?>
                                            <span style="background: #ffeaa7; color: #d35400; padding: 3px 8px; border-radius: 12px; font-size: 0.75rem;">Elderly</span>
                                        <?php endif; ?>
                                        <?php if ($victim['has_baby'] == true || $victim['has_baby'] == 't' || $victim['has_baby'] == '1'): ?>
                                            <span style="background: #fab1a0; color: #c23616; padding: 3px 8px; border-radius: 12px; font-size: 0.75rem;">Baby</span>
                                        <?php endif; ?>
                                        <?php if ($victim['has_disabled'] == true || $victim['has_disabled'] == 't' || $victim['has_disabled'] == '1'): ?>
                                            <span style="background: #a29bfe; color: #5f27cd; padding: 3px 8px; border-radius: 12px; font-size: 0.75rem;">Disabled</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <script>
    // Show success modal if assignment was successful
    <?php if ($show_success_modal): ?>
    window.addEventListener('load', function() {
        document.getElementById('successModal').style.display = 'flex';
    });
    <?php endif; ?>
    
    function closeSuccessModal() {
        document.getElementById('successModal').style.display = 'none';
        const url = new URL(window.location);
        url.searchParams.delete('success');
        window.history.replaceState({}, '', url);
    }
    
    function removeVolunteer(volunteerId) {
        if (confirm('Are you sure you want to remove this volunteer from the assignment?\n\nThis will:\n• Remove volunteer from distribution\n• Remove their distribution log entries\n• Remove their assigned items\n• Update distribution status if no volunteers remain')) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'assign_volunteer.php?distribution_id=<?php echo $distribution_id; ?>';
            
            const removeInput = document.createElement('input');
            removeInput.type = 'hidden';
            removeInput.name = 'remove_volunteer';
            removeInput.value = '1';
            
            const volunteerInput = document.createElement('input');
            volunteerInput.type = 'hidden';
            volunteerInput.name = 'volunteer_id';
            volunteerInput.value = volunteerId;
            
            form.appendChild(removeInput);
            form.appendChild(volunteerInput);
            document.body.appendChild(form);
            form.submit();
        }
    }
    
    // Volunteer selection and filtering
    const volunteerCards = document.querySelectorAll('.volunteer-card');
    const ngoFilter = document.getElementById('ngo_filter');
    const availabilityFilter = document.getElementById('availability_filter');
    const skillFilter = document.getElementById('skill_filter');
    const assignButton = document.getElementById('assign-button');
    const selectAllCheckbox = document.getElementById('select-all-available-volunteers');
    const selectedCountSpan = document.getElementById('selected-count');
    const visibleCountSpan = document.getElementById('visible-count');
    const availableCountSpan = document.getElementById('available-count');
    const noResultsMessage = document.getElementById('no-results-message');
    const volunteersContainer = document.getElementById('volunteers-container');
    
    function updateSelectedCount() {
        // Get all enabled checkboxes that are checked
        const selected = document.querySelectorAll('.volunteer-checkbox:not(:disabled):checked');
        const selectedCount = selected.length;
        selectedCountSpan.textContent = selectedCount;
        assignButton.disabled = selectedCount === 0;
        
        // Update button text
        if (selectedCount === 1) {
            assignButton.innerHTML = '<i class="fas fa-user-check"></i> Assign 1 Volunteer';
        } else {
            assignButton.innerHTML = `<i class="fas fa-user-check"></i> Assign ${selectedCount} Volunteers`;
        }
    }
    
    function updateRoleSelect(checkbox) {
        if (checkbox.disabled) return;
        
        const card = checkbox.closest('.volunteer-card');
        const volunteerId = checkbox.getAttribute('data-volunteer-id');
        const roleSelection = document.getElementById('role-selection-' + volunteerId);
        
        // Toggle selected class on card
        card.classList.toggle('selected', checkbox.checked);
        
        // Show/hide role selection
        if (roleSelection) {
            roleSelection.style.display = checkbox.checked ? 'block' : 'none';
        }
        
        updateSelectedCount();
    }
    
    function filterVolunteers() {
        const ngoValue = ngoFilter.value;
        const availabilityValue = availabilityFilter.value;
        const skillValue = skillFilter.value;
        
        let visibleCount = 0;
        let selectableVisibleCount = 0;
        
        volunteerCards.forEach(card => {
            const ngo = card.getAttribute('data-ngo');
            const availability = card.getAttribute('data-availability');
            const skill = card.getAttribute('data-skill');
            const isSelectable = card.getAttribute('data-selectable') === 'true';
            let show = true;
            
            if (ngoValue !== 'all' && ngo !== ngoValue) {
                show = false;
            }
            
            if (availabilityValue !== 'all' && availability !== availabilityValue) {
                show = false;
            }
            
            if (skillValue !== 'all' && skill !== skillValue) {
                show = false;
            }
            
            if (show) {
                card.style.display = 'block';
                visibleCount++;
                if (isSelectable) selectableVisibleCount++;
            } else {
                card.style.display = 'none';
            }
        });
        
        // Update visible counts
        visibleCountSpan.textContent = visibleCount;
        availableCountSpan.textContent = selectableVisibleCount;
        
        // Show/hide no results message
        if (visibleCount === 0) {
            volunteersContainer.style.display = 'none';
            noResultsMessage.style.display = 'block';
        } else {
            volunteersContainer.style.display = 'grid';
            noResultsMessage.style.display = 'none';
        }
        
        // Reset select all checkbox
        if (selectAllCheckbox) {
            selectAllCheckbox.checked = false;
        }
        
        updateSelectedCount();
    }
    
    function clearFilters() {
        if (ngoFilter) ngoFilter.value = 'all';
        if (availabilityFilter) availabilityFilter.value = 'all';
        if (skillFilter) skillFilter.value = 'all';
        
        volunteerCards.forEach(card => {
            card.style.display = 'block';
        });
        
        // Count selectable volunteers
        let selectableVisibleCount = 0;
        let totalVisibleCount = 0;
        volunteerCards.forEach(card => {
            totalVisibleCount++;
            if (card.getAttribute('data-selectable') === 'true') {
                selectableVisibleCount++;
            }
        });
        
        visibleCountSpan.textContent = totalVisibleCount;
        availableCountSpan.textContent = selectableVisibleCount;
        
        volunteersContainer.style.display = 'grid';
        noResultsMessage.style.display = 'none';
        
        if (selectAllCheckbox) {
            selectAllCheckbox.checked = false;
        }
        
        updateSelectedCount();
    }
    
    function selectAllAvailableVolunteers() {
        const isChecked = selectAllCheckbox.checked;
        
        volunteerCards.forEach(card => {
            if (card.style.display !== 'none') {
                const checkbox = card.querySelector('.volunteer-checkbox:not(:disabled)');
                if (checkbox && card.getAttribute('data-selectable') === 'true') {
                    checkbox.checked = isChecked;
                    updateRoleSelect(checkbox);
                }
            }
        });
        
        updateSelectedCount();
    }
    
    // Event listeners
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', selectAllAvailableVolunteers);
    }
    
    if (ngoFilter) ngoFilter.addEventListener('change', filterVolunteers);
    if (availabilityFilter) availabilityFilter.addEventListener('change', filterVolunteers);
    if (skillFilter) skillFilter.addEventListener('change', filterVolunteers);
    
    // Add event listeners to all checkboxes
    document.querySelectorAll('.volunteer-checkbox').forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            updateRoleSelect(this);
        });
    });
    
    // Form submission handler
    const form = document.getElementById('assign-volunteers-form');
    if (form) {
        form.addEventListener('submit', function(e) {
            const selected = document.querySelectorAll('.volunteer-checkbox:not(:disabled):checked').length;
            if (selected === 0) {
                e.preventDefault();
                alert('Please select at least one AVAILABLE volunteer. Volunteers with date conflicts or inactive status cannot be assigned.');
                return;
            }
            
            let message = `Assign ${selected} volunteer(s) to this distribution on <?php echo date('d/m/Y', strtotime($distribution['date'] ?? 'selected date')); ?>?\n\n✅ Volunteers will be assigned with their selected roles\n🔔 Alerts will be created for volunteers\n📊 Distribution status will be updated to Assigned\n📋 Family data will be synced to volunteer logs`;
            
            if (!confirm(message)) {
                e.preventDefault();
                return;
            }
            
            assignButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Assigning...';
            assignButton.disabled = true;
            assignButton.style.opacity = '0.7';
        });
    }
    
    // Initialize
    updateSelectedCount();
    filterVolunteers();
    
    // Clear filters on page load to ensure consistency
    window.addEventListener('load', function() {
        clearFilters();
        updateSelectedCount();
    });
    </script>
</body>
</html>