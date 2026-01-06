<?php
header("Content-Type: application/json");

$conn = new mysqli("localhost", "root", "Frero@2950", "distribution");

if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(["error" => "DB connection failed"]);
    exit;
}

$sql = "SELECT * FROM distribution_resources";
$result = $conn->query($sql);

$resources = [];

while ($row = $result->fetch_assoc()) {
    $resources[] = $row;
}

echo json_encode($resources);
$conn->close();