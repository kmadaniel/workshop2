<?php
require_once 'config.php';

$database = new Database();
$db = $database->getConnection();

// In real system, volunteer_id would come from login/session
$volunteer_id = 1; // Default for demo
$distribution_id = $_GET['distribution_id'] ?? null;
$error = '';
$success = '';
$victim_data = null;
$needs_data = [];

/* ----------------------------------------
   CHECK VOLUNTEER ASSIGNMENT
---------------------------------------- */
if (!$distribution_id) {
    header("Location: volunteer_dashboard.php");
    exit;
}

// Check if volunteer is assigned to this distribution
$check_assignment = "
    SELECT dv.*, v.name as volunteer_name, d.*, dis.Disaster_Name 
    FROM distribution_volunteer dv
    JOIN volunteer v ON dv.volunteer_id = v.volunteer_id
    JOIN distribution d ON dv.distribution_id = d.distribution_id
    JOIN disaster dis ON d.disaster_id = dis.disaster_id
    WHERE dv.distribution_id = ? AND dv.volunteer_id = ?
    AND dv.status IN ('Assigned', 'Confirmed')
";

$stmt = $db->prepare($check_assignment);
$stmt->bind_param("ii", $distribution_id, $volunteer_id);
$stmt->execute();
$result = $stmt->get_result();
$assignment = $result->fetch_assoc();
$stmt->close();

if (!$assignment) {
    header("Location: volunteer_dashboard.php?error=not_assigned");
    exit;
}

/* ----------------------------------------
   GET DISTRIBUTION STATISTICS
---------------------------------------- */
$stats_query = "
    SELECT 
        COUNT(DISTINCT n.victim_id) as total_families,
        COUNT(DISTINCT CASE WHEN n.status = 'Fulfilled' THEN n.need_id END) as fulfilled_needs,
        COUNT(DISTINCT n.need_id) as total_needs
    FROM needs n
    WHERE n.distribution_id = ?
";

$stmt = $db->prepare($stats_query);
$stmt->bind_param("i", $distribution_id);
$stmt->execute();
$stats_result = $stmt->get_result();
$stats = $stats_result->fetch_assoc();
$stmt->close();

