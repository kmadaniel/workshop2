<?php
// ========================================
// MANAGE COORDINATORS PAGE
// ========================================

require_once 'config.php';
session_start();

$database = new Database();
$db = $database->getConnection();

$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_coordinator'])) {
        $name = $_POST['name'];
        $ic_number = $_POST['ic_number'];
        $phone = $_POST['phone'];
        $email = $_POST['email'];
        $department = $_POST['department'];
        $position = $_POST['position'];
        $status = $_POST['status'] ?? 'Active';
        
        try {
            $query = "INSERT INTO coordinators (name, ic_number, phone, email, department, position, status) 
                     VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $db->prepare($query);
            $stmt->bind_param("sssssss", $name, $ic_number, $phone, $email, $department, $position, $status);
            
            if ($stmt->execute()) {
                $success = "Coordinator added successfully!";
            } else {
                throw new Exception($stmt->error);
            }
            $stmt->close();
        } catch (Exception $e) {
            $error = "Error: " . $e->getMessage();
        }
    } elseif (isset($_POST['update_coordinator'])) {
        $coordinator_id = $_POST['coordinator_id'];
        $name = $_POST['name'];
        $ic_number = $_POST['ic_number'];
        $phone = $_POST['phone'];
        $email = $_POST['email'];
        $department = $_POST['department'];
        $position = $_POST['position'];
        $status = $_POST['status'];
        
        $query = "UPDATE coordinators SET 
                  name = ?, ic_number = ?, phone = ?, email = ?, 
                  department = ?, position = ?, status = ?
                  WHERE coordinator_id = ?";
        $stmt = $db->prepare($query);
        $stmt->bind_param("sssssssi", $name, $ic_number, $phone, $email, $department, $position, $status, $coordinator_id);
        $stmt->execute();
        $stmt->close();
        
        $success = "Coordinator updated successfully!";
    } elseif (isset($_POST['delete_coordinator'])) {
        $coordinator_id = $_POST['coordinator_id'];
        
        $query = "DELETE FROM coordinators WHERE coordinator_id = ?";
        $stmt = $db->prepare($query);
        $stmt->bind_param("i", $coordinator_id);
        $stmt->execute();
        $stmt->close();
        
        $success = "Coordinator deleted successfully!";
    }
}

