<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");

include "db.php"; // Your PDO connection

try {
    // Conditions array - ALWAYS require distribution_id and resource_id to be NOT NULL
    $conditions = ["n.distribution_id IS NOT NULL", "n.resource_id IS NOT NULL"];
    $params = [];

    // Filter: special needs (baby, elderly, disabled)
    if (isset($_GET['special']) && $_GET['special'] == 1) {
        $conditions[] = "(v.has_baby = true OR v.has_elderly = true OR v.has_disabled = true)";
    }

    // Filter: disaster ID
    if (!empty($_GET['disaster'])) {
        $conditions[] = "n.disaster_id = :disaster_id";
        $params[':disaster_id'] = $_GET['disaster'];
    }

    // Filter: victim ID
    if (!empty($_GET['victim'])) {
        $conditions[] = "n.victim_id = :victim_id";
        $params[':victim_id'] = $_GET['victim'];
    }

    // Filter: priority
    if (!empty($_GET['priority'])) {
        $conditions[] = "n.priority = :priority";
        $params[':priority'] = $_GET['priority'];
    }

    // Filter: status
    if (!empty($_GET['status'])) {
        $conditions[] = "n.status = :status";
        $params[':status'] = $_GET['status'];
    }

    // Filter: date range
    if (!empty($_GET['start_date'])) {
        $conditions[] = "n.created_at >= :start_date";
        $params[':start_date'] = $_GET['start_date'];
    }
    if (!empty($_GET['end_date'])) {
        $conditions[] = "n.created_at <= :end_date";
        $params[':end_date'] = $_GET['end_date'];
    }

    // Build WHERE clause
    $whereSQL = "WHERE " . implode(" AND ", $conditions);

    // Main query - SIMPLIFIED for your database structure
    $sql = "
        SELECT
            n.need_id,
            n.victim_id,
            n.disaster_id,
            n.distribution_id,
            n.resource_id,
            n.priority,
            n.status,
            n.quantity_needed,
            n.created_at,
            n.temp_resource_name,
            v.has_baby,
            v.has_elderly,
            v.has_disabled
        FROM public.needs n
        JOIN public.victim v ON v.victim_id = n.victim_id
        $whereSQL
        ORDER BY 
            CASE n.priority 
                WHEN 'High' THEN 1
                WHEN 'Medium' THEN 2
                WHEN 'Low' THEN 3
                ELSE 4
            END,
            n.created_at ASC
    ";

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);

    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get summary statistics
    $summarySql = "
        SELECT 
            COUNT(*) as total_needs,
            SUM(CASE WHEN n.priority = 'High' THEN 1 ELSE 0 END) as high_priority,
            SUM(CASE WHEN n.priority = 'Medium' THEN 1 ELSE 0 END) as medium_priority,
            SUM(CASE WHEN n.priority = 'Low' THEN 1 ELSE 0 END) as low_priority,
            COUNT(DISTINCT n.victim_id) as unique_victims,
            COUNT(DISTINCT n.disaster_id) as unique_disasters,
            COUNT(DISTINCT n.distribution_id) as unique_distributions,
            COUNT(DISTINCT n.resource_id) as unique_resources
        FROM public.needs n
        $whereSQL
    ";

    $summaryStmt = $conn->prepare($summarySql);
    $summaryStmt->execute($params);
    $summary = $summaryStmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        "success" => true,
        "total" => count($data),
        "summary" => $summary,
        "data" => $data
    ], JSON_PRETTY_PRINT);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "error" => $e->getMessage()
    ]);
}
?>