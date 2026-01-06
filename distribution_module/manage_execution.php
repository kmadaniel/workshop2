<?php
// manage_execution.php
// Volunteer Distribution & Execution Management Dashboard
session_start();
require_once 'config.php';

// Check if user is coordinator/admin
if (!isset($_SESSION['user_role']) || ($_SESSION['user_role'] !== 'coordinator' && $_SESSION['user_role'] !== 'admin')) {
    header("Location: login_gateway.php");
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

$db->query($create_cancellations_table);
$db->query($create_coordinator_alerts_table);

// API URLs
$VOLUNTEER_API_URL = 'http://10.147.17.30:8000/api_volunteer.php';
$DISASTER_API_URL = 'http://10.147.17.116:8000/disaster.php';
$VICTIM_API_URL = 'http://10.147.17.116:8000/victim.php';
$NEEDS_API_URL = 'http://10.147.17.116:8000/needs.php';

// Fetch data functions
function fetchFromAPI($url) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if (is_resource($ch)) {
        curl_close($ch);
    }
    
    if ($httpCode === 200) {
        return json_decode($response, true);
    }
    
    return null;
}

// Fetch all data from APIs
$volunteers_data = fetchFromAPI($VOLUNTEER_API_URL);
$api_volunteers = $volunteers_data['volunteers'] ?? $volunteers_data['data'] ?? [];

$disasters_data = fetchFromAPI($DISASTER_API_URL);
$disasters = $disasters_data['disasters'] ?? $disasters_data['data'] ?? [];

$victims_data = fetchFromAPI($VICTIM_API_URL);
$api_victims = $victims_data['victims'] ?? $victims_data['data'] ?? [];

$needs_data = fetchFromAPI($NEEDS_API_URL);
$api_needs = $needs_data['needs'] ?? $needs_data['data'] ?? [];

// Get all distribution assignments (for statistics only)
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
        d.estimated_duration
    FROM distribution d
    WHERE d.status NOT IN ('Completed', 'Cancelled')
    ORDER BY d.date DESC, d.distribution_id DESC
";

$assignments = [];
if ($result = $db->query($assignments_query)) {
    $assignments = $result->fetch_all(MYSQLI_ASSOC);
}

// Get distribution execution data (from distribution_log) - UPDATED TO EXCLUDE CANCELLED VOLUNTEERS
$execution_query = "
    SELECT 
        dl.*,
        d.disaster_id,
        d.date as distribution_date,
        d.location as distribution_location,
        d.coordinator_name
    FROM distribution_log dl
    LEFT JOIN distribution d ON dl.distribution_id = d.distribution_id
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
        // Get victim info from API
        $victim_name = 'Unknown';
        $victim_contact = 'N/A';
        $family_size = 1;
        
        foreach ($api_victims as $victim) {
            $victim_id = $victim['victim_id'] ?? $victim['Victim_ID'] ?? $victim['id'] ?? 0;
            if (intval($victim_id) == $exec['victim_id']) {
                $victim_name = $victim['FullName'] ?? $victim['fullName'] ?? $victim['name'] ?? 'Unknown';
                $victim_contact = $victim['ContactNumber'] ?? $victim['contact_number'] ?? $victim['phone'] ?? 'N/A';
                $family_size = $victim['FamilyMembers'] ?? $victim['family_members'] ?? $victim['family_size'] ?? 1;
                break;
            }
        }
        
        // Get need/item info from API
        $resource_name = 'Item';
        $quantity_needed = 1;
        $unit = 'units';
        
        foreach ($api_needs as $need) {
            $need_id = $need['NeedID'] ?? $need['need_id'] ?? $need['id'] ?? 0;
            if (intval($need_id) == $exec['need_id']) {
                $resource_name = $need['ResourceName'] ?? $need['resource_name'] ?? $need['item_name'] ?? 'Item';
                $quantity_needed = $need['QuantityNeeded'] ?? $need['quantity_needed'] ?? $need['quantity'] ?? 1;
                $unit = $need['Unit'] ?? $need['unit'] ?? 'units';
                break;
            }
        }
        
        $exec['victim_name'] = $victim_name;
        $exec['victim_contact'] = $victim_contact;
        $exec['family_size'] = $family_size;
        $exec['resource_name'] = $resource_name;
        $exec['quantity_needed'] = $quantity_needed;
        $exec['unit'] = $unit;
    }
    unset($exec); // Unset reference
}

