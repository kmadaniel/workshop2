<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");

// Connect to your friend's database
$conn = new mysqli("localhost", "root", "Frero@2950", "distribution");

if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(["error" => "DB connection failed"]);
    exit;
}

// Fetch needs with related data
$sql = "SELECT 
            n.*,
            v.name as victim_name,
            v.address,
            v.age,
            v.family_size,
            r.name as resource_name,
            r.type as resource_type,
            r.unit,
            r.quantity_available
        FROM needs n
        LEFT JOIN victim v ON n.victim_id = v.victim_id
        LEFT JOIN resource r ON n.resource_id = r.resource_id
        ORDER BY n.need_id";

$result = $conn->query($sql);

$needs = [];
while ($row = $result->fetch_assoc()) {
    $needs[] = $row;
}

echo json_encode($needs);
$conn->close();
?>