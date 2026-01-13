<?php
// ========================================
// CREATE DISTRIBUTION PLAN - UPDATED WITH SPECIAL REQUESTS
// ========================================

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start timing for performance monitoring
$start_time = microtime(true);

require_once 'config.php';

// Start session for user data
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$database = new Database();
$db = $database->getConnection();

$disasters = [];
$approved_victims = [];
$error = '';
$success = '';

// API Configuration with reduced timeout
$DISASTER_API_URL = 'http://10.147.17.116:8000/disaster.php';
$VICTIM_API_URL = 'http://10.147.17.116:8000/victim.php';
$NEEDS_API_URL = 'http://10.147.17.116:8000/needs.php';
$BASIC_NEEDS_API_URL = 'http://10.147.17.224:8000/basic_needs_api.php';

/* ----------------------------------------
   FETCH DATA FROM APIS WITH CACHING
---------------------------------------- */
function fetchDataFromAPI($url, $timeout = 3) {
    $cache_key = md5($url);
    $cache_file = sys_get_temp_dir() . '/api_cache_' . $cache_key . '.json';
    $cache_duration = 300; // 5 minutes
    
    // Check cache first
    if (file_exists($cache_file) && (time() - filemtime($cache_file)) < $cache_duration) {
        $cached_data = file_get_contents($cache_file);
        $data = json_decode($cached_data, true);
        if ($data) {
            return ['success' => true, 'data' => $data, 'cached' => true];
        }
    }
    
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
            error_log("API timeout or error for: " . $url);
            return ['success' => false, 'error' => 'Server not responding: ' . $url];
        }
        
        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['success' => false, 'error' => 'Invalid JSON: ' . json_last_error_msg()];
        }
        
        // Cache the response
        file_put_contents($cache_file, json_encode($data));
        
        return ['success' => true, 'data' => $data, 'cached' => false];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

// Fetch API data
$disasterApiResult = fetchDataFromAPI($DISASTER_API_URL);
$victimApiResult = fetchDataFromAPI($VICTIM_API_URL);
$needsApiResult = fetchDataFromAPI($NEEDS_API_URL);
$basicNeedsApiResult = fetchDataFromAPI($BASIC_NEEDS_API_URL);

/* ----------------------------------------
   CREATE TABLES WITH PROPER INDEXES
---------------------------------------- */
try {
    $tables = [
        "victim_approvals" => "CREATE TABLE IF NOT EXISTS victim_approvals (
            approval_id INT PRIMARY KEY AUTO_INCREMENT,
            disaster_id INT NOT NULL,
            victim_id INT NOT NULL,
            approval_status ENUM('Pending', 'Approved', 'Rejected') DEFAULT 'Pending',
            approved_by VARCHAR(100),
            approved_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            comments TEXT,
            UNIQUE KEY unique_victim_disaster (disaster_id, victim_id),
            INDEX idx_approval_status (approval_status),
            INDEX idx_disaster_approval (disaster_id, approval_status)
        ) ENGINE=InnoDB",
        
        "distribution_items" => "CREATE TABLE IF NOT EXISTS distribution_items (
            item_id INT PRIMARY KEY AUTO_INCREMENT,
            distribution_id BIGINT UNSIGNED NOT NULL,
            victim_id INT NOT NULL,
            status ENUM('Scheduled', 'Dispatched', 'Delivered', 'Cancelled') DEFAULT 'Scheduled',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_distribution (distribution_id),
            INDEX idx_victim (victim_id),
            INDEX idx_status (status),
            INDEX idx_victim_status (victim_id, status)
        ) ENGINE=InnoDB",
        
        "coordinators" => "CREATE TABLE IF NOT EXISTS coordinators (
            coordinator_id INT PRIMARY KEY AUTO_INCREMENT,
            name VARCHAR(100) NOT NULL,
            ic_number VARCHAR(20) UNIQUE,
            phone VARCHAR(20) NOT NULL,
            email VARCHAR(100),
            department VARCHAR(50),
            position VARCHAR(50),
            status ENUM('Active', 'Inactive') DEFAULT 'Active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_status (status),
            INDEX idx_name (name)
        ) ENGINE=InnoDB",
        
        "distribution_resources" => "CREATE TABLE IF NOT EXISTS distribution_resources (
            allocation_id INT PRIMARY KEY AUTO_INCREMENT,
            distribution_id BIGINT UNSIGNED NOT NULL,
            resource_name VARCHAR(255),
            quantity_allocated INT NOT NULL,
            quantity_distributed INT DEFAULT 0,
            is_special_request BOOLEAN DEFAULT FALSE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_distribution (distribution_id),
            INDEX idx_special_request (is_special_request)
        ) ENGINE=InnoDB",
        
        "processed_disasters" => "CREATE TABLE IF NOT EXISTS processed_disasters (
            processed_id INT PRIMARY KEY AUTO_INCREMENT,
            disaster_id INT NOT NULL,
            processed_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            processed_by VARCHAR(100),
            notes TEXT,
            UNIQUE KEY unique_disaster (disaster_id),
            INDEX idx_disaster (disaster_id)
        ) ENGINE=InnoDB"
    ];
    
    foreach ($tables as $table_name => $create_sql) {
        if (!$db->query($create_sql)) {
            error_log("Failed to create table $table_name: " . $db->error);
        }
    }
    
} catch (Exception $e) {
    $error = "Database setup error: " . $e->getMessage();
}

/* ----------------------------------------
   CHECK IF DISASTER IS PROCESSED
---------------------------------------- */
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
   FETCH DISASTERS WITH APPROVED VICTIMS NOT YET DISTRIBUTED
---------------------------------------- */
try {
    $query_start = microtime(true);
    
    // Get disasters with approved victims that are NOT YET DISTRIBUTED and NOT PROCESSED
    $disasters_query = "
        SELECT DISTINCT 
            va.disaster_id,
            COUNT(DISTINCT va.victim_id) as approved_victims_count,
            SUM(CASE WHEN di.victim_id IS NOT NULL THEN 1 ELSE 0 END) as distributed_count,
            COUNT(DISTINCT CASE WHEN di.victim_id IS NULL THEN va.victim_id END) as available_victims_count
        FROM victim_approvals va
        LEFT JOIN distribution_items di ON va.victim_id = di.victim_id 
            AND di.status IN ('Scheduled', 'Dispatched', 'Delivered')
        WHERE va.approval_status = 'Approved'
        GROUP BY va.disaster_id
        HAVING COUNT(DISTINCT CASE WHEN di.victim_id IS NULL THEN va.victim_id END) > 0
        ORDER BY va.disaster_id DESC
    ";
    
    $result = $db->query($disasters_query);
    $query_time = microtime(true) - $query_start;
    error_log("Disasters query took: " . round($query_time, 4) . " seconds");
    
    if ($result) {
        $localDisasters = $result->fetch_all(MYSQLI_ASSOC);
        
        // Now get disaster details from API
        if ($disasterApiResult['success'] && is_array($disasterApiResult['data'])) {
            $apiDisasters = $disasterApiResult['data'];
            
            // Create lookup map for API disasters
            $apiDisasterMap = [];
            foreach ($apiDisasters as $apiDisaster) {
                $apiDisasterId = $apiDisaster['disaster_id'] ?? 0;
                $apiDisasterId = intval($apiDisasterId);
                if ($apiDisasterId > 0) {
                    $apiDisasterMap[$apiDisasterId] = $apiDisaster;
                }
            }
            
            foreach ($localDisasters as $localDisaster) {
                $disaster_id = $localDisaster['disaster_id'];
                $distributed_count = $localDisaster['distributed_count'];
                $approved_count = $localDisaster['approved_victims_count'];
                $available_count = $localDisaster['available_victims_count'];
                
                // Check if disaster is processed
                $is_processed = isDisasterProcessed($db, $disaster_id);
                
                // Only include disasters where not all victims are distributed AND not processed
                if ($available_count > 0 && !$is_processed) {
                    // Find this disaster in the API data using map
                    if (isset($apiDisasterMap[$disaster_id])) {
                        $apiDisaster = $apiDisasterMap[$disaster_id];
                        
                        // Get total victims from API for this disaster
                        $total_api_victims = 0;
                        if ($victimApiResult['success'] && is_array($victimApiResult['data'])) {
                            foreach ($victimApiResult['data'] as $victim) {
                                $victimDisasterId = intval($victim['disaster_id'] ?? 0);
                                if ($victimDisasterId == $disaster_id) {
                                    $total_api_victims++;
                                }
                            }
                        }
                        
                        $disasters[] = [
                            'disaster_id' => $disaster_id,
                            'Disaster_Name' => $apiDisaster['disaster_name'] ?? 'Unknown',
                            'Location' => $apiDisaster['district'] ?? 'Unknown',
                            'Disaster_Type' => $apiDisaster['severity'] ?? 'Unknown',
                            'status' => $apiDisaster['status'] ?? 'Unknown',
                            'approved_victims_count' => $approved_count,
                            'distributed_count' => $distributed_count,
                            'available_victims_count' => $available_count,
                            'total_api_victims' => $total_api_victims,
                            'remaining_victims' => $available_count,
                            'is_processed' => $is_processed
                        ];
                    }
                }
            }
        }
        $result->free();
    }
} catch (Exception $e) {
    $error = "Error loading disasters: " . $e->getMessage();
    error_log("Disaster loading error: " . $e->getMessage());
}