// Get statistics - UPDATED to exclude cancelled volunteers
$stats_query = "
    SELECT 
        (SELECT COUNT(DISTINCT assigned_volunteer_id) FROM distribution_items WHERE assigned_volunteer_id IS NOT NULL AND distribution_id NOT IN (
            SELECT distribution_id FROM assignment_cancellations WHERE volunteer_id = assigned_volunteer_id
        )) as active_volunteers,
        (SELECT COUNT(*) FROM distribution WHERE status IN ('In Transit', 'In Progress', 'Assigned')) as active_distributions,
        (SELECT COUNT(DISTINCT victim_id) FROM distribution_log WHERE status = 'completed' AND distribution_id NOT IN (
            SELECT distribution_id FROM assignment_cancellations WHERE volunteer_id = volunteer_id
        )) as families_served,
        (SELECT COUNT(DISTINCT need_id) FROM distribution_log WHERE status = 'completed' AND distribution_id NOT IN (
            SELECT distribution_id FROM assignment_cancellations WHERE volunteer_id = volunteer_id
        )) as items_delivered,
        (SELECT COUNT(DISTINCT distribution_id) FROM distribution_items WHERE assigned_volunteer_id IS NULL AND status = 'Scheduled') as pending_assignments,
        (SELECT COUNT(*) FROM distribution WHERE volunteers_needed > 0 AND status != 'Completed') as need_volunteers,
        (SELECT COUNT(*) FROM distribution WHERE status = 'Completed') as completed_distributions
";

$stats = [];
if ($result = $db->query($stats_query)) {
    $stats = $result->fetch_assoc();
}

// Get all unique volunteer IDs from distribution_items and distribution_log for dropdown (excluding cancelled)
$volunteer_ids = [];

// Get from distribution_items (excluding cancelled)
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

// Get from distribution_log (excluding cancelled)
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

// Get from distribution_tracking (excluding cancelled)
$volunteer_tracking_query = "
    SELECT DISTINCT volunteer_id 
    FROM distribution_tracking 
    WHERE volunteer_id IS NOT NULL
    AND distribution_id NOT IN (
        SELECT distribution_id FROM assignment_cancellations WHERE volunteer_id = volunteer_id
    )
    ORDER BY volunteer_id
";
if ($result = $db->query($volunteer_tracking_query)) {
    while ($row = $result->fetch_assoc()) {
        $volunteer_ids[$row['volunteer_id']] = $row['volunteer_id'];
    }
}

// Get volunteer names from API for the IDs we have
$db_volunteers = [];
foreach ($volunteer_ids as $volunteer_id) {
    // Try to find volunteer in API data
    foreach ($api_volunteers as $api_volunteer) {
        $api_volunteer_id = $api_volunteer['volunteer_id'] ?? $api_volunteer['VolunteerID'] ?? $api_volunteer['id'] ?? 0;
        if (intval($api_volunteer_id) == $volunteer_id) {
            $db_volunteers[] = [
                'volunteer_id' => $volunteer_id,
                'name' => $api_volunteer['FullName'] ?? $api_volunteer['fullName'] ?? $api_volunteer['name'] ?? "Volunteer $volunteer_id",
                'phone' => $api_volunteer['Phone'] ?? $api_volunteer['phone'] ?? $api_volunteer['contact_number'] ?? '',
                'email' => $api_volunteer['Email'] ?? $api_volunteer['email'] ?? ''
            ];
            break;
        }
    }
    
    // If not found in API, create a basic entry
    if (!in_array($volunteer_id, array_column($db_volunteers, 'volunteer_id'))) {
        $db_volunteers[] = [
            'volunteer_id' => $volunteer_id,
            'name' => "Volunteer $volunteer_id",
            'phone' => '',
            'email' => ''
        ];
    }
}

// Get coordinator alerts
$coordinator_alerts = [];
$alerts_query = "SELECT * FROM coordinator_alerts WHERE is_read = 0 ORDER BY created_at DESC LIMIT 5";
if ($result = $db->query($alerts_query)) {
    $coordinator_alerts = $result->fetch_all(MYSQLI_ASSOC);
}

// Mark alerts as read when viewed
if (!empty($coordinator_alerts)) {
    $mark_read_query = "UPDATE coordinator_alerts SET is_read = 1 WHERE is_read = 0";
    $db->query($mark_read_query);
}

// Filter options
$filter_disaster = $_GET['disaster_id'] ?? '';
$filter_status = $_GET['status'] ?? '';
$filter_date = $_GET['date'] ?? '';
$filter_volunteer = $_GET['volunteer_id'] ?? '';

// Build filter conditions for execution log
$execution_where_conditions = ["dl.distribution_id NOT IN (SELECT distribution_id FROM assignment_cancellations WHERE volunteer_id = dl.volunteer_id)"];
$execution_params = [];
$execution_types = '';

if ($filter_disaster) {
    $execution_where_conditions[] = "d.disaster_id = ?";
    $execution_params[] = $filter_disaster;
    $execution_types .= 'i';
}

