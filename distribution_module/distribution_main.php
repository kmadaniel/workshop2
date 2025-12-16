<?php
// ========================================
// Enhanced Distribution Dashboard with Filters & Pagination
// distribution_main.php (index.php) - MAIN DASHBOARD
// ========================================

require_once 'config.php';

// Start session for user data
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Sample user data - replace with actual session data
$current_user = [
    'name' => $_SESSION['user_name'] ?? 'Admin User',
    'role' => $_SESSION['user_role'] ?? 'System Administrator',
    'avatar' => $_SESSION['user_avatar'] ?? 'AU',
    'email' => $_SESSION['user_email'] ?? 'admin@disasterrelief.org'
];

try {
    $database = new Database();
    $db = $database->getConnection();

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
        throw new Exception("Failed to fetch statistics: " . $db->error);
    }

    // ========================================
    // BUILD FILTERED QUERY - Updated to include victims
    // ========================================
    $where_conditions = [];
    $params = [];
    $types = '';

    // Search filter - Now includes victim names
    if (!empty($search)) {
        $where_conditions[] = "(d.comments LIKE ? OR dis.Disaster_Name LIKE ? OR dis.Location LIKE ? OR v.name LIKE ?)";
        $search_param = "%{$search}%";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
        $types .= 'ssss';
    }

    // Status filter
    if (!empty($status_filter)) {
        $where_conditions[] = "d.status = ?";
        $params[] = $status_filter;
        $types .= 's';
    }

    // Disaster filter
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
        LEFT JOIN Disaster dis ON d.disaster_id = dis.disaster_id
        LEFT JOIN Needs n ON d.distribution_id = n.distribution_id
        LEFT JOIN victim v ON n.victim_id = v.victim_id
        {$where_clause}
    ";

    if (count($params) > 0) {
        $count_stmt = $db->prepare($count_query);
        $count_stmt->bind_param($types, ...$params);
        $count_stmt->execute();
        $count_result = $count_stmt->get_result();
        $total_rows = $count_result->fetch_assoc()['total'];
        $count_stmt->close();
    } else {
        $count_result = $db->query($count_query);
        $total_rows = $count_result->fetch_assoc()['total'];
        $count_result->free();
    }

    $total_pages = ceil($total_rows / $per_page);

    // ========================================
    // GET FILTERED DISTRIBUTIONS WITH PAGINATION - UPDATED TO INCLUDE VICTIM NAMES
    // ========================================
    $distributions_query = "
        SELECT 
            d.distribution_id,
            d.date,
            d.status,
            d.comments,
            dis.Disaster_Name as disaster_name,
            dis.Location as disaster_location,
            COUNT(DISTINCT n.need_id) as total_needs,
            COUNT(DISTINCT dv.volunteer_id) as volunteer_count,
            GROUP_CONCAT(DISTINCT v.name ORDER BY v.name ASC SEPARATOR ', ') as victim_names,
            COUNT(DISTINCT v.victim_id) as victim_count
        FROM distribution d
        LEFT JOIN Disaster dis ON d.disaster_id = dis.disaster_id
        LEFT JOIN Needs n ON d.distribution_id = n.distribution_id
        LEFT JOIN distribution_volunteer dv ON d.distribution_id = dv.distribution_id
        LEFT JOIN victim v ON n.victim_id = v.victim_id
        {$where_clause}
        GROUP BY d.distribution_id, d.date, d.status, d.comments, dis.Disaster_Name, dis.Location
        ORDER BY d.date DESC, d.distribution_id DESC
        LIMIT ? OFFSET ?
    ";
    
    // Add pagination parameters
    $params[] = $per_page;
    $params[] = $offset;
    $types .= 'ii';

    if (count($params) > 0) {
        $dist_stmt = $db->prepare($distributions_query);
        $dist_stmt->bind_param($types, ...$params);
        $dist_stmt->execute();
        $dist_result = $dist_stmt->get_result();
        $recent_distributions = $dist_result->fetch_all(MYSQLI_ASSOC);
        $dist_stmt->close();
    } else {
        // Fallback for when there are no filters (only pagination)
        $dist_result = $db->query($distributions_query);
        $recent_distributions = $dist_result->fetch_all(MYSQLI_ASSOC);
        $dist_result->free();
    }

    // ========================================
    // GET FILTER OPTIONS
    // ========================================
    
    // Status options for new system
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

    // Get all disasters for filter dropdown
    $disaster_query = "SELECT disaster_id, Disaster_Name FROM Disaster ORDER BY Disaster_Name ASC";
    $disaster_result = $db->query($disaster_query);
    $disaster_options = [];
    
    if ($disaster_result) {
        while ($row = $disaster_result->fetch_assoc()) {
            $disaster_options[$row['disaster_id']] = $row['Disaster_Name'];
        }
        $disaster_result->free();
    }

    // Set defaults for null values in stats
    foreach ($stats as $key => $value) {
        $stats[$key] = $value ?? 0;
    }

} catch (Exception $e) {
    $error = "Database error: " . $e->getMessage();
    
    // Set default values
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
        /* Fix for dropdown positioning */
        .user-dropdown, .notifications-dropdown {
            display: none;
            position: absolute;
            background: white;
            border-radius: 8px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
            z-index: 1001;
            border: 1px solid #ddd;
        }
        
        .user-dropdown {
            right: 20px;
            top: 70px;
            width: 280px;
        }
        
        .notifications-dropdown {
            right: 120px;
            top: 70px;
            width: 350px;
        }
        
        .dropdown-header {
            padding: 20px;
            background: linear-gradient(135deg, #1a237e 0%, #283593 100%);
            color: white;
            border-radius: 8px 8px 0 0;
        }
        
        .dropdown-menu {
            padding: 10px 0;
            max-height: 400px;
            overflow-y: auto;
        }
        
        .dropdown-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 20px;
            color: #333;
            text-decoration: none;
            transition: background 0.3s ease;
        }
        
        .dropdown-item:hover {
            background: #f5f5f5;
        }
        
        .dropdown-footer {
            padding: 15px 20px;
            background: #f8f9fa;
            border-radius: 0 0 8px 8px;
            font-size: 11px;
            color: #7f8c8d;
            text-align: center;
        }
        
        /* Loading screen */
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
            width: 50px;
            height: 50px;
            border: 5px solid #f3f3f3;
            border-top: 5px solid #3498db;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 20px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* Modal styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        
        .modal-content {
            background: white;
            border-radius: 8px;
            width: 90%;
            max-width: 600px;
            max-height: 80vh;
            overflow-y: auto;
        }
        
        .modal-header {
            padding: 20px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-close {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #666;
        }
        
        .modal-body {
            padding: 20px;
        }
        
        /* Toast notifications */
        .toast-container {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 1000;
        }
        
        .toast {
            background: white;
            border-radius: 8px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            padding: 15px;
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            min-width: 300px;
            animation: slideIn 0.3s ease;
            border-left: 4px solid #3498db;
        }
        
        .toast.toast-success {
            border-left-color: #27ae60;
        }
        
        .toast.toast-error {
            border-left-color: #e74c3c;
        }
        
        .toast.toast-warning {
            border-left-color: #f39c12;
        }
        
        .toast-content {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .toast-close {
            background: none;
            border: none;
            font-size: 18px;
            cursor: pointer;
            color: #999;
        }
        
        .toast.fade-out {
            animation: slideOut 0.3s ease forwards;
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
        
        /* Button colors */
        .btn-purple { 
            background: #9b59b6; 
            color: white; 
            border: none;
            padding: 5px 10px;
            border-radius: 4px;
            cursor: pointer;
        }
        
        .btn-purple:hover { 
            background: #8e44ad; 
            color: white; 
        }
        
        .btn-orange { 
            background: #f39c12; 
            color: white; 
            border: none;
            padding: 5px 10px;
            border-radius: 4px;
            cursor: pointer;
        }
        
        .btn-orange:hover { 
            background: #e67e22; 
            color: white; 
        }
        
        /* Refresh button animation */
        .refreshing {
            animation: spin 1s linear infinite;
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
                </div>
                
                <div class="notifications" id="notificationsBtn">
                    <i class="fas fa-bell"></i>
                    <span class="notification-badge">3</span>
                </div>
                
                <div class="user-profile" id="userProfile">
                    <div class="user-avatar">
                        <?php echo substr($current_user['name'], 0, 2); ?>
                    </div>
                    <div class="user-info">
                        <div class="user-name"><?php echo htmlspecialchars($current_user['name']); ?></div>
                        <div class="user-role"><?php echo htmlspecialchars($current_user['role']); ?></div>
                    </div>
                    <i class="fas fa-chevron-down"></i>
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
                <a href="volunteer_dashboard.php" class="nav-link">
                    <i class="fas fa-users"></i>
                    <span class="nav-text">Volunteers</span>
                    <span class="badge badge-success" style="margin-left: auto;">24</span>
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
                    <?php echo substr($current_user['name'], 0, 2); ?>
                </div>
                <h4 style="margin: 0 0 5px 0;"><?php echo htmlspecialchars($current_user['name']); ?></h4>
                <p style="margin: 0; opacity: 0.9; font-size: 13px;"><?php echo htmlspecialchars($current_user['role']); ?></p>
                <p style="margin: 5px 0 0 0; font-size: 12px; opacity: 0.8;"><?php echo htmlspecialchars($current_user['email']); ?></p>
            </div>
            
            <div class="dropdown-menu">
                <a href="profile.php" class="dropdown-item">
                    <i class="fas fa-user" style="color: #3498db;"></i>
                    <span>My Profile</span>
                </a>
                
                <a href="settings.php" class="dropdown-item">
                    <i class="fas fa-cog" style="color: #95a5a6;"></i>
                    <span>Account Settings</span>
                </a>
                
                <a href="notifications.php" class="dropdown-item">
                    <i class="fas fa-bell" style="color: #f39c12;"></i>
                    <span>Notifications</span>
                    <span class="badge" style="background: #f44336; color: white; padding: 2px 8px; border-radius: 10px; font-size: 11px; margin-left: auto;">3</span>
                </a>
                
                <div style="height: 1px; background: #eee; margin: 10px 0;"></div>
                
                <a href="help.php" class="dropdown-item">
                    <i class="fas fa-question-circle" style="color: #1abc9c;"></i>
                    <span>Help & Support</span>
                </a>
                
                <a href="logout.php" class="dropdown-item">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </div>
            
            <div class="dropdown-footer">
                Last login: Today, 09:45 AM
            </div>
        </div>

        <!-- Notifications Dropdown -->
        <div class="notifications-dropdown" id="notificationsDropdown">
            <div class="dropdown-header" style="padding: 15px 20px; border-bottom: 1px solid #eee;">
                <h4 style="margin: 0; font-size: 16px;">Notifications</h4>
                <button id="markAllRead" style="background: none; border: none; color: #3498db; font-size: 12px; cursor: pointer;">Mark all as read</button>
            </div>
            
            <div class="dropdown-menu">
                <div class="notification-item unread" style="padding: 15px 20px; border-bottom: 1px solid #f5f5f5; background: #f8fdff; cursor: pointer;">
                    <div style="display: flex; gap: 10px;">
                        <div style="color: #3498db; font-size: 18px;">
                            <i class="fas fa-users"></i>
                        </div>
                        <div>
                            <strong style="font-size: 14px;">New Volunteers Assigned</strong>
                            <p style="margin: 5px 0 0 0; font-size: 13px; color: #666;">3 volunteers assigned to distribution #0452</p>
                            <small style="color: #95a5a6;">10 minutes ago</small>
                        </div>
                    </div>
                </div>
                
                <div class="notification-item" style="padding: 15px 20px; border-bottom: 1px solid #f5f5f5; cursor: pointer;">
                    <div style="display: flex; gap: 10px;">
                        <div style="color: #27ae60; font-size: 18px;">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div>
                            <strong style="font-size: 14px;">Distribution Completed</strong>
                            <p style="margin: 5px 0 0 0; font-size: 13px; color: #666;">Distribution #0421 marked as completed</p>
                            <small style="color: #95a5a6;">2 hours ago</small>
                        </div>
                    </div>
                </div>
                
                <div class="notification-item" style="padding: 15px 20px; border-bottom: 1px solid #f5f5f5; cursor: pointer;">
                    <div style="display: flex; gap: 10px;">
                        <div style="color: #f39c12; font-size: 18px;">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        <div>
                            <strong style="font-size: 14px;">Low Inventory Alert</strong>
                            <p style="margin: 5px 0 0 0; font-size: 13px; color: #666;">Food packs inventory below minimum level</p>
                            <small style="color: #95a5a6;">5 hours ago</small>
                        </div>
                    </div>
                </div>
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

        <!-- Recent Distributions Table - UPDATED TO INCLUDE VICTIM NAMES -->
        <div class="card animated-card">
            <div class="card-header">
                <h2>
                    <i class="fas fa-list"></i> Recent Distributions
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
                            <th>Needs</th>
                            <th>Volunteers</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_distributions as $row): ?>
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
                                    <strong><?php echo htmlspecialchars($row['disaster_name'] ?? 'N/A'); ?></strong>
                                </div>
                            </td>
                            
                            <td>
                                <div class="victim-cell">
                                    <div>
                                        <span class="victim-count-badge">
                                            <i class="fas fa-user"></i> <?php echo $row['victim_count']; ?>
                                        </span>
                                        <?php if ($row['victim_count'] > 0): ?>
                                            <span class="badge badge-light"><?php echo $row['victim_count']; ?> victims</span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if (!empty($row['victim_names'])): ?>
                                        <div class="victim-names" title="<?php echo htmlspecialchars($row['victim_names']); ?>">
                                            <?php 
                                            $victim_names = explode(', ', $row['victim_names']);
                                            if (count($victim_names) > 3) {
                                                echo htmlspecialchars(implode(', ', array_slice($victim_names, 0, 3))) . '...';
                                            } else {
                                                echo htmlspecialchars($row['victim_names']);
                                            }
                                            ?>
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
                                    <?php echo htmlspecialchars($row['disaster_location'] ?? 'N/A'); ?>
                                </div>
                            </td>
                            
                            <td>
                                <div class="needs-cell">
                                    <span class="needs-count"><?php echo $row['total_needs']; ?></span>
                                    <small>needs</small>
                                </div>
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
                                    
                                    <!-- Quick View button -->
                                    <button class="btn btn-secondary btn-sm quick-view-btn" 
                                            data-id="<?php echo $row['distribution_id']; ?>" 
                                            data-victims="<?php echo htmlspecialchars($row['victim_names'] ?? ''); ?>"
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
                    <!-- Content loaded via AJAX -->
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
            // Debug logging
            console.log('DOM loaded, initializing dashboard...');
            
            // ========== FIX 1: Initialize dropdowns properly ==========
            const userProfile = document.getElementById('userProfile');
            const userDropdown = document.getElementById('userDropdown');
            const notificationsBtn = document.getElementById('notificationsBtn');
            const notificationsDropdown = document.getElementById('notificationsDropdown');
            
            console.log('User Profile element:', userProfile);
            console.log('User Dropdown element:', userDropdown);
            console.log('Notifications element:', notificationsBtn);
            console.log('Notifications Dropdown element:', notificationsDropdown);
            
            // Make sure dropdowns are hidden initially
            if (userDropdown) userDropdown.style.display = 'none';
            if (notificationsDropdown) notificationsDropdown.style.display = 'none';
            
            // ========== FIX 2: User profile dropdown functionality ==========
            if (userProfile && userDropdown) {
                userProfile.addEventListener('click', function(e) {
                    e.stopPropagation();
                    console.log('User profile clicked');
                    
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
            } else {
                console.error('User profile or dropdown elements not found!');
            }
            
            // ========== FIX 3: Notifications dropdown functionality ==========
            if (notificationsBtn && notificationsDropdown) {
                notificationsBtn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    console.log('Notifications clicked');
                    
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
            } else {
                console.error('Notifications or dropdown elements not found!');
            }
            
            // ========== FIX 4: Close dropdowns when clicking outside ==========
            document.addEventListener('click', function(e) {
                if (userDropdown && !userProfile.contains(e.target) && !userDropdown.contains(e.target)) {
                    userDropdown.style.display = 'none';
                }
                if (notificationsDropdown && !notificationsBtn.contains(e.target) && !notificationsDropdown.contains(e.target)) {
                    notificationsDropdown.style.display = 'none';
                }
            });
            
            // ========== FIX 5: Search functionality ==========
            const globalSearch = document.getElementById('globalSearch');
            if (globalSearch) {
                console.log('Global search element found');
                
                // Add event listener for Enter key
                globalSearch.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        const searchTerm = this.value.trim();
                        console.log('Searching for:', searchTerm);
                        if (searchTerm) {
                            // Build URL with current parameters
                            const urlParams = new URLSearchParams(window.location.search);
                            urlParams.set('search', searchTerm);
                            urlParams.set('page', '1'); // Reset to first page when searching
                            
                            // Navigate to new URL
                            window.location.href = '?' + urlParams.toString();
                        } else {
                            // If search is empty, remove search parameter
                            const urlParams = new URLSearchParams(window.location.search);
                            urlParams.delete('search');
                            window.location.href = '?' + urlParams.toString();
                        }
                    }
                });
                
                // Add search button if needed
                const searchBox = globalSearch.parentElement;
                if (searchBox) {
                    const searchButton = document.createElement('button');
                    searchButton.innerHTML = '<i class="fas fa-search"></i>';
                    searchButton.style.background = 'none';
                    searchButton.style.border = 'none';
                    searchButton.style.cursor = 'pointer';
                    searchButton.style.color = '#666';
                    searchButton.addEventListener('click', function() {
                        const searchTerm = globalSearch.value.trim();
                        if (searchTerm) {
                            const urlParams = new URLSearchParams(window.location.search);
                            urlParams.set('search', searchTerm);
                            urlParams.set('page', '1');
                            window.location.href = '?' + urlParams.toString();
                        }
                    });
                    searchBox.appendChild(searchButton);
                }
            } else {
                console.error('Global search element not found!');
            }
            
            // ========== FIX 6: Mark all notifications as read ==========
            const markAllReadBtn = document.getElementById('markAllRead');
            if (markAllReadBtn) {
                markAllReadBtn.addEventListener('click', function() {
                    console.log('Marking all as read');
                    document.querySelectorAll('.notification-item.unread').forEach(item => {
                        item.classList.remove('unread');
                        item.style.background = 'white';
                    });
                    document.querySelector('.notification-badge').style.display = 'none';
                    showToast('All notifications marked as read', 'success');
                });
            }
            
            // ========== FIX 7: Loading screen ==========
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
            
            // ========== FIX 8: Refresh button ==========
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
            
            // ========== FIX 9: Sidebar toggle ==========
            const sidebarToggle = document.getElementById('sidebarToggle');
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('mainContent');
            
            if (sidebarToggle && sidebar && mainContent) {
                sidebarToggle.addEventListener('click', function() {
                    sidebar.classList.toggle('active');
                    mainContent.classList.toggle('main-content-expanded');
                });
            }
            
            // ========== FIX 10: Filter form auto-submit ==========
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
            
            // ========== FIX 11: Quick view modal ==========
            const quickViewButtons = document.querySelectorAll('.quick-view-btn');
            const modal = document.getElementById('quick-view-modal');
            const modalContent = document.getElementById('quick-view-content');
            
            if (quickViewButtons.length > 0 && modal && modalContent) {
                quickViewButtons.forEach(button => {
                    button.addEventListener('click', function() {
                        const distributionId = this.getAttribute('data-id');
                        const victimNames = this.getAttribute('data-victims') || 'None';
                        
                        // Get the row data
                        const row = this.closest('tr');
                        const disasterName = row.querySelector('.disaster-cell strong').textContent;
                        const location = row.querySelector('.location-cell').textContent.replace('📍', '').trim();
                        const date = row.querySelector('.date-cell').textContent;
                        const status = row.querySelector('.badge').textContent.trim();
                        const needsCount = row.querySelector('.needs-count').textContent;
                        const victimCount = row.querySelector('.victim-count-badge') ? 
                            row.querySelector('.victim-count-badge').textContent.replace('👤', '').trim() : '0';
                        const volunteerCount = row.querySelector('.volunteer-count') ? 
                            row.querySelector('.volunteer-count').textContent : '0';
                        
                        // Show loading in modal
                        modalContent.innerHTML = '<div class="loading-spinner small"></div><p>Loading distribution details...</p>';
                        modal.style.display = 'block';
                        
                        // Simulate AJAX request with row data
                        setTimeout(() => {
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
                                        <div class="detail-label">Date</div>
                                        <div class="detail-value">📅 ${date}</div>
                                    </div>
                                    <div class="detail-row">
                                        <div class="detail-label">Status</div>
                                        <div class="detail-value"><span class="badge badge-${status.toLowerCase().replace(' ', '-')}">${status}</span></div>
                                    </div>
                                    <div class="detail-row">
                                        <div class="detail-label">Victims</div>
                                        <div class="detail-value">
                                            <span class="badge badge-info">${victimCount}</span>
                                            <div style="margin-top: 5px; font-size: 0.9em; color: #666;">
                                                ${victimNames.split(', ').map(name => `<div>👤 ${name}</div>`).join('')}
                                            </div>
                                        </div>
                                    </div>
                                    <div class="detail-row">
                                        <div class="detail-label">Needs</div>
                                        <div class="detail-value"><span class="badge badge-warning">${needsCount} items</span></div>
                                    </div>
                                    <div class="detail-row">
                                        <div class="detail-label">Volunteers</div>
                                        <div class="detail-value"><span class="badge badge-success">${volunteerCount} assigned</span></div>
                                    </div>
                                    <div class="detail-actions">
                                        <a href="view_distribution.php?id=${distributionId}" class="btn btn-primary">
                                            <i class="fas fa-external-link-alt"></i> View Full Details
                                        </a>
                                        <a href="update_status.php?id=${distributionId}" class="btn btn-warning">
                                            <i class="fas fa-edit"></i> Update Status
                                        </a>
                                    </div>
                                </div>
                            `;
                        }, 300);
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
            
            // ========== FIX 12: Toast notifications ==========
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
            
            // Test toast notification
            setTimeout(() => {
                showToast('Dashboard loaded successfully!', 'success');
            }, 1000);
            
            // ========== FIX 13: Table row animations ==========
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