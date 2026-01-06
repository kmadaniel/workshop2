<?php
// ========================================
// MANAGE VOLUNTEERS
// Consolidated volunteer management system
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
$DISTRIBUTION_API_URL = 'http://10.147.17.116:8000/distribution.php';

// Initialize variables
$all_volunteers = [];
$filtered_volunteers = [];
$assigned_volunteers = [];
$available_volunteers = [];
$active_distributions = [];
$ngos = ['All'];
$error = '';
$success = '';

// Filter parameters
$filter_ngo = $_GET['ngo'] ?? 'All';
$filter_status = $_GET['status'] ?? 'All';
$filter_skill = $_GET['skill'] ?? 'All';
$search_query = $_GET['search'] ?? '';

/* ========================================
   API FETCH FUNCTIONS
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
        
        // Fallback to cURL
        if ($response === FALSE) {
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

/* ========================================
   FETCH VOLUNTEERS FROM EXTERNAL API
======================================== */
try {
    $volunteer_api_result = fetchFromAPI($VOLUNTEER_API_URL);
    
    if ($volunteer_api_result['success']) {
        $api_volunteers = $volunteer_api_result['data'];
        
        // Check if data is nested
        if (isset($api_volunteers['volunteers']) && is_array($api_volunteers['volunteers'])) {
            $api_volunteers = $api_volunteers['volunteers'];
        } elseif (isset($api_volunteers['data']) && is_array($api_volunteers['data'])) {
            $api_volunteers = $api_volunteers['data'];
        }
        
        foreach ($api_volunteers as $api_vol) {
            $external_volunteer_id = $api_vol['VolunteerID'] ?? $api_vol['volunteer_id'] ?? $api_vol['id'] ?? 0;
            $name = $api_vol['FullName'] ?? $api_vol['fullName'] ?? $api_vol['name'] ?? 'Unknown Volunteer';
            $skill_category = $api_vol['SkillCategory'] ?? $api_vol['skill_category'] ?? $api_vol['Role'] ?? $api_vol['role'] ?? 'Volunteer';
            
            if ($external_volunteer_id && $name != 'Unknown Volunteer') {
                $volunteer = [
                    'volunteer_id' => $external_volunteer_id,
                    'name' => $name,
                    'phone' => $api_vol['Phone'] ?? $api_vol['phone'] ?? '',
                    'email' => $api_vol['Email'] ?? $api_vol['email'] ?? '',
                    'role' => $skill_category,
                    'skill_category' => $skill_category,
                    'availability_status' => $api_vol['Status'] ?? $api_vol['status'] ?? 'Available',
                    'availability' => $api_vol['Availability'] ?? $api_vol['availability'] ?? 'Available',
                    'ngo_affiliation' => $api_vol['AssignedNGO'] ?? $api_vol['assignedNGO'] ?? $api_vol['ngo'] ?? 'Various',
                    'address' => $api_vol['Address'] ?? $api_vol['address'] ?? '',
                    'registered_date' => $api_vol['RegisteredDate'] ?? $api_vol['registered_date'] ?? date('Y-m-d'),
                    'last_active' => $api_vol['LastActive'] ?? $api_vol['last_active'] ?? null
                ];
                
                $all_volunteers[] = $volunteer;
                
                // Add NGO to list if not already there
                if (!empty($volunteer['ngo_affiliation']) && !in_array($volunteer['ngo_affiliation'], $ngos)) {
                    $ngos[] = $volunteer['ngo_affiliation'];
                }
            }
        }
        
        if (empty($all_volunteers)) {
            throw new Exception("No volunteers found in API response");
        }
        
    } else {
        throw new Exception("Failed to fetch volunteers from API: " . ($volunteer_api_result['error'] ?? 'Unknown error'));
    }
} catch (Exception $e) {
    error_log("Error fetching volunteers from API: " . $e->getMessage());
    $error = "Unable to load volunteers from external system. Please try again later.";
}

