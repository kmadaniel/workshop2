<?php
// ================= ERROR REPORTING =================
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ================= DATABASE CONNECTION =================
include "db.php";
$message = "";

// Test connection
if (!$conn) {
    die("❌ Database connection failed! Check your db.php file.");
}

// Fetch active disasters
try {
    $disasters = $conn->query("
        SELECT disaster_id, disaster_name, district
        FROM disaster
        WHERE status IN ('Active', 'Under Control')
        ORDER BY created_at DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    die("❌ Error fetching disasters: " . $e->getMessage());
}

// District options for dropdown
$districts = [
    'Melaka Tengah',
    'Alor Gajah', 
    'Jasin',
    'Bandaraya Melaka'
];

// ================= SHELTER DATA =================
$shelters = [
    'Melaka Tengah' => [
        [
            'name' => 'Melaka Tengah Emergency Shelter 1',
            'address' => '123 Jalan Merdeka, 75000 Melaka',
            'phone' => '012-3456789',
            'email' => 'mt1@shelter.gov.my',
            'capacity' => '200 people',
            'facilities' => 'Beds, Showers, Kitchen, Medical Bay',
            'status' => 'Available'
        ],
        [
            'name' => 'SMK Tun Tuah Temporary Shelter',
            'address' => '45 Jalan Hang Tuah, 75300 Melaka',
            'phone' => '013-9876543',
            'email' => 'shelter2@melaka.gov.my',
            'capacity' => '150 people',
            'facilities' => 'Classrooms, Toilets, Food Service',
            'status' => 'Available'
        ],
        [
            'name' => 'Dewan Seri Negeri Melaka',
            'address' => 'Kompleks Seri Negeri, Ayer Keroh',
            'phone' => '06-2319999',
            'email' => 'dewan@sriegeri.melaka.gov.my',
            'capacity' => '500 people',
            'facilities' => 'Large Hall, Kitchen, Medical Post',
            'status' => 'Full'
        ]
    ],
    'Alor Gajah' => [
        [
            'name' => 'Alor Gajah District Shelter',
            'address' => '12 Jalan Melati, 78000 Alor Gajah',
            'phone' => '013-1112223',
            'email' => 'ag1@shelter.gov.my',
            'capacity' => '180 people',
            'facilities' => 'Sleeping Mats, Blankets, Basic Kitchen',
            'status' => 'Available'
        ],
        [
            'name' => 'SK Alor Gajah Relief Center',
            'address' => '89 Jalan Pegaga, 78010 Alor Gajah',
            'phone' => '019-8765432',
            'email' => 'relief@ag.edu.my',
            'capacity' => '120 people',
            'facilities' => 'School Hall, Basic Amenities',
            'status' => 'Available'
        ],
        [
            'name' => 'Masjid Al-Amin Emergency Shelter',
            'address' => 'Kampung Padang Sebang, 78000 Alor Gajah',
            'phone' => '011-22334455',
            'email' => 'alamin.masjid@gmail.com',
            'capacity' => '100 people',
            'facilities' => 'Prayer Hall, Basic Kitchen',
            'status' => 'Limited Space'
        ]
    ],
    'Jasin' => [
        [
            'name' => 'Jasin Community Shelter',
            'address' => '56 Jalan Kenanga, 77000 Jasin',
            'phone' => '014-5556667',
            'email' => 'js1@shelter.gov.my',
            'capacity' => '160 people',
            'facilities' => 'Community Hall, Showers, Kitchen',
            'status' => 'Available'
        ],
        [
            'name' => 'Sekolah Kebangsaan Jasin 2',
            'address' => 'Jalan Durian Daun, 77000 Jasin',
            'phone' => '06-5291234',
            'email' => 'skjasin2@moe.edu.my',
            'capacity' => '220 people',
            'facilities' => 'Classrooms, Canteen, Toilets',
            'status' => 'Available'
        ],
        [
            'name' => 'Balai Raya Merlimau',
            'address' => 'Kampung Merlimau, 77300 Jasin',
            'phone' => '017-8899001',
            'email' => 'merlimau.balai@gmail.com',
            'capacity' => '90 people',
            'facilities' => 'Village Hall, Basic Facilities',
            'status' => 'Available'
        ]
    ],
    'Bandaraya Melaka' => [
        [
            'name' => 'Melaka International Trade Center',
            'address' => 'MITC, Ayer Keroh, 75450 Melaka',
            'phone' => '06-2329000',
            'email' => 'mitc@shelter.melaka.gov.my',
            'capacity' => '1000 people',
            'facilities' => 'Large Hall, Medical Center, Kitchen',
            'status' => 'Available'
        ],
        [
            'name' => 'Stadium Hang Jebat',
            'address' => 'Krubong, 75250 Melaka',
            'phone' => '06-3175000',
            'email' => 'stadium@melaka.gov.my',
            'capacity' => '800 people',
            'facilities' => 'Stadium Hall, Showers, Food Court',
            'status' => 'Available'
        ]
    ]
];

// ================= AUTOMATIC API DISCOVERY =================
function discoverApis($base_url = 'http://10.147.17.224:8000/') {
    $api_endpoints = [];
    
    // Common API endpoint patterns to try
    $possible_endpoints = [
        'baby_api.php',
        'elderly_api.php',
        'disabled_api.php',
        'basic_needs_api.php',
        'medical_api.php',
        'food_api.php',
        'clothing_api.php',
        'shelter_api.php',
        'resource_api.php',
        'items_api.php'
    ];
    
    foreach ($possible_endpoints as $endpoint) {
        $url = $base_url . $endpoint;
        if (testApiEndpoint($url)) {
            $type = determineApiType($endpoint);
            $api_endpoints[$type] = $url;
        }
    }
    
    return $api_endpoints;
}

function testApiEndpoint($url) {
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 5,
            'ignore_errors' => true
        ]
    ]);
    
    try {
        $response = @file_get_contents($url, false, $context);
        if ($response === FALSE) {
            return false;
        }
        
        $data = json_decode($response, true);
        return json_last_error() === JSON_ERROR_NONE;
        
    } catch (Exception $e) {
        return false;
    }
}

function determineApiType($endpoint) {
    $endpoint = strtolower($endpoint);
    
    if (strpos($endpoint, 'baby') !== false) return 'baby';
    if (strpos($endpoint, 'elderly') !== false) return 'elderly';
    if (strpos($endpoint, 'disabled') !== false) return 'disabled';
    if (strpos($endpoint, 'basic') !== false) return 'basic';
    if (strpos($endpoint, 'medical') !== false) return 'medical';
    if (strpos($endpoint, 'food') !== false) return 'food';
    if (strpos($endpoint, 'clothing') !== false) return 'clothing';
    if (strpos($endpoint, 'shelter') !== false) return 'shelter';
    if (strpos($endpoint, 'resource') !== false) return 'resources';
    if (strpos($endpoint, 'items') !== false) return 'items';
    
    return 'other';
}

function fetchResources($url) {
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 10,
            'ignore_errors' => true
        ]
    ]);
    
    try {
        $response = @file_get_contents($url, false, $context);
        
        if ($response === FALSE) {
            return ["error" => "API Connection Failed: Unable to reach server"];
        }
        
        $data = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return ["error" => "Invalid JSON response: " . json_last_error_msg()];
        }
        
        return $data;
        
    } catch (Exception $e) {
        return ["error" => "API Error: " . $e->getMessage()];
    }
}

// Discover all available APIs
$api_endpoints = discoverApis('http://10.147.17.224:8000/');

// Track all discovered resource types
$all_resource_types = [];
$resources = [];
$api_errors = [];

