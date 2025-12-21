<?php
session_start();

error_reporting(E_ALL);
ini_set('display_errors', 1);

// SECURITY CHECK
if (!isset($_SESSION['name']) || $_SESSION['role'] !== "ngo") {
    header("Location: login.php");
    exit();
}

require_once "connection.php";

// CURRENT NGO ID
$current_ngo_id = $_SESSION['user_id'];

// FETCH VOLUNTEERS
$sql = "
SELECT 
    VolunteerID,
    FullName,
    Email,
    Phone,
    SkillCategory,
    Status
FROM Volunteer
WHERE AssignedNGO = ?
ORDER BY VolunteerID DESC
";

$params = array($current_ngo_id);
$result = sqlsrv_query($conn, $sql, $params);

// COUNT VOLUNTEERS
$count_sql = "
SELECT COUNT(*) AS total 
FROM Volunteer 
WHERE AssignedNGO = ?
";
$count_result = sqlsrv_query($conn, $count_sql, $params);
$count_row = sqlsrv_fetch_array($count_result, SQLSRV_FETCH_ASSOC);
$total_volunteers = $count_row['total'];

// Fetch NGO name for header
$ngo_sql = "SELECT NGOName FROM NGO WHERE NGOID = ?";
$ngo_stmt = sqlsrv_query($conn, $ngo_sql, array($current_ngo_id));
$ngo_row = sqlsrv_fetch_array($ngo_stmt, SQLSRV_FETCH_ASSOC);
$ngo_name = $ngo_row['NGOName'] ?? 'NGO User';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Volunteers - VolunteerHub</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>

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
            margin-bottom: 30px;
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

        /* Stats Cards */
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
        }

        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }

        .stats-card.total {
            border-top: 4px solid var(--primary-color);
        }

        .stats-card.active {
            border-top: 4px solid var(--success-color);
        }

        .stats-card.inactive {
            border-top: 4px solid var(--warning-color);
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

        .stats-card.active .stats-icon {
            background: rgba(76, 175, 80, 0.1);
            color: var(--success-color);
        }

        .stats-card.inactive .stats-icon {
            background: rgba(255, 152, 0, 0.1);
            color: var(--warning-color);
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

        .stats-change.negative {
            color: var(--danger-color);
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
        }

        .table-actions {
            display: flex;
            gap: 10px;
        }

        /* DataTable Custom */
        #volunteerTable_wrapper {
            padding: 0;
        }

        #volunteerTable {
            border: 1px solid var(--border-color) !important;
            border-radius: 8px;
            overflow: hidden;
        }

        #volunteerTable thead {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            color: white;
        }

        #volunteerTable thead th {
            border: none;
            padding: 15px 20px;
            font-weight: 600;
        }

        #volunteerTable tbody tr {
            transition: background-color 0.2s ease;
        }

        #volunteerTable tbody tr:hover {
            background-color: rgba(46, 125, 50, 0.05);
        }

        #volunteerTable tbody td {
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

        .badge-info {
            background-color: rgba(33, 150, 243, 0.1) !important;
            color: var(--info-color) !important;
            border: 1px solid rgba(33, 150, 243, 0.3);
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

        .btn-email {
            background: rgba(76, 175, 80, 0.1);
            color: var(--success-color);
            border: 1px solid rgba(76, 175, 80, 0.3);
        }

        .btn-email:hover {
            background: var(--success-color);
            color: white;
            border-color: var(--success-color);
        }

        .btn-call {
            background: rgba(255, 152, 0, 0.1);
            color: var(--warning-color);
            border: 1px solid rgba(255, 152, 0, 0.3);
        }

        .btn-call:hover {
            background: var(--warning-color);
            color: white;
            border-color: var(--warning-color);
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

        /* Search and Filter */
        .search-filter {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 25px;
        }

        .search-box {
            position: relative;
        }

        .search-box i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-light);
        }

        .search-box input {
            width: 100%;
            padding: 12px 15px 12px 45px;
            border: 2px solid var(--border-color);
            border-radius: 10px;
            font-size: 1rem;
            transition: all 0.3s;
        }

        .search-box input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(46, 125, 50, 0.1);
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
            
            .table-actions {
                width: 100%;
                justify-content: flex-start;
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
                    <a href="ngo_view_volunteer.php" class="nav-link active">
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
                    <a href="ngo_view_opportunities.php" class="nav-link">
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
                    <h1><i class="fas fa-users me-3"></i>My Volunteers</h1>
                    <p class="profile-subtitle">Manage and communicate with volunteers assigned to your NGO</p>
                </div>
                <div class="ngo-badge">
                    <i class="fas fa-hands-helping me-2"></i>VOLUNTEER MANAGEMENT
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="stats-container">
                <div class="stats-card total">
                    <div class="stats-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stats-number"><?= $total_volunteers ?></div>
                    <div class="stats-label">Total Volunteers</div>
                    <div class="stats-change positive">
                        <i class="fas fa-chart-line"></i> Assigned to your NGO
                    </div>
                </div>

                <?php
                // Get active volunteers count
                $active_sql = "SELECT COUNT(*) AS active FROM Volunteer WHERE AssignedNGO = ? AND Status = 'Active'";
                $active_result = sqlsrv_query($conn, $active_sql, $params);
                $active_row = sqlsrv_fetch_array($active_result, SQLSRV_FETCH_ASSOC);
                $active_volunteers = $active_row['active'] ?? 0;
                
                // Get inactive volunteers count
                $inactive_sql = "SELECT COUNT(*) AS inactive FROM Volunteer WHERE AssignedNGO = ? AND Status = 'Inactive'";
                $inactive_result = sqlsrv_query($conn, $inactive_sql, $params);
                $inactive_row = sqlsrv_fetch_array($inactive_result, SQLSRV_FETCH_ASSOC);
                $inactive_volunteers = $inactive_row['inactive'] ?? 0;
                ?>

                <div class="stats-card active">
                    <div class="stats-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stats-number"><?= $active_volunteers ?></div>
                    <div class="stats-label">Active Volunteers</div>
                    <div class="stats-change positive">
                        <i class="fas fa-user-check"></i> Ready to assist
                    </div>
                </div>

                <div class="stats-card inactive">
                    <div class="stats-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stats-number"><?= $inactive_volunteers ?></div>
                    <div class="stats-label">Inactive Volunteers</div>
                    <div class="stats-change">
                        <i class="fas fa-user-clock"></i> Currently unavailable
                    </div>
                </div>
            </div>

            <!-- Table Section -->
            <div class="table-container">
                <div class="table-header">
                    <h3><i class="fas fa-list me-2"></i>Volunteer List</h3>
                    <div class="table-actions">
                        <div class="search-box">
                            <i class="fas fa-search"></i>
                            <input type="text" id="searchInput" placeholder="Search volunteers...">
                        </div>
                    </div>
                </div>

                <?php if ($result && sqlsrv_has_rows($result)): ?>
                <table id="volunteerTable" class="table table-hover">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Volunteer</th>
                            <th>Contact Info</th>
                            <th>Skills</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php 
                    // Reset result pointer
                    sqlsrv_query($conn, $sql, $params);
                    $no = 1; 
                    while ($row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC)): 
                    ?>
                        <tr>
                            <td class="fw-bold"><?= $no++ ?></td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="user-avatar me-3" style="width: 40px; height: 40px; font-size: 14px;">
                                        <?php echo strtoupper(substr($row['FullName'], 0, 1)); ?>
                                    </div>
                                    <div>
                                        <div class="fw-bold"><?= htmlspecialchars($row['FullName']) ?></div>
                                        <small class="text-muted">Volunteer ID: <?= htmlspecialchars($row['VolunteerID']) ?></small>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="mb-1">
                                    <i class="fas fa-envelope me-2 text-primary"></i>
                                    <?= htmlspecialchars($row['Email']) ?>
                                </div>
                                <div>
                                    <i class="fas fa-phone me-2 text-success"></i>
                                    <?= htmlspecialchars($row['Phone']) ?>
                                </div>
                            </td>
                            <td>
                                <?php
                                $skills = explode(',', $row['SkillCategory']);
                                foreach ($skills as $skill):
                                    $skill = trim($skill);
                                    if (!empty($skill)):
                                ?>
                                    <span class="badge badge-info mb-1"><?= htmlspecialchars($skill) ?></span>
                                <?php 
                                    endif;
                                endforeach; 
                                ?>
                            </td>
                            <td>
                                <span class="badge <?= $row['Status']=='Active'?'badge-success':'badge-warning' ?>">
                                    <i class="fas <?= $row['Status']=='Active'?'fa-check-circle':'fa-clock' ?> me-1"></i>
                                    <?= htmlspecialchars($row['Status']) ?>
                                </span>
                            </td>
                            <td>
                                <div class="d-flex gap-2">
                                    <button class="btn btn-action btn-view viewBtn"
                                        data-name="<?= htmlspecialchars($row['FullName']) ?>"
                                        data-email="<?= htmlspecialchars($row['Email']) ?>"
                                        data-phone="<?= htmlspecialchars($row['Phone']) ?>"
                                        data-skills="<?= htmlspecialchars($row['SkillCategory']) ?>"
                                        data-status="<?= htmlspecialchars($row['Status']) ?>">
                                        <i class="fas fa-eye"></i> View
                                    </button>
                                    <a href="mailto:<?= htmlspecialchars($row['Email']) ?>" class="btn btn-action btn-email">
                                        <i class="fas fa-envelope"></i> Email
                                    </a>
                                    <a href="tel:<?= htmlspecialchars($row['Phone']) ?>" class="btn btn-action btn-call">
                                        <i class="fas fa-phone"></i> Call
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-users-slash"></i>
                        <h4>No Volunteers Assigned Yet</h4>
                        <p class="mb-4">Your NGO doesn't have any volunteers assigned at the moment.</p>
                        <a href="post_opportunity.php" class="btn btn-primary">
                            <i class="fas fa-bullhorn me-2"></i>Post an Opportunity
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Modal -->
    <div class="modal fade" id="viewModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%); color: white;">
                    <h5 class="modal-title"><i class="fas fa-user-circle me-2"></i>Volunteer Details</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="text-center mb-4">
                        <div class="user-avatar mx-auto" style="width: 80px; height: 80px; font-size: 28px;" id="modalAvatar">
                            V
                        </div>
                        <h4 class="mt-3 mb-1" id="modalName"></h4>
                        <span class="badge" id="modalStatusBadge"></span>
                    </div>
                    
                    <div class="row">
                        <div class="col-12 mb-3">
                            <label class="form-label"><i class="fas fa-envelope me-2 text-primary"></i>Email Address</label>
                            <div class="form-control bg-light" id="modalEmail"></div>
                        </div>
                        
                        <div class="col-12 mb-3">
                            <label class="form-label"><i class="fas fa-phone me-2 text-success"></i>Phone Number</label>
                            <div class="form-control bg-light" id="modalPhone"></div>
                        </div>
                        
                        <div class="col-12 mb-3">
                            <label class="form-label"><i class="fas fa-tools me-2 text-warning"></i>Skills & Expertise</label>
                            <div class="p-3 bg-light rounded" id="modalSkills"></div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <a href="#" class="btn btn-primary" id="modalEmailBtn">
                        <i class="fas fa-envelope me-2"></i>Send Email
                    </a>
                    <a href="#" class="btn btn-success" id="modalCallBtn">
                        <i class="fas fa-phone me-2"></i>Make Call
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    $(document).ready(function() {
        // Initialize DataTable
        $('#volunteerTable').DataTable({
            "pageLength": 10,
            "language": {
                "search": "Search volunteers:",
                "lengthMenu": "Show _MENU_ entries",
                "info": "Showing _START_ to _END_ of _TOTAL_ volunteers",
                "paginate": {
                    "previous": "<i class='fas fa-chevron-left'></i>",
                    "next": "<i class='fas fa-chevron-right'></i>"
                }
            },
            "dom": '<"top"lf>rt<"bottom"ip><"clear">',
            "initComplete": function() {
                // Move search box to custom location
                $('.dataTables_filter').hide();
            }
        });

        // Custom search
        $('#searchInput').on('keyup', function() {
            $('#volunteerTable').DataTable().search(this.value).draw();
        });

        // View button click handler
        $('.viewBtn').click(function() {
            const name = $(this).data('name');
            const email = $(this).data('email');
            const phone = $(this).data('phone');
            const skills = $(this).data('skills');
            const status = $(this).data('status');
            
            // Update modal content
            $('#modalAvatar').text(name.charAt(0).toUpperCase());
            $('#modalName').text(name);
            $('#modalEmail').text(email);
            $('#modalPhone').text(phone);
            
            // Update status badge
            const statusBadge = $('#modalStatusBadge');
            statusBadge.text(status);
            if (status === 'Active') {
                statusBadge.removeClass('badge-warning').addClass('badge-success');
            } else {
                statusBadge.removeClass('badge-success').addClass('badge-warning');
            }
            
            // Update skills
            const skillsArray = skills.split(',');
            let skillsHtml = '';
            skillsArray.forEach(skill => {
                skill = skill.trim();
                if (skill) {
                    skillsHtml += `<span class="badge badge-info me-1 mb-1">${skill}</span>`;
                }
            });
            $('#modalSkills').html(skillsHtml || '<em>No skills specified</em>');
            
            // Update action buttons
            $('#modalEmailBtn').attr('href', `mailto:${email}`);
            $('#modalCallBtn').attr('href', `tel:${phone}`);
            
            // Show modal
            const modal = new bootstrap.Modal(document.getElementById('viewModal'));
            modal.show();
        });

        // Refresh page every 30 seconds for new volunteers
        setInterval(function() {
            // You could implement AJAX refresh here if needed
            // For now, we'll just log to console
            console.log('Checking for new volunteers...');
        }, 30000);
    });
    </script>

</body>
</html>

<?php sqlsrv_close($conn); ?>