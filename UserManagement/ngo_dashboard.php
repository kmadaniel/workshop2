<?php
session_start();

// --- SECURITY CHECK ---
if(!isset($_SESSION['name']) || $_SESSION['role'] != "ngo"){
    header("Location: login.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>NGO Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        body {
            display: flex;
            background: #f5f5f5;
        }
        .sidebar {
            width: 250px;
            height: 100vh;
            background: #1d3557;
            padding: 20px;
        }
        .sidebar h4 {
            color: white;
        }
        .sidebar a {
            display: block;
            padding: 10px;
            margin: 5px 0;
            color: #f1faee;
            text-decoration: none;
            border-radius: 5px;
        }
        .sidebar a:hover {
            background: #457b9d;
        }
        .content {
            flex-grow: 1;
            padding: 30px;
        }
    </style>
</head>
<body>

    <!-- SIDEBAR -->
    <div class="sidebar">
        <h4>NGO Panel</h4>
        <hr style="color:white;">

        <a href="ngo_dashboard.php">🏠 Dashboard</a>
        <a href="ngo_profile.php">👤 Profile</a>
        <a href="ngo_history.php">📜 History</a>
        <a href="ngo_apply_activity.php">📝 Apply Activity</a> <!-- optional -->
        <a href="logout.php" class="text-danger">🚪 Logout</a>
    </div>

    <!-- CONTENT -->
    <div class="content">
        <h2>Welcome, NGO: <?php echo $_SESSION['name']; ?></h2>
        <p>This is your NGO dashboard overview.</p>

        <div class="card p-3 mt-3">
            <h4>Your Quick Summary</h4>
            <ul>
                <li>Recent activities</li>
                <li>Pending approvals</li>
                <li>Messages or notifications</li>
            </ul>
        </div>
    </div>

</body>
</html>
