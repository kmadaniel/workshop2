<?php
require_once 'config.php';

session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['volunteer_id']) && !isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$user_id = isset($_SESSION['volunteer_id']) ? $_SESSION['volunteer_id'] : $_SESSION['user_id'];

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $query = "UPDATE notifications SET read_status = 1 WHERE user_id = ?";
    $stmt = $db->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->close();
    
    echo json_encode(['success' => true, 'message' => 'Notifications marked as read']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>