<?php
session_start();
require_once 'config.php';

$database = new Database();
$db = $database->getConnection();

// Check if user is logged in
if (!isset($_SESSION['volunteer_id'])) {
    // Check for demo login
    if (isset($_POST['volunteer_id'])) {
        $volunteer_id = intval($_POST['volunteer_id']);
        if ($volunteer_id > 0) {
            $_SESSION['volunteer_id'] = $volunteer_id;
            $_SESSION['volunteer_name'] = "Demo Volunteer #" . $volunteer_id;
            $_SESSION['volunteer_email'] = "volunteer" . $volunteer_id . "@demo.com";
            $_SESSION['demo_mode'] = true;
        }
    }
    
    // If still not logged in, redirect to gateway
    if (!isset($_SESSION['volunteer_id'])) {
        header("Location: login_gateway.php");
        exit;
    }
}

$volunteer_id = $_SESSION['volunteer_id'];
$API_URL = 'http://10.147.17.30:8000/api_volunteer.php';

/* ----------------------------------------
   FETCH VOLUNTEER DATA FROM API
---------------------------------------- */
function fetchDataFromAPI($url, $params = []) {
    $context = stream_context_create([
        'http' => [
            'timeout' => 5,
            'header' => "Accept: application/json\r\n"
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
            return ['success' => false, 'error' => 'API server not responding: ' . $full_url];
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

/* ----------------------------------------
   GET VOLUNTEER INFORMATION FROM API
---------------------------------------- */
try {
    // Fetch volunteer data from API
    $apiResult = fetchDataFromAPI($API_URL, ['volunteer_id' => $volunteer_id]);
    
    if (!$apiResult['success']) {
        throw new Exception($apiResult['error']);
    }
    
    if (empty($apiResult['data'])) {
        throw new Exception("Volunteer not found in API!");
    }
    
    // Find the specific volunteer from API data
    $foundVolunteer = null;
    foreach ($apiResult['data'] as $volunteer) {
        // Check multiple possible field names for volunteer ID
        $apiVolunteerId = null;
        $possibleFields = ['VolunteerID', 'volunteer_id', 'volunteerId', 'id', 'volunteerID'];
        
        foreach ($possibleFields as $field) {
            if (isset($volunteer[$field]) && intval($volunteer[$field]) == $volunteer_id) {
                $apiVolunteerId = intval($volunteer[$field]);
                $foundVolunteer = $volunteer;
                break;
            }
        }
        
        if ($foundVolunteer) break;
    }
    
    if (!$foundVolunteer) {
        throw new Exception("Volunteer ID $volunteer_id not found in API data!");
    }
    
    // Map API fields to our expected format
    $volunteer_info = [
        'volunteer_id' => $volunteer_id,
        'name' => $foundVolunteer['FullName'] ?? 
                 $foundVolunteer['fullName'] ?? 
                 $foundVolunteer['full_name'] ?? 
                 'Unknown Volunteer',
        'email' => $foundVolunteer['Email'] ?? 
                  $foundVolunteer['email'] ?? 
                  '',
        'phone' => $foundVolunteer['Phone'] ?? 
                  $foundVolunteer['phone'] ?? 
                  '',
        'address' => $foundVolunteer['Address'] ?? 
                    $foundVolunteer['address'] ?? 
                    '',
        'ngo_affiliation' => $foundVolunteer['AssignedNGO'] ?? 
                           $foundVolunteer['assignedNGO'] ?? 
                           $foundVolunteer['ngo'] ?? 
                           '',
        'skill_category' => $foundVolunteer['SkillCategory'] ?? 
                          $foundVolunteer['skillCategory'] ?? 
                          $foundVolunteer['skills'] ?? 
                          '',
        'status' => $foundVolunteer['Status'] ?? 
                   $foundVolunteer['status'] ?? 
                   'Active',
        'role' => 'Volunteer' // Default role, can be enhanced based on skills
    ];
    
} catch (Exception $e) {
    $error = "Error loading volunteer information: " . $e->getMessage();
}

/* ----------------------------------------
   GET VOLUNTEER'S DISTRIBUTION ASSIGNMENTS FROM LOCAL DATABASE
   (We'll use local database for assignments since API doesn't have this)
---------------------------------------- */
if ($volunteer_info) {
    try {
        // Get active assignments (Assigned, Active)
        $active_query = "
            SELECT 
                dv.*,
                d.*,
                dis.Disaster_Name,
                dis.Location as disaster_location,
                COUNT(DISTINCT n.victim_id) as total_victims,
                COUNT(DISTINCT n.need_id) as total_needs
            FROM distribution_volunteer dv
            JOIN distribution d ON dv.distribution_id = d.distribution_id
            JOIN disaster dis ON d.disaster_id = dis.disaster_id
            LEFT JOIN needs n ON d.distribution_id = n.distribution_id 
                AND n.status IN ('Approved', 'Scheduled')
            WHERE dv.volunteer_id = ? 
            AND dv.status IN ('Assigned', 'Active')
            AND d.status != 'Completed'
            GROUP BY dv.distribution_id, dv.volunteer_id
            ORDER BY d.date ASC
        ";
        
        $stmt = $db->prepare($active_query);
        $stmt->bind_param("i", $volunteer_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $upcoming_assignments = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
    } catch (Exception $e) {
        $error .= "<br>Error loading active assignments: " . $e->getMessage();
    }
    
    // Get completed assignments
    try {
        $completed_query = "
            SELECT 
                dv.*,
                d.*,
                dis.Disaster_Name,
                dis.Location as disaster_location,
                COUNT(DISTINCT dl.victim_id) as victims_helped,
                COUNT(DISTINCT dl.need_id) as items_distributed
            FROM distribution_volunteer dv
            JOIN distribution d ON dv.distribution_id = d.distribution_id
            JOIN disaster dis ON d.disaster_id = dis.disaster_id
            LEFT JOIN distribution_log dl ON dv.distribution_id = dl.distribution_id 
                AND dv.volunteer_id = dl.volunteer_id
            WHERE dv.volunteer_id = ? 
            AND dv.status = 'Completed'
            GROUP BY dv.distribution_id, dv.volunteer_id
            ORDER BY d.date DESC
            LIMIT 10
        ";
        
        $stmt = $db->prepare($completed_query);
        $stmt->bind_param("i", $volunteer_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $past_assignments = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
    } catch (Exception $e) {
        $error .= "<br>Error loading completed assignments: " . $e->getMessage();
    }
}

/* ----------------------------------------
   GET STATISTICS
---------------------------------------- */
$stats = [
    'total_families_helped' => 0,
    'total_items_distributed' => 0,
    'total_hours_volunteered' => 0,
    'active_assignments' => count($upcoming_assignments)
];

try {
    $stats_query = "
        SELECT 
            COUNT(DISTINCT dl.victim_id) as total_families_helped,
            COUNT(DISTINCT dl.need_id) as total_items_distributed
        FROM distribution_log dl
        WHERE dl.volunteer_id = ?
    ";
    
    $stmt = $db->prepare($stats_query);
    $stmt->bind_param("i", $volunteer_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $stats_data = $result->fetch_assoc();
    $stmt->close();
    
    if ($stats_data) {
        $stats['total_families_helped'] = $stats_data['total_families_helped'] ?? 0;
        $stats['total_items_distributed'] = $stats_data['total_items_distributed'] ?? 0;
    }
    
} catch (Exception $e) {
    // Silently continue if distribution_log doesn't exist yet
}

// Calculate hours volunteered based on completed assignments
$stats['total_hours_volunteered'] = count($past_assignments) * 3; // Assume 3 hours per distribution

/* ----------------------------------------
   HANDLE ACTION REQUESTS
---------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $distribution_id = $_POST['distribution_id'] ?? 0;
    $action = $_POST['action'] ?? '';
    
    // Handle logout
    if ($action === 'logout') {
        session_destroy();
        // Redirect to your friend's login page on logout
        header("Location: http://10.147.17.30:8000/login.php");
        exit;
    }
    
    try {
        if (!$distribution_id || !$action) {
            throw new Exception("Invalid request.");
        }
        
        if ($action === 'start_distribution') {
            // Redirect to execute distribution page in YOUR module
            header("Location: execute_distribution.php?distribution_id=" . $distribution_id);
            exit;
        }
        elseif ($action === 'confirm_assignment') {
            // Update assignment status to Active
            $update_query = "UPDATE distribution_volunteer SET status = 'Active' WHERE distribution_id = ? AND volunteer_id = ?";
            $stmt = $db->prepare($update_query);
            $stmt->bind_param("ii", $distribution_id, $volunteer_id);
            $stmt->execute();
            $stmt->close();
            
            $success = "Assignment confirmed successfully!";
            
            // Refresh assignments
            $stmt = $db->prepare($active_query);
            $stmt->bind_param("i", $volunteer_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $upcoming_assignments = $result->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
        }
        
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Volunteer Dashboard - JKM Melaka</title>
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
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        /* Header Styles */
        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #e1e5eb;
        }
        
        .welcome-section h1 {
            color: #2c3e50;
            font-size: 28px;
            margin-bottom: 5px;
        }
        
        .welcome-section p {
            color: #7f8c8d;
            font-size: 16px;
        }
        
        .date-time-section {
            text-align: right;
        }
        
        .date-time-section .date {
            font-size: 18px;
            font-weight: 600;
            color: #2c3e50;
        }
        
        .date-time-section .time {
            font-size: 24px;
            font-weight: 700;
            color: #3498db;
            margin-top: 5px;
        }
        
        /* Stats Cards */
        .stats-section {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            padding: 25px;
            text-align: center;
            transition: transform 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-card.active {
            border-top: 5px solid #3498db;
        }
        
        .stat-card.families {
            border-top: 5px solid #2ecc71;
        }
        
        .stat-card.items {
            border-top: 5px solid #9b59b6;
        }
        
        .stat-card.hours {
            border-top: 5px solid #f39c12;
        }
        
        .stat-number {
            font-size: 42px;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .stat-card.active .stat-number {
            color: #3498db;
        }
        
        .stat-card.families .stat-number {
            color: #2ecc71;
        }
        
        .stat-card.items .stat-number {
            color: #9b59b6;
        }
        
        .stat-card.hours .stat-number {
            color: #f39c12;
        }
        
        .stat-label {
            color: #7f8c8d;
            font-size: 16px;
            font-weight: 500;
        }
        
        /* Profile Card */
        .profile-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            padding: 25px;
            margin-bottom: 20px;
        }
        
        .profile-header {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .profile-icon {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            color: white;
            font-size: 30px;
        }
        
        .profile-info h2 {
            color: #2c3e50;
            font-size: 22px;
            margin-bottom: 5px;
        }
        
        .profile-info p {
            color: #7f8c8d;
            font-size: 15px;
        }
        
        .volunteer-id {
            display: inline-block;
            background-color: #f0f7ff;
            color: #3498db;
            padding: 6px 12px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 14px;
            margin-bottom: 15px;
        }
        
        .contact-info {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #eee;
        }
        
        .contact-item {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .contact-item i {
            color: #3498db;
            width: 20px;
            margin-right: 10px;
        }
        
        /* Quick Actions */
        .quick-actions {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            padding: 25px;
        }
        
        .quick-actions h3 {
            color: #2c3e50;
            font-size: 18px;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .action-btn {
            display: flex;
            align-items: center;
            width: 100%;
            padding: 12px 15px;
            margin-bottom: 10px;
            background-color: #f8fafc;
            border: 1px solid #e1e5eb;
            border-radius: 8px;
            color: #2c3e50;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .action-btn i {
            margin-right: 10px;
            color: #3498db;
        }
        
        .action-btn:hover {
            background-color: #3498db;
            color: white;
            border-color: #3498db;
        }
        
        .action-btn:hover i {
            color: white;
        }
        
        /* Assignments Section */
        .assignments-section {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            padding: 30px;
            margin-top: 30px;
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }
        
        .section-header h2 {
            color: #2c3e50;
            font-size: 24px;
        }
        
        .section-header .badge {
            background: #e1f5fe;
            color: #0288d1;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
        }
        
        .assignments-list {
            min-height: 200px;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #95a5a6;
        }
        
        .empty-state i {
            font-size: 60px;
            margin-bottom: 15px;
            color: #ecf0f1;
        }
        
        .empty-state h3 {
            font-size: 22px;
            margin-bottom: 10px;
            color: #7f8c8d;
        }
        
        /* Assignment Cards */
        .assignment-card {
            background-color: #f8fafc;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            border-left: 5px solid #3498db;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .assignment-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.08);
        }
        
        .assignment-card.active {
            border-left-color: #3498db;
        }
        
        .assignment-card.completed {
            border-left-color: #2ecc71;
        }
        
        .assignment-card.pending {
            border-left-color: #f39c12;
        }
        
        .assignment-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }
        
        .assignment-title {
            font-size: 20px;
            color: #2c3e50;
            font-weight: 600;
        }
        
        .assignment-status {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
        }
        
        .status-assigned {
            background-color: #e1f5fe;
            color: #0288d1;
        }
        
        .status-active {
            background-color: #e8f5e9;
            color: #2e7d32;
        }
        
        .status-pending {
            background-color: #fff3e0;
            color: #ef6c00;
        }
        
        .status-completed {
            background-color: #e8f5e9;
            color: #2e7d32;
        }
        
        .assignment-details {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .detail-item {
            display: flex;
            align-items: center;
        }
        
        .detail-item i {
            color: #3498db;
            width: 20px;
            margin-right: 10px;
        }
        
        .detail-label {
            font-weight: 600;
            color: #7f8c8d;
            margin-right: 5px;
        }
        
        .assignment-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .action-button {
            padding: 8px 16px;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            border: none;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .start-btn {
            background-color: #9b59b6;
            color: white;
        }
        
        .start-btn:hover {
            background-color: #8e44ad;
        }
        
        .confirm-btn {
            background-color: #2ecc71;
            color: white;
        }
        
        .confirm-btn:hover {
            background-color: #27ae60;
        }
        
        .details-btn {
            background-color: #3498db;
            color: white;
        }
        
        .details-btn:hover {
            background-color: #2980b9;
        }
        
        .view-btn {
            background-color: #95a5a6;
            color: white;
        }
        
        .view-btn:hover {
            background-color: #7f8c8d;
        }
        
        /* Alert Messages */
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border-left: 5px solid #2ecc71;
        }
        
        .alert-error {
            background-color: #fde8e8;
            color: #c53030;
            border-left: 5px solid #e74c3c;
        }
        
        /* API Status Indicator */
        .api-status {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            margin-left: 10px;
            vertical-align: middle;
        }
        
        .api-status.connected {
            background-color: #d4edda;
            color: #155724;
        }
        
        .api-status.disconnected {
            background-color: #fde8e8;
            color: #c53030;
        }
        
        /* Role Badge */
        .role-badge {
            display: inline-block;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
            margin-top: 5px;
        }
        
        .skill-badge {
            display: inline-block;
            background: linear-gradient(135deg, #2ecc71 0%, #27ae60 100%);
            color: white;
            padding: 3px 10px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: 600;
            margin-top: 5px;
            margin-left: 5px;
        }
        
        /* Logout Button */
        .logout-btn {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #e74c3c;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 5px;
            z-index: 1000;
        }
        
        .logout-btn:hover {
            background: #c0392b;
        }
        
        /* Responsive Design */
        @media (max-width: 992px) {
            .stats-section {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 768px) {
            .dashboard-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .date-time-section {
                text-align: left;
                margin-top: 15px;
            }
            
            .assignment-details {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 576px) {
            .stats-section {
                grid-template-columns: 1fr;
            }
            
            .assignment-actions {
                flex-direction: column;
            }
            
            .action-button {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <!-- Logout Button -->
    <form method="POST" style="display: inline;">
        <input type="hidden" name="action" value="logout">
        <button type="submit" class="logout-btn">
            <i class="fas fa-sign-out-alt"></i> Logout
        </button>
    </form>
    
    <div class="dashboard-container">
        <!-- Header Section -->
        <div class="dashboard-header">
            <div class="welcome-section">
                <h1>Volunteer Dashboard</h1>
                <p>JKM Melaka - Disaster Relief Distribution System
                    <?php if (isset($apiResult['success']) && $apiResult['success']): ?>
                        <span class="api-status connected">API Connected</span>
                    <?php else: ?>
                        <span class="api-status disconnected">API Disconnected</span>
                    <?php endif; ?>
                </p>
            </div>
            
            <div class="date-time-section">
                <div class="date" id="current-date">Loading...</div>
                <div class="time" id="current-time">Loading...</div>
            </div>
        </div>
        
        <!-- Error/Success Messages -->
        <?php if ($error): ?>
            <div class="alert alert-error">
                <strong>Error:</strong> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success">
                <?php echo $success; ?>
            </div>
        <?php endif; ?>
        
        <!-- API Debug Info -->
        <?php if (isset($apiResult) && !$apiResult['success']): ?>
            <div class="alert alert-error">
                <strong>API Error:</strong> <?php echo htmlspecialchars($apiResult['error']); ?>
                <br><small>API URL: <?php echo $API_URL; ?></small>
            </div>
        <?php endif; ?>
        
        <!-- Stats Cards -->
        <div class="stats-section">
            <div class="stat-card active">
                <div class="stat-number"><?php echo $stats['active_assignments']; ?></div>
                <div class="stat-label">Active Assignments</div>
            </div>
            
            <div class="stat-card families">
                <div class="stat-number"><?php echo $stats['total_families_helped']; ?></div>
                <div class="stat-label">Families Helped</div>
            </div>
            
            <div class="stat-card items">
                <div class="stat-number"><?php echo $stats['total_items_distributed']; ?></div>
                <div class="stat-label">Items Distributed</div>
            </div>
            
            <div class="stat-card hours">
                <div class="stat-number"><?php echo $stats['total_hours_volunteered']; ?></div>
                <div class="stat-label">Hours Volunteered</div>
            </div>
        </div>
        
        <!-- Profile & Assignments Layout -->
        <div style="display: grid; grid-template-columns: 300px 1fr; gap: 30px;">
            <!-- Left Column: Profile & Quick Actions -->
            <div>
                <div class="profile-card">
                    <div class="profile-header">
                        <div class="profile-icon">
                            <i class="fas fa-user-check"></i>
                        </div>
                        <div class="profile-info">
                            <h2><?php echo htmlspecialchars($volunteer_info['name'] ?? 'Volunteer'); ?></h2>
                            <div class="role-badge"><?php echo htmlspecialchars($volunteer_info['role'] ?? 'Volunteer'); ?></div>
                            <?php if (!empty($volunteer_info['skill_category'])): ?>
                                <div class="skill-badge"><?php echo htmlspecialchars($volunteer_info['skill_category']); ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="volunteer-id">VOL<?php echo str_pad($volunteer_id, 4, '0', STR_PAD_LEFT); ?></div>
                    
                    <div class="contact-info">
                        <?php if (!empty($volunteer_info['email'])): ?>
                        <div class="contact-item">
                            <i class="fas fa-envelope"></i>
                            <span><?php echo htmlspecialchars($volunteer_info['email']); ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($volunteer_info['phone'])): ?>
                        <div class="contact-item">
                            <i class="fas fa-phone"></i>
                            <span><?php echo htmlspecialchars($volunteer_info['phone']); ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($volunteer_info['ngo_affiliation'])): ?>
                        <div class="contact-item">
                            <i class="fas fa-hands-helping"></i>
                            <span><?php echo htmlspecialchars($volunteer_info['ngo_affiliation']); ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($volunteer_info['address'])): ?>
                        <div class="contact-item">
                            <i class="fas fa-map-marker-alt"></i>
                            <span><?php echo htmlspecialchars($volunteer_info['address']); ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <div class="contact-item">
                            <i class="fas fa-user-circle"></i>
                            <span>Status: <strong><?php echo htmlspecialchars($volunteer_info['status'] ?? 'Unknown'); ?></strong></span>
                        </div>
                    </div>
                </div>
                
                <div class="quick-actions">
                    <h3>Quick Actions</h3>
                    <?php if (!empty($upcoming_assignments)): ?>
                    <button class="action-btn" onclick="document.querySelector('.assignment-card:first-child .start-btn')?.click()">
                        <i class="fas fa-play-circle"></i> Start Next Distribution
                    </button>
                    <?php endif; ?>
                    <button class="action-btn" onclick="window.location.href='emergency.php'">
                        <i class="fas fa-exclamation-triangle"></i> Emergency Alert
                    </button>
                    <button class="action-btn" onclick="window.location.href='distribution_main.php'">
                        <i class="fas fa-home"></i> Back to Main Dashboard
                    </button>
                </div>
            </div>
            
            <!-- Right Column: Assignments -->
            <div>
                <!-- Active Assignments -->
                <div class="assignments-section">
                    <div class="section-header">
                        <h2>Active Distribution Assignments</h2>
                        <span class="badge"><?php echo count($upcoming_assignments); ?> assignments</span>
                    </div>
                    
                    <div class="assignments-list">
                        <?php if (empty($upcoming_assignments)): ?>
                            <div class="empty-state">
                                <i class="fas fa-clipboard-list"></i>
                                <h3>No Active Assignments</h3>
                                <p>You don't have any active distribution assignments. Check back later or contact your coordinator.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($upcoming_assignments as $assignment): ?>
                            <div class="assignment-card <?php echo strtolower($assignment['status']); ?>">
                                <div class="assignment-header">
                                    <h3 class="assignment-title">
                                        <?php echo htmlspecialchars($assignment['Disaster_Name']); ?>
                                    </h3>
                                    <span class="assignment-status status-<?php echo strtolower($assignment['status']); ?>">
                                        <?php echo $assignment['status']; ?>
                                    </span>
                                </div>
                                
                                <div class="assignment-details">
                                    <div class="detail-item">
                                        <i class="fas fa-hashtag"></i>
                                        <span class="detail-label">Distribution ID:</span>
                                        <span>DIST<?php echo str_pad($assignment['distribution_id'], 6, '0', STR_PAD_LEFT); ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <i class="fas fa-calendar"></i>
                                        <span class="detail-label">Date:</span>
                                        <span><?php echo date('d/m/Y', strtotime($assignment['date'])); ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <i class="fas fa-map-marker-alt"></i>
                                        <span class="detail-label">Location:</span>
                                        <span><?php echo htmlspecialchars($assignment['disaster_location']); ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <i class="fas fa-users"></i>
                                        <span class="detail-label">Victims:</span>
                                        <span><?php echo $assignment['total_victims'] ?? 0; ?> families</span>
                                    </div>
                                    <div class="detail-item">
                                        <i class="fas fa-box"></i>
                                        <span class="detail-label">Needs:</span>
                                        <span><?php echo $assignment['total_needs'] ?? 0; ?> items</span>
                                    </div>
                                    <div class="detail-item">
                                        <i class="fas fa-user-tag"></i>
                                        <span class="detail-label">Your Role:</span>
                                        <span><?php echo htmlspecialchars($assignment['role'] ?? 'Volunteer'); ?></span>
                                    </div>
                                </div>
                                
                                <div class="assignment-actions">
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="distribution_id" value="<?php echo $assignment['distribution_id']; ?>">
                                        <input type="hidden" name="action" value="start_distribution">
                                        <button type="submit" class="action-button start-btn">
                                            <i class="fas fa-play-circle"></i> Start Distribution
                                        </button>
                                    </form>
                                    
                                    <?php if ($assignment['status'] === 'Assigned'): ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="distribution_id" value="<?php echo $assignment['distribution_id']; ?>">
                                        <input type="hidden" name="action" value="confirm_assignment">
                                        <button type="submit" class="action-button confirm-btn">
                                            <i class="fas fa-check"></i> Confirm Assignment
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                    
                                    <button class="action-button details-btn" onclick="viewAssignmentDetails(<?php echo $assignment['distribution_id']; ?>)">
                                        <i class="fas fa-info-circle"></i> Details
                                    </button>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Completed Assignments -->
                <?php if (!empty($past_assignments)): ?>
                <div class="assignments-section" style="margin-top: 20px;">
                    <div class="section-header">
                        <h2>Completed Distributions</h2>
                        <span class="badge"><?php echo count($past_assignments); ?> completed</span>
                    </div>
                    
                    <div class="assignments-list">
                        <?php foreach ($past_assignments as $assignment): ?>
                        <div class="assignment-card completed">
                            <div class="assignment-header">
                                <h3 class="assignment-title">
                                    <?php echo htmlspecialchars($assignment['Disaster_Name']); ?>
                                </h3>
                                <span class="assignment-status status-completed">Completed</span>
                            </div>
                            
                            <div class="assignment-details">
                                <div class="detail-item">
                                    <i class="fas fa-hashtag"></i>
                                    <span class="detail-label">Distribution ID:</span>
                                    <span>DIST<?php echo str_pad($assignment['distribution_id'], 6, '0', STR_PAD_LEFT); ?></span>
                                </div>
                                <div class="detail-item">
                                    <i class="fas fa-calendar"></i>
                                    <span class="detail-label">Date:</span>
                                    <span><?php echo date('d/m/Y', strtotime($assignment['date'])); ?></span>
                                </div>
                                <div class="detail-item">
                                    <i class="fas fa-users"></i>
                                    <span class="detail-label">Victims Helped:</span>
                                    <span><?php echo $assignment['victims_helped'] ?? 0; ?> families</span>
                                </div>
                                <div class="detail-item">
                                    <i class="fas fa-box"></i>
                                    <span class="detail-label">Items Distributed:</span>
                                    <span><?php echo $assignment['items_distributed'] ?? 0; ?> items</span>
                                </div>
                            </div>
                            
                            <div class="assignment-actions">
                                <button class="action-button view-btn" onclick="viewDistributionReport(<?php echo $assignment['distribution_id']; ?>)">
                                    <i class="fas fa-chart-bar"></i> View Report
                                </button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
        // Update current date and time
        function updateDateTime() {
            const now = new Date();
            
            // Format date
            const options = { 
                weekday: 'long', 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric' 
            };
            const dateString = now.toLocaleDateString('en-US', options);
            
            // Format time
            let hours = now.getHours();
            let minutes = now.getMinutes();
            let seconds = now.getSeconds();
            const ampm = hours >= 12 ? 'PM' : 'AM';
            
            hours = hours % 12;
            hours = hours ? hours : 12; // the hour '0' should be '12'
            minutes = minutes < 10 ? '0' + minutes : minutes;
            seconds = seconds < 10 ? '0' + seconds : seconds;
            
            const timeString = `${hours}:${minutes}:${seconds} ${ampm}`;
            
            document.getElementById('current-date').textContent = dateString;
            document.getElementById('current-time').textContent = timeString;
        }
        
        // Initialize time and update every second
        updateDateTime();
        setInterval(updateDateTime, 1000);
        
        // View assignment details
        function viewAssignmentDetails(distributionId) {
            window.location.href = 'view_distribution.php?id=' + distributionId;
        }
        
        // View distribution report
        function viewDistributionReport(distributionId) {
            window.location.href = 'distribution_report.php?id=' + distributionId;
        }
        
        // Auto-refresh page every 60 seconds for new assignments
        setTimeout(() => {
            window.location.reload();
        }, 60000);
    </script>
</body>
</html>
