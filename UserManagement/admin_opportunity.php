<?php
session_start();

// --- SECURITY CHECK ---
if(!isset($_SESSION['name']) || $_SESSION['role'] != "admin"){
    header("Location: login.php");
    exit();
}

// ================= DATABASE CONNECTION =================
$serverName = "localhost";
$connectionInfo = array(
    "Database" => "UserManagement",
    "Uid" => "yanadb",
    "PWD" => "yana123",
    "CharacterSet" => "UTF-8"
);

$conn = sqlsrv_connect($serverName, $connectionInfo);

if($conn === false){
    die(print_r(sqlsrv_errors(), true));
}

// ================= HANDLE ACTIONS =================
if(isset($_POST['action']) && isset($_POST['opportunity_id'])) {
    $opportunity_id = $_POST['opportunity_id'];
    $action = $_POST['action'];
    
    if($action == 'approve') {
        $updateSql = "UPDATE opportunity SET status = 'Open' WHERE opportunity_id = ?";
        $message = "Opportunity approved successfully!";
    } elseif($action == 'reject') {
        $updateSql = "UPDATE opportunity SET status = 'Rejected' WHERE opportunity_id = ?";
        $message = "Opportunity rejected.";
    } elseif($action == 'delete') {
        $updateSql = "DELETE FROM opportunity WHERE opportunity_id = ?";
        $message = "Opportunity deleted.";
    }
    
    $params = array($opportunity_id);
    $updateStmt = sqlsrv_query($conn, $updateSql, $params);
    
    if($updateStmt) {
        $_SESSION['success'] = $message;
    } else {
        $_SESSION['error'] = "Failed to update opportunity.";
    }
    
    header("Location: admin_opportunity.php");
    exit();
}

// ================= FETCH ALL OPPORTUNITIES =================
$sql = "
SELECT 
    o.*,
    n.NGOName,
    n.Email as NGOEmail,
    n.Phone as NGOPhone,
    -- Count berapa banyak volunteers dah apply
    (SELECT COUNT(*) FROM opportunity_volunteer ov 
     WHERE ov.opportunity_id = o.opportunity_id) as applied_count
FROM opportunity o
LEFT JOIN NGO n ON o.ngo_id = n.NGOID
ORDER BY o.created_at DESC";

$stmt = sqlsrv_query($conn, $sql);

if($stmt === false){
    die(print_r(sqlsrv_errors(), true));
}

// Count stats
$pending = 0;
$open = 0;
$rejected = 0;
$total = 0;
$rows = [];

while($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)){
    $rows[] = $row;
    $total++;
    
    if($row['status'] === 'Pending') $pending++;
    if($row['status'] === 'Open') $open++;
    if($row['status'] === 'Rejected') $rejected++;
}

// Get recent activities for dashboard
$recentSql = "
SELECT TOP 5 o.title, n.NGOName, o.status, o.created_at 
FROM opportunity o
LEFT JOIN NGO n ON o.ngo_id = n.NGOID
ORDER BY o.created_at DESC";
$recentStmt = sqlsrv_query($conn, $recentSql);
$recentActivities = [];
while($activity = sqlsrv_fetch_array($recentStmt, SQLSRV_FETCH_ASSOC)){
    $recentActivities[] = $activity;
}

