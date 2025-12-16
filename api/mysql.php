<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

$apiKey = "kunci_ikmal"; 

// Check if the request includes the key
if (!isset($_GET['key']) || $_GET['key'] !== $apiKey) {
    http_response_code(401);
    echo json_encode([
        "status" => "error",
        "message" => "Invalid API Key"
    ]);
    exit;
}

include(__DIR__ . '/../distribution_module/config.php');

// Read parameters
$action = $_GET['action'] ?? 'list';
$table  = $_GET['table']  ?? 'distribution';
$id     = $_GET['id']     ?? null;
$search = $_GET['q']      ?? "";
$limit  = min((int)($_GET['limit'] ?? 100), 1000);
$offset = (int)($_GET['offset'] ?? 0);

// Allowed tables for security
$allowed_tables = ['users', 'victim', 'distribution', 'resource', 'disaster', 'needs'];

if (!in_array($table, $allowed_tables)) {
    http_response_code(400);
    echo json_encode([
        "status" => "error",
        "message" => "Table not allowed"
    ]);
    exit;
}

switch ($action) {
    case 'list':
        $sql = "SELECT * FROM `$table` LIMIT $limit OFFSET $offset";
        $result = $conn->query($sql);
        if (!$result) {
            http_response_code(500);
            echo json_encode([
                "status" => "error",
                "message" => "Query failed: " . $conn->error
            ]);
            exit;
        }
        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }

        $countResult = $conn->query("SELECT COUNT(*) AS total FROM `$table`");
        $count = $countResult ? $countResult->fetch_assoc()['total'] : 0;

        echo json_encode([
            "status" => "success",
            "count" => count($data),
            "total" => (int)$count,
            "data" => $data
        ]);
        break;

    case 'get':
        if (!$id) {
            http_response_code(400);
            echo json_encode([
                "status" => "error",
                "message" => "ID required"
            ]);
            exit;
        }
        $id = (int)$id;
        $sql = "SELECT * FROM `$table` WHERE id = $id";
        $result = $conn->query($sql);
        if (!$result || $result->num_rows === 0) {
            http_response_code(404);
            echo json_encode([
                "status" => "error",
                "message" => "Record not found"
            ]);
            exit;
        }
        echo json_encode([
            "status" => "success",
            "data" => $result->fetch_assoc()
        ]);
        break;

    case 'search':
        $search = $conn->real_escape_string($search);
        $sql = "SELECT * FROM `$table` WHERE 1=1";
        
        // Add search conditions based on table
        if (!empty($search)) {
            if ($table == 'victim') {
                $sql .= " AND (name LIKE '%$search%' OR address LIKE '%$search%')";
            } elseif ($table == 'disaster') {
                $sql .= " AND (Disaster_Name LIKE '%$search%' OR Location LIKE '%$search%')";
            } elseif ($table == 'resource') {
                $sql .= " AND (name LIKE '%$search%' OR type LIKE '%$search%')";
            }
        }
        
        $sql .= " LIMIT $limit";
        $result = $conn->query($sql);
        
        if (!$result) {
            http_response_code(500);
            echo json_encode([
                "status" => "error",
                "message" => "Query failed: " . $conn->error
            ]);
            exit;
        }
        
        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        
        echo json_encode([
            "status" => "success",
            "count" => count($data),
            "query" => $search,
            "data" => $data
        ]);
        break;

    default:
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "Invalid action",
            "available_actions" => ["list", "get", "search"]
        ]);
        break;
}
?>