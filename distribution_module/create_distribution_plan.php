<?php
// ========================================
// CREATE DISTRIBUTION PLAN - UPDATED FOR VICTIM APPROVALS AND NEEDS API
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
            resource_id INT,
            quantity_allocated INT NOT NULL,
            quantity_distributed INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_distribution (distribution_id),
            INDEX idx_resource (resource_id)
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
                $apiDisasterId = $apiDisaster['disaster_id'] ?? 
                                 $apiDisaster['Disaster_ID'] ?? 
                                 $apiDisaster['disasterId'] ?? 
                                 $apiDisaster['DisasterID'] ??
                                 $apiDisaster['id'] ?? 0;
                
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
                                $victimDisasterId = $victim['disaster_id'] ?? 
                                                   $victim['Disaster_ID'] ?? 
                                                   $victim['disasterId'] ?? 
                                                   $victim['DisasterID'] ?? 0;
                                if (intval($victimDisasterId) == $disaster_id) {
                                    $total_api_victims++;
                                }
                            }
                        }
                        
                        $disasters[] = [
                            'disaster_id' => $disaster_id,
                            'Disaster_Name' => $apiDisaster['disaster_name'] ?? 
                                            $apiDisaster['Disaster_Name'] ?? 
                                            $apiDisaster['disasterName'] ?? 
                                            'Unknown',
                            'Location' => $apiDisaster['district'] ?? 
                                        $apiDisaster['District'] ?? 
                                        $apiDisaster['location'] ?? 
                                        $apiDisaster['Location'] ?? 'Unknown',
                            'Disaster_Type' => $apiDisaster['severity'] ?? 
                                             $apiDisaster['Severity'] ?? 
                                             'Unknown',
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
   GET APPROVED VICTIMS FOR SELECTED DISASTER
---------------------------------------- */
if (isset($_GET['disaster_id']) && is_numeric($_GET['disaster_id'])) {
    $disaster_id = intval($_GET['disaster_id']);
    
    // Check if disaster is processed
    $disaster_processed = isDisasterProcessed($db, $disaster_id);
    
    if ($disaster_processed) {
        $error = "This disaster has been marked as processed and is no longer available for distribution planning.";
    } else {
        try {
            $query_start = microtime(true);
            
            // Get approved victim IDs from local approval table EXCLUDING already distributed ones
            $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
            $limit = 50;
            $offset = ($page - 1) * $limit;
            
            $approved_victims_query = "
                SELECT va.victim_id, va.approval_status, va.approved_at
                FROM victim_approvals va
                LEFT JOIN distribution_items di ON va.victim_id = di.victim_id 
                    AND di.status IN ('Scheduled', 'Dispatched', 'Delivered')
                WHERE va.disaster_id = ? 
                    AND va.approval_status = 'Approved'
                    AND di.victim_id IS NULL
                ORDER BY va.approved_at DESC
                LIMIT ? OFFSET ?
            ";
            
            $stmt = $db->prepare($approved_victims_query);
            if ($stmt === false) {
                throw new Exception("Prepare failed: " . $db->error);
            }
            
            $stmt->bind_param("iii", $disaster_id, $limit, $offset);
            $stmt->execute();
            $result = $stmt->get_result();
            $approvedVictimIds = $result->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
            
            $query_time = microtime(true) - $query_start;
            error_log("Victim IDs query took: " . round($query_time, 4) . " seconds");
            
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
                    if ($needsApiResult['success'] && isset($needsApiResult['data']['data']) && is_array($needsApiResult['data']['data'])) {
                        $apiNeeds = $needsApiResult['data']['data'];
                        
                        foreach ($apiNeeds as $need) {
                            $needVictimId = $need['victim_id'] ?? 
                                           $need['Victim_ID'] ?? 
                                           $need['victimId'] ?? 
                                           $need['VictimID'] ?? 0;
                            $needVictimId = intval($needVictimId);
                            
                            if (isset($victimIdMap[$needVictimId])) {
                                if (!isset($needsByVictim[$needVictimId])) {
                                    $needsByVictim[$needVictimId] = [];
                                }
                                
                                $needsByVictim[$needVictimId][] = [
                                    'need_id' => $need['need_id'] ?? 0,
                                    'resource_id' => $need['resource_id'] ?? $need['Resource_ID'] ?? $need['resourceId'] ?? $need['ResourceID'] ?? null,
                                    'resource_name' => $need['temp_resource_name'] ?? $need['resource_name'] ?? 'Unknown Resource',
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
                    
                    // Find victims in API data
                    $process_start = microtime(true);
                    foreach ($apiVictims as $victim) {
                        $victimId = null;
                        $possibleFields = ['victim_id', 'Victim_ID', 'victimId', 'VictimID', 'id'];
                        
                        foreach ($possibleFields as $field) {
                            if (isset($victim[$field]) && is_numeric($victim[$field])) {
                                $victimId = intval($victim[$field]);
                                break;
                            }
                        }
                        
                        if ($victimId && isset($victimIdMap[$victimId])) {
                            $victimDisasterId = null;
                            $disasterFields = ['disaster_id', 'Disaster_ID', 'disasterId', 'DisasterID'];
                            
                            foreach ($disasterFields as $field) {
                                if (isset($victim[$field]) && is_numeric($victim[$field])) {
                                    $victimDisasterId = intval($victim[$field]);
                                    break;
                                }
                            }
                            
                            // Only add if disaster_id matches or if we can't verify
                            if ($victimDisasterId === null || $victimDisasterId == $disaster_id) {
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
                                
                                // Check for special needs
                                $has_baby = $victim['has_baby'] ?? false;
                                $has_elderly = $victim['has_elderly'] ?? false;
                                $has_disabled = $victim['has_disabled'] ?? false;
                                
                                foreach ($victimNeeds as $need) {
                                    $has_baby = $has_baby || $need['has_baby'];
                                    $has_elderly = $has_elderly || $need['has_elderly'];
                                    $has_disabled = $has_disabled || $need['has_disabled'];
                                }
                                
                                $approved_victims[] = [
                                    'victim_id' => $victimId,
                                    'full_name' => $victim['full_name'] ?? 
                                                 $victim['Full_Name'] ?? 
                                                 $victim['fullName'] ?? 
                                                 $victim['name'] ?? 'Unknown',
                                    'ic_number' => $victim['ic_number'] ?? 
                                                  $victim['IC_Number'] ?? 
                                                  $victim['icNumber'] ?? 'N/A',
                                    'email' => $victim['email'] ?? 
                                              $victim['Email'] ?? 'N/A',
                                    'phone' => $victim['phone'] ?? 
                                              $victim['Phone'] ?? '',
                                    'address' => $victim['address'] ?? 
                                                $victim['Address'] ?? 'Unknown',
                                    'city' => $victim['city'] ?? 
                                             $victim['City'] ?? '',
                                    'postal_code' => $victim['postal_code'] ?? 
                                                   $victim['Postal_Code'] ?? 
                                                   $victim['postalCode'] ?? '',
                                    'district' => $victim['district'] ?? 
                                                 $victim['District'] ?? 'N/A',
                                    'family_members' => $victim['family_members'] ?? 
                                                      $victim['Family_Members'] ?? 
                                                      $victim['familyMembers'] ?? 1,
                                    'has_baby' => $has_baby,
                                    'has_elderly' => $has_elderly,
                                    'has_disabled' => $has_disabled,
                                    'priority' => $overallPriority,
                                    'needs' => $victimNeeds,
                                    'special_request' => '',
                                    'approval_status' => 'Approved',
                                    'approved_at' => $victimIdMap[$victimId]['approved_at'] ?? date('Y-m-d H:i:s'),
                                    'is_distributed' => 0 // These victims are not distributed yet
                                ];
                            }
                        }
                    }
                    
                    $process_time = microtime(true) - $process_start;
                    error_log("Victim processing took: " . round($process_time, 4) . " seconds");
                }
            }
            
            error_log("Found " . count($approved_victims) . " approved victims for disaster $disaster_id (not yet distributed)");
            
        } catch (Exception $e) {
            $error = "Error loading approved victims: " . $e->getMessage();
            error_log("Exception in get approved victims: " . $e->getMessage());
        }
    }
}
/* ----------------------------------------
   GET RESOURCES FOR DISTRIBUTION - IMPROVED PARSING
---------------------------------------- */
$resources = [];
$resource_allocations_history = [];

try {
    // Get resources from needs API
    if ($needsApiResult['success'] && isset($needsApiResult['data']['data']) && is_array($needsApiResult['data']['data'])) {
        
        // First, get all previous resource allocations
        $history_query = "
            SELECT dr.resource_id, SUM(dr.quantity_allocated) as total_allocated
            FROM distribution_resources dr
            JOIN distribution d ON dr.distribution_id = d.distribution_id
            WHERE d.status IN ('Planning', 'Scheduled', 'Dispatched')
            GROUP BY dr.resource_id
        ";

        $history_result = $db->query($history_query);
        if ($history_result) {
            while ($row = $history_result->fetch_assoc()) {
                if ($row['resource_id'] !== null && $row['resource_id'] !== 'null' && $row['resource_id'] !== '') {
                    $resource_allocations_history[intval($row['resource_id'])] = intval($row['total_allocated']);
                }
            }
            $history_result->free();
        }

        // Improved helper function to determine resource type and unit from name
        function determineResourceTypeAndUnit($resourceName) {
            $lowerName = strtolower(trim($resourceName));
            
            // Check if name has parentheses with type hint
            if (preg_match('/\((.*?)\)/', $lowerName, $matches)) {
                $typeHint = strtolower(trim($matches[1]));
                
                // Extract type from parentheses - this is the most reliable
                if (strpos($typeHint, 'clothing') !== false || 
                    strpos($typeHint, 'cloth') !== false) {
                    return ['type' => 'Clothing', 'unit' => 'pieces'];
                }
                if (strpos($typeHint, 'medical') !== false) {
                    return ['type' => 'Medical', 'unit' => 'units'];
                }
                if (strpos($typeHint, 'food') !== false) {
                    return ['type' => 'Food', 'unit' => 'units'];
                }
                if (strpos($typeHint, 'hygiene') !== false) {
                    return ['type' => 'Hygiene', 'unit' => 'units'];
                }
            }
            
            // Extract base name (remove parentheses) for further matching
            $baseName = preg_replace('/\s*\([^)]*\)/', '', $lowerName);
            $baseName = trim($baseName);
            
            // Medical supplies
            if (strpos($baseName, 'disinfectant') !== false || 
                strpos($baseName, 'sanitizer') !== false ||
                strpos($baseName, 'antiseptic') !== false ||
                strpos($baseName, 'alcohol') !== false) {
                return ['type' => 'Medical', 'unit' => 'bottles'];
            }
            
            if (strpos($baseName, 'bandage') !== false ||
                strpos($baseName, 'gauze') !== false ||
                strpos($baseName, 'dressing') !== false) {
                return ['type' => 'Medical', 'unit' => 'rolls'];
            }
            
            if (strpos($baseName, 'syringe') !== false ||
                strpos($baseName, 'needle') !== false ||
                strpos($baseName, 'thermometer') !== false ||
                strpos($baseName, 'eye drop') !== false) {
                return ['type' => 'Medical', 'unit' => 'units'];
            }
            
            if (strpos($baseName, 'tablet') !== false ||
                strpos($baseName, 'pill') !== false ||
                strpos($baseName, 'medicine') !== false ||
                strpos($baseName, 'paracetamol') !== false ||
                strpos($baseName, 'antibiotic') !== false ||
                strpos($baseName, 'pain relief') !== false ||
                strpos($baseName, 'cough syrup') !== false ||
                strpos($baseName, 'vitamin') !== false ||
                strpos($baseName, 'insulin') !== false) {
                return ['type' => 'Medical', 'unit' => 'boxes/units'];
            }
            
            if (strpos($baseName, 'first aid') !== false ||
                strpos($baseName, 'face mask') !== false ||
                strpos($baseName, 'glove') !== false) {
                return ['type' => 'Medical', 'unit' => 'units/packs'];
            }
            
            // Clothing items
            if (strpos($baseName, 'blanket') !== false ||
                strpos($baseName, 'towel') !== false ||
                strpos($baseName, 'cloth') !== false ||
                strpos($baseName, 'shirt') !== false ||
                strpos($baseName, 'pant') !== false ||
                strpos($baseName, 'dress') !== false ||
                strpos($baseName, 'jacket') !== false) {
                return ['type' => 'Clothing', 'unit' => 'pieces'];
            }
            
            // Food items
            if (strpos($baseName, 'rice') !== false ||
                strpos($baseName, 'flour') !== false ||
                strpos($baseName, 'sugar') !== false ||
                strpos($baseName, 'salt') !== false) {
                return ['type' => 'Food', 'unit' => 'kg'];
            }
            
            if (strpos($baseName, 'water') !== false ||
                strpos($baseName, 'juice') !== false ||
                strpos($baseName, 'milk') !== false) {
                return ['type' => 'Food', 'unit' => 'liters'];
            }
            
            if (strpos($baseName, 'can') !== false ||
                strpos($baseName, 'food') !== false ||
                strpos($baseName, 'noodle') !== false ||
                strpos($baseName, 'pasta') !== false) {
                return ['type' => 'Food', 'unit' => 'units'];
            }
            
            // Default
            return ['type' => 'General', 'unit' => 'units'];
        }

        // Extract unique resources from needs API
        $resourceMap = [];
        $apiNeeds = $needsApiResult['data']['data'];

        // Process each resource from API
        foreach ($apiNeeds as $need) {
            $resourceId = $need['resource_id'] ?? null;
            $rawResourceName = $need['temp_resource_name'] ?? $need['resource_name'] ?? 'Unknown Resource';
            $rawResourceName = trim($rawResourceName);
            
            // Skip invalid resources
            if (empty($rawResourceName) || $rawResourceName === 'Unknown Resource') {
                continue;
            }
            
            // Extract base name (remove parentheses)
            $resourceName = preg_replace('/\s*\([^)]*\)/', '', $rawResourceName);
            $resourceName = trim($resourceName);
            
            // Determine type and unit
            $typeAndUnit = determineResourceTypeAndUnit($rawResourceName);
            $resourceType = $typeAndUnit['type'];
            $resourceUnit = $typeAndUnit['unit'];
            
            error_log("PARSING: '$rawResourceName' => Name: '$resourceName', Type: '$resourceType', Unit: '$resourceUnit'");
            
            $quantityNeeded = floatval($need['quantity_needed'] ?? 0);
            
            // Handle resources with valid IDs
            if ($resourceId !== null && $resourceId !== '' && $resourceId !== 'null') {
                $resourceId = intval($resourceId);
                
                // Use resource_id as key
                $uniqueKey = $resourceId;
                
                if (!isset($resourceMap[$uniqueKey])) {
                    // New resource
                    $base_quantity = 1000;
                    $previously_allocated = $resource_allocations_history[$resourceId] ?? 0;
                    $available_quantity = max(0, $base_quantity - $previously_allocated);
                    
                    $resourceMap[$uniqueKey] = [
                        'resource_id' => $resourceId,
                        'name' => $resourceName, // Name without parentheses
                        'type' => $resourceType,
                        'unit' => $resourceUnit,
                        'base_quantity' => $base_quantity,
                        'quantity_available' => $available_quantity,
                        'previously_allocated' => $previously_allocated,
                        'quantity_needed_total' => 0,
                        'needs_count' => 0,
                        'raw_name' => $rawResourceName
                    ];
                    
                    error_log("CREATED resource ID $resourceId: '{$resourceName}' Type=$resourceType, Unit=$resourceUnit (from: '$rawResourceName')");
                } else {
                    // Resource exists - accumulate quantities
                    error_log("ADDING to existing resource ID $resourceId: Current needs={$resourceMap[$uniqueKey]['needs_count']}, Adding quantity=$quantityNeeded");
                }
                
                // Accumulate quantities
                $resourceMap[$uniqueKey]['quantity_needed_total'] += $quantityNeeded;
                $resourceMap[$uniqueKey]['needs_count']++;
                    
            } elseif (!empty($resourceName)) {
                // Resources without IDs - use name+type as key
                $uniqueKey = 'named_' . md5($resourceName . $resourceType);
                
                if (!isset($resourceMap[$uniqueKey])) {
                    $base_quantity = 500;
                    
                    $resourceMap[$uniqueKey] = [
                        'resource_id' => 0,
                        'name' => $resourceName,
                        'type' => $resourceType,
                        'unit' => $resourceUnit,
                        'base_quantity' => $base_quantity,
                        'quantity_available' => $base_quantity,
                        'previously_allocated' => 0,
                        'quantity_needed_total' => 0,
                        'needs_count' => 0,
                        'is_named_only' => true,
                        'raw_name' => $rawResourceName
                    ];
                }
                
                $resourceMap[$uniqueKey]['quantity_needed_total'] += $quantityNeeded;
                $resourceMap[$uniqueKey]['needs_count']++;
            }
        }

        // Convert to array and sort
        $resources = array_values($resourceMap);

        error_log("=== BEFORE SORT ===");
        foreach ($resources as $idx => $res) {
            error_log("[$idx] '{$res['name']}' - Type: '{$res['type']}', Unit: '{$res['unit']}'");
        }

        // Sort by type first, then name
        usort($resources, function($a, $b) {
            $typeCompare = strcmp($a['type'], $b['type']);
            if ($typeCompare !== 0) {
                return $typeCompare;
            }
            return strcmp($a['name'], $b['name']);
        });

        error_log("=== AFTER SORT ===");
        foreach ($resources as $idx => $res) {
            error_log("[$idx] '{$res['name']}' - Type: '{$res['type']}', Unit: '{$res['unit']}'");
        }

        error_log("Loaded " . count($resources) . " resources from API");
        
    } else {
        // No fallback to local resource database - API is required
        error_log("Needs API failed, cannot load resources");
        $error .= "<br>⚠️ Unable to load resources from Needs API. Please try again later.";
    }
    
} catch (Exception $e) {
    $error .= "<br>Error loading resources: " . $e->getMessage();
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
   GET PPS LOCATIONS
---------------------------------------- */
$pps_locations = [
    'Dewan Serbaguna Masjid Tanah',
    'Sekolah Kebangsaan Alor Gajah',
    'Dewan Komuniti Taman Seri Bayu',
    'Balai Raya Kampung Baru',
    'Gelanggang Futsal Bandaraya'
];

/* ----------------------------------------
   FORM SUBMISSION: CREATE DISTRIBUTION PLAN
---------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_distribution'])) {
    
    error_log("=== CREATE DISTRIBUTION FORM SUBMITTED ===");
    
    $disaster_id = intval($_POST['disaster_id']);
    $distribution_date = $_POST['distribution_date'];
    $distribution_time = $_POST['distribution_time'];
    $location = $_POST['location'];
    $coordinator_id = isset($_POST['coordinator_id']) ? intval($_POST['coordinator_id']) : 0;
    $coordinator_name = $_POST['coordinator_name'] ?? '';
    $coordinator_contact = $_POST['coordinator_contact'] ?? '';
    $selected_victims = $_POST['selected_victims'] ?? [];
    $estimated_duration = intval($_POST['estimated_duration']);
    $volunteers_needed = intval($_POST['volunteers_needed']);
    $comments = $_POST['comments'] ?? '';
    
    $resource_allocations = [];
    foreach ($_POST as $key => $value) {
        if (strpos($key, 'resource_') === 0 && $value > 0) {
            $resource_id = str_replace('resource_', '', $key);
            $resource_allocations[$resource_id] = intval($value);
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
        
        // Check resource availability
        $insufficient_resources = [];
        foreach ($resource_allocations as $resource_id => $quantity) {
            foreach ($resources as $resource) {
                if ($resource['resource_id'] == $resource_id || 
                    (isset($resource['is_named_only']) && "resource_named_" . md5($resource['name'] . $resource['type']) === "resource_" . $resource_id)) {
                    if ($quantity > $resource['quantity_available']) {
                        $insufficient_resources[] = [
                            'name' => $resource['name'],
                            'needed' => $quantity,
                            'available' => $resource['quantity_available'],
                            'unit' => $resource['unit'],
                            'previously_allocated' => $resource['previously_allocated'] ?? 0
                        ];
                    }
                    break;
                }
            }
        }
        
        if (!empty($insufficient_resources)) {
            $warning = "⚠️ Insufficient resources after previous allocations: ";
            foreach ($insufficient_resources as $resource) {
                $warning .= "{$resource['name']} (Needed: {$resource['needed']}, Available: {$resource['available']} {$resource['unit']}, Previously allocated: {$resource['previously_allocated']}), ";
            }
            $error = $warning . " You can still proceed with partial distribution.";
        }
        
        // Start transaction
        $db->begin_transaction();
        
        // Create distribution record
        $combined_datetime = $distribution_date . ' ' . $distribution_time . ':00';
        
        error_log("Combined datetime: " . $combined_datetime);
        
        $plan_details = "DISTRIBUTION PLAN\n";
        $plan_details .= "=================\n";
        $plan_details .= "Time: $distribution_date at $distribution_time\n";
        $plan_details .= "Location: $location\n";
        $plan_details .= "Coordinator: $coordinator_name ($coordinator_contact)\n";
        $plan_details .= "Estimated Duration: {$estimated_duration} hours\n";
        $plan_details .= "Volunteers Needed: $volunteers_needed\n";
        $plan_details .= "Selected Victims: " . count($selected_victims) . " families\n";
        $plan_details .= "Resource Allocations:\n";
        foreach ($resource_allocations as $resource_id => $quantity) {
            foreach ($resources as $resource) {
                if ($resource['resource_id'] == $resource_id || 
                    (isset($resource['is_named_only']) && "resource_named_" . md5($resource['name'] . $resource['type']) === "resource_" . $resource_id)) {
                    $plan_details .= "- {$resource['name']}: $quantity {$resource['unit']}\n";
                    break;
                }
            }
        }
        if (!empty($comments)) {
            $plan_details .= "Additional Comments: $comments\n";
        }
        $plan_details .= "Plan Created: " . date('Y-m-d H:i:s');

        $first_resource_id = 0;
        if (!empty($resource_allocations)) {
            $keys = array_keys($resource_allocations);
            $first_resource_id = !empty($keys) ? intval($keys[0]) : 0;
            if ($first_resource_id === 'null') {
                $first_resource_id = 0;
            }
        }

        $first_victim_id = !empty($selected_victims) ? intval($selected_victims[0]) : 0;

        // Insert into distribution table
        $distribution_query = "
            INSERT INTO distribution (
                victim_id, disaster_id, resource_id, date, status, 
                quantity_sent, comments, location, coordinator_name, 
                coordinator_contact, estimated_duration, volunteers_needed
            ) VALUES (?, ?, ?, ?, 'Planning', 0, ?, ?, ?, ?, ?, ?)
        ";

        $dist_stmt = $db->prepare($distribution_query);
        if (!$dist_stmt) {
            throw new Exception("Prepare failed: " . $db->error);
        }

        $dist_stmt->bind_param(
            "iiisssssii",
            $first_victim_id,
            $disaster_id,
            $first_resource_id,
            $combined_datetime,
            $plan_details,
            $location,
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
        
        // Create resource allocations
        foreach ($resource_allocations as $resource_id => $quantity) {
            if ($quantity > 0) {
                // Create allocation record
                $allocation_query = "
                    INSERT INTO distribution_resources (
                        distribution_id, resource_id, quantity_allocated
                    ) VALUES (?, ?, ?)
                ";
                $alloc_stmt = $db->prepare($allocation_query);
                if (!$alloc_stmt) {
                    throw new Exception("Prepare failed for resource allocation: " . $db->error);
                }
                
                // Handle named resources (without numeric IDs)
                if (strpos($resource_id, 'named_') !== false) {
                    $db_resource_id = 0;
                } else {
                    $db_resource_id = intval($resource_id);
                }
                
                $alloc_stmt->bind_param("iii", $distribution_id, $db_resource_id, $quantity);
                
                if (!$alloc_stmt->execute()) {
                    throw new Exception("Error allocating resources: " . $alloc_stmt->error);
                }
                $alloc_stmt->close();
            }
        }
        
        error_log("Added " . count($resource_allocations) . " resource allocations");
        
        $db->commit();
        
        error_log("Transaction committed successfully!");
        
        // Generate a display ID for user (DIST001 format)
        $display_id = 'DIST' . str_pad($distribution_id, 3, '0', STR_PAD_LEFT);
        
        $success = "✅ Distribution plan created successfully!";
        $success .= "<br><strong>Distribution ID: $display_id (Database ID: $distribution_id)</strong>";
        $success .= "<br><small>Date: $distribution_date at $distribution_time</small>";
        $success .= "<br><small>Location: $location</small>";
        $success .= "<br><small>Coordinator: $coordinator_name</small>";
        $success .= "<br><small>Families: " . count($selected_victims) . "</small>";
        $success .= "<br><small>Resources allocated: " . count($resource_allocations) . " types</small>";
        
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
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 15px;
            max-height: 400px;
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
        
        /* IMPROVED RESOURCE GRID LAYOUT */
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
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 15px;
            max-height: 500px;
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
        
        .resource-type-badge {
            background: #e9ecef;
            padding: 3px 8px;
            border-radius: 10px;
            font-size: 0.75rem;
            color: #666;
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
        
        .resource-controls {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 10px;
        }
        
        .quantity-input {
            width: 80px;
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
                        ?>
                        <div class="victim-card" onclick="toggleVictim(<?php echo $victim['victim_id']; ?>)">
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
                                    <i class="fas fa-home"></i> <?php echo htmlspecialchars($victim['address']); ?>
                                </div>
                                <div style="margin-bottom: 8px;">
                                    <i class="fas fa-phone"></i> <?php echo htmlspecialchars($victim['phone']); ?>
                                </div>
                                <div>
                                    <i class="fas fa-user-friends"></i> Family members: <?php echo $victim['family_members']; ?>
                                </div>
                            </div>
                            
                            <?php if ($needs_count > 0): ?>
                                <div class="needs-container">
                                    <div style="font-size: 0.9rem; font-weight: 600; margin-bottom: 8px; color: #444;">
                                        Needs Assessment (<?php echo $needs_count; ?> items):
                                    </div>
                                    <?php foreach ($victim['needs'] as $need): ?>
                                        <div class="need-item">
                                            <span><?php echo htmlspecialchars($need['resource_name']); ?></span>
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
                                            <i class="fas fa-baby"></i> Baby Care
                                        </span>
                                    <?php endif; ?>
                                    <?php if ($victim['has_elderly']): ?>
                                        <span class="needs-badge needs-elderly">
                                            <i class="fas fa-walking-cane"></i> Elderly Care
                                        </span>
                                    <?php endif; ?>
                                    <?php if ($victim['has_disabled']): ?>
                                        <span class="needs-badge needs-disabled">
                                            <i class="fas fa-wheelchair"></i> Disability Support
                                        </span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            
                            <input type="checkbox" 
                                   name="selected_victims[]" 
                                   value="<?php echo $victim['victim_id']; ?>" 
                                   class="victim-checkbox"
                                   style="position: absolute; top: 20px; right: 20px; transform: scale(1.3);"
                                   onchange="updateVictimSummary()">
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
                                <i class="fas fa-arrow-right"></i> Proceed to Resource Allocation
                            </button>
                        </div>
                    </div>
                    
                    <!-- Step 3: Resource Allocation - IMPROVED LAYOUT -->
                    <div id="resource-section" style="display: none;">
                        <div class="section-title">
                            <i class="fas fa-boxes"></i>
                            <h2>Allocate Relief Resources</h2>
                        </div>
                        
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i>
                            <div>
                                <strong>Resource Allocation Guidelines</strong>
                                <br>
                                <small>Allocate resources based on family needs. Available quantities already subtract previous allocations.</small>
                            </div>
                        </div>
                        
                        <?php if (!empty($resources)): ?>
                            <div class="resource-grid-container">
                                <?php 
                                // Group resources by type
                                $resourceGroups = [];
                                foreach ($resources as $resource) {
                                    $type = $resource['type'] ?? 'General';
                                    if (!isset($resourceGroups[$type])) {
                                        $resourceGroups[$type] = [];
                                    }
                                    $resourceGroups[$type][] = $resource;
                                }

                                // Sort resource groups by type name
                                ksort($resourceGroups);
                                
                                foreach ($resourceGroups as $type => $typeResources): 
                                    // Skip if category has no resources
                                    if (empty($typeResources)) continue;
                                ?>
                                    <div class="resource-category">
                                        <div class="category-header">
                                            <div class="category-title">
                                                <i class="fas fa-tag"></i> <?php echo htmlspecialchars($type); ?> Resources
                                                <span class="category-count"><?php echo count($typeResources); ?> items</span>
                                            </div>
                                        </div>
                                        
                                        <div class="resources-columns">
                                            <?php foreach ($typeResources as $resource): 
                                                $previously_allocated = $resource['previously_allocated'] ?? 0;
                                                $needs_count = $resource['needs_count'] ?? 0;
                                                $needed_total = $resource['quantity_needed_total'] ?? 0;
                                                $resource_id = $resource['resource_id'];
                                                $input_name = $resource_id > 0 ? "resource_{$resource_id}" : "resource_named_" . md5($resource['name'] . $resource['type']);
                                            ?>
                                            <div class="resource-item">
                                                <div class="resource-header">
                                                    <div class="resource-name"><?php echo htmlspecialchars($resource['name']); ?></div>
                                                    <span class="available-badge">
                                                        <?php echo $resource['quantity_available']; ?> <?php echo htmlspecialchars($resource['unit']); ?>
                                                    </span>
                                                </div>
                                                
                                                <div class="resource-stats">
                                                    <div class="stat-row">
                                                        <span class="stat-label-small">
                                                            <i class="fas fa-balance-scale"></i> Unit:
                                                        </span>
                                                        <span class="stat-value"><?php echo htmlspecialchars($resource['unit']); ?></span>
                                                    </div>
                                                    
                                                    <?php if ($needs_count > 0): ?>
                                                        <div class="stat-row">
                                                            <span class="stat-label-small">
                                                                <i class="fas fa-clipboard-list"></i> Needed by:
                                                            </span>
                                                            <span class="stat-value"><?php echo $needs_count; ?> families</span>
                                                        </div>
                                                        <div class="stat-row">
                                                            <span class="stat-label-small">
                                                                <i class="fas fa-calculator"></i> Total demand:
                                                            </span>
                                                            <span class="stat-value"><?php echo number_format($needed_total, 2); ?> <?php echo htmlspecialchars($resource['unit']); ?></span>
                                                        </div>
                                                    <?php endif; ?>
                                                    
                                                    <?php if ($previously_allocated > 0): ?>
                                                        <div class="stat-row" style="color: #f39c12;">
                                                            <span class="stat-label-small">
                                                                <i class="fas fa-history"></i> Previously allocated:
                                                            </span>
                                                            <span class="stat-value"><?php echo $previously_allocated; ?> <?php echo htmlspecialchars($resource['unit']); ?></span>
                                                        </div>
                                                    <?php endif; ?>
                                                    
                                                    <?php if ($resource['quantity_available'] < $needed_total && $needed_total > 0): ?>
                                                        <div class="stat-row" style="color: #e74c3c;">
                                                            <span class="stat-label-small">
                                                                <i class="fas fa-exclamation-circle"></i> Status:
                                                            </span>
                                                            <span class="stat-value">Demand exceeds supply</span>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                                
                                                <div class="resource-controls">
                                                    <div>
                                                        <div class="form-label" style="font-size: 0.85rem; margin-bottom: 5px;">Allocate Quantity:</div>
                                                        <input type="number" 
                                                               name="<?php echo $input_name; ?>" 
                                                               class="quantity-input"
                                                               min="0" 
                                                               max="<?php echo $resource['quantity_available']; ?>"
                                                               value="0"
                                                               onchange="updateResourceTotal()"
                                                               data-available="<?php echo $resource['quantity_available']; ?>"
                                                               data-unit="<?php echo htmlspecialchars($resource['unit']); ?>"
                                                               data-name="<?php echo htmlspecialchars($resource['name']); ?>"
                                                               style="width: 100px;">
                                                    </div>
                                                    <div class="quantity-label">
                                                        Max: <?php echo $resource['quantity_available']; ?> <?php echo htmlspecialchars($resource['unit']); ?>
                                                    </div>
                                                </div>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <div class="summary-bar" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%);">
                                <div style="display: flex; justify-content: space-between; align-items: center;">
                                    <div>
                                        <h3 style="color: white; margin: 0;">Resource Allocation Summary</h3>
                                        <p style="color: rgba(255,255,255,0.9); margin: 5px 0 0 0;">
                                            Total allocated: <strong id="resource-total">0 units</strong>
                                        </p>
                                    </div>
                                    <button type="button" class="btn btn-primary" onclick="proceedToDetails()">
                                        <i class="fas fa-arrow-right"></i> Continue to Plan Details
                                    </button>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle"></i>
                                <div>
                                    <strong>No Resources Available</strong>
                                    <br>
                                    <small>The Needs API might be down or returning no data.</small>
                                </div>
                            </div>
                        <?php endif; ?>
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
                                <label class="form-label">Location *</label>
                                <select class="form-control" name="location" required>
                                    <option value="">Select distribution location...</option>
                                    <?php foreach ($pps_locations as $loc): ?>
                                    <option value="<?php echo htmlspecialchars($loc); ?>">
                                        <?php echo $loc; ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
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
                                <div class="stat-number" id="final-resource-count">0</div>
                                <div class="stat-label">Resource Types</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-number" id="final-duration">2</div>
                                <div class="stat-label">Hours Estimated</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-number" id="final-volunteers">4</div>
                                <div class="stat-label">Volunteers Needed</div>
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
        
        function toggleVictim(victimId) {
            const checkbox = document.querySelector(`input[value="${victimId}"]`);
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
            const selectedCount = checkboxes.length;
            const selectedCountEl = document.getElementById('selected-count');
            const proceedBtn = document.getElementById('proceed-btn');
            
            if (selectedCountEl) {
                selectedCountEl.textContent = selectedCount;
                document.getElementById('final-family-count').textContent = selectedCount;
            }
            
            if (proceedBtn) {
                proceedBtn.disabled = selectedCount === 0;
            }
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
            
            const resourceTotalEl = document.getElementById('resource-total');
            if (resourceTotalEl) {
                resourceTotalEl.textContent = total + ' units';
                document.getElementById('final-resource-count').textContent = resourceCount;
            }
        }
        
        function proceedToResources() {
            document.getElementById('resource-section').style.display = 'block';
            document.getElementById('details-section').style.display = 'none';
            currentStep = 3;
            updateStepIndicator();
        }
        
        function proceedToDetails() {
            document.getElementById('details-section').style.display = 'block';
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
        
        document.addEventListener('DOMContentLoaded', function() {
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
            
            // Auto-proceed if we have victims
            if (document.querySelectorAll('input[name="selected_victims[]"]').length > 0) {
                currentStep = 2;
                updateStepIndicator();
            }
            
            // Update step indicator based on current state
            if (document.getElementById('resource-section').style.display === 'block') {
                currentStep = 3;
            } else if (document.getElementById('details-section').style.display === 'block') {
                currentStep = 4;
            }
            updateStepIndicator();
            
            // Initialize summary values
            updateVictimSummary();
            updateResourceTotal();
        });
        
        // Update duration and volunteers in real-time
        document.addEventListener('input', function(e) {
            if (e.target.name === 'estimated_duration') {
                document.getElementById('final-duration').textContent = e.target.value;
            }
            if (e.target.name === 'volunteers_needed') {
                document.getElementById('final-volunteers').textContent = e.target.value;
            }
        });
    </script>
</body>
</html>