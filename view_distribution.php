<?php
require_once 'config.php';

$database = new Database();
$db = $database->getConnection();

$distribution_id = $_GET['id'] ?? null;

if (!$distribution_id) {
    header("Location: distribution_main.php");
    exit;
}

// Get distribution details with all related information
$query = "
    SELECT 
        d.*,
        v.name as victim_name,
        v.age as victim_age,
        v.address as victim_address,
        dis.disaster_id,
        dis.Disaster_Name,
        dis.Disaster_Type,
        dis.Disaster_Date,
        dis.Severity_level,
        dis.Location as disaster_location,
        dis.Description as disaster_description,
        r.resource_id,
        r.name as resource_name,
        r.type as resource_type,
        r.unit as resource_unit
    FROM distribution d
    LEFT JOIN victim v ON d.victim_id = v.victim_id
    LEFT JOIN disaster dis ON d.disaster_id = dis.disaster_id
    LEFT JOIN resource r ON d.resource_id = r.resource_id
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

// Get distribution history/logs
$history_query = "
    SELECT 
        'Created' as action,
        date as action_date,
        comments as details,
        status
    FROM distribution 
    WHERE distribution_id = ?
    
    UNION ALL
    
    SELECT 
        CONCAT('Status: ', status) as action,
        assigned_timestamp as action_date,
        CONCAT('Volunteer assigned: ', v.name, ' as ', dv.role) as details,
        dv.status
    FROM distribution_volunteer dv
    LEFT JOIN volunteer v ON dv.volunteer_id = v.volunteer_id
    WHERE dv.distribution_id = ?
    
    ORDER BY action_date DESC
";

