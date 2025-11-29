<?php
session_start();
include 'db.php';

if (!isset($_SESSION['account_id'])) {
    header("Location: login.php");
    exit();
}

$account_id = $_SESSION['account_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle selected needs (checkboxes)
    if (!empty($_POST['needs'])) {
        foreach ($_POST['needs'] as $need) {
            if($need === "Other" && !empty($_POST['needs_other'])){
                $need = $_POST['needs_other']; // use custom text
            }
            $stmt = $conn->prepare("INSERT INTO victim_disaster(victim_id, needs, disaster_id, aid_status, registration_date)
                                    VALUES (?, ?, NULL, 'Pending', NOW())");
            $stmt->execute([$account_id, $need]);
        }
        $_SESSION['success'] = "Your needs request has been submitted!";
    } else {
        $_SESSION['success'] = "Please select at least one need.";
    }
}

header("Location: dashboard.php");
exit();
?>
