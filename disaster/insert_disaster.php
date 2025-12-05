<?php
include "../db.php";

try {
    $sql = "INSERT INTO disaster 
    (disaster_name, description, district, severity, alert_message, created_at,
     start_date, end_date, affected_people, status)
    VALUES
    (:name, :description, :district, :severity, :alert_message, NOW(),
     :start_date, :end_date, :affected_people, 'Active')";

    $stmt = $conn->prepare($sql);
    $stmt->execute([
        ':name' => $_POST['disaster_name'],
        ':description' => $_POST['description'],
        ':district' => $_POST['district'],
        ':severity' => $_POST['severity'],
        ':alert_message' => $_POST['alert_message'],
        ':start_date' => $_POST['start_date'],
        ':end_date' => $_POST['end_date'],
        ':affected_people' => $_POST['affected_people']
    ]);

    header("Location: list_disaster.php?success=1");
    exit;

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
