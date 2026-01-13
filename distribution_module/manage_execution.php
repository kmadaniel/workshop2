<?php
// manage_execution.php
// Volunteer Distribution & Execution Management Dashboard
session_start();
require_once 'config.php';

// Check if user is coordinator/admin
if (!isset($_SESSION['user_role']) || ($_SESSION['user_role'] !== 'coordinator' && $_SESSION['user_role'] !== 'admin')) {
    header("Location: http://10.147.17.30:8000/login.php");
    exit;
}

$database = new Database();
$db = $database->getConnection();

// Set SQL mode to fix GROUP BY issue
$db->query("SET SESSION sql_mode=(SELECT REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY',''))");

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

$create_coordinator_alerts_table = "
    CREATE TABLE IF NOT EXISTS coordinator_alerts (
        alert_id INT PRIMARY KEY AUTO_INCREMENT,
        distribution_id INT NOT NULL,
        message TEXT NOT NULL,
        alert_type ENUM('volunteer_cancelled', 'status_change', 'new_volunteer', 'system') DEFAULT 'system',
        is_read BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (distribution_id),
        INDEX (is_read),
        INDEX (created_at)
    )
";

// Create tracking table if it doesn't exist
$create_tracking_table = "
    CREATE TABLE IF NOT EXISTS distribution_tracking (
        tracking_id INT PRIMARY KEY AUTO_INCREMENT,
        distribution_id INT NOT NULL,
        volunteer_id INT NOT NULL,
        victim_id INT DEFAULT NULL,
        status ENUM('departed', 'in_transit', 'arrived', 'delayed', 'completed', 'cancelled') DEFAULT 'departed',
        current_location VARCHAR(255) NOT NULL,
        tracking_notes TEXT,
        estimated_arrival TIME DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (distribution_id),
        INDEX (volunteer_id),
        INDEX (status),
        INDEX (created_at)
    )
";

// Create coordinator_messages table if it doesn't exist
$create_messages_table = "
    CREATE TABLE IF NOT EXISTS coordinator_messages (
        message_id INT PRIMARY KEY AUTO_INCREMENT,
        distribution_id INT NOT NULL,
        volunteer_id INT NOT NULL,
        message TEXT NOT NULL,
        message_type ENUM('general', 'urgent', 'update', 'reminder', 'instructions') DEFAULT 'general',
        created_by VARCHAR(100),
        is_read BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (distribution_id),
        INDEX (volunteer_id),
        INDEX (is_read)
    )
";

$db->query($create_cancellations_table);
$db->query($create_coordinator_alerts_table);
$db->query($create_tracking_table);
$db->query($create_messages_table);

// ========================================
// API URLs
// ========================================
$VOLUNTEER_API_URL = 'http://10.147.17.30:8000/api_volunteer.php';
$DISASTER_API_URL = 'http://10.147.17.116:8000/disaster.php';
$VICTIM_API_URL = 'http://10.147.17.116:8000/victim.php';
$NEEDS_API_URL = 'http://10.147.17.116:8000/needs.php';

// ========================================
// FETCH DATA FROM APIS
// ========================================
function fetchFromAPI($url) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HTTPHEADER => ['Accept: application/json']
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if (is_resource($ch)) {
        curl_close($ch);
    }
    
    if ($httpCode === 200) {
        $data = json_decode($response, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $data;
        }
    }
    
    return null;
}

// Fetch all data from APIs
$volunteers_data = fetchFromAPI($VOLUNTEER_API_URL);
$api_volunteers = is_array($volunteers_data) ? $volunteers_data : [];

$disasters_data = fetchFromAPI($DISASTER_API_URL);
$disasters = is_array($disasters_data) ? $disasters_data : [];

$victims_data = fetchFromAPI($VICTIM_API_URL);
$api_victims = is_array($victims_data) ? $victims_data : [];

$needs_data = fetchFromAPI($NEEDS_API_URL);
$api_needs = isset($needs_data['data']) && is_array($needs_data['data']) ? $needs_data['data'] : (is_array($needs_data) ? $needs_data : []);

// ========================================
// CREATE LOOKUP ARRAYS FOR FAST ACCESS
// ========================================
$volunteer_lookup = [];
foreach ($api_volunteers as $volunteer) {
    if (isset($volunteer['VolunteerID'])) {
        $volunteer_lookup[$volunteer['VolunteerID']] = $volunteer;
    } elseif (isset($volunteer['volunteer_id'])) {
        $volunteer_lookup[$volunteer['volunteer_id']] = $volunteer;
    } elseif (isset($volunteer['id'])) {
        $volunteer_lookup[$volunteer['id']] = $volunteer;
    }
}

$victim_lookup = [];
foreach ($api_victims as $victim) {
    if (isset($victim['victim_id'])) {
        $victim_lookup[$victim['victim_id']] = $victim;
    } elseif (isset($victim['id'])) {
        $victim_lookup[$victim['id']] = $victim;
    }
}

$needs_lookup = [];
foreach ($api_needs as $need) {
    if (isset($need['need_id'])) {
        $needs_lookup[$need['need_id']] = $need;
    } elseif (isset($need['id'])) {
        $needs_lookup[$need['id']] = $need;
    }
}

// ========================================
// GET ALL DISTRIBUTION ASSIGNMENTS
// ========================================
$assignments_query = "
    SELECT 
        d.distribution_id,
        d.disaster_id,
        d.date,
        d.location,
        d.status as distribution_status,
        d.coordinator_name,
        d.coordinator_contact,
        d.volunteers_needed,
        d.estimated_duration,
        COUNT(DISTINCT dv.volunteer_id) as assigned_volunteers
    FROM distribution d
    LEFT JOIN distribution_volunteer dv ON d.distribution_id = dv.distribution_id 
        AND dv.status NOT IN ('Cancelled', 'Declined')
        AND dv.distribution_id NOT IN (
            SELECT distribution_id FROM assignment_cancellations WHERE volunteer_id = dv.volunteer_id
        )
    WHERE d.status NOT IN ('Completed', 'Cancelled')
    GROUP BY d.distribution_id
    ORDER BY d.date DESC, d.distribution_id DESC
";

$assignments = [];
if ($result = $db->query($assignments_query)) {
    $assignments = $result->fetch_all(MYSQLI_ASSOC);
}

// ========================================
// GET REAL-TIME TRACKING DATA FROM VOLUNTEERS
// ========================================
$tracking_query = "
    SELECT 
        dt.*,
        d.disaster_id,
        d.date as distribution_date,
        d.location as distribution_location,
        d.coordinator_name,
        dv.status as volunteer_assignment_status
    FROM distribution_tracking dt
    JOIN distribution d ON dt.distribution_id = d.distribution_id
    LEFT JOIN distribution_volunteer dv ON dt.distribution_id = dv.distribution_id 
        AND dt.volunteer_id = dv.volunteer_id
        AND dv.status NOT IN ('Cancelled', 'Declined')
    WHERE dt.created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
        AND dt.distribution_id NOT IN (
            SELECT distribution_id FROM assignment_cancellations WHERE volunteer_id = dt.volunteer_id
        )
    ORDER BY dt.created_at DESC
    LIMIT 100
";

$tracking_data = [];
if ($result = $db->query($tracking_query)) {
    $tracking_data = $result->fetch_all(MYSQLI_ASSOC);
}

// ========================================
// GET DISTRIBUTION EXECUTION DATA WITH REAL STATUS
// ========================================
$execution_query = "
    SELECT 
        dl.*,
        d.disaster_id,
        d.date as distribution_date,
        d.location as distribution_location,
        d.coordinator_name,
        d.status as overall_distribution_status,
        COALESCE(
            dt.status, 
            dl.status, 
            dv.status,
            'pending'
        ) as real_status,
        dt.current_location as latest_location,
        dt.created_at as last_tracking_update
    FROM distribution_log dl
    LEFT JOIN distribution d ON dl.distribution_id = d.distribution_id
    LEFT JOIN distribution_volunteer dv ON dl.distribution_id = dv.distribution_id 
        AND dl.volunteer_id = dv.volunteer_id
    LEFT JOIN (
        SELECT distribution_id, volunteer_id, status, current_location, MAX(created_at) as created_at
        FROM distribution_tracking
        GROUP BY distribution_id, volunteer_id
    ) dt ON dl.distribution_id = dt.distribution_id AND dl.volunteer_id = dt.volunteer_id
    WHERE dl.distribution_id NOT IN (
        SELECT distribution_id FROM assignment_cancellations WHERE volunteer_id = dl.volunteer_id
    )
    ORDER BY dl.created_at DESC
    LIMIT 100
