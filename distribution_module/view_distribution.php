<?php
require_once 'config.php';

$database = new Database();
$db = $database->getConnection();

$distribution_id = $_GET['id'] ?? null;

if (!$distribution_id) {
    header("Location: distribution_main.php");
    exit;
}

// Get distribution basic details
$query = "
    SELECT 
        d.*,
        dis.disaster_id,
        dis.Disaster_Name,
        dis.Disaster_Type,
        dis.Disaster_Date,
        dis.Severity_level,
        dis.Location as disaster_location,
        dis.Description as disaster_description
    FROM distribution d
    LEFT JOIN disaster dis ON d.disaster_id = dis.disaster_id
    WHERE d.distribution_id = ?
";

$stmt = $db->prepare($query);
$stmt->bind_param("i", $distribution_id);
$stmt->execute();
$result = $stmt->get_result();
$distribution = $result->fetch_assoc();
$stmt->close();

if (!$distribution) {
    echo "<div class='container'><div class='alert alert-danger'>Distribution not found!</div></div>";
    exit;
}

// Get all needs linked to this distribution
$needs_query = "
    SELECT 
        n.*,
        v.name as victim_name,
        v.age as victim_age,
        v.address as victim_address,
        v.family_size,
        r.name as resource_name,
        r.type as resource_type,
        r.unit as resource_unit
    FROM needs n
    JOIN victim v ON n.victim_id = v.victim_id
    JOIN resource r ON n.resource_id = r.resource_id
    WHERE n.distribution_id = ?
    ORDER BY v.victim_id, n.need_id
";

$needs_stmt = $db->prepare($needs_query);
$needs_stmt->bind_param("i", $distribution_id);
$needs_stmt->execute();
$needs_result = $needs_stmt->get_result();
$distribution_needs = $needs_result->fetch_all(MYSQLI_ASSOC);
$needs_stmt->close();

// Calculate statistics
$total_families = count(array_unique(array_column($distribution_needs, 'victim_id')));
$total_needs = count($distribution_needs);

// Group resources
$resources_summary = [];
foreach ($distribution_needs as $need) {
    $resource_id = $need['resource_id'];
    if (!isset($resources_summary[$resource_id])) {
        $resources_summary[$resource_id] = [
            'name' => $need['resource_name'],
            'type' => $need['resource_type'],
            'unit' => $need['resource_unit'],
            'total_quantity' => 0
        ];
    }
    $resources_summary[$resource_id]['total_quantity'] += $need['quantity_needed'];
}

// Parse distribution plan details from comments
$plan_details = [];
if ($distribution['comments']) {
    $lines = explode("\n", $distribution['comments']);
    foreach ($lines as $line) {
        if (strpos($line, 'Location:') !== false) {
            $plan_details['location'] = trim(str_replace('Location:', '', $line));
        }
        if (strpos($line, 'Coordinator:') !== false) {
            $plan_details['coordinator'] = trim(str_replace('Coordinator:', '', $line));
        }
        if (strpos($line, 'Estimated Duration:') !== false) {
            $plan_details['duration'] = trim(str_replace('Estimated Duration:', '', $line));
        }
        if (strpos($line, 'Volunteers Needed:') !== false) {
            $plan_details['volunteers_needed'] = trim(str_replace('Volunteers Needed:', '', $line));
        }
    }
}

// Get assigned volunteers for this distribution
$volunteers_query = "
    SELECT 
        dv.*,
        v.name as volunteer_name,
        v.phone as volunteer_phone,
        v.role as volunteer_main_role
    FROM distribution_volunteer dv
    LEFT JOIN volunteer v ON dv.volunteer_id = v.volunteer_id
    WHERE dv.distribution_id = ?
    ORDER BY dv.assigned_timestamp DESC
";

$volunteers_stmt = $db->prepare($volunteers_query);
$volunteers_stmt->bind_param("i", $distribution_id);
$volunteers_stmt->execute();
$volunteers_result = $volunteers_stmt->get_result();
$assigned_volunteers = $volunteers_result->fetch_all(MYSQLI_ASSOC);
$volunteers_stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Distribution #<?php echo $distribution_id; ?> - Details</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        /* Additional custom styles for view distribution */
        .victim-group {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border: 1px solid rgba(0,0,0,0.05);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .victim-group:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
            border-color: #3498db;
        }
        
        .victim-group::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, #667eea, #764ba2);
        }
        
        .victim-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 2px solid #3498db;
        }
        
        .need-item {
            background: white;
            padding: 15px;
            margin: 8px 0;
            border-radius: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.3s ease;
            border: 1px solid rgba(0,0,0,0.05);
        }
        
        .need-item:hover {
            transform: translateX(5px);
            background: #f8f9fa;
            border-color: #3498db;
        }
        
        .resource-summary-card {
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            border: 1px solid rgba(0,0,0,0.05);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 15px;
            transition: all 0.3s ease;
        }
        
        .resource-summary-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        
        .plan-detail-box {
            background: linear-gradient(135deg, #e8f4f8 0%, #d1ecf1 100%);
            border-left: 4px solid #3498db;
            padding: 15px;
            margin: 10px 0;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        
        .plan-detail-box:hover {
            transform: translateX(5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }
        
        /* Enhanced typography */
        .victim-header h3 {
            font-size: 1.2rem;
            color: #2c3e50;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .victim-header h3::before {
            content: '👤';
        }
        
        /* Priority badges */
        .priority-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 15px;
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 600;
            color: white;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .priority-badge::before {
            content: '';
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: currentColor;
            animation: pulse 2s infinite;
        }
        
        .badge-urgent {
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
        }
        
        .badge-high {
            background: linear-gradient(135deg, #f39c12 0%, #d35400 100%);
        }
        
        .badge-medium {
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
        }
        
        .badge-low {
            background: linear-gradient(135deg, #2ecc71 0%, #27ae60 100%);
        }
        
        /* Status badges enhancement */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 20px;
            border-radius: 50px;
            font-size: 0.9rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .status-badge.large {
            padding: 10px 25px;
            font-size: 1rem;
        }
        
        .status-badge::before {
            content: '';
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: currentColor;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.4; }
        }
        
        .status-planned {
            background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%);
            color: white;
        }
        
        .status-assigned {
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            color: white;
        }
        
        .status-active {
            background: linear-gradient(135deg, #2ecc71 0%, #27ae60 100%);
            color: white;
        }
        
        .status-completed {
            background: linear-gradient(135deg, #9b59b6 0%, #8e44ad 100%);
            color: white;
        }
        
        .status-cancelled {
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            color: white;
        }
        
        /* Severity badges */
        .severity-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 20px;
            border-radius: 50px;
            font-weight: 600;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .severity-badge::before {
            content: '';
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: currentColor;
        }
        
        .severity-low {
            background: rgba(46, 204, 113, 0.1);
            color: #27ae60;
            border: 1px solid rgba(46, 204, 113, 0.3);
        }
        
        .severity-medium {
            background: rgba(243, 156, 18, 0.1);
            color: #f39c12;
            border: 1px solid rgba(243, 156, 18, 0.3);
        }
        
        .severity-high {
            background: rgba(231, 76, 60, 0.1);
            color: #e74c3c;
            border: 1px solid rgba(231, 76, 60, 0.3);
        }
        
        .severity-critical {
            background: rgba(192, 57, 43, 0.1);
            color: #c0392b;
            border: 1px solid rgba(192, 57, 43, 0.3);
        }
        
        /* Badge count */
        .badge-count {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 8px 20px;
            border-radius: 50px;
            font-weight: 600;
            font-size: 0.9rem;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-right: 15px;
        }
        
        .badge-count::before {
            content: '🎯';
        }
        
        /* Progress bar */
        .progress-bar-container {
            width: 100%;
            background: #e9ecef;
            border-radius: 10px;
            margin: 20px 0;
            overflow: hidden;
        }
        
        .progress-bar {
            height: 20px;
            border-radius: 10px;
            text-align: center;
            line-height: 20px;
            color: white;
            font-weight: bold;
            transition: width 0.5s ease;
        }
        
        .progress-planned { background: linear-gradient(90deg, #f39c12, #e67e22); }
        .progress-assigned { background: linear-gradient(90deg, #3498db, #2980b9); }
        .progress-active { background: linear-gradient(90deg, #2ecc71, #27ae60); }
        .progress-completed { background: linear-gradient(90deg, #9b59b6, #8e44ad); }
        
        /* QR Code section */
        .qr-section {
            background: white;
            padding: 20px;
            border-radius: 12px;
            text-align: center;
            margin-top: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }
        
        .qr-code {
            width: 150px;
            height: 150px;
            margin: 15px auto;
            background: #f8f9fa;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            color: #667eea;
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .victim-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .need-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
        }
        
        /* Print styles */
        @media print {
            .btn, .action-buttons { 
                display: none !important; 
            }
            
            .card-3d {
                box-shadow: none !important;
                border: 1px solid #ddd !important;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Enhanced Header with Gradient -->
        <div class="header fade-in">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 20px;">
                <div>
                    <h1 style="margin: 0; display: flex; align-items: center; gap: 15px;">
                        <span style="background: rgba(255,255,255,0.2); padding: 10px; border-radius: 12px;">📦</span>
                        Distribution Plan Details
                    </h1>
                    <p style="margin: 10px 0 0 0; opacity: 0.9; font-size: 1.1rem;">
                        <strong>ID:</strong> DIST<?php echo str_pad($distribution_id, 3, '0', STR_PAD_LEFT); ?>
                        • <strong>Status:</strong> <?php echo ucfirst($distribution['status']); ?>
                        • <strong>Date:</strong> <?php echo date('F j, Y', strtotime($distribution['date'])); ?>
                    </p>
                </div>
                <div class="header-actions">
                    <?php if ($distribution['status'] == 'Planned'): ?>
                        <a href="assign_volunteer.php?distribution_id=<?php echo $distribution_id; ?>" class="btn btn-success">
                            👥 Assign Volunteers
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Quick Action Bar -->
        <div class="card-3d slide-in">
            <div class="action-buttons" style="display: flex; gap: 10px; flex-wrap: wrap;">
                <a href="manage_needs.php?disaster_id=<?php echo $distribution['disaster_id']; ?>" class="btn btn-warning">
                    📋 Manage Needs
                </a>
                <a href="create_distribution.php" class="btn btn-primary">
                    ➕ Create New Distribution
                </a>
                <a href="distribution_main.php" class="btn btn-secondary">
                    📊 Dashboard
                </a>
                <button onclick="window.print()" class="btn btn-info">
                    🖨️ Print
                </button>
                <button onclick="shareDistribution()" class="btn btn-success">
                    📤 Share
                </button>
            </div>
        </div>

        <!-- Distribution Status Progress -->
        <div class="card-3d">
            <div class="section-header">
                <h2 style="display: flex; align-items: center; gap: 10px;">
                    <span>📊</span> Distribution Progress
                </h2>
                <div class="badge-count">
                    <?php echo count($assigned_volunteers); ?> Volunteers
                </div>
            </div>
            
            <div style="text-align: center; margin: 20px 0;">
                <div style="margin-bottom: 15px;">
                    <span class="status-badge status-<?php echo strtolower($distribution['status']); ?> large">
                        <?php echo ucfirst($distribution['status']); ?>
                    </span>
                </div>
                
                <!-- Simple progress indicator -->
                <div style="max-width: 400px; margin: 0 auto;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 10px; font-size: 0.9em; color: #6c757d;">
                        <span>Planned</span>
                        <span>Assigned</span>
                        <span>Active</span>
                        <span>Completed</span>
                    </div>
                    
                    <div class="progress-bar-container">
                        <?php 
                        $progress_width = [
                            'Planned' => 25,
                            'Assigned' => 50,
                            'Active' => 75,
                            'Completed' => 100
                        ];
                        $current_width = $progress_width[$distribution['status']] ?? 25;
                        ?>
                        <div class="progress-bar progress-<?php echo strtolower($distribution['status']); ?>" 
                             style="width: <?php echo $current_width; ?>%;">
                            <?php echo $current_width; ?>%
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Action buttons based on status -->
            <?php if ($distribution['status'] == 'Assigned'): ?>
                <div style="text-align: center; margin-top: 20px;">
                    <p>⚠️ To start distribution, volunteers need to confirm attendance</p>
                </div>
            <?php elseif ($distribution['status'] == 'Active'): ?>
                <div style="text-align: center; margin-top: 20px;">
                    <p>✅ Distribution is currently in progress</p>
                    <a href="execute_distribution.php?id=<?php echo $distribution_id; ?>" class="btn btn-success">
                        📱 Go to Distribution Console
                    </a>
                </div>
            <?php elseif ($distribution['status'] == 'Completed'): ?>
                <div style="text-align: center; margin-top: 20px; background: #d4edda; padding: 15px; border-radius: 8px;">
                    <p style="color: #155724; font-weight: bold;">
                        ✅ Distribution completed successfully!
                    </p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Main Information Grid -->
        <div class="grid-2">
            <!-- Distribution Overview Card -->
            <div class="card-3d">
                <div class="section-header">
                    <h2 style="display: flex; align-items: center; gap: 10px;">
                        <span>📊</span> Distribution Overview
                    </h2>
                </div>
                
                <div class="overview-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 20px;">
                    <div class="overview-item">
                        <label style="font-weight: 600; color: #7f8c8d; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.5px;">Distribution ID</label>
                        <div class="value" style="font-size: 1.5rem; font-weight: 700; color: #2c3e50;">
                            #<?php echo $distribution['distribution_id']; ?>
                        </div>
                    </div>
                    <div class="overview-item">
                        <label style="font-weight: 600; color: #7f8c8d; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.5px;">Status</label>
                        <div class="value">
                            <span class="status-badge status-<?php echo strtolower($distribution['status']); ?>">
                                <?php echo ucfirst($distribution['status']); ?>
                            </span>
                        </div>
                    </div>
                    <div class="overview-item">
                        <label style="font-weight: 600; color: #7f8c8d; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.5px;">Distribution Date</label>
                        <div class="value" style="font-size: 1.2rem; color: #2c3e50; font-weight: 500;">
                            <?php echo date('F j, Y g:i A', strtotime($distribution['date'])); ?>
                        </div>
                    </div>
                    <div class="overview-item">
                        <label style="font-weight: 600; color: #7f8c8d; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.5px;">Total Families</label>
                        <div class="value" style="font-size: 1.5rem; font-weight: 700; color: #3498db;">
                            <?php echo $total_families; ?> <span style="font-size: 1rem; color: #7f8c8d;">families</span>
                        </div>
                    </div>
                    <div class="overview-item">
                        <label style="font-weight: 600; color: #7f8c8d; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.5px;">Total Needs</label>
                        <div class="value" style="font-size: 1.5rem; font-weight: 700; color: #2ecc71;">
                            <?php echo $total_needs; ?> <span style="font-size: 1rem; color: #7f8c8d;">items</span>
                        </div>
                    </div>
                    <div class="overview-item">
                        <label style="font-weight: 600; color: #7f8c8d; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.5px;">Assigned Volunteers</label>
                        <div class="value" style="font-size: 1.5rem; font-weight: 700; color: #9b59b6;">
                            <?php echo count($assigned_volunteers); ?> <span style="font-size: 1rem; color: #7f8c8d;">volunteers</span>
                        </div>
                    </div>
                </div>

                <?php if (!empty($plan_details)): ?>
                <div style="margin-top: 25px; padding-top: 20px; border-top: 2px solid #ecf0f1;">
                    <h3 style="font-size: 1.2em; margin-bottom: 15px; color: #2c3e50; display: flex; align-items: center; gap: 10px;">
                        <span>📋</span> Plan Details
                    </h3>
                    <?php if (isset($plan_details['location'])): ?>
                    <div class="plan-detail-box">
                        <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 5px;">
                            <span style="font-size: 1.2em;">📍</span>
                            <strong style="color: #2c3e50;">Location:</strong>
                        </div>
                        <div style="color: #34495e; padding-left: 30px;">
                            <?php echo htmlspecialchars($plan_details['location']); ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (isset($plan_details['coordinator'])): ?>
                    <div class="plan-detail-box">
                        <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 5px;">
                            <span style="font-size: 1.2em;">👤</span>
                            <strong style="color: #2c3e50;">Coordinator:</strong>
                        </div>
                        <div style="color: #34495e; padding-left: 30px;">
                            <?php echo htmlspecialchars($plan_details['coordinator']); ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-top: 15px;">
                        <?php if (isset($plan_details['duration'])): ?>
                        <div class="plan-detail-box">
                            <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 5px;">
                                <span style="font-size: 1.2em;">⏱️</span>
                                <strong style="color: #2c3e50;">Duration:</strong>
                            </div>
                            <div style="color: #34495e; padding-left: 30px;">
                                <?php echo htmlspecialchars($plan_details['duration']); ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (isset($plan_details['volunteers_needed'])): ?>
                        <div class="plan-detail-box">
                            <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 5px;">
                                <span style="font-size: 1.2em;">👥</span>
                                <strong style="color: #2c3e50;">Volunteers Needed:</strong>
                            </div>
                            <div style="color: #34495e; padding-left: 30px;">
                                <?php echo htmlspecialchars($plan_details['volunteers_needed']); ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Disaster Information Card -->
            <div class="card-3d">
                <div class="section-header">
                    <h2 style="display: flex; align-items: center; gap: 10px;">
                        <span>🌪️</span> Disaster Information
                    </h2>
                    <span class="severity-badge severity-<?php echo strtolower($distribution['Severity_level']); ?>">
                        <?php echo htmlspecialchars($distribution['Severity_level']); ?> Severity
                    </span>
                </div>
                
                <div class="info-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
                    <div class="info-item full-width">
                        <label style="font-weight: 600; color: #7f8c8d; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.5px;">Disaster Name</label>
                        <div class="value" style="font-size: 1.3rem; color: #2c3e50; font-weight: 600;">
                            <?php echo htmlspecialchars($distribution['Disaster_Name']); ?>
                        </div>
                    </div>
                    <div class="info-item">
                        <label style="font-weight: 600; color: #7f8c8d; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.5px;">Type</label>
                        <div class="value" style="font-size: 1.1rem; color: #2c3e50; font-weight: 500;">
                            <?php echo htmlspecialchars($distribution['Disaster_Type']); ?>
                        </div>
                    </div>
                    <div class="info-item">
                        <label style="font-weight: 600; color: #7f8c8d; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.5px;">Severity</label>
                        <div class="value">
                            <span class="severity-badge severity-<?php echo strtolower($distribution['Severity_level']); ?>">
                                <?php echo htmlspecialchars($distribution['Severity_level']); ?>
                            </span>
                        </div>
                    </div>
                    <div class="info-item">
                        <label style="font-weight: 600; color: #7f8c8d; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.5px;">Date</label>
                        <div class="value" style="font-size: 1.1rem; color: #2c3e50; font-weight: 500;">
                            <?php echo date('F j, Y', strtotime($distribution['Disaster_Date'])); ?>
                        </div>
                    </div>
                    <div class="info-item">
                        <label style="font-weight: 600; color: #7f8c8d; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.5px;">Location</label>
                        <div class="value" style="font-size: 1.1rem; color: #2c3e50; font-weight: 500;">
                            <?php echo htmlspecialchars($distribution['disaster_location']); ?>
                        </div>
                    </div>
                    <?php if ($distribution['disaster_description']): ?>
                    <div class="info-item full-width">
                        <label style="font-weight: 600; color: #7f8c8d; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.5px;">Description</label>
                        <div class="value" style="color: #5d6d7e; line-height: 1.6; background: #f8f9fa; padding: 15px; border-radius: 8px; margin-top: 5px;">
                            <?php echo nl2br(htmlspecialchars($distribution['disaster_description'])); ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Resources Summary Card -->
        <div class="card-3d">
            <div class="section-header">
                <h2 style="display: flex; align-items: center; gap: 10px;">
                    <span>📦</span> Resources Summary
                    <span style="background: #e3f2fd; color: #1976d2; padding: 5px 15px; border-radius: 20px; font-size: 0.9rem; font-weight: 600;">
                        <?php echo count($resources_summary); ?> types
                    </span>
                </h2>
            </div>
            
            <div class="grid-3">
                <?php foreach ($resources_summary as $resource): ?>
                <div class="resource-summary-card">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <div style="font-weight: 600; color: #2c3e50; font-size: 1.1em;">
                                <?php echo htmlspecialchars($resource['name']); ?>
                            </div>
                            <div style="color: #7f8c8d; font-size: 0.9em; margin-top: 5px;">
                                <span style="background: #f0f4f8; padding: 3px 10px; border-radius: 12px;">
                                    <?php echo htmlspecialchars($resource['type']); ?>
                                </span>
                            </div>
                        </div>
                        <div style="text-align: right;">
                            <div style="font-size: 1.8em; font-weight: bold; color: #3498db;">
                                <?php echo $resource['total_quantity']; ?>
                            </div>
                            <div style="font-size: 0.9em; color: #7f8c8d; margin-top: 5px;">
                                <?php echo htmlspecialchars($resource['unit']); ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Victims and Their Needs Card -->
        <div class="card-3d">
            <div class="section-header">
                <h2 style="display: flex; align-items: center; gap: 10px;">
                    <span>👨‍👩‍👧‍👦</span> Families & Their Needs
                    <span style="background: #ffeaa7; color: #856404; padding: 5px 15px; border-radius: 20px; font-size: 0.9rem; font-weight: 600;">
                        <?php echo $total_families; ?> families • <?php echo $total_needs; ?> needs
                    </span>
                </h2>
            </div>
            
            <?php 
            $victims_grouped = [];
            foreach ($distribution_needs as $need) {
                $victim_id = $need['victim_id'];
                if (!isset($victims_grouped[$victim_id])) {
                    $victims_grouped[$victim_id] = [
                        'info' => [
                            'name' => $need['victim_name'],
                            'age' => $need['victim_age'],
                            'address' => $need['victim_address'],
                            'family_size' => $need['family_size']
                        ],
                        'needs' => []
                    ];
                }
                $victims_grouped[$victim_id]['needs'][] = $need;
            }
            
            foreach ($victims_grouped as $victim_id => $victim_data): 
            ?>
            <div class="victim-group">
                <div class="victim-header">
                    <div>
                        <h3>
                            <?php echo htmlspecialchars($victim_data['info']['name']); ?>
                        </h3>
                        <div style="font-size: 0.9em; color: #7f8c8d; margin-top: 5px; display: flex; gap: 20px; flex-wrap: wrap;">
                            <span>
                                <strong>Age:</strong> <?php echo $victim_data['info']['age']; ?>
                            </span>
                            <span>
                                <strong>Family Size:</strong> <?php echo $victim_data['info']['family_size']; ?> people
                            </span>
                            <span>
                                <strong>Address:</strong> <?php echo htmlspecialchars($victim_data['info']['address']); ?>
                            </span>
                        </div>
                    </div>
                    <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 8px 20px; border-radius: 20px; font-weight: 600;">
                        <?php echo count($victim_data['needs']); ?> needs
                    </div>
                </div>
                
                <div style="margin-top: 15px;">
                    <?php foreach ($victim_data['needs'] as $need): ?>
                    <div class="need-item">
                        <div style="flex-grow: 1;">
                            <div style="font-weight: 600; color: #2c3e50; font-size: 1rem;">
                                <?php echo htmlspecialchars($need['resource_name']); ?>
                            </div>
                            <div style="font-size: 0.85em; color: #7f8c8d; margin-top: 3px;">
                                <?php echo htmlspecialchars($need['resource_type']); ?>
                            </div>
                        </div>
                        <div style="text-align: right;">
                            <div style="font-weight: 700; color: #2c3e50; font-size: 1.2rem;">
                                <?php echo $need['quantity_needed']; ?> <?php echo htmlspecialchars($need['resource_unit']); ?>
                            </div>
                            <div style="margin-top: 8px;">
                                <span class="priority-badge badge-<?php echo strtolower($need['priority']); ?>">
                                    <?php echo $need['priority']; ?> Priority
                                </span>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Assigned Volunteers Card -->
        <div class="card-3d">
            <div class="section-header">
                <h2 style="display: flex; align-items: center; gap: 10px;">
                    <span>👥</span> Assigned Volunteers
                </h2>
                <div>
                    <span class="badge-count"><?php echo count($assigned_volunteers); ?> assigned</span>
                    <?php if ($distribution['status'] == 'Planned' || $distribution['status'] == 'Assigned'): ?>
                        <a href="assign_volunteer.php?distribution_id=<?php echo $distribution_id; ?>" class="btn btn-success btn-sm">
                            ➕ Assign More
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if (count($assigned_volunteers) > 0): ?>
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px;">
                    <?php foreach ($assigned_volunteers as $volunteer): ?>
                    <div class="volunteer-card" style="background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%); border: 1px solid rgba(0,0,0,0.05); border-radius: 12px; padding: 20px; transition: all 0.3s ease;">
                        <div style="display: flex; align-items: start; gap: 15px;">
                            <div class="volunteer-avatar" style="width: 60px; height: 60px; border-radius: 50%; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; display: flex; align-items: center; justify-content: center; font-size: 1.5em; font-weight: bold;">
                                <?php echo strtoupper(substr($volunteer['volunteer_name'], 0, 1)); ?>
                            </div>
                            <div style="flex-grow: 1;">
                                <h4 style="margin: 0 0 8px 0; color: #2c3e50;"><?php echo htmlspecialchars($volunteer['volunteer_name']); ?></h4>
                                <p style="margin: 5px 0; font-size: 0.9em; color: #7f8c8d;">
                                    <strong>Role:</strong> 
                                    <span style="background: #e3f2fd; color: #1976d2; padding: 2px 10px; border-radius: 12px; font-size: 0.85em;">
                                        <?php echo htmlspecialchars($volunteer['role']); ?>
                                    </span>
                                </p>
                                <p style="margin: 5px 0; font-size: 0.9em; color: #7f8c8d;">
                                    📱 <?php echo htmlspecialchars($volunteer['volunteer_phone']); ?>
                                </p>
                                <p style="margin: 8px 0 5px 0;">
                                    <span class="status-badge status-<?php echo strtolower($volunteer['status']); ?>">
                                        <?php echo $volunteer['status']; ?>
                                    </span>
                                </p>
                                <p style="margin: 5px 0 0 0; font-size: 0.85em; color: #95a5a6;">
                                    <i class="fas fa-clock"></i> Assigned: <?php echo date('M j, Y g:i A', strtotime($volunteer['assigned_timestamp'])); ?>
                                </p>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state" style="text-align: center; padding: 40px; color: #7f8c8d;">
                    <div style="font-size: 3em; margin-bottom: 15px;">👥</div>
                    <h3>No Volunteers Assigned</h3>
                    <p>No volunteers have been assigned to this distribution yet.</p>
                    <?php if ($distribution['status'] == 'Planned' || $distribution['status'] == 'Assigned'): ?>
                        <a href="assign_volunteer.php?distribution_id=<?php echo $distribution_id; ?>" class="btn btn-success mt-3" style="padding: 12px 30px;">
                            Assign Volunteers Now
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Quick Stats Cards -->
        <div class="grid-4" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 30px 0;">
            <div class="stat-card" style="border-left: 4px solid #667eea;">
                <div class="stat-label">Total Families</div>
                <div class="stat-number"><?php echo $total_families; ?></div>
                <div style="font-size: 0.9em; color: #7f8c8d;">Families Assisted</div>
            </div>
            <div class="stat-card" style="border-left: 4px solid #2ecc71;">
                <div class="stat-label">Total Needs</div>
                <div class="stat-number"><?php echo $total_needs; ?></div>
                <div style="font-size: 0.9em; color: #7f8c8d;">Resource Items</div>
            </div>
            <div class="stat-card" style="border-left: 4px solid #9b59b6;">
                <div class="stat-label">Resource Types</div>
                <div class="stat-number"><?php echo count($resources_summary); ?></div>
                <div style="font-size: 0.9em; color: #7f8c8d;">Different Resources</div>
            </div>
            <div class="stat-card" style="border-left: 4px solid #f39c12;">
                <div class="stat-label">Volunteers</div>
                <div class="stat-number"><?php echo count($assigned_volunteers); ?></div>
                <div style="font-size: 0.9em; color: #7f8c8d;">Assigned</div>
            </div>
        </div>

        <!-- Distribution Plan Details & QR Code -->
        <?php if ($distribution['comments']): ?>
        <div class="grid-2" style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px;">
            <div class="card-3d">
                <h2 style="display: flex; align-items: center; gap: 10px;">
                    <span>📝</span> Distribution Plan Details
                </h2>
                <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; white-space: pre-line; font-family: monospace; font-size: 0.9em; line-height: 1.6; border: 1px solid rgba(0,0,0,0.05);">
                    <?php echo htmlspecialchars($distribution['comments']); ?>
                </div>
            </div>
            
            <div class="qr-section card-3d">
                <h3 style="margin-bottom: 15px; color: #2c3e50;">📱 Quick Actions</h3>
                <div class="qr-code">
                    <?php echo $distribution_id; ?>
                </div>
                <p style="color: #7f8c8d; margin: 10px 0 15px 0; font-size: 0.9em;">
                    Scan to view distribution details
                </p>
                <div style="display: flex; flex-direction: column; gap: 10px;">
                    <button onclick="downloadReport()" class="btn btn-info" style="width: 100%;">
                        📥 Download Report
                    </button>
                    <button onclick="shareDistribution()" class="btn btn-success" style="width: 100%;">
                        📤 Share Distribution
                    </button>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Footer Actions -->
        <div class="card-3d" style="margin-top: 30px; text-align: center;">
            <div style="display: flex; justify-content: center; gap: 15px; flex-wrap: wrap;">
                <?php if ($distribution['status'] == 'Planned' || $distribution['status'] == 'Assigned'): ?>
                    <a href="edit_distribution.php?id=<?php echo $distribution_id; ?>" class="btn btn-primary">
                        ✏️ Edit Distribution
                    </a>
                <?php endif; ?>
                
                <?php if ($distribution['status'] == 'Active'): ?>
                    <button onclick="completeDistribution()" class="btn btn-success">
                        ✅ Mark as Completed
                    </button>
                <?php endif; ?>
                
                <button onclick="exportToPDF()" class="btn btn-info">
                    📄 Export to PDF
                </button>
                
                <a href="distribution_main.php" class="btn btn-secondary">
                    📊 Back to Dashboard
                </a>
            </div>
        </div>
    </div>

    <script>
        // JavaScript functions for enhanced UI
        function shareDistribution() {
            const distributionId = <?php echo $distribution_id; ?>;
            const shareData = {
                title: 'Distribution #' + distributionId + ' Details',
                text: 'View distribution details for Disaster Relief System',
                url: window.location.href
            };
            
            if (navigator.share) {
                navigator.share(shareData)
                    .then(() => console.log('Shared successfully'))
                    .catch(console.error);
            } else {
                // Fallback for browsers that don't support Web Share API
                alert('Share URL: ' + window.location.href);
            }
        }

        function downloadReport() {
            // Simulate report download
            alert('Downloading distribution report...');
            // In a real application, this would generate and download a PDF report
        }

        function completeDistribution() {
            if (confirm('Are you sure you want to mark this distribution as COMPLETED?\n\nThis will:\n• Close the distribution\n• Update inventory records\n• Mark volunteers as available')) {
                // In a real application, this would make an AJAX call
                fetch('complete_distribution.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'distribution_id=' + <?php echo $distribution_id; ?>
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Distribution marked as completed successfully!');
                        location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    alert('Error: ' + error);
                });
            }
        }

        function exportToPDF() {
            alert('Exporting to PDF...');
            // In a real application, this would generate a PDF
        }

        // Add scroll animations
        document.addEventListener('DOMContentLoaded', function() {
            const observerOptions = {
                threshold: 0.1,
                rootMargin: '0px 0px -50px 0px'
            };

            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('fade-in');
                    }
                });
            }, observerOptions);

            // Observe all cards for animation
            document.querySelectorAll('.card-3d, .victim-group, .resource-summary-card').forEach(card => {
                observer.observe(card);
            });
        });
    </script>
</body>
</html>