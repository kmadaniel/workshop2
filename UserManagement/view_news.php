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

// Query to get all news
$sql = "SELECT NewsID, Title, Description, ImageURL, CreatedAt, CreatedBy 
        FROM dbo.News 
        ORDER BY CreatedAt DESC";
$stmt = sqlsrv_query($conn, $sql);

if($stmt === false) {
    die(print_r(sqlsrv_errors(), true));
}

// Fetch all news data
$news = array();
while($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $news[] = $row;
}

sqlsrv_free_stmt($stmt);

// Get statistics
$today = date('Y-m-d');
$todayCount = 0;
$withImages = 0;
$authors = array();

foreach($news as $item) {
    // Today's news count
    $newsDate = $item['CreatedAt'] instanceof DateTime 
        ? $item['CreatedAt']->format('Y-m-d') 
        : date('Y-m-d', strtotime($item['CreatedAt']));
    if($newsDate == $today) {
        $todayCount++;
    }
    
    // News with images count
    if(!empty($item['ImageURL'])) {
        $withImages++;
    }
    
    // Unique authors count
    $authors[$item['CreatedBy']] = true;
}

$totalAuthors = count($authors);
$totalNews = count($news);

sqlsrv_close($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View News - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* System Header - Same as Dashboard */
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

        /* Main Content */
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

        /* Content Header */
        .content-header {
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 25px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            border: 1px solid #e9ecef;
        }

        .content-header h2 {
            color: #2c3e50;
            margin-bottom: 10px;
            font-size: 22px;
            display: flex;
            align-items: center;
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
        .stat-total { border-top: 4px solid #3498db; }
        .stat-total .stat-icon { background: rgba(52, 152, 219, 0.1); color: #3498db; }

        .stat-today { border-top: 4px solid #2ecc71; }
        .stat-today .stat-icon { background: rgba(46, 204, 113, 0.1); color: #2ecc71; }

        .stat-images { border-top: 4px solid #e74c3c; }
        .stat-images .stat-icon { background: rgba(231, 76, 60, 0.1); color: #e74c3c; }

        .stat-authors { border-top: 4px solid #9b59b6; }
        .stat-authors .stat-icon { background: rgba(155, 89, 182, 0.1); color: #9b59b6; }

        /* News Grid */
        .news-grid-container {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            border: 1px solid #e9ecef;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f1f1f1;
        }

        .section-header h3 {
            color: #2c3e50;
            font-size: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 0;
        }

        .news-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 25px;
        }

        .news-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            border: 1px solid #e9ecef;
            transition: all 0.3s ease;
            height: 100%;
            display: flex;
            flex-direction: column;
        }

        .news-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
            border-color: #4fc3f7;
        }

        .news-img-container {
            height: 200px;
            overflow: hidden;
            position: relative;
        }

        .news-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s ease;
        }

        .news-card:hover .news-img {
            transform: scale(1.05);
        }

        .no-image {
            background: linear-gradient(135deg, #6c757d 0%, #495057 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100%;
        }

        .no-image i {
            font-size: 48px;
            color: white;
            opacity: 0.7;
        }

        .news-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            color: white;
        }

        .badge-new {
            background: #e74c3c;
        }

        .badge-old {
            background: #6c757d;
        }

        .news-content {
            padding: 20px;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
        }

        .news-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }

        .news-date {
            font-size: 13px;
            color: #6c757d;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .news-author {
            font-size: 13px;
            color: #3498db;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .news-title {
            font-size: 18px;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 12px;
            line-height: 1.4;
            display: -webkit-box;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .news-desc {
            font-size: 14px;
            color: #6c757d;
            line-height: 1.6;
            flex-grow: 1;
            display: -webkit-box;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .news-actions {
            display: flex;
            justify-content: space-between;
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px solid #f1f1f1;
        }

        .btn-edit, .btn-delete {
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }

        .btn-edit {
            background: #e3f2fd;
            color: #1976d2;
            border: 1px solid #bbdefb;
        }

        .btn-edit:hover {
            background: #bbdefb;
            transform: translateY(-2px);
        }

        .btn-delete {
            background: #ffebee;
            color: #d32f2f;
            border: 1px solid #ffcdd2;
        }

        .btn-delete:hover {
            background: #ffcdd2;
            transform: translateY(-2px);
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            grid-column: 1 / -1;
        }

        .empty-state i {
            font-size: 64px;
            color: #dee2e6;
            margin-bottom: 20px;
        }

        .empty-state h4 {
            color: #6c757d;
            margin-bottom: 10px;
        }

        .empty-state p {
            color: #adb5bd;
            margin-bottom: 25px;
        }

        .btn-create-news {
            background: linear-gradient(135deg, #4fc3f7 0%, #0288d1 100%);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 16px;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
        }

        .btn-create-news:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(79, 195, 247, 0.4);
            color: white;
        }

        /* Alert Messages */
        .alert-fixed {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1050;
            max-width: 400px;
            animation: slideIn 0.3s ease-out;
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
            
            .news-grid {
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

        @media (max-width: 480px) {
            .stats-container {
                grid-template-columns: 1fr;
            }
            
            .section-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .news-actions {
                flex-direction: column;
                gap: 10px;
            }
            
            .btn-edit, .btn-delete {
                width: 100%;
                justify-content: center;
            }
            
            .alert-fixed {
                left: 20px;
                right: 20px;
                max-width: calc(100% - 40px);
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
                    <i class="fas fa-newspaper"></i>
                </div>
                <div class="logo-text">
                    <h1>News Management</h1>
                    <small>View All News Articles</small>
                </div>
            </div>
            
            <div class="header-controls">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" id="searchInput" placeholder="Search news...">
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
                <i class="fas fa-building"></i>
                <span>View NGO</span>
            </a>
            
            <a href="view_news.php" class="nav-btn active">
                <i class="fas fa-list"></i>
                <span>View News</span>
            </a>
           
            <a href="bridge_to_distribution.php" class="nav-btn" target="_blank">
                <i class="fas fa-truck"></i>
                <span>Distribution System</span>
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
                        <i class="fas fa-building"></i>
                        <span class="nav-text">View NGO</span>
                    </a>
                </li>
                
                <li class="nav-divider"></li>
                
                <li class="nav-label">CONTENT MANAGEMENT</li>
                
              
                
                <li class="nav-item">
                    <a href="view_news.php" class="nav-link active">
                        <i class="fas fa-list"></i>
                        <span class="nav-text">View News</span>
                    </a>
                </li>
                
                <li class="nav-divider"></li>
                
                <li class="nav-label">OPERATIONS</li>
                
                <li class="nav-item">
                    <a href="bridge_to_distribution.php" class="nav-link">
                        <i class="fas fa-truck"></i>
                        <span class="nav-text">Distribution System</span>
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
        <!-- Content Header -->
        <div class="content-header">
            <h2>
                <i class="fas fa-newspaper"></i>
                News Management
            </h2>
            <p class="text-muted mb-0">View and manage all published news articles. <span class="badge bg-info">Logged in as: <?php echo $_SESSION['name']; ?></span></p>
        </div>

        <!-- Stats Cards -->
        <div class="stats-container">
            <div class="stat-card stat-total">
                <div class="stat-icon">
                    <i class="fas fa-newspaper"></i>
                </div>
                <div class="stat-number"><?php echo $totalNews; ?></div>
                <div class="stat-label">Total News</div>
            </div>
            
            <div class="stat-card stat-today">
                <div class="stat-icon">
                    <i class="fas fa-calendar-alt"></i>
                </div>
                <div class="stat-number"><?php echo $todayCount; ?></div>
                <div class="stat-label">Today's News</div>
            </div>
            
            <div class="stat-card stat-images">
                <div class="stat-icon">
                    <i class="fas fa-image"></i>
                </div>
                <div class="stat-number"><?php echo $withImages; ?></div>
                <div class="stat-label">With Images</div>
            </div>
            
            <div class="stat-card stat-authors">
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-number"><?php echo $totalAuthors; ?></div>
                <div class="stat-label">Authors</div>
            </div>
        </div>

        <!-- News Grid -->
        <div class="news-grid-container">
            <div class="section-header">
                <h3><i class="fas fa-list"></i> All News Articles</h3>
                <div class="d-flex align-items-center gap-3">
                    <span class="badge bg-dark fs-6"><?php echo $totalNews; ?> articles</span>
                    <a href="create_news.php" class="btn-create-news">
                        <i class="fas fa-plus"></i> Create News
                    </a>
                </div>
            </div>
            
            <?php if(empty($news)): ?>
                <div class="empty-state">
                    <i class="fas fa-newspaper"></i>
                    <h4>No News Found</h4>
                    <p>Start by creating your first news article</p>
                    <a href="create_news.php" class="btn-create-news">
                        <i class="fas fa-plus"></i> Create First News
                    </a>
                </div>
            <?php else: ?>
                <div class="news-grid" id="newsGrid">
                    <?php foreach($news as $item): 
                        $isNew = false;
                        $newsDate = $item['CreatedAt'] instanceof DateTime 
                            ? $item['CreatedAt']->format('Y-m-d') 
                            : date('Y-m-d', strtotime($item['CreatedAt']));
                        if($newsDate == date('Y-m-d')) {
                            $isNew = true;
                        }
                        
                        // Format date for display
                        if($item['CreatedAt'] instanceof DateTime) {
                            $displayDate = $item['CreatedAt']->format('M d, Y');
                        } else {
                            $displayDate = date('M d, Y', strtotime($item['CreatedAt']));
                        }
                        
                        // Clean description for display
                        $cleanDescription = strip_tags($item['Description']);
                        $shortDescription = strlen($cleanDescription) > 150 ? substr($cleanDescription, 0, 150) . '...' : $cleanDescription;
                    ?>
                    <div class="news-card" data-title="<?php echo strtolower(htmlspecialchars($item['Title'])); ?>" 
                         data-author="<?php echo strtolower(htmlspecialchars($item['CreatedBy'])); ?>">
                        <div class="news-img-container">
                            <?php if(!empty($item['ImageURL'])): ?>
                                <img src="<?php echo htmlspecialchars($item['ImageURL']); ?>" 
                                     class="news-img" 
                                     alt="<?php echo htmlspecialchars($item['Title']); ?>"
                                     onerror="this.src='data:image/svg+xml;charset=UTF-8,%3Csvg%20width%3D%22320%22%20height%3D%22200%22%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%3E%3Crect%20width%3D%22100%25%22%20height%3D%22100%25%22%20fill%3D%22%236c757d%22%2F%3E%3Ctext%20x%3D%2250%25%22%20y%3D%2250%25%22%20font-family%3D%22Arial%22%20font-size%3D%2216%22%20fill%3D%22%23fff%22%20text-anchor%3D%22middle%22%20dy%3D%22.3em%22%3ENo%20Image%3C%2Ftext%3E%3C%2Fsvg%3E'">
                            <?php else: ?>
                                <div class="no-image">
                                    <i class="fas fa-newspaper"></i>
                                </div>
                            <?php endif; ?>
                            <span class="news-badge <?php echo $isNew ? 'badge-new' : 'badge-old'; ?>">
                                <?php echo $isNew ? 'NEW' : 'POSTED'; ?>
                            </span>
                        </div>
                        <div class="news-content">
                            <div class="news-meta">
                                <span class="news-date">
                                    <i class="far fa-calendar"></i> <?php echo $displayDate; ?>
                                </span>
                                <span class="news-author">
                                    <i class="fas fa-user"></i> <?php echo htmlspecialchars($item['CreatedBy']); ?>
                                </span>
                            </div>
                            <h4 class="news-title" title="<?php echo htmlspecialchars($item['Title']); ?>">
                                <?php echo htmlspecialchars($item['Title']); ?>
                            </h4>
                            <p class="news-desc" title="<?php echo htmlspecialchars($cleanDescription); ?>">
                                <?php echo htmlspecialchars($shortDescription); ?>
                            </p>
                            <div class="news-actions">
                                <a href="edit_news.php?id=<?php echo $item['NewsID']; ?>" 
                                   class="btn-edit">
                                    <i class="fas fa-edit"></i> Edit
                                </a>
                                <a href="delete_news.php?id=<?php echo $item['NewsID']; ?>" 
                                   class="btn-delete"
                                   onclick="return confirmDelete('<?php echo addslashes($item['Title']); ?>')">
                                    <i class="fas fa-trash"></i> Delete
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
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

        // User profile click
        document.getElementById('userProfileBtn').addEventListener('click', function() {
            window.location.href = 'admin_profile.php';
        });

        // Delete confirmation with news title
        function confirmDelete(newsTitle) {
            return confirm(`Are you sure you want to delete the news article:\n\n"${newsTitle}"?\n\nThis action cannot be undone.`);
        }

        // Search functionality
        document.getElementById('searchInput').addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase().trim();
            const newsCards = document.querySelectorAll('.news-card');
            let visibleCount = 0;
            
            newsCards.forEach(card => {
                const title = card.getAttribute('data-title');
                const author = card.getAttribute('data-author');
                
                if (searchTerm === '' || title.includes(searchTerm) || author.includes(searchTerm)) {
                    card.style.display = 'flex';
                    visibleCount++;
                } else {
                    card.style.display = 'none';
                }
            });
            
            // Update count badge
            const badge = document.querySelector('.section-header .badge');
            if (badge) {
                badge.textContent = visibleCount + ' articles';
            }
        });

        // Check for success/error messages from URL parameters
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const addStatus = urlParams.get('add');
            const deleteStatus = urlParams.get('delete');
            const title = urlParams.get('title');
            const msg = urlParams.get('msg');
            
            // Check for add success message
            if(addStatus === 'success') {
                showAlert('success', 'Success! News created successfully!');
                window.history.replaceState({}, document.title, window.location.pathname);
            }
            
            // Check for delete success message
            if(deleteStatus === 'success' && title) {
                showAlert('success', `News article "${decodeURIComponent(title)}" has been deleted successfully!`);
                window.history.replaceState({}, document.title, window.location.pathname);
            }
            
            // Check for delete error message
            if(deleteStatus === 'error' && msg) {
                showAlert('danger', `Error: ${decodeURIComponent(msg)}`);
                window.history.replaceState({}, document.title, window.location.pathname);
            }
        });

        // Function to show alert messages
        function showAlert(type, message) {
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type} alert-dismissible fade show alert-fixed`;
            alertDiv.innerHTML = `
                <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'} me-2"></i>
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            
            document.body.appendChild(alertDiv);
            
            // Auto remove after 5 seconds
            setTimeout(() => {
                if(alertDiv.parentNode) {
                    alertDiv.remove();
                }
            }, 5000);
        }

        // Initialize tooltips for long text
        document.querySelectorAll('.news-title, .news-desc').forEach(element => {
            if (element.scrollWidth > element.clientWidth) {
                element.setAttribute('data-bs-toggle', 'tooltip');
                element.setAttribute('data-bs-placement', 'top');
                element.setAttribute('title', element.getAttribute('title') || element.textContent);
            }
        });

        // Initialize Bootstrap tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    </script>
</body>
</html>