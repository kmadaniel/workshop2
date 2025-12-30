<?php
session_start();
if(!isset($_SESSION['name']) || $_SESSION['role'] != "admin"){
    header("Location: login.php");
    exit();
}

// Database connection (sama seperti sebelumnya)
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

// Get total admins count
$sqlAdmins = "SELECT COUNT(*) as total FROM dbo.Admin";
$stmtAdmins = sqlsrv_query($conn, $sqlAdmins);
$rowAdmins = sqlsrv_fetch_array($stmtAdmins, SQLSRV_FETCH_ASSOC);
$totalAdmins = $rowAdmins['total'] ?? 0;
sqlsrv_free_stmt($stmtAdmins);

// Get total volunteers count
$totalVolunteers = 0;
$sqlVolunteers = "SELECT COUNT(*) as total FROM dbo.Volunteer";
$stmtVolunteers = sqlsrv_query($conn, $sqlVolunteers);
if($stmtVolunteers !== false) {
    $rowVolunteers = sqlsrv_fetch_array($stmtVolunteers, SQLSRV_FETCH_ASSOC);
    $totalVolunteers = $rowVolunteers['total'] ?? 0;
    sqlsrv_free_stmt($stmtVolunteers);
}

// Get NGOs count
$totalNGOs = 0;
$sqlNGOs = "SELECT COUNT(*) as total FROM dbo.NGO";
$stmtNGOs = sqlsrv_query($conn, $sqlNGOs);
if($stmtNGOs !== false) {
    $rowNGOs = sqlsrv_fetch_array($stmtNGOs, SQLSRV_FETCH_ASSOC);
    $totalNGOs = $rowNGOs['total'] ?? 0;
    sqlsrv_free_stmt($stmtNGOs);
}

// Get reports count
$totalReports = 0;
$sqlReports = "SELECT COUNT(*) as total FROM dbo.Reports";
$stmtReports = sqlsrv_query($conn, $sqlReports);
if($stmtReports !== false) {
    $rowReports = sqlsrv_fetch_array($stmtReports, SQLSRV_FETCH_ASSOC);
    $totalReports = $rowReports['total'] ?? 0;
    sqlsrv_free_stmt($stmtReports);
}

