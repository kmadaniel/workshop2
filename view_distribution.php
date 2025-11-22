<?php
require_once 'config.php';

$database = new Database();
$db = $database->getConnection();

$distribution_id = $_GET['id'] ?? null;

if (!$distribution_id) {
    header("Location: distribution_main.php");
    exit;
}

// Handle comment update/delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_comment'])) {
        $new_comment = $_POST['comments'] ?? '';
        $update_query = "UPDATE distribution SET comments = ? WHERE distribution_id = ?";
        $update_stmt = $db->prepare($update_query);
        $update_stmt->bind_param("si", $new_comment, $distribution_id);
        
        if ($update_stmt->execute()) {
            // Refresh the page to show updated comment
            header("Location: view_distribution.php?id=" . $distribution_id);
            exit;
        }
        $update_stmt->close();
    }
    
    if (isset($_POST['delete_comment'])) {
        $update_query = "UPDATE distribution SET comments = NULL WHERE distribution_id = ?";
        $update_stmt = $db->prepare($update_query);
        $update_stmt->bind_param("i", $distribution_id);
        
        if ($update_stmt->execute()) {
            header("Location: view_distribution.php?id=" . $distribution_id);
            exit;
        }
        $update_stmt->close();
    }
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
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <!-- Header Section -->
        <div class="header">
            <h1>Distribution Details</h1>
            <p>Distribution #<?php echo $distribution_id; ?> - <?php echo htmlspecialchars($distribution['victim_name']); ?></p>
        </div>

        <!-- Quick Actions -->
        <div class="card-3d quick-actions">
            <div class="grid-4">
                <a href="assign_volunteer.php?id=<?php echo $distribution_id; ?>" class="btn btn-success">
                    👥 Assign Volunteers
                </a>
                <a href="update_status.php?id=<?php echo $distribution_id; ?>" class="btn btn-warning">
                    📝 Update Status
                </a>
                <a href="distribution_main.php" class="btn btn-primary">
                    📊 Back to Dashboard
                </a>
                <button onclick="window.print()" class="btn btn-secondary">
                    🖨️ Print Details
                </button>
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

                <!-- Comments Section with Edit/Delete -->
                <div class="comments-section">
                    <div class="comments-header">
                        <label>Additional Comments</label>
                        <div class="comment-actions">
                            <?php if ($distribution['comments']): ?>
                                <button type="button" class="btn-edit-comment" onclick="toggleEditComment()">
                                    ✏️ Edit
                                </button>
                                <button type="button" class="btn-delete-comment" onclick="deleteComment()">
                                    🗑️ Delete
                                </button>
                            <?php else: ?>
                                <button type="button" class="btn-add-comment" onclick="toggleEditComment()">
                                    ➕ Add Comment
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Display current comment -->
                    <?php if ($distribution['comments'] && !isset($_GET['edit_comment'])): ?>
                        <div class="comments-box" id="commentDisplay">
                            <?php echo nl2br(htmlspecialchars($distribution['comments'])); ?>
                        </div>
                    <?php endif; ?>

                    <!-- Edit comment form -->
                    <div class="comment-edit-form <?php echo isset($_GET['edit_comment']) || !$distribution['comments'] ? 'active' : ''; ?>" id="commentEditForm">
                        <form method="POST" action="">
                            <textarea 
                                name="comments" 
                                class="form-control comment-textarea" 
                                placeholder="Enter additional comments here..." 
                                rows="4"
                            ><?php echo htmlspecialchars($distribution['comments'] ?? ''); ?></textarea>
                            
                            <div class="comment-form-actions">
                                <button type="submit" name="update_comment" class="btn btn-success btn-sm">
                                    💾 Save Comment
                                </button>
                                <button type="button" class="btn btn-secondary btn-sm" onclick="cancelEditComment()">
                                    ❌ Cancel
                                </button>
                                <?php if ($distribution['comments']): ?>
                                    <button type="submit" name="delete_comment" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this comment?')">
                                        🗑️ Delete Comment
                                    </button>
                                <?php endif; ?>
                            </div>
                        </form>
                        
                        <!-- Delete comment form (separate for confirmation) -->
                        <form method="POST" action="" id="deleteForm" style="display: none;">
                            <input type="hidden" name="delete_comment" value="1">
                        </form>
                    </div>
                </div>
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
                <span class="badge-count"><?php echo count($assigned_volunteers); ?> assigned</span>
            </div>
            
            <?php if (count($assigned_volunteers) > 0): ?>
                <div class="volunteers-grid">
                    <?php foreach ($assigned_volunteers as $volunteer): ?>
                    <div class="volunteer-card">
                        <div class="volunteer-avatar">
                            <?php echo strtoupper(substr($volunteer['volunteer_name'], 0, 1)); ?>
                        </div>
                        <div class="volunteer-info">
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

    <script>
        // Toggle comment edit mode
        function toggleEditComment() {
            const display = document.getElementById('commentDisplay');
            const editForm = document.getElementById('commentEditForm');
            
            if (display) display.style.display = 'none';
            if (editForm) editForm.classList.add('active');
        }

        // Cancel comment editing
        function cancelEditComment() {
            const display = document.getElementById('commentDisplay');
            const editForm = document.getElementById('commentEditForm');
            
            if (display) display.style.display = 'block';
            if (editForm) editForm.classList.remove('active');
            
            // Redirect without edit parameter
            const url = new URL(window.location.href);
            url.searchParams.delete('edit_comment');
            window.history.replaceState({}, '', url);
        }

        // Delete comment with confirmation
        function deleteComment() {
            if (confirm('Are you sure you want to delete this comment? This action cannot be undone.')) {
                document.getElementById('deleteForm').submit();
            }
        }

        // Auto-expand textarea as user types
        document.addEventListener('DOMContentLoaded', function() {
            const textarea = document.querySelector('.comment-textarea');
            if (textarea) {
                textarea.addEventListener('input', function() {
                    this.style.height = 'auto';
                    this.style.height = (this.scrollHeight) + 'px';
                });
                
                // Trigger initial resize
                textarea.dispatchEvent(new Event('input'));
            }
            
            // Show edit form if URL has edit parameter
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('edit_comment')) {
                toggleEditComment();
            }
        });
    </script>
</body>
</html>