<?php
include "db.php";

/* =======================
   HANDLE FEEDBACK
======================= */
$feedback_message = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['feedback_submit'])) {
    try {
        $stmt = $conn->prepare("INSERT INTO feedback (rating, comments) VALUES (?, ?)");
        $stmt->execute([$_POST['rating'], $_POST['comments']]);
        $feedback_message = "✅ Thank you for sharing your feedback!";
    } catch (PDOException $e) {
        $feedback_message = "❌ Unable to submit feedback.";
    }
}

/* =======================
   FETCH DISASTERS - ONLY ACTIVE AND UNDER CONTROL
======================= */
$disasters = $conn->query("
    SELECT d.disaster_id, d.disaster_name, d.district, d.severity,
           d.alert_message, d.status,
           COUNT(v.victim_id) AS total_victims
    FROM disaster d
    LEFT JOIN victim v ON d.disaster_id = v.disaster_id
    WHERE d.status IN ('Active', 'Under Control')
    GROUP BY d.disaster_id, d.disaster_name, d.district, d.severity, d.alert_message, d.status
    ORDER BY d.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);


/* =======================
   SHELTERS - EXPANDED DUMMY DATA
======================= */
$shelters_by_district = [
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

/* =======================
   FETCH RESOURCES FROM APIs - IMPROVED VERSION
======================= */
$resources = [
    'baby' => [
        'title' => 'Baby & Infant Care',
        'api_url' => 'http://10.147.17.224:8000/baby_api.php',
        'items' => []
    ],
    'basic_needs' => [
        'title' => 'Basic Needs & Essentials',
        'api_url' => 'http://10.147.17.224:8000/basic_needs_api.php',
        'items' => []
    ],
    'elderly' => [
        'title' => 'Elderly Assistance',
        'api_url' => 'http://10.147.17.224:8000/elderly_api.php',
        'items' => []
    ],
    'disabled' => [
        'title' => 'Disabled Support',
        'api_url' => 'http://10.147.17.224:8000/disabled_api.php',
        'items' => []
    ],
    'medical' => [
        'title' => 'Medical Assistance',
        'api_url' => 'http://10.147.17.224:8000/medical_api.php',
        'items' => []
    ]
];

// Function to fetch resources from API using improved method
function fetchResourcesFromAPI($url) {
    // Try multiple methods to fetch data
    $methods = [
        'file_get_contents' => function($url) {
            $context = stream_context_create([
                'http' => [
                    'timeout' => 5,
                    'ignore_errors' => true,
                    'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36\r\n"
                ]
            ]);
            $response = @file_get_contents($url, false, $context);
            return $response !== false ? $response : false;
        },
        'curl' => function($url) {
            if (function_exists('curl_init')) {
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 5);
                curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
                $response = curl_exec($ch);
                curl_close($ch);
                return $response;
            }
            return false;
        }
    ];
    
    foreach ($methods as $method) {
        $response = $method($url);
        if ($response !== false && !empty($response)) {
            $data = json_decode($response, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
                return $data;
            }
        }
    }
    
    return [];
}

// Enhanced item extraction function
function extractItemNames($api_data) {
    $items = [];
    
    if (!is_array($api_data)) {
        return $items;
    }
    
    foreach ($api_data as $item) {
        if (!is_array($item)) {
            if (is_string($item)) {
                $items[] = $item;
            }
            continue;
        }
        
        // Try different possible field names
        $possible_fields = ['name', 'item_name', 'resource_name', 'type', 'title', 'product', 'item', 'resource'];
        
        foreach ($possible_fields as $field) {
            if (isset($item[$field]) && !empty($item[$field]) && is_string($item[$field])) {
                $items[] = $item[$field];
                break;
            }
        }
        
        // If no field found, use description
        if (isset($item['description']) && !empty($item['description']) && is_string($item['description'])) {
            $words = explode(' ', trim($item['description']));
            if (count($words) > 0) {
                $name = implode(' ', array_slice($words, 0, min(4, count($words))));
                if (strlen($name) > 60) {
                    $name = substr($name, 0, 57) . '...';
                }
                $items[] = $name;
            }
        }
    }
    
    return $items;
}