sqlsrv_close($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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

        /* Sidebar Navigation - FIXED SCROLL ISSUE */
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
            overflow: hidden; /* Parent hide overflow */
        }

        .sidebar-content {
            flex: 1;
            overflow-y: auto; /* Scroll content saja */
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

        /* Top Action Header */
        .top-action-header {
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 25px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            border: 1px solid #e9ecef;
        }

        .top-action-header h2 {
            color: #2c3e50;
            margin-bottom: 20px;
            text-align: center;
            font-size: 22px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        /* Stats Cards */
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
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
            width: 60px;
            height: 60px;
            margin: 0 auto 15px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
        }

        .stat-number {
            font-size: 36px;
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
        .stat-admin { border-top: 4px solid #3498db; }
        .stat-admin .stat-icon { background: rgba(52, 152, 219, 0.1); color: #3498db; }

        .stat-ngo { border-top: 4px solid #9b59b6; }
        .stat-ngo .stat-icon { background: rgba(155, 89, 182, 0.1); color: #9b59b6; }

        .stat-volunteer { border-top: 4px solid #2ecc71; }
        .stat-volunteer .stat-icon { background: rgba(46, 204, 113, 0.1); color: #2ecc71; }

        .stat-report { border-top: 4px solid #e74c3c; }
        .stat-report .stat-icon { background: rgba(231, 76, 60, 0.1); color: #e74c3c; }

        /* Activity Section */
        .activity-section {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            border: 1px solid #e9ecef;
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
                grid-template-columns: 1fr;
            }
            
            .nav-container {
                padding: 10px 15px;
            }
            
            .nav-btn {
                padding: 8px 12px;
                font-size: 13px;
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
                    <input type="text" placeholder="Search...">
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
            <a href="admin_dashboard.php" class="nav-btn active">
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

    <!-- Sidebar Navigation - FIXED SCROLL -->
    <nav class="sidebar" id="sidebar">
        <div class="sidebar-content">
            <ul class="nav-menu">
                <li class="nav-label">MAIN NAVIGATION</li>
                
                <li class="nav-item">
                    <a href="admin_dashboard.php" class="nav-link active">
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
            <a href="main_page.php">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="main-content" id="mainContent">
        <div class="top-action-header">
            <h2>
                <i class="fas fa-tachometer-alt"></i>
                Welcome, <?php echo $_SESSION['name']; ?>
            </h2>
            <p class="text-center text-muted mb-0">Admin Dashboard Overview</p>
        </div>

        <!-- Stats Cards -->
        <div class="stats-container">
            <div class="stat-card stat-admin">
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-number"><?php echo $totalAdmins; ?></div>
                <div class="stat-label">Total Admins</div>
            </div>
            
            <div class="stat-card stat-ngo">
                <div class="stat-icon">
                    <i class="fas fa-building"></i>
                </div>
                <div class="stat-number"><?php echo $totalNGOs; ?></div>
                <div class="stat-label">Total NGOs</div>
            </div>
            
            <div class="stat-card stat-volunteer">
                <div class="stat-icon">
                    <i class="fas fa-hands-helping"></i>
                </div>
                <div class="stat-number"><?php echo $totalVolunteers; ?></div>
                <div class="stat-label">Volunteers</div>
            </div>
            
            <div class="stat-card stat-report">
                <div class="stat-icon">
                    <i class="fas fa-chart-bar"></i>
                </div>
                <div class="stat-number"><?php echo $totalReports; ?></div>
                <div class="stat-label">Reports</div>
            </div>
        </div>

        <!-- Activity Section -->
        <div class="activity-section">
            <h3>
                <i class="fas fa-history"></i>
                Recent Activity
            </h3>
            <ul class="activity-list">
                <li class="activity-item">
                    <div class="activity-icon" style="background: rgba(46, 204, 113, 0.1); color: #2ecc71;">
                        <i class="fas fa-sign-in-alt"></i>
                    </div>
                    <div class="activity-text">
                        You logged in to the system
                        <div class="activity-time"><?php echo date('h:i A'); ?></div>
                    </div>
                </li>
                
                <li class="activity-item">
                    <div class="activity-icon" style="background: rgba(52, 152, 219, 0.1); color: #3498db;">
                        <i class="fas fa-tachometer-alt"></i>
                    </div>
                    <div class="activity-text">
                        Viewing admin dashboard
                        <div class="activity-time"><?php echo date('h:i A', strtotime('-5 minutes')); ?></div>
                    </div>
                </li>
                
                <li class="activity-item">
                    <div class="activity-icon" style="background: rgba(155, 89, 182, 0.1); color: #9b59b6;">
                        <i class="fas fa-user-shield"></i>
                    </div>
                    <div class="activity-text">
                        Logged in as Administrator
                        <div class="activity-time"><?php echo date('h:i A', strtotime('-10 minutes')); ?></div>
                    </div>
                </li>
                
                <li class="activity-item">
                    <div class="activity-icon" style="background: rgba(241, 196, 15, 0.1); color: #f1c40f;">
                        <i class="fas fa-sync-alt"></i>
                    </div>
                    <div class="activity-text">
                        Dashboard updated
                        <div class="activity-time">Last updated: <?php echo date('h:i A'); ?></div>
                    </div>
                </li>
            </ul>
        </div>
    </main>

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

        // Smooth scroll for sidebar
        document.querySelector('.sidebar-content').addEventListener('wheel', function(e) {
            e.preventDefault();
            this.scrollTop += e.deltaY;
        });
    </script>
</body>
</html>