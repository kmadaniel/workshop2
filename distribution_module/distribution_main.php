<?php
// ========================================
// Enhanced Distribution Dashboard with Filters & Pagination
// distribution_main.php (index.php) - MAIN DASHBOARD
// UPDATED WITH CORRECT API AUTHENTICATION HANDLING
// ========================================

// ========================================
// CROSS-SYSTEM AUTHENTICATION CHECK
// ========================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Debug: Log all session variables
error_log("Session at start: " . json_encode($_SESSION));

// Check if user is authenticated via API bridge
// Check for both possible indicators of API authentication
$is_api_authenticated = false;

// Check multiple possible API authentication indicators
if (isset($_SESSION['admin_api_verified']) && $_SESSION['admin_api_verified'] === true) {
    $is_api_authenticated = true;
} elseif (isset($_SESSION['AdminID']) || isset($_SESSION['FullName'])) {
    // If we have API fields in session, treat as API authenticated
    $is_api_authenticated = true;
    $_SESSION['admin_api_verified'] = true;
}

// Check if API session is still valid (10 minutes)
if ($is_api_authenticated) {
    $auth_time = $_SESSION['auth_time'] ?? 0;
    $current_time = time();
    
    if (($current_time - $auth_time) > 600) { // 10 minutes expiry
        // Session expired
        session_destroy();
        header("Location: http://10.147.17.30:8000/login.php");
        exit;
    }
}

// ========================================
// USER DETECTION WITH CORRECT ORDER - FIXED
// ========================================
$user_type = 'guest';
$current_user = null;

// 1. FIRST PRIORITY: Check for API-authenticated admin
if ($is_api_authenticated) {
    $user_type = 'admin';
    
    // Get admin data from API session fields (AdminID, FullName, Email, Role)
    $admin_id = $_SESSION['AdminID'] ?? $_SESSION['user_id'] ?? 0;
    $admin_name = $_SESSION['FullName'] ?? $_SESSION['user_name'] ?? 'Admin User';
    $admin_email = $_SESSION['Email'] ?? $_SESSION['user_email'] ?? 'admin@disasterrelief.org';
    $admin_role = $_SESSION['Role'] ?? $_SESSION['user_role'] ?? 'Administrator';
    $admin_phone = $_SESSION['Phone'] ?? $_SESSION['admin_phone'] ?? $_SESSION['user_phone'] ?? '';
    
    $current_user = [
        'id' => $admin_id,
        'name' => $admin_name,
        'role' => $admin_role,
        'avatar' => substr($admin_name, 0, 2),
        'email' => $admin_email,
        'phone' => $admin_phone,
        'department' => 'System Administration',
        'api_verified' => true,
        'auth_source' => 'Main System API',
        'original_admin_id' => $_SESSION['AdminID'] ?? null
    ];
    
    // Ensure consistent session variables for the rest of the code
    if (!isset($_SESSION['user_id'])) {
        $_SESSION['user_id'] = $admin_id;
    }
    if (!isset($_SESSION['user_name'])) {
        $_SESSION['user_name'] = $admin_name;
    }
    if (!isset($_SESSION['user_email'])) {
        $_SESSION['user_email'] = $admin_email;
    }
    if (!isset($_SESSION['user_role'])) {
        $_SESSION['user_role'] = $admin_role;
    }
}
// 2. SECOND PRIORITY: Check for local staff/admin (NOT volunteer)
elseif (isset($_SESSION['user_id']) && !isset($_SESSION['volunteer_id'])) {
    $user_type = 'staff';
    $current_user = [
        'id' => $_SESSION['user_id'],
        'name' => $_SESSION['user_name'] ?? 'Admin User',
        'role' => $_SESSION['user_role'] ?? 'System Administrator',
        'avatar' => $_SESSION['user_avatar'] ?? substr($_SESSION['user_name'] ?? 'AU', 0, 2),
        'email' => $_SESSION['user_email'] ?? 'admin@disasterrelief.org',
        'phone' => $_SESSION['user_phone'] ?? '',
        'department' => $_SESSION['user_department'] ?? '',
        'api_verified' => false,
        'auth_source' => 'Local System'
    ];
}
// 3. THIRD PRIORITY: Check for volunteer (ONLY if no admin session exists)
elseif (isset($_SESSION['volunteer_id']) && !$is_api_authenticated && !isset($_SESSION['user_id'])) {
    $user_type = 'volunteer';
    $current_user = [
        'id' => $_SESSION['volunteer_id'],
        'name' => $_SESSION['volunteer_name'] ?? 'Volunteer',
        'role' => 'Volunteer',
        'avatar' => substr($_SESSION['volunteer_name'] ?? 'V', 0, 2),
        'email' => $_SESSION['volunteer_email'] ?? 'volunteer@disasterrelief.org',
        'phone' => $_SESSION['volunteer_phone'] ?? '',
        'address' => $_SESSION['volunteer_address'] ?? '',
        'ngo_affiliation' => $_SESSION['volunteer_ngo'] ?? '',
        'skill_category' => $_SESSION['volunteer_skills'] ?? ''
    ];
}
// 4. Fallback - no valid session
else {
    // If no session at all, redirect to main system login gateway
    if (!$is_api_authenticated && !isset($_SESSION['volunteer_id']) && !isset($_SESSION['user_id'])) {
        header("Location: http://10.147.17.30:8000/login.php");
        exit;
    }
    
    // Emergency fallback user
    $current_user = [
        'id' => 0,
        'name' => 'Guest User',
        'role' => 'Guest',
        'avatar' => 'GU',
        'email' => 'guest@example.com',
        'api_verified' => false
    ];
}

// ========================================
// CONTINUE WITH EXISTING LOGIC
// ========================================
require_once 'config.php';

// API URLs
$DISASTER_API_URL = 'http://10.147.17.116:8000/disaster.php';
$VICTIM_API_URL = 'http://10.147.17.116:8000/victim.php';

// Helper function for API calls
function fetchFromAPI($url) {
    try {
        $context = stream_context_create([
            'http' => [
                'timeout' => 5,
                'ignore_errors' => true,
                'header' => "Accept: application/json\r\n"
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ]
        ]);
        
        $response = @file_get_contents($url, false, $context);
        if ($response === false) {
            return null;
        }
        
        $data = json_decode($response, true);
        return $data;
    } catch (Exception $e) {
        error_log("API call failed to $url: " . $e->getMessage());
        return null;
    }
}

// Initialize variables
$stats = [
    'total_distributions' => 0,
    'pending' => 0,
    'planning' => 0,
    'assigned' => 0,
    'in_transit' => 0,
    'delivered' => 0,
    'completed' => 0,
    'on_hold' => 0,
    'cancelled' => 0
];
$recent_distributions = [];
$total_rows = 0;
$total_pages = 0;
$page = 1;
$search = '';
$status_filter = '';
$disaster_filter = 0;
$status_options = [];
$disaster_options = [];
$error = null;
$notification_count = 0;

