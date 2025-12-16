<?php
session_start();
if(!isset($_SESSION['name']) || $_SESSION['role'] != "admin"){
    header("Location: login.php");
    exit();
}

// Database connection
$serverName = "localhost";
$connectionInfo = array(
    "Database" => "UserManagement",
    "Uid" => "yanadb",
    "PWD" => "yana123",
    "CharacterSet" => "UTF-8"
);

$conn = sqlsrv_connect($serverName, $connectionInfo);
if($conn === false) die(print_r(sqlsrv_errors(), true));

// ================= HANDLE DELETE =================
if(isset($_GET['delete_id'])){
    $delete_id = $_GET['delete_id'];
    
    // CONFIRMATION: Check if user confirmed
    if(isset($_GET['confirm']) && $_GET['confirm'] == 'yes'){
        // Delete NGO from database
        $delete_sql = "DELETE FROM NGO WHERE NGOID = ?";
        $params = array($delete_id);
        $delete_stmt = sqlsrv_query($conn, $delete_sql, $params);
        
        if($delete_stmt){
            $success_msg = "NGO deleted successfully!";
        } else {
            $error_msg = "Failed to delete NGO.";
        }
        
        // Redirect to remove URL parameters
        header("Location: view_ngo.php?msg=" . ($success_msg ? "deleted" : "error"));
        exit();
    } else {
        // Show confirmation modal
        $confirm_delete_id = $delete_id;
    }
}

// ================= FETCH NGOs =================
$sql = "SELECT NGOID, NGOName, email, phone, address, RegistrationNo FROM NGO ORDER BY NGOName";
$stmt = sqlsrv_query($conn, $sql);
if($stmt === false) die(print_r(sqlsrv_errors(), true));

$ngos = [];
while($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)){
    $ngos[] = $row;
}

