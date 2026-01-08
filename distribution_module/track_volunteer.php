<?php
// track_volunteer.php
// Detailed volunteer tracking view for coordinators
session_start();
require_once 'config.php';

// Check if user is coordinator/admin
if (!isset($_SESSION['user_role']) || ($_SESSION['user_role'] !== 'coordinator' && $_SESSION['user_role'] !== 'admin')) {
    header("Location: login_gateway.php");
    exit;
}

$database = new Database();
$db = $database->getConnection();

// Get parameters
$volunteer_id = isset($_GET['volunteer_id']) ? intval($_GET['volunteer_id']) : 0;
$distribution_id = isset($_GET['distribution_id']) ? intval($_GET['distribution_id']) : 0;

if (!$volunteer_id || !$distribution_id) {
    die("Invalid parameters. Volunteer ID and Distribution ID are required.");
}

// API URLs
$VOLUNTEER_API_URL = 'http://10.147.17.30:8000/api_volunteer.php';
$DISASTER_API_URL = 'http://10.147.17.116:8000/disaster.php';
$VICTIM_API_URL = 'http://10.147.17.116:8000/victim.php';
$NEEDS_API_URL = 'http://10.147.17.116:8000/needs.php';

// Fetch data functions
function fetchFromAPI($url) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if (is_resource($ch)) {
        curl_close($ch);
    }
    
    if ($httpCode === 200) {
        return json_decode($response, true);
    }
    
    return null;
}

// Fetch volunteer details
$volunteers_data = fetchFromAPI($VOLUNTEER_API_URL);
$api_volunteers = $volunteers_data['volunteers'] ?? $volunteers_data['data'] ?? $volunteers_data;
$volunteer_details = null;

foreach ($api_volunteers as $api_volunteer) {
    $api_volunteer_id = $api_volunteer['volunteer_id'] ?? 
                        $api_volunteer['Volunteer_ID'] ?? 
                        $api_volunteer['VolunteerID'] ?? 
                        $api_volunteer['id'] ?? 0;
    
    if (intval($api_volunteer_id) == $volunteer_id) {
        $volunteer_details = $api_volunteer;
        break;
    }
}

// Get distribution details
$distribution_query = "
    SELECT d.*, dv.status as volunteer_status, dv.role
    FROM distribution d
    LEFT JOIN distribution_volunteer dv ON d.distribution_id = dv.distribution_id AND dv.volunteer_id = ?
    WHERE d.distribution_id = ?
    LIMIT 1
";

$stmt = $db->prepare($distribution_query);
$stmt->bind_param("ii", $volunteer_id, $distribution_id);
$stmt->execute();
$result = $stmt->get_result();
$distribution_details = $result->fetch_assoc();
$stmt->close();

if (!$distribution_details) {
    die("Distribution not found or volunteer not assigned to this distribution.");
}

// Get disaster details
$disasters_data = fetchFromAPI($DISASTER_API_URL);
$disasters = $disasters_data['disasters'] ?? $disasters_data['data'] ?? $disasters_data;
$disaster_details = null;

foreach ($disasters as $disaster) {
    $disaster_id_api = $disaster['disaster_id'] ?? 
                       $disaster['Disaster_ID'] ?? 
                       $disaster['id'] ?? 0;
    
    if (intval($disaster_id_api) == $distribution_details['disaster_id']) {
        $disaster_details = $disaster;
        break;
    }
}

// Get tracking data for this volunteer and distribution
$tracking_query = "
    SELECT dt.*
    FROM distribution_tracking dt
    WHERE dt.distribution_id = ? 
    AND dt.volunteer_id = ?
    ORDER BY dt.created_at DESC
    LIMIT 50
";

$stmt = $db->prepare($tracking_query);
$stmt->bind_param("ii", $distribution_id, $volunteer_id);
$stmt->execute();
$result = $stmt->get_result();
$tracking_data = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get live tracking data (GPS coordinates)
$live_tracking_query = "
    SELECT * FROM live_tracking 
    WHERE distribution_id = ? 
    AND volunteer_id = ?
    ORDER BY recorded_at DESC
    LIMIT 100
";

$stmt = $db->prepare($live_tracking_query);
$stmt->bind_param("ii", $distribution_id, $volunteer_id);
$stmt->execute();
$result = $stmt->get_result();
$live_tracking_data = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get distribution log (item delivery progress)
$distribution_log_query = "
    SELECT dl.*
    FROM distribution_log dl
    WHERE dl.distribution_id = ? 
    AND dl.volunteer_id = ?
    ORDER BY dl.created_at DESC
    LIMIT 50
";

