<?php
include "db.php";

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $disaster_name = trim($_POST['disaster_name']);
    $description = trim($_POST['description']);
    $district = trim($_POST['district']);
    $severity = trim($_POST['severity']); // Must be 'Low', 'Medium', 'High'
    $alert_message = trim($_POST['alert_message']);
    $status = 'Active'; // default status

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
        $message = "✅ Disaster added to the main disaster table successfully!";
    } catch (PDOException $e) {
        $message = "❌ Failed to report disaster: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Report Disaster - Melaka Disaster Assistance Portal</title>
    <style>
        body { font-family:'Segoe UI',sans-serif; background:#eef3f8; margin:0; }
        .container { max-width:700px; margin:30px auto; padding:0 20px; }
        .card { background:white; border-radius:14px; padding:25px; box-shadow:0 4px 12px rgba(0,0,0,0.08); }
        h2 { color:#333; }
        label { display:block; margin-bottom:6px; font-weight:600; }
        input, textarea, select { width:100%; padding:12px; margin-bottom:15px; border-radius:8px; border:1px solid #ccc; }
        textarea { resize: vertical; }
        .submit-btn, .back-btn { display:block; background:#007bff; color:white; border:none; padding:15px; border-radius:10px; text-align:center; cursor:pointer; text-decoration:none; transition:0.2s ease; margin-bottom:10px; }
        .submit-btn:hover, .back-btn:hover { background:#0056b3; }
        .message { padding:12px; border-radius:8px; margin-bottom:15px; font-size:14px; }
        .success { background:#e8f5e9; border-left:4px solid #28a745; }
        .error { background:#fbe9e7; border-left:4px solid #dc3545; }
    </style>
</head>
<body>

<div class="container">
    <a href="index.php" class="back-btn">← Back to Dashboard</a>

    <div class="card">
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
