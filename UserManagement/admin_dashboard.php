<?php
session_start();
if(!isset($_SESSION['name']) || $_SESSION['role'] != "admin"){
    header("Location: login.php");
    exit();
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
    </style>
</head>
<body>

    <!-- Sidebar SAMA seperti view_admin.php -->
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
        
        <!-- Dashboard Cards -->
        <div class="row">
            <div class="col-md-3">
                <div class="card bg-primary text-white">
                    <div class="card-body text-center">
                        <i class="fas fa-users fa-3x mb-3"></i>
                        <h5>Total Admins</h5>
                        <h3>4</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-success text-white">
                    <div class="card-body text-center">
                        <i class="fas fa-user-shield fa-3x mb-3"></i>
                        <h5>Super Admins</h5>
                        <h3>1</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-info text-white">
                    <div class="card-body text-center">
                        <i class="fas fa-building fa-3x mb-3"></i>
                        <h5>NGOs</h5>
                        <h3>0</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-warning text-white">
                    <div class="card-body text-center">
                        <i class="fas fa-chart-line fa-3x mb-3"></i>
                        <h5>Reports</h5>
                        <h3>0</h3>
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
                <ul class="list-group list-group-flush">
                    <li class="list-group-item">
                        <i class="fas fa-user-plus text-success me-2"></i>
                        New admin "yang" added on 2025-12-05
                    </li>
                    <li class="list-group-item">
                        <i class="fas fa-sign-in-alt text-primary me-2"></i>
                        You logged in just now
                    </li>
                    <li class="list-group-item">
                        <i class="fas fa-database text-info me-2"></i>
                        System database updated yesterday
                    </li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
</body>
</html>