// Fetch from discovered APIs
foreach ($api_endpoints as $type => $url) {
    $all_resource_types[] = $type;
    
    $api_data = fetchResources($url);
    
    if (isset($api_data['error'])) {
        $api_errors[$type] = $api_data['error'];
        error_log("Failed to fetch $type resources: " . $api_data['error']);
        // Use sample data if API fails
        $resources[$type] = getSampleResources($type);
    } else {
        $processed_data = processApiData($api_data, $type);
        $resources[$type] = $processed_data['items'];
    }
}

// If no APIs were discovered, use default ones with sample data
if (empty($api_endpoints)) {
    $api_endpoints = [
        'baby' => 'http://10.147.17.224:8000/baby_api.php',
        'elderly' => 'http://10.147.17.224:8000/elderly_api.php',
        'disabled' => 'http://10.147.17.224:8000/disabled_api.php',
        'basic' => 'http://10.147.17.224:8000/basic_needs_api.php',
        'medical' => 'http://10.147.17.224:8000/medical_api.php'
    ];
    
    foreach ($api_endpoints as $type => $url) {
        $all_resource_types[] = $type;
        $resources[$type] = getSampleResources($type);
    }
}

function getSampleResources($type) {
    $sample_data = [
        'baby' => [
            'Baby Diapers',
            'Baby Formula',
            'Baby Wipes',
            'Baby Clothes',
            'Baby Bottles'
        ],
        'elderly' => [
            'Adult Diapers',
            'Walking Stick',
            'Walker',
            'Blood Pressure Monitor',
            'Glucose Meter'
        ],
        'disabled' => [
            'Wheelchair',
            'Crutches',
            'Commode Chair',
            'Shower Chair',
            'Grab Bars'
        ],
        'basic' => [
            'First Aid Kits',
            'Bandages & Gauze',
            'Antiseptic Solution',
            'Pain Relievers',
            'Prescription Medications'
        ],
        'medical' => [
            'Thermometers',
            'Blood Pressure Monitors',
            'Medical Gloves',
            'Face Masks',
            'Emergency Blankets'
        ],
        'food' => [
            'Canned Food',
            'Bottled Water',
            'Energy Bars',
            'Rice Packets',
            'Ready-to-Eat Meals'
        ],
        'clothing' => [
            'Blankets',
            'Winter Jackets',
            'Socks',
            'Raincoats',
            'Sleeping Bags'
        ],
        'shelter' => [
            'Tents',
            'Sleeping Mats',
            'Mosquito Nets',
            'Portable Toilets',
            'Solar Lights'
        ],
        'resources' => [
            'General Supplies',
            'Emergency Kits',
            'Hygiene Products',
            'Cleaning Supplies',
            'Tools'
        ],
        'items' => [
            'Miscellaneous Items',
            'Donated Goods',
            'Relief Packages',
            'Community Aid',
            'Volunteer Supplies'
        ],
        'other' => [
            'General Resources',
            'Emergency Assistance',
            'Support Services',
            'Aid Packages',
            'Relief Materials'
        ]
    ];
    
    return $sample_data[$type] ?? ['Resource Item 1', 'Resource Item 2', 'Resource Item 3'];
}

function processApiData($api_data, $type) {
    $items = [];
    
    if (isset($api_data['status']) && $api_data['status'] === 'success' && isset($api_data['data'])) {
        $items_data = $api_data['data'];
        
        foreach ($items_data as $item) {
            if (is_array($item)) {
                $items[] = $item['name'] ?? $item['item_name'] ?? $item['resource_name'] ?? 'Resource Item';
            } else {
                $items[] = $item;
            }
        }
        
    } elseif (is_array($api_data) && count($api_data) > 0) {
        // Assume direct array of items
        foreach ($api_data as $item) {
            if (is_array($item)) {
                $items[] = $item['name'] ?? $item['item_name'] ?? $item['resource_name'] ?? 'Resource Item';
            } else {
                $items[] = $item;
            }
        }
    } else {
        $items = ['No items available from API'];
    }
    
    return ['items' => $items];
}

