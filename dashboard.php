<?php 
include "db.php";

// Fetch dashboard statistics
$totalVictims = $conn->query("SELECT COUNT(*) FROM victim")->fetchColumn();
$totalDisasters = $conn->query("SELECT COUNT(*) FROM disaster")->fetchColumn();
$activeDisasters = $conn->query("SELECT COUNT(*) FROM disaster WHERE status='Active'")->fetchColumn();
?>

<!DOCTYPE html>
<html>
<head>
<title>Dashboard</title>
<style>
    body { 
        background: #6a0dad; /* Purple background */
        font-family: Arial, sans-serif; 
        margin: 0;
        padding: 0;
        color: #333;
    }
    .container { 
        width: 90%; 
        margin: 40px auto; 
    }
    h1 { 
        text-align: center; 
        color: white;
        margin-bottom: 40px;
    }
    .cards {
        display: flex;
        gap: 25px;
        flex-wrap: wrap;
        justify-content: center;
    }
    .card {
        width: 250px;
        padding: 30px;
        border-radius: 12px;
        text-align: center;
        box-shadow: 0px 6px 15px rgba(0,0,0,0.2);
        background: #f5f5f5; /* Light grey cards */
        transition: transform 0.3s, box-shadow 0.3s;
    }
    .card:hover {
        transform: translateY(-5px);
        box-shadow: 0px 10px 20px rgba(0,0,0,0.3);
    }
    .card h2 { color: #333; }
    .card p { color: #555; }

    a.button {
        display: inline-block;
        margin: 10px 10px 0 10px;
        padding: 12px 20px;
        background: #ffcc00; /* Yellow buttons */
        color: #333;
        border-radius: 8px;
        text-decoration: none;
        font-weight: bold;
        transition: background 0.3s;
    }
    a.button:hover {
        background: #e6b800;
    }

    .buttons-container {
        text-align: center;
        margin-top: 40px;
    }
</style>
</head>
<body>

<div class="container">

    <h1>Victim & Disaster Management Dashboard</h1>

    <div class="cards">
        <div class="card">
            <h2><?= $totalVictims ?></h2>
            <p>Total Registered Victims</p>
        </div>

        <div class="card">
            <h2><?= $totalDisasters ?></h2>
            <p>Total Disasters</p>
        </div>

        <div class="card">
            <h2><?= $activeDisasters ?></h2>
            <p>Active Disasters</p>
        </div>
    </div>

    <div class="buttons-container">
        <a class="button" href="victim/add_victim.php">Register Victim</a>
        <a class="button" href="victim/list_victims.php">View Victims</a>
        <a class="button" href="disaster/add_disaster.php">Register Disaster</a>
        <a class="button" href="disaster/list_disaster.php">View Disasters</a>
    </div>

</div>

</body>
</html>
