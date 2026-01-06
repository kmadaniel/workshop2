<?php
session_start();
require_once '../config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['volunteer_id'])) {
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

$distribution_id = filter_var($_GET['distribution_id'], FILTER_VALIDATE_INT);
if (!$distribution_id) {
    echo json_encode(['error' => 'Invalid distribution ID']);
    exit;
}

$database = new Database();
$db = $database->getConnection();

$stats_query = "
    SELECT 
        COUNT(DISTINCT n.victim_id) as total_victims,
        COUNT(DISTINCT CASE WHEN n.status = 'Fulfilled' THEN n.victim_id END) as completed_victims,
        (COUNT(DISTINCT n.victim_id) - COUNT(DISTINCT CASE WHEN n.status = 'Fulfilled' THEN n.victim_id END)) as pending_victims,
        COUNT(DISTINCT CASE WHEN n.status = 'Fulfilled' THEN n.need_id END) as fulfilled_needs,
        COUNT(DISTINCT n.need_id) as total_needs
    FROM needs n
    WHERE n.distribution_id = ?
";

$stmt = $db->prepare($stats_query);
$stmt->bind_param("i", $distribution_id);
$stmt->execute();
$result = $stmt->get_result();
$stats = $result->fetch_assoc();
$stmt->close();

echo json_encode($stats);
?>