// Fetch resources from all APIs with better error reporting
foreach ($resources as $category => &$resource) {
    $api_data = fetchResourcesFromAPI($resource['api_url']);
    $resource['api_success'] = !empty($api_data);
    
    if (!empty($api_data)) {
        $resource['items'] = extractItemNames($api_data);
        
        // Debug output (you can remove this after testing)
        if (empty($resource['items'])) {
            error_log("Category $category: API returned data but no items extracted. Data sample: " . json_encode(array_slice($api_data, 0, 2)));
        }
    }
    
    // If API returns nothing or fails, use default items
    if (empty($resource['items'])) {
        $resource['items'] = getDefaultItems($category);
        $resource['api_success'] = false;
    }
    
    // Remove duplicates and limit to 20 items for display
    $resource['items'] = array_unique($resource['items']);
    $resource['items'] = array_values(array_filter($resource['items'])); // Remove empty values
    $resource['items'] = array_slice($resource['items'], 0, 20);
}

// Function to get default items if API fails
function getDefaultItems($category) {
    $defaults = [
        'baby' => [
            'Baby Formula', 'Diapers', 'Baby Wipes', 'Infant Clothing',
            'Baby Bottles', 'Baby Food', 'Pacifiers', 'Baby Blankets',
            'Infant Carriers', 'Baby Care Kits'
        ],
        'basic_needs' => [
            'Canned Food', 'Bottled Water', 'Blankets', 'Clothing', 
            'Hygiene Kits', 'Sleeping Mats', 'Flashlights', 'Batteries',
            'Cooking Equipment', 'Utensils'
        ],
        'elderly' => [
            'Wheelchairs', 'Walking Aids', 'Adult Diapers', 'Special Medications', 
            'Mobility Assistance', 'Hearing Aids', 'Reading Glasses', 
            'Oxygen Tanks', 'Walkers', 'Bed Pans'
        ],
        'disabled' => [
            'Wheelchair Ramps', 'Accessible Toilets', 'Braille Materials', 
            'Sign Language Interpreters', 'Special Transport', 'Adaptive Equipment',
            'Therapy Services', 'Support Animals Care'
        ],
        'medical' => [
            'First Aid Kits', 'Bandages & Gauze', 'Antiseptic Solution', 'Pain Relievers', 
            'Prescription Medications', 'Thermometers', 'Blood Pressure Monitors', 
            'Medical Gloves', 'Face Masks', 'Emergency Blankets'
        ]
    ];
    
    return $defaults[$category] ?? ['Sample Item 1', 'Sample Item 2', 'Sample Item 3'];
}