// ================= HANDLE FORM SUBMISSION =================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $conn->beginTransaction();

        // Force boolean values for checkboxes
        $has_baby = !empty($_POST['has_baby']);
        $has_elderly = !empty($_POST['has_elderly']);
        $has_disabled = !empty($_POST['has_disabled']);

        // Generate a reference number
        $reference_number = 'VICT-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));

        // ================= INSERT INTO VICTIM TABLE =================
        $stmt = $conn->prepare("
            INSERT INTO victim 
            (full_name, ic_number, email, phone, email_verified, verification_token, address,
             postal_code, city, district, country, family_members, has_baby, has_elderly,
             has_disabled, created_at, disaster_id, special_request, selected_shelter)
            VALUES 
            (:full_name, :ic, :email, :phone, false, :token, :address,
             :postal, :city, :district, 'Malaysia', :family, :baby, :elderly,
             :disabled, NOW(), :disaster, :special_request, :selected_shelter)
            RETURNING victim_id
        ");

        $stmt->bindParam(':full_name', $_POST['full_name']);
        $stmt->bindParam(':ic', $_POST['ic_number']);
        $stmt->bindParam(':email', $_POST['email']);
        $stmt->bindParam(':phone', $_POST['phone']);
        $stmt->bindParam(':token', $reference_number);
        $stmt->bindParam(':address', $_POST['address']);
        $stmt->bindParam(':postal', $_POST['postal_code']);
        $city = $_POST['city'] ?: 'Melaka';
        $stmt->bindParam(':city', $city);
        $stmt->bindParam(':district', $_POST['district']);
        $family_members = (int)$_POST['family_members'];
        $stmt->bindParam(':family', $family_members, PDO::PARAM_INT);
        $stmt->bindParam(':baby', $has_baby, PDO::PARAM_BOOL);
        $stmt->bindParam(':elderly', $has_elderly, PDO::PARAM_BOOL);
        $stmt->bindParam(':disabled', $has_disabled, PDO::PARAM_BOOL);
        $disaster_id = (int)$_POST['disaster_id'];
        $stmt->bindParam(':disaster', $disaster_id, PDO::PARAM_INT);
        
        // Check if special needs can be requested
        $has_special_person = $has_baby || $has_elderly || $has_disabled;
        $special_request = '';
        
        // Collect selected resources
        $selected_resources = [];
        if ($has_special_person && isset($_POST['selected_resources'])) {
            $selected_resources = json_decode($_POST['selected_resources'], true) ?? [];
            $special_request = implode("\n", $selected_resources);
        }
        $stmt->bindParam(':special_request', $special_request);
        
        // Get selected shelter
        $selected_shelter = $_POST['selected_shelter'] ?? '';
        $stmt->bindParam(':selected_shelter', $selected_shelter);

        $stmt->execute();
        
        // Get the victim_id using RETURNING clause
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $victim_id = $result['victim_id'] ?? $conn->lastInsertId();

        if (!$victim_id) {
            throw new Exception("Failed to get victim ID after insertion");
        }

        // ================= INSERT INTO NEEDS TABLE =================
        $needs_inserted = false;
        $needs_error = "";
        
        // Insert into needs table for ALL victims (with or without special needs)
        try {
            // Process selected resources
            $special_items = $selected_resources;
            
            // Calculate quantities
            $normal_qty = $family_members; // Each family member gets 1 basic need
            $special_qty = count($special_items); // Number of special items requested
            $total_qty = $normal_qty + $special_qty;
            
            // Set priority based on special needs
            $priority = $has_special_person ? 'High' : 'Medium';
            
            // Prepare PostgreSQL array format
            $special_requests_param = null;
            if (!empty($special_items)) {
                // Format array for PostgreSQL: {"item1","item2","item3"}
                $escaped_items = array_map(function($item) {
                    return '"' . str_replace('"', '\"', $item) . '"';
                }, $special_items);
                $special_requests_param = '{' . implode(',', $escaped_items) . '}';
            } else {
                $special_requests_param = '{}'; // Empty array
            }
            
            // Insert into needs table with shelter information
            $needs_stmt = $conn->prepare("
                INSERT INTO needs 
                (victim_id, disaster_id, priority, status, 
                 temp_resource_name, quantity_needed, normal_needs_quantity, 
                 special_needs_quantity, special_needs_requests, created_at, selected_shelter)
                VALUES 
                (:victim_id, :disaster_id, :priority, 'Pending',
                 :temp_resource_name, :quantity_needed, :normal_qty,
                 :special_qty, :special_requests, NOW(), :selected_shelter)
            ");
            
            $temp_resource_name = !empty($special_request) ? 
                substr($special_request, 0, 100) : 'Basic Needs';
            
            $needs_stmt->bindValue(':victim_id', $victim_id, PDO::PARAM_INT);
            $needs_stmt->bindValue(':disaster_id', $disaster_id, PDO::PARAM_INT);
            $needs_stmt->bindValue(':priority', $priority);
            $needs_stmt->bindValue(':temp_resource_name', $temp_resource_name);
            $needs_stmt->bindValue(':quantity_needed', $total_qty, PDO::PARAM_INT);
            $needs_stmt->bindValue(':normal_qty', $normal_qty, PDO::PARAM_INT);
            $needs_stmt->bindValue(':special_qty', $special_qty, PDO::PARAM_INT);
            $needs_stmt->bindValue(':special_requests', $special_requests_param);
            $needs_stmt->bindValue(':selected_shelter', $selected_shelter);
            
            $needs_stmt->execute();
            $needs_inserted = true;
            
        } catch (Exception $e) {
            // Don't rollback if needs insert fails
            $needs_error = $e->getMessage();
            error_log("Needs table insert failed: " . $needs_error);
        }

        $conn->commit();

        // ================= GET DISASTER NAME =================
        $disaster_name = "Unknown Disaster";
        foreach ($disasters as $d) {
            if ($d['disaster_id'] == $_POST['disaster_id']) {
                $disaster_name = $d['disaster_name'];
                break;
            }
        }
        
        // ================= GET SHELTER INFO =================
$shelter_info = "Not selected";
$shelter_details = "";
if (!empty($selected_shelter)) {
    $shelter_info = $selected_shelter;
    // Find shelter details
    $found_shelter = false;
    foreach ($shelters as $district => $district_shelters) {
        foreach ($district_shelters as $shelter) {
            if ($shelter['name'] === $selected_shelter) {
                $shelter_details = "
                <div class='shelter-details-box'>
                    <h5><i class='fas fa-info-circle'></i> Collection Point Details</h5>
                    <div class='shelter-details'>
                        <p><strong>📍 Address:</strong> {$shelter['address']}</p>
                        <p><strong>📞 Phone:</strong> {$shelter['phone']}</p>
                        <p><strong>📧 Email:</strong> {$shelter['email']}</p>
                        <p><strong>👥 Capacity:</strong> {$shelter['capacity']}</p>
                        <p><strong>🏪 Facilities:</strong> {$shelter['facilities']}</p>
                        <p><strong>📊 Status:</strong> <span class='status-badge'>{$shelter['status']}</span></p>
                    </div>
                </div>";
                $found_shelter = true;
                break 2;
            }
        }
    }
    
    // If shelter not found in our array (shouldn't happen, but just in case)
    if (!$found_shelter) {
        $shelter_details = "
        <div class='shelter-details-box'>
            <h5><i class='fas fa-info-circle'></i> Collection Point</h5>
            <div class='shelter-details'>
                <p><strong>Selected Collection Point:</strong> " . htmlspecialchars($selected_shelter) . "</p>
                <p><em>Please bring your reference number and IC to collect your supplies.</em></p>
            </div>
        </div>";
    }
}
        
        
        // ================= CREATE WHATSAPP LINK =================
        $clean_phone = preg_replace('/[^0-9]/', '', $_POST['phone']);
        if (substr($clean_phone, 0, 1) === '0') {
            $clean_phone = substr($clean_phone, 1);
        }
        
        // Build WhatsApp message with shelter info
$whatsapp_shelter_info = "";
if (!empty($selected_shelter)) {
    if ($selected_shelter === "No shelter needed - I will collect from another location") {
        $whatsapp_shelter_info = "
*Shelter Selection:* No shelter needed
*Note:* You will be contacted for alternative collection arrangements";
    } else {
        $whatsapp_shelter_info = "
*Selected Shelter:* $selected_shelter
*Location:* $disaster_name area

*Shelter Collection Instructions:*
1. Bring your Reference Number and IC
2. Go to the shelter in the disaster area
3. Shelter operating hours: 8:00 AM - 8:00 PM
4. Contact shelter if you cannot make it";
    }
}
        
        $whatsapp_message = "📋 *Melaka Disaster Assistance - Registration Confirmation*

✅ Registration Successful!

*Reference Number:* $reference_number
*Victim ID:* $victim_id
*Name:* " . $_POST['full_name'] . "
*Disaster:* $disaster_name
*Date:* " . date('d/m/Y H:i') . "

" . ($has_special_person ? "*Special Persons in Household:* " . 
    ($has_baby ? "👶 Baby " : "") . 
    ($has_elderly ? "👵 Elderly " : "") . 
    ($has_disabled ? "♿ Disabled " : "") . "\n" : "") . "

" . (!empty($special_request) ? 
"*Special Needs Request:*
" . $special_request . "\n" : 
"*Special Needs Request:* No special needs requested\n") . "

" . $whatsapp_shelter_info . "

*Important Information:*
1. Keep this Reference Number for all future communications
2. You will receive updates about your special needs request
3. For emergencies, contact disaster hotline: 1-300-88-2010
4. Save this message for future reference

Thank you for registering with Melaka Disaster Assistance. Stay safe!";
        
        $whatsapp_link = "https://wa.me/6" . $clean_phone . "?text=" . urlencode($whatsapp_message);

        // ================= DISPLAY SUCCESS MESSAGE =================
        $db_status = "✅ Data saved to: <strong>victim table</strong>";
        if ($needs_inserted) {
            $db_status .= " and <strong>needs table</strong>";
        } else {
            $db_status .= " (needs table: error - " . htmlspecialchars($needs_error) . ")";
        }
        
        $message = "
        <div class='success-message'>
            <div class='success-header'>
                <i class='fas fa-check-circle'></i>
                <h3>Registration Successful!</h3>
            </div>
            
            <div class='alert-box'>
                <i class='fas fa-database'></i>
                <div>
                    <p>$db_status</p>
                    <p>Reference Number: <strong class='ref-highlight'>$reference_number</strong></p>
                </div>
            </div>
            
            <div class='details-container'>
                <div class='details-card'>
                    <h4><i class='fas fa-id-card'></i> Registration Details</h4>
                    <div class='details-grid'>
                        <div class='detail-item'>
                            <span class='detail-label'>Reference No:</span>
                            <span class='detail-value highlight'>$reference_number</span>
                        </div>
                        <div class='detail-item'>
                            <span class='detail-label'>Victim ID:</span>
                            <span class='detail-value'>$victim_id</span>
                        </div>
                        <div class='detail-item'>
                            <span class='detail-label'>Full Name:</span>
                            <span class='detail-value'>" . htmlspecialchars($_POST['full_name']) . "</span>
                        </div>
                        <div class='detail-item'>
                            <span class='detail-label'>IC Number:</span>
                            <span class='detail-value'>" . htmlspecialchars($_POST['ic_number']) . "</span>
                        </div>
                        <div class='detail-item'>
                            <span class='detail-label'>Phone:</span>
                            <span class='detail-value'>" . htmlspecialchars($_POST['phone']) . "</span>
                        </div>
                        <div class='detail-item'>
                            <span class='detail-label'>Email:</span>
                            <span class='detail-value'>" . htmlspecialchars($_POST['email']) . "</span>
                        </div>
                        <div class='detail-item'>
                            <span class='detail-label'>Disaster:</span>
                            <span class='detail-value'>" . htmlspecialchars($disaster_name) . "</span>
                        </div>
                        <div class='detail-item'>
                            <span class='detail-label'>Date:</span>
                            <span class='detail-value'>" . date('d/m/Y H:i') . "</span>
                        </div>
                        " . (!empty($selected_shelter) ? "