";

$executions = [];
if ($result = $db->query($execution_query)) {
    $executions = $result->fetch_all(MYSQLI_ASSOC);
    
    // Enhance execution data with API information
    foreach ($executions as &$exec) {
        // Use the REAL status from the query
        $real_status = $exec['real_status'] ?? $exec['status'] ?? 'pending';
        
        // 1. Get VOLUNTEER info from API
        $volunteer_name = 'Unknown Volunteer';
        $volunteer_contact = 'N/A';
        
        if (isset($volunteer_lookup[$exec['volunteer_id']])) {
            $volunteer = $volunteer_lookup[$exec['volunteer_id']];
            $volunteer_name = $volunteer['FullName'] ?? 
                             $volunteer['full_name'] ?? 
                             $volunteer['name'] ?? 
                             'Volunteer ' . $exec['volunteer_id'];
            $volunteer_contact = $volunteer['Phone'] ?? 
                                $volunteer['phone'] ?? 
                                $volunteer['Email'] ?? 
                                $volunteer['email'] ?? 
                                'N/A';
        }
        
        // 2. Get VICTIM info from API
        $victim_name = 'Unknown Victim';
        $victim_contact = 'N/A';
        $family_size = 1;
        
        if (isset($victim_lookup[$exec['victim_id']])) {
            $victim = $victim_lookup[$exec['victim_id']];
            $victim_name = $victim['full_name'] ?? 
                          $victim['name'] ?? 
                          'Victim ' . $exec['victim_id'];
            $victim_contact = $victim['phone'] ?? 
                            $victim['email'] ?? 
                            'N/A';
            $family_size = intval($victim['family_members'] ?? 1);
        }
        
        // 3. Get NEEDS/ITEM info from API
        $resource_name = 'Unknown Item';
        $quantity_needed = 1;
        
        if (isset($needs_lookup[$exec['need_id']])) {
            $need = $needs_lookup[$exec['need_id']];
            $resource_name = $need['temp_resource_name'] ?? 
                           $need['resource_name'] ?? 
                           'Item';
            $quantity_needed = $need['quantity_needed'] ?? 1;
        }
        
        // 4. Format last update time
        $last_update_formatted = '';
        if (!empty($exec['last_tracking_update'])) {
            $last_update = new DateTime($exec['last_tracking_update']);
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
            
            $last_update_formatted = date('h:i A', strtotime($exec['last_tracking_update'])) . ' (' . $time_ago . ')';
        }
        
        // Update the execution record
        $exec['status'] = $real_status;
        $exec['volunteer_name'] = $volunteer_name;
        $exec['volunteer_contact'] = $volunteer_contact;
        $exec['victim_name'] = $victim_name;
        $exec['victim_contact'] = $victim_contact;
        $exec['family_size'] = $family_size;
        $exec['resource_name'] = $resource_name;
        $exec['quantity_needed'] = $quantity_needed;
        $exec['unit'] = 'units';
        $exec['last_update_formatted'] = $last_update_formatted;
        $exec['latest_location'] = $exec['latest_location'] ?? 'N/A';
    }
    unset($exec);
}

// ========================================
// GET STATISTICS - UPDATED TO EXCLUDE CANCELLED VOLUNTEERS
// ========================================
$stats_query = "
    SELECT 
        (SELECT COUNT(DISTINCT assigned_volunteer_id) FROM distribution_items WHERE assigned_volunteer_id IS NOT NULL AND distribution_id NOT IN (
            SELECT distribution_id FROM assignment_cancellations WHERE volunteer_id = assigned_volunteer_id
        )) as active_volunteers,
        (SELECT COUNT(*) FROM distribution WHERE status IN ('In Transit', 'In Progress', 'Assigned', 'Active')) as active_distributions,
        (SELECT COUNT(DISTINCT dt.volunteer_id) FROM distribution_tracking dt WHERE dt.created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)) as active_trackers,
        (SELECT COUNT(DISTINCT victim_id) FROM distribution_log WHERE status = 'completed' AND distribution_id NOT IN (
            SELECT distribution_id FROM assignment_cancellations WHERE volunteer_id = volunteer_id
        )) as families_served,
        (SELECT COUNT(DISTINCT need_id) FROM distribution_log WHERE status = 'completed' AND distribution_id NOT IN (
            SELECT distribution_id FROM assignment_cancellations WHERE volunteer_id = volunteer_id
        )) as items_delivered,
        (SELECT COUNT(DISTINCT distribution_id) FROM distribution_items WHERE assigned_volunteer_id IS NULL AND status = 'Scheduled') as pending_assignments,
        (SELECT COUNT(*) FROM distribution WHERE volunteers_needed > 0 AND status != 'Completed') as need_volunteers,
        (SELECT COUNT(*) FROM distribution WHERE status = 'Completed') as completed_distributions,
        (SELECT COUNT(*) FROM distribution_tracking WHERE status = 'arrived' AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)) as arrived_today,
        (SELECT COUNT(*) FROM distribution_tracking WHERE status = 'delayed' AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)) as delayed_today
";

$stats = [];
if ($result = $db->query($stats_query)) {
    $stats = $result->fetch_assoc();
}

// ========================================
// GET ALL UNIQUE VOLUNTEER IDs FOR FILTER
// ========================================
$volunteer_ids = [];

$volunteer_items_query = "
    SELECT DISTINCT assigned_volunteer_id as volunteer_id 
    FROM distribution_items 
    WHERE assigned_volunteer_id IS NOT NULL
    AND distribution_id NOT IN (
        SELECT distribution_id FROM assignment_cancellations WHERE volunteer_id = assigned_volunteer_id
    )
    ORDER BY assigned_volunteer_id
";
if ($result = $db->query($volunteer_items_query)) {
    while ($row = $result->fetch_assoc()) {
        $volunteer_ids[$row['volunteer_id']] = $row['volunteer_id'];
    }
}

$volunteer_log_query = "
    SELECT DISTINCT volunteer_id 
    FROM distribution_log 
    WHERE volunteer_id IS NOT NULL
    AND distribution_id NOT IN (
        SELECT distribution_id FROM assignment_cancellations WHERE volunteer_id = volunteer_id
    )
    ORDER BY volunteer_id
";
if ($result = $db->query($volunteer_log_query)) {
    while ($row = $result->fetch_assoc()) {
        $volunteer_ids[$row['volunteer_id']] = $row['volunteer_id'];
    }
}

// Get volunteer names from API for the IDs we have
$db_volunteers = [];
foreach ($volunteer_ids as $volunteer_id) {
    if (isset($volunteer_lookup[$volunteer_id])) {
        $volunteer = $volunteer_lookup[$volunteer_id];
        $db_volunteers[] = [
            'volunteer_id' => $volunteer_id,
            'name' => $volunteer['FullName'] ?? 
                     $volunteer['full_name'] ?? 
                     $volunteer['name'] ?? 
                     "Volunteer $volunteer_id",
            'phone' => $volunteer['Phone'] ?? 
                      $volunteer['phone'] ?? '',
            'email' => $volunteer['Email'] ?? 
                      $volunteer['email'] ?? ''
        ];
    } else {
        $db_volunteers[] = [
            'volunteer_id' => $volunteer_id,
            'name' => "Volunteer $volunteer_id",
            'phone' => '',
            'email' => ''
        ];
    }
}

// ========================================
// GET COORDINATOR ALERTS
// ========================================
$coordinator_alerts = [];
$alerts_query = "SELECT * FROM coordinator_alerts WHERE is_read = 0 ORDER BY created_at DESC LIMIT 10";
if ($result = $db->query($alerts_query)) {
    $coordinator_alerts = $result->fetch_all(MYSQLI_ASSOC);
}

// Mark alerts as read when viewed
if (!empty($coordinator_alerts)) {
    $mark_read_query = "UPDATE coordinator_alerts SET is_read = 1 WHERE is_read = 0";
    $db->query($mark_read_query);
}

