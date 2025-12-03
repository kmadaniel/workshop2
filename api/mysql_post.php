<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$apiKey = "kunci_ikmal";

// Check API key from header or query
$key = $_GET['key'] ?? $_SERVER['HTTP_X_API_KEY'] ?? null;

if ($key !== $apiKey) {
    http_response_code(401);
    echo json_encode([
        "status" => "error",
        "message" => "Invalid API Key"
    ]);
    exit;
}

include(__DIR__ . '/../config.php');

// Get request method
$method = $_SERVER['REQUEST_METHOD'];

// Get action from query parameter
$action = $_GET['action'] ?? null;
$table = $_GET['table'] ?? null;

// Allowed tables
$allowed_tables = ['users', 'victims_cache', 'distributions', 'inventory'];

if (!$table || !in_array($table, $allowed_tables)) {
    http_response_code(400);
    echo json_encode([
        "status" => "error",
        "message" => "Valid table parameter required",
        "allowed_tables" => $allowed_tables
    ]);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

switch ($method) {
    case 'POST':
        // CREATE - Insert new record
        if (empty($input)) {
            http_response_code(400);
            echo json_encode([
                "status" => "error",
                "message" => "No data provided"
            ]);
            exit;
        }

        $columns = array_keys($input);
        $values = array_values($input);
        
        // Escape values
        $escaped_values = array_map(function($val) use ($conn) {
            return "'" . $conn->real_escape_string($val) . "'";
        }, $values);

        $sql = "INSERT INTO `$table` (`" . implode("`, `", $columns) . "`) 
                VALUES (" . implode(", ", $escaped_values) . ")";
        
        if ($conn->query($sql)) {
            echo json_encode([
                "status" => "success",
                "message" => "Record created",
                "id" => $conn->insert_id,
                "data" => $input
            ]);
        } else {
            http_response_code(500);
            echo json_encode([
                "status" => "error",
                "message" => "Insert failed: " . $conn->error
            ]);
        }
        break;

    case 'PUT':
        // UPDATE - Update existing record
        $id = $_GET['id'] ?? null;
        
        if (!$id) {
            http_response_code(400);
            echo json_encode([
                "status" => "error",
                "message" => "ID required for update"
            ]);
            exit;
        }

        if (empty($input)) {
            http_response_code(400);
            echo json_encode([
                "status" => "error",
                "message" => "No data provided"
            ]);
            exit;
        }

        $id = (int)$id;
        $set_parts = [];
        
        foreach ($input as $key => $value) {
            $escaped_value = $conn->real_escape_string($value);
            $set_parts[] = "`$key` = '$escaped_value'";
        }

        $sql = "UPDATE `$table` SET " . implode(", ", $set_parts) . " WHERE id = $id";
        
        if ($conn->query($sql)) {
            if ($conn->affected_rows > 0) {
                echo json_encode([
                    "status" => "success",
                    "message" => "Record updated",
                    "id" => $id,
                    "data" => $input
                ]);
            } else {
                http_response_code(404);
                echo json_encode([
                    "status" => "error",
                    "message" => "Record not found or no changes made"
                ]);
            }
        } else {
            http_response_code(500);
            echo json_encode([
                "status" => "error",
                "message" => "Update failed: " . $conn->error
            ]);
        }
        break;

    case 'DELETE':
        // DELETE - Delete record
        $id = $_GET['id'] ?? null;
        
        if (!$id) {
            http_response_code(400);
            echo json_encode([
                "status" => "error",
                "message" => "ID required for delete"
            ]);
            exit;
        }

        $id = (int)$id;
        $sql = "DELETE FROM `$table` WHERE id = $id";
        
        if ($conn->query($sql)) {
            if ($conn->affected_rows > 0) {
                echo json_encode([
                    "status" => "success",
                    "message" => "Record deleted",
                    "id" => $id
                ]);
            } else {
                http_response_code(404);
                echo json_encode([
                    "status" => "error",
                    "message" => "Record not found"
                ]);
            }
        } else {
            http_response_code(500);
            echo json_encode([
                "status" => "error",
                "message" => "Delete failed: " . $conn->error
            ]);
        }
        break;

    default:
        http_response_code(405);
        echo json_encode([
            "status" => "error",
            "message" => "Method not allowed",
            "allowed_methods" => ["POST", "PUT", "DELETE"]
        ]);
        break;
}

$conn->close();
?>