<?php
session_start();
if(!isset($_SESSION['name']) || $_SESSION['role'] != "admin"){
    header("Location: login.php");
    exit();
}

// Database connection
$serverName = "localhost";
$connectionOptions = array(
    "Database" => "UserManagement",
    "Uid" => "yanadb",
    "PWD" => "yana123"
);

$conn = sqlsrv_connect($serverName, $connectionOptions);

if($conn === false) {
    die(print_r(sqlsrv_errors(), true));
}

// Query to get all volunteers with NGO name (JOIN with NGO table)
$sql = "SELECT v.VolunteerID, v.FullName, v.Email, v.Phone, v.Address, 
               v.AssignedNGO, v.PasswordHash, v.SkillCategory,
               n.NGOName
        FROM dbo.volunteer v
        LEFT JOIN dbo.NGO n ON v.AssignedNGO = n.NGOID
        ORDER BY v.VolunteerID DESC";
$stmt = sqlsrv_query($conn, $sql);

if($stmt === false) {
    die(print_r(sqlsrv_errors(), true));
}

// Fetch all volunteer data
$volunteers = array();
while($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $volunteers[] = $row;
}

// Get total count
$sqlCount = "SELECT COUNT(*) as total FROM dbo.volunteer";
$stmtCount = sqlsrv_query($conn, $sqlCount);
$rowCount = sqlsrv_fetch_array($stmtCount, SQLSRV_FETCH_ASSOC);
$totalVolunteers = $rowCount['total'] ?? 0;

// Get volunteers by category count
$sqlByCategory = "SELECT SkillCategory, COUNT(*) as count 
                  FROM dbo.volunteer 
                  GROUP BY SkillCategory";
$stmtCategory = sqlsrv_query($conn, $sqlByCategory);
$categoryCounts = array();
while($row = sqlsrv_fetch_array($stmtCategory, SQLSRV_FETCH_ASSOC)) {
    $categoryCounts[$row['SkillCategory']] = $row['count'];
}

