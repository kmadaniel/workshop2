<?php
session_start();
if(!isset($_SESSION['name']) || $_SESSION['role'] != "admin"){
    header("Location: login.php");
    exit();
}

require_once "connection.php";

// Handle Approve/Reject/Delete Actions
if(isset($_GET['action']) && isset($_GET['id'])){
    $ngo_id = $_GET['id'];
    $action = $_GET['action'];
    
    if($action == 'approve'){
        $sql = "UPDATE NGO SET status = 'Approved' WHERE NGOID = ?";
        $message = "NGO approved successfully!";
    } elseif($action == 'reject'){
        $sql = "UPDATE NGO SET status = 'Rejected' WHERE NGOID = ?";
        $message = "NGO rejected.";
    } elseif($action == 'delete'){
        $sql = "DELETE FROM NGO WHERE NGOID = ?";
        $message = "NGO deleted.";
    }
    
    $params = array($ngo_id);
    $stmt = sqlsrv_query($conn, $sql, $params);
    
    if($stmt){
        $_SESSION['success'] = $message;
    } else {
        $_SESSION['error'] = "Failed to update NGO.";
    }
    
    header("Location: admin_manage_ngo.php");
    exit();
}

// Fetch NGOs with Pending status
$sql = "SELECT * FROM NGO WHERE status = 'Pending' ORDER BY CreatedAt DESC";
$stmt = sqlsrv_query($conn, $sql);
if($stmt === false) die(print_r(sqlsrv_errors(), true));

$pending_ngos = [];
while($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)){
    $pending_ngos[] = $row;
}

// Fetch ALL NGOs for the table view
$all_sql = "SELECT * FROM NGO ORDER BY CreatedAt DESC";
$all_stmt = sqlsrv_query($conn, $all_sql);
if($all_stmt === false) die(print_r(sqlsrv_errors(), true));

$all_ngos = [];
while($row = sqlsrv_fetch_array($all_stmt, SQLSRV_FETCH_ASSOC)){
    $all_ngos[] = $row;
}

// Count stats
$statsSql = "SELECT 
    SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status = 'Approved' THEN 1 ELSE 0 END) as approved,
    SUM(CASE WHEN status = 'Rejected' THEN 1 ELSE 0 END) as rejected,
    COUNT(*) as total
    FROM NGO";