sqlsrv_free_stmt($stmt);
sqlsrv_free_stmt($recentStmt);
sqlsrv_close($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Manage Opportunities</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <style>
        /* System Header */
        .system-header {
            background: linear-gradient(135deg, #1a237e 0%, #283593 100%);
            color: white;
            padding: 0 20px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .header-container {
            display: flex;
            align-items: center;
            justify-content: space-between;
            height: 70px;
        }

        .logo-section {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .logo-icon {
            font-size: 28px;
            color: #4fc3f7;
        }

        .logo-text h1 {
            font-size: 22px;
            margin: 0;
            font-weight: 600;
            color: white;
        }

        .logo-text small {
            font-size: 12px;
            opacity: 0.8;
            color: #bbdefb;
        }

        .header-controls {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .search-box {
            position: relative;
            width: 300px;
        }

        .search-box input {
            width: 100%;
            padding: 10px 15px 10px 40px;
            border: none;
            border-radius: 20px;
            background: rgba(255, 255, 255, 0.1);
            color: white;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .search-box input:focus {
            outline: none;
            background: rgba(255, 255, 255, 0.15);
            box-shadow: 0 0 0 2px rgba(79, 195, 247, 0.3);
        }

        .search-box i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #bbdefb;
        }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 5px 15px;
            border-radius: 25px;
            background: rgba(255, 255, 255, 0.08);
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .user-profile:hover {
            background: rgba(255, 255, 255, 0.15);
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #4fc3f7 0%, #0288d1 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: white;
            font-size: 16px;
        }

        .user-info {
            line-height: 1.3;
        }

        .user-name {
            font-weight: 600;
            font-size: 14px;
        }

        .user-role {
            font-size: 12px;
            opacity: 0.8;
            color: #bbdefb;
        }

       

        /* Collapsible Navigation Bar */
        .nav-toggle-container {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 10px 20px;
            background: #f8f9fa;
            border-bottom: 1px solid #e9ecef;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .nav-toggle-container:hover {
            background: #e9ecef;
        }

        .nav-toggle-btn {
            display: flex;
            align-items: center;
            gap: 10px;
            background: none;
            border: none;
            color: #2c3e50;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            padding: 8px 15px;
            border-radius: 6px;
            transition: all 0.3s ease;
        }

        .nav-toggle-btn:hover {
            background: rgba(79, 195, 247, 0.1);
            color: #1a237e;
        }

        .nav-toggle-btn i {
            transition: transform 0.3s ease;
        }

        .nav-toggle-btn.collapsed i {
            transform: rotate(180deg);
        }

        .collapsible-nav {
            background: #f8f9fa;
            border-bottom: 1px solid #e9ecef;
            overflow: hidden;
            transition: all 0.3s ease;
            max-height: 500px;
        }

        .collapsible-nav.collapsed {
            max-height: 0;
            border-bottom: none;
        }

        .nav-container {
            display: flex;
            align-items: center;
            gap: 10px;
            overflow-x: auto;
            padding: 15px 20px;
            scrollbar-width: thin;
            scrollbar-color: #c1c1c1 #f1f1f1;
        }

        .nav-container::-webkit-scrollbar {
            height: 6px;
        }

        .nav-container::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 3px;
        }

        .nav-container::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 3px;
        }

        .nav-btn {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 15px;
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 6px;
            color: #2c3e50;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            white-space: nowrap;
            transition: all 0.3s ease;
            flex-shrink: 0;
        }

        .nav-btn:hover {
            background: #f8f9fa;
            border-color: #4fc3f7;
            transform: translateY(-2px);
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .nav-btn.active {
            background: #4fc3f7;
            border-color: #4fc3f7;
            color: white;
        }

        .nav-btn i {
            font-size: 16px;
        }

        /* Sidebar Navigation */
        .sidebar {
            position: fixed;
            left: 0;
            top: 70px;
            width: 250px;
            height: calc(100vh - 70px);
            background: linear-gradient(180deg, #1a237e 0%, #283593 100%);
            color: white;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
            z-index: 999;
            transition: transform 0.3s ease;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .sidebar-content {
            flex: 1;
            overflow-y: auto;
            padding: 20px 0;
        }

        .sidebar-collapsed {
            transform: translateX(-250px);
        }

        /* Custom scrollbar for sidebar */
        .sidebar-content::-webkit-scrollbar {
            width: 6px;
        }

        .sidebar-content::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 3px;
        }

        .sidebar-content::-webkit-scrollbar-thumb {
            background: rgba(79, 195, 247, 0.5);
            border-radius: 3px;
        }

        .sidebar-content::-webkit-scrollbar-thumb:hover {
            background: rgba(79, 195, 247, 0.8);
        }

        .nav-menu {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .nav-item {
            margin: 5px 15px;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px;
            color: #bbdefb;
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.3s ease;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .nav-link:hover {
            background: rgba(255, 255, 255, 0.1);
            color: white;
        }

        .nav-link.active {
            background: rgba(79, 195, 247, 0.2);
            color: white;
            border-left: 4px solid #4fc3f7;
        }

        .nav-link i {
            width: 20px;
            text-align: center;
            font-size: 18px;
            flex-shrink: 0;
        }

        .nav-text {
            font-size: 14px;
            font-weight: 500;
            flex: 1;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .nav-divider {
            height: 1px;
            background: rgba(255, 255, 255, 0.1);
            margin: 20px 15px;
        }

        .nav-label {
            padding: 10px 20px;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #90caf9;
            font-weight: 600;
            white-space: nowrap;
        }

        /* Sidebar Footer */
        .sidebar-footer {
            padding: 15px 20px;
            background: rgba(0, 0, 0, 0.2);
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            flex-shrink: 0;
        }

        .sidebar-footer a {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #bbdefb;
            text-decoration: none;
            padding: 10px;
            border-radius: 6px;
            transition: all 0.3s ease;
        }

        .sidebar-footer a:hover {
            background: rgba(231, 76, 60, 0.2);
            color: #ff6b6b;
        }

        /* Main Content Area */
        .main-content {
            margin-left: 250px;
            padding: 30px;
            transition: margin-left 0.3s ease;
            min-height: calc(100vh - 70px);
            background: #f8f9fa;
        }

        .main-content-expanded {
            margin-left: 0;
        }

        /* Page Header */
        .page-header {
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 25px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            border: 1px solid #e9ecef;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }

        .page-header h2 {
            color: #2c3e50;
            margin: 0;
            font-size: 24px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .page-header p {
            color: #7f8c8d;
            margin: 5px 0 0 0;
            font-size: 14px;
        }

        /* Stats Cards */
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            border: 1px solid #e9ecef;
            text-align: center;
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            margin: 0 auto 15px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }

        .stat-number {
            font-size: 32px;
            font-weight: 700;
            margin: 10px 0;
            color: #2c3e50;
        }

        .stat-label {
            color: #7f8c8d;
            font-size: 14px;
            font-weight: 500;
        }

        /* Category Colors */
        .stat-total { border-top: 4px solid #3498db; }
        .stat-total .stat-icon { background: rgba(52, 152, 219, 0.1); color: #3498db; }

        .stat-pending { border-top: 4px solid #f39c12; }
        .stat-pending .stat-icon { background: rgba(243, 156, 18, 0.1); color: #f39c12; }

        .stat-open { border-top: 4px solid #27ae60; }
        .stat-open .stat-icon { background: rgba(39, 174, 96, 0.1); color: #27ae60; }

        .stat-rejected { border-top: 4px solid #e74c3c; }
        .stat-rejected .stat-icon { background: rgba(231, 76, 60, 0.1); color: #e74c3c; }

        /* Table Container */
        .table-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            border: 1px solid #e9ecef;
            overflow: hidden;
            margin-bottom: 30px;
        }

        .card-header {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            padding: 20px;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .card-header h5 {
            color: #2c3e50;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .table-responsive {
            overflow-x: auto;
        }

        .table {
            margin: 0;
        }

        .table thead th {
            background: #f8f9fa;
            border-bottom: 2px solid #e9ecef;
            color: #2c3e50;
            font-weight: 600;
            padding: 15px;
            text-transform: uppercase;
            font-size: 13px;
            letter-spacing: 0.5px;
            white-space: nowrap;
        }

        .table tbody td {
            padding: 15px;
            vertical-align: middle;
            border-color: #f1f1f1;
        }

        .table tbody tr {
            transition: all 0.3s ease;
        }

        .table tbody tr:hover {
            background: #f8f9fa;
        }

        /* Status Badges */
        .badge {
            padding: 6px 12px;
            font-weight: 600;
            border-radius: 20px;
            font-size: 12px;
        }

        .badge-pending {
            background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%);
            color: white;
        }

        .badge-open {
            background: linear-gradient(135deg, #27ae60 0%, #219653 100%);
            color: white;
        }

        .badge-rejected {
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            color: white;
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .btn-action {
            padding: 6px 12px;
            border-radius: 8px;
            font-weight: 500;
            font-size: 12px;
            display: flex;
            align-items: center;
            gap: 5px;
            text-decoration: none;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
        }

        .btn-approve {
            background: linear-gradient(135deg, #27ae60 0%, #219653 100%);
            color: white;
        }

        .btn-approve:hover {
            background: linear-gradient(135deg, #219653 0%, #1e874b 100%);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(39, 174, 96, 0.3);
        }

        .btn-reject {
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            color: white;
        }

        .btn-reject:hover {
            background: linear-gradient(135deg, #c0392b 0%, #a93226 100%);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(231, 76, 60, 0.3);
        }

        .btn-delete {
            background: linear-gradient(135deg, #95a5a6 0%, #7f8c8d 100%);
            color: white;
        }

        .btn-delete:hover {
            background: linear-gradient(135deg, #7f8c8d 0%, #6c7b7d 100%);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(149, 165, 166, 0.3);
        }

        /* Slots Info */
        .slots-info {
            display: flex;
            flex-direction: column;
            gap: 3px;
        }

        .slots-total {
            font-weight: 600;
            color: #2c3e50;
        }

        .slots-applied {
            font-size: 11px;
            color: #7f8c8d;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        /* Toast Notification */
        .toast-container {
            position: fixed;
            top: 90px;
            right: 20px;
            z-index: 1000;
        }

        .toast {
            background: white;
            border-radius: 8px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
            padding: 15px 20px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 15px;
            min-width: 300px;
            animation: slideInRight 0.3s ease;
            border-left: 4px solid #4fc3f7;
        }

        .toast.toast-success {
            border-left-color: #27ae60;
        }

        .toast.toast-error {
            border-left-color: #e74c3c;
        }

        .toast-content {
            flex: 1;
        }

        .toast-close {
            background: none;
            border: none;
            color: #999;
            cursor: pointer;
            font-size: 18px;
        }

        /* Recent Activities */
        .activity-section {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            border: 1px solid #e9ecef;
            margin-bottom: 30px;
        }

        .activity-section h3 {
            color: #2c3e50;
            margin-bottom: 20px;
            font-size: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .activity-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .activity-item {
            padding: 15px 0;
            border-bottom: 1px solid #f1f1f1;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            background: #f8f9fa;
        }

        .activity-text {
            flex: 1;
            color: #2c3e50;
        }

        .activity-time {
            font-size: 12px;
            color: #95a5a6;
            text-align: right;
        }

        /* Mobile Toggle */
        .mobile-toggle {
            display: none;
            background: none;
            border: none;
            color: white;
            font-size: 20px;
            cursor: pointer;
            padding: 10px;
            border-radius: 5px;
            transition: background 0.3s ease;
        }

        .mobile-toggle:hover {
            background: rgba(255, 255, 255, 0.1);
        }

        /* Responsive Design */
        @media (max-width: 1024px) {
            .sidebar {
                transform: translateX(-250px);
            }
            
            .sidebar.active {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .mobile-toggle {
                display: block;
            }
            
            .search-box {
                width: 200px;
            }
        }

        @media (max-width: 768px) {
            .header-container {
                flex-wrap: wrap;
                height: auto;
                padding: 15px 0;
            }
            
            .logo-text h1 {
                font-size: 18px;
            }
            
            .search-box {
                width: 100%;
                order: 3;
                margin-top: 15px;
            }
            
            .stats-container {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .nav-container {
                padding: 10px 15px;
            }
            
            .nav-btn {
                padding: 8px 12px;
                font-size: 13px;
            }
            
            .page-header {
                flex-direction: column;
                text-align: center;
                gap: 15px;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .btn-action {
                width: 100%;
                justify-content: center;
            }
        }

        @keyframes slideInRight {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
    </style>
</head>
<body>
    <!-- System Header -->
    <header class="system-header">
        <div class="header-container">
            <div class="logo-section">
                <button class="mobile-toggle" id="mobileToggle">
                    <i class="fas fa-bars"></i>
                </button>
                <div class="logo-icon">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <div class="logo-text">
                    <h1>Admin Panel</h1>
                    <small>User Management System</small>
                </div>
            </div>
            
            <div class="header-controls">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" placeholder="Search opportunities..." id="searchInput">
                </div>
                
                
                
                <div class="user-profile" id="userProfileBtn">
                    <div class="user-avatar">
                        <?php echo strtoupper(substr($_SESSION['name'], 0, 1)); ?>
                    </div>
                    <div class="user-info">
                        <div class="user-name"><?php echo $_SESSION['name']; ?></div>
                        <div class="user-role">Administrator</div>
                    </div>
                    <i class="fas fa-chevron-down"></i>
                </div>
            </div>
        </div>
    </header>

    <!-- Collapsible Navigation Bar -->
    <div class="nav-toggle-container" id="navToggleContainer">
        <button class="nav-toggle-btn" id="navToggleBtn">
            <i class="fas fa-chevron-up"></i>
            <span>Quick Navigation Menu</span>
        </button>
    </div>

    <div class="collapsible-nav" id="collapsibleNav">
        <div class="nav-container">
            <a href="admin_dashboard.php" class="nav-btn">
                <i class="fas fa-tachometer-alt"></i>
                <span>Dashboard</span>
            </a>
            <a href="admin_profile.php" class="nav-btn">
                <i class="fas fa-user"></i>
                <span>Profile</span>
            </a>
            <a href="view_volunteer.php" class="nav-btn">
                <i class="fas fa-users"></i>
                <span>View Volunteer</span>
            </a>
            <a href="admin_manage_ngo.php" class="nav-btn">
                <i class="fas fa-handshake"></i>
                <span>Manage NGO</span>
            </a>
           
            <a href="view_news.php" class="nav-btn">
                <i class="fas fa-list"></i>
                <span>View News</span>
            </a>
            <a href="admin_opportunity.php" class="nav-btn active">
                <i class="fas fa-briefcase"></i>
                <span>Opportunity</span>
            </a>
            <a href="distribution.php" class="nav-btn">
                <i class="fas fa-truck"></i>
                <span>Distribution</span>
            </a>
            <a href="admin_victim.php" class="nav-btn">
                <i class="fas fa-hands-helping"></i>
                <span>Victim</span>
            </a>
            <a href="report.php" class="nav-btn">
                <i class="fas fa-chart-bar"></i>
                <span>Reports</span>
            </a>
        </div>
    </div>

    <!-- Sidebar Navigation -->
    <nav class="sidebar" id="sidebar">
        <div class="sidebar-content">
            <ul class="nav-menu">
                <li class="nav-label">MAIN NAVIGATION</li>
                
                <li class="nav-item">
                    <a href="admin_dashboard.php" class="nav-link">
                        <i class="fas fa-tachometer-alt"></i>
                        <span class="nav-text">Dashboard</span>
                    </a>
                </li>
                
                <li class="nav-divider"></li>
                
                <li class="nav-label">USER MANAGEMENT</li>
                
                <li class="nav-item">
                    <a href="admin_profile.php" class="nav-link">
                        <i class="fas fa-user"></i>
                        <span class="nav-text">Profile</span>
                    </a>
                </li>
                
                <li class="nav-item">
                    <a href="view_volunteer.php" class="nav-link">
                        <i class="fas fa-users"></i>
                        <span class="nav-text">View Volunteer</span>
                    </a>
                </li>
                
                <li class="nav-item">
                    <a href="admin_manage_ngo.php" class="nav-link">
                        <i class="fas fa-handshake"></i>
                        <span class="nav-text">Manage NGO</span>
                    </a>
                </li>
                
                <li class="nav-divider"></li>
                
                <li class="nav-label">CONTENT MANAGEMENT</li>
                
             
                
                <li class="nav-item">
                    <a href="view_news.php" class="nav-link">
                        <i class="fas fa-list"></i>
                        <span class="nav-text">View News</span>
                    </a>
                </li>
                
                <li class="nav-divider"></li>
                
                <li class="nav-label">OPERATIONS</li>
                
                <li class="nav-item">
                    <a href="admin_opportunity.php" class="nav-link active">
                        <i class="fas fa-briefcase"></i>
                        <span class="nav-text">Opportunity</span>
                    </a>
                </li>
                
                <li class="nav-item">
                    <a href="distribution.php" class="nav-link">
                        <i class="fas fa-truck"></i>
                        <span class="nav-text">Distribution</span>
                    </a>
                </li>
                
                <li class="nav-item">
                    <a href="admin_victim.php" class="nav-link">
                        <i class="fas fa-hands-helping"></i>
                        <span class="nav-text">Victim</span>
                    </a>
                </li>
                
                <li class="nav-divider"></li>
                
                <li class="nav-label">ANALYTICS</li>
                
                <li class="nav-item">
                    <a href="report.php" class="nav-link">
                        <i class="fas fa-chart-bar"></i>
                        <span class="nav-text">Reports</span>
                    </a>
                </li>
            </ul>
        </div>
        
        <!-- Sidebar Footer -->
        <div class="sidebar-footer">
            <a href="main_page.php">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="main-content" id="mainContent">
        <!-- Toast Notification -->
        <?php if(isset($_SESSION['success'])): ?>
        <div class="toast-container">
            <div class="toast toast-success">
                <div class="toast-content">
                    <strong>Success!</strong>
                    <p style="margin: 5px 0 0 0; font-size: 14px;"><?php echo $_SESSION['success']; ?></p>
                </div>
                <button class="toast-close" onclick="this.parentElement.remove()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
        <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
        
        <?php if(isset($_SESSION['error'])): ?>
        <div class="toast-container">
            <div class="toast toast-error">
                <div class="toast-content">
                    <strong>Error!</strong>
                    <p style="margin: 5px 0 0 0; font-size: 14px;"><?php echo $_SESSION['error']; ?></p>
                </div>
                <button class="toast-close" onclick="this.parentElement.remove()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
        <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <!-- Page Header -->
        <div class="page-header">
            <div>
                <h2><i class="fas fa-briefcase"></i> Manage Opportunities</h2>
                <p>Approve or reject opportunities posted by NGOs</p>
            </div>
            <div>
                <span class="badge badge-pending p-2">
                    <i class="fas fa-bell me-1"></i>
                    Pending: <?php echo $pending; ?>
                </span>
            </div>
        </div>

        <!-- Recent Activities -->
        <div class="activity-section">
            <h3><i class="fas fa-history"></i> Recent Activities</h3>
            <ul class="activity-list">
                <?php if(count($recentActivities) > 0): ?>
                    <?php foreach($recentActivities as $activity): ?>
                    <li class="activity-item">
                        <div class="activity-icon" style="background: rgba(52, 152, 219, 0.1); color: #3498db;">
                            <i class="fas fa-briefcase"></i>
                        </div>
                        <div class="activity-text">
                            <strong><?php echo htmlspecialchars($activity['NGOName']); ?></strong> posted 
                            "<?php echo htmlspecialchars($activity['title']); ?>"
                            <div class="activity-time">
                                <?php 
                                if($activity['created_at'] instanceof DateTime){
                                    echo $activity['created_at']->format('d M Y, H:i');
                                } else {
                                    echo date('d M Y, H:i', strtotime($activity['created_at']));
                                }
                                ?>
                            </div>
                        </div>
                        <div>
                            <?php if($activity['status'] === 'Pending'): ?>
                                <span class="badge badge-pending">Pending</span>
                            <?php elseif($activity['status'] === 'Open'): ?>
                                <span class="badge badge-open">Open</span>
                            <?php elseif($activity['status'] === 'Rejected'): ?>
                                <span class="badge badge-rejected">Rejected</span>
                            <?php endif; ?>
                        </div>
                    </li>
                    <?php endforeach; ?>
                <?php else: ?>
                    <li class="activity-item">
                        <div class="activity-icon" style="background: rgba(149, 165, 166, 0.1); color: #95a5a6;">
                            <i class="fas fa-info-circle"></i>
                        </div>
                        <div class="activity-text">
                            No recent activities found
                        </div>
                    </li>
                <?php endif; ?>
            </ul>
        </div>

        <!-- Stats Cards -->
        <div class="stats-container">
            <div class="stat-card stat-total">
                <div class="stat-icon">
                    <i class="fas fa-briefcase"></i>
                </div>
                <div class="stat-number"><?php echo $total; ?></div>
                <div class="stat-label">Total Opportunities</div>
            </div>
            
            <div class="stat-card stat-pending">
                <div class="stat-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-number"><?php echo $pending; ?></div>
                <div class="stat-label">Pending Approval</div>
            </div>
            
            <div class="stat-card stat-open">
                <div class="stat-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-number"><?php echo $open; ?></div>
                <div class="stat-label">Open Opportunities</div>
            </div>
            
            <div class="stat-card stat-rejected">
                <div class="stat-icon">
                    <i class="fas fa-times-circle"></i>
                </div>
                <div class="stat-number"><?php echo $rejected; ?></div>
                <div class="stat-label">Rejected</div>
            </div>
        </div>

        <!-- OPPORTUNITIES TABLE -->
        <div class="table-container">
            <div class="card-header">
                <h5><i class="fas fa-list"></i> All Opportunities</h5>
                <span style="color: #7f8c8d; font-size: 14px;">
                    <i class="fas fa-database me-1"></i>
                    Total: <?php echo count($rows); ?> opportunities
                </span>
            </div>
            
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Title & Description</th>
                            <th>NGO</th>
                            <th>Location</th>
                            <th>Date</th>
                            <th>Slots</th>
                            <th>Status</th>
                            <th>Posted On</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if(count($rows) > 0): ?>
                    <?php $i = 1; foreach($rows as $row): 
                        // Format dates
                        $event_date = $row['event_date'] instanceof DateTime ? 
                            $row['event_date']->format('d M Y') : 
                            date('d M Y', strtotime($row['event_date']));
                        $created_at = $row['created_at'] instanceof DateTime ? 
                            $row['created_at']->format('d M Y, H:i') : 
                            date('d M Y, H:i', strtotime($row['created_at']));
                        
                        // Calculate available slots
                        $total_slots = $row['slots'];
                        $applied_count = $row['applied_count'];
                        $available_slots = $total_slots - $applied_count;
                    ?>
                        <tr>
                            <td>
                                <strong class="text-primary">#<?php echo $i++; ?></strong>
                            </td>
                            <td>
                                <div class="fw-semibold"><?php echo htmlspecialchars($row['title']); ?></div>
                                <small class="text-muted">
                                    <?php echo substr(htmlspecialchars($row['description']), 0, 100); ?>
                                    <?php echo strlen($row['description']) > 100 ? '...' : ''; ?>
                                </small>
                            </td>
                            <td>
                                <div class="fw-semibold"><?php echo htmlspecialchars($row['NGOName'] ?? 'Unknown NGO'); ?></div>
                                <small class="text-muted">
                                    <i class="fas fa-envelope me-1"></i>
                                    <?php echo htmlspecialchars($row['NGOEmail'] ?? 'N/A'); ?>
                                </small>
                            </td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-map-marker-alt text-muted me-2"></i>
                                    <?php echo htmlspecialchars($row['location']); ?>
                                </div>
                            </td>
                            <td>
                                <div class="text-muted">
                                    <i class="fas fa-calendar-alt me-1"></i>
                                    <?php echo $event_date; ?>
                                </div>
                            </td>
                            <td>
                                <div class="slots-info">
                                    <div class="slots-total"><?php echo $total_slots; ?> total</div>
                                    <div class="slots-applied">
                                        <i class="fas fa-users"></i>
                                        <?php echo $applied_count; ?> applied
                                    </div>
                                </div>
                            </td>
                            <td>
                                <?php if($row['status'] === 'Pending'): ?>
                                    <span class="badge badge-pending">
                                        <i class="fas fa-clock me-1"></i>Pending
                                    </span>
                                <?php elseif($row['status'] === 'Open'): ?>
                                    <span class="badge badge-open">
                                        <i class="fas fa-check me-1"></i>Open
                                    </span>
                                <?php elseif($row['status'] === 'Rejected'): ?>
                                    <span class="badge badge-rejected">
                                        <i class="fas fa-times me-1"></i>Rejected
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-secondary"><?php echo $row['status']; ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="text-muted">
                                    <?php echo $created_at; ?>
                                </div>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <?php if($row['status'] === 'Pending'): ?>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="opportunity_id" value="<?php echo $row['opportunity_id']; ?>">
                                            <input type="hidden" name="action" value="approve">
                                            <button type="submit" class="btn-action btn-approve">
                                                <i class="fas fa-check"></i> Approve
                                            </button>
                                        </form>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="opportunity_id" value="<?php echo $row['opportunity_id']; ?>">
                                            <input type="hidden" name="action" value="reject">
                                            <button type="submit" class="btn-action btn-reject">
                                                <i class="fas fa-times"></i> Reject
                                            </button>
                                        </form>
                                    <?php elseif($row['status'] === 'Open'): ?>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="opportunity_id" value="<?php echo $row['opportunity_id']; ?>">
                                            <input type="hidden" name="action" value="reject">
                                            <button type="submit" class="btn-action btn-reject">
                                                <i class="fas fa-times"></i> Reject
                                            </button>
                                        </form>
                                    <?php elseif($row['status'] === 'Rejected'): ?>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="opportunity_id" value="<?php echo $row['opportunity_id']; ?>">
                                            <input type="hidden" name="action" value="approve">
                                            <button type="submit" class="btn-action btn-approve">
                                                <i class="fas fa-check"></i> Approve
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="opportunity_id" value="<?php echo $row['opportunity_id']; ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <button type="submit" class="btn-action btn-delete">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" class="text-center py-4">
                                <div style="text-align: center; padding: 40px;">
                                    <i class="fas fa-briefcase fa-3x text-muted mb-3"></i>
                                    <h5 style="color: #7f8c8d;">No opportunities found</h5>
                                    <p class="text-muted">No NGOs have posted opportunities yet.</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <!-- SweetAlert JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
        // Mobile Toggle
        document.getElementById('mobileToggle').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('active');
        });

        // Collapsible Nav Toggle
        let navCollapsed = false;
        document.getElementById('navToggleBtn').addEventListener('click', function() {
            const nav = document.getElementById('collapsibleNav');
            const btn = this;
            
            navCollapsed = !navCollapsed;
            nav.classList.toggle('collapsed');
            btn.classList.toggle('collapsed');
        });

        // Auto-collapse nav on small screens
        function checkScreenSize() {
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('mainContent');
            const mobileToggle = document.getElementById('mobileToggle');
            
            if (window.innerWidth <= 1024) {
                sidebar.classList.add('sidebar-collapsed');
                mainContent.classList.add('main-content-expanded');
                mobileToggle.style.display = 'block';
            } else {
                sidebar.classList.remove('sidebar-collapsed', 'active');
                mainContent.classList.remove('main-content-expanded');
                mobileToggle.style.display = 'none';
            }
        }

        // Check on load and resize
        window.addEventListener('load', checkScreenSize);
        window.addEventListener('resize', checkScreenSize);

        // User profile dropdown (simplified)
        document.getElementById('userProfileBtn').addEventListener('click', function() {
            window.location.href = 'admin_profile.php';
        });

        // Notifications dropdown (simplified)
        document.getElementById('notificationsBtn').addEventListener('click', function() {
            // Filter to show only pending opportunities
            const searchInput = document.getElementById('searchInput');
            searchInput.value = '';
            searchInput.dispatchEvent(new Event('input'));
            
            // Highlight pending rows
            const rows = document.querySelectorAll('.table tbody tr');
            rows.forEach(row => {
                const statusBadge = row.querySelector('.badge-pending');
                if(statusBadge) {
                    row.style.backgroundColor = '#fff3cd';
                    setTimeout(() => {
                        row.style.backgroundColor = '';
                    }, 3000);
                }
            });
        });

        // Search functionality
        document.getElementById('searchInput').addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            const rows = document.querySelectorAll('.table tbody tr');
            
            rows.forEach(row => {
                if(row.querySelector('.text-muted.fa-3x')) return; // Skip empty state
                
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchTerm) ? '' : 'none';
            });
        });

        // Action confirmation with SweetAlert
        document.querySelectorAll('form button[type="submit"]').forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                
                const form = this.closest('form');
                const action = form.querySelector('input[name="action"]').value;
                const opportunityTitle = this.closest('tr').querySelector('td:nth-child(2) .fw-semibold').textContent;
                const ngoName = this.closest('tr').querySelector('td:nth-child(3) .fw-semibold').textContent;
                
                let title, text, icon, confirmButtonText, confirmButtonColor;
                
                if(action === 'approve') {
                    title = 'Approve Opportunity?';
                    text = `Are you sure you want to approve "<strong>${opportunityTitle}</strong>" by ${ngoName}?`;
                    icon = 'question';
                    confirmButtonText = '<i class="fas fa-check me-2"></i>Approve';
                    confirmButtonColor = '#27ae60';
                } else if(action === 'reject') {
                    title = 'Reject Opportunity?';
                    text = `Are you sure you want to reject "<strong>${opportunityTitle}</strong>" by ${ngoName}?`;
                    icon = 'warning';
                    confirmButtonText = '<i class="fas fa-times me-2"></i>Reject';
                    confirmButtonColor = '#e74c3c';
                } else {
                    title = 'Delete Opportunity?';
                    text = `⚠️ Are you sure you want to permanently delete "<strong>${opportunityTitle}</strong>" by ${ngoName}?<br><br>
                           <strong>This action will also remove all volunteer applications and cannot be undone!</strong>`;
                    icon = 'error';
                    confirmButtonText = '<i class="fas fa-trash me-2"></i>Delete';
                    confirmButtonColor = '#d33';
                }
                
                Swal.fire({
                    title: title,
                    html: text,
                    icon: icon,
                    showCancelButton: true,
                    confirmButtonColor: confirmButtonColor,
                    cancelButtonColor: '#7f8c8d',
                    confirmButtonText: confirmButtonText,
                    cancelButtonText: '<i class="fas fa-times me-2"></i>Cancel',
                    width: '500px',
                    reverseButtons: true,
                    customClass: {
                        popup: 'rounded-4',
                        confirmButton: 'btn-lg',
                        cancelButton: 'btn-lg'
                    },
                    buttonsStyling: false,
                    showClass: {
                        popup: 'animate__animated animate__fadeInDown'
                    },
                    hideClass: {
                        popup: 'animate__animated animate__fadeOutUp'
                    }
                }).then((result) => {
                    if (result.isConfirmed) {
                        // Show loading
                        Swal.fire({
                            title: 'Processing...',
                            text: 'Please wait',
                            allowOutsideClick: false,
                            didOpen: () => {
                                Swal.showLoading();
                            }
                        });
                        
                        // Submit the form
                        form.submit();
                    }
                });
            });
        });

        // Auto-remove toast after 5 seconds
        setTimeout(function() {
            var toast = document.querySelector('.toast');
            if(toast) {
                toast.style.animation = 'slideInRight 0.3s ease reverse';
                setTimeout(() => toast.remove(), 300);
            }
        }, 5000);
    </script>
</body>
</html>