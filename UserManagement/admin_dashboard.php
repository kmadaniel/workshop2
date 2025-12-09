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

// Get super admins count
$sqlSuperAdmins = "SELECT COUNT(*) as total FROM dbo.Admin WHERE Role = 'SuperAdmin'";
$stmtSuperAdmins = sqlsrv_query($conn, $sqlSuperAdmins);
$rowSuperAdmins = sqlsrv_fetch_array($stmtSuperAdmins, SQLSRV_FETCH_ASSOC);
$totalSuperAdmins = $rowSuperAdmins['total'] ?? 0;
sqlsrv_free_stmt($stmtSuperAdmins);

// Get NGOs count - CHECK IF TABLE EXISTS FIRST
$totalNGOs = 0;
$checkNGO = "SELECT COUNT(*) as table_exists FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = 'NGO' OR TABLE_NAME = 'NGOs' OR TABLE_NAME = 'Organization'";
$stmtCheckNGO = sqlsrv_query($conn, $checkNGO);
if($stmtCheckNGO !== false) {
    $rowCheck = sqlsrv_fetch_array($stmtCheckNGO, SQLSRV_FETCH_ASSOC);
    if($rowCheck['table_exists'] > 0) {
        // Try different possible table names
        $tables = ['dbo.NGO', 'dbo.NGOs', 'dbo.Organization', 'dbo.Organizations'];
        foreach($tables as $table) {
            $sqlNGOs = "SELECT COUNT(*) as total FROM $table";
            $stmtNGOs = sqlsrv_query($conn, $sqlNGOs);
            if($stmtNGOs !== false) {
                $rowNGOs = sqlsrv_fetch_array($stmtNGOs, SQLSRV_FETCH_ASSOC);
                $totalNGOs = $rowNGOs['total'] ?? 0;
                sqlsrv_free_stmt($stmtNGOs);
                break;
            }
        }
    }
}
sqlsrv_free_stmt($stmtCheckNGO);

// Get reports count - CHECK IF TABLE EXISTS FIRST
$totalReports = 0;
$checkReports = "SELECT COUNT(*) as table_exists FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = 'Reports' OR TABLE_NAME = 'Report'";
$stmtCheckReports = sqlsrv_query($conn, $checkReports);
if($stmtCheckReports !== false) {
    $rowCheckR = sqlsrv_fetch_array($stmtCheckReports, SQLSRV_FETCH_ASSOC);
    if($rowCheckR['table_exists'] > 0) {
        $sqlReports = "SELECT COUNT(*) as total FROM dbo.Reports";
        $stmtReports = sqlsrv_query($conn, $sqlReports);
        if($stmtReports !== false) {
            $rowReports = sqlsrv_fetch_array($stmtReports, SQLSRV_FETCH_ASSOC);
            $totalReports = $rowReports['total'] ?? 0;
            sqlsrv_free_stmt($stmtReports);
        }
    }
}
sqlsrv_free_stmt($stmtCheckReports);

// Get current admin info
$currentAdminName = $_SESSION['name'];
$sqlCurrentAdmin = "SELECT * FROM dbo.Admin WHERE FullName = ?";
$params = array($currentAdminName);
$stmtCurrent = sqlsrv_query($conn, $sqlCurrentAdmin, $params);
$currentAdmin = null;
if($stmtCurrent !== false) {
    $currentAdmin = sqlsrv_fetch_array($stmtCurrent, SQLSRV_FETCH_ASSOC);
    sqlsrv_free_stmt($stmtCurrent);
}

sqlsrv_close($conn);

