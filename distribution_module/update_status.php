<?php
require_once 'config.php';

$database = new Database();
$db = $database->getConnection();

$distribution_id = $_GET['id'] ?? null;

if (!$distribution_id) {
    header("Location: index.php");
    exit;
}

// Get distribution details
$query = "
    SELECT 
        d.*,
        v.name as victim_name,
        r.name as resource_name,
        r.unit as resource_unit,
        dis.Disaster_Name
    FROM distribution d
    LEFT JOIN victim v ON d.victim_id = v.victim_id
    LEFT JOIN resource r ON d.resource_id = r.resource_id
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
if ($_POST && isset($_POST['update_status'])) {
    $new_status = $_POST['status'];
    $quantity_received = $_POST['quantity_received'] ?? $distribution['quantity_sent'];
    $status_notes = $_POST['status_notes'] ?? '';
    
    try {
        // Start transaction
        $db->begin_transaction();

        // Update distribution status
        $update_query = "
            UPDATE distribution 
            SET status = ?, quantity_received = ?, comments = CONCAT(IFNULL(comments, ''), '\n\nStatus Update: ', ?, ' - ', NOW())
            WHERE distribution_id = ?
        ";
        $update_stmt = $db->prepare($update_query);
        $update_stmt->bind_param("sisi", $new_status, $quantity_received, $status_notes, $distribution_id);
        
        if (!$update_stmt->execute()) {
            throw new Exception("Error updating distribution status: " . $update_stmt->error);
        }
        $update_stmt->close();

        // If status is completed, update resource inventory
        if ($new_status === 'completed' && $distribution['resource_id']) {
            // Update resource quantity
            $resource_query = "
                UPDATE resource 
                SET quantity_available = quantity_available - ? 
                WHERE resource_id = ?
            ";
            $resource_stmt = $db->prepare($resource_query);
            $resource_stmt->bind_param("ii", $quantity_received, $distribution['resource_id']);
            
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
        
        $success = "✅ Distribution status updated successfully to: " . ucfirst($new_status);
        
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Status - Distribution #<?php echo $distribution_id; ?></title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="container">
        <!-- Header Section -->
        <div class="header">
            <h1>📝 Update Distribution Status</h1>
            <p>Distribution #<?php echo $distribution_id; ?> - <?php echo htmlspecialchars($distribution['victim_name']); ?></p>
        </div>

        <!-- Alert Messages -->
        <?php if (isset($success)): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <div class="grid-2">
            <!-- Current Status & Details -->
            <div class="card-3d">
                <h2>📊 Current Status</h2>
                <div class="status-overview">
                    <div class="current-status">
                        <label>Current Status:</label>
                        <span class="status-badge status-<?php echo $distribution['status']; ?> large">
                            <?php echo ucfirst($distribution['status']); ?>
                        </span>
                    </div>
                    
                    <div class="distribution-info">
                        <div class="info-row">
                            <label>Distribution ID:</label>
                            <span>#<?php echo $distribution['distribution_id']; ?></span>
                        </div>
                        <div class="info-row">
                            <label>Victim:</label>
                            <span><?php echo htmlspecialchars($distribution['victim_name']); ?></span>
                        </div>
                        <div class="info-row">
                            <label>Disaster:</label>
                            <span><?php echo htmlspecialchars($distribution['Disaster_Name']); ?></span>
                        </div>
                        <div class="info-row">
                            <label>Resource:</label>
                            <span><?php echo htmlspecialchars($distribution['resource_name']); ?></span>
                        </div>
                        <div class="info-row">
                            <label>Quantity Sent:</label>
                            <span><?php echo $distribution['quantity_sent']; ?> <?php echo htmlspecialchars($distribution['resource_unit']); ?></span>
                        </div>
                        <?php if ($distribution['quantity_received']): ?>
                        <div class="info-row">
                            <label>Quantity Received:</label>
                            <span><?php echo $distribution['quantity_received']; ?> <?php echo htmlspecialchars($distribution['resource_unit']); ?></span>
                        </div>
                        <?php endif; ?>
                        <div class="info-row">
                            <label>Delivery Date:</label>
                            <span><?php echo date('F j, Y', strtotime($distribution['date'])); ?></span>
                        </div>
                    </div>
                </div>

                <!-- Progress Tracking -->
                <div class="progress-section">
                    <h3>Distribution Progress</h3>
                    <div class="progress-container large">
                        <div class="progress-bar progress-<?php echo $distribution['status']; ?>"></div>
                    </div>
                    <div class="progress-labels">
                        <span class="progress-label <?php echo $distribution['status'] == 'pending' ? 'active' : ''; ?>">Pending</span>
                        <span class="progress-label <?php echo $distribution['status'] == 'assigned' ? 'active' : ''; ?>">Assigned</span>
                        <span class="progress-label <?php echo $distribution['status'] == 'in-transit' ? 'active' : ''; ?>">In Transit</span>
                        <span class="progress-label <?php echo $distribution['status'] == 'delivered' ? 'active' : ''; ?>">Delivered</span>
                        <span class="progress-label <?php echo $distribution['status'] == 'completed' ? 'active' : ''; ?>">Completed</span>
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
                            <option value="pending" <?php echo $distribution['status'] == 'pending' ? 'selected' : ''; ?>>⏳ Pending</option>
                            <option value="assigned" <?php echo $distribution['status'] == 'assigned' ? 'selected' : ''; ?>>👥 Assigned</option>
                            <option value="in-transit" <?php echo $distribution['status'] == 'in-transit' ? 'selected' : ''; ?>>🚚 In Transit</option>
                            <option value="delivered" <?php echo $distribution['status'] == 'delivered' ? 'selected' : ''; ?>>📦 Delivered</option>
                            <option value="completed" <?php echo $distribution['status'] == 'completed' ? 'selected' : ''; ?>>✅ Completed</option>
                        </select>
                    </div>

                    <div class="form-group" id="quantity-received-group" style="display: none;">
                        <label class="form-label">Quantity Received *</label>
                        <input type="number" class="form-control" name="quantity_received" 
                               id="quantity_received" min="0" max="<?php echo $distribution['quantity_sent']; ?>"
                               value="<?php echo $distribution['quantity_received'] ?? $distribution['quantity_sent']; ?>">
                        <small class="form-text">Actual quantity received by victim (max: <?php echo $distribution['quantity_sent']; ?> <?php echo htmlspecialchars($distribution['resource_unit']); ?>)</small>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Status Notes</label>
                        <textarea class="form-control" name="status_notes" rows="3" 
                                  placeholder="Add any notes about this status update..."></textarea>
                    </div>

                    <!-- Status Descriptions -->
                    <div class="status-descriptions">
                        <h4>Status Meanings:</h4>
                        <div class="status-desc-item">
                            <span class="status-badge status-pending">Pending</span>
                            <span>Distribution created, waiting for action</span>
                        </div>
                        <div class="status-desc-item">
                            <span class="status-badge status-assigned">Assigned</span>
                            <span>Volunteers assigned to deliver</span>
                        </div>
                        <div class="status-desc-item">
                            <span class="status-badge status-in-transit">In Transit</span>
                            <span>Volunteer on the way to victim</span>
                        </div>
                        <div class="status-desc-item">
                            <span class="status-badge status-delivered">Delivered</span>
                            <span>Victim confirmed receipt</span>
                        </div>
                        <div class="status-desc-item">
                            <span class="status-badge status-completed">Completed</span>
                            <span>Inventory updated, distribution fulfilled</span>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-warning btn-update">
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
                                    <strong><?php echo htmlspecialchars($volunteer['volunteer_name']); ?></strong>
                                </td>
                                <td><?php echo htmlspecialchars($volunteer['role']); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo strtolower($volunteer['status']); ?>">
                                        <?php echo $volunteer['status']; ?>
                                    </span>
                                </td>
                                <td><?php echo date('M j, Y g:i A', strtotime($volunteer['assigned_timestamp'])); ?></td>
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
                    <a href="assign_volunteer.php?id=<?php echo $distribution_id; ?>" class="btn btn-success">
                        Assign Volunteers
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <!-- Status History -->
        <div class="card-3d">
            <h2>📋 Status History</h2>
            <div class="status-history">
                <div class="history-item <?php echo $distribution['status'] == 'pending' ? 'current' : 'completed'; ?>">
                    <div class="history-marker"></div>
                    <div class="history-content">
                        <span class="history-status">Pending</span>
                        <span class="history-date">Distribution Created</span>
                    </div>
                </div>
                
                <div class="history-item <?php echo $distribution['status'] == 'assigned' ? 'current' : ($distribution['status'] == 'pending' ? 'pending' : 'completed'); ?>">
                    <div class="history-marker"></div>
                    <div class="history-content">
                        <span class="history-status">Assigned</span>
                        <span class="history-date">
                            <?php echo count($assigned_volunteers) > 0 ? date('M j, Y', strtotime($assigned_volunteers[0]['assigned_timestamp'])) : 'Not yet'; ?>
                        </span>
                    </div>
                </div>
                
                <div class="history-item <?php echo $distribution['status'] == 'in-transit' ? 'current' : (in_array($distribution['status'], ['pending', 'assigned']) ? 'pending' : 'completed'); ?>">
                    <div class="history-marker"></div>
                    <div class="history-content">
                        <span class="history-status">In Transit</span>
                        <span class="history-date">On the way to victim</span>
                    </div>
                </div>
                
                <div class="history-item <?php echo $distribution['status'] == 'delivered' ? 'current' : (in_array($distribution['status'], ['pending', 'assigned', 'in-transit']) ? 'pending' : 'completed'); ?>">
                    <div class="history-marker"></div>
                    <div class="history-content">
                        <span class="history-status">Delivered</span>
                        <span class="history-date">Victim received items</span>
                    </div>
                </div>
                
                <div class="history-item <?php echo $distribution['status'] == 'completed' ? 'current' : 'pending'; ?>">
                    <div class="history-marker"></div>
                    <div class="history-content">
                        <span class="history-status">Completed</span>
                        <span class="history-date">Distribution fulfilled</span>
                    </div>
                </div>
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
                if (statusSelect.value === 'delivered' || statusSelect.value === 'completed') {
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

                if ((status === 'delivered' || status === 'completed') && !quantityInput.value) {
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

                // Re-enable button after 3 seconds (in case of error)
                setTimeout(() => {
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                }, 3000);

                return true;
            });

            // Auto-focus status select
            statusSelect.focus();
        });
    </script>
</body>
</html>