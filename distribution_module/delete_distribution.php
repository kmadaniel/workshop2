<?php
// delete_distribution.php - AJAX VERSION
require_once 'config.php';

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Set header for JSON response
header('Content-Type: application/json');

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Check if request is POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method. Use POST.');
    }
    
    // Get distribution ID from POST
    $distribution_id = isset($_POST['distribution_id']) ? intval($_POST['distribution_id']) : 0;
    $confirm = isset($_POST['confirm_delete']) ? intval($_POST['confirm_delete']) : 0;
    
    if ($distribution_id <= 0) {
        throw new Exception('Invalid distribution ID: ' . $distribution_id);
    }
    
    if ($confirm !== 1) {
        throw new Exception('Confirmation required. Set confirm_delete=1');
    }
    
    // First, check if distribution exists
    $check_sql = "SELECT distribution_id, date, status, disaster_id, comments FROM distribution WHERE distribution_id = ?";
    $check_stmt = $db->prepare($check_sql);
    if (!$check_stmt) {
        throw new Exception("Prepare failed: " . $db->error);
    }
    
    $check_stmt->bind_param("i", $distribution_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows === 0) {
        throw new Exception("Distribution #{$distribution_id} not found in database.");
    }
    
    $distribution_data = $check_result->fetch_assoc();
    $check_stmt->close();
    
    // Log what we're about to delete
    error_log("Attempting to delete distribution #{$distribution_id} (Status: {$distribution_data['status']})");
    
    // Start transaction
    $db->begin_transaction();
    
    try {
        $total_deleted = 0;
        
        // 1. Delete from related tables first (in correct order to avoid foreign key constraints)
        // Start with the deepest child tables and work up to parent
        $tables_to_check = ['distribution_log', 'distribution_volunteer', 'Needs', 'distribution_items'];
        
        foreach ($tables_to_check as $table) {
            // Check if table exists
            $table_check = $db->query("SHOW TABLES LIKE '$table'");
            if ($table_check && $table_check->num_rows > 0) {
                // Use DELETE for foreign key constraints
                $sql = "DELETE FROM $table WHERE distribution_id = ?";
                $stmt = $db->prepare($sql);
                if ($stmt) {
                    $stmt->bind_param("i", $distribution_id);
                    $stmt->execute();
                    $affected = $stmt->affected_rows;
                    $stmt->close();
                    $total_deleted += $affected;
                    error_log("Deleted {$affected} records from {$table} for distribution #{$distribution_id}");
                } else {
                    error_log("Prepare failed for table '{$table}': " . $db->error);
                }
            } else {
                error_log("Table '{$table}' does not exist or cannot be accessed");
            }
        }
        
        // 2. Now delete the distribution itself (parent table)
        $sql_final = "DELETE FROM distribution WHERE distribution_id = ?";
        $stmt_final = $db->prepare($sql_final);
        if (!$stmt_final) {
            throw new Exception("Prepare failed for distribution delete: " . $db->error);
        }
        
        $stmt_final->bind_param("i", $distribution_id);
        $stmt_final->execute();
        
        $affected_final = $stmt_final->affected_rows;
        $stmt_final->close();
        
        if ($affected_final > 0) {
            $db->commit();
            $total_deleted += $affected_final;
            
            error_log("SUCCESS: Deleted distribution #{$distribution_id}. Total affected rows: {$total_deleted}");
            
            echo json_encode([
                'success' => true,
                'message' => "Distribution #{$distribution_id} deleted successfully!",
                'deleted_id' => $distribution_id,
                'total_deleted' => $total_deleted
            ]);
        } else {
            throw new Exception('Failed to delete distribution - no rows affected in distribution table.');
        }
        
    } catch (Exception $e) {
        $db->rollback();
        throw $e;
    }
    
} catch (Exception $e) {
    http_response_code(500);
    error_log("Delete distribution error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage(),
        'error' => $e->getMessage()
    ]);
}
?>