// Default activities if no log table
$activities = array();
$activities[] = array(
    'icon' => 'fas fa-user-plus text-success',
    'text' => 'You logged in to the system',
    'time' => date('Y-m-d H:i:s')
);
$activities[] = array(
    'icon' => 'fas fa-users text-primary', 
    'text' => 'Viewing admin dashboard',
    'time' => date('Y-m-d H:i:s')
);
if($currentAdmin) {
    $activities[] = array(
        'icon' => 'fas fa-id-card text-info',
        'text' => "Logged in as " . $currentAdmin['Role'],
        'time' => date('Y-m-d H:i:s')
    );
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            display: flex;
            background-color: #f8f9fa;
        }
        .sidebar {
            width: 250px;
            height: 100vh;
            background: #343a40;
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
            font-size: 15px;
            transition: all 0.3s;
        }
        .sidebar a:hover {
            background: #495057;
            color: white;
        }
        .sidebar a.active {
            background: #0d6efd;
            color: white;
        }
        .sidebar h4 {
            color: white;
            padding-bottom: 15px;
            border-bottom: 2px solid #495057;
            margin-bottom: 20px;
        }
        .content {
            flex-grow: 1;
            padding: 30px;
            margin-left: 250px;
            width: calc(100% - 250px);
        }
        .card {
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            border: none;
            margin-bottom: 20px;
        }
        .stat-card {
            transition: transform 0.3s;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .bg-primary { background: linear-gradient(45deg, #0d6efd, #0dcaf0) !important; }
        .bg-success { background: linear-gradient(45deg, #198754, #20c997) !important; }
        .bg-info { background: linear-gradient(45deg, #0dcaf0, #6f42c1) !important; }
        .bg-warning { background: linear-gradient(45deg, #ffc107, #fd7e14) !important; }
        .activity-item {
            border-left: 4px solid #0d6efd;
            padding-left: 15px;
            margin-bottom: 15px;
        }
        .refresh-btn {
            background: rgba(255,255,255,0.2);
            border: none;
            color: white;
            border-radius: 5px;
            padding: 5px 10px;
            font-size: 12px;
            cursor: pointer;
        }
        .refresh-btn:hover {
            background: rgba(255,255,255,0.3);
        }
    </style>
</head>
<body>

    <!-- Sidebar SAMA TEPAT seperti view_admin.php -->
    <div class="sidebar">
        <h4><i class="fas fa-user-shield me-2"></i>Admin Panel</h4>
        <hr style="border-color: #495057; margin: 15px 0;">
        <a href="admin_dashboard.php" class="active"><i class="fas fa-home me-2"></i>Dashboard</a>
        <a href="admin_profile.php"><i class="fas fa-user me-2"></i>Profile</a>
        <a href="add_admin.php"><i class="fas fa-plus-circle me-2"></i>Add Admin</a>
        <a href="view_admin.php"><i class="fas fa-users me-2"></i>View Admins</a>
        <a href="view_ngo.php"><i class="fas fa-building me-2"></i>View NGO Register</a>
        <a href="report.php"><i class="fas fa-chart-bar me-2"></i>Reports</a>
        <hr style="border-color: #495057; margin: 20px 0;">
        <a href="logout.php" class="text-danger"><i class="fas fa-sign-out-alt me-2"></i>Logout</a>
    </div>

    <div class="content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="mb-1">Welcome, Admin: <?php echo $_SESSION['name']; ?></h2>
                <p class="text-muted">This is your dashboard overview.</p>
            </div>
            <div>
                <span class="badge bg-info fs-6 p-2">Role: <?php echo $_SESSION['role']; ?></span>
            </div>
        </div>
        
        <!-- Dashboard Cards - REAL DATA -->
        <div class="row">
            <div class="col-md-3">
                <div class="card stat-card bg-primary text-white">
                    <div class="card-body text-center">
                        <div class="d-flex justify-content-between align-items-start">
                            <div></div>
                            <button class="refresh-btn" onclick="window.location.reload()" title="Refresh">
                                <i class="fas fa-sync-alt"></i>
                            </button>
                        </div>
                        <i class="fas fa-users fa-3x mb-3"></i>
                        <h5>Total Admins</h5>
                        <h3><?php echo $totalAdmins; ?></h3>
                        <p class="small mb-0 opacity-75">Current: <?php echo $totalAdmins; ?> administrators</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card bg-success text-white">
                    <div class="card-body text-center">
                        <div class="d-flex justify-content-between align-items-start">
                            <div></div>
                            <button class="refresh-btn" onclick="window.location.reload()" title="Refresh">
                                <i class="fas fa-sync-alt"></i>
                            </button>
                        </div>
                        <i class="fas fa-user-shield fa-3x mb-3"></i>
                        <h5>Super Admins</h5>
                        <h3><?php echo $totalSuperAdmins; ?></h3>
                        <p class="small mb-0 opacity-75">With full system privileges</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card bg-info text-white">
                    <div class="card-body text-center">
                        <div class="d-flex justify-content-between align-items-start">
                            <div></div>
                            <button class="refresh-btn" onclick="window.location.reload()" title="Refresh">
                                <i class="fas fa-sync-alt"></i>
                            </button>
                        </div>
                        <i class="fas fa-building fa-3x mb-3"></i>
                        <h5>NGOs</h5>
                        <h3><?php echo $totalNGOs; ?></h3>
                        <p class="small mb-0 opacity-75">Registered organizations</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card bg-warning text-white">
                    <div class="card-body text-center">
                        <div class="d-flex justify-content-between align-items-start">
                            <div></div>
                            <button class="refresh-btn" onclick="window.location.reload()" title="Refresh">
                                <i class="fas fa-sync-alt"></i>
                            </button>
                        </div>
                        <i class="fas fa-chart-line fa-3x mb-3"></i>
                        <h5>Reports</h5>
                        <h3><?php echo $totalReports; ?></h3>
                        <p class="small mb-0 opacity-75">Generated system reports</p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Recent Activity -->
        <div class="card mt-4">
            <div class="card-header bg-dark text-white">
                <h5 class="mb-0"><i class="fas fa-history me-2"></i>Recent Activity</h5>
            </div>
            <div class="card-body">
                <?php if(!empty($activities)): ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach($activities as $activity): ?>
                        <li class="list-group-item">
                            <div class="activity-item">
                                <i class="<?php echo $activity['icon']; ?> me-2"></i>
                                <?php echo $activity['text']; ?>
                                <div class="text-muted small mt-1">
                                    <i class="far fa-clock me-1"></i>
                                    <?php echo $activity['time']; ?>
                                </div>
                            </div>
                        </li>
                        <?php endforeach; ?>
                        <li class="list-group-item">
                            <div class="activity-item">
                                <i class="fas fa-database text-primary me-2"></i>
                                Dashboard data refreshed
                                <div class="text-muted small mt-1">
                                    <i class="far fa-clock me-1"></i>
                                    <?php echo date('Y-m-d H:i:s'); ?>
                                </div>
                            </div>
                        </li>
                    </ul>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="fas fa-clipboard-list fa-3x text-muted mb-3"></i>
                        <p class="text-muted">No recent activities found</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Quick Stats -->
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-chart-pie me-2"></i>System Statistics</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6>Admin Distribution</h6>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between">
                                <span>Super Admins</span>
                                <span><?php echo $totalSuperAdmins; ?> (<?php echo ($totalAdmins > 0) ? round(($totalSuperAdmins/$totalAdmins)*100, 1) : 0; ?>%)</span>
                            </div>
                            <div class="progress" style="height: 10px;">
                                <div class="progress-bar bg-success" style="width: <?php echo ($totalAdmins > 0) ? ($totalSuperAdmins/$totalAdmins)*100 : 0; ?>%"></div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between">
                                <span>Regular Admins</span>
                                <span><?php echo $totalAdmins - $totalSuperAdmins; ?> (<?php echo ($totalAdmins > 0) ? round((($totalAdmins - $totalSuperAdmins)/$totalAdmins)*100, 1) : 0; ?>%)</span>
                            </div>
                            <div class="progress" style="height: 10px;">
                                <div class="progress-bar bg-primary" style="width: <?php echo ($totalAdmins > 0) ? (($totalAdmins - $totalSuperAdmins)/$totalAdmins)*100 : 0; ?>%"></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <h6>Database Status</h6>
                        <div class="mb-3">
                            <i class="fas fa-database text-primary me-2"></i>
                            <span>Admin Table:</span>
                            <span class="badge bg-success ms-2"><?php echo $totalAdmins; ?> records</span>
                        </div>
                        <?php if($totalNGOs > 0): ?>
                        <div class="mb-3">
                            <i class="fas fa-database text-info me-2"></i>
                            <span>NGO Table:</span>
                            <span class="badge bg-info ms-2"><?php echo $totalNGOs; ?> records</span>
                        </div>
                        <?php endif; ?>
                        <?php if($totalReports > 0): ?>
                        <div class="mb-3">
                            <i class="fas fa-database text-warning me-2"></i>
                            <span>Reports Table:</span>
                            <span class="badge bg-warning ms-2"><?php echo $totalReports; ?> records</span>
                        </div>
                        <?php endif; ?>
                        <div>
                            <i class="fas fa-server text-secondary me-2"></i>
                            <span>Last Updated:</span>
                            <span class="text-muted"><?php echo date('Y-m-d H:i:s'); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Check URL parameters for delete success/error
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const deleteStatus = urlParams.get('delete');
            
            if(deleteStatus === 'success') {
                showAlert('Admin deleted successfully!', 'success');
                // Remove parameter from URL without reloading
                window.history.replaceState({}, document.title, window.location.pathname);
            } else if(deleteStatus === 'error') {
                showAlert('Failed to delete admin.', 'danger');
                window.history.replaceState({}, document.title, window.location.pathname);
            }
        });
        
        function showAlert(message, type) {
            // Create alert element
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
            alertDiv.style.cssText = 'top: 20px; right: 20px; z-index: 1050; max-width: 300px;';
            alertDiv.innerHTML = `
                <strong>${type === 'success' ? 'Success!' : 'Error!'}</strong><br>
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
        
        // Auto refresh data every 60 seconds
        setInterval(() => {
            // Just update the time display or optionally reload
            console.log('Dashboard data auto-refresh available');
        }, 60000);
    </script>
</body>
</html>