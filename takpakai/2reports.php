<?php
session_start();
if(!isset($_SESSION['name']) || $_SESSION['role'] != "admin"){
    header("Location: http://10.147.17.30:8000/login.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Include the same header/sidebar styles from admin_dashboard.php */
        body {
            margin: 0;
            padding: 0;
        }

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

        /* Sidebar styles */
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

        .sidebar.active {
            transform: translateX(0);
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

        /* Main Content with iframe */
        .main-content {
            margin-left: 250px;
            padding: 0;
            transition: margin-left 0.3s ease;
            min-height: calc(100vh - 70px);
            background: #f8f9fa;
        }

        .main-content-expanded {
            margin-left: 0;
        }

        /* Report header */
        .report-header {
            background: white;
            padding: 20px;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .report-header h2 {
            margin: 0;
            color: #2c3e50;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .iframe-container {
            width: 100%;
            height: calc(100vh - 140px); /* Adjust for header */
            border: none;
        }

        /* Responsive */
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
                    <small>Report System</small>
                </div>
            </div>
            
            <div class="header-controls">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" placeholder="Search...">
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
                    <a href="view_news.php" class="nav-link">
                        <i class="fas fa-list"></i>
                        <span class="nav-text">View News</span>
                    </a>
                </li>
                
                <li class="nav-divider"></li>
                
                <li class="nav-label">OPERATIONS</li>
                
                <li class="nav-item">
                    <a href="bridge_to_distribution.php" class="nav-link" target="_blank">
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
                    <a href="report.php" class="nav-link active">
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

    <!-- Main Content with iframe -->
    <main class="main-content" id="mainContent">
        <div class="report-header">
            <h2>
                <i class="fas fa-chart-bar"></i>
                Report System
            </h2>
            <div>
                <small class="text-muted">External Report System</small>
            </div>
        </div>
        
        <!-- Iframe to load external report system -->
        <iframe 
            src="http://10.147.17.58:8000/reports.php" 
            class="iframe-container"
            title="Report System"
            allow="fullscreen"
            sandbox="allow-same-origin allow-scripts allow-forms allow-popups"
        >
            <p>Your browser does not support iframes. Please <a href="http://10.147.17.58:8000/reports.php" target="_blank">click here</a> to access the report system.</p>
        </iframe>
    </main>

    <script>
        // Mobile Toggle
        document.getElementById('mobileToggle').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('active');
            document.getElementById('mainContent').classList.toggle('main-content-expanded');
        });

        // Auto-collapse sidebar on small screens
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

        // Iframe error handling
        const iframe = document.querySelector('iframe');
        iframe.addEventListener('load', function() {
            console.log('Iframe loaded successfully');
        });

        iframe.addEventListener('error', function() {
            const errorDiv = document.createElement('div');
            errorDiv.style.cssText = `
                padding: 40px;
                text-align: center;
                background: #ffeaa7;
                border: 2px solid #f39c12;
                border-radius: 8px;
                margin: 20px;
            `;
            errorDiv.innerHTML = `
                <h3><i class="fas fa-exclamation-triangle"></i> Unable to Load Report System</h3>
                <p>The external report system is currently unavailable.</p>
                <p>You can try:</p>
                <ul style="text-align: left; display: inline-block;">
                    <li>Check if the report server is running</li>
                    <li>Verify network connectivity</li>
                    <li>Try accessing directly: <a href="http://10.147.17.58:8000/reports.php" target="_blank">http://10.147.17.58:8000/reports.php</a></li>
                </ul>
            `;
            iframe.parentNode.replaceChild(errorDiv, iframe);
        });
    </script>
</body>
</html>