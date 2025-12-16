<?php
session_start();
if(!isset($_SESSION['name']) || $_SESSION['role'] != "admin"){
    header("Location: login.php");
    exit();
}

require_once "connection.php"; // Sama connection.php

// Handle Approve/Reject Actions
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
    <title>Admin - NGO Approvals</title>
    <style>
        body {
            background: #f5f5f5;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
        }
        
        .sidebar {
            width: 250px;
            height: 100vh;
            background: linear-gradient(135deg, #1d3557 0%, #457b9d 100%);
            padding: 20px;
            position: fixed;
            left: 0;
            top: 0;
            color: white;
        }
        
        .sidebar h3 {
            margin-top: 0;
            margin-bottom: 30px;
            color: white;
        }
        
        .sidebar a {
            display: block;
            padding: 12px 15px;
            color: white;
            text-decoration: none;
            margin: 5px 0;
            border-radius: 8px;
            transition: all 0.3s;
        }
        
        .sidebar a:hover {
            background: rgba(255,255,255,0.1);
        }
        
        .sidebar a.active {
            background: rgba(255,255,255,0.15);
            border-left: 4px solid #ffc107;
        }
        
        .content {
            margin-left: 250px;
            padding: 30px;
        }
        
        .header {
            background: white;
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
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
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #666;
            font-size: 14px;
        }
        
        .pending-card { border-left: 5px solid #ffc107; }
        .approved-card { border-left: 5px solid #28a745; }
        .rejected-card { border-left: 5px solid #dc3545; }
        
        .ngo-card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.08);
            border: 1px solid #eaeaea;
        }
        
        .btn-approve {
            background: #28a745;
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
        }
        
        .btn-reject {
            background: #dc3545;
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
        }
        
        .btn-approve:hover {
            background: #218838;
        }
        
        .btn-reject:hover {
            background: #c82333;
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
    </style>
</head>
<body>

<!-- SIDEBAR -->
<div class="sidebar">
        <h4>Admin Panel</h4>
        <a href="admin_dashboard.php" class="active">🏠 Dashboard</a>
        <a href="admin_profile.php">👤 Profile</a>
        <a href="view_admin.php">📋 View Admins</a>
        <a href="admin_manage_ngo.php">🏢 View NGO</a>
        <a href="view_ngo.php">🏢 View NGOs</a>
        <a href="create_news.php">📰 Create News</a>
        <a href="view_news.php">📜 View News</a>
        <a href="admin_opportunity.php">📜 Opportunity</a>
        <a href="report.php">📊 Reports</a>
        <a href="logout.php" style="color: #e74c3c;">🚪 Logout</a>
    </div>

<!-- MAIN CONTENT -->
<div class="content">
    <!-- Messages -->
    <?php if(isset($_SESSION['success'])): ?>
        <div class="alert alert-success">
            ✅ <?php echo $_SESSION['success']; ?>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>
    
    <?php if(isset($_SESSION['error'])): ?>
        <div class="alert alert-danger">
            ❌ <?php echo $_SESSION['error']; ?>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <!-- HEADER -->
    <div class="header">
        <h2 style="margin-top: 0;">NGO Approvals</h2>
        <p style="color: #666; margin-bottom: 0;">Approve or reject NGO registrations</p>
    </div>

    <!-- STATISTICS -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px;">
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
        <div class="stat-card">
            <div class="stat-number"><?php echo $stats['total']; ?></div>
            <div class="stat-label">Total NGOs</div>
        </div>
    </div>

    <!-- PENDING NGOs LIST -->
    <div style="background: white; border-radius: 10px; padding: 0; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.05);">
        <div style="padding: 20px; background: #f8f9fa; border-bottom: 1px solid #eee;">
            <h4 style="margin: 0;">Pending NGO Registrations</h4>
        </div>
        
        <div style="padding: 20px;">
            <?php if(count($pending_ngos) > 0): ?>
                <?php foreach($pending_ngos as $ngo): ?>
                <div class="ngo-card">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 20px;">
                        <div style="flex: 1; min-width: 300px;">
                            <h4 style="margin-top: 0; color: #1d3557;"><?php echo htmlspecialchars($ngo['NGOName']); ?></h4>
                            <div style="color: #666; margin-bottom: 10px;">
                                <strong>Email:</strong> <?php echo htmlspecialchars($ngo['Email']); ?><br>
                                <strong>Phone:</strong> <?php echo htmlspecialchars($ngo['Phone'] ?? 'N/A'); ?><br>
                                <strong>Registration No:</strong> <?php echo htmlspecialchars($ngo['RegistrationNo'] ?? 'N/A'); ?><br>
                                <strong>Address:</strong> <?php echo htmlspecialchars($ngo['Address'] ?? 'N/A'); ?><br>
                                <strong>Registered:</strong> 
                                <?php 
                                if($ngo['CreatedAt'] instanceof DateTime){
                                    echo $ngo['CreatedAt']->format('d/m/Y H:i');
                                } else {
                                    echo date('d/m/Y H:i', strtotime($ngo['CreatedAt']));
                                }
                                ?>
                            </div>
                        </div>
                        
                        <div style="display: flex; gap: 10px; align-items: center;">
                            <a href="admin_manage_ngo.php?action=approve&id=<?php echo $ngo['NGOID']; ?>" 
                               class="btn-approve"
                               onclick="return confirm('Approve <?php echo htmlspecialchars(addslashes($ngo['NGOName'])); ?>?')">
                                ✅ Approve
                            </a>
                            <a href="admin_manage_ngo.php?action=reject&id=<?php echo $ngo['NGOID']; ?>" 
                               class="btn-reject"
                               onclick="return confirm('Reject <?php echo htmlspecialchars(addslashes($ngo['NGOName'])); ?>?')">
                                ❌ Reject
                            </a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div style="text-align: center; padding: 40px; color: #666;">
                    <div style="font-size: 48px; margin-bottom: 20px;">✅</div>
                    <h4>No Pending Registrations</h4>
                    <p>All NGO registrations have been processed</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

</body>
</html>