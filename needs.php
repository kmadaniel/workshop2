<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");

include "db.php"; // PDO + PostgreSQL

try {

    /*
      Optional filters:
      ?special=1   → only baby / elderly / disabled
      ?disaster=5  → only disaster_id = 5
    */

    $conditions = [];
    $params = [];

    if (isset($_GET['special']) && $_GET['special'] == 1) {
        $conditions[] = "(v.has_baby = true OR v.has_elderly = true OR v.has_disabled = true)";
    }

    if (!empty($_GET['disaster'])) {
        $conditions[] = "n.disaster_id = :disaster_id";
        $params[':disaster_id'] = $_GET['disaster'];
    }

    $whereSQL = "";
    if ($conditions) {
        $whereSQL = "WHERE " . implode(" AND ", $conditions);
    }

    $sql = "
        SELECT
            n.need_id,
            n.victim_id,
            n.disaster_id,
            n.distribution_id,
            n.item_description,
            n.priority,
            n.status AS need_status,
            n.quantity_needed,
            n.created_at AS need_created_at,

            v.has_baby,
            v.has_elderly,
            v.has_disabled,

            r.resource_id,
            r.name AS resource_name,
            r.type AS resource_type,
            r.unit,

            d.status AS distribution_status,
            d.distribution_date

        FROM needs n
        JOIN victim v ON v.victim_id = n.victim_id
        JOIN resources r ON r.resource_id = n.resource_id
        JOIN distribution d ON d.distribution_id = n.distribution_id

        $whereSQL
        ORDER BY n.created_at ASC
    ";

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);

    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "success" => true,
        "total" => count($data),
        "data" => $data
    ], JSON_PRETTY_PRINT);

} catch (PDOException $e) {

    http_response_code(500);
    echo json_encode([
        "success" => false,
        "error" => $e->getMessage()
    ]);
}