try {
    $database = new Database();
    $db = $database->getConnection();

    // ========================================
    // GET NOTIFICATION COUNT
    // ========================================
    try {
        $notif_query = "SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND read_status = 0";
        $notif_stmt = $db->prepare($notif_query);
        if ($notif_stmt) {
            $notif_stmt->bind_param("i", $current_user['id']);
            $notif_stmt->execute();
            $notif_result = $notif_stmt->get_result();
            $notif_row = $notif_result->fetch_assoc();
            $notification_count = $notif_row ? $notif_row['count'] : 0;
            $notif_stmt->close();
        }
    } catch (Exception $e) {
        // Silently continue if notifications table doesn't exist
        $notification_count = 0;
    }

    // ========================================
    // PAGINATION & FILTER PARAMETERS
    // ========================================
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $per_page = 10; // Items per page
    $offset = ($page - 1) * $per_page;

    // Get filter parameters
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';
    $disaster_filter = isset($_GET['disaster']) ? intval($_GET['disaster']) : 0;

    // ========================================
    // GET STATISTICS - Updated for new system
    // ========================================
    $stats_query = "
        SELECT 
            COUNT(*) as total_distributions,
            SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'Planning' THEN 1 ELSE 0 END) as planning,
            SUM(CASE WHEN status = 'Assigned' THEN 1 ELSE 0 END) as assigned,
            SUM(CASE WHEN status = 'In Transit' THEN 1 ELSE 0 END) as in_transit,
            SUM(CASE WHEN status = 'Delivered' THEN 1 ELSE 0 END) as delivered,
            SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed,
            SUM(CASE WHEN status = 'On Hold' THEN 1 ELSE 0 END) as on_hold,
            SUM(CASE WHEN status = 'Cancelled' THEN 1 ELSE 0 END) as cancelled
        FROM distribution
    ";
    
    $result = $db->query($stats_query);
    
    if ($result) {
        $stats = $result->fetch_assoc();
        $result->free();
    } else {
        error_log("Failed to fetch statistics: " . $db->error);
    }

    // ========================================
    // GET DISASTER DATA FROM API ONLY
    // ========================================
    $disaster_data = fetchFromAPI($DISASTER_API_URL);
    if ($disaster_data && is_array($disaster_data)) {
        // Create lookup array for disaster names
        if (isset($disaster_data[0]['disaster_id'])) {
            // Direct array structure
            foreach ($disaster_data as $disaster) {
                if (isset($disaster['disaster_id'])) {
                    $disaster_id = $disaster['disaster_id'];
                    $disaster_name = $disaster['disaster_name'] ?? 'Unknown Disaster';
                    $disaster_options[$disaster_id] = $disaster_name;
                }
            }
        } elseif (isset($disaster_data['data']) && is_array($disaster_data['data'])) {
            // Nested in 'data' key
            foreach ($disaster_data['data'] as $disaster) {
                if (isset($disaster['disaster_id'])) {
                    $disaster_id = $disaster['disaster_id'];
                    $disaster_name = $disaster['disaster_name'] ?? 'Unknown Disaster';
                    $disaster_options[$disaster_id] = $disaster_name;
                }
            }
        }
    }
    
    // If no disasters from API, use empty array
    if (empty($disaster_options)) {
        $disaster_options = [];
    }

    // ========================================
    // BUILD FILTERED QUERY - NO JOIN WITH Disaster Table
    // ========================================
    $where_conditions = [];
    $params = [];
    $types = '';

    // Search filter - Now includes victim names from distribution_items
    if (!empty($search)) {
        $where_conditions[] = "(d.comments LIKE ? OR d.location LIKE ? OR d.coordinator_name LIKE ?)";
        $search_param = "%{$search}%";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
        $types .= 'sss';
    }

    // Status filter
    if (!empty($status_filter)) {
        $where_conditions[] = "d.status = ?";
        $params[] = $status_filter;
        $types .= 's';
    }

    // Disaster filter - using disaster_id from distribution table
    if ($disaster_filter > 0) {
        $where_conditions[] = "d.disaster_id = ?";
        $params[] = $disaster_filter;
        $types .= 'i';
    }

    // Build WHERE clause
    $where_clause = '';
    if (count($where_conditions) > 0) {
        $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
    }

    // ========================================
    // GET TOTAL COUNT FOR PAGINATION
    // ========================================
    $count_query = "
        SELECT COUNT(DISTINCT d.distribution_id) as total
        FROM distribution d
        {$where_clause}
    ";

    if (count($params) > 0) {
        $count_stmt = $db->prepare($count_query);
        $count_stmt->bind_param($types, ...$params);
        $count_stmt->execute();
        $count_result = $count_stmt->get_result();
        $total_row = $count_result->fetch_assoc();
        $total_rows = $total_row ? $total_row['total'] : 0;
        $count_stmt->close();
    } else {
        $count_result = $db->query($count_query);
        $total_row = $count_result->fetch_assoc();
        $total_rows = $total_row ? $total_row['total'] : 0;
        $count_result->free();
    }

    $total_pages = ceil($total_rows / $per_page);

    // ========================================
    // GET FILTERED DISTRIBUTIONS WITH PAGINATION - SIMPLIFIED VERSION
    // ========================================
    $distributions_query = "
        SELECT 
            d.distribution_id,
            d.date,
            d.status,
            d.comments,
            d.disaster_id,
            d.location as distribution_location,
            d.coordinator_name,
            d.coordinator_contact,
            d.estimated_duration,
            d.volunteers_needed,
            COUNT(DISTINCT di.victim_id) as victim_count,
            COUNT(DISTINCT dv.volunteer_id) as volunteer_count
        FROM distribution d
        LEFT JOIN distribution_items di ON d.distribution_id = di.distribution_id
        LEFT JOIN distribution_volunteer dv ON d.distribution_id = dv.distribution_id
        {$where_clause}
        GROUP BY d.distribution_id, d.date, d.status, d.comments, d.disaster_id, 
                 d.location, d.coordinator_name, d.coordinator_contact, 
                 d.estimated_duration, d.volunteers_needed
        ORDER BY d.date DESC, d.distribution_id DESC
        LIMIT ? OFFSET ?
    ";
    
    // Prepare statement
    $dist_stmt = $db->prepare($distributions_query);
    
    if (count($params) > 0) {
        // Add pagination parameters to existing params
        $all_params = $params;
        $all_params[] = $per_page;
        $all_params[] = $offset;
        $all_types = $types . 'ii';
        
        $dist_stmt->bind_param($all_types, ...$all_params);
    } else {
        // Only pagination parameters
        $dist_stmt->bind_param('ii', $per_page, $offset);
    }
    
    $dist_stmt->execute();
    $dist_result = $dist_stmt->get_result();
    $recent_distributions = $dist_result->fetch_all(MYSQLI_ASSOC);
    $dist_stmt->close();

    // ========================================
    // GET VICTIM DATA FROM API
    // ========================================
    $victim_data = fetchFromAPI($VICTIM_API_URL);

    // ========================================
    // GET STATUS OPTIONS
    // ========================================
    $status_options = [
        'Pending',
        'Planning',
        'Assigned',
        'In Transit',
        'Delivered',
        'Completed',
        'On Hold',
        'Cancelled'
    ];

    // Set defaults for null values in stats
    foreach ($stats as $key => $value) {
        $stats[$key] = $value ?? 0;
    }

} catch (Exception $e) {
    $error = "Database error: " . $e->getMessage();
    error_log($error);
}

