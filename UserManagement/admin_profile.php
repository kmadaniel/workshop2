<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != "admin") {
    header("Location: login.php");
    exit();
}

require_once "connection.php"; // SQL Server connection

$user_id = $_SESSION['user_id']; 

// ============================
// FETCH ADMIN DATA
// ============================
$sql = "SELECT AdminID, FullName, Email, Phone, Role FROM Admin WHERE AdminID = ?";
$params = array($user_id);
$stmt = sqlsrv_query($conn, $sql, $params);

if ($stmt === false) {
    die(print_r(sqlsrv_errors(), true));
}

$row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

// ============================
// HANDLE PROFILE UPDATE
// ============================
$msg = "";

if (isset($_POST['update'])) {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    
    // Password update is optional
    if (!empty($_POST['password'])) {
        // Hash the new password
        $passwordHash = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $update_sql = "UPDATE Admin SET FullName = ?, Email = ?, Phone = ?, PasswordHash = ? WHERE AdminID = ?";
        $update_params = array($name, $email, $phone, $passwordHash, $user_id);
    } else {
        $update_sql = "UPDATE Admin SET FullName = ?, Email = ?, Phone = ? WHERE AdminID = ?";
        $update_params = array($name, $email, $phone, $user_id);
    }

    $update_stmt = sqlsrv_query($conn, $update_sql, $update_params);

    if ($update_stmt === false) {
        $msg = "Failed to update profile: " . print_r(sqlsrv_errors(), true);
    } else {
        $_SESSION['name'] = $name; // update session
        $msg = "Profile updated successfully!";
        
        // Refresh the data
        $row['FullName'] = $name;
        $row['Email'] = $email;
        $row['Phone'] = $phone;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Profile</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            display: flex;
            min-height: 100vh;
            background: #f8f9fa;
        }
        .sidebar {
            width: 250px;
            background: #343a40;
            padding: 20px;
            min-height: 100vh;
        }
        .sidebar a {
            display: block;
            padding: 10px;
            margin: 5px 0;
            color: white;
            text-decoration: none;
            border-radius: 5px;
        }
        .sidebar a:hover {
            background: #495057;
        }
        .content {
            flex-grow: 1;
            padding: 40px;
        }
        .password-toggle {
            cursor: pointer;
            color: #007bff;
            font-size: 0.875rem;
            margin-top: 5px;
            display: inline-block;
        }
    </style>
</head>
<body>

    <!-- Sidebar -->
    <div class="sidebar">
        <h4 class="text-white">Admin Panel</h4>
        <hr style="color:white;">
        <a href="admin_dashboard.php">🏠 Dashboard</a>
        <a href="admin_profile.php">👤 Profile</a>
        <a href="add_admin.php">➕ Add Admin</a>
        <a href="view_admin.php">📋 View Admins</a>
        <a href="view_ngo.php">🏢 View NGO Register</a>
        <a href="report.php">📊 Reports</a>
        <a href="logout.php" class="text-danger">🚪 Logout</a>
    </div>

    <!-- Main Content -->
    <div class="content">
        <h2 class="mb-4">Edit Profile: <?= htmlspecialchars($row['FullName']); ?></h2>

        <?php if ($msg) : ?>
            <div class="alert alert-info"><?= $msg ?></div>
        <?php endif; ?>

        <div class="card shadow p-4" style="max-width: 600px;">
            <form method="POST">
                <div class="mb-3">
                    <label class="form-label">Name:</label>
                    <input type="text" name="name" value="<?= htmlspecialchars($row['FullName']); ?>" class="form-control" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Email:</label>
                    <input type="email" name="email" value="<?= htmlspecialchars($row['Email']); ?>" class="form-control" required>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Phone:</label>
                    <input type="text" name="phone" value="<?= htmlspecialchars($row['Phone'] ?? ''); ?>" class="form-control">
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Role:</label>
                    <input type="text" value="<?= htmlspecialchars($row['Role']); ?>" class="form-control" disabled readonly>
                    <small class="text-muted">Role cannot be changed from profile page</small>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Change Password (Optional):</label>
                    <input type="password" name="password" id="password" class="form-control" placeholder="Leave blank to keep current password">
                    <div class="password-toggle" onclick="togglePassword()">👁️ Show/Hide Password</div>
                    <small class="text-muted">Leave empty if you don't want to change password</small>
                </div>

                <button type="submit" name="update" class="btn btn-primary">Update Profile</button>
                <a href="admin_dashboard.php" class="btn btn-secondary ms-2">Back</a>
            </form>
        </div>
    </div>
    
    <script>
        function togglePassword() {
            var passwordField = document.getElementById("password");
            if (passwordField.type === "password") {
                passwordField.type = "text";
            } else {
                passwordField.type = "password";
            }
        }
    </script>

</body>
</html>