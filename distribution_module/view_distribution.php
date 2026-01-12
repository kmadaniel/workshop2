<?php
// ========================================
// VIEW DISTRIBUTION DETAILS - SHELTER BASED VERSION
// Updated to show shelters instead of individual addresses
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
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'User-Agent: DisasterReliefSystem/1.0'
        ]
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($response === false) {
        return [
            'success' => false,
            'error' => "CURL Error: $error",
            'http_code' => $httpCode
        ];
    }
    
    $data = json_decode($response, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        return [
            'success' => false,
            'error' => 'Invalid JSON: ' . json_last_error_msg(),
            'raw_response' => substr($response, 0, 200),
            'http_code' => $httpCode
        ];
    }
    
    return [
        'success' => true,
        'data' => $data,
        'http_code' => $httpCode
    ];
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
        if (isset($apiDisaster['disaster_id']) && intval($apiDisaster['disaster_id']) == $disaster_id_from_distribution) {
            $disaster_info = [
                'disaster_id' => intval($apiDisaster['disaster_id']),
                'disaster_name' => $apiDisaster['disaster_name'] ?? 'Unknown',
                'description' => $apiDisaster['description'] ?? '',
                'district' => $apiDisaster['district'] ?? 'Unknown',
                'severity' => $apiDisaster['severity'] ?? 'Medium',
                'start_date' => $apiDisaster['start_date'] ?? 'Unknown',
                'end_date' => $apiDisaster['end_date'] ?? 'Unknown',
                'affected_people' => $apiDisaster['affected_people'] ?? '0'
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
   GET VICTIM DETAILS FROM API AND GROUP BY SHELTER
---------------------------------------- */
$distribution_victims = [];
$shelters = []; // To track unique shelters
$all_victim_needs = []; // Store all needs for each victim

if ($victimApiResult['success'] && isset($victimApiResult['data']) && is_array($victimApiResult['data']) && !empty($distribution_victim_ids)) {
    $apiVictims = $victimApiResult['data'];
    
    foreach ($distribution_victim_ids as $victim_item) {
        $victimId = $victim_item['victim_id'];
        $found = false;
        
        foreach ($apiVictims as $apiVictim) {
            if (isset($apiVictim['victim_id']) && intval($apiVictim['victim_id']) == $victimId) {
                $found = true;
                
                // Get needs info for this victim from needs API
                $needsInfo = [
                    'has_baby' => false,
                    'has_elderly' => false,
                    'has_disabled' => false,
                    'priority' => 'Medium',
                    'special_requests' => '',
                    'special_needs_items' => [],
                    'normal_needs_items' => [],
                    'quantity_needed' => 0
                ];
                
                if ($needsApiResult['success'] && isset($needsApiResult['data']) && is_array($needsApiResult['data'])) {
                    $needsData = $needsApiResult['data'];
                    
                    // Check if data is nested in 'data' array
                    if (isset($needsData['data']) && is_array($needsData['data'])) {
                        $needsData = $needsData['data'];
                    }
                    
                    foreach ($needsData as $need) {
                        if (isset($need['victim_id']) && intval($need['victim_id']) == $victimId) {
                            // Extract special requests
                            $specialRequests = '';
                            $specialNeedsItems = [];
                            
                            if (!empty($need['special_needs_requests'])) {
                                $specialRequests = $need['special_needs_requests'];
                                // Parse special needs requests
                                if (strpos($specialRequests, '{') !== false) {
                                    // Remove curly braces and quotes
                                    $specialRequests = str_replace(['{', '}', '"'], '', $specialRequests);
                                    // Split by commas
                                    $specialNeedsItems = array_map('trim', explode(',', $specialRequests));
                                    $specialRequests = implode(', ', $specialNeedsItems);
                                }
                            }
                            
                            // Parse temp_resource_name for normal needs
                            $normalNeedsItems = [];
                            if (!empty($need['temp_resource_name'])) {
                                $normalNeedsItems = array_map('trim', explode("\n", $need['temp_resource_name']));
                            }
                            
                            $needsInfo = [
                                'has_baby' => $need['has_baby'] ?? false,
                                'has_elderly' => $need['has_elderly'] ?? false,
                                'has_disabled' => $need['has_disabled'] ?? false,
                                'priority' => $need['priority'] ?? 'Medium',
                                'special_requests' => $specialRequests,
                                'special_needs_items' => $specialNeedsItems,
                                'normal_needs_items' => $normalNeedsItems,
                                'quantity_needed' => floatval($need['quantity_needed'] ?? 0),
                                'special_needs_quantity' => floatval($need['special_needs_quantity'] ?? 0),
                                'normal_needs_quantity' => floatval($need['normal_needs_quantity'] ?? 0)
                            ];
                            
                            // Store needs for this victim
                            $all_victim_needs[$victimId] = $needsInfo;
                            break;
                        }
                    }
                }
                
                // Get shelter information
                $shelter = $apiVictim['selected_shelter'] ?? 'No Shelter Assigned';
                
                // Track unique shelters
                if (!isset($shelters[$shelter])) {
                    $shelters[$shelter] = [
                        'name' => $shelter,
                        'victim_count' => 0,
                        'victims' => [],
                        'total_baby' => 0,
                        'total_elderly' => 0,
                        'total_disabled' => 0,
                        'special_requests' => []
                    ];
                }
                
                $shelters[$shelter]['victim_count']++;
                $shelters[$shelter]['victims'][] = $victimId;
                
                // Track special needs counts
                if ($needsInfo['has_baby']) {
                    $shelters[$shelter]['total_baby']++;
                }
                if ($needsInfo['has_elderly']) {
                    $shelters[$shelter]['total_elderly']++;
                }
                if ($needsInfo['has_disabled']) {
                    $shelters[$shelter]['total_disabled']++;
                }
                
                // Collect special requests
                if (!empty($needsInfo['special_requests'])) {
                    if (!in_array($needsInfo['special_requests'], $shelters[$shelter]['special_requests'])) {
                        $shelters[$shelter]['special_requests'][] = $needsInfo['special_requests'];
                    }
                }
                
                $victim_data = array_merge($victim_item, [
                    'victim_id' => $victimId,
                    'full_name' => $apiVictim['full_name'] ?? 'Unknown',
                    'ic_number' => $apiVictim['ic_number'] ?? 'N/A',
                    'email' => $apiVictim['email'] ?? 'N/A',
                    'phone' => $apiVictim['phone'] ?? '',
                    'district' => $apiVictim['district'] ?? 'Unknown',
                    'family_members' => $apiVictim['family_members'] ?? 1,
                    'has_baby' => $needsInfo['has_baby'],
                    'has_elderly' => $needsInfo['has_elderly'],
                    'has_disabled' => $needsInfo['has_disabled'],
                    'priority' => $needsInfo['priority'],
                    'special_requests' => $needsInfo['special_requests'],
                    'special_needs_items' => $needsInfo['special_needs_items'],
                    'normal_needs_items' => $needsInfo['normal_needs_items'],
                    'quantity_needed' => $needsInfo['quantity_needed'],
                    'special_needs_quantity' => $needsInfo['special_needs_quantity'],
                    'normal_needs_quantity' => $needsInfo['normal_needs_quantity'],
                    'selected_shelter' => $shelter,
                    'api_missing' => false
                ]);
                
                $distribution_victims[] = $victim_data;
                break;
            }
        }
        
        if (!$found) {
            // Add placeholder if victim not found
            $distribution_victims[] = array_merge($victim_item, [
                'victim_id' => $victimId,
                'full_name' => 'Victim #' . $victimId . ' (API data unavailable)',
                'selected_shelter' => 'Unknown Shelter',
                'ic_number' => 'N/A',
                'email' => 'N/A',
                'phone' => '',
                'district' => 'Unknown',
                'family_members' => 1,
                'has_baby' => false,
                'has_elderly' => false,
                'has_disabled' => false,
                'priority' => 'Medium',
                'special_requests' => '',
                'special_needs_items' => [],
                'normal_needs_items' => [],
                'quantity_needed' => 0,
                'special_needs_quantity' => 0,
                'normal_needs_quantity' => 0,
                'api_missing' => true
            ]);
        }
    }
}

/* ----------------------------------------
   CALCULATE TOTAL FAMILIES
---------------------------------------- */
$total_families = count($distribution_victims);
$total_resources_allocated = 0;

/* ----------------------------------------
   ANALYZE NEEDS FROM API FOR ALL FAMILIES
---------------------------------------- */
// Collect all needs from victims to determine common items
$all_special_needs_items = [];
$all_normal_needs_items = [];
$all_special_requests = [];

foreach ($distribution_victims as $victim) {
    if (!$victim['api_missing']) {
        // Collect special needs items
        foreach ($victim['special_needs_items'] as $item) {
            if (!empty($item)) {
                if (!isset($all_special_needs_items[$item])) {
                    $all_special_needs_items[$item] = 0;
                }
                $all_special_needs_items[$item]++;
            }
        }
        
        // Collect normal needs items
        foreach ($victim['normal_needs_items'] as $item) {
            if (!empty($item)) {
                if (!isset($all_normal_needs_items[$item])) {
                    $all_normal_needs_items[$item] = 0;
                }
                $all_normal_needs_items[$item]++;
            }
        }
        
        // Collect special requests
        if (!empty($victim['special_requests'])) {
            $all_special_requests[] = $victim['special_requests'];
        }
    }
}

// Sort items by frequency (most common first)
arsort($all_normal_needs_items);
arsort($all_special_needs_items);

// Get top 6 most common normal needs for all families
$common_normal_needs = array_slice($all_normal_needs_items, 0, 6, true);

// Get all special needs items
$common_special_needs = $all_special_needs_items;

/* ----------------------------------------
   AUTOMATED BASIC NEEDS ALLOCATION
   Every family gets the same basic items
---------------------------------------- */
$basic_needs = [
    'food' => [
        'name' => 'Food Supplies',
        'icon' => 'fas fa-utensils',
        'unit' => 'packages',
        'quantity' => $total_families * 2, // 2 packages per family
        'color' => '#3498db',
        'description' => 'Emergency food packages for all families'
    ],
    'water' => [
        'name' => 'Clean Water',
        'icon' => 'fas fa-tint',
        'unit' => 'bottles',
        'quantity' => $total_families * 6, // 6 bottles per family
        'color' => '#2980b9',
        'description' => 'Drinking water for all families'
    ],
    'clothing' => [
        'name' => 'Clothing Sets',
        'icon' => 'fas fa-tshirt',
        'unit' => 'sets',
        'quantity' => $total_families * 4, // 4 sets per family
        'color' => '#2ecc71',
        'description' => 'Basic clothing for all family members'
    ],
    'blankets' => [
        'name' => 'Blankets',
        'icon' => 'fas fa-bed',
        'unit' => 'pieces',
        'quantity' => $total_families * 2, // 2 blankets per family
        'color' => '#27ae60',
        'description' => 'Warm blankets for all families'
    ],
    'hygiene' => [
        'name' => 'Hygiene Kits',
        'icon' => 'fas fa-soap',
        'unit' => 'kits',
        'quantity' => $total_families, // 1 kit per family
        'color' => '#9b59b6',
        'description' => 'Basic hygiene supplies for all families'
    ],
    'medical' => [
        'name' => 'First Aid Kits',
        'icon' => 'fas fa-first-aid',
        'unit' => 'kits',
        'quantity' => ceil($total_families / 5), // 1 kit per 5 families
        'color' => '#e74c3c',
        'description' => 'Basic first aid supplies shared among families'
    ]
];

/* ----------------------------------------
   SPECIAL NEEDS BASED ON API DATA
   Only allocate for families that need them
---------------------------------------- */
$special_needs = [
    'baby_kits' => [
        'name' => 'Baby Care Kits',
        'icon' => 'fas fa-baby',
        'unit' => 'kits',
        'quantity' => 0,
        'color' => '#f1c40f',
        'description' => 'Special kits for families with babies',
        'items' => []
    ],
    'elderly_kits' => [
        'name' => 'Elderly Care Kits',
        'icon' => 'fas fa-user-friends',
        'unit' => 'kits',
        'quantity' => 0,
        'color' => '#e67e22',
        'description' => 'Special kits for elderly family members',
        'items' => []
    ],
    'disabled_kits' => [
        'name' => 'Disabled Care Kits',
        'icon' => 'fas fa-wheelchair',
        'unit' => 'kits',
        'quantity' => 0,
        'color' => '#1abc9c',
        'description' => 'Special kits for disabled family members',
        'items' => []
    ]
];

// Collect common special needs items from API
$special_items_by_type = [
    'baby' => [],
    'elderly' => [],
    'disabled' => []
];

// Analyze special needs items from API
foreach ($common_special_needs as $item => $count) {
    $lower_item = strtolower($item);
    
    if (strpos($lower_item, 'baby') !== false || 
        strpos($lower_item, 'bottle') !== false || 
        strpos($lower_item, 'diaper') !== false ||
        strpos($lower_item, 'formula') !== false) {
        $special_items_by_type['baby'][] = $item;
    } elseif (strpos($lower_item, 'elderly') !== false || 
              strpos($lower_item, 'walker') !== false || 
              strpos($lower_item, 'cane') !== false ||
              strpos($lower_item, 'adult') !== false ||
              strpos($lower_item, 'senior') !== false) {
        $special_items_by_type['elderly'][] = $item;
    } elseif (strpos($lower_item, 'disabled') !== false || 
              strpos($lower_item, 'wheelchair') !== false || 
              strpos($lower_item, 'crutch') !== false ||
              strpos($lower_item, 'accessible') !== false) {
        $special_items_by_type['disabled'][] = $item;
    }
}

// Count victims with special needs and assign items
$baby_count = 0;
$elderly_count = 0;
$disabled_count = 0;

foreach ($distribution_victims as $victim) {
    if ($victim['has_baby']) {
        $baby_count++;
        $special_needs['baby_kits']['quantity']++;
    }
    if ($victim['has_elderly']) {
        $elderly_count++;
        $special_needs['elderly_kits']['quantity']++;
    }
    if ($victim['has_disabled']) {
        $disabled_count++;
        $special_needs['disabled_kits']['quantity']++;
    }
}

// Assign items to kits
$special_needs['baby_kits']['items'] = array_slice($special_items_by_type['baby'], 0, 5); // Top 5 baby items
$special_needs['elderly_kits']['items'] = array_slice($special_items_by_type['elderly'], 0, 5); // Top 5 elderly items
$special_needs['disabled_kits']['items'] = array_slice($special_items_by_type['disabled'], 0, 5); // Top 5 disabled items

/* ----------------------------------------
   ADDITIONAL RESOURCES FROM COMMON NORMAL NEEDS
---------------------------------------- */
$additional_resources = [];
$resource_counter = 0;

foreach ($common_normal_needs as $item => $count) {
    $resource_counter++;
    $percentage = $total_families > 0 ? round(($count / $total_families) * 100) : 0;
    
    // Determine unit based on item name
    $lower_item = strtolower($item);
    if (strpos($lower_item, 'food') !== false || strpos($lower_item, 'rice') !== false || 
        strpos($lower_item, 'noodle') !== false || strpos($lower_item, 'meal') !== false) {
        $unit = 'packages';
    } elseif (strpos($lower_item, 'water') !== false || strpos($lower_item, 'drink') !== false || 
              strpos($lower_item, 'bottle') !== false) {
        $unit = 'bottles';
    } elseif (strpos($lower_item, 'medicine') !== false || strpos($lower_item, 'pill') !== false || 
              strpos($lower_item, 'tablet') !== false) {
        $unit = 'packets';
    } elseif (strpos($lower_item, 'kit') !== false || strpos($lower_item, 'set') !== false) {
        $unit = 'kits';
    } else {
        $unit = 'units';
    }
    
    // Allocate quantity based on how many families need it
    $quantity = ceil($count * 1.5); // Give 1.5 units per family that needs it
    
    $additional_resources[] = [
        'name' => $item,
        'quantity' => $quantity,
        'unit' => $unit,
        'families_needing' => $count,
        'percentage' => $percentage,
        'color' => sprintf('#%06X', mt_rand(0, 0xFFFFFF)) // Random color
    ];
    
    if ($resource_counter >= 6) break; // Limit to 6 additional resources
}

/* ----------------------------------------
   SPECIAL REQUESTS SUMMARY
---------------------------------------- */
$special_requests_summary = [];
if (!empty($all_special_requests)) {
    $request_counts = array_count_values($all_special_requests);
    arsort($request_counts);
    
    foreach ($request_counts as $request => $count) {
        $percentage = $total_families > 0 ? round(($count / $total_families) * 100) : 0;
        $special_requests_summary[] = [
            'request' => $request,
            'count' => $count,
            'percentage' => $percentage
        ];
    }
}

/* ----------------------------------------
   GET RESOURCE ALLOCATIONS FROM DATABASE
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

if (!empty($distribution_resources)) {
    $total_resources_allocated = array_sum(array_column($distribution_resources, 'quantity_allocated'));
}

// Calculate totals
$total_basic_needs = array_sum(array_column($basic_needs, 'quantity'));
$total_special_needs = array_sum(array_column($special_needs, 'quantity'));
$total_additional_resources = array_sum(array_column($additional_resources, 'quantity'));

/* ----------------------------------------
   GET ASSIGNED VOLUNTEERS WITH NAMES
---------------------------------------- */
$assigned_volunteers = [];

// Create a mapping of volunteer ID to volunteer name from API
$volunteerMap = [];
if ($volunteerApiResult['success'] && isset($volunteerApiResult['data']) && is_array($volunteerApiResult['data'])) {
    $apiVolunteers = $volunteerApiResult['data'];
    
    // Handle different API response formats
    if (isset($apiVolunteers['volunteers']) && is_array($apiVolunteers['volunteers'])) {
        $apiVolunteers = $apiVolunteers['volunteers'];
    } elseif (isset($apiVolunteers['data']) && is_array($apiVolunteers['data'])) {
        $apiVolunteers = $apiVolunteers['data'];
    }
    
    foreach ($apiVolunteers as $volunteer) {
        // Extract volunteer ID from different possible field names
        $volunteerId = $volunteer['volunteer_id'] ?? 
                      $volunteer['Volunteer_ID'] ?? 
                      $volunteer['id'] ?? 
                      $volunteer['ID'] ?? null;
        
        $volunteerName = $volunteer['name'] ?? 
                        $volunteer['full_name'] ?? 
                        $volunteer['Full_Name'] ??
                        $volunteer['Name'] ?? 
                        null;
        
        if ($volunteerId && $volunteerName) {
            $volunteerMap[intval($volunteerId)] = [
                'name' => $volunteerName,
                'phone' => $volunteer['phone'] ?? $volunteer['Phone'] ?? '',
                'email' => $volunteer['email'] ?? $volunteer['Email'] ?? '',
                'role' => $volunteer['role'] ?? $volunteer['Role'] ?? $volunteer['SkillCategory'] ?? 'Volunteer',
                'ngo' => $volunteer['ngo'] ?? $volunteer['NGO'] ?? $volunteer['AssignedNGO'] ?? 'Various'
            ];
        }
    }
}

// Check if distribution_volunteer table exists and fetch volunteers
$check_table_query = "SHOW TABLES LIKE 'distribution_volunteer'";
$table_result = $db->query($check_table_query);

if ($table_result && $table_result->num_rows > 0) {
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
            
            if ($volunteerInfo) {
                $assigned_volunteers[] = [
                    'volunteer_id' => $volunteerId,
                    'volunteer_name' => $volunteerInfo['name'],
                    'phone' => $volunteerInfo['phone'] ?? '',
                    'email' => $volunteerInfo['email'] ?? '',
                    'volunteer_role' => $volunteerInfo['role'] ?? 'Volunteer',
                    'ngo' => $volunteerInfo['ngo'] ?? 'Various',
                    'status' => $volunteer['status'] ?? 'Active',
                    'assigned_timestamp' => $volunteer['assigned_timestamp'] ?? date('Y-m-d H:i:s')
                ];
            } else {
                // If volunteer not found in API, try to get from distribution_volunteer table if name exists
                $assigned_volunteers[] = [
                    'volunteer_id' => $volunteerId,
                    'volunteer_name' => $volunteer['volunteer_name'] ?? ('Volunteer #' . $volunteerId),
                    'phone' => $volunteer['phone'] ?? '',
                    'email' => $volunteer['email'] ?? '',
                    'volunteer_role' => $volunteer['volunteer_role'] ?? 'Volunteer',
                    'ngo' => $volunteer['ngo'] ?? 'Various',
                    'status' => $volunteer['status'] ?? 'Active',
                    'assigned_timestamp' => $volunteer['assigned_timestamp'] ?? date('Y-m-d H:i:s')
                ];
            }
        }
    }
}