// Check for success message
if(isset($_GET['msg'])){
    if($_GET['msg'] == 'deleted'){
        $success_msg = "NGO deleted successfully!";
    } elseif($_GET['msg'] == 'error'){
        $error_msg = "Failed to delete NGO.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin - View NGOs</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        /* Sidebar */
        .sidebar {
            width: 250px;
            height: 100vh;
            background: #1d3557;
            padding: 20px;
            position: fixed;
            left: 0;
            top: 0;
        }
        
        .sidebar h4 {
            color: white;
            margin-bottom: 30px;
        }
        
        .sidebar a {
            display: block;
            padding: 10px 15px;
            color: white;
            text-decoration: none;
            margin: 5px 0;
            border-radius: 5px;
        }
        
        .sidebar a:hover {
            background: #457b9d;
        }
        
        .sidebar a.active {
            background: #457b9d;
            border-left: 4px solid white;
        }
        
        /* Content */
        .content {
            margin-left: 250px;
            padding: 30px;
        }
        
        /* Header */
        .header-section {
            background: white;
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .admin-info {
            background: #e8f4fd;
            padding: 15px;
            border-radius: 8px;
            margin-top: 15px;
        }
        
        /* Table */
        .table-container {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .table-header {
            background: #f8f9fa;
            padding: 20px;
            border-bottom: 1px solid #dee2e6;
        }
        
        th {
            background: #f8f9fa;
            font-weight: 600;
            color: #495057;
            border-bottom: 2px solid #dee2e6;
        }
        
        .actions-column {
            text-align: center;
        }
        
        .btn-action {
            padding: 5px 10px;
            font-size: 12px;
            margin: 2px;
        }
        
        /* Confirmation Modal */
        .modal-confirm {
            display: none;
            position: fixed;
            z-index: 9999;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        
        .modal-content {
            background-color: white;
            margin: 15% auto;
            padding: 20px;
            border-radius: 10px;
            width: 400px;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
        }
        
        .modal-buttons {
            margin-top: 20px;
            display: flex;
            justify-content: center;
            gap: 10px;
        }
        
        .btn-yes {
            background: #dc3545;
            color: white;
            padding: 8px 25px;
        }
        
        .btn-no {
            background: #6c757d;
            color: white;
            padding: 8px 25px;
        }
    </style>
</head>
<body>

<!-- ================= CONFIRMATION MODAL ================= -->
<div id="confirmModal" class="modal-confirm" style="<?php echo isset($confirm_delete_id) ? 'display: block;' : 'display: none;'; ?>">
    <div class="modal-content">
        <h4>⚠️ Confirm Delete</h4>
        <p>Are you sure you want to delete this NGO?</p>
        <p><strong>This action cannot be undone!</strong></p>
        
        <div class="modal-buttons">
            <?php if(isset($confirm_delete_id)): ?>
                <a href="view_ngo.php?delete_id=<?php echo $confirm_delete_id; ?>&confirm=yes" 
                   class="btn btn-yes">
                    YES, DELETE
                </a>
                <a href="view_ngo.php" class="btn btn-no">NO, CANCEL</a>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ================= SIDEBAR ================= -->
<div class="sidebar">
    <h4>Admin Panel</h4>
    
    <a href="admin_dashboard.php">📊 Dashboard</a>
    <a href="admin_opportunity.php">📅 Opportunities</a>
    <a href="view_ngo.php" class="active">🏢 View NGOs</a>
    <a href="view_volunteers_admin.php">👥 Volunteers</a>
    <a href="logout.php" style="color: #ff6b6b;">🚪 Logout</a>
</div>

<!-- ================= MAIN CONTENT ================= -->
<div class="content">
    <!-- MESSAGES -->
    <?php if(isset($success_msg)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            ✅ <?php echo $success_msg; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if(isset($error_msg)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            ❌ <?php echo $error_msg; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- HEADER SECTION -->
    <div class="header-section">
        <h2>NGO Management</h2>
        <p class="text-muted mb-3">View and manage all registered non-governmental organizations</p>
        
        <div class="admin-info">
            <strong>📋 Logged in as:</strong> <?php echo htmlspecialchars($_SESSION['name']); ?>
            <span class="badge bg-primary ms-2">Admin</span>
        </div>
    </div>

    <!-- NGOs TABLE -->
    <div class="table-container">
        <div class="table-header">
            <h5 class="mb-0">NGO List</h5>
            <p class="text-muted mb-0">Total: <?php echo count($ngos); ?> organizations</p>
        </div>
        
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>#ID</th>
                        <th>NGO Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Address</th>
                        <th>Registration No</th>
                        <th class="actions-column">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(count($ngos) > 0): ?>
                        <?php foreach($ngos as $ngo): ?>
                        <tr>
                            <td><strong>#<?php echo htmlspecialchars($ngo['NGOID']); ?></strong></td>
                            <td><strong><?php echo htmlspecialchars($ngo['NGOName']); ?></strong></td>
                            <td><?php echo htmlspecialchars($ngo['email']); ?></td>
                            <td><?php echo htmlspecialchars($ngo['phone'] ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars($ngo['address'] ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars($ngo['RegistrationNo'] ?? '-'); ?></td>
                            <td class="actions-column">
                                <button class="btn btn-sm btn-outline-primary btn-action">View</button>
                                <button class="btn btn-sm btn-outline-warning btn-action">Edit</button>
                                <a href="view_ngo.php?delete_id=<?php echo $ngo['NGOID']; ?>" 
                                   class="btn btn-sm btn-outline-danger btn-action"
                                   onclick="return confirmDelete()">
                                    Delete
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center py-4">
                                <div class="text-muted">
                                    <i class="bi bi-building" style="font-size: 40px;"></i>
                                    <p class="mt-2">No NGOs found</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <div class="p-3 border-top bg-light">
            <small class="text-muted">
                Showing <?php echo count($ngos); ?> NGOs | Last updated: <?php echo date('Y-m-d H:i'); ?>
            </small>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // JavaScript Confirmation Dialog
    function confirmDelete() {
        // Option 1: JavaScript confirm dialog (simple)
        return confirm("Are you sure you want to delete this NGO?\nThis action cannot be undone!");
        
        // Option 2: Custom modal (uncomment if you want custom modal)
        /*
        const confirmed = confirm("Are you sure you want to delete this NGO?\nThis action cannot be undone!");
        if(!confirmed) {
            return false; // Cancel the link
        }
        return true; // Proceed with deletion
        */
    }
    
    // Auto-hide alerts after 5 seconds
    setTimeout(function() {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        });
    }, 5000);
    
    // Close modal when clicking outside
    const modal = document.getElementById('confirmModal');
    if(modal) {
        modal.addEventListener('click', function(e) {
            if(e.target === modal) {
                window.location.href = 'view_ngo.php';
            }
        });
    }
</script>
</body>
</html>

<?php
sqlsrv_close($conn);
?>