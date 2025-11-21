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
$dist_query = "SELECT * FROM distribution WHERE distribution_id = ?";
$dist_stmt = $db->prepare($dist_query);
$dist_stmt->bind_param("i", $distribution_id);
$dist_stmt->execute();
$result = $dist_stmt->get_result();
$distribution = $result->fetch_assoc();
$dist_stmt->close();

if (!$distribution) {
    echo "<div class='container'><div class='alert alert-danger'>Distribution not found!</div></div>";
    exit;
}

// Get victim and resource details for this distribution
$details_query = "
    SELECT 
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

$details_stmt = $db->prepare($details_query);
$details_stmt->bind_param("i", $distribution_id);
$details_stmt->execute();
$details_result = $details_stmt->get_result();
$distribution_details = $details_result->fetch_assoc();
$details_stmt->close();

// Get available volunteers (not already assigned to this distribution)
$volunteers_query = "
    SELECT v.* 
    FROM volunteer v 
    WHERE v.volunteer_id NOT IN (
        SELECT dv.volunteer_id 
        FROM distribution_volunteer dv 
        WHERE dv.distribution_id = ?
    )
    ORDER BY v.name ASC
";

$volunteers_stmt = $db->prepare($volunteers_query);
$volunteers_stmt->bind_param("i", $distribution_id);
$volunteers_stmt->execute();
$volunteers_result = $volunteers_stmt->get_result();
$available_volunteers = $volunteers_result->fetch_all(MYSQLI_ASSOC);
$volunteers_stmt->close();

// Get currently assigned volunteers for this distribution
$assigned_query = "
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

$assigned_stmt = $db->prepare($assigned_query);
$assigned_stmt->bind_param("i", $distribution_id);
$assigned_stmt->execute();
$assigned_result = $assigned_stmt->get_result();
$assigned_volunteers = $assigned_result->fetch_all(MYSQLI_ASSOC);
$assigned_stmt->close();

// Get all volunteers for the system
$all_volunteers_query = "SELECT * FROM volunteer ORDER BY name ASC";
$all_volunteers_result = $db->query($all_volunteers_query);
$all_volunteers = $all_volunteers_result->fetch_all(MYSQLI_ASSOC);
$all_volunteers_result->free();

