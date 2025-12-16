<?php
session_start();
if(!isset($_SESSION['name']) || $_SESSION['role'] != "admin"){
    header("Location: login.php");
    exit();
}

require_once "connection.php";

// Handle Approve/Reject/Delete Actions
if(isset($_GET['action']) && isset($_GET['id'])){
    $ngo_id = $_GET['id'];
    $action = $_GET['action'];
    
    if($action == 'approve'){
        $sql = "UPDATE NGO SET status = 'Approved' WHERE NGOID = ?";
        $message = "NGO approved successfully!";
    } elseif($action == 'reject'){
        $sql = "UPDATE NGO SET status = 'Rejected' WHERE NGOID = ?";
        $message = "NGO rejected.";
    } elseif($action == 'delete'){
        $sql = "DELETE FROM NGO WHERE NGOID = ?";
        $message = "NGO deleted.";
    }
    
    $params = array($ngo_id);
    $stmt = sqlsrv_query($conn, $sql, $params);
    
    if($stmt){
        $_SESSION['success'] = $message;
    } else {
        $_SESSION['error'] = "Failed to update NGO.";
    }
    
    header("Location: admin_manage_ngo.php");
    exit();
}

// Fetch NGOs with Pending status
$sql = "SELECT * FROM NGO WHERE status = 'Pending' ORDER BY CreatedAt DESC";
$stmt = sqlsrv_query($conn, $sql);
if($stmt === false) die(print_r(sqlsrv_errors(), true));

$pending_ngos = [];
while($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)){
    $pending_ngos[] = $row;
}

// Fetch ALL NGOs for the table view
$all_sql = "SELECT * FROM NGO ORDER BY CreatedAt DESC";
$all_stmt = sqlsrv_query($conn, $all_sql);
if($all_stmt === false) die(print_r(sqlsrv_errors(), true));

$all_ngos = [];
while($row = sqlsrv_fetch_array($all_stmt, SQLSRV_FETCH_ASSOC)){
    $all_ngos[] = $row;
}

// Count stats
$statsSql = "SELECT 
    SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status = 'Approved' THEN 1 ELSE 0 END) as approved,
    SUM(CASE WHEN status = 'Rejected' THEN 1 ELSE 0 END) as rejected,
    COUNT(*) as total
    FROM NGO";
