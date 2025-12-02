<?php
session_start();
include 'db.php';

if (!isset($_SESSION['account_id'])) {
    header("Location: login.php");
    exit();
}

$account_id = $_SESSION['account_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $family_size = $_POST['family_size'] ?? 1;
    $needs = $_POST['needs'] ?? [];
    $shelter_id = $_POST['shelter_id'] ?? null;
    $details = $_POST['details'] ?? '';

    if (!empty($needs) || $shelter_id) {
        foreach ($needs as $need) {
            $stmt = $conn->prepare("
                INSERT INTO victim_disaster(victim_id, needs, disaster_id, aid_status, registration_date)
                VALUES (?, ?, NULL, 'Pending', NOW())
            ");
            $stmt->execute([$account_id, $need . " for $family_size people"]);
        }

        // If shelter selected, also insert as a need
        if ($shelter_id) {
            $stmt = $conn->prepare("
                INSERT INTO victim_disaster(victim_id, needs, disaster_id, aid_status, registration_date)
                VALUES (?, ?, NULL, 'Pending', NOW())
            ");
            $stmt->execute([$account_id, "Shelter: $shelter_id for $family_size people"]);
        }

        $_SESSION['success'] = "Your needs request has been submitted!";
    } else {
        $_SESSION['success'] = "Please select at least one need or shelter.";
    }
}

header("Location: dashboard.php");
exit();
