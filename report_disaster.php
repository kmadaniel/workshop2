<?php
include "db.php";

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $disaster_name = trim($_POST['disaster_name']);
    $description = trim($_POST['description']);
    $district = trim($_POST['district']);
    $severity = trim($_POST['severity']); // Low, Medium, High
    $alert_message = trim($_POST['alert_message']);
    $status = 'Active';

    try {
        $stmt = $conn->prepare("
            INSERT INTO disaster (disaster_name, description, district, severity, alert_message, status)
            VALUES (:name, :desc, :district, :severity, :alert, :status)
        ");
        $stmt->execute([
            ':name' => $disaster_name,
            ':desc' => $description,
            ':district' => $district,
            ':severity' => $severity,
            ':alert' => $alert_message,
            ':status' => $status
        ]);
        $message = "✅ Disaster added successfully!";
    } catch (PDOException $e) {
        $message = "❌ Failed to report disaster: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Report Disaster - Melaka Disaster Assistance</title>
<link rel="stylesheet" href="header.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<style>
/* Main container card for form */
.card-form {
    background: #fff;
    border-radius: 12px;
    padding: 25px;
    box-shadow: 0 6px 15px rgba(0,0,0,0.05);
    max-width: 700px;
    margin: 0 auto;
}
.card-form h2 {
    margin-top: 0;
    margin-bottom: 20px;
    font-size: 22px;
    display: flex;
    align-items: center;
    gap: 10px;
}
.card-form input, .card-form textarea, .card-form select {
    width: 100%;
    padding: 12px;
    margin-bottom: 15px;
    border-radius: 8px;
    border: 1px solid #ccc;
    box-sizing: border-box;
}
.card-form textarea { resize: vertical; }
.submit-btn {
    background: #9b59b6;
    color: white;
    border: none;
    padding: 15px;
    border-radius: 8px;
    cursor: pointer;
    font-size: 16px;
    width: 100%;
    transition: 0.3s ease;
}
.submit-btn:hover { background: #8e44ad; }
.message {
    padding: 14px;
    border-radius: 10px;
    margin-bottom: 20px;
    font-size: 14px;
}
.success { background:#e8f5e9; color:#2e7d32; }
.error { background:#fdd; color:#c62828; }

/* Responsive adjustments */
@media (max-width: 768px) {
    .card-form { padding: 20px; }
}
</style>
</head>

<body>

<!-- ================= HEADER ================= -->
<header class="system-header">
    <div class="header-container">
        <div class="logo-section">
            <i class="fas fa-shield-heart logo-icon"></i>
            <div class="logo-text">
                <h1>Melaka Disaster Assistance</h1>
                <small>Public Support & Emergency Information</small>
            </div>
        </div>
    </div>
</header>

<!-- ================= SIDEBAR ================= -->
<div class="sidebar">
    <ul class="nav-menu">
        <li class="nav-item">
            <a class="nav-link" href="index.php">
                <i class="fas fa-house"></i>
                <span class="nav-text">Dashboard</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="victim_register.php">
                <i class="fas fa-user-plus"></i>
                <span class="nav-text">Victim Registration</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link active" href="report_disaster.php">
                <i class="fas fa-triangle-exclamation"></i>
                <span class="nav-text">Report Disaster</span>
            </a>
        </li>
    </ul>
</div>

<!-- ================= MAIN ================= -->
<div class="main-content">

<div style="margin-top:20px; margin-bottom:20px;">
    <a href="index.php" class="submit-btn" style="background:#3498db; width:auto; padding:10px 15px; display:inline-block; margin-bottom:20px;">
        ← Back to Dashboard
    </a>
</div>

<div class="card-form">
    <h2>🚨 Report a Disaster</h2>

    <?php if($message): ?>
        <div class="message <?= strpos($message,'✅')!==false ? 'success':'error' ?>">
            <?= $message ?>
        </div>
    <?php endif; ?>

    <form method="POST">
        <label>Disaster Name</label>
        <input type="text" name="disaster_name" placeholder="e.g. Flood" required>

        <label>Description</label>
        <textarea name="description" placeholder="Details of the disaster" required></textarea>

        <label>District</label>
        <input type="text" name="district" placeholder="e.g. Melaka Tengah" required>

        <label>Severity</label>
        <select name="severity" required>
            <option value="">-- Select Severity --</option>
            <option value="Low">Low</option>
            <option value="Medium">Medium</option>
            <option value="High">High</option>
        </select>

        <label>Alert Message</label>
        <textarea name="alert_message" placeholder="e.g. Avoid riverbanks" required></textarea>

        <button type="submit" class="submit-btn">Submit Disaster</button>
    </form>
</div>

</div>
</body>
</html>
