<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['system_choice'])) {
    $system_choice = $_POST['system_choice'];
    
    // Validate system choice
    if (in_array($system_choice, ['main', 'distribution'])) {
        $_SESSION['system_choice'] = $system_choice;
        
        // Log the switch for tracking
        error_log("User " . ($_SESSION['user_id'] ?? 'unknown') . " switched to system: " . $system_choice);
        
        echo json_encode(['success' => true, 'system' => $system_choice]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid system choice']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
}
?>