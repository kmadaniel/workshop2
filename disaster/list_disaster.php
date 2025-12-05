<?php 
include "../db.php";

$district = $_GET['district'] ?? "";

$sql = "SELECT * FROM disaster";
$params = [];

if ($district !== "") {
    $sql .= " WHERE district = :district";
    $params[':district'] = $district;
}

$sql .= " ORDER BY created_at DESC";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
<title>Disaster List</title>
<style>
    body { font-family: Arial; background: #f4f6f9; }
    .container { width: 90%; margin: 30px auto; }
    table {
        width: 100%;
        background: white;
        border-collapse: collapse;
        border-radius: 8px;
        overflow: hidden;
        box-shadow: 0px 4px 12px rgba(0,0,0,0.1);
    }
    th, td {
        padding: 12px;
        border-bottom: 1px solid #ddd;
    }
    th {
        background: #003f8a;
        color: white;
    }
    tr:hover { background: #f0f4ff; }
    .badge {
        padding: 5px 10px;
        border-radius: 6px;
        color: white;
    }
    .active { background: #28a745; }
    .ended { background: #dc3545; }
    select, button {
        padding: 8px;
        margin-bottom: 18px;
    }
</style>
</head>
<body>

<div class="container">

<h2>Disaster List</h2>

<form method="GET">
    <select name="district">
        <option value="">All Districts</option>
        <option value="Jasin">Jasin</option>
        <option value="Melaka Tengah">Melaka Tengah</option>
        <option value="Alor Gajah">Alor Gajah</option>
    </select>
    <button type="submit">Filter</button>
</form>

<table>
<tr>
    <th>ID</th>
    <th>Name</th>
    <th>District</th>
    <th>Severity</th>
    <th>Affected People</th>
    <th>Status</th>
    <th>Start</th>
    <th>End</th>
</tr>

<?php foreach ($data as $d) { ?>
<tr>
    <td><?= $d['disaster_id'] ?></td>
    <td><?= $d['disaster_name'] ?></td>
    <td><?= $d['district'] ?></td>
    <td><?= $d['severity'] ?></td>
    <td><?= $d['affected_people'] ?></td>
    <td>
        <?php if ($d['status'] == 'Active') { ?>
            <span class="badge active">Active</span>
        <?php } else { ?>
            <span class="badge ended">Ended</span>
        <?php } ?>
    </td>
    <td><?= $d['start_date'] ?></td>
    <td><?= $d['end_date'] ?></td>
</tr>
<?php } ?>

</table>

</div>

</body>
</html>
