<?php
// ========================================
// MANAGE VOLUNTEERS - COMPLETE VERSION WITH ALL APIS
// ========================================

session_start();
require_once 'config.php';

// Check if admin is logged in
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: login_gateway.php");
    exit;
}

$database = new Database();
$db = $database->getConnection();

if (!$db || !is_object($db)) {
    die("Database connection failed. Please check your configuration.");
}

// API URLs
$VOLUNTEER_API_URL = 'http://10.147.17.30:8000/api_volunteer.php';
$NGO_API_URL = 'http://10.147.17.30:8000/api_ngo.php';
$DISASTER_API_URL = 'http://10.147.17.116:8000/disaster.php';
$NEEDS_API_URL = 'http://10.147.17.116:8000/needs.php';
$VICTIM_API_URL = 'http://10.147.17.116:8000/victim.php';

// Initialize variables
$all_volunteers = [];
$filtered_volunteers = [];
$active_distributions = [];
$disasters = [];
$victims = [];
$needs = [];
$ngos = ['All'];
$error = '';
$success = '';

// Filter parameters
$filter_ngo = $_GET['ngo'] ?? 'All';
$filter_status = $_GET['status'] ?? 'All';
$filter_skill = $_GET['skill'] ?? 'All';
$search_query = $_GET['search'] ?? '';

/* ========================================
   API FETCH FUNCTION
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
        
        $response = @file_get_contents($full_url, false, $context);
        
        if ($response === FALSE) {
            return ['success' => false, 'error' => 'API server not responding'];
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

/* ========================================
   FETCH DISASTERS FROM EXTERNAL API
======================================== */
try {
    $disaster_api_result = fetchFromAPI($DISASTER_API_URL);
    
    if ($disaster_api_result['success']) {
        $api_disasters = $disaster_api_result['data'];
        
        foreach ($api_disasters as $disaster) {
            if (!empty($disaster['disaster_id']) && !empty($disaster['disaster_name'])) {
                $disasters[$disaster['disaster_id']] = [
                    'disaster_id' => $disaster['disaster_id'],
                    'disaster_name' => $disaster['disaster_name'],
                    'description' => $disaster['description'] ?? '',
                    'district' => $disaster['district'] ?? '',
                    'severity' => $disaster['severity'] ?? 'Medium',
                    'status' => $disaster['status'] ?? 'Active',
                    'start_date' => $disaster['start_date'] ?? '',
                    'end_date' => $disaster['end_date'] ?? '',
                    'affected_people' => $disaster['affected_people'] ?? '0',
                    'alert_message' => $disaster['alert_message'] ?? ''
                ];
            }
        }
        
        if (empty($disasters)) {
            error_log("No disasters found in API response.");
        }
    } else {
        error_log("Failed to fetch disasters: " . ($disaster_api_result['error'] ?? 'Unknown error'));
    }
} catch (Exception $e) {
    error_log("Error fetching disasters: " . $e->getMessage());
}

/* ========================================
   FETCH VICTIMS FROM EXTERNAL API
======================================== */
try {
    $victim_api_result = fetchFromAPI($VICTIM_API_URL);
    
    if ($victim_api_result['success']) {
        $api_victims = $victim_api_result['data'];
        
        foreach ($api_victims as $victim) {
            if (!empty($victim['victim_id'])) {
                $victims[$victim['victim_id']] = [
                    'victim_id' => $victim['victim_id'],
                    'full_name' => $victim['full_name'] ?? 'Unknown',
                    'ic_number' => $victim['ic_number'] ?? '',
                    'phone' => $victim['phone'] ?? '',
                    'email' => $victim['email'] ?? '',
                    'address' => $victim['address'] ?? '',
                    'city' => $victim['city'] ?? '',
                    'district' => $victim['district'] ?? '',
                    'family_members' => $victim['family_members'] ?? '1',
                    'has_baby' => ($victim['has_baby'] ?? 'f') == 't',
                    'has_elderly' => ($victim['has_elderly'] ?? 'f') == 't',
                    'has_disabled' => ($victim['has_disabled'] ?? 'f') == 't',
                    'disaster_id' => $victim['disaster_id'] ?? '0',
                    'special_request' => $victim['special_request'] ?? '',
                    'selected_shelter' => $victim['selected_shelter'] ?? ''
                ];
            }
        }
        
        if (empty($victims)) {
            error_log("No victims found in API response.");
        }
    } else {
        error_log("Failed to fetch victims: " . ($victim_api_result['error'] ?? 'Unknown error'));
    }
} catch (Exception $e) {
    error_log("Error fetching victims: " . $e->getMessage());
}

/* ========================================
   FETCH NEEDS FROM EXTERNAL API
======================================== */
try {
    $needs_api_result = fetchFromAPI($NEEDS_API_URL);
    
    if ($needs_api_result['success']) {
        $api_needs = $needs_api_result['data'];
        
        // If API returns summary and data separately
        if (isset($api_needs['data']) && is_array($api_needs['data'])) {
            $needs_data = $api_needs['data'];
        } else {
            $needs_data = $api_needs;
        }
        
        foreach ($needs_data as $need) {
            if (!empty($need['need_id'])) {
                $needs[$need['need_id']] = [
                    'need_id' => $need['need_id'],
                    'victim_id' => $need['victim_id'] ?? 0,
                    'disaster_id' => $need['disaster_id'] ?? 0,
                    'priority' => $need['priority'] ?? 'Medium',
                    'status' => $need['status'] ?? 'Pending',
                    'quantity_needed' => $need['quantity_needed'] ?? '0',
                    'temp_resource_name' => $need['temp_resource_name'] ?? '',
                    'special_needs_quantity' => $need['special_needs_quantity'] ?? '0',
                    'normal_needs_quantity' => $need['normal_needs_quantity'] ?? '0',
                    'selected_shelter' => $need['selected_shelter'] ?? '',
                    'has_baby' => $need['has_baby'] ?? false,
                    'has_elderly' => $need['has_elderly'] ?? false,
                    'has_disabled' => $need['has_disabled'] ?? false
                ];
            }
        }
        
        if (empty($needs)) {
            error_log("No needs found in API response.");
        }
    } else {
        error_log("Failed to fetch needs: " . ($needs_api_result['error'] ?? 'Unknown error'));
    }
} catch (Exception $e) {
    error_log("Error fetching needs: " . $e->getMessage());
}

/* ========================================
   FETCH VOLUNTEERS FROM EXTERNAL API
======================================== */
try {
    $volunteer_api_result = fetchFromAPI($VOLUNTEER_API_URL);
    
    if ($volunteer_api_result['success']) {
        $api_volunteers = $volunteer_api_result['data'];
        
        // The API returns a JSON array directly
        foreach ($api_volunteers as $api_vol) {
            $external_volunteer_id = $api_vol['VolunteerID'] ?? 0;
            $name = $api_vol['FullName'] ?? 'Unknown Volunteer';
            $skill_category = $api_vol['SkillCategory'] ?? 'Volunteer';
            $ngo_id = $api_vol['AssignedNGO'] ?? 0;
            
            if ($external_volunteer_id && $name != 'Unknown Volunteer') {
                // Fetch NGO name using the NGO ID
                $ngo_name = 'Various';
                if ($ngo_id > 0) {
                    try {
                        $ngo_api_result = fetchFromAPI($NGO_API_URL, ['id' => $ngo_id]);
                        if ($ngo_api_result['success'] && isset($ngo_api_result['data'][0]['NGOName'])) {
                            $ngo_name = $ngo_api_result['data'][0]['NGOName'];
                        }
                    } catch (Exception $e) {
                        // Silently continue with default NGO name
                    }
                }
                
                $volunteer = [
                    'volunteer_id' => $external_volunteer_id,
                    'name' => $name,
                    'phone' => $api_vol['Phone'] ?? '',
                    'email' => $api_vol['Email'] ?? '',
                    'role' => $skill_category,
                    'skill_category' => $skill_category,
                    'availability_status' => ucfirst($api_vol['Status'] ?? 'Available'),
                    'availability' => 'Available', // Default
                    'ngo_affiliation' => $ngo_name,
                    'address' => $api_vol['Address'] ?? '',
                    'registered_date' => date('Y-m-d'), // Default
                    'last_active' => null
                ];
                
                $all_volunteers[] = $volunteer;
                
                // Add NGO to list if not already there
                if (!empty($ngo_name) && !in_array($ngo_name, $ngos)) {
                    $ngos[] = $ngo_name;
                }
            }
        }
        
        if (empty($all_volunteers)) {
            $error = "No volunteers found in API response.";
        }
        
    } else {
        $error = "Failed to fetch volunteers from API: " . ($volunteer_api_result['error'] ?? 'Unknown error');
    }
} catch (Exception $e) {
    $error = "Error fetching volunteers from API: " . $e->getMessage();
}

/* ========================================
   DEBUG: CHECK DATABASE TABLES
======================================== */
$debug_tables = [];
try {
    // Check what tables exist
    $tables_result = $db->query("SHOW TABLES");
    if ($tables_result) {
        while ($row = $tables_result->fetch_array()) {
            $debug_tables[] = $row[0];
        }
    }
} catch (Exception $e) {
    $debug_tables[] = "Error checking tables: " . $e->getMessage();
}