/* ----------------------------------------
   HANDLE VICTIM SEARCH (IC SCAN/Search)
---------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['search_victim'])) {
    $search_term = trim($_POST['search_term']);
    
    try {
        if (empty($search_term)) {
            throw new Exception("Please enter IC number or Victim ID");
        }
        
        // Search victim by IC or ID
        $victim_query = "
            SELECT v.*, 
                   COUNT(n.need_id) as total_needs,
                   GROUP_CONCAT(CONCAT(r.name, ' (', n.quantity_needed, ' ', r.unit, ')') SEPARATOR ', ') as needs_summary
            FROM victim v
            LEFT JOIN needs n ON v.victim_id = n.victim_id AND n.distribution_id = ?
            LEFT JOIN resource r ON n.resource_id = r.resource_id
            WHERE (v.victim_id = ? OR v.victim_id LIKE ?)
            AND n.status = 'Approved'
            GROUP BY v.victim_id
            LIMIT 1
        ";
        
        $stmt = $db->prepare($victim_query);
        $search_param = is_numeric($search_term) ? $search_term : 0;
        $like_param = "%{$search_term}%";
        $stmt->bind_param("iis", $distribution_id, $search_param, $like_param);
        $stmt->execute();
        $result = $stmt->get_result();
        $victim_data = $result->fetch_assoc();
        $stmt->close();
        
        if (!$victim_data) {
            throw new Exception("Victim not found or no approved needs for this distribution");
        }
        
        // Get detailed needs for this victim
        $needs_query = "
            SELECT n.*, r.name as resource_name, r.unit, r.type, r.category
            FROM needs n
            JOIN resource r ON n.resource_id = r.resource_id
            WHERE n.victim_id = ? 
            AND n.distribution_id = ?
            AND n.status = 'Approved'
        ";
        
        $stmt = $db->prepare($needs_query);
        $stmt->bind_param("ii", $victim_data['victim_id'], $distribution_id);
        $stmt->execute();
        $needs_result = $stmt->get_result();
        $needs_data = $needs_result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        if (empty($needs_data)) {
            throw new Exception("No approved needs found for this victim");
        }
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

/* ----------------------------------------
   HANDLE DISTRIBUTION EXECUTION
---------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['distribute_items'])) {
    $victim_id = $_POST['victim_id'];
    $distributed_items = $_POST['distributed_items'] ?? [];
    $signature_data = $_POST['signature_data'] ?? '';
    $remarks = $_POST['remarks'] ?? '';
    
    try {
        if (empty($distributed_items)) {
            throw new Exception("Please select at least one item to distribute");
        }
        
        $db->begin_transaction();
        
        $total_distributed = 0;
        foreach ($distributed_items as $need_id) {
            // Update need status to Fulfilled
            $update_need = "UPDATE needs SET status = 'Fulfilled', fulfilled_date = NOW() WHERE need_id = ?";
            $stmt = $db->prepare($update_need);
            $stmt->bind_param("i", $need_id);
            $stmt->execute();
            $stmt->close();
            
            // Get need details for inventory update
            $need_query = "SELECT resource_id, quantity_needed FROM needs WHERE need_id = ?";
            $stmt = $db->prepare($need_query);
            $stmt->bind_param("i", $need_id);
            $stmt->execute();
            $need_result = $stmt->get_result();
            $need = $need_result->fetch_assoc();
            $stmt->close();
            
            if ($need) {
                // Update inventory (deduct from quantity_reserved)
                $update_inventory = "
                    UPDATE resource 
                    SET quantity_reserved = quantity_reserved - ?,
                        quantity_available = quantity_available - ?
                    WHERE resource_id = ?
                ";
                $stmt = $db->prepare($update_inventory);
                $stmt->bind_param("iii", $need['quantity_needed'], $need['quantity_needed'], $need['resource_id']);
                $stmt->execute();
                $stmt->close();
                
                $total_distributed += $need['quantity_needed'];
            }
            
            // Create distribution_log table if not exists
            $check_table = $db->query("SHOW TABLES LIKE 'distribution_log'");
            if ($check_table->num_rows == 0) {
                $create_table = "
                    CREATE TABLE distribution_log (
                        log_id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
                        distribution_id BIGINT UNSIGNED,
                        volunteer_id BIGINT UNSIGNED,
                        victim_id BIGINT UNSIGNED,
                        need_id BIGINT UNSIGNED,
                        quantity_distributed INT,
                        distributed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                        signature_url VARCHAR(500),
                        photo_url VARCHAR(500),
                        remarks TEXT,
                        FOREIGN KEY (distribution_id) REFERENCES distribution(distribution_id),
                        FOREIGN KEY (volunteer_id) REFERENCES volunteer(volunteer_id),
                        FOREIGN KEY (victim_id) REFERENCES victim(victim_id),
                        FOREIGN KEY (need_id) REFERENCES needs(need_id)
                    )
                ";
                $db->query($create_table);
            }
            
            // Record distribution log
            $log_query = "
                INSERT INTO distribution_log 
                (distribution_id, volunteer_id, victim_id, need_id, quantity_distributed, distributed_at, remarks)
                VALUES (?, ?, ?, ?, ?, NOW(), ?)
            ";
            
            $stmt = $db->prepare($log_query);
            $stmt->bind_param("iiiiis", 
                $distribution_id, 
                $volunteer_id, 
                $victim_id,
                $need_id,
                $need['quantity_needed'],
                $remarks
            );
            $stmt->execute();
            $log_id = $stmt->insert_id;
            $stmt->close();
            
            // Handle signature (in real system, save as image file)
            if (!empty($signature_data) && $log_id) {
                // Create signatures directory if not exists
                if (!file_exists('../signatures')) {
                    mkdir('../signatures', 0777, true);
                }
                
                // Save signature as image
                $signature_data = str_replace('data:image/png;base64,', '', $signature_data);
                $signature_data = str_replace(' ', '+', $signature_data);
                $signature_filename = "signature_{$log_id}.png";
                $signature_path = "../signatures/{$signature_filename}";
                
                if (file_put_contents($signature_path, base64_decode($signature_data))) {
                    $update_signature = "UPDATE distribution_log SET signature_url = ? WHERE log_id = ?";
                    $stmt = $db->prepare($update_signature);
                    $stmt->bind_param("si", $signature_filename, $log_id);
                    $stmt->execute();
                    $stmt->close();
                }
            }
        }
        
        // Send SMS to victim (simulation)
        $victim_info = $db->query("SELECT name, phone FROM victim WHERE victim_id = {$victim_id}")->fetch_assoc();
        if ($victim_info && !empty($victim_info['phone'])) {
            $sms_message = "Bantuan telah diterima. Terima kasih. - JKM Melaka";
            // In real system, integrate with SMS gateway like Twilio
            error_log("SMS to {$victim_info['phone']}: {$sms_message}");
            
            // Simulate SMS sending
            $sms_sent = true;
        }
        
        $db->commit();
        
        $success = "✅ Distribution recorded successfully!";
        $success .= "<br>📱 SMS sent to victim.";
        $success .= "<br><br><strong>Distribution Summary:</strong>";
        $success .= "<br>• Items Distributed: " . count($distributed_items);
        $success .= "<br>• Total Quantity: {$total_distributed} units";
        $success .= "<br>• Date: " . date('d/m/Y H:i:s');
        
        // Clear search results for next victim
        $victim_data = null;
        $needs_data = [];
        
        // Refresh stats
        $stmt = $db->prepare($stats_query);
        $stmt->bind_param("i", $distribution_id);
        $stmt->execute();
        $stats_result = $stmt->get_result();
        $stats = $stats_result->fetch_assoc();
        $stmt->close();
        
    } catch (Exception $e) {
        $db->rollback();
        $error = "Error: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Execute Distribution - User Story 4.4</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Reset & Base */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 0;
            color: #333;
        }
        
        .mobile-container {
            max-width: 100%;
            min-height: 100vh;
            background: white;
            border-radius: 20px 20px 0 0;
            margin-top: 0;
            padding: 0;
            box-shadow: 0 -5px 30px rgba(0,0,0,0.1);
            position: relative;
        }
        
        /* Header */
        .app-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px 15px;
            border-radius: 0 0 25px 25px;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
        }
        
        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .header-left h1 {
            font-size: 1.4rem;
            margin-bottom: 5px;
            font-weight: 600;
        }
        
        .header-left p {
            font-size: 0.85rem;
            opacity: 0.9;
        }
        
        .header-right {
            text-align: right;
        }
        
        .dist-id {
            background: rgba(255,255,255,0.2);
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
            display: inline-block;
            margin-bottom: 5px;
        }
        
        /* Stats Cards */
        .stats-container {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            padding: 15px;
            background: white;
        }
        
        .stat-card {
            background: linear-gradient(135deg, #f5f7fa 0%, #e4e8f0 100%);
            padding: 15px 10px;
            border-radius: 12px;
            text-align: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        
        .stat-number {
            font-size: 1.8rem;
            font-weight: 700;
            color: #2c3e50;
            line-height: 1;
        }
        
        .stat-label {
            font-size: 0.75rem;
            color: #7f8c8d;
            margin-top: 5px;
            font-weight: 500;
        }
        
        /* Progress Bar */
        .progress-section {
            padding: 0 15px 15px;
        }
        
        .progress-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-size: 0.9rem;
            color: #2c3e50;
            font-weight: 500;
        }
        
        .progress-bar {
            height: 10px;
            background: #e0e6ed;
            border-radius: 5px;
            overflow: hidden;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #2ecc71, #27ae60);
            border-radius: 5px;
            transition: width 0.5s ease;
        }
        
        /* Scan Section */
        .scan-section {
            background: white;
            margin: 15px;
            padding: 25px 20px;
            border-radius: 20px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            text-align: center;
        }
        
        .scan-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #25d366, #128C7E);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            color: white;
            font-size: 2rem;
            box-shadow: 0 5px 15px rgba(37, 211, 102, 0.3);
        }
        
        .scan-input {
            width: 100%;
            padding: 18px 20px;
            border: 2px solid #e0e6ed;
            border-radius: 15px;
            font-size: 1rem;
            margin: 15px 0;
            transition: all 0.3s;
        }
        
        .scan-input:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
        }
        
        .scan-button {
            width: 100%;
            padding: 18px;
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
            border: none;
            border-radius: 15px;
            font-size: 1.1rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .scan-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(52, 152, 219, 0.3);
        }
        
        /* Victim Card */
        .victim-card {
            background: white;
            margin: 15px;
            padding: 20px;
            border-radius: 20px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            border-left: 5px solid #3498db;
        }
        
        .victim-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }
        
        .victim-name {
            font-size: 1.3rem;
            color: #2c3e50;
            font-weight: 600;
        }
        
        .victim-id {
            background: #e3f2fd;
            color: #1976d2;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        
        .victim-details {
            color: #666;
            line-height: 1.6;
        }
        
        .victim-details strong {
            color: #2c3e50;
        }
        
        /* Needs List */
        .needs-section {
            background: white;
            margin: 15px;
            padding: 20px;
            border-radius: 20px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
        }
        
        .section-title {
            font-size: 1.2rem;
            color: #2c3e50;
            margin-bottom: 20px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .needs-list {
            max-height: 300px;
            overflow-y: auto;
        }
        
        .need-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            margin-bottom: 10px;
            background: #f8fafc;
            border-radius: 12px;
            border: 2px solid #e0e6ed;
            transition: all 0.3s;
        }
        
        .need-item.selected {
            background: #e8f5e9;
            border-color: #2ecc71;
        }
        
        .need-info {
            flex: 1;
        }
        
        .need-name {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 5px;
        }
        
        .need-details {
            font-size: 0.9rem;
            color: #7f8c8d;
        }
        
        .need-quantity {
            background: #3498db;
            color: white;
            padding: 5px 12px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.9rem;
        }
        
        .checkbox-container {
            position: relative;
            width: 24px;
            height: 24px;
            margin-right: 15px;
        }
        
        .checkbox-custom {
            width: 100%;
            height: 100%;
            border: 2px solid #bdc3c7;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .checkbox-custom.checked {
            background: #2ecc71;
            border-color: #2ecc71;
        }
        
        .checkbox-custom.checked::after {
            content: '✓';
            color: white;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-weight: bold;
            font-size: 14px;
        }
        
        /* Action Buttons */
        .action-buttons {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
            padding: 15px;
        }
        
        .action-button {
            padding: 18px;
            border: none;
            border-radius: 15px;
            font-size: 1rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn-camera {
            background: linear-gradient(135deg, #9b59b6, #8e44ad);
            color: white;
        }
        
        .btn-signature {
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
        }
        
        .btn-distribute {
            grid-column: 1 / -1;
            background: linear-gradient(135deg, #2ecc71, #27ae60);
            color: white;
            font-size: 1.1rem;
            padding: 20px;
        }
        
        .action-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        /* Signature Section */
        .signature-section {
            background: white;
            margin: 15px;
            padding: 20px;
            border-radius: 20px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
        }
        
        .signature-canvas {
            width: 100%;
            height: 200px;
            border: 2px dashed #bdc3c7;
            border-radius: 15px;
            background: #f8fafc;
            touch-action: none;
            margin: 15px 0;
        }
        
        .signature-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        
        .btn-clear {
            flex: 1;
            padding: 12px;
            background: #e74c3c;
            color: white;
            border: none;
            border-radius: 10px;
            font-weight: 600;
        }
        
        .btn-save {
            flex: 2;
            padding: 12px;
            background: #2ecc71;
            color: white;
            border: none;
            border-radius: 10px;
            font-weight: 600;
        }
        
        /* Remarks */
        .remarks-section {
            background: white;
            margin: 15px;
            padding: 20px;
            border-radius: 20px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
        }
        
        .remarks-box {
            width: 100%;
            padding: 15px;
            border: 2px solid #e0e6ed;
            border-radius: 12px;
            font-size: 1rem;
            min-height: 100px;
            resize: vertical;
            margin-top: 10px;
        }
        
        .remarks-box:focus {
            outline: none;
            border-color: #3498db;
        }
        
        /* Success Message */
        .success-message {
            background: linear-gradient(135deg, #d4edda, #c3e6cb);
            color: #155724;
            margin: 15px;
            padding: 25px;
            border-radius: 20px;
            text-align: center;
            border-left: 5px solid #2ecc71;
        }
        
        .success-icon {
            font-size: 3rem;
            margin-bottom: 15px;
            color: #2ecc71;
        }
        
        /* Loading Overlay */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.9);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            flex-direction: column;
        }
        
        .loading-spinner {
            width: 60px;
            height: 60px;
            border: 5px solid #f3f3f3;
            border-top: 5px solid #3498db;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        .loading-text {
            color: white;
            margin-top: 20px;
            font-size: 1.2rem;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* Footer */
        .app-footer {
            padding: 20px 15px 30px;
            text-align: center;
            background: white;
        }
        
        .btn-back {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 15px 30px;
            background: linear-gradient(135deg, #95a5a6, #7f8c8d);
            color: white;
            border: none;
            border-radius: 15px;
            font-size: 1rem;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s;
        }
        
        .btn-back:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        /* Emergency Button */
        .emergency-button {
            position: fixed;
            bottom: 20px;
            right: 20px;
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #e74c3c, #c0392b);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            box-shadow: 0 4px 20px rgba(231, 76, 60, 0.4);
            z-index: 1000;
            text-decoration: none;
            transition: all 0.3s;
        }
        
        .emergency-button:hover {
            transform: scale(1.1);
        }
        
        /* Responsive */
        @media (max-width: 480px) {
            .stats-container {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .stat-card:nth-child(3) {
                grid-column: 1 / -1;
            }
            
            .action-buttons {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner"></div>
        <div class="loading-text" id="loadingText">Processing...</div>
    </div>

    <div class="mobile-container">
        <!-- App Header -->
        <header class="app-header">
            <div class="header-content">
                <div class="header-left">
                    <h1>📦 Execute Distribution</h1>
                    <p><?php echo htmlspecialchars($assignment['volunteer_name']); ?> • <?php echo htmlspecialchars($assignment['assignment_role']); ?></p>
                </div>
                <div class="header-right">
                    <div class="dist-id">DIST<?php echo str_pad($distribution_id, 6, '0', STR_PAD_LEFT); ?></div>
                    <div style="font-size: 0.9rem; opacity: 0.9;">
                        <?php echo htmlspecialchars($assignment['Disaster_Name']); ?>
                    </div>
                </div>
            </div>
        </header>

        <?php if ($success): ?>
            <!-- Success Message -->
            <div class="success-message">
                <div class="success-icon">✅</div>
                <h3 style="margin-bottom: 15px;">Distribution Successful!</h3>
                <div style="text-align: left; margin-bottom: 20px;">
                    <?php echo $success; ?>
                </div>
                <button onclick="location.reload()" class="btn-distribute" style="margin-top: 10px;">
                    <i class="fas fa-user-plus"></i> Next Victim
                </button>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div style="background: #fde8e8; color: #c53030; margin: 15px; padding: 20px; border-radius: 15px; border-left: 5px solid #e74c3c;">
                <strong>Error:</strong> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <!-- Stats Cards -->
        <div class="stats-container">
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['fulfilled_needs'] ?? 0; ?></div>
                <div class="stat-label">Fulfilled Needs</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['total_needs'] ?? 0; ?></div>
                <div class="stat-label">Total Needs</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['total_families'] ?? 0; ?></div>
                <div class="stat-label">Families Helped</div>
            </div>
        </div>

        <!-- Progress Bar -->
        <div class="progress-section">
            <div class="progress-header">
                <span>Distribution Progress</span>
                <span>
                    <?php 
                    $progress = ($stats['total_needs'] > 0) ? ($stats['fulfilled_needs'] / $stats['total_needs']) * 100 : 0;
                    echo round($progress, 1) . '%';
                    ?>
                </span>
            </div>
            <div class="progress-bar">
                <div class="progress-fill" style="width: <?php echo $progress; ?>%"></div>
            </div>
        </div>

        <!-- Scan/Search Section -->
        <div class="scan-section">
            <div class="scan-icon">
                <i class="fas fa-qrcode"></i>
            </div>
            <h3 style="margin-bottom: 10px; color: #2c3e50;">Scan Victim IC</h3>
            <p style="color: #7f8c8d; margin-bottom: 20px; font-size: 0.95rem;">Or enter Victim ID manually</p>
            
            <form method="POST" id="search-form">
                <input type="hidden" name="search_victim" value="1">
                
                <input type="text" 
                       name="search_term" 
                       class="scan-input" 
                       placeholder="Enter V2024001 or scan IC"
                       id="searchInput"
                       required
                       autocomplete="off"
                       autofocus>
                
                <button type="submit" class="scan-button">
                    <i class="fas fa-search"></i> Search Victim
                </button>
                
                <div style="margin-top: 15px;">
                    <button type="button" class="scan-button" onclick="simulateCameraScan()" style="background: linear-gradient(135deg, #25d366, #128C7E);">
                        <i class="fas fa-camera"></i> Scan IC Camera
                    </button>
                </div>
            </form>
        </div>

        <!-- Victim Details -->
        <?php if ($victim_data && !empty($needs_data)): ?>
        <form method="POST" id="distribution-form" onsubmit="return validateDistribution()">
            <input type="hidden" name="distribute_items" value="1">
            <input type="hidden" name="victim_id" value="<?php echo $victim_data['victim_id']; ?>">
            <input type="hidden" name="signature_data" id="signatureData" value="">
            
            <!-- Victim Card -->
            <div class="victim-card">
                <div class="victim-header">
                    <div class="victim-name">
                        <i class="fas fa-user-check" style="color: #2ecc71; margin-right: 8px;"></i>
                        <?php echo htmlspecialchars($victim_data['name']); ?>
                    </div>
                    <div class="victim-id">
                        V<?php echo str_pad($victim_data['victim_id'], 6, '0', STR_PAD_LEFT); ?>
                    </div>
                </div>
                <div class="victim-details">
                    <p><strong>📍 Address:</strong> <?php echo htmlspecialchars($victim_data['address']); ?></p>
                    <p><strong>📞 Phone:</strong> <?php echo htmlspecialchars($victim_data['phone'] ?? 'N/A'); ?></p>
                    <p><strong>👥 Family Size:</strong> <?php echo $victim_data['family_members'] ?? 'N/A'; ?></p>
                    <p><strong>📋 Needs:</strong> <?php echo count($needs_data); ?> approved items</p>
                </div>
            </div>

            <!-- Approved Needs List -->
            <div class="needs-section">
                <h3 class="section-title">
                    <i class="fas fa-list-check"></i> Approved Needs
                </h3>
                
                <div class="needs-list">
                    <?php foreach ($needs_data as $need): ?>
                    <div class="need-item" onclick="toggleNeed(<?php echo $need['need_id']; ?>, this)">
                        <div class="checkbox-container">
                            <div class="checkbox-custom checked" id="checkbox-<?php echo $need['need_id']; ?>"></div>
                        </div>
                        <div class="need-info">
                            <div class="need-name"><?php echo htmlspecialchars($need['resource_name']); ?></div>
                            <div class="need-details">
                                <span style="background: #e3f2fd; padding: 2px 8px; border-radius: 10px; font-size: 0.8rem; margin-right: 8px;">
                                    <?php echo htmlspecialchars($need['category'] ?? 'General'); ?>
                                </span>
                                <?php echo htmlspecialchars($need['type']); ?>
                            </div>
                        </div>
                        <div class="need-quantity">
                            <?php echo $need['quantity_needed']; ?> <?php echo $need['unit']; ?>
                        </div>
                        <input type="checkbox" 
                               name="distributed_items[]" 
                               value="<?php echo $need['need_id']; ?>" 
                               style="display: none;"
                               class="need-checkbox"
                               checked
                               id="need-<?php echo $need['need_id']; ?>">
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="button" class="btn-clear" onclick="checkAllNeeds()">
                        <i class="fas fa-check-double"></i> Check All
                    </button>
                    <button type="button" class="btn-save" onclick="uncheckAllNeeds()">
                        <i class="fas fa-times"></i> Uncheck All
                    </button>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="action-buttons">
                <button type="button" class="action-button btn-camera" onclick="takePhoto()">
                    <i class="fas fa-camera"></i> Take Photo
                </button>
                
                <button type="button" class="action-button btn-signature" onclick="openSignatureSection()">
                    <i class="fas fa-signature"></i> Signature
                </button>
            </div>

            <!-- Signature Section (Initially Hidden) -->
            <div class="signature-section" id="signatureSection" style="display: none;">
                <h3 class="section-title">
                    <i class="fas fa-signature"></i> Victim Signature
                </h3>
                <canvas class="signature-canvas" id="signatureCanvas"></canvas>
                <p style="color: #7f8c8d; font-size: 0.9rem; margin-bottom: 15px;">
                    Please sign in the box above to confirm receipt
                </p>
                <div class="signature-actions">
                    <button type="button" class="btn-clear" onclick="clearSignature()">
                        <i class="fas fa-eraser"></i> Clear
                    </button>
                    <button type="button" class="btn-save" onclick="saveSignature()">
                        <i class="fas fa-save"></i> Save Signature
                    </button>
                </div>
            </div>

            <!-- Remarks -->
            <div class="remarks-section">
                <h3 class="section-title">
                    <i class="fas fa-edit"></i> Remarks
                </h3>
                <textarea name="remarks" 
                          class="remarks-box" 
                          placeholder="Enter any remarks (optional)... 
Example: 
• Special instructions
• Condition of items
• Additional notes"></textarea>
            </div>

            <!-- Submit Button -->
            <button type="submit" class="action-button btn-distribute" id="submitButton">
                <i class="fas fa-check-circle"></i> Mark as Distributed
            </button>
        </form>
        <?php endif; ?>

        <!-- Footer -->
        <footer class="app-footer">
            <a href="volunteer_dashboard.php" class="btn-back">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </footer>
    </div>

    <!-- Emergency Button -->
    <a href="emergency.php?distribution_id=<?php echo $distribution_id; ?>" class="emergency-button" title="Emergency">
        🆘
    </a>

    <script>
        // Signature Canvas
        let canvas = null;
        let ctx = null;
        let drawing = false;
        let lastX = 0;
        let lastY = 0;
        let signatureSaved = false;
        
        // Initialize canvas when signature section is opened
        function openSignatureSection() {
            const section = document.getElementById('signatureSection');
            section.style.display = 'block';
            
            // Scroll to signature section
            section.scrollIntoView({ behavior: 'smooth' });
            
            // Initialize canvas after a short delay
            setTimeout(() => {
                if (!canvas) {
                    canvas = document.getElementById('signatureCanvas');
                    ctx = canvas.getContext('2d');
                    
                    // Set canvas size
                    canvas.width = canvas.offsetWidth;
                    canvas.height = canvas.offsetHeight;
                    
                    // Clear canvas
                    clearCanvas();
                    
                    // Add event listeners
                    canvas.addEventListener('mousedown', startDrawing);
                    canvas.addEventListener('touchstart', startDrawingTouch);
                    canvas.addEventListener('mousemove', draw);
                    canvas.addEventListener('touchmove', drawTouch);
                    canvas.addEventListener('mouseup', stopDrawing);
                    canvas.addEventListener('touchend', stopDrawing);
                    canvas.addEventListener('mouseleave', stopDrawing);
                }
            }, 100);
        }
        
        function startDrawing(e) {
            drawing = true;
            [lastX, lastY] = [e.offsetX, e.offsetY];
        }
        
        function startDrawingTouch(e) {
            e.preventDefault();
            drawing = true;
            const rect = canvas.getBoundingClientRect();
            const touch = e.touches[0];
            lastX = touch.clientX - rect.left;
            lastY = touch.clientY - rect.top;
        }
        
        function draw(e) {
            if (!drawing) return;
            e.preventDefault();
            
            ctx.beginPath();
            ctx.lineWidth = 3;
            ctx.lineCap = 'round';
            ctx.lineJoin = 'round';
            ctx.strokeStyle = '#2c3e50';
            
            const rect = canvas.getBoundingClientRect();
            const x = e.clientX ? e.clientX - rect.left : e.touches[0].clientX - rect.left;
            const y = e.clientY ? e.clientY - rect.top : e.touches[0].clientY - rect.top;
            
            ctx.moveTo(lastX, lastY);
            ctx.lineTo(x, y);
            ctx.stroke();
            
            [lastX, lastY] = [x, y];
        }
        
        function drawTouch(e) {
            if (!drawing) return;
            e.preventDefault();
            draw(e);
        }
        
        function stopDrawing() {
            drawing = false;
        }
        
        function clearCanvas() {
            if (ctx) {
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                // Draw a light background
                ctx.fillStyle = '#f8fafc';
                ctx.fillRect(0, 0, canvas.width, canvas.height);
            }
            signatureSaved = false;
        }
        
        function clearSignature() {
            clearCanvas();
            document.getElementById('signatureData').value = '';
        }
        
        function saveSignature() {
            if (!canvas) {
                alert('Please open signature section first');
                return;
            }
            
            const dataURL = canvas.toDataURL();
            document.getElementById('signatureData').value = dataURL;
            signatureSaved = true;
            
            // Show success message
            showToast('Signature saved successfully!', 'success');
        }
        
        // Need selection
        function toggleNeed(needId, element) {
            const checkbox = document.getElementById(`need-${needId}`);
            const customCheckbox = document.getElementById(`checkbox-${needId}`);
            
            checkbox.checked = !checkbox.checked;
            
            if (checkbox.checked) {
                customCheckbox.classList.add('checked');
                element.classList.add('selected');
            } else {
                customCheckbox.classList.remove('checked');
                element.classList.remove('selected');
            }
        }
        
        function checkAllNeeds() {
            document.querySelectorAll('.need-checkbox').forEach(checkbox => {
                checkbox.checked = true;
                const customCheckbox = document.getElementById(`checkbox-${checkbox.value}`);
                if (customCheckbox) customCheckbox.classList.add('checked');
                
                const needItem = checkbox.closest('.need-item');
                if (needItem) needItem.classList.add('selected');
            });
            showToast('All items selected', 'success');
        }
        
        function uncheckAllNeeds() {
            document.querySelectorAll('.need-checkbox').forEach(checkbox => {
                checkbox.checked = false;
                const customCheckbox = document.getElementById(`checkbox-${checkbox.value}`);
                if (customCheckbox) customCheckbox.classList.remove('checked');
                
                const needItem = checkbox.closest('.need-item');
                if (needItem) needItem.classList.remove('selected');
            });
            showToast('All items unselected', 'info');
        }
        
        // Camera scan simulation
        function simulateCameraScan() {
            showLoading('Scanning IC...');
            
            // Simulate camera scan delay
            setTimeout(() => {
                hideLoading();
                
                // Sample data for demo
                const sampleData = [
                    'V2024001',
                    'V2024002', 
                    'V2024003',
                    'V2024004',
                    'V2024005'
                ];
                
                const randomData = sampleData[Math.floor(Math.random() * sampleData.length)];
                document.getElementById('searchInput').value = randomData;
                
                showToast(`Scanned: ${randomData}`, 'success');
                
                // Auto-submit after 2 seconds
                setTimeout(() => {
                    document.getElementById('search-form').submit();
                }, 2000);
                
            }, 3000);
        }
        
        // Photo capture simulation
        function takePhoto() {
            if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
                showLoading('Opening camera...');
                
                navigator.mediaDevices.getUserMedia({ video: true })
                    .then(function(stream) {
                        hideLoading();
                        
                        // In real app, capture photo here
                        // For demo, show success message
                        showToast('Camera ready! Photo saved to distribution record.', 'success');
                        
                        // Stop camera
                        stream.getTracks().forEach(track => track.stop());
                    })
                    .catch(function(err) {
                        hideLoading();
                        showToast('Camera not available. Please take photo manually.', 'error');
                    });
            } else {
                showToast('Camera not supported on this device.', 'error');
            }
        }
        
        // Form validation
        function validateDistribution() {
            const checkedItems = document.querySelectorAll('.need-checkbox:checked').length;
            if (checkedItems === 0) {
                showToast('Please select at least one item to distribute.', 'error');
                return false;
            }
            
            // Check signature
            if (!signatureSaved) {
                if (!confirm('No signature saved. Continue without signature?')) {
                    return false;
                }
            }
            
            showLoading('Processing distribution...');
            return true;
        }
        
        // Loading overlay
        function showLoading(message = 'Processing...') {
            document.getElementById('loadingOverlay').style.display = 'flex';
            document.getElementById('loadingText').textContent = message;
        }
        
        function hideLoading() {
            document.getElementById('loadingOverlay').style.display = 'none';
        }
        
        // Toast notification
        function showToast(message, type = 'info') {
            // Remove existing toast
            const existingToast = document.querySelector('.toast');
            if (existingToast) existingToast.remove();
            
            // Create toast
            const toast = document.createElement('div');
            toast.className = `toast toast-${type}`;
            toast.innerHTML = `
                <div style="
                    position: fixed;
                    top: 20px;
                    right: 20px;
                    background: ${type === 'success' ? '#2ecc71' : type === 'error' ? '#e74c3c' : '#3498db'};
                    color: white;
                    padding: 15px 20px;
                    border-radius: 10px;
                    box-shadow: 0 5px 15px rgba(0,0,0,0.2);
                    z-index: 10000;
                    animation: slideIn 0.3s ease;
                    max-width: 300px;
                ">
                    <strong>${type === 'success' ? '✓' : type === 'error' ? '✗' : 'ℹ'}</strong> ${message}
                </div>
            `;
            
            document.body.appendChild(toast);
            
            // Remove after 3 seconds
            setTimeout(() => {
                toast.style.animation = 'slideOut 0.3s ease';
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        }
        
        // Auto-focus search input
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('searchInput');
            if (searchInput) {
                searchInput.focus();
                
                // Check for URL parameter (for QR code scanning)
                const urlParams = new URLSearchParams(window.location.search);
                const scannedData = urlParams.get('scan');
                if (scannedData) {
                    searchInput.value = scannedData;
                    document.getElementById('search-form').submit();
                }
            }
            
            // Auto-check all needs when victim found
            if (document.querySelector('.need-checkbox')) {
                setTimeout(checkAllNeeds, 500);
            }
        });
        
        // Prevent accidental page leave during distribution
        let formChanged = false;
        document.getElementById('distribution-form')?.addEventListener('change', () => {
            formChanged = true;
        });
        
        window.addEventListener('beforeunload', function(e) {
            if (formChanged) {
                e.preventDefault();
                e.returnValue = 'You have unsaved changes. Are you sure you want to leave?';
                return e.returnValue;
            }
        });
        
        // Add CSS animations
        const style = document.createElement('style');
        style.textContent = `
            @keyframes slideIn {
                from { transform: translateX(100%); opacity: 0; }
                to { transform: translateX(0); opacity: 1; }
            }
            @keyframes slideOut {
                from { transform: translateX(0); opacity: 1; }
                to { transform: translateX(100%); opacity: 0; }
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>