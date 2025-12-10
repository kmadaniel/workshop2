<?php
include "../db.php";

$id = $_GET['id'] ?? null;

if (!$id) {
    die("Invalid Victim ID.");
}

// Delete victim
$stmt = $conn->prepare("DELETE FROM victim WHERE victim_id = ?");
$stmt->execute([$id]);

header("Location: list_victims.php");
exit;
?>
