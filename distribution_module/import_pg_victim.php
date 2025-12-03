<?php
// ========== MYSQL CONNECTION (YOUR DB) ==========
$mysql_host = "localhost";
$mysql_user = "root";
$mysql_pass = "Frero@2950";
$mysql_db   = "distribution";

$mysql = new mysqli($mysql_host, $mysql_user, $mysql_pass, $mysql_db);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$mysql->set_charset("utf8mb4");

// ========== POSTGRES CONNECTION (FRIEND DB) ==========
$pg_host = "localhost"; // <- Change to friend's IP if remote
$pg_port = "5433";
$pg_dbname = "victimdisaster";
$pg_user = "postgres";
$pg_pass = "0212";

try {
    $pg = new PDO("pgsql:host=$pg_host;port=$pg_port;dbname=$pg_dbname;", $pg_user, $pg_pass);
    $pg->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("PostgreSQL connection failed: " . $e->getMessage());
}

// ========== FETCH FROM POSTGRES ==========

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

$pg_stmt = $pg->query($sql);
$rows = $pg_stmt->fetchAll(PDO::FETCH_ASSOC);

echo "PostgreSQL rows found: " . count($rows) . "<br>";

// ========== INSERT INTO MYSQL ==========

$insert = $mysql->prepare("
    INSERT INTO victim (
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
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");

foreach ($rows as $v) {
    $insert->bind_param(
        "iisisissis",
        $v['victim_id'],
        $v['account_id'],
        $v['name'],
        $v['age'],
        $v['gender'],
        $v['phone_num'],
        $v['address'],
        $v['needs'],
        $v['location_id'],
        $v['created_at']
    );

    $insert->execute();
}

echo "<br>Data imported successfully from PostgreSQL → MySQL!";
