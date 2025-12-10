<?php 
include "../db.php"; 
?>

<!DOCTYPE html>
<html>
<head>
    <title>Victim List</title>
    <style>
        body { font-family: Arial; background: #800080; padding: 20px; color: #333; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; background: #fff; }
        th, td { padding: 12px; border: 1px solid #ccc; text-align: left; }
        th { background: #4b0082; color: white; }
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
        h2 { color: #fff; }
        a.add-btn {
            display: inline-block;
            margin-bottom: 10px;
            padding: 8px 16px;
            background: #007bff;
            color: white;
            border-radius: 4px;
            text-decoration: none;
        }
    </style>
</head>
<body>

<h2>Victim List</h2>
<a href="add_victim.php" class="add-btn">Add New Victim</a>

<table>
    <tr>
        <th>ID</th>
        <th>Name</th>
        <th>Age</th>
        <th>Gender</th>
        <th>District</th>
        <th>Actions</th>
    </tr>

<?php
// Fetch all victims
$stmt = $conn->query("
    SELECT victim_id, name, age, gender, district
    FROM victim
    ORDER BY victim_id DESC
");

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "<tr>";
    echo "<td>{$row['victim_id']}</td>";
    echo "<td>{$row['name']}</td>";
    echo "<td>{$row['age']}</td>";
    echo "<td>{$row['gender']}</td>";
    echo "<td>{$row['district']}</td>";
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
