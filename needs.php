<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");

// Check if db.php exists
if (!file_exists("db.php")) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "error" => "Database configuration file not found"
    ]);
    exit();
}

include "db.php"; // Your PDO connection

// Check if connection was established
if (!isset($conn) || !$conn) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "error" => "Database connection failed"
    ]);
    exit();
}

try {
    // Conditions array
    $conditions = ["1=1"]; // Always true condition
    $params = [];

    // Filter: special needs (baby, elderly, disabled)
    if (isset($_GET['special']) && $_GET['special'] == 1) {
        $conditions[] = "(v.has_baby = true OR v.has_elderly = true OR v.has_disabled = true)";
    }

    // Filter: disaster ID
    if (!empty($_GET['disaster'])) {
        $conditions[] = "n.disaster_id = :disaster_id";
        $params[':disaster_id'] = (int)$_GET['disaster'];
    }

    // Filter: victim ID
    if (!empty($_GET['victim'])) {
        $conditions[] = "n.victim_id = :victim_id";
        $params[':victim_id'] = (int)$_GET['victim'];
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
        $params[':start_date'] = $_GET['start_date'] . ' 00:00:00';
    }
    if (!empty($_GET['end_date'])) {
        $conditions[] = "n.created_at <= :end_date";
        $params[':end_date'] = $_GET['end_date'] . ' 23:59:59';
    }

    // Filter: resource ID
    if (!empty($_GET['resource_id'])) {
        $conditions[] = "n.resource_id = :resource_id";
        $params[':resource_id'] = (int)$_GET['resource_id'];
    }

    // Filter: distribution ID
    if (!empty($_GET['distribution_id'])) {
        $conditions[] = "n.distribution_id = :distribution_id";
        $params[':distribution_id'] = (int)$_GET['distribution_id'];
    }

    // Filter: shelter selection
    if (!empty($_GET['shelter'])) {
        $conditions[] = "n.selected_shelter = :shelter";
        $params[':shelter'] = $_GET['shelter'];
    }

    // Build WHERE clause
    $whereSQL = "WHERE " . implode(" AND ", $conditions);

    // Main query - Only include columns that exist
    $sql = "
        SELECT
            n.need_id,
            n.victim_id,
            n.disaster_id,
            n.priority,
            n.status,
            n.quantity_needed,
            n.created_at,
            n.temp_resource_name,
            n.resource_id,
            n.distribution_id,
            n.special_needs_quantity,
            n.normal_needs_quantity,
            n.special_needs_requests,
            n.selected_shelter,
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

    // For debugging - show SQL if debug parameter is set
    if (isset($_GET['debug']) && $_GET['debug'] == 1) {
        echo json_encode([
            "sql" => $sql,
            "params" => $params,
            "where_clause" => $whereSQL
        ]);
        exit();
    }

    $stmt = $conn->prepare($sql);
    
    // Bind parameters
    foreach ($params as $key => $value) {
        if (is_int($value)) {
            $stmt->bindValue($key, $value, PDO::PARAM_INT);
        } else {
            $stmt->bindValue($key, $value, PDO::PARAM_STR);
        }
    }
    
    $stmt->execute();
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Summary for needs - Include distribution and resource counts
    $summarySql = "
        SELECT 
            COUNT(*) as total_needs,
            SUM(CASE WHEN n.priority = 'High' THEN 1 ELSE 0 END) as high_priority,
            SUM(CASE WHEN n.priority = 'Medium' THEN 1 ELSE 0 END) as medium_priority,
            SUM(CASE WHEN n.priority = 'Low' THEN 1 ELSE 0 END) as low_priority,
            COUNT(DISTINCT n.victim_id) as unique_victims_with_needs,
            COUNT(DISTINCT n.disaster_id) as unique_disasters,
            COUNT(DISTINCT n.distribution_id) as unique_distributions,
            COUNT(DISTINCT n.resource_id) as unique_resources,
            COALESCE(SUM(n.quantity_needed), 0) as total_quantity_needed,
            COALESCE(SUM(n.special_needs_quantity), 0) as total_special_quantity,
            COALESCE(SUM(n.normal_needs_quantity), 0) as total_normal_quantity,
            COUNT(DISTINCT n.selected_shelter) as unique_shelters
        FROM public.needs n
        JOIN public.victim v ON v.victim_id = n.victim_id
        $whereSQL
    ";
    
    $summaryStmt = $conn->prepare($summarySql);
    
    // Bind parameters for summary query
    foreach ($params as $key => $value) {
        if (is_int($value)) {
            $summaryStmt->bindValue($key, $value, PDO::PARAM_INT);
        } else {
            $summaryStmt->bindValue($key, $value, PDO::PARAM_STR);
        }
    }
    
    $summaryStmt->execute();
    $summary = $summaryStmt->fetch(PDO::FETCH_ASSOC);

    // Always count total victims from victim table
    $victimSummarySql = "SELECT COUNT(*) as total_victims FROM public.victim";
    $victimStmt = $conn->query($victimSummarySql);
    $victimSummary = $victimStmt->fetch(PDO::FETCH_ASSOC);

    // Count victims with special needs
    $specialNeedsSql = "SELECT 
        COUNT(*) as total_special_needs_victims
        FROM public.victim 
        WHERE has_baby = true OR has_elderly = true OR has_disabled = true";
    
    $specialStmt = $conn->query($specialNeedsSql);
    $specialSummary = $specialStmt->fetch(PDO::FETCH_ASSOC);

    // Merge summaries
    $summary['total_victims'] = $victimSummary['total_victims'] ?? 0;
    $summary['total_special_needs_victims'] = $specialSummary['total_special_needs_victims'] ?? 0;
    
    // Calculate percentage of victims with needs
    if ($summary['total_victims'] > 0) {
        $summary['victims_with_needs_percentage'] = round(
            ($summary['unique_victims_with_needs'] / $summary['total_victims']) * 100, 
            2
        );
    } else {
        $summary['victims_with_needs_percentage'] = 0;
    }

    // Calculate percentage of special needs victims
    if ($summary['total_victims'] > 0) {
        $summary['special_needs_percentage'] = round(
            ($summary['total_special_needs_victims'] / $summary['total_victims']) * 100, 
            2
        );
    } else {
        $summary['special_needs_percentage'] = 0;
    }

    // Add filter info to response
    $filters = [];
    foreach ($_GET as $key => $value) {
        if (!empty($value) && $key !== 'debug') {
            $filters[$key] = $value;
        }
    }

    echo json_encode([
        "success" => true,
        "total" => count($data),
        "summary" => $summary,
        "filters_applied" => $filters,
        "data" => $data
    ], JSON_PRETTY_PRINT);

} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    http_response_code(500);
    
    // For debugging - show detailed error
    if (isset($_GET['debug']) && $_GET['debug'] == 1) {
        echo json_encode([
            "success" => false,
            "error" => "Database error occurred",
            "debug" => [
                "message" => $e->getMessage(),
                "sql" => $sql ?? "Not available",
                "params" => $params ?? [],
                "trace" => $e->getTraceAsString()
            ]
        ]);
    } else {
        echo json_encode([
            "success" => false,
            "error" => "Database error occurred"
        ]);
    }
} catch (Exception $e) {
    error_log("General error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "error" => "An error occurred"
    ]);
}