// ========================================
// CHECK FOR NEW TRACKING UPDATES (AJAX ENDPOINT)
// ========================================
if (isset($_GET['check_updates']) && $_GET['check_updates'] == '1') {
    $last_check = $_GET['last_check'] ?? '0';
    
    // Check for new tracking updates
    $updates_query = "
        SELECT COUNT(*) as new_updates 
        FROM distribution_tracking 
        WHERE created_at > FROM_UNIXTIME(?) 
        AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
    ";
    
    $stmt = $db->prepare($updates_query);
    $stmt->bind_param("i", $last_check);
    $stmt->execute();
    $result = $stmt->get_result();
    $updates = $result->fetch_assoc();
    $stmt->close();
    
    // Check for cancellations
    $cancellations_query = "
        SELECT COUNT(*) as new_cancellations 
        FROM assignment_cancellations 
        WHERE created_at > FROM_UNIXTIME(?)
    ";
    
    $stmt = $db->prepare($cancellations_query);
    $stmt->bind_param("i", $last_check);
    $stmt->execute();
    $result = $stmt->get_result();
    $cancellations = $result->fetch_assoc();
    $stmt->close();
    
    header('Content-Type: application/json');
    echo json_encode([
        'new_updates' => $updates['new_updates'] ?? 0,
        'new_cancellations' => $cancellations['new_cancellations'] ?? 0,
        'current_time' => time()
    ]);
    exit;
}

// ========================================
// FILTER OPTIONS
// ========================================
$filter_disaster = $_GET['disaster_id'] ?? '';
$filter_status = $_GET['status'] ?? '';
$filter_date = $_GET['date'] ?? '';
$filter_volunteer = $_GET['volunteer_id'] ?? '';

