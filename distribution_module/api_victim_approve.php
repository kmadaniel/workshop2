<?php
// victim_approvals_api.php
header("Content-Type: application/json");

$conn = new mysqli("localhost", "root", "Frero@2950", "distribution");

if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(["error" => "DB connection failed"]);
    exit;
} 

$sql = "SELECT * FROM victim_approvals";
$result = $conn->query($sql);

$victim_approvals = [];

while ($row = $result->fetch_assoc()) {
    $victim_approvals[] = $row;
}

echo json_encode($victim_approvals);

$conn->close();
?>