/* ========================================
   FETCH ACTIVE DISTRIBUTIONS - FIXED VERSION
======================================== */
try {
    // First, check if distribution table exists
    if (in_array('distribution', $debug_tables)) {
        // Simple query without JOIN to disaster table
        $distributions_query = "
            SELECT d.*,
                   (SELECT COUNT(*) FROM distribution_volunteer dv WHERE dv.distribution_id = d.distribution_id AND dv.status != 'Cancelled') as assigned_volunteers
            FROM distribution d
            WHERE d.status IN ('Planning', 'Assigned', 'In Progress', 'In Transit', 'Active')
            ORDER BY d.date ASC
            LIMIT 10
        ";
        
        $stmt = $db->prepare($distributions_query);
        if ($stmt) {
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                // Get disaster details from API
                $disaster_id = $row['disaster_id'] ?? 0;
                if ($disaster_id && isset($disasters[$disaster_id])) {
                    $row['disaster_name'] = $disasters[$disaster_id]['disaster_name'];
                    $row['disaster_location'] = $disasters[$disaster_id]['district'] ?? 'Unknown';
                } else {
                    $row['disaster_name'] = 'Unknown Disaster';
                    $row['disaster_location'] = $row['location'] ?? 'Unknown';
                }
                $active_distributions[] = $row;
            }
            $stmt->close();
        }
    }
    
    // If no distributions in database, create mock distributions from active disasters
    if (empty($active_distributions) && !empty($disasters)) {
        foreach ($disasters as $disaster) {
            // Only include active or ongoing disasters
            if (in_array(strtolower($disaster['status']), ['active', 'ongoing', 'in progress'])) {
                $active_distributions[] = [
                    'distribution_id' => 'API' . $disaster['disaster_id'],
                    'disaster_id' => $disaster['disaster_id'],
                    'disaster_name' => $disaster['disaster_name'],
                    'disaster_location' => $disaster['district'],
                    'location' => $disaster['district'],
                    'date' => date('Y-m-d', strtotime('+3 days')),
                    'status' => 'Planning',
                    'assigned_volunteers' => 0
                ];
            }
        }
    }
} catch (Exception $e) {
    // Log error but continue
    error_log("Error fetching active distributions: " . $e->getMessage());
}

/* ========================================
   GET VOLUNTEER ASSIGNMENTS - FIXED QUERY
======================================== */
$volunteer_stats = [];
$assigned_volunteers_by_distribution = [];
$volunteer_assignments = []; // Store assignments for modal

// Initialize stats for all volunteers first
foreach ($all_volunteers as $volunteer) {
    $volunteer_id = $volunteer['volunteer_id'];
    $volunteer_stats[$volunteer_id] = [
        'total_assignments' => 0,
        'active_assignments' => 0,
        'completed_assignments' => 0,
        'families_helped' => 0,
        'items_distributed' => 0,
        'total_quantity' => 0,
        'assignments' => []
    ];
    $volunteer_assignments[$volunteer_id] = []; // Initialize assignments array
}

// DEBUG: Check distribution_volunteer table structure
$dv_structure = [];
try {
    if (in_array('distribution_volunteer', $debug_tables)) {
        $structure_query = "DESCRIBE distribution_volunteer";
        $stmt = $db->prepare($structure_query);
        if ($stmt) {
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $dv_structure[] = $row['Field'];
            }
            $stmt->close();
        }
    }
} catch (Exception $e) {
    error_log("Error checking distribution_volunteer structure: " . $e->getMessage());
}

try {
    // Check if distribution_volunteer table exists and has data
    if (in_array('distribution_volunteer', $debug_tables)) {
        $assignments_query = "
            SELECT dv.*, d.date, d.location, d.status as distribution_status, d.disaster_id
            FROM distribution_volunteer dv
            JOIN distribution d ON dv.distribution_id = d.distribution_id
            WHERE dv.status IN ('Assigned', 'Active')
            ORDER BY d.date DESC
        ";
        
        $stmt = $db->prepare($assignments_query);
        if ($stmt) {
            $stmt->execute();
            $result = $stmt->get_result();
            $assignment_count = 0;
            
            while ($row = $result->fetch_assoc()) {
                $assignment_count++;
                $volunteer_id = $row['volunteer_id'];
                $distribution_id = $row['distribution_id'];
                $disaster_id = $row['disaster_id'];
                
                // Add disaster info from API
                if (isset($disasters[$disaster_id])) {
                    $row['disaster_name'] = $disasters[$disaster_id]['disaster_name'];
                    $row['disaster_location'] = $disasters[$disaster_id]['district'] ?? $disasters[$disaster_id]['location'] ?? 'Unknown';
                } else {
                    $row['disaster_name'] = 'Unknown Disaster';
                    $row['disaster_location'] = $row['location'] ?? 'Unknown';
                }
                
                // Update stats
                if (isset($volunteer_stats[$volunteer_id])) {
                    $volunteer_stats[$volunteer_id]['total_assignments']++;
                    $volunteer_stats[$volunteer_id]['active_assignments']++;
                    $volunteer_stats[$volunteer_id]['assignments'][] = $row;
                    $volunteer_assignments[$volunteer_id][] = $row;
                } else {
                    // Volunteer not in API list but has assignments
                    error_log("Warning: Volunteer ID " . $volunteer_id . " has assignments but not in API list");
                }
                
                // Track assigned volunteers by distribution
                if (!isset($assigned_volunteers_by_distribution[$distribution_id])) {
                    $assigned_volunteers_by_distribution[$distribution_id] = [];
                }
                $assigned_volunteers_by_distribution[$distribution_id][] = $volunteer_id;
            }
            $stmt->close();
        }
    }
    
    // Get distribution log stats for each volunteer
    if (in_array('distribution_log', $debug_tables)) {
        $log_stats_query = "
            SELECT volunteer_id,
                   COUNT(DISTINCT victim_id) as families_helped,
                   COUNT(DISTINCT need_id) as items_distributed,
                   SUM(quantity_distributed) as total_quantity
            FROM distribution_log
            WHERE status = 'completed'
            GROUP BY volunteer_id
        ";
        
        $stmt = $db->prepare($log_stats_query);
        if ($stmt) {
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $volunteer_id = $row['volunteer_id'];
                
                if (isset($volunteer_stats[$volunteer_id])) {
                    $volunteer_stats[$volunteer_id]['families_helped'] = $row['families_helped'] ?? 0;
                    $volunteer_stats[$volunteer_id]['items_distributed'] = $row['items_distributed'] ?? 0;
                    $volunteer_stats[$volunteer_id]['total_quantity'] = $row['total_quantity'] ?? 0;
                }
            }
            $stmt->close();
        }
    }
    
} catch (Exception $e) {
    error_log("Error fetching volunteer assignments: " . $e->getMessage());
    $error .= "<br>Error loading assignments: " . $e->getMessage();
}

/* ========================================
   CALCULATE DISASTER STATISTICS FROM APIS
======================================== */
$disaster_stats = [
    'total_disasters' => count($disasters),
    'active_disasters' => count(array_filter($disasters, function($d) {
        return in_array(strtolower($d['status']), ['active', 'ongoing', 'in progress']);
    })),
    'total_victims' => count($victims),
    'total_needs' => count($needs),
    'high_priority_needs' => count(array_filter($needs, function($n) {
        return strtolower($n['priority']) == 'high';
    })),
    'special_needs_victims' => count(array_filter($victims, function($v) {
        return $v['has_baby'] || $v['has_elderly'] || $v['has_disabled'];
    }))
];

/* ========================================
   APPLY FILTERS TO VOLUNTEERS
======================================== */
$filtered_volunteers = $all_volunteers;

// Apply NGO filter
if ($filter_ngo != 'All') {
    $filtered_volunteers = array_filter($filtered_volunteers, function($vol) use ($filter_ngo) {
        return $vol['ngo_affiliation'] == $filter_ngo;
    });
}

// Apply status filter
if ($filter_status != 'All') {
    $filtered_volunteers = array_filter($filtered_volunteers, function($vol) use ($filter_status) {
        return strtolower($vol['availability_status']) == strtolower($filter_status);
    });
}

// Apply skill filter
if ($filter_skill != 'All') {
    $filtered_volunteers = array_filter($filtered_volunteers, function($vol) use ($filter_skill) {
        return strtolower($vol['skill_category']) == strtolower($filter_skill);
    });
}

// Apply search filter
if (!empty($search_query)) {
    $search_lower = strtolower($search_query);
    $filtered_volunteers = array_filter($filtered_volunteers, function($vol) use ($search_lower) {
        return strpos(strtolower($vol['name']), $search_lower) !== false ||
               strpos(strtolower($vol['email']), $search_lower) !== false ||
               strpos(strtolower($vol['phone']), $search_lower) !== false ||
               strpos(strtolower($vol['volunteer_id']), $search_lower) !== false;
    });
}

// Reset array keys
$filtered_volunteers = array_values($filtered_volunteers);

