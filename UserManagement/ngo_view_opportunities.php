<?php
session_start();

// --- SECURITY CHECK ---
if(!isset($_SESSION['name']) || $_SESSION['role'] != "ngo"){
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

// Get NGO ID and name
$ngo_id = $_SESSION['user_id'] ?? 0;
$ngo_name = $_SESSION['name'];

// Fetch NGO details for header
$ngo_sql = "SELECT NGOName FROM NGO WHERE NGOID = ?";
$ngo_stmt = sqlsrv_query($conn, $ngo_sql, array($ngo_id));
if($ngo_stmt && sqlsrv_has_rows($ngo_stmt)){
    $ngo_row = sqlsrv_fetch_array($ngo_stmt, SQLSRV_FETCH_ASSOC);
    $ngo_name = $ngo_row['NGOName'] ?? $_SESSION['name'];
}

// ================= FETCH OPPORTUNITIES WITH VOLUNTEER INFO =================
$sql = "
SELECT 
    o.*,
    -- Count berapa banyak volunteers dah apply
    (SELECT COUNT(*) FROM opportunity_volunteer ov 
     WHERE ov.opportunity_id = o.opportunity_id) as applied_count,
    -- List nama volunteers yang dah apply
    STUFF((
        SELECT ', ' + v.FullName
        FROM opportunity_volunteer ov
        JOIN Volunteer v ON ov.volunteer_id = v.VolunteerID
        WHERE ov.opportunity_id = o.opportunity_id
        FOR XML PATH(''), TYPE
    ).value('.', 'NVARCHAR(MAX)'),1,2,'') AS volunteers_list,
    -- Format date nicely
    FORMAT(o.created_at, 'dd MMM yyyy HH:mm') as formatted_date,
    FORMAT(o.event_date, 'dd MMM yyyy') as formatted_event_date
FROM opportunity o
WHERE o.ngo_id = ? 
ORDER BY o.created_at DESC";

$params = array($ngo_id);
$stmt = sqlsrv_query($conn, $sql, $params);

if($stmt === false){
    die(print_r(sqlsrv_errors(), true));
}

// Count stats
$total = 0;
$open = 0;
$pending = 0;
$closed = 0;
$rows = [];

while($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)){
    $rows[] = $row;
    $total++;
    if($row['status'] === 'Open') $open++;
    if($row['status'] === 'Pending') $pending++;
    if($row['status'] === 'Closed') $closed++;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>View Opportunities - VolunteerHub</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --primary-color: #2e7d32;
            --primary-dark: #1b5e20;
            --primary-light: #4caf50;
            --secondary-color: #ff9800;
            --accent-color: #2196f3;
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
            min-height: 100vh;
        }

        /* System Header */
        .system-header {
            background: linear-gradient(135deg, var(--primary-dark) 0%, var(--primary-color) 100%);
            color: white;
            padding: 0 20px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.1);
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
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
            color: #a5d6a7;
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
            color: #c8e6c9;
        }

        .header-controls {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 5px 15px;
            border-radius: 25px;
            background: rgba(255, 255, 255, 0.08);
            transition: all 0.3s ease;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, var(--primary-light) 0%, var(--primary-color) 100%);
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
            color: #c8e6c9;
        }

        /* Main Layout */
        .main-wrapper {
            display: flex;
            min-height: 100vh;
            padding-top: 70px;
        }

        /* Sidebar */
        .sidebar {
            width: 260px;
            background: linear-gradient(180deg, var(--primary-dark) 0%, var(--primary-color) 100%);
            color: white;
            padding: 25px 0;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
            flex-shrink: 0;
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

        .nav-menu {
            list-style: none;
            padding: 0 15px;
            margin: 0;
        }

        .nav-item {
            margin-bottom: 5px;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 15px;
            color: rgba(255, 255, 255, 0.9);
            text-decoration: none;
            border-radius: 10px;
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
            border-left: 4px solid var(--secondary-color);
        }

        .nav-link i {
            width: 24px;
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

        /* Main Content */
        .main-content {
            flex: 1;
            padding: 30px;
            background: var(--bg-light);
            overflow-y: auto;
        }

        /* Profile Header */
        .profile-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 40px;
            padding: 25px;
            background: var(--card-bg);
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        }

        .profile-title h1 {
            font-size: 2rem;
            color: var(--text-dark);
            margin-bottom: 5px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .profile-subtitle {
            color: var(--text-light);
            font-size: 1rem;
        }

        .ngo-badge {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
            padding: 8px 20px;
            border-radius: 50px;
            font-weight: 600;
            font-size: 0.9rem;
            box-shadow: 0 4px 10px rgba(46, 125, 50, 0.2);
        }

        /* Stats Container */
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stats-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            border: 1px solid var(--border-color);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }

        .stats-card.total {
            border-top: 4px solid var(--primary-color);
        }

        .stats-card.open {
            border-top: 4px solid var(--success-color);
        }

        .stats-card.pending {
            border-top: 4px solid var(--warning-color);
        }

        .stats-card.closed {
            border-top: 4px solid #9b59b6;
        }

        .stats-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 15px;
            font-size: 24px;
        }

        .stats-card.total .stats-icon {
            background: rgba(46, 125, 50, 0.1);
            color: var(--primary-color);
        }

        .stats-card.open .stats-icon {
            background: rgba(76, 175, 80, 0.1);
            color: var(--success-color);
        }

        .stats-card.pending .stats-icon {
            background: rgba(255, 152, 0, 0.1);
            color: var(--warning-color);
        }

        .stats-card.closed .stats-icon {
            background: rgba(155, 89, 182, 0.1);
            color: #9b59b6;
        }

        .stats-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--text-dark);
            line-height: 1;
            margin-bottom: 5px;
        }

        .stats-label {
            color: var(--text-light);
            font-size: 0.95rem;
            margin-bottom: 5px;
        }

        .stats-change {
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .stats-change.positive {
            color: var(--success-color);
        }

        /* Table Container */
        .table-container {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            margin-top: 20px;
            overflow: hidden;
        }

        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--border-color);
        }

        .table-header h3 {
            color: var(--text-dark);
            font-weight: 700;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* DataTable Custom */
        .dataTables_wrapper {
            padding: 0;
        }

        .table {
            border: 1px solid var(--border-color) !important;
            border-radius: 8px;
            overflow: hidden;
        }

        .table thead {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            color: white;
        }

        .table thead th {
            border: none;
            padding: 15px 20px;
            font-weight: 600;
        }

        .table tbody tr {
            transition: background-color 0.2s ease;
        }

        .table tbody tr:hover {
            background-color: rgba(46, 125, 50, 0.05);
        }

        .table tbody td {
            padding: 15px 20px;
            vertical-align: middle;
            border-color: var(--border-color);
        }

        /* Badge Styles */
        .badge {
            padding: 6px 12px;
            font-weight: 600;
            border-radius: 20px;
        }

        .badge-success {
            background-color: rgba(76, 175, 80, 0.1) !important;
            color: var(--success-color) !important;
            border: 1px solid rgba(76, 175, 80, 0.3);
        }

        .badge-warning {
            background-color: rgba(255, 152, 0, 0.1) !important;
            color: var(--warning-color) !important;
            border: 1px solid rgba(255, 152, 0, 0.3);
        }

        .badge-secondary {
            background-color: rgba(158, 158, 158, 0.1) !important;
            color: #757575 !important;
            border: 1px solid rgba(158, 158, 158, 0.3);
        }

        /* Action Buttons */
        .btn-action {
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.3s;
            text-decoration: none;
            border: none;
            cursor: pointer;
        }

        .btn-view {
            background: rgba(33, 150, 243, 0.1);
            color: var(--info-color);
            border: 1px solid rgba(33, 150, 243, 0.3);
        }

        .btn-view:hover {
            background: var(--info-color);
            color: white;
            border-color: var(--info-color);
        }

        .btn-edit {
            background: rgba(255, 152, 0, 0.1);
            color: var(--warning-color);
            border: 1px solid rgba(255, 152, 0, 0.3);
        }

        .btn-edit:hover {
            background: var(--warning-color);
            color: white;
            border-color: var(--warning-color);
        }

        .btn-delete {
            background: rgba(244, 67, 54, 0.1);
            color: var(--danger-color);
            border: 1px solid rgba(244, 67, 54, 0.3);
        }

        .btn-delete:hover {
            background: var(--danger-color);
            color: white;
            border-color: var(--danger-color);
        }

        /* Progress Bar */
        .progress {
            height: 8px;
            border-radius: 4px;
            background-color: rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .progress-bar {
            border-radius: 4px;
        }

        /* Volunteer List */
        .volunteer-tag {
            display: inline-block;
            background: rgba(33, 150, 243, 0.1);
            color: var(--info-color);
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.8rem;
            margin: 2px;
            border: 1px solid rgba(33, 150, 243, 0.2);
        }

        .more-volunteers {
            background: rgba(158, 158, 158, 0.1);
            color: #757575;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.8rem;
            margin: 2px;
            border: 1px solid rgba(158, 158, 158, 0.2);
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-light);
        }

        .empty-state i {
            font-size: 4rem;
            color: #e0e0e0;
            margin-bottom: 20px;
        }

        .empty-state h4 {
            color: var(--text-light);
            margin-bottom: 10px;
        }

        /* Action Buttons */
        .btn {
            padding: 16px 35px;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            color: white;
            box-shadow: 0 5px 15px rgba(46, 125, 50, 0.3);
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, var(--primary-dark) 0%, #1b5e20 100%);
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(46, 125, 50, 0.4);
        }

        /* Footer Stats */
        .footer-stats {
            display: flex;
            justify-content: center;
            gap: 30px;
            margin-top: 30px;
            padding: 20px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            border: 1px solid var(--border-color);
        }

        .footer-stat {
            text-align: center;
        }

        .footer-stat-number {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 5px;
        }

        .footer-stat-label {
            font-size: 0.9rem;
            color: var(--text-light);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .main-wrapper {
                flex-direction: column;
            }
            
            .sidebar {
                width: 100%;
                position: relative;
                margin-bottom: 20px;
            }
            
            .profile-header {
                flex-direction: column;
                gap: 20px;
                text-align: center;
            }
            
            .stats-container {
                grid-template-columns: 1fr;
            }
            
            .table-header {
                flex-direction: column;
                gap: 15px;
                align-items: flex-start;
            }
            
            .footer-stats {
                flex-direction: column;
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
                <div class="logo-icon">
                    <i class="fas fa-hands-helping"></i>
                </div>
                <div class="logo-text">
                    <h1>NGO Panel</h1>
                    <small>VolunteerHub - Disaster Relief System</small>
                </div>
            </div>
            
            <div class="header-controls">
                <div class="user-profile">
                    <div class="user-avatar">
                        <?php echo !empty($ngo_name) ? strtoupper(substr($ngo_name, 0, 1)) : 'N'; ?>
                    </div>
                    <div class="user-info">
                        <div class="user-name"><?php echo htmlspecialchars($ngo_name); ?></div>
                        <div class="user-role">NGO Organization</div>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Wrapper -->
    <div class="main-wrapper">
        <!-- Sidebar -->
        <nav class="sidebar">
            <div class="sidebar-header">
                <h2>NGO Panel</h2>
                <p>Disaster Relief Management</p>
            </div>
            
            <ul class="nav-menu">
                <li class="nav-item">
                    <a href="ngo_dashboard.php" class="nav-link">
                        <i class="fas fa-tachometer-alt"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                
                <li class="nav-item">
                    <a href="ngo_profile.php" class="nav-link">
                        <i class="fas fa-user-circle"></i>
                        <span>Profile</span>
                    </a>
                </li>
                
                <li class="nav-item">
                    <a href="ngo_view_volunteer.php" class="nav-link">
                        <i class="fas fa-users"></i>
                        <span>My Volunteers</span>
                    </a>
                </li>
                
                <li class="nav-item">
                    <a href="ngo_create_news.php" class="nav-link">
                        <i class="fas fa-newspaper"></i>
                        <span>Apply Story Activity</span>
                    </a>
                </li>
                
                <li class="nav-item">
                    <a href="ngo_post_opportunity.php" class="nav-link">
                        <i class="fas fa-bullhorn"></i>
                        <span>Post Opportunity</span>
                    </a>
                </li>
                
                <li class="nav-item">
                    <a href="ngo_view_opportunities.php" class="nav-link active">
                        <i class="fas fa-eye"></i>
                        <span>View Opportunities</span>
                    </a>
                </li>
                
                <li class="nav-item">
                    <a href="distribution.php" class="nav-link">
                        <i class="fas fa-box-open"></i>
                        <span>Distribution</span>
                    </a>
                </li>
                
                <li class="nav-item logout-link">
                    <a href="main_page.php" class="nav-link">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>Logout</span>
                    </a>
                </li>
            </ul>
        </nav>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Profile Header -->
            <div class="profile-header">
                <div class="profile-title">
                    <h1><i class="fas fa-briefcase me-3"></i>My Opportunities</h1>
                    <p class="profile-subtitle">Manage and track all volunteer opportunities posted by your NGO</p>
                </div>
                <div class="ngo-badge">
                    <i class="fas fa-tasks me-2"></i>OPPORTUNITY MANAGEMENT
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="stats-container">
                <div class="stats-card total">
                    <div class="stats-icon">
                        <i class="fas fa-briefcase"></i>
                    </div>
                    <div class="stats-number"><?php echo $total; ?></div>
                    <div class="stats-label">Total Opportunities</div>
                    <div class="stats-change">
                        <i class="fas fa-chart-line"></i> Created by your NGO
                    </div>
                </div>

                <div class="stats-card open">
                    <div class="stats-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stats-number"><?php echo $open; ?></div>
                    <div class="stats-label">Open Opportunities</div>
                    <div class="stats-change positive">
                        <i class="fas fa-user-check"></i> Accepting volunteers
                    </div>
                </div>

                <div class="stats-card pending">
                    <div class="stats-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stats-number"><?php echo $pending; ?></div>
                    <div class="stats-label">Pending Approval</div>
                    <div class="stats-change">
                        <i class="fas fa-hourglass-half"></i> Awaiting admin review
                    </div>
                </div>

                <div class="stats-card closed">
                    <div class="stats-icon">
                        <i class="fas fa-lock"></i>
                    </div>
                    <div class="stats-number"><?php echo $closed; ?></div>
                    <div class="stats-label">Closed Opportunities</div>
                    <div class="stats-change">
                        <i class="fas fa-archive"></i> No longer accepting
                    </div>
                </div>
            </div>

            <!-- Table Section -->
            <div class="table-container">
                <div class="table-header">
                    <h3><i class="fas fa-list me-2"></i>Opportunities List</h3>
                    <a href="post_opportunity.php" class="btn btn-primary">
                        <i class="fas fa-plus me-2"></i>Post New Opportunity
                    </a>
                </div>

                <?php if(count($rows) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Opportunity Details</th>
                                <th>Location & Date</th>
                                <th>Slots & Progress</th>
                                <th>Status</th>
                                <th>Volunteers</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php $i = 1; foreach($rows as $row): 
                            // Calculate available slots
                            $total_slots = $row['slots'];
                            $applied_count = $row['applied_count'];
                            $available_slots = $total_slots - $applied_count;
                            $progress_percentage = $total_slots > 0 ? ($applied_count / $total_slots) * 100 : 0;
                            
                            // Get volunteer names
                            $volunteers_list = $row['volunteers_list'];
                            $volunteers_array = !empty($volunteers_list) ? explode(', ', $volunteers_list) : [];
                        ?>
                            <tr>
                                <td class="fw-bold"><?php echo $i++; ?></td>
                                <td>
                                    <div class="mb-1">
                                        <strong><?php echo htmlspecialchars($row['title']); ?></strong>
                                    </div>
                                    <small class="text-muted">
                                        <?php echo substr(htmlspecialchars($row['description']), 0, 80); ?>...
                                    </small>
                                    <div class="mt-1">
                                        <small class="text-muted">
                                            <i class="fas fa-calendar-plus me-1"></i>
                                            Posted: <?php echo $row['formatted_date']; ?>
                                        </small>
                                    </div>
                                </td>
                                <td>
                                    <div class="mb-2">
                                        <i class="fas fa-map-marker-alt me-2 text-success"></i>
                                        <?php echo htmlspecialchars($row['location']) ?: 'Not specified'; ?>
                                    </div>
                                    <div>
                                        <?php if($row['formatted_event_date']): ?>
                                            <i class="fas fa-calendar-day me-2 text-primary"></i>
                                            <?php echo $row['formatted_event_date']; ?>
                                        <?php else: ?>
                                            <i class="fas fa-infinity me-2 text-muted"></i>
                                            Ongoing
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="mb-2">
                                        <span class="fw-bold"><?php echo $available_slots; ?></span> / 
                                        <span class="text-muted"><?php echo $total_slots; ?> slots available</span>
                                    </div>
                                    <div class="progress mb-1">
                                        <div class="progress-bar 
                                            <?php echo $available_slots > 0 ? 'bg-success' : 'bg-danger'; ?>" 
                                            role="progressbar" 
                                            style="width: <?php echo $progress_percentage; ?>%">
                                        </div>
                                    </div>
                                    <small class="text-muted">
                                        <?php echo $applied_count; ?> volunteer(s) applied
                                    </small>
                                </td>
                                <td>
                                    <?php if($row['status'] === 'Open'): ?>
                                        <span class="badge badge-success">
                                            <i class="fas fa-check-circle me-1"></i>Open
                                        </span>
                                    <?php elseif($row['status'] === 'Pending'): ?>
                                        <span class="badge badge-warning">
                                            <i class="fas fa-clock me-1"></i>Pending
                                        </span>
                                    <?php else: ?>
                                        <span class="badge badge-secondary">
                                            <i class="fas fa-lock me-1"></i>Closed
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if(count($volunteers_array) > 0): ?>
                                        <div class="d-flex flex-wrap gap-1">
                                            <?php 
                                            $display_count = min(2, count($volunteers_array));
                                            for($j = 0; $j < $display_count; $j++): 
                                            ?>
                                                <span class="volunteer-tag">
                                                    <?php echo htmlspecialchars(substr($volunteers_array[$j], 0, 15)) . (strlen($volunteers_array[$j]) > 15 ? '...' : ''); ?>
                                                </span>
                                            <?php endfor; ?>
                                            
                                            <?php if(count($volunteers_array) > 2): ?>
                                                <span class="more-volunteers" 
                                                      data-bs-toggle="tooltip" 
                                                      title="<?php echo htmlspecialchars(implode(', ', array_slice($volunteers_array, 2))); ?>">
                                                    +<?php echo count($volunteers_array) - 2; ?> more
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted">No volunteers yet</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="d-flex gap-2">
                                        <a href="view_opportunity_details.php?id=<?php echo $row['opportunity_id']; ?>" 
                                           class="btn-action btn-view"
                                           title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="edit_opportunity.php?id=<?php echo $row['opportunity_id']; ?>" 
                                           class="btn-action btn-edit"
                                           title="Edit Opportunity">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button class="btn-action btn-delete" 
                                                onclick="confirmDelete(<?php echo $row['opportunity_id']; ?>)"
                                                title="Delete Opportunity">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-briefcase"></i>
                        <h4>No Opportunities Found</h4>
                        <p class="mb-4">You haven't posted any volunteer opportunities yet.</p>
                        <a href="post_opportunity.php" class="btn btn-primary">
                            <i class="fas fa-plus me-2"></i>Post Your First Opportunity
                        </a>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Footer Statistics -->
            <div class="footer-stats">
                <div class="footer-stat">
                    <div class="footer-stat-number"><?php echo $total; ?></div>
                    <div class="footer-stat-label">Total Opportunities</div>
                </div>
                <div class="footer-stat">
                    <div class="footer-stat-number"><?php echo $open; ?></div>
                    <div class="footer-stat-label">Open</div>
                </div>
                <div class="footer-stat">
                    <div class="footer-stat-number"><?php echo $pending; ?></div>
                    <div class="footer-stat-label">Pending</div>
                </div>
                <div class="footer-stat">
                    <div class="footer-stat-number"><?php echo $closed; ?></div>
                    <div class="footer-stat-label">Closed</div>
                </div>
            </div>
        </main>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, var(--danger-color) 0%, #c0392b 100%); color: white;">
                    <h5 class="modal-title"><i class="fas fa-exclamation-triangle me-2"></i>Confirm Delete</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <p>Are you sure you want to delete this opportunity?</p>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <strong>Warning:</strong> This action cannot be undone. All volunteer applications for this opportunity will also be removed.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form method="POST" action="" id="deleteForm" style="margin: 0;">
                        <input type="hidden" name="delete_id" id="deleteOpportunityId">
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-trash me-2"></i>Delete Opportunity
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Delete confirmation
        function confirmDelete(opportunityId) {
            document.getElementById('deleteOpportunityId').value = opportunityId;
            const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
            deleteModal.show();
        }

        // Initialize tooltips
        document.addEventListener('DOMContentLoaded', function() {
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        });

        // Add hover effect to table rows
        document.querySelectorAll('.table tbody tr').forEach(row => {
            row.addEventListener('mouseenter', function() {
                this.style.transform = 'translateX(5px)';
                this.style.transition = 'transform 0.2s ease';
            });
            
            row.addEventListener('mouseleave', function() {
                this.style.transform = 'translateX(0)';
            });
        });

        // Handle delete form submission
        document.getElementById('deleteForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Get the opportunity ID
            const opportunityId = document.getElementById('deleteOpportunityId').value;
            
            // Show loading state
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Deleting...';
            submitBtn.disabled = true;
            
            // Simulate API call (replace with actual AJAX call)
            setTimeout(() => {
                // In a real application, you would make an AJAX call here
                // For now, we'll just submit the form
                this.submit();
            }, 1000);
        });

        // Refresh page every 30 seconds for updates
        setInterval(function() {
            // You could implement AJAX refresh here if needed
            // For now, we'll just log to console
            console.log('Checking for opportunity updates...');
        }, 30000);
    </script>
</body>
</html>

<?php
sqlsrv_close($conn);
?>