<?php
header("Content-Type: application/json");

// Connection SQL Server
$serverName = "localhost";
$connectionOptions = array(
    "Database" => "UserManagement",
    "Uid" => "yanadb",
    "PWD" => "yana123",
    "CharacterSet" => "UTF-8"
);

$conn = sqlsrv_connect($serverName, $connectionOptions);

if (!$conn) {
    http_response_code(500);
    echo json_encode(["error" => "DB connection failed"]);
    exit;
}

// 🔹 Query guna schema dbo (Admin)
$sql = "SELECT * FROM dbo.Admin";
$stmt = sqlsrv_query($conn, $sql);

if ($stmt === false) {
    http_response_code(500);
    echo json_encode(["error" => "Query failed"]);
    exit;
}

$admin = [];
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $admin[] = $row;
}

// 🔹 Return data admin dalam JSON
echo json_encode($admin);

sqlsrv_close($conn);
?>
 