<?php
// api_reports.php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST");

include_once 'report_config.php';

$database = new Database();
$db = $database->getConnection();

$request_method = $_SERVER["REQUEST_METHOD"];

if ($request_method == 'GET') {
    // Fetch all reports using Stored Procedure
    try {
        // Call the PostgreSQL function
        $query = "SELECT * FROM get_generated_reports_history()";
        
        $stmt = $db->prepare($query);
        $stmt->execute();
        $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($reports);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["message" => "Error: " . $e->getMessage()]);
    }
}
elseif ($request_method == 'POST') {
    // Log a new report using Stored Procedure
    $data = json_decode(file_get_contents("php://input"));

    if (!empty($data->report_type)) {
        try {
            // Call the PostgreSQL function
            $query = "SELECT log_generated_report(:type, :by, :desc)";
            
            $stmt = $db->prepare($query);
            
            $type = htmlspecialchars(strip_tags($data->report_type));
            $by = htmlspecialchars(strip_tags($data->generated_by));
            $desc = htmlspecialchars(strip_tags($data->description));

            $stmt->bindParam(":type", $type);
            $stmt->bindParam(":by", $by);
            $stmt->bindParam(":desc", $desc);

            if ($stmt->execute()) {
                http_response_code(201);
                echo json_encode(["message" => "Report logged successfully via Stored Procedure."]);
            } else {
                http_response_code(503);
                echo json_encode(["message" => "Unable to log report."]);
            }
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(["message" => "Error: " . $e->getMessage()]);
        }
    } else {
        http_response_code(400);
        echo json_encode(["message" => "Incomplete data."]);
    }
}
?>