// Get all coordinators
$coordinators = [];
$query = "SELECT * FROM coordinators ORDER BY status DESC, name ASC";
$result = $db->query($query);
if ($result) {
    $coordinators = $result->fetch_all(MYSQLI_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Coordinators</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .container { max-width: 1200px; margin: 20px auto; padding: 20px; }
        .card { background: white; border-radius: 8px; padding: 20px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px; }
        .form-group { margin-bottom: 15px; }
        .form-label { display: block; margin-bottom: 5px; font-weight: 600; }
        .form-control { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; }
        .btn { padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; font-weight: 600; margin-right: 5px; }
        .btn-success { background: #27ae60; color: white; }
        .btn-danger { background: #e74c3c; color: white; }
        .btn-warning { background: #f39c12; color: white; }
        .btn-primary { background: #3498db; color: white; }
        .btn-info { background: #17a2b8; color: white; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f8f9fa; font-weight: 600; }
        .status-badge { padding: 3px 10px; border-radius: 12px; font-size: 12px; font-weight: 600; }
        .status-active { background: #d4edda; color: #155724; }
        .status-inactive { background: #f8d7da; color: #721c24; }
        .alert { padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        .alert-success { background: #d4edda; color: #155724; }
        .alert-danger { background: #f8d7da; color: #721c24; }
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; }
        .modal-content { background: white; margin: 50px auto; padding: 20px; border-radius: 8px; max-width: 500px; }
        .action-buttons { display: flex; gap: 5px; }
    </style>
</head>
<body>
    <div class="container">
        <h1><i class="fas fa-users-cog"></i> Manage Coordinators</h1>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <!-- Add New Coordinator -->
        <div class="card">
            <h2>Add New Coordinator</h2>
            <form method="POST">
                <input type="hidden" name="add_coordinator" value="1">
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Full Name *</label>
                        <input type="text" class="form-control" name="name" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">IC Number *</label>
                        <input type="text" class="form-control" name="ic_number" required 
                               placeholder="XXXXXX-XX-XXXX">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Phone *</label>
                        <input type="text" class="form-control" name="phone" required 
                               placeholder="06-XXX-XXXX">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control" name="email" 
                               placeholder="name@jkm.gov.my">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Department</label>
                        <input type="text" class="form-control" name="department" 
                               placeholder="e.g., Relief Operations">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Position</label>
                        <input type="text" class="form-control" name="position" 
                               placeholder="e.g., Senior Coordinator">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Status</label>
                        <select class="form-control" name="status">
                            <option value="Active" selected>Active</option>
                            <option value="Inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <button type="submit" class="btn btn-success">
                    <i class="fas fa-plus"></i> Add Coordinator
                </button>
            </form>
        </div>
        
        <!-- List of Coordinators -->
        <div class="card">
            <h2>All Coordinators (<?php echo count($coordinators); ?>)</h2>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>IC Number</th>
                        <th>Phone</th>
                        <th>Email</th>
                        <th>Department</th>
                        <th>Position</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($coordinators as $coordinator): ?>
                    <tr>
                        <td>#<?php echo $coordinator['coordinator_id']; ?></td>
                        <td><strong><?php echo htmlspecialchars($coordinator['name']); ?></strong></td>
                        <td><?php echo htmlspecialchars($coordinator['ic_number']); ?></td>
                        <td><?php echo htmlspecialchars($coordinator['phone']); ?></td>
                        <td><?php echo htmlspecialchars($coordinator['email'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($coordinator['department'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($coordinator['position'] ?? 'N/A'); ?></td>
                        <td>
                            <span class="status-badge status-<?php echo strtolower($coordinator['status']); ?>">
                                <?php echo $coordinator['status']; ?>
                            </span>
                        </td>
                        <td>
                            <div class="action-buttons">
                                <button class="btn btn-info btn-sm" onclick="editCoordinator(<?php echo $coordinator['coordinator_id']; ?>)">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-danger btn-sm" onclick="deleteCoordinator(<?php echo $coordinator['coordinator_id']; ?>, '<?php echo htmlspecialchars($coordinator['name']); ?>')">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <a href="create_distribution.php" class="btn btn-primary">
            <i class="fas fa-arrow-left"></i> Back to Create Distribution
        </a>
    </div>

    <!-- Edit Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <h2>Edit Coordinator</h2>
            <form method="POST" id="editForm">
                <input type="hidden" name="update_coordinator" value="1">
                <input type="hidden" name="coordinator_id" id="edit_coordinator_id">
                <div class="form-group">
                    <label class="form-label">Full Name</label>
                    <input type="text" class="form-control" name="name" id="edit_name" required>
                </div>
                <div class="form-group">
                    <label class="form-label">IC Number</label>
                    <input type="text" class="form-control" name="ic_number" id="edit_ic_number" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Phone</label>
                    <input type="text" class="form-control" name="phone" id="edit_phone" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Email</label>
                    <input type="email" class="form-control" name="email" id="edit_email">
                </div>
                <div class="form-group">
                    <label class="form-label">Department</label>
                    <input type="text" class="form-control" name="department" id="edit_department">
                </div>
                <div class="form-group">
                    <label class="form-label">Position</label>
                    <input type="text" class="form-control" name="position" id="edit_position">
                </div>
                <div class="form-group">
                    <label class="form-label">Status</label>
                    <select class="form-control" name="status" id="edit_status">
                        <option value="Active">Active</option>
                        <option value="Inactive">Inactive</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-success">Update</button>
                <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
            </form>
        </div>
    </div>

    <!-- Delete Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <h2>Delete Coordinator</h2>
            <p id="deleteMessage"></p>
            <form method="POST" id="deleteForm">
                <input type="hidden" name="delete_coordinator" value="1">
                <input type="hidden" name="coordinator_id" id="delete_coordinator_id">
                <button type="submit" class="btn btn-danger">Delete</button>
                <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
            </form>
        </div>
    </div>

    <script>
        // Edit coordinator
        function editCoordinator(id) {
            // In a real application, you would fetch data via AJAX
            // For now, we'll redirect to a new page with the coordinator ID
            window.location.href = 'edit_coordinator.php?id=' + id;
        }
        
        // Delete coordinator
        function deleteCoordinator(id, name) {
            document.getElementById('deleteMessage').innerHTML = 
                'Are you sure you want to delete coordinator: <strong>' + name + '</strong>?';
            document.getElementById('delete_coordinator_id').value = id;
            document.getElementById('deleteModal').style.display = 'block';
        }
        
        // Close modal
        function closeModal() {
            document.getElementById('editModal').style.display = 'none';
            document.getElementById('deleteModal').style.display = 'none';
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target.className === 'modal') {
                closeModal();
            }
        }
    </script>
</body>
</html>