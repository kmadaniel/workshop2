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
        $success_msg = $message;
    } else {
        $error_msg = "Failed to update opportunity.";
    }
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
    <style>
        body {
            background: #f8f9fa;
            padding: 20px;
        }
        
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            margin-bottom: 20px;
        }
        
        .stat-number {
            font-size: 32px;
            font-weight: 700;
        }
        
        .pending-card { border-left: 5px solid #ffc107; }
        .open-card { border-left: 5px solid #28a745; }
        .rejected-card { border-left: 5px solid #dc3545; }
        .total-card { border-left: 5px solid #007bff; }
        
        .status-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .badge-pending { background: #fff3cd; color: #856404; }
        .badge-open { background: #d4edda; color: #155724; }
        .badge-rejected { background: #f8d7da; color: #721c24; }
        
        .btn-approve { background: #28a745; color: white; }
        .btn-reject { background: #dc3545; color: white; }
        .btn-delete { background: #6c757d; color: white; }
        
        .btn-group-sm .btn {
            padding: 3px 8px;
            font-size: 12px;
        }
    </style>
</head>
<body>

<div class="container-fluid">
    <!-- HEADER -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2>Manage Opportunities</h2>
            <p class="text-muted">Approve or reject opportunities posted by NGOs</p>
        </div>
        <a href="admin_dashboard.php" class="btn btn-outline-secondary">
            ← Back to Dashboard
        </a>
    </div>

    <!-- MESSAGES -->
    <?php if(isset($success_msg)): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?php echo $success_msg; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if(isset($error_msg)): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?php echo $error_msg; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- STATISTICS -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="stat-card total-card">
                <div class="stat-number"><?php echo $total; ?></div>
                <div class="text-muted">Total Opportunities</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card pending-card">
                <div class="stat-number"><?php echo $pending; ?></div>
                <div class="text-muted">Pending Approval</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card open-card">
                <div class="stat-number"><?php echo $open; ?></div>
                <div class="text-muted">Open Opportunities</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card rejected-card">
                <div class="stat-number"><?php echo $rejected; ?></div>
                <div class="text-muted">Rejected</div>
            </div>
        </div>
    </div>

    <!-- OPPORTUNITIES TABLE -->
    <div class="card">
        <div class="card-header bg-white">
            <h5 class="mb-0">All Opportunities</h5>
        </div>
        <div class="card-body p-0">
            <?php if(count($rows) > 0): ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
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
                            <td><?php echo $i++; ?></td>
                            <td>
                                <strong><?php echo htmlspecialchars($row['title']); ?></strong><br>
                                <small class="text-muted"><?php echo substr(htmlspecialchars($row['description']),0,80); ?>...</small>
                            </td>
                            <td><?php echo htmlspecialchars($row['NGOName'] ?? 'Unknown NGO'); ?></td>
                            <td><?php echo htmlspecialchars($row['location']); ?></td>
                            <td><?php echo $event_date; ?></td>
                            <td>
                                <div><?php echo $total_slots; ?> total</div>
                                <small class="text-muted">
                                    <?php echo $applied_count; ?> applied
                                </small>
                            </td>
                            <td>
                                <?php if($row['status'] === 'Pending'): ?>
                                    <span class="status-badge badge-pending">Pending</span>
                                <?php elseif($row['status'] === 'Open'): ?>
                                    <span class="status-badge badge-open">Open</span>
                                <?php elseif($row['status'] === 'Rejected'): ?>
                                    <span class="status-badge badge-rejected">Rejected</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary"><?php echo $row['status']; ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo $created_at; ?></td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <?php if($row['status'] === 'Pending'): ?>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="opportunity_id" value="<?php echo $row['opportunity_id']; ?>">
                                            <input type="hidden" name="action" value="approve">
                                            <button type="submit" class="btn btn-approve" onclick="return confirm('Approve this opportunity?')">
                                                Approve
                                            </button>
                                        </form>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="opportunity_id" value="<?php echo $row['opportunity_id']; ?>">
                                            <input type="hidden" name="action" value="reject">
                                            <button type="submit" class="btn btn-reject" onclick="return confirm('Reject this opportunity?')">
                                                Reject
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="opportunity_id" value="<?php echo $row['opportunity_id']; ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <button type="submit" class="btn btn-delete" 
                                                onclick="return confirm('Are you sure you want to delete this opportunity? This will also remove all volunteer applications.')">
                                            Delete
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
                <div class="p-5 text-center">
                    <h5>No opportunities found</h5>
                    <p class="text-muted">No NGOs have posted opportunities yet.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Auto-hide alerts after 5 seconds
    setTimeout(function() {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        });
    }, 5000);
</script>
</body>
</html>

<?php
sqlsrv_close($conn);
?>