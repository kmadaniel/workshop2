<?php
session_start();

// ========================================
// SIMPLE AUTHENTICATION FOR YANA SYAKINAHS
// ========================================

// Force set the current user to "yana syakinahs"
$current_user = [
    'id' => 1,
    'name' => 'yana syakinahs',
    'role' => 'Administrator',
    'avatar' => 'YS',
    'email' => 'yana@disasterrelief.org',
    'phone' => '',
    'department' => 'System Administration',
    'api_verified' => true,
    'auth_source' => 'Direct Access'
];

// Set session variables so you don't get redirected
$_SESSION['user_id'] = 1;
$_SESSION['user_name'] = 'yana syakinahs';
$_SESSION['user_role'] = 'Administrator';
$_SESSION['user_email'] = 'yana@disasterrelief.org';
$_SESSION['admin_api_verified'] = true;

// Include database connection - CORRECT PATH for db.php outside admin folder
$dbPath = dirname(__DIR__) . '/db.php'; // This goes up one level from admin folder
if (!file_exists($dbPath)) {
    die("❌ ERROR: db.php not found at: $dbPath");
}

require_once $dbPath;

// Include AuditLogger class
require_once dirname(__DIR__) . '/admin/audit_functions.php';

// Check if connection was established
if (!isset($conn)) {
    die("❌ ERROR: Database connection failed. \$conn is not set.");
}

try {
    // Test the connection
    $conn->query("SELECT 1");
} catch (Exception $e) {
    die("❌ ERROR: Database connection test failed: " . $e->getMessage());
}

// Initialize AuditLogger
$logger = new AuditLogger($conn);

$message = "";

// API Configuration for YOUR system
$YOUR_API_URL = "http://10.147.17.154:8000/distribution_module/api_victim_approve.php";

// Start timing for performance monitoring
$start_time = microtime(true);


// Function to get current approval status from YOUR API - BATCH VERSION
function getStatusesFromYourAPI($victim_ids, $disaster_id) {
    global $YOUR_API_URL;
    
    if (empty($victim_ids)) {
        return [];
    }
    
    try {
        // Batch request - send all victim IDs at once
        $victim_ids_str = implode(',', array_map('intval', $victim_ids));
        $url = $YOUR_API_URL . "?victim_ids=$victim_ids_str&disaster_id=$disaster_id";
        
        // Use file_get_contents with stream context for timeout
        $context = stream_context_create([
            'http' => [
                'timeout' => 5, // 5 second timeout
                'ignore_errors' => true
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ]
        ]);
        
        $response = @file_get_contents($url, false, $context);
        
        if ($response !== false) {
            $data = json_decode($response, true);
            
            // Return associative array with victim_id as key
            $statuses = [];
            if (is_array($data)) {
                foreach ($data as $entry) {
                    if (isset($entry['victim_id'])) {
                        $statuses[$entry['victim_id']] = $entry['approval_status'] ?? 'Pending';
                    }
                }
            }
            return $statuses;
        }
    } catch (Exception $e) {
        error_log("Error fetching statuses from YOUR API: " . $e->getMessage());
    }
    
    return [];
}

// Function to sync status from YOUR API to local database - BATCH VERSION
function syncStatusesFromYourAPI($conn, $api_statuses, $disaster_id) {
    if (empty($api_statuses)) {
        return 0;
    }
    
    try {
        // Prepare CASE statement for batch update
        $cases = [];
        $params = [];
        
        foreach ($api_statuses as $victim_id => $status) {
            $cases[] = "WHEN victim_id = ? AND disaster_id = ? THEN ?";
            $params[] = $victim_id;
            $params[] = $disaster_id;
            $params[] = $status;
        }
        
        // Add disaster_id for WHERE clause
        $params[] = $disaster_id;
        
        // Build the batch update query
        $case_sql = implode(' ', $cases);
        $sql = "UPDATE needs 
                SET status = CASE 
                    $case_sql
                    ELSE status 
                END
                WHERE disaster_id = ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        
        // Also update victim table in batch
        $victim_cases = [];
        $victim_params = [];
        
        foreach ($api_statuses as $victim_id => $status) {
            $victim_cases[] = "WHEN victim_id = ? AND disaster_id = ? THEN ?";
            $victim_params[] = $victim_id;
            $victim_params[] = $disaster_id;
            $victim_params[] = $status;
        }
        
        $victim_case_sql = implode(' ', $victim_cases);
        $victim_params[] = $disaster_id;
        
        $victim_sql = "UPDATE victim 
                      SET status = CASE 
                          $victim_case_sql
                          ELSE status 
                      END
                      WHERE disaster_id = ?";
        
        $victim_stmt = $conn->prepare($victim_sql);
        $victim_stmt->execute($victim_params);
        
        return count($api_statuses);
        
    } catch (Exception $e) {
        error_log("Error syncing statuses from API: " . $e->getMessage());
        return 0;
    }
}

// Handle Clear Filter
if (isset($_GET['clear'])) {
    $selected_disaster_id = null;
    $filter_type = null;
}

// Handle sync from YOUR API - SINGLE (keep for compatibility)
if (isset($_GET['sync_from_api'])) {
    $victim_id = $_GET['victim_id'] ?? null;
    $disaster_id = $_GET['disaster_id'] ?? null;
    
    if ($victim_id && $disaster_id) {
        // Use batch function even for single sync
        $api_statuses = getStatusesFromYourAPI([$victim_id], $disaster_id);
        if (!empty($api_statuses)) {
            $updated = syncStatusesFromYourAPI($conn, $api_statuses, $disaster_id);
            if ($updated > 0) {
                $message = "✅ Status synced from API";
                // Log audit action using AuditLogger
                $logger->log('api_sync_single', 'Synced victim from API: ' . $victim_id, 'victim', $victim_id, ['disaster_id' => $disaster_id]);
            } else {
                $message = "❌ Failed to sync status from API";
            }
        }
    }
}

// Handle bulk sync from YOUR API
if (isset($_POST['bulk_sync_from_api'])) {
    $disaster_id = $_POST['disaster_id'] ?? null;
    $victim_ids = $_POST['victim_ids'] ?? [];
    
    if ($disaster_id && !empty($victim_ids)) {
        $api_statuses = getStatusesFromYourAPI($victim_ids, $disaster_id);
        $synced = syncStatusesFromYourAPI($conn, $api_statuses, $disaster_id);
        $failed = count($victim_ids) - $synced;
        
        $message = "✅ Bulk sync completed: $synced synced, $failed failed";

        // Log audit action using AuditLogger
        $logger->log('api_bulk_sync', 'Bulk synced ' . count($victim_ids) . ' victims from API', 'victim', null, [
            'count' => count($victim_ids),
            'synced' => $synced,
            'failed' => $failed,
            'disaster_id' => $disaster_id
        ]);
    }
}