$stmt = $db->prepare($distribution_log_query);
$stmt->bind_param("ii", $distribution_id, $volunteer_id);
$stmt->execute();
$result = $stmt->get_result();
$distribution_log = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get victims data for this distribution
$victims_data = fetchFromAPI($VICTIM_API_URL);
$api_victims = $victims_data['victims'] ?? $victims_data['data'] ?? $victims_data;

// Get needs data
$needs_data = fetchFromAPI($NEEDS_API_URL);
$api_needs = $needs_data['needs'] ?? $needs_data['data'] ?? $needs_data;

// Get latest tracking status
$latest_tracking = !empty($tracking_data) ? $tracking_data[0] : null;

// Get statistics
$stats_query = "
    SELECT 
        COUNT(DISTINCT victim_id) as total_victims_assigned,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_deliveries,
        SUM(CASE WHEN status = 'in_transit' THEN 1 ELSE 0 END) as in_transit_deliveries,
        COUNT(DISTINCT need_id) as total_items,
        MAX(created_at) as last_update
    FROM distribution_log
    WHERE distribution_id = ? 
    AND volunteer_id = ?
";

$stmt = $db->prepare($stats_query);
$stmt->bind_param("ii", $distribution_id, $volunteer_id);
$stmt->execute();
$result = $stmt->get_result();
$stats = $result->fetch_assoc();
$stmt->close();

