<?php
// POSTGRES CONNECTION (YOUR FRIEND)
$pg_host = "localhost"; // if remote = friend's IP
$pg_port = "5433";
$pg_dbname = "victimdisaster";
$pg_user = "postgres";
$pg_pass = "0212";

try {
    $pg = new PDO("pgsql:host=$pg_host;port=$pg_port;dbname=$pg_dbname;", $pg_user, $pg_pass);
    $pg->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("PostgreSQL Connection Failed: " . $e->getMessage());
}

// FETCH DATA FROM POSTGRES
$sql = "SELECT 
            victim_id,
            account_id,
            name,
            age,
            gender,
            phone_num,
            address,
            needs,
            location_id,
            created_at
        FROM victim";

$stmt = $pg->query($sql);
$victims = $stmt->fetchAll(PDO::FETCH_ASSOC);

// DISPLAY TABLE
echo "<h2>Victim List (From PostgreSQL)</h2>";
echo "<table border='1' cellpadding='8'>";
echo "<tr>
        <th>ID</th>
        <th>Name</th>
        <th>Age</th>
        <th>Gender</th>
        <th>Phone</th>
        <th>Address</th>
        <th>Needs</th>
        <th>Location</th>
        <th>Created At</th>
      </tr>";

foreach ($victims as $v) {
    echo "<tr>
            <td>{$v['victim_id']}</td>
            <td>{$v['name']}</td>
            <td>{$v['age']}</td>
            <td>{$v['gender']}</td>
            <td>{$v['phone_num']}</td>
            <td>{$v['address']}</td>
            <td>{$v['needs']}</td>
            <td>{$v['location_id']}</td>
            <td>{$v['created_at']}</td>
          </tr>";
}

echo "</table>";
?>
