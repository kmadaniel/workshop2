<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != "admin") {
    header("Location: login.php");
    exit();
}

require_once "connection.php"; // SQL Server connection

$user_id = $_SESSION['user_id']; 

// ============================
// FETCH ADMIN DATA
// ============================
$sql = "SELECT AdminID, FullName, Email, Phone, Role FROM Admin WHERE AdminID = ?";
$params = array($user_id);
$stmt = sqlsrv_query($conn, $sql, $params);

if ($stmt === false) {
    die(print_r(sqlsrv_errors(), true));
}

$row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

// ============================
// HANDLE PROFILE UPDATE
// ============================
$msg = "";
$msg_type = "";

if (isset($_POST['update'])) {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    
    // Password update is optional
    if (!empty($_POST['password'])) {
        // Hash the new password
        $passwordHash = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $update_sql = "UPDATE Admin SET FullName = ?, Email = ?, Phone = ?, PasswordHash = ? WHERE AdminID = ?";
        $update_params = array($name, $email, $phone, $passwordHash, $user_id);
    } else {
        $update_sql = "UPDATE Admin SET FullName = ?, Email = ?, Phone = ? WHERE AdminID = ?";
        $update_params = array($name, $email, $phone, $user_id);
    }

    $update_stmt = sqlsrv_query($conn, $update_sql, $update_params);

    if ($update_stmt === false) {
        $msg = "Failed to update profile: " . print_r(sqlsrv_errors(), true);
        $msg_type = "error";
    } else {
        $_SESSION['name'] = $name; // update session
        $msg = "Profile updated successfully!";
        $msg_type = "success";
        
        // Refresh the data
        $row['FullName'] = $name;
        $row['Email'] = $email;
        $row['Phone'] = $phone;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Profile</title>
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

        /* Profile Header */
        .profile-header {
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 25px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            border: 1px solid #e9ecef;
        }

        .profile-header h2 {
            color: #2c3e50;
            margin-bottom: 10px;
            font-size: 24px;
            display: flex;
            align-items: center;
            gap: 10px;
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

        /* Profile Cards */
        .profile-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            border: 1px solid #e9ecef;
            margin-bottom: 20px;
            height: 100%;
        }

        .profile-card h5 {
            color: #2c3e50;
            margin-bottom: 20px;
            font-size: 18px;
            display: flex;
            align-items: center;
            gap: 10px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f1f1f1;
        }

        .profile-avatar {
            width: 120px;
            height: 120px;
            margin: 0 auto 20px;
            border-radius: 50%;
            background: linear-gradient(135deg, #4fc3f7 0%, #0288d1 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 48px;
            color: white;
            font-weight: bold;
        }

        .info-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .info-item {
            padding: 12px 0;
            border-bottom: 1px solid #f1f1f1;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .info-item:last-child {
            border-bottom: none;
        }

        .info-label {
            color: #7f8c8d;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .info-value {
            color: #2c3e50;
            font-weight: 500;
        }

        /* Form Styles */
        .form-label {
            color: #2c3e50;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .input-group {
            border-radius: 8px;
            overflow: hidden;
            border: 1px solid #e0e0e0;
        }

        .input-group:focus-within {
            border-color: #4fc3f7;
            box-shadow: 0 0 0 2px rgba(79, 195, 247, 0.2);
        }

        .input-group-text {
            background: #f8f9fa;
            border: none;
            color: #7f8c8d;
        }

        .form-control {
            border: none;
            padding: 12px 15px;
        }

        .form-control:focus {
            box-shadow: none;
        }

        .password-toggle-btn {
            border: none;
            background: #f8f9fa;
            color: #7f8c8d;
            cursor: pointer;
        }

        .password-toggle-btn:hover {
            color: #4fc3f7;
        }

        /* Security Tips */
        .security-tips {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 10px;
            padding: 20px;
            margin-top: 20px;
            border-left: 4px solid #4fc3f7;
        }

        .security-tips h6 {
            color: #2c3e50;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .security-tips ul {
            padding-left: 20px;
            margin: 0;
        }

        .security-tips li {
            color: #7f8c8d;
            margin-bottom: 8px;
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
            
            .nav-container {
                padding: 10px 15px;
            }
            
            .nav-btn {
                padding: 8px 12px;
                font-size: 13px;
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
                    <input type="text" placeholder="Search...">
                </div>
                
                <div class="notifications" id="notificationsBtn">
                    <i class="fas fa-bell"></i>
                    <span class="notification-badge">3</span>
                </div>
                
                <div class="user-profile" id="userProfileBtn">
                    <div class="user-avatar">
                        <?php echo strtoupper(substr($row['FullName'], 0, 1)); ?>
                    </div>
                    <div class="user-info">
                        <div class="user-name"><?php echo $row['FullName']; ?></div>
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
            <a href="admin_profile.php" class="nav-btn active">
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
                    <a href="admin_profile.php" class="nav-link active">
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
            <a href="logout.php">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="main-content" id="mainContent">
        <!-- Toast Notification -->
        <?php if ($msg): ?>
        <div class="toast-container">
            <div class="toast <?php echo $msg_type == 'success' ? 'toast-success' : 'toast-error'; ?>">
                <div class="toast-content">
                    <strong><?php echo $msg_type == 'success' ? 'Success!' : 'Error!'; ?></strong>
                    <p style="margin: 5px 0 0 0; font-size: 14px;"><?php echo $msg; ?></p>
                </div>
                <button class="toast-close" onclick="this.parentElement.remove()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
        <?php endif; ?>

        <!-- Profile Header -->
        <div class="profile-header">
            <h2>
                <i class="fas fa-user-circle"></i>
                Profile Settings
            </h2>
            <p class="text-muted mb-0">Manage your account information and security settings</p>
        </div>

        <div class="row">
            <!-- Left Column: Edit Form -->
            <div class="col-lg-8">
                <div class="profile-card">
                    <h5><i class="fas fa-edit"></i> Edit Profile Information</h5>
                    
                    <form method="POST" id="profileForm">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Full Name</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-user"></i>
                                    </span>
                                    <input type="text" name="name" value="<?= htmlspecialchars($row['FullName']); ?>" 
                                           class="form-control" required>
                                </div>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email Address</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-envelope"></i>
                                    </span>
                                    <input type="email" name="email" value="<?= htmlspecialchars($row['Email']); ?>" 
                                           class="form-control" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Phone Number</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-phone"></i>
                                    </span>
                                    <input type="text" name="phone" value="<?= htmlspecialchars($row['Phone'] ?? ''); ?>" 
                                           class="form-control">
                                </div>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Admin Role</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-user-tag"></i>
                                    </span>
                                    <input type="text" value="<?= htmlspecialchars($row['Role']); ?>" 
                                           class="form-control" disabled readonly>
                                </div>
                                <small class="text-muted">Role cannot be changed from profile page</small>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label class="form-label">Change Password (Optional)</label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-lock"></i>
                                </span>
                                <input type="password" name="password" id="password" 
                                       class="form-control" placeholder="Leave blank to keep current password">
                                <button class="btn password-toggle-btn" type="button" onclick="togglePassword()">
                                    <i class="fas fa-eye" id="toggleIcon"></i>
                                </button>
                            </div>
                            <small class="text-muted mt-2 d-block">Leave empty if you don't want to change password</small>
                        </div>

                        <div class="d-flex justify-content-between align-items-center mt-4 pt-3 border-top">
                            <div>
                                <button type="submit" name="update" class="btn btn-primary px-4">
                                    <i class="fas fa-save me-2"></i> Save Changes
                                </button>
                                <a href="admin_dashboard.php" class="btn btn-outline-secondary ms-2">
                                    <i class="fas fa-arrow-left me-2"></i> Back to Dashboard
                                </a>
                            </div>
                            <a href="#" class="btn btn-outline-danger" onclick="return confirm('Are you sure you want to delete your account? This action cannot be undone.')">
                                <i class="fas fa-trash-alt me-2"></i> Delete Account
                            </a>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Right Column: Profile Summary -->
            <div class="col-lg-4">
                <div class="profile-card">
                    <h5><i class="fas fa-user-circle"></i> Profile Summary</h5>
                    
                    <div class="text-center mb-4">
                        <div class="profile-avatar">
                            <?php echo strtoupper(substr($row['FullName'], 0, 1)); ?>
                        </div>
                        <h5 class="mb-1"><?= htmlspecialchars($row['FullName']); ?></h5>
                        <p class="text-muted"><?= htmlspecialchars($row['Email']); ?></p>
                    </div>
                    
                    <ul class="info-list">
                        <li class="info-item">
                            <span class="info-label">
                                <i class="fas fa-id-badge text-primary"></i>
                                Admin ID
                            </span>
                            <span class="info-value">#<?= htmlspecialchars($user_id); ?></span>
                        </li>
                        <li class="info-item">
                            <span class="info-label">
                                <i class="fas fa-phone text-primary"></i>
                                Phone
                            </span>
                            <span class="info-value"><?= htmlspecialchars($row['Phone'] ?? 'Not set'); ?></span>
                        </li>
                        <li class="info-item">
                            <span class="info-label">
                                <i class="fas fa-user-tag text-primary"></i>
                                Role
                            </span>
                            <span class="badge bg-primary rounded-pill"><?= htmlspecialchars($row['Role']); ?></span>
                        </li>
                        <li class="info-item">
                            <span class="info-label">
                                <i class="fas fa-calendar-alt text-primary"></i>
                                Member Since
                            </span>
                            <span class="info-value">-</span>
                        </li>
                    </ul>
                    
                    <div class="security-tips">
                        <h6><i class="fas fa-shield-alt"></i> Security Tips</h6>
                        <ul>
                            <li>Use a strong, unique password</li>
                            <li>Enable two-factor authentication</li>
                            <li>Never share your login credentials</li>
                            <li>Log out when using public computers</li>
                        </ul>
                    </div>
                </div>
            </div>
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

        // Password toggle
        function togglePassword() {
            var passwordField = document.getElementById("password");
            var toggleIcon = document.getElementById("toggleIcon");
            
            if (passwordField.type === "password") {
                passwordField.type = "text";
                toggleIcon.classList.remove("fa-eye");
                toggleIcon.classList.add("fa-eye-slash");
            } else {
                passwordField.type = "password";
                toggleIcon.classList.remove("fa-eye-slash");
                toggleIcon.classList.add("fa-eye");
            }
        }

        // Auto-remove toast after 5 seconds
        setTimeout(function() {
            var toast = document.querySelector('.toast');
            if(toast) {
                toast.style.animation = 'slideInRight 0.3s ease reverse';
                setTimeout(() => toast.remove(), 300);
            }
        }, 5000);

        // Form validation
        document.getElementById('profileForm').addEventListener('submit', function(e) {
            var password = document.getElementById('password').value;
            if (password && password.length < 6) {
                e.preventDefault();
                alert('Password must be at least 6 characters long!');
                return false;
            }
            return true;
        });
    </script>
</body>
</html>