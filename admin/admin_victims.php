<?php
include "../db.php";

$disaster_id = $_GET['disaster_id'] ?? null;
if (!$disaster_id) die("Disaster not selected");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = $conn->prepare("
        UPDATE victim_disaster
        SET approval_status = :approval,
            distribution_status = :distribution
        WHERE victim_id = :victim
          AND disaster_id = :disaster
    ");
    $stmt->execute([
        ':approval' => $_POST['approval_status'],
        ':distribution' => $_POST['distribution_status'],
        ':victim' => $_POST['victim_id'],
        ':disaster' => $disaster_id
    ]);
}

$victims = $conn->prepare("
    SELECT v.victim_id, v.full_name, v.phone,
           vd.approval_status, vd.distribution_status
    FROM victim v
    JOIN victim_disaster vd ON v.victim_id = vd.victim_id
    WHERE vd.disaster_id = :id
");
$victims->execute([':id' => $disaster_id]);
$victims = $victims->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
<title>Victim List</title>
<style>
body { font-family:Segoe UI; background:#eef2f7; }
table {
    width:100%; border-collapse:collapse;
    background:white;
}
th, td {
    padding:12px; border-bottom:1px solid #ddd;
}
select { padding:6px; }
button { padding:8px 12px; }
</style>
</head>

<body>
<h2>📋 Victims for Selected Disaster</h2>

<table>
<tr>
<th>Name</th>
<th>Phone</th>
<th>Approval</th>
<th>Distribution</th>
<th>Action</th>
</tr>

<?php foreach ($victims as $v): ?>
<tr>
<form method="POST">
<td><?= htmlspecialchars($v['full_name']) ?></td>
<td><?= $v['phone'] ?></td>

<td>
<select name="approval_status">
<option <?= $v['approval_status']=='Pending'?'selected':'' ?>>Pending</option>
<option <?= $v['approval_status']=='Approved'?'selected':'' ?>>Approved</option>
<option <?= $v['approval_status']=='Rejected'?'selected':'' ?>>Rejected</option>
</select>
</td>

<td>
<select name="distribution_status">
<option <?= $v['distribution_status']=='Pending'?'selected':'' ?>>Pending</option>
<option <?= $v['distribution_status']=='Completed'?'selected':'' ?>>Completed</option>
</select>
</td>

<td>
<input type="hidden" name="victim_id" value="<?= $v['victim_id'] ?>">
<button>Update</button>
</td>
</form>
</tr>
<?php endforeach; ?>

</table>
</body>
</html>
