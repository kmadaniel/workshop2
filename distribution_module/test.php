<?php
header("Content-Type: application/json");

$conn = new mysqli("localhost", "root", "Frero@2950", "distribution");

if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(["error" => "DB connection failed"]);
    exit;
} 

$sql = "SELECT * FROM distribution";
$result = $conn->query($sql);

$distribution = [];

while ($row = $result->fetch_assoc()) {
    $distribution[] = $row;
}

echo json_encode($distribution);