<div class='detail-item'>
    <span class='detail-label'>Collection Point:</span>
    <span class='detail-value highlight'>" . htmlspecialchars($selected_shelter) . "</span>
</div>
" : "") . "
                        " . ($selected_shelter === "No shelter needed - I will collect from another location" ? "
                        <div class='detail-item'>
                            <span class='detail-label'>Shelter:</span>
                            <span class='detail-value'>No shelter selected - alternative arrangements</span>
                        </div>
                        " : "") . "
                    </div>
                </div>
                
                " . (!empty($shelter_details) ? $shelter_details : "") . "
                
                " . (!empty($special_request) && $has_special_person ? "
                <div class='special-needs-card'>
                    <h4><i class='fas fa-hand-holding-heart'></i> Special Needs Request</h4>
                    <div class='needs-box'>
                        <p><strong>Your Selected Items:</strong></p>
                        <div class='needs-text'>" . nl2br(htmlspecialchars($special_request)) . "</div>
                        <p><strong>Status:</strong> <span class='status-badge'>Pending Review</span></p>
                        <p><strong>Database Status:</strong> " . ($needs_inserted ? "✅ Saved to needs table" : "⚠️ Not saved to needs table") . "</p>
                    </div>
                </div>
                " : "") . "
                
                " . ($has_special_person && empty($special_request) ? "
                <div class='note' style='margin: 20px;'>
                    <i class='fas fa-info-circle'></i> 
                    <strong>Note:</strong> You have special persons in your household but did not select any special needs. If you need special assistance, please contact disaster management.
                </div>
                " : "") . "
                
                <div class='whatsapp-section'>
                    <div class='section-header'>
                        <i class='fab fa-whatsapp'></i>
                        <h4>Save to WhatsApp</h4>
                    </div>
                    <p>Click below to save your registration details to WhatsApp:</p>
                    
                    <a href='$whatsapp_link' target='_blank' class='whatsapp-button'>
                        <i class='fab fa-whatsapp'></i> Save to WhatsApp
                    </a>
                    
                    <div class='instructions'>
                        <p><strong>How to save:</strong></p>
                        <ol>
                            <li>Click the WhatsApp button above</li>
                            <li>WhatsApp will open with your details</li>
                            <li>Send the message to yourself or save it</li>
                            <li>Keep the message for future reference</li>
                        </ol>
                    </div>
                </div>
                
                <div class='action-buttons'>
                    <button onclick='window.print()' class='action-btn print-btn'>
                        <i class='fas fa-print'></i> Print This Page
                    </button>
                    <button onclick='copyReference()' class='action-btn copy-btn'>
                        <i class='fas fa-copy'></i> Copy Reference Number
                    </button>
                </div>
                
                <div class='emergency-info'>
                    <h4><i class='fas fa-phone-alt'></i> Emergency Contact</h4>
                    <div class='emergency-card'>
                        <i class='fas fa-phone-volume'></i>
                        <div>
                            <h5>Disaster Hotline</h5>
                            <p class='emergency-number'>1-300-88-2010</p>
                            <p>24/7 Emergency Assistance</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        ";

    } catch (Exception $e) {
        $conn->rollBack();
        $message = "<div class='error-message'><h3><i class='fas fa-exclamation-triangle'></i> Registration Failed</h3><p><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p><p>Please check your database connection and table structure.</p></div>";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Victim Registration - Melaka Disaster Assistance</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
/* ALL YOUR ORIGINAL CSS STAYS EXACTLY THE SAME - I'M NOT CHANGING IT! */
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}

body {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    min-height: 100vh;
}

/* NAVBAR - Same as dashboard */
.navbar {
    width: 100%;
    padding: 20px 60px;
    background: white;
    display: flex;
    justify-content: space-between;
    align-items: center;
    position: fixed;
    top: 0;
    left: 0;
    border-bottom: 2px solid #eee;
    z-index: 1000;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.nav-left a {
    margin: 0 20px;
    text-decoration: none;
    color: #333;
    font-size: 16px;
    font-weight: 600;
    transition: color 0.3s;
    padding: 8px 0;
    position: relative;
}

.nav-left a:hover {
    color: #007bff;
}

.nav-left a.active {
    color: #007bff;
}

.nav-left a.active::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 0;
    width: 100%;
    height: 3px;
    background: #007bff;
    border-radius: 2px;
}

.nav-right a {
    margin-left: 20px;
    padding: 10px 20px;
    text-decoration: none;
    border-radius: 8px;
    font-weight: bold;
    font-size: 14px;
    transition: all 0.3s;
}

.btn-login {
    color: #333;
    border: 2px solid #007bff;
}

.btn-login:hover {
    background: #007bff;
    color: white;
}

.btn-register {
    background: #007bff;
    color: white;
}

.btn-register:hover {
    background: #0056b3;
    transform: translateY(-2px);
}

/* PURPLE HERO SECTION - Same as dashboard */
.dashboard-hero {
    height: 50vh;
    background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
    color: white;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    text-align: center;
    padding-top: 80px;
    position: relative;
    overflow: hidden;
    margin-bottom: 40px;
}

.dashboard-hero::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: url('https://images.unsplash.com/photo-1582213782179-e0d53f98f2ca?ixlib=rb-4.0.3&auto=format&fit=crop&w=1920&q=80') center/cover;
    opacity: 0.15;
}

.dashboard-hero-content {
    position: relative;
    z-index: 2;
    max-width: 900px;
    padding: 0 20px;
}

.dashboard-hero h1 {
    font-size: 2.5rem;
    font-weight: bold;
    margin-bottom: 15px;
    text-shadow: 2px 2px 8px rgba(0,0,0,0.5);
}

.dashboard-hero p {
    font-size: 1.2rem;
    margin-bottom: 30px;
    opacity: 0.95;
    text-shadow: 1px 1px 4px rgba(0,0,0,0.5);
    max-width: 700px;
    line-height: 1.6;
    margin: 0 auto 30px;
}

/* Quick Actions */
.quick-actions {
    display: flex;
    gap: 20px;
    justify-content: center;
    flex-wrap: wrap;
    margin-top: 30px;
}

.action-btn {
    padding: 15px 35px;
    background: rgba(255, 255, 255, 0.2);
    color: white;
    font-size: 1.1rem;
    border-radius: 50px;
    text-decoration: none;
    font-weight: bold;
    transition: all 0.3s;
    box-shadow: 0 5px 15px rgba(0,0,0,0.2);
    border: 2px solid rgba(255, 255, 255, 0.3);
    display: flex;
    align-items: center;
    gap: 10px;
}

