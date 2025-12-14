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
    <title>Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            display: flex;
            background-color: #f5f5f5;
            font-family: Arial, sans-serif;
        }
        .sidebar {
            width: 250px;
            height: 100vh;
            background: #2c3e50;
            padding: 20px;
            position: fixed;
        }
        .sidebar a {
            display: block;
            padding: 12px 15px;
            margin: 8px 0;
            color: white;
            text-decoration: none;
            border-radius: 5px;
        }
        .sidebar a:hover {
            background: #34495e;
        }
        .sidebar a.active {
            background: #3498db;
            font-weight: bold;
        }
        .sidebar h4 {
            color: white;
            margin-bottom: 20px;
            border-bottom: 2px solid #34495e;
            padding-bottom: 10px;
        }
        .content {
            flex-grow: 1;
            padding: 30px;
            margin-left: 250px;
        }
        .card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            border: 1px solid #ddd;
        }
        .stat-card {
            text-align: center;
            padding: 25px 15px;
        }
        .stat-icon {
            font-size: 40px;
            margin-bottom: 15px;
            color: #3498db;
        }
        .stat-number {
            font-size: 36px;
            font-weight: bold;
            color: #2c3e50;
            margin: 10px 0;
        }
        .stat-label {
            color: #7f8c8d;
            font-size: 16px;
        }
    </style>
</head>
<body>

    <!-- Sidebar -->
    <div class="sidebar">
        <h4>Admin Panel</h4>
        <a href="admin_dashboard.php" class="active">🏠 Dashboard</a>
        <a href="admin_profile.php">👤 Profile</a>
        <a href="add_admin.php">➕ Add Admin</a>
        <a href="view_admin.php">📋 View Admins</a>
        <a href="view_ngo.php">🏢 View NGO</a>
        <a href="create_news.php">📰 Create News</a>
        <a href="view_news.php">📜 View News</a>
        <a href="report.php">📊 Reports</a>
        <a href="logout.php" style="color: #e74c3c;">🚪 Logout</a>
    </div>

    <div class="content">
        <div class="mb-4">
            <h2>Welcome, <?php echo $_SESSION['name']; ?></h2>
            <p class="text-muted">Admin Dashboard</p>
        </div>
        
        <!-- Stats Cards -->
        <div class="row">
            <div class="col-md-3">
                <div class="card stat-card">
                    <div class="stat-icon">👥</div>
                    <div class="stat-number"><?php echo $totalAdmins; ?></div>
                    <div class="stat-label">Total Admins</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card">
                    <div class="stat-icon">🏢</div>
                    <div class="stat-number"><?php echo $totalNGOs; ?></div>
                    <div class="stat-label">Total NGOs</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card">
                    <div class="stat-icon">🙋‍♂️</div>
                    <div class="stat-number"><?php echo $totalVolunteers; ?></div>
                    <div class="stat-label">Volunteers</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card">
                    <div class="stat-icon">📊</div>
                    <div class="stat-number"><?php echo $totalReports; ?></div>
                    <div class="stat-label">Reports</div>
                </div>
            </div>
        </div>
        
        <!-- Simple Recent Activity -->
        <div class="card">
            <h5>Recent Activity</h5>
            <ul style="list-style: none; padding-left: 0;">
                <li style="padding: 8px 0; border-bottom: 1px solid #eee;">✅ You logged in to the system</li>
                <li style="padding: 8px 0; border-bottom: 1px solid #eee;">📊 Viewing admin dashboard</li>
                <li style="padding: 8px 0; border-bottom: 1px solid #eee;">👤 Logged in as Admin</li>
                <li style="padding: 8px 0;">🕒 Last updated: <?php echo date('h:i A'); ?></li>
            </ul>
        </div>
        
        <!-- Quick Links -->
        <div class="card">
            <h5>Quick Links</h5>
            <div class="row">
                <div class="col-md-3 mb-2">
                    <a href="add_admin.php" style="text-decoration: none;">
                        <div class="card" style="background: #f8f9fa; padding: 15px; text-align: center;">
                            ➕ Add Admin
                        </div>
                    </a>
                </div>
                <div class="col-md-3 mb-2">
                    <a href="view_admin.php" style="text-decoration: none;">
                        <div class="card" style="background: #f8f9fa; padding: 15px; text-align: center;">
                            👥 View Admins
                        </div>
                    </a>
                </div>
                <div class="col-md-3 mb-2">
                    <a href="view_ngo.php" style="text-decoration: none;">
                        <div class="card" style="background: #f8f9fa; padding: 15px; text-align: center;">
                            🏢 View NGOs
                        </div>
                    </a>
                </div>
                <div class="col-md-3 mb-2">
                    <a href="report.php" style="text-decoration: none;">
                        <div class="card" style="background: #f8f9fa; padding: 15px; text-align: center;">
                            📊 Reports
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </div>

</body>
</html>