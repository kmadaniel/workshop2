<?php
require_once 'config.php';

$database = new Database();
$db = $database->getConnection();

$distribution_id = $_GET['id'] ?? null;

if (!$distribution_id) {
    header("Location: distribution_main.php");
    exit;
}

// Get distribution details
$dist_query = "SELECT * FROM Distribution WHERE distribution_id = ?";
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
        r.Unit as resource_unit,
        dis.Disaster_Name
    FROM Distribution d
    LEFT JOIN victim v ON d.victim_id = v.victim_id
    LEFT JOIN RESOURCE r ON d.resource_id = r.Resource_ID
    LEFT JOIN Needs n ON d.victim_id = n.victim_id AND d.resource_id = n.resource_id
    LEFT JOIN Disaster dis ON n.disaster_id = dis.disaster_id
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
        FROM Distribution_volunteer dv 
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
    FROM Distribution_volunteer dv 
    LEFT JOIN volunteer v ON dv.volunteer_id = v.volunteer_id 
    WHERE dv.distribution_id = ?
    ORDER BY dv.volunteer_id DESC
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
    
    try {
        // Check if volunteer is already assigned
        $check_query = "SELECT volunteer_id FROM Distribution_volunteer WHERE volunteer_id = ? AND distribution_id = ?";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->bind_param("ii", $volunteer_id, $distribution_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            throw new Exception("This volunteer is already assigned to this distribution!");
        }
        $check_stmt->close();

        // Assign volunteer - only using columns that exist in your table
        $assign_query = "
            INSERT INTO Distribution_volunteer (volunteer_id, distribution_id, role) 
            VALUES (?, ?, ?)
        ";
        $assign_stmt = $db->prepare($assign_query);
        $assign_stmt->bind_param("iis", $volunteer_id, $distribution_id, $role);
        
        if ($assign_stmt->execute()) {
            // Update distribution status to Assigned if it's Pending
            if ($distribution['status'] == 'Pending') {
                $update_query = "UPDATE Distribution SET status = 'Assigned' WHERE distribution_id = ?";
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
    $volunteer_id = $_POST['volunteer_id'];
    $dist_id = $_POST['distribution_id'];
    
    try {
        $remove_query = "DELETE FROM Distribution_volunteer WHERE volunteer_id = ? AND distribution_id = ?";
        $remove_stmt = $db->prepare($remove_query);
        $remove_stmt->bind_param("ii", $volunteer_id, $dist_id);
        
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assign Volunteers - Distribution #<?php echo $distribution_id; ?></title>
    <link rel="stylesheet" href="main.css">
</head>
<body>
    <div class="container">
        <!-- Header Section -->
        <div class="page-header">
            <h2>👥 Assign Volunteers - Distribution #<?php echo $distribution_id; ?></h2>
            <a href="distribution_main.php" class="btn btn-secondary">← Back to Dashboard</a>
        </div>

        <!-- Alert Messages -->
        <?php if (isset($success)): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <!-- Quick Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3>Assigned</h3>
                <div class="stat-value"><?php echo count($assigned_volunteers); ?></div>
                <span class="stat-icon">👥</span>
            </div>
            <div class="stat-card">
                <h3>Available</h3>
                <div class="stat-value"><?php echo count($available_volunteers); ?></div>
                <span class="stat-icon">✅</span>
            </div>
            <div class="stat-card">
                <h3>Total</h3>
                <div class="stat-value"><?php echo count($all_volunteers); ?></div>
                <span class="stat-icon">📊</span>
            </div>
            <div class="stat-card">
                <h3>Status</h3>
                <div class="stat-value">
                    <span class="badge badge-<?php echo strtolower(str_replace(' ', '-', $distribution['status'])); ?>">
                        <?php echo $distribution['status']; ?>
                    </span>
                </div>
                <span class="stat-icon">🏷️</span>
            </div>
        </div>

        <div class="row">
            <!-- Distribution Information -->
            <div class="col-6">
                <div class="card">
                    <div class="card-header">
                        <h2>📦 Distribution Details</h2>
                    </div>
                    <div class="card-body">
                        <table class="info-table">
                            <tr>
                                <th>Distribution ID:</th>
                                <td>#<?php echo $distribution['distribution_id']; ?></td>
                            </tr>
                            <tr>
                                <th>Victim:</th>
                                <td><?php echo htmlspecialchars($distribution_details['victim_name'] ?? 'N/A'); ?></td>
                            </tr>
                            <tr>
                                <th>Disaster:</th>
                                <td><?php echo htmlspecialchars($distribution_details['Disaster_Name'] ?? 'N/A'); ?></td>
                            </tr>
                            <tr>
                                <th>Resource:</th>
                                <td><?php echo htmlspecialchars($distribution_details['resource_name'] ?? 'N/A'); ?></td>
                            </tr>
                            <tr>
                                <th>Quantity:</th>
                                <td><?php echo $distribution['quantity_sent']; ?> <?php echo htmlspecialchars($distribution_details['resource_unit'] ?? 'units'); ?></td>
                            </tr>
                            <tr>
                                <th>Delivery Date:</th>
                                <td><?php echo date('F j, Y', strtotime($distribution['date'])); ?></td>
                            </tr>
                            <tr>
                                <th>Status:</th>
                                <td>
                                    <span class="badge badge-<?php echo strtolower(str_replace(' ', '-', $distribution['status'])); ?>">
                                        <?php echo $distribution['status']; ?>
                                    </span>
                                </td>
                            </tr>
                        </table>
                        
                        <div class="action-buttons" style="margin-top: 20px;">
                            <a href="view_distribution.php?id=<?php echo $distribution_id; ?>" class="btn btn-info btn-sm">
                                👁️ View Details
                            </a>
                            <a href="update_status.php?id=<?php echo $distribution_id; ?>" class="btn btn-warning btn-sm">
                                ✏️ Update Status
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Assign New Volunteer Form -->
            <div class="col-6">
                <div class="card">
                    <div class="card-header">
                        <h2>➕ Assign New Volunteer</h2>
                    </div>
                    <div class="card-body">
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
                                
                                <button type="submit" class="btn btn-success" style="width: 100%;">
                                    ✅ Assign Volunteer
                                </button>
                            </form>
                        <?php else: ?>
                            <div class="empty-state">
                                <div style="font-size: 48px; margin-bottom: 15px;">👥</div>
                                <h3>No Available Volunteers</h3>
                                <p>All volunteers are currently assigned to this distribution.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Currently Assigned Volunteers -->
        <div class="card">
            <div class="card-header">
                <h2>📋 Currently Assigned Volunteers</h2>
                <div style="color: rgba(255,255,255,0.9); font-size: 14px;">
                    <?php echo count($assigned_volunteers); ?> volunteer(s) assigned
                </div>
            </div>
            
            <div class="table-container">
                <?php if (count($assigned_volunteers) > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Volunteer Name</th>
                                <th>Contact</th>
                                <th>Main Role</th>
                                <th>Assignment Role</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($assigned_volunteers as $assignment): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($assignment['volunteer_name']); ?></strong></td>
                                <td>📱 <?php echo htmlspecialchars($assignment['volunteer_phone']); ?></td>
                                <td><?php echo htmlspecialchars($assignment['volunteer_main_role']); ?></td>
                                <td>
                                    <span class="badge badge-info">
                                        <?php echo htmlspecialchars($assignment['role']); ?>
                                    </span>
                                </td>
                                <td>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="remove_assignment" value="1">
                                        <input type="hidden" name="volunteer_id" value="<?php echo $assignment['volunteer_id']; ?>">
                                        <input type="hidden" name="distribution_id" value="<?php echo $distribution_id; ?>">
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
                <?php else: ?>
                    <div class="empty-state">
                        <div style="font-size: 64px; margin-bottom: 20px;">👥</div>
                        <h3>No Volunteers Assigned</h3>
                        <p>No volunteers have been assigned to this distribution yet.</p>
                        <p>Use the form above to assign volunteers.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Available Volunteers Grid -->
        <?php if (count($available_volunteers) > 0): ?>
        <div class="card">
            <div class="card-header">
                <h2>✅ Available Volunteers</h2>
            </div>
            <div class="card-body">
                <div class="row">
                    <?php foreach ($available_volunteers as $volunteer): ?>
                    <div class="col-4">
                        <div class="volunteer-card" style="border: 2px solid #e8e8e8; border-radius: 8px; padding: 15px; margin-bottom: 15px;">
                            <h4 style="margin: 0 0 10px 0; color: #2c3e50;">
                                <?php echo htmlspecialchars($volunteer['name']); ?>
                            </h4>
                            <p style="margin: 5px 0; color: #7f8c8d;">
                                <strong>Role:</strong> <?php echo htmlspecialchars($volunteer['role']); ?>
                            </p>
                            <p style="margin: 5px 0; color: #7f8c8d;">
                                📱 <?php echo htmlspecialchars($volunteer['phone']); ?>
                            </p>
                            <button class="btn btn-success btn-sm quick-assign" 
                                    style="width: 100%; margin-top: 10px;"
                                    data-volunteer-id="<?php echo $volunteer['volunteer_id']; ?>"
                                    data-volunteer-name="<?php echo htmlspecialchars($volunteer['name']); ?>">
                                Quick Assign
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
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
                    
                    // Highlight the form
                    const formCard = document.querySelector('.assign-form').closest('.card');
                    formCard.style.boxShadow = '0 0 20px rgba(102, 126, 234, 0.5)';
                    setTimeout(() => {
                        formCard.style.boxShadow = '';
                    }, 2000);
                });
            });

            // Auto-select role based on volunteer's main role
            volunteerSelect.addEventListener('change', function() {
                const selectedOption = this.options[this.selectedIndex];
                if (selectedOption.value) {
                    const mainRole = selectedOption.getAttribute('data-role');
                    
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
        });
    </script>
</body>
</html>