sqlsrv_free_stmt($stmt);
sqlsrv_free_stmt($stmtCount);
sqlsrv_free_stmt($stmtCategory);
sqlsrv_close($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Volunteers - Admin Panel</title>
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
        .stat-total { border-top: 4px solid #3498db; }
        .stat-total .stat-icon { background: rgba(52, 152, 219, 0.1); color: #3498db; }

        .stat-medical { border-top: 4px solid #e74c3c; }
        .stat-medical .stat-icon { background: rgba(231, 76, 60, 0.1); color: #e74c3c; }

        .stat-technical { border-top: 4px solid #2ecc71; }
        .stat-technical .stat-icon { background: rgba(46, 204, 113, 0.1); color: #2ecc71; }

        .stat-education { border-top: 4px solid #9b59b6; }
        .stat-education .stat-icon { background: rgba(155, 89, 182, 0.1); color: #9b59b6; }

        /* Badges */
        .badge-category {
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .badge-medical {
            background: rgba(231, 76, 60, 0.1);
            color: #e74c3c;
            border: 1px solid rgba(231, 76, 60, 0.3);
        }
        
        .badge-technical {
            background: rgba(46, 204, 113, 0.1);
            color: #2ecc71;
            border: 1px solid rgba(46, 204, 113, 0.3);
        }
        
        .badge-education {
            background: rgba(155, 89, 182, 0.1);
            color: #9b59b6;
            border: 1px solid rgba(155, 89, 182, 0.3);
        }
        
        .badge-other {
            background: rgba(52, 152, 219, 0.1);
            color: #3498db;
            border: 1px solid rgba(52, 152, 219, 0.3);
        }

        /* Volunteer Table Card */
        .table-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            border: 1px solid #e9ecef;
            overflow: hidden;
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
            border-radius: 0 0 12px 12px;
            overflow: hidden;
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

        /* Avatar */
        .user-avatar-sm {
            width: 35px;
            height: 35px;
            background: linear-gradient(135deg, #4fc3f7 0%, #0288d1 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: white;
            font-size: 14px;
        }

        /* Badges */
        .badge {
            padding: 6px 12px;
            font-weight: 600;
            border-radius: 20px;
            font-size: 12px;
        }

        .badge-assigned {
            background: linear-gradient(135deg, #2ecc71 0%, #27ae60 100%);
            color: white;
        }

        .badge-unassigned {
            background: linear-gradient(135deg, #f39c12 0%, #d35400 100%);
            color: white;
        }

        .badge-warning {
            background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%);
            color: white;
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 8px;
        }

        .btn-action {
            width: 35px;
            height: 35px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: none;
            transition: all 0.3s ease;
        }

        .btn-action:hover {
            transform: translateY(-2px);
        }

        .btn-edit {
            background: rgba(52, 152, 219, 0.1);
            color: #3498db;
        }

        .btn-edit:hover {
            background: #3498db;
            color: white;
        }

        .btn-delete {
            background: rgba(231, 76, 60, 0.1);
            color: #e74c3c;
        }

        .btn-delete:hover {
            background: #e74c3c;
            color: white;
        }

        .btn-assign {
            background: rgba(46, 204, 113, 0.1);
            color: #2ecc71;
        }

        .btn-assign:hover {
            background: #2ecc71;
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
                grid-template-columns: 1fr;
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
                    <input type="text" placeholder="Search volunteers...">
                </div>
                
                <div class="notifications" id="notificationsBtn">
                    <i class="fas fa-bell"></i>
                    <span class="notification-badge">3</span>
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
            <a href="view_volunteers.php" class="nav-btn active">
                <i class="fas fa-hands-helping"></i>
                <span>View Volunteers</span>
            </a>
            <a href="admin_manage_ngo.php" class="nav-btn">
                <i class="fas fa-building"></i>
                <span>View NGO</span>
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
                    <a href="view_volunteer.php" class="nav-link active">
                        <i class="fas fa-hands-helping"></i>
                        <span class="nav-text">View Volunteer</span>
                    </a>
                </li>
                
                <li class="nav-item">
                    <a href="admin_manage_ngo.php" class="nav-link">
                        <i class="fas fa-building"></i>
                        <span class="nav-text">View NGO</span>
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
        <!-- Page Header -->
        <div class="page-header">
            <div>
                <h2><i class="fas fa-hands-helping"></i> Volunteer Management</h2>
                <p>View and manage all volunteers and their assigned NGOs</p>
            </div>
            <div>
                <a href="add_volunteer.php" class="btn btn-primary">
                    <i class="fas fa-plus me-2"></i> Add New Volunteer
                </a>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="stats-container">
            <div class="stat-card stat-total">
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-number"><?php echo $totalVolunteers; ?></div>
                <div class="stat-label">Total Volunteers</div>
            </div>
            
            <div class="stat-card stat-medical">
                <div class="stat-icon">
                    <i class="fas fa-stethoscope"></i>
                </div>
                <div class="stat-number"><?php echo $categoryCounts['Medical'] ?? 0; ?></div>
                <div class="stat-label">Medical Volunteers</div>
            </div>
            
            <div class="stat-card stat-technical">
                <div class="stat-icon">
                    <i class="fas fa-tools"></i>
                </div>
                <div class="stat-number"><?php echo $categoryCounts['Technical'] ?? 0; ?></div>
                <div class="stat-label">Technical Volunteers</div>
            </div>
            
            <div class="stat-card stat-education">
                <div class="stat-icon">
                    <i class="fas fa-graduation-cap"></i>
                </div>
                <div class="stat-number"><?php echo $categoryCounts['Education'] ?? 0; ?></div>
                <div class="stat-label">Education Volunteers</div>
            </div>
        </div>

        <!-- Volunteer Table Card -->
        <div class="table-card">
            <div class="card-header">
                <h5><i class="fas fa-list"></i> Volunteer List</h5>
                <div class="d-flex align-items-center gap-3">
                    <span class="badge badge-warning">
                        <i class="fas fa-exclamation-triangle me-1"></i>
                        Total: <?php echo count($volunteers); ?> records
                    </span>
                </div>
            </div>
            
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Contact</th>
                            <th>Address</th>
                            <th>Skills</th>
                            <th>Assigned NGO</th>
                            <th>Status</th>
                           
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($volunteers)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-4">
                                    <div class="empty-state">
                                        <i class="fas fa-user-slash"></i>
                                        <h5>No Volunteers Found</h5>
                                        <p>Start by adding a new volunteer</p>
                                        <a href="add_volunteer.php" class="btn btn-primary mt-2">
                                            <i class="fas fa-plus me-2"></i> Add First Volunteer
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach($volunteers as $volunteer): ?>
                            <tr>
                                <td>
                                    <strong class="text-primary">#<?php echo $volunteer['VolunteerID']; ?></strong>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="user-avatar-sm">
                                            <?php echo strtoupper(substr($volunteer['FullName'], 0, 1)); ?>
                                        </div>
                                        <div>
                                            <div class="fw-semibold"><?php echo htmlspecialchars($volunteer['FullName']); ?></div>
                                            <small class="text-muted">
                                                <?php echo htmlspecialchars($volunteer['Email']); ?>
                                            </small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="d-flex flex-column">
                                        <div>
                                            <i class="fas fa-envelope text-muted me-2"></i>
                                            <?php echo htmlspecialchars($volunteer['Email']); ?>
                                        </div>
                                        <?php if($volunteer['Phone']): ?>
                                        <div class="mt-1">
                                            <i class="fas fa-phone text-muted me-2"></i>
                                            <?php echo htmlspecialchars($volunteer['Phone']); ?>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="text-muted small">
                                        <?php echo htmlspecialchars($volunteer['Address']); ?>
                                    </div>
                                </td>
                                <td>
                                    <?php if($volunteer['SkillCategory']): 
                                        $badgeClass = '';
                                        $category = $volunteer['SkillCategory'];
                                        
                                        if(strpos(strtolower($category), 'medic') !== false) {
                                            $badgeClass = 'badge-medical';
                                            $icon = 'fa-stethoscope';
                                        } elseif(strpos(strtolower($category), 'tech') !== false) {
                                            $badgeClass = 'badge-technical';
                                            $icon = 'fa-tools';
                                        } elseif(strpos(strtolower($category), 'edu') !== false) {
                                            $badgeClass = 'badge-education';
                                            $icon = 'fa-graduation-cap';
                                        } else {
                                            $badgeClass = 'badge-other';
                                            $icon = 'fa-star';
                                        }
                                    ?>
                                        <span class="badge-category <?php echo $badgeClass; ?>">
                                            <i class="fas <?php echo $icon; ?> me-1"></i>
                                            <?php echo htmlspecialchars($volunteer['SkillCategory']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">Not specified</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if($volunteer['AssignedNGO'] && $volunteer['NGOName']): ?>
                                        <div class="fw-semibold">
                                            <i class="fas fa-building text-primary me-1"></i>
                                            <?php echo htmlspecialchars($volunteer['NGOName']); ?>
                                        </div>
                                        <small class="text-muted">
                                            ID: <?php echo $volunteer['AssignedNGO']; ?>
                                        </small>
                                    <?php elseif($volunteer['AssignedNGO']): ?>
                                        <div class="text-warning">
                                            <i class="fas fa-building me-1"></i>
                                            NGO ID: <?php echo $volunteer['AssignedNGO']; ?>
                                        </div>
                                        <small class="text-muted">(Name not found)</small>
                                    <?php else: ?>
                                        <span class="text-muted">Not assigned</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if($volunteer['AssignedNGO']): ?>
                                        <span class="badge badge-assigned">
                                            <i class="fas fa-check-circle me-1"></i>
                                            Assigned
                                        </span>
                                    <?php else: ?>
                                        <span class="badge badge-unassigned">
                                            <i class="fas fa-clock me-1"></i>
                                            Unassigned
                                        </span>
                                    <?php endif; ?>
                                </td>
                            
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
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
            alert('Notifications feature would open here');
        });

        // Delete confirmation with SweetAlert
        document.querySelectorAll('.delete-btn').forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                
                const volunteerId = this.getAttribute('data-id');
                const volunteerName = this.getAttribute('data-name');
                
                Swal.fire({
                    title: 'Delete Volunteer?',
                    html: `<div style="text-align: center;">
                              <i class="fas fa-exclamation-triangle fa-3x text-warning mb-3"></i>
                              <p>Are you sure you want to delete volunteer <strong>"${volunteerName}"</strong>?</p>
                              <div class="alert alert-warning mt-2 mb-0">
                                  <i class="fas fa-exclamation-circle me-2"></i>
                                  This action cannot be undone.
                              </div>
                           </div>`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#3085d6',
                    confirmButtonText: '<i class="fas fa-trash me-2"></i>Delete',
                    cancelButtonText: '<i class="fas fa-times me-2"></i>Cancel',
                    width: '450px',
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
                            title: 'Deleting...',
                            text: 'Please wait while we delete the volunteer',
                            allowOutsideClick: false,
                            didOpen: () => {
                                Swal.showLoading();
                            }
                        });
                        
                        // Redirect to delete script
                        window.location.href = `delete_volunteer.php?id=${volunteerId}`;
                    }
                });
            });
        });

        // Check for delete success/failure message in URL
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const deleteStatus = urlParams.get('delete');
            
            if(deleteStatus === 'success') {
                Swal.fire({
                    title: 'Success!',
                    text: 'Volunteer has been deleted successfully.',
                    icon: 'success',
                    confirmButtonColor: '#3085d6',
                    timer: 3000,
                    showClass: {
                        popup: 'animate__animated animate__fadeInDown'
                    }
                }).then(() => {
                    // Remove parameter from URL
                    window.history.replaceState({}, document.title, window.location.pathname);
                });
            } else if(deleteStatus === 'error') {
                Swal.fire({
                    title: 'Error!',
                    text: 'Failed to delete volunteer. Please try again.',
                    icon: 'error',
                    confirmButtonColor: '#d33'
                }).then(() => {
                    // Remove parameter from URL
                    window.history.replaceState({}, document.title, window.location.pathname);
                });
            }
        });

        // Search functionality
        document.querySelector('.search-box input').addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            const rows = document.querySelectorAll('.table tbody tr');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchTerm) ? '' : 'none';
            });
        });
    </script>
</body>
</html>