<?php
/**
 * Coordinator API Endpoint
 * Provides coordinator data in JSON format
 * 
 * Usage:
 * - GET all coordinators: api_coordinator.php
 * - GET specific coordinator: api_coordinator.php?coordinator_id=1
 * - GET by status: api_coordinator.php?status=Active
 * - GET by department: api_coordinator.php?department=Relief Operations
 */

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

// Database connection
$conn = new mysqli("localhost", "root", "Frero@2950", "distribution");

// Check connection
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "error" => "Database connection failed",
        "message" => "Unable to connect to database"
    ]);
    exit;
}

// Set charset to UTF-8
$conn->set_charset("utf8mb4");

try {
    // Build SQL query based on parameters
    $sql = "SELECT 
                coordinator_id,
                name,
                ic_number,
                phone,
                email,
                department,
                position,
                status,
                created_at,
                updated_at
            FROM coordinators";
    
    $conditions = [];
    $params = [];
    $types = "";
    
    // Filter by coordinator_id
    if (isset($_GET['coordinator_id']) && is_numeric($_GET['coordinator_id'])) {
        $conditions[] = "coordinator_id = ?";
        $params[] = intval($_GET['coordinator_id']);
        $types .= "i";
    }
    
    // Filter by status
    if (isset($_GET['status']) && in_array($_GET['status'], ['Active', 'Inactive'])) {
        $conditions[] = "status = ?";
        $params[] = $_GET['status'];
        $types .= "s";
    }
    
    // Filter by department
    if (isset($_GET['department']) && !empty($_GET['department'])) {
        $conditions[] = "department LIKE ?";
        $params[] = "%" . $_GET['department'] . "%";
        $types .= "s";
    }
    
    // Filter by name search
    if (isset($_GET['search']) && !empty($_GET['search'])) {
        $conditions[] = "(name LIKE ? OR email LIKE ? OR phone LIKE ?)";
        $search_term = "%" . $_GET['search'] . "%";
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
        $types .= "sss";
    }
    
    // Add WHERE clause if conditions exist
    if (!empty($conditions)) {
        $sql .= " WHERE " . implode(" AND ", $conditions);
    }
    
    // Add ORDER BY
    $sql .= " ORDER BY status DESC, name ASC";
    
    // Add LIMIT if specified
    if (isset($_GET['limit']) && is_numeric($_GET['limit'])) {
        $sql .= " LIMIT ?";
        $params[] = intval($_GET['limit']);
        $types .= "i";
    }
    
    // Prepare and execute query
    if (!empty($params)) {
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Query preparation failed: " . $conn->error);
        }
        
        // Bind parameters dynamically
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $result = $conn->query($sql);
        if (!$result) {
            throw new Exception("Query execution failed: " . $conn->error);
        }
    }
    
    // Fetch all coordinators
    $coordinators = [];
    while ($row = $result->fetch_assoc()) {
        $coordinators[] = $row;
    }
    
    // Prepare response
    $response = [
        "success" => true,
        "count" => count($coordinators),
        "data" => $coordinators
    ];
    
    // If searching for specific coordinator and found
    if (isset($_GET['coordinator_id']) && count($coordinators) === 1) {
        $response["coordinator"] = $coordinators[0];
    }
    
    // Add summary statistics if requested
    if (isset($_GET['include_stats']) && $_GET['include_stats'] == 'true') {
        $stats_sql = "SELECT 
                        COUNT(*) as total_coordinators,
                        SUM(CASE WHEN status = 'Active' THEN 1 ELSE 0 END) as active_coordinators,
                        SUM(CASE WHEN status = 'Inactive' THEN 1 ELSE 0 END) as inactive_coordinators,
                        COUNT(DISTINCT department) as total_departments
                      FROM coordinators";
        
        $stats_result = $conn->query($stats_sql);
        $stats = $stats_result->fetch_assoc();
        $response["statistics"] = $stats;
    }
    
    // Add departments list if requested
    if (isset($_GET['include_departments']) && $_GET['include_departments'] == 'true') {
        $dept_sql = "SELECT DISTINCT department 
                     FROM coordinators 
                     WHERE department IS NOT NULL AND department != '' 
                     ORDER BY department";
        $dept_result = $conn->query($dept_sql);
        
        $departments = [];
        while ($dept = $dept_result->fetch_assoc()) {
            $departments[] = $dept['department'];
        }
        $response["departments"] = $departments;
    }
    
    // Return JSON response
    http_response_code(200);
    echo json_encode($response, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "error" => "Server error",
        "message" => $e->getMessage()
    ]);
} finally {
    // Close statement if exists
    if (isset($stmt)) {
        $stmt->close();
    }
    
    // Close connection
    $conn->close();
}
?>