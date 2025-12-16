<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

// API Key check
$apiKey = "kunci_ikmal";
if (!isset($_GET['key']) || $_GET['key'] !== $apiKey) {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Invalid API Key"]);
    exit;
}

// Database connection
$host = "localhost";
$user = "root";
$pass = "Frero@2950";
$db = "distribution";

try {
    $conn = new mysqli($host, $user, $pass, $db);
    $conn->set_charset("utf8mb4");
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "DB connection failed: " . $e->getMessage()]);
    exit;
}

// Get parameters
$action = $_GET['action'] ?? 'list';
$table = $_GET['table'] ?? 'distribution';
$limit = min((int)($_GET['limit'] ?? 100), 1000);
$offset = (int)($_GET['offset'] ?? 0);

// Allowed tables
$allowed_tables = ['distribution', 'victim', 'disaster', 'resource', 'needs'];
if (!in_array($table, $allowed_tables)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Table not allowed"]);
    exit;
}

// Execute query
if ($action === 'list') {
    $sql = "SELECT * FROM `$table` LIMIT $limit OFFSET $offset";
    $result = $conn->query($sql);
    
    if (!$result) {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Query failed: " . $conn->error]);
        exit;
    }
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    
    $countResult = $conn->query("SELECT COUNT(*) AS total FROM `$table`");
    $total = $countResult ? $countResult->fetch_assoc()['total'] : 0;
    
    echo json_encode([
        "status" => "success",
        "count" => count($data),
        "total" => (int)$total,
        "data" => $data
    ]);
} else {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Invalid action"]);
}
?>
```

**Test this new API:**
```
http://localhost:8000/workshop2/api/mysql_new.php?key=kunci_ikmal&action=list&table=distribution