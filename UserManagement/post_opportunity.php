<?php
session_start();
if(!isset($_SESSION['name']) || $_SESSION['role'] != "ngo"){
    header("Location: login.php");
    exit();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Post Opportunity</title>
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

<div class="sidebar">
    <h4>NGO Panel</h4>
    <hr style="color:white;">

    <a href="ngo_dashboard.php">🏠 Dashboard</a>
    <a href="ngo_profile.php">👤 Profile</a>
    <a href="view_volunteers.php">👥 My Volunteers</a>
    <a href="create_news.php">📝 Apply Story Activity</a> 
    <a href="post_opportunity.php">📢 Post Opportunity</a>
    <a href="view_opportunities.php">📋 View Opportunities</a>

    <a href="logout.php" class="text-danger">🚪 Logout</a>
</div>


<!-- CONTENT -->
<div class="content">
    <h2>Post Volunteer Opportunity</h2>

    <form method="POST" action="save_opportunity.php" class="card p-4 mt-3">
        <div class="mb-3">
            <label>Title</label>
            <input type="text" name="title" class="form-control" required>
        </div>

        <div class="mb-3">
            <label>Description</label>
            <textarea name="description" class="form-control" required></textarea>
        </div>

        <div class="mb-3">
            <label>Location</label>
            <input type="text" name="location" class="form-control">
        </div>

        <div class="mb-3">
            <label>Event Date</label>
            <input type="date" name="event_date" class="form-control">
        </div>

        <div class="mb-3">
            <label>Available Slots</label>
            <input type="number" name="slots" class="form-control">
        </div>

        <button type="submit" class="btn btn-primary">Post Opportunity</button>
    </form>
</div>

</body>
</html>
