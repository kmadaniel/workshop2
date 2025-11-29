<?php
include 'db.php';
session_start();

if ($_SERVER['REQUEST_METHOD']=='POST') {
    $victim_id = $_POST['account_id'];
    $disaster_id = $_POST['disaster_id'];
    $aid_status = 'Pending';
    $reg_date = date('Y-m-d H:i:s');

    // Insert into victim_disaster
    $stmt = $conn->prepare("INSERT INTO victim_disaster(victim_id, disaster_id, aid_status, registration_date) VALUES(?,?,?,?)");
    $stmt->execute([$victim_id, $disaster_id, $aid_status, $reg_date]);

    echo "Your aid request has been recorded!";
}
?>