$statsStmt = sqlsrv_query($conn, $statsSql);
$stats = sqlsrv_fetch_array($statsStmt, SQLSRV_FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin - NGO Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            display: flex;
            min-height: 100vh;
            background: #f8f9fa;
        }
        .sidebar {
            width: 250px;
            background: #343a40;
            padding: 20px;
            min-height: 100vh;
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
            padding: 40px;
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
            background: white;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            margin-bottom: 20px;
            border-left: 5px solid #ccc;
        }
        
        .stat-number {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #666;
            font-size: 14px;
        }
        
        .pending-card { border-left: 5px solid #ffc107; }
        .approved-card { border-left: 5px solid #28a745; }
        .rejected-card { border-left: 5px solid #dc3545; }
        .total-card { border-left: 5px solid #0d6efd; }
        
        .ngo-card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.08);
            border: 1px solid #eaeaea;
        }
        
        /* Button Styles */
        .btn-approve {
            background: #28a745;
            color: white;
            border: none;
            padding: 6px 15px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-reject {
            background: #dc3545;
            color: white;
            border: none;
            padding: 6px 15px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-delete {
            background: #6c757d;
            color: white;
            border: none;
            padding: 6px 15px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-approve:hover {
            background: #218838;
        }
        
        .btn-reject:hover {
            background: #c82333;
        }
        
        .btn-delete:hover {
            background: #5a6268;
        }
        
        .btn-group {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        /* Table Styles */
        .table-container {
            background: white;
            border-radius: 10px;
            padding: 0;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 30px;
        }
        
        .table-header {
            padding: 20px;
            background: #f8f9fa;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th {
            background: #f8f9fa;
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: #333;
            border-bottom: 2px solid #dee2e6;
        }
        
        td {
            padding: 15px;
            border-bottom: 1px solid #eee;
            vertical-align: top;
        }
        
        tr:hover {
            background-color: #f9f9f9;
        }
        
        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }
        
        .status-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-approved {
            background: #d4edda;
            color: #155724;
        }
        
        .status-rejected {
            background: #f8d7da;
            color: #721c24;
        }
        
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #666;
        }
        
        .empty-state-icon {
            font-size: 48px;
            margin-bottom: 20px;
        }
        
        .profile-header {
            background: linear-gradient(90deg, #343a40 0%, #495057 100%);
            color: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
        }
        
        .grid-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
    </style>
</head>
<body>

    <!-- SIDEBAR SAMA DENGAN ADMIN_PROFILE.PHP -->
    <div class="sidebar">
        <h4><i class="fas fa-user-shield me-2"></i>Admin Panel</h4>
        <hr style="border-color: #495057; margin: 15px 0;">
        <a href="admin_dashboard.php"><i class="fas fa-home me-2"></i>Dashboard</a>
        <a href="admin_profile.php"><i class="fas fa-user me-2"></i>Profile</a>
        <a href="view_admin.php"><i class="fas fa-users me-2"></i>View Admins</a>
        <a href="admin_manage_ngo.php" class="active"><i class="fas fa-handshake me-2"></i>Manage NGO</a>
        <a href="create_news.php"><i class="fas fa-newspaper me-2"></i>Create News</a>
        <a href="view_news.php"><i class="fas fa-list me-2"></i>View News</a>
        <a href="admin_opportunity.php"><i class="fas fa-briefcase me-2"></i>Opportunity</a>
        <a href="report.php"><i class="fas fa-chart-bar me-2"></i>Reports</a>
        <hr style="border-color: #495057; margin: 20px 0;">
        <a href="logout.php" class="text-danger"><i class="fas fa-sign-out-alt me-2"></i>Logout</a>
    </div>

<!-- MAIN CONTENT -->
<div class="content">
    <!-- Header -->
    <div class="profile-header">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h3 class="mb-1"><i class="fas fa-handshake me-2"></i>NGO Management</h3>
                <p class="mb-0">Manage all NGO registrations and approvals</p>
            </div>
            <div>
                <span class="badge bg-light text-dark fs-6 p-2">
                    <i class="fas fa-user-tag me-1"></i> Admin: <?php echo htmlspecialchars($_SESSION['name']); ?>
                </span>
            </div>
        </div>
    </div>

    <!-- Messages -->
    <?php if(isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i>
            <?php echo $_SESSION['success']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>
    
    <?php if(isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i>
            <?php echo $_SESSION['error']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <!-- STATISTICS -->
    <div class="grid-stats">
        <div class="stat-card pending-card">
            <div class="stat-number"><?php echo $stats['pending']; ?></div>
            <div class="stat-label">Pending Approval</div>
        </div>
        <div class="stat-card approved-card">
            <div class="stat-number"><?php echo $stats['approved']; ?></div>
            <div class="stat-label">Approved NGOs</div>
        </div>
        <div class="stat-card rejected-card">
            <div class="stat-number"><?php echo $stats['rejected']; ?></div>
            <div class="stat-label">Rejected NGOs</div>
        </div>
        <div class="stat-card total-card">
            <div class="stat-number"><?php echo $stats['total']; ?></div>
            <div class="stat-label">Total NGOs</div>
        </div>
    </div>

    <!-- PENDING NGOs LIST -->
    <div class="table-container">
        <div class="table-header">
            <h4 class="mb-0"><i class="fas fa-clock me-2"></i>Pending NGO Registrations</h4>
        </div>
        
        <div style="padding: 20px;">
            <?php if(count($pending_ngos) > 0): ?>
                <?php foreach($pending_ngos as $ngo): ?>
                <div class="ngo-card">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 20px;">
                        <div style="flex: 1; min-width: 300px;">
                            <h5 style="margin-top: 0; color: #1d3557;">
                                <i class="fas fa-building me-2"></i><?php echo htmlspecialchars($ngo['NGOName']); ?>
                            </h5>
                            <div style="color: #666; margin-bottom: 10px;">
                                <strong><i class="fas fa-envelope me-1"></i>Email:</strong> <?php echo htmlspecialchars($ngo['Email']); ?><br>
                                <strong><i class="fas fa-phone me-1"></i>Phone:</strong> <?php echo htmlspecialchars($ngo['Phone'] ?? 'N/A'); ?><br>
                                <strong><i class="fas fa-id-card me-1"></i>Registration No:</strong> <?php echo htmlspecialchars($ngo['RegistrationNo'] ?? 'N/A'); ?><br>
                                <strong><i class="fas fa-map-marker-alt me-1"></i>Address:</strong> <?php echo htmlspecialchars($ngo['Address'] ?? 'N/A'); ?><br>
                                <strong><i class="fas fa-calendar-alt me-1"></i>Registered:</strong> 
                                <?php 
                                if($ngo['CreatedAt'] instanceof DateTime){
                                    echo $ngo['CreatedAt']->format('d/m/Y H:i');
                                } else {
                                    echo date('d/m/Y H:i', strtotime($ngo['CreatedAt']));
                                }
                                ?>
                            </div>
                        </div>
                        
                        <div class="btn-group">
                            <a href="admin_manage_ngo.php?action=approve&id=<?php echo $ngo['NGOID']; ?>" 
                               class="btn-approve"
                               onclick="return confirm('Approve <?php echo htmlspecialchars(addslashes($ngo['NGOName'])); ?>?')">
                                <i class="fas fa-check me-1"></i>Approve
                            </a>
                            <a href="admin_manage_ngo.php?action=reject&id=<?php echo $ngo['NGOID']; ?>" 
                               class="btn-reject"
                               onclick="return confirm('Reject <?php echo htmlspecialchars(addslashes($ngo['NGOName'])); ?>?')">
                                <i class="fas fa-times me-1"></i>Reject
                            </a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-state-icon"><i class="fas fa-check-circle text-success"></i></div>
                    <h4>No Pending Registrations</h4>
                    <p>All NGO registrations have been processed</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ALL NGOs TABLE -->
    <div class="table-container">
        <div class="table-header">
            <h4 class="mb-0"><i class="fas fa-list me-2"></i>All NGOs</h4>
            <span style="color: #666;">Total: <?php echo count($all_ngos); ?> NGOs</span>
        </div>
        
        <div style="overflow-x: auto;">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>NGO Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Status</th>
                        <th>Registration Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(count($all_ngos) > 0): ?>
                        <?php foreach($all_ngos as $ngo): ?>
                        <tr>
                            <td><strong>#<?php echo htmlspecialchars($ngo['NGOID']); ?></strong></td>
                            <td>
                                <strong><i class="fas fa-building me-1"></i><?php echo htmlspecialchars($ngo['NGOName']); ?></strong><br>
                                <small style="color: #666;">
                                    <i class="fas fa-id-card me-1"></i>Reg: <?php echo htmlspecialchars($ngo['RegistrationNo'] ?? 'N/A'); ?>
                                </small>
                            </td>
                            <td><i class="fas fa-envelope me-1 text-muted"></i><?php echo htmlspecialchars($ngo['Email']); ?></td>
                            <td><i class="fas fa-phone me-1 text-muted"></i><?php echo htmlspecialchars($ngo['Phone'] ?? 'N/A'); ?></td>
                            <td>
                                <?php 
                                $status_class = '';
                                if($ngo['status'] == 'Pending') $status_class = 'status-pending';
                                if($ngo['status'] == 'Approved') $status_class = 'status-approved';
                                if($ngo['status'] == 'Rejected') $status_class = 'status-rejected';
                                ?>
                                <span class="status-badge <?php echo $status_class; ?>">
                                    <?php if($ngo['status'] == 'Pending'): ?>
                                        <i class="fas fa-clock me-1"></i>
                                    <?php elseif($ngo['status'] == 'Approved'): ?>
                                        <i class="fas fa-check me-1"></i>
                                    <?php elseif($ngo['status'] == 'Rejected'): ?>
                                        <i class="fas fa-times me-1"></i>
                                    <?php endif; ?>
                                    <?php echo htmlspecialchars($ngo['status']); ?>
                                </span>
                            </td>
                            <td>
                                <i class="fas fa-calendar-alt me-1 text-muted"></i>
                                <?php 
                                if($ngo['CreatedAt'] instanceof DateTime){
                                    echo $ngo['CreatedAt']->format('d/m/Y');
                                } else {
                                    echo date('d/m/Y', strtotime($ngo['CreatedAt']));
                                }
                                ?>
                            </td>
                            <td>
                                <div class="btn-group">
                                    <?php if($ngo['status'] == 'Pending'): ?>
                                        <a href="admin_manage_ngo.php?action=approve&id=<?php echo $ngo['NGOID']; ?>" 
                                           class="btn-approve"
                                           onclick="return confirm('Approve this NGO?')">
                                            <i class="fas fa-check me-1"></i>Approve
                                        </a>
                                        <a href="admin_manage_ngo.php?action=reject&id=<?php echo $ngo['NGOID']; ?>" 
                                           class="btn-reject"
                                           onclick="return confirm('Reject this NGO?')">
                                            <i class="fas fa-times me-1"></i>Reject
                                        </a>
                                    <?php elseif($ngo['status'] == 'Approved'): ?>
                                        <a href="admin_manage_ngo.php?action=reject&id=<?php echo $ngo['NGOID']; ?>" 
                                           class="btn-reject"
                                           onclick="return confirm('Reject this approved NGO?')">
                                            <i class="fas fa-times me-1"></i>Reject
                                        </a>
                                    <?php elseif($ngo['status'] == 'Rejected'): ?>
                                        <a href="admin_manage_ngo.php?action=approve&id=<?php echo $ngo['NGOID']; ?>" 
                                           class="btn-approve"
                                           onclick="return confirm('Approve this rejected NGO?')">
                                            <i class="fas fa-check me-1"></i>Approve
                                        </a>
                                    <?php endif; ?>
                                    
                                    <a href="admin_manage_ngo.php?action=delete&id=<?php echo $ngo['NGOID']; ?>" 
                                       class="btn-delete"
                                       onclick="return confirm('⚠️ Delete this NGO permanently? This action cannot be undone!')">
                                        <i class="fas fa-trash me-1"></i>Delete
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="empty-state">
                                <div class="empty-state-icon"><i class="fas fa-building text-muted"></i></div>
                                <h4>No NGOs Found</h4>
                                <p>No NGOs have registered yet</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
    // Auto-dismiss alert after 5 seconds
    setTimeout(function() {
        var alerts = document.querySelectorAll('.alert');
        alerts.forEach(function(alert) {
            var bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        });
    }, 5000);
</script>

</body>
</html>