// Handle form submission for assigning new volunteer
if ($_POST && isset($_POST['assign_volunteer'])) {
    $volunteer_id = $_POST['volunteer_id'];
    $role = $_POST['role'];
    $assignment_notes = $_POST['assignment_notes'] ?? '';
    
    try {
        // Check if volunteer is already assigned
        $check_query = "SELECT id FROM distribution_volunteer WHERE volunteer_id = ? AND distribution_id = ?";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->bind_param("ii", $volunteer_id, $distribution_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            throw new Exception("This volunteer is already assigned to this distribution!");
        }
        $check_stmt->close();

        // Assign volunteer
        $assign_query = "
            INSERT INTO distribution_volunteer (volunteer_id, distribution_id, role, status, assignment_notes) 
            VALUES (?, ?, ?, 'Assigned', ?)
        ";
        $assign_stmt = $db->prepare($assign_query);
        $assign_stmt->bind_param("iiss", $volunteer_id, $distribution_id, $role, $assignment_notes);
        
        if ($assign_stmt->execute()) {
            // Update distribution status to assigned if it's pending
            if ($distribution['status'] == 'pending') {
                $update_query = "UPDATE distribution SET status = 'assigned' WHERE distribution_id = ?";
                $update_stmt = $db->prepare($update_query);
                $update_stmt->bind_param("i", $distribution_id);
                $update_stmt->execute();
                $update_stmt->close();
                
                // Update distribution data
                $dist_stmt = $db->prepare($dist_query);
                $dist_stmt->bind_param("i", $distribution_id);
                $dist_stmt->execute();
                $result = $dist_stmt->get_result();
                $distribution = $result->fetch_assoc();
                $dist_stmt->close();
            }
            
            $success = "✅ Volunteer assigned successfully!";
            
            // Refresh data
            $volunteers_stmt = $db->prepare($volunteers_query);
            $volunteers_stmt->bind_param("i", $distribution_id);
            $volunteers_stmt->execute();
            $volunteers_result = $volunteers_stmt->get_result();
            $available_volunteers = $volunteers_result->fetch_all(MYSQLI_ASSOC);
            $volunteers_stmt->close();
            
            $assigned_stmt = $db->prepare($assigned_query);
            $assigned_stmt->bind_param("i", $distribution_id);
            $assigned_stmt->execute();
            $assigned_result = $assigned_stmt->get_result();
            $assigned_volunteers = $assigned_result->fetch_all(MYSQLI_ASSOC);
            $assigned_stmt->close();
            
        } else {
            throw new Exception("Error assigning volunteer: " . $assign_stmt->error);
        }
        $assign_stmt->close();
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Handle removing volunteer assignment
if ($_POST && isset($_POST['remove_assignment'])) {
    $assignment_id = $_POST['assignment_id'];
    
    try {
        $remove_query = "DELETE FROM distribution_volunteer WHERE id = ?";
        $remove_stmt = $db->prepare($remove_query);
        $remove_stmt->bind_param("i", $assignment_id);
        
        if ($remove_stmt->execute()) {
            $success = "✅ Volunteer assignment removed successfully!";
            
            // Refresh data
            $assigned_stmt = $db->prepare($assigned_query);
            $assigned_stmt->bind_param("i", $distribution_id);
            $assigned_stmt->execute();
            $assigned_result = $assigned_stmt->get_result();
            $assigned_volunteers = $assigned_result->fetch_all(MYSQLI_ASSOC);
            $assigned_stmt->close();
            
            $volunteers_stmt = $db->prepare($volunteers_query);
            $volunteers_stmt->bind_param("i", $distribution_id);
            $volunteers_stmt->execute();
            $volunteers_result = $volunteers_stmt->get_result();
            $available_volunteers = $volunteers_result->fetch_all(MYSQLI_ASSOC);
            $volunteers_stmt->close();
            
        } else {
            throw new Exception("Error removing assignment: " . $remove_stmt->error);
        }
        $remove_stmt->close();
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Handle updating assignment status
if ($_POST && isset($_POST['update_status'])) {
    $assignment_id = $_POST['assignment_id'];
    $new_status = $_POST['status'];
    
    try {
        $update_query = "UPDATE distribution_volunteer SET status = ? WHERE id = ?";
        $update_stmt = $db->prepare($update_query);
        $update_stmt->bind_param("si", $new_status, $assignment_id);
        
        if ($update_stmt->execute()) {
            $success = "✅ Volunteer status updated successfully!";
            
            // Refresh assigned volunteers
            $assigned_stmt = $db->prepare($assigned_query);
            $assigned_stmt->bind_param("i", $distribution_id);
            $assigned_stmt->execute();
            $assigned_result = $assigned_stmt->get_result();
            $assigned_volunteers = $assigned_result->fetch_all(MYSQLI_ASSOC);
            $assigned_stmt->close();
            
        } else {
            throw new Exception("Error updating status: " . $update_stmt->error);
        }
        $update_stmt->close();
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assign Volunteers - Distribution #<?php echo $distribution_id; ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <!-- Header Section -->
        <div class="header">
            <h1>👥 Assign Volunteers</h1>
            <p>Manage volunteer assignments for Distribution #<?php echo $distribution_id; ?></p>
        </div>

        <!-- Alert Messages -->
        <?php if (isset($success)): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <!-- Quick Stats -->
        <div class="grid-4">
            <div class="stat-card mini">
                <div class="stat-label">Assigned</div>
                <div class="stat-number"><?php echo count($assigned_volunteers); ?></div>
                <div class="stat-desc">Volunteers</div>
            </div>
            <div class="stat-card mini">
                <div class="stat-label">Available</div>
                <div class="stat-number"><?php echo count($available_volunteers); ?></div>
                <div class="stat-desc">Volunteers</div>
            </div>
            <div class="stat-card mini">
                <div class="stat-label">Total</div>
                <div class="stat-number"><?php echo count($all_volunteers); ?></div>
                <div class="stat-desc">Volunteers</div>
            </div>
            <div class="stat-card mini">
                <div class="stat-label">Status</div>
                <div class="stat-number">
                    <span class="status-badge status-<?php echo $distribution['status']; ?>">
                        <?php echo ucfirst($distribution['status']); ?>
                    </span>
                </div>
            </div>
        </div>

        <div class="grid-2">
            <!-- Distribution Information -->
            <div class="card-3d">
                <h2>📦 Distribution Details</h2>
                <div class="distribution-info">
                    <div class="info-row">
                        <label>Distribution ID:</label>
                        <span>#<?php echo $distribution['distribution_id']; ?></span>
                    </div>
                    <div class="info-row">
                        <label>Victim:</label>
                        <span><?php echo htmlspecialchars($distribution_details['victim_name'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="info-row">
                        <label>Disaster:</label>
                        <span><?php echo htmlspecialchars($distribution_details['Disaster_Name'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="info-row">
                        <label>Resource:</label>
                        <span><?php echo htmlspecialchars($distribution_details['resource_name'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="info-row">
                        <label>Quantity:</label>
                        <span><?php echo $distribution['quantity_sent']; ?> <?php echo htmlspecialchars($distribution_details['resource_unit'] ?? 'units'); ?></span>
                    </div>
                    <div class="info-row">
                        <label>Delivery Date:</label>
                        <span><?php echo date('F j, Y', strtotime($distribution['date'])); ?></span>
                    </div>
                    <div class="info-row">
                        <label>Current Status:</label>
                        <span class="status-badge status-<?php echo $distribution['status']; ?>">
                            <?php echo ucfirst($distribution['status']); ?>
                        </span>
                    </div>
                    <?php if ($distribution['comments']): ?>
                    <div class="info-row full-width">
                        <label>Comments:</label>
                        <span><?php echo htmlspecialchars($distribution['comments']); ?></span>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="action-buttons">
                    <a href="view_distribution.php?id=<?php echo $distribution_id; ?>" class="btn btn-primary">
                        👁️ View Details
                    </a>
                    <a href="update_status.php?id=<?php echo $distribution_id; ?>" class="btn btn-warning">
                        📝 Update Status
                    </a>
                    <a href="index.php" class="btn btn-secondary">
                        📊 Dashboard
                    </a>
                </div>
            </div>

            <!-- Assign New Volunteer Form -->
            <div class="card-3d">
                <h2>➕ Assign New Volunteer</h2>
                <?php if (count($available_volunteers) > 0): ?>
                    <form method="POST" class="assign-form">
                        <input type="hidden" name="assign_volunteer" value="1">
                        
                        <div class="form-group">
                            <label class="form-label">Select Volunteer *</label>
                            <select class="form-control" name="volunteer_id" required id="volunteer_select">
                                <option value="">Choose a volunteer...</option>
                                <?php foreach ($available_volunteers as $volunteer): ?>
                                <option value="<?php echo $volunteer['volunteer_id']; ?>" 
                                        data-phone="<?php echo htmlspecialchars($volunteer['phone']); ?>"
                                        data-role="<?php echo htmlspecialchars($volunteer['role']); ?>">
                                    <?php echo htmlspecialchars($volunteer['name']); ?> 
                                    - <?php echo htmlspecialchars($volunteer['role']); ?>
                                    (<?php echo htmlspecialchars($volunteer['phone']); ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Assignment Role *</label>
                            <select class="form-control" name="role" required id="assignment_role">
                                <option value="">Select assignment role...</option>
                                <option value="Driver">🚗 Driver</option>
                                <option value="Coordinator">👨‍💼 Coordinator</option>
                                <option value="Helper">👷 Helper</option>
                                <option value="Medical Staff">🏥 Medical Staff</option>
                                <option value="Logistics">📦 Logistics</option>
                                <option value="Supervisor">👨‍💼 Supervisor</option>
                                <option value="Safety Officer">🛡️ Safety Officer</option>
                                <option value="Communications">📞 Communications</option>
                                <option value="First Aid">🩹 First Aid</option>
                            </select>
                            <small class="form-text">Specify the role for this particular assignment</small>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Assignment Notes</label>
                            <textarea class="form-control" name="assignment_notes" rows="2" 
                                      placeholder="Any special instructions or notes for this assignment..."></textarea>
                        </div>
                        
                        <button type="submit" class="btn btn-success btn-assign">
                            ✅ Assign Volunteer
                        </button>
                    </form>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-icon">👥</div>
                        <h3>No Available Volunteers</h3>
                        <p>All volunteers are currently assigned to this distribution.</p>
                        <p>Remove some assignments or add new volunteers to continue.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Currently Assigned Volunteers -->
        <div class="card-3d">
            <div class="section-header">
                <h2>📋 Currently Assigned Volunteers</h2>
                <span class="badge-count"><?php echo count($assigned_volunteers); ?> assigned</span>
            </div>
            
            <?php if (count($assigned_volunteers) > 0): ?>
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Volunteer</th>
                                <th>Contact</th>
                                <th>Main Role</th>
                                <th>Assignment Role</th>
                                <th>Status</th>
                                <th>Assigned On</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($assigned_volunteers as $assignment): ?>
                            <tr>
                                <td>
                                    <div class="volunteer-info-compact">
                                        <strong><?php echo htmlspecialchars($assignment['volunteer_name']); ?></strong>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($assignment['volunteer_phone']); ?></td>
                                <td><?php echo htmlspecialchars($assignment['volunteer_main_role']); ?></td>
                                <td>
                                    <span class="role-badge">
                                        <?php echo htmlspecialchars($assignment['role']); ?>
                                    </span>
                                </td>
                                <td>
                                    <form method="POST" class="status-form">
                                        <input type="hidden" name="update_status" value="1">
                                        <input type="hidden" name="assignment_id" value="<?php echo $assignment['id']; ?>">
                                        <select name="status" class="status-select" onchange="this.form.submit()">
                                            <option value="Assigned" <?php echo $assignment['status'] == 'Assigned' ? 'selected' : ''; ?>>Assigned</option>
                                            <option value="Active" <?php echo $assignment['status'] == 'Active' ? 'selected' : ''; ?>>Active</option>
                                            <option value="Completed" <?php echo $assignment['status'] == 'Completed' ? 'selected' : ''; ?>>Completed</option>
                                        </select>
                                    </form>
                                </td>
                                <td><?php echo date('M j, Y g:i A', strtotime($assignment['assigned_timestamp'])); ?></td>
                                <td>
                                    <form method="POST" class="action-form">
                                        <input type="hidden" name="remove_assignment" value="1">
                                        <input type="hidden" name="assignment_id" value="<?php echo $assignment['id']; ?>">
                                        <button type="submit" class="btn btn-danger btn-sm" 
                                                onclick="return confirm('Are you sure you want to remove this volunteer assignment?')">
                                            🗑️ Remove
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-icon">👥</div>
                    <h3>No Volunteers Assigned</h3>
                    <p>No volunteers have been assigned to this distribution yet.</p>
                    <p>Use the form above to assign volunteers.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Available Volunteers -->
        <div class="card-3d">
            <h2>✅ Available Volunteers</h2>
            <?php if (count($available_volunteers) > 0): ?>
                <div class="volunteers-grid">
                    <?php foreach ($available_volunteers as $volunteer): ?>
                    <div class="volunteer-card available">
                        <div class="volunteer-avatar">
                            <?php echo strtoupper(substr($volunteer['name'], 0, 1)); ?>
                        </div>
                        <div class="volunteer-details">
                            <h4><?php echo htmlspecialchars($volunteer['name']); ?></h4>
                            <p class="volunteer-role"><?php echo htmlspecialchars($volunteer['role']); ?></p>
                            <p class="volunteer-phone">📱 <?php echo htmlspecialchars($volunteer['phone']); ?></p>
                            <div class="volunteer-actions">
                                <button class="btn btn-success btn-sm quick-assign" 
                                        data-volunteer-id="<?php echo $volunteer['volunteer_id']; ?>"
                                        data-volunteer-name="<?php echo htmlspecialchars($volunteer['name']); ?>">
                                    Quick Assign
                                </button>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state mini">
                    <div class="empty-icon">✅</div>
                    <h4>All Volunteers Assigned</h4>
                    <p>No available volunteers at the moment.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- All Volunteers in System -->
        <div class="card-3d">
            <div class="section-header">
                <h2>👥 All Volunteers in System</h2>
                <span class="badge-count"><?php echo count($all_volunteers); ?> total</span>
            </div>
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Role</th>
                            <th>Phone</th>
                            <th>Status</th>
                            <th>Current Assignment</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($all_volunteers as $volunteer): 
                            $is_assigned = false;
                            foreach ($assigned_volunteers as $assigned) {
                                if ($assigned['volunteer_id'] == $volunteer['volunteer_id']) {
                                    $is_assigned = true;
                                    break;
                                }
                            }
                        ?>
                        <tr class="<?php echo $is_assigned ? 'assigned-row' : 'available-row'; ?>">
                            <td>
                                <strong><?php echo htmlspecialchars($volunteer['name']); ?></strong>
                            </td>
                            <td><?php echo htmlspecialchars($volunteer['role']); ?></td>
                            <td><?php echo htmlspecialchars($volunteer['phone']); ?></td>
                            <td>
                                <?php if ($is_assigned): ?>
                                    <span class="status-badge status-assigned">Assigned</span>
                                <?php else: ?>
                                    <span class="status-badge status-available">Available</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($is_assigned): ?>
                                    <span class="assignment-info">Distribution #<?php echo $distribution_id; ?></span>
                                <?php else: ?>
                                    <span class="assignment-info available">Available for assignment</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Quick assign functionality
            const quickAssignButtons = document.querySelectorAll('.quick-assign');
            const volunteerSelect = document.getElementById('volunteer_select');
            const assignmentRole = document.getElementById('assignment_role');
            
            quickAssignButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const volunteerId = this.getAttribute('data-volunteer-id');
                    const volunteerName = this.getAttribute('data-volunteer-name');
                    
                    // Set the volunteer in the dropdown
                    volunteerSelect.value = volunteerId;
                    
                    // Scroll to the form
                    document.querySelector('.assign-form').scrollIntoView({ 
                        behavior: 'smooth',
                        block: 'center'
                    });
                    
                    // Focus on role selection
                    assignmentRole.focus();
                    
                    // Show confirmation message
                    alert(`Quick assigning ${volunteerName}. Please select their role and submit the form.`);
                });
            });

            // Auto-select role based on volunteer's main role
            volunteerSelect.addEventListener('change', function() {
                const selectedOption = this.options[this.selectedIndex];
                if (selectedOption.value) {
                    const mainRole = selectedOption.getAttribute('data-role');
                    const phone = selectedOption.getAttribute('data-phone');
                    
                    // Try to match main role with assignment roles
                    const roleOptions = assignmentRole.options;
                    for (let i = 0; i < roleOptions.length; i++) {
                        if (roleOptions[i].value === mainRole) {
                            assignmentRole.value = mainRole;
                            break;
                        }
                    }
                }
            });

            // Form validation
            document.querySelector('.assign-form').addEventListener('submit', function(e) {
                const volunteerId = volunteerSelect.value;
                const role = assignmentRole.value;
                
                if (!volunteerId || !role) {
                    e.preventDefault();
                    alert('Please select both a volunteer and an assignment role.');
                    return false;
                }
                
                return true;
            });
        });
    </script>
    <script src="js/script.js"></script>
</body>
</html>