/* ----------------------------------------
   GET APPROVED VICTIMS FOR SELECTED DISASTER - WITH SPECIAL REQUESTS
---------------------------------------- */
if (isset($_GET['disaster_id']) && is_numeric($_GET['disaster_id'])) {
    $disaster_id = intval($_GET['disaster_id']);
    
    // Check if disaster is processed
    $disaster_processed = isDisasterProcessed($db, $disaster_id);
    
    if ($disaster_processed) {
        $error = "This disaster has been marked as processed and is no longer available for distribution planning.";
    } else {
        try {
            // Get approved victim IDs from local approval table EXCLUDING already distributed ones
            $approved_victims_query = "
                SELECT va.victim_id, va.approval_status, va.approved_at
                FROM victim_approvals va
                LEFT JOIN distribution_items di ON va.victim_id = di.victim_id 
                    AND di.status IN ('Scheduled', 'Dispatched', 'Delivered')
                WHERE va.disaster_id = ? 
                    AND va.approval_status = 'Approved'
                    AND di.victim_id IS NULL
                ORDER BY va.approved_at DESC
            ";
            
            $stmt = $db->prepare($approved_victims_query);
            if ($stmt === false) {
                throw new Exception("Prepare failed: " . $db->error);
            }
            
            $stmt->bind_param("i", $disaster_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $approvedVictimIds = $result->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
            
            if (empty($approvedVictimIds)) {
                $error = "No approved victims available for distribution for this disaster. All approved victims have already been distributed or are in progress.";
            } else {
                // Get victim details from API
                if ($victimApiResult['success'] && is_array($victimApiResult['data'])) {
                    $apiVictims = $victimApiResult['data'];
                    
                    // Create a map of victim IDs for quick lookup
                    $victimIdMap = [];
                    foreach ($approvedVictimIds as $victim) {
                        $victimIdMap[$victim['victim_id']] = $victim;
                    }
                    
                    // Get needs data from needs API
                    $needsByVictim = [];
                    if ($needsApiResult['success']) {
                        // Check different possible structures
                        if (isset($needsApiResult['data']['data']) && is_array($needsApiResult['data']['data'])) {
                            $apiNeeds = $needsApiResult['data']['data'];
                        } elseif (is_array($needsApiResult['data'])) {
                            $apiNeeds = $needsApiResult['data'];
                        } else {
                            $apiNeeds = [];
                        }
                        
                        foreach ($apiNeeds as $need) {
                            $needVictimId = intval($need['victim_id'] ?? 0);
                            
                            if (isset($victimIdMap[$needVictimId])) {
                                if (!isset($needsByVictim[$needVictimId])) {
                                    $needsByVictim[$needVictimId] = [];
                                }
                                
                                // Get resource name from various possible fields
                                $resource_name = $need['temp_resource_name'] ?? 
                                               'Unknown Resource';
                                
                                // Get quantity needed
                                $quantity_needed = $need['quantity_needed'] ?? '1';
                                
                                // Get priority
                                $priority = $need['priority'] ?? 'Medium';
                                
                                $needsByVictim[$needVictimId][] = [
                                    'need_id' => $need['need_id'] ?? 0,
                                    'resource_name' => $resource_name,
                                    'quantity_needed' => $quantity_needed,
                                    'priority' => $priority,
                                    'status' => $need['status'] ?? 'Pending',
                                    'has_baby' => isset($need['has_baby']) ? (($need['has_baby'] === 't' || $need['has_baby'] === true) ? true : false) : false,
                                    'has_elderly' => isset($need['has_elderly']) ? (($need['has_elderly'] === 't' || $need['has_elderly'] === true) ? true : false) : false,
                                    'has_disabled' => isset($need['has_disabled']) ? (($need['has_disabled'] === 't' || $need['has_disabled'] === true) ? true : false) : false
                                ];
                            }
                        }
                    }
                    
                    // Find victims in API data
                    foreach ($apiVictims as $victim) {
                        $victimId = intval($victim['victim_id'] ?? 0);
                        
                        if ($victimId && isset($victimIdMap[$victimId])) {
                            $victimNeeds = $needsByVictim[$victimId] ?? [];
                            
                            // Calculate overall priority based on needs
                            $overallPriority = 'Medium';
                            $hasHighPriority = false;
                            foreach ($victimNeeds as $need) {
                                if ($need['priority'] === 'High') {
                                    $hasHighPriority = true;
                                    break;
                                }
                            }
                            $overallPriority = $hasHighPriority ? 'High' : 'Medium';
                            
                            // Check for special needs from victim data
                            $has_baby = false;
                            if (isset($victim['has_baby'])) {
                                $has_baby = ($victim['has_baby'] === 't' || $victim['has_baby'] === true);
                            }
                            
                            $has_elderly = false;
                            if (isset($victim['has_elderly'])) {
                                $has_elderly = ($victim['has_elderly'] === 't' || $victim['has_elderly'] === true);
                            }
                            
                            $has_disabled = false;
                            if (isset($victim['has_disabled'])) {
                                $has_disabled = ($victim['has_disabled'] === 't' || $victim['has_disabled'] === true);
                            }
                            
                            // Also check from needs data
                            foreach ($victimNeeds as $need) {
                                $has_baby = $has_baby || $need['has_baby'];
                                $has_elderly = $has_elderly || $need['has_elderly'];
                                $has_disabled = $has_disabled || $need['has_disabled'];
                            }
                            
                            // Get victim details with correct field names
                            $full_name = $victim['full_name'] ?? 'Unknown';
                            $ic_number = $victim['ic_number'] ?? 'N/A';
                            $email = $victim['email'] ?? 'N/A';
                            $phone = $victim['phone'] ?? '';
                            $address = $victim['address'] ?? 'Unknown';
                            $city = $victim['city'] ?? '';
                            $postal_code = $victim['postal_code'] ?? '';
                            $district = $victim['district'] ?? 'N/A';
                            $family_members = $victim['family_members'] ?? 1;
                            $selected_shelter = $victim['selected_shelter'] ?? 'Not assigned';
                            
                            // Get special request from victim data
                            $special_request = $victim['special_request'] ?? '';
                            
                            // Parse special request for specific resource needs
                            $special_request_items = [];
                            if (!empty($special_request)) {
                                // Split by new lines or commas
                                $items = preg_split('/[\n\r,]+/', $special_request);
                                foreach ($items as $item) {
                                    $item = trim($item);
                                    if (!empty($item) && strlen($item) > 2) {
                                        $special_request_items[] = $item;
                                    }
                                }
                            }
                            
                            $approved_victims[] = [
                                'victim_id' => $victimId,
                                'full_name' => $full_name,
                                'ic_number' => $ic_number,
                                'email' => $email,
                                'phone' => $phone,
                                'address' => $address,
                                'city' => $city,
                                'postal_code' => $postal_code,
                                'district' => $district,
                                'family_members' => intval($family_members),
                                'selected_shelter' => $selected_shelter,
                                'has_baby' => $has_baby,
                                'has_elderly' => $has_elderly,
                                'has_disabled' => $has_disabled,
                                'priority' => $overallPriority,
                                'needs' => $victimNeeds,
                                'special_request' => $special_request,
                                'special_request_items' => $special_request_items,
                                'approval_status' => 'Approved',
                                'approved_at' => $victimIdMap[$victimId]['approved_at'] ?? date('Y-m-d H:i:s'),
                                'is_distributed' => 0
                            ];
                        }
                    }
                }
            }
            
            error_log("Found " . count($approved_victims) . " approved victims for disaster $disaster_id");
            
        } catch (Exception $e) {
            $error = "Error loading approved victims: " . $e->getMessage();
            error_log("Exception in get approved victims: " . $e->getMessage());
        }
    }
}

/* ----------------------------------------
   GET RESOURCES FOR DISTRIBUTION - WITH SPECIAL REQUESTS
---------------------------------------- */
$resources = [];
$basic_needs = [];
$special_request_items = [];

// Fetch basic needs from API
$standard_basic_needs = [];

try {
    // Get basic needs from API
    if ($basicNeedsApiResult['success'] && is_array($basicNeedsApiResult['data'])) {
        $apiBasicNeeds = $basicNeedsApiResult['data'];
        
        foreach ($apiBasicNeeds as $basic_need) {
            $name = $basic_need['name'] ?? 'Unknown Item';
            $quantity = isset($basic_need['quantity']) ? intval($basic_need['quantity']) : 100;
            $unit = $basic_need['unit'] ?? 'units';
            $location = $basic_need['location'] ?? 'Unknown';
            
            // Parse the name to extract quantity per family
            $quantity_per_family = 1; // Default
            
            // Try to extract quantity from name (e.g., "Rice 10kg" -> 10kg)
            if (preg_match('/(\d+)\s*(kg|g|ml|l|bags|packets|cans|pieces|units|liters|meters)/i', $name, $matches)) {
                $quantity_per_family = intval($matches[1]);
                $unit = strtolower($matches[2]);
            }
            
            // Determine type based on name
            $type = 'Basic Supplies';
            $name_lower = strtolower($name);
            
            if (strpos($name_lower, 'rice') !== false || 
                strpos($name_lower, 'water') !== false ||
                strpos($name_lower, 'food') !== false ||
                strpos($name_lower, 'noodle') !== false ||
                strpos($name_lower, 'milk') !== false) {
                $type = 'Food';
            } elseif (strpos($name_lower, 'blanket') !== false || 
                     strpos($name_lower, 'cloth') !== false ||
                     strpos($name_lower, 'jacket') !== false) {
                $type = 'Clothing';
            } elseif (strpos($name_lower, 'aid') !== false || 
                     strpos($name_lower, 'medical') !== false ||
                     strpos($name_lower, 'medicine') !== false) {
                $type = 'Medical';
            } elseif (strpos($name_lower, 'soap') !== false || 
                     strpos($name_lower, 'toothpaste') !== false ||
                     strpos($name_lower, 'hygiene') !== false) {
                $type = 'Hygiene';
            }
            
            $standard_basic_needs[] = [
                'id' => $basic_need['id'] ?? 0,
                'name' => $name,
                'type' => $type,
                'unit' => $unit,
                'is_special' => false,
                'quantity_per_family' => $quantity_per_family,
                'description' => $basic_need['description'] ?? "Basic relief item from " . $location,
                'location' => $location,
                'total_quantity_available' => $quantity,
                'expiry_date' => $basic_need['expiry_date'] ?? null
            ];
        }
        
        // Convert standard basic needs to our format
        foreach ($standard_basic_needs as $basic_need) {
            $basic_needs[] = [
                'id' => $basic_need['id'],
                'name' => $basic_need['name'],
                'type' => $basic_need['type'],
                'unit' => $basic_need['unit'],
                'is_special' => $basic_need['is_special'],
                'is_basic_need' => true,
                'quantity_per_family' => $basic_need['quantity_per_family'],
                'description' => $basic_need['description'],
                'location' => $basic_need['location'],
                'expiry_date' => $basic_need['expiry_date'],
                'quantity_available' => $basic_need['total_quantity_available'],
                'base_quantity' => $basic_need['total_quantity_available']
            ];
        }
        
        // Extract special requests from approved victims
        $specialRequestsMap = [];
        foreach ($approved_victims as $victim) {
            if (!empty($victim['special_request_items'])) {
                foreach ($victim['special_request_items'] as $item) {
                    $item_lower = strtolower(trim($item));
                    
                    // Skip generic terms
                    $generic_terms = ['help', 'assistance', 'support', 'aid', 'urgent', 'emergency', 'please'];
                    if (in_array($item_lower, $generic_terms) || strlen($item_lower) < 3) {
                        continue;
                    }
                    
                    // Categorize special request
                    $resourceType = 'Special Request';
                    $unit = 'units';
                    
                    if (strpos($item_lower, 'medicine') !== false || 
                        strpos($item_lower, 'medical') !== false ||
                        strpos($item_lower, 'pill') !== false ||
                        strpos($item_lower, 'tablet') !== false ||
                        strpos($item_lower, 'injection') !== false ||
                        strpos($item_lower, 'thermometer') !== false) {
                        $resourceType = 'Medical';
                    } elseif (strpos($item_lower, 'food') !== false || 
                             strpos($item_lower, 'rice') !== false ||
                             strpos($item_lower, 'water') !== false ||
                             strpos($item_lower, 'milk') !== false ||
                             strpos($item_lower, 'bread') !== false ||
                             strpos($item_lower, 'formula') !== false) {
                        $resourceType = 'Food';
                    } elseif (strpos($item_lower, 'cloth') !== false || 
                             strpos($item_lower, 'blanket') !== false ||
                             strpos($item_lower, 'jacket') !== false ||
                             strpos($item_lower, 'shirt') !== false ||
                             strpos($item_lower, 'pant') !== false ||
                             strpos($item_lower, 'diaper') !== false) {
                        $resourceType = 'Clothing';
                        $unit = 'pieces';
                    } elseif (strpos($item_lower, 'baby') !== false || 
                             strpos($item_lower, 'bottle') !== false) {
                        $resourceType = 'Baby Care';
                    } elseif (strpos($item_lower, 'elderly') !== false || 
                             strpos($item_lower, 'walker') !== false ||
                             strpos($item_lower, 'cane') !== false) {
                        $resourceType = 'Elderly Care';
                    } elseif (strpos($item_lower, 'wheelchair') !== false || 
                             strpos($item_lower, 'disabled') !== false ||
                             strpos($item_lower, 'accessibility') !== false) {
                        $resourceType = 'Disability Support';
                    }
                    
                    $key = md5($item_lower . $resourceType);
                    
                    if (!isset($specialRequestsMap[$key])) {
                        // Base quantity for special requests
                        $base_quantity = 50;
                        
                        $specialRequestsMap[$key] = [
                            'name' => ucwords(trim($item)),
                            'type' => $resourceType,
                            'unit' => $unit,
                            'is_special' => true,
                            'is_special_request' => true,
                            'base_quantity' => $base_quantity,
                            'quantity_available' => $base_quantity,
                            'quantity_per_family' => 1, // Typically 1 per requesting family
                            'requested_by' => [$victim['victim_id'] => $victim['full_name']]
                        ];
                    } else {
                        $specialRequestsMap[$key]['requested_by'][$victim['victim_id']] = $victim['full_name'];
                    }
                }
            }
        }
        
        // Convert to array for special requests
        $special_request_items = array_values($specialRequestsMap);
        
        // Combine all resources
        $resources = array_merge($basic_needs, $special_request_items);
        
        // Sort by type and name
        usort($resources, function($a, $b) {
            // Special requests come first
            if (isset($a['is_special_request']) && !isset($b['is_special_request'])) {
                return -1;
            }
            if (!isset($a['is_special_request']) && isset($b['is_special_request'])) {
                return 1;
            }
            
            $typeCompare = strcmp($a['type'], $b['type']);
            if ($typeCompare !== 0) {
                return $typeCompare;
            }
            return strcmp($a['name'], $b['name']);
        });
        
    } else {
        $error .= "<br>⚠️ Unable to load resources from Basic Needs API.";
    }
    
} catch (Exception $e) {
    $error = "Error loading resources: " . $e->getMessage();
    error_log("Resource loading error: " . $e->getMessage());
}

/* ----------------------------------------
   GET COORDINATORS
---------------------------------------- */
$coordinators = [];
try {
    $coordinators_query = "SELECT coordinator_id, name, phone, email, department, position 
                          FROM coordinators 
                          WHERE status = 'Active' 
                          ORDER BY name";
    $result = $db->query($coordinators_query);
    if ($result) {
        $coordinators = $result->fetch_all(MYSQLI_ASSOC);
        $result->free();
    }
} catch (Exception $e) {
    $error .= "<br>Error loading coordinators: " . $e->getMessage();
}

/* ----------------------------------------
   FORM SUBMISSION: CREATE DISTRIBUTION PLAN
---------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_distribution'])) {
    
    error_log("=== CREATE DISTRIBUTION FORM SUBMITTED ===");
    
    $disaster_id = intval($_POST['disaster_id']);
    $distribution_date = $_POST['distribution_date'];
    $distribution_time = $_POST['distribution_time'];
    $coordinator_id = isset($_POST['coordinator_id']) ? intval($_POST['coordinator_id']) : 0;
    $coordinator_name = $_POST['coordinator_name'] ?? '';
    $coordinator_contact = $_POST['coordinator_contact'] ?? '';
    $selected_victims = $_POST['selected_victims'] ?? [];
    $estimated_duration = intval($_POST['estimated_duration']);
    $volunteers_needed = intval($_POST['volunteers_needed']);
    $comments = $_POST['comments'] ?? '';
    
    $special_request_allocations = [];
    
    // Automatically calculate basic needs allocation based on number of families
    $selected_families_count = count($selected_victims);
    $basic_needs_allocations = [];
    
    foreach ($standard_basic_needs as $basic_need) {
        $quantity = $basic_need['quantity_per_family'] * $selected_families_count;
        $basic_needs_allocations[$basic_need['name']] = $quantity;
    }
    
    // Get special request allocations from form
    foreach ($_POST as $key => $value) {
        if (strpos($key, 'special_') === 0 && $value > 0) {
            $resource_key = str_replace('special_', '', $key);
            $special_request_allocations[$resource_key] = intval($value);
        }
    }
    
    try {
        // Check if disaster is processed
        if (isDisasterProcessed($db, $disaster_id)) {
            throw new Exception("This disaster has been marked as processed and cannot be used for new distribution plans.");
        }
        
        // Validate selected victims
        if (empty($selected_victims)) {
            throw new Exception("Please select at least one approved victim.");
        }
        
        // Validate coordinator
        if ($coordinator_id === 0 || empty($coordinator_name) || empty($coordinator_contact)) {
            throw new Exception("Please select a valid coordinator.");
        }
        
        error_log("Validation passed. Starting transaction...");
        
        // CHECK FOR DUPLICATE DISTRIBUTION
        if (!empty($selected_victims)) {
            $placeholders = str_repeat('?,', count($selected_victims) - 1) . '?';
            $check_duplicate_query = "
                SELECT COUNT(DISTINCT di.victim_id) as already_distributed_count
                FROM distribution_items di
                WHERE di.victim_id IN ($placeholders)
                AND di.status IN ('Scheduled', 'Dispatched', 'Delivered')
            ";
            
            $check_params = $selected_victims;
            $types = str_repeat('i', count($selected_victims));
            
            $check_stmt = $db->prepare($check_duplicate_query);
            if (!$check_stmt) {
                throw new Exception("Prepare failed for duplicate check: " . $db->error);
            }
            
            $check_stmt->bind_param($types, ...$check_params);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            $check_data = $check_result->fetch_assoc();
            $check_stmt->close();
            
            if ($check_data['already_distributed_count'] > 0) {
                throw new Exception("Some selected victims are already included in another distribution. Please refresh the page to see available victims.");
            }
        }
        
        // Check if all selected victims are approved for this disaster
        if (!empty($selected_victims)) {
            $placeholders = str_repeat('?,', count($selected_victims) - 1) . '?';
            $check_victims_query = "SELECT COUNT(*) as count FROM victim_approvals WHERE victim_id IN ($placeholders) AND disaster_id = ? AND approval_status != 'Approved'";
            
            $check_params = $selected_victims;
            $check_params[] = $disaster_id;
            $types = str_repeat('i', count($selected_victims)) . 'i';
            
            $check_stmt = $db->prepare($check_victims_query);
            if (!$check_stmt) {
                throw new Exception("Prepare failed for check victims: " . $db->error);
            }
            
            $check_stmt->bind_param($types, ...$check_params);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            $check_data = $check_result->fetch_assoc();
            $check_stmt->close();
            
            if ($check_data['count'] > 0) {
                throw new Exception("Some selected victims are not approved.");
            }
        }
        
        // Check resource availability for special requests only
        $insufficient_resources = [];
        
        foreach ($special_request_allocations as $resource_key => $quantity) {
            foreach ($special_request_items as $resource) {
                $check_key = md5(strtolower($resource['name']) . $resource['type']);
                if ($check_key === $resource_key && isset($resource['is_special_request'])) {
                    if ($quantity > $resource['quantity_available']) {
                        $insufficient_resources[] = [
                            'name' => $resource['name'],
                            'needed' => $quantity,
                            'available' => $resource['quantity_available'],
                            'unit' => $resource['unit'],
                            'type' => 'Special Request'
                        ];
                    }
                    break;
                }
            }
        }
        
        if (!empty($insufficient_resources)) {
            $warning = "⚠️ Insufficient special request items: ";
            foreach ($insufficient_resources as $resource) {
                $warning .= "{$resource['name']} (Needed: {$resource['needed']}, Available: {$resource['available']} {$resource['unit']}), ";
            }
            $error = rtrim($warning, ', ') . ". You can still proceed with partial distribution.";
        }
        
        // Start transaction
        $db->begin_transaction();
        
        // Create distribution record
        $combined_datetime = $distribution_date . ' ' . $distribution_time . ':00';
        
        error_log("Combined datetime: " . $combined_datetime);
        
        $plan_details = "DISTRIBUTION PLAN\n";
        $plan_details .= "=================\n";
        $plan_details .= "Time: $distribution_date at $distribution_time\n";
        $plan_details .= "Coordinator: $coordinator_name ($coordinator_contact)\n";
        $plan_details .= "Estimated Duration: {$estimated_duration} hours\n";
        $plan_details .= "Volunteers Needed: $volunteers_needed\n";
        $plan_details .= "Selected Victims: " . count($selected_victims) . " families\n";
        $plan_details .= "Total Family Members: " . array_sum(array_column($approved_victims, 'family_members')) . " individuals\n\n";
        
        if (!empty($selected_victims)) {
            $plan_details .= "SELECTED FAMILIES DETAILS:\n";
            $plan_details .= "-------------------------\n";
            foreach ($selected_victims as $victim_id) {
                foreach ($approved_victims as $victim) {
                    if ($victim['victim_id'] == $victim_id) {
                        $plan_details .= "- {$victim['full_name']} (ID: {$victim_id})\n";
                        $plan_details .= "  Family: {$victim['family_members']} members\n";
                        $plan_details .= "  Shelter: {$victim['selected_shelter']}\n";
                        $plan_details .= "  Contact: {$victim['phone']}\n";
                        $plan_details .= "  Address: {$victim['address']}, {$victim['city']}, {$victim['district']}\n";
                        if (!empty($victim['special_request'])) {
                            $plan_details .= "  Special Request: {$victim['special_request']}\n";
                        }
                        if ($victim['has_baby']) $plan_details .= "  👶 Has baby\n";
                        if ($victim['has_elderly']) $plan_details .= "  👴 Has elderly\n";
                        if ($victim['has_disabled']) $plan_details .= "  ♿ Has disabled\n";
                        $plan_details .= "\n";
                        break;
                    }
                }
            }
        }
        
        $plan_details .= "AUTOMATIC BASIC NEEDS ALLOCATION:\n";
        $plan_details .= "---------------------------------\n";
        $plan_details .= "Basic needs are automatically allocated to all selected families:\n\n";
        
        // Basic needs allocations (automatic)
        foreach ($standard_basic_needs as $basic_need) {
            $quantity = $basic_need['quantity_per_family'] * $selected_families_count;
            $plan_details .= "- {$basic_need['name']}: $quantity {$basic_need['unit']} ";
            $plan_details .= "({$basic_need['quantity_per_family']} {$basic_need['unit']} per family × $selected_families_count families)\n";
        }
        
        // Special request allocations
        if (!empty($special_request_allocations)) {
            $plan_details .= "\nSPECIAL REQUEST ALLOCATIONS:\n";
            $plan_details .= "----------------------------\n";
            foreach ($special_request_allocations as $resource_key => $quantity) {
                foreach ($special_request_items as $resource) {
                    $check_key = md5(strtolower($resource['name']) . $resource['type']);
                    if ($check_key === $resource_key && isset($resource['is_special_request'])) {
                        // Find which victims requested this
                        $requesting_victims = [];
                        if (isset($resource['requested_by']) && is_array($resource['requested_by'])) {
                            foreach ($resource['requested_by'] as $victim_id => $victim_name) {
                                if (in_array($victim_id, $selected_victims)) {
                                    $requesting_victims[] = $victim_name;
                                }
                            }
                        }
                        
                        $victim_list = !empty($requesting_victims) ? 
                            " (Requested by: " . implode(', ', array_slice($requesting_victims, 0, 3)) . 
                            (count($requesting_victims) > 3 ? ' and others' : '') . ")" : "";
                        
                        $plan_details .= "- [Special] {$resource['name']}: $quantity {$resource['unit']}$victim_list\n";
                        break;
                    }
                }
            }
        }
        
        if (!empty($comments)) {
            $plan_details .= "\nAdditional Comments: $comments\n";
        }
        $plan_details .= "\nPlan Created: " . date('Y-m-d H:i:s');

        $first_victim_id = !empty($selected_victims) ? intval($selected_victims[0]) : 0;

        // Insert into distribution table
        $distribution_query = "
            INSERT INTO distribution (
                victim_id, disaster_id, date, status, 
                quantity_sent, comments, coordinator_name, 
                coordinator_contact, estimated_duration, volunteers_needed
            ) VALUES (?, ?, ?, 'Planning', 0, ?, ?, ?, ?, ?)
        ";

        $dist_stmt = $db->prepare($distribution_query);
        if (!$dist_stmt) {
            throw new Exception("Prepare failed: " . $db->error);
        }

        $dist_stmt->bind_param(
            "iissssii",
            $first_victim_id,
            $disaster_id,
            $combined_datetime,
            $plan_details,
            $coordinator_name,
            $coordinator_contact,
            $estimated_duration,
            $volunteers_needed
        );

        error_log("Executing distribution insert...");
        
        if (!$dist_stmt->execute()) {
            throw new Exception("Error creating distribution plan: " . $dist_stmt->error);
        }

        // Get the auto-generated distribution_id
        $distribution_id = $dist_stmt->insert_id;
        $dist_stmt->close();
        
        error_log("Distribution created with ID: " . $distribution_id);
        
        // Create distribution items for each victim
        foreach ($selected_victims as $victim_id) {
            $victim_item_query = "
                INSERT INTO distribution_items (
                    distribution_id, victim_id, status
                ) VALUES (?, ?, 'Scheduled')
            ";
            $item_stmt = $db->prepare($victim_item_query);
            if (!$item_stmt) {
                throw new Exception("Prepare failed for victim items: " . $db->error);
            }
            $item_stmt->bind_param("ii", $distribution_id, $victim_id);
            if (!$item_stmt->execute()) {
                throw new Exception("Error adding victim to distribution: " . $item_stmt->error);
            }
            $item_stmt->close();
        }
        
        error_log("Added " . count($selected_victims) . " victims to distribution_items");
        
        // Create resource allocations for basic needs (automatic)
        foreach ($basic_needs_allocations as $resource_name => $quantity) {
            if ($quantity > 0) {
                // Create allocation record
                $allocation_query = "
                    INSERT INTO distribution_resources (
                        distribution_id, resource_name, quantity_allocated, is_special_request
                    ) VALUES (?, ?, ?, FALSE)
                ";
                $alloc_stmt = $db->prepare($allocation_query);
                if (!$alloc_stmt) {
                    throw new Exception("Prepare failed for basic needs allocation: " . $db->error);
                }
                
                $alloc_stmt->bind_param("isi", $distribution_id, $resource_name, $quantity);
                
                if (!$alloc_stmt->execute()) {
                    throw new Exception("Error allocating basic needs: " . $alloc_stmt->error);
                }
                $alloc_stmt->close();
            }
        }
        
        // Create resource allocations for special requests
        foreach ($special_request_allocations as $resource_key => $quantity) {
            if ($quantity > 0) {
                foreach ($special_request_items as $resource) {
                    $check_key = md5(strtolower($resource['name']) . $resource['type']);
                    if ($check_key === $resource_key && isset($resource['is_special_request'])) {
                        // Create allocation record
                        $allocation_query = "
                            INSERT INTO distribution_resources (
                                distribution_id, resource_name, quantity_allocated, is_special_request
                            ) VALUES (?, ?, ?, TRUE)
                        ";
                        $alloc_stmt = $db->prepare($allocation_query);
                        if (!$alloc_stmt) {
                            throw new Exception("Prepare failed for special request allocation: " . $db->error);
                        }
                        
                        $resource_name = $resource['name'];
                        
                        $alloc_stmt->bind_param("isi", $distribution_id, $resource_name, $quantity);
                        
                        if (!$alloc_stmt->execute()) {
                            throw new Exception("Error allocating special requests: " . $alloc_stmt->error);
                        }
                        $alloc_stmt->close();
                        break;
                    }
                }
            }
        }
        
        error_log("Added " . count($basic_needs_allocations) . " basic needs and " . count($special_request_allocations) . " special request allocations");
        
        $db->commit();
        
        error_log("Transaction committed successfully!");
        
        // Generate a display ID for user (DIST001 format)
        $display_id = 'DIST' . str_pad($distribution_id, 3, '0', STR_PAD_LEFT);
        
        $success = "✅ Distribution plan created successfully!";
        $success .= "<br><strong>Distribution ID: $display_id (Database ID: $distribution_id)</strong>";
        $success .= "<br><small>Date: $distribution_date at $distribution_time</small>";
        $success .= "<br><small>Coordinator: $coordinator_name</small>";
        $success .= "<br><small>Families: " . count($selected_victims) . "</small>";
        $success .= "<br><small>Total Family Members: " . array_sum(array_column($approved_victims, 'family_members')) . "</small>";
        $success .= "<br><small>Basic needs: " . count($standard_basic_needs) . " types (automatically allocated)</small>";
        $success .= "<br><small>Special requests: " . count($special_request_allocations) . " types</small>";
        
        // Clear the form by redirecting to same page without disaster_id
        $success .= "<br><div class='mt-3'>";
        $success .= "<a href='assign_volunteer.php?distribution_id=$distribution_id' class='btn btn-success'>Assign Volunteers</a> ";
        $success .= "<a href='view_distribution.php?id=$distribution_id' class='btn btn-info'>View Details</a> ";
        $success .= "<a href='create_distribution.php' class='btn btn-secondary'>Create Another</a>";
        $success .= "</div>";
        
        // Clear selected victims to prevent duplicate submission
        $approved_victims = [];
        unset($_GET['disaster_id']);
        
    } catch (Exception $e) {
        error_log("EXCEPTION CAUGHT: " . $e->getMessage());
        if (isset($db) && method_exists($db, 'rollback')) {
            $db->rollback();
            error_log("Transaction rolled back!");
        }
        $error = "Error: " . $e->getMessage();
    }
}

// Calculate execution time
$end_time = microtime(true);
$execution_time = $end_time - $start_time;
error_log("Total page execution time: " . round($execution_time, 4) . " seconds");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Distribution Plan</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* (CSS styles remain exactly the same as in your original code) */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
            color: white;
        }
        
        .header h1 {
            font-size: 2.5rem;
            margin-bottom: 10px;
            text-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }
        
        .header p {
            font-size: 1.1rem;
            opacity: 0.9;
        }
        
        .main-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            padding: 30px;
            margin-bottom: 30px;
        }
        
        .step-indicator {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-bottom: 30px;
        }
        
        .step {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            background: #e0e0e0;
            color: #666;
            position: relative;
            font-size: 0.9rem;
        }
        
        .step.active {
            background: #4CAF50;
            color: white;
            transform: scale(1.1);
        }
        
        .step.completed {
            background: #4CAF50;
            color: white;
        }
        
        .step:not(:last-child):after {
            content: '';
            position: absolute;
            left: 100%;
            top: 50%;
            width: 15px;
            height: 2px;
            background: #e0e0e0;
        }
        
        .step.completed:not(:last-child):after {
            background: #4CAF50;
        }
        
        .step-label {
            position: absolute;
            top: 100%;
            margin-top: 8px;
            white-space: nowrap;
            font-size: 0.8rem;
            color: #666;
        }
        
        .step.active .step-label {
            color: #4CAF50;
            font-weight: bold;
        }
        
        .section-title {
            font-size: 1.3rem;
            margin-bottom: 15px;
            color: #333;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .section-title i {
            color: #667eea;
        }
        
        .alert {
            padding: 12px 15px;
            border-radius: 10px;
            margin-bottom: 15px;
            border: none;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 0.9rem;
        }
        
        .alert-success {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            color: #155724;
            border-left: 5px solid #28a745;
        }
        
        .alert-error {
            background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
            color: #721c24;
            border-left: 5px solid #dc3545;
        }
        
        .alert-info {
            background: linear-gradient(135deg, #d1ecf1 0%, #bee5eb 100%);
            color: #0c5460;
            border-left: 5px solid #17a2b8;
        }
        
        .alert-warning {
            background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
            color: #856404;
            border-left: 5px solid #ffc107;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 6px;
            font-weight: 600;
            color: #444;
            font-size: 0.9rem;
        }
        
        .form-control {
            width: 100%;
            padding: 12px 14px;
            border: 2px solid #e1e1e1;
            border-radius: 8px;
            font-size: 0.95rem;
            transition: all 0.3s;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        
        .btn {
            padding: 12px 24px;
            border-radius: 8px;
            border: none;
            font-weight: 600;
            font-size: 0.95rem;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }
        
        .btn-success {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
        }
        
        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(40, 167, 69, 0.3);
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-lg {
            padding: 15px 30px;
            font-size: 1rem;
        }
        
        .victims-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 15px;
            max-height: 500px;
            overflow-y: auto;
            padding: 8px;
        }
        
        .victim-card {
            background: white;
            border: 2px solid #e1e1e1;
            border-radius: 10px;
            padding: 15px;
            cursor: pointer;
            transition: all 0.3s;
            position: relative;
        }
        
        .victim-card:hover {
            border-color: #667eea;
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.1);
        }
        
        .victim-card.selected {
            border-color: #4CAF50;
            background: linear-gradient(135deg, #f8fff8 0%, #f0fff0 100%);
        }
        
        .victim-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 12px;
        }
        
        .victim-name {
            font-size: 1.1rem;
            font-weight: 600;
            color: #333;
        }
        
        .priority-badge {
            padding: 3px 10px;
            border-radius: 15px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .priority-high {
            background: #dc3545;
            color: white;
        }
        
        .priority-medium {
            background: #ffc107;
            color: #212529;
        }
        
        .priority-low {
            background: #28a745;
            color: white;
        }
        
        .victim-details {
            font-size: 0.85rem;
            color: #666;
            margin-bottom: 12px;
        }
        
        .victim-details i {
            width: 18px;
            color: #667eea;
        }
        
        .special-request {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 10px;
            border-radius: 6px;
            margin: 10px 0;
            font-size: 0.85rem;
        }
        
        .special-request-title {
            font-weight: 600;
            color: #856404;
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .special-request-content {
            color: #856404;
            font-style: italic;
        }
        
        .special-needs {
            display: flex;
            gap: 6px;
            margin-top: 8px;
            flex-wrap: wrap;
        }
        
        .needs-badge {
            padding: 3px 8px;
            border-radius: 10px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        .needs-baby {
            background: #fff3cd;
            color: #856404;
        }
        
        .needs-elderly {
            background: #d1ecf1;
            color: #0c5460;
        }
        
        .needs-disabled {
            background: #f8d7da;
            color: #721c24;
        }
        
        .needs-container {
            margin-top: 8px;
            padding: 8px;
            background: #f8f9fa;
            border-radius: 6px;
            font-size: 0.8rem;
        }
        
        .need-item {
            display: flex;
            justify-content: space-between;
            padding: 4px 0;
            border-bottom: 1px solid #eee;
        }
        
        .need-item:last-child {
            border-bottom: none;
        }
        
        .select-all-container {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 15px;
            border: 2px solid #dee2e6;
        }
        
        .summary-bar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px;
            border-radius: 10px;
            margin: 15px 0;
        }
        
        .summary-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        
        .stat-card {
            background: rgba(255, 255, 255, 0.1);
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            backdrop-filter: blur(10px);
        }
        
        .stat-number {
            font-size: 1.5rem;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .stat-label {
            font-size: 0.8rem;
            opacity: 0.9;
        }
        
        /* RESOURCE GRID LAYOUT */
        .resource-grid-container {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        
        .resource-category {
            background: white;
            border: 2px solid #e1e1e1;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
        }
        
        .category-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .category-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: #667eea;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .category-count {
            background: #667eea;
            color: white;
            padding: 4px 10px;
            border-radius: 15px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        
        .resources-columns {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
            gap: 15px;
            max-height: 600px;
            overflow-y: auto;
            padding: 5px;
        }
        
        .resource-item {
            background: #f8f9fa;
            border: 1px solid #e1e1e1;
            border-radius: 8px;
            padding: 15px;
            transition: all 0.3s;
        }
        
        .resource-item:hover {
            border-color: #667eea;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .special-request-item {
            background: #fff3cd;
            border: 1px solid #ffc107;
        }
        
        .basic-need-item {
            background: #e8f5e9;
            border: 1px solid #4CAF50;
        }
        
        .resource-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 12px;
        }
        
        .resource-name {
            font-weight: 600;
            color: #333;
            font-size: 1rem;
            flex: 1;
        }
        
        .basic-need-badge {
            background: #28a745;
            color: white;
            padding: 3px 8px;
            border-radius: 10px;
            font-size: 0.75rem;
            font-weight: 500;
            margin-left: 5px;
        }
        
        .special-request-badge {
            background: #dc3545;
            color: white;
            padding: 3px 8px;
            border-radius: 10px;
            font-size: 0.75rem;
            font-weight: 500;
            margin-left: 5px;
        }
        
        .resource-unit-badge {
            background: #17a2b8;
            color: white;
            padding: 3px 8px;
            border-radius: 10px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        .available-badge {
            background: #28a745;
            color: white;
            padding: 4px 10px;
            border-radius: 10px;
            font-size: 0.8rem;
            font-weight: 500;
            white-space: nowrap;
        }
        
        .warning-badge {
            background: #ffc107;
            color: #212529;
            padding: 4px 10px;
            border-radius: 10px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .resource-stats {
            font-size: 0.85rem;
            color: #666;
            margin-bottom: 15px;
        }
        
        .stat-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
            padding: 3px 0;
        }
        
        .stat-label-small {
            color: #666;
        }
        
        .stat-value {
            font-weight: 600;
            color: #333;
        }
        
        .requested-by {
            background: #e9ecef;
            padding: 8px;
            border-radius: 6px;
            margin: 10px 0;
            font-size: 0.8rem;
        }
        
        .requested-by-title {
            font-weight: 600;
            margin-bottom: 5px;
            color: #495057;
        }
        
        .requested-by-list {
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
        }
        
        .requested-by-item {
            background: #fff;
            padding: 3px 8px;
            border-radius: 12px;
            border: 1px solid #dee2e6;
            font-size: 0.75rem;
        }
        
        .resource-controls {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 10px;
        }
        
        .quantity-input {
            width: 100px;
            text-align: center;
            padding: 8px;
            border: 2px solid #e1e1e1;
            border-radius: 6px;
            font-size: 0.9rem;
        }
        
        .quantity-label {
            font-size: 0.85rem;
            color: #666;
            flex: 1;
            text-align: right;
        }
        
        .auto-allocation-note {
            background: #e8f5e9;
            padding: 10px;
            border-radius: 6px;
            margin-top: 10px;
            border-left: 4px solid #4CAF50;
            font-size: 0.85rem;
        }
        
        .form-actions {
            display: flex;
            gap: 12px;
            margin-top: 30px;
            padding-top: 15px;
            border-top: 2px solid #e1e1e1;
        }
        
        @media (max-width: 768px) {
            .main-card {
                padding: 20px;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .victims-grid {
                grid-template-columns: 1fr;
            }
            
            .resources-columns {
                grid-template-columns: 1fr;
            }
            
            .form-actions {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
            }
            
            .resource-controls {
                flex-direction: column;
                align-items: stretch;
            }
            
            .quantity-label {
                text-align: left;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-hands-helping"></i> Create Distribution Plan</h1>
            <p>Organize relief operations for approved disaster victims</p>
        </div>
        
        <div class="main-card">
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle fa-2x"></i>
                    <div>
                        <h3>Plan Created Successfully!</h3>
                        <?php echo $success; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-triangle fa-2x"></i>
                    <div>
                        <h3>Error</h3>
                        <?php echo $error; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Step Indicator -->
            <div class="step-indicator">
                <div class="step <?php echo isset($_GET['disaster_id']) ? 'completed' : 'active'; ?>">
                    1
                    <span class="step-label">Select Disaster</span>
                </div>
                <div class="step <?php echo isset($_GET['disaster_id']) && !empty($approved_victims) ? 'active' : ''; ?>">
                    2
                    <span class="step-label">Select Victims</span>
                </div>
                <div class="step <?php echo isset($_GET['disaster_id']) && !empty($approved_victims) ? '' : ''; ?>">
                    3
                    <span class="step-label">Allocate Resources</span>
                </div>
                <div class="step <?php echo isset($_GET['disaster_id']) && !empty($approved_victims) ? '' : ''; ?>">
                    4
                    <span class="step-label">Plan Details</span>
                </div>
            </div>
            
            <!-- Step 1: Select Disaster -->
            <?php if (!isset($_GET['disaster_id'])): ?>
                <div class="section-title">
                    <i class="fas fa-map-marked-alt"></i>
                    <h2>Select Disaster Area</h2>
                </div>
                
                <form method="GET" action="" id="disaster-selection-form">
                    <div class="form-group">
                        <label class="form-label">Choose Disaster Area *</label>
                        <select class="form-control" name="disaster_id" required onchange="this.form.submit()">
                            <option value="">Select a disaster area...</option>
                            <?php if (empty($disasters)): ?>
                                <option value="" disabled>No disasters with available victims found</option>
                            <?php else: ?>
                                <?php foreach ($disasters as $disaster): ?>
                                <option value="<?php echo $disaster['disaster_id']; ?>">
                                    <?php echo htmlspecialchars($disaster['Disaster_Name']); ?> - 
                                    <?php echo htmlspecialchars($disaster['Location']); ?>
                                    <?php if ($disaster['is_processed']): ?>
                                        <span style="color: #dc3545;">(Processed)</span>
                                    <?php else: ?>
                                        (<?php echo $disaster['available_victims_count']; ?> victims available)
                                    <?php endif; ?>
                                </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                        <?php if (empty($disasters)): ?>
                            <div class="alert alert-warning mt-2">
                                <i class="fas fa-exclamation-triangle"></i>
                                No disasters with available victims found. All approved victims have been distributed or disasters are marked as processed.
                            </div>
                        <?php else: ?>
                            <small class="form-text">Only disasters with available (not yet distributed) victims are shown. Processed disasters are marked in red.</small>
                        <?php endif; ?>
                        </div>
                    </div>
                </form>
            <?php endif; ?>
            
            <!-- Step 2: Select Victims -->
            <?php if (isset($_GET['disaster_id']) && !empty($approved_victims)): 
                $selected_disaster = null;
                foreach ($disasters as $disaster) {
                    if ($disaster['disaster_id'] == $_GET['disaster_id']) {
                        $selected_disaster = $disaster;
                        break;
                    }
                }
            ?>
                <form method="POST" id="create-distribution-form">
                    <input type="hidden" name="create_distribution" value="1">
                    <input type="hidden" name="disaster_id" value="<?php echo $_GET['disaster_id']; ?>">
                    
                    <div class="section-title">
                        <i class="fas fa-users"></i>
                        <h2>Select Families for Distribution</h2>
                        <span class="available-badge"><?php echo count($approved_victims); ?> available</span>
                    </div>
                    
                    <?php if ($selected_disaster): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i>
                            <div>
                                <strong><?php echo htmlspecialchars($selected_disaster['Disaster_Name']); ?></strong> - 
                                <?php echo htmlspecialchars($selected_disaster['Location']); ?>
                                <br>
                                <small>
                                    <?php echo $selected_disaster['available_victims_count']; ?> victims available for distribution
                                    <?php if ($selected_disaster['distributed_count'] > 0): ?>
                                        (<?php echo $selected_disaster['distributed_count']; ?> already distributed)
                                    <?php endif; ?>
                                </small>
                                <?php if ($selected_disaster['total_api_victims'] > 0): ?>
                                <br>
                                <small>
                                    Total victims in disaster: <?php echo $selected_disaster['total_api_victims']; ?> | 
                                    Approved: <?php echo $selected_disaster['approved_victims_count']; ?> | 
                                    Available: <?php echo $selected_disaster['available_victims_count']; ?>
                                </small>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <div class="select-all-container">
                        <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                            <input type="checkbox" id="select-all-victims" class="victim-checkbox" style="transform: scale(1.3);">
                            <div>
                                <strong style="font-size: 1.1rem;">Select All Families</strong>
                                <div style="font-size: 0.9rem; color: #666; margin-top: 5px;">
                                    Total available for distribution: <strong><?php echo count($approved_victims); ?></strong> families
                                </div>
                            </div>
                        </label>
                    </div>
                    
                    <div class="victims-grid">
                        <?php foreach ($approved_victims as $victim): 
                            $priorityClass = 'priority-' . strtolower($victim['priority']);
                            $needs_count = count($victim['needs'] ?? []);
                            $has_special_request = !empty($victim['special_request']);
                        ?>
                        <div class="victim-card">
                            <div class="victim-header">
                                <div>
                                    <div class="victim-name"><?php echo htmlspecialchars($victim['full_name']); ?></div>
                                    <div style="font-size: 0.9rem; color: #666; margin-top: 5px;">
                                        <i class="fas fa-id-card"></i> <?php echo htmlspecialchars($victim['ic_number']); ?>
                                    </div>
                                    <div style="font-size: 0.8rem; color: #28a745; margin-top: 3px;">
                                        <i class="fas fa-check-circle"></i> Approved on <?php echo date('M d, Y', strtotime($victim['approved_at'])); ?>
                                    </div>
                                </div>
                                <span class="priority-badge <?php echo $priorityClass; ?>">
                                    <?php echo $victim['priority']; ?>
                                </span>
                            </div>
                            
                            <div class="victim-details">
                                <div style="margin-bottom: 8px;">
                                    <i class="fas fa-home"></i> <?php echo htmlspecialchars($victim['address']); ?>, <?php echo htmlspecialchars($victim['city']); ?>, <?php echo htmlspecialchars($victim['district']); ?>
                                </div>
                                <?php if (!empty($victim['phone'])): ?>
                                <div style="margin-bottom: 8px;">
                                    <i class="fas fa-phone"></i> <?php echo htmlspecialchars($victim['phone']); ?>
                                </div>
                                <?php endif; ?>
                                <div style="margin-bottom: 8px;">
                                    <i class="fas fa-hotel"></i> Shelter: <?php echo htmlspecialchars($victim['selected_shelter']); ?>
                                </div>
                                <div>
                                    <i class="fas fa-user-friends"></i> Family members: <?php echo $victim['family_members']; ?>
                                </div>
                            </div>
                            
                            <?php if ($has_special_request): ?>
                                <div class="special-request">
                                    <div class="special-request-title">
                                        <i class="fas fa-exclamation-circle"></i> Special Request
                                    </div>
                                    <div class="special-request-content">
                                        "<?php echo htmlspecialchars($victim['special_request']); ?>"
                                    </div>
                                    <?php if (!empty($victim['special_request_items'])): ?>
                                        <div style="margin-top: 5px; font-size: 0.8rem; color: #856404;">
                                            <strong>Items needed:</strong> 
                                            <?php echo implode(', ', array_slice($victim['special_request_items'], 0, 3)); ?>
                                            <?php if (count($victim['special_request_items']) > 3): ?>
                                                and <?php echo count($victim['special_request_items']) - 3; ?> more
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($victim['needs'])): ?>
                                <div class="needs-container">
                                    <div style="font-size: 0.9rem; font-weight: 600; margin-bottom: 8px; color: #444;">
                                        Additional Needs (<?php echo $needs_count; ?> items):
                                    </div>
                                    <?php foreach ($victim['needs'] as $need): ?>
                                        <div class="need-item">
                                            <span>
                                                <?php echo htmlspecialchars($need['resource_name']); ?>
                                                <?php if ($need['priority'] === 'High'): ?>
                                                    <span class="priority-need-badge" style="margin-left: 5px; font-size: 0.7rem;">
                                                        High Priority
                                                    </span>
                                                <?php endif; ?>
                                            </span>
                                            <span>
                                                <strong><?php echo $need['quantity_needed']; ?></strong>
                                                <span class="priority-badge priority-<?php echo strtolower($need['priority']); ?>" style="margin-left: 10px; font-size: 0.7rem;">
                                                    <?php echo $need['priority']; ?>
                                                </span>
                                            </span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($victim['has_baby'] || $victim['has_elderly'] || $victim['has_disabled']): ?>
                                <div class="special-needs">
                                    <?php if ($victim['has_baby']): ?>
                                        <span class="needs-badge needs-baby">
                                            <i class="fas fa-baby"></i> Baby Care Needed
                                        </span>
                                    <?php endif; ?>
                                    <?php if ($victim['has_elderly']): ?>
                                        <span class="needs-badge needs-elderly">
                                            <i class="fas fa-walking-cane"></i> Elderly Care Needed
                                        </span>
                                    <?php endif; ?>
                                    <?php if ($victim['has_disabled']): ?>
                                        <span class="needs-badge needs-disabled">
                                            <i class="fas fa-wheelchair"></i> Disability Support Needed
                                        </span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            
                            <input type="checkbox" 
                                   name="selected_victims[]" 
                                   value="<?php echo $victim['victim_id']; ?>" 
                                   class="victim-checkbox"
                                   style="position: absolute; top: 20px; right: 20px; transform: scale(1.3);">
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="summary-bar">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <h3 style="color: white; margin: 0;">Selected Families</h3>
                                <p style="color: rgba(255,255,255,0.9); margin: 5px 0 0 0;">
                                    Total selected: <strong id="selected-count">0</strong> families
                                </p>
                            </div>
                            <button type="button" class="btn btn-success" onclick="proceedToResources()" id="proceed-btn" disabled>
                                <i class="fas fa-arrow-right"></i> Proceed to Special Requests
                            </button>
                        </div>
                    </div>
                    
                    <!-- Step 3: Resource Allocation -->
                    <div id="resource-section" style="display: none;">
                        <div class="section-title">
                            <i class="fas fa-boxes"></i>
                            <h2>Allocate Special Request Items</h2>
                            <span class="available-badge" id="special-request-count-badge">0 items</span>
                        </div>
                        
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i>
                            <div>
                                <strong>Resource Allocation Information</strong>
                                <br>
                                <small><strong>Basic Needs:</strong> Automatically allocated to all selected families (fetched from API)</small>
                                <br>
                                <small><strong>Special Requests:</strong> Only showing items requested by selected families. Allocate based on actual needs.</small>
                            </div>
                        </div>
                        
                        <!-- Basic Needs Section (Informational Only) -->
                        <?php if (!empty($standard_basic_needs)): ?>
                            <div class="resource-category" style="border-color: #4CAF50;">
                                <div class="category-header">
                                    <div class="category-title">
                                        <i class="fas fa-check-circle"></i> 
                                        Basic Needs (Automatically Allocated)
                                        <span class="category-count"><?php echo count($standard_basic_needs); ?> items</span>
                                    </div>
                                    <div style="font-size: 0.9rem; color: #28a745;">
                                        <i class="fas fa-info-circle"></i> Standard items fetched from Basic Needs API
                                    </div>
                                </div>
                                
                                <div style="margin: 15px 0 10px 0; padding-left: 10px; border-left: 3px solid #4CAF50;">
                                    <h4 style="margin: 0; color: #495057; font-size: 1rem;">
                                        <i class="fas fa-tag"></i> Standard Relief Package
                                        <small style="color: #6c757d; font-size: 0.85rem;">
                                            (automatically calculated: <strong><span class="family-count-display">0</span> families</strong> selected)
                                        </small>
                                    </h4>
                                </div>
                                
                                <div class="resources-columns">
                                    <?php foreach ($standard_basic_needs as $index => $basic_need): 
                                        $basic_need_hash = md5($basic_need['name']);
                                    ?>
                                    <div class="resource-item basic-need-item">
                                        <div class="resource-header">
                                            <div>
                                                <div class="resource-name"><?php echo htmlspecialchars($basic_need['name']); ?></div>
                                                <span class="basic-need-badge">Basic Need</span>
                                                <span class="resource-unit-badge"><?php echo htmlspecialchars($basic_need['unit']); ?></span>
                                            </div>
                                            <span class="available-badge">Auto-allocated</span>
                                        </div>
                                        
                                        <div class="resource-stats">
                                            <div class="stat-row">
                                                <span class="stat-label-small">
                                                    <i class="fas fa-users"></i> Quantity per family:
                                                </span>
                                                <span class="stat-value"><?php echo $basic_need['quantity_per_family']; ?> <?php echo htmlspecialchars($basic_need['unit']); ?></span>
                                            </div>
                                            
                                            <div class="stat-row">
                                                <span class="stat-label-small">
                                                    <i class="fas fa-info-circle"></i> Description:
                                                </span>
                                                <span class="stat-value"><?php echo htmlspecialchars($basic_need['description']); ?></span>
                                            </div>
                                            
                                            <?php if (!empty($basic_need['location'])): ?>
                                            <div class="stat-row">
                                                <span class="stat-label-small">
                                                    <i class="fas fa-map-marker-alt"></i> Location:
                                                </span>
                                                <span class="stat-value"><?php echo htmlspecialchars($basic_need['location']); ?></span>
                                            </div>
                                            <?php endif; ?>
                                            
                                            <?php if (!empty($basic_need['expiry_date'])): ?>
                                            <div class="stat-row">
                                                <span class="stat-label-small">
                                                    <i class="fas fa-calendar"></i> Expiry Date:
                                                </span>
                                                <span class="stat-value"><?php echo htmlspecialchars($basic_need['expiry_date']); ?></span>
                                            </div>
                                            <?php endif; ?>
                                            
                                            <div class="stat-row">
                                                <span class="stat-label-small">
                                                    <i class="fas fa-box"></i> Total Available:
                                                </span>
                                                <span class="stat-value"><?php echo $basic_need['total_quantity_available']; ?> <?php echo htmlspecialchars($basic_need['unit']); ?></span>
                                            </div>
                                        </div>
                                        
                                        <div class="auto-allocation-note">
                                            <i class="fas fa-calculator"></i> Will be automatically calculated: 
                                            <strong id="calc-<?php echo $basic_need_hash; ?>">0 <?php echo htmlspecialchars($basic_need['unit']); ?></strong>
                                            (<span id="calc-per-family-<?php echo $basic_need_hash; ?>"><?php echo $basic_need['quantity_per_family']; ?></span> 
                                            <?php echo htmlspecialchars($basic_need['unit']); ?> × <span class="family-count-display">0</span> families)
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle"></i>
                                <div>
                                    <strong>No Basic Needs Available</strong>
                                    <br>
                                    <small>Unable to fetch basic needs from API. Please check the Basic Needs API connection.</small>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Special Requests Section -->
                        <?php if (!empty($special_request_items)): ?>
                            <div class="resource-category" style="border-color: #ffc107;">
                                <div class="category-header">
                                    <div class="category-title">
                                        <i class="fas fa-star"></i> 
                                        Special Requests from Selected Families
                                        <span class="category-count" id="special-request-count-badge">0 items</span>
                                    </div>
                                    <div style="font-size: 0.9rem; color: #856404;">
                                        <i class="fas fa-info-circle"></i> Only showing requests from selected families
                                    </div>
                                </div>
                                
                                <div id="filtered-special-requests-container">
                                    <!-- Will be populated by JavaScript -->
                                    <div class="alert alert-info">
                                        <i class="fas fa-info-circle"></i>
                                        <div>
                                            <strong>Select families first</strong>
                                            <br>
                                            <small>Special requests will appear here when you select families that have made requests.</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <div class="summary-bar" style="background: linear-gradient(135deg, #ffc107 0%, #ff9800 100%);">
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <div>
                                    <h3 style="color: white; margin: 0;">Special Request Allocation Summary</h3>
                                    <p style="color: rgba(255,255,255,0.9); margin: 5px 0 0 0;">
                                        Special requests allocated: <strong id="special-request-total">0 units</strong>
                                        (<span id="special-request-count">0</span> items)
                                    </p>
                                    <p style="color: rgba(255,255,255,0.8); margin: 5px 0 0 0; font-size: 0.9rem;">
                                        <i class="fas fa-info-circle"></i> Basic needs: <strong><?php echo count($standard_basic_needs); ?> items</strong> (automatically allocated)
                                    </p>
                                </div>
                                <button type="button" class="btn btn-primary" onclick="proceedToDetails()">
                                    <i class="fas fa-arrow-right"></i> Continue to Plan Details
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Step 4: Distribution Details -->
                    <div id="details-section" style="display: none;">
                        <div class="section-title">
                            <i class="fas fa-calendar-alt"></i>
                            <h2>Distribution Plan Details</h2>
                        </div>
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label">Distribution Date *</label>
                                <input type="date" class="form-control" name="distribution_date" required 
                                       min="<?php echo date('Y-m-d'); ?>"
                                       value="<?php echo date('Y-m-d', strtotime('+2 days')); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Distribution Time *</label>
                                <input type="time" class="form-control" name="distribution_time" required 
                                       value="14:00">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Coordinator *</label>
                                <select class="form-control" name="coordinator_id" id="coordinator_select" required 
                                        onchange="updateCoordinatorInfo()">
                                    <option value="">Select coordinator...</option>
                                    <?php foreach ($coordinators as $coordinator): ?>
                                    <option value="<?php echo $coordinator['coordinator_id']; ?>"
                                            data-phone="<?php echo htmlspecialchars($coordinator['phone']); ?>"
                                            data-name="<?php echo htmlspecialchars($coordinator['name']); ?>">
                                        <?php echo htmlspecialchars($coordinator['name']); ?>
                                        <?php if (!empty($coordinator['position'])): ?>
                                            - <?php echo htmlspecialchars($coordinator['position']); ?>
                                        <?php endif; ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (empty($coordinators)): ?>
                                    <div class="alert alert-warning mt-2">
                                        <i class="fas fa-exclamation-triangle"></i>
                                        No coordinators available.
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Estimated Duration (hours) *</label>
                                <input type="number" class="form-control" name="estimated_duration" 
                                       min="1" max="8" required 
                                       value="2">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Volunteers Needed *</label>
                                <input type="number" class="form-control" name="volunteers_needed" 
                                       min="2" max="20" required 
                                       value="4">
                            </div>
                        </div>
                        
                        <input type="hidden" name="coordinator_name" id="coordinator_name" value="">
                        <input type="hidden" name="coordinator_contact" id="coordinator_contact" value="">
                        
                        <div class="form-group">
                            <label class="form-label">Additional Notes</label>
                            <textarea class="form-control" name="comments" rows="3" 
                                      placeholder="Any special instructions, notes, or observations..."></textarea>
                        </div>
                        
                        <div class="summary-stats">
                            <div class="stat-card">
                                <div class="stat-number" id="final-family-count">0</div>
                                <div class="stat-label">Families Selected</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-number" id="final-total-members">0</div>
                                <div class="stat-label">Total Family Members</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-number" id="final-basic-needs"><?php echo count($standard_basic_needs); ?></div>
                                <div class="stat-label">Basic Need Types</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-number" id="final-special-requests">0</div>
                                <div class="stat-label">Special Requests</div>
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn btn-success btn-lg">
                                <i class="fas fa-check"></i> Create Distribution Plan
                            </button>
                            <button type="reset" class="btn btn-secondary" onclick="resetForm()">
                                <i class="fas fa-redo"></i> Reset Form
                            </button>
                        </div>
                    </div>
                </form>
            <?php elseif (isset($_GET['disaster_id'])): ?>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle fa-2x"></i>
                    <div>
                        <h3>No Available Victims Found</h3>
                        <p>
                            <?php if (isset($disaster_processed) && $disaster_processed): ?>
                                This disaster has been marked as processed and is no longer available for distribution.
                            <?php else: ?>
                                All approved victims for this disaster have already been distributed or are in progress.
                            <?php endif; ?>
                        </p>
                        <a href="create_distribution.php" class="btn btn-primary mt-2">
                            <i class="fas fa-arrow-left"></i> Back to Disaster Selection
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        let currentStep = 1;
        let selectedVictims = [];
        let selectedFamiliesCount = 0;
        
        // PHP data passed to JavaScript
        let allSpecialRequests = <?php echo json_encode($special_request_items); ?>;
        let allVictimsData = <?php echo json_encode($approved_victims); ?>;
        
        function toggleVictim(victimId) {
            const checkbox = document.querySelector(`input[name="selected_victims[]"][value="${victimId}"]`);
            if (checkbox) {
                checkbox.checked = !checkbox.checked;
                const card = checkbox.closest('.victim-card');
                if (card) {
                    card.classList.toggle('selected', checkbox.checked);
                }
                updateVictimSummary();
            }
        }
        
        function updateVictimSummary() {
            const checkboxes = document.querySelectorAll('input[name="selected_victims[]"]:checked');
            selectedVictims = Array.from(checkboxes).map(cb => cb.value);
            selectedFamiliesCount = selectedVictims.length;
            
            console.log('Selected victims:', selectedVictims);
            console.log('Selected count:', selectedFamiliesCount);
            
            // Update all family count displays
            const selectedCountEl = document.getElementById('selected-count');
            const proceedBtn = document.getElementById('proceed-btn');
            const familyCountElements = document.querySelectorAll('.family-count-display');
            
            if (selectedCountEl) {
                selectedCountEl.textContent = selectedFamiliesCount;
            }
            
            // Update all family count elements
            familyCountElements.forEach(el => {
                el.textContent = selectedFamiliesCount;
            });
            
            // Update final family count in step 4
            const finalFamilyCountEl = document.getElementById('final-family-count');
            if (finalFamilyCountEl) {
                finalFamilyCountEl.textContent = selectedFamiliesCount;
            }
            
            // Calculate total family members
            let totalMembers = 0;
            checkboxes.forEach(checkbox => {
                const card = checkbox.closest('.victim-card');
                if (card) {
                    const victimDetails = card.querySelector('.victim-details');
                    if (victimDetails) {
                        const divs = victimDetails.querySelectorAll('div');
                        divs.forEach(div => {
                            const text = div.textContent || '';
                            if (text.includes('Family members:')) {
                                const match = text.match(/Family members:\s*(\d+)/);
                                if (match) {
                                    totalMembers += parseInt(match[1]);
                                }
                            }
                        });
                    }
                }
            });
            
            const finalTotalMembersEl = document.getElementById('final-total-members');
            if (finalTotalMembersEl) {
                finalTotalMembersEl.textContent = totalMembers;
            }
            
            // IMPORTANT: Update basic needs calculations immediately
            updateBasicNeedsCalculation();
            
            // Filter special requests based on selected families
            filterSpecialRequests();
            
            if (proceedBtn) {
                proceedBtn.disabled = selectedFamiliesCount === 0;
            }
        }
        
        function updateBasicNeedsCalculation() {
            console.log('Updating basic needs calculation for', selectedFamiliesCount, 'families');
            
            // Find all elements with IDs starting with 'calc-'
            const calcElements = document.querySelectorAll('[id^="calc-"]');
            
            calcElements.forEach(calcEl => {
                const elementId = calcEl.id;
                
                // Extract the hash from the ID (format: calc-HASH)
                const hash = elementId.replace('calc-', '');
                
                // Find the corresponding per-family element
                const perFamilyEl = document.getElementById('calc-per-family-' + hash);
                
                if (perFamilyEl) {
                    // Get the quantity per family
                    const perFamilyText = perFamilyEl.textContent.trim();
                    const perFamily = parseInt(perFamilyText) || 0;
                    
                    // Calculate total
                    const total = perFamily * selectedFamiliesCount;
                    
                    // Get the unit from the parent element's data or text
                    const unitMatch = calcEl.textContent.match(/(\w+)$/);
                    const unit = unitMatch ? unitMatch[1] : 'units';
                    
                    // Update the display
                    calcEl.textContent = total + ' ' + unit;
                    
                    console.log('Updated', elementId, ':', perFamily, 'x', selectedFamiliesCount, '=', total, unit);
                }
            });
        }
        
        // Function to filter special requests based on selected families
        function filterSpecialRequests() {
            console.log('Filtering special requests for selected families:', selectedVictims);
            
            const container = document.getElementById('filtered-special-requests-container');
            if (!container) return;
            
            if (selectedVictims.length === 0) {
                // No families selected
                container.innerHTML = `
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        <div>
                            <strong>No families selected</strong>
                            <br>
                            <small>Select families first to see their special requests.</small>
                        </div>
                    </div>
                `;
                document.getElementById('special-request-count-badge').textContent = '0 items';
                return;
            }
            
            // Create a map of selected victim IDs for quick lookup
            const selectedVictimIds = new Set(selectedVictims);
            
            // Filter special requests to only include those requested by selected families
            const filteredRequests = [];
            
            allSpecialRequests.forEach(request => {
                // Check if any of the requesting victims are in our selected list
                const requestingVictims = request.requested_by || {};
                const requestingVictimIds = Object.keys(requestingVictims);
                
                // Find which of these victims are selected
                const selectedRequestingVictims = {};
                requestingVictimIds.forEach(victimId => {
                    if (selectedVictimIds.has(victimId.toString())) {
                        selectedRequestingVictims[victimId] = requestingVictims[victimId];
                    }
                });
                
                // Only include this request if at least one selected family requested it
                if (Object.keys(selectedRequestingVictims).length > 0) {
                    // Clone the request and update requested_by to only include selected families
                    const filteredRequest = {...request};
                    filteredRequest.requested_by = selectedRequestingVictims;
                    filteredRequest.requesting_count = Object.keys(selectedRequestingVictims).length;
                    filteredRequests.push(filteredRequest);
                }
            });
            
            // Group filtered requests by type
            const filteredRequestsByType = {};
            filteredRequests.forEach(request => {
                const type = request.type || 'Special Request';
                if (!filteredRequestsByType[type]) {
                    filteredRequestsByType[type] = [];
                }
                filteredRequestsByType[type].push(request);
            });
            
            // Update the UI
            updateSpecialRequestsUI(filteredRequestsByType);
        }
        
        // Function to update the special requests UI
        function updateSpecialRequestsUI(filteredRequestsByType) {
            const container = document.getElementById('filtered-special-requests-container');
            
            if (Object.keys(filteredRequestsByType).length === 0) {
                container.innerHTML = `
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        <div>
                            <strong>No special requests from selected families</strong>
                            <br>
                            <small>The selected families have not made any special requests.</small>
                        </div>
                    </div>
                `;
                document.getElementById('special-request-count-badge').textContent = '0 items';
                return;
            }
            
            let html = '';
            let totalRequests = 0;
            
            // Sort types
            const sortedTypes = Object.keys(filteredRequestsByType).sort();
            
            sortedTypes.forEach(type => {
                const typeRequests = filteredRequestsByType[type];
                totalRequests += typeRequests.length;
                
                html += `
                    <div style="margin: 15px 0 10px 0; padding-left: 10px; border-left: 3px solid #ffc107;">
                        <h4 style="margin: 0; color: #495057; font-size: 1rem;">
                            <i class="fas fa-tag"></i> ${escapeHtml(type)}
                            <small style="color: #6c757d; font-size: 0.85rem;">(${typeRequests.length} items)</small>
                        </h4>
                    </div>
                    
                    <div class="resources-columns">
                `;
                
                typeRequests.forEach(request => {
                    const resource_key = md5(request.name.toLowerCase() + request.type);
                    const input_name = "special_" + resource_key;
                    
                    html += `
                        <div class="resource-item special-request-item" data-resource-key="${resource_key}">
                            <div class="resource-header">
                                <div>
                                    <div class="resource-name">${escapeHtml(request.name)}</div>
                                    <span class="special-request-badge">
                                        Requested by ${request.requesting_count} family${request.requesting_count > 1 ? 's' : ''}
                                    </span>
                                    <span class="resource-unit-badge">
                                        ${escapeHtml(request.unit)}
                                    </span>
                                </div>
                                <span class="available-badge">
                                    ${request.quantity_available} available
                                </span>
                            </div>
                            
                            <div class="requested-by">
                                <div class="requested-by-title">
                                    <i class="fas fa-user-check"></i> Requested by selected families:
                                </div>
                                <div class="requested-by-list">
                    `;
                    
                    // Show up to 5 requesting families
                    const requestingFamilies = Object.values(request.requested_by || {});
                    requestingFamilies.slice(0, 5).forEach(name => {
                        html += `<span class="requested-by-item">${escapeHtml(name)}</span>`;
                    });
                    
                    if (requestingFamilies.length > 5) {
                        html += `<span class="requested-by-item">+${requestingFamilies.length - 5} more</span>`;
                    }
                    
                    html += `
                                </div>
                            </div>
                            
                            <div class="resource-controls">
                                <div>
                                    <div class="form-label" style="font-size: 0.85rem; margin-bottom: 5px;">Allocate Quantity:</div>
                                    <input type="number" 
                                           name="${input_name}" 
                                           class="quantity-input"
                                           min="0" 
                                           max="${request.quantity_available}"
                                           value="0"
                                           onchange="updateResourceTotal()"
                                           data-available="${request.quantity_available}"
                                           data-unit="${escapeHtml(request.unit)}"
                                           data-name="${escapeHtml(request.name)}"
                                           style="width: 120px;">
                                </div>
                                <div class="quantity-label">
                                    Max: ${request.quantity_available} ${escapeHtml(request.unit)}
                                </div>
                            </div>
                        </div>
                    `;
                });
                
                html += `</div>`;
            });
            
            container.innerHTML = html;
            document.getElementById('special-request-count-badge').textContent = totalRequests + ' items';
            
            // Reattach event listeners to the new quantity inputs
            attachQuantityInputListeners();
        }
        
        // Helper function to escape HTML
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        // Helper function for MD5 (simplified version)
        function md5(input) {
            // This is a simplified version - in production, use a proper MD5 library
            return btoa(input).replace(/[+/=]/g, '').substring(0, 32);
        }
        
        // Function to attach event listeners to quantity inputs
        function attachQuantityInputListeners() {
            document.querySelectorAll('.quantity-input').forEach(input => {
                input.addEventListener('change', updateResourceTotal);
            });
        }
        
        function updateResourceTotal() {
            const resourceInputs = document.querySelectorAll('input.quantity-input');
            let total = 0;
            let resourceCount = 0;
            
            resourceInputs.forEach(input => {
                const value = parseInt(input.value) || 0;
                if (value > 0) {
                    total += value;
                    resourceCount++;
                }
            });
            
            const resourceTotalEl = document.getElementById('special-request-total');
            if (resourceTotalEl) {
                resourceTotalEl.textContent = total + ' units';
            }
            
            const resourceCountEl = document.getElementById('special-request-count');
            if (resourceCountEl) {
                resourceCountEl.textContent = resourceCount;
            }
            
            const finalSpecialRequestsEl = document.getElementById('final-special-requests');
            if (finalSpecialRequestsEl) {
                finalSpecialRequestsEl.textContent = resourceCount;
            }
        }
        
        function proceedToResources() {
            console.log('Proceeding to resources with', selectedFamiliesCount, 'families...');
            
            const resourceSection = document.getElementById('resource-section');
            const detailsSection = document.getElementById('details-section');
            
            if (resourceSection) {
                resourceSection.style.display = 'block';
                
                // Filter the requests based on selected families (already done by updateVictimSummary)
                // Auto-suggest quantities
                setTimeout(() => {
                    autoSuggestSpecialRequests();
                }, 100);
                
                // Scroll to resource section
                resourceSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
            if (detailsSection) {
                detailsSection.style.display = 'none';
            }
            
            currentStep = 3;
            updateStepIndicator();
            
            // Force update of basic needs calculation
            setTimeout(() => {
                updateBasicNeedsCalculation();
            }, 100);
        }
        
        function proceedToDetails() {
            console.log('Proceeding to details...');
            const detailsSection = document.getElementById('details-section');
            if (detailsSection) {
                detailsSection.style.display = 'block';
                // Scroll to details section
                detailsSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
            
            currentStep = 4;
            updateStepIndicator();
            updateResourceTotal();
        }
        
        function updateStepIndicator() {
            const steps = document.querySelectorAll('.step');
            steps.forEach((step, index) => {
                step.classList.remove('active');
                if (index + 1 < currentStep) {
                    step.classList.add('completed');
                } else if (index + 1 === currentStep) {
                    step.classList.add('active');
                }
            });
        }
        
        function updateCoordinatorInfo() {
            const select = document.getElementById('coordinator_select');
            const selectedOption = select.options[select.selectedIndex];
            const nameField = document.getElementById('coordinator_name');
            const contactField = document.getElementById('coordinator_contact');
            
            if (nameField && contactField && selectedOption) {
                nameField.value = selectedOption.dataset.name || '';
                contactField.value = selectedOption.dataset.phone || '';
            }
        }
        
        function resetForm() {
            if (confirm('Are you sure you want to reset the entire form?')) {
                location.reload();
            }
        }
        
        function autoSuggestSpecialRequests() {
            const resourceInputs = document.querySelectorAll('input.quantity-input');
            resourceInputs.forEach(input => {
                const max = parseInt(input.dataset.available) || 0;
                
                // Find how many families requested this item
                const resourceItem = input.closest('.resource-item');
                if (resourceItem) {
                    const badge = resourceItem.querySelector('.special-request-badge');
                    if (badge) {
                        const match = badge.textContent.match(/\d+/);
                        const requestingFamilies = match ? parseInt(match[0]) : 0;
                        
                        // Suggest quantity based on number of families requesting this item
                        // But not more than available
                        const suggested = Math.min(requestingFamilies, max);
                        input.value = suggested > 0 ? suggested : 0;
                    }
                }
            });
            
            updateResourceTotal();
        }
        
        // Initialize when page loads
        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOM loaded, initializing...');
            
            // Initialize select all functionality
            const selectAllCheckbox = document.getElementById('select-all-victims');
            if (selectAllCheckbox) {
                selectAllCheckbox.addEventListener('change', function() {
                    const checkboxes = document.querySelectorAll('input[name="selected_victims[]"]');
                    checkboxes.forEach(checkbox => {
                        checkbox.checked = this.checked;
                        const card = checkbox.closest('.victim-card');
                        if (card) {
                            card.classList.toggle('selected', this.checked);
                        }
                    });
                    updateVictimSummary();
                });
            }
            
            // Handle clicks on victim cards
            document.querySelectorAll('.victim-card').forEach(card => {
                card.addEventListener('click', function(e) {
                    // Check if we clicked directly on the checkbox or its container
                    const clickedCheckbox = e.target.type === 'checkbox';
                    
                    if (!clickedCheckbox) {
                        // We clicked somewhere else on the card, so toggle the checkbox
                        const checkbox = this.querySelector('input[name="selected_victims[]"]');
                        if (checkbox) {
                            checkbox.checked = !checkbox.checked;
                            this.classList.toggle('selected', checkbox.checked);
                            updateVictimSummary();
                        }
                    }
                });
            });
            
            // Add change listeners to all victim checkboxes
            const victimCheckboxes = document.querySelectorAll('input[name="selected_victims[]"]');
            victimCheckboxes.forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    console.log('Checkbox changed:', this.value, this.checked);
                    const card = this.closest('.victim-card');
                    if (card) {
                        card.classList.toggle('selected', this.checked);
                    }
                    updateVictimSummary();
                });
            });
            
            // Initialize step indicator
            if (document.querySelectorAll('input[name="selected_victims[]"]').length > 0) {
                currentStep = 2;
                updateStepIndicator();
            }
            
            // Initialize summary values
            updateVictimSummary();
            updateResourceTotal();
            
            // Force an initial update after a short delay
            setTimeout(() => {
                updateVictimSummary();
                console.log('Initial update complete');
            }, 200);
        });
    </script>
</body>
</html>