// ========================================
// HANDLE ACTIONS
// ========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $distribution_id = $_POST['distribution_id'] ?? 0;
    $volunteer_id = $_POST['volunteer_id'] ?? 0;
    
    if ($action === 'reassign_volunteer' && $distribution_id && $volunteer_id) {
        $new_volunteer_id = $_POST['new_volunteer_id'] ?? 0;
        
        if ($new_volunteer_id) {
            $update_query = "UPDATE distribution_items SET assigned_volunteer_id = ? WHERE distribution_id = ? AND assigned_volunteer_id = ?";
            $stmt = $db->prepare($update_query);
            $stmt->bind_param("iii", $new_volunteer_id, $distribution_id, $volunteer_id);
            $stmt->execute();
            $stmt->close();
            
            // Create alert
            $alert_message = "Volunteer reassigned: Volunteer {$volunteer_id} replaced with Volunteer {$new_volunteer_id} in Distribution {$distribution_id}";
            $alert_query = "INSERT INTO coordinator_alerts (distribution_id, message, alert_type) VALUES (?, ?, 'system')";
            $stmt = $db->prepare($alert_query);
            $stmt->bind_param("is", $distribution_id, $alert_message);
            $stmt->execute();
            $stmt->close();
            
            header("Location: manage_execution.php?success=reassigned");
            exit;
        }
    }
    
    if ($action === 'update_status' && $distribution_id) {
        $new_status = $_POST['new_status'] ?? '';
        $reason = $_POST['reason'] ?? '';
        
        if ($new_status) {
            $update_query = "UPDATE distribution SET status = ? WHERE distribution_id = ?";
            $stmt = $db->prepare($update_query);
            $stmt->bind_param("si", $new_status, $distribution_id);
            $stmt->execute();
            $stmt->close();
            
            // Create alert
            $alert_message = "Distribution {$distribution_id} status changed to {$new_status}" . ($reason ? " - Reason: {$reason}" : "");
            $alert_query = "INSERT INTO coordinator_alerts (distribution_id, message, alert_type) VALUES (?, ?, 'status_change')";
            $stmt = $db->prepare($alert_query);
            $stmt->bind_param("is", $distribution_id, $alert_message);
            $stmt->execute();
            $stmt->close();
            
            header("Location: manage_execution.php?success=status_updated");
            exit;
        }
    }
    
    if ($action === 'add_volunteer' && $distribution_id) {
        $volunteer_id = $_POST['volunteer_id'] ?? 0;
        
        if ($volunteer_id) {
            $check_items_query = "SELECT item_id FROM distribution_items WHERE distribution_id = ? LIMIT 1";
            $stmt = $db->prepare($check_items_query);
            $stmt->bind_param("i", $distribution_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $add_query = "UPDATE distribution_items SET assigned_volunteer_id = ? WHERE distribution_id = ? AND assigned_volunteer_id IS NULL LIMIT 1";
                $stmt = $db->prepare($add_query);
                $stmt->bind_param("ii", $volunteer_id, $distribution_id);
                $stmt->execute();
                
                if ($stmt->affected_rows > 0) {
                    $update_count_query = "UPDATE distribution SET volunteers_needed = GREATEST(0, volunteers_needed - 1) WHERE distribution_id = ?";
                    $stmt = $db->prepare($update_count_query);
                    $stmt->bind_param("i", $distribution_id);
                    $stmt->execute();
                    
                    // Create alert
                    $alert_message = "Volunteer {$volunteer_id} added to Distribution {$distribution_id}";
                    $alert_query = "INSERT INTO coordinator_alerts (distribution_id, message, alert_type) VALUES (?, ?, 'new_volunteer')";
                    $stmt = $db->prepare($alert_query);
                    $stmt->bind_param("is", $distribution_id, $alert_message);
                    $stmt->execute();
                    $stmt->close();
                    
                    header("Location: manage_execution.php?success=volunteer_added");
                    exit;
                }
            }
            
            $create_query = "INSERT INTO distribution_items (distribution_id, victim_id, status, assigned_volunteer_id) VALUES (?, 0, 'Scheduled', ?)";
            $stmt = $db->prepare($create_query);
            $stmt->bind_param("ii", $distribution_id, $volunteer_id);
            $stmt->execute();
            
            $update_count_query = "UPDATE distribution SET volunteers_needed = GREATEST(0, volunteers_needed - 1) WHERE distribution_id = ?";
            $stmt = $db->prepare($update_count_query);
            $stmt->bind_param("i", $distribution_id);
            $stmt->execute();
            
            // Create alert
            $alert_message = "Volunteer {$volunteer_id} added to Distribution {$distribution_id}";
            $alert_query = "INSERT INTO coordinator_alerts (distribution_id, message, alert_type) VALUES (?, ?, 'new_volunteer')";
            $stmt = $db->prepare($alert_query);
            $stmt->bind_param("is", $distribution_id, $alert_message);
            $stmt->execute();
            $stmt->close();
            
            header("Location: manage_execution.php?success=volunteer_added");
            exit;
        }
    }
    
    if ($action === 'send_message' && $distribution_id && $volunteer_id) {
        $message = $_POST['message'] ?? '';
        $message_type = $_POST['message_type'] ?? 'general';
        
        if (!empty($message)) {
            // Store message in database
            $message_query = "
                INSERT INTO coordinator_messages 
                (distribution_id, volunteer_id, message, message_type, created_by) 
                VALUES (?, ?, ?, ?, ?)
            ";
            $stmt = $db->prepare($message_query);
            $coordinator_name = $_SESSION['user_name'] ?? 'Coordinator';
            $stmt->bind_param("iisss", $distribution_id, $volunteer_id, $message, $message_type, $coordinator_name);
            $stmt->execute();
            $stmt->close();
            
            // Create alert
            $alert_message = "Message sent to Volunteer {$volunteer_id} in Distribution {$distribution_id}";
            $alert_query = "INSERT INTO coordinator_alerts (distribution_id, message, alert_type) VALUES (?, ?, 'system')";
            $stmt = $db->prepare($alert_query);
            $stmt->bind_param("is", $distribution_id, $alert_message);
            $stmt->execute();
            $stmt->close();
            
            header("Location: manage_execution.php?success=message_sent&distribution_id=" . $distribution_id);
            exit;
        }
    }
    
    if ($action === 'clear_alerts') {
        $clear_query = "UPDATE coordinator_alerts SET is_read = 1 WHERE is_read = 0";
        $db->query($clear_query);
        header("Location: manage_execution.php");
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Distribution Execution - JKM Melaka</title>
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
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .header {
            background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%);
            color: white;
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 30px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .header h1 {
            font-size: 32px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .header p {
            font-size: 16px;
            opacity: 0.9;
            max-width: 800px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 30px 0;
        }
        
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            text-align: center;
            transition: transform 0.3s ease;
            border-top: 4px solid #3498db;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-card:nth-child(2) { border-top-color: #2ecc71; }
        .stat-card:nth-child(3) { border-top-color: #9b59b6; }
        .stat-card:nth-child(4) { border-top-color: #e74c3c; }
        .stat-card:nth-child(5) { border-top-color: #f39c12; }
        .stat-card:nth-child(6) { border-top-color: #1abc9c; }
        .stat-card:nth-child(7) { border-top-color: #e67e22; }
        .stat-card:nth-child(8) { border-top-color: #27ae60; }
        .stat-card:nth-child(9) { border-top-color: #e74c3c; }
        
        .stat-number {
            font-size: 42px;
            font-weight: 700;
            margin-bottom: 10px;
        }
        
        .stat-label {
            color: #7f8c8d;
            font-size: 16px;
            font-weight: 500;
        }
        
        .filter-section {
            background: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }
        
        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .filter-group label {
            font-weight: 600;
            color: #2c3e50;
        }
        
        .filter-control {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            background: white;
        }
        
        .filter-btn {
            background: #3498db;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 20px;
        }
        
        .filter-btn:hover {
            background: #2980b9;
        }
        
        .reset-btn {
            background: #95a5a6;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 20px;
            margin-left: 10px;
        }
        
        .reset-btn:hover {
            background: #7f8c8d;
        }
        
        .tabs {
            display: flex;
            background: white;
            border-radius: 12px 12px 0 0;
            overflow: hidden;
            margin-top: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .tab {
            flex: 1;
            padding: 20px;
            text-align: center;
            font-weight: 600;
            color: #7f8c8d;
            cursor: pointer;
            transition: all 0.3s;
            border-bottom: 3px solid transparent;
        }
        
        .tab:hover {
            background: #f8f9fa;
            color: #3498db;
        }
        
        .tab.active {
            background: #f8f9fa;
            color: #3498db;
            border-bottom: 3px solid #3498db;
        }
        
        .tab-content {
            display: none;
            background: white;
            border-radius: 0 0 12px 12px;
            padding: 30px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        }
        
        .tab-content.active {
            display: block;
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        .data-table th {
            background: #f8f9fa;
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: #2c3e50;
            border-bottom: 2px solid #e1e5eb;
        }
        
        .data-table td {
            padding: 15px;
            border-bottom: 1px solid #eee;
            vertical-align: middle;
        }
        
        .data-table tr:hover {
            background: #f8f9fa;
        }
        
        .status-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-pending {
            background: #e1f5fe;
            color: #0288d1;
        }
        
        .status-assigned {
            background: #fff3e0;
            color: #ef6c00;
        }
        
        .status-in_transit, .status-in_progress {
            background: #e3f2fd;
            color: #1565c0;
        }
        
        .status-completed {
            background: #e8f5e9;
            color: #2e7d32;
        }
        
        .status-cancelled {
            background: #ffebee;
            color: #c62828;
        }
        
        .status-delivered {
            background: #c8e6c9;
            color: #1b5e20;
        }
        
        .status-dispatched {
            background: #fff3e0;
            color: #e65100;
        }
        
        .status-scheduled {
            background: #f5f5f5;
            color: #616161;
        }
        
        .status-departed {
            background: #d4f1f9;
            color: #0288d1;
        }
        
        .status-arrived {
            background: #e8f5e9;
            color: #2e7d32;
        }
        
        .status-delayed {
            background: #f8d7da;
            color: #c62828;
        }
        
        .status-active {
            background: #2ecc71;
            color: white;
        }
        
        .action-buttons {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .action-btn {
            padding: 6px 12px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            border: none;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .action-btn.view {
            background: #3498db;
            color: white;
        }
        
        .action-btn.view:hover {
            background: #2980b9;
        }
        
        .action-btn.edit {
            background: #f39c12;
            color: white;
        }
        
        .action-btn.edit:hover {
            background: #e67e22;
        }
        
        .action-btn.reassign {
            background: #9b59b6;
            color: white;
        }
        
        .action-btn.reassign:hover {
            background: #8e44ad;
        }
        
        .action-btn.status {
            background: #2ecc71;
            color: white;
        }
        
        .action-btn.status:hover {
            background: #27ae60;
        }
        
        .action-btn.track {
            background: #e67e22;
            color: white;
        }
        
        .action-btn.track:hover {
            background: #d35400;
        }
        
        .action-btn.add {
            background: #1abc9c;
            color: white;
        }
        
        .action-btn.add:hover {
            background: #16a085;
        }
        
        .action-btn.message {
            background: #8e44ad;
            color: white;
        }
        
        .action-btn.message:hover {
            background: #7d3c98;
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        
        .modal-content {
            background: white;
            border-radius: 12px;
            padding: 30px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
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
        
        .modal-btn {
            padding: 10px 20px;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            border: none;
        }
        
        .modal-btn.primary {
            background: #3498db;
            color: white;
        }
        
        .modal-btn.primary:hover {
            background: #2980b9;
        }
        
        .modal-btn.secondary {
            background: #95a5a6;
            color: white;
        }
        
        .modal-btn.secondary:hover {
            background: #7f8c8d;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #2c3e50;
        }
        
        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
        }
        
        .success-message {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            border-left: 4px solid #2ecc71;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .alert-message {
            background: #fff3cd;
            color: #856404;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            border-left: 4px solid #ffc107;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #95a5a6;
        }
        
        .empty-state i {
            font-size: 60px;
            margin-bottom: 20px;
            color: #ecf0f1;
        }
        
        .empty-state h3 {
            font-size: 22px;
            margin-bottom: 10px;
            color: #7f8c8d;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 30px;
        }
        
        .page-btn {
            padding: 8px 16px;
            border: 1px solid #ddd;
            border-radius: 4px;
            background: white;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .page-btn:hover {
            background: #f8f9fa;
            border-color: #3498db;
        }
        
        .page-btn.active {
            background: #3498db;
            color: white;
            border-color: #3498db;
        }
        
        .search-box {
            position: relative;
            margin-bottom: 20px;
        }
        
        .search-box input {
            width: 100%;
            padding: 12px 45px 12px 15px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
        }
        
        .search-box i {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #7f8c8d;
        }
        
        .export-buttons {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .export-btn {
            padding: 10px 20px;
            border: 1px solid #3498db;
            border-radius: 6px;
            background: white;
            color: #3498db;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }
        
        .export-btn:hover {
            background: #3498db;
            color: white;
        }
        
        /* NEW STYLES FOR TRACKING */
        .tracking-card {
            transition: all 0.3s ease;
        }
        
        .tracking-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .quick-message-btn {
            background: #e8f4fc;
            border: 1px solid #3498db;
            color: #3498db;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 12px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .quick-message-btn:hover {
            background: #3498db;
            color: white;
        }
        
        /* Status colors for tracking */
        .status-departed { background: #e1f5fe; color: #0288d1; }
        .status-in_transit { background: #fff3e0; color: #ef6c00; }
        .status-arrived { background: #e8f5e9; color: #2e7d32; }
        .status-delayed { background: #ffebee; color: #c62828; }
        
        /* Notification badge styles */
        .notification-badge {
            position: fixed;
            top: 80px;
            right: 20px;
            background: #ff7675;
            color: white;
            padding: 15px 20px;
            border-radius: 8px;
            z-index: 1000;
            box-shadow: 0 4px 12px rgba(255, 118, 117, 0.3);
            animation: slideIn 0.5s ease;
            max-width: 400px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .notification-badge .close-btn {
            background: rgba(255,255,255,0.2);
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
        
        /* Real-time update indicator */
        .live-indicator {
            display: inline-block;
            width: 10px;
            height: 10px;
            background-color: #2ecc71;
            border-radius: 50%;
            margin-right: 5px;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }
        
        /* Update notification */
        .update-notification {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: #3498db;
            color: white;
            padding: 15px 20px;
            border-radius: 8px;
            z-index: 1000;
            box-shadow: 0 4px 12px rgba(52, 152, 219, 0.3);
            display: none;
            align-items: center;
            gap: 10px;
        }
        
        .update-notification.show {
            display: flex;
            animation: slideInUp 0.5s ease;
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
        
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .filter-grid {
                grid-template-columns: 1fr;
            }
            
            .data-table {
                display: block;
                overflow-x: auto;
            }
            
            .tabs {
                flex-direction: column;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .action-btn {
                width: 100%;
                justify-content: center;
            }
            
            .notification-badge {
                left: 20px;
                right: 20px;
                max-width: none;
            }
        }
        
        @media (max-width: 576px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .header h1 {
                font-size: 24px;
            }
            
            .stat-number {
                font-size: 32px;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Header -->
        <div class="header">
            <h1>
                <i class="fas fa-tasks"></i>
                Manage Distribution Execution
            </h1>
            <p>Monitor and manage volunteer assignments, distribution progress, and execution tracking in real-time.</p>
            <div style="margin-top: 20px; display: flex; gap: 10px; flex-wrap: wrap;">
                <a href="distribution_main.php" style="background: white; color: #3498db; padding: 10px 20px; border-radius: 6px; text-decoration: none; font-weight: 600; display: flex; align-items: center; gap: 8px;">
                    <i class="fas fa-list"></i> Distribution Overview
                </a>
                <a href="manage_volunteer.php" style="background: rgba(255,255,255,0.2); color: white; padding: 10px 20px; border-radius: 6px; text-decoration: none; font-weight: 600; display: flex; align-items: center; gap: 8px;">
                    <i class="fas fa-user"></i> Volunteer Distribution
                </a>
                <a href="manage_coordinators.php" style="background: rgba(255,255,255,0.2); color: white; padding: 10px 20px; border-radius: 6px; text-decoration: none; font-weight: 600; display: flex; align-items: center; gap: 8px;">
                    <i class="fas fa-chart-line"></i> Coordinator Dashboard
                </a>
            </div>
        </div>
        
        <!-- Success Message -->
        <?php if (isset($_GET['success'])): ?>
            <div class="success-message">
                <i class="fas fa-check-circle"></i>
                <?php if ($_GET['success'] == 'reassigned'): ?>
                    Volunteer reassigned successfully!
                <?php elseif ($_GET['success'] == 'status_updated'): ?>
                    Status updated successfully!
                <?php elseif ($_GET['success'] == 'volunteer_added'): ?>
                    Volunteer added to distribution!
                <?php elseif ($_GET['success'] == 'message_sent'): ?>
                    Message sent to volunteer!
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <!-- Coordinator Alerts -->
        <?php if (!empty($coordinator_alerts)): ?>
            <div class="alert-message">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                    <h4 style="margin: 0;"><i class="fas fa-bell"></i> Coordinator Alerts</h4>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="clear_alerts">
                        <button type="submit" style="background: none; border: none; color: #856404; cursor: pointer; font-size: 12px;">
                            <i class="fas fa-times"></i> Clear All
                        </button>
                    </form>
                </div>
                <?php foreach ($coordinator_alerts as $alert): ?>
                    <div style="padding: 8px; background: rgba(255, 193, 7, 0.1); border-radius: 4px; margin-bottom: 5px; font-size: 14px;">
                        <i class="fas fa-info-circle"></i> 
                        <?php echo htmlspecialchars($alert['message']); ?>
                        <span style="font-size: 12px; color: #856404; float: right;">
                            <?php echo date('h:i A', strtotime($alert['created_at'])); ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <!-- Real-time Update Notification -->
        <div class="update-notification" id="updateNotification">
            <i class="fas fa-sync-alt fa-spin"></i>
            <span id="updateMessage">New updates available</span>
            <button onclick="refreshPage()" style="background: white; color: #3498db; border: none; padding: 5px 10px; border-radius: 4px; cursor: pointer; margin-left: 10px;">
                Refresh
            </button>
        </div>
        
        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['active_volunteers'] ?? 0; ?></div>
                <div class="stat-label">Active Volunteers</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['active_distributions'] ?? 0; ?></div>
                <div class="stat-label">Active Distributions</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['active_trackers'] ?? 0; ?></div>
                <div class="stat-label">Live Trackers</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['families_served'] ?? 0; ?></div>
                <div class="stat-label">Families Served</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['items_delivered'] ?? 0; ?></div>
                <div class="stat-label">Items Delivered</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['pending_assignments'] ?? 0; ?></div>
                <div class="stat-label">Pending Assignments</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['need_volunteers'] ?? 0; ?></div>
                <div class="stat-label">Need Volunteers</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['arrived_today'] ?? 0; ?></div>
                <div class="stat-label">Arrived Today</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['delayed_today'] ?? 0; ?></div>
                <div class="stat-label">Delayed Today</div>
            </div>
        </div>
        
        <!-- Filter Section -->
        <div class="filter-section">
            <h3 style="color: #2c3e50; margin-bottom: 20px;">
                <i class="fas fa-filter"></i> Filter Data
            </h3>
            <form method="GET" id="filterForm">
                <div class="filter-grid">
                    <div class="filter-group">
                        <label for="disaster_id">Disaster</label>
                        <select id="disaster_id" name="disaster_id" class="filter-control">
                            <option value="">All Disasters</option>
                            <?php foreach ($disasters as $disaster): 
                                $disaster_id = $disaster['disaster_id'] ?? $disaster['id'] ?? $disaster['Disaster_ID'] ?? '';
                                $disaster_name = $disaster['Disaster_Name'] ?? $disaster['name'] ?? $disaster['disaster_name'] ?? 'Unknown';
                            ?>
                                <option value="<?php echo htmlspecialchars((string)$disaster_id); ?>" <?php echo $filter_disaster == $disaster_id ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars((string)$disaster_name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="status">Status</label>
                        <select id="status" name="status" class="filter-control">
                            <option value="">All Status</option>
                            <option value="pending" <?php echo $filter_status == 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="assigned" <?php echo $filter_status == 'assigned' ? 'selected' : ''; ?>>Assigned</option>
                            <option value="active" <?php echo $filter_status == 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="departed" <?php echo $filter_status == 'departed' ? 'selected' : ''; ?>>Departed</option>
                            <option value="in_transit" <?php echo $filter_status == 'in_transit' ? 'selected' : ''; ?>>In Transit</option>
                            <option value="arrived" <?php echo $filter_status == 'arrived' ? 'selected' : ''; ?>>Arrived</option>
                            <option value="delayed" <?php echo $filter_status == 'delayed' ? 'selected' : ''; ?>>Delayed</option>
                            <option value="completed" <?php echo $filter_status == 'completed' ? 'selected' : ''; ?>>Completed</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="date">Distribution Date</label>
                        <input type="date" id="date" name="date" class="filter-control" value="<?php echo htmlspecialchars((string)$filter_date); ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label for="volunteer_id">Volunteer</label>
                        <select id="volunteer_id" name="volunteer_id" class="filter-control">
                            <option value="">All Volunteers</option>
                            <?php foreach ($db_volunteers as $volunteer): ?>
                                <option value="<?php echo htmlspecialchars((string)$volunteer['volunteer_id']); ?>" <?php echo $filter_volunteer == $volunteer['volunteer_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars((string)$volunteer['name']); ?> (ID: <?php echo htmlspecialchars((string)$volunteer['volunteer_id']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div style="display: flex; gap: 10px;">
                    <button type="submit" class="filter-btn">
                        <i class="fas fa-search"></i> Apply Filters
                    </button>
                    <button type="button" class="reset-btn" onclick="resetFilters()">
                        <i class="fas fa-redo"></i> Reset Filters
                    </button>
                    <button type="button" class="filter-btn" onclick="checkForUpdates()" style="background: #2ecc71;">
                        <i class="fas fa-sync-alt"></i> Check Updates
                    </button>
                </div>
            </form>
        </div>
        
        <!-- Tabs -->
        <div class="tabs">
            <div class="tab active" onclick="showTab('execution')">
                <i class="fas fa-truck-loading"></i> Execution Log
            </div>
            <div class="tab" onclick="showTab('tracking')">
                <i class="fas fa-map-marked-alt"></i> Live Tracking
                <?php if ($stats['active_trackers'] > 0): ?>
                    <span style="background: #e74c3c; color: white; padding: 2px 6px; border-radius: 10px; font-size: 11px; margin-left: 5px;">
                        <?php echo $stats['active_trackers']; ?>
                    </span>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Tab Content: Execution Log -->
        <div id="execution-tab" class="tab-content active">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3 style="color: #2c3e50;">
                    <i class="fas fa-truck-loading"></i> Distribution Execution Log
                    <span style="font-size: 14px; color: #7f8c8d; font-weight: normal; margin-left: 10px;">
                        (<?php echo count($executions); ?> log entries) 
                        <span class="live-indicator"></span> Live Updates
                    </span>
                </h3>
                
                <div class="export-buttons">
                    <button class="export-btn" onclick="exportToCSV('execution')">
                        <i class="fas fa-file-csv"></i> Export CSV
                    </button>
                    <button class="export-btn" onclick="printTable('execution')">
                        <i class="fas fa-print"></i> Print
                    </button>
                </div>
            </div>
            
            <?php if (empty($executions)): ?>
                <div class="empty-state">
                    <i class="fas fa-history"></i>
                    <h3>No Execution Data</h3>
                    <p>No distribution execution logs available yet.</p>
                </div>
            <?php else: ?>
                <div class="search-box">
                    <input type="text" id="searchExecution" placeholder="Search execution logs..." onkeyup="searchTable('execution')">
                    <i class="fas fa-search"></i>
                </div>
                
                <div style="overflow-x: auto;">
                    <table class="data-table" id="executionTable">
                        <thead>
                            <tr>
                                <th>Timestamp</th>
                                <th>Distribution</th>
                                <th>Volunteer</th>
                                <th>Victim</th>
                                <th>Item</th>
                                <th>Quantity</th>
                                <th>Status</th>
                                <th>Location</th>
                                <th>Last Update</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($executions as $exec): 
                                $volunteer_name = $exec['volunteer_name'] ?? 'Volunteer ' . $exec['volunteer_id'];
                                $victim_name = $exec['victim_name'] ?? 'Unknown';
                                $resource_name = $exec['resource_name'] ?? 'Item';
                                $quantity = $exec['quantity_distributed'] ?? $exec['quantity_needed'] ?? 1;
                                $status = $exec['status'] ?? 'pending';
                                $status_class = strtolower(str_replace(' ', '_', $status));
                                $latest_location = $exec['latest_location'] ?? 'N/A';
                                $last_update = $exec['last_update_formatted'] ?? 'Never';
                            ?>
                            <tr>
                                <td><?php echo date('d/m/Y H:i', strtotime($exec['created_at'])); ?></td>
                                <td>
                                    <strong>DIST<?php echo str_pad($exec['distribution_id'], 6, '0', STR_PAD_LEFT); ?></strong>
                                    <div style="font-size: 12px; color: #7f8c8d;">
                                        <?php echo date('d/m/Y', strtotime($exec['distribution_date'])); ?>
                                    </div>
                                </td>
                                <td>
                                    <div><?php echo htmlspecialchars($volunteer_name); ?></div>
                                    <div style="font-size: 12px; color: #7f8c8d;">
                                        ID: VOL<?php echo str_pad($exec['volunteer_id'], 4, '0', STR_PAD_LEFT); ?>
                                    </div>
                                </td>
                                <td>
                                    <div><?php echo htmlspecialchars($victim_name); ?></div>
                                    <div style="font-size: 12px; color: #7f8c8d;">
                                        Family: <?php echo $exec['family_size'] ?? 1; ?>
                                    </div>
                                </td>
                                <td>
                                    <div><?php echo htmlspecialchars($resource_name); ?></div>
                                </td>
                                <td>
                                    <?php echo $quantity; ?> units
                                </td>
                                <td>
                                    <span class="status-badge status-<?php echo $status_class; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $status)); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($latest_location); ?>
                                </td>
                                <td>
                                    <?php echo $last_update; ?>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="action-btn track" 
                                                onclick="viewVolunteerTracking(<?php echo $exec['volunteer_id']; ?>, <?php echo $exec['distribution_id']; ?>)"
                                                title="View tracking">
                                            <i class="fas fa-map-marker-alt"></i>
                                        </button>
                                        <button class="action-btn message"
                                                onclick="sendMessage(<?php echo $exec['distribution_id']; ?>, <?php echo $exec['volunteer_id']; ?>, '<?php echo htmlspecialchars($volunteer_name); ?>')"
                                                title="Send message">
                                            <i class="fas fa-comment"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <div class="pagination">
                    <button class="page-btn active">1</button>
                    <button class="page-btn">2</button>
                    <button class="page-btn">3</button>
                    <button class="page-btn">4</button>
                    <button class="page-btn">5</button>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Tab Content: Live Tracking -->
        <div id="tracking-tab" class="tab-content">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3 style="color: #2c3e50;">
                    <i class="fas fa-map-marked-alt"></i> Live Tracking Dashboard
                    <span style="font-size: 14px; color: #7f8c8d; font-weight: normal; margin-left: 10px;">
                        (Real-time volunteer location tracking)
                    </span>
                </h3>
                
                <div style="display: flex; gap: 10px;">
                    <button class="export-btn" onclick="refreshTracking()">
                        <i class="fas fa-sync-alt"></i> Refresh
                    </button>
                </div>
            </div>
            
            <!-- Live Tracking Dashboard -->
            <div style="background: white; border-radius: 8px; padding: 20px; margin-bottom: 20px;">
                <h4 style="color: #2c3e50; margin-bottom: 15px;">
                    <i class="fas fa-tachometer-alt"></i> Tracking Overview
                </h4>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin-bottom: 20px;">
                    <div style="text-align: center; padding: 15px; background: #f8f9fa; border-radius: 6px;">
                        <div style="font-size: 24px; font-weight: bold; color: #3498db;">
                            <?php echo $stats['active_trackers'] ?? 0; ?>
                        </div>
                        <div style="font-size: 12px; color: #7f8c8d;">Active Trackers</div>
                    </div>
                    
                    <div style="text-align: center; padding: 15px; background: #f8f9fa; border-radius: 6px;">
                        <div style="font-size: 24px; font-weight: bold; color: #27ae60;">
                            <?php echo $stats['arrived_today'] ?? 0; ?>
                        </div>
                        <div style="font-size: 12px; color: #7f8c8d;">Arrived Today</div>
                    </div>
                    
                    <div style="text-align: center; padding: 15px; background: #f8f9fa; border-radius: 6px;">
                        <div style="font-size: 24px; font-weight: bold; color: #e67e22;">
                            <?php 
                            $transit_count = 0;
                            foreach ($tracking_data as $track) {
                                if ($track['status'] == 'in_transit' || $track['status'] == 'departed') {
                                    $transit_count++;
                                }
                            }
                            echo $transit_count;
                            ?>
                        </div>
                        <div style="font-size: 12px; color: #7f8c8d;">In Transit</div>
                    </div>
                    
                    <div style="text-align: center; padding: 15px; background: #f8f9fa; border-radius: 6px;">
                        <div style="font-size: 24px; font-weight: bold; color: #e74c3c;">
                            <?php echo $stats['delayed_today'] ?? 0; ?>
                        </div>
                        <div style="font-size: 12px; color: #7f8c8d;">Delayed Today</div>
                    </div>
                </div>
            </div>
            
            <!-- Filter Controls -->
            <div style="background: white; border-radius: 8px; padding: 15px; margin-bottom: 20px;">
                <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                    <select id="trackingFilter" class="filter-control" style="flex: 1; min-width: 200px;" onchange="filterTracking()">
                        <option value="all">All Status</option>
                        <option value="active">Active (Last 1 hour)</option>
                        <option value="departed">Departed</option>
                        <option value="in_transit">In Transit</option>
                        <option value="arrived">Arrived</option>
                        <option value="delayed">Delayed</option>
                    </select>
                    
                    <select id="volunteerFilter" class="filter-control" style="flex: 1; min-width: 200px;" onchange="filterTracking()">
                        <option value="all">All Volunteers</option>
                        <?php foreach ($db_volunteers as $volunteer): ?>
                            <option value="<?php echo $volunteer['volunteer_id']; ?>">
                                <?php echo htmlspecialchars($volunteer['name']); ?> (ID: <?php echo $volunteer['volunteer_id']; ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    
                    <select id="distributionFilter" class="filter-control" style="flex: 1; min-width: 200px;" onchange="filterTracking()">
                        <option value="all">All Distributions</option>
                        <?php foreach ($assignments as $dist): ?>
                            <option value="<?php echo $dist['distribution_id']; ?>">
                                DIST<?php echo str_pad($dist['distribution_id'], 6, '0', STR_PAD_LEFT); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <!-- Live Tracking Grid -->
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 20px;" id="trackingGrid">
                <?php if (empty($tracking_data)): ?>
                    <div class="empty-state" style="grid-column: 1 / -1; background: white; padding: 40px;">
                        <i class="fas fa-map-marked-alt"></i>
                        <h3>No Live Tracking Data</h3>
                        <p>No volunteers are currently being tracked. Check back when distributions are active.</p>
                    </div>
                <?php else: 
                    $processed_volunteers = [];
                    foreach ($tracking_data as $track): 
                        // Get the latest update for each volunteer-distribution combo
                        $track_key = $track['volunteer_id'] . '_' . $track['distribution_id'];
                        
                        if (in_array($track_key, $processed_volunteers)) {
                            continue;
                        }
                        $processed_volunteers[] = $track_key;
                        
                        $time_ago = '';
                        $created = new DateTime($track['created_at']);
                        $now = new DateTime();
                        $interval = $created->diff($now);
                        
                        if ($interval->h > 0) {
                            $time_ago = $interval->h . ' hour' . ($interval->h > 1 ? 's' : '') . ' ago';
                        } elseif ($interval->i > 0) {
                            $time_ago = $interval->i . ' minute' . ($interval->i > 1 ? 's' : '') . ' ago';
                        } else {
                            $time_ago = 'Just now';
                        }
                        
                        // Check if active (within last 30 minutes)
                        $is_active = ($interval->i < 30 && $interval->h == 0);
                        
                        // Get volunteer name from API
                        $volunteer_name = "Volunteer {$track['volunteer_id']}";
                        $volunteer_phone = '';
                        if (isset($volunteer_lookup[$track['volunteer_id']])) {
                            $volunteer = $volunteer_lookup[$track['volunteer_id']];
                            $volunteer_name = $volunteer['FullName'] ?? $volunteer_name;
                            $volunteer_phone = $volunteer['Phone'] ?? '';
                        }
                        
                        // Get disaster name
                        $disaster_name = '';
                        foreach ($disasters as $disaster) {
                            $disaster_id = $disaster['disaster_id'] ?? $disaster['id'] ?? $disaster['Disaster_ID'] ?? 0;
                            if (intval($disaster_id) == $track['disaster_id']) {
                                $disaster_name = $disaster['Disaster_Name'] ?? $disaster['name'] ?? $disaster['disaster_name'] ?? '';
                                break;
                            }
                        }
                        
                        // Get victim info if available
                        $victim_name = '';
                        $victim_contact = '';
                        if ($track['victim_id'] && isset($victim_lookup[$track['victim_id']])) {
                            $victim = $victim_lookup[$track['victim_id']];
                            $victim_name = $victim['full_name'] ?? '';
                            $victim_contact = $victim['phone'] ?? '';
                        }
                        
                        // Status mapping for display
                        $status_display_map = [
                            'departed' => 'Departed',
                            'in_transit' => 'In Transit',
                            'arrived' => 'Arrived',
                            'delayed' => 'Delayed',
                            'completed' => 'Completed'
                        ];
                        
                        $tracking_display = $status_display_map[$track['status']] ?? ucfirst($track['status']);
                ?>
                <div class="tracking-card" 
                     data-status="<?php echo $track['status']; ?>" 
                     data-volunteer="<?php echo $track['volunteer_id']; ?>"
                     data-distribution="<?php echo $track['distribution_id']; ?>"
                     data-active="<?php echo $is_active ? 'true' : 'false'; ?>"
                     style="background: white; border-radius: 8px; padding: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); border-left: 4px solid #3498db;">
                    
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 15px;">
                        <div style="flex: 1;">
                            <div style="font-weight: 600; color: #2c3e50; margin-bottom: 5px;">
                                <?php echo htmlspecialchars($volunteer_name); ?>
                                <?php if ($is_active): ?>
                                    <span style="background: #27ae60; color: white; padding: 2px 6px; border-radius: 10px; font-size: 10px; margin-left: 5px;">
                                        <i class="fas fa-circle" style="font-size: 8px;"></i> LIVE
                                    </span>
                                <?php endif; ?>
                            </div>
                            <div style="font-size: 12px; color: #7f8c8d;">
                                DIST<?php echo str_pad($track['distribution_id'], 6, '0', STR_PAD_LEFT); ?>
                                <?php if ($disaster_name): ?>
                                    • <?php echo htmlspecialchars($disaster_name); ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php 
                        $tracking_class = strtolower($track['status']);
                        ?>
                        <span class="status-badge status-<?php echo $tracking_class; ?>">
                            <?php echo $tracking_display; ?>
                        </span>
                    </div>
                    
                    <div style="margin-bottom: 15px;">
                        <!-- Location with map icon -->
                        <div style="font-size: 14px; color: #2c3e50; margin-bottom: 8px;">
                            <i class="fas fa-map-marker-alt" style="color: #e74c3c; margin-right: 8px;"></i>
                            <strong>Location:</strong> <?php echo htmlspecialchars($track['current_location']); ?>
                        </div>
                        
                        <?php if ($victim_name): ?>
                        <div style="font-size: 13px; color: #7f8c8d; margin-bottom: 5px;">
                            <i class="fas fa-user" style="margin-right: 8px;"></i>
                            <strong>Victim:</strong> <?php echo htmlspecialchars($victim_name); ?>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($track['tracking_notes'])): ?>
                        <div style="font-size: 13px; color: #7f8c8d; margin-top: 8px; background: #f8f9fa; padding: 8px; border-radius: 4px;">
                            <i class="fas fa-sticky-note" style="margin-right: 8px;"></i> 
                            <?php echo htmlspecialchars($track['tracking_notes']); ?>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($track['estimated_arrival'])): ?>
                        <div style="font-size: 13px; color: #2ecc71; margin-top: 8px;">
                            <i class="fas fa-clock" style="margin-right: 8px;"></i>
                            <strong>ETA:</strong> <?php echo htmlspecialchars($track['estimated_arrival']); ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div style="font-size: 12px; color: #7f8c8d;">
                            <i class="fas fa-clock"></i> <?php echo $time_ago; ?>
                            <?php if ($volunteer_phone): ?>
                                <br><i class="fas fa-phone"></i> <?php echo htmlspecialchars($volunteer_phone); ?>
                            <?php endif; ?>
                        </div>
                        <div style="display: flex; gap: 5px;">
                            <button class="action-btn track" 
                                    onclick="viewVolunteerTracking(<?php echo $track['volunteer_id']; ?>, <?php echo $track['distribution_id']; ?>)"
                                    title="View detailed tracking">
                                <i class="fas fa-location-arrow"></i> Track
                            </button>
                            <button class="action-btn message"
                                    onclick="sendMessage(<?php echo $track['distribution_id']; ?>, <?php echo $track['volunteer_id']; ?>, '<?php echo htmlspecialchars($volunteer_name); ?>')"
                                    title="Send message">
                                <i class="fas fa-comment"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Send Message Modal -->
    <div class="modal" id="messageModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-comment"></i> Send Message to Volunteer</h3>
            </div>
            <div class="modal-body">
                <form id="messageForm" method="POST">
                    <input type="hidden" name="action" value="send_message">
                    <input type="hidden" id="msgDistributionId" name="distribution_id" value="">
                    <input type="hidden" id="msgVolunteerId" name="volunteer_id" value="">
                    
                    <div class="form-group">
                        <label for="volunteerName">Volunteer:</label>
                        <input type="text" id="volunteerName" class="form-control" readonly>
                    </div>
                    
                    <div class="form-group">
                        <label for="messageType">Message Type:</label>
                        <select id="messageType" name="message_type" class="form-control">
                            <option value="general">General Message</option>
                            <option value="urgent">Urgent</option>
                            <option value="update">Status Update Request</option>
                            <option value="reminder">Reminder</option>
                            <option value="instructions">Instructions</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="message">Message:</label>
                        <textarea id="message" name="message" class="form-control" rows="4" required 
                                  placeholder="Type your message to the volunteer..."></textarea>
                    </div>
                    
                    <div style="background: #f8f9fa; padding: 10px; border-radius: 6px; margin-bottom: 15px;">
                        <strong>Quick Messages:</strong>
                        <div style="margin-top: 5px; display: flex; flex-wrap: wrap; gap: 5px;">
                            <button type="button" class="quick-message-btn" onclick="setQuickMessage('Please update your current location and status.')">Request Update</button>
                            <button type="button" class="quick-message-btn" onclick="setQuickMessage('Are you experiencing any delays?')">Check Delays</button>
                            <button type="button" class="quick-message-btn" onclick="setQuickMessage('Great work! Keep going.')">Encouragement</button>
                            <button type="button" class="quick-message-btn" onclick="setQuickMessage('Please proceed to next location.')">Next Location</button>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="modal-btn secondary" onclick="closeMessageModal()">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="button" class="modal-btn primary" onclick="submitMessage()">
                    <i class="fas fa-paper-plane"></i> Send Message
                </button>
            </div>
        </div>
    </div>

    <script>
        // ========================================
        // TAB SWITCHING
        // ========================================
        function showTab(tabName) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            
            document.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Show selected tab
            document.getElementById(tabName + '-tab').classList.add('active');
            event.target.classList.add('active');
        }
        
        // ========================================
        // SEARCH FUNCTIONALITY
        // ========================================
        function searchTable(tableId) {
            const input = document.getElementById('search' + (tableId === 'execution' ? 'Execution' : ''));
            const filter = input.value.toUpperCase();
            const table = document.getElementById('executionTable');
            const tr = table.getElementsByTagName('tr');
            
            for (let i = 1; i < tr.length; i++) {
                const td = tr[i].getElementsByTagName('td');
                let found = false;
                
                for (let j = 0; j < td.length; j++) {
                    if (td[j]) {
                        const txtValue = td[j].textContent || td[j].innerText;
                        if (txtValue.toUpperCase().indexOf(filter) > -1) {
                            found = true;
                            break;
                        }
                    }
                }
                
                tr[i].style.display = found ? '' : 'none';
            }
        }
        
        // ========================================
        // FILTER FUNCTIONS
        // ========================================
        function resetFilters() {
            document.getElementById('filterForm').reset();
            window.location.href = 'manage_execution.php';
        }
        
        function filterTracking() {
            const statusFilter = document.getElementById('trackingFilter').value;
            const volunteerFilter = document.getElementById('volunteerFilter').value;
            const distributionFilter = document.getElementById('distributionFilter').value;
            
            const cards = document.querySelectorAll('.tracking-card');
            
            cards.forEach(card => {
                let show = true;
                
                // Status filter
                if (statusFilter !== 'all') {
                    if (statusFilter === 'active') {
                        if (card.dataset.active !== 'true') show = false;
                    } else if (card.dataset.status !== statusFilter) {
                        show = false;
                    }
                }
                
                // Volunteer filter
                if (volunteerFilter !== 'all' && card.dataset.volunteer !== volunteerFilter) {
                    show = false;
                }
                
                // Distribution filter
                if (distributionFilter !== 'all' && card.dataset.distribution !== distributionFilter) {
                    show = false;
                }
                
                card.style.display = show ? 'block' : 'none';
            });
        }
        
        // ========================================
        // VIEW VOLUNTEER TRACKING
        // ========================================
        function viewVolunteerTracking(volId, distId) {
            window.open(`track_volunteer.php?volunteer_id=${volId}&distribution_id=${distId}`, '_blank');
        }
        
        // ========================================
        // MESSAGE MODAL FUNCTIONS
        // ========================================
        function sendMessage(distId, volId, volName) {
            document.getElementById('msgDistributionId').value = distId;
            document.getElementById('msgVolunteerId').value = volId;
            document.getElementById('volunteerName').value = volName;
            document.getElementById('message').value = '';
            
            const modal = document.getElementById('messageModal');
            modal.style.display = 'flex';
        }
        
        function closeMessageModal() {
            const modal = document.getElementById('messageModal');
            modal.style.display = 'none';
        }
        
        function setQuickMessage(message) {
            document.getElementById('message').value = message;
        }
        
        function submitMessage() {
            const message = document.getElementById('message').value.trim();
            if (!message) {
                alert('Please enter a message');
                return;
            }
            
            document.getElementById('messageForm').submit();
        }
        
        // ========================================
        // EXPORT FUNCTIONALITY
        // ========================================
        function exportToCSV(type) {
            let table;
            let filename;
            
            if (type === 'execution') {
                table = document.getElementById('executionTable');
                filename = 'distribution_execution_' + new Date().toISOString().slice(0,10) + '.csv';
            }
            
            let csv = [];
            const rows = table.querySelectorAll('tr');
            
            for (let i = 0; i < rows.length; i++) {
                const row = [], cols = rows[i].querySelectorAll('td, th');
                
                for (let j = 0; j < cols.length; j++) {
                    let data = cols[j].innerText.replace(/(\r\n|\n|\r)/gm, '').replace(/(\s\s)/gm, ' ');
                    data = data.replace(/"/g, '""');
                    row.push('"' + data + '"');
                }
                
                csv.push(row.join(','));
            }
            
            downloadCSV(csv.join('\n'), filename);
        }
        
        function downloadCSV(csv, filename) {
            const csvFile = new Blob([csv], {type: 'text/csv'});
            const downloadLink = document.createElement('a');
            
            downloadLink.download = filename;
            downloadLink.href = window.URL.createObjectURL(csvFile);
            downloadLink.style.display = 'none';
            document.body.appendChild(downloadLink);
            downloadLink.click();
            document.body.removeChild(downloadLink);
        }
        
        function printTable(type) {
            let table;
            let title;
            
            if (type === 'execution') {
                table = document.getElementById('executionTable').cloneNode(true);
                title = 'Distribution Execution Report';
            }
            
            // Remove action buttons for printing
            const actionCells = table.querySelectorAll('td:last-child, th:last-child');
            actionCells.forEach(cell => cell.remove());
            
            const printWindow = window.open('', '_blank');
            printWindow.document.write(`
                <html>
                    <head>
                        <title>${title}</title>
                        <style>
                            body { font-family: Arial, sans-serif; margin: 20px; }
                            h1 { color: #2c3e50; margin-bottom: 20px; }
                            table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                            th { background: #f8f9fa; padding: 12px; text-align: left; border-bottom: 2px solid #ddd; }
                            td { padding: 10px; border-bottom: 1px solid #ddd; }
                            .print-date { color: #7f8c8d; margin-bottom: 20px; }
                            .status-badge { padding: 3px 8px; border-radius: 12px; font-size: 11px; font-weight: bold; }
                        </style>
                    </head>
                    <body>
                        <h1>${title}</h1>
                        <div class="print-date">Generated on: ${new Date().toLocaleString()}</div>
                        ${table.outerHTML}
                    </body>
                </html>
            `);
            printWindow.document.close();
            printWindow.print();
        }
        
        // ========================================
        // REAL-TIME UPDATES CHECK
        // ========================================
        let lastCheckTime = <?php echo time(); ?>;
        let updateCheckInterval;
        
        function checkForUpdates() {
            fetch(`manage_execution.php?check_updates=1&last_check=${lastCheckTime}`)
                .then(response => response.json())
                .then(data => {
                    if (data.new_updates > 0 || data.new_cancellations > 0) {
                        showUpdateNotification(data.new_updates, data.new_cancellations);
                    }
                    
                    lastCheckTime = data.current_time;
                })
                .catch(error => {
                    console.error('Error checking for updates:', error);
                });
        }
        
        function showUpdateNotification(updates, cancellations) {
            const notification = document.getElementById('updateNotification');
            const message = document.getElementById('updateMessage');
            
            let msg = '';
            if (updates > 0 && cancellations > 0) {
                msg = `${updates} new tracking update(s) and ${cancellations} cancellation(s) detected`;
            } else if (updates > 0) {
                msg = `${updates} new tracking update(s) detected`;
            } else if (cancellations > 0) {
                msg = `${cancellations} cancellation(s) detected`;
            }
            
            message.textContent = msg;
            notification.classList.add('show');
            
            // Auto-hide after 10 seconds
            setTimeout(() => {
                notification.classList.remove('show');
            }, 10000);
        }
        
        function refreshPage() {
            window.location.reload();
        }
        
        function refreshTracking() {
            if (document.getElementById('tracking-tab').classList.contains('active')) {
                window.location.reload();
            }
        }
        
        // ========================================
        // AUTO-REFRESH AND INITIALIZATION
        // ========================================
        function startAutoRefresh() {
            // Check for updates every 30 seconds
            updateCheckInterval = setInterval(checkForUpdates, 30000);
            
            // Auto-refresh tracking tab every 60 seconds if active
            setInterval(() => {
                if (document.getElementById('tracking-tab').classList.contains('active')) {
                    console.log('Auto-refreshing tracking data...');
                    // You could implement AJAX refresh here instead of full page reload
                    // For now, just check for updates
                    checkForUpdates();
                }
            }, 60000);
        }
        
        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            // Set default date filter to today
            if (!document.getElementById('date').value) {
                const today = new Date().toISOString().split('T')[0];
                document.getElementById('date').value = today;
            }
            
            // Set default filter to show active trackers
            const trackingFilter = document.getElementById('trackingFilter');
            if (trackingFilter) {
                trackingFilter.value = 'active';
                filterTracking();
            }
            
            // Start auto-refresh
            startAutoRefresh();
            
            // Check for updates immediately
            setTimeout(checkForUpdates, 5000);
            
            // Close message modal on outside click
            document.addEventListener('click', function(event) {
                const modal = document.getElementById('messageModal');
                if (event.target === modal) {
                    closeMessageModal();
                }
            });
            
            // Close message modal on escape key
            document.addEventListener('keydown', function(event) {
                if (event.key === 'Escape') {
                    closeMessageModal();
                }
            });
        });
        
        // Stop auto-refresh when page is not visible
        document.addEventListener('visibilitychange', function() {
            if (document.hidden) {
                clearInterval(updateCheckInterval);
            } else {
                startAutoRefresh();
                // Check for updates immediately when page becomes visible
                checkForUpdates();
            }
        });
    </script>
</body>
</html>