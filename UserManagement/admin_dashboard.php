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
    <style>
        body {
            display: flex;
        }
        .sidebar {
            width: 250px;
            height: 100vh;
            background: #343a40;
            padding: 20px;
        }
        .sidebar a {
            display: block;
            padding: 10px;
            margin: 5px 0;
            color: white;
            text-decoration: none;
            border-radius: 5px;
        }
        .sidebar a:hover {
            background: #495057;
        }
        .content {
            flex-grow: 1;
            padding: 30px;
        }
    </style>
</head>
<body>

    <div class="sidebar">
        <h4 class="text-white">Admin Panel</h4>
        <hr style="color:white;">
        <a href="admin_dashboard.php">🏠 Dashboard</a>
        <a href="admin_profile.php">👤 Profile</a>
        <a href="add_admin.php">➕ Add Admin</a>
        <a href="view_admin.php">📋 View Admins</a>
        <a href="view_ngo.php">🏢 View NGO Register</a>
        <a href="report.php">📊 Reports</a>
        <a href="logout.php" class="text-danger">🚪 Logout</a>
    </div>

    <div class="content">
        <h2>Welcome, Admin: <?php echo $_SESSION['name']; ?></h2>
        <p>This is your dashboard overview.</p>
    </div>

</body>
</html>