.action-btn:hover {
    background: rgba(255, 255, 255, 0.3);
    transform: translateY(-3px);
    box-shadow: 0 8px 20px rgba(0,0,0,0.3);
    color: white;
}

.action-btn.primary {
    background: #ff6b6b;
    border-color: #ff6b6b;
}

.action-btn.primary:hover {
    background: #ff5252;
}

/* MAIN CONTENT */
.main-content {
    max-width: 800px;
    margin: 0 auto 60px;
    padding: 0 20px;
}

/* FORM CARD */
.form-card {
    background: white;
    border-radius: 15px;
    padding: 30px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    margin-bottom: 30px;
}

.form-card h2 {
    color: #2c3e50;
    margin-bottom: 25px;
    padding-bottom: 15px;
    border-bottom: 2px solid #f0f0f0;
    display: flex;
    align-items: center;
    gap: 10px;
}

/* FORM STYLES */
.form-group {
    margin-bottom: 20px;
}

.form-label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: #333;
}

.form-control {
    width: 100%;
    padding: 12px 15px;
    border: 2px solid #dee2e6;
    border-radius: 8px;
    font-size: 14px;
    transition: border-color 0.3s;
}

.form-control:focus {
    outline: none;
    border-color: #007bff;
    box-shadow: 0 0 0 3px rgba(0,123,255,0.1);
}

textarea.form-control {
    min-height: 100px;
    resize: vertical;
}

select.form-control {
    appearance: none;
    background: white url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%23333' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14 2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E") no-repeat right 15px center;
    background-size: 16px;
}

/* CHECKBOX GROUP */
.checkbox-group {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 8px;
    margin-top: 10px;
}

.checkbox-item {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 10px;
}

.checkbox-item input[type="checkbox"] {
    width: auto;
}

/* INFO NOTES */
.note {
    background: #fff3cd;
    border-left: 5px solid #ffc107;
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 20px;
    font-size: 14px;
}

.note i {
    color: #ffc107;
    margin-right: 8px;
}

/* SECTION TITLES */
.section-title {
    font-size: 18px;
    font-weight: 600;
    color: #007bff;
    margin: 25px 0 15px;
    padding-left: 15px;
    border-left: 4px solid #007bff;
    display: flex;
    align-items: center;
    gap: 10px;
}

/* SUBMIT BUTTON */
.submit-btn {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border: none;
    padding: 15px;
    border-radius: 10px;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
    width: 100%;
    margin-top: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
}

.submit-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
}

/* SUCCESS MESSAGE STYLES */
.success-message {
    background: white;
    border-radius: 15px;
    overflow: hidden;
    border: 2px solid #4caf50;
}

.success-header {
    background: linear-gradient(135deg, #4caf50, #2e7d32);
    color: white;
    padding: 25px;
    text-align: center;
}

.success-header i {
    font-size: 48px;
    margin-bottom: 15px;
}

.success-header h3 {
    margin: 0;
    font-size: 24px;
}

.alert-box {
    background: #e8f5e9;
    border: 1px solid #4caf50;
    border-radius: 10px;
    padding: 15px;
    margin: 20px;
    display: flex;
    align-items: center;
    gap: 15px;
}

.alert-box i {
    color: #2e7d32;
    font-size: 24px;
}

.ref-highlight {
    background: #ffeb3b;
    padding: 5px 10px;
    border-radius: 5px;
    font-size: 18px;
    font-weight: bold;
    cursor: pointer;
}

.details-container {
    padding: 20px;
}

.details-card {
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 10px;
    padding: 20px;
    margin-bottom: 25px;
}

.details-card h4 {
    color: #495057;
    margin-top: 0;
    margin-bottom: 20px;
    padding-bottom: 10px;
    border-bottom: 2px solid #007bff;
    display: flex;
    align-items: center;
    gap: 10px;
}

.details-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 15px;
}

.detail-item {
    display: flex;
    flex-direction: column;
    padding: 10px;
    background: white;
    border-radius: 8px;
    border: 1px solid #eee;
}

.detail-label {
    font-size: 12px;
    color: #6c757d;
    text-transform: uppercase;
    margin-bottom: 5px;
}

.detail-value {
    font-size: 16px;
    color: #212529;
    font-weight: 500;
}

.highlight {
    color: #007bff;
    font-weight: bold;
    font-size: 18px;
}

/* SPECIAL NEEDS CARD */
.special-needs-card {
    background: #e8f4f8;
    border: 1px solid #17a2b8;
    border-radius: 10px;
    padding: 20px;
    margin-bottom: 25px;
}

