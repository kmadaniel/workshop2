<?php
include "db.php";
$message = "";
$status_data = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ic_number = trim($_POST['ic_number']);
    $phone     = trim($_POST['phone']);

    if (!empty($ic_number) && !empty($phone)) {
        try {
            $stmt = $conn->prepare("
                SELECT v.full_name, v.ic_number, v.phone, v.family_id, 
                       n.item_name, n.quantity, n.status AS need_status,
                       d.disaster_name, d.district
                FROM victim v
                LEFT JOIN needs n ON v.victim_id = n.victim_id
                LEFT JOIN disaster d ON v.disaster_id = d.disaster_id
                WHERE v.ic_number = :ic AND v.phone = :phone
                ORDER BY n.created_at
            ");
            $stmt->bindValue(':ic', $ic_number);
            $stmt->bindValue(':phone', $phone);
            $stmt->execute();
            $status_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($status_data)) {
                $message = "❌ No registration found with that IC Number and Phone Number.";
            }

        } catch (PDOException $e) {
            $message = "❌ Error fetching status: " . $e->getMessage();
        }
    } else {
        $message = "❌ Please enter both IC Number and Phone Number.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Check Registration Status</title>
<link rel="stylesheet" href="header.css">
<style>
body { font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; padding:20px; }
input { width:100%; padding:12px; margin-bottom:15px; border-radius:8px; border:1px solid #ccc; box-sizing:border-box; }
button { background:#007bff; color:white; padding:12px; border:none; border-radius:8px; cursor:pointer; width:100%; font-size:16px; }
button:hover { background:#0056b3; }
.message { padding:12px; margin-bottom:20px; border-radius:8px; }
.success { background:#e8f5e9; color:#2e7d32; }
.error { background:#fdd; color:#c62828; }
table { width:100%; border-collapse: collapse; margin-top:20px; }
table, th, td { border:1px solid #ccc; }
th, td { padding:10px; text-align:left; }
th { background:#f0f0f0; }
</style>
</head>
<body>

<h2>🔎 Check Your Registration Status</h2>

<?php if ($message): ?>
<div class="message <?= str_contains($message,'❌') ? 'error':'success' ?>">
<?= htmlspecialchars($message) ?>
</div>
<?php endif; ?>

<form method="POST">
<input type="text" name="ic_number" placeholder="Enter IC Number" required>
<input type="text" name="phone" placeholder="Enter Phone Number" required>
<button type="submit">Check Status</button>
</form>

<?php if (!empty($status_data)): ?>
<h3>✅ Registration Details</h3>
<p><strong>Name:</strong> <?= htmlspecialchars($status_data[0]['full_name']) ?></p>
<p><strong>IC Number:</strong> <?= htmlspecialchars($status_data[0]['ic_number']) ?></p>
<p><strong>Phone:</strong> <?= htmlspecialchars($status_data[0]['phone']) ?></p>
<p><strong>Family ID:</strong> <?= htmlspecialchars($status_data[0]['family_id']) ?></p>
<p><strong>Disaster:</strong> <?= htmlspecialchars($status_data[0]['disaster_name'] . ' (' . $status_data[0]['district'] . ')') ?></p>

<h3>🧾 Requested Needs</h3>
<table>
<tr>
    <th>Item</th>
    <th>Quantity</th>
    <th>Status</th>
</tr>
<?php foreach ($status_data as $row): ?>
<tr>
    <td><?= htmlspecialchars($row['item_name'] ?? '-') ?></td>
    <td><?= htmlspecialchars($row['quantity'] ?? '-') ?></td>
    <td><?= htmlspecialchars($row['need_status'] ?? '-') ?></td>
</tr>
<?php endforeach; ?>
</table>
<?php endif; ?>

</body>
</html>
