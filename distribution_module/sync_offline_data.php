<?php
session_start();
require_once 'config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['volunteer_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['distribution_id']) || !isset($input['volunteer_id']) || !isset($input['data'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid input']);
    exit;
}

$database = new Database();
$db = $database->getConnection();

$synced_count = 0;
$errors = [];

try {
    $db->begin_transaction();
    
    foreach ($input['data'] as $offlineDist) {
        // Validate offline distribution data
        if (!isset($offlineDist['victim_id']) || !isset($offlineDist['distributed_items']) || !is_array($offlineDist['distributed_items'])) {
            $errors[] = "Invalid offline data structure for victim";
            continue;
        }
        
        $victim_id = (int)$offlineDist['victim_id'];
        $timestamp = isset($offlineDist['timestamp']) ? $offlineDist['timestamp'] : date('Y-m-d H:i:s');
        $remarks = isset($offlineDist['remarks']) ? $offlineDist['remarks'] : '';
        
        foreach ($offlineDist['distributed_items'] as $need_id) {
            $need_id = (int)$need_id;
            
            if ($need_id <= 0) {
                $errors[] = "Invalid need_id for victim $victim_id";
                continue;
            }
            
            // First, get the quantity needed from the needs table
            $get_need_query = "SELECT quantity_needed, resource_id FROM needs WHERE need_id = ?";
            $get_stmt = $db->prepare($get_need_query);
            $get_stmt->bind_param("i", $need_id);
            $get_stmt->execute();
            $need_result = $get_stmt->get_result();
            
            if ($need_row = $need_result->fetch_assoc()) {
                $quantity_needed = (int)$need_row['quantity_needed'];
                $resource_id = (int)$need_row['resource_id'];
                $get_stmt->close();
                
                // Update need status to Fulfilled
                $update_need = "UPDATE needs SET status = 'Fulfilled' WHERE need_id = ?";
                $stmt = $db->prepare($update_need);
                $stmt->bind_param("i", $need_id);
                if (!$stmt->execute()) {
                    $errors[] = "Failed to update need $need_id: " . $stmt->error;
                }
                $stmt->close();
                
                // Update inventory (deduct from quantity_reserved)
                $update_inventory = "
                    UPDATE resource 
                    SET quantity_reserved = quantity_reserved - ?,
                        quantity_available = quantity_available - ?
                    WHERE resource_id = ?
                ";
                $stmt = $db->prepare($update_inventory);
                $stmt->bind_param("iii", $quantity_needed, $quantity_needed, $resource_id);
                if (!$stmt->execute()) {
                    $errors[] = "Failed to update inventory for resource $resource_id: " . $stmt->error;
                }
                $stmt->close();
                
                // Record in distribution_log
                $log_query = "
                    INSERT INTO distribution_log 
                    (distribution_id, volunteer_id, victim_id, need_id, quantity_distributed, distributed_at, remarks, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'completed')
                ";
                
                $stmt = $db->prepare($log_query);
                
                // Bind parameters properly
                $distribution_id = (int)$input['distribution_id'];
                $volunteer_id = (int)$input['volunteer_id'];
                
                $stmt->bind_param(
                    "iiiiiss", 
                    $distribution_id,
                    $volunteer_id,
                    $victim_id,
                    $need_id,
                    $quantity_needed,
                    $timestamp,
                    $remarks
                );
                
                if (!$stmt->execute()) {
                    $errors[] = "Failed to log distribution for need $need_id: " . $stmt->error;
                }
                $stmt->close();
                
                // Handle photo data if exists
                if (isset($offlineDist['photo_data']) && !empty($offlineDist['photo_data'])) {
                    // Get the last inserted log_id
                    $log_id = $db->insert_id;
                    
                    // Save photo
                    $photo_data = $offlineDist['photo_data'];
                    if (strpos($photo_data, 'data:image') === 0) {
                        // Create photos directory if not exists
                        if (!file_exists('../distribution_photos')) {
                            mkdir('../distribution_photos', 0777, true);
                        }
                        
                        // Save photo as image
                        $photo_data = str_replace('data:image/jpeg;base64,', '', $photo_data);
                        $photo_data = str_replace('data:image/png;base64,', '', $photo_data);
                        $photo_data = str_replace(' ', '+', $photo_data);
                        $photo_filename = "photo_offline_{$log_id}.jpg";
                        $photo_path = "../distribution_photos/{$photo_filename}";
                        
                        if (file_put_contents($photo_path, base64_decode($photo_data))) {
                            $update_photo = "UPDATE distribution_log SET photo_url = ? WHERE log_id = ?";
                            $stmt = $db->prepare($update_photo);
                            $stmt->bind_param("si", $photo_filename, $log_id);
                            $stmt->execute();
                            $stmt->close();
                        }
                    }
                }
                
            } else {
                $get_stmt->close();
                $errors[] = "Need $need_id not found in database";
                continue;
            }
        }
        
        $synced_count++;
    }
    
    $db->commit();
    
    $response = [
        'success' => true, 
        'synced_count' => $synced_count,
        'total_attempted' => count($input['data'])
    ];
    
    if (!empty($errors)) {
        $response['warnings'] = $errors;
        $response['partial_success'] = true;
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    $db->rollback();
    error_log("Offline sync error: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'error' => 'Sync failed: ' . $e->getMessage(),
        'errors' => $errors
    ]);
}
?>