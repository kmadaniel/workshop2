<?php
include "../db.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = $conn->prepare("
        INSERT INTO disaster
        (disaster_name, district, severity, status)
        VALUES (:name, :district, :severity, :status)
    ");
    $stmt->execute($_POST);
}

$disasters = $conn->query("
    SELECT * FROM disaster ORDER BY created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
<title>Manage Disasters</title>
<style>
body { font-family:Segoe UI; background:#f7f7f7; }
.card { background:white; padding:30px; margin:30px; border-radius:14px; }
</style>
</head>

<body>

<div class="card">
<h2>➕ Report New Disaster</h2>
<form method="POST">
<input name="name" placeholder="Disaster Name" required>
<input name="district" placeholder="District" required>

<select name="severity">
<option>Low</option>
<option>Medium</option>
<option>High</option>
</select>

<select name="status">
<option>Active</option>
<option>Under Control</option>
<option>Ended</option>
</select>

<button>Add Disaster</button>
</form>
</div>

<div class="card">
<h2>📌 Existing Disasters</h2>
<ul>
<?php foreach ($disasters as $d): ?>
<li>
<?= $d['disaster_name'] ?> — <?= $d['status'] ?>
</li>
<?php endforeach; ?>
</ul>
</div>

</body>
</html>