// Count shelters
$total_shelters = count($shelters);

/* ----------------------------------------
   PARSE PLAN DETAILS FROM COMMENTS
---------------------------------------- */
$plan_details = [];
if ($distribution['comments']) {
    $lines = explode("\n", $distribution['comments']);
    foreach ($lines as $line) {
        $line = trim($line);
        if (strpos($line, 'Shelter:') !== false) {
            $plan_details['shelter'] = trim(str_replace('Shelter:', '', $line));
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

// If shelter is not in comments, use distribution location
if (empty($plan_details['shelter']) && !empty($distribution['location'])) {
    $plan_details['shelter'] = $distribution['location'];
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

// Helper function to get shelter icon
function getShelterIcon($shelter_name) {
    $lower_name = strtolower($shelter_name);
    
    if (strpos($lower_name, 'school') !== false) {
        return 'fas fa-school';
    } elseif (strpos($lower_name, 'stadium') !== false) {
        return 'fas fa-futbol';
    } elseif (strpos($lower_name, 'hall') !== false) {
        return 'fas fa-building';
    } elseif (strpos($lower_name, 'mosque') !== false) {
        return 'fas fa-mosque';
    } elseif (strpos($lower_name, 'church') !== false) {
        return 'fas fa-church';
    } elseif (strpos($lower_name, 'temple') !== false) {
        return 'fas fa-place-of-worship';
    } elseif (strpos($lower_name, 'community') !== false) {
        return 'fas fa-users';
    } elseif (strpos($lower_name, 'center') !== false) {
        return 'fas fa-warehouse';
    } else {
        return 'fas fa-home';
    }
}

// Helper function to get shelter type
function getShelterType($shelter_name) {
    $lower_name = strtolower($shelter_name);
    
    if (strpos($lower_name, 'school') !== false) {
        return 'School';
    } elseif (strpos($lower_name, 'stadium') !== false) {
        return 'Stadium';
    } elseif (strpos($lower_name, 'hall') !== false) {
        return 'Community Hall';
    } elseif (strpos($lower_name, 'mosque') !== false || 
              strpos($lower_name, 'church') !== false || 
              strpos($lower_name, 'temple') !== false) {
        return 'Place of Worship';
    } elseif (strpos($lower_name, 'emergency') !== false) {
        return 'Emergency Shelter';
    } elseif (strpos($lower_name, 'community') !== false) {
        return 'Community Center';
    } else {
        return 'Temporary Shelter';
    }
}

// Helper function to format item list
function formatItemList($items, $limit = 5) {
    if (empty($items)) {
        return 'None specified';
    }
    
    if (count($items) <= $limit) {
        return implode(', ', $items);
    }
    
    $display_items = array_slice($items, 0, $limit);
    return implode(', ', $display_items) . ' and ' . (count($items) - $limit) . ' more';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Distribution #<?php echo $distribution_id; ?> - Shelter Distribution | Disaster Relief System</title>
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
            --shelter-blue: #3498db;
            --shelter-green: #2ecc71;
            --shelter-orange: #e67e22;
            --shelter-purple: #9b59b6;
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
            background: linear-gradient(135deg, var(--shelter-blue) 0%, #2980b9 100%);
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
        
        .header-subtitle .shelter-badge {
            background: rgba(255,255,255,0.2);
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.9rem;
            margin-left: 10px;
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
            border-top: 4px solid var(--shelter-blue);
        }
        
        .stat-card:nth-child(2) {
            border-top-color: var(--shelter-green);
        }
        
        .stat-card:nth-child(3) {
            border-top-color: var(--shelter-orange);
        }
        
        .stat-card:nth-child(4) {
            border-top-color: var(--shelter-purple);
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
        
        /* ===== SHELTER CARDS ===== */
        .shelter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 25px;
            margin-top: 20px;
        }
        
        .shelter-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            border: 1px solid var(--light-gray);
            transition: var(--transition);
            position: relative;
            overflow: hidden;
            box-shadow: var(--box-shadow);
        }
        
        .shelter-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 5px;
            background: linear-gradient(90deg, var(--shelter-blue), var(--shelter-green));
        }
        
        .shelter-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 30px rgba(0,0,0,0.15);
        }
        
        .shelter-header {
            display: flex;
            align-items: flex-start;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .shelter-icon {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, var(--shelter-blue), #2980b9);
            color: white;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            flex-shrink: 0;
        }
        
        .shelter-info {
            flex-grow: 1;
        }
        
        .shelter-name {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--secondary);
            margin-bottom: 5px;
        }
        
        .shelter-type {
            color: var(--gray);
            font-size: 0.9rem;
            margin-bottom: 10px;
        }
        
        .shelter-stats {
            display: flex;
            gap: 20px;
            margin-top: 15px;
        }
        
        .shelter-stat {
            text-align: center;
            padding: 10px;
            background: var(--light);
            border-radius: 10px;
            min-width: 80px;
        }
        
        .shelter-stat-number {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--shelter-blue);
        }
        
        .shelter-stat-label {
            font-size: 0.8rem;
            color: var(--gray);
            margin-top: 5px;
        }
        
        .shelter-victims {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid var(--light-gray);
        }
        
        .shelter-victims h4 {
            color: var(--secondary);
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .victim-list {
            max-height: 200px;
            overflow-y: auto;
            padding-right: 10px;
        }
        
        .victim-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px;
            background: var(--light);
            border-radius: 8px;
            margin-bottom: 8px;
            transition: var(--transition);
        }
        
        .victim-item:hover {
            background: #e3f2fd;
            transform: translateX(5px);
        }
        
        .victim-avatar {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, var(--shelter-green), #27ae60);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 1rem;
            flex-shrink: 0;
        }
        
        .victim-details {
            flex-grow: 1;
        }
        
        .victim-name {
            font-weight: 600;
            color: var(--secondary);
            margin-bottom: 3px;
        }
        
        .victim-info {
            font-size: 0.85rem;
            color: var(--gray);
        }
        
        .victim-needs {
            display: flex;
            gap: 5px;
            margin-top: 5px;
        }
        
        .need-badge {
            width: 8px;
            height: 8px;
            border-radius: 50%;
        }
        
        .need-baby { background: #ffd700; }
        .need-elderly { background: #ff6b6b; }
        .need-disabled { background: #4ecdc4; }
        
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
            display: flex;
            align-items: center;
            justify-content: space-between;
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
            align-items: center;
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
        
        .basic-need-card {
            background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
            border-left: 4px solid var(--shelter-blue);
        }
        
        .special-need-card {
            background: linear-gradient(135deg, #f3e5f5 0%, #e1bee7 100%);
            border-left: 4px solid var(--shelter-purple);
        }
        
        .need-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.2rem;
        }
        
        /* ===== ADDITIONAL RESOURCES ===== */
        .additional-resource-card {
            background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
            border-left: 4px solid var(--warning);
        }
        
        .family-need-badge {
            background: var(--light);
            padding: 4px 10px;
            border-radius: 15px;
            font-size: 0.8rem;
            color: var(--dark);
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
            .shelter-grid,
            .resource-grid {
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
            
            .shelter-header {
                flex-direction: column;
                text-align: center;
            }
            
            .shelter-icon {
                align-self: center;
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
        
        /* ===== DISTRIBUTION INFO ===== */
        .distribution-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin: 15px 0;
        }
        
        .distribution-info-item {
            background: var(--light);
            padding: 15px;
            border-radius: 8px;
            border-left: 3px solid var(--shelter-blue);
        }
        
        .distribution-info-label {
            font-size: 0.8rem;
            color: var(--gray);
            margin-bottom: 5px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .distribution-info-value {
            font-size: 1rem;
            color: var(--secondary);
            font-weight: 600;
        }
        
        .shelter-distribution-plan {
            margin-top: 30px;
            padding: 25px;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 15px;
            border: 2px dashed var(--shelter-blue);
        }
        
        .shelter-distribution-plan h3 {
            color: var(--shelter-blue);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .shelter-plan-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }
        
        .shelter-plan-item {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .shelter-plan-item h4 {
            color: var(--secondary);
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .shelter-plan-item ul {
            list-style: none;
            padding-left: 0;
        }
        
        .shelter-plan-item li {
            padding: 5px 0;
            border-bottom: 1px solid var(--light-gray);
        }
        
        .shelter-plan-item li:last-child {
            border-bottom: none;
        }
        
        /* ===== VOLUNTEER CARDS ===== */
        .volunteer-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
        }
        
        .volunteer-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            border: 1px solid var(--light-gray);
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }
        
        .volunteer-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.1);
            border-color: var(--primary);
        }
        
        .volunteer-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, var(--success), #27ae60);
        }
        
        .volunteer-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .volunteer-avatar {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, var(--success), #27ae60);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 1.2rem;
            flex-shrink: 0;
        }
        
        .volunteer-name {
            font-weight: 600;
            color: var(--secondary);
            font-size: 1.1rem;
            margin-bottom: 5px;
        }
        
        .volunteer-role {
            background: var(--light-gray);
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            color: var(--gray);
        }
        
        .volunteer-details {
            font-size: 0.9rem;
            color: var(--gray);
        }
        
        .volunteer-details p {
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .volunteer-details i {
            width: 20px;
            color: var(--primary);
        }
        
        /* ===== NEEDS SUMMARY ===== */
        .needs-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }
        
        .needs-summary-item {
            background: white;
            padding: 15px;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            border-top: 4px solid var(--shelter-blue);
        }
        
        .needs-summary-item.special {
            border-top-color: var(--shelter-purple);
        }
        
        .needs-summary-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 10px;
            color: white;
            font-size: 1.5rem;
        }
        
        .needs-summary-number {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--secondary);
            margin: 5px 0;
        }
        
        .needs-summary-label {
            font-size: 0.85rem;
            color: var(--gray);
        }
        
        /* ===== SPECIAL REQUESTS ===== */
        .special-requests-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .request-card {
            background: linear-gradient(135deg, #f0f7ff 0%, #e1eeff 100%);
            border-radius: 10px;
            padding: 20px;
            border-left: 4px solid #4a6fa5;
        }
        
        .request-content {
            margin-top: 10px;
            padding: 10px;
            background: white;
            border-radius: 8px;
            font-style: italic;
            color: #555;
        }
        
        .request-stats {
            display: flex;
            justify-content: space-between;
            margin-top: 15px;
            font-size: 0.9rem;
            color: var(--gray);
        }
        
        /* ===== ITEM LIST ===== */
        .item-list {
            margin-top: 10px;
            padding: 10px;
            background: rgba(255,255,255,0.7);
            border-radius: 8px;
            font-size: 0.85rem;
        }
        
        .item-list ul {
            list-style-type: none;
            padding-left: 0;
            margin: 0;
        }
        
        .item-list li {
            padding: 3px 0;
            border-bottom: 1px dashed rgba(0,0,0,0.1);
        }
        
        .item-list li:last-child {
            border-bottom: none;
        }
    </style>
</head>
<body>
    <!-- Loading Screen -->
    <div id="loading-screen" class="loading-screen">
        <div class="loading-content">
            <div class="loading-spinner"></div>
            <h3>Loading Shelter Distribution Details</h3>
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
                        <?php if ($needsApiResult['success']): ?>
                        <p style="color: var(--success); font-size: 0.7rem;">
                            <?php echo count($distribution_victims); ?> victims needs analyzed
                        </p>
                        <?php endif; ?>
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
                        <i class="fas fa-home"></i>
                        Shelter Distribution Plan
                    </h1>
                    <p class="header-subtitle">
                        <strong>Distribution ID:</strong> DIST<?php echo str_pad($distribution_id, 3, '0', STR_PAD_LEFT); ?>
                        • <strong>Status:</strong> <?php echo ucfirst($distribution['status'] ?? 'Unknown'); ?>
                        • <strong>Date:</strong> <?php echo date('F j, Y', strtotime($distribution['date'] ?? 'now')); ?>
                        <?php if (!empty($plan_details['shelter'])): ?>
                        <span class="shelter-badge">
                            <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($plan_details['shelter']); ?>
                        </span>
                        <?php endif; ?>
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
                        <button onclick="printPage()" class="btn btn-warning">
                            <i class="fas fa-print"></i> Print Report
                        </button>
                    </div>
                </div>
            </div>

            <!-- Quick Stats -->
            <div class="stats-grid fade-in">
                <div class="stat-card">
                    <i class="fas fa-home"></i>
                    <div class="stat-number"><?php echo $total_shelters; ?></div>
                    <div class="stat-label">Shelters Served</div>
                </div>
                
                <div class="stat-card">
                    <i class="fas fa-users"></i>
                    <div class="stat-number"><?php echo $total_families; ?></div>
                    <div class="stat-label">Families Assisted</div>
                </div>
                
                <div class="stat-card">
                    <i class="fas fa-boxes"></i>
                    <div class="stat-number"><?php echo $total_basic_needs + $total_special_needs + $total_additional_resources; ?></div>
                    <div class="stat-label">Total Resources</div>
                </div>
                
                <div class="stat-card">
                    <i class="fas fa-hands-helping"></i>
                    <div class="stat-number"><?php echo count($assigned_volunteers); ?></div>
                    <div class="stat-label">Volunteers</div>
                </div>
            </div>

            <!-- Needs Summary -->
            <div class="card fade-in">
                <div class="card-header">
                    <h2><i class="fas fa-clipboard-list"></i> Distribution Needs Summary</h2>
                </div>
                
                <div class="needs-summary">
                    <!-- Basic Needs Summary -->
                    <?php foreach ($basic_needs as $need_type => $need): ?>
                    <div class="needs-summary-item">
                        <div class="needs-summary-icon" style="background: <?php echo $need['color']; ?>;">
                            <i class="<?php echo $need['icon']; ?>"></i>
                        </div>
                        <div class="needs-summary-number"><?php echo $need['quantity']; ?></div>
                        <div class="needs-summary-label"><?php echo $need['name']; ?></div>
                    </div>
                    <?php endforeach; ?>
                    
                    <!-- Special Needs Summary -->
                    <?php foreach ($special_needs as $need_type => $need): 
                        if ($need['quantity'] > 0):
                    ?>
                    <div class="needs-summary-item special">
                        <div class="needs-summary-icon" style="background: <?php echo $need['color']; ?>;">
                            <i class="<?php echo $need['icon']; ?>"></i>
                        </div>
                        <div class="needs-summary-number"><?php echo $need['quantity']; ?></div>
                        <div class="needs-summary-label"><?php echo $need['name']; ?></div>
                    </div>
                    <?php endif; endforeach; ?>
                </div>
                
                <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid var(--light-gray); text-align: center;">
                    <div style="display: inline-flex; gap: 30px; flex-wrap: wrap; justify-content: center;">
                        <div>
                            <div style="font-size: 2rem; font-weight: 700; color: var(--shelter-blue);"><?php echo $total_basic_needs; ?></div>
                            <div style="color: var(--gray); font-size: 0.9rem;">Total Basic Needs</div>
                        </div>
                        <div>
                            <div style="font-size: 2rem; font-weight: 700; color: var(--shelter-purple);"><?php echo $total_special_needs; ?></div>
                            <div style="color: var(--gray); font-size: 0.9rem;">Special Needs Kits</div>
                        </div>
                        <?php if ($total_additional_resources > 0): ?>
                        <div>
                            <div style="font-size: 2rem; font-weight: 700; color: var(--warning);"><?php echo $total_additional_resources; ?></div>
                            <div style="color: var(--gray); font-size: 0.9rem;">Additional Resources</div>
                        </div>
                        <?php endif; ?>
                    </div>
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
                    
                    <div class="distribution-info-grid">
                        <div class="distribution-info-item">
                            <div class="distribution-info-label">Distribution ID</div>
                            <div class="distribution-info-value">DIST<?php echo str_pad($distribution['distribution_id'], 3, '0', STR_PAD_LEFT); ?></div>
                        </div>
                        
                        <div class="distribution-info-item">
                            <div class="distribution-info-label">Distribution Date</div>
                            <div class="distribution-info-value"><?php echo date('F j, Y g:i A', strtotime($distribution['date'] ?? 'now')); ?></div>
                        </div>
                        
                        <?php if (!empty($plan_details['shelter'])): ?>
                        <div class="distribution-info-item">
                            <div class="distribution-info-label">Main Shelter Location</div>
                            <div class="distribution-info-value"><?php echo htmlspecialchars($plan_details['shelter']); ?></div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($plan_details['coordinator'])): ?>
                        <div class="distribution-info-item">
                            <div class="distribution-info-label">Coordinator</div>
                            <div class="distribution-info-value"><?php echo htmlspecialchars($plan_details['coordinator']); ?></div>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <?php if (!empty($plan_details['duration']) || !empty($plan_details['volunteers_needed'])): ?>
                    <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid var(--light-gray);">
                        <div class="distribution-info-grid">
                            <?php if (!empty($plan_details['duration'])): ?>
                            <div class="distribution-info-item">
                                <div class="distribution-info-label"><i class="fas fa-clock"></i> Estimated Duration</div>
                                <div class="distribution-info-value"><?php echo htmlspecialchars($plan_details['duration']); ?></div>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($plan_details['volunteers_needed'])): ?>
                            <div class="distribution-info-item">
                                <div class="distribution-info-label"><i class="fas fa-users"></i> Volunteers Needed</div>
                                <div class="distribution-info-value"><?php echo htmlspecialchars($plan_details['volunteers_needed']); ?></div>
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
                        <?php if ($disaster_info && !empty($disaster_info['severity'])): ?>
                        <?php 
                        $severity = strtolower($disaster_info['severity']);
                        ?>
                        <span class="severity-badge severity-<?php echo $severity; ?>">
                            <?php echo htmlspecialchars($disaster_info['severity']); ?>
                        </span>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($disaster_info): ?>
                    <div class="info-grid">
                        <div class="info-item" style="grid-column: span 2;">
                            <label>Disaster Name</label>
                            <div class="value" style="font-size: 1.2rem; font-weight: 600;">
                                <?php echo htmlspecialchars($disaster_info['disaster_name']); ?>
                            </div>
                        </div>
                        
                        <div class="info-item">
                            <label>Type</label>
                            <div class="value"><?php echo htmlspecialchars($disaster_info['description'] ? substr($disaster_info['description'], 0, 30) . '...' : 'Natural Disaster'); ?></div>
                        </div>
                        
                        <div class="info-item">
                            <label>Date</label>
                            <div class="value"><?php echo date('F j, Y', strtotime($disaster_info['start_date'])); ?></div>
                        </div>
                        
                        <div class="info-item">
                            <label>Location</label>
                            <div class="value"><?php echo htmlspecialchars($disaster_info['district']); ?></div>
                        </div>
                        
                        <?php if (!empty($disaster_info['affected_people'])): ?>
                        <div class="info-item">
                            <label>Affected People</label>
                            <div class="value"><?php echo htmlspecialchars($disaster_info['affected_people']); ?></div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($disaster_info['description'])): ?>
                        <div class="info-item" style="grid-column: span 2;">
                            <label>Description</label>
                            <div class="value"><?php echo htmlspecialchars($disaster_info['description']); ?></div>
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

            <!-- Shelter Distribution Section -->
            <div class="card fade-in">
                <div class="card-header">
                    <h2><i class="fas fa-home"></i> Shelters Served</h2>
                    <span style="background: var(--light-gray); padding: 6px 16px; border-radius: 20px; font-size: 0.9rem; font-weight: 600;">
                        <?php echo $total_shelters; ?> shelters • <?php echo $total_families; ?> families
                    </span>
                </div>
                
                <?php if (!empty($shelters)): ?>
                <div class="shelter-grid">
                    <?php foreach ($shelters as $shelter_name => $shelter_data): 
                        $shelter_icon = getShelterIcon($shelter_name);
                        $shelter_type = getShelterType($shelter_name);
                        
                        // Get victims in this shelter
                        $shelter_victims = [];
                        foreach ($distribution_victims as $victim) {
                            if (($victim['selected_shelter'] ?? '') === $shelter_name) {
                                $shelter_victims[] = $victim;
                            }
                        }
                    ?>
                    <div class="shelter-card">
                        <div class="shelter-header">
                            <div class="shelter-icon">
                                <i class="<?php echo $shelter_icon; ?>"></i>
                            </div>
                            <div class="shelter-info">
                                <div class="shelter-name"><?php echo htmlspecialchars($shelter_name); ?></div>
                                <div class="shelter-type"><?php echo htmlspecialchars($shelter_type); ?></div>
                            </div>
                        </div>
                        
                        <div class="shelter-stats">
                            <div class="shelter-stat">
                                <div class="shelter-stat-number"><?php echo $shelter_data['victim_count']; ?></div>
                                <div class="shelter-stat-label">Families</div>
                            </div>
                            <?php if ($shelter_data['total_baby'] > 0): ?>
                            <div class="shelter-stat">
                                <div class="shelter-stat-number"><?php echo $shelter_data['total_baby']; ?></div>
                                <div class="shelter-stat-label">Babies</div>
                            </div>
                            <?php endif; ?>
                            <?php if ($shelter_data['total_elderly'] > 0): ?>
                            <div class="shelter-stat">
                                <div class="shelter-stat-number"><?php echo $shelter_data['total_elderly']; ?></div>
                                <div class="shelter-stat-label">Elderly</div>
                            </div>
                            <?php endif; ?>
                            <?php if ($shelter_data['total_disabled'] > 0): ?>
                            <div class="shelter-stat">
                                <div class="shelter-stat-number"><?php echo $shelter_data['total_disabled']; ?></div>
                                <div class="shelter-stat-label">Disabled</div>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <?php if (!empty($shelter_data['special_requests'])): ?>
                        <div style="margin-top: 15px; padding: 10px; background: #fff3cd; border-radius: 8px; font-size: 0.9rem;">
                            <strong><i class="fas fa-star"></i> Special Requests:</strong>
                            <?php echo implode(' • ', array_slice($shelter_data['special_requests'], 0, 3)); ?>
                            <?php if (count($shelter_data['special_requests']) > 3): ?>
                            <span style="color: var(--gray);">(+<?php echo count($shelter_data['special_requests']) - 3; ?> more)</span>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($shelter_victims)): ?>
                        <div class="shelter-victims">
                            <h4><i class="fas fa-users"></i> Families at this Shelter</h4>
                            <div class="victim-list">
                                <?php foreach ($shelter_victims as $victim): 
                                    $first_letter = substr($victim['full_name'] ?? 'V', 0, 1);
                                ?>
                                <div class="victim-item">
                                    <div class="victim-avatar"><?php echo $first_letter; ?></div>
                                    <div class="victim-details">
                                        <div class="victim-name"><?php echo htmlspecialchars($victim['full_name'] ?? 'Unknown'); ?></div>
                                        <div class="victim-info">
                                            <?php echo $victim['family_members']; ?> members
                                            <?php if ($victim['priority'] ?? 'Medium' == 'High'): ?>
                                            <span style="color: #e74c3c; margin-left: 10px;">High Priority</span>
                                            <?php endif; ?>
                                        </div>
                                        <?php if ($victim['has_baby'] || $victim['has_elderly'] || $victim['has_disabled']): ?>
                                        <div class="victim-needs">
                                            <?php if ($victim['has_baby']): ?>
                                            <span class="need-badge need-baby" title="Has Baby"></span>
                                            <?php endif; ?>
                                            <?php if ($victim['has_elderly']): ?>
                                            <span class="need-badge need-elderly" title="Has Elderly"></span>
                                            <?php endif; ?>
                                            <?php if ($victim['has_disabled']): ?>
                                            <span class="need-badge need-disabled" title="Has Disabled"></span>
                                            <?php endif; ?>
                                        </div>
                                        <?php endif; ?>
                                        <?php if (!empty($victim['special_requests'])): ?>
                                        <div style="margin-top: 5px; font-size: 0.8rem; color: #e67e22;">
                                            <i class="fas fa-star"></i> <?php echo substr($victim['special_requests'], 0, 30); ?>...
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Shelter Distribution Plan -->
                <div class="shelter-distribution-plan fade-in" style="margin-top: 30px;">
                    <h3><i class="fas fa-clipboard-list"></i> Shelter Distribution Plan</h3>
                    <div class="shelter-plan-details">
                        <div class="shelter-plan-item">
                            <h4><i class="fas fa-truck"></i> Delivery Schedule</h4>
                            <ul>
                                <li><strong>Date:</strong> <?php echo date('F j, Y', strtotime($distribution['date'] ?? 'now')); ?></li>
                                <li><strong>Time:</strong> 9:00 AM - 5:00 PM</li>
                                <li><strong>Delivery Method:</strong> Bulk Delivery to Shelters</li>
                                <li><strong>Coordinator:</strong> <?php echo $plan_details['coordinator'] ?? 'To be assigned'; ?></li>
                            </ul>
                        </div>
                        
                        <div class="shelter-plan-item">
                            <h4><i class="fas fa-boxes"></i> Resource Allocation Plan</h4>
                            <ul>
                                <li>Resources delivered in bulk to each shelter</li>
                                <li>Shelter staff distribute to individual families</li>
                                <li>Special needs packages prepared separately</li>
                                <li>Medical supplies distributed by medical volunteers</li>
                            </ul>
                        </div>
                        
                        <div class="shelter-plan-item">
                            <h4><i class="fas fa-users"></i> Volunteer Deployment</h4>
                            <ul>
                                <li>Volunteers assigned to specific shelters</li>
                                <li>Medical volunteers at shelters with high needs</li>
                                <li>Coordinators oversee each shelter distribution</li>
                                <li>Transport volunteers handle logistics</li>
                            </ul>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-home"></i>
                    <h3>No Shelters Assigned</h3>
                    <p>This distribution plan doesn't have any shelters assigned yet. Victims will be organized by their assigned shelters.</p>
                    <a href="edit_distribution.php?id=<?php echo $distribution_id; ?>" class="btn btn-primary" style="margin-top: 20px;">
                        <i class="fas fa-edit"></i> Assign to Shelters
                    </a>
                </div>
                <?php endif; ?>
            </div>

            <!-- Basic Needs Section - Automated for All Families -->
            <div class="card fade-in">
                <div class="card-header">
                    <h2><i class="fas fa-boxes"></i> Basic Needs - Automated Allocation</h2>
                    <span style="background: var(--light-gray); padding: 6px 16px; border-radius: 20px; font-size: 0.9rem; font-weight: 600;">
                        <?php echo count($basic_needs); ?> items • All <?php echo $total_families; ?> families
                    </span>
                </div>
                
                <div style="margin-bottom: 20px; padding: 15px; background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%); border-radius: 10px;">
                    <h4 style="color: var(--shelter-blue); margin-bottom: 10px;"><i class="fas fa-info-circle"></i> Automated Basic Needs Allocation</h4>
                    <p style="color: var(--dark);">
                        <strong>Every family receives the same basic essentials.</strong> This ensures equitable distribution of fundamental necessities 
                        to all affected families regardless of individual circumstances.
                    </p>
                    <div style="margin-top: 10px; font-size: 0.9rem; color: var(--gray);">
                        <i class="fas fa-check-circle"></i> Allocation formula: <?php echo $total_families; ?> families × standard quantity per family
                    </div>
                </div>
                
                <div class="resource-grid">
                    <?php foreach ($basic_needs as $need_type => $need): ?>
                    <div class="resource-card basic-need-card">
                        <div class="resource-name">
                            <span><?php echo htmlspecialchars($need['name']); ?></span>
                            <div class="need-icon" style="background: <?php echo $need['color']; ?>;">
                                <i class="<?php echo $need['icon']; ?>"></i>
                            </div>
                        </div>
                        <div class="resource-meta">
                            <span class="resource-type">Basic Need</span>
                            <span class="resource-unit"><?php echo htmlspecialchars($need['unit']); ?></span>
                            <span class="family-need-badge">All <?php echo $total_families; ?> families</span>
                        </div>
                        
                        <div style="margin: 10px 0; font-size: 0.9rem; color: var(--gray);">
                            <?php echo htmlspecialchars($need['description']); ?>
                        </div>
                        
                        <div class="resource-stats">
                            <div>
                                <div class="resource-amount"><?php echo $need['quantity']; ?></div>
                                <div class="resource-unit">total for all families</div>
                            </div>
                            <div style="text-align: right;">
                                <div style="font-size: 1.2rem; font-weight: 600; color: var(--shelter-blue);">
                                    <?php 
                                    $per_family = $need['quantity'] / $total_families;
                                    echo number_format($per_family, 1); 
                                    ?>
                                </div>
                                <div style="font-size: 0.8rem; color: var(--gray);">per family</div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Special Needs Section -->
            <?php if ($total_special_needs > 0): ?>
            <div class="card fade-in">
                <div class="card-header">
                    <h2><i class="fas fa-star"></i> Special Needs - Based on API Analysis</h2>
                    <span style="background: var(--light-gray); padding: 6px 16px; border-radius: 20px; font-size: 0.9rem; font-weight: 600;">
                        <?php echo count(array_filter($special_needs, function($need) { return $need['quantity'] > 0; })); ?> types • <?php echo $total_special_needs; ?> families
                    </span>
                </div>
                
                <div style="margin-bottom: 20px; padding: 15px; background: linear-gradient(135deg, #f3e5f5 0%, #e1bee7 100%); border-radius: 10px;">
                    <h4 style="color: var(--shelter-purple); margin-bottom: 10px;"><i class="fas fa-info-circle"></i> Targeted Special Needs Allocation</h4>
                    <p style="color: var(--dark);">
                        <strong>Special needs kits are allocated only to families who need them.</strong> These kits contain specific items 
                        identified from the needs API for vulnerable groups (babies, elderly, disabled).
                    </p>
                    <div style="margin-top: 10px; font-size: 0.9rem; color: var(--gray);">
                        <i class="fas fa-database"></i> Source: Needs API analysis of <?php echo count($distribution_victims); ?> victim records
                    </div>
                </div>
                
                <div class="resource-grid">
                    <?php foreach ($special_needs as $need_type => $need): 
                        if ($need['quantity'] > 0):
                            $percentage = $total_families > 0 ? round(($need['quantity'] / $total_families) * 100) : 0;
                    ?>
                    <div class="resource-card special-need-card">
                        <div class="resource-name">
                            <span><?php echo htmlspecialchars($need['name']); ?></span>
                            <div class="need-icon" style="background: <?php echo $need['color']; ?>;">
                                <i class="<?php echo $need['icon']; ?>"></i>
                            </div>
                        </div>
                        <div class="resource-meta">
                            <span class="resource-type">Special Need</span>
                            <span class="resource-unit"><?php echo htmlspecialchars($need['unit']); ?></span>
                            <span class="family-need-badge"><?php echo $need['quantity']; ?> families</span>
                        </div>
                        
                        <div style="margin: 10px 0; font-size: 0.9rem; color: var(--gray);">
                            <?php echo htmlspecialchars($need['description']); ?>
                        </div>
                        
                        <?php if (!empty($need['items'])): ?>
                        <div class="item-list">
                            <strong>Includes:</strong>
                            <ul>
                                <?php foreach ($need['items'] as $item): ?>
                                <li><?php echo htmlspecialchars($item); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <?php endif; ?>
                        
                        <div class="resource-stats">
                            <div>
                                <div class="resource-amount"><?php echo $need['quantity']; ?></div>
                                <div class="resource-unit"><?php echo $percentage; ?>% of families</div>
                            </div>
                        </div>
                    </div>
                    <?php endif; endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Additional Resources Section -->
            <?php if (!empty($additional_resources)): ?>
            <div class="card fade-in">
                <div class="card-header">
                    <h2><i class="fas fa-plus-circle"></i> Additional Resources - Based on Common Needs</h2>
                    <span style="background: var(--light-gray); padding: 6px 16px; border-radius: 20px; font-size: 0.9rem; font-weight: 600;">
                        <?php echo count($additional_resources); ?> items • From needs analysis
                    </span>
                </div>
                
                <div style="margin-bottom: 20px; padding: 15px; background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%); border-radius: 10px;">
                    <h4 style="color: var(--warning); margin-bottom: 10px;"><i class="fas fa-chart-bar"></i> Data-Driven Resource Allocation</h4>
                    <p style="color: var(--dark);">
                        <strong>These resources are allocated based on actual needs reported in the API.</strong> 
                        Items are prioritized by how many families requested them, ensuring resources go where they're most needed.
                    </p>
                </div>
                
                <div class="resource-grid">
                    <?php foreach ($additional_resources as $resource): ?>
                    <div class="resource-card additional-resource-card">
                        <div class="resource-name">
                            <span><?php echo htmlspecialchars($resource['name']); ?></span>
                        </div>
                        <div class="resource-meta">
                            <span class="resource-type">Additional Need</span>
                            <span class="resource-unit"><?php echo htmlspecialchars($resource['unit']); ?></span>
                            <span class="family-need-badge"><?php echo $resource['families_needing']; ?> families</span>
                        </div>
                        
                        <div class="progress-container">
                            <div class="progress-label">
                                <span>Family Coverage</span>
                                <span><?php echo $resource['percentage']; ?>%</span>
                            </div>
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: <?php echo $resource['percentage']; ?>%;"></div>
                            </div>
                        </div>
                        
                        <div class="resource-stats">
                            <div>
                                <div class="resource-amount"><?php echo $resource['quantity']; ?></div>
                                <div class="resource-unit">total allocation</div>
                            </div>
                            <div style="text-align: right;">
                                <div style="font-size: 1rem; font-weight: 600; color: var(--warning);">
                                    <?php 
                                    $per_family = $resource['quantity'] / max(1, $resource['families_needing']);
                                    echo number_format($per_family, 1); 
                                    ?>
                                </div>
                                <div style="font-size: 0.8rem; color: var(--gray);">per needing family</div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Special Requests Section -->
            <?php if (!empty($special_requests_summary)): ?>
            <div class="card fade-in">
                <div class="card-header">
                    <h2><i class="fas fa-comments"></i> Special Requests from Families</h2>
                    <span style="background: var(--light-gray); padding: 6px 16px; border-radius: 20px; font-size: 0.9rem; font-weight: 600;">
                        <?php echo count($special_requests_summary); ?> types of requests
                    </span>
                </div>
                
                <div style="margin-bottom: 20px; padding: 15px; background: linear-gradient(135deg, #d1ecf1 0%, #a2d9f3 100%); border-radius: 10px;">
                    <h4 style="color: #0c5460; margin-bottom: 10px;"><i class="fas fa-bullhorn"></i> Family-Specific Requests</h4>
                    <p style="color: var(--dark);">
                        <strong>These are specific requests made by individual families.</strong> 
                        They represent unique needs that go beyond standard allocations and may require special attention or customized solutions.
                    </p>
                </div>
                
                <div class="special-requests-grid">
                    <?php foreach ($special_requests_summary as $request): ?>
                    <div class="request-card">
                        <div style="font-weight: 600; color: var(--secondary);">
                            <i class="fas fa-star"></i> Request Details
                        </div>
                        <div class="request-content">
                            "<?php echo htmlspecialchars($request['request']); ?>"
                        </div>
                        <div class="request-stats">
                            <span><i class="fas fa-users"></i> <?php echo $request['count']; ?> families</span>
                            <span><i class="fas fa-chart-pie"></i> <?php echo $request['percentage']; ?>% of total</span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Volunteers Section -->
            <?php if (!empty($assigned_volunteers)): ?>
            <div class="card fade-in">
                <div class="card-header">
                    <h2><i class="fas fa-hands-helping"></i> Assigned Volunteers</h2>
                    <span style="background: var(--light-gray); padding: 6px 16px; border-radius: 20px; font-size: 0.9rem; font-weight: 600;">
                        <?php echo count($assigned_volunteers); ?> volunteers assigned
                    </span>
                </div>
                
                <div class="volunteer-grid">
                    <?php foreach ($assigned_volunteers as $volunteer): 
                        $first_letter = substr($volunteer['volunteer_name'] ?? 'V', 0, 1);
                    ?>
                    <div class="volunteer-card">
                        <div class="volunteer-header">
                            <div class="volunteer-avatar"><?php echo $first_letter; ?></div>
                            <div>
                                <div class="volunteer-name"><?php echo htmlspecialchars($volunteer['volunteer_name']); ?></div>
                                <span class="volunteer-role"><?php echo htmlspecialchars($volunteer['volunteer_role']); ?></span>
                            </div>
                        </div>
                        
                        <div class="volunteer-details">
                            <?php if (!empty($volunteer['phone'])): ?>
                            <p><i class="fas fa-phone"></i> <?php echo htmlspecialchars($volunteer['phone']); ?></p>
                            <?php endif; ?>
                            
                            <?php if (!empty($volunteer['email'])): ?>
                            <p><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($volunteer['email']); ?></p>
                            <?php endif; ?>
                            
                            <?php if (!empty($volunteer['ngo']) && $volunteer['ngo'] !== 'Various'): ?>
                            <p><i class="fas fa-hands-helping"></i> <?php echo htmlspecialchars($volunteer['ngo']); ?></p>
                            <?php endif; ?>
                            
                            <?php if (!empty($volunteer['assigned_timestamp'])): ?>
                            <p style="font-size: 0.8rem; color: var(--gray); margin-top: 10px; padding-top: 10px; border-top: 1px solid var(--light-gray);">
                                <i class="fas fa-calendar-alt"></i> Assigned on <?php echo date('M j, Y', strtotime($volunteer['assigned_timestamp'])); ?>
                            </p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php else: ?>
            <div class="card fade-in">
                <div class="card-header">
                    <h2><i class="fas fa-hands-helping"></i> Assigned Volunteers</h2>
                </div>
                <div class="empty-state">
                    <i class="fas fa-users"></i>
                    <h3>No Volunteers Assigned</h3>
                    <p>This distribution doesn't have any volunteers assigned yet.</p>
                    <?php if (($distribution['status'] ?? '') == 'Planning' || ($distribution['status'] ?? '') == 'Scheduled'): ?>
                    <a href="assign_volunteer.php?distribution_id=<?php echo $distribution_id; ?>" class="btn btn-success" style="margin-top: 20px;">
                        <i class="fas fa-users"></i> Assign Volunteers
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Footer Actions -->
            <div class="card fade-in" style="text-align: center; margin-top: 30px;">
                <h3 style="margin-bottom: 20px; color: var(--secondary);">Shelter Distribution Actions</h3>
                <div style="display: flex; justify-content: center; gap: 15px; flex-wrap: wrap;">
                    <a href="edit_distribution.php?id=<?php echo $distribution_id; ?>" class="btn btn-outline">
                        <i class="fas fa-edit"></i> Edit Distribution
                    </a>
                    <a href="distribution_main.php" class="btn btn-secondary">
                        <i class="fas fa-chart-bar"></i> View All Distributions
                    </a>
                    <a href="create_distribution_plan.php" class="btn btn-primary">
                        <i class="fas fa-plus-circle"></i> Create New Shelter Plan
                    </a>
                </div>
                
                <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid var(--light-gray);">
                    <p style="color: var(--gray); font-size: 0.9rem;">
                        <i class="fas fa-info-circle"></i> Shelter Distribution ID: DIST<?php echo str_pad($distribution_id, 3, '0', STR_PAD_LEFT); ?> 
                        • Created: <?php echo date('F j, Y', strtotime($distribution['date'] ?? 'now')); ?>
                        <?php if (!empty($distribution['last_updated'])): ?>
                        • Last Updated: <?php echo date('F j, Y', strtotime($distribution['last_updated'])); ?>
                        <?php endif; ?>
                    </p>
                    <p style="color: var(--gray); font-size: 0.8rem; margin-top: 10px;">
                        <i class="fas fa-database"></i> Data Sources: Needs API (<?php echo $needsApiResult['success'] ? 'Connected' : 'Disconnected'; ?>) • 
                        Automated allocation for <?php echo $total_families; ?> families
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

        // Print functionality
        function printPage() {
            const originalContent = document.body.innerHTML;
            const printContent = document.querySelector('.container').innerHTML;
            
            document.body.innerHTML = `
                <html>
                    <head>
                        <title>Shelter Distribution Report - DIST<?php echo str_pad($distribution_id, 3, '0', STR_PAD_LEFT); ?></title>
                        <style>
                            body { font-family: Arial, sans-serif; padding: 20px; }
                            .page-header { background: #f0f0f0; padding: 20px; margin-bottom: 20px; }
                            .card { border: 1px solid #ddd; padding: 15px; margin-bottom: 20px; break-inside: avoid; }
                            .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin: 20px 0; }
                            .shelter-card { border: 1px solid #ddd; padding: 15px; margin-bottom: 15px; border-radius: 10px; }
                            .needs-summary { display: grid; grid-template-columns: repeat(6, 1fr); gap: 10px; margin: 20px 0; }
                            .resource-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px; margin: 20px 0; }
                            .special-requests-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px; margin: 20px 0; }
                            @media print {
                                .btn { display: none !important; }
                                .no-print { display: none !important; }
                                .card { break-inside: avoid; }
                                .resource-grid { break-inside: avoid; }
                            }
                            @page {
                                size: landscape;
                                margin: 1cm;
                            }
                        </style>
                    </head>
                    <body>
                        <div class="page-header">
                            <h1>Shelter Distribution Report - DIST<?php echo str_pad($distribution_id, 3, '0', STR_PAD_LEFT); ?></h1>
                            <p>Generated on: <?php echo date('F j, Y g:i A'); ?></p>
                            <p><strong>Shelter-Based Distribution Plan with Automated Allocation</strong></p>
                            <p>Total Families: <?php echo $total_families; ?> | Total Shelters: <?php echo $total_shelters; ?></p>
                        </div>
                        ${printContent}
                    </body>
                </html>
            `;
            
            window.print();
            document.body.innerHTML = originalContent;
            location.reload();
        }
        
        // Add smooth scrolling
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
        
        // Add card hover effects
        document.querySelectorAll('.card, .shelter-card, .resource-card, .volunteer-card').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transition = 'all 0.3s ease';
            });
        });
        
        // Shelter cards animation
        document.querySelectorAll('.shelter-card').forEach((card, index) => {
            card.style.animationDelay = `${index * 0.1}s`;
        });
        
        // Resource cards animation
        document.querySelectorAll('.resource-card').forEach((card, index) => {
            card.style.animationDelay = `${index * 0.05}s`;
        });
    </script>
</body>
</html>