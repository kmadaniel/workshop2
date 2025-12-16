<?php
session_start();

// --- SECURITY CHECK ---
if(!isset($_SESSION['name']) || $_SESSION['role'] != "admin"){
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

// ================= HANDLE ACTIONS =================
if(isset($_POST['action']) && isset($_POST['opportunity_id'])) {
    $opportunity_id = $_POST['opportunity_id'];
    $action = $_POST['action'];
    
    if($action == 'approve') {
        $updateSql = "UPDATE opportunity SET status = 'Open' WHERE opportunity_id = ?";
        $message = "Opportunity approved successfully!";
    } elseif($action == 'reject') {
        $updateSql = "UPDATE opportunity SET status = 'Rejected' WHERE opportunity_id = ?";
        $message = "Opportunity rejected.";
    } elseif($action == 'delete') {
        $updateSql = "DELETE FROM opportunity WHERE opportunity_id = ?";
        $message = "Opportunity deleted.";
    }
    
    $params = array($opportunity_id);
    $updateStmt = sqlsrv_query($conn, $updateSql, $params);
    
    if($updateStmt) {
        $_SESSION['success'] = $message;
    } else {
        $_SESSION['error'] = "Failed to update opportunity.";
    }
    
    header("Location: admin_opportunity.php");
    exit();
}

// ================= FETCH ALL OPPORTUNITIES =================
$sql = "
SELECT 
    o.*,
    n.NGOName,
    -- Count berapa banyak volunteers dah apply
    (SELECT COUNT(*) FROM opportunity_volunteer ov 
     WHERE ov.opportunity_id = o.opportunity_id) as applied_count
FROM opportunity o
LEFT JOIN NGO n ON o.ngo_id = n.NGOID
ORDER BY o.created_at DESC";

$stmt = sqlsrv_query($conn, $sql);

if($stmt === false){
    die(print_r(sqlsrv_errors(), true));
}

// Count stats
$pending = 0;
$open = 0;
$rejected = 0;
$total = 0;
$rows = [];

while($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)){
    $rows[] = $row;
    $total++;
    
    if($row['status'] === 'Pending') $pending++;
    if($row['status'] === 'Open') $open++;
    if($row['status'] === 'Rejected') $rejected++;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin - Manage Opportunities</title>
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
        
        .total-card { border-left: 5px solid #0d6efd; }
        .pending-card { border-left: 5px solid #ffc107; }
        .open-card { border-left: 5px solid #28a745; }
        .rejected-card { border-left: 5px solid #dc3545; }
        
        .status-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }
        
        .badge-pending { 
            background: #fff3cd; 
            color: #856404; 
        }
        .badge-open { 
            background: #d4edda; 
            color: #155724; 
        }
        .badge-rejected { 
            background: #f8d7da; 
            color: #721c24; 
        }
        
        .btn-approve { 
            background: #28a745; 
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 4px;
            text-decoration: none;
            display: inline-block;
        }
        .btn-reject { 
            background: #dc3545; 
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 4px;
            text-decoration: none;
            display: inline-block;
        }
        .btn-delete { 
            background: #6c757d; 
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 4px;
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
        
        .btn-group-sm {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
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
        }
        
        .table th {
            background: #f8f9fa;
            padding: 15px;
            font-weight: 600;
            color: #333;
            border-bottom: 2px solid #dee2e6;
        }
        
        .table td {
            padding: 15px;
            border-bottom: 1px solid #eee;
            vertical-align: middle;
        }
        
        .table tr:hover {
            background-color: #f9f9f9;
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
        <a href="admin_manage_ngo.php"><i class="fas fa-handshake me-2"></i>Manage NGO</a>
        <a href="create_news.php"><i class="fas fa-newspaper me-2"></i>Create News</a>
        <a href="view_news.php"><i class="fas fa-list me-2"></i>View News</a>
        <a href="admin_opportunity.php" class="active"><i class="fas fa-briefcase me-2"></i>Opportunity</a>
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
                    <h3 class="mb-1"><i class="fas fa-briefcase me-2"></i>Manage Opportunities</h3>
                    <p class="mb-0">Approve or reject opportunities posted by NGOs</p>
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
            <div class="stat-card total-card">
                <div class="stat-number"><?php echo $total; ?></div>
                <div class="stat-label">Total Opportunities</div>
            </div>
            <div class="stat-card pending-card">
                <div class="stat-number"><?php echo $pending; ?></div>
                <div class="stat-label">Pending Approval</div>
            </div>
            <div class="stat-card open-card">
                <div class="stat-number"><?php echo $open; ?></div>
                <div class="stat-label">Open Opportunities</div>
            </div>
            <div class="stat-card rejected-card">
                <div class="stat-number"><?php echo $rejected; ?></div>
                <div class="stat-label">Rejected</div>
            </div>
        </div>

        <!-- OPPORTUNITIES TABLE -->
        <div class="table-container">
            <div class="table-header">
                <h5 class="mb-0"><i class="fas fa-list me-2"></i>All Opportunities</h5>
            </div>
            
            <div style="overflow-x: auto;">
                <?php if(count($rows) > 0): ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Title</th>
                            <th>NGO</th>
                            <th>Location</th>
                            <th>Date</th>
                            <th>Slots</th>
                            <th>Status</th>
                            <th>Posted On</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php $i = 1; foreach($rows as $row): 
                        // Format dates
                        $event_date = $row['event_date'] instanceof DateTime ? 
                            $row['event_date']->format('d/m/Y') : 
                            date('d/m/Y', strtotime($row['event_date']));
                        $created_at = $row['created_at'] instanceof DateTime ? 
                            $row['created_at']->format('d/m/Y H:i') : 
                            date('d/m/Y H:i', strtotime($row['created_at']));
                        
                        // Calculate available slots
                        $total_slots = $row['slots'];
                        $applied_count = $row['applied_count'];
                        $available_slots = $total_slots - $applied_count;
                    ?>
                        <tr>
                            <td><strong>#<?php echo $i++; ?></strong></td>
                            <td>
                                <strong><i class="fas fa-briefcase me-1"></i><?php echo htmlspecialchars($row['title']); ?></strong><br>
                                <small class="text-muted"><?php echo substr(htmlspecialchars($row['description']),0,80); ?>...</small>
                            </td>
                            <td><i class="fas fa-building me-1"></i><?php echo htmlspecialchars($row['NGOName'] ?? 'Unknown NGO'); ?></td>
                            <td><i class="fas fa-map-marker-alt me-1"></i><?php echo htmlspecialchars($row['location']); ?></td>
                            <td><i class="fas fa-calendar-alt me-1"></i><?php echo $event_date; ?></td>
                            <td>
                                <div><strong><?php echo $total_slots; ?></strong> total</div>
                                <small class="text-muted">
                                    <i class="fas fa-users me-1"></i><?php echo $applied_count; ?> applied
                                </small>
                            </td>
                            <td>
                                <?php if($row['status'] === 'Pending'): ?>
                                    <span class="status-badge badge-pending">
                                        <i class="fas fa-clock me-1"></i>Pending
                                    </span>
                                <?php elseif($row['status'] === 'Open'): ?>
                                    <span class="status-badge badge-open">
                                        <i class="fas fa-check me-1"></i>Open
                                    </span>
                                <?php elseif($row['status'] === 'Rejected'): ?>
                                    <span class="status-badge badge-rejected">
                                        <i class="fas fa-times me-1"></i>Rejected
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-secondary"><?php echo $row['status']; ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <i class="fas fa-calendar me-1"></i><?php echo $created_at; ?>
                            </td>
                            <td>
                                <div class="btn-group-sm">
                                    <?php if($row['status'] === 'Pending'): ?>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="opportunity_id" value="<?php echo $row['opportunity_id']; ?>">
                                            <input type="hidden" name="action" value="approve">
                                            <button type="submit" class="btn-approve" 
                                                    onclick="return confirm('Approve this opportunity?')">
                                                <i class="fas fa-check me-1"></i>Approve
                                            </button>
                                        </form>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="opportunity_id" value="<?php echo $row['opportunity_id']; ?>">
                                            <input type="hidden" name="action" value="reject">
                                            <button type="submit" class="btn-reject" 
                                                    onclick="return confirm('Reject this opportunity?')">
                                                <i class="fas fa-times me-1"></i>Reject
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="opportunity_id" value="<?php echo $row['opportunity_id']; ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <button type="submit" class="btn-delete" 
                                                onclick="return confirm('Are you sure you want to delete this opportunity? This will also remove all volunteer applications.')">
                                            <i class="fas fa-trash me-1"></i>Delete
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-state-icon"><i class="fas fa-briefcase text-muted"></i></div>
                        <h4>No opportunities found</h4>
                        <p>No NGOs have posted opportunities yet.</p>
                    </div>
                <?php endif; ?>
            </div>
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

<?php
sqlsrv_close($conn);
?>