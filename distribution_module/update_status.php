<?php
require_once 'config.php';

$database = new Database();
$db = $database->getConnection();

$distribution_id = $_GET['id'] ?? null;

if (!$distribution_id) {
    header("Location: index.php");
    exit;
}

// First, let's check if the distribution exists at all
$check_query = "SELECT * FROM distribution WHERE distribution_id = ?";
$check_stmt = $db->prepare($check_query);
$check_stmt->bind_param("i", $distribution_id);
$check_stmt->execute();
$check_result = $check_stmt->get_result();
$basic_distribution = $check_result->fetch_assoc();
$check_stmt->close();

if (!$basic_distribution) {
    echo "<div class='container'><div class='alert alert-danger'>Distribution #$distribution_id not found in database!</div></div>";
    exit;
}

// Get distribution details
$query = "
    SELECT 
        d.*,
        dis.Disaster_Name,
        dis.Disaster_Type,
        dis.Severity_level,
        dis.Location as disaster_location
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

// Get ALL needs for this distribution (REMOVED LIMIT 1)
$needs_query = "
    SELECT 
        n.*,
        v.name as victim_name,
        v.age as victim_age,
        v.address as victim_address,
        v.family_size,
        r.name as resource_name,
        r.unit as resource_unit,
        r.type as resource_type
    FROM needs n
    LEFT JOIN victim v ON n.victim_id = v.victim_id
    LEFT JOIN resource r ON n.resource_id = r.resource_id
    WHERE n.distribution_id = ?
";

$needs_stmt = $db->prepare($needs_query);
$needs_stmt->bind_param("i", $distribution_id);
$needs_stmt->execute();
$needs_result = $needs_stmt->get_result();
$needs = $needs_result->fetch_all(MYSQLI_ASSOC); // Get ALL needs
$needs_stmt->close();

// Get total quantity needed from ALL needs
$total_quantity_needed = 0;
foreach ($needs as $need_item) {
    $total_quantity_needed += $need_item['quantity_needed'] ?? 0;
}

// Get assigned volunteers for this distribution
$volunteers_query = "
    SELECT 
        dv.*,
        v.name as volunteer_name,
        v.role as volunteer_role
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

// Handle status update
$success = $error = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_status'])) {
    $new_status = $_POST['status'] ?? '';
    $quantity_received = $_POST['quantity_received'] ?? $basic_distribution['quantity_sent'];
    $status_notes = $_POST['status_notes'] ?? '';
    
    try {
        // Start transaction
        $db->begin_transaction();

        // Update distribution status
        $update_query = "
            UPDATE distribution 
            SET status = ?, 
                quantity_received = ?, 
                comments = CONCAT(IFNULL(comments, ''), '\n\nStatus Update (', DATE_FORMAT(NOW(), '%Y-%m-%d %H:%i:%s'), '): ', ?)
            WHERE distribution_id = ?
        ";
        $update_stmt = $db->prepare($update_query);
        $update_stmt->bind_param("sisi", $new_status, $quantity_received, $status_notes, $distribution_id);
        
        if (!$update_stmt->execute()) {
            throw new Exception("Error updating distribution status: " . $update_stmt->error);
        }
        $update_stmt->close();

        // If status is Completed, update resource inventory
        if ($new_status === 'Completed' && !empty($needs[0]['resource_id'])) {
            // Update resource quantity
            $resource_query = "
                UPDATE resource 
                SET quantity_available = quantity_available - ? 
                WHERE resource_id = ?
            ";
            $resource_stmt = $db->prepare($resource_query);
            $resource_stmt->bind_param("ii", $quantity_received, $needs[0]['resource_id']);
            
            if (!$resource_stmt->execute()) {
                throw new Exception("Error updating resource inventory: " . $resource_stmt->error);
            }
            $resource_stmt->close();

            // Update needs status if exists
            $needs_update_query = "
                UPDATE needs 
                SET status = 'fulfilled' 
                WHERE distribution_id = ?
            ";
            $needs_update_stmt = $db->prepare($needs_update_query);
            $needs_update_stmt->bind_param("i", $distribution_id);
            $needs_update_stmt->execute();
            $needs_update_stmt->close();
        }

        // Commit transaction
        $db->commit();
        
        $success = "✅ Distribution status updated successfully to: " . $new_status;
        
        // Refresh distribution data
        $stmt = $db->prepare($query);
        $stmt->bind_param("i", $distribution_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $distribution = $result->fetch_assoc();
        $stmt->close();
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $db->rollback();
        $error = "❌ Error updating status: " . $e->getMessage();
    }
}