/* ========================================
   FETCH NGOS FROM EXTERNAL API
======================================== */
try {
    $ngo_api_result = fetchFromAPI($NGO_API_URL);
    
    if ($ngo_api_result['success']) {
        $api_ngos = $ngo_api_result['data'];
        
        // Check if data is nested
        if (isset($api_ngos['ngos']) && is_array($api_ngos['ngos'])) {
            $api_ngos = $api_ngos['ngos'];
        } elseif (isset($api_ngos['data']) && is_array($api_ngos['data'])) {
            $api_ngos = $api_ngos['data'];
        }
        
        // Reset NGO list (keep 'All')
        $ngos = ['All'];
        
        foreach ($api_ngos as $api_ngo) {
            $ngo_name = $api_ngo['NGOName'] ?? $api_ngo['ngoName'] ?? $api_ngo['name'] ?? '';
            if (!empty($ngo_name) && !in_array($ngo_name, $ngos)) {
                $ngos[] = $ngo_name;
            }
        }
    }
} catch (Exception $e) {
    error_log("Error fetching NGOs: " . $e->getMessage());
}

/* ========================================
   FETCH ACTIVE DISTRIBUTIONS
======================================== */
try {
    $distributions_query = "
        SELECT d.*, 
               (SELECT COUNT(*) FROM distribution_volunteer dv WHERE dv.distribution_id = d.distribution_id) as assigned_volunteers,
               (SELECT COUNT(*) FROM distribution_log dl WHERE dl.distribution_id = d.distribution_id AND dl.status = 'completed') as completed_items
        FROM distribution d
        WHERE d.status IN ('Planning', 'Assigned', 'In Progress', 'In Transit')
        ORDER BY d.date ASC
        LIMIT 10
    ";
    
    $stmt = $db->prepare($distributions_query);
    if ($stmt) {
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $active_distributions[] = $row;
        }
        $stmt->close();
    }
} catch (Exception $e) {
    error_log("Error fetching active distributions: " . $e->getMessage());
}

/* ========================================
   GET VOLUNTEER ASSIGNMENTS AND STATS - FIXED VERSION
======================================== */
$volunteer_stats = [];
$assigned_volunteers_by_distribution = [];

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
}

try {
    // Get all assignments
    $assignments_query = "
        SELECT dv.*, d.date, d.location, d.status as distribution_status
        FROM distribution_volunteer dv
        JOIN distribution d ON dv.distribution_id = d.distribution_id
        WHERE d.status != 'Completed'
        ORDER BY d.date DESC
    ";
    
    $stmt = $db->prepare($assignments_query);
    if ($stmt) {
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $volunteer_id = $row['volunteer_id'];
            $distribution_id = $row['distribution_id'];
            
            // Update stats
            if (isset($volunteer_stats[$volunteer_id])) {
                $volunteer_stats[$volunteer_id]['total_assignments']++;
                if ($row['distribution_status'] != 'Completed') {
                    $volunteer_stats[$volunteer_id]['active_assignments']++;
                }
                $volunteer_stats[$volunteer_id]['assignments'][] = $row;
            }
            
            // Track assigned volunteers by distribution
            if (!isset($assigned_volunteers_by_distribution[$distribution_id])) {
                $assigned_volunteers_by_distribution[$distribution_id] = [];
            }
            $assigned_volunteers_by_distribution[$distribution_id][] = $volunteer_id;
        }
        $stmt->close();
    }
    
    // Get distribution log stats for each volunteer
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
    
} catch (Exception $e) {
    error_log("Error fetching volunteer assignments: " . $e->getMessage());
}