// If no disaster options, show empty
if (empty($disaster_options)) {
    $disaster_options = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Distribution Dashboard - Disaster Relief System</title>
    <link rel="stylesheet" href="../css/main.css">
    <link rel="stylesheet" href="../css/header.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* API Admin Badge */
        .api-admin-badge {
            background: linear-gradient(135deg, #d32f2f 0%, #b71c1c 100%);
            color: white;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 0.7em;
            margin-left: 5px;
            font-weight: bold;
            display: inline-flex;
            align-items: center;
            gap: 3px;
        }
        
        /* Custom styles */
        .victim-names {
            font-size: 0.85em;
            color: #666;
            margin-top: 3px;
            max-width: 200px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .victim-cell {
            display: flex;
            flex-direction: column;
        }
        .victim-count-badge {
            background: #e3f2fd;
            color: #1976d2;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.8em;
            display: inline-block;
            margin-bottom: 3px;
        }
        .date-cell {
            display: flex;
            flex-direction: column;
            align-items: center;
            min-width: 70px;
        }
        .date-day {
            font-size: 1.4em;
            font-weight: bold;
            color: #333;
        }
        .date-month {
            font-size: 0.9em;
            color: #666;
            text-transform: uppercase;
        }
        .date-year {
            font-size: 0.8em;
            color: #999;
        }
        .action-buttons {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }
        .action-btn {
            padding: 5px 8px;
            min-width: 32px;
            text-align: center;
        }
        .badge-planning { background-color: #3498db; color: white; }
        .badge-assigned { background-color: #9b59b6; color: white; }
        .badge-transit { background-color: #f39c12; color: white; }
        .badge-delivered { background-color: #27ae60; color: white; }
        .badge-completed { background-color: #00b894; color: white; }
        .badge-onhold { background-color: #95a5a6; color: white; }
        .badge-cancelled { background-color: #e74c3c; color: white; }
        .badge-pending { background-color: #7f8c8d; color: white; }
        
        .status-indicator {
            font-size: 0.7em;
            margin-right: 5px;
        }
        
        .loading-screen {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.95);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            transition: opacity 0.5s ease;
        }
        
        .loading-content {
            text-align: center;
        }
        
        .loading-spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #3498db;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            animation: spin 1s linear infinite;
            margin: 0 auto 20px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
        }
        
        .toast {
            background: white;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            padding: 15px;
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            min-width: 300px;
            max-width: 400px;
            animation: slideIn 0.3s ease;
        }
        
        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        
        .toast-success {
            border-left: 4px solid #27ae60;
        }
        
        .toast-error {
            border-left: 4px solid #e74c3c;
        }
        
        .toast-info {
            border-left: 4px solid #3498db;
        }
        
        .toast-warning {
            border-left: 4px solid #f39c12;
        }
        
        .fade-out {
            opacity: 0;
            transform: translateX(100%);
            transition: all 0.3s ease;
        }
        
        /* Add new styles for location display */
        .location-cell {
            max-width: 150px;
        }
        
        .distribution-location {
            font-size: 0.9em;
            color: #666;
            margin-top: 2px;
        }
        
        .api-status-indicator {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            margin-left: 5px;
        }
        .api-online { background: #27ae60; }
        .api-offline { background: #e74c3c; }
        
        .disaster-cell {
            max-width: 150px;
        }
        
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        
        .alert-warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        
        .btn {
            padding: 10px 20px;
            border-radius: 5px;
            border: none;
            cursor: pointer;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            font-size: 0.95em;
        }
        
        .btn-info {
            background: #3498db;
            color: white;
        }
        
        .btn-primary {
            background: #007bff;
            color: white;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        
        .btn-warning {
            background: #ffc107;
            color: #212529;
        }
        
        .btn-success {
            background: #28a745;
            color: white;
        }
        
        .btn-purple {
            background: #6f42c1;
            color: white;
        }
        
        .btn-orange {
            background: #fd7e14;
            color: white;
        }
        
        .btn-sm {
            padding: 5px 10px;
            font-size: 0.85em;
        }
        
        .badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 0.8em;
            font-weight: 600;
        }
        
        .badge-info {
            background: #17a2b8;
            color: white;
        }
        
        .badge-light {
            background: #f8f9fa;
            color: #212529;
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 10000;
            justify-content: center;
            align-items: center;
        }
        
        .modal-content {
            background: white;
            border-radius: 8px;
            width: 90%;
            max-width: 800px;
            max-height: 80vh;
            overflow-y: auto;
        }
        
        .modal-header {
            padding: 20px;
            border-bottom: 1px solid #dee2e6;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-body {
            padding: 20px;
        }
        
        .modal-footer {
            padding: 20px;
            border-top: 1px solid #dee2e6;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
        
        .modal-close {
            background: none;
            border: none;
            font-size: 1.5em;
            cursor: pointer;
        }
        
        .pagination-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 20px;
            padding: 15px;
            border-top: 1px solid #dee2e6;
        }
        
        .pagination {
            display: flex;
            gap: 5px;
        }
        
        .page-link {
            padding: 8px 12px;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            text-decoration: none;
            color: #007bff;
        }
        
        .page-link.active {
            background: #007bff;
            color: white;
            border-color: #007bff;
        }
        
        .page-link:hover:not(.active) {
            background: #f8f9fa;
        }
        
        .pagination-info {
            color: #6c757d;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #6c757d;
        }
        
        .empty-icon {
            font-size: 4em;
            margin-bottom: 20px;
            opacity: 0.5;
        }
        
        /* Header logout button */
        .logout-section {
            margin-left: 10px;
        }
        
        .logout-btn {
            background: #e74c3c;
            color: white;
            border: none;
            padding: 8px 12px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
        }
        
        .logout-btn:hover {
            background: #c0392b;
        }
        
        /* Debug panel - REMOVE AFTER TESTING */
        .debug-panel {
            position: fixed;
            bottom: 10px;
            right: 10px;
            background: white;
            padding: 15px;
            border: 2px solid #d32f2f;
            border-radius: 8px;
            z-index: 9999;
            font-size: 11px;
            max-width: 300px;
            box-shadow: 0 0 10px rgba(0,0,0,0.2);
            font-family: monospace;
        }
        
        .debug-panel h4 {
            margin: 0 0 10px 0;
            color: #d32f2f;
            border-bottom: 1px solid #eee;
            padding-bottom: 5px;
        }
        
        .debug-panel p {
            margin: 5px 0;
            line-height: 1.4;
        }
        
        .debug-panel .label {
            font-weight: bold;
            color: #333;
            min-width: 100px;
            display: inline-block;
        }
        
        .debug-panel .value {
            color: #007bff;
        }
    </style>
</head>
<body>
    <!-- Loading Screen -->
    <div id="loading-screen" class="loading-screen">
        <div class="loading-content">
            <div class="loading-spinner"></div>
            <h3>Loading Dashboard</h3>
            <p>Please wait while we load your distribution data...</p>
        </div>
    </div>


    <!-- System Header -->
    <header class="system-header">
        <div class="header-container">
            <div class="logo-section">
                <button class="mobile-toggle" id="sidebarToggle">
                    <i class="fas fa-bars"></i>
                </button>
                <div class="logo-icon">
                    <i class="fas fa-hands-helping"></i>
                </div>
                <div class="logo-text">
                    <h1>Disaster Relief System</h1>
                    <small>Distribution Management Dashboard</small>
                </div>
            </div>
            
            <div class="header-controls">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" placeholder="Search distributions, victims, needs..." id="globalSearch" value="<?php echo htmlspecialchars($search); ?>">
                    <span class="api-status-indicator <?php echo ($disaster_data ? 'api-online' : 'api-offline'); ?>" 
                          title="<?php echo ($disaster_data ? 'API Online' : 'API Offline'); ?>"></span>
                </div>
                
                <div class="notifications" id="notificationsBtn">
                    <i class="fas fa-bell"></i>
                    <?php if ($notification_count > 0): ?>
                    <span class="notification-badge"><?php echo $notification_count; ?></span>
                    <?php endif; ?>
                </div>
                
                <div class="user-profile" id="userProfile">
                    <div class="user-avatar">
                        <?php echo $current_user['avatar']; ?>
                    </div>
                    <div class="user-info">
                        <div class="user-name"><?php echo htmlspecialchars($current_user['name']); ?></div>
                        <div class="user-role"><?php echo htmlspecialchars($current_user['role']); ?>
                            <?php if ($is_api_authenticated): ?>
                            <span class="api-admin-badge">
                                <i class="fas fa-shield-alt"></i> API Admin
                            </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <i class="fas fa-chevron-down"></i>
                </div>
                
                <!-- Logout Button -->
                <div class="logout-section">
                    <form method="POST" action="logout_api.php" style="display: inline;">
                        <button type="submit" class="logout-btn" title="Logout">
                            <i class="fas fa-sign-out-alt"></i>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </header>

    <!-- Sidebar Navigation -->
    <nav class="sidebar" id="sidebar">
        <ul class="nav-menu">
            <li class="nav-label">MAIN NAVIGATION</li>
            
            <li class="nav-item">
                <a href="distribution_main.php" class="nav-link active">
                    <i class="fas fa-tachometer-alt"></i>
                    <span class="nav-text">Dashboard</span>
                </a>
            </li>
            
            <li class="nav-item">
                <a href="create_distribution_plan.php" class="nav-link">
                    <i class="fas fa-plus-circle"></i>
                    <span class="nav-text">Create Distribution</span>
                </a>
            </li>
            
            <li class="nav-divider"></li>
            <li class="nav-label">MANAGEMENT</li>
            
            <li class="nav-item">
                <a href="manage_needs.php" class="nav-link">
                    <i class="fas fa-clipboard-check"></i>
                    <span class="nav-text">Manage Needs</span>
                    <span class="badge badge-info" style="margin-left: auto;"><?php echo $stats['planning']; ?></span>
                </a>
            </li>

              <li class="nav-item">
                <a href="manage_execution.php" class="nav-link">
                    <i class="fas fa-users"></i>
                    <span class="nav-text">Manage Execution</span>
                    <span class="badge badge-success" style="margin-left: auto;">24</span>
                </a>
            </li>

            <!-- Logout Section at bottom -->
            <li class="nav-divider"></li>
            <li class="nav-item logout-item">
                <a href="logout_api.php" class="nav-link logout-link">
                    <i class="fas fa-sign-out-alt"></i>
                    <span class="nav-text">Logout</span>
                </a>
            </li>
        </ul>
    </nav>

    <!-- Main Content Area -->
    <main class="main-content" id="mainContent">
        <!-- User Profile Dropdown -->
        <div class="user-dropdown" id="userDropdown">
            <div class="dropdown-header">
                <div class="user-avatar" style="width: 60px; height: 60px; margin-bottom: 10px; background: #3498db; color: white; display: flex; align-items: center; justify-content: center; border-radius: 50%; font-weight: bold; font-size: 20px;">
                    <?php echo $current_user['avatar']; ?>
                </div>
                <h4 style="margin: 0 0 5px 0;"><?php echo htmlspecialchars($current_user['name']); ?></h4>
                <p style="margin: 0; opacity: 0.9; font-size: 13px;">
                    <?php echo htmlspecialchars($current_user['role']); ?>
                    <?php if ($is_api_authenticated): ?>
                    <span style="color: #d32f2f; font-weight: bold;"> (API Verified)</span>
                    <?php endif; ?>
                </p>
                <p style="margin: 5px 0 0 0; font-size: 12px; opacity: 0.8;"><?php echo htmlspecialchars($current_user['email']); ?></p>
                <?php if (!empty($current_user['phone'])): ?>
                <p style="margin: 5px 0 0 0; font-size: 11px; color: #666;">📞 <?php echo htmlspecialchars($current_user['phone']); ?></p>
                <?php endif; ?>
            </div>
            
            <div class="dropdown-menu">
                <?php if ($is_api_authenticated): ?>
                    <!-- API Admin Profile Link -->
                    <div class="dropdown-item" style="background: #fff5f5; border-left: 3px solid #d32f2f;">
                        <i class="fas fa-shield-alt" style="color: #d32f2f;"></i>
                        <span>Verified via Main System API</span>
                    </div>
                    
                    <a href="http://10.147.17.30:8000/admin_profile.php" class="dropdown-item" target="_blank">
                        <i class="fas fa-user" style="color: #3498db;"></i>
                        <span>View Main Profile</span>
                    </a>
                <?php else: ?>
                    <!-- Local Admin/Staff Profile Link -->
                    <a href="profile.php" class="dropdown-item">
                        <i class="fas fa-user" style="color: #3498db;"></i>
                        <span>My Profile</span>
                    </a>
                <?php endif; ?>
                
                <a href="notifications.php" class="dropdown-item">
                    <i class="fas fa-bell" style="color: #f39c12;"></i>
                    <span>Notifications</span>
                    <?php if ($notification_count > 0): ?>
                    <span class="badge" style="background: #f44336; color: white; padding: 2px 8px; border-radius: 10px; font-size: 11px; margin-left: auto;">
                        <?php echo $notification_count; ?>
                    </span>
                    <?php endif; ?>
                </a>
                
                <div style="height: 1px; background: #eee; margin: 10px 0;"></div>
                
                <a href="logout_api.php" class="dropdown-item">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </div>
            
            <div class="dropdown-footer">
                <?php if ($is_api_authenticated): ?>
                <small>Authenticated via Main System API</small><br>
                <?php endif; ?>
                <small>Session started: <?php echo date('h:i A', $_SESSION['auth_time'] ?? time()); ?></small>
            </div>
        </div>

        <!-- Notifications Dropdown -->
        <div class="notifications-dropdown" id="notificationsDropdown">
            <div class="dropdown-header" style="padding: 15px 20px; border-bottom: 1px solid #eee;">
                <h4 style="margin: 0; font-size: 16px;">Notifications</h4>
                <button id="markAllRead" style="background: none; border: none; color: #3498db; font-size: 12px; cursor: pointer;">Mark all as read</button>
            </div>
            
            <div class="dropdown-menu">
                <?php
                // Fetch notifications from database
                $notifications = [];
                try {
                    $notif_query = "SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 5";
                    $notif_stmt = $db->prepare($notif_query);
                    if ($notif_stmt) {
                        $notif_stmt->bind_param("i", $current_user['id']);
                        $notif_stmt->execute();
                        $notif_result = $notif_stmt->get_result();
                        while ($notif_row = $notif_result->fetch_assoc()) {
                            $notifications[] = $notif_row;
                        }
                        $notif_stmt->close();
                    }
                } catch (Exception $e) {
                    // Silently continue
                }
                
                if (empty($notifications)): ?>
                <div class="notification-item" style="padding: 20px; text-align: center; color: #95a5a6;">
                    <i class="fas fa-bell-slash" style="font-size: 24px; margin-bottom: 10px;"></i>
                    <p>No notifications</p>
                </div>
                <?php else: 
                    foreach ($notifications as $notif): 
                        $icon = 'fas fa-bell';
                        $color = '#3498db';
                        $bg_color = $notif['read_status'] == 0 ? '#f8fdff' : 'white';
                ?>
                <div class="notification-item <?php echo $notif['read_status'] == 0 ? 'unread' : ''; ?>" 
                     style="padding: 15px 20px; border-bottom: 1px solid #f5f5f5; background: <?php echo $bg_color; ?>; cursor: pointer;"
                     onclick="window.location.href='notifications.php'">
                    <div style="display: flex; gap: 10px;">
                        <div style="color: <?php echo $color; ?>; font-size: 18px;">
                            <i class="<?php echo $icon; ?>"></i>
                        </div>
                        <div style="flex: 1;">
                            <strong style="font-size: 14px;"><?php echo htmlspecialchars($notif['title']); ?></strong>
                            <p style="margin: 5px 0 0 0; font-size: 13px; color: #666;"><?php echo htmlspecialchars($notif['message']); ?></p>
                            <small style="color: #95a5a6;"><?php echo date('M j, g:i A', strtotime($notif['created_at'])); ?></small>
                        </div>
                    </div>
                </div>
                <?php endforeach; 
                endif; ?>
            </div>
            
            <div class="dropdown-footer" style="padding: 12px 20px; text-align: center; background: #f8f9fa;">
                <a href="notifications.php" style="color: #3498db; text-decoration: none; font-size: 13px;">View all notifications</a>
            </div>
        </div>

        <!-- Error Alert -->
        <?php if (isset($error)): ?>
            <div class="alert alert-danger">
                <strong>⚠️ Error:</strong> <?php echo htmlspecialchars($error); ?>
                <p><small>Please check your database connection and table structure.</small></p>
            </div>
        <?php endif; ?>

        <!-- Top Action Header -->
        <div class="top-action-header animated-card">
            <h2><i class="fas fa-tasks"></i> Distribution Management Quick Actions</h2>
            <div class="action-buttons-grid">
                <!-- All Distributions -->
                <a href="#distributions-table" class="action-header-btn btn-view" onclick="document.querySelector('#distributions-table').scrollIntoView({behavior: 'smooth'})">
                    <i class="fas fa-list"></i>
                    <div class="btn-text">
                        View All Distributions
                        <small>See complete list below</small>
                    </div>
                </a>
            </div>
        </div>

        <!-- Page Header -->
        <div class="page-header">
            <div class="header-content">
                <h2>
                    <span class="animated-icon">📦</span> Distribution Dashboard
                    <?php if ($is_api_authenticated): ?>
                    <span style="font-size: 0.6em; color: #d32f2f; margin-left: 10px; font-weight: bold;">
                        <i class="fas fa-shield-alt"></i> API-AUTHENTICATED ADMIN
                    </span>
                    <?php endif; ?>
                    <small style="font-size: 0.6em; color: #666; margin-left: 10px;">
                        API Status: 
                        <span class="api-status-indicator <?php echo ($disaster_data ? 'api-online' : 'api-offline'); ?>"></span>
                        <?php echo ($disaster_data ? 'Connected' : 'Offline'); ?>
                    </small>
                </h2>
                <div class="header-actions">
                    <button id="refresh-btn" class="btn btn-info">
                        <i class="fas fa-sync-alt"></i> Refresh
                    </button>
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card animated-card">
                <h3>Total Distributions</h3>
                <div class="stat-value"><?php echo number_format($stats['total_distributions']); ?></div>
                <span class="stat-icon">📦</span>
            </div>
            
            <div class="stat-card pending animated-card">
                <h3>Planning</h3>
                <div class="stat-value" style="color: #3498db;">
                    <?php echo number_format($stats['planning']); ?>
                </div>
                <span class="stat-icon">📋</span>
            </div>
            
            <div class="stat-card assigned animated-card">
                <h3>Assigned</h3>
                <div class="stat-value" style="color: #9b59b6;">
                    <?php echo number_format($stats['assigned']); ?>
                </div>
                <span class="stat-icon">👥</span>
            </div>
            
            <div class="stat-card transit animated-card">
                <h3>In Transit</h3>
                <div class="stat-value" style="color: #f39c12;">
                    <?php echo number_format($stats['in_transit']); ?>
                </div>
                <span class="stat-icon">🚚</span>
            </div>
            
            <div class="stat-card delivered animated-card">
                <h3>Delivered</h3>
                <div class="stat-value" style="color: #27ae60;">
                    <?php echo number_format($stats['delivered']); ?>
                </div>
                <span class="stat-icon">✅</span>
            </div>
            
            <div class="stat-card completed animated-card">
                <h3>Completed</h3>
                <div class="stat-value" style="color: #00b894;">
                    <?php echo number_format($stats['completed']); ?>
                </div>
                <span class="stat-icon">🎯</span>
            </div>
        </div>

        <!-- Filters and Search -->
        <div class="card animated-card">
            <div class="card-header">
                <h2><i class="fas fa-filter"></i> Filters & Search</h2>
            </div>
            <div class="card-body">
                <form id="filter-form" method="GET" class="filter-form">
                    <div class="row">
                        <div class="col-4">
                            <div class="form-group">
                                <label for="search"><i class="fas fa-search"></i> Search</label>
                                <input type="text" id="search" name="search" class="form-control" 
                                       placeholder="Search by disaster, victim, location, or comments..." 
                                       value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="form-group">
                                <label for="status"><i class="fas fa-tag"></i> Status</label>
                                <select id="status" name="status" class="form-control">
                                    <option value="">All Statuses</option>
                                    <?php foreach ($status_options as $status): ?>
                                        <option value="<?php echo $status; ?>" 
                                            <?php echo $status_filter === $status ? 'selected' : ''; ?>>
                                            <?php echo $status; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="form-group">
                                <label for="disaster"><i class="fas fa-exclamation-triangle"></i> Disaster</label>
                                <select id="disaster" name="disaster" class="form-control">
                                    <option value="">All Disasters</option>
                                    <?php foreach ($disaster_options as $id => $name): ?>
                                        <option value="<?php echo $id; ?>" 
                                            <?php echo $disaster_filter == $id ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($name); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-filter"></i> Apply Filters
                        </button>
                        <a href="?" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Clear Filters
                        </a>
                        <div class="results-count">
                            Showing <?php echo count($recent_distributions); ?> of <?php echo $total_rows; ?> distributions
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Recent Distributions Table - FIXED FOR CORRECT API STRUCTURE -->
        <div class="card animated-card">
            <div class="card-header">
                <h2>
                    <i class="fas fa-list"></i> Recent Distributions
                    <?php if ($is_api_authenticated): ?>
                    <span style="font-size: 0.7em; color: #d32f2f; margin-left: 10px;">
                        <i class="fas fa-eye"></i> Full Administrative Access
                    </span>
                    <?php endif; ?>
                </h2>
                <div class="header-badge">
                    <span class="badge badge-info"><?php echo $total_rows; ?> total</span>
                </div>
            </div>

            <div class="table-container">
                <?php if (count($recent_distributions) > 0): ?>
                <table id="distributions-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Date</th>
                            <th>Disaster</th>
                            <th>Victims</th>
                            <th>Location</th>
                            <th>Coordinator</th>
                            <th>Volunteers</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        // Create a lookup array for disaster names from API
                        $disaster_lookup = [];
                        if (isset($disaster_data) && is_array($disaster_data)) {
                            // If it's already parsed as an array
                            if (isset($disaster_data[0]['disaster_id'])) {
                                // Direct array structure
                                foreach ($disaster_data as $disaster) {
                                    if (isset($disaster['disaster_id'])) {
                                        $disaster_lookup[$disaster['disaster_id']] = $disaster['disaster_name'] ?? 'Unknown Disaster';
                                    }
                                }
                            } 
                            // If it's in a 'data' key
                            elseif (isset($disaster_data['data']) && is_array($disaster_data['data'])) {
                                foreach ($disaster_data['data'] as $disaster) {
                                    if (isset($disaster['disaster_id'])) {
                                        $disaster_lookup[$disaster['disaster_id']] = $disaster['disaster_name'] ?? 'Unknown Disaster';
                                    }
                                }
                            }
                        }
                        
                        // Create a lookup array for victim names from API
                        $victim_lookup = [];
                        if (isset($victim_data) && is_array($victim_data)) {
                            // If it's already parsed as an array
                            if (isset($victim_data[0]['victim_id'])) {
                                // Direct array structure
                                foreach ($victim_data as $victim) {
                                    if (isset($victim['victim_id'])) {
                                        $victim_lookup[$victim['victim_id']] = $victim['full_name'] ?? $victim['name'] ?? 'Unknown Victim';
                                    }
                                }
                            } 
                            // If it's in a 'data' key
                            elseif (isset($victim_data['data']) && is_array($victim_data['data'])) {
                                foreach ($victim_data['data'] as $victim) {
                                    if (isset($victim['victim_id'])) {
                                        $victim_lookup[$victim['victim_id']] = $victim['full_name'] ?? $victim['name'] ?? 'Unknown Victim';
                                    }
                                }
                            }
                        }
                        
                        foreach ($recent_distributions as $row): 
                            // Get disaster name from lookup array
                            $disaster_id = $row['disaster_id'];
                            $disaster_name = isset($disaster_lookup[$disaster_id]) 
                                ? $disaster_lookup[$disaster_id] 
                                : "Disaster #" . $disaster_id;
                            
                            // Get victim names for this distribution
                            $victim_names_array = [];
                            $victim_count = $row['victim_count'] ?? 0;
                            
                            if ($victim_count > 0) {
                                $victim_ids_query = "SELECT victim_id FROM distribution_items WHERE distribution_id = ?";
                                $victim_stmt = $db->prepare($victim_ids_query);
                                $victim_stmt->bind_param("i", $row['distribution_id']);
                                $victim_stmt->execute();
                                $victim_result = $victim_stmt->get_result();
                                $victim_ids = $victim_result->fetch_all(MYSQLI_ASSOC);
                                $victim_stmt->close();
                                
                                if (!empty($victim_ids)) {
                                    foreach ($victim_ids as $vid) {
                                        $victim_id = $vid['victim_id'];
                                        if (isset($victim_lookup[$victim_id])) {
                                            $victim_names_array[] = $victim_lookup[$victim_id];
                                        } else {
                                            // Try alternative victim ID formats
                                            $found = false;
                                            foreach ($victim_lookup as $key => $name) {
                                                if ((string)$key == (string)$victim_id) {
                                                    $victim_names_array[] = $name;
                                                    $found = true;
                                                    break;
                                                }
                                            }
                                            if (!$found) {
                                                $victim_names_array[] = "Victim #" . $victim_id;
                                            }
                                        }
                                    }
                                }
                            }
                            
                            // Format victim names for display
                            $victim_names_display = '';
                            $victim_names_full = '';
                            if (!empty($victim_names_array)) {
                                $victim_names_full = implode(', ', $victim_names_array);
                                $victim_names_display = implode(', ', array_slice($victim_names_array, 0, 3));
                                if (count($victim_names_array) > 3) {
                                    $victim_names_display .= '... (+' . (count($victim_names_array) - 3) . ' more)';
                                }
                            }
                        ?>
                        <tr class="table-row" id="row-<?php echo $row['distribution_id']; ?>">
                            <td><strong>#<?php echo str_pad($row['distribution_id'], 4, '0', STR_PAD_LEFT); ?></strong></td>
                            
                            <td>
                                <div class="date-cell">
                                    <div class="date-day"><?php echo date('d', strtotime($row['date'])); ?></div>
                                    <div class="date-month"><?php echo date('M', strtotime($row['date'])); ?></div>
                                    <div class="date-year"><?php echo date('Y', strtotime($row['date'])); ?></div>
                                </div>
                            </td>
                            
                            <td>
                                <div class="disaster-cell">
                                    <strong><?php echo htmlspecialchars($disaster_name); ?></strong>
                                    <div style="font-size: 0.8em; color: #666;">
                                        ID: <?php echo $disaster_id; ?>
                                        <?php if (isset($disaster_lookup[$disaster_id])): ?>
                                        <span style="color: #27ae60; margin-left: 5px;">
                                            <i class="fas fa-check-circle"></i> API Verified
                                        </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            
                            <td>
                                <div class="victim-cell">
                                    <div>
                                        <span class="victim-count-badge">
                                            <i class="fas fa-user"></i> <?php echo $victim_count; ?>
                                        </span>
                                    </div>
                                    <?php if (!empty($victim_names_display)): ?>
                                        <div class="victim-names" title="<?php echo htmlspecialchars($victim_names_full); ?>">
                                            <?php echo htmlspecialchars($victim_names_display); ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="victim-names" style="color: #999;">
                                            <i>No victims assigned</i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </td>
                            
                            <td>
                                <div class="location-cell">
                                    <i class="fas fa-map-marker-alt"></i>
                                    <div><?php echo htmlspecialchars($row['distribution_location'] ?? 'N/A'); ?></div>
                                </div>
                            </td>
                            
                            <td>
                                <?php if (!empty($row['coordinator_name'])): ?>
                                    <div style="font-weight: 500;"><?php echo htmlspecialchars($row['coordinator_name']); ?></div>
                                    <?php if (!empty($row['coordinator_contact'])): ?>
                                    <div style="font-size: 0.8em; color: #666;">
                                        <?php echo htmlspecialchars($row['coordinator_contact']); ?>
                                    </div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <div style="color: #999; font-style: italic;">Not assigned</div>
                                <?php endif; ?>
                            </td>
                            
                            <td>
                                <?php if ($row['volunteer_count'] > 0): ?>
                                    <div class="volunteer-cell">
                                        <i class="fas fa-users"></i>
                                        <span class="volunteer-count"><?php echo $row['volunteer_count']; ?></span>
                                    </div>
                                <?php else: ?>
                                    <div class="volunteer-cell no-volunteers">
                                        <i class="fas fa-user-times"></i>
                                        <span>None</span>
                                    </div>
                                <?php endif; ?>
                            </td>
                            
                            <td>
                                <?php
                                $status_class = '';
                                switch($row['status']) {
                                    case 'Pending': $status_class = 'badge-pending'; break;
                                    case 'Planning': $status_class = 'badge-planning'; break;
                                    case 'Assigned': $status_class = 'badge-assigned'; break;
                                    case 'In Transit': $status_class = 'badge-transit'; break;
                                    case 'Delivered': $status_class = 'badge-delivered'; break;
                                    case 'Completed': $status_class = 'badge-completed'; break;
                                    case 'Cancelled': $status_class = 'badge-cancelled'; break;
                                    case 'On Hold': $status_class = 'badge-onhold'; break;
                                    default: $status_class = 'badge-pending';
                                }
                                ?>
                                <span class="badge <?php echo $status_class; ?>">
                                    <i class="fas fa-circle status-indicator"></i>
                                    <?php echo $row['status']; ?>
                                </span>
                            </td>
                            
                            <td>
                                <div class="action-buttons">
                                    <!-- Eye icon - View Details -->
                                    <a href="view_distribution.php?id=<?php echo $row['distribution_id']; ?>" 
                                       class="btn btn-info btn-sm action-btn" title="View Details">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    
                                    <!-- Edit icon - Update Status -->
                                    <?php if ($row['status'] != 'Completed' && $row['status'] != 'Cancelled'): ?>
                                        <a href="update_status.php?id=<?php echo $row['distribution_id']; ?>" 
                                           class="btn btn-warning btn-sm action-btn" title="Update Status">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                    <?php endif; ?>
                                    
                                    <!-- Assign Volunteers button -->
                                    <?php if ($row['status'] == 'Planning' || $row['status'] == 'Pending'): ?>
                                        <a href="assign_volunteer.php?distribution_id=<?php echo $row['distribution_id']; ?>" 
                                           class="btn btn-purple btn-sm action-btn" title="Assign Volunteers">
                                            <i class="fas fa-user-plus"></i>
                                        </a>
                                    <?php endif; ?>
                                    
                                    <!-- Execute button -->
                                    <?php if ($row['status'] == 'Assigned'): ?>
                                        <a href="execute_distribution.php?distribution_id=<?php echo $row['distribution_id']; ?>" 
                                           class="btn btn-orange btn-sm action-btn" title="Execute Distribution">
                                            <i class="fas fa-play-circle"></i>
                                        </a>
                                    <?php endif; ?>

                                    <!-- Delete button (only for admins) -->
                                    <?php if ($is_api_authenticated && $row['status'] == 'Assigned'): ?>
                                        <button class="btn btn-danger btn-sm delete-btn" 
                                                data-id="<?php echo $row['distribution_id']; ?>"
                                                data-name="Distribution #<?php echo str_pad($row['distribution_id'], 4, '0', STR_PAD_LEFT); ?>"
                                                title="Delete Distribution (Admin Only)">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    <?php endif; ?>
                                    
                                    <!-- Quick View button -->
                                    <button class="btn btn-secondary btn-sm quick-view-btn" 
                                            data-id="<?php echo $row['distribution_id']; ?>" 
                                            data-victims="<?php echo htmlspecialchars($victim_names_display); ?>"
                                            data-victims-full="<?php echo htmlspecialchars($victim_names_full); ?>"
                                            data-disaster="<?php echo htmlspecialchars($disaster_name); ?>"
                                            data-location="<?php echo htmlspecialchars($row['distribution_location'] ?? ''); ?>"
                                            data-coordinator="<?php echo htmlspecialchars($row['coordinator_name'] ?? ''); ?>"
                                            data-status="<?php echo $row['status']; ?>"
                                            data-date="<?php echo date('Y-m-d', strtotime($row['date'])); ?>"
                                            data-time="<?php echo date('H:i', strtotime($row['date'])); ?>"
                                            title="Quick View">
                                        <i class="fas fa-info-circle"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <div class="pagination-container">
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => 1])); ?>" class="page-link first">
                                <i class="fas fa-angle-double-left"></i>
                            </a>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" class="page-link prev">
                                <i class="fas fa-angle-left"></i>
                            </a>
                        <?php endif; ?>
                        
                        <?php
                        $start_page = max(1, $page - 2);
                        $end_page = min($total_pages, $page + 2);
                        
                        for ($i = $start_page; $i <= $end_page; $i++):
                        ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" 
                               class="page-link <?php echo $i == $page ? 'active' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" class="page-link next">
                                <i class="fas fa-angle-right"></i>
                            </a>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $total_pages])); ?>" class="page-link last">
                                <i class="fas fa-angle-double-right"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                    <div class="pagination-info">
                        Page <?php echo $page; ?> of <?php echo $total_pages; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php else: ?>
                <div class="empty-state">
                    <div class="empty-icon">
                        <i class="fas fa-box-open"></i>
                    </div>
                    <h3>No distributions found</h3>
                    <p>Get started by creating your first distribution!</p>
                    <br>
                    <div class="row">
                        <div class="col-6">
                            <a href="manage_needs.php" class="btn btn-warning">
                                <i class="fas fa-clipboard-check"></i> Manage Needs First
                            </a>
                        </div>
                        <div class="col-6">
                            <a href="create_distribution_plan.php" class="btn btn-primary">
                                <i class="fas fa-plus"></i> Create Distribution
                            </a>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- Quick View Modal -->
    <div id="quick-view-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Distribution Quick View</h3>
                <button class="modal-close">&times;</button>
            </div>
            <div class="modal-body">
                <div id="quick-view-content">
                    <!-- Content loaded via JavaScript -->
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="delete-modal" class="modal">
        <div class="modal-content" style="max-width: 500px;">
            <div class="modal-header">
                <h3>Confirm Deletion</h3>
                <button class="modal-close" id="delete-modal-close">&times;</button>
            </div>
            <div class="modal-body">
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle"></i>
                    <strong>Warning:</strong> This action cannot be undone!
                </div>
                <p id="delete-message"></p>
                <div id="delete-details" class="mt-3"></div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" id="delete-cancel">Cancel</button>
                <button class="btn btn-danger" id="delete-confirm">Delete Distribution</button>
            </div>
        </div>
    </div>

    <!-- Toast Notifications -->
    <div id="toast-container" class="toast-container"></div>

    <script>
        // Wait for DOM to be fully loaded
        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOM loaded, initializing dashboard...');
            
            // Show API admin welcome message
            <?php if ($is_api_authenticated): ?>
            setTimeout(() => {
                showToast('Welcome Admin! Authenticated via Main System API.', 'success');
            }, 1000);
            <?php endif; ?>
            
            // Initialize dropdowns
            const userProfile = document.getElementById('userProfile');
            const userDropdown = document.getElementById('userDropdown');
            const notificationsBtn = document.getElementById('notificationsBtn');
            const notificationsDropdown = document.getElementById('notificationsDropdown');
            
            // Hide dropdowns initially
            if (userDropdown) userDropdown.style.display = 'none';
            if (notificationsDropdown) notificationsDropdown.style.display = 'none';
            
            // User profile dropdown functionality
            if (userProfile && userDropdown) {
                userProfile.addEventListener('click', function(e) {
                    e.stopPropagation();
                    
                    // Toggle user dropdown
                    if (userDropdown.style.display === 'none' || userDropdown.style.display === '') {
                        userDropdown.style.display = 'block';
                        if (notificationsDropdown) {
                            notificationsDropdown.style.display = 'none';
                        }
                    } else {
                        userDropdown.style.display = 'none';
                    }
                });
            }
            
            // Notifications dropdown functionality
            if (notificationsBtn && notificationsDropdown) {
                notificationsBtn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    
                    // Toggle notifications dropdown
                    if (notificationsDropdown.style.display === 'none' || notificationsDropdown.style.display === '') {
                        notificationsDropdown.style.display = 'block';
                        if (userDropdown) {
                            userDropdown.style.display = 'none';
                        }
                    } else {
                        notificationsDropdown.style.display = 'none';
                    }
                });
            }
            
            // Close dropdowns when clicking outside
            document.addEventListener('click', function(e) {
                if (userDropdown && !userProfile.contains(e.target) && !userDropdown.contains(e.target)) {
                    userDropdown.style.display = 'none';
                }
                if (notificationsDropdown && !notificationsBtn.contains(e.target) && !notificationsDropdown.contains(e.target)) {
                    notificationsDropdown.style.display = 'none';
                }
            });
            
            // Search functionality
            const globalSearch = document.getElementById('globalSearch');
            if (globalSearch) {
                globalSearch.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        const searchTerm = this.value.trim();
                        if (searchTerm) {
                            const urlParams = new URLSearchParams(window.location.search);
                            urlParams.set('search', searchTerm);
                            urlParams.set('page', '1');
                            window.location.href = '?' + urlParams.toString();
                        } else {
                            const urlParams = new URLSearchParams(window.location.search);
                            urlParams.delete('search');
                            window.location.href = '?' + urlParams.toString();
                        }
                    }
                });
            }
            
            // Mark all notifications as read
            const markAllReadBtn = document.getElementById('markAllRead');
            if (markAllReadBtn) {
                markAllReadBtn.addEventListener('click', function() {
                    // Send AJAX request to mark all as read
                    fetch('mark_notifications_read.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'user_id=<?php echo $current_user["id"]; ?>'
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Remove unread styles
                            document.querySelectorAll('.notification-item.unread').forEach(item => {
                                item.classList.remove('unread');
                                item.style.background = 'white';
                            });
                            // Hide notification badge
                            const notificationBadge = document.querySelector('.notification-badge');
                            if (notificationBadge) {
                                notificationBadge.style.display = 'none';
                            }
                            showToast('All notifications marked as read', 'success');
                        }
                    })
                    .catch(error => {
                        console.error('Error marking notifications as read:', error);
                    });
                });
            }
            
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
                }, 800);
            });
            
            // Refresh button
            const refreshBtn = document.getElementById('refresh-btn');
            if (refreshBtn) {
                refreshBtn.addEventListener('click', function() {
                    this.classList.add('refreshing');
                    showToast('Refreshing dashboard data...', 'info');
                    setTimeout(() => {
                        location.reload();
                    }, 500);
                });
            }
            
            // Sidebar toggle
            const sidebarToggle = document.getElementById('sidebarToggle');
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('mainContent');
            
            if (sidebarToggle && sidebar && mainContent) {
                sidebarToggle.addEventListener('click', function() {
                    sidebar.classList.toggle('active');
                    mainContent.classList.toggle('main-content-expanded');
                });
            }
            
            // Filter form auto-submit
            const statusSelect = document.getElementById('status');
            const disasterSelect = document.getElementById('disaster');
            
            if (statusSelect) {
                statusSelect.addEventListener('change', function() {
                    document.getElementById('filter-form').submit();
                });
            }
            
            if (disasterSelect) {
                disasterSelect.addEventListener('change', function() {
                    document.getElementById('filter-form').submit();
                });
            }
            
            // Quick view modal
            const quickViewButtons = document.querySelectorAll('.quick-view-btn');
            const modal = document.getElementById('quick-view-modal');
            const modalContent = document.getElementById('quick-view-content');
            
            if (quickViewButtons.length > 0 && modal && modalContent) {
                quickViewButtons.forEach(button => {
                    button.addEventListener('click', function() {
                        const distributionId = this.getAttribute('data-id');
                        // Use data-victims-full attribute for full list of victim names
                        const victimNames = this.getAttribute('data-victims-full') || 
                                          this.getAttribute('data-victims') || 
                                          'None';
                        const disasterName = this.getAttribute('data-disaster');
                        const location = this.getAttribute('data-location');
                        const coordinator = this.getAttribute('data-coordinator');
                        const status = this.getAttribute('data-status');
                        const date = this.getAttribute('data-date');
                        const time = this.getAttribute('data-time');
                        
                        // Create victim names HTML
                        let victimNamesHTML = '';
                        if (victimNames !== 'None') {
                            const victimsArray = victimNames.split(', ');
                            victimNamesHTML = victimsArray.map(name => `<div>👤 ${name}</div>`).join('');
                        } else {
                            victimNamesHTML = '<div><i>No victims assigned</i></div>';
                        }
                        
                        modalContent.innerHTML = `
                            <div class="distribution-details">
                                <div class="detail-row">
                                    <div class="detail-label">Distribution ID</div>
                                    <div class="detail-value"><strong>#${distributionId.padStart(4, '0')}</strong></div>
                                </div>
                                <div class="detail-row">
                                    <div class="detail-label">Disaster</div>
                                    <div class="detail-value">${disasterName}</div>
                                </div>
                                <div class="detail-row">
                                    <div class="detail-label">Location</div>
                                    <div class="detail-value">📍 ${location}</div>
                                </div>
                                <div class="detail-row">
                                    <div class="detail-label">Date & Time</div>
                                    <div class="detail-value">📅 ${date} at ${time}</div>
                                </div>
                                <div class="detail-row">
                                    <div class="detail-label">Coordinator</div>
                                    <div class="detail-value">👤 ${coordinator || 'Not assigned'}</div>
                                </div>
                                <div class="detail-row">
                                    <div class="detail-label">Status</div>
                                    <div class="detail-value"><span class="badge badge-${status.toLowerCase().replace(' ', '-')}">${status}</span></div>
                                </div>
                                <div class="detail-row">
                                    <div class="detail-label">Victims</div>
                                    <div class="detail-value">
                                        <div style="margin-top: 5px; font-size: 0.9em; color: #666;">
                                            ${victimNamesHTML}
                                        </div>
                                    </div>
                                </div>
                                <div class="detail-actions" style="margin-top: 15px; display: flex; gap: 10px;">
                                    <a href="view_distribution.php?id=${distributionId}" class="btn btn-primary">
                                        <i class="fas fa-external-link-alt"></i> View Full Details
                                    </a>
                                    <a href="update_status.php?id=${distributionId}" class="btn btn-warning">
                                        <i class="fas fa-edit"></i> Update Status
                                    </a>
                                </div>
                            </div>
                        `;
                        modal.style.display = 'block';
                    });
                });
                
                // Close quick view modal
                const modalClose = document.querySelector('.modal-close');
                if (modalClose) {
                    modalClose.addEventListener('click', function() {
                        modal.style.display = 'none';
                    });
                }
                
                // Close quick view modal when clicking outside
                window.addEventListener('click', function(event) {
                    if (event.target === modal) {
                        modal.style.display = 'none';
                    }
                });
            }
            
            // Delete distribution functionality (only for API admins)
            const deleteButtons = document.querySelectorAll('.delete-btn');
            const deleteModal = document.getElementById('delete-modal');
            const deleteCancelBtn = document.getElementById('delete-cancel');
            const deleteModalClose = document.getElementById('delete-modal-close');
            const deleteConfirmBtn = document.getElementById('delete-confirm');
            const deleteMessage = document.getElementById('delete-message');
            const deleteDetails = document.getElementById('delete-details');
            
            let distributionToDelete = null;
            
            // Handle delete button clicks
            deleteButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const distributionId = this.getAttribute('data-id');
                    const distributionName = this.getAttribute('data-name');
                    
                    distributionToDelete = distributionId;
                    
                    // Get row data for display
                    const row = document.querySelector(`#row-${distributionId}`);
                    if (row) {
                        const disasterName = row.querySelector('.disaster-cell strong').textContent;
                        const location = row.querySelector('.location-cell').textContent.replace('📍', '').trim();
                        const status = row.querySelector('.badge').textContent.trim();
                        const victimCount = row.querySelector('.victim-count-badge i').nextSibling.textContent.trim();
                        const coordinatorName = row.querySelector('td:nth-child(6) div')?.textContent || 'Not assigned';
                        
                        deleteMessage.innerHTML = `Are you sure you want to delete <strong>${distributionName}</strong>?`;
                        deleteDetails.innerHTML = `
                            <div style="background: #f8f9fa; padding: 10px; border-radius: 5px; margin-top: 10px;">
                                <p><strong>Disaster:</strong> ${disasterName}</p>
                                <p><strong>Location:</strong> ${location}</p>
                                <p><strong>Status:</strong> ${status}</p>
                                <p><strong>Coordinator:</strong> ${coordinatorName}</p>
                                <p><strong>Victims:</strong> ${victimCount}</p>
                                <div class="alert alert-warning mt-2">
                                    <i class="fas fa-exclamation-triangle"></i>
                                    <small>This action is only available to API-authenticated administrators.</small>
                                </div>
                            </div>
                        `;
                    } else {
                        deleteMessage.textContent = `Are you sure you want to delete ${distributionName}?`;
                        deleteDetails.innerHTML = '<div class="alert alert-warning mt-2"><i class="fas fa-exclamation-triangle"></i> <small>API Admin action</small></div>';
                    }
                    
                    deleteModal.style.display = 'block';
                });
            });
            
            // Close delete modal
            if (deleteCancelBtn) {
                deleteCancelBtn.addEventListener('click', function() {
                    deleteModal.style.display = 'none';
                    distributionToDelete = null;
                });
            }
            
            if (deleteModalClose) {
                deleteModalClose.addEventListener('click', function() {
                    deleteModal.style.display = 'none';
                    distributionToDelete = null;
                });
            }
            
            // Close modal when clicking outside
            window.addEventListener('click', function(event) {
                if (event.target === deleteModal) {
                    deleteModal.style.display = 'none';
                    distributionToDelete = null;
                }
            });
            
            // Handle delete confirmation
            if (deleteConfirmBtn) {
                deleteConfirmBtn.addEventListener('click', function() {
                    if (!distributionToDelete) return;
                    
                    // Show loading state
                    const originalText = deleteConfirmBtn.innerHTML;
                    deleteConfirmBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Deleting...';
                    deleteConfirmBtn.disabled = true;
                    
                    // Send AJAX request
                    fetch('delete_distribution.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `distribution_id=${distributionToDelete}&confirm_delete=1&admin_action=true&api_verified=<?php echo $is_api_authenticated ? '1' : '0'; ?>`
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Success - remove row from table with animation
                            const row = document.querySelector(`#row-${distributionToDelete}`);
                            if (row) {
                                row.style.transition = 'all 0.5s ease';
                                row.style.opacity = '0';
                                row.style.transform = 'translateX(-100%)';
                                
                                setTimeout(() => {
                                    row.remove();
                                    
                                    // Show success toast
                                    showToast('Distribution deleted successfully (API Admin action)', 'success');
                                    
                                    // Update total count if exists
                                    const totalBadge = document.querySelector('.header-badge .badge-info');
                                    if (totalBadge) {
                                        const currentTotal = parseInt(totalBadge.textContent);
                                        if (!isNaN(currentTotal)) {
                                            totalBadge.textContent = currentTotal - 1;
                                        }
                                    }
                                    
                                    // Update results count
                                    const resultsCount = document.querySelector('.results-count');
                                    if (resultsCount) {
                                        const text = resultsCount.textContent;
                                        const match = text.match(/Showing (\d+) of (\d+)/);
                                        if (match) {
                                            const showing = parseInt(match[1]) - 1;
                                            const total = parseInt(match[2]) - 1;
                                            resultsCount.textContent = `Showing ${showing} of ${total} distributions`;
                                        }
                                    }
                                }, 500);
                            } else {
                                showToast('Distribution deleted successfully (API Admin action)', 'success');
                            }
                        } else {
                            // Error
                            showToast(data.message, 'error');
                            deleteConfirmBtn.innerHTML = originalText;
                            deleteConfirmBtn.disabled = false;
                        }
                        
                        // Close modal
                        deleteModal.style.display = 'none';
                        distributionToDelete = null;
                    })
                    .catch(error => {
                        showToast('Error deleting distribution: ' + error.message, 'error');
                        deleteConfirmBtn.innerHTML = originalText;
                        deleteConfirmBtn.disabled = false;
                        deleteModal.style.display = 'none';
                        distributionToDelete = null;
                    });
                });
            }
            
            // Toast notifications function
            window.showToast = function(message, type = 'info') {
                const toastContainer = document.getElementById('toast-container');
                if (!toastContainer) {
                    console.error('Toast container not found!');
                    return;
                }
                
                const toast = document.createElement('div');
                toast.className = `toast toast-${type}`;
                toast.innerHTML = `
                    <div class="toast-content">
                        <i class="fas fa-${getToastIcon(type)}"></i>
                        <span>${message}</span>
                    </div>
                    <button class="toast-close">&times;</button>
                `;
                
                toastContainer.appendChild(toast);
                
                // Auto remove after 5 seconds
                setTimeout(() => {
                    toast.classList.add('fade-out');
                    setTimeout(() => {
                        if (toast.parentNode) {
                            toast.remove();
                        }
                    }, 300);
                }, 5000);
                
                // Close button
                const closeBtn = toast.querySelector('.toast-close');
                if (closeBtn) {
                    closeBtn.addEventListener('click', function() {
                        toast.classList.add('fade-out');
                        setTimeout(() => {
                            if (toast.parentNode) {
                                toast.remove();
                            }
                        }, 300);
                    });
                }
            };
            
            function getToastIcon(type) {
                switch(type) {
                    case 'success': return 'check-circle';
                    case 'error': return 'exclamation-circle';
                    case 'warning': return 'exclamation-triangle';
                    default: return 'info-circle';
                }
            }
            
            // Table row animations
            const rows = document.querySelectorAll('.table-row');
            rows.forEach((row, index) => {
                setTimeout(() => {
                    row.style.opacity = '0';
                    row.style.transform = 'translateY(20px)';
                    row.style.transition = 'all 0.5s ease';
                    
                    setTimeout(() => {
                        row.style.opacity = '1';
                        row.style.transform = 'translateY(0)';
                    }, 50);
                }, index * 50);
            });
            
            console.log('Dashboard initialization complete!');
        });
        
        // Auto-refresh every 60 seconds
        let refreshInterval = setInterval(function() {
            showToast('Auto-refreshing data...', 'info');
            setTimeout(() => {
                location.reload();
            }, 1000);
        }, 60000);
    </script>
</body>
</html>