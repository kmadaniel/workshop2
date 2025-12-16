<?php
include "../db.php";

$disasters = $conn->query("
    SELECT disaster_id, disaster_name, status
    FROM disaster
    ORDER BY created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
<title>Admin Dashboard</title>
<style>
body { font-family:Segoe UI; background:#f4f6f9; }
.container { max-width:800px; margin:50px auto; }
.card { background:white; padding:30px; border-radius:14px; }
a.btn {
    display:block; padding:14px; margin:10px 0;
    background:#007bff; color:white; text-decoration:none;
    border-radius:10px;
}
</style>
</head>

<body>
<div class="container">
<div class="card">

<h2>🛠 Admin Dashboard</h2>

<?php foreach ($disasters as $d): ?>
<a class="btn" href="admin_victims.php?disaster_id=<?= $d['disaster_id'] ?>">
<?= $d['disaster_name'] ?> (<?= $d['status'] ?>)
</a>
<?php endforeach; ?>

<a class="btn" style="background:#28a745" href="admin_disaster.php">
➕ Manage Disasters
</a>

</div>
</div>
</body>
</html>
