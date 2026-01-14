<?php
// admin/get_audit_details.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once dirname(__DIR__) . '/db.php';

$audit_id = $_GET['audit_id'] ?? 0;

if (!$audit_id) {
    die('No audit ID provided');
}

try {
    $stmt = $conn->prepare("SELECT * FROM audit_log WHERE id = ?");
    $stmt->execute([$audit_id]);
    $log = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$log) {
        die('Audit log not found');
    }
    
    echo '<div style="padding: 20px;">';
    echo '<h4 style="margin-top: 0; color: var(--primary);">Audit Details #' . $log['id'] . '</h4>';
    
    echo '<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 20px;">';
    echo '<div><strong>User:</strong><br>' . htmlspecialchars($log['user_name'] ?? 'System') . '</div>';
    echo '<div><strong>Date:</strong><br>' . $log['created_at'] . '</div>';
    echo '<div><strong>Action:</strong><br>' . htmlspecialchars($log['action_type']) . '</div>';
    echo '<div><strong>IP:</strong><br>' . ($log['ip_address'] ?? 'N/A') . '</div>';
    echo '</div>';
    
    echo '<div style="margin-bottom: 20px;">';
    echo '<strong>Description:</strong>';
    echo '<div style="background: #f8f9fa; padding: 10px; border-radius: 5px; margin-top: 5px;">';
    echo htmlspecialchars($log['action_description']);
    echo '</div>';
    echo '</div>';
    
    if ($log['affected_table']) {
        echo '<div style="margin-bottom: 20px;">';
        echo '<strong>Affected Table:</strong> ' . htmlspecialchars($log['affected_table']);
        if ($log['affected_id']) {
            echo ' (ID: ' . $log['affected_id'] . ')';
        }
        echo '</div>';
    }
    
    if ($log['changes_data']) {
        $changes = json_decode($log['changes_data'], true);
        if ($changes) {
            echo '<div style="margin-bottom: 20px;">';
            echo '<strong>Changes:</strong>';
            echo '<div style="background: #f0f0f0; padding: 10px; border-radius: 5px; margin-top: 5px; font-size: 12px;">';
            echo '<pre style="margin: 0;">' . htmlspecialchars(print_r($changes, true)) . '</pre>';
            echo '</div>';
            echo '</div>';
        }
    }
    
    echo '<div style="text-align: center; margin-top: 20px;">';
    echo '<button onclick="this.closest(\'#auditDetailsModal\').remove()" 
            style="background: var(--primary); color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer;">
            <i class="fas fa-times"></i> Close
          </button>';
    echo '</div>';
    
    echo '</div>';
    
} catch (Exception $e) {
    echo '<div style="color: red; padding: 20px;">Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
}
?>