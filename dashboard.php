<?php
session_start();
include 'db.php';

if (!isset($_SESSION['account_id'])) {
    header("Location: login.php");
    exit();
}

$account_id = $_SESSION['account_id'];

// Handle form submissions directly
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Request Needs
    if (isset($_POST['family_size'])) {
        $family_size = $_POST['family_size'] ?? 1;
        $needs = $_POST['needs'] ?? [];
        $details = $_POST['details'] ?? '';

        if (!empty($needs)) {
            foreach ($needs as $need) {
                $stmt = $conn->prepare("
                    INSERT INTO victim_disaster(victim_id, needs, disaster_id, aid_status, registration_date)
                    VALUES (?, ?, NULL, 'Pending', NOW())
                ");
                $stmt->execute([$account_id, $need . " for $family_size people"]);
            }
            $_SESSION['success'] = "Your needs request has been submitted!";
        } else {
            $_SESSION['success'] = "Please select at least one need.";
        }
    }

    // Report Disaster
    if (isset($_POST['disaster_name'])) {
        $disaster_name = $_POST['disaster_name'];
        $severity = $_POST['severity_level'];
        $description = $_POST['description'];

        // Get victim location_id
        $stmt = $conn->prepare("SELECT location_id FROM victim WHERE account_id=?");
        $stmt->execute([$account_id]);
        $victim_loc = $stmt->fetch(PDO::FETCH_ASSOC);

        $alert_message = "Severity: $severity. " . $description;

        $stmt = $conn->prepare("
            INSERT INTO disaster(disaster_name, description, alert_message, location_id)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$disaster_name, $description, $alert_message, $victim_loc['location_id']]);

        $_SESSION['success'] = "Disaster reported successfully!";
    }

    header("Location: dashboard.php");
    exit();
}

// Get victim info with location
$stmt = $conn->prepare("
    SELECT v.*, l.address, l.postal_code, l.city, l.country, l.latitude, l.longitude
    FROM victim v
    JOIN location l ON v.location_id = l.location_id
    WHERE v.account_id = ?
");
$stmt->execute([$account_id]);
$victim = $stmt->fetch(PDO::FETCH_ASSOC);

// Get all disasters in victim's city
$stmt = $conn->prepare("
    SELECT d.disaster_id, d.disaster_name, d.description, d.alert_message,
           l.city, l.latitude, l.longitude
    FROM disaster d
    JOIN location l ON d.location_id = l.location_id
    WHERE l.city = ?
");
$stmt->execute([$victim['city'] ?? '']);
$disasters = $stmt->fetchAll(PDO::FETCH_ASSOC);

$success = $_SESSION['success'] ?? '';
unset($_SESSION['success']);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Victim Dashboard</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <style>
        #map { height: 400px; width: 100%; margin-bottom: 30px; }
        .card { margin-bottom: 20px; }
    </style>
</head>
<body>
<div class="container mt-4">

    <h2 class="mb-4">Welcome, <?php echo htmlspecialchars($victim['name']); ?></h2>

    <?php if($success): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>

    <!-- Personal Info -->
    <div class="card shadow">
        <div class="card-header bg-primary text-white">Personal Info</div>
        <div class="card-body">
            <p><b>Age:</b> <?php echo $victim['age']; ?></p>
            <p><b>Gender:</b> <?php echo $victim['gender']; ?></p>
            <p><b>Phone:</b> <?php echo $victim['phone_num']; ?></p>
            <p><b>Address:</b> 
                <?php echo htmlspecialchars($victim['address']) . ", " . 
                    htmlspecialchars($victim['postal_code']) . ", " . 
                    htmlspecialchars($victim['city']) . ", " . 
                    htmlspecialchars($victim['country']); ?>
            </p>
        </div>
    </div>

    <!-- Safety Messages -->
    <div class="card shadow">
        <div class="card-header bg-danger text-white">Safety Messages</div>
        <div class="card-body">
            <?php
            if (!empty($disasters)) {
                foreach ($disasters as $d) {
                    echo "<p><b>".htmlspecialchars($d['disaster_name']).":</b> ".htmlspecialchars($d['alert_message'])."</p>";
                }
            } else {
                echo "<p>No disasters reported in your area.</p>";
            }
            ?>
        </div>
    </div>

    <!-- Request Needs -->
    <div class="card shadow">
        <div class="card-header bg-success text-white">Request Needs</div>
        <div class="card-body">
            <form method="POST">
                <div class="mb-3">
                    <label>Number of Family Members:</label>
                    <input type="number" name="family_size" class="form-control" min="1" required>
                </div>
                <div class="mb-3">
                    <label>Select Needs:</label><br>
                    <?php
                    $needs_options = ['Medical','Food','Basic Needs'];
                    foreach ($needs_options as $n):
                    ?>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="needs[]" value="<?php echo $n; ?>" id="need_<?php echo $n; ?>">
                            <label class="form-check-label" for="need_<?php echo $n; ?>"><?php echo $n; ?></label>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="mb-3">
                    <label>Additional Details (optional):</label>
                    <textarea name="details" class="form-control" rows="2"></textarea>
                </div>
                <button type="submit" class="btn btn-success">Submit Request</button>
            </form>
        </div>
    </div>

    <!-- Report Disaster -->
    <div class="card shadow">
        <div class="card-header bg-warning text-dark">Report Current Disaster</div>
        <div class="card-body">
            <form method="POST">
                <div class="mb-3">
                    <label>Disaster Name:</label>
                    <input type="text" name="disaster_name" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label>Severity:</label>
                    <select name="severity_level" class="form-select" required>
                        <option value="">Select Severity</option>
                        <option value="Low">Low</option>
                        <option value="Medium">Medium</option>
                        <option value="High">High</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label>Description:</label>
                    <textarea name="description" class="form-control" rows="2"></textarea>
                </div>
                <button type="submit" class="btn btn-warning">Report Disaster</button>
            </form>
        </div>
    </div>

    <!-- Map -->
    <div class="card shadow">
        <div class="card-header bg-info text-white">Current Disasters Map</div>
        <div class="card-body">
            <div id="map"></div>
        </div>
    </div>

    <a href="logout.php" class="btn btn-secondary mb-4">Logout</a>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
var map = L.map('map').setView([<?php echo $victim['latitude'] ?? 2.1896; ?>, <?php echo $victim['longitude'] ?? 102.2501; ?>], 11);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: 'Map data © OpenStreetMap'
}).addTo(map);

var disasters = <?php echo json_encode($disasters); ?>;
disasters.forEach(function(d){
    var lat = d.latitude || 2.18 + Math.random()*0.05;
    var lng = d.longitude || 102.25 + Math.random()*0.05;
    L.marker([lat, lng]).addTo(map)
        .bindPopup("<b>" + d.disaster_name + "</b><br>City: " + d.city + "<br>Description: " + d.description);
});
</script>
</body>
</html>