// -------------------------
// Handle Disaster Addition / Update / Delete
// -------------------------
if (isset($_POST['add_disaster'])) {
    // Insert into disaster_reports
    $stmt = $conn->prepare("
        INSERT INTO disaster_reports 
        (reported_by_email, disaster_type, description, district, severity, status, reported_at)
        VALUES 
        (:reported_by_email, :disaster_type, :description, :district, :severity, :status, NOW())
    ");
    $stmt->execute([
        ':reported_by_email' => 'admin',
        ':disaster_type' => $_POST['disaster_type'],
        ':description' => $_POST['description'],
        ':district' => $_POST['district'],
        ':severity' => $_POST['severity'],
        ':status' => $_POST['status']
    ]);

    // Insert into disaster table
    $stmt2 = $conn->prepare("
        INSERT INTO disaster (disaster_name, description, district, severity, status, start_date, alert_message)
        VALUES (:name, :description, :district, :severity, :status, NOW(), :alert)
    ");
    $stmt2->execute([
        ':name' => $_POST['disaster_type'],
        ':description' => $_POST['description'],
        ':district' => $_POST['district'],
        ':severity' => $_POST['severity'],
        ':status' => $_POST['status'],
        ':alert' => "New disaster reported: " . $_POST['disaster_type'] . " in " . $_POST['district']
    ]);

    $new_disaster_id = $conn->lastInsertId();
    $message = "✅ Disaster added successfully!";

    // Log audit action using AuditLogger
    $logger->log('disaster_add', 'Added new disaster: ' . $_POST['disaster_type'], 'disaster', $new_disaster_id, $_POST);
}

// Update Disaster Status
if (isset($_POST['update_disaster'])) {
    $disaster_id = $_POST['disaster_id'];
    $status = $_POST['status'];
    
    $stmt = $conn->prepare("UPDATE disaster SET status = :status WHERE disaster_id = :id");
    $stmt->execute([
        ':status' => $status,
        ':id' => $disaster_id
    ]);
    
    $message = "✅ Disaster status updated!";
    
    // Log audit action using AuditLogger
    $logger->log('disaster_update', 'Updated disaster status to: ' . $status, 'disaster', $disaster_id, ['status' => $status]);
}

// Delete Disaster
if (isset($_POST['delete_disaster'])) {
    $disaster_id = $_POST['disaster_id'];
    
    // Check if there are victims associated
    $check_victims = $conn->prepare("SELECT COUNT(*) as count FROM victim WHERE disaster_id = ?");
    $check_victims->execute([$disaster_id]);
    $result = $check_victims->fetch(PDO::FETCH_ASSOC);
    
    if ($result['count'] > 0) {
        $message = "❌ Cannot delete disaster. There are " . $result['count'] . " victims associated with it.";
    } else {
        $stmt = $conn->prepare("DELETE FROM disaster WHERE disaster_id = ?");
        $stmt->execute([$disaster_id]);
        $message = "✅ Disaster deleted successfully!";
        
        // Log audit action using AuditLogger
        $logger->log('disaster_delete', 'Deleted disaster ID: ' . $disaster_id, 'disaster', $disaster_id, ['disaster_id' => $disaster_id]);
    }
}

// Update Victim Status (local only - friend shouldn't send back to you)
if (isset($_POST['update_victim'])) {
    $victim_id = $_POST['victim_id'];
    $disaster_id = $_POST['disaster_id'];
    $status = $_POST['status'];
    $distribution_id = $_POST['distribution_id'];
    $priority = $_POST['priority'];
    
    try {
        // Update local database only
        $stmt = $conn->prepare("
            UPDATE needs 
            SET status = :status, 
                distribution_id = :distribution_id,
                priority = :priority
            WHERE victim_id = :victim_id 
            AND disaster_id = :disaster_id
        ");
        $stmt->execute([
            ':status' => $status,
            ':distribution_id' => $distribution_id,
            ':priority' => $priority,
            ':victim_id' => $victim_id,
            ':disaster_id' => $disaster_id
        ]);
        
        // Also update victim table status if exists
        $update_victim_stmt = $conn->prepare("
            UPDATE victim 
            SET status = :status 
            WHERE victim_id = :victim_id 
            AND disaster_id = :disaster_id
        ");
        $update_victim_stmt->execute([
            ':status' => $status,
            ':victim_id' => $victim_id,
            ':disaster_id' => $disaster_id
        ]);
        
        $message = "✅ Victim status updated locally!";

        // Log audit action using AuditLogger
        $logger->log('victim_update', 'Updated victim status for ID: ' . $victim_id, 'victim', $victim_id, [
            'victim_id' => $victim_id,
            'disaster_id' => $disaster_id,
            'status' => $status,
            'distribution_id' => $distribution_id,
            'priority' => $priority
        ]);
        
    } catch (Exception $e) {
        $message = "❌ Error updating victim: " . $e->getMessage();
        error_log("Database error: " . $e->getMessage());
    }
}

// Handle Emergency Alert
if (isset($_POST['send_alert'])) {
    $alert_message = htmlspecialchars($_POST['alert_message']);
    $disaster_id = $_POST['disaster_id'];
    $alert_type = $_POST['alert_type'] ?? 'Emergency Alert';
    
    $stmt = $conn->prepare("UPDATE disaster SET alert_message = :alert WHERE disaster_id = :id");
    $stmt->execute([
        ':alert' => $alert_message,
        ':id' => $disaster_id
    ]);
    $message = "🚨 Alert message updated for the disaster!";
    
    // Log audit action using AuditLogger
    $logger->log('alert_sent', 'Sent emergency alert: ' . substr($alert_message, 0, 50), 'disaster', $disaster_id, [
        'alert' => $alert_message,
        'alert_type' => $alert_type,
        'disaster_id' => $disaster_id
    ]);
}

// Function to get victim details for modal
function getVictimDetails($conn, $victim_id) {
    $stmt = $conn->prepare("
        SELECT 
            v.*,
            d.disaster_name,
            d.district as disaster_district,
            n.status as need_status,
            n.priority,
            n.created_at as need_created
        FROM victim v
        LEFT JOIN disaster d ON v.disaster_id = d.disaster_id
        LEFT JOIN needs n ON v.victim_id = n.victim_id AND v.disaster_id = n.disaster_id
        WHERE v.victim_id = ?
        LIMIT 1
    ");
    $stmt->execute([$victim_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Check if we're requesting victim details via AJAX
if (isset($_GET['get_victim_details'])) {
    $victim_id = $_GET['victim_id'];
    $victim_details = getVictimDetails($conn, $victim_id);
    
    if ($victim_details) {
        echo json_encode($victim_details);
    } else {
        echo json_encode(['error' => 'Victim not found']);
    }
    exit();
}

// Fetch basic statistics
$stats = $conn->query("
    SELECT 
        COUNT(*) as total_disasters,
        SUM(CASE WHEN status = 'Active' THEN 1 ELSE 0 END) as active_disasters,
        SUM(CASE WHEN status = 'Under Control' THEN 1 ELSE 0 END) as controlled_disasters,
        SUM(CASE WHEN status = 'Ended' THEN 1 ELSE 0 END) as ended_disasters,
        COALESCE(SUM(affected_people), 0) as total_victims
    FROM disaster
")->fetch(PDO::FETCH_ASSOC);

// Fetch all disasters
$disasters = $conn->query("SELECT * FROM disaster ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

// Fetch victims for selected disaster with optional filter
$selected_disaster_id = $_GET['disaster_id'] ?? null;
$filter_type = $_GET['filter'] ?? null; // 'baby', 'elderly', 'disabled', or null for all
$victims = [];
$disaster_details = null;

if ($selected_disaster_id) {
    // Get disaster details
    $stmt = $conn->prepare("SELECT * FROM disaster WHERE disaster_id = ? LIMIT 1");
    $stmt->execute([$selected_disaster_id]);
    $disaster_details = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Build query based on filter with PAGINATION
    $page = $_GET['page'] ?? 1;
    $limit = 50; // Show 50 victims per page
    $offset = ($page - 1) * $limit;
    
    $sql = "
        SELECT v.*, 
               n.status as need_status, 
               n.priority as need_priority,
               n.distribution_id,
               n.created_at as need_created
        FROM victim v
        LEFT JOIN needs n ON v.victim_id = n.victim_id AND v.disaster_id = n.disaster_id
        WHERE v.disaster_id = ?
    ";
    
    $params = [$selected_disaster_id];
    
    // Add filter condition if specified
    if ($filter_type === 'baby') {
        $sql .= " AND v.has_baby = true";
    } elseif ($filter_type === 'elderly') {
        $sql .= " AND v.has_elderly = true";
    } elseif ($filter_type === 'disabled') {
        $sql .= " AND v.has_disabled = true";
    }
    
    $sql .= " ORDER BY 
            CASE WHEN n.priority = 'High' THEN 1
                 WHEN n.priority = 'Medium' THEN 2
                 WHEN n.priority = 'Low' THEN 3
                 ELSE 4 END,
            v.created_at DESC
            LIMIT ? OFFSET ?";
    
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $victims = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // BATCH API CALL - Get all statuses at once instead of one-by-one
    if (!empty($victims)) {
        $victim_ids = array_column($victims, 'victim_id');
        $api_statuses = getStatusesFromYourAPI($victim_ids, $selected_disaster_id);
        
        // Create a map for quick lookup
        $status_map = [];
        foreach ($api_statuses as $victim_id => $status) {
            $status_map[$victim_id] = $status;
        }
        
        // Batch update local database if we got statuses from API
        if (!empty($api_statuses)) {
            $updated_count = syncStatusesFromYourAPI($conn, $api_statuses, $selected_disaster_id);
            
            // Also update the in-memory array for display
            foreach ($victims as &$victim) {
                if (isset($status_map[$victim['victim_id']])) {
                    $victim['api_status'] = $status_map[$victim['victim_id']];
                    $victim['need_status'] = $status_map[$victim['victim_id']];
                } else {
                    $victim['api_status'] = null;
                }
            }
            unset($victim); // Break reference
        }
    }
}

// Get pending needs count - with LIMIT for performance
$pending_needs = $conn->query("SELECT COUNT(*) as count FROM needs WHERE status = 'Pending' LIMIT 1")->fetch(PDO::FETCH_ASSOC);

// Get special needs counts for the selected disaster
$special_needs_counts = [
    'total' => 0,
    'baby' => 0,
    'elderly' => 0,
    'disabled' => 0,
    'pending' => 0,
    'approved' => 0,
    'rejected' => 0
];

if ($selected_disaster_id) {
    $counts_query = $conn->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN has_baby = true THEN 1 ELSE 0 END) as baby_count,
            SUM(CASE WHEN has_elderly = true THEN 1 ELSE 0 END) as elderly_count,
            SUM(CASE WHEN has_disabled = true THEN 1 ELSE 0 END) as disabled_count,
            SUM(CASE WHEN n.status = 'Pending' THEN 1 ELSE 0 END) as pending_count,
            SUM(CASE WHEN n.status = 'Approved' THEN 1 ELSE 0 END) as approved_count,
            SUM(CASE WHEN n.status = 'Rejected' THEN 1 ELSE 0 END) as rejected_count
        FROM victim v
        LEFT JOIN needs n ON v.victim_id = n.victim_id AND v.disaster_id = n.disaster_id
        WHERE v.disaster_id = ?
        GROUP BY v.disaster_id
        LIMIT 1
    ");
    $counts_query->execute([$selected_disaster_id]);
    $counts = $counts_query->fetch(PDO::FETCH_ASSOC);
    
    if ($counts) {
        $special_needs_counts = [
            'total' => $counts['total'] ?? 0,
            'baby' => $counts['baby_count'] ?? 0,
            'elderly' => $counts['elderly_count'] ?? 0,
            'disabled' => $counts['disabled_count'] ?? 0,
            'pending' => $counts['pending_count'] ?? 0,
            'approved' => $counts['approved_count'] ?? 0,
            'rejected' => $counts['rejected_count'] ?? 0
        ];
    }
}

// Log performance
$end_time = microtime(true);
$load_time = round(($end_time - $start_time), 3);
if ($load_time > 1) {
    error_log("Page loaded in {$load_time}s - Disaster: {$selected_disaster_id}, Victims: " . count($victims));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Dashboard - Disaster Management</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
/* ALL YOUR EXISTING CSS STYLES REMAIN EXACTLY THE SAME */
:root {
    --primary: #1a237e;
    --primary-dark: #283593;
    --primary-light: #4fc3f7;
    --secondary: #4CAF50;
    --danger: #f44336;
    --warning: #ff9800;
    --info: #2196F3;
    --light: #f8f9fa;
    --dark: #343a40;
    --border: #e0e0e0;
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background: #f8f9fa;
    min-height: 100vh;
}

/* System Header - FROM ADMIN DASHBOARD */
.system-header {
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
    color: white;
    padding: 0 20px;
    box-shadow: 0 2px 15px rgba(0,0,0,0.1);
    position: sticky;
    top: 0;
    z-index: 1000;
}

.header-container {
    display: flex;
    align-items: center;
    justify-content: space-between;
    height: 70px;
}

.logo-section {
    display: flex;
    align-items: center;
    gap: 15px;
}

.logo-icon {
    font-size: 28px;
    color: var(--primary-light);
}

.logo-text h1 {
    font-size: 22px;
    margin: 0;
    font-weight: 600;
    color: white;
}

.logo-text small {
    font-size: 12px;
    opacity: 0.8;
    color: #bbdefb;
}

.header-controls {
    display: flex;
    align-items: center;
    gap: 20px;
}

.search-box {
    position: relative;
    width: 300px;
}

.search-box input {
    width: 100%;
    padding: 10px 15px 10px 40px;
    border: none;
    border-radius: 20px;
    background: rgba(255, 255, 255, 0.1);
    color: white;
    font-size: 14px;
    transition: all 0.3s ease;
}

.search-box input:focus {
    outline: none;
    background: rgba(255, 255, 255, 0.15);
    box-shadow: 0 0 0 2px rgba(79, 195, 247, 0.3);
}

.search-box i {
    position: absolute;
    left: 15px;
    top: 50%;
    transform: translateY(-50%);
    color: #bbdefb;
}

.user-profile {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 5px 15px;
    border-radius: 25px;
    background: rgba(255, 255, 255, 0.08);
    cursor: pointer;
    transition: all 0.3s ease;
}

.user-profile:hover {
    background: rgba(255, 255, 255, 0.15);
}

.user-avatar {
    width: 40px;
    height: 40px;
    background: linear-gradient(135deg, var(--primary-light) 0%, #0288d1 100%);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    color: white;
    font-size: 16px;
}

.user-info {
    line-height: 1.3;
}

.user-name {
    font-weight: 600;
    font-size: 14px;
}

.user-role {
    font-size: 12px;
    opacity: 0.8;
    color: #bbdefb;
}

.notifications {
    position: relative;
    cursor: pointer;
    padding: 10px;
    border-radius: 50%;
    transition: background 0.3s ease;
}

.notifications:hover {
    background: rgba(255, 255, 255, 0.1);
}

.notification-badge {
    position: absolute;
    top: 5px;
    right: 5px;
    background: var(--danger);
    color: white;
    font-size: 10px;
    width: 18px;
    height: 18px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}

/* Mobile Toggle */
.mobile-toggle {
    display: none;
    background: none;
    border: none;
    color: white;
    font-size: 20px;
    cursor: pointer;
    padding: 10px;
    border-radius: 5px;
    transition: background 0.3s ease;
}

.mobile-toggle:hover {
    background: rgba(255, 255, 255, 0.1);
}

/* Sidebar Navigation - FROM ADMIN DASHBOARD */
.sidebar {
    position: fixed;
    left: 0;
    top: 70px;
    width: 250px;
    height: calc(100vh - 70px);
    background: linear-gradient(180deg, var(--primary) 0%, var(--primary-dark) 100%);
    color: white;
    box-shadow: 2px 0 10px rgba(0,0,0,0.1);
    z-index: 999;
    transition: transform 0.3s ease;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

.sidebar-content {
    flex: 1;
    overflow-y: auto;
    padding: 20px 0;
}

.sidebar-collapsed {
    transform: translateX(-250px);
}

/* Custom scrollbar for sidebar */
.sidebar-content::-webkit-scrollbar {
    width: 6px;
}

.sidebar-content::-webkit-scrollbar-track {
    background: rgba(255, 255, 255, 0.1);
    border-radius: 3px;
}

.sidebar-content::-webkit-scrollbar-thumb {
    background: rgba(79, 195, 247, 0.5);
    border-radius: 3px;
}

.sidebar-content::-webkit-scrollbar-thumb:hover {
    background: rgba(79, 195, 247, 0.8);
}

.nav-menu {
    list-style: none;
    padding: 0;
    margin: 0;
}

.nav-item {
    margin: 5px 15px;
}

.nav-link {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 15px;
    color: #bbdefb;
    text-decoration: none;
    border-radius: 8px;
    transition: all 0.3s ease;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.nav-link:hover {
    background: rgba(255, 255, 255, 0.1);
    color: white;
}

.nav-link.active {
    background: rgba(79, 195, 247, 0.2);
    color: white;
    border-left: 4px solid var(--primary-light);
}

.nav-link i {
    width: 20px;
    text-align: center;
    font-size: 18px;
    flex-shrink: 0;
}

.nav-text {
    font-size: 14px;
    font-weight: 500;
    flex: 1;
    overflow: hidden;
    text-overflow: ellipsis;
}

.nav-divider {
    height: 1px;
    background: rgba(255, 255, 255, 0.1);
    margin: 20px 15px;
}

.nav-label {
    padding: 10px 20px;
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 1px;
    color: #90caf9;
    font-weight: 600;
    white-space: nowrap;
}

/* Sidebar Footer */
.sidebar-footer {
    padding: 15px 20px;
    background: rgba(0, 0, 0, 0.2);
    border-top: 1px solid rgba(255, 255, 255, 0.1);
    flex-shrink: 0;
}

.sidebar-footer a {
    display: flex;
    align-items: center;
    gap: 10px;
    color: #bbdefb;
    text-decoration: none;
    padding: 10px;
    border-radius: 6px;
    transition: all 0.3s ease;
}

.sidebar-footer a:hover {
    background: rgba(231, 76, 60, 0.2);
    color: #ff6b6b;
}

/* Main Content Area */
.main-content {
    margin-left: 250px;
    padding: 30px;
    transition: margin-left 0.3s ease;
    min-height: calc(100vh - 70px);
    background: #f8f9fa;
}

.main-content-expanded {
    margin-left: 0;
}

/* YOUR EXISTING STYLES (keeping all your functionality styles) */
.quick-actions {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin-bottom: 30px;
}

.action-btn {
    background: white;
    border: none;
    padding: 20px;
    border-radius: 12px;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s;
    box-shadow: 0 3px 10px rgba(0,0,0,0.08);
    color: var(--dark);
    font-size: 16px;
    font-weight: 500;
}

.action-btn:hover {
    transform: translateY(-3px);
    box-shadow: 0 5px 20px rgba(0,0,0,0.12);
}

.action-btn i {
    font-size: 24px;
    color: var(--primary);
    margin-bottom: 10px;
    display: block;
}

/* Dashboard Sections */
.dashboard-section {
    background: white;
    border-radius: 15px;
    padding: 30px;
    margin-bottom: 30px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.08);
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
    padding-bottom: 15px;
    border-bottom: 2px solid var(--border);
}

.section-header h3 {
    color: var(--primary);
    font-size: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
}

/* Badges */
.badge {
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    display: inline-block;
}

.badge-primary { background: #e3f2fd; color: var(--info); }
.badge-success { background: #e8f5e9; color: var(--secondary); }
.badge-danger { background: #ffebee; color: var(--danger); }
.badge-warning { background: #fff3e0; color: var(--warning); }
.badge-info { background: #e0f2f1; color: #00796b; }
.badge-high { background: #ffebee; color: var(--danger); }
.badge-medium { background: #fff3e0; color: var(--warning); }
.badge-low { background: #e8f5e9; color: var(--secondary); }

/* Special Needs Filter */
.filter-tabs {
    display: flex;
    gap: 10px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}

.filter-tab {
    padding: 10px 20px;
    border-radius: 25px;
    text-decoration: none;
    font-weight: 500;
    font-size: 14px;
    transition: all 0.3s;
    display: flex;
    align-items: center;
    gap: 8px;
}

.filter-tab.all {
    background: #e3f2fd;
    color: var(--info);
    border: 2px solid transparent;
}

.filter-tab.baby {
    background: #e1f5fe;
    color: #0288d1;
    border: 2px solid transparent;
}

.filter-tab.elderly {
    background: #f3e5f5;
    color: #7b1fa2;
    border: 2px solid transparent;
}

.filter-tab.disabled {
    background: #e8f5e9;
    color: #2e7d32;
    border: 2px solid transparent;
}

.filter-tab:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.filter-tab.active {
    border: 2px solid var(--primary);
    font-weight: 600;
}

/* Tables */
.table-responsive {
    overflow-x: auto;
    border-radius: 10px;
    border: 1px solid var(--border);
    margin-bottom: 20px;
}

table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    min-width: 800px;
}

table th {
    background: var(--light);
    padding: 16px;
    text-align: left;
    font-weight: 600;
    color: var(--dark);
    border-bottom: 2px solid var(--border);
}

table td {
    padding: 14px 16px;
    border-bottom: 1px solid var(--border);
    vertical-align: middle;
}

table tr:last-child td {
    border-bottom: none;
}

table tr:hover {
    background: rgba(108, 99, 255, 0.05);
}

/* Forms */
.form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-bottom: 20px;
}

.form-group {
    margin-bottom: 20px;
}

.form-control {
    width: 100%;
    padding: 12px 15px;
    border: 2px solid var(--border);
    border-radius: 10px;
    font-size: 16px;
    transition: all 0.3s;
    font-family: inherit;
}

.form-control:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(108, 99, 255, 0.1);
}

textarea.form-control {
    min-height: 120px;
    resize: vertical;
}

/* Buttons */
.btn {
    padding: 12px 24px;
    border: none;
    border-radius: 10px;
    font-size: 15px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-family: inherit;
}

.btn-primary {
    background: var(--primary);
    color: white;
}

.btn-primary:hover {
    background: var(--primary-dark);
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(108, 99, 255, 0.3);
}

.btn-danger {
    background: var(--danger);
    color: white;
}

.btn-danger:hover {
    background: #d32f2f;
    transform: translateY(-2px);
}

.btn-sm {
    padding: 8px 16px;
    font-size: 14px;
}

.btn-xs {
    padding: 6px 12px;
    font-size: 12px;
}

/* Messages */
.message {
    padding: 15px 20px;
    border-radius: 10px;
    margin-bottom: 25px;
    text-align: center;
    font-weight: 500;
    animation: slideIn 0.5s ease-out;
}

@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.message.success {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.message.error {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

/* Alert Section */
.alert-section {
    background: linear-gradient(135deg, #ff6b6b, #ff8e8e);
    color: white;
    border-radius: 15px;
    padding: 25px;
    margin-bottom: 30px;
}

.alert-section h3 {
    color: white;
    margin-bottom: 15px;
}

.alert-section textarea {
    background: rgba(255,255,255,0.1);
    border: 2px solid rgba(255,255,255,0.3);
    color: white;
}

.alert-section textarea::placeholder {
    color: rgba(255,255,255,0.7);
}

/* Status Indicators */
.status-indicator {
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.status-dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    display: inline-block;
}

.status-active { background: var(--secondary); }
.status-pending { background: var(--warning); }
.status-ended { background: #999; }

/* Victim Special Needs Tags */
.needs-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 5px;
}

.tag {
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
    display: inline-block;
}

.tag-baby { background: #e1f5fe; color: #0288d1; }
.tag-elderly { background: #f3e5f5; color: #7b1fa2; }
.tag-disabled { background: #e8f5e9; color: #2e7d32; }

/* Disaster Status Colors */
.status-active-bg { background: #ffebee !important; }
.status-control-bg { background: #e8f5e9 !important; }
.status-ended-bg { background: #f5f5f5 !important; }

/* Special Needs Stats */
.special-needs-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 15px;
    margin-bottom: 25px;
}

.stat-card {
    background: white;
    border-radius: 10px;
    padding: 20px;
    box-shadow: 0 3px 10px rgba(0,0,0,0.08);
    border-left: 4px solid;
    transition: transform 0.3s;
    cursor: pointer;
}

.stat-card:hover {
    transform: translateY(-3px);
}

.stat-card.baby { border-left-color: #0288d1; }
.stat-card.elderly { border-left-color: #7b1fa2; }
.stat-card.disabled { border-left-color: #2e7d32; }
.stat-card.pending { border-left-color: #ff9800; }
.stat-card.approved { border-left-color: #4CAF50; }
.stat-card.rejected { border-left-color: #f44336; }

.stat-card .stat-number {
    font-size: 32px;
    font-weight: bold;
    margin-bottom: 5px;
}

.stat-card.baby .stat-number { color: #0288d1; }
.stat-card.elderly .stat-number { color: #7b1fa2; }
.stat-card.disabled .stat-number { color: #2e7d32; }
.stat-card.pending .stat-number { color: #ff9800; }
.stat-card.approved .stat-number { color: #4CAF50; }
.stat-card.rejected .stat-number { color: #f44336; }

.stat-card .stat-label {
    font-size: 14px;
    color: #666;
}

/* Modal Styles */
.modal-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.7);
    z-index: 9999;
    align-items: center;
    justify-content: center;
    padding: 20px;
}

.modal-content {
    background: white;
    border-radius: 15px;
    width: 100%;
    max-width: 700px;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 10px 30px rgba(0,0,0,0.3);
    animation: modalFadeIn 0.3s ease-out;
}

@keyframes modalFadeIn {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.modal-header {
    position: sticky;
    top: 0;
    background: white;
    border-bottom: 2px solid var(--border);
    padding: 20px 30px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    z-index: 1;
}

.modal-header h3 {
    color: var(--primary);
    margin: 0;
    font-size: 20px;
}

.modal-close {
    background: none;
    border: none;
    font-size: 24px;
    color: #666;
    cursor: pointer;
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    transition: all 0.3s;
}

.modal-close:hover {
    background: #f5f5f5;
    color: #333;
}

.modal-body {
    padding: 30px;
}

.victim-details-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
}

.detail-card {
    background: #f8f9fa;
    border-radius: 10px;
    padding: 20px;
    border-left: 4px solid var(--primary);
}

.detail-card h4 {
    color: var(--primary);
    margin-top: 0;
    margin-bottom: 15px;
    font-size: 16px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.detail-row {
    display: flex;
    margin-bottom: 12px;
    padding-bottom: 12px;
    border-bottom: 1px solid #eee;
}

.detail-row:last-child {
    margin-bottom: 0;
    padding-bottom: 0;
    border-bottom: none;
}

.detail-label {
    flex: 0 0 150px;
    font-weight: 600;
    color: #666;
    font-size: 14px;
}

.detail-value {
    flex: 1;
    color: #333;
    font-size: 15px;
}

.modal-actions {
    display: flex;
    gap: 10px;
    margin-top: 25px;
    padding-top: 20px;
    border-top: 2px solid var(--border);
    justify-content: center;
    flex-wrap: wrap;
}

.action-btn-circle {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    border: none;
    cursor: pointer;
    transition: all 0.3s;
    font-size: 20px;
}

.action-btn-circle:hover {
    transform: translateY(-3px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.2);
}

.action-btn-circle.call { background: #e3f2fd; color: #2196F3; }
.action-btn-circle.email { background: #f3e5f5; color: #9C27B0; }
.action-btn-circle.whatsapp { background: #e8f5e9; color: #4CAF50; }
.action-btn-circle.report { background: #fff3e0; color: #ff9800; }
.action-btn-circle.edit { background: #e0f2f1; color: #00796b; }

/* API Status Indicator */
.api-status-indicator {
    font-size: 0.7em;
    padding: 2px 6px;
    border-radius: 4px;
    margin-left: 5px;
}

.api-synced { background: #d4edda; color: #155724; }
.api-pending { background: #fff3cd; color: #856404; }
.api-unsynced { background: #f8d7da; color: #721c24; }

/* Sync Button */
.sync-btn {
    background: #e3f2fd;
    color: #2196F3;
    border: 1px solid #bbdefb;
    padding: 2px 8px;
    border-radius: 4px;
    font-size: 0.8em;
    cursor: pointer;
    transition: all 0.3s;
}

.sync-btn:hover {
    background: #bbdefb;
}

/* Bulk Sync Bar */
.bulk-sync-bar {
    background: #fff3cd;
    border: 2px solid #ffc107;
    padding: 10px;
    border-radius: 8px;
    margin-bottom: 15px;
    display: none;
}

.bulk-sync-bar.active {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

/* API Info Card */
.api-info-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 15px;
    border-radius: 10px;
    margin-bottom: 20px;
}

.api-info-card h4 {
    color: white;
    margin-bottom: 10px;
}

.api-info-card small {
    opacity: 0.9;
}

/* Responsive */
@media (max-width: 1200px) {
    .main-content {
        margin-left: 0;
    }
    
    .sidebar {
        transform: translateX(-250px);
    }
    
    .sidebar.active {
        transform: translateX(0);
    }
    
    .mobile-toggle {
        display: block;
    }
}

@media (max-width: 768px) {
    .form-grid {
        grid-template-columns: 1fr;
    }
    
    .quick-actions {
        grid-template-columns: 1fr;
    }
    
    .table-responsive {
        font-size: 14px;
    }
    
    .special-needs-stats {
        grid-template-columns: 1fr 1fr;
    }
    
    .filter-tabs {
        justify-content: center;
    }
    
    .victim-details-grid {
        grid-template-columns: 1fr;
    }
    
    .detail-row {
        flex-direction: column;
        gap: 5px;
    }
    
    .detail-label {
        flex: none;
    }
    
    .search-box {
        width: 200px;
    }
    
    .bulk-sync-bar {
        flex-direction: column;
        gap: 10px;
    }
}

@media (max-width: 576px) {
    .header-container {
        flex-wrap: wrap;
        height: auto;
        padding: 15px 0;
    }
    
    .logo-text h1 {
        font-size: 18px;
    }
    
    .search-box {
        width: 100%;
        order: 3;
        margin-top: 15px;
    }
    
    .stats-container {
        grid-template-columns: 1fr;
    }
}

/* Add a performance indicator */
.load-time {
    position: fixed;
    bottom: 10px;
    right: 10px;
    background: rgba(0,0,0,0.7);
    color: white;
    padding: 5px 10px;
    border-radius: 5px;
    font-size: 12px;
    z-index: 9999;
    display: none; /* Hidden by default, can enable for debugging */
}

/* ========== AUDIT LOG STYLES ========== */
.audit-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
}

.audit-table th {
    background: #f8f9fa;
    padding: 12px 10px;
    text-align: left;
    font-weight: 600;
    color: #333;
    border-bottom: 2px solid #dee2e6;
}

.audit-table td {
    padding: 10px;
    border-bottom: 1px solid #eee;
    vertical-align: top;
}

.audit-table tr:hover {
    background: #f8f9fa;
}

.audit-details {
    background: white;
    padding: 20px;
    border-radius: 10px;
    margin-top: 20px;
    border: 1px solid #dee2e6;
}

.diff-row {
    padding: 8px 0;
    border-bottom: 1px solid #eee;
}

.diff-label {
    font-weight: 600;
    color: #333;
    margin-bottom: 5px;
}

.diff-old {
    color: #f44336;
    text-decoration: line-through;
    padding: 2px 5px;
    background: #ffebee;
    border-radius: 3px;
    display: inline-block;
    margin-right: 10px;
}

.diff-new {
    color: #4caf50;
    padding: 2px 5px;
    background: #e8f5e9;
    border-radius: 3px;
    display: inline-block;
}

.arrow {
    color: #999;
    margin: 0 5px;
}

.audit-badge {
    padding: 3px 8px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 600;
}

.audit-badge.create { background: #d4edda; color: #155724; }
.audit-badge.update { background: #fff3cd; color: #856404; }
.audit-badge.delete { background: #f8d7da; color: #721c24; }
.audit-badge.api { background: #cce5ff; color: #004085; }
.audit-badge.system { background: #d1ecf1; color: #0c5460; }
/* ========== END AUDIT LOG STYLES ========== */

</style>
</head>
<body>
    <!-- Performance indicator (optional, for debugging) -->
    <div class="load-time" id="loadTime">
        Loaded in <?= $load_time ?>s
    </div>

    <!-- System Header -->
    <header class="system-header">
        <div class="header-container">
            <div class="logo-section">
                <button class="mobile-toggle" id="mobileToggle">
                    <i class="fas fa-bars"></i>
                </button>
                <div class="logo-icon">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <div class="logo-text">
                    <h1>Admin Panel</h1>
                    <small>Disaster Management System</small>
                </div>
            </div>
            
            <div class="header-controls">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" placeholder="Search...">
                </div>
                
               
    <div class="user-avatar">
        <?= strtoupper(substr($current_user['name'] ?? 'YS', 0, 2)) ?>
    </div>
    <div class="user-info">
        <div class="user-name"><?= htmlspecialchars($current_user['name'] ?? 'yana syakinahs') ?></div>
        <div class="user-role"><?= htmlspecialchars($current_user['role'] ?? 'Administrator') ?></div>
    </div>
</div>
            </div>
        </div>
    </header>

    <!-- Sidebar Navigation -->
    <nav class="sidebar" id="sidebar">
        <div class="sidebar-content">
            <ul class="nav-menu">
                <li class="nav-label">MAIN NAVIGATION</li>
                
                <li class="nav-item">
    <a href="http://10.147.17.30:8000/admin_dashboard.php" class="nav-link active">
        <i class="fas fa-tachometer-alt"></i>
        <span class="nav-text">Dashboard</span>
    </a>
</li>

<!-- INSERT BACK BUTTON HERE (after line 901) -->
<li class="nav-item">
    <a href="http://10.147.17.30:8000/admin_dashboard.php" class="nav-link">
        <i class="fas fa-arrow-left"></i>
        <span class="nav-text">Back to Main Dashboard</span>
    </a>
</li>
                
                <li class="nav-divider"></li>
                
                <li class="nav-label">DISASTER MANAGEMENT</li>
                
                <li class="nav-item">
                    <a href="#disaster-management" class="nav-link">
                        <i class="fas fa-fire"></i>
                        <span class="nav-text">Manage Disasters</span>
                    </a>
                </li>
                
                <li class="nav-item">
                    <a href="#victim-management" class="nav-link">
                        <i class="fas fa-users"></i>
                        <span class="nav-text">Manage Victims</span>
                    </a>
                </li>
                
                <li class="nav-divider"></li>
                
                <li class="nav-label">QUICK ACTIONS</li>
                
                <li class="nav-item">
                    <a href="#addDisaster" class="nav-link">
                        <i class="fas fa-plus-circle"></i>
                        <span class="nav-text">Add Disaster</span>
                    </a>
                </li>
                
                <li class="nav-item">
                    <a href="#sendAlert" class="nav-link">
                        <i class="fas fa-bullhorn"></i>
                        <span class="nav-text">Emergency Alert</span>
                    </a>
                </li>
            </ul>
        </div>
        
        <!-- Sidebar Footer -->
        <div class="sidebar-footer">
            <a href="main_page.php">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="main-content" id="mainContent">
        <?php if($message): ?>
        <div class="message <?= strpos($message,'✅')!==false || strpos($message,'🚨')!==false ? 'success':'error' ?>">
            <?= $message ?>
        </div>
        <?php endif; ?>


        <!-- Quick Actions -->
        <div class="quick-actions">
            <button class="action-btn" onclick="document.getElementById('addDisaster').scrollIntoView({behavior: 'smooth'})">
                <i class="fas fa-plus-circle"></i>
                <span>Add New Disaster</span>
            </button>
            <button class="action-btn" onclick="document.getElementById('sendAlert').scrollIntoView({behavior: 'smooth'})">
                <i class="fas fa-bullhorn"></i>
                <span>Send Emergency Alert</span>
            </button>
            <?php if($selected_disaster_id): ?>
            <button class="action-btn" onclick="syncAllFromAPI()" style="background: #fff3cd;">
                <i class="fas fa-sync-alt"></i>
                <span>Sync from Approval System</span>
            </button>
            <?php endif; ?>
        </div>

        <!-- Emergency Alert Section -->
<div class="alert-section" id="sendAlert">
    <h3><i class="fas fa-bullhorn"></i> Quick Emergency Alert</h3>
    <form method="POST">
        <div class="form-grid">
            <div class="form-group">
                <select name="disaster_id" class="form-control" required>
                    <option value="">Select Disaster</option>
                    <?php 
                    // Only show Active and Under Control disasters
                    $active_disasters = $conn->query("
                        SELECT * FROM disaster 
                        WHERE status IN ('Active', 'Under Control') 
                        ORDER BY disaster_name
                    ")->fetchAll(PDO::FETCH_ASSOC);
                    
                    foreach($active_disasters as $d): ?>
                        <option value="<?= $d['disaster_id'] ?>">
                            <?= htmlspecialchars($d['disaster_name']) ?> - <?= htmlspecialchars($d['district']) ?> 
                            (<?= $d['status'] ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <input type="text" name="alert_type" class="form-control" placeholder="Alert Type (e.g., Warning, Update)" value="Emergency Alert">
            </div>
        </div>
        <div class="form-group">
            <textarea name="alert_message" class="form-control" rows="3" placeholder="Enter emergency message..." required></textarea>
        </div>
        <button type="submit" name="send_alert" class="btn btn-primary">
            <i class="fas fa-paper-plane"></i> Send Alert
        </button>
    </form>
</div>

       <!-- ADD NEW DISASTER -->
<div class="dashboard-section" id="addDisaster">
    <div class="section-header">
        <h3><i class="fas fa-plus-circle"></i> Add New Disaster</h3>
        <span class="badge badge-primary">NEW</span>
    </div>
    <form method="POST">
        <div class="form-grid">
            <div class="form-group">
                <input type="text" name="disaster_type" class="form-control" placeholder="Disaster Type (e.g., Flood, Fire, Earthquake)" required>
            </div>
            <div class="form-group">
                <select name="district" class="form-control" required>
                    <option value="">-- Select District --</option>
                    <option value="Melaka Tengah">Melaka Tengah</option>
                    <option value="Jasin">Jasin</option>
                    <option value="Alor Gajah">Alor Gajah</option>
                </select>
            </div>
        </div>
        
        <div class="form-grid">
            <div class="form-group">
                <select name="severity" class="form-control" required>
                    <option value="">-- Select Severity --</option>
                    <option value="Low">Low</option>
                    <option value="Medium">Medium</option>
                    <option value="High">High</option>
                </select>
            </div>
            <div class="form-group">
                <select name="status" class="form-control" required>
                    <option value="Active">Active</option>
                    <option value="Under Control">Under Control</option>
                    <option value="Ended">Ended</option>
                </select>
            </div>
        </div>
        
        <div class="form-group">
            <textarea name="description" class="form-control" rows="4" placeholder="Detailed description of the disaster..." required></textarea>
        </div>
        
        <button type="submit" name="add_disaster" class="btn btn-primary">
            <i class="fas fa-save"></i> Add Disaster
        </button>
    </form>
</div> 

        <!-- MANAGE EXISTING DISASTERS -->
        <div class="dashboard-section" id="disaster-management">
            <div class="section-header">
                <h3><i class="fas fa-fire"></i> Manage Disasters</h3>
                <div>
                    <span class="badge badge-danger"><?= $stats['active_disasters'] ?? 0 ?> ACTIVE</span>
                    <span class="badge badge-warning"><?= $stats['total_disasters'] ?? 0 ?> TOTAL</span>
                </div>
            </div>

            <?php if (empty($disasters)): ?>
                <div style="text-align: center; padding: 40px; color: #666;">
                    <i class="fas fa-check-circle" style="font-size: 48px; margin-bottom: 20px; color: var(--secondary);"></i>
                    <p>No disasters found. All clear!</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Disaster Name</th>
                                <th>Description</th>
                                <th>District</th>
                                <th>Severity</th>
                                <th>Status</th>
                                <th>Start Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($disasters as $d): ?>
                            <tr class="<?= $d['status'] == 'Active' ? 'status-active-bg' : ($d['status'] == 'Under Control' ? 'status-control-bg' : 'status-ended-bg') ?>">
                                <td>#<?= $d['disaster_id'] ?></td>
                                <td>
                                    <strong><?= htmlspecialchars($d['disaster_name']) ?></strong>
                                </td>
                                <td>
                                    <small><?= htmlspecialchars(substr($d['description'] ?? 'No description', 0, 50)) ?>...</small>
                                </td>
                                <td><?= htmlspecialchars($d['district']) ?></td>
                                <td>
                                    <span class="badge badge-<?= strtolower($d['severity']) ?>">
                                        <?= $d['severity'] ?>
                                    </span>
                                </td>
                                <td>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="disaster_id" value="<?= $d['disaster_id'] ?>">
                                        <select name="status" onchange="this.form.submit()" 
                                                class="form-control" style="width: auto; display: inline-block; padding: 6px 12px; min-width: 140px;">
                                            <option value="Active" <?= $d['status']=='Active'?'selected':'' ?>>Active</option>
                                            <option value="Under Control" <?= $d['status']=='Under Control'?'selected':'' ?>>Under Control</option>
                                            <option value="Ended" <?= $d['status']=='Ended'?'selected':'' ?>>Ended</option>
                                        </select>
                                        <input type="hidden" name="update_disaster" value="1">
                                    </form>
                                </td>
                                <td><?= date('d M Y', strtotime($d['start_date'] ?? $d['created_at'])) ?></td>
                                <td>
                                    <div style="display: flex; gap: 5px;">
                                        <a href="?disaster_id=<?= $d['disaster_id'] ?>" 
                                           class="btn btn-primary btn-xs">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                        <form method="POST" style="display: inline;" onsubmit="return confirmDelete()">
                                            <input type="hidden" name="disaster_id" value="<?= $d['disaster_id'] ?>">
                                            <button type="submit" name="delete_disaster" class="btn btn-danger btn-xs">
                                                <i class="fas fa-trash"></i> Delete
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- VICTIM MANAGEMENT SECTION -->
        <div class="dashboard-section" id="victim-management">
            <?php if($selected_disaster_id): ?>
                <div class="section-header">
                    <h3><i class="fas fa-users"></i> 
                        <?php if($disaster_details): ?>
                            Victims for: <?= htmlspecialchars($disaster_details['disaster_name']) ?> 
                            <small style="color: #666; font-weight: normal;">(District: <?= htmlspecialchars($disaster_details['district']) ?>)</small>
                        <?php else: ?>
                            Victim Management
                        <?php endif; ?>
                    </h3>
                    <div>
                        <span class="badge badge-info"><?= count($victims) ?> VICTIMS</span>
                         <a href="admin_dashboard.php" class="btn btn-sm btn-primary" style="margin-left: 10px;">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>
        <a href="admin_dashboard.php" class="btn btn-sm" style="background: #f5f5f5; margin-left: 10px;">
            <i class="fas fa-times"></i> Clear Filter
        </a>
                        <a href="admin_dashboard.php" class="btn btn-sm" style="background: #f5f5f5; margin-left: 10px;">
                            <i class="fas fa-times"></i> Clear Filter
                        </a>
                        <button onclick="syncAllFromAPI()" class="btn btn-sm" style="background: #e3f2fd; margin-left: 10px;">
                            <i class="fas fa-sync-alt"></i> Sync All
                        </button>
                    </div>
                </div>
                
                <!-- Special Needs Statistics -->
                <div class="special-needs-stats">
                    <div class="stat-card total" onclick="window.location.href='?disaster_id=<?= $selected_disaster_id ?>'">
                        <div class="stat-number"><?= $special_needs_counts['total'] ?></div>
                        <div class="stat-label">Total Victims</div>
                    </div>
                    <div class="stat-card baby" onclick="window.location.href='?disaster_id=<?= $selected_disaster_id ?>&filter=baby'">
                        <div class="stat-number"><?= $special_needs_counts['baby'] ?></div>
                        <div class="stat-label">With Babies <i class="fas fa-baby"></i></div>
                    </div>
                    <div class="stat-card elderly" onclick="window.location.href='?disaster_id=<?= $selected_disaster_id ?>&filter=elderly'">
                        <div class="stat-number"><?= $special_needs_counts['elderly'] ?></div>
                        <div class="stat-label">Elderly <i class="fas fa-wheelchair"></i></div>
                    </div>
                    <div class="stat-card disabled" onclick="window.location.href='?disaster_id=<?= $selected_disaster_id ?>&filter=disabled'">
                        <div class="stat-number"><?= $special_needs_counts['disabled'] ?></div>
                        <div class="stat-label">Disabled <i class="fas fa-universal-access"></i></div>
                    </div>
                    <div class="stat-card pending">
                        <div class="stat-number"><?= $special_needs_counts['pending'] ?></div>
                        <div class="stat-label">Pending Approval</div>
                    </div>
                    <div class="stat-card approved">
                        <div class="stat-number"><?= $special_needs_counts['approved'] ?></div>
                        <div class="stat-label">Approved</div>
                    </div>
                    <div class="stat-card rejected">
                        <div class="stat-number"><?= $special_needs_counts['rejected'] ?></div>
                        <div class="stat-label">Rejected</div>
                    </div>
                </div>
                
                <!-- Bulk Sync Bar -->
                <div class="bulk-sync-bar" id="bulkSyncBar">
                    <div>
                        <strong><i class="fas fa-sync-alt"></i> 
                        <span id="selectedSyncCount">0</span> victim(s) selected for sync</strong>
                    </div>
                    <div style="display: flex; gap: 10px;">
                        <form method="POST" id="bulkSyncForm">
                            <input type="hidden" name="disaster_id" value="<?= $selected_disaster_id ?>">
                            <button type="submit" name="bulk_sync_from_api" class="btn btn-warning btn-sm"
                                    onclick="return confirm('Sync selected victims from approval system?')">
                                <i class="fas fa-sync-alt"></i> Sync Selected
                            </button>
                        </form>
                        <button type="button" class="btn btn-secondary btn-sm" onclick="clearSyncSelection()">
                            <i class="fas fa-times"></i> Clear
                        </button>
                    </div>
                </div>
                
                <!-- Filter Tabs -->
                <div class="filter-tabs">
                    <a href="?disaster_id=<?= $selected_disaster_id ?>" 
                       class="filter-tab all <?= !$filter_type ? 'active' : '' ?>">
                        <i class="fas fa-users"></i> All Victims (<?= $special_needs_counts['total'] ?>)
                    </a>
                    <a href="?disaster_id=<?= $selected_disaster_id ?>&filter=baby" 
                       class="filter-tab baby <?= $filter_type === 'baby' ? 'active' : '' ?>">
                        <i class="fas fa-baby"></i> With Babies (<?= $special_needs_counts['baby'] ?>)
                    </a>
                    <a href="?disaster_id=<?= $selected_disaster_id ?>&filter=elderly" 
                       class="filter-tab elderly <?= $filter_type === 'elderly' ? 'active' : '' ?>">
                        <i class="fas fa-wheelchair"></i> Elderly (<?= $special_needs_counts['elderly'] ?>)
                    </a>
                    <a href="?disaster_id=<?= $selected_disaster_id ?>&filter=disabled" 
                       class="filter-tab disabled <?= $filter_type === 'disabled' ? 'active' : '' ?>">
                        <i class="fas fa-universal-access"></i> Disabled (<?= $special_needs_counts['disabled'] ?>)
                    </a>
                </div>
                
                <!-- Pagination Controls -->
                <?php if($special_needs_counts['total'] > 50): ?>
                <div style="display: flex; justify-content: center; margin-bottom: 20px; gap: 10px;">
                    <?php 
                    $total_pages = ceil($special_needs_counts['total'] / 50);
                    $current_page = $_GET['page'] ?? 1;
                    
                    if ($current_page > 1): ?>
                    <a href="?disaster_id=<?= $selected_disaster_id ?>&filter=<?= $filter_type ?>&page=<?= $current_page - 1 ?>" class="btn btn-sm btn-primary">
                        <i class="fas fa-chevron-left"></i> Previous
                    </a>
                    <?php endif; ?>
                    
                    <span style="padding: 8px 16px; background: #f5f5f5; border-radius: 5px;">
                        Page <?= $current_page ?> of <?= $total_pages ?>
                    </span>
                    
                    <?php if ($current_page < $total_pages): ?>
                    <a href="?disaster_id=<?= $selected_disaster_id ?>&filter=<?= $filter_type ?>&page=<?= $current_page + 1 ?>" class="btn btn-sm btn-primary">
                        Next <i class="fas fa-chevron-right"></i>
                    </a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                
                <?php if(empty($victims)): ?>
                    <div style="text-align: center; padding: 40px; color: #666;">
                        <i class="fas fa-user-slash" style="font-size: 48px; margin-bottom: 20px; color: #ccc;"></i>
                        <p>
                            <?php if($filter_type === 'baby'): ?>
                                No victims with babies registered for this disaster.
                            <?php elseif($filter_type === 'elderly'): ?>
                                No elderly victims registered for this disaster.
                            <?php elseif($filter_type === 'disabled'): ?>
                                No disabled victims registered for this disaster.
                            <?php else: ?>
                                No victims registered for this disaster yet.
                            <?php endif; ?>
                        </p>
                        <?php if($filter_type): ?>
                            <a href="?disaster_id=<?= $selected_disaster_id ?>" class="btn btn-primary btn-sm">
                                <i class="fas fa-users"></i> View All Victims
                            </a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <!-- Victim Table -->
                    <div class="table-responsive">
                        <table id="victimsTable">
                            <thead>
                                <tr>
                                    <th style="width: 40px;">
                                        <input type="checkbox" id="selectAllSync">
                                    </th>
                                    <th>#</th>
                                    <th>Victim Details</th>
                                    <th>Contact Info</th>
                                    <th>Family & Special Needs</th>
                                    <th>Needs Status</th>
                                    <th>Registration Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $start_number = (($_GET['page'] ?? 1) - 1) * 50 + 1;
                                foreach($victims as $index => $v): 
                                ?>
                                <tr>
                                    <td>
                                        <input type="checkbox" name="victim_ids[]" 
                                               value="<?= $v['victim_id'] ?>" 
                                               class="sync-checkbox">
                                    </td>
                                    <td><?= $start_number + $index ?></td>
                                    <td>
                                        <strong><?= htmlspecialchars($v['full_name']) ?></strong><br>
                                        <small style="color: #666;">IC: <?= htmlspecialchars($v['ic_number']) ?></small><br>
                                        <small style="color: #888;"><?= htmlspecialchars($v['city'] ?? 'Unknown City') ?></small>
                                        <div style="margin-top: 3px;">
                                            <button type="button" class="sync-btn" 
                                                    onclick="syncSingleFromAPI(<?= $v['victim_id'] ?>, <?= $selected_disaster_id ?>)"
                                                    title="Sync from approval system">
                                                <i class="fas fa-sync-alt"></i> Sync
                                            </button>
                                        </div>
                                    </td>
                                    <td>
                                        <div><i class="fas fa-envelope" style="color: #666; width: 16px;"></i> 
                                            <?= htmlspecialchars($v['email']) ?>
                                            <?= $v['email_verified'] ? ' <span class="badge" style="background:#e8f5e9; color:#2e7d32; font-size:10px;">Verified</span>' : '' ?>
                                        </div>
                                        <div><i class="fas fa-phone" style="color: #666; width: 16px;"></i> 
                                            <?= htmlspecialchars($v['phone'] ?? 'Not provided') ?>
                                        </div>
                                        <div><i class="fas fa-home" style="color: #666; width: 16px;"></i> 
                                            <small><?= htmlspecialchars(substr($v['address'], 0, 30)) ?>...</small>
                                        </div>
                                    </td>
                                    <td>
                                        <div style="margin-bottom: 5px;">
                                            <small><strong>Family:</strong> <?= $v['family_members'] ?> members</small>
                                        </div>
                                        <div class="needs-tags">
                                            <?php if($v['has_baby']): ?>
                                                <span class="tag tag-baby"><i class="fas fa-baby"></i> Baby</span>
                                            <?php endif; ?>
                                            <?php if($v['has_elderly']): ?>
                                                <span class="tag tag-elderly"><i class="fas fa-wheelchair"></i> Elderly</span>
                                            <?php endif; ?>
                                            <?php if($v['has_disabled']): ?>
                                                <span class="tag tag-disabled"><i class="fas fa-universal-access"></i> Disabled</span>
                                            <?php endif; ?>
                                        </div>
                                        <?php if($v['special_request']): ?>
                                            <div style="margin-top: 5px;">
                                                <small style="color: #ff9800;">
                                                    <i class="fas fa-exclamation-circle"></i> Special request
                                                </small>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <form method="POST" class="status-form" style="margin-bottom: 5px;">
                                            <input type="hidden" name="victim_id" value="<?= $v['victim_id'] ?>">
                                            <input type="hidden" name="disaster_id" value="<?= $selected_disaster_id ?>">
                                            
                                            <td>
                                                <form method="POST" class="status-form" style="margin-bottom: 5px;">
                                                    <input type="hidden" name="victim_id" value="<?= $v['victim_id'] ?>">
                                                    <input type="hidden" name="disaster_id" value="<?= $selected_disaster_id ?>">
                                                    
                                                    <!-- Status Section -->
                                                    <div style="margin-bottom: 15px;">
                                                        <div style="font-weight: 600; color: #666; margin-bottom: 5px; font-size: 14px;">
                                                            Status:
                                                        </div>
                                                        
                                                        <div style="
                                                            background: white;
                                                            border: 2px solid #e0e0e0;
                                                            border-radius: 10px;
                                                            padding: 10px 15px;
                                                            font-size: 14px;
                                                            color: #333;
                                                            height: 46px;
                                                            display: flex;
                                                            align-items: center;
                                                            justify-content: space-between;
                                                            font-family: inherit;
                                                        ">
                                                            <span style="color: #333; font-weight: normal;">
                                                                <?= htmlspecialchars($v['need_status'] ?? 'Pending') ?>
                                                            </span>
                                                            
                                                            <?php if(isset($v['api_status'])): ?>
                                                            <span style="font-size: 11px; color: #666;">
                                                                <i class="fas fa-sync-alt"></i> API
                                                            </span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                    
                                                    <!-- Priority Level Section -->
                                                    <div style="margin-bottom: 15px;">
                                                        <div style="font-weight: 600; color: #666; margin-bottom: 5px; font-size: 14px;">
                                                            Priority Level:
                                                        </div>
                                                        
                                                        <div style="
                                                            background: white;
                                                            border: 2px solid #e0e0e0;
                                                            border-radius: 10px;
                                                            padding: 10px 15px;
                                                            font-size: 14px;
                                                            color: #333;
                                                            height: 46px;
                                                            display: flex;
                                                            align-items: center;
                                                            justify-content: space-between;
                                                            font-family: inherit;
                                                        ">
                                                            <span style="color: #333; font-weight: normal;">
                                                                <?= htmlspecialchars($v['need_priority'] ?? 'Medium') ?>
                                                            </span>
                                                            
                                                            <!-- Priority badge color based on level -->
                                                            <span style="
                                                                font-size: 11px;
                                                                padding: 3px 8px;
                                                                border-radius: 4px;
                                                                background: <?= 
                                                                    ($v['need_priority'] ?? 'Medium') == 'High' ? '#ffebee' : 
                                                                    (($v['need_priority'] ?? 'Medium') == 'Medium' ? '#fff3e0' : '#e8f5e9')
                                                                ?>;
                                                                color: <?= 
                                                                    ($v['need_priority'] ?? 'Medium') == 'High' ? '#f44336' : 
                                                                    (($v['need_priority'] ?? 'Medium') == 'Medium' ? '#ff9800' : '#4CAF50')
                                                                ?>;
                                                                font-weight: 500;
                                                            ">
                                                                <?= 
                                                                    ($v['need_priority'] ?? 'Medium') == 'High' ? '⚡ High' : 
                                                                    (($v['need_priority'] ?? 'Medium') == 'Medium' ? '⚖ Medium' : '📈 Low')
                                                                ?>
                                                            </span>
                                                        </div>
                                                    </div>
                                                    
                                                    <!-- Quick Status Buttons -->
                                                    <div style="margin-top: 10px; padding-top: 10px; border-top: 1px dashed #eee;">
                                                        <small style="color: #666;">
                                                            <i class="fas fa-info-circle"></i> Status is synced from approval system
                                                        </small>
                                                    </div>
                                                </form>
                                            </td>
                                    <td>
                                        <?= date('d M Y', strtotime($v['created_at'])) ?><br>
                                        <small style="color: #888;">
                                            <?= date('H:i', strtotime($v['created_at'])) ?>
                                        </small>
                                    </td>
                                    <td>
                                        <div style="display: flex; flex-direction: column; gap: 5px;">
                                            <!-- Quick Contact -->
                                            <div style="display: flex; gap: 3px;">
                                                <?php if($v['phone']): ?>
                                                    <a href="tel:<?= htmlspecialchars($v['phone']) ?>" 
                                                       class="btn btn-xs" style="background: #e3f2fd; flex: 1; padding: 4px 6px;"
                                                       title="Call <?= htmlspecialchars($v['full_name']) ?>">
                                                        <i class="fas fa-phone"></i>
                                                    </a>
                                                <?php endif; ?>
                                                <a href="mailto:<?= htmlspecialchars($v['email']) ?>" 
                                                   class="btn btn-xs" style="background: #f3e5f5; flex: 1; padding: 4px 6px;"
                                                   title="Email <?= htmlspecialchars($v['full_name']) ?>">
                                                    <i class="fas fa-envelope"></i>
                                                </a>
                                            </div>
                                            
                                            <!-- Main Action Buttons -->
                                            <div style="display: flex; flex-direction: column; gap: 3px;">
                                                <!-- View Details Button -->
                                                <button class="btn btn-xs" style="background: #e3f2fd; color: #2196F3;"
                                                        onclick="viewVictimDetails(<?= $v['victim_id'] ?>)"
                                                        title="View Full Details">
                                                    <i class="fas fa-eye"></i> Details
                                                </button>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Export Options -->
                    <div style="margin-top: 20px; text-align: center;">
                        <button onclick="exportVictims(<?= $selected_disaster_id ?>, '<?= $filter_type ?>')" class="btn btn-primary">
                            <i class="fas fa-download"></i> Export Victim List (CSV)
                        </button>
                        <button onclick="window.print()" class="btn" style="background: #f5f5f5; margin-left: 10px;">
                            <i class="fas fa-print"></i> Print This List
                        </button>
                        <button onclick="syncAllFromAPI()" class="btn" style="background: #e3f2fd; margin-left: 10px;">
                            <i class="fas fa-sync-alt"></i> Sync All Statuses
                        </button>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="section-header">
                    <h3><i class="fas fa-users"></i> Victim Management</h3>
                    <span class="badge badge-info">SELECT A DISASTER</span>
                </div>
                <div style="text-align: center; padding: 40px; color: #666;">
                    <i class="fas fa-mouse-pointer" style="font-size: 48px; margin-bottom: 20px; color: var(--primary);"></i>
                    <p>Select a disaster from the list above to view and manage victims.</p>
                    <p><small>Click "View" on any disaster to see its registered victims.</small></p>
                </div>
            <?php endif; ?>
        </div>

    <!-- AUDIT LOG SECTION - WORKING IFRAME -->
<div class="dashboard-section" id="audit-log">
    <div class="section-header">
        <h3><i class="fas fa-clipboard-list"></i> System Audit Trail</h3>
        <div>
            <button onclick="refreshAudit()" class="btn btn-sm" style="background: #e3f2fd; margin-left: 10px;">
                <i class="fas fa-sync-alt"></i> Refresh
            </button>
        </div>
    </div>
    
    <!-- IFRAME THAT ACTUALLY WORKS -->
    <iframe id="auditFrame" src="get_audit_log.php" 
            style="width:100%; height:500px; border:none; border-radius:10px; margin-top:20px;">
    </iframe>
</div>

<script>
function loadAuditFilter(filter) {
    const iframe = document.getElementById('auditFrame');
    iframe.src = 'get_audit_log.php?filter=' + filter;
    
    // Update active button
    document.querySelectorAll('#audit-log .filter-tab').forEach(btn => {
        btn.classList.remove('active');
    });
    event.target.classList.add('active');
}

function refreshAudit() {
    const iframe = document.getElementById('auditFrame');
    iframe.src = 'get_audit_log.php?t=' + Date.now();
}
</script>

<script>
function loadAuditFilter(filter) {
    const iframe = document.getElementById('auditFrame');
    iframe.src = 'admin/get_audit_log.php?filter=' + filter;
    
    // Update active button
    document.querySelectorAll('#audit-log .filter-tab').forEach(btn => {
        btn.classList.remove('active');
    });
    event.target.classList.add('active');
}

function refreshAudit() {
    const iframe = document.getElementById('auditFrame');
    iframe.src = iframe.src + '&t=' + Date.now();
}
</script>

    </main>

    <!-- Victim Details Modal -->
    <div id="victimModal" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-user-circle"></i> Victim Details</h3>
                <button class="modal-close" onclick="closeModal()">&times;</button>
            </div>
            <div class="modal-body" id="victimModalContent">
                <!-- Content will be loaded here by JavaScript -->
            </div>
        </div>
    </div>

    <script>
    // Mobile Toggle for Sidebar
    document.getElementById('mobileToggle').addEventListener('click', function() {
        document.getElementById('sidebar').classList.toggle('active');
    });

    // Check screen size on load and resize
    function checkScreenSize() {
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.getElementById('mainContent');
        const mobileToggle = document.getElementById('mobileToggle');
        
        if (window.innerWidth <= 1200) {
            sidebar.classList.add('sidebar-collapsed');
            mainContent.classList.add('main-content-expanded');
            mobileToggle.style.display = 'block';
        } else {
            sidebar.classList.remove('sidebar-collapsed', 'active');
            mainContent.classList.remove('main-content-expanded');
            mobileToggle.style.display = 'none';
        }
    }

    // Check on load and resize
    window.addEventListener('load', checkScreenSize);
    window.addEventListener('resize', checkScreenSize);

    // Smooth scroll for sidebar
    document.querySelector('.sidebar-content').addEventListener('wheel', function(e) {
        e.preventDefault();
        this.scrollTop += e.deltaY;
    });

    // Your existing JavaScript functions
    function exportData() {
        alert('Export functionality would be implemented here.\nYou can export disaster data to CSV or PDF formats.');
    }

    function printReport() {
        window.print();
    }

    function confirmDelete() {
        return confirm('Are you sure you want to delete this disaster? This action cannot be undone.');
    }

    // Victim Management Functions
    function callVictim(phoneNumber) {
        if(phoneNumber && phoneNumber !== 'Not provided' && phoneNumber !== '') {
            if(confirm('Call ' + phoneNumber + '?')) {
                window.location.href = 'tel:' + phoneNumber.replace(/\s+/g, '');
            }
        } else {
            alert('No phone number available for this victim.');
        }
    }

    function emailVictim(email) {
        if(confirm('Send email to ' + email + '?')) {
            window.location.href = 'mailto:' + email;
        }
    }

    // Send WhatsApp message
    function sendWhatsApp(phoneNumber) {
        if(phoneNumber && phoneNumber !== 'Not provided' && phoneNumber !== '') {
            const message = "Hello, this is Melaka Disaster Assistance. We're checking on your situation.";
            const whatsappUrl = `https://wa.me/${phoneNumber.replace(/\D/g, '')}?text=${encodeURIComponent(message)}`;
            window.open(whatsappUrl, '_blank');
        } else {
            alert('No phone number available for WhatsApp.');
        }
    }

    // Edit Victim Information
    function editVictim(victimId) {
        if(confirm('Edit victim #' + victimId + '?\nThis will open the edit form.')) {
            window.open('edit_victim.php?id=' + victimId, '_blank');
        }
    }

    // Generate PDF Report for a victim
    function generateReport(victimId) {
        if(confirm('Generate PDF report for this victim?')) {
            window.open('generate_report.php?victim_id=' + victimId, '_blank');
        }
    }

    // Generate reports for all victims in current disaster
    function generateAllReports(disasterId) {
        if(confirm('Generate PDF reports for ALL victims in this disaster?\nThis may take a moment.')) {
            window.location.href = 'generate_all_reports.php?disaster_id=' + disasterId;
        }
    }

    // Export victims to CSV
    function exportVictims(disasterId, filterType) {
        let filterText = '';
        if (filterType === 'baby') filterText = 'with babies';
        else if (filterType === 'elderly') filterText = 'elderly';
        else if (filterType === 'disabled') filterText = 'disabled';
        
        const message = filterText ? 
            `Export ${filterText} victim list for this disaster to CSV?` :
            'Export victim list for this disaster to CSV?';
        
        if(confirm(message)) {
            window.location.href = 'export_victims.php?disaster_id=' + disasterId + '&filter=' + filterType;
        }
    }

    // View Victim Details in Modal with actual data
    function viewVictimDetails(victimId) {
        // Show loading in modal
        document.getElementById('victimModalContent').innerHTML = `
            <div style="text-align: center; padding: 40px;">
                <i class="fas fa-spinner fa-spin" style="font-size: 24px; color: var(--primary);"></i>
                <p>Loading victim details...</p>
            </div>
        `;
        
        // Show modal
        document.getElementById('victimModal').style.display = 'flex';
        
        // Update modal header to show ID
        const modalHeader = document.querySelector('#victimModal .modal-header h3');
        if (modalHeader) {
            modalHeader.innerHTML = `<i class="fas fa-user-circle"></i> Victim Details #${victimId}`;
        }
        
        // Fetch actual data from server
        fetch(`?get_victim_details=1&victim_id=${victimId}`)
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    document.getElementById('victimModalContent').innerHTML = `
                        <div style="text-align: center; padding: 40px; color: #666;">
                            <i class="fas fa-exclamation-triangle" style="font-size: 48px; margin-bottom: 20px; color: #ff9800;"></i>
                            <p>Error: ${data.error}</p>
                            <button onclick="closeModal()" class="btn" style="background: var(--primary); color: white; margin-top: 20px;">
                                Close
                            </button>
                        </div>
                    `;
                    return;
                }
                
                // Format special needs
                const specialNeeds = [];
                if (data.has_baby) specialNeeds.push('👶 Has Baby');
                if (data.has_elderly) specialNeeds.push('👵 Has Elderly');
                if (data.has_disabled) specialNeeds.push('♿ Has Disabled');
                
                // Format registration date
                const regDate = new Date(data.created_at);
                const formattedDate = regDate.toLocaleDateString('en-MY', {
                    day: 'numeric',
                    month: 'long',
                    year: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit'
                });
                
                // Format need date if exists
                let needDate = 'Not recorded';
                if (data.need_created) {
                    const needDateObj = new Date(data.need_created);
                    needDate = needDateObj.toLocaleDateString('en-MY', {
                        day: 'numeric',
                        month: 'long',
                        year: 'numeric'
                    });
                }
                
                // Update modal content with actual data
                document.getElementById('victimModalContent').innerHTML = `
                    <div style="margin-bottom: 20px;">
                        <h4 style="color: var(--primary); margin-bottom: 10px; padding-bottom: 10px; border-bottom: 2px solid var(--border);">
                            ${data.full_name}
                        </h4>
                        <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 15px; flex-wrap: wrap;">
                            <span class="badge" style="background: #e3f2fd; color: var(--info);">
                                ID: #${data.victim_id}
                            </span>
                            ${data.email_verified ? 
                                '<span class="badge" style="background:#e8f5e9; color:#2e7d32;">Email Verified</span>' : 
                                '<span class="badge" style="background:#fff3e0; color:#ff9800;">Email Not Verified</span>'
                            }
                        </div>
                    </div>
                    
                    <div class="victim-details-grid">
                        <div class="detail-card">
                            <h4><i class="fas fa-id-card"></i> Personal Information</h4>
                            <div class="detail-row">
                                <div class="detail-label">IC Number</div>
                                <div class="detail-value">${data.ic_number}</div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-label">Email</div>
                                <div class="detail-value">${data.email}</div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-label">Phone Number</div>
                                <div class="detail-value">${data.phone || 'Not provided'}</div>
                            </div>
                        </div>
                        
                        <div class="detail-card">
                            <h4><i class="fas fa-map-marker-alt"></i> Location Details</h4>
                            <div class="detail-row">
                                <div class="detail-label">District</div>
                                <div class="detail-value">${data.district}</div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-label">City</div>
                                <div class="detail-value">${data.city || 'Not specified'}</div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-label">Postal Code</div>
                                <div class="detail-value">${data.postal_code || 'Not specified'}</div>
                            </div>
                        </div>
                        
                        <div class="detail-card">
                            <h4><i class="fas fa-home"></i> Address</h4>
                            <div class="detail-row">
                                <div class="detail-value" style="white-space: pre-wrap;">${data.address}</div>
                            </div>
                        </div>
                        
                        <div class="detail-card">
                            <h4><i class="fas fa-users"></i> Family & Needs</h4>
                            <div class="detail-row">
                                <div class="detail-label">Family Members</div>
                                <div class="detail-value">${data.family_members} persons</div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-label">Special Needs</div>
                                <div class="detail-value">
                                    ${specialNeeds.length > 0 ? 
                                        specialNeeds.map(need => `<span style="display: block; margin-bottom: 3px;">${need}</span>`).join('') : 
                                        'No special needs recorded'
                                    }
                                </div>
                            </div>
                        </div>
                        
                        <div class="detail-card">
                            <h4><i class="fas fa-clipboard-check"></i> Disaster Information</h4>
                            <div class="detail-row">
                                <div class="detail-label">Assigned Disaster</div>
                                <div class="detail-value">${data.disaster_name || 'Not assigned'}</div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-label">Needs Status</div>
                                <div class="detail-value">
                                    <span class="badge badge-${data.need_status ? data.need_status.toLowerCase() : 'pending'}">
                                        ${data.need_status || 'Pending'}
                                    </span>
                                    ${data.priority ? `<br><small>Priority: ${data.priority}</small>` : ''}
                                </div>
                            </div>
                        </div>
                        
                        <div class="detail-card">
                            <h4><i class="fas fa-calendar-alt"></i> Dates</h4>
                            <div class="detail-row">
                                <div class="detail-label">Registration Date</div>
                                <div class="detail-value">${formattedDate}</div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-label">Needs Recorded</div>
                                <div class="detail-value">${needDate}</div>
                            </div>
                        </div>
                    </div>
                    
                    ${data.special_request ? `
                        <div class="detail-card" style="margin-top: 20px;">
                            <h4><i class="fas fa-exclamation-circle"></i> Special Request</h4>
                            <div class="detail-row">
                                <div class="detail-value" style="background: #fff3e0; border-left-color: #ff9800; padding: 15px;">
                                    <i class="fas fa-exclamation-circle" style="color: #ff9800; margin-right: 10px;"></i>
                                    ${data.special_request}
                                </div>
                            </div>
                        </div>
                    ` : ''}
                    
                    <div class="modal-actions">
                        ${data.phone ? `
                            <button class="action-btn-circle call" onclick="callVictim('${data.phone.replace(/'/g, "\\'")}')" title="Call Victim">
                                <i class="fas fa-phone"></i>
                            </button>
                            <button class="action-btn-circle whatsapp" onclick="sendWhatsApp('${data.phone.replace(/'/g, "\\'")}')" title="Send WhatsApp">
                                <i class="fab fa-whatsapp"></i>
                            </button>
                        ` : ''}
                        <button class="action-btn-circle email" onclick="emailVictim('${data.email.replace(/'/g, "\\'")}')" title="Send Email">
                            <i class="fas fa-envelope"></i>
                        </button>
                    </div>
                    
                    <div style="text-align: center; margin-top: 20px;">
                        <button onclick="closeModal()" class="btn" style="background: var(--primary); color: white; padding: 10px 30px;">
                            <i class="fas fa-times"></i> Close
                        </button>
                    </div>
                `;
            })
            .catch(error => {
                console.error('Error fetching victim details:', error);
                document.getElementById('victimModalContent').innerHTML = `
                    <div style="text-align: center; padding: 40px; color: #666;">
                        <i class="fas fa-exclamation-triangle" style="font-size: 48px; margin-bottom: 20px; color: #f44336;"></i>
                        <p>Error loading victim details. Please try again.</p>
                        <button onclick="closeModal()" class="btn" style="background: var(--primary); color: white; margin-top: 20px;">
                            Close
                        </button>
                    </div>
                `;
            });
    }

    function closeModal() {
        document.getElementById('victimModal').style.display = 'none';
    }

    // Sync functions for friend's admin_dashboard.php
    function syncSingleFromAPI(victimId, disasterId) {
        if (confirm(`Sync status for victim #${victimId} from approval system?`)) {
            showLoading('Syncing from API...');
            
            window.location.href = `?sync_from_api=1&victim_id=${victimId}&disaster_id=${disasterId}&disaster_id=<?= $selected_disaster_id ?>&filter=<?= $filter_type ?><?= isset($_GET['page']) ? '&page=' . $_GET['page'] : '' ?>`;
        }
    }
    
    function syncAllFromAPI() {
        if (confirm('Sync ALL victim statuses from approval system? This will update all victims for this disaster.')) {
            showLoading('Syncing all from API...');
            
            // Get all victim IDs from checkboxes
            const syncCheckboxes = document.querySelectorAll('.sync-checkbox');
            const victimIds = Array.from(syncCheckboxes).map(cb => cb.value);
            
            if (victimIds.length === 0) {
                // If no checkboxes selected, sync all victims on current page
                const pageVictimIds = Array.from(document.querySelectorAll('tr td:nth-child(3) strong')).map(td => {
                    const match = td.closest('tr').querySelector('.sync-checkbox');
                    return match ? match.value : null;
                }).filter(id => id !== null);
                
                if (pageVictimIds.length === 0) {
                    alert('No victims to sync on this page.');
                    return;
                }
                
                // Create a form to submit
                const form = document.createElement('form');
                form.method = 'POST';
                form.style.display = 'none';
                
                const disasterInput = document.createElement('input');
                disasterInput.type = 'hidden';
                disasterInput.name = 'disaster_id';
                disasterInput.value = <?= $selected_disaster_id ?>;
                form.appendChild(disasterInput);
                
                pageVictimIds.forEach(victimId => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'victim_ids[]';
                    input.value = victimId;
                    form.appendChild(input);
                });
                
                const submitInput = document.createElement('input');
                submitInput.type = 'hidden';
                submitInput.name = 'bulk_sync_from_api';
                submitInput.value = '1';
                form.appendChild(submitInput);
                
                document.body.appendChild(form);
                form.submit();
            } else {
                // Use selected checkboxes
                const form = document.getElementById('bulkSyncForm');
                if (form) {
                    form.submit();
                }
            }
        }
    }
    
    // Bulk sync selection functionality
    document.addEventListener('DOMContentLoaded', function() {
        const selectAllSyncCheckbox = document.getElementById('selectAllSync');
        const syncCheckboxes = document.querySelectorAll('.sync-checkbox');
        const bulkSyncBar = document.getElementById('bulkSyncBar');
        const selectedSyncCountSpan = document.getElementById('selectedSyncCount');
        
        if (selectAllSyncCheckbox) {
            selectAllSyncCheckbox.addEventListener('change', function() {
                const isChecked = this.checked;
                syncCheckboxes.forEach(checkbox => {
                    checkbox.checked = isChecked;
                });
                updateBulkSyncBar();
            });
        }
        
        syncCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', updateBulkSyncBar);
        });
        
        function updateBulkSyncBar() {
            const checkedBoxes = document.querySelectorAll('.sync-checkbox:checked');
            const count = checkedBoxes.length;
            
            if (count > 0) {
                bulkSyncBar.classList.add('active');
                selectedSyncCountSpan.textContent = count;
                
                // Update the bulk sync form
                const bulkSyncForm = document.getElementById('bulkSyncForm');
                const existingInputs = bulkSyncForm.querySelectorAll('input[name="victim_ids[]"]');
                existingInputs.forEach(input => input.remove());
                
                checkedBoxes.forEach(checkbox => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'victim_ids[]';
                    input.value = checkbox.value;
                    bulkSyncForm.appendChild(input);
                });
            } else {
                bulkSyncBar.classList.remove('active');
            }
            
            // Update select all checkbox state
            if (selectAllSyncCheckbox) {
                if (count === syncCheckboxes.length && syncCheckboxes.length > 0) {
                    selectAllSyncCheckbox.checked = true;
                    selectAllSyncCheckbox.indeterminate = false;
                } else if (count > 0) {
                    selectAllSyncCheckbox.checked = false;
                    selectAllSyncCheckbox.indeterminate = true;
                } else {
                    selectAllSyncCheckbox.checked = false;
                    selectAllSyncCheckbox.indeterminate = false;
                }
            }
        }
        
        // Initialize
        updateBulkSyncBar();
    });
    
    function clearSyncSelection() {
        const syncCheckboxes = document.querySelectorAll('.sync-checkbox');
        syncCheckboxes.forEach(checkbox => {
            checkbox.checked = false;
        });
        const selectAllSyncCheckbox = document.getElementById('selectAllSync');
        if (selectAllSyncCheckbox) {
            selectAllSyncCheckbox.checked = false;
            selectAllSyncCheckbox.indeterminate = false;
        }
        updateBulkSyncBar();
    }
    
    function showLoading(message) {
        let loadingDiv = document.getElementById('loadingOverlay');
        if (!loadingDiv) {
            loadingDiv = document.createElement('div');
            loadingDiv.id = 'loadingOverlay';
            loadingDiv.style.cssText = `
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0,0,0,0.7);
                display: flex;
                flex-direction: column;
                justify-content: center;
                align-items: center;
                color: white;
                z-index: 99999;
                font-size: 18px;
            `;
            document.body.appendChild(loadingDiv);
        }
        
        loadingDiv.innerHTML = `
            <div style="text-align: center;">
                <i class="fas fa-spinner fa-spin" style="font-size: 48px; margin-bottom: 20px;"></i>
                <p>${message}</p>
            </div>
        `;
    }
    
    // Auto-dismiss messages after 5 seconds
    setTimeout(() => {
        const messages = document.querySelectorAll('.message');
        messages.forEach(msg => {
            msg.style.opacity = '0';
            msg.style.transition = 'opacity 0.5s';
            setTimeout(() => {
                if (msg.parentNode) {
                    msg.style.display = 'none';
                }
            }, 500);
        });
    }, 5000);

    // Smooth scrolling for navigation
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const targetId = this.getAttribute('href');
            if(targetId !== '#') {
                const targetElement = document.querySelector(targetId);
                if(targetElement) {
                    targetElement.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            }
        });
    });

    // Close modal when clicking outside
    document.getElementById('victimModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeModal();
        }
    });

    // Keyboard shortcut to close modal
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeModal();
        }
    });

    // Prevent form resubmission on page refresh
    if (window.history.replaceState) {
        window.history.replaceState(null, null, window.location.href);
    }

  // ========== SIMPLIFIED WORKING AUDIT LOG FUNCTIONS ==========
function loadAuditFilter(filter) {
    const iframe = document.getElementById('auditFrame');
    if (!iframe) return;
    
    // Use relative path
    iframe.src = './get_audit_log.php?filter=' + filter + '&t=' + Date.now();
    
    // Update active button
    document.querySelectorAll('#audit-log .filter-tab').forEach(btn => {
        btn.classList.remove('active');
    });
    
    // Mark clicked button as active
    event.target.classList.add('active');
}

function refreshAudit() {
    const iframe = document.getElementById('auditFrame');
    if (iframe) {
        // Reload iframe
        iframe.src = iframe.src.split('?')[0] + '?t=' + Date.now();
    }
}
// ========== END AUDIT LOG FUNCTIONS ==========
    
    </script>
</body>
</html>