.special-needs-card h4 {
    color: #0c5460;
    margin-top: 0;
    margin-bottom: 15px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.needs-box {
    background: white;
    padding: 15px;
    border-radius: 8px;
    border-left: 4px solid #17a2b8;
}

.needs-text {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 5px;
    margin: 10px 0;
    border: 1px solid #dee2e6;
}

.status-badge {
    background: #ff9800;
    color: white;
    padding: 3px 10px;
    border-radius: 20px;
    font-size: 14px;
}

/* WHATSAPP SECTION */
.whatsapp-section {
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 10px;
    padding: 20px;
    margin-bottom: 25px;
}

.section-header {
    display: flex;
    align-items: center;
    gap: 15px;
    margin-bottom: 20px;
}

.section-header i {
    font-size: 24px;
    color: #25D366;
}

.section-header h4 {
    margin: 0;
    color: #495057;
}

.whatsapp-button {
    display: block;
    background: #25D366;
    color: white;
    text-align: center;
    padding: 18px;
    border-radius: 10px;
    text-decoration: none;
    font-size: 18px;
    font-weight: bold;
    margin: 20px 0;
    transition: all 0.3s;
}

.whatsapp-button:hover {
    background: #128C7E;
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(37, 211, 102, 0.3);
}

.whatsapp-button i {
    margin-right: 10px;
    font-size: 24px;
}

.instructions {
    background: white;
    padding: 15px;
    border-radius: 8px;
    border-left: 4px solid #25D366;
}

.instructions ol {
    margin: 10px 0 0 20px;
}

.instructions li {
    margin-bottom: 8px;
}

/* ACTION BUTTONS */
.action-buttons {
    display: flex;
    gap: 15px;
    margin: 25px 0;
}

.action-btn {
    flex: 1;
    padding: 15px;
    font-size: 16px;
    border: none;
    border-radius: 10px;
    cursor: pointer;
    transition: all 0.3s;
}

.print-btn {
    background: #28a745;
    color: white;
}

.print-btn:hover {
    background: #218838;
    transform: translateY(-2px);
}

.copy-btn {
    background: #6c757d;
    color: white;
}

.copy-btn:hover {
    background: #5a6268;
    transform: translateY(-2px);
}

/* EMERGENCY INFO */
.emergency-info {
    background: #fff3cd;
    border: 1px solid #ffc107;
    border-radius: 10px;
    padding: 20px;
}

.emergency-info h4 {
    color: #856404;
    margin-bottom: 15px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.emergency-card {
    display: flex;
    align-items: center;
    gap: 20px;
    padding: 15px;
    background: white;
    border-radius: 8px;
    border-left: 4px solid #dc3545;
}

.emergency-card i {
    font-size: 36px;
    color: #dc3545;
}

.emergency-number {
    font-size: 24px;
    font-weight: bold;
    color: #dc3545;
    margin: 5px 0;
}

/* ERROR MESSAGE */
.error-message {
    background: #ffebee;
    border: 1px solid #f44336;
    border-radius: 10px;
    padding: 20px;
    margin-bottom: 20px;
}

.error-message h3 {
    color: #c62828;
    margin-bottom: 10px;
    display: flex;
    align-items: center;
    gap: 10px;
}

/* RESPONSIVE */
@media (max-width: 768px) {
    .navbar {
        padding: 15px 20px;
    }
    
    .nav-left a {
        margin: 0 10px;
        font-size: 14px;
    }
    
    .dashboard-hero {
        height: 40vh;
        margin-bottom: 30px;
    }
    
    .dashboard-hero h1 {
        font-size: 2rem;
    }
    
    .dashboard-hero p {
        font-size: 1rem;
    }
    
    .main-content {
        padding: 0 15px;
        margin: 0 auto 30px;
    }
    
    .form-card {
        padding: 20px;
    }
    
    .details-grid {
        grid-template-columns: 1fr;
    }
    
    .action-buttons {
        flex-direction: column;
    }
    
    .quick-actions {
        flex-direction: column;
        align-items: center;
    }
    
    .action-btn {
        width: 100%;
        max-width: 300px;
        justify-content: center;
    }
}

@media (max-width: 480px) {
    .navbar {
        flex-direction: column;
        padding: 15px;
    }
    
    .nav-left {
        margin-bottom: 15px;
        display: flex;
        flex-wrap: wrap;
        justify-content: center;
        gap: 10px;
    }
    
    .nav-left a {
        margin: 0 5px;
    }
    
    .dashboard-hero h1 {
        font-size: 1.8rem;
    }
}

/* Special needs section visibility */
.special-needs-hidden {
    display: none;
}

/* Resource selection styles */
.resource-options {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 20px;
    border: 1px solid #dee2e6;
}

.resource-options h5 {
    margin-bottom: 15px;
    color: #495057;
    display: flex;
    align-items: center;
    gap: 10px;
}

.resource-options h5 i {
    color: #007bff;
}

.resource-checkbox {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 10px;
    padding: 8px;
    background: white;
    border-radius: 6px;
    border: 1px solid #dee2e6;
    transition: all 0.3s;
}

.resource-checkbox:hover {
    border-color: #007bff;
    background: #f0f8ff;
}

.resource-checkbox input[type="checkbox"] {
    width: auto;
    margin: 0;
}

.resource-checkbox label {
    cursor: pointer;
    flex: 1;
    margin: 0;
    font-size: 14px;
    color: #333;
}

.resource-checkbox.selected {
    border-color: #28a745;
    background: #f0fff4;
}

.selection-info {
    background: #e7f3ff;
    border: 1px solid #b3d7ff;
    border-radius: 8px;
    padding: 10px;
    margin-top: 10px;
    font-size: 14px;
    color: #004085;
    display: flex;
    align-items: center;
    gap: 10px;
}

.selection-info i {
    color: #0056b3;
}

/* Shelter selection styles */
.shelter-section {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 20px;
    border: 1px solid #dee2e6;
}

.shelter-options {
    max-height: 300px;
    overflow-y: auto;
    padding: 10px;
    background: white;
    border-radius: 6px;
    border: 1px solid #dee2e6;
}

.shelter-option {
    padding: 12px;
    margin-bottom: 10px;
    border: 2px solid #e9ecef;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.3s;
    background: white;
}

.shelter-option:hover {
    border-color: #007bff;
    background: #f0f8ff;
    transform: translateY(-2px);
}

.shelter-option.selected {
    border-color: #28a745;
    background: #f0fff4;
}

.shelter-option input[type="radio"] {
    display: none;
}

.shelter-option label {
    display: block;
    cursor: pointer;
    margin: 0;
}

.shelter-name {
    font-weight: bold;
    color: #333;
    margin-bottom: 5px;
    font-size: 16px;
}

.shelter-details {
    font-size: 13px;
    color: #6c757d;
    line-height: 1.4;
}

.shelter-details p {
    margin: 3px 0;
}

.shelter-details strong {
    color: #495057;
}

.shelter-status {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: bold;
    margin-top: 5px;
}

.shelter-status.available {
    background: #d4edda;
    color: #155724;
}

.shelter-status.full {
    background: #f8d7da;
    color: #721c24;
}

.shelter-status.limited {
    background: #fff3cd;
    color: #856404;
}

/* Shelter details in success message */
.shelter-details-box {
    background: #e8f4f8;
    border: 1px solid #17a2b8;
    border-radius: 10px;
    padding: 20px;
    margin-bottom: 25px;
}

.shelter-details-box h5 {
    color: #0c5460;
    margin-top: 0;
    margin-bottom: 15px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.shelter-details-box .shelter-details {
    background: white;
    padding: 15px;
    border-radius: 8px;
    border-left: 4px solid #17a2b8;
}

/* API status styles */
.api-status-good {
    background: #d4edda !important;
    border-color: #c3e6cb !important;
    color: #155724 !important;
}

.api-status-warning {
    background: #fff3cd !important;
    border-color: #ffeaa7 !important;
    color: #856404 !important;
}
</style>
<script>
let selectedResources = [];
let maxSelections = 3;
let allResourceTypes = <?php echo json_encode($all_resource_types); ?>;
let apiResources = <?php echo json_encode($resources); ?>;

function toggleSpecialNeeds() {
    const hasBaby = document.getElementById('has_baby').checked;
    const hasElderly = document.getElementById('has_elderly').checked;
    const hasDisabled = document.getElementById('has_disabled').checked;
    
    const specialNeedsElements = document.querySelectorAll('.special-needs-section');
    
    if (hasBaby || hasElderly || hasDisabled) {
        // Show special needs section
        specialNeedsElements.forEach(element => {
            element.classList.remove('special-needs-hidden');
        });
        
        // Load appropriate resources
        loadResourcesForSelection(hasBaby, hasElderly, hasDisabled);
    } else {
        // Hide special needs section
        specialNeedsElements.forEach(element => {
            element.classList.add('special-needs-hidden');
        });
        
        // Clear all selections
        clearResourceSelections();
    }
}

function loadResourcesForSelection(hasBaby, hasElderly, hasDisabled) {
    const container = document.getElementById('dynamic-resources');
    container.innerHTML = '';
    
    // Show medical resources for everyone (only once!)
    if (allResourceTypes.includes('medical')) {
        addResourceGroup('medical', 'Medical Resources', container);
    } else if (allResourceTypes.includes('basic')) {
        addResourceGroup('basic', 'Basic Medical Resources', container);
    }
    
    // Show specific resources based on selection
    if (hasBaby && allResourceTypes.includes('baby')) {
        addResourceGroup('baby', 'Baby Resources', container);
    }
    
    // FIX: Elderly should NOT show medical resources again - they're already shown above
    if (hasElderly && allResourceTypes.includes('elderly')) {
        addResourceGroup('elderly', 'Elderly Resources', container);
    }
    
    if (hasDisabled && allResourceTypes.includes('disabled')) {
        addResourceGroup('disabled', 'Disabled Resources', container);
    }
}

function addResourceGroup(type, title, container) {
    const items = apiResources[type] || [];
    
    if (items.length > 0) {
        const group = document.createElement('div');
        group.className = 'resource-options';
        group.innerHTML = `
            <h5><i class="fas fa-${getIconForType(type)}"></i> ${title}</h5>
            <div id="resources-${type}">
                ${items.map((item, index) => `
                    <div class="resource-checkbox">
                        <input type="checkbox" 
                               id="${type}-${index}" 
                               data-type="${type}"
                               value="${item}"
                               onchange="toggleResourceSelection(this)">
                        <label for="${type}-${index}">${item}</label>
                    </div>
                `).join('')}
            </div>
        `;
        container.appendChild(group);
    }
}

function getIconForType(type) {
    switch(type) {
        case 'baby': return 'baby';
        case 'elderly': return 'user-friends';
        case 'disabled': return 'wheelchair';
        case 'basic': return 'first-aid';
        case 'medical': return 'first-aid';
        default: return 'box';
    }
}

function toggleResourceSelection(checkbox) {
    const resourceValue = checkbox.value;
    const resourceDiv = checkbox.closest('.resource-checkbox');
    
    if (checkbox.checked) {
        if (selectedResources.length >= maxSelections) {
            checkbox.checked = false;
            showSelectionLimitWarning();
            return;
        }
        selectedResources.push(resourceValue);
        resourceDiv.classList.add('selected');
    } else {
        const index = selectedResources.indexOf(resourceValue);
        if (index > -1) {
            selectedResources.splice(index, 1);
        }
        resourceDiv.classList.remove('selected');
    }
    
    updateSelectionCounter();
    updateHiddenInput();
}

function clearResourceSelections() {
    selectedResources = [];
    document.querySelectorAll('.resource-checkbox input[type="checkbox"]').forEach(cb => {
        cb.checked = false;
        cb.closest('.resource-checkbox').classList.remove('selected');
    });
    updateSelectionCounter();
    updateHiddenInput();
}

function updateSelectionCounter() {
    const counter = document.getElementById('selection-counter');
    if (counter) {
        counter.textContent = `${selectedResources.length}/${maxSelections}`;
    }
}

function showSelectionLimitWarning() {
    alert(`You can only select a maximum of ${maxSelections} items. Please deselect some items first.`);
}

function updateHiddenInput() {
    const hiddenInput = document.getElementById('selected_resources_input');
    if (hiddenInput) {
        hiddenInput.value = JSON.stringify(selectedResources);
    }
}

// Shelter selection functions - UPDATED to use disaster district
function updateShelterOptions() {
    console.log('updateShelterOptions called'); // Debug log
    
    const disasterSelect = document.getElementById('disaster_id');
    const selectedDisaster = disasterSelect.value;
    const shelterSection = document.getElementById('shelter-section');
    const shelterOptions = document.getElementById('shelter-options');
    
    console.log('Selected disaster value:', selectedDisaster); // Debug log
    
    if (!selectedDisaster) {
        shelterSection.classList.add('special-needs-hidden');
        return;
    }
    
    // Get the selected disaster's district from the option text
    const selectedOption = disasterSelect.options[disasterSelect.selectedIndex];
    const optionText = selectedOption.textContent;
    
    console.log('Option text:', optionText); // Debug log
    
    // Extract district from parentheses, e.g., "Flood (Melaka Tengah)"
    const match = optionText.match(/\(([^)]+)\)/);
    if (!match) {
        console.log('No district found in option text'); // Debug log
        shelterSection.classList.add('special-needs-hidden');
        return;
    }
    
    const disasterDistrict = match[1].trim();
    console.log('Extracted district:', disasterDistrict); // Debug log
    
    const shelters = <?php echo json_encode($shelters); ?>;
    
    if (shelters[disasterDistrict] && shelters[disasterDistrict].length > 0) {
        console.log('Found shelters for district:', disasterDistrict); // Debug log
        shelterSection.classList.remove('special-needs-hidden');
        
        // Clear existing options
        shelterOptions.innerHTML = '';
        
        // Add available shelters for the disaster's district
        shelters[disasterDistrict].forEach((shelter, index) => {
            const option = document.createElement('div');
            option.className = 'shelter-option';
            option.innerHTML = `
                <input type="radio" name="selected_shelter" id="shelter-${index}" value="${shelter.name}" onchange="toggleShelterSelection(this)">
                <label for="shelter-${index}">
                    <div class="shelter-name">${shelter.name}</div>
                    <div class="shelter-details">
                        <p><strong>📍 Address:</strong> ${shelter.address}</p>
                        <p><strong>📞 Phone:</strong> ${shelter.phone}</p>
                        <p><strong>👥 Capacity:</strong> ${shelter.capacity}</p>
                        <p><strong>🏪 Facilities:</strong> ${shelter.facilities}</p>
                        <span class="shelter-status ${shelter.status.toLowerCase().replace(' ', '-')}">${shelter.status}</span>
                    </div>
                </label>
            `;
            shelterOptions.appendChild(option);
        });
        
        // Update the shelter section title to show disaster district
        const title = shelterSection.querySelector('h5');
        if (title) {
            title.innerHTML = `<i class="fas fa-map-marker-alt"></i> Available Shelters in ${disasterDistrict} (Disaster Area)`;
        }
    } else {
        console.log('No shelters found for district:', disasterDistrict); // Debug log
        shelterSection.classList.add('special-needs-hidden');
    }
}

function toggleShelterSelection(radio) {
    // Remove selected class from all options
    document.querySelectorAll('.shelter-option').forEach(option => {
        option.classList.remove('selected');
    });
    
    // Add selected class to clicked option
    if (radio.checked) {
        radio.closest('.shelter-option').classList.add('selected');
    }
}

function validateForm() {
    const hasBaby = document.getElementById('has_baby').checked;
    const hasElderly = document.getElementById('has_elderly').checked;
    const hasDisabled = document.getElementById('has_disabled').checked;
    
    const hasSpecialPerson = hasBaby || hasElderly || hasDisabled;
    
    // If special person exists but no special needs selected, show warning
    if (hasSpecialPerson && selectedResources.length === 0) {
        if (!confirm('You have special persons in your household but didn\'t select any special needs. Are you sure you don\'t need any special assistance? Click OK to continue without special needs, or Cancel to go back and select items.')) {
            return false;
        }
    }
    
    // Validate selection limit
    if (selectedResources.length > maxSelections) {
        alert(`You can only select up to ${maxSelections} items. Please deselect some items.`);
        return false;
    }
    
    // Validate shelter selection (REQUIRED now)
    const disaster = document.getElementById('disaster_id').value;
    const shelterSection = document.getElementById('shelter-section');
    
    if (disaster && !shelterSection.classList.contains('special-needs-hidden')) {
        const selectedShelter = document.querySelector('input[name="selected_shelter"]:checked');
        if (!selectedShelter) {
            alert('Please select a collection point where you will collect your emergency supplies.');
            return false;
        }
    }
    
    return true;
}

function copyReference() {
    const refElement = document.querySelector('.ref-highlight');
    if (refElement) {
        const text = refElement.textContent;
        navigator.clipboard.writeText(text).then(function() {
            const originalText = refElement.textContent;
            refElement.textContent = 'Copied!';
            refElement.style.background = '#4caf50';
            refElement.style.color = 'white';
            
            setTimeout(function() {
                refElement.textContent = originalText;
                refElement.style.background = '#ffeb3b';
                refElement.style.color = 'black';
            }, 2000);
            
            alert('Reference number copied to clipboard!');
        });
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    console.log('Page loaded, initializing...'); // Debug log
    
    toggleSpecialNeeds();
    updateSelectionCounter();
    
    // Add event listener to disaster dropdown for shelter updates
    const disasterSelect = document.getElementById('disaster_id');
    if (disasterSelect) {
        console.log('Found disaster select, adding event listener'); // Debug log
        disasterSelect.addEventListener('change', updateShelterOptions);
        // Trigger once on load if disaster is already selected (e.g., after form submission)
        if (disasterSelect.value) {
            console.log('Disaster already selected, triggering update'); // Debug log
            updateShelterOptions();
        }
    } else {
        console.log('Disaster select element not found!'); // Debug log
    }
});
</script>
</head>
<body>

    <!-- NAVBAR - Same as dashboard -->
    <div class="navbar">
        <div class="nav-left">
            <a href="http://10.147.17.30:8000/main_page.php">HOME</a>
            <a href="http://10.147.17.30:8000/news.php">NEWS</a>
            <a href="index.php">VICTIM</a>
        </div>

        <div class="nav-right">
            <a href="http://10.147.17.30:8000/login.php" class="btn-login">Sign in</a>
            <a href="http://10.147.17.30:8000/register.php" class="btn-register">Register</a>
        </div>
    </div>

    <!-- PURPLE HERO SECTION - Same as dashboard -->
    <div class="dashboard-hero">
        <div class="dashboard-hero-content">
            <h1>Victim Registration Form</h1>
            <p>Register as a victim for disaster assistance and emergency support in Melaka</p>
        </div>
    </div>

    <!-- MAIN CONTENT -->
    <div class="main-content">

        <!-- Main Form Card -->
        <div class="form-card">
            
            <?php if ($message): ?>
            <div class="message <?= str_contains($message,'Registration Successful') ? 'success':'error' ?>">
                <?= $message ?>
            </div>
            <?php endif; ?>

            <?php if (empty($message) || str_contains($message, 'Registration Failed')): ?>
            <h2><i class="fas fa-user-plus"></i> Registration Details</h2>
            
            <!-- API Status Indicator -->
            <?php if (!empty($api_errors)): ?>
            <div class="note api-status-warning">
                <i class="fas fa-exclamation-triangle"></i>
                <strong>API Status:</strong> Some resource APIs failed to load. Using sample data for missing resources.
            </div>
            <?php elseif (!empty($all_resource_types)): ?>
            <div class="note api-status-good">
                <i class="fas fa-check-circle"></i>
                <strong>API Status:</strong> Successfully loaded resources from <?= count($all_resource_types) ?> APIs
            </div>
            <?php endif; ?>
            
            <form method="POST" onsubmit="return validateForm()">
                
                <!-- Section 1: Disaster Information -->
                <div class="section-title">
                    <i class="fas fa-triangle-exclamation"></i> 1. Disaster Information
                </div>
                
                <div class="form-group">
                    <label class="form-label">Select Disaster</label>
                    <select name="disaster_id" id="disaster_id" class="form-control" required>
                        <option value="">-- Please select the affected disaster --</option>
                        <?php foreach ($disasters as $d): ?>
                        <option value="<?= $d['disaster_id'] ?>">
                            <?= htmlspecialchars($d['disaster_name']) ?> (<?= htmlspecialchars($d['district']) ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Section 2: Personal Information -->
                <div class="section-title">
                    <i class="fas fa-user-circle"></i> 2. Personal Information
                </div>
                
                <div class="form-group">
                    <input type="text" name="full_name" class="form-control" placeholder="Full Name (as per IC)" required>
                </div>
                
                <div class="form-group">
                    <input type="text" name="ic_number" class="form-control" placeholder="IC Number (e.g., 901231-01-1234)" required>
                </div>
                
                <div class="form-group">
                    <input type="email" name="email" class="form-control" placeholder="Email Address" required>
                </div>
                
                <div class="form-group">
                    <input type="tel" name="phone" class="form-control" placeholder="Phone Number (e.g., 012-3456789)" required>
                </div>
                
                <div class="note">
                    <i class="fas fa-info-circle"></i> 
                    <strong>Important:</strong> Make sure all information is accurate. Your phone number will be used for WhatsApp confirmation.
                </div>

                <!-- Section 3: Address Details -->
                <div class="section-title">
                    <i class="fas fa-home"></i> 3. Address Details
                </div>
                
                <div class="form-group">
                    <textarea name="address" class="form-control" placeholder="Full Address (House no, Street, Area)" rows="3" required></textarea>
                </div>
                
                <div class="form-group">
                    <input type="text" name="postal_code" class="form-control" placeholder="Postal Code" required>
                </div>
                
                <div class="form-group">
                    <input type="text" name="city" class="form-control" placeholder="City" value="Melaka">
                </div>
                
                <!-- UPDATED: District as dropdown -->
                <div class="form-group">
                    <label class="form-label">District</label>
                    <select name="district" id="district" class="form-control" required>
                        <option value="">-- Select District --</option>
                        <?php foreach ($districts as $district): ?>
                        <option value="<?= htmlspecialchars($district) ?>"><?= htmlspecialchars($district) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Section 4: Supply Collection -->
<div class="section-title">
    <i class="fas fa-boxes"></i> 4. Select Supply Collection Point <span style="color: red;">*</span>
</div>

<div class="note">
    <i class="fas fa-lightbulb"></i> 
    <strong>Important:</strong> All registered victims will receive basic emergency supplies. You must select a collection point in the disaster area.
</div>

<div id="shelter-section" class="shelter-section special-needs-hidden">
    <h5><i class="fas fa-map-marker-alt"></i> Available Collection Points in Disaster Area</h5>
    <div id="shelter-options" class="shelter-options">
        <!-- Shelters will be dynamically loaded here based on disaster district -->
    </div>
    <div class="note" style="margin-top: 10px;">
        <i class="fas fa-info-circle"></i> 
        <strong>Note:</strong> Please bring your reference number and IC to your selected collection point during operating hours (8:00 AM - 8:00 PM).
    </div>
</div>

                <!-- Section 5: Household Information -->
                <div class="section-title">
                    <i class="fas fa-users"></i> 5. Household Information
                </div>
                
                <div class="form-group">
                    <label class="form-label">Number of Family Members (including yourself)</label>
                    <input type="number" name="family_members" class="form-control" min="1" value="1" required>
                </div>
                
                <div class="checkbox-group">
                    <div class="checkbox-item">
                        <input type="checkbox" name="has_baby" id="has_baby" onclick="toggleSpecialNeeds()">
                        <label for="has_baby">Household has baby (below 2 years)</label>
                    </div>
                    <div class="checkbox-item">
                        <input type="checkbox" name="has_elderly" id="has_elderly" onclick="toggleSpecialNeeds()">
                        <label for="has_elderly">Household has elderly (above 65 years)</label>
                    </div>
                    <div class="checkbox-item">
                        <input type="checkbox" name="has_disabled" id="has_disabled" onclick="toggleSpecialNeeds()">
                        <label for="has_disabled">Household has disabled member</label>
                    </div>
                </div>

                <!-- Section 6: Resource Selection - DYNAMIC -->
                <div class="section-title special-needs-section special-needs-hidden">
                    <i class="fas fa-hand-holding-heart"></i> 6. Select Special Needs Resources
                </div>
                
                <div class="note special-needs-section special-needs-hidden">
                    <i class="fas fa-lightbulb"></i> 
                    <strong>Important:</strong> Select up to 3 items from the available resources below. Resources are fetched live from our database.
                </div>
                
                <!-- Selection Limit Warning -->
                <div class="selection-info special-needs-section special-needs-hidden">
                    <i class="fas fa-info-circle"></i>
                    Select up to 3 items. Currently selected: <span id="selection-counter">0/3</span>
                </div>
                
                <!-- Dynamic Resources Container -->
                <div id="dynamic-resources" class="special-needs-section special-needs-hidden">
                    <!-- Resources will be dynamically loaded here -->
                </div>
                
                <!-- Hidden input to store selected resources -->
                <input type="hidden" name="selected_resources" id="selected_resources_input" value="">

                <button type="submit" class="submit-btn">
                    <i class="fas fa-paper-plane"></i> Submit Registration
                </button>

            </form>
            <?php endif; ?>
            
        </div>
    </div>

</body>
</html>