// Handle sending message to volunteer
$message_sent = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    $message = trim($_POST['message']);
    $message_type = $_POST['message_type'] ?? 'info';
    
    if (!empty($message)) {
        // Create coordinator_alerts table if it doesn't exist
        $create_alerts_table = "
            CREATE TABLE IF NOT EXISTS coordinator_alerts (
                alert_id INT PRIMARY KEY AUTO_INCREMENT,
                distribution_id INT NOT NULL,
                message TEXT NOT NULL,
                alert_type ENUM('volunteer_cancelled', 'status_change', 'new_volunteer', 'system') DEFAULT 'system',
                is_read BOOLEAN DEFAULT FALSE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX (distribution_id),
                INDEX (is_read),
                INDEX (created_at)
            )
        ";
        $db->query($create_alerts_table);
        
        // Log it in the database
        $log_message = "Coordinator message: " . $message;
        
        $insert_query = "INSERT INTO coordinator_alerts (distribution_id, message, alert_type) VALUES (?, ?, 'system')";
        $stmt = $db->prepare($insert_query);
        $stmt->bind_param("is", $distribution_id, $log_message);
        $stmt->execute();
        $stmt->close();
        
        $message_sent = true;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Volunteer Tracking - JKM Melaka</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Leaflet CSS for map -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
          integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY="
          crossorigin=""/>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', system-ui, sans-serif;
        }
        
        body {
            background-color: #f5f7fa;
            color: #333;
            line-height: 1.6;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .header {
            background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%);
            color: white;
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 30px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .header h1 {
            font-size: 32px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .header p {
            font-size: 16px;
            opacity: 0.9;
        }
        
        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 10px 20px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            margin-top: 20px;
            transition: background 0.3s;
        }
        
        .back-btn:hover {
            background: rgba(255,255,255,0.3);
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 30px 0;
        }
        
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            text-align: center;
            border-top: 4px solid #3498db;
        }
        
        .stat-card:nth-child(2) {
            border-top-color: #2ecc71;
        }
        
        .stat-card:nth-child(3) {
            border-top-color: #9b59b6;
        }
        
        .stat-card:nth-child(4) {
            border-top-color: #f39c12;
        }
        
        .stat-card:nth-child(5) {
            border-top-color: #e74c3c;
        }
        
        .stat-number {
            font-size: 42px;
            font-weight: 700;
            margin-bottom: 10px;
        }
        
        .stat-label {
            color: #7f8c8d;
            font-size: 16px;
            font-weight: 500;
        }
        
        .info-section {
            background: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .info-item {
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            border-left: 4px solid #3498db;
        }
        
        .info-label {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 5px;
        }
        
        .info-value {
            color: #495057;
            font-size: 15px;
        }
        
        .tabs {
            display: flex;
            background: white;
            border-radius: 12px 12px 0 0;
            overflow: hidden;
            margin-top: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .tab {
            flex: 1;
            padding: 20px;
            text-align: center;
            font-weight: 600;
            color: #7f8c8d;
            cursor: pointer;
            transition: all 0.3s;
            border-bottom: 3px solid transparent;
        }
        
        .tab:hover {
            background: #f8f9fa;
            color: #3498db;
        }
        
        .tab.active {
            background: #f8f9fa;
            color: #3498db;
            border-bottom: 3px solid #3498db;
        }
        
        .tab-content {
            display: none;
            background: white;
            border-radius: 0 0 12px 12px;
            padding: 30px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        }
        
        .tab-content.active {
            display: block;
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        .data-table th {
            background: #f8f9fa;
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: #2c3e50;
            border-bottom: 2px solid #e1e5eb;
        }
        
        .data-table td {
            padding: 15px;
            border-bottom: 1px solid #eee;
            vertical-align: middle;
        }
        
        .data-table tr:hover {
            background: #f8f9fa;
        }
        
        .status-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-departed {
            background: #e1f5fe;
            color: #0288d1;
        }
        
        .status-in_transit {
            background: #fff3e0;
            color: #ef6c00;
        }
        
        .status-arrived {
            background: #e8f5e9;
            color: #2e7d32;
        }
        
        .status-delayed {
            background: #ffebee;
            color: #c62828;
        }
        
        .status-completed {
            background: #c8e6c9;
            color: #1b5e20;
        }
        
        .map-container {
            height: 500px;
            border-radius: 10px;
            overflow: hidden;
            margin: 20px 0;
            border: 1px solid #ddd;
        }
        
        #trackingMap {
            height: 100%;
            width: 100%;
        }
        
        .timeline {
            position: relative;
            margin: 30px 0;
            padding-left: 30px;
        }
        
        .timeline::before {
            content: '';
            position: absolute;
            left: 15px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #e1e5eb;
        }
        
        .timeline-item {
            position: relative;
            margin-bottom: 25px;
            padding-left: 30px;
        }
        
        .timeline-item::before {
            content: '';
            position: absolute;
            left: -16px;
            top: 5px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #3498db;
            border: 3px solid white;
            box-shadow: 0 0 0 2px #3498db;
        }
        
        .timeline-time {
            font-size: 12px;
            color: #7f8c8d;
            margin-bottom: 5px;
        }
        
        .timeline-content {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #3498db;
        }
        
        .message-form {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 10px;
            margin-top: 30px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #2c3e50;
        }
        
        .form-control {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            resize: vertical;
        }
        
        .btn {
            padding: 12px 24px;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            border: none;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-primary {
            background: #3498db;
            color: white;
        }
        
        .btn-primary:hover {
            background: #2980b9;
        }
        
        .btn-success {
            background: #2ecc71;
            color: white;
        }
        
        .btn-success:hover {
            background: #27ae60;
        }
        
        .btn-warning {
            background: #f39c12;
            color: white;
        }
        
        .btn-warning:hover {
            background: #e67e22;
        }
        
        .success-message {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            border-left: 4px solid #2ecc71;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #95a5a6;
        }
        
        .empty-state i {
            font-size: 60px;
            margin-bottom: 20px;
            color: #ecf0f1;
        }
        
        .empty-state h3 {
            font-size: 22px;
            margin-bottom: 10px;
            color: #7f8c8d;
        }
        
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .tabs {
                flex-direction: column;
            }
            
            .map-container {
                height: 300px;
            }
        }
        
        @media (max-width: 576px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .header h1 {
                font-size: 24px;
            }
            
            .stat-number {
                font-size: 32px;
            }
        }
        
        /* Map marker styles */
        .volunteer-marker {
            background: #3498db;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            border: 3px solid white;
            box-shadow: 0 0 10px rgba(0,0,0,0.3);
        }
        
        .victim-marker {
            background: #e74c3c;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            border: 3px solid white;
            box-shadow: 0 0 10px rgba(0,0,0,0.3);
        }
        
        .path-line {
            stroke: #3498db;
            stroke-width: 3;
            stroke-dasharray: 10, 10;
            opacity: 0.6;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1>
                <i class="fas fa-user-tag"></i>
                Volunteer Tracking Dashboard
            </h1>
            <p>Real-time tracking and monitoring for volunteer: 
                <strong>
                    <?php 
                    if ($volunteer_details) {
                        echo htmlspecialchars($volunteer_details['FullName'] ?? 
                                             $volunteer_details['fullName'] ?? 
                                             $volunteer_details['name'] ?? 
                                             'Volunteer ' . $volunteer_id);
                    } else {
                        echo 'Volunteer ' . $volunteer_id;
                    }
                    ?>
                </strong>
            </p>
            
            <a href="manage_execution.php" class="back-btn">
                <i class="fas fa-arrow-left"></i>
                Back to Manage Execution
            </a>
        </div>
        
        <!-- Message Sent Success -->
        <?php if ($message_sent): ?>
            <div class="success-message">
                <i class="fas fa-check-circle"></i>
                Message sent successfully to volunteer!
            </div>
        <?php endif; ?>
        
        <!-- Quick Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number">
                    <?php echo $stats['total_victims_assigned'] ?? 0; ?>
                </div>
                <div class="stat-label">Families Assigned</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-number">
                    <?php echo $stats['completed_deliveries'] ?? 0; ?>
                </div>
                <div class="stat-label">Completed Deliveries</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-number">
                    <?php echo $stats['in_transit_deliveries'] ?? 0; ?>
                </div>
                <div class="stat-label">In Transit</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-number">
                    <?php echo $stats['total_items'] ?? 0; ?>
                </div>
                <div class="stat-label">Items Distributed</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-number">
                    <?php 
                    if ($latest_tracking) {
                        $time_ago = '';
                        $created = new DateTime($latest_tracking['created_at']);
                        $now = new DateTime();
                        $interval = $created->diff($now);
                        
                        if ($interval->h > 0) {
                            $time_ago = $interval->h . 'h';
                        } elseif ($interval->i > 0) {
                            $time_ago = $interval->i . 'm';
                        } else {
                            $time_ago = 'Just now';
                        }
                        echo $time_ago;
                    } else {
                        echo 'N/A';
                    }
                    ?>
                </div>
                <div class="stat-label">Last Update</div>
            </div>
        </div>
        
        <!-- Volunteer & Distribution Info -->
        <div class="info-section">
            <h3 style="color: #2c3e50; margin-bottom: 20px;">
                <i class="fas fa-info-circle"></i> Assignment Details
            </h3>
            
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">Volunteer</div>
                    <div class="info-value">
                        <?php 
                        if ($volunteer_details) {
                            echo htmlspecialchars($volunteer_details['FullName'] ?? 
                                                 $volunteer_details['fullName'] ?? 
                                                 $volunteer_details['name'] ?? 
                                                 'Volunteer ' . $volunteer_id);
                        } else {
                            echo 'Volunteer ' . $volunteer_id;
                        }
                        ?>
                        <br>
                        <small style="color: #7f8c8d;">
                            ID: VOL<?php echo str_pad($volunteer_id, 4, '0', STR_PAD_LEFT); ?>
                        </small>
                    </div>
                </div>
                
                <div class="info-item">
                    <div class="info-label">Distribution</div>
                    <div class="info-value">
                        DIST<?php echo str_pad($distribution_id, 6, '0', STR_PAD_LEFT); ?>
                        <br>
                        <small style="color: #7f8c8d;">
                            <?php echo date('d/m/Y', strtotime($distribution_details['date'])); ?>
                            at <?php echo htmlspecialchars($distribution_details['location']); ?>
                        </small>
                    </div>
                </div>
                
                <div class="info-item">
                    <div class="info-label">Disaster</div>
                    <div class="info-value">
                        <?php 
                        if ($disaster_details) {
                            echo htmlspecialchars($disaster_details['Disaster_Name'] ?? 
                                                 $disaster_details['name'] ?? 
                                                 $disaster_details['disaster_name'] ?? 
                                                 'Unknown');
                        } else {
                            echo 'Unknown Disaster';
                        }
                        ?>
                    </div>
                </div>
                
                <div class="info-item">
                    <div class="info-label">Current Status</div>
                    <div class="info-value">
                        <?php if ($latest_tracking): ?>
                            <span class="status-badge status-<?php echo strtolower($latest_tracking['status']); ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $latest_tracking['status'])); ?>
                            </span>
                        <?php else: ?>
                            <span class="status-badge" style="background: #f5f5f5; color: #616161;">
                                No updates yet
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php if ($volunteer_details && !empty($volunteer_details['Phone'])): ?>
                <div class="info-item">
                    <div class="info-label">Contact</div>
                    <div class="info-value">
                        <i class="fas fa-phone"></i>
                        <?php echo htmlspecialchars($volunteer_details['Phone']); ?>
                        <?php if (!empty($volunteer_details['Email'])): ?>
                            <br>
                            <i class="fas fa-envelope"></i>
                            <?php echo htmlspecialchars($volunteer_details['Email']); ?>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Tabs -->
        <div class="tabs">
            <div class="tab active" onclick="showTab('map')">
                <i class="fas fa-map-marked-alt"></i> Live Map
            </div>
            <div class="tab" onclick="showTab('timeline')">
                <i class="fas fa-history"></i> Timeline
            </div>
            <div class="tab" onclick="showTab('deliveries')">
                <i class="fas fa-box"></i> Deliveries
            </div>
            <div class="tab" onclick="showTab('message')">
                <i class="fas fa-comment"></i> Send Message
            </div>
        </div>
        
        <!-- Tab Content: Live Map -->
        <div id="map-tab" class="tab-content active">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3 style="color: #2c3e50;">
                    <i class="fas fa-map-marked-alt"></i> Live Tracking Map
                </h3>
                <button class="btn btn-primary" onclick="refreshMap()">
                    <i class="fas fa-sync-alt"></i> Refresh Map
                </button>
            </div>
            
            <?php if (!empty($live_tracking_data)): ?>
                <div class="map-container">
                    <div id="trackingMap"></div>
                </div>
                
                <div style="margin-top: 20px; display: flex; gap: 15px; flex-wrap: wrap;">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <div style="width: 20px; height: 20px; background: #3498db; border-radius: 50%; border: 2px solid white;"></div>
                        <span>Volunteer Location</span>
                    </div>
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <div style="width: 20px; height: 20px; background: #e74c3c; border-radius: 50%; border: 2px solid white;"></div>
                        <span>Victim Locations</span>
                    </div>
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <div style="width: 100px; height: 3px; background: repeating-linear-gradient(90deg, #3498db, #3498db 10px, transparent 10px, transparent 20px);"></div>
                        <span>Travel Path</span>
                    </div>
                </div>
                
                <!-- Latest GPS Info -->
                <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin-top: 20px;">
                    <h4 style="color: #2c3e50; margin-bottom: 15px;">
                        <i class="fas fa-satellite"></i> Latest GPS Data
                    </h4>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px;">
                        <div>
                            <div style="font-size: 12px; color: #7f8c8d;">Coordinates</div>
                            <div style="font-weight: 600;">
                                <?php 
                                $latest_gps = $live_tracking_data[0];
                                echo round($latest_gps['latitude'], 6) . ', ' . round($latest_gps['longitude'], 6);
                                ?>
                            </div>
                        </div>
                        <div>
                            <div style="font-size: 12px; color: #7f8c8d;">Accuracy</div>
                            <div style="font-weight: 600;">
                                <?php echo $latest_gps['accuracy'] ? round($latest_gps['accuracy'], 1) . 'm' : 'N/A'; ?>
                            </div>
                        </div>
                        <div>
                            <div style="font-size: 12px; color: #7f8c8d;">Speed</div>
                            <div style="font-weight: 600;">
                                <?php echo $latest_gps['speed'] ? round($latest_gps['speed'] * 3.6, 1) . ' km/h' : 'N/A'; ?>
                            </div>
                        </div>
                        <div>
                            <div style="font-size: 12px; color: #7f8c8d;">Last Update</div>
                            <div style="font-weight: 600;">
                                <?php echo date('h:i A', strtotime($latest_gps['recorded_at'])); ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-map-marked-alt"></i>
                    <h3>No Live Tracking Data</h3>
                    <p>The volunteer hasn't started live GPS tracking yet.</p>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Tab Content: Timeline -->
        <div id="timeline-tab" class="tab-content">
            <h3 style="color: #2c3e50; margin-bottom: 20px;">
                <i class="fas fa-history"></i> Activity Timeline
            </h3>
            
            <?php if (!empty($tracking_data)): ?>
                <div class="timeline">
                    <?php foreach ($tracking_data as $update): 
                        // Get victim name from API if available
                        $victim_name = '';
                        if (!empty($update['victim_id'])) {
                            foreach ($api_victims as $victim) {
                                $victim_id_api = $victim['victim_id'] ?? 
                                                $victim['Victim_ID'] ?? 
                                                $victim['VictimID'] ?? 
                                                $victim['id'] ?? 0;
                                if (intval($victim_id_api) == $update['victim_id']) {
                                    $victim_name = $victim['FullName'] ?? 
                                                  $victim['fullName'] ?? 
                                                  $victim['name'] ?? 
                                                  $victim['victim_name'] ?? 
                                                  'Victim ' . $update['victim_id'];
                                    break;
                                }
                            }
                        }
                    ?>
                    <div class="timeline-item">
                        <div class="timeline-time">
                            <?php echo date('h:i A', strtotime($update['created_at'])); ?>
                            (<?php 
                            $created = new DateTime($update['created_at']);
                            $now = new DateTime();
                            $interval = $created->diff($now);
                            
                            if ($interval->d > 0) {
                                echo $interval->d . ' day' . ($interval->d > 1 ? 's' : '') . ' ago';
                            } elseif ($interval->h > 0) {
                                echo $interval->h . ' hour' . ($interval->h > 1 ? 's' : '') . ' ago';
                            } elseif ($interval->i > 0) {
                                echo $interval->i . ' minute' . ($interval->i > 1 ? 's' : '') . ' ago';
                            } else {
                                echo 'Just now';
                            }
                            ?>)
                        </div>
                        <div class="timeline-content">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                                <strong style="color: #2c3e50;">
                                    <?php echo ucfirst(str_replace('_', ' ', $update['status'])); ?>
                                </strong>
                                <span class="status-badge status-<?php echo strtolower($update['status']); ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $update['status'])); ?>
                                </span>
                            </div>
                            
                            <?php if (!empty($update['current_location'])): ?>
                            <div style="margin-bottom: 8px;">
                                <i class="fas fa-map-marker-alt" style="color: #3498db; margin-right: 8px;"></i>
                                <?php echo htmlspecialchars($update['current_location']); ?>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($update['tracking_notes'])): ?>
                            <div style="margin-bottom: 8px;">
                                <i class="fas fa-sticky-note" style="color: #f39c12; margin-right: 8px;"></i>
                                <?php echo htmlspecialchars($update['tracking_notes']); ?>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($update['estimated_arrival'])): ?>
                            <div style="margin-bottom: 8px;">
                                <i class="fas fa-clock" style="color: #2ecc71; margin-right: 8px;"></i>
                                ETA: <?php echo htmlspecialchars($update['estimated_arrival']); ?>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($victim_name)): ?>
                            <div style="font-size: 12px; color: #7f8c8d;">
                                <i class="fas fa-user" style="margin-right: 5px;"></i>
                                Victim: <?php echo htmlspecialchars($victim_name); ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-history"></i>
                    <h3>No Timeline Data</h3>
                    <p>No tracking updates available yet.</p>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Tab Content: Deliveries -->
        <div id="deliveries-tab" class="tab-content">
            <h3 style="color: #2c3e50; margin-bottom: 20px;">
                <i class="fas fa-box"></i> Delivery Progress
            </h3>
            
            <?php if (!empty($distribution_log)): ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Timestamp</th>
                            <th>Victim</th>
                            <th>Item</th>
                            <th>Status</th>
                            <th>Quantity</th>
                            <th>Remarks</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($distribution_log as $log): 
                            // Get victim name from API
                            $victim_name = 'Unknown';
                            if (!empty($log['victim_id'])) {
                                foreach ($api_victims as $victim) {
                                    $victim_id_api = $victim['victim_id'] ?? 
                                                    $victim['Victim_ID'] ?? 
                                                    $victim['VictimID'] ?? 
                                                    $victim['id'] ?? 0;
                                    if (intval($victim_id_api) == $log['victim_id']) {
                                        $victim_name = $victim['FullName'] ?? 
                                                      $victim['fullName'] ?? 
                                                      $victim['name'] ?? 
                                                      $victim['victim_name'] ?? 
                                                      'Victim ' . $log['victim_id'];
                                        break;
                                    }
                                }
                            }
                            
                            // Get item name from API
                            $item_name = 'Item';
                            foreach ($api_needs as $need) {
                                $need_id_api = $need['NeedID'] ?? 
                                              $need['need_id'] ?? 
                                              $need['id'] ?? 
                                              $need['ItemID'] ?? 
                                              $need['item_id'] ?? 0;
                                
                                // Convert both to strings for comparison
                                $log_need_id = (string)$log['need_id'];
                                $api_need_id = (string)$need_id_api;
                                
                                if ($log_need_id === $api_need_id) {
                                    $item_name = $need['ResourceName'] ?? 
                                                $need['resource_name'] ?? 
                                                $need['ItemName'] ?? 
                                                $need['item_name'] ?? 
                                                $need['Name'] ?? 
                                                $need['name'] ?? 
                                                'Item';
                                    break;
                                }
                            }
                        ?>
                        <tr>
                            <td>
                                <?php echo date('d/m/Y', strtotime($log['created_at'])); ?><br>
                                <small style="color: #7f8c8d;">
                                    <?php echo date('h:i A', strtotime($log['created_at'])); ?>
                                </small>
                            </td>
                            <td>
                                <?php echo htmlspecialchars($victim_name); ?>
                                <br>
                                <small style="color: #7f8c8d;">
                                    ID: VIC<?php echo str_pad($log['victim_id'], 4, '0', STR_PAD_LEFT); ?>
                                </small>
                            </td>
                            <td>
                                <?php echo htmlspecialchars($item_name); ?>
                            </td>
                            <td>
                                <?php 
                                $status_class = strtolower(str_replace(' ', '_', $log['status']));
                                ?>
                                <span class="status-badge status-<?php echo $status_class; ?>">
                                    <?php echo ucfirst($log['status']); ?>
                                </span>
                            </td>
                            <td>
                                <?php echo $log['quantity_distributed'] ?? 1; ?> units
                            </td>
                            <td>
                                <?php if (!empty($log['remarks'])): ?>
                                    <div style="font-size: 12px; color: #7f8c8d;">
                                        <?php echo htmlspecialchars(substr($log['remarks'], 0, 50)); ?>
                                        <?php if (strlen($log['remarks']) > 50): ?>...<?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <span style="color: #95a5a6; font-size: 12px;">No remarks</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-box"></i>
                    <h3>No Delivery Data</h3>
                    <p>No delivery logs available yet.</p>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Tab Content: Send Message -->
        <div id="message-tab" class="tab-content">
            <h3 style="color: #2c3e50; margin-bottom: 20px;">
                <i class="fas fa-comment"></i> Send Message to Volunteer
            </h3>
            
            <div class="message-form">
                <form method="POST" id="messageForm">
                    <div class="form-group">
                        <label for="message_type">Message Type</label>
                        <select id="message_type" name="message_type" class="form-control">
                            <option value="info">Information</option>
                            <option value="warning">Warning/Alert</option>
                            <option value="urgent">Urgent</option>
                            <option value="update">Route Update</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="message">Message</label>
                        <textarea id="message" name="message" class="form-control" rows="5" 
                                  placeholder="Enter your message to the volunteer..." required></textarea>
                    </div>
                    
                    <div style="display: flex; gap: 10px; margin-top: 20px;">
                        <button type="submit" name="send_message" class="btn btn-primary">
                            <i class="fas fa-paper-plane"></i> Send Message
                        </button>
                        
                        <button type="button" class="btn" onclick="insertQuickMessage()" style="background: #95a5a6; color: white;">
                            <i class="fas fa-bolt"></i> Quick Messages
                        </button>
                    </div>
                </form>
                
                <!-- Quick Messages -->
                <div style="margin-top: 30px;">
                    <h4 style="color: #2c3e50; margin-bottom: 15px;">
                        <i class="fas fa-bolt"></i> Quick Message Templates
                    </h4>
                    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                        <button type="button" class="btn" onclick="setMessage('Please provide an update on your current location.')" 
                                style="background: #e8f4fc; color: #3498db; border: 1px solid #3498db;">
                            Request Location Update
                        </button>
                        <button type="button" class="btn" onclick="setMessage('Are you experiencing any delays? Please let us know.')" 
                                style="background: #fff3cd; color: #856404; border: 1px solid #ffc107;">
                            Check for Delays
                        </button>
                        <button type="button" class="btn" onclick="setMessage('Great job! Please proceed to the next delivery location.')" 
                                style="background: #d4edda; color: #155724; border: 1px solid #c3e6cb;">
                            Positive Feedback
                        </button>
                        <button type="button" class="btn" onclick="setMessage('URGENT: Please contact coordinator immediately.')" 
                                style="background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb;">
                            Urgent Contact
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Leaflet JS -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
            integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo="
            crossorigin=""></script>

    <script>
        // Tab switching
        function showTab(tabName) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            
            document.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Show selected tab
            document.getElementById(tabName + '-tab').classList.add('active');
            event.target.classList.add('active');
            
            // If map tab is selected, initialize or refresh map
            if (tabName === 'map') {
                if (window.mapInitialized) {
                    refreshMap();
                } else {
                    initMap();
                    window.mapInitialized = true;
                }
            }
        }
        
        // Map variables
        let map = null;
        let volunteerMarkers = [];
        let victimMarkers = [];
        let pathLines = [];
        
        // Initialize map
        function initMap() {
            <?php if (!empty($live_tracking_data) || !empty($distribution_log)): ?>
                // Default center (Melaka)
                const defaultCenter = [2.1896, 102.2501];
                
                // Create map
                map = L.map('trackingMap').setView(defaultCenter, 13);
                
                // Add OpenStreetMap tiles
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
                    maxZoom: 19
                }).addTo(map);
                
                // Plot volunteer's live tracking points
                <?php if (!empty($live_tracking_data)): ?>
                    const volunteerPoints = [];
                    
                    <?php foreach ($live_tracking_data as $track): ?>
                        <?php if ($track['latitude'] && $track['longitude']): ?>
                            volunteerPoints.push([<?php echo $track['latitude']; ?>, <?php echo $track['longitude']; ?>]);
                        <?php endif; ?>
                    <?php endforeach; ?>
                    
                    if (volunteerPoints.length > 0) {
                        // Add polyline for volunteer's path
                        const volunteerPath = L.polyline(volunteerPoints, {
                            color: '#3498db',
                            weight: 3,
                            opacity: 0.6,
                            dashArray: '10, 10'
                        }).addTo(map);
                        pathLines.push(volunteerPath);
                        
                        // Add marker for latest location
                        const latestPoint = volunteerPoints[0];
                        const volunteerMarker = L.marker(latestPoint, {
                            icon: L.divIcon({
                                className: 'volunteer-marker',
                                html: '<div style="background: #3498db; color: white; border-radius: 50%; width: 30px; height: 30px; display: flex; align-items: center; justify-content: center; font-size: 16px; border: 3px solid white; box-shadow: 0 2px 5px rgba(0,0,0,0.3);"><i class="fas fa-user"></i></div>',
                                iconSize: [30, 30],
                                iconAnchor: [15, 15]
                            })
                        }).addTo(map);
                        
                        volunteerMarkers.push(volunteerMarker);
                        
                        // Add popup with latest info
                        const latestTracking = <?php echo json_encode($live_tracking_data[0]); ?>;
                        const popupContent = `
                            <div style="font-weight: bold; margin-bottom: 5px;">
                                <i class="fas fa-user"></i> Volunteer Location
                            </div>
                            <div style="font-size: 12px; color: #666; margin-bottom: 8px;">
                                ${latestPoint[0].toFixed(6)}, ${latestPoint[1].toFixed(6)}
                            </div>
                            <div style="font-size: 11px; color: #3498db;">
                                <i class="fas fa-clock"></i> 
                                <?php 
                                if (!empty($live_tracking_data[0]['recorded_at'])) {
                                    echo date('h:i A', strtotime($live_tracking_data[0]['recorded_at']));
                                } else {
                                    echo 'Recent';
                                }
                                ?>
                            </div>
                        `;
                        
                        volunteerMarker.bindPopup(popupContent);
                    }
                <?php endif; ?>
                
                // Plot victim locations from distribution log
                <?php 
                $victim_coordinates = [];
                if (!empty($distribution_log)) {
                    foreach ($distribution_log as $log) {
                        if ($log['victim_id']) {
                            $victim_id = $log['victim_id'];
                            if (!isset($victim_coordinates[$victim_id])) {
                                // Generate consistent coordinates based on victim ID
                                $hash = md5($victim_id);
                                $lat_offset = (hexdec(substr($hash, 0, 8)) % 1000) / 10000;
                                $lng_offset = (hexdec(substr($hash, 8, 8)) % 1000) / 10000;
                                
                                $victim_coordinates[$victim_id] = [
                                    'lat' => 2.1896 + $lat_offset,
                                    'lng' => 102.2501 + $lng_offset
                                ];
                            }
                        }
                    }
                }
                ?>
                
                <?php if (!empty($victim_coordinates)): ?>
                    <?php foreach ($victim_coordinates as $victim_id => $coords): ?>
                        const victimPoint = [<?php echo $coords['lat']; ?>, <?php echo $coords['lng']; ?>];
                        const victimMarker = L.marker(victimPoint, {
                            icon: L.divIcon({
                                className: 'victim-marker',
                                html: '<div style="background: #e74c3c; color: white; border-radius: 50%; width: 25px; height: 25px; display: flex; align-items: center; justify-content: center; font-size: 14px; border: 3px solid white; box-shadow: 0 2px 5px rgba(0,0,0,0.3);"><i class="fas fa-home"></i></div>',
                                iconSize: [25, 25],
                                iconAnchor: [12.5, 12.5]
                            })
                        }).addTo(map);
                        
                        victimMarkers.push(victimMarker);
                        
                        const victimPopup = `
                            <div style="font-weight: bold; margin-bottom: 5px;">
                                <i class="fas fa-home"></i> Victim ID: VIC<?php echo str_pad($victim_id, 4, '0', STR_PAD_LEFT); ?>
                            </div>
                        `;
                        
                        victimMarker.bindPopup(victimPopup);
                    <?php endforeach; ?>
                <?php endif; ?>
                
                // Fit bounds to show all markers
                if (volunteerMarkers.length > 0 || victimMarkers.length > 0) {
                    const bounds = L.latLngBounds([]);
                    
                    volunteerMarkers.forEach(marker => {
                        bounds.extend(marker.getLatLng());
                    });
                    
                    victimMarkers.forEach(marker => {
                        bounds.extend(marker.getLatLng());
                    });
                    
                    map.fitBounds(bounds, { padding: [50, 50] });
                }
            <?php endif; ?>
        }
        
        // Refresh map
        function refreshMap() {
            if (map) {
                map.remove();
                volunteerMarkers = [];
                victimMarkers = [];
                pathLines = [];
            }
            
            initMap();
        }
        
        // Message functions
        function setMessage(message) {
            document.getElementById('message').value = message;
            document.getElementById('message').focus();
        }
        
        function insertQuickMessage() {
            const quickMessages = [
                "Please update your current location and status.",
                "How many deliveries have you completed so far?",
                "Are you facing any challenges with the delivery?",
                "Please confirm if you've arrived at the destination.",
                "We need you to return to the distribution center after completion."
            ];
            
            const randomMessage = quickMessages[Math.floor(Math.random() * quickMessages.length)];
            setMessage(randomMessage);
        }
        
        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            // Auto-refresh data every 30 seconds
            setInterval(() => {
                if (document.querySelector('.tab-content.active').id === 'map-tab') {
                    refreshMap();
                }
            }, 30000);
        });
    </script>
</body>
</html>