if ($filter_status) {
    if ($filter_status === 'need_volunteers') {
        // Not applicable for execution log
    } else {
        $execution_where_conditions[] = "dl.status = ?";
        $execution_params[] = $filter_status;
        $execution_types .= 's';
    }
}

if ($filter_date) {
    $execution_where_conditions[] = "DATE(d.date) = ?";
    $execution_params[] = $filter_date;
    $execution_types .= 's';
}

if ($filter_volunteer) {
    $execution_where_conditions[] = "dl.volunteer_id = ?";
    $execution_params[] = $filter_volunteer;
    $execution_types .= 'i';
}

// Apply filters to executions
if (!empty($execution_where_conditions)) {
    $filtered_execution_query = "
        SELECT 
            dl.*,
            d.disaster_id,
            d.date as distribution_date,
            d.location as distribution_location,
            d.coordinator_name
        FROM distribution_log dl
        LEFT JOIN distribution d ON dl.distribution_id = d.distribution_id
        WHERE " . implode(' AND ', $execution_where_conditions) . "
        ORDER BY dl.created_at DESC
        LIMIT 100
    ";
    
    if (!empty($execution_params)) {
        $stmt = $db->prepare($filtered_execution_query);
        if ($stmt) {
            if (!empty($execution_types)) {
                $stmt->bind_param($execution_types, ...$execution_params);
            }
            $stmt->execute();
            $result = $stmt->get_result();
            $executions = $result->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
            
            // Re-enhance execution data with API information after filtering
            foreach ($executions as &$exec) {
                // Get victim info from API
                $victim_name = 'Unknown';
                $victim_contact = 'N/A';
                $family_size = 1;
                
                foreach ($api_victims as $victim) {
                    $victim_id = $victim['victim_id'] ?? $victim['Victim_ID'] ?? $victim['id'] ?? 0;
                    if (intval($victim_id) == $exec['victim_id']) {
                        $victim_name = $victim['FullName'] ?? $victim['fullName'] ?? $victim['name'] ?? 'Unknown';
                        $victim_contact = $victim['ContactNumber'] ?? $victim['contact_number'] ?? $victim['phone'] ?? 'N/A';
                        $family_size = $victim['FamilyMembers'] ?? $victim['family_members'] ?? $victim['family_size'] ?? 1;
                        break;
                    }
                }
                
                // Get need/item info from API
                $resource_name = 'Item';
                $quantity_needed = 1;
                $unit = 'units';
                
                foreach ($api_needs as $need) {
                    $need_id = $need['NeedID'] ?? $need['need_id'] ?? $need['id'] ?? 0;
                    if (intval($need_id) == $exec['need_id']) {
                        $resource_name = $need['ResourceName'] ?? $need['resource_name'] ?? $need['item_name'] ?? 'Item';
                        $quantity_needed = $need['QuantityNeeded'] ?? $need['quantity_needed'] ?? $need['quantity'] ?? 1;
                        $unit = $need['Unit'] ?? $need['unit'] ?? 'units';
                        break;
                    }
                }
                
                $exec['victim_name'] = $victim_name;
                $exec['victim_contact'] = $victim_contact;
                $exec['family_size'] = $family_size;
                $exec['resource_name'] = $resource_name;
                $exec['quantity_needed'] = $quantity_needed;
                $exec['unit'] = $unit;
            }
            unset($exec); // Unset reference
        }
    } else {
        if ($result = $db->query($filtered_execution_query)) {
            $executions = $result->fetch_all(MYSQLI_ASSOC);
        }
    }
}

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $distribution_id = $_POST['distribution_id'] ?? 0;
    $volunteer_id = $_POST['volunteer_id'] ?? 0;
    
    if ($action === 'reassign_volunteer' && $distribution_id && $volunteer_id) {
        $new_volunteer_id = $_POST['new_volunteer_id'] ?? 0;
        
        if ($new_volunteer_id) {
            // Update distribution_items
            $update_query = "UPDATE distribution_items SET assigned_volunteer_id = ? WHERE distribution_id = ? AND assigned_volunteer_id = ?";
            $stmt = $db->prepare($update_query);
            $stmt->bind_param("iii", $new_volunteer_id, $distribution_id, $volunteer_id);
            $stmt->execute();
            $stmt->close();
            
            // Redirect to refresh
            header("Location: manage_execution.php?success=reassigned");
            exit;
        }
    }
    
    if ($action === 'update_status' && $distribution_id) {
        $new_status = $_POST['new_status'] ?? '';
        $reason = $_POST['reason'] ?? '';
        
        if ($new_status) {
            // Update distribution status
            $update_query = "UPDATE distribution SET status = ? WHERE distribution_id = ?";
            $stmt = $db->prepare($update_query);
            $stmt->bind_param("si", $new_status, $distribution_id);
            $stmt->execute();
            $stmt->close();
            
            // If status is completed, also update distribution_items
            if ($new_status === 'Completed') {
                $update_items_query = "UPDATE distribution_items SET status = 'Delivered' WHERE distribution_id = ?";
                $stmt = $db->prepare($update_items_query);
                $stmt->bind_param("i", $distribution_id);
                $stmt->execute();
                $stmt->close();
            }
            
            // Redirect to refresh
            header("Location: manage_execution.php?success=status_updated");
            exit;
        }
    }
    
    if ($action === 'add_volunteer' && $distribution_id) {
        $volunteer_id = $_POST['volunteer_id'] ?? 0;
        
        if ($volunteer_id) {
            // Check if distribution has items
            $check_items_query = "SELECT item_id FROM distribution_items WHERE distribution_id = ? LIMIT 1";
            $stmt = $db->prepare($check_items_query);
            $stmt->bind_param("i", $distribution_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                // Add volunteer to first available distribution item
                $add_query = "UPDATE distribution_items SET assigned_volunteer_id = ? WHERE distribution_id = ? AND assigned_volunteer_id IS NULL LIMIT 1";
                $stmt = $db->prepare($add_query);
                $stmt->bind_param("ii", $volunteer_id, $distribution_id);
                $stmt->execute();
                
                if ($stmt->affected_rows > 0) {
                    // Decrease volunteers_needed count
                    $update_count_query = "UPDATE distribution SET volunteers_needed = GREATEST(0, volunteers_needed - 1) WHERE distribution_id = ?";
                    $stmt = $db->prepare($update_count_query);
                    $stmt->bind_param("i", $distribution_id);
                    $stmt->execute();
                    
                    header("Location: manage_execution.php?success=volunteer_added");
                    exit;
                }
            }
            
            // If no existing items, create one
            $create_query = "INSERT INTO distribution_items (distribution_id, victim_id, status, assigned_volunteer_id) VALUES (?, 0, 'Scheduled', ?)";
            $stmt = $db->prepare($create_query);
            $stmt->bind_param("ii", $distribution_id, $volunteer_id);
            $stmt->execute();
            
            // Decrease volunteers_needed count
            $update_count_query = "UPDATE distribution SET volunteers_needed = GREATEST(0, volunteers_needed - 1) WHERE distribution_id = ?";
            $stmt = $db->prepare($update_count_query);
            $stmt->bind_param("i", $distribution_id);
            $stmt->execute();
            
            header("Location: manage_execution.php?success=volunteer_added");
            exit;
        }
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
        
        .stat-card:nth-child(2) {
            border-top-color: #2ecc71;
        }
        
        .stat-card:nth-child(3) {
            border-top-color: #9b59b6;
        }
        
        .stat-card:nth-child(4) {
            border-top-color: #e74c3c;
        }
        
        .stat-card:nth-child(5) {
            border-top-color: #f39c12;
        }
        
        .stat-card:nth-child(6) {
            border-top-color: #1abc9c;
        }
        
        .stat-card:nth-child(7) {
            border-top-color: #e67e22;
        }
        
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
        
        .volunteer-info {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 6px;
            margin-bottom: 10px;
            border-left: 4px solid #3498db;
        }
        
        .distribution-info {
            background: #e8f4fc;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
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
        
        /* Map modal specific styles */
        #mapContainer {
            position: relative;
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
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <!-- Coordinator Alerts -->
        <?php if (!empty($coordinator_alerts)): ?>
            <div class="alert-message">
                <h4 style="margin-bottom: 10px;"><i class="fas fa-bell"></i> Coordinator Alerts</h4>
                <?php foreach ($coordinator_alerts as $alert): ?>
                    <div style="padding: 8px; background: rgba(255, 193, 7, 0.1); border-radius: 4px; margin-bottom: 5px;">
                        <i class="fas fa-info-circle"></i> 
                        <?php echo htmlspecialchars($alert['message']); ?>
                        <span style="font-size: 12px; color: #856404; float: right;">
                            <?php echo date('h:i A', strtotime($alert['created_at'])); ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <!-- Statistics -->
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
                <div class="stat-number"><?php echo $stats['completed_distributions'] ?? 0; ?></div>
                <div class="stat-label">Completed</div>
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
                                $disaster_id = $disaster['disaster_id'] ?? $disaster['id'] ?? '';
                                $disaster_name = $disaster['Disaster_Name'] ?? $disaster['name'] ?? 'Unknown';
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
                            <option value="in_progress" <?php echo $filter_status == 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                            <option value="dispatched" <?php echo $filter_status == 'dispatched' ? 'selected' : ''; ?>>Dispatched</option>
                            <option value="delivered" <?php echo $filter_status == 'delivered' ? 'selected' : ''; ?>>Delivered</option>
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
                </div>
            </form>
        </div>
        
        <!-- Tabs (Only 2 tabs now) -->
        <div class="tabs">
            <div class="tab active" onclick="showTab('execution')">
                <i class="fas fa-truck-loading"></i> Execution Log
            </div>
            <div class="tab" onclick="showTab('tracking')">
                <i class="fas fa-map-marked-alt"></i> Live Tracking
            </div>
        </div>
        
        <!-- Tab Content: Execution Log -->
        <div id="execution-tab" class="tab-content active">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3 style="color: #2c3e50;">
                    <i class="fas fa-truck-loading"></i> Distribution Execution Log
                    <span style="font-size: 14px; color: #7f8c8d; font-weight: normal; margin-left: 10px;">
                        (<?php echo count($executions); ?> log entries)
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
                                <th>Volunteer ID</th>
                                <th>Victim</th>
                                <th>Item</th>
                                <th>Quantity</th>
                                <th>Status</th>
                                <th>Remarks</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($executions as $exec): 
                                // Get volunteer name from API data
                                $volunteer_name = 'Unknown';
                                foreach ($api_volunteers as $api_volunteer) {
                                    $api_volunteer_id = $api_volunteer['volunteer_id'] ?? $api_volunteer['VolunteerID'] ?? $api_volunteer['id'] ?? 0;
                                    if (intval($api_volunteer_id) == $exec['volunteer_id']) {
                                        $volunteer_name = $api_volunteer['FullName'] ?? $api_volunteer['fullName'] ?? $api_volunteer['name'] ?? "Volunteer {$exec['volunteer_id']}";
                                        break;
                                    }
                                }
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
                                    <div><?php echo htmlspecialchars((string)$volunteer_name); ?></div>
                                    <div style="font-size: 12px; color: #7f8c8d;">
                                        ID: VOL<?php echo str_pad($exec['volunteer_id'], 4, '0', STR_PAD_LEFT); ?>
                                    </div>
                                </td>
                                <td>
                                    <div><?php echo htmlspecialchars((string)($exec['victim_name'] ?? 'Unknown')); ?></div>
                                    <div style="font-size: 12px; color: #7f8c8d;">
                                        Family: <?php echo $exec['family_size'] ?? 1; ?> | <?php echo htmlspecialchars((string)($exec['victim_contact'] ?? 'N/A')); ?>
                                    </div>
                                </td>
                                <td>
                                    <div><?php echo htmlspecialchars((string)($exec['resource_name'] ?? 'Item')); ?></div>
                                    <div style="font-size: 12px; color: #7f8c8d;">
                                        ID: <?php echo $exec['need_id'] ?? 'N/A'; ?>
                                    </div>
                                </td>
                                <td>
                                    <?php echo $exec['quantity_distributed'] ?? 1; ?> 
                                    <?php echo htmlspecialchars((string)($exec['unit'] ?? 'units')); ?>
                                </td>
                                <td>
                                    <?php 
                                    $status_class = strtolower(str_replace(' ', '_', $exec['status']));
                                    ?>
                                    <span class="status-badge status-<?php echo $status_class; ?>">
                                        <?php echo ucfirst($exec['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (!empty($exec['remarks'])): ?>
                                        <div style="font-size: 12px; color: #7f8c8d; max-width: 200px;">
                                            <?php echo htmlspecialchars(substr((string)$exec['remarks'], 0, 50)); ?>
                                            <?php if (strlen($exec['remarks']) > 50): ?>...<?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <span style="color: #95a5a6; font-size: 12px;">No remarks</span>
                                    <?php endif; ?>
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
                    <button class="export-btn" style="background: #e74c3c; border-color: #e74c3c;" onclick="showAllVolunteersOnMap()">
                        <i class="fas fa-map"></i> View All on Map
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
                            <?php 
                            // Get ALL tracking data (not just last 2 hours) - UPDATED TO EXCLUDE CANCELLED VOLUNTEERS
                            $all_tracking_query = "
                                SELECT dt.*
                                FROM distribution_tracking dt
                                WHERE dt.created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
                                AND dt.distribution_id NOT IN (
                                    SELECT distribution_id FROM assignment_cancellations WHERE volunteer_id = dt.volunteer_id
                                )
                                ORDER BY dt.created_at DESC
                            ";
                            
                            $all_tracking_data = [];
                            if ($result = $db->query($all_tracking_query)) {
                                $all_tracking_data = $result->fetch_all(MYSQLI_ASSOC);
                            }
                            
                            $active_tracking_count = 0;
                            foreach ($all_tracking_data as $track) {
                                $time_diff = time() - strtotime($track['created_at']);
                                if ($time_diff < 3600) { // Within last hour
                                    $active_tracking_count++;
                                }
                            }
                            echo $active_tracking_count;
                            ?>
                        </div>
                        <div style="font-size: 12px; color: #7f8c8d;">Active Trackers</div>
                    </div>
                    
                    <div style="text-align: center; padding: 15px; background: #f8f9fa; border-radius: 6px;">
                        <div style="font-size: 24px; font-weight: bold; color: #27ae60;">
                            <?php 
                            $arrived_count = 0;
                            foreach ($all_tracking_data as $track) {
                                if ($track['status'] == 'arrived' || $track['status'] == 'delivered') {
                                    $arrived_count++;
                                }
                            }
                            echo $arrived_count;
                            ?>
                        </div>
                        <div style="font-size: 12px; color: #7f8c8d;">Arrived/Delivered</div>
                    </div>
                    
                    <div style="text-align: center; padding: 15px; background: #f8f9fa; border-radius: 6px;">
                        <div style="font-size: 24px; font-weight: bold; color: #e67e22;">
                            <?php 
                            $transit_count = 0;
                            foreach ($all_tracking_data as $track) {
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
                            <?php 
                            $delayed_count = 0;
                            foreach ($all_tracking_data as $track) {
                                if ($track['status'] == 'delayed') {
                                    $delayed_count++;
                                }
                            }
                            echo $delayed_count;
                            ?>
                        </div>
                        <div style="font-size: 12px; color: #7f8c8d;">Delayed</div>
                    </div>
                </div>
            </div>
            
            <!-- Filter Controls -->
            <div style="background: white; border-radius: 8px; padding: 15px; margin-bottom: 20px;">
                <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                    <select id="trackingFilter" class="filter-control" style="flex: 1; min-width: 200px;" onchange="filterTracking()">
                        <option value="all">All Status</option>
                        <option value="active">Active (Last 1 hour)</option>
                        <option value="in_transit">In Transit</option>
                        <option value="arrived">Arrived</option>
                        <option value="delayed">Delayed</option>
                        <option value="departed">Departed</option>
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
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 20px;">
                <?php 
                // Get ALL tracking data (not just last 2 hours) - UPDATED TO EXCLUDE CANCELLED VOLUNTEERS
                $all_tracking_query = "
                    SELECT 
                        dt.*,
                        d.distribution_id,
                        d.disaster_id,
                        d.location as distribution_location,
                        d.coordinator_name
                    FROM distribution_tracking dt
                    JOIN distribution d ON dt.distribution_id = d.distribution_id
                    WHERE dt.created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
                    AND dt.distribution_id NOT IN (
                        SELECT distribution_id FROM assignment_cancellations WHERE volunteer_id = dt.volunteer_id
                    )
                    ORDER BY dt.created_at DESC
                    LIMIT 50
                ";
                
                $all_tracking_data = [];
                if ($result = $db->query($all_tracking_query)) {
                    $all_tracking_data = $result->fetch_all(MYSQLI_ASSOC);
                }
                
                if (empty($all_tracking_data)): ?>
                    <div class="empty-state" style="grid-column: 1 / -1; background: white; padding: 40px;">
                        <i class="fas fa-map-marked-alt"></i>
                        <h3>No Live Tracking Data</h3>
                        <p>No volunteers are currently being tracked. Check back when distributions are active.</p>
                    </div>
                <?php else: 
                    $processed_volunteers = [];
                    foreach ($all_tracking_data as $track): 
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
                        foreach ($api_volunteers as $api_volunteer) {
                            $api_volunteer_id = $api_volunteer['volunteer_id'] ?? $api_volunteer['VolunteerID'] ?? $api_volunteer['id'] ?? 0;
                            if (intval($api_volunteer_id) == $track['volunteer_id']) {
                                $volunteer_name = $api_volunteer['FullName'] ?? $api_volunteer['fullName'] ?? $api_volunteer['name'] ?? $volunteer_name;
                                $volunteer_phone = $api_volunteer['Phone'] ?? $api_volunteer['phone'] ?? $api_volunteer['contact_number'] ?? '';
                                break;
                            }
                        }
                        
                        // Get disaster name
                        $disaster_name = '';
                        foreach ($disasters as $disaster) {
                            $disaster_id = $disaster['disaster_id'] ?? $disaster['id'] ?? 0;
                            if (intval($disaster_id) == $track['disaster_id']) {
                                $disaster_name = $disaster['Disaster_Name'] ?? $disaster['name'] ?? '';
                                break;
                            }
                        }
                        
                        // Get victim info if available
                        $victim_name = '';
                        if ($track['victim_id']) {
                            foreach ($api_victims as $victim) {
                                $victim_id = $victim['victim_id'] ?? $victim['Victim_ID'] ?? $victim['id'] ?? 0;
                                if (intval($victim_id) == $track['victim_id']) {
                                    $victim_name = $victim['FullName'] ?? $victim['fullName'] ?? $victim['name'] ?? '';
                                    break;
                                }
                            }
                        }
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
                        $tracking_display = ucfirst(str_replace('_', ' ', $track['status']));
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
                            <button class="action-btn view" 
                                    onclick="sendMessage(<?php echo $track['volunteer_id']; ?>, '<?php echo htmlspecialchars(addslashes($volunteer_name)); ?>')"
                                    title="Send message to volunteer">
                                <i class="fas fa-comment"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <!-- Map Modal -->
            <div id="mapModal" class="modal">
                <div class="modal-content" style="max-width: 800px;">
                    <div class="modal-header">
                        <h3><i class="fas fa-map"></i> All Volunteers on Map</h3>
                        <button onclick="closeMapModal()" style="background: none; border: none; font-size: 20px; cursor: pointer; color: #7f8c8d;">&times;</button>
                    </div>
                    <div class="modal-body">
                        <div id="mapContainer" style="height: 400px; background: #f8f9fa; border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                            <p style="color: #7f8c8d;"><i class="fas fa-map-marked-alt"></i> Interactive map would appear here</p>
                            <!-- In a real implementation, you would integrate Google Maps or Leaflet here -->
                        </div>
                        <div id="mapLegend" style="margin-top: 15px; display: flex; gap: 15px; flex-wrap: wrap;">
                            <div style="display: flex; align-items: center; gap: 5px;">
                                <div style="width: 15px; height: 15px; background: #3498db; border-radius: 50%;"></div>
                                <span style="font-size: 12px;">Departed</span>
                            </div>
                            <div style="display: flex; align-items: center; gap: 5px;">
                                <div style="width: 15px; height: 15px; background: #e67e22; border-radius: 50%;"></div>
                                <span style="font-size: 12px;">In Transit</span>
                            </div>
                            <div style="display: flex; align-items: center; gap: 5px;">
                                <div style="width: 15px; height: 15px; background: #27ae60; border-radius: 50%;"></div>
                                <span style="font-size: 12px;">Arrived</span>
                            </div>
                            <div style="display: flex; align-items: center; gap: 5px;">
                                <div style="width: 15px; height: 15px; background: #e74c3c; border-radius: 50%;"></div>
                                <span style="font-size: 12px;">Delayed</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Message Modal -->
            <div id="messageModal" class="modal">
                <div class="modal-content" style="max-width: 500px;">
                    <div class="modal-header">
                        <h3><i class="fas fa-comment"></i> Send Message</h3>
                        <button onclick="closeMessageModal()" style="background: none; border: none; font-size: 20px; cursor: pointer; color: #7f8c8d;">&times;</button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label>To:</label>
                            <input type="text" id="messageTo" class="form-control" readonly>
                        </div>
                        <div class="form-group">
                            <label>Message:</label>
                            <textarea id="messageContent" class="form-control" rows="4" placeholder="Type your message to the volunteer..."></textarea>
                        </div>
                        <div class="form-group">
                            <label>Quick Messages:</label>
                            <div style="display: flex; gap: 5px; flex-wrap: wrap; margin-top: 5px;">
                                <button type="button" class="quick-message-btn" onclick="setQuickMessage('What is your current ETA?')">ETA?</button>
                                <button type="button" class="quick-message-btn" onclick="setQuickMessage('Are you facing any issues?')">Issues?</button>
                                <button type="button" class="quick-message-btn" onclick="setQuickMessage('Please update your location')">Update Location</button>
                                <button type="button" class="quick-message-btn" onclick="setQuickMessage('Great job! Please proceed')">Encourage</button>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="modal-btn secondary" onclick="closeMessageModal()">Cancel</button>
                        <button class="modal-btn primary" onclick="sendMessageNow()">Send Message</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Tab switching
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
        
        // Search functionality
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
        
        // Reset filters
        function resetFilters() {
            document.getElementById('filterForm').reset();
            window.location.href = 'manage_execution.php';
        }
        
        function viewVolunteerTracking(volId, distId) {
            window.open(`track_volunteer.php?volunteer_id=${volId}&distribution_id=${distId}`, '_blank');
        }
        
        // Export functionality
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
            
            // Remove action buttons for printing (if any)
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
        
        function refreshTracking() {
            window.location.reload();
        }
        
        // Filter tracking cards
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
        
        // Map modal functions
        function showAllVolunteersOnMap() {
            document.getElementById('mapModal').style.display = 'flex';
            // In real implementation, you would initialize the map here
            // Example: initMap();
        }
        
        function closeMapModal() {
            document.getElementById('mapModal').style.display = 'none';
        }
        
        // Message modal functions
        let currentVolunteerId = null;
        let currentVolunteerName = null;
        
        function sendMessage(volunteerId, volunteerName) {
            currentVolunteerId = volunteerId;
            currentVolunteerName = volunteerName;
            
            document.getElementById('messageTo').value = volunteerName + ' (ID: VOL' + volunteerId.toString().padStart(4, '0') + ')';
            document.getElementById('messageContent').value = '';
            document.getElementById('messageModal').style.display = 'flex';
        }
        
        function closeMessageModal() {
            document.getElementById('messageModal').style.display = 'none';
            currentVolunteerId = null;
            currentVolunteerName = null;
        }
        
        function setQuickMessage(message) {
            document.getElementById('messageContent').value = message;
        }
        
        function sendMessageNow() {
            const message = document.getElementById('messageContent').value.trim();
            
            if (!message) {
                alert('Please enter a message');
                return;
            }
            
            // In a real implementation, you would send this to your backend
            // For now, just show a confirmation
            alert(`Message sent to ${currentVolunteerName}: "${message}"`);
            
            // Simulate sending
            console.log(`Sending to volunteer ${currentVolunteerId}: ${message}`);
            
            closeMessageModal();
        }
        
        // Auto-refresh tracking every 30 seconds
        setInterval(() => {
            if (document.getElementById('tracking-tab').classList.contains('active')) {
                // Refresh the tracking data
                fetch('?refresh_tracking=1')
                    .then(response => {
                        if (response.ok) {
                            // You could implement AJAX refresh here
                            // For demonstration, just show a notification
                            console.log('Tracking data refreshed at ' + new Date().toLocaleTimeString());
                        }
                    });
            }
        }, 30000);
        
        // ========================================
        // AUTO-REFRESH FOR CANCELLATIONS
        // ========================================
        let lastRefreshTime = new Date().getTime();
        
        function checkForCancellations() {
            // Only check if on the tracking tab or execution tab
            if (document.getElementById('tracking-tab').classList.contains('active') || 
                document.getElementById('execution-tab').classList.contains('active')) {
                
                fetch('check_cancellations.php?last_check=' + lastRefreshTime)
                    .then(response => response.json())
                    .then(data => {
                        if (data.has_new_cancellations) {
                            // Show notification
                            showCancellationNotification(data.cancellations);
                            
                            // Refresh the page after 2 seconds
                            setTimeout(() => {
                                window.location.reload();
                            }, 2000);
                        }
                        
                        lastRefreshTime = data.current_time;
                    })
                    .catch(error => {
                        console.error('Error checking for cancellations:', error);
                    });
            }
        }
        
        function showCancellationNotification(cancellations) {
            const notification = document.createElement('div');
            notification.className = 'notification-badge';
            notification.innerHTML = `
                <div class="notification-content">
                    <div class="notification-icon">
                        <i class="fas fa-user-times"></i>
                    </div>
                    <div>
                        <strong>Volunteer Cancellation Detected</strong>
                        <div style="font-size: 12px; margin-top: 5px;">
                            ${cancellations} volunteer(s) cancelled assignments. Page will refresh...
                        </div>
                    </div>
                </div>
                <button class="close-btn" onclick="this.parentElement.remove()">
                    <i class="fas fa-times"></i>
                </button>
            `;
            document.body.appendChild(notification);
            
            // Auto-remove after 5 seconds
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.style.animation = 'slideOut 0.5s ease';
                    setTimeout(() => notification.parentNode.removeChild(notification), 500);
                }
            }, 5000);
        }
        
        // Check for cancellations every 10 seconds
        setInterval(checkForCancellations, 10000);
        
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
            
            // Add quick message button styles
            const style = document.createElement('style');
            style.textContent = `
                .quick-message-btn {
                    background: #e8f4fc;
                    border: 1px solid #3498db;
                    color: #3498db;
                    padding: 5px 10px;
                    border-radius: 4px;
                    font-size: 12px;
                    cursor: pointer;
                    transition: all 0.3s;
                    margin: 2px;
                }
                .quick-message-btn:hover {
                    background: #3498db;
                    color: white;
                }
            `;
            document.head.appendChild(style);
            
            // Start checking for cancellations
            checkForCancellations();
        });
    </script>
</body>
</html>