$statsStmt = sqlsrv_query($conn, $statsSql);
$stats = sqlsrv_fetch_array($statsStmt, SQLSRV_FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - NGO Management</title>
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

        .notifications {
            position: relative;
            cursor: pointer;
            padding: 10px;
            border-radius: 50%;
            transition: background 0.3s ease;
        }

        .notifications:hover {
            background: rgba(255, 255, 255, 0.1);
        }

        .notification-badge {
            position: absolute;
            top: 5px;
            right: 5px;
            background: #f44336;
            color: white;
            font-size: 10px;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
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
        .stat-pending { border-top: 4px solid #f39c12; }
        .stat-pending .stat-icon { background: rgba(243, 156, 18, 0.1); color: #f39c12; }

        .stat-approved { border-top: 4px solid #27ae60; }
        .stat-approved .stat-icon { background: rgba(39, 174, 96, 0.1); color: #27ae60; }

        .stat-rejected { border-top: 4px solid #e74c3c; }
        .stat-rejected .stat-icon { background: rgba(231, 76, 60, 0.1); color: #e74c3c; }

        .stat-total { border-top: 4px solid #3498db; }
        .stat-total .stat-icon { background: rgba(52, 152, 219, 0.1); color: #3498db; }

        /* NGO Cards */
        .ngo-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            border: 1px solid #e9ecef;
            margin-bottom: 20px;
            transition: all 0.3s ease;
            border-left: 4px solid #f39c12;
        }

        .ngo-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }

        .ngo-card h5 {
            color: #2c3e50;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f8f9fa;
        }

        .ngo-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }

        .info-item {
            display: flex;
            align-items: flex-start;
            gap: 10px;
        }

        .info-icon {
            color: #4fc3f7;
            font-size: 16px;
            width: 24px;
            text-align: center;
            margin-top: 2px;
        }

        .info-content {
            flex: 1;
        }

        .info-label {
            font-size: 12px;
            color: #7f8c8d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 3px;
        }

        .info-value {
            color: #2c3e50;
            font-size: 14px;
            font-weight: 500;
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #f1f1f1;
        }

        .btn-action {
            padding: 8px 16px;
            border-radius: 8px;
            font-weight: 500;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
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

        .badge-approved {
            background: linear-gradient(135deg, #27ae60 0%, #219653 100%);
            color: white;
        }

        .badge-rejected {
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            color: white;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 50px 20px;
        }

        .empty-state i {
            font-size: 60px;
            color: #ddd;
            margin-bottom: 20px;
        }

        .empty-state h5 {
            color: #7f8c8d;
            margin-bottom: 10px;
        }

        .empty-state p {
            color: #95a5a6;
            font-size: 14px;
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
            
            .ngo-info {
                grid-template-columns: 1fr;
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
                    <input type="text" placeholder="Search NGOs..." id="searchInput">
                </div>
                
                <div class="notifications" id="notificationsBtn">
                    <i class="fas fa-bell"></i>
                    <span class="notification-badge"><?php echo $stats['pending']; ?></span>
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
            <a href="view_admin.php" class="nav-btn">
                <i class="fas fa-users"></i>
                <span>View Admins</span>
            </a>
            <a href="admin_manage_ngo.php" class="nav-btn active">
                <i class="fas fa-handshake"></i>
                <span>Manage NGO</span>
            </a>
            <a href="create_news.php" class="nav-btn">
                <i class="fas fa-newspaper"></i>
                <span>Create News</span>
            </a>
            <a href="view_news.php" class="nav-btn">
                <i class="fas fa-list"></i>
                <span>View News</span>
            </a>
            <a href="admin_opportunity.php" class="nav-btn">
                <i class="fas fa-briefcase"></i>
                <span>Opportunity</span>
            </a>
            <a href="distribution.php" class="nav-btn">
                <i class="fas fa-truck"></i>
                <span>Distribution</span>
            </a>
            <a href="victim.php" class="nav-btn">
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
                    <a href="view_admin.php" class="nav-link">
                        <i class="fas fa-users"></i>
                        <span class="nav-text">View Admins</span>
                    </a>
                </li>
                
                <li class="nav-item">
                    <a href="admin_manage_ngo.php" class="nav-link active">
                        <i class="fas fa-handshake"></i>
                        <span class="nav-text">Manage NGO</span>
                    </a>
                </li>
                
                <li class="nav-divider"></li>
                
                <li class="nav-label">CONTENT MANAGEMENT</li>
                
                <li class="nav-item">
                    <a href="create_news.php" class="nav-link">
                        <i class="fas fa-newspaper"></i>
                        <span class="nav-text">Create News</span>
                    </a>
                </li>
                
                <li class="nav-item">
                    <a href="view_news.php" class="nav-link">
                        <i class="fas fa-list"></i>
                        <span class="nav-text">View News</span>
                    </a>
                </li>
                
                <li class="nav-divider"></li>
                
                <li class="nav-label">OPERATIONS</li>
                
                <li class="nav-item">
                    <a href="admin_opportunity.php" class="nav-link">
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
                    <a href="victim.php" class="nav-link">
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
            <a href="logout.php">
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
                <h2><i class="fas fa-handshake"></i> NGO Management</h2>
                <p>Manage all NGO registrations and approvals</p>
            </div>
            <div>
                <span class="badge badge-pending p-2">
                    <i class="fas fa-bell me-1"></i>
                    Pending: <?php echo $stats['pending']; ?>
                </span>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="stats-container">
            <div class="stat-card stat-pending">
                <div class="stat-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-number"><?php echo $stats['pending']; ?></div>
                <div class="stat-label">Pending Approval</div>
            </div>
            
            <div class="stat-card stat-approved">
                <div class="stat-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-number"><?php echo $stats['approved']; ?></div>
                <div class="stat-label">Approved NGOs</div>
            </div>
            
            <div class="stat-card stat-rejected">
                <div class="stat-icon">
                    <i class="fas fa-times-circle"></i>
                </div>
                <div class="stat-number"><?php echo $stats['rejected']; ?></div>
                <div class="stat-label">Rejected NGOs</div>
            </div>
            
            <div class="stat-card stat-total">
                <div class="stat-icon">
                    <i class="fas fa-building"></i>
                </div>
                <div class="stat-number"><?php echo $stats['total']; ?></div>
                <div class="stat-label">Total NGOs</div>
            </div>
        </div>

        <!-- PENDING NGOs LIST -->
        <div class="table-container">
            <div class="card-header">
                <h5><i class="fas fa-clock"></i> Pending NGO Registrations</h5>
                <span style="color: #7f8c8d; font-size: 14px;">
                    <i class="fas fa-info-circle me-1"></i>
                    <?php echo count($pending_ngos); ?> awaiting approval
                </span>
            </div>
            
            <div style="padding: 20px;">
                <?php if(count($pending_ngos) > 0): ?>
                    <?php foreach($pending_ngos as $ngo): ?>
                    <div class="ngo-card">
                        <h5>
                            <i class="fas fa-building"></i>
                            <?php echo htmlspecialchars($ngo['NGOName']); ?>
                        </h5>
                        
                        <div class="ngo-info">
                            <div class="info-item">
                                <div class="info-icon">
                                    <i class="fas fa-envelope"></i>
                                </div>
                                <div class="info-content">
                                    <div class="info-label">Email</div>
                                    <div class="info-value"><?php echo htmlspecialchars($ngo['Email']); ?></div>
                                </div>
                            </div>
                            
                            <div class="info-item">
                                <div class="info-icon">
                                    <i class="fas fa-phone"></i>
                                </div>
                                <div class="info-content">
                                    <div class="info-label">Phone</div>
                                    <div class="info-value"><?php echo htmlspecialchars($ngo['Phone'] ?? 'N/A'); ?></div>
                                </div>
                            </div>
                            
                            <div class="info-item">
                                <div class="info-icon">
                                    <i class="fas fa-id-card"></i>
                                </div>
                                <div class="info-content">
                                    <div class="info-label">Registration No</div>
                                    <div class="info-value"><?php echo htmlspecialchars($ngo['RegistrationNo'] ?? 'N/A'); ?></div>
                                </div>
                            </div>
                            
                            <div class="info-item">
                                <div class="info-icon">
                                    <i class="fas fa-map-marker-alt"></i>
                                </div>
                                <div class="info-content">
                                    <div class="info-label">Address</div>
                                    <div class="info-value"><?php echo htmlspecialchars($ngo['Address'] ?? 'N/A'); ?></div>
                                </div>
                            </div>
                            
                            <div class="info-item">
                                <div class="info-icon">
                                    <i class="fas fa-calendar-alt"></i>
                                </div>
                                <div class="info-content">
                                    <div class="info-label">Registered Date</div>
                                    <div class="info-value">
                                        <?php 
                                        if($ngo['CreatedAt'] instanceof DateTime){
                                            echo $ngo['CreatedAt']->format('d M Y, H:i');
                                        } else {
                                            echo date('d M Y, H:i', strtotime($ngo['CreatedAt']));
                                        }
                                        ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="action-buttons">
                            <a href="admin_manage_ngo.php?action=approve&id=<?php echo $ngo['NGOID']; ?>" 
                               class="btn-action btn-approve">
                                <i class="fas fa-check"></i> Approve
                            </a>
                            <a href="admin_manage_ngo.php?action=reject&id=<?php echo $ngo['NGOID']; ?>" 
                               class="btn-action btn-reject">
                                <i class="fas fa-times"></i> Reject
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-check-circle text-success"></i>
                        <h5>No Pending Registrations</h5>
                        <p>All NGO registrations have been processed</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- ALL NGOs TABLE -->
        <div class="table-container">
            <div class="card-header">
                <h5><i class="fas fa-list"></i> All NGOs</h5>
                <span style="color: #7f8c8d; font-size: 14px;">
                    <i class="fas fa-database me-1"></i>
                    Total: <?php echo count($all_ngos); ?> NGOs
                </span>
            </div>
            
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>NGO Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Status</th>
                            <th>Registration Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(count($all_ngos) > 0): ?>
                            <?php foreach($all_ngos as $ngo): ?>
                            <tr>
                                <td>
                                    <strong class="text-primary">#<?php echo htmlspecialchars($ngo['NGOID']); ?></strong>
                                </td>
                                <td>
                                    <div class="fw-semibold"><?php echo htmlspecialchars($ngo['NGOName']); ?></div>
                                    <small class="text-muted">
                                        <i class="fas fa-id-card me-1"></i>
                                        <?php echo htmlspecialchars($ngo['RegistrationNo'] ?? 'N/A'); ?>
                                    </small>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <i class="fas fa-envelope text-muted me-2"></i>
                                        <?php echo htmlspecialchars($ngo['Email']); ?>
                                    </div>
                                </td>
                                <td>
                                    <?php if($ngo['Phone']): ?>
                                        <div class="d-flex align-items-center">
                                            <i class="fas fa-phone text-muted me-2"></i>
                                            <?php echo htmlspecialchars($ngo['Phone']); ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php 
                                    $badgeClass = '';
                                    if($ngo['status'] == 'Pending') $badgeClass = 'badge-pending';
                                    if($ngo['status'] == 'Approved') $badgeClass = 'badge-approved';
                                    if($ngo['status'] == 'Rejected') $badgeClass = 'badge-rejected';
                                    ?>
                                    <span class="badge <?php echo $badgeClass; ?>">
                                        <?php if($ngo['status'] == 'Pending'): ?>
                                            <i class="fas fa-clock me-1"></i>
                                        <?php elseif($ngo['status'] == 'Approved'): ?>
                                            <i class="fas fa-check me-1"></i>
                                        <?php elseif($ngo['status'] == 'Rejected'): ?>
                                            <i class="fas fa-times me-1"></i>
                                        <?php endif; ?>
                                        <?php echo htmlspecialchars($ngo['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="text-muted">
                                        <?php 
                                        if($ngo['CreatedAt'] instanceof DateTime){
                                            echo $ngo['CreatedAt']->format('d M Y');
                                        } else {
                                            echo date('d M Y', strtotime($ngo['CreatedAt']));
                                        }
                                        ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="action-buttons" style="border: none; padding: 0; margin: 0;">
                                        <?php if($ngo['status'] == 'Pending'): ?>
                                            <a href="admin_manage_ngo.php?action=approve&id=<?php echo $ngo['NGOID']; ?>" 
                                               class="btn-action btn-approve" style="padding: 5px 10px; font-size: 12px;">
                                                <i class="fas fa-check"></i> Approve
                                            </a>
                                            <a href="admin_manage_ngo.php?action=reject&id=<?php echo $ngo['NGOID']; ?>" 
                                               class="btn-action btn-reject" style="padding: 5px 10px; font-size: 12px;">
                                                <i class="fas fa-times"></i> Reject
                                            </a>
                                        <?php elseif($ngo['status'] == 'Approved'): ?>
                                            <a href="admin_manage_ngo.php?action=reject&id=<?php echo $ngo['NGOID']; ?>" 
                                               class="btn-action btn-reject" style="padding: 5px 10px; font-size: 12px;">
                                                <i class="fas fa-times"></i> Reject
                                            </a>
                                        <?php elseif($ngo['status'] == 'Rejected'): ?>
                                            <a href="admin_manage_ngo.php?action=approve&id=<?php echo $ngo['NGOID']; ?>" 
                                               class="btn-action btn-approve" style="padding: 5px 10px; font-size: 12px;">
                                                <i class="fas fa-check"></i> Approve
                                            </a>
                                        <?php endif; ?>
                                        
                                        <a href="admin_manage_ngo.php?action=delete&id=<?php echo $ngo['NGOID']; ?>" 
                                           class="btn-action btn-delete" style="padding: 5px 10px; font-size: 12px;">
                                            <i class="fas fa-trash"></i> Delete
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center py-4">
                                    <div class="empty-state">
                                        <i class="fas fa-building text-muted"></i>
                                        <h5>No NGOs Found</h5>
                                        <p>No NGOs have registered yet</p>
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
            window.location.href = 'admin_manage_ngo.php#pending';
        });

        // Action confirmation with SweetAlert
        document.querySelectorAll('.btn-action').forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                
                const action = this.classList.contains('btn-approve') ? 'approve' : 
                              this.classList.contains('btn-reject') ? 'reject' : 'delete';
                const url = this.getAttribute('href');
                const ngoName = this.closest('.ngo-card') ? 
                    this.closest('.ngo-card').querySelector('h5').textContent.trim() :
                    this.closest('tr').querySelector('td:nth-child(2) .fw-semibold').textContent.trim();
                
                let title, text, icon, confirmButtonText, confirmButtonColor;
                
                if(action === 'approve') {
                    title = 'Approve NGO?';
                    text = `Are you sure you want to approve "${ngoName}"?`;
                    icon = 'warning';
                    confirmButtonText = '<i class="fas fa-check me-2"></i>Approve';
                    confirmButtonColor = '#27ae60';
                } else if(action === 'reject') {
                    title = 'Reject NGO?';
                    text = `Are you sure you want to reject "${ngoName}"?`;
                    icon = 'error';
                    confirmButtonText = '<i class="fas fa-times me-2"></i>Reject';
                    confirmButtonColor = '#e74c3c';
                } else {
                    title = 'Delete NGO?';
                    text = `⚠️ Are you sure you want to permanently delete "${ngoName}"?<br><br><strong>This action cannot be undone!</strong>`;
                    icon = 'warning';
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
                        
                        // Redirect to action URL
                        window.location.href = url;
                    }
                });
            });
        });

        // Search functionality
        document.getElementById('searchInput').addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            const cards = document.querySelectorAll('.ngo-card');
            const rows = document.querySelectorAll('.table tbody tr');
            
            // Search in cards (pending section)
            cards.forEach(card => {
                const text = card.textContent.toLowerCase();
                card.style.display = text.includes(searchTerm) ? '' : 'none';
            });
            
            // Search in table rows (all NGOs section)
            rows.forEach(row => {
                if(row.querySelector('.empty-state')) return;
                
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchTerm) ? '' : 'none';
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