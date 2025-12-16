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

// Get needs for this distribution
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
    LIMIT 1
";

$needs_stmt = $db->prepare($needs_query);
$needs_stmt->bind_param("i", $distribution_id);
$needs_stmt->execute();
$needs_result = $needs_stmt->get_result();
$need = $needs_result->fetch_assoc();
$needs_stmt->close();

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
        if ($new_status === 'Completed' && !empty($need['resource_id'])) {
            // Update resource quantity
            $resource_query = "
                UPDATE resource 
                SET quantity_available = quantity_available - ? 
                WHERE resource_id = ?
            ";
            $resource_stmt = $db->prepare($resource_query);
            $resource_stmt->bind_param("ii", $quantity_received, $need['resource_id']);
            
            if (!$resource_stmt->execute()) {
                throw new Exception("Error updating resource inventory: " . $resource_stmt->error);
            }
            $resource_stmt->close();

            // Update needs status if exists
            $needs_query = "
                UPDATE needs 
                SET status = 'fulfilled' 
                WHERE distribution_id = ?
            ";
            $needs_stmt = $db->prepare($needs_query);
            $needs_stmt->bind_param("i", $distribution_id);
            $needs_stmt->execute();
            $needs_stmt->close();
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Status - Distribution #<?php echo $distribution_id; ?></title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        /* Status badges - Match view_distribution.php */
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
            color: white;
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
        }
        
        .status-Planned {
            background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%);
        }
        
        .status-Assigned {
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
        }
        
        .status-Active {
            background: linear-gradient(135deg, #2ecc71 0%, #27ae60 100%);
        }
        
        .status-Completed {
            background: linear-gradient(135deg, #9b59b6 0%, #8e44ad 100%);
        }
        
        .status-Cancelled {
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
        }
        
        /* Progress bar */
        .progress-container {
            width: 100%;
            background: #e9ecef;
            border-radius: 10px;
            margin: 10px 0;
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
        
        .progress-Planned { 
            background: linear-gradient(90deg, #f39c12, #e67e22); 
            width: 25%; 
        }
        .progress-Assigned { 
            background: linear-gradient(90deg, #3498db, #2980b9); 
            width: 50%; 
        }
        .progress-Active { 
            background: linear-gradient(90deg, #2ecc71, #27ae60); 
            width: 75%; 
        }
        .progress-Completed { 
            background: linear-gradient(90deg, #9b59b6, #8e44ad); 
            width: 100%; 
        }
        .progress-Cancelled { 
            background: linear-gradient(90deg, #e74c3c, #c0392b); 
            width: 100%; 
        }
        
        .progress-labels {
            display: flex;
            justify-content: space-between;
            margin-top: 10px;
            font-size: 0.85rem;
        }
        
        .progress-label {
            color: #6c757d;
            position: relative;
            padding: 0 5px;
        }
        
        .progress-label.active {
            color: #2c3e50;
            font-weight: bold;
        }
        
        .progress-label.active::after {
            content: '↓';
            position: absolute;
            bottom: -15px;
            left: 50%;
            transform: translateX(-50%);
            color: #3498db;
            font-size: 1.2rem;
        }
        
        /* Status history */
        .status-history {
            position: relative;
            padding-left: 30px;
        }
        
        .history-item {
            position: relative;
            margin-bottom: 25px;
        }
        
        .history-item:last-child {
            margin-bottom: 0;
        }
        
        .history-marker {
            position: absolute;
            left: -30px;
            top: 2px;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: #e9ecef;
            border: 3px solid white;
            z-index: 2;
        }
        
        .history-item.completed .history-marker {
            background: #28a745;
        }
        
        .history-item.current .history-marker {
            background: #3498db;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.2);
        }
        
        .history-content {
            background: white;
            padding: 10px 15px;
            border-radius: 8px;
            border-left: 4px solid #dee2e6;
        }
        
        .history-item.completed .history-content {
            border-left-color: #28a745;
        }
        
        .history-item.current .history-content {
            border-left-color: #3498db;
        }
        
        .history-status {
            font-weight: 600;
            color: #2c3e50;
        }
        
        .history-date {
            font-size: 0.85rem;
            color: #6c757d;
            margin-left: 10px;
        }
        
        /* Debug info styling */
        .debug-panel {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            padding: 15px;
            margin: 10px 0;
            border-radius: 5px;
            font-family: monospace;
            font-size: 12px;
        }
        .debug-panel h4 {
            margin-top: 0;
            color: #dc3545;
        }
        .data-row {
            margin: 5px 0;
            padding: 3px;
            border-bottom: 1px dotted #ddd;
        }
        .data-label {
            font-weight: bold;
            color: #495057;
            display: inline-block;
            width: 180px;
        }
        
        /* Form styling */
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #2c3e50;
        }
        
        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            font-size: 1rem;
        }
        
        .form-text {
            display: block;
            margin-top: 5px;
            font-size: 0.85rem;
            color: #6c757d;
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
        </div>
        <?php endif; ?>

        <!-- Header Section -->
        <div class="header">
            <h1>📝 Update Distribution Status</h1>
            <p>Distribution #<?php echo $distribution_id; ?> 
                - <?php echo safe_output($need['victim_name'] ?? $distribution['Disaster_Name'] ?? 'Unknown'); ?></p>
        </div>

        <!-- Alert Messages -->
        <?php if (!empty($success)): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <div class="grid-2">
            <!-- Current Status & Details -->
            <div class="card-3d">
                <h2>📊 Current Status</h2>
                <div class="status-overview">
                    <div class="current-status">
                        <label>Current Status:</label>
                        <span class="status-badge status-<?php echo safe_output($distribution['status'] ?? 'Planned'); ?> large">
                            <?php echo safe_output($distribution['status'] ?? 'Planned'); ?>
                        </span>
                    </div>
                    
                    <div class="distribution-info">
                        <div class="info-row">
                            <label>Distribution ID:</label>
                            <span>#<?php echo $distribution_id; ?></span>
                        </div>
                        <div class="info-row">
                            <label>Victim:</label>
                            <span><?php echo safe_output($need['victim_name'] ?? 'No victims assigned'); ?></span>
                        </div>
                        <div class="info-row">
                            <label>Disaster:</label>
                            <span><?php echo safe_output($distribution['Disaster_Name'] ?? 'Not linked'); ?></span>
                            <?php if (!empty($distribution['disaster_id'])): ?>
                            <small style="color: #666;">(ID: <?php echo $distribution['disaster_id']; ?>)</small>
                            <?php endif; ?>
                        </div>
                        <div class="info-row">
                            <label>Resource:</label>
                            <span><?php echo safe_output($need['resource_name'] ?? 'No resources specified'); ?></span>
                        </div>
                        <div class="info-row">
                            <label>Quantity Sent:</label>
                            <span>
                                <?php echo safe_output($basic_distribution['quantity_sent'] ?? '0'); ?> 
                                <?php echo safe_output($need['resource_unit'] ?? 'units'); ?>
                            </span>
                        </div>
                        <?php if (!empty($distribution['quantity_received'])): ?>
                        <div class="info-row">
                            <label>Quantity Received:</label>
                            <span>
                                <?php echo $distribution['quantity_received']; ?> 
                                <?php echo safe_output($need['resource_unit'] ?? 'units'); ?>
                            </span>
                        </div>
                        <?php endif; ?>
                        <div class="info-row">
                            <label>Distribution Date:</label>
                            <span><?php echo date('F j, Y', strtotime($distribution['date'] ?? $basic_distribution['date'] ?? 'now')); ?></span>
                        </div>
                    </div>
                </div>

                <!-- Progress Tracking - Updated to match view_distribution.php -->
                <div class="progress-section">
                    <h3>Distribution Progress</h3>
                    <div class="progress-container large">
                        <div class="progress-bar progress-<?php echo safe_output($distribution['status'] ?? 'Planned'); ?>"></div>
                    </div>
                    <div class="progress-labels">
                        <span class="progress-label <?php echo ($distribution['status'] ?? 'Planned') == 'Planned' ? 'active' : ''; ?>">Planned</span>
                        <span class="progress-label <?php echo ($distribution['status'] ?? 'Planned') == 'Assigned' ? 'active' : ''; ?>">Assigned</span>
                        <span class="progress-label <?php echo ($distribution['status'] ?? 'Planned') == 'Active' ? 'active' : ''; ?>">Active</span>
                        <span class="progress-label <?php echo ($distribution['status'] ?? 'Planned') == 'Completed' ? 'active' : ''; ?>">Completed</span>
                    </div>
                </div>

                <div class="action-buttons">
                    <a href="view_distribution.php?id=<?php echo $distribution_id; ?>" class="btn btn-primary">
                        👁️ View Details
                    </a>
                    <a href="assign_volunteer.php?id=<?php echo $distribution_id; ?>" class="btn btn-success">
                        👥 Assign Volunteers
                    </a>
                    <a href="distribution_main.php" class="btn btn-secondary">
                        📊 Dashboard
                    </a>
                </div>
            </div>

            <!-- Update Status Form -->
            <div class="card-3d">
                <h2>🔄 Update Status</h2>
                <form method="POST" id="status-form">
                    <input type="hidden" name="update_status" value="1">
                    
                    <div class="form-group">
                        <label class="form-label">New Status *</label>
                        <select class="form-control" name="status" id="status-select" required>
                            <option value="">Select new status...</option>
                            <option value="Planned" <?php echo ($distribution['status'] ?? '') == 'Planned' ? 'selected' : ''; ?>>📋 Planned</option>
                            <option value="Assigned" <?php echo ($distribution['status'] ?? '') == 'Assigned' ? 'selected' : ''; ?>>👥 Assigned</option>
                            <option value="Active" <?php echo ($distribution['status'] ?? '') == 'Active' ? 'selected' : ''; ?>>🚚 Active (In Progress)</option>
                            <option value="Completed" <?php echo ($distribution['status'] ?? '') == 'Completed' ? 'selected' : ''; ?>>✅ Completed</option>
                            <option value="Cancelled" <?php echo ($distribution['status'] ?? '') == 'Cancelled' ? 'selected' : ''; ?>>❌ Cancelled</option>
                        </select>
                    </div>

                    <div class="form-group" id="quantity-received-group" style="display: <?php echo ($distribution['status'] ?? '') == 'Completed' ? 'block' : 'none'; ?>;">
                        <label class="form-label">Quantity Received *</label>
                        <input type="number" class="form-control" name="quantity_received" 
                               id="quantity_received" min="0" max="<?php echo $basic_distribution['quantity_sent'] ?? 0; ?>"
                               value="<?php echo $distribution['quantity_received'] ?? ($basic_distribution['quantity_sent'] ?? 0); ?>">
                        <small class="form-text">Actual quantity received by victim (max: <?php echo $basic_distribution['quantity_sent'] ?? 0; ?> <?php echo safe_output($need['resource_unit'] ?? 'units'); ?>)</small>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Status Notes</label>
                        <textarea class="form-control" name="status_notes" rows="3" 
                                  placeholder="Add any notes about this status update..."><?php echo safe_output($_POST['status_notes'] ?? ''); ?></textarea>
                    </div>

                    <!-- Status Descriptions - Updated to match new statuses -->
                    <div class="status-descriptions">
                        <h4>Status Meanings:</h4>
                        <div class="status-desc-item">
                            <span class="status-badge status-Planned">Planned</span>
                            <span>Distribution created, resources planned</span>
                        </div>
                        <div class="status-desc-item">
                            <span class="status-badge status-Assigned">Assigned</span>
                            <span>Volunteers assigned to distribute</span>
                        </div>
                        <div class="status-desc-item">
                            <span class="status-badge status-Active">Active</span>
                            <span>Distribution is in progress</span>
                        </div>
                        <div class="status-desc-item">
                            <span class="status-badge status-Completed">Completed</span>
                            <span>Distribution finished successfully</span>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-warning btn-update" style="width: 100%; padding: 12px; font-size: 1.1rem;">
                        🔄 Update Status
                    </button>
                </form>
            </div>
        </div>

        <!-- Assigned Volunteers -->
        <div class="card-3d">
            <h2>👥 Assigned Volunteers</h2>
            <?php if (count($assigned_volunteers) > 0): ?>
                <div class="table-container">
                    <table class="data-table">
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
                                    <span class="status-badge status-<?php echo strtolower(safe_output($volunteer['status'] ?? 'unknown')); ?>">
                                        <?php echo safe_output($volunteer['status'] ?? 'Unknown'); ?>
                                    </span>
                                </td>
                                <td><?php echo date('M j, Y g:i A', strtotime($volunteer['assigned_timestamp'] ?? 'now')); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-icon">👥</div>
                    <h3>No Volunteers Assigned</h3>
                    <p>No volunteers are currently assigned to this distribution.</p>
                    <?php if (($distribution['status'] ?? '') != 'Completed'): ?>
                        <a href="assign_volunteer.php?id=<?php echo $distribution_id; ?>" class="btn btn-success">
                            Assign Volunteers
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Status History - Updated to match new statuses -->
        <div class="card-3d">
            <h2>📋 Status History</h2>
            <div class="status-history">
                <?php
                $statuses = ['Planned', 'Assigned', 'Active', 'Completed'];
                $current_status = $distribution['status'] ?? 'Planned';
                $current_index = array_search($current_status, $statuses);
                
                foreach ($statuses as $index => $status): 
                    $is_completed = $index < $current_index;
                    $is_current = $status === $current_status;
                ?>
                <div class="history-item <?php echo $is_completed ? 'completed' : ($is_current ? 'current' : ''); ?>">
                    <div class="history-marker"></div>
                    <div class="history-content">
                        <span class="history-status"><?php echo $status; ?></span>
                        <?php if ($is_completed): ?>
                            <span class="history-date">Completed</span>
                        <?php elseif ($is_current): ?>
                            <span class="history-date">Current Status</span>
                        <?php else: ?>
                            <span class="history-date">Pending</span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const statusSelect = document.getElementById('status-select');
            const quantityGroup = document.getElementById('quantity-received-group');
            const quantityInput = document.getElementById('quantity_received');
            const statusForm = document.getElementById('status-form');

            function toggleQuantityField() {
                // Only show quantity field for Completed status
                if (statusSelect.value === 'Completed') {
                    quantityGroup.style.display = 'block';
                    quantityInput.required = true;
                } else {
                    quantityGroup.style.display = 'none';
                    quantityInput.required = false;
                }
            }

            // Initial check
            toggleQuantityField();

            // Update on status change
            statusSelect.addEventListener('change', toggleQuantityField);

            // Form validation
            statusForm.addEventListener('submit', function(e) {
                const status = statusSelect.value;
                
                if (!status) {
                    e.preventDefault();
                    alert('Please select a new status.');
                    statusSelect.focus();
                    return false;
                }

                if (status === 'Completed' && !quantityInput.value) {
                    e.preventDefault();
                    alert('Please enter the quantity received.');
                    quantityInput.focus();
                    return false;
                }

                // Show loading state
                const submitBtn = this.querySelector('.btn-update');
                const originalText = submitBtn.innerHTML;
                submitBtn.innerHTML = '⏳ Updating...';
                submitBtn.disabled = true;

                return true;
            });

            // Auto-focus status select
            statusSelect.focus();
        });
    </script>
</body>
</html>