<?php
// ========================================
// EDIT DISTRIBUTION PLAN
// User-friendly interface for editing distribution details
// ========================================

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';

// Start session for user data
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check user authentication
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$database = new Database();
$db = $database->getConnection();

$distribution_id = $_GET['id'] ?? null;

if (!$distribution_id) {
    header("Location: distribution_main.php");
    exit;
}

// API Configuration
$DISASTER_API_URL = 'http://10.147.17.116:8000/disaster.php';
$VICTIM_API_URL = 'http://10.147.17.116:8000/victim.php';
$NEEDS_API_URL = 'http://10.147.17.116:8000/needs.php';

/* ----------------------------------------
   HELPER FUNCTIONS FOR RESOURCE CLASSIFICATION
---------------------------------------- */
function determineResourceType($resource_name) {
    $lowerName = strtolower($resource_name);
    if (strpos($lowerName, 'disinfectant') !== false || 
        strpos($lowerName, 'sanitizer') !== false ||
        strpos($lowerName, 'alcohol') !== false ||
        strpos($lowerName, 'bandage') !== false ||
        strpos($lowerName, 'gauze') !== false ||
        strpos($lowerName, 'syringe') !== false ||
        strpos($lowerName, 'medicine') !== false ||
        strpos($lowerName, 'first aid') !== false ||
        strpos($lowerName, 'panadol') !== false ||
        strpos($lowerName, 'vitamin') !== false) {
        return 'Medical';
    } elseif (strpos($lowerName, 'blanket') !== false ||
             strpos($lowerName, 'towel') !== false ||
             strpos($lowerName, 'cloth') !== false ||
             strpos($lowerName, 'shirt') !== false ||
             strpos($lowerName, 'pant') !== false ||
             strpos($lowerName, 'clothing') !== false) {
        return 'Clothing';
    } elseif (strpos($lowerName, 'rice') !== false ||
             strpos($lowerName, 'water') !== false ||
             strpos($lowerName, 'food') !== false ||
             strpos($lowerName, 'noodle') !== false ||
             strpos($lowerName, 'biscuit') !== false ||
             strpos($lowerName, 'canned') !== false ||
             strpos($lowerName, 'drinking') !== false) {
        return 'Food';
    } elseif (strpos($lowerName, 'tent') !== false ||
             strpos($lowerName, 'tarpaulin') !== false ||
             strpos($lowerName, 'shelter') !== false) {
        return 'Shelter';
    } elseif (strpos($lowerName, 'emergency') !== false ||
             strpos($lowerName, 'kit') !== false ||
             strpos($lowerName, 'rope') !== false ||
             strpos($lowerName, 'general') !== false) {
        return 'General';
    } else {
        return 'General';
    }
}

function determineResourceUnit($resource_name) {
    $lowerName = strtolower($resource_name);
    if (strpos($lowerName, 'blanket') !== false ||
        strpos($lowerName, 'towel') !== false ||
        strpos($lowerName, 'shirt') !== false ||
        strpos($lowerName, 'pant') !== false ||
        strpos($lowerName, 'bandage') !== false) {
        return 'pieces';
    } elseif (strpos($lowerName, 'rice') !== false ||
             strpos($lowerName, 'water') !== false ||
             strpos($lowerName, 'noodle') !== false) {
        return 'kg/liters';
    } elseif (strpos($lowerName, 'tent') !== false ||
             strpos($lowerName, 'tarpaulin') !== false ||
             strpos($lowerName, 'kit') !== false ||
             strpos($lowerName, 'medicine') !== false ||
             strpos($lowerName, 'panadol') !== false ||
             strpos($lowerName, 'vitamin') !== false) {
        return 'units';
    } else {
        return 'units';
    }
}

/* ----------------------------------------
   FETCH DATA FROM APIS
---------------------------------------- */
function fetchDataFromAPI($url, $timeout = 5) {
    $context = stream_context_create([
        'http' => [
            'timeout' => $timeout,
            'header' => "Accept: application/json\r\n"
        ],
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
        ]
    ]);
    
    try {
        $response = @file_get_contents($url, false, $context);
        if ($response === FALSE) {
            return ['success' => false, 'error' => 'Server not responding: ' . $url];
        }
        
        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['success' => false, 'error' => 'Invalid JSON: ' . json_last_error_msg()];
        }
        
        return ['success' => true, 'data' => $data];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

// Fetch API data
$needsApiResult = fetchDataFromAPI($NEEDS_API_URL);

/* ----------------------------------------
   GET DISTRIBUTION DETAILS
---------------------------------------- */
// SIMPLIFIED QUERY - only select existing columns
$query = "
    SELECT 
        d.distribution_id,
        d.date,
        d.status,
        d.coordinator_name,
        d.coordinator_contact,
        d.estimated_duration,
        d.volunteers_needed,
        d.comments
    FROM distribution d
    WHERE d.distribution_id = ?
";

$stmt = $db->prepare($query);
if (!$stmt) {
    die("Prepare failed: " . $db->error);
}
$stmt->bind_param("i", $distribution_id);
$stmt->execute();
$result = $stmt->get_result();
$distribution = $result->fetch_assoc();
$stmt->close();

if (!$distribution) {
    echo "<div class='container'><div class='alert alert-danger'>Distribution not found!</div></div>";
    exit;
}

/* ----------------------------------------
   GET CURRENT DISTRIBUTION RESOURCES
---------------------------------------- */
$resources_query = "
    SELECT 
        dr.resource_id,
        dr.quantity_allocated,
        dr.quantity_distributed
    FROM distribution_resources dr
    WHERE dr.distribution_id = ?
";

$resources_stmt = $db->prepare($resources_query);
if (!$resources_stmt) {
    die("Prepare failed for resources: " . $db->error);
}
$resources_stmt->bind_param("i", $distribution_id);
$resources_stmt->execute();
$resources_result = $resources_stmt->get_result();
$current_resources = $resources_result->fetch_all(MYSQLI_ASSOC);
$resources_stmt->close();

// Calculate totals for display
$total_allocated = 0;
$total_resources = count($current_resources);
$resource_types = [];

foreach ($current_resources as $resource) {
    $total_allocated += $resource['quantity_allocated'];
}

/* ----------------------------------------
   GET AVAILABLE RESOURCES FROM API (UNIQUE ONLY)
---------------------------------------- */
$available_resources = [];
$unique_resource_ids = []; // Track unique resource IDs