// Helper function to get icon for each resource category
function getResourceIcon($category) {
    $icons = [
        'baby' => 'fa-baby',
        'basic_needs' => 'fa-box',
        'elderly' => 'fa-person-cane',
        'disabled' => 'fa-wheelchair',
        'medical' => 'fa-heart-pulse'
    ];
    return $icons[$category] ?? 'fa-box';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Melaka Disaster Assistance Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
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

        /* NAVBAR */
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

        /* HERO SECTION WITH PICTURE */
        .dashboard-hero {
            height: 60vh;
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
            font-size: 3rem;
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

        /* MAIN CONTENT AREA */
        .main-content {
            max-width: 1200px;
            margin: 0 auto 60px;
            padding: 0 40px;
        }

        /* Section Cards */
        .section-card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 40px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            transition: transform 0.3s ease;
        }

        .section-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 18px rgba(0,0,0,0.12);
        }

        .section-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f8f9fa;
        }

        .section-icon {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
        }

        .section-title {
            font-size: 1.8rem;
            color: #333;
            font-weight: 600;
        }

        /* DISASTER GRID */
        .disaster-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 25px;
            margin-top: 20px;
        }

        .disaster-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            border: 1px solid #e9ecef;
            transition: all 0.3s ease;
            border-left: 4px solid #ffc107;
        }

        .disaster-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.1);
        }

        .disaster-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .disaster-name {
            font-size: 1.2rem;
            font-weight: 600;
            color: #333;
        }

        .status-badge {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .disaster-info {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 15px;
        }

        .info-item {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #666;
            font-size: 0.9rem;
        }

        .info-item i {
            color: #007bff;
        }

        .alert-message {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            color: #333;
            font-size: 0.95rem;
            border-left: 3px solid #007bff;
        }

        /* FEEDBACK SECTION */
        .feedback-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-top: 20px;
        }

        .feedback-form-card, .feedback-info-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            border: 1px solid #e9ecef;
        }

        .feedback-info-card {
            background: #f8f9fa;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            margin-bottom: 10px;
            font-weight: 600;
            color: #333;
        }

        .rating-stars {
            display: flex;
            gap: 8px;
            flex-direction: row-reverse;
            justify-content: flex-end;
        }

        .rating-stars input {
            display: none;
        }

        .rating-stars label {
            font-size: 2rem;
            color: #ddd;
            cursor: pointer;
            transition: color 0.3s;
        }

        .rating-stars label:hover,
        .rating-stars label:hover ~ label,
        .rating-stars input:checked ~ label {
            color: #ffc107;
        }

        .feedback-textarea {
            width: 100%;
            min-height: 120px;
            padding: 15px;
            border: 2px solid #dee2e6;
            border-radius: 8px;
            font-size: 1rem;
            resize: vertical;
            transition: border-color 0.3s;
        }

        .feedback-textarea:focus {
            outline: none;
            border-color: #007bff;
            box-shadow: 0 0 0 3px rgba(0,123,255,0.1);
        }

        .submit-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }

        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }

        .info-list {
            list-style: none;
        }

        .info-list li {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 15px;
            color: #333;
        }

        .info-list i {
            color: #007bff;
        }

        /* SHELTER GRID - IMPROVED */
        .shelter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 25px;
            margin-top: 20px;
        }

        .shelter-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            border: 1px solid #e9ecef;
            transition: all 0.3s ease;
            border-left: 4px solid #28a745;
        }

        .shelter-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.1);
        }

        .shelter-card h4 {
            color: #007bff;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f8f9fa;
        }

        .shelter-details p {
            margin-bottom: 8px;
            color: #666;
            font-size: 0.9rem;
        }

        .shelter-details strong {
            color: #333;
        }

        .shelter-status {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 600;
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

       /* RESOURCES SECTION - AUTO UPDATING */
.resources-container {
    margin-top: 20px;
}

.resource-category {
    margin-bottom: 30px;
    padding: 20px;
    background: #f8f9fa;
    border-radius: 10px;
    border-left: 5px solid;
}

.resource-category.baby {
    border-left-color: #ff6b9d;
}

.resource-category.basic_needs {
    border-left-color: #4ecdc4;
}

.resource-category.elderly {
    border-left-color: #ff9f43;
}

.resource-category.disabled {
    border-left-color: #5f27cd;
}

.resource-category.medical {
    border-left-color: #ff3838;
}

.resource-category-header {
    display: flex;
    align-items: center;
    gap: 15px;
    margin-bottom: 15px;
}

.resource-category-icon {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: white;
}

.resource-category-icon.baby {
    background: linear-gradient(135deg, #ff6b9d 0%, #ff8fab 100%);
    box-shadow: 0 4px 10px rgba(255, 107, 157, 0.3);
}

.resource-category-icon.basic_needs {
    background: linear-gradient(135deg, #4ecdc4 0%, #44a08d 100%);
    box-shadow: 0 4px 10px rgba(78, 205, 196, 0.3);
}

.resource-category-icon.elderly {
    background: linear-gradient(135deg, #ff9f43 0%, #ffb347 100%);
    box-shadow: 0 4px 10px rgba(255, 159, 67, 0.3);
}

.resource-category-icon.disabled {
    background: linear-gradient(135deg, #5f27cd 0%, #8e44ad 100%);
    box-shadow: 0 4px 10px rgba(95, 39, 205, 0.3);
}

.resource-category-icon.medical {
    background: linear-gradient(135deg, #ff3838 0%, #ff6b6b 100%);
    box-shadow: 0 4px 10px rgba(255, 56, 56, 0.3);
}

.resource-category-title {
    font-size: 1.4rem;
    font-weight: 600;
    color: #333;
}
        .api-source {
            font-size: 0.8rem;
            color: #666;
            background: white;
            padding: 3px 8px;
            border-radius: 10px;
            border: 1px solid #dee2e6;
            margin-left: 10px;
        }

        .resource-items-list {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 10px;
        }

        .resource-item-tag {
            background: white;
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 0.9rem;
            color: #333;
            border: 1px solid #dee2e6;
            transition: all 0.3s;
        }

        .resource-item-tag:hover {
            background: #007bff;
            color: white;
            border-color: #007bff;
            transform: translateY(-2px);
            box-shadow: 0 3px 8px rgba(0,123,255,0.2);
        }

        .resources-note {
            background: #e7f3ff;
            border-left: 4px solid #007bff;
            padding: 15px;
            border-radius: 8px;
            margin-top: 25px;
            font-size: 0.95rem;
            color: #333;
        }

        .api-update-info {
            font-size: 0.85rem;
            margin-top: 5px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .api-update-info.success {
            color: #28a745;
        }

        .api-update-info.error {
            color: #dc3545;
        }

        /* MESSAGES */
        .message {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
            border-left: 4px solid;
        }

        .message.success {
            background: #d4edda;
            color: #155724;
            border-left-color: #28a745;
        }

        .message.error {
            background: #f8d7da;
            color: #721c24;
            border-left-color: #dc3545;
        }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #666;
            font-style: italic;
        }

        /* RESPONSIVE */
        @media (max-width: 992px) {
            .feedback-container {
                grid-template-columns: 1fr;
            }
            
            .dashboard-hero h1 {
                font-size: 2.5rem;
            }
        }

        @media (max-width: 768px) {
            .navbar {
                padding: 15px 20px;
            }
            
            .nav-left a {
                margin: 0 10px;
                font-size: 14px;
            }
            
            .main-content {
                padding: 0 20px;
            }
            
            .disaster-grid,
            .shelter-grid {
                grid-template-columns: 1fr;
            }
            
            .dashboard-hero {
                height: 50vh;
            }
            
            .dashboard-hero h1 {
                font-size: 2rem;
            }
            
            .dashboard-hero p {
                font-size: 1.1rem;
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
            
            .section-card {
                padding: 20px;
            }
            
            .dashboard-hero h1 {
                font-size: 1.8rem;
            }
            
            .resource-category-header {
                flex-direction: column;
                text-align: center;
                gap: 10px;
            }
        }
    </style>
</head>
<body>

    <!-- NAVBAR - With ALL correct URLs -->
    <div class="navbar">
        <div class="nav-left">
            <a href="http://10.147.17.30:8000/main_page.php">HOME</a>
            <a href="http://10.147.17.30:8000/news.php">NEWS</a>
            <a href="index.php" class="active">VICTIM</a>
        </div>

        <div class="nav-right">
            <a href="http://10.147.17.30:8000/login.php" class="btn-login">Sign in</a>
            <a href="http://10.147.17.30:8000/register.php" class="btn-register">Register</a>
        </div>
    </div>

    <!-- HERO SECTION WITH PICTURE -->
    <div class="dashboard-hero">
        <div class="dashboard-hero-content">
            <h1>Melaka Disaster Assistance Portal</h1>
            <p>Public Support & Emergency Information - Access real-time disaster alerts, emergency shelters, and help us improve our response services</p>
            
            <div class="quick-actions">
                <a href="victim_register.php" class="action-btn">
                    <i class="fas fa-user-injured"></i>Register as Victim
                </a>
                <a href="report_disaster.php" class="action-btn primary">
                    <i class="fas fa-bullhorn"></i>Report Disaster
                </a>
            </div>
        </div>
    </div>

    <!-- MAIN CONTENT -->
    <div class="main-content">
        
        <!-- DISASTER ALERTS -->
        <div class="section-card">
            <div class="section-header">
                <div class="section-icon">
                    <i class="fas fa-triangle-exclamation"></i>
                </div>
                <h2 class="section-title">Current Disaster Alerts</h2>
            </div>

            <?php if (!$disasters): ?>
                <p class="empty-state">No active disasters reported. Stay safe 💙</p>
            <?php else: ?>
                <div class="disaster-grid">
                    <?php foreach ($disasters as $d): ?>
                    <div class="disaster-card">
                        <div class="disaster-header">
                            <h3 class="disaster-name"><?= htmlspecialchars($d['disaster_name']) ?></h3>
                            <span class="status-badge"><?= htmlspecialchars($d['status']) ?></span>
                        </div>

                        <div class="disaster-info">
                            <div class="info-item">
                                <i class="fas fa-location-dot"></i>
                                <span><?= htmlspecialchars($d['district']) ?></span>
                            </div>
                            <div class="info-item">
                                <i class="fas fa-bolt"></i>
                                <span><?= htmlspecialchars($d['severity']) ?></span>
                            </div>
                            <div class="info-item">
                                <i class="fas fa-users"></i>
                                <span><?= $d['total_victims'] ?> victims</span>
                            </div>
                        </div>

                        <p class="alert-message">
                            <?= htmlspecialchars($d['alert_message']) ?>
                        </p>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- RESOURCES SECTION - AUTO UPDATING FROM APIs -->
        <div class="section-card">
            <div class="section-header">
                <div class="section-icon">
                    <i class="fas fa-boxes-stacked"></i>
                </div>
                <h2 class="section-title">Available Resources</h2>
            </div>

            <div class="resources-container">
                <p class="mb-4">Victims can request assistance from the following items. This list updates automatically from our resource databases.</p>
                
                <?php foreach ($resources as $category => $resource): ?>
                <div class="resource-category <?= $category ?>">
                    <div class="resource-category-header">
                        <div class="resource-category-icon <?= $category ?>">
                            <i class="fas <?= getResourceIcon($category) ?>"></i>
                        </div>
                        <div class="resource-category-title">
                            <?= $resource['title'] ?>
                            <span class="api-source">Live Updates</span>
                        </div>
                    </div>
                    
                    <div class="api-update-info <?= $resource['api_success'] ? 'success' : 'error' ?>">
                        <i class="fas <?= $resource['api_success'] ? 'fa-check-circle' : 'fa-exclamation-triangle' ?>"></i>
                        <?= $resource['api_success'] ? 
                            'Connected to live database' : 
                            'Using sample data (API connection failed)' ?>
                    </div>
                    
                    <div class="resource-items-list">
                        <?php if (!empty($resource['items'])): ?>
                            <?php foreach ($resource['items'] as $item): ?>
                            <div class="resource-item-tag"><?= htmlspecialchars($item) ?></div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="resource-item-tag">No items available</div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
                
                <div class="resources-note">
                    <strong>Important:</strong> Please make sure you fill in the special needs using the exact same capital letters and correct spelling, as the system is not case-sensitive.
                </div>
            </div>
        </div>

        <!-- FEEDBACK SECTION -->
        <div class="section-card">
            <div class="section-header">
                <div class="section-icon">
                    <i class="fas fa-comment-dots"></i>
                </div>
                <h2 class="section-title">Your Feedback</h2>
            </div>

            <?php if ($feedback_message): ?>
                <div class="message <?= str_contains($feedback_message,'✅') ? 'success':'error' ?>">
                    <?= $feedback_message ?>
                </div>
            <?php endif; ?>

            <div class="feedback-container">
                <!-- Feedback Form -->
                <div class="feedback-form-card">
                    <form method="POST" class="feedback-form">
                        <!-- Rating -->
                        <div class="form-group">
                            <label class="form-label">Overall experience</label>
                            <div class="rating-stars">
                                <?php for ($i = 5; $i >= 1; $i--): ?>
                                    <input type="radio" name="rating" id="rate<?= $i ?>" value="<?= $i ?>" required>
                                    <label for="rate<?= $i ?>">★</label>
                                <?php endfor; ?>
                            </div>
                        </div>

                        <!-- Comments -->
                        <div class="form-group">
                            <label class="form-label">Comments</label>
                            <textarea 
                                name="comments" 
                                class="feedback-textarea"
                                placeholder="Share your experience or suggestions..."
                                required></textarea>
                        </div>

                        <button type="submit" name="feedback_submit" class="submit-btn">
                            <i class="fas fa-paper-plane"></i> Submit Feedback
                        </button>
                    </form>
                </div>

                <!-- Feedback Info -->
                <div class="feedback-info-card">
                    <h3>Why your feedback matters</h3>
                    <ul class="info-list">
                        <li><i class="fas fa-check-circle"></i> Improves emergency response</li>
                        <li><i class="fas fa-check-circle"></i> Helps allocate aid fairly</li>
                        <li><i class="fas fa-check-circle"></i> Identifies system issues</li>
                        <li><i class="fas fa-lock"></i> Anonymous & confidential</li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- SHELTERS SECTION - WITH EXPANDED DATA -->
        <div class="section-card">
            <div class="section-header">
                <div class="section-icon">
                    <i class="fas fa-building"></i>
                </div>
                <h2 class="section-title">Nearby Shelters</h2>
            </div>

            <div class="shelter-grid">
                <?php foreach ($shelters_by_district as $district => $list): ?>
                <div class="shelter-card">
                    <h4><?= htmlspecialchars($district) ?> District</h4>
                    <div class="shelter-details">
                        <?php foreach ($list as $s): ?>
                        <p><strong><?= $s['name'] ?></strong></p>
                        <p>📍 <strong>Address:</strong> <?= $s['address'] ?></p>
                        <p>📞 <strong>Phone:</strong> <?= $s['phone'] ?></p>
                        <p>✉ <strong>Email:</strong> <?= $s['email'] ?></p>
                        <p>👥 <strong>Capacity:</strong> <?= $s['capacity'] ?></p>
                        <p>🏪 <strong>Facilities:</strong> <?= $s['facilities'] ?></p>
                        <p>
                            <strong>Status:</strong> 
                            <span class="shelter-status <?= strtolower(str_replace(' ', '', $s['status'])) ?>">
                                <?= $s['status'] ?>
                            </span>
                        </p>
                        <hr style="margin: 15px 0; border: none; border-top: 1px solid #eee;">
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

    </div>

    <script>
        // Navbar scroll effect
        window.addEventListener('scroll', function() {
            const navbar = document.querySelector('.navbar');
            if (window.scrollY > 50) {
                navbar.style.boxShadow = '0 4px 12px rgba(0,0,0,0.1)';
                navbar.style.padding = '15px 60px';
            } else {
                navbar.style.boxShadow = '0 2px 10px rgba(0,0,0,0.1)';
                navbar.style.padding = '20px 60px';
            }
        });
    </script>
</body>
</html>