// Also check if distribution_log table exists to avoid errors
try {
    $table_check = $db->query("SHOW TABLES LIKE 'distribution_log'");
    if (!$table_check || $table_check->num_rows == 0) {
        error_log("distribution_log table doesn't exist yet");
    }
} catch (Exception $e) {
    error_log("Error checking distribution_log table: " . $e->getMessage());
}

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
        
        if (empty($selected_volunteers)) {
            $error = "Please select at least one volunteer.";
        } elseif (!$distribution_id) {
            $error = "Please select a distribution.";
        } else {
            try {
                $db->begin_transaction();
                $assigned_count = 0;
                
                foreach ($selected_volunteers as $volunteer_id) {
                    // Check if already assigned
                    $check_query = "SELECT id FROM distribution_volunteer WHERE distribution_id = ? AND volunteer_id = ?";
                    $stmt = $db->prepare($check_query);
                    $stmt->bind_param("ii", $distribution_id, $volunteer_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $already_assigned = ($result->num_rows > 0);
                    $stmt->close();
                    
                    if (!$already_assigned) {
                        // Assign volunteer
                        $assign_query = "INSERT INTO distribution_volunteer (distribution_id, volunteer_id, role, status) VALUES (?, ?, ?, 'Assigned')";
                        $stmt = $db->prepare($assign_query);
                        
                        // Get volunteer's skill category
                        $volunteer_role = 'Volunteer';
                        foreach ($all_volunteers as $vol) {
                            if ($vol['volunteer_id'] == $volunteer_id) {
                                $volunteer_role = $vol['skill_category'] ?? 'Volunteer';
                                break;
                            }
                        }
                        
                        $stmt->bind_param("iis", $distribution_id, $volunteer_id, $volunteer_role);
                        if ($stmt->execute()) {
                            $assigned_count++;
                        }
                        $stmt->close();
                    }
                }
                
                // Update distribution status if not already assigned
                $update_query = "UPDATE distribution SET status = 'Assigned' WHERE distribution_id = ? AND status = 'Planning'";
                $stmt = $db->prepare($update_query);
                $stmt->bind_param("i", $distribution_id);
                $stmt->execute();
                $stmt->close();
                
                $db->commit();
                $success = "Successfully assigned $assigned_count volunteer(s) to distribution #$distribution_id";
                
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
                
                // Remove from distribution_volunteer
                $remove_query = "DELETE FROM distribution_volunteer WHERE distribution_id = ? AND volunteer_id = ?";
                $stmt = $db->prepare($remove_query);
                $stmt->bind_param("ii", $distribution_id, $volunteer_id);
                $stmt->execute();
                $stmt->close();
                
                // Remove from distribution_log
                $remove_log_query = "DELETE FROM distribution_log WHERE distribution_id = ? AND volunteer_id = ?";
                $stmt = $db->prepare($remove_log_query);
                $stmt->bind_param("ii", $distribution_id, $volunteer_id);
                $stmt->execute();
                $stmt->close();
                
                // Check if any volunteers remain
                $check_remaining = "SELECT COUNT(*) as count FROM distribution_volunteer WHERE distribution_id = ?";
                $stmt = $db->prepare($check_remaining);
                $stmt->bind_param("i", $distribution_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $row = $result->fetch_assoc();
                $remaining_count = $row['count'] ?? 0;
                $stmt->close();
                
                // Update distribution status if no volunteers left
                if ($remaining_count == 0) {
                    $update_query = "UPDATE distribution SET status = 'Planning' WHERE distribution_id = ?";
                    $stmt = $db->prepare($update_query);
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
    
    // Handle volunteer status update
    if (isset($_POST['update_status'])) {
        $volunteer_id = $_POST['volunteer_id'] ?? 0;
        $new_status = $_POST['new_status'] ?? '';
        $notes = $_POST['status_notes'] ?? '';
        
        if ($volunteer_id && $new_status) {
            try {
                // Create volunteer_status_updates table if not exists
                $create_table = "
                    CREATE TABLE IF NOT EXISTS volunteer_status_updates (
                        id INT PRIMARY KEY AUTO_INCREMENT,
                        volunteer_id INT NOT NULL,
                        old_status VARCHAR(50),
                        new_status VARCHAR(50),
                        notes TEXT,
                        updated_by VARCHAR(100),
                        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        INDEX (volunteer_id)
                    )
                ";
                $db->query($create_table);
                
                // Get current status
                $current_status = '';
                foreach ($all_volunteers as $vol) {
                    if ($vol['volunteer_id'] == $volunteer_id) {
                        $current_status = $vol['availability_status'];
                        break;
                    }
                }
                
                // Log status change
                $insert_query = "INSERT INTO volunteer_status_updates (volunteer_id, old_status, new_status, notes, updated_by) VALUES (?, ?, ?, ?, ?)";
                $stmt = $db->prepare($insert_query);
                $admin_name = $_SESSION['user_name'] ?? 'Admin';
                $stmt->bind_param("issss", $volunteer_id, $current_status, $new_status, $notes, $admin_name);
                $stmt->execute();
                $stmt->close();
                
                $success = "Volunteer status updated successfully.";
                
            } catch (Exception $e) {
                $error = "Error updating status: " . $e->getMessage();
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
    return strtolower($v['availability_status']) == 'available';
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
        
        .header-actions {
            display: flex;
            gap: 15px;
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
        
        .btn-outline {
            background: transparent;
            color: var(--primary);
            border: 2px solid var(--primary);
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
        
        /* Main Content */
        .main-content {
            display: grid;
            grid-template-columns: 350px 1fr;
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
            transform: scale(1.5);
            accent-color: var(--primary);
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
        
        .data-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            background: white;
            border-radius: var(--border-radius);
            overflow: hidden;
        }
        
        .data-table th {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            padding: 18px 15px;
            text-align: left;
            font-weight: 600;
        }
        
        .data-table td {
            padding: 15px;
            border-bottom: 1px solid var(--light-gray);
        }
        
        .data-table tr:last-child td {
            border-bottom: none;
        }
        
        .data-table tr:hover {
            background: rgba(67, 97, 238, 0.05);
        }
        
        /* Actions */
        .action-buttons {
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
        
        .action-btn.view {
            background: var(--info);
            color: white;
        }
        
        .action-btn.remove {
            background: var(--danger);
            color: white;
        }
        
        .action-btn.edit {
            background: var(--warning);
            color: white;
        }
        
        /* Mass Assignment */
        .mass-assign-section {
            background: white;
            border-radius: var(--border-radius);
            padding: 30px;
            margin: 30px 0;
            box-shadow: var(--box-shadow);
            border-left: 5px solid var(--primary);
        }
        
        .mass-assign-form {
            display: grid;
            grid-template-columns: 2fr 1fr auto;
            gap: 15px;
            align-items: end;
        }
        
        @media (max-width: 768px) {
            .mass-assign-form {
                grid-template-columns: 1fr;
            }
        }
        
        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 30px;
        }
        
        .page-btn {
            padding: 10px 18px;
            border: 1px solid var(--light-gray);
            background: white;
            border-radius: 6px;
            cursor: pointer;
            transition: var(--transition);
        }
        
        .page-btn.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }
        
        .page-btn:hover:not(.active) {
            background: var(--light);
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
        
        /* Responsive */
        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .header-actions {
                width: 100%;
                justify-content: space-between;
            }
            
            .volunteers-container {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 576px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .volunteer-stats {
                grid-template-columns: repeat(2, 1fr);
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
            </div>
        </div>
        
        <!-- Stats -->
        <div class="stats-grid">
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
            <!-- Sidebar - Mass Assignment & Filters -->
            <div class="sidebar">
                <!-- Mass Assignment Form -->
                <div class="sidebar-section">
                    <h3 class="sidebar-title">
                        <i class="fas fa-user-plus"></i>
                        Mass Assignment
                    </h3>
                    
                    <form method="POST" id="massAssignForm">
                        <input type="hidden" name="mass_assign" value="1">
                        
                        <div class="filter-group">
                            <label class="filter-label">
                                <i class="fas fa-box-open"></i>
                                Select Distribution
                            </label>
                            <select name="distribution_id" class="filter-select" required>
                                <option value="">-- Select Distribution --</option>
                                <?php foreach ($active_distributions as $distribution): ?>
                                <option value="<?php echo $distribution['distribution_id']; ?>">
                                    #DIST<?php echo str_pad($distribution['distribution_id'], 6, '0', STR_PAD_LEFT); ?> 
                                    - <?php echo htmlspecialchars($distribution['location'] ?? 'N/A'); ?>
                                    (<?php echo date('d/m/Y', strtotime($distribution['date'])); ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div style="margin: 20px 0; padding: 15px; background: #f8f9fa; border-radius: 8px;">
                            <div style="font-weight: 600; color: var(--dark); margin-bottom: 10px;">
                                <i class="fas fa-info-circle"></i> Instructions:
                            </div>
                            <ol style="margin: 0; padding-left: 20px; font-size: 0.9rem; color: var(--gray);">
                                <li>Select volunteers from the list</li>
                                <li>Choose a distribution</li>
                                <li>Click "Assign Selected"</li>
                            </ol>
                        </div>
                        
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 25px;">
                            <span id="selectedCount" style="font-weight: 600; color: var(--primary);">0 selected</span>
                            <button type="submit" class="btn btn-success" id="assignButton" disabled>
                                <i class="fas fa-user-check"></i>
                                Assign Selected
                            </button>
                        </div>
                    </form>
                </div>
                
                <!-- Filters -->
                <div class="sidebar-section">
                    <h3 class="sidebar-title">
                        <i class="fas fa-filter"></i>
                        Filter Volunteers
                    </h3>
                    
                    <form method="GET" id="filterForm">
                        <div class="filter-group">
                            <label class="filter-label">
                                <i class="fas fa-hands-helping"></i>
                                NGO Affiliation
                            </label>
                            <select name="ngo" class="filter-select" onchange="this.form.submit()">
                                <?php foreach ($ngos as $ngo): ?>
                                <option value="<?php echo htmlspecialchars($ngo); ?>" <?php echo $filter_ngo == $ngo ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($ngo); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label class="filter-label">
                                <i class="fas fa-circle"></i>
                                Status
                            </label>
                            <select name="status" class="filter-select" onchange="this.form.submit()">
                                <?php foreach ($status_options as $status): ?>
                                <option value="<?php echo $status; ?>" <?php echo $filter_status == $status ? 'selected' : ''; ?>>
                                    <?php echo $status; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label class="filter-label">
                                <i class="fas fa-tools"></i>
                                Skill Category
                            </label>
                            <select name="skill" class="filter-select" onchange="this.form.submit()">
                                <option value="All">All Skills</option>
                                <?php foreach ($skill_categories as $skill): ?>
                                <option value="<?php echo htmlspecialchars($skill); ?>" <?php echo $filter_skill == $skill ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($skill); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label class="filter-label">
                                <i class="fas fa-search"></i>
                                Search
                            </label>
                            <div style="display: flex; gap: 10px;">
                                <input type="text" 
                                       name="search" 
                                       class="filter-input" 
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
                <!-- Volunteers Grid -->
                <div class="filter-section">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <h2 style="margin: 0; color: var(--dark);">
                            <i class="fas fa-users"></i>
                            Volunteers (<?php echo count($filtered_volunteers); ?>)
                        </h2>
                        <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
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
                        <div class="volunteer-card">
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
                                    <div class="stat-mini-label">Assignments</div>
                                </div>
                                <div class="stat-mini">
                                    <div class="stat-mini-value"><?php echo $stats['families_helped']; ?></div>
                                    <div class="stat-mini-label">Families Helped</div>
                                </div>
                                <div class="stat-mini">
                                    <div class="stat-mini-value"><?php echo $stats['items_distributed']; ?></div>
                                    <div class="stat-mini-label">Items Distributed</div>
                                </div>
                            </div>
                            
                            <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid var(--light-gray);">
                                <div class="action-buttons">
                                    <button class="action-btn view" onclick="viewVolunteerDetails(<?php echo $volunteer_id; ?>)">
                                        <i class="fas fa-eye"></i> View
                                    </button>
                                    
                                    <?php if ($stats['active_assignments'] > 0): ?>
                                    <button class="action-btn remove" onclick="viewAssignments(<?php echo $volunteer_id; ?>)">
                                        <i class="fas fa-tasks"></i> Assignments (<?php echo $stats['active_assignments']; ?>)
                                    </button>
                                    <?php endif; ?>
                                    
                                    <button class="action-btn edit" onclick="editVolunteerStatus(<?php echo $volunteer_id; ?>, '<?php echo htmlspecialchars($volunteer['availability_status']); ?>')">
                                        <i class="fas fa-edit"></i> Status
                                    </button>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                
                <!-- Active Assignments Table -->
                <?php if (!empty($assigned_volunteers_by_distribution)): ?>
                <div class="assignments-section">
                    <div class="section-header">
                        <h2 class="section-title">
                            <i class="fas fa-tasks"></i>
                            Active Assignments
                        </h2>
                        <span style="background: var(--primary); color: white; padding: 5px 15px; border-radius: 20px; font-size: 0.9rem; font-weight: 600;">
                            <?php echo count($assigned_volunteers_by_distribution); ?> distributions
                        </span>
                    </div>
                    
                    <div style="overflow-x: auto;">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Distribution</th>
                                    <th>Assigned Volunteers</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                    <th>Location</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($active_distributions as $distribution): 
                                    $distribution_id = $distribution['distribution_id'];
                                    $assigned_vols = $assigned_volunteers_by_distribution[$distribution_id] ?? [];
                                    if (!empty($assigned_vols)):
                                ?>
                                <tr>
                                    <td>
                                        <div style="font-weight: 600; color: var(--dark);">
                                            #DIST<?php echo str_pad($distribution_id, 6, '0', STR_PAD_LEFT); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div style="display: flex; flex-wrap: wrap; gap: 5px;">
                                            <?php 
                                            $displayed = 0;
                                            foreach ($assigned_vols as $vol_id) {
                                                foreach ($all_volunteers as $vol) {
                                                    if ($vol['volunteer_id'] == $vol_id) {
                                                        $initial = strtoupper(substr($vol['name'], 0, 1));
                                                        echo '<div title="' . htmlspecialchars($vol['name']) . '" style="width: 30px; height: 30px; background: linear-gradient(135deg, var(--primary), var(--secondary)); color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 0.8rem;">' . $initial . '</div>';
                                                        $displayed++;
                                                        break;
                                                    }
                                                }
                                                if ($displayed >= 5) {
                                                    echo '<div style="width: 30px; height: 30px; background: var(--light-gray); color: var(--gray); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 0.8rem;">+' . (count($assigned_vols) - 5) . '</div>';
                                                    break;
                                                }
                                            }
                                            ?>
                                        </div>
                                        <div style="font-size: 0.8rem; color: var(--gray); margin-top: 5px;">
                                            <?php echo count($assigned_vols); ?> volunteer(s) assigned
                                        </div>
                                    </td>
                                    <td>
                                        <span style="background: <?php echo $distribution['status'] == 'In Progress' ? '#d4edda' : '#fff3cd'; ?>; color: <?php echo $distribution['status'] == 'In Progress' ? '#155724' : '#856404'; ?>; padding: 5px 12px; border-radius: 20px; font-size: 0.8rem; font-weight: 600;">
                                            <?php echo $distribution['status']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php echo date('d/m/Y', strtotime($distribution['date'])); ?>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($distribution['location'] ?? 'N/A'); ?>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <button class="action-btn view" onclick="viewDistribution(<?php echo $distribution_id; ?>)">
                                                <i class="fas fa-eye"></i> View
                                            </button>
                                            <a href="assign_volunteer.php?distribution_id=<?php echo $distribution_id; ?>" class="action-btn edit">
                                                <i class="fas fa-user-plus"></i> Manage
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endif; ?>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Status Update Modal -->
    <div id="statusModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
        <div style="background: white; border-radius: var(--border-radius); padding: 30px; max-width: 500px; width: 90%; box-shadow: var(--box-shadow);">
            <h2 style="margin: 0 0 20px 0; color: var(--dark);">
                <i class="fas fa-user-edit"></i>
                Update Volunteer Status
            </h2>
            
            <form method="POST" id="statusForm">
                <input type="hidden" name="update_status" value="1">
                <input type="hidden" id="modalVolunteerId" name="volunteer_id">
                
                <div class="filter-group">
                    <label class="filter-label">New Status</label>
                    <select name="new_status" class="filter-select" required>
                        <option value="Available">Available</option>
                        <option value="Busy">Busy</option>
                        <option value="Unavailable">Unavailable</option>
                        <option value="On Leave">On Leave</option>
                        <option value="Active">Active</option>
                        <option value="Inactive">Inactive</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label class="filter-label">Notes (Optional)</label>
                    <textarea name="status_notes" class="filter-select" rows="3" placeholder="Reason for status change..."></textarea>
                </div>
                
                <div style="display: flex; gap: 15px; margin-top: 25px;">
                    <button type="button" class="btn btn-outline" onclick="closeStatusModal()" style="flex: 1;">
                        Cancel
                    </button>
                    <button type="submit" class="btn btn-primary" style="flex: 1;">
                        Update Status
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Assignments Modal -->
    <div id="assignmentsModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
        <div style="background: white; border-radius: var(--border-radius); padding: 30px; max-width: 700px; width: 90%; max-height: 80vh; overflow-y: auto; box-shadow: var(--box-shadow);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
                <h2 style="margin: 0; color: var(--dark);">
                    <i class="fas fa-tasks"></i>
                    Volunteer Assignments
                </h2>
                <button onclick="closeAssignmentsModal()" style="background: none; border: none; font-size: 1.5rem; color: var(--gray); cursor: pointer;">×</button>
            </div>
            
            <div id="assignmentsList"></div>
        </div>
    </div>

    <script>
        // Volunteer selection
        let selectedVolunteers = new Set();
        
        function updateSelectedCount() {
            const checkboxes = document.querySelectorAll('.volunteer-checkbox:checked');
            const count = checkboxes.length;
            document.getElementById('selectedCount').textContent = count + ' selected';
            document.getElementById('assignButton').disabled = count === 0;
            
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
        }
        
        // Select all volunteers
        document.getElementById('select-all-volunteers')?.addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.volunteer-checkbox');
            checkboxes.forEach(cb => {
                cb.checked = this.checked;
            });
            updateSelectedCount();
        });
        
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
            
            if (!confirm(`Assign ${count} volunteer(s) to this distribution?`)) {
                e.preventDefault();
            }
        });
        
        // Status modal
        let statusModal = document.getElementById('statusModal');
        
        function editVolunteerStatus(volunteerId, currentStatus) {
            document.getElementById('modalVolunteerId').value = volunteerId;
            document.querySelector('[name="new_status"]').value = currentStatus;
            statusModal.style.display = 'flex';
        }
        
        function closeStatusModal() {
            statusModal.style.display = 'none';
        }
        
        // Assignments modal
        let assignmentsModal = document.getElementById('assignmentsModal');
        
        function viewAssignments(volunteerId) {
            fetch('get_volunteer_assignments.php?volunteer_id=' + volunteerId)
                .then(response => response.json())
                .then(data => {
                    let html = '';
                    
                    if (data.assignments && data.assignments.length > 0) {
                        html += '<table class="data-table" style="width: 100%; margin-bottom: 20px;">';
                        html += '<thead><tr><th>Distribution</th><th>Role</th><th>Status</th><th>Date</th><th>Actions</th></tr></thead><tbody>';
                        
                        data.assignments.forEach(assignment => {
                            html += '<tr>';
                            html += '<td>#DIST' + assignment.distribution_id.toString().padStart(6, '0') + '</td>';
                            html += '<td>' + (assignment.role || 'Volunteer') + '</td>';
                            html += '<td><span style="background: #d4edda; color: #155724; padding: 3px 10px; border-radius: 20px; font-size: 0.8rem; font-weight: 600;">' + assignment.status + '</span></td>';
                            html += '<td>' + new Date(assignment.date).toLocaleDateString() + '</td>';
                            html += '<td>';
                            html += '<form method="POST" style="display: inline;" onsubmit="return confirm(\'Remove volunteer from this assignment?\')">';
                            html += '<input type="hidden" name="remove_assignment" value="1">';
                            html += '<input type="hidden" name="volunteer_id" value="' + volunteerId + '">';
                            html += '<input type="hidden" name="distribution_id" value="' + assignment.distribution_id + '">';
                            html += '<button type="submit" class="action-btn remove" style="padding: 5px 10px; font-size: 0.8rem;">';
                            html += '<i class="fas fa-times"></i> Remove';
                            html += '</button>';
                            html += '</form>';
                            html += '</td>';
                            html += '</tr>';
                        });
                        
                        html += '</tbody></table>';
                    } else {
                        html += '<div class="empty-state" style="padding: 20px;">';
                        html += '<i class="fas fa-clipboard-list" style="font-size: 3rem;"></i>';
                        html += '<p>No active assignments found.</p>';
                        html += '</div>';
                    }
                    
                    document.getElementById('assignmentsList').innerHTML = html;
                    assignmentsModal.style.display = 'flex';
                })
                .catch(error => {
                    console.error('Error fetching assignments:', error);
                    document.getElementById('assignmentsList').innerHTML = '<div class="message error">Error loading assignments.</div>';
                    assignmentsModal.style.display = 'flex';
                });
        }
        
        function closeAssignmentsModal() {
            assignmentsModal.style.display = 'none';
        }
        
        // View volunteer details
        function viewVolunteerDetails(volunteerId) {
            window.open('view_volunteer.php?id=' + volunteerId, '_blank');
        }
        
        // View distribution details
        function viewDistribution(distributionId) {
            window.location.href = 'view_distribution.php?id=' + distributionId;
        }
        
        // Export volunteers
        function exportVolunteers() {
            let csvContent = "data:text/csv;charset=utf-8,";
            csvContent += "ID,Name,Email,Phone,Skill,NGO,Status,Availability,Total Assignments,Families Helped,Items Distributed\n";
            
            <?php foreach ($filtered_volunteers as $volunteer): 
                $stats = $volunteer_stats[$volunteer['volunteer_id']] ?? ['total_assignments' => 0, 'families_helped' => 0, 'items_distributed' => 0];
            ?>
            csvContent += "<?php 
                echo $volunteer['volunteer_id'] . ',' . 
                     str_replace(',', ' ', addslashes($volunteer['name'])) . ',' . 
                     $volunteer['email'] . ',' . 
                     $volunteer['phone'] . ',' . 
                     str_replace(',', ' ', addslashes($volunteer['skill_category'])) . ',' . 
                     str_replace(',', ' ', addslashes($volunteer['ngo_affiliation'])) . ',' . 
                     $volunteer['availability_status'] . ',' . 
                     $volunteer['availability'] . ',' . 
                     $stats['total_assignments'] . ',' . 
                     $stats['families_helped'] . ',' . 
                     $stats['items_distributed']; 
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
        
        // Close modals when clicking outside
        window.addEventListener('click', function(event) {
            if (event.target === statusModal) {
                closeStatusModal();
            }
            if (event.target === assignmentsModal) {
                closeAssignmentsModal();
            }
        });
        
        // Initialize
        updateSelectedCount();
    </script>
</body>
</html>