if ($needsApiResult['success'] && isset($needsApiResult['data']) && is_array($needsApiResult['data'])) {
    $apiNeeds = $needsApiResult['data'];
    
    // Check if data is in a nested array structure
    if (isset($apiNeeds['data']) && is_array($apiNeeds['data'])) {
        $apiNeeds = $apiNeeds['data'];
    }
    
    foreach ($apiNeeds as $need) {
        $resource_id = $need['resource_id'] ?? $need['Resource_ID'] ?? $need['id'] ?? null;
        $resource_name = $need['temp_resource_name'] ?? 
                        $need['resource_name'] ?? 
                        $need['Resource_Name'] ?? 
                        $need['resourceName'] ?? 
                        null;
        
        // Skip if resource ID is null or empty
        if (!$resource_id || !$resource_name) {
            continue;
        }
        
        // Skip duplicates - only add each resource once
        if (in_array($resource_id, $unique_resource_ids)) {
            continue;
        }
        
        // Check if this resource is already in current resources
        $current_qty = 0;
        $current_distributed = 0;
        foreach ($current_resources as $current_resource) {
            if ($current_resource['resource_id'] == $resource_id) {
                $current_qty = $current_resource['quantity_allocated'];
                $current_distributed = $current_resource['quantity_distributed'];
                break;
            }
        }
        
        // Add to unique resource tracking
        $unique_resource_ids[] = $resource_id;
        
        $resource_type = determineResourceType($resource_name);
        if (!in_array($resource_type, $resource_types)) {
            $resource_types[] = $resource_type;
        }
        
        $available_resources[] = [
            'id' => $resource_id,
            'name' => trim($resource_name),
            'current_qty' => $current_qty,
            'current_distributed' => $current_distributed,
            'type' => $resource_type,
            'unit' => determineResourceUnit($resource_name)
        ];
    }
    
    // Sort resources by name for better organization
    usort($available_resources, function($a, $b) {
        return strcmp($a['name'], $b['name']);
    });
}

/* ----------------------------------------
   HANDLE FORM SUBMISSION
---------------------------------------- */
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db->begin_transaction();
        
        // Update distribution basic info
        if (isset($_POST['distribution_date'])) {
            // Get the values from POST and store them in variables
            $distribution_date = $_POST['distribution_date'];
            $coordinator_name = $_POST['coordinator_name'] ?? '';
            $coordinator_contact = $_POST['coordinator_contact'] ?? '';
            $estimated_duration = $_POST['estimated_duration'] ?? 0;
            $volunteers_needed = $_POST['volunteers_needed'] ?? 0;
            $comments = $_POST['comments'] ?? '';
            
            // SIMPLIFIED UPDATE QUERY - no last_updated column
            $update_query = "
                UPDATE distribution 
                SET date = ?, 
                    coordinator_name = ?, 
                    coordinator_contact = ?, 
                    estimated_duration = ?, 
                    volunteers_needed = ?, 
                    comments = ?
                WHERE distribution_id = ?
            ";
            
            $update_stmt = $db->prepare($update_query);
            if (!$update_stmt) {
                throw new Exception("Prepare failed: " . $db->error);
            }
            
            $update_stmt->bind_param(
                "ssssssi",
                $distribution_date,
                $coordinator_name,
                $coordinator_contact,
                $estimated_duration,
                $volunteers_needed,
                $comments,
                $distribution_id
            );
            
            if (!$update_stmt->execute()) {
                throw new Exception("Execute failed: " . $update_stmt->error);
            }
            $update_stmt->close();
        }
        
        // Update resources
        if (isset($_POST['resources'])) {
            // Remove all current resources
            $delete_resources_query = "DELETE FROM distribution_resources WHERE distribution_id = ?";
            $delete_stmt = $db->prepare($delete_resources_query);
            if (!$delete_stmt) {
                throw new Exception("Prepare failed for delete resources: " . $db->error);
            }
            $delete_stmt->bind_param("i", $distribution_id);
            if (!$delete_stmt->execute()) {
                throw new Exception("Execute failed for delete resources: " . $delete_stmt->error);
            }
            $delete_stmt->close();
            
            // Add updated resources - REMOVED allocated_at column
            $resources = json_decode($_POST['resources'], true);
            if (is_array($resources) && !empty($resources)) {
                $insert_resource_query = "
                    INSERT INTO distribution_resources (distribution_id, resource_id, quantity_allocated) 
                    VALUES (?, ?, ?)
                ";
                $insert_stmt = $db->prepare($insert_resource_query);
                if (!$insert_stmt) {
                    throw new Exception("Prepare failed for insert resources: " . $db->error);
                }
                
                foreach ($resources as $resource) {
                    if (isset($resource['id']) && isset($resource['quantity']) && $resource['quantity'] > 0) {
                        $resource_id = $resource['id'];
                        $quantity = $resource['quantity'];
                        $insert_stmt->bind_param("iii", $distribution_id, $resource_id, $quantity);
                        if (!$insert_stmt->execute()) {
                            throw new Exception("Execute failed for insert resource: " . $insert_stmt->error);
                        }
                    }
                }
                $insert_stmt->close();
            }
        }
        
        $db->commit();
        $success_message = "Distribution plan updated successfully!";
        
        // Refresh the data
        header("Location: edit_distribution.php?id=" . $distribution_id . "&success=1");
        exit;
        
    } catch (Exception $e) {
        $db->rollback();
        $error_message = "Error updating distribution: " . $e->getMessage();
    }
}

// Check for success parameter
if (isset($_GET['success']) && $_GET['success'] == 1) {
    $success_message = "Distribution plan updated successfully!";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Distribution Plan #<?php echo $distribution_id; ?> | Disaster Relief System</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/main.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* ===== BASE STYLES ===== */
        :root {
            --primary: #4361ee;
            --secondary: #3a0ca3;
            --success: #4cc9f0;
            --warning: #f8961e;
            --danger: #f94144;
            --light: #f8f9fa;
            --dark: #212529;
            --gray: #6c757d;
            --light-gray: #e9ecef;
            --border-radius: 12px;
            --box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: var(--dark);
            line-height: 1.6;
            min-height: 100vh;
        }
        
        .main-content {
            padding: 20px;
            min-height: 100vh;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        /* ===== HEADER ===== */
        .page-header {
            background: white;
            color: var(--dark);
            padding: 25px 30px;
            border-radius: var(--border-radius);
            margin-bottom: 30px;
            box-shadow: var(--box-shadow);
            position: relative;
            overflow: hidden;
            border-left: 5px solid var(--primary);
        }
        
        .page-header::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 200px;
            height: 200px;
            background: linear-gradient(45deg, var(--primary) 0%, rgba(67, 97, 238, 0.1) 100%);
            border-radius: 50%;
            transform: translate(100px, -100px);
        }
        
        .header-content {
            position: relative;
            z-index: 2;
        }
        
        .header-content h1 {
            font-size: 1.8rem;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 15px;
            color: var(--secondary);
        }
        
        .header-content h1 i {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            padding: 12px;
            border-radius: 12px;
            font-size: 1.4rem;
        }
        
        .header-subtitle {
            font-size: 0.95rem;
            color: var(--gray);
            margin-bottom: 15px;
        }
        
        .header-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        /* ===== FORM STYLES ===== */
        .form-container {
            background: white;
            border-radius: var(--border-radius);
            padding: 35px;
            box-shadow: var(--box-shadow);
            margin-bottom: 25px;
        }
        
        .form-section {
            margin-bottom: 35px;
            padding-bottom: 25px;
            border-bottom: 1px solid var(--light-gray);
        }
        
        .form-section:last-child {
            border-bottom: none;
            margin-bottom: 20px;
        }
        
        .form-section h3 {
            font-size: 1.2rem;
            color: var(--secondary);
            margin-bottom: 20px;
            padding-bottom: 8px;
            border-bottom: 2px solid var(--primary);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .form-section h3 i {
            color: var(--primary);
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 6px;
            font-weight: 600;
            color: var(--dark);
            font-size: 0.9rem;
        }
        
        .required::after {
            content: ' *';
            color: var(--danger);
        }
        
        input, select, textarea {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid var(--light-gray);
            border-radius: 8px;
            font-size: 0.95rem;
            transition: var(--transition);
            font-family: inherit;
            background: white;
        }
        
        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.1);
            transform: translateY(-1px);
        }
        
        textarea {
            min-height: 100px;
            resize: vertical;
            line-height: 1.5;
        }
        
        /* ===== RESOURCE MANAGEMENT ===== */
        .resource-management {
            background: #f8fafc;
            border-radius: 10px;
            padding: 20px;
            margin-top: 15px;
            border: 1px solid var(--light-gray);
        }
        
        /* ===== ALLOCATED RESOURCES SUMMARY ===== */
        .allocated-summary {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            border: 1px solid var(--light-gray);
        }
        
        .summary-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--light-gray);
        }
        
        .summary-title {
            font-size: 1.1rem;
            color: var(--secondary);
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .summary-stats {
            display: flex;
            gap: 20px;
        }
        
        .stat-item {
            text-align: center;
            padding: 10px 15px;
            background: #f8fafc;
            border-radius: 8px;
            min-width: 100px;
        }
        
        .stat-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary);
            display: block;
        }
        
        .stat-label {
            font-size: 0.8rem;
            color: var(--gray);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        /* ===== RESOURCE ITEMS ===== */
        .resource-list {
            margin-top: 15px;
        }
        
        .resource-item {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr auto;
            gap: 15px;
            align-items: center;
            padding: 18px;
            background: white;
            border-radius: 10px;
            margin-bottom: 12px;
            border: 1px solid var(--light-gray);
            transition: var(--transition);
        }
        
        .resource-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            border-color: var(--primary);
        }
        
        .resource-info {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        
        .resource-info h4 {
            font-size: 1rem;
            margin-bottom: 3px;
            color: var(--dark);
            font-weight: 600;
        }
        
        .resource-meta {
            display: flex;
            gap: 15px;
            font-size: 0.85rem;
            color: var(--gray);
        }
        
        .resource-meta span {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .resource-allocations {
            display: flex;
            flex-direction: column;
            gap: 5px;
            background: #f8fafc;
            padding: 10px;
            border-radius: 6px;
        }
        
        .allocation-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.85rem;
        }
        
        .allocation-label {
            color: var(--gray);
            font-weight: 500;
        }
        
        .allocation-value {
            color: var(--dark);
            font-weight: 600;
            background: white;
            padding: 2px 8px;
            border-radius: 4px;
            border: 1px solid var(--light-gray);
        }
        
        .allocated-value {
            color: var(--primary);
            border-color: rgba(67, 97, 238, 0.2);
            background: rgba(67, 97, 238, 0.05);
        }
        
        .distributed-value {
            color: var(--success);
            border-color: rgba(76, 201, 240, 0.2);
            background: rgba(76, 201, 240, 0.05);
        }
        
        .resource-quantity {
            display: flex;
            align-items: center;
            gap: 10px;
            justify-content: center;
        }
        
        .quantity-input {
            width: 80px;
            padding: 8px;
            border: 2px solid var(--light-gray);
            border-radius: 6px;
            text-align: center;
            font-weight: 600;
            font-size: 1rem;
            transition: var(--transition);
            background: white;
        }
        
        .quantity-input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 2px rgba(67, 97, 238, 0.1);
            transform: scale(1.05);
        }
        
        .remove-resource {
            background: linear-gradient(135deg, var(--danger) 0%, #d90429 100%);
            color: white;
            border: none;
            width: 36px;
            height: 36px;
            border-radius: 8px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: var(--transition);
            font-size: 0.9rem;
        }
        
        .remove-resource:hover {
            transform: scale(1.1) rotate(90deg);
            box-shadow: 0 4px 12px rgba(249, 65, 68, 0.3);
        }
        
        /* ===== AVAILABLE RESOURCES ===== */
        .add-resource-btn {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 8px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: var(--transition);
            font-weight: 600;
            font-size: 0.9rem;
            width: 100%;
            justify-content: center;
        }
        
        .add-resource-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(67, 97, 238, 0.3);
        }
        
        .resources-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        
        .resource-card {
            background: white;
            border: 2px solid var(--light-gray);
            border-radius: 10px;
            padding: 18px;
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }
        
        .resource-card:hover {
            border-color: var(--primary);
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.08);
        }
        
        .resource-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: var(--primary);
            opacity: 0;
            transition: var(--transition);
        }
        
        .resource-card:hover::before {
            opacity: 1;
        }
        
        .resource-card h4 {
            font-size: 1rem;
            margin-bottom: 8px;
            color: var(--dark);
            font-weight: 600;
        }
        
        .resource-card p {
            font-size: 0.85rem;
            color: var(--gray);
            margin-bottom: 3px;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .resource-type-badge {
            display: inline-block;
            padding: 4px 10px;
            background: rgba(67, 97, 238, 0.1);
            color: var(--primary);
            border-radius: 15px;
            font-size: 0.75rem;
            font-weight: 600;
            margin-top: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        /* ===== ACTION BUTTONS ===== */
        .action-buttons {
            display: flex;
            gap: 15px;
            justify-content: flex-end;
            margin-top: 35px;
            padding-top: 20px;
            border-top: 1px solid var(--light-gray);
        }
        
        .btn {
            padding: 14px 28px;
            border-radius: 10px;
            border: none;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.95rem;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            transition: var(--transition);
            letter-spacing: 0.5px;
            min-width: 140px;
            justify-content: center;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.15);
        }
        
        .btn-cancel {
            background: white;
            color: var(--dark);
            border: 2px solid var(--light-gray);
        }
        
        .btn-cancel:hover {
            background: #f8f9fa;
            border-color: var(--danger);
            color: var(--danger);
        }
        
        .btn-save {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
        }
        
        .btn-save:hover {
            background: linear-gradient(135deg, #3a56d4 0%, #2a0a82 100%);
            box-shadow: 0 8px 25px rgba(67, 97, 238, 0.3);
        }
        
        /* ===== ALERTS ===== */
        .alert {
            padding: 18px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 15px;
            border-left: 4px solid;
            animation: slideIn 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
        }
        
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-30px) scale(0.95);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }
        
        .alert i {
            font-size: 1.4rem;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border-left-color: var(--success);
        }
        
        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border-left-color: var(--danger);
        }
        
        /* ===== LOADING OVERLAY ===== */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255,255,255,0.95);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            opacity: 0;
            pointer-events: none;
            transition: var(--transition);
            backdrop-filter: blur(4px);
        }
        
        .loading-overlay.active {
            opacity: 1;
            pointer-events: all;
        }
        
        .loading-content {
            text-align: center;
            background: white;
            padding: 35px;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.1);
        }
        
        .loading-spinner {
            width: 50px;
            height: 50px;
            border: 3px solid #f0f0f0;
            border-top: 3px solid var(--primary);
            border-radius: 50%;
            animation: spin 1s cubic-bezier(0.68, -0.55, 0.265, 1.55) infinite;
            margin: 0 auto 15px;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        /* ===== EMPTY STATES ===== */
        .empty-state {
            text-align: center;
            padding: 50px 20px;
            color: var(--gray);
        }
        
        .empty-state i {
            font-size: 3.5rem;
            color: #e2e8f0;
            margin-bottom: 15px;
            opacity: 0.5;
        }
        
        .empty-state h4 {
            font-size: 1.2rem;
            margin-bottom: 8px;
            color: var(--dark);
        }
        
        .empty-state p {
            max-width: 400px;
            margin: 0 auto;
            line-height: 1.5;
            font-size: 0.9rem;
        }
        
        /* ===== RESPONSIVE ===== */
        @media (max-width: 768px) {
            .container {
                padding: 15px;
            }
            
            .page-header {
                padding: 20px;
            }
            
            .form-container {
                padding: 25px;
            }
            
            .header-content h1 {
                font-size: 1.5rem;
            }
            
            .form-row {
                grid-template-columns: 1fr;
                gap: 15px;
            }
            
            .resource-item {
                grid-template-columns: 1fr;
                gap: 10px;
            }
            
            .resources-grid {
                grid-template-columns: 1fr;
            }
            
            .summary-stats {
                flex-direction: column;
                gap: 10px;
            }
            
            .stat-item {
                min-width: auto;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
                min-width: auto;
            }
            
            .resource-allocations {
                grid-column: 1 / -1;
            }
        }
        
        /* ===== STATUS BADGE ===== */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .status-planned { 
            background: linear-gradient(135deg, #ffd166 0%, #ff9e00 100%); 
            color: white; 
        }
        .status-scheduled { 
            background: linear-gradient(135deg, #118ab2 0%, #073b4c 100%); 
            color: white; 
        }
        .status-active { 
            background: linear-gradient(135deg, #06d6a0 0%, #04a777 100%); 
            color: white; 
        }
        .status-completed { 
            background: linear-gradient(135deg, #7209b7 0%, #3a0ca3 100%); 
            color: white; 
        }
        
        /* ===== CUSTOM SCROLLBAR ===== */
        ::-webkit-scrollbar {
            width: 6px;
        }
        
        ::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 3px;
        }
        
        ::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            border-radius: 3px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(135deg, #3a56d4 0%, #2a0a82 100%);
        }
        
        /* ===== ANIMATIONS ===== */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(15px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .form-section {
            animation: fadeInUp 0.5s cubic-bezier(0.4, 0, 0.2, 1) forwards;
        }
        
        /* ===== HOVER EFFECTS ===== */
        input[type="number"]::-webkit-inner-spin-button,
        input[type="number"]::-webkit-outer-spin-button {
            opacity: 1;
            height: 25px;
        }
        
        select {
            appearance: none;
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%236c757d' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 12px center;
            background-size: 14px;
            padding-right: 40px;
        }
        
        /* ===== TYPE COLORS ===== */
        .type-medical {
            background: rgba(76, 201, 240, 0.1);
            color: #4cc9f0;
        }
        
        .type-food {
            background: rgba(248, 150, 30, 0.1);
            color: #f8961e;
        }
        
        .type-shelter {
            background: rgba(106, 13, 173, 0.1);
            color: #6a0dad;
        }
        
        .type-clothing {
            background: rgba(155, 89, 182, 0.1);
            color: #9b59b6;
        }
        
        .type-general {
            background: rgba(108, 117, 125, 0.1);
            color: #6c757d;
        }
    </style>
</head>
<body>
    <!-- Loading Overlay -->
    <div id="loadingOverlay" class="loading-overlay">
        <div class="loading-content">
            <div class="loading-spinner"></div>
            <h3 style="margin-bottom: 8px; color: var(--dark); font-size: 1.1rem;">Saving Changes...</h3>
            <p style="color: var(--gray); font-size: 0.9rem;">Please wait while we update the distribution plan</p>
        </div>
    </div>

    <!-- Main Content Area -->
    <main class="main-content">
        <div class="container">
            <!-- Success/Error Messages -->
            <?php if ($success_message): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <div>
                    <h4 style="margin-bottom: 5px; font-size: 1.1rem;">Success!</h4>
                    <p style="font-size: 0.9rem;"><?php echo $success_message; ?></p>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i>
                <div>
                    <h4 style="margin-bottom: 5px; font-size: 1.1rem;">Error!</h4>
                    <p style="font-size: 0.9rem;"><?php echo $error_message; ?></p>
                </div>
            </div>
            <?php endif; ?>

            <!-- Page Header -->
            <div class="page-header">
                <div class="header-content">
                    <h1>
                        <i class="fas fa-edit"></i>
                        Edit Distribution Plan
                    </h1>
                    <p class="header-subtitle">
                        <strong>Distribution ID:</strong> <span style="color: var(--primary); font-weight: 600;">DIST<?php echo str_pad($distribution_id, 3, '0', STR_PAD_LEFT); ?></span>
                        • <strong>Status:</strong> <span class="status-badge status-<?php echo strtolower($distribution['status'] ?? 'planned'); ?>">
                            <?php echo ucfirst($distribution['status'] ?? 'Planned'); ?>
                        </span>
                    </p>
                    
                    <div class="header-actions">
                        <a href="view_distribution.php?id=<?php echo $distribution_id; ?>" class="btn btn-cancel" style="min-width: auto; padding: 10px 20px;">
                            <i class="fas fa-eye"></i> View Details
                        </a>
                        <a href="distribution_main.php" class="btn btn-cancel" style="min-width: auto; padding: 10px 20px;">
                            <i class="fas fa-arrow-left"></i> Back to Dashboard
                        </a>
                    </div>
                </div>
            </div>

            <!-- Edit Form -->
            <form id="editDistributionForm" method="POST" class="form-container">
                <input type="hidden" name="resources" id="resourcesInput" value='<?php echo json_encode(array_map(function($r) {
                    return [
                        'id' => $r['resource_id'],
                        'quantity' => $r['quantity_allocated']
                    ];
                }, $current_resources)); ?>'>
                
                <!-- Basic Information Section -->
                <div class="form-section">
                    <h3><i class="fas fa-info-circle"></i> Basic Information</h3>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="distribution_date" class="required">
                                <i class="fas fa-calendar-alt"></i> Distribution Date & Time
                            </label>
                            <input type="datetime-local" 
                                   id="distribution_date" 
                                   name="distribution_date" 
                                   value="<?php echo date('Y-m-d\TH:i', strtotime($distribution['date'])); ?>" 
                                   required>
                            <small style="color: var(--gray); margin-top: 5px; display: block; font-size: 0.85rem;">
                                Select when the distribution will take place
                            </small>
                        </div>
                        
                        <div class="form-group">
                            <label for="estimated_duration">
                                <i class="fas fa-clock"></i> Estimated Duration (hours)
                            </label>
                            <input type="number" 
                                   id="estimated_duration" 
                                   name="estimated_duration" 
                                   value="<?php echo $distribution['estimated_duration'] ?? 4; ?>" 
                                   min="1" 
                                   max="24" 
                                   step="0.5"
                                   style="background: #f8fafc;">
                            <small style="color: var(--gray); margin-top: 5px; display: block; font-size: 0.85rem;">
                                How long will the distribution take?
                            </small>
                        </div>
                    </div>
                </div>
                
                <!-- Coordinator Information -->
                <div class="form-section">
                    <h3><i class="fas fa-user-tie"></i> Coordinator Information</h3>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="coordinator_name">
                                <i class="fas fa-user"></i> Coordinator Name
                            </label>
                            <input type="text" 
                                   id="coordinator_name" 
                                   name="coordinator_name" 
                                   value="<?php echo htmlspecialchars($distribution['coordinator_name'] ?? ''); ?>" 
                                   placeholder="Enter coordinator's full name"
                                   style="background: #f8fafc;">
                        </div>
                        
                        <div class="form-group">
                            <label for="coordinator_contact">
                                <i class="fas fa-phone"></i> Contact Information
                            </label>
                            <input type="text" 
                                   id="coordinator_contact" 
                                   name="coordinator_contact" 
                                   value="<?php echo htmlspecialchars($distribution['coordinator_contact'] ?? ''); ?>" 
                                   placeholder="Phone number or email"
                                   style="background: #f8fafc;">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="volunteers_needed">
                            <i class="fas fa-users"></i> Volunteers Required
                        </label>
                        <input type="number" 
                               id="volunteers_needed" 
                               name="volunteers_needed" 
                               value="<?php echo $distribution['volunteers_needed'] ?? 5; ?>" 
                               min="1" 
                               max="100"
                               style="background: #f8fafc;">
                        <small style="color: var(--gray); margin-top: 5px; display: block; font-size: 0.85rem;">
                            Number of volunteers needed for this distribution
                        </small>
                    </div>
                </div>
                
                <!-- Allocated Resources Section -->
                <div class="form-section">
                    <h3><i class="fas fa-check-circle"></i> Allocated Resources</h3>
                    
                    <!-- Summary Card -->
                    <div class="allocated-summary">
                        <div class="summary-header">
                            <div class="summary-title">
                                <i class="fas fa-chart-bar"></i>
                                Resource Allocation Summary
                            </div>
                            <div class="summary-stats">
                                <div class="stat-item">
                                    <span class="stat-value"><?php echo $total_resources; ?></span>
                                    <span class="stat-label">Resources</span>
                                </div>
                                <div class="stat-item">
                                    <span class="stat-value"><?php echo count($resource_types); ?></span>
                                    <span class="stat-label">Types</span>
                                </div>
                                <div class="stat-item">
                                    <span class="stat-value"><?php echo $total_allocated; ?></span>
                                    <span class="stat-label">Total Units</span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="resource-list" id="resourceList">
                            <?php if (empty($current_resources)): ?>
                            <div class="empty-state">
                                <i class="fas fa-box-open"></i>
                                <h4>No Resources Allocated</h4>
                                <p>Add resources from the available list below to get started.</p>
                            </div>
                            <?php else: ?>
                                <?php 
                                foreach ($current_resources as $resource): 
                                    // Find resource details
                                    $resource_details = null;
                                    foreach ($available_resources as $avail_resource) {
                                        if ($avail_resource['id'] == $resource['resource_id']) {
                                            $resource_details = $avail_resource;
                                            break;
                                        }
                                    }
                                    
                                    if ($resource_details):
                                ?>
                                <div class="resource-item" data-resource-id="<?php echo $resource['resource_id']; ?>">
                                    <div class="resource-info">
                                        <h4><?php echo htmlspecialchars($resource_details['name']); ?></h4>
                                        <div class="resource-meta">
                                            <span><i class="fas fa-tag"></i> <?php echo htmlspecialchars($resource_details['type']); ?></span>
                                            <span><i class="fas fa-balance-scale"></i> <?php echo htmlspecialchars($resource_details['unit']); ?></span>
                                        </div>
                                    </div>
                                    
                                    <div class="resource-allocations">
                                        <div class="allocation-item">
                                            <span class="allocation-label">Allocated:</span>
                                            <span class="allocation-value allocated-value"><?php echo $resource['quantity_allocated']; ?></span>
                                        </div>
                                        <div class="allocation-item">
                                            <span class="allocation-label">Distributed:</span>
                                            <span class="allocation-value distributed-value"><?php echo $resource['quantity_distributed']; ?></span>
                                        </div>
                                    </div>
                                    
                                    <div class="resource-quantity">
                                        <input type="number" 
                                               class="quantity-input" 
                                               value="<?php echo $resource['quantity_allocated']; ?>" 
                                               min="1" 
                                               max="1000"
                                               onchange="updateResourceQuantity(<?php echo $resource['resource_id']; ?>, this.value)"
                                               style="background: white;"
                                               title="Adjust allocation quantity">
                                    </div>
                                    
                                    <button type="button" 
                                            class="remove-resource" 
                                            onclick="removeResource(<?php echo $resource['resource_id']; ?>)"
                                            title="Remove resource from distribution">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                                <?php endif; endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Available Resources Section -->
                <div class="form-section">
                    <h3><i class="fas fa-box"></i> Available Resources</h3>
                    <p style="color: var(--gray); margin-bottom: 15px; font-size: 0.9rem;">
                        Click "Add to Distribution" to allocate resources to this distribution plan.
                    </p>
                    
                    <div class="resource-management">
                        <div id="availableResources">
                            <?php 
                            // Filter out already allocated resources
                            $allocated_resource_ids = array_column($current_resources, 'resource_id');
                            $available_for_allocation = array_filter($available_resources, function($resource) use ($allocated_resource_ids) {
                                return !in_array($resource['id'], $allocated_resource_ids);
                            });
                            
                            // Group resources by type
                            $grouped_resources = [];
                            foreach ($available_for_allocation as $resource) {
                                $type = $resource['type'];
                                if (!isset($grouped_resources[$type])) {
                                    $grouped_resources[$type] = [];
                                }
                                $grouped_resources[$type][] = $resource;
                            }
                            ?>
                            
                            <?php if (empty($available_for_allocation)): ?>
                            <div class="empty-state">
                                <i class="fas fa-check-circle"></i>
                                <h4>All Resources Allocated</h4>
                                <p>All available resources have been allocated to this distribution.</p>
                            </div>
                            <?php else: ?>
                                <!-- Show resources grouped by type -->
                                <?php foreach ($grouped_resources as $type => $resources): ?>
                                <div class="resource-section" style="margin-bottom: 25px;">
                                    <h5 style="font-size: 1rem; color: var(--primary); margin-bottom: 15px; padding-bottom: 8px; border-bottom: 1px solid var(--light-gray);">
                                        <i class="fas fa-folder"></i> <?php echo htmlspecialchars($type); ?> Resources
                                        <span style="color: var(--gray); font-size: 0.85rem; font-weight: normal; margin-left: 8px;">
                                            (<?php echo count($resources); ?> items)
                                        </span>
                                    </h5>
                                    <div class="resources-grid">
                                        <?php foreach ($resources as $resource): ?>
                                        <div class="resource-card">
                                            <h4><?php echo htmlspecialchars($resource['name']); ?></h4>
                                            <p><i class="fas fa-info-circle"></i> <strong>Type:</strong> <?php echo htmlspecialchars($resource['type']); ?></p>
                                            <p><i class="fas fa-balance-scale"></i> <strong>Unit:</strong> <?php echo htmlspecialchars($resource['unit']); ?></p>
                                            <div class="resource-type-badge type-<?php echo strtolower($resource['type']); ?>">
                                                <?php echo htmlspecialchars($resource['type']); ?>
                                            </div>
                                            <button type="button" 
                                                    class="add-resource-btn" 
                                                    onclick="addResource(<?php echo $resource['id']; ?>, '<?php echo addslashes($resource['name']); ?>', '<?php echo addslashes($resource['type']); ?>', '<?php echo addslashes($resource['unit']); ?>')"
                                                    data-resource-id="<?php echo $resource['id']; ?>">
                                                <i class="fas fa-plus"></i> Add to Distribution
                                            </button>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                                
                                <!-- Summary -->
                                <div style="background: var(--light); padding: 15px; border-radius: 8px; margin-top: 20px; text-align: center;">
                                    <p style="color: var(--gray); font-size: 0.9rem; margin: 0;">
                                        <i class="fas fa-info-circle"></i> 
                                        Showing <?php echo count($available_for_allocation); ?> unique resources across 
                                        <?php echo count($grouped_resources); ?> categories
                                    </p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Additional Information -->
                <div class="form-section">
                    <h3><i class="fas fa-sticky-note"></i> Additional Information</h3>
                    
                    <div class="form-group">
                        <label for="comments">
                            <i class="fas fa-comment-alt"></i> Notes & Comments
                        </label>
                        <textarea id="comments" 
                                  name="comments" 
                                  placeholder="Add any special instructions, comments, or notes about this distribution..."
                                  style="background: #f8fafc;"><?php echo htmlspecialchars($distribution['comments'] ?? ''); ?></textarea>
                        <small style="color: var(--gray); margin-top: 5px; display: block; font-size: 0.85rem;">
                            This information will be visible to all volunteers and coordinators.
                        </small>
                    </div>
                </div>
                
                <!-- Form Actions -->
                <div class="action-buttons">
                    <button type="button" class="btn btn-cancel" onclick="window.history.back()">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button type="submit" class="btn btn-save">
                        <i class="fas fa-save"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
    </main>

    <script>
        // Global variables
        let allocatedResources = <?php echo json_encode(array_map(function($r) {
            return [
                'id' => $r['resource_id'],
                'quantity' => $r['quantity_allocated']
            ];
        }, $current_resources)); ?>;
        
        // Initialize form
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize form submission
            document.getElementById('editDistributionForm').addEventListener('submit', handleFormSubmit);
            
            // Add auto-focus to first input
            const firstInput = document.querySelector('input[required]');
            if (firstInput) {
                firstInput.focus();
            }
            
            // Add smooth scrolling for form sections
            document.querySelectorAll('.form-section h3').forEach(header => {
                header.style.cursor = 'pointer';
                header.addEventListener('click', function() {
                    const section = this.parentElement;
                    section.scrollIntoView({ behavior: 'smooth', block: 'start' });
                });
            });
            
            // Initialize tooltips for quantity inputs
            document.querySelectorAll('.quantity-input').forEach(input => {
                input.addEventListener('focus', function() {
                    this.select();
                });
            });
        });
        
        // Resource management
        function addResource(resourceId, resourceName, resourceType, resourceUnit) {
            // Check if already allocated
            const existingResource = allocatedResources.find(r => r.id === resourceId);
            if (existingResource) {
                showNotification('This resource is already allocated.', 'warning');
                
                // Add visual feedback to the button
                const button = document.querySelector(`button[data-resource-id="${resourceId}"]`);
                if (button) {
                    button.style.background = 'var(--danger)';
                    button.innerHTML = '<i class="fas fa-times"></i> Already Added';
                    button.disabled = true;
                    setTimeout(() => {
                        button.style.background = '';
                        button.innerHTML = '<i class="fas fa-plus"></i> Add to Distribution';
                        button.disabled = false;
                    }, 2000);
                }
                return;
            }
            
            // Add to allocated resources array
            allocatedResources.push({
                id: resourceId,
                quantity: 1
            });
            
            // Add to UI
            const resourceList = document.getElementById('resourceList');
            
            // Remove empty state if present
            const emptyState = resourceList.querySelector('.empty-state');
            if (emptyState) {
                emptyState.remove();
            }
            
            const resourceItem = document.createElement('div');
            resourceItem.className = 'resource-item';
            resourceItem.setAttribute('data-resource-id', resourceId);
            resourceItem.innerHTML = `
                <div class="resource-info">
                    <h4>${resourceName}</h4>
                    <div class="resource-meta">
                        <span><i class="fas fa-tag"></i> ${resourceType}</span>
                        <span><i class="fas fa-balance-scale"></i> ${resourceUnit}</span>
                    </div>
                </div>
                
                <div class="resource-allocations">
                    <div class="allocation-item">
                        <span class="allocation-label">Allocated:</span>
                        <span class="allocation-value allocated-value">1</span>
                    </div>
                    <div class="allocation-item">
                        <span class="allocation-label">Distributed:</span>
                        <span class="allocation-value distributed-value">0</span>
                    </div>
                </div>
                
                <div class="resource-quantity">
                    <input type="number" 
                           class="quantity-input" 
                           value="1" 
                           min="1" 
                           max="1000"
                           onchange="updateResourceQuantity(${resourceId}, this.value)"
                           style="background: white;"
                           title="Adjust allocation quantity">
                </div>
                
                <button type="button" 
                        class="remove-resource" 
                        onclick="removeResource(${resourceId})"
                        title="Remove resource from distribution">
                    <i class="fas fa-times"></i>
                </button>
            `;
            
            resourceList.appendChild(resourceItem);
            
            // Add animation
            resourceItem.style.opacity = '0';
            resourceItem.style.transform = 'translateY(20px)';
            setTimeout(() => {
                resourceItem.style.transition = 'all 0.3s cubic-bezier(0.4, 0, 0.2, 1)';
                resourceItem.style.opacity = '1';
                resourceItem.style.transform = 'translateY(0)';
            }, 10);
            
            // Remove from available resources
            const resourceCard = document.querySelector(`.resource-card:has(button[data-resource-id="${resourceId}"])`);
            if (resourceCard) {
                resourceCard.style.opacity = '0.5';
                resourceCard.style.transform = 'scale(0.95)';
                setTimeout(() => {
                    resourceCard.remove();
                    
                    // Check if no more resources in category
                    const categorySection = resourceCard.closest('.resource-section');
                    if (categorySection) {
                        const remainingCards = categorySection.querySelectorAll('.resource-card');
                        if (remainingCards.length === 0) {
                            categorySection.remove();
                        }
                    }
                    
                    // Check if no more resources available
                    const availableGrid = document.getElementById('availableResources');
                    const remainingCategories = availableGrid.querySelectorAll('.resource-section');
                    if (remainingCategories.length === 0) {
                        availableGrid.innerHTML = `
                            <div class="empty-state">
                                <i class="fas fa-check-circle"></i>
                                <h4>All Resources Allocated</h4>
                                <p>All available resources have been allocated to this distribution.</p>
                            </div>
                        `;
                    }
                }, 300);
            }
            
            // Update hidden input
            updateResourcesInput();
            
            // Update summary stats
            updateSummaryStats();
            
            // Show success notification
            showNotification(`Added "${resourceName}" to distribution`, 'success');
        }
        
        function updateResourceQuantity(resourceId, quantity) {
            const resource = allocatedResources.find(r => r.id === resourceId);
            if (resource) {
                const newQuantity = parseInt(quantity);
                if (newQuantity > 0 && newQuantity <= 1000) {
                    resource.quantity = newQuantity;
                    
                    // Update UI
                    const allocationSpan = document.querySelector(`.resource-item[data-resource-id="${resourceId}"] .allocated-value`);
                    if (allocationSpan) {
                        allocationSpan.textContent = newQuantity;
                        
                        // Add visual feedback
                        allocationSpan.style.transform = 'scale(1.2)';
                        setTimeout(() => {
                            allocationSpan.style.transform = 'scale(1)';
                        }, 300);
                    }
                    
                    updateResourcesInput();
                    updateSummaryStats();
                    
                    // Add visual feedback to input
                    const input = document.querySelector(`.resource-item[data-resource-id="${resourceId}"] .quantity-input`);
                    input.style.borderColor = 'var(--primary)';
                    input.style.boxShadow = '0 0 0 2px rgba(67, 97, 238, 0.2)';
                    setTimeout(() => {
                        input.style.borderColor = '';
                        input.style.boxShadow = '';
                    }, 1000);
                } else {
                    // Reset to previous value if invalid
                    input.value = resource.quantity;
                    showNotification('Please enter a quantity between 1 and 1000', 'warning');
                }
            }
        }
        
        function removeResource(resourceId) {
            if (!confirm('Are you sure you want to remove this resource from the distribution?')) {
                return;
            }
            
            // Remove from array
            allocatedResources = allocatedResources.filter(r => r.id !== resourceId);
            
            // Remove from UI with animation
            const resourceItem = document.querySelector(`.resource-item[data-resource-id="${resourceId}"]`);
            if (resourceItem) {
                resourceItem.style.opacity = '0';
                resourceItem.style.transform = 'translateX(100px) scale(0.8)';
                setTimeout(() => {
                    resourceItem.remove();
                    
                    // Show empty state if no resources left
                    const resourceList = document.getElementById('resourceList');
                    if (resourceList.children.length === 0) {
                        resourceList.innerHTML = `
                            <div class="empty-state">
                                <i class="fas fa-box-open"></i>
                                <h4>No Resources Allocated</h4>
                                <p>Add resources from the available list below to get started.</p>
                            </div>
                        `;
                    }
                }, 300);
            }
            
            updateResourcesInput();
            updateSummaryStats();
            
            // Show notification
            showNotification('Resource removed from distribution', 'info');
        }
        
        function updateResourcesInput() {
            document.getElementById('resourcesInput').value = JSON.stringify(allocatedResources);
        }
        
        function updateSummaryStats() {
            // Calculate new totals
            const totalResources = allocatedResources.length;
            const totalUnits = allocatedResources.reduce((sum, resource) => sum + resource.quantity, 0);
            
            // Get unique types
            const resourceTypes = new Set();
            allocatedResources.forEach(resource => {
                // We would need to track types in allocatedResources array
                // For now, we'll just count the number of resources
            });
            
            // Update UI if elements exist
            const resourceCountElement = document.querySelector('.stat-item:nth-child(1) .stat-value');
            const unitCountElement = document.querySelector('.stat-item:nth-child(3) .stat-value');
            
            if (resourceCountElement) {
                resourceCountElement.textContent = totalResources;
                resourceCountElement.style.transform = 'scale(1.2)';
                setTimeout(() => {
                    resourceCountElement.style.transform = 'scale(1)';
                }, 300);
            }
            
            if (unitCountElement) {
                unitCountElement.textContent = totalUnits;
                unitCountElement.style.transform = 'scale(1.2)';
                setTimeout(() => {
                    unitCountElement.style.transform = 'scale(1)';
                }, 300);
            }
        }
        
        // Form submission
        function handleFormSubmit(e) {
            e.preventDefault();
            
            // Show loading overlay
            showLoading('Saving your changes...');
            
            // Validate form
            if (!validateForm()) {
                hideLoading();
                return;
            }
            
            // Submit form
            setTimeout(() => {
                this.submit();
            }, 1000); // Small delay for better UX
        }
        
        function validateForm() {
            const distributionDate = document.getElementById('distribution_date').value;
            
            if (!distributionDate) {
                showNotification('Please select a distribution date and time.', 'error');
                document.getElementById('distribution_date').focus();
                return false;
            }
            
            // Validate future date
            const selectedDate = new Date(distributionDate);
            const now = new Date();
            if (selectedDate < now) {
                if (!confirm('The selected date is in the past. Are you sure you want to continue?')) {
                    document.getElementById('distribution_date').focus();
                    return false;
                }
            }
            
            if (allocatedResources.length === 0) {
                const confirm = window.confirm('No resources allocated. Are you sure you want to create a distribution without resources?');
                if (!confirm) {
                    return false;
                }
            }
            
            return true;
        }
        
        // Loading functions
        function showLoading(message = 'Processing...') {
            const overlay = document.getElementById('loadingOverlay');
            overlay.querySelector('h3').textContent = message;
            overlay.classList.add('active');
        }
        
        function hideLoading() {
            document.getElementById('loadingOverlay').classList.remove('active');
        }
        
        // Notification system
        function showNotification(message, type = 'info') {
            // Create notification element
            const notification = document.createElement('div');
            notification.className = `alert alert-${type}`;
            notification.style.position = 'fixed';
            notification.style.top = '20px';
            notification.style.right = '20px';
            notification.style.zIndex = '10000';
            notification.style.width = '350px';
            notification.style.margin = '0';
            notification.style.boxShadow = '0 10px 30px rgba(0,0,0,0.1)';
            notification.innerHTML = `
                <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
                <div>
                    <h4 style="margin-bottom: 5px; font-size: 1rem;">${type.charAt(0).toUpperCase() + type.slice(1)}</h4>
                    <p style="font-size: 0.9rem;">${message}</p>
                </div>
            `;
            
            // Add to page
            document.body.appendChild(notification);
            
            // Animate in
            setTimeout(() => {
                notification.style.transform = 'translateX(0)';
                notification.style.opacity = '1';
            }, 10);
            
            // Remove after delay
            setTimeout(() => {
                notification.style.transform = 'translateX(400px)';
                notification.style.opacity = '0';
                setTimeout(() => {
                    notification.remove();
                }, 300);
            }, 3000);
        }
        
        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl/Cmd + S to save
            if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                e.preventDefault();
                document.querySelector('button[type="submit"]').click();
            }
            
            // Escape to cancel
            if (e.key === 'Escape') {
                if (confirm('Discard all changes?')) {
                    window.history.back();
                }
            }
        });
        
        // Form change detection
        let originalFormData = new FormData(document.getElementById('editDistributionForm'));
        document.getElementById('editDistributionForm').addEventListener('input', function() {
            const currentFormData = new FormData(this);
            const hasChanges = JSON.stringify([...originalFormData]) !== JSON.stringify([...currentFormData]);
            
            if (hasChanges) {
                document.title = document.title.replace(/^(\* )?/, '* ');
            } else {
                document.title = document.title.replace(/^\* /, '');
            }
        });
        
        // Page leave confirmation
        window.addEventListener('beforeunload', function(e) {
            if (document.title.startsWith('*')) {
                e.preventDefault();
                e.returnValue = 'You have unsaved changes. Are you sure you want to leave?';
            }
        });
    </script>
</body>
</html>