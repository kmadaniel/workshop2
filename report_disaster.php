<?php
session_start();
include 'db.php';

if (!isset($_SESSION['account_id'])) {
    header("Location: login.php");
    exit();
}

$account_id = $_SESSION['account_id'];

// Get victim location
$stmt = $conn->prepare("SELECT location_id FROM victim WHERE account_id=?");
$stmt->execute([$account_id]);
$victim = $stmt->fetch(PDO::FETCH_ASSOC);

if($_SERVER['REQUEST_METHOD'] === 'POST'){
    $disaster_name = $_POST['disaster_name'];
    $severity = $_POST['severity_level'];
    $description = $_POST['description'];

    $alert_message = "Severity: $severity. " . $description;

    // Insert into disaster table with location_id
    $stmt = $conn->prepare("
        INSERT INTO disaster(disaster_name, description, alert_message, location_id)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([$disaster_name, $description, $alert_message, $victim['location_id']]);

    $_SESSION['success'] = "Disaster reported successfully!";
}

header("Location: dashboard.php");
exit();
