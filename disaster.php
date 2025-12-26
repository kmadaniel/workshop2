<?php
header("Content-Type: application/json");

// ✅ ADD port=5433
    $conn = pg_connect("host=localhost port=5433 dbname=victimdisaster user=postgres password=0212");


if (!$conn) {
    http_response_code(500);
    echo json_encode(["error" => "DB connection failed"]);
    exit;
}

$result = pg_query($conn, "SELECT * FROM disaster");

$disaster = [];
while ($row = pg_fetch_assoc($result)) {
    $disaster[] = $row;
}

echo json_encode($disaster);