/* ========================================
   FORM SUBMISSION HANDLERS
======================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle mass assignment
    if (isset($_POST['mass_assign'])) {
        $selected_volunteers = $_POST['selected_volunteers'] ?? [];
        $distribution_id = $_POST['distribution_id'] ?? 0;
        $assignment_role = $_POST['assignment_role'] ?? 'Volunteer';
        
        if (empty($selected_volunteers)) {
            $error = "Please select at least one volunteer.";
        } elseif (!$distribution_id) {
            $error = "Please select a distribution.";
        } else {
            try {
                $db->begin_transaction();
                $assigned_count = 0;
                $already_assigned = 0;
                
                // Get distribution details - handle API distributions differently
                if (strpos($distribution_id, 'API') === 0) {
                    // This is an API-based distribution (mock)
                    $disaster_id = str_replace('API', '', $distribution_id);
                    $distribution_name = isset($disasters[$disaster_id]) ? $disasters[$disaster_id]['disaster_name'] : 'API Distribution';
                    $location = isset($disasters[$disaster_id]) ? $disasters[$disaster_id]['district'] : 'Unknown';
                } else {
                    // This is a database distribution
                    $dist_query = "SELECT disaster_id, location FROM distribution WHERE distribution_id = ?";
                    $stmt = $db->prepare($dist_query);
                    $stmt->bind_param("i", $distribution_id);
                    $stmt->execute();
                    $dist_result = $stmt->get_result();
                    $distribution = $dist_result->fetch_assoc();
                    $stmt->close();
                    $disaster_id = $distribution['disaster_id'] ?? 0;
                    $location = $distribution['location'] ?? 'Unknown';
                }
                
                foreach ($selected_volunteers as $volunteer_id) {
                    // Only proceed for database distributions
                    if (strpos($distribution_id, 'API') !== 0) {
                        // Check if already assigned
                        $check_query = "SELECT id FROM distribution_volunteer WHERE distribution_id = ? AND volunteer_id = ?";
                        $stmt = $db->prepare($check_query);
                        $stmt->bind_param("ii", $distribution_id, $volunteer_id);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        $already_assigned_in_db = ($result->num_rows > 0);
                        $stmt->close();
                        
                        if (!$already_assigned_in_db) {
                            // Assign volunteer
                            $assign_query = "INSERT INTO distribution_volunteer (distribution_id, volunteer_id, role, status, assigned_timestamp) VALUES (?, ?, ?, 'Assigned', NOW())";
                            $stmt = $db->prepare($assign_query);
                            
                            // Get volunteer's skill category
                            $volunteer_role = $assignment_role;
                            foreach ($all_volunteers as $vol) {
                                if ($vol['volunteer_id'] == $volunteer_id) {
                                    if ($assignment_role == 'Auto (Based on Skill)') {
                                        $volunteer_role = $vol['skill_category'] ?? 'Volunteer';
                                    }
                                    break;
                                }
                            }
                            
                            $stmt->bind_param("iis", $distribution_id, $volunteer_id, $volunteer_role);
                            if ($stmt->execute()) {
                                $assigned_count++;
                                
                                // Create notification for volunteer if table exists
                                if (in_array('volunteer_alerts', $debug_tables)) {
                                    $notification_query = "INSERT INTO volunteer_alerts (volunteer_id, distribution_id, message) VALUES (?, ?, ?)";
                                    $notification_stmt = $db->prepare($notification_query);
                                    $message = "You have been assigned to Distribution #{$distribution_id} as {$volunteer_role}";
                                    $notification_stmt->bind_param("iis", $volunteer_id, $distribution_id, $message);
                                    $notification_stmt->execute();
                                    $notification_stmt->close();
                                }
                            }
                            $stmt->close();
                        } else {
                            $already_assigned++;
                        }
                    } else {
                        $error = "Cannot assign volunteers to API-based distributions. Please create the distribution in the database first.";
                        break;
                    }
                }
                
                // Update distribution status if not already assigned
                if (strpos($distribution_id, 'API') !== 0 && $assigned_count > 0) {
                    $update_query = "UPDATE distribution SET status = 'Assigned' WHERE distribution_id = ? AND status = 'Planning'";
                    $stmt = $db->prepare($update_query);
                    $stmt->bind_param("i", $distribution_id);
                    $stmt->execute();
                    $stmt->close();
                }
                
                $db->commit();
                
                if ($assigned_count > 0) {
                    $success = "Successfully assigned $assigned_count volunteer(s) to distribution #$distribution_id";
                    if ($already_assigned > 0) {
                        $success .= " ($already_assigned were already assigned)";
                    }
                } elseif (empty($error)) {
                    $error = "All selected volunteers were already assigned to this distribution.";
                }
                
            } catch (Exception $e) {
                $db->rollback();
                $error = "Error assigning volunteers: " . $e->getMessage();
            }
        }
    }
    
    // Handle remove assignment
    if (isset($_POST['remove_assignment'])) {
        $volunteer_id = $_POST['volunteer_id'] ?? 0;
        $distribution_id = $_POST['distribution_id'] ?? 0;
        
        if ($volunteer_id && $distribution_id) {
            try {
                $db->begin_transaction();
                
                // Mark as cancelled in distribution_volunteer
                $update_query = "UPDATE distribution_volunteer SET status = 'Cancelled', completed_at = NOW() WHERE distribution_id = ? AND volunteer_id = ?";
                $stmt = $db->prepare($update_query);
                $stmt->bind_param("ii", $distribution_id, $volunteer_id);
                $stmt->execute();
                $stmt->close();
                
                // Log the cancellation if table exists
                if (in_array('assignment_cancellations', $debug_tables)) {
                    $cancel_query = "INSERT INTO assignment_cancellations (distribution_id, volunteer_id, reason, cancelled_by) VALUES (?, ?, 'Manually removed by admin', ?)";
                    $stmt = $db->prepare($cancel_query);
                    $admin_name = $_SESSION['user_name'] ?? 'Admin';
                    $stmt->bind_param("iis", $distribution_id, $volunteer_id, $admin_name);
                    $stmt->execute();
                    $stmt->close();
                }
                
                // Remove from distribution_log if table exists
                if (in_array('distribution_log', $debug_tables)) {
                    $remove_log_query = "DELETE FROM distribution_log WHERE distribution_id = ? AND volunteer_id = ?";
                    $stmt = $db->prepare($remove_log_query);
                    $stmt->bind_param("ii", $distribution_id, $volunteer_id);
                    $stmt->execute();
                    $stmt->close();
                }
                
                // Check if any volunteers remain
                $check_remaining = "SELECT COUNT(*) as count FROM distribution_volunteer WHERE distribution_id = ? AND status IN ('Assigned', 'Active')";
                $stmt = $db->prepare($check_remaining);
                $stmt->bind_param("i", $distribution_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $row = $result->fetch_assoc();
                $remaining_count = $row['count'] ?? 0;
                $stmt->close();
                
                // Update distribution status if no volunteers left
                if ($remaining_count == 0) {
                    $update_dist_query = "UPDATE distribution SET status = 'Planning' WHERE distribution_id = ?";
                    $stmt = $db->prepare($update_dist_query);
                    $stmt->bind_param("i", $distribution_id);
                    $stmt->execute();
                    $stmt->close();
                }
                
                $db->commit();
                $success = "Volunteer removed from assignment successfully.";
                
            } catch (Exception $e) {
                $db->rollback();
                $error = "Error removing assignment: " . $e->getMessage();
            }
        }
    }
}

// Get unique skill categories
$skill_categories = [];
foreach ($all_volunteers as $volunteer) {
    $skill = $volunteer['skill_category'] ?? 'Volunteer';
    if (!in_array($skill, $skill_categories)) {
        $skill_categories[] = $skill;
    }
}
sort($skill_categories);

// Get availability statuses
$status_options = ['All', 'Available', 'Busy', 'Unavailable', 'On Leave', 'Active', 'Inactive'];

// Calculate statistics
$total_volunteers = count($all_volunteers);
$available_count = count(array_filter($all_volunteers, function($v) {
    return strtolower($v['availability_status']) == 'available' || strtolower($v['availability_status']) == 'active';
}));
$assigned_count = count(array_filter($all_volunteers, function($v) use ($volunteer_stats) {
    $id = $v['volunteer_id'];
    return isset($volunteer_stats[$id]) && $volunteer_stats[$id]['active_assignments'] > 0;
}));
$ngos_count = count(array_unique(array_column($all_volunteers, 'ngo_affiliation')));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Volunteers - Disaster Relief System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #4361ee;
            --primary-dark: #3a56d4;
            --secondary: #7209b7;
            --success: #2ecc71;
            --warning: #f39c12;
            --danger: #e74c3c;
            --info: #3498db;
            --light: #f8f9fa;
            --dark: #2c3e50;
            --gray: #6c757d;
            --light-gray: #e9ecef;
            --border-radius: 12px;
            --box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            --transition: all 0.3s ease;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding: 20px;
        }
        
        .container {
            max-width: 1600px;
            margin: 0 auto;
        }
        
        /* Header */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
            margin-bottom: 30px;
            padding: 25px;
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
        }
        
        .header-title {
            color: var(--dark);
            font-size: 2.2rem;
            margin: 0;
        }
        
        .debug-info {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin: 20px 0;
            border-radius: 8px;
            font-size: 0.9rem;
        }
        
        .debug-info h4 {
            margin: 0 0 10px 0;
            color: #856404;
        }
        
        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 50px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: var(--transition);
            text-decoration: none;
            font-size: 0.95rem;
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
        
        .btn-outline {
            background: transparent;
            color: var(--primary);
            border: 2px solid var(--primary);
        }
        
        .btn-outline:hover {
            background: var(--primary);
            color: white;
        }
        
        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
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
        
        .stat-card.total { border-top-color: var(--primary); }
        .stat-card.available { border-top-color: var(--success); }
        .stat-card.assigned { border-top-color: var(--info); }
        .stat-card.ngos { border-top-color: var(--warning); }
        
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
        
        /* Messages */
        .message {
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 15px;
            font-weight: 600;
        }
        
        .message.error {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid var(--danger);
        }
        
        .message.success {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid var(--success);
        }
        
        .main-content {
            display: grid;
            grid-template-columns: 400px 1fr;
            gap: 30px;
            margin: 30px 0;
        }
        
        @media (max-width: 1200px) {
            .main-content {
                grid-template-columns: 1fr;
            }
        }
        
        /* Sidebar */
        .sidebar {
            background: white;
            border-radius: var(--border-radius);
            padding: 25px;
            box-shadow: var(--box-shadow);
            height: fit-content;
            position: sticky;
            top: 20px;
        }
        
        .sidebar-section {
            margin-bottom: 30px;
        }
        
        .sidebar-title {
            font-size: 1.2rem;
            color: var(--dark);
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--light-gray);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        /* Mass Assignment Card */
        .mass-assign-card {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: var(--border-radius);
            padding: 25px;
            border: 2px solid var(--primary);
            box-shadow: 0 5px 20px rgba(67, 97, 238, 0.1);
        }
        
        .mass-assign-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .mass-assign-icon {
            width: 50px;
            height: 50px;
            background: var(--primary);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
        }
        
        .mass-assign-title {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--dark);
            margin: 0;
        }
        
        .mass-assign-steps {
            margin: 20px 0;
            padding-left: 20px;
        }
        
        .mass-assign-step {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 15px;
            color: var(--gray);
        }
        
        .step-number {
            width: 25px;
            height: 25px;
            background: var(--light-gray);
            color: var(--dark);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: bold;
        }
        
        .step-active .step-number {
            background: var(--primary);
            color: white;
        }
        
        .step-active {
            color: var(--dark);
            font-weight: 600;
        }
        
        .mass-assign-form {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .form-label {
            font-weight: 600;
            color: var(--dark);
            font-size: 0.9rem;
        }
        
        .form-input, .form-select {
            padding: 12px 16px;
            border: 2px solid var(--light-gray);
            border-radius: 8px;
            font-size: 14px;
            transition: var(--transition);
        }
        
        .form-input:focus, .form-select:focus {
            border-color: var(--primary);
            outline: none;
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.1);
        }
        
        .selected-info {
            background: #e8f4fc;
            border-radius: 8px;
            padding: 15px;
            margin: 15px 0;
            border-left: 4px solid var(--info);
        }
        
        .selected-count {
            font-size: 2rem;
            font-weight: 700;
            color: var(--info);
            text-align: center;
            margin: 10px 0;
        }
        
        .assign-btn {
            background: linear-gradient(135deg, var(--success), #27ae60);
            color: white;
            border: none;
            padding: 15px;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: var(--transition);
            margin-top: 10px;
        }
        
        .assign-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(46, 204, 113, 0.3);
        }
        
        .assign-btn:disabled {
            background: var(--light-gray);
            color: var(--gray);
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }
        
        /* Volunteers Grid */
        .volunteers-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .volunteer-card {
            background: white;
            border-radius: var(--border-radius);
            padding: 25px;
            box-shadow: var(--box-shadow);
            transition: var(--transition);
            border: 2px solid transparent;
            position: relative;
            overflow: hidden;
        }
        
        .volunteer-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0,0,0,0.12);
        }
        
        .volunteer-card.selected {
            border-color: var(--primary);
            background: linear-gradient(135deg, rgba(67, 97, 238, 0.05), rgba(114, 9, 183, 0.05));
        }
        
        .volunteer-checkbox {
            position: absolute;
            top: 20px;
            right: 20px;
            width: 22px;
            height: 22px;
            border: 2px solid var(--primary);
            border-radius: 4px;
            cursor: pointer;
            appearance: none;
            -webkit-appearance: none;
            transition: var(--transition);
        }
        
        .volunteer-checkbox:checked {
            background: var(--primary);
            border-color: var(--primary);
        }
        
        .volunteer-checkbox:checked::after {
            content: '✓';
            color: white;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 14px;
            font-weight: bold;
        }
        
        .volunteer-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .volunteer-avatar {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            font-weight: bold;
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
            margin: 15px 0;
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
        }
        
        .status-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .badge-available {
            background: #d4edda;
            color: #155724;
        }
        
        .badge-busy {
            background: #fff3cd;
            color: #856404;
        }
        
        .badge-unavailable {
            background: #f8d7da;
            color: #721c24;
        }
        
        .badge-on-leave {
            background: #d1ecf1;
            color: #0c5460;
        }
        
        .volunteer-stats {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid var(--light-gray);
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            text-align: center;
        }
        
        .stat-mini {
            font-size: 0.85rem;
        }
        
        .stat-mini-value {
            font-weight: 700;
            color: var(--dark);
            font-size: 1.1rem;
        }
        
        .stat-mini-label {
            color: var(--gray);
            font-size: 0.75rem;
        }
        
        /* Volunteer Actions */
        .volunteer-actions {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid var(--light-gray);
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .action-btn {
            padding: 8px 15px;
            border: none;
            border-radius: 6px;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 5px;
            transition: var(--transition);
        }
        
        .action-btn.assignments {
            background: var(--info);
            color: white;
        }
        
        .action-btn.assignments:hover {
            background: #2980b9;
        }
        
        .action-btn.status {
            background: var(--warning);
            color: white;
        }
        
        .action-btn.status:hover {
            background: #e67e22;
        }
        
        /* Assignments Section */
        .assignments-section {
            background: white;
            border-radius: var(--border-radius);
            padding: 30px;
            margin-top: 30px;
            box-shadow: var(--box-shadow);
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--light-gray);
        }
        
        .section-title {
            font-size: 1.5rem;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        /* Filters */
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
        
        .filter-select,
        .filter-input {
            padding: 12px 16px;
            border: 2px solid var(--light-gray);
            border-radius: 8px;
            font-size: 14px;
            transition: var(--transition);
        }
        
        .filter-select:focus,
        .filter-input:focus {
            border-color: var(--primary);
            outline: none;
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.1);
        }
        
        /* Empty State */
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
        
        /* API Specific Styles */
        .api-disaster-badge {
            background: #d1ecf1 !important;
            color: #0c5460 !important;
            border: 1px dashed #0c5460 !important;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 0.7rem;
            margin-right: 8px;
        }
        
        .api-disaster-option {
            background: #e8f4fc;
            font-style: italic;
        }
        
        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.7);
            z-index: 1000;
            overflow-y: auto;
        }
        
        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 0;
            border-radius: var(--border-radius);
            width: 90%;
            max-width: 800px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            animation: modalFadeIn 0.3s;
        }
        
        @keyframes modalFadeIn {
            from { opacity: 0; transform: translateY(-30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .modal-header {
            padding: 25px 30px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            border-radius: var(--border-radius) var(--border-radius) 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-header h2 {
            margin: 0;
            font-size: 1.8rem;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .close-modal {
            color: white;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            background: none;
            border: none;
            transition: var(--transition);
        }
        
        .close-modal:hover {
            color: #ddd;
            transform: scale(1.1);
        }
        
        .modal-body {
            padding: 30px;
            max-height: 60vh;
            overflow-y: auto;
        }
        
        .modal-section {
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--light-gray);
        }
        
        .modal-section:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        
        .modal-section-title {
            font-size: 1.2rem;
            color: var(--dark);
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        
        .info-item {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        
        .info-label {
            font-size: 0.85rem;
            color: var(--gray);
            font-weight: 600;
        }
        
        .info-value {
            font-size: 1rem;
            color: var(--dark);
            font-weight: 500;
        }
        
        .assignment-card {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 15px;
            border-left: 4px solid var(--info);
            transition: var(--transition);
        }
        
        .assignment-card:hover {
            transform: translateX(5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .assignment-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 10px;
        }
        
        .assignment-title {
            font-weight: 700;
            color: var(--dark);
            font-size: 1.1rem;
        }
        
        .assignment-status {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .status-assigned {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-active {
            background: #d4edda;
            color: #155724;
        }
        
        .assignment-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 10px;
            font-size: 0.9rem;
            color: var(--gray);
        }
        
        .assignment-details div {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .no-assignments {
            text-align: center;
            padding: 40px 20px;
            color: var(--gray);
        }
        
        .no-assignments i {
            font-size: 3rem;
            margin-bottom: 15px;
            color: #ddd;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .volunteers-container {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .modal-content {
                width: 95%;
                margin: 10% auto;
            }
        }
        
        @media (max-width: 576px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .modal-body {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1 class="header-title">
                <i class="fas fa-users-cog"></i>
                Manage Volunteers
            </h1>
            
            <div class="header-actions">
                <a href="distribution_main.php" class="btn btn-outline">
                    <i class="fas fa-arrow-left"></i>
                    Back to Dashboard
                </a>
                <button class="btn btn-primary" onclick="exportVolunteers()">
                    <i class="fas fa-file-export"></i>
                    Export Volunteers
                </button>
                <button class="btn btn-success" onclick="exportDisasterData()" style="background: linear-gradient(135deg, var(--warning), #e74c3c);">
                    <i class="fas fa-download"></i>
                    Export Disaster Data
                </button>
            </div>
        </div>
        
        <!-- Debug Info (remove in production) -->
        <?php if (!empty($debug_tables)): ?>
        <div class="debug-info">
            <h4>Debug Information:</h4>
            <p><strong>Tables Found:</strong> <?php echo implode(', ', $debug_tables); ?></p>
            <p><strong>Volunteers from API:</strong> <?php echo count($all_volunteers); ?></p>
            <p><strong>Disasters from API:</strong> <?php echo count($disasters); ?></p>
            <p><strong>Victims from API:</strong> <?php echo count($victims); ?></p>
            <p><strong>Needs from API:</strong> <?php echo count($needs); ?></p>
            <p><strong>Active Distributions:</strong> <?php echo count($active_distributions); ?></p>
            <p><strong>Volunteers with Assignments:</strong> <?php echo $assigned_count; ?></p>
        </div>
        <?php endif; ?>
        
        <!-- Stats -->
        <div class="stats-grid">
            <!-- Volunteer Stats -->
            <div class="stat-card total">
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-value"><?php echo $total_volunteers; ?></div>
                <div class="stat-label">Total Volunteers</div>
            </div>
            
            <div class="stat-card available">
                <div class="stat-icon">
                    <i class="fas fa-user-check"></i>
                </div>
                <div class="stat-value"><?php echo $available_count; ?></div>
                <div class="stat-label">Available Now</div>
            </div>
            
            <div class="stat-card assigned">
                <div class="stat-icon">
                    <i class="fas fa-tasks"></i>
                </div>
                <div class="stat-value"><?php echo $assigned_count; ?></div>
                <div class="stat-label">Currently Assigned</div>
            </div>
            
            <div class="stat-card ngos">
                <div class="stat-icon">
                    <i class="fas fa-hands-helping"></i>
                </div>
                <div class="stat-value"><?php echo $ngos_count; ?></div>
                <div class="stat-label">NGO Partners</div>
            </div>
            
            <!-- Disaster Stats -->
            <div class="stat-card" style="border-top-color: var(--warning);">
                <div class="stat-icon" style="background: linear-gradient(135deg, var(--warning), #e74c3c);">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="stat-value"><?php echo $disaster_stats['active_disasters']; ?></div>
                <div class="stat-label">Active Disasters</div>
            </div>
            
            <div class="stat-card" style="border-top-color: var(--danger);">
                <div class="stat-icon" style="background: linear-gradient(135deg, var(--danger), #c0392b);">
                    <i class="fas fa-user-injured"></i>
                </div>
                <div class="stat-value"><?php echo $disaster_stats['total_victims']; ?></div>
                <div class="stat-label">Total Victims</div>
            </div>
            
            <div class="stat-card" style="border-top-color: var(--info);">
                <div class="stat-icon" style="background: linear-gradient(135deg, var(--info), #2980b9);">
                    <i class="fas fa-hand-holding-heart"></i>
                </div>
                <div class="stat-value"><?php echo $disaster_stats['high_priority_needs']; ?></div>
                <div class="stat-label">High Priority Needs</div>
            </div>
            
            <div class="stat-card" style="border-top-color: #9b59b6;">
                <div class="stat-icon" style="background: linear-gradient(135deg, #9b59b6, #8e44ad);">
                    <i class="fas fa-wheelchair"></i>
                </div>
                <div class="stat-value"><?php echo $disaster_stats['special_needs_victims']; ?></div>
                <div class="stat-label">Special Needs</div>
            </div>
        </div>
        
        <!-- Messages -->
        <?php if ($error): ?>
            <div class="message error">
                <i class="fas fa-exclamation-circle"></i>
                <div><?php echo $error; ?></div>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="message success">
                <i class="fas fa-check-circle"></i>
                <div><?php echo $success; ?></div>
            </div>
        <?php endif; ?>
        
        <div class="main-content">
            <!-- Sidebar -->
            <div class="sidebar">
                <!-- Mass Assignment -->
                <div class="mass-assign-card">
                    <div class="mass-assign-header">
                        <div class="mass-assign-icon">
                            <i class="fas fa-user-plus"></i>
                        </div>
                        <div>
                            <h3 class="mass-assign-title">Mass Assignment</h3>
                            <p style="color: var(--gray); font-size: 0.9rem; margin: 5px 0 0 0;">
                                Assign multiple volunteers at once
                            </p>
                        </div>
                    </div>
                    
                    <div class="mass-assign-steps">
                        <div class="mass-assign-step step-active">
                            <div class="step-number">1</div>
                            <span>Select volunteers from list</span>
                        </div>
                        <div class="mass-assign-step">
                            <div class="step-number">2</div>
                            <span>Choose distribution & role</span>
                        </div>
                        <div class="mass-assign-step">
                            <div class="step-number">3</div>
                            <span>Confirm and assign</span>
                        </div>
                    </div>
                    
                    <div class="selected-info">
                        <div style="text-align: center; color: var(--gray); font-size: 0.9rem;">
                            Selected Volunteers
                        </div>
                        <div class="selected-count" id="selectedCount">0</div>
                        <div style="text-align: center; color: var(--gray); font-size: 0.9rem;">
                            ready for assignment
                        </div>
                    </div>
                    
                    <form method="POST" id="massAssignForm" class="mass-assign-form">
                        <input type="hidden" name="mass_assign" value="1">
                        
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-box-open"></i>
                                Select Distribution
                            </label>
                            <select name="distribution_id" class="form-select" required id="distributionSelect">
                                <option value="">-- Choose Distribution --</option>
                                
                                <!-- Database distributions -->
                                <?php 
                                $has_db_distributions = false;
                                foreach ($active_distributions as $distribution): 
                                    if (strpos($distribution['distribution_id'], 'API') !== 0): 
                                        $has_db_distributions = true;
                                        $disaster_name = $distribution['disaster_name'] ?? 'N/A';
                                        $location = $distribution['disaster_location'] ?? $distribution['location'] ?? 'N/A';
                                ?>
                                <option value="<?php echo $distribution['distribution_id']; ?>" 
                                        data-disaster="<?php echo htmlspecialchars($disaster_name); ?>"
                                        data-location="<?php echo htmlspecialchars($location); ?>"
                                        data-date="<?php echo date('d/m/Y', strtotime($distribution['date'])); ?>"
                                        data-type="db">
                                    #DIST<?php echo str_pad($distribution['distribution_id'], 6, '0', STR_PAD_LEFT); ?> 
                                    - <?php echo htmlspecialchars($disaster_name); ?>
                                </option>
                                <?php endif; endforeach; ?>
                                
                                <!-- API-based distributions (mock) -->
                                <?php foreach ($active_distributions as $distribution): 
                                    if (strpos($distribution['distribution_id'], 'API') === 0): 
                                        $disaster_name = $distribution['disaster_name'] ?? 'N/A';
                                        $location = $distribution['disaster_location'] ?? $distribution['location'] ?? 'N/A';
                                ?>
                                <option value="<?php echo $distribution['distribution_id']; ?>" 
                                        data-disaster="<?php echo htmlspecialchars($disaster_name); ?>"
                                        data-location="<?php echo htmlspecialchars($location); ?>"
                                        data-date="<?php echo date('d/m/Y', strtotime($distribution['date'])); ?>"
                                        data-type="api"
                                        class="api-disaster-option">
                                    [API] <?php echo htmlspecialchars($disaster_name); ?> 
                                    - <?php echo htmlspecialchars($location); ?>
                                </option>
                                <?php endif; endforeach; ?>
                                
                                <?php if (empty($active_distributions)): ?>
                                <option value="" disabled>No active distributions found</option>
                                <?php endif; ?>
                            </select>
                            
                            <?php if (!$has_db_distributions && !empty($active_distributions)): ?>
                            <div style="margin-top: 10px; padding: 10px; background: #fff3cd; border-radius: 6px; font-size: 0.85rem; color: #856404;">
                                <i class="fas fa-info-circle"></i> Only API-based distributions available. 
                                <a href="create_distribution.php" style="color: var(--primary); font-weight: 600;">Create a distribution</a> to assign volunteers.
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-user-tag"></i>
                                Assignment Role
                            </label>
                            <select name="assignment_role" class="form-select">
                                <option value="Auto (Based on Skill)">Auto (Based on Skill)</option>
                                <option value="Coordinator">Coordinator</option>
                                <option value="Driver">Driver</option>
                                <option value="Medical Staff">Medical Staff</option>
                                <option value="Logistics">Logistics</option>
                                <option value="General Volunteer">General Volunteer</option>
                            </select>
                        </div>
                        
                        <div id="distributionDetails" style="display: none; background: #e8f4fc; padding: 15px; border-radius: 8px; margin: 10px 0;">
                            <div style="font-weight: 600; color: var(--dark); margin-bottom: 8px;">
                                <i class="fas fa-info-circle"></i> Distribution Details
                                <span id="apiIndicator" style="display: none; margin-left: 10px; font-size: 0.8rem; padding: 2px 8px; background: #0c5460; color: white; border-radius: 10px;">API</span>
                            </div>
                            <div style="font-size: 0.9rem;">
                                <div><strong>Disaster:</strong> <span id="detailDisaster">-</span></div>
                                <div><strong>Location:</strong> <span id="detailLocation">-</span></div>
                                <div><strong>Date:</strong> <span id="detailDate">-</span></div>
                                <div id="apiWarning" style="display: none; margin-top: 10px; padding: 8px; background: #f8d7da; color: #721c24; border-radius: 4px; font-size: 0.85rem;">
                                    <i class="fas fa-exclamation-triangle"></i> This is an API-based distribution. Create it in the system first.
                                </div>
                            </div>
                        </div>
                        
                        <button type="submit" class="assign-btn" id="assignButton" disabled>
                            <i class="fas fa-user-check"></i>
                            Assign Selected Volunteers
                        </button>
                        
                        <div style="margin-top: 15px; text-align: center;">
                            <button type="button" class="btn btn-outline" style="width: 100%;" onclick="clearSelections()">
                                <i class="fas fa-times"></i>
                                Clear Selection
                            </button>
                        </div>
                    </form>
                </div>
                
                <!-- Quick Disaster Info -->
                <?php if (!empty($disasters)): ?>
                <div class="sidebar-section">
                    <h3 class="sidebar-title">
                        <i class="fas fa-exclamation-triangle"></i>
                        Active Disasters
                    </h3>
                    
                    <div style="max-height: 300px; overflow-y: auto;">
                        <?php 
                        $active_disasters = array_filter($disasters, function($d) {
                            return in_array(strtolower($d['status']), ['active', 'ongoing', 'in progress']);
                        });
                        
                        if (empty($active_disasters)): ?>
                            <div style="padding: 15px; background: var(--light); border-radius: 8px; text-align: center; color: var(--gray);">
                                <i class="fas fa-check-circle"></i><br>
                                No active disasters
                            </div>
                        <?php else: ?>
                            <?php foreach (array_slice($active_disasters, 0, 3) as $disaster): ?>
                            <div style="padding: 12px; background: #f8f9fa; border-radius: 8px; margin-bottom: 10px; border-left: 3px solid var(--warning);">
                                <div style="font-weight: 600; color: var(--dark); margin-bottom: 5px;">
                                    <?php echo htmlspecialchars($disaster['disaster_name']); ?>
                                </div>
                                <div style="font-size: 0.85rem; color: var(--gray);">
                                    <div><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($disaster['district']); ?></div>
                                    <div><i class="fas fa-users"></i> <?php echo $disaster['affected_people']; ?> affected</div>
                                    <div><i class="fas fa-bolt"></i> Severity: <?php echo $disaster['severity']; ?></div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            
                            <?php if (count($active_disasters) > 3): ?>
                            <div style="text-align: center; margin-top: 10px;">
                                <a href="view_disasters.php" style="color: var(--primary); font-size: 0.9rem; font-weight: 600;">
                                    <i class="fas fa-ellipsis-h"></i> View all <?php echo count($active_disasters); ?> disasters
                                </a>
                            </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Filters -->
                <div class="sidebar-section">
                    <h3 class="sidebar-title">
                        <i class="fas fa-filter"></i>
                        Filter Volunteers
                    </h3>
                    
                    <form method="GET" id="filterForm">
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-hands-helping"></i>
                                NGO Affiliation
                            </label>
                            <select name="ngo" class="form-select" onchange="this.form.submit()">
                                <?php foreach ($ngos as $ngo): ?>
                                <option value="<?php echo htmlspecialchars($ngo); ?>" <?php echo $filter_ngo == $ngo ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($ngo); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-circle"></i>
                                Status
                            </label>
                            <select name="status" class="form-select" onchange="this.form.submit()">
                                <?php foreach ($status_options as $status): ?>
                                <option value="<?php echo $status; ?>" <?php echo $filter_status == $status ? 'selected' : ''; ?>>
                                    <?php echo $status; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-tools"></i>
                                Skill Category
                            </label>
                            <select name="skill" class="form-select" onchange="this.form.submit()">
                                <option value="All">All Skills</option>
                                <?php foreach ($skill_categories as $skill): ?>
                                <option value="<?php echo htmlspecialchars($skill); ?>" <?php echo $filter_skill == $skill ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($skill); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-search"></i>
                                Search
                            </label>
                            <div style="display: flex; gap: 10px;">
                                <input type="text" 
                                       name="search" 
                                       class="form-input" 
                                       placeholder="Name, Email, Phone..." 
                                       value="<?php echo htmlspecialchars($search_query); ?>"
                                       style="flex: 1;">
                                <button type="submit" class="btn btn-primary" style="padding: 12px 20px;">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div style="margin-top: 20px;">
                            <a href="manage_volunteer.php" class="btn btn-outline" style="width: 100%; justify-content: center;">
                                <i class="fas fa-redo"></i>
                                Clear Filters
                            </a>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Main Content Area -->
            <div>
                <!-- Filter Header -->
                <div class="filter-section">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <h2 style="margin: 0; color: var(--dark);">
                            <i class="fas fa-users"></i>
                            Volunteers (<?php echo count($filtered_volunteers); ?>)
                        </h2>
                        <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; padding: 10px 15px; background: var(--light); border-radius: 8px;">
                            <input type="checkbox" id="select-all-volunteers" style="transform: scale(1.3);">
                            <span style="font-weight: 600; color: var(--dark);">Select All</span>
                        </label>
                    </div>
                </div>
                
                <?php if (empty($filtered_volunteers)): ?>
                    <div class="empty-state">
                        <i class="fas fa-user-slash"></i>
                        <h3>No Volunteers Found</h3>
                        <p>No volunteers match your current filters. Try adjusting your search criteria.</p>
                    </div>
                <?php else: ?>
                    <div class="volunteers-container">
                        <?php foreach ($filtered_volunteers as $volunteer): 
                            $volunteer_id = $volunteer['volunteer_id'];
                            $initial = strtoupper(substr($volunteer['name'], 0, 1));
                            $stats = $volunteer_stats[$volunteer_id] ?? [
                                'total_assignments' => 0,
                                'active_assignments' => 0,
                                'completed_assignments' => 0,
                                'families_helped' => 0,
                                'items_distributed' => 0,
                                'total_quantity' => 0
                            ];
                            
                            // Determine status badge class
                            $status_class = 'badge-available';
                            $status = strtolower($volunteer['availability_status']);
                            if (strpos($status, 'busy') !== false) $status_class = 'badge-busy';
                            if (strpos($status, 'unavail') !== false) $status_class = 'badge-unavailable';
                            if (strpos($status, 'leave') !== false) $status_class = 'badge-on-leave';
                            if (strpos($status, 'inactive') !== false) $status_class = 'badge-unavailable';
                        ?>
                        <div class="volunteer-card" id="volunteer-<?php echo $volunteer_id; ?>">
                            <input type="checkbox" 
                                   name="selected_volunteers[]" 
                                   value="<?php echo $volunteer_id; ?>" 
                                   class="volunteer-checkbox"
                                   onchange="updateSelectedCount()"
                                   data-volunteer-id="<?php echo $volunteer_id; ?>">
                            
                            <div class="volunteer-header">
                                <div class="volunteer-avatar">
                                    <?php echo $initial; ?>
                                </div>
                                <div class="volunteer-info">
                                    <h3 class="volunteer-name"><?php echo htmlspecialchars($volunteer['name']); ?></h3>
                                    <span class="volunteer-id">VOL<?php echo str_pad($volunteer_id, 4, '0', STR_PAD_LEFT); ?></span>
                                </div>
                            </div>
                            
                            <div class="volunteer-details">
                                <?php if (!empty($volunteer['email'])): ?>
                                <div class="detail-item">
                                    <i class="fas fa-envelope"></i>
                                    <span><?php echo htmlspecialchars($volunteer['email']); ?></span>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($volunteer['phone'])): ?>
                                <div class="detail-item">
                                    <i class="fas fa-phone"></i>
                                    <span><?php echo htmlspecialchars($volunteer['phone']); ?></span>
                                </div>
                                <?php endif; ?>
                                
                                <div class="detail-item">
                                    <i class="fas fa-hands-helping"></i>
                                    <span><?php echo htmlspecialchars($volunteer['ngo_affiliation']); ?></span>
                                </div>
                                
                                <?php if (!empty($volunteer['address'])): ?>
                                <div class="detail-item">
                                    <i class="fas fa-map-marker-alt"></i>
                                    <span><?php echo htmlspecialchars($volunteer['address']); ?></span>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <div style="margin: 15px 0;">
                                <span class="skill-badge">
                                    <i class="fas fa-tools"></i> <?php echo htmlspecialchars($volunteer['skill_category']); ?>
                                </span>
                                <span class="status-badge <?php echo $status_class; ?>">
                                    <?php echo $volunteer['availability_status']; ?>
                                </span>
                            </div>
                            
                            <div class="volunteer-stats">
                                <div class="stat-mini">
                                    <div class="stat-mini-value"><?php echo $stats['total_assignments']; ?></div>
                                    <div class="stat-mini-label">Total Assignments</div>
                                </div>
                                <div class="stat-mini">
                                    <div class="stat-mini-value"><?php echo $stats['active_assignments']; ?></div>
                                    <div class="stat-mini-label">Active Now</div>
                                </div>
                                <div class="stat-mini">
                                    <div class="stat-mini-value"><?php echo $stats['families_helped']; ?></div>
                                    <div class="stat-mini-label">Families Helped</div>
                                </div>
                            </div>
                            
                            <div class="volunteer-actions">
                                <?php if ($stats['active_assignments'] > 0): ?>
                                <button class="action-btn assignments" onclick="showAssignmentsModal(<?php echo $volunteer_id; ?>)">
                                    <i class="fas fa-tasks"></i> 
                                    Assignments (<?php echo $stats['active_assignments']; ?>)
                                </button>
                                <?php endif; ?>
                                
                                <button class="action-btn status" onclick="showVolunteerDetailsModal(<?php echo $volunteer_id; ?>)">
                                    <i class="fas fa-eye"></i> 
                                    View Details
                                </button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                
                <!-- Active Assignments Section -->
                <?php if (!empty($assigned_volunteers_by_distribution)): ?>
                <div class="assignments-section">
                    <div class="section-header">
                        <h2 class="section-title">
                            <i class="fas fa-tasks"></i>
                            Active Assignments
                        </h2>
                        <span style="background: var(--primary); color: white; padding: 5px 15px; border-radius: 20px; font-size: 0.9rem; font-weight: 600;">
                            <?php echo count($assigned_volunteers_by_distribution); ?> active distributions
                        </span>
                    </div>
                    
                    <div style="display: grid; gap: 20px;">
                        <?php foreach ($active_distributions as $distribution): 
                            $distribution_id = $distribution['distribution_id'];
                            $assigned_vols = $assigned_volunteers_by_distribution[$distribution_id] ?? [];
                            if (!empty($assigned_vols)):
                                $disaster_name = $distribution['disaster_name'] ?? 'N/A';
                                $location = $distribution['disaster_location'] ?? $distribution['location'] ?? 'N/A';
                                $is_api = strpos($distribution_id, 'API') === 0;
                        ?>
                        <div style="background: white; border-radius: var(--border-radius); padding: 20px; box-shadow: var(--box-shadow); border-left: 4px solid <?php echo $is_api ? '#0c5460' : 'var(--primary)'; ?>;">
                            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 15px;">
                                <div>
                                    <div style="font-weight: 700; color: var(--dark); font-size: 1.1rem;">
                                        <?php if ($is_api): ?>
                                            <span class="api-disaster-badge">API</span>
                                        <?php else: ?>
                                            #DIST<?php echo str_pad($distribution_id, 6, '0', STR_PAD_LEFT); ?> 
                                        <?php endif; ?>
                                        - <?php echo htmlspecialchars($disaster_name); ?>
                                    </div>
                                    <div style="color: var(--gray); font-size: 0.9rem; margin-top: 5px;">
                                        <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($location); ?>
                                        <span style="margin-left: 15px;"><i class="fas fa-calendar"></i> <?php echo date('d/m/Y', strtotime($distribution['date'])); ?></span>
                                    </div>
                                </div>
                                <span style="background: <?php echo $distribution['status'] == 'In Progress' ? '#d4edda' : '#fff3cd'; ?>; color: <?php echo $distribution['status'] == 'In Progress' ? '#155724' : '#856404'; ?>; padding: 5px 12px; border-radius: 20px; font-size: 0.8rem; font-weight: 600;">
                                    <?php echo $distribution['status']; ?>
                                </span>
                            </div>
                            
                            <div style="margin: 15px 0;">
                                <div style="font-weight: 600; color: var(--dark); margin-bottom: 10px; display: flex; align-items: center; gap: 8px;">
                                    <i class="fas fa-users"></i> Assigned Volunteers (<?php echo count($assigned_vols); ?>)
                                </div>
                                <div style="display: flex; flex-wrap: wrap; gap: 10px;">
                                    <?php 
                                    $displayed = 0;
                                    foreach ($assigned_vols as $vol_id) {
                                        foreach ($all_volunteers as $vol) {
                                            if ($vol['volunteer_id'] == $vol_id) {
                                                $initial = strtoupper(substr($vol['name'], 0, 1));
                                                echo '<div title="' . htmlspecialchars($vol['name']) . '" style="display: flex; flex-direction: column; align-items: center; gap: 5px;">';
                                                echo '<div style="width: 40px; height: 40px; background: linear-gradient(135deg, var(--primary), var(--secondary)); color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 1rem;">' . $initial . '</div>';
                                                echo '<div style="font-size: 0.75rem; color: var(--gray); max-width: 80px; text-align: center; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">' . htmlspecialchars($vol['name']) . '</div>';
                                                echo '</div>';
                                                $displayed++;
                                                break;
                                            }
                                        }
                                        if ($displayed >= 8) {
                                            echo '<div style="display: flex; flex-direction: column; align-items: center; gap: 5px;">';
                                            echo '<div style="width: 40px; height: 40px; background: var(--light-gray); color: var(--gray); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 1rem;" title="' . count($assigned_vols) . ' volunteers total">+' . (count($assigned_vols) - 8) . '</div>';
                                            echo '<div style="font-size: 0.75rem; color: var(--gray);">More</div>';
                                            echo '</div>';
                                            break;
                                        }
                                    }
                                    ?>
                                </div>
                            </div>
                            
                            <div style="display: flex; gap: 10px; margin-top: 15px;">
                                <button class="action-btn assignments" onclick="showDistributionModal('<?php echo $distribution_id; ?>', '<?php echo $is_api ? 'api' : 'db'; ?>')">
                                    <i class="fas fa-eye"></i> View Details
                                </button>
                                <?php if (!$is_api): ?>
                                <a href="assign_volunteer.php?distribution_id=<?php echo $distribution_id; ?>" class="action-btn status">
                                    <i class="fas fa-user-plus"></i> Manage Volunteers
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Modal for Volunteer Details -->
    <div id="volunteerDetailsModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-user"></i> Volunteer Details</h2>
                <button class="close-modal" onclick="closeModal('volunteerDetailsModal')">&times;</button>
            </div>
            <div class="modal-body" id="volunteerDetailsContent">
                <!-- Content will be loaded here -->
            </div>
        </div>
    </div>

    <!-- Modal for Assignments -->
    <div id="assignmentsModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-tasks"></i> Volunteer Assignments</h2>
                <button class="close-modal" onclick="closeModal('assignmentsModal')">&times;</button>
            </div>
            <div class="modal-body" id="assignmentsContent">
                <!-- Content will be loaded here -->
            </div>
        </div>
    </div>

    <!-- Modal for Distribution Details -->
    <div id="distributionModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-box-open"></i> Distribution Details</h2>
                <button class="close-modal" onclick="closeModal('distributionModal')">&times;</button>
            </div>
            <div class="modal-body" id="distributionContent">
                <!-- Content will be loaded here -->
            </div>
        </div>
    </div>

    <script>
        // Volunteer selection
        let selectedVolunteers = new Set();
        
        function updateSelectedCount() {
            const checkboxes = document.querySelectorAll('.volunteer-checkbox:checked');
            const count = checkboxes.length;
            
            // Update counter display
            document.getElementById('selectedCount').textContent = count;
            
            // Update distribution select requirement
            const distributionSelect = document.getElementById('distributionSelect');
            if (count > 0 && !distributionSelect.value) {
                distributionSelect.style.borderColor = '#e74c3c';
            } else {
                distributionSelect.style.borderColor = '';
            }
            
            // Update selectedVolunteers set
            selectedVolunteers.clear();
            checkboxes.forEach(cb => {
                selectedVolunteers.add(cb.value);
            });
            
            // Update card styling
            document.querySelectorAll('.volunteer-card').forEach(card => {
                const checkbox = card.querySelector('.volunteer-checkbox');
                if (checkbox) {
                    card.classList.toggle('selected', checkbox.checked);
                }
            });
            
            // Update step styling
            const steps = document.querySelectorAll('.mass-assign-step');
            steps.forEach((step, index) => {
                if (index === 0 && count > 0) {
                    step.classList.add('step-active');
                } else if (index === 0) {
                    step.classList.remove('step-active');
                }
            });
            
            updateAssignButtonState();
        }
        
        // Clear all selections
        function clearSelections() {
            document.querySelectorAll('.volunteer-checkbox').forEach(cb => {
                cb.checked = false;
            });
            updateSelectedCount();
        }
        
        // Select all volunteers
        document.getElementById('select-all-volunteers')?.addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.volunteer-checkbox');
            checkboxes.forEach(cb => {
                cb.checked = this.checked;
            });
            updateSelectedCount();
        });
        
        // Distribution select change
        document.getElementById('distributionSelect')?.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const detailsDiv = document.getElementById('distributionDetails');
            const apiIndicator = document.getElementById('apiIndicator');
            const apiWarning = document.getElementById('apiWarning');
            const assignButton = document.getElementById('assignButton');
            
            if (this.value) {
                detailsDiv.style.display = 'block';
                document.getElementById('detailDisaster').textContent = selectedOption.dataset.disaster;
                document.getElementById('detailLocation').textContent = selectedOption.dataset.location;
                document.getElementById('detailDate').textContent = selectedOption.dataset.date;
                
                // Check if it's an API distribution
                const isApiDistribution = selectedOption.dataset.type === 'api';
                if (isApiDistribution) {
                    apiIndicator.style.display = 'inline-block';
                    apiWarning.style.display = 'block';
                    assignButton.disabled = true;
                    assignButton.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Cannot assign to API distribution';
                    assignButton.style.background = 'linear-gradient(135deg, #f39c12, #e74c3c)';
                } else {
                    apiIndicator.style.display = 'none';
                    apiWarning.style.display = 'none';
                    assignButton.style.background = 'linear-gradient(135deg, var(--success), #27ae60)';
                    updateAssignButtonState();
                }
                
                // Update step 2 styling
                const steps = document.querySelectorAll('.mass-assign-step');
                steps.forEach((step, index) => {
                    if (index <= 1 && this.value && selectedVolunteers.size > 0) {
                        step.classList.add('step-active');
                    } else if (index > 1) {
                        step.classList.remove('step-active');
                    }
                });
            } else {
                detailsDiv.style.display = 'none';
                updateAssignButtonState();
            }
        });
        
        // Update assign button state
        function updateAssignButtonState() {
            const distributionSelect = document.getElementById('distributionSelect');
            const selectedOption = distributionSelect.options[distributionSelect.selectedIndex];
            const isApiDistribution = selectedOption ? selectedOption.dataset.type === 'api' : false;
            const assignButton = document.getElementById('assignButton');
            
            if (selectedVolunteers.size === 0 || !distributionSelect.value) {
                assignButton.disabled = true;
                assignButton.innerHTML = '<i class="fas fa-user-check"></i> Assign Selected Volunteers';
            } else if (isApiDistribution) {
                assignButton.disabled = true;
                assignButton.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Cannot assign to API distribution';
            } else {
                assignButton.disabled = false;
                assignButton.innerHTML = '<i class="fas fa-user-check"></i> Assign Selected Volunteers';
            }
        }
        
        // Mass assignment form validation
        document.getElementById('massAssignForm')?.addEventListener('submit', function(e) {
            const distributionId = this.querySelector('[name="distribution_id"]').value;
            if (!distributionId) {
                e.preventDefault();
                alert('Please select a distribution first.');
                return;
            }
            
            const count = selectedVolunteers.size;
            if (count === 0) {
                e.preventDefault();
                alert('Please select at least one volunteer.');
                return;
            }
            
            // Check if it's an API distribution
            const selectedOption = document.getElementById('distributionSelect').options[document.getElementById('distributionSelect').selectedIndex];
            if (selectedOption.dataset.type === 'api') {
                e.preventDefault();
                alert('Cannot assign volunteers to API-based distributions. Please create the distribution in the system first.');
                return;
            }
            
            // Show confirmation
            const disasterName = selectedOption.dataset.disaster;
            const location = selectedOption.dataset.location;
            const date = selectedOption.dataset.date;
            
            const confirmMessage = `Assign ${count} volunteer(s) to:\n\n` +
                                  `Distribution: ${disasterName}\n` +
                                  `Location: ${location}\n` +
                                  `Date: ${date}\n\n` +
                                  `Proceed with assignment?`;
            
            if (!confirm(confirmMessage)) {
                e.preventDefault();
            }
        });
        
        // Modal functions
        function showModal(modalId) {
            document.getElementById(modalId).style.display = 'block';
            document.body.style.overflow = 'hidden'; // Prevent scrolling
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
            document.body.style.overflow = 'auto'; // Restore scrolling
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
                document.body.style.overflow = 'auto';
            }
        }
        
        // Show volunteer details modal
        function showVolunteerDetailsModal(volunteerId) {
            // Find volunteer data
            const volunteer = findVolunteerById(volunteerId);
            if (!volunteer) return;
            
            const stats = volunteerStats[volunteerId] || {};
            const initial = volunteer.name.charAt(0).toUpperCase();
            
            // Determine status badge class
            let statusClass = 'badge-available';
            const status = volunteer.availability_status.toLowerCase();
            if (status.includes('busy')) statusClass = 'badge-busy';
            if (status.includes('unavail')) statusClass = 'badge-unavailable';
            if (status.includes('leave')) statusClass = 'badge-on-leave';
            if (status.includes('inactive')) statusClass = 'badge-unavailable';
            
            // Build modal content
            let content = `
                <div class="modal-section">
                    <div style="display: flex; align-items: center; gap: 20px; margin-bottom: 20px;">
                        <div style="width: 80px; height: 80px; background: linear-gradient(135deg, var(--primary), var(--secondary)); color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 32px; font-weight: bold;">
                            ${initial}
                        </div>
                        <div>
                            <h3 style="margin: 0 0 5px 0; color: var(--dark);">${volunteer.name}</h3>
                            <div style="display: flex; gap: 10px; align-items: center;">
                                <span style="background: var(--light-gray); color: var(--gray); padding: 3px 10px; border-radius: 20px; font-size: 0.85rem;">
                                    VOL${volunteerId.toString().padStart(4, '0')}
                                </span>
                                <span class="status-badge ${statusClass}">
                                    ${volunteer.availability_status}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="modal-section">
                    <h3 class="modal-section-title"><i class="fas fa-info-circle"></i> Basic Information</h3>
                    <div class="info-grid">
                        <div class="info-item">
                            <span class="info-label">Email</span>
                            <span class="info-value">${volunteer.email || 'Not provided'}</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Phone</span>
                            <span class="info-value">${volunteer.phone || 'Not provided'}</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">NGO Affiliation</span>
                            <span class="info-value">${volunteer.ngo_affiliation}</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Skill Category</span>
                            <span class="info-value">${volunteer.skill_category}</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Address</span>
                            <span class="info-value">${volunteer.address || 'Not provided'}</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Registered Date</span>
                            <span class="info-value">${volunteer.registered_date || 'Unknown'}</span>
                        </div>
                    </div>
                </div>
                
                <div class="modal-section">
                    <h3 class="modal-section-title"><i class="fas fa-chart-bar"></i> Performance Statistics</h3>
                    <div class="info-grid">
                        <div class="info-item">
                            <span class="info-label">Total Assignments</span>
                            <span class="info-value">${stats.total_assignments || 0}</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Active Assignments</span>
                            <span class="info-value">${stats.active_assignments || 0}</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Families Helped</span>
                            <span class="info-value">${stats.families_helped || 0}</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Items Distributed</span>
                            <span class="info-value">${stats.items_distributed || 0}</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Total Quantity</span>
                            <span class="info-value">${stats.total_quantity || 0}</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Completed Assignments</span>
                            <span class="info-value">${stats.completed_assignments || 0}</span>
                        </div>
                    </div>
                </div>
            `;
            
            document.getElementById('volunteerDetailsContent').innerHTML = content;
            showModal('volunteerDetailsModal');
        }
        
        // Show assignments modal
        function showAssignmentsModal(volunteerId) {
            const assignments = volunteerAssignments[volunteerId] || [];
            const volunteer = findVolunteerById(volunteerId);
            
            let content = `
                <div class="modal-section">
                    <h3 class="modal-section-title">
                        <i class="fas fa-tasks"></i> 
                        Active Assignments for ${volunteer ? volunteer.name : 'Volunteer'}
                        <span style="font-size: 0.9rem; color: var(--gray);">(${assignments.length} assignments)</span>
                    </h3>
                </div>
            `;
            
            if (assignments.length === 0) {
                content += `
                    <div class="no-assignments">
                        <i class="fas fa-clipboard-list"></i>
                        <h3>No Active Assignments</h3>
                        <p>This volunteer doesn't have any active assignments at the moment.</p>
                    </div>
                `;
            } else {
                assignments.forEach((assignment, index) => {
                    const statusClass = assignment.status === 'Active' ? 'status-active' : 'status-assigned';
                    const date = new Date(assignment.date).toLocaleDateString('en-GB');
                    
                    content += `
                        <div class="assignment-card">
                            <div class="assignment-header">
                                <div class="assignment-title">
                                    Distribution #${assignment.distribution_id}
                                </div>
                                <span class="assignment-status ${statusClass}">
                                    ${assignment.status}
                                </span>
                            </div>
                            <div class="assignment-details">
                                <div><i class="fas fa-exclamation-triangle"></i> ${assignment.disaster_name || 'Unknown Disaster'}</div>
                                <div><i class="fas fa-map-marker-alt"></i> ${assignment.disaster_location || assignment.location || 'Unknown'}</div>
                                <div><i class="fas fa-calendar"></i> ${date}</div>
                                <div><i class="fas fa-user-tag"></i> ${assignment.role || 'Volunteer'}</div>
                            </div>
                            <div style="margin-top: 10px; font-size: 0.85rem; color: var(--gray);">
                                <i class="fas fa-clock"></i> Assigned: ${new Date(assignment.assigned_timestamp).toLocaleString()}
                            </div>
                        </div>
                    `;
                });
            }
            
            document.getElementById('assignmentsContent').innerHTML = content;
            showModal('assignmentsModal');
        }
        
        // Show distribution modal
        function showDistributionModal(distributionId, type) {
            // Find distribution data
            const distribution = activeDistributions.find(d => d.distribution_id == distributionId || d.distribution_id === distributionId);
            if (!distribution) return;
            
            const isApi = type === 'api';
            const assignedVols = assignedVolunteersByDistribution[distributionId] || [];
            
            let content = `
                <div class="modal-section">
                    <h3 class="modal-section-title">
                        <i class="fas fa-box-open"></i> 
                        Distribution Details
                        ${isApi ? '<span class="api-disaster-badge">API</span>' : ''}
                    </h3>
                    
                    <div class="info-grid">
                        <div class="info-item">
                            <span class="info-label">Distribution ID</span>
                            <span class="info-value">${isApi ? distributionId : '#DIST' + distributionId.toString().padStart(6, '0')}</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Disaster</span>
                            <span class="info-value">${distribution.disaster_name || 'Unknown'}</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Location</span>
                            <span class="info-value">${distribution.disaster_location || distribution.location || 'Unknown'}</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Date</span>
                            <span class="info-value">${new Date(distribution.date).toLocaleDateString('en-GB')}</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Status</span>
                            <span class="info-value">
                                <span style="background: ${distribution.status === 'In Progress' ? '#d4edda' : '#fff3cd'}; color: ${distribution.status === 'In Progress' ? '#155724' : '#856404'}; padding: 4px 10px; border-radius: 20px; font-size: 0.8rem; font-weight: 600;">
                                    ${distribution.status}
                                </span>
                            </span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Assigned Volunteers</span>
                            <span class="info-value">${assignedVols.length} volunteer(s)</span>
                        </div>
                    </div>
                </div>
                
                <div class="modal-section">
                    <h3 class="modal-section-title"><i class="fas fa-users"></i> Assigned Volunteers</h3>
            `;
            
            if (assignedVols.length === 0) {
                content += `
                    <div class="no-assignments">
                        <i class="fas fa-user-slash"></i>
                        <h3>No Volunteers Assigned</h3>
                        <p>No volunteers are currently assigned to this distribution.</p>
                    </div>
                `;
            } else {
                content += `<div style="display: grid; gap: 15px;">`;
                
                assignedVols.forEach(volId => {
                    const volunteer = findVolunteerById(volId);
                    if (volunteer) {
                        const initial = volunteer.name.charAt(0).toUpperCase();
                        content += `
                            <div style="display: flex; align-items: center; gap: 15px; padding: 15px; background: #f8f9fa; border-radius: 8px;">
                                <div style="width: 50px; height: 50px; background: linear-gradient(135deg, var(--primary), var(--secondary)); color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 1.2rem;">
                                    ${initial}
                                </div>
                                <div style="flex: 1;">
                                    <div style="font-weight: 600; color: var(--dark);">${volunteer.name}</div>
                                    <div style="font-size: 0.85rem; color: var(--gray);">${volunteer.skill_category} • ${volunteer.ngo_affiliation}</div>
                                </div>
                                <button class="action-btn status" style="padding: 6px 12px; font-size: 0.8rem;" onclick="showVolunteerDetailsModal(${volId})">
                                    <i class="fas fa-eye"></i> View
                                </button>
                            </div>
                        `;
                    }
                });
                
                content += `</div>`;
            }
            
            content += `</div>`;
            
            document.getElementById('distributionContent').innerHTML = content;
            showModal('distributionModal');
        }
        
        // Helper function to find volunteer by ID
        function findVolunteerById(volunteerId) {
            return window.volunteersData.find(v => v.volunteer_id == volunteerId);
        }
        
        // Export volunteers
        function exportVolunteers() {
            let csvContent = "data:text/csv;charset=utf-8,";
            csvContent += "ID,Name,Email,Phone,Skill,NGO,Status,Total Assignments,Active Assignments,Families Helped\n";
            
            <?php foreach ($filtered_volunteers as $volunteer): 
                $stats = $volunteer_stats[$volunteer['volunteer_id']] ?? [
                    'total_assignments' => 0,
                    'active_assignments' => 0,
                    'families_helped' => 0
                ];
            ?>
            csvContent += "<?php 
                echo $volunteer['volunteer_id'] . ',' . 
                     str_replace(',', ' ', addslashes($volunteer['name'])) . ',' . 
                     $volunteer['email'] . ',' . 
                     $volunteer['phone'] . ',' . 
                     str_replace(',', ' ', addslashes($volunteer['skill_category'])) . ',' . 
                     str_replace(',', ' ', addslashes($volunteer['ngo_affiliation'])) . ',' . 
                     $volunteer['availability_status'] . ',' . 
                     $stats['total_assignments'] . ',' . 
                     $stats['active_assignments'] . ',' . 
                     $stats['families_helped']; 
            ?>\n";
            <?php endforeach; ?>
            
            const encodedUri = encodeURI(csvContent);
            const link = document.createElement("a");
            link.setAttribute("href", encodedUri);
            link.setAttribute("download", "volunteers_export_" + new Date().toISOString().split('T')[0] + ".csv");
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
        
        // Export disaster data
        function exportDisasterData() {
            let csvContent = "data:text/csv;charset=utf-8,";
            csvContent += "ID,Name,Description,District,Severity,Status,Start Date,Affected People\n";
            
            <?php foreach ($disasters as $disaster): ?>
            csvContent += "<?php 
                echo $disaster['disaster_id'] . ',' . 
                     str_replace(',', ' ', addslashes($disaster['disaster_name'])) . ',' . 
                     str_replace(',', ' ', addslashes($disaster['description'])) . ',' . 
                     str_replace(',', ' ', $disaster['district']) . ',' . 
                     $disaster['severity'] . ',' . 
                     $disaster['status'] . ',' . 
                     $disaster['start_date'] . ',' . 
                     $disaster['affected_people']; 
            ?>\n";
            <?php endforeach; ?>
            
            const encodedUri = encodeURI(csvContent);
            const link = document.createElement("a");
            link.setAttribute("href", encodedUri);
            link.setAttribute("download", "disasters_export_" + new Date().toISOString().split('T')[0] + ".csv");
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
        
        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            updateSelectedCount();
            updateAssignButtonState();
            
            // Make PHP data available to JavaScript
            window.volunteersData = <?php echo json_encode($all_volunteers); ?>;
            window.volunteerStats = <?php echo json_encode($volunteer_stats); ?>;
            window.volunteerAssignments = <?php echo json_encode($volunteer_assignments); ?>;
            window.activeDistributions = <?php echo json_encode($active_distributions); ?>;
            window.assignedVolunteersByDistribution = <?php echo json_encode($assigned_volunteers_by_distribution); ?>;
        });
    </script>
</body>
</html>