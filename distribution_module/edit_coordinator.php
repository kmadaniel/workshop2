<?php
require_once 'config.php';
session_start();

$database = new Database();
$db = $database->getConnection();

$coordinator_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$coordinator = null;
$error = '';
$success = '';

// Fetch coordinator data
if ($coordinator_id > 0) {
    $query = "SELECT * FROM coordinators WHERE coordinator_id = ?";
    $stmt = $db->prepare($query);
    $stmt->bind_param("i", $coordinator_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $coordinator = $result->fetch_assoc();
    $stmt->close();
}

if (!$coordinator) {
    header("Location: manage_coordinators.php");
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_coordinator'])) {
    $name = $_POST['name'];
    $ic_number = $_POST['ic_number'];
    $phone = $_POST['phone'];
    $email = $_POST['email'];
    $department = $_POST['department'];
    $position = $_POST['position'];
    $status = $_POST['status'];
    
    try {
        $query = "UPDATE coordinators SET 
                  name = ?, ic_number = ?, phone = ?, email = ?, 
                  department = ?, position = ?, status = ?
                  WHERE coordinator_id = ?";
        $stmt = $db->prepare($query);
        $stmt->bind_param("sssssssi", $name, $ic_number, $phone, $email, $department, $position, $status, $coordinator_id);
        
        if ($stmt->execute()) {
            $success = "Coordinator updated successfully!";
            // Refresh coordinator data
            $query = "SELECT * FROM coordinators WHERE coordinator_id = ?";
            $stmt = $db->prepare($query);
            $stmt->bind_param("i", $coordinator_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $coordinator = $result->fetch_assoc();
            $stmt->close();
        } else {
            throw new Exception($stmt->error);
        }
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Coordinator</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .container { max-width: 800px; margin: 20px auto; padding: 20px; }
        .card { background: white; border-radius: 8px; padding: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .form-group { margin-bottom: 15px; }
        .form-label { display: block; margin-bottom: 5px; font-weight: 600; }
        .form-control { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; }
        .btn { padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; font-weight: 600; margin-right: 5px; }
        .btn-success { background: #27ae60; color: white; }
        .btn-primary { background: #3498db; color: white; }
        .btn-secondary { background: #6c757d; color: white; }
        .alert { padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        .alert-success { background: #d4edda; color: #155724; }
        .alert-danger { background: #f8d7da; color: #721c24; }
    </style>
</head>
<body>
    <div class="container">
        <h1><i class="fas fa-edit"></i> Edit Coordinator</h1>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <div class="card">
            <form method="POST">
                <input type="hidden" name="update_coordinator" value="1">
                <div class="form-group">
                    <label class="form-label">Full Name *</label>
                    <input type="text" class="form-control" name="name" value="<?php echo htmlspecialchars($coordinator['name']); ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label">IC Number *</label>
                    <input type="text" class="form-control" name="ic_number" value="<?php echo htmlspecialchars($coordinator['ic_number']); ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Phone *</label>
                    <input type="text" class="form-control" name="phone" value="<?php echo htmlspecialchars($coordinator['phone']); ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Email</label>
                    <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($coordinator['email'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Department</label>
                    <input type="text" class="form-control" name="department" value="<?php echo htmlspecialchars($coordinator['department'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Position</label>
                    <input type="text" class="form-control" name="position" value="<?php echo htmlspecialchars($coordinator['position'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Status</label>
                    <select class="form-control" name="status">
                        <option value="Active" <?php echo $coordinator['status'] == 'Active' ? 'selected' : ''; ?>>Active</option>
                        <option value="Inactive" <?php echo $coordinator['status'] == 'Inactive' ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-success">
                    <i class="fas fa-save"></i> Update Coordinator
                </button>
                <a href="manage_coordinators.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to List
                </a>
            </form>
        </div>
    </div>
</body>
</html>