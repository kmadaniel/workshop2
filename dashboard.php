<?php
session_start();
include 'db.php';

// Ensure user is logged in
if (!isset($_SESSION['account_id'])) {
    header("Location: login.php");
    exit();
}

$account_id = $_SESSION['account_id'];

// Fetch victim info and location
$stmt = $conn->prepare("
    SELECT v.*, a.username, 
           CONCAT(l.city, ' - ', l.district) AS location_name
    FROM victim v
    JOIN accounts a ON v.account_id = a.account_id
    JOIN location l ON v.location_id = l.location_id
    WHERE v.account_id = ?
");
$stmt->execute([$account_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Default Lat/Lng for Melaka if no exact coords yet
$lat = $user['latitude'] ?? 2.1896;
$lng = $user['longitude'] ?? 102.2501;

// Fetch previous needs requests
$stmt2 = $conn->prepare("SELECT needs, aid_status, registration_date FROM victim_disaster WHERE victim_id = ?");
$stmt2->execute([$account_id]);
$requests = $stmt2->fetchAll(PDO::FETCH_ASSOC);

// Success message after needs request
$success = $_SESSION['success'] ?? '';
unset($_SESSION['success']);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Victim Dashboard</title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <style>
        body { font-family: Arial, sans-serif; background: #f8f9fa; padding: 20px; }
        .container { width: 80%; margin: auto; background: white; padding: 20px; border-radius: 12px; box-shadow: 0 0 10px #ccc; }
        #map { height: 400px; width: 100%; border-radius: 12px; margin-top: 20px; }
        .section { margin-bottom: 30px; }
        .alert { padding: 10px; border-radius: 8px; background: #ffefc6; color: #b85b00; margin-bottom: 15px; }
        .btn { padding: 10px 15px; background: #007bff; color: white; border-radius: 8px; text-decoration: none; }
        select, button, input { padding: 8px; border-radius: 6px; }
        .request-item { margin-bottom: 8px; }
        .success { padding: 10px; background: #d4edda; color: #155724; border-radius: 8px; margin-bottom: 15px; }
    </style>
</head>
<body>

<div class="container">
    <h2>Welcome, <?php echo htmlspecialchars($user['name']); ?> 👋</h2>

    <!-- Success message -->
    <?php if($success) echo "<div class='success'>$success</div>"; ?>

    <!-- Profile Section -->
    <div class="section">
        <h3>Your Information</h3>
        <p><strong>Username:</strong> <?php echo htmlspecialchars($user['username']); ?></p>
        <p><strong>Age:</strong> <?php echo htmlspecialchars($user['age']); ?></p>
        <p><strong>Gender:</strong> <?php echo htmlspecialchars($user['gender']); ?></p>
        <p><strong>Phone:</strong> <?php echo htmlspecialchars($user['phone_num']); ?></p>
        <p><strong>Address:</strong> <?php echo htmlspecialchars($user['address']); ?></p>
        <p><strong>District:</strong> <?php echo htmlspecialchars($user['location_name']); ?></p>
        <a class="btn" href="update_account.php">Update Info</a>
    </div>

    <!-- Needs Request Section -->
    <div class="section">
        <h3>Request Needs 🛒</h3>
        <form method="POST" action="request_needs.php">
            <label>Choose your needs:</label><br>
            <input type="checkbox" name="needs[]" value="Food"> Food
            <input type="checkbox" name="needs[]" value="Medicine"> Medicine
            <input type="checkbox" name="needs[]" value="Clothes"> Clothes
            <input type="checkbox" name="needs[]" value="Shelter"> Shelter
            <input type="checkbox" name="needs[]" value="Other"> Other
            <br><br>
            <input type="text" name="needs_other" placeholder="Specify other need if any">
            <br><br>
            <button type="submit" class="btn">Submit Request</button>
        </form>

        <!-- Previous Requests -->
        <?php if($requests): ?>
            <h4>Your Previous Requests:</h4>
            <?php foreach($requests as $r): ?>
                <div class="request-item">
                    <strong><?php echo htmlspecialchars($r['needs']); ?></strong> - Status: <?php echo htmlspecialchars($r['aid_status']); ?> (<?php echo $r['registration_date']; ?>)
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Disaster Alerts Section -->
    <div class="section">
        <h3>Disaster Alerts ⚠️</h3>
        <div class="alert">Heavy rain expected today. Stay indoors and avoid low-lying areas.</div>
        <div class="alert">Possible flood risk in Melaka Tengah.</div>
        <a class="btn" href="report_disaster.php">Report a New Issue</a>
    </div>

    <!-- Map Section -->
    <div class="section">
        <h3>Your Location</h3>
        <p>You can update your exact location if needed.</p>
        <div id="map"></div>
    </div>
</div>

<script>
var map = L.map('map').setView([<?php echo $lat; ?>, <?php echo $lng; ?>], 13);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19 }).addTo(map);
L.marker([<?php echo $lat; ?>, <?php echo $lng; ?>]).addTo(map).bindPopup("Your Location").openPopup();
</script>

</body>
</html>
