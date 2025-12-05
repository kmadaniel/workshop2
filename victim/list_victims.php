<?php include "../db.php"; ?>

<!DOCTYPE html>
<html>
<head>
    <title>Victim List</title>
    <style>
        body { font-family: Arial; background: #eef2f7; padding: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 12px; border: 1px solid #ccc; text-align: left; }
        th { background: #007bff; color: white; }
        a.button {
            display: inline-block;
            padding: 6px 12px;
            margin: 2px;
            background: #28a745;
            color: white;
            text-decoration: none;
            border-radius: 4px;
        }
        a.button.delete { background: #dc3545; }
    </style>
</head>
<body>

<h2>Victim List</h2>
<a href="add_victim.php" class="button">Add New Victim</a>

<table>
    <tr>
        <th>ID</th>
        <th>Name</th>
        <th>Age</th>
        <th>Gender</th>
        <th>District</th>
        <th>Disaster</th>
        <th>Actions</th>
    </tr>

<?php
$stmt = $conn->query("
    SELECT v.victim_id, v.name, v.age, v.gender, v.district, d.disaster_name
    FROM victim v
    LEFT JOIN disaster d ON v.disaster_id = d.disaster_id
    ORDER BY v.victim_id DESC
");

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "<tr>";
    echo "<td>{$row['victim_id']}</td>";
    echo "<td>{$row['name']}</td>";
    echo "<td>{$row['age']}</td>";
    echo "<td>{$row['gender']}</td>";
    echo "<td>{$row['district']}</td>";
    echo "<td>{$row['disaster_name']}</td>";
    echo "<td>
            <a class='button' href='edit_victim.php?id={$row['victim_id']}'>Edit</a>
            <a class='button delete' href='delete_victim.php?id={$row['victim_id']}' onclick=\"return confirm('Are you sure?')\">Delete</a>
          </td>";
    echo "</tr>";
}
?>

</table>
</body>
</html>