// Helper function to safely output data
function safe_output($data, $default = '') {
    return htmlspecialchars($data ?? $default);
}

// Get current status for CSS classes
$current_status = $distribution['status'] ?? 'Planned';
$status_class = 'status-' . strtolower($current_status);

// Map your statuses to example statuses for progress tracking
$status_map = [
    'Planned' => 'planned',
    'Assigned' => 'assigned', 
    'In Transit' => 'active',
    'Delivered' => 'active',
    'Completed' => 'completed',
    'Cancelled' => 'cancelled'
];

$current_status_mapped = $status_map[$current_status] ?? 'planned';

// Calculate progress percentage
$progress_percentage = 0;
switch ($current_status_mapped) {
    case 'planned': $progress_percentage = 25; break;
    case 'assigned': $progress_percentage = 50; break;
    case 'active': $progress_percentage = 75; break;
    case 'completed': $progress_percentage = 100; break;
    default: $progress_percentage = 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Distribution Status - Disaster Relief System</title>
    <style>
        <?php
        // Include the CSS from the example
        echo file_get_contents('../css/update.css');
        ?>
        
        /* Additional styles for your PHP integration */
        .alert {
            padding: 16px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            border-left: 4px solid;
            animation: slideDown 0.4s ease;
        }
        
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .alert-success {
            background-color: rgba(16, 185, 129, 0.1);
            border-color: var(--success);
            color: #065f46;
        }
        
        .alert-error {
            background-color: rgba(239, 68, 68, 0.1);
            border-color: var(--danger);
            color: #991b1b;
        }
        
        .debug-panel {
            background: rgba(0,0,0,0.1);
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-family: monospace;
            font-size: 12px;
        }
        
        .data-row {
            display: flex;
            gap: 10px;
            margin-bottom: 5px;
        }
        
        .data-label {
            font-weight: bold;
            color: #4b5563;
        }
        
        /* Status badge overrides for your status system */
        .status-badge-large.status-planned {
            background: linear-gradient(135deg, #f39c12, #e67e22);
            color: white;
        }
        
        .status-badge-large.status-assigned {
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
        }
        
        .status-badge-large.status-active {
            background: linear-gradient(135deg, #2ecc71, #27ae60);
            color: white;
        }
        
        .status-badge-large.status-completed {
            background: linear-gradient(135deg, #9b59b6, #8e44ad);
            color: white;
        }
        
        .status-badge-large.status-cancelled {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
            color: white;
        }
        
        /* Status guide badges */
        .status-guide-badge.status-planned {
            background: linear-gradient(135deg, #f39c12, #e67e22);
            color: white;
        }
        
        .status-guide-badge.status-assigned {
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
        }
        
        .status-guide-badge.status-active {
            background: linear-gradient(135deg, #2ecc71, #27ae60);
            color: white;
        }
        
        .status-guide-badge.status-completed {
            background: linear-gradient(135deg, #9b59b6, #8e44ad);
            color: white;
        }
        
        .status-guide-badge.status-cancelled {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
            color: white;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Debug Information (Remove this section after testing) -->
        <?php if (isset($_GET['debug'])): ?>
        <div class="debug-panel">
            <h4>Debug Information</h4>
            <div class="data-row">
                <span class="data-label">Distribution ID:</span>
                <span><?php echo $distribution_id; ?></span>
            </div>
            <div class="data-row">
                <span class="data-label">Current Status:</span>
                <span><?php echo $distribution['status'] ?? 'NULL'; ?></span>
            </div>
            <div class="data-row">
                <span class="data-label">Basic Distribution Found:</span>
                <span><?php echo $basic_distribution ? 'YES' : 'NO'; ?></span>
            </div>
            <div class="data-row">
                <span class="data-label">Number of Needs Found:</span>
                <span><?php echo count($needs); ?></span>
            </div>
            <div class="data-row">
                <span class="data-label">Total Quantity Needed:</span>
                <span><?php echo $total_quantity_needed; ?></span>
            </div>
        </div>
        <?php endif; ?>

        <!-- Alert Messages -->
        <?php if (!empty($success)): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <?php if (!empty($error)): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <!-- Breadcrumb Navigation -->
        <nav class="breadcrumb">
            <a href="distribution_main.php">🏠 Dashboard</a>
            <span class="breadcrumb-separator">›</span>
            <a href="distribution_main.php">📦 Distributions</a>
            <span class="breadcrumb-separator">›</span>
            <span class="breadcrumb-current">Update Status</span>
        </nav>

        <!-- Page Header -->
        <header class="page-header">
            <div class="page-header-top">
                <div class="page-title-section">
                    <h1>
                        <span>📝</span>
                        Update Distribution Status
                    </h1>
                    <p class="page-subtitle">
                        <span>📍</span>
                        Distribution ID: #<?php echo $distribution_id; ?> - 
                        <?php echo count($needs); ?> victim(s) - 
                        Total needed: <?php echo $total_quantity_needed; ?> 
                        <?php echo !empty($needs) ? safe_output($needs[0]['resource_unit'] ?? 'units') : 'units'; ?>
                    </p>
                </div>
                <div class="distribution-id-badge">
                    #<?php echo $distribution_id; ?>
                </div>
            </div>

            <!-- Quick Stats -->
            <div class="quick-stats">
                <div class="quick-stat-item">
                    <div class="quick-stat-label">Disaster</div>
                    <div class="quick-stat-value"><?php echo safe_output($distribution['Disaster_Name'] ?? 'Not linked'); ?></div>
                </div>
                <div class="quick-stat-item">
                    <div class="quick-stat-label">Quantity Needed</div>
                    <div class="quick-stat-value">
                        <?php echo $total_quantity_needed; ?> 
                        <?php echo !empty($needs) ? safe_output($needs[0]['resource_unit'] ?? 'units') : 'units'; ?>
                    </div>
                </div>
                <div class="quick-stat-item">
                    <div class="quick-stat-label">Quantity Sent</div>
                    <div class="quick-stat-value">
                        <?php echo safe_output($basic_distribution['quantity_sent'] ?? '0'); ?> 
                        <?php echo !empty($needs) ? safe_output($needs[0]['resource_unit'] ?? 'units') : 'units'; ?>
                    </div>
                </div>
                <div class="quick-stat-item">
                    <div class="quick-stat-label">Volunteers</div>
                    <div class="quick-stat-value"><?php echo count($assigned_volunteers); ?> assigned</div>
                </div>
            </div>
        </header>

        <!-- Main Grid Layout -->
        <div class="main-grid">
            <!-- Sidebar -->
            <aside class="sidebar">
                <!-- Distribution Overview -->
                <div class="card">
                    <div class="card-header">
                        📊 Distribution Overview
                    </div>
                    <div class="card-body">
                        <div class="distribution-overview">
                            <!-- Current Status -->
                            <div class="current-status-display">
                                <div class="status-label-small">Current Status</div>
                                <div class="status-badge-large <?php echo 'status-' . strtolower($current_status_mapped); ?>">
                                    <?php echo safe_output($current_status); ?>
                                </div>
                            </div>

                            <!-- Basic Information -->
                            <div class="overview-section">
                                <div class="section-title">Basic Information</div>
                                <div class="info-item">
                                    <span class="info-label">Distribution ID</span>
                                    <span class="info-value">#<?php echo $distribution_id; ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Created Date</span>
                                    <span class="info-value"><?php echo date('M d, Y', strtotime($distribution['date'] ?? $basic_distribution['date'] ?? 'now')); ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Status</span>
                                    <span class="info-value"><?php echo safe_output($current_status); ?></span>
                                </div>
                            </div>

                            <!-- Disaster Details -->
                            <div class="overview-section">
                                <div class="section-title">Disaster Details</div>
                                <div class="info-item">
                                    <span class="info-label">Disaster</span>
                                    <span class="info-value"><?php echo safe_output($distribution['Disaster_Name'] ?? 'Not linked'); ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Location</span>
                                    <span class="info-value"><?php echo safe_output($distribution['disaster_location'] ?? 'Unknown'); ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Type</span>
                                    <span class="info-value"><?php echo safe_output($distribution['Disaster_Type'] ?? 'Unknown'); ?></span>
                                </div>
                            </div>

                            <!-- Resource Details -->
                            <div class="overview-section">
                                <div class="section-title">Resource Details</div>
                                <div class="info-item">
                                    <span class="info-label">Resource</span>
                                    <span class="info-value">
                                        <?php 
                                        $resource_names = [];
                                        foreach ($needs as $need_item) {
                                            if (!empty($need_item['resource_name'])) {
                                                $resource_names[] = safe_output($need_item['resource_name']);
                                            }
                                        }
                                        echo !empty($resource_names) ? implode(', ', array_unique($resource_names)) : 'No resources specified';
                                        ?>
                                    </span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Total Needed</span>
                                    <span class="info-value">
                                        <?php echo $total_quantity_needed; ?> 
                                        <?php echo !empty($needs) ? safe_output($needs[0]['resource_unit'] ?? 'units') : 'units'; ?>
                                    </span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Quantity Sent</span>
                                    <span class="info-value">
                                        <?php echo safe_output($basic_distribution['quantity_sent'] ?? '0'); ?> 
                                        <?php echo !empty($needs) ? safe_output($needs[0]['resource_unit'] ?? 'units') : 'units'; ?>
                                    </span>
                                </div>
                                <?php if (!empty($distribution['quantity_received'])): ?>
                                <div class="info-item">
                                    <span class="info-label">Quantity Received</span>
                                    <span class="info-value">
                                        <?php echo safe_output($distribution['quantity_received'] ?? '0'); ?> 
                                        <?php echo !empty($needs) ? safe_output($needs[0]['resource_unit'] ?? 'units') : 'units'; ?>
                                    </span>
                                </div>
                                <?php endif; ?>
                            </div>

                            <!-- Victims and Their Needs -->
                            <?php if (!empty($needs)): ?>
                            <div class="overview-section">
                                <div class="section-title">Victims & Their Needs (<?php echo count($needs); ?>)</div>
                                <?php foreach ($needs as $index => $need_item): ?>
                                <div class="info-item" style="border-bottom: 1px solid #eee; padding-bottom: 10px; margin-bottom: 10px;">
                                    <div style="display: flex; justify-content: space-between; width: 100%;">
                                        <div>
                                            <div style="font-weight: 600; color: #333; margin-bottom: 3px;">
                                                <?php echo ($index + 1) . '. ' . safe_output($need_item['victim_name'] ?? 'Unknown Victim'); ?>
                                            </div>
                                            <div style="font-size: 0.8rem; color: #666;">
                                                Needs: <?php echo $need_item['quantity_needed'] ?? 0; ?> 
                                                <?php echo safe_output($need_item['resource_unit'] ?? 'units'); ?> 
                                                of <?php echo safe_output($need_item['resource_name'] ?? 'Unknown Resource'); ?>
                                            </div>
                                        </div>
                                        <div style="text-align: right;">
                                            <div style="font-size: 0.8rem; color: #666;">
                                                Priority: <?php echo safe_output($need_item['priority_level'] ?? 'Medium'); ?>
                                            </div>
                                            <div style="font-size: 0.8rem; color: #666;">
                                                Status: <span class="timeline-badge badge-<?php echo strtolower($need_item['status'] ?? 'pending'); ?>">
                                                    <?php echo safe_output($need_item['status'] ?? 'Pending'); ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- Action Buttons -->
                        <div class="action-buttons-grid">
                            <a href="view_distribution.php?id=<?php echo $distribution_id; ?>" class="btn btn-primary">
                                👁️ View Details
                            </a>
                            <a href="assign_volunteer.php?distribution_id=<?php echo $distribution_id; ?>" class="btn btn-success">
                                👥 Assign
                            </a>
                            <button class="btn btn-outline" onclick="window.print()">
                                🖨️ Print
                            </button>
                            <a href="distribution_main.php" class="btn btn-outline">
                                📊 Dashboard
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Progress Tracker -->
                <div class="card">
                    <div class="card-header">
                        📈 Progress Tracker
                    </div>
                    <div class="card-body">
                        <div class="progress-tracker">
                            <div class="progress-bar-wrapper">
                                <div class="progress-fill" id="progressFill" style="width: <?php echo $progress_percentage; ?>%;"></div>
                            </div>
                            <div class="progress-steps">
                                <div class="progress-step <?php echo $current_status_mapped === 'planned' ? 'active' : ($progress_percentage >= 25 ? 'completed' : ''); ?>" data-status="planned">
                                    <div class="step-circle">1</div>
                                    <div class="step-label">Planned</div>
                                </div>
                                <div class="progress-step <?php echo $current_status_mapped === 'assigned' ? 'active' : ($progress_percentage >= 50 ? 'completed' : ''); ?>" data-status="assigned">
                                    <div class="step-circle">2</div>
                                    <div class="step-label">Assigned</div>
                                </div>
                                <div class="progress-step <?php echo $current_status_mapped === 'active' ? 'active' : ($progress_percentage >= 75 ? 'completed' : ''); ?>" data-status="active">
                                    <div class="step-circle">3</div>
                                    <div class="step-label">Active</div>
                                </div>
                                <div class="progress-step <?php echo $current_status_mapped === 'completed' ? 'active' : ($progress_percentage >= 100 ? 'completed' : ''); ?>" data-status="completed">
                                    <div class="step-circle">✓</div>
                                    <div class="step-label">Completed</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </aside>

            <!-- Main Content -->
            <main class="main-content">
                <!-- Update Status Form -->
                <div class="update-form-section">
                    <div class="card">
                        <div class="card-header">
                            🔄 Update Distribution Status
                        </div>
                        <div class="card-body">
                            <form id="updateForm" method="POST">
                                <input type="hidden" name="update_status" value="1">
                                
                                <div class="form-group">
                                    <label class="form-label">
                                        New Status <span class="required-star">*</span>
                                    </label>
                                    <div class="form-control-icon">
                                        <span class="input-icon">🏷️</span>
                                        <select class="form-control" id="statusSelect" name="status" required>
                                            <option value="">Select new status...</option>
                                            <option value="Planned" <?php echo ($current_status === 'Planned') ? 'selected' : ''; ?>>📋 Planned</option>
                                            <option value="Assigned" <?php echo ($current_status === 'Assigned') ? 'selected' : ''; ?>>👥 Assigned</option>
                                            <option value="In Transit" <?php echo ($current_status === 'In Transit') ? 'selected' : ''; ?>>🚚 In Transit</option>
                                            <option value="Delivered" <?php echo ($current_status === 'Delivered') ? 'selected' : ''; ?>>📦 Delivered</option>
                                            <option value="Completed" <?php echo ($current_status === 'Completed') ? 'selected' : ''; ?>>✅ Completed</option>
                                            <option value="Cancelled" <?php echo ($current_status === 'Cancelled') ? 'selected' : ''; ?>>❌ Cancelled</option>
                                        </select>
                                    </div>
                                    <small class="form-text">Select the new status for this distribution</small>
                                </div>

                                <div class="form-group" id="quantityGroup" style="display: <?php echo ($current_status === 'Completed') ? 'block' : 'none'; ?>;">
                                    <label class="form-label">
                                        Quantity Received <span class="required-star">*</span>
                                    </label>
                                    <div class="form-control-icon">
                                        <span class="input-icon">📦</span>
                                        <input type="number" class="form-control" id="quantityInput" name="quantity_received"
                                               placeholder="Enter quantity received" min="0" max="<?php echo $basic_distribution['quantity_sent'] ?? 0; ?>"
                                               value="<?php echo $distribution['quantity_received'] ?? ($basic_distribution['quantity_sent'] ?? 0); ?>">
                                    </div>
                                    <small class="form-text">
                                        Actual quantity received by victim (max: <?php echo $basic_distribution['quantity_sent'] ?? 0; ?> 
                                        <?php echo !empty($needs) ? safe_output($needs[0]['resource_unit'] ?? 'units') : 'units'; ?>)
                                    </small>
                                </div>

                                <div class="form-group">
                                    <label class="form-label">Status Notes</label>
                                    <textarea class="form-control" id="statusNotes" name="status_notes" rows="4" 
                                              placeholder="Add any notes about this status update..."><?php echo safe_output($_POST['status_notes'] ?? ''); ?></textarea>
                                    <small class="form-text">Optional: Add details or comments about the status change</small>
                                </div>

                                <button type="submit" class="btn btn-update">
                                    <span id="btnText">🔄 Update Status</span>
                                </button>
                            </form>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            📚 Status Reference Guide
                        </div>
                        <div class="card-body">
                            <div class="status-guide">
                                <div class="status-guide-title">Status Meanings</div>
                                <div class="status-guide-item">
                                    <div class="status-guide-badge status-planned">Planned</div>
                                    <div class="status-guide-text">Distribution created, resources planned</div>
                                </div>
                                <div class="status-guide-item">
                                    <div class="status-guide-badge status-assigned">Assigned</div>
                                    <div class="status-guide-text">Volunteers assigned to distribute</div>
                                </div>
                                <div class="status-guide-item">
                                    <div class="status-guide-badge status-active">In Transit/Delivered</div>
                                    <div class="status-guide-text">Distribution is in progress or delivered</div>
                                </div>
                                <div class="status-guide-item">
                                    <div class="status-guide-badge status-completed">Completed</div>
                                    <div class="status-guide-text">Distribution finished successfully</div>
                                </div>
                                <div class="status-guide-item">
                                    <div class="status-guide-badge status-cancelled">Cancelled</div>
                                    <div class="status-guide-text">Distribution has been cancelled</div>
                                </div>
                            </div>

                            <div style="margin-top: 20px; padding: 15px; background: rgba(59, 130, 246, 0.1); border-radius: 8px; border-left: 3px solid var(--info);">
                                <div style="display: flex; align-items: start; gap: 10px;">
                                    <span style="font-size: 1.2rem;">💡</span>
                                    <div>
                                        <div style="font-weight: 600; color: var(--info); margin-bottom: 5px;">Pro Tip</div>
                                        <div style="font-size: 0.85rem; color: var(--gray-600);">
                                            Make sure to add detailed notes when updating status. This helps maintain clear communication with your team.
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Assigned Volunteers -->
                <div class="card">
                    <div class="card-header">
                        👥 Assigned Volunteers (<?php echo count($assigned_volunteers); ?>)
                    </div>
                    <div class="card-body">
                        <?php if (count($assigned_volunteers) > 0): ?>
                            <table class="volunteers-table">
                                <thead>
                                    <tr>
                                        <th>Volunteer</th>
                                        <th>Role</th>
                                        <th>Assignment Status</th>
                                        <th>Assigned On</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($assigned_volunteers as $volunteer): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo safe_output($volunteer['volunteer_name']); ?></strong>
                                        </td>
                                        <td><?php echo safe_output($volunteer['volunteer_role']); ?></td>
                                        <td>
                                            <?php 
                                            $status_text = $volunteer['status'] ?? 'Active';
                                            $status_class = strtolower($status_text);
                                            ?>
                                            <span class="timeline-badge badge-<?php echo $status_class; ?>">
                                                <?php echo $status_text; ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('M j, Y g:i A', strtotime($volunteer['assigned_timestamp'] ?? 'now')); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <div class="empty-state">
                                <div class="empty-icon">👥</div>
                                <h3>No Volunteers Assigned</h3>
                                <p>No volunteers are currently assigned to this distribution. Assign volunteers to proceed with the distribution.</p>
                                <a href="assign_volunteer.php?distribution_id=<?php echo $distribution_id; ?>" class="btn btn-success">
                                    👥 Assign Volunteers
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Status History Timeline -->
                <div class="card">
                    <div class="card-header">
                        📋 Status History
                    </div>
                    <div class="card-body">
                        <div class="timeline-wrapper">
                            <div class="timeline">
                                <?php
                                // Define statuses in order
                                $statuses_in_order = ['Planned', 'Assigned', 'In Transit', 'Delivered', 'Completed'];
                                $current_status_index = array_search($current_status, $statuses_in_order);
                                if ($current_status_index === false) {
                                    $current_status_index = 0;
                                }
                                
                                foreach ($statuses_in_order as $index => $status):
                                    $is_completed = $index < $current_status_index;
                                    $is_active = $index === $current_status_index;
                                ?>
                                <div class="timeline-item <?php echo $is_completed ? 'completed' : ($is_active ? 'active' : ''); ?>">
                                    <div class="timeline-marker"></div>
                                    <div class="timeline-content">
                                        <span class="timeline-status"><?php echo $status; ?></span>
                                        <span class="timeline-badge <?php echo $is_completed ? 'badge-completed' : ($is_active ? 'badge-active' : 'badge-pending'); ?>">
                                            <?php 
                                            if ($is_completed) echo '✓ Completed';
                                            elseif ($is_active) echo '⏳ In Progress';
                                            else echo '○ Pending';
                                            ?>
                                        </span>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
        // Status Select Handler
        const statusSelect = document.getElementById('statusSelect');
        const quantityGroup = document.getElementById('quantityGroup');
        const quantityInput = document.getElementById('quantityInput');
        const progressFill = document.getElementById('progressFill');
        const progressSteps = document.querySelectorAll('.progress-step');
        const updateForm = document.getElementById('updateForm');
        const btnText = document.getElementById('btnText');

        // Get quantities from PHP
        const maxQuantitySent = <?php echo $basic_distribution['quantity_sent'] ?? 0; ?>;
        const totalNeeded = <?php echo $total_quantity_needed; ?>;

        statusSelect.addEventListener('change', function() {
            const status = this.value;

            // Show/hide quantity field for completed status
            if (status === 'Completed') {
                quantityGroup.style.display = 'block';
                quantityInput.required = true;
            } else {
                quantityGroup.style.display = 'none';
                quantityInput.required = false;
            }

            // Update progress bar and steps
            updateProgress(status);

            // Show notification preview
            if (status) {
                showNotification('Status Preview', getStatusDescription(status), 'info');
            }
        });

        function updateProgress(status) {
            // Reset all steps
            progressSteps.forEach(step => {
                step.classList.remove('active', 'completed');
            });

            const statusMap = {
                'Planned': { width: '25%', activeIndex: 0 },
                'Assigned': { width: '50%', activeIndex: 1 },
                'In Transit': { width: '75%', activeIndex: 2 },
                'Delivered': { width: '75%', activeIndex: 2 },
                'Completed': { width: '100%', activeIndex: 3 }
            };

            if (statusMap[status]) {
                const { width, activeIndex } = statusMap[status];
                progressFill.style.width = width;

                // Mark completed steps
                for (let i = 0; i < activeIndex; i++) {
                    progressSteps[i].classList.add('completed');
                }

                // Mark active step
                if (progressSteps[activeIndex]) {
                    progressSteps[activeIndex].classList.add('active');
                }
            }
        }

        function getStatusDescription(status) {
            const descriptions = {
                'Planned': 'Distribution is being planned and organized',
                'Assigned': 'Volunteers have been assigned to this distribution',
                'In Transit': 'Distribution is currently in transit',
                'Delivered': 'Distribution has been delivered',
                'Completed': 'Distribution has been successfully completed',
                'Cancelled': 'Distribution has been cancelled'
            };
            return descriptions[status] || '';
        }

        // Form Submission
        updateForm.addEventListener('submit', function(e) {
            const status = statusSelect.value;
            const quantity = parseInt(quantityInput.value) || 0;

            if (!status) {
                e.preventDefault();
                showNotification('Error', 'Please select a new status', 'error');
                return;
            }

            if (status === 'Completed' && !quantityInput.value) {
                e.preventDefault();
                showNotification('Error', 'Please enter the quantity received', 'error');
                return;
            }

            if (status === 'Completed' && quantity > maxQuantitySent) {
                e.preventDefault();
                showNotification('Error', `Quantity received (${quantity}) cannot exceed quantity sent (${maxQuantitySent})`, 'error');
                return;
            }

            // Add warning if quantity is less than total needed
            if (status === 'Completed' && quantity < totalNeeded) {
                if (!confirm(`Warning: Quantity received (${quantity}) is less than total needed (${totalNeeded}). Some victims may not receive enough. Are you sure you want to continue?`)) {
                    e.preventDefault();
                    return;
                }
            }

            // Show loading state
            const originalText = btnText.innerHTML;
            btnText.innerHTML = '<span class="loading-spinner"></span> Updating...';
            document.querySelector('.btn-update').disabled = true;
        });

        // Notification System
        function showNotification(title, message, type = 'info') {
            const notification = document.createElement('div');
            notification.className = `notification ${type}`;

            const icons = {
                success: '✅',
                error: '❌',
                warning: '⚠️',
                info: 'ℹ️'
            };

            notification.innerHTML = `
                <div class="notification-icon">${icons[type]}</div>
                <div class="notification-content">
                    <div class="notification-title">${title}</div>
                    <div class="notification-message">${message}</div>
                </div>
            `;

            document.body.appendChild(notification);

            setTimeout(() => {
                notification.style.animation = 'slideInRight 0.3s ease reverse';
                setTimeout(() => notification.remove(), 300);
            }, 3000);
        }

        // Real-time validation for quantity
        quantityInput.addEventListener('input', function() {
            const value = parseInt(this.value) || 0;

            if (value > maxQuantitySent) {
                this.style.borderColor = 'var(--danger)';
                showNotification('Warning', `Quantity cannot exceed ${maxQuantitySent}`, 'warning');
            } else if (value < totalNeeded) {
                this.style.borderColor = 'var(--warning)';
                showNotification('Notice', `Quantity is less than total needed (${totalNeeded})`, 'warning');
            } else {
                this.style.borderColor = 'var(--success)';
            }
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl/Cmd + S to save
            if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                e.preventDefault();
                updateForm.dispatchEvent(new Event('submit'));
            }

            // Escape to go back
            if (e.key === 'Escape') {
                window.location.href = 'distribution_main.php';
            }
        });

        // Initialize on load
        document.addEventListener('DOMContentLoaded', function() {
            // Focus on status select
            statusSelect.focus();

            // Show welcome notification
            setTimeout(() => {
                showNotification('Ready to Update', 'Select a new status to begin updating this distribution', 'info');
            }, 500);

            // Initialize progress based on current status
            updateProgress('<?php echo $current_status; ?>');
        });
    </script>
</body>
</html>