$history_stmt = $db->prepare($history_query);
$history_stmt->bind_param("ii", $distribution_id, $distribution_id);
$history_stmt->execute();
$history_result = $history_stmt->get_result();
$distribution_history = $history_result->fetch_all(MYSQLI_ASSOC);
$history_stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Distribution #<?php echo $distribution_id; ?></title>
    <link rel="stylesheet" href="css/style.css">
    <div class="container">
        <!-- Header Section -->
        <div class="header">
            <h1>Distribution Details</h1>
            <p>Distribution #<?php echo $distribution_id; ?> - <?php echo htmlspecialchars($distribution['victim_name']); ?></p>
        </div>

        <!-- Quick Actions -->
        <div class="card-3d quick-actions">
            <div class="grid-5">
                <a href="assign_volunteer.php?id=<?php echo $distribution_id; ?>" class="btn btn-success">
                    👥 Assign Volunteers
                </a>
                <button onclick="openQuickUpdateStatusModal()" class="btn btn-warning">
                    📝 Update Status
                </button>
                <a href="distribution_main.php" class="btn btn-primary">
                    📊 Back to Dashboard
                </a>
                <button onclick="window.print()" class="btn btn-secondary">
                    🖨️ Print Details
                </button>
                <a href="edit_distribution.php?id=<?php echo $distribution_id; ?>" class="btn btn-info">
                    ✏️ Edit Distribution
                </a>
            </div>
        </div>

        <div class="grid-2">
            <!-- Distribution Overview -->
            <div class="card-3d">
                <h2>📦 Distribution Overview</h2>
                <div class="overview-grid">
                    <div class="overview-item">
                        <label>Distribution ID</label>
                        <div class="value">#<?php echo $distribution['distribution_id']; ?></div>
                    </div>
                    <div class="overview-item">
                        <label>Status</label>
                        <div class="value">
                            <span class="status-badge status-<?php echo $distribution['status']; ?> large">
                                <?php echo ucfirst($distribution['status']); ?>
                            </span>
                        </div>
                    </div>
                    <div class="overview-item">
                        <label>Distribution Date</label>
                        <div class="value"><?php echo date('F j, Y', strtotime($distribution['date'])); ?></div>
                    </div>
                    <div class="overview-item">
                        <label>Quantity Sent</label>
                        <div class="value"><?php echo $distribution['quantity_sent']; ?> <?php echo htmlspecialchars($distribution['resource_unit']); ?></div>
                    </div>
                </div>

                <!-- Progress Bar -->
                <div class="progress-section">
                    <label>Distribution Progress</label>
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

                <?php if ($distribution['comments']): ?>
                <div class="comments-section">
                    <label>Additional Comments</label>
                    <div class="comments-box">
                        <?php echo nl2br(htmlspecialchars($distribution['comments'])); ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Victim Information -->
            <div class="card-3d">
                <h2>👤 Victim Information</h2>
                <div class="info-grid">
                    <div class="info-item">
                        <label>Full Name</label>
                        <div class="value"><?php echo htmlspecialchars($distribution['victim_name']); ?></div>
                    </div>
                    <div class="info-item">
                        <label>Age</label>
                        <div class="value"><?php echo htmlspecialchars($distribution['victim_age']); ?> years</div>
                    </div>
                    <div class="info-item full-width">
                        <label>Address</label>
                        <div class="value address"><?php echo nl2br(htmlspecialchars($distribution['victim_address'])); ?></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid-2">
            <!-- Disaster Information -->
            <div class="card-3d">
                <h2>🌪️ Disaster Information</h2>
                <div class="info-grid">
                    <div class="info-item">
                        <label>Disaster Name</label>
                        <div class="value"><?php echo htmlspecialchars($distribution['Disaster_Name']); ?></div>
                    </div>
                    <div class="info-item">
                        <label>Disaster Type</label>
                        <div class="value"><?php echo htmlspecialchars($distribution['Disaster_Type']); ?></div>
                    </div>
                    <div class="info-item">
                        <label>Severity Level</label>
                        <div class="value">
                            <span class="severity-badge severity-<?php echo strtolower($distribution['Severity_level']); ?>">
                                <?php echo htmlspecialchars($distribution['Severity_level']); ?>
                            </span>
                        </div>
                    </div>
                    <div class="info-item">
                        <label>Disaster Date</label>
                        <div class="value"><?php echo date('F j, Y', strtotime($distribution['Disaster_Date'])); ?></div>
                    </div>
                    <div class="info-item full-width">
                        <label>Location</label>
                        <div class="value"><?php echo htmlspecialchars($distribution['disaster_location']); ?></div>
                    </div>
                    <?php if ($distribution['disaster_description']): ?>
                    <div class="info-item full-width">
                        <label>Description</label>
                        <div class="value"><?php echo nl2br(htmlspecialchars($distribution['disaster_description'])); ?></div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Resource Information -->
            <div class="card-3d">
                <h2>📦 Resource Information</h2>
                <div class="info-grid">
                    <div class="info-item">
                        <label>Resource Name</label>
                        <div class="value"><?php echo htmlspecialchars($distribution['resource_name']); ?></div>
                    </div>
                    <div class="info-item">
                        <label>Resource Type</label>
                        <div class="value"><?php echo htmlspecialchars($distribution['resource_type']); ?></div>
                    </div>
                    <div class="info-item">
                        <label>Quantity Distributed</label>
                        <div class="value highlight"><?php echo $distribution['quantity_sent']; ?> <?php echo htmlspecialchars($distribution['resource_unit']); ?></div>
                    </div>
                    <div class="info-item">
                        <label>Unit</label>
                        <div class="value"><?php echo htmlspecialchars($distribution['resource_unit']); ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Assigned Volunteers -->
        <div class="card-3d">
            <div class="section-header">
                <h2>👥 Assigned Volunteers</h2>
                <div class="header-actions">
                    <span class="badge-count"><?php echo count($assigned_volunteers); ?> assigned</span>
                    <a href="assign_volunteer.php?id=<?php echo $distribution_id; ?>" class="btn btn-success btn-sm">
                        👥 Assign More
                    </a>
                </div>
            </div>
            
            <?php if (count($assigned_volunteers) > 0): ?>
                <div class="volunteers-list">
                    <?php foreach ($assigned_volunteers as $volunteer): ?>
                    <div class="volunteer-card">
                        <div class="volunteer-info">
                            <div class="volunteer-main">
                                <div class="volunteer-avatar">
                                    <?php echo strtoupper(substr($volunteer['volunteer_name'], 0, 1)); ?>
                                </div>
                                <div class="volunteer-details">
                                    <h4><?php echo htmlspecialchars($volunteer['volunteer_name']); ?></h4>
                                    <p class="volunteer-role"><?php echo htmlspecialchars($volunteer['role']); ?></p>
                                    <p class="volunteer-phone">📱 <?php echo htmlspecialchars($volunteer['volunteer_phone']); ?></p>
                                    <p class="volunteer-main-role">Main Role: <?php echo htmlspecialchars($volunteer['volunteer_main_role']); ?></p>
                                    <p class="volunteer-status">
                                        <span class="status-badge status-<?php echo strtolower($volunteer['status']); ?>">
                                            <?php echo $volunteer['status']; ?>
                                        </span>
                                    </p>
                                    <p class="volunteer-assigned">
                                        Assigned: <?php echo date('M j, Y g:i A', strtotime($volunteer['assigned_timestamp'])); ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="volunteer-actions">
    <button class="btn-action btn-update" 
            onclick="openUpdateStatusModal(<?php echo $volunteer['distribution_volunteer_id']; ?>, '<?php echo addslashes($volunteer['volunteer_name']); ?>')"
            title="Update Status">
        ✏️
    </button>
    <button class="btn-action btn-remove" 
           onclick="openRemoveAssignmentModal(<?php echo $volunteer['distribution_volunteer_id']; ?>, '<?php echo addslashes($volunteer['volunteer_name']); ?>')"
            title="Remove Assignment">
        🗑️
    </button>
</div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-icon">👥</div>
                    <h3>No Volunteers Assigned</h3>
                    <p>No volunteers have been assigned to this distribution yet.</p>
                    <a href="assign_volunteer.php?id=<?php echo $distribution_id; ?>" class="btn btn-success">
                        Assign Volunteers Now
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <!-- Distribution History -->
        <div class="card-3d">
            <h2>📋 Distribution History</h2>
            <div class="timeline">
                <?php if (count($distribution_history) > 0): ?>
                    <?php foreach ($distribution_history as $history): ?>
                    <div class="timeline-item">
                        <div class="timeline-marker"></div>
                        <div class="timeline-content">
                            <div class="timeline-header">
                                <span class="timeline-action"><?php echo htmlspecialchars($history['action']); ?></span>
                                <span class="timeline-date"><?php echo date('M j, Y g:i A', strtotime($history['action_date'])); ?></span>
                            </div>
                            <?php if ($history['details']): ?>
                            <div class="timeline-details"><?php echo htmlspecialchars($history['details']); ?></div>
                            <?php endif; ?>
                            <?php if ($history['status']): ?>
                            <div class="timeline-status">
                                Status: <span class="status-badge status-<?php echo strtolower($history['status']); ?>">
                                    <?php echo $history['status']; ?>
                                </span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-icon">📝</div>
                        <h3>No History Available</h3>
                        <p>Distribution history will appear here as actions are taken.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Quick Stats -->
        <div class="grid-4">
            <div class="stat-card mini">
                <div class="stat-label">Assigned Volunteers</div>
                <div class="stat-number"><?php echo count($assigned_volunteers); ?></div>
            </div>
            <div class="stat-card mini">
                <div class="stat-label">Days Since Created</div>
                <div class="stat-number"><?php echo floor((time() - strtotime($distribution['date'])) / (60 * 60 * 24)); ?></div>
            </div>
            <div class="stat-card mini">
                <div class="stat-label">Quantity</div>
                <div class="stat-number"><?php echo $distribution['quantity_sent']; ?></div>
            </div>
            <div class="stat-card mini">
                <div class="stat-label">History Items</div>
                <div class="stat-number"><?php echo count($distribution_history); ?></div>
            </div>
        </div>
    </div>

    <!-- Update Status Modal -->
    <div id="updateStatusModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Update Volunteer Status</h3>
                <span class="close" onclick="closeModal('updateStatusModal')">&times;</span>
            </div>
            <div class="modal-body">
                <form id="updateStatusForm" method="POST" action="ajax_update_status.php">
                    <input type="hidden" name="distribution_volunteer_id" id="modal_distribution_volunteer_id">
                    <input type="hidden" name="distribution_id" value="<?php echo $distribution_id; ?>">
                    
                    <div class="form-group">
                        <label>Volunteer</label>
                        <div id="modal_volunteer_name" class="readonly-field"></div>
                    </div>
                    
                    <div class="form-group">
                        <label>New Status</label>
                        <select name="status" id="modal_status" required>
                            <option value="assigned">Assigned</option>
                            <option value="active">Active</option>
                            <option value="completed">Completed</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Notes (Optional)</label>
                        <textarea name="notes" placeholder="Add any notes about this status change..."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('updateStatusModal')">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="submitUpdateStatus()">Update Status</button>
            </div>
        </div>
    </div>

    <!-- Remove Assignment Modal -->
    <div id="removeAssignmentModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Remove Volunteer Assignment</h3>
                <span class="close" onclick="closeModal('removeAssignmentModal')">&times;</span>
            </div>
            <div class="modal-body">
                <form id="removeAssignmentForm" method="POST" action="ajax_remove_assignment.php">
                    <input type="hidden" name="distribution_volunteer_id" id="remove_modal_distribution_volunteer_id">
                    <input type="hidden" name="distribution_id" value="<?php echo $distribution_id; ?>">
                    
                    <div class="warning-message">
                        <div class="warning-icon">⚠️</div>
                        <p>Are you sure you want to remove <strong id="remove_modal_volunteer_name"></strong> from this distribution?</p>
                    </div>
                    
                    <div class="form-group">
                        <label>Reason for Removal</label>
                        <select name="removal_reason" required>
                            <option value="">Select a reason...</option>
                            <option value="volunteer_unavailable">Volunteer Unavailable</option>
                            <option value="reassigned">Reassigned to Other Task</option>
                            <option value="distribution_completed">Distribution Completed</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Additional Notes</label>
                        <textarea name="removal_notes" placeholder="Explain why this volunteer is being removed..."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('removeAssignmentModal')">Cancel</button>
                <button type="button" class="btn btn-danger" onclick="submitRemoveAssignment()">Remove Assignment</button>
            </div>
        </div>
    </div>

<!-- Quick Update Distribution Status Modal -->
<div id="quickUpdateStatusModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Update Distribution Status</h3>
            <span class="close" onclick="closeModal('quickUpdateStatusModal')">&times;</span>
        </div>

        <div class="modal-body">
            <form id="quickUpdateStatusForm" method="POST" action="ajax_update_distribution_status.php">
                <input type="hidden" name="distribution_id" value="<?php echo $distribution_id; ?>">

                <div class="status-options">

                    <!-- Pending -->
                    <div class="status-option">
                        <input type="radio" name="status" value="pending" id="status_pending" 
                            <?php echo $distribution['status'] == 'pending' ? 'checked' : ''; ?>>
                        <label for="status_pending" class="status-label status-pending">
                            <span class="status-badge status-pending"></span>
                            <strong>Pending</strong>
                            <small>Distribution planned but not yet started</small>
                        </label>
                    </div>

                    <!-- Assigned -->
                    <div class="status-option">
                        <input type="radio" name="status" value="assigned" id="status_assigned"
                            <?php echo $distribution['status'] == 'assigned' ? 'checked' : ''; ?>>
                        <label for="status_assigned" class="status-label status-assigned">
                            <span class="status-badge status-assigned"></span>
                            <strong>Assigned</strong>
                            <small>Volunteers assigned and preparing</small>
                        </label>
                    </div>

                    <!-- In Transit -->
                    <div class="status-option">
                        <input type="radio" name="status" value="in-transit" id="status_in_transit"
                            <?php echo $distribution['status'] == 'in-transit' ? 'checked' : ''; ?>>
                        <label for="status_in_transit" class="status-label status-in-transit">
                            <span class="status-badge status-in-transit"></span>
                            <strong>In Transit</strong>
                            <small>Distribution is currently on the way</small>
                        </label>
                    </div>

                    <!-- Delivered -->
                    <div class="status-option">
                        <input type="radio" name="status" value="delivered" id="status_delivered"
                            <?php echo $distribution['status'] == 'delivered' ? 'checked' : ''; ?>>
                        <label for="status_delivered" class="status-label status-delivered">
                            <span class="status-badge status-delivered"></span>
                            <strong>Delivered</strong>
                            <small>Resources have reached the site</small>
                        </label>
                    </div>

                    <!-- Completed -->
                    <div class="status-option">
                        <input type="radio" name="status" value="completed" id="status_completed"
                            <?php echo $distribution['status'] == 'completed' ? 'checked' : ''; ?>>
                        <label for="status_completed" class="status-label status-completed">
                            <span class="status-badge status-completed"></span>
                            <strong>Completed</strong>
                            <small>Distribution process fully completed</small>
                        </label>
                    </div>

                </div>
            </form>
        </div>

        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('quickUpdateStatusModal')">
                Cancel
            </button>
            <button type="button" class="btn btn-primary" onclick="submitQuickUpdateStatus()">
                Update Status
            </button>
        </div>
    </div>
</div>

    <script>
        // Modal Functions
        function openUpdateStatusModal(distributionVolunteerId, volunteerName) {
            document.getElementById('modal_distribution_volunteer_id').value = distributionVolunteerId;
            document.getElementById('modal_volunteer_name').textContent = volunteerName;
            document.getElementById('updateStatusModal').style.display = 'block';
        }

        function openRemoveAssignmentModal(distributionVolunteerId, volunteerName) {
            document.getElementById('remove_modal_distribution_volunteer_id').value = distributionVolunteerId;
            document.getElementById('remove_modal_volunteer_name').textContent = volunteerName;
            document.getElementById('removeAssignmentModal').style.display = 'block';
        }

        function openQuickUpdateStatusModal() {
            document.getElementById('quickUpdateStatusModal').style.display = 'block';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modals = document.getElementsByClassName('modal');
            for (let modal of modals) {
                if (event.target == modal) {
                    modal.style.display = 'none';
                }
            }
        }

        // AJAX Form Submissions
        function submitUpdateStatus() {
            const form = document.getElementById('updateStatusForm');
            const formData = new FormData(form);
            
            fetch(form.action, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload(); // Reload to show updated status
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while updating status.');
            });
        }

        function submitRemoveAssignment() {
            const form = document.getElementById('removeAssignmentForm');
            const formData = new FormData(form);
            
            fetch(form.action, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload(); // Reload to show updated volunteer list
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while removing assignment.');
            });
        }

        function submitQuickUpdateStatus() {
            const form = document.getElementById('quickUpdateStatusForm');
            const formData = new FormData(form);
            
            fetch(form.action, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload(); // Reload to show updated status
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while updating distribution status.');
            });
        }
    </script>
</body>
</html>