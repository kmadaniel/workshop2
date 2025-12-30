<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NGO Dashboard - Disaster Relief System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #2e7d32; /* Green color scheme */
            --primary-dark: #1b5e20;
            --primary-light: #4caf50;
            --secondary-color: #ff9800; /* Orange accent */
            --accent-color: #2196f3; /* Blue accent */
            --bg-light: #f5f7fa;
            --card-bg: #ffffff;
            --text-dark: #2c3e50;
            --text-light: #546e7a;
            --border-color: #e0e0e0;
            --success-color: #4caf50;
            --warning-color: #ff9800;
            --danger-color: #f44336;
            --info-color: #2196f3;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background-color: var(--bg-light);
            color: var(--text-dark);
        }

        /* SIDEBAR */
        .sidebar {
            width: 260px;
            background: linear-gradient(180deg, var(--primary-dark) 0%, var(--primary-color) 100%);
            color: white;
            height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            padding: 20px 0;
            box-shadow: 3px 0 15px rgba(0, 0, 0, 0.1);
            z-index: 1000;
            overflow-y: auto;
        }

        .sidebar-header {
            padding: 0 20px 25px;
            text-align: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            margin-bottom: 20px;
        }

        .sidebar-header h2 {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 5px;
            color: white;
        }

        .sidebar-header p {
            font-size: 0.9rem;
            opacity: 0.8;
            margin: 0;
        }

        .nav-menu {
            list-style: none;
            padding: 0 15px;
        }

        .nav-item {
            margin-bottom: 5px;
        }

        .nav-link {
            display: flex;
            align-items: center;
            padding: 12px 15px;
            color: rgba(255, 255, 255, 0.9);
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.3s;
            font-weight: 500;
        }

        .nav-link:hover {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            transform: translateX(5px);
        }

        .nav-link.active {
            background: rgba(255, 255, 255, 0.15);
            color: white;
            font-weight: 600;
            border-left: 4px solid var(--secondary-color);
        }

        .nav-link i {
            width: 24px;
            margin-right: 12px;
            font-size: 1.1rem;
            text-align: center;
        }

        .logout-link {
            margin-top: 30px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            padding-top: 20px;
        }

        .logout-link .nav-link {
            color: #ffccbc;
        }

        .logout-link .nav-link:hover {
            background: rgba(244, 67, 54, 0.2);
            color: #ffccbc;
        }

        /* MAIN CONTENT */
        .main-content {
            margin-left: 260px;
            padding: 30px;
            min-height: 100vh;
        }

        /* HEADER */
        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--border-color);
        }

        .welcome-section h1 {
            font-size: 1.8rem;
            color: var(--text-dark);
            margin-bottom: 5px;
        }

        .welcome-section p {
            color: var(--text-light);
            font-size: 1rem;
            margin: 0;
        }

        .ngo-name {
            color: var(--primary-color);
            font-weight: 600;
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .notification-btn {
            position: relative;
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            width: 45px;
            height: 45px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-light);
            text-decoration: none;
            transition: all 0.3s;
        }

        .notification-btn:hover {
            background: var(--primary-light);
            color: white;
            border-color: var(--primary-light);
        }

        .notification-badge {
            position: absolute;
            top: -2px;
            right: -2px;
            background: var(--danger-color);
            color: white;
            font-size: 0.7rem;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* QUICK STATS */
        .quick-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }

        .stat-card {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            transition: all 0.3s;
            border-left: 5px solid var(--primary-color);
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        }

        .stat-card.volunteers {
            border-left-color: var(--accent-color);
        }

        .stat-card.activities {
            border-left-color: var(--secondary-color);
        }

        .stat-card.opportunities {
            border-left-color: var(--success-color);
        }

        .stat-card.resources {
            border-left-color: var(--info-color);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            margin-bottom: 20px;
        }

        .stat-card.volunteers .stat-icon {
            background: rgba(33, 150, 243, 0.1);
            color: var(--accent-color);
        }

        .stat-card.activities .stat-icon {
            background: rgba(255, 152, 0, 0.1);
            color: var(--secondary-color);
        }

        .stat-card.opportunities .stat-icon {
            background: rgba(76, 175, 80, 0.1);
            color: var(--success-color);
        }

        .stat-card.resources .stat-icon {
            background: rgba(33, 150, 243, 0.1);
            color: var(--info-color);
        }

        .stat-card h3 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 5px;
            color: var(--text-dark);
        }

        .stat-card p {
            color: var(--text-light);
            font-size: 0.95rem;
            margin: 0;
        }

        /* RECENT ACTIVITIES */
        .dashboard-section {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            margin-bottom: 30px;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }

        .section-header h2 {
            font-size: 1.5rem;
            color: var(--text-dark);
            font-weight: 600;
            margin: 0;
        }

        .section-header a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
            display: flex;
            align-items: center;
            transition: all 0.3s;
        }

        .section-header a:hover {
            color: var(--primary-dark);
        }

        .section-header a i {
            margin-left: 5px;
            transition: transform 0.3s;
        }

        .section-header a:hover i {
            transform: translateX(5px);
        }

        .activity-list {
            list-style: none;
            padding: 0;
        }

        .activity-item {
            display: flex;
            align-items: center;
            padding: 15px 0;
            border-bottom: 1px solid var(--border-color);
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        .activity-icon {
            width: 45px;
            height: 45px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            font-size: 1.2rem;
            color: white;
        }

        .activity-icon.profile {
            background: linear-gradient(45deg, var(--primary-light), var(--primary-color));
        }

        .activity-icon.volunteer {
            background: linear-gradient(45deg, var(--accent-color), #0d47a1);
        }

        .activity-icon.story {
            background: linear-gradient(45deg, var(--secondary-color), #e65100);
        }

        .activity-icon.opportunity {
            background: linear-gradient(45deg, var(--success-color), #1b5e20);
        }

        .activity-content h4 {
            font-size: 1rem;
            margin-bottom: 5px;
            color: var(--text-dark);
        }

        .activity-content p {
            font-size: 0.9rem;
            color: var(--text-light);
            margin: 0;
        }

        .activity-time {
            font-size: 0.85rem;
            color: var(--text-light);
            margin-left: auto;
        }

        /* QUICK ACTIONS */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .action-btn {
            background: var(--card-bg);
            border: 2px solid var(--border-color);
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            text-decoration: none;
            color: var(--text-dark);
            transition: all 0.3s;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .action-btn:hover {
            border-color: var(--primary-color);
            background: rgba(46, 125, 50, 0.05);
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(46, 125, 50, 0.1);
        }

        .action-btn i {
            font-size: 2rem;
            margin-bottom: 15px;
            color: var(--primary-color);
        }

        .action-btn h4 {
            font-size: 1.1rem;
            margin-bottom: 5px;
        }

        .action-btn p {
            font-size: 0.9rem;
            color: var(--text-light);
            margin: 0;
        }

        /* RESPONSIVE */
        @media (max-width: 992px) {
            .sidebar {
                width: 230px;
            }
            
            .main-content {
                margin-left: 230px;
            }
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
                margin-bottom: 20px;
            }
            
            .main-content {
                margin-left: 0;
                padding: 20px;
            }
            
            .dashboard-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .header-actions {
                align-self: flex-end;
            }
            
            .quick-stats {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 576px) {
            .quick-stats {
                grid-template-columns: 1fr;
            }
            
            .quick-actions {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .dashboard-section {
                padding: 20px;
            }
        }

        @media (max-width: 480px) {
            .quick-actions {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- SIDEBAR -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h2>NGO Panel</h2>
            <p>Disaster Relief Management</p>
        </div>
        
        <ul class="nav-menu">
            <li class="nav-item">
                <a href="ngo_dashboard.php" class="nav-link active">
                    <i class="fas fa-tachometer-alt"></i>Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a href="ngo_profile.php" class="nav-link">
                    <i class="fas fa-user-circle"></i>Profile
                </a>
            </li>
            <li class="nav-item">
                <a href="ngo_view_volunteer.php" class="nav-link">
                    <i class="fas fa-users"></i>My Volunteers
                </a>
            </li>
            <li class="nav-item">
                <a href="ngo_create_news.php" class="nav-link">
                    <i class="fas fa-newspaper"></i>Apply Story Activity
                </a>
            </li>
            <li class="nav-item">
                <a href="ngo_post_opportunity.php" class="nav-link">
                    <i class="fas fa-bullhorn"></i>Post Opportunity
                </a>
            </li>
            <li class="nav-item">
                <a href="ngo_view_opportunities.php" class="nav-link">
                    <i class="fas fa-eye"></i>View Opportunities
                </a>
            </li>
            <li class="nav-item">
                <a href="#" class="nav-link">
                    <i class="fas fa-box-open"></i>Distribution
                </a>
            </li>
            <li class="nav-item">
                <a href="resource.php" class="nav-link">
                    <i class="fas fa-boxes"></i>Resource
                </a>
            </li>
            <li class="nav-item logout-link">
                <a href="main_page.php" class="nav-link">
                    <i class="fas fa-sign-out-alt"></i>Logout
                </a>
            </li>
        </ul>
    </div>

    <!-- MAIN CONTENT -->
    <div class="main-content">
        <!-- HEADER -->
        <div class="dashboard-header">
            <div class="welcome-section">
                <h1>Welcome, <span class="ngo-name">kk</span></h1>
                <p>This is your NGO dashboard overview</p>
            </div>
            
            <div class="header-actions">
                <a href="#" class="notification-btn">
                    <i class="fas fa-bell"></i>
                    <span class="notification-badge">3</span>
                </a>
                <div class="user-avatar">
                    <div style="width: 45px; height: 45px; background: linear-gradient(45deg, var(--primary-color), var(--primary-dark)); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold;">
                        KK
                    </div>
                </div>
            </div>
        </div>

        <!-- QUICK STATS -->
        <div class="quick-stats">
            <div class="stat-card volunteers">
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
                <h3>24</h3>
                <p>Active Volunteers</p>
            </div>
            
            <div class="stat-card activities">
                <div class="stat-icon">
                    <i class="fas fa-tasks"></i>
                </div>
                <h3>8</h3>
                <p>Story Activities</p>
            </div>
            
            <div class="stat-card opportunities">
                <div class="stat-icon">
                    <i class="fas fa-bullhorn"></i>
                </div>
                <h3>12</h3>
                <p>Opportunities Posted</p>
            </div>
            
            <div class="stat-card resources">
                <div class="stat-icon">
                    <i class="fas fa-boxes"></i>
                </div>
                <h3>156</h3>
                <p>Resources Managed</p>
            </div>
        </div>

        <!-- RECENT ACTIVITIES -->
        <div class="dashboard-section">
            <div class="section-header">
                <h2>Recent Activities</h2>
                <a href="#">View All <i class="fas fa-arrow-right"></i></a>
            </div>
            
            <ul class="activity-list">
                <li class="activity-item">
                    <div class="activity-icon profile">
                        <i class="fas fa-user-edit"></i>
                    </div>
                    <div class="activity-content">
                        <h4>Profile Updated</h4>
                        <p>You updated your organization profile</p>
                    </div>
                    <div class="activity-time">2 hours ago</div>
                </li>
                
                <li class="activity-item">
                    <div class="activity-icon volunteer">
                        <i class="fas fa-user-plus"></i>
                    </div>
                    <div class="activity-content">
                        <h4>New Volunteer</h4>
                        <p>Ahmad joined your volunteer team</p>
                    </div>
                    <div class="activity-time">1 day ago</div>
                </li>
                
                <li class="activity-item">
                    <div class="activity-icon story">
                        <i class="fas fa-newspaper"></i>
                    </div>
                    <div class="activity-content">
                        <h4>Story Activity Submitted</h4>
                        <p>"Flood Relief in Alor Gajah" submitted for approval</p>
                    </div>
                    <div class="activity-time">2 days ago</div>
                </li>
                
                <li class="activity-item">
                    <div class="activity-icon opportunity">
                        <i class="fas fa-bullhorn"></i>
                    </div>
                    <div class="activity-content">
                        <h4>Opportunity Posted</h4>
                        <p>"Medical Volunteers Needed" opportunity published</p>
                    </div>
                    <div class="activity-time">3 days ago</div>
                </li>
            </ul>
        </div>

        <!-- PENDING APPROVALS -->
        <div class="dashboard-section">
            <div class="section-header">
                <h2>Pending Approvals</h2>
                <a href="#">View All <i class="fas fa-arrow-right"></i></a>
            </div>
            
            <div class="alert alert-warning" style="background: rgba(255, 152, 0, 0.1); border-color: rgba(255, 152, 0, 0.3); color: #e65100; border-radius: 10px; padding: 15px;">
                <div style="display: flex; align-items: center;">
                    <i class="fas fa-clock me-3" style="font-size: 1.2rem;"></i>
                    <div>
                        <h5 style="margin: 0 0 5px 0; font-weight: 600;">3 Items Awaiting Approval</h5>
                        <p style="margin: 0; font-size: 0.95rem;">You have 2 story activities and 1 resource request pending approval from the admin.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- QUICK ACTIONS -->
        <div class="dashboard-section">
            <div class="section-header">
                <h2>Quick Actions</h2>
            </div>
            
            <div class="quick-actions">
                <a href="#" class="action-btn">
                    <i class="fas fa-user-plus"></i>
                    <h4>Add Volunteer</h4>
                    <p>Register new volunteers to your team</p>
                </a>
                
                <a href="#" class="action-btn">
                    <i class="fas fa-newspaper"></i>
                    <h4>Post Story</h4>
                    <p>Share your latest relief activities</p>
                </a>
                
                <a href="#" class="action-btn">
                    <i class="fas fa-bullhorn"></i>
                    <h4>Create Opportunity</h4>
                    <p>Post volunteer opportunities</p>
                </a>
                
                <a href="#" class="action-btn">
                    <i class="fas fa-box-open"></i>
                    <h4>Manage Resources</h4>
                    <p>Update inventory and distributions</p>
                </a>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Active menu item highlighting
        document.addEventListener('DOMContentLoaded', function() {
            const navLinks = document.querySelectorAll('.nav-link');
            
            navLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    // Remove active class from all links
                    navLinks.forEach(item => {
                        item.classList.remove('active');
                    });
                    
                    // Add active class to clicked link
                    this.classList.add('active');
                });
            });
            
            // Notification bell animation
            const notificationBtn = document.querySelector('.notification-btn');
            if (notificationBtn) {
                notificationBtn.addEventListener('click', function() {
                    const badge = this.querySelector('.notification-badge');
                    if (badge) {
                        badge.style.transform = 'scale(1.2)';
                        setTimeout(() => {
                            badge.style.transform = 'scale(1)';
                        }, 300);
                    }
                });
            }
        });
    </script>
</body>
</html>