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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
            position: fixed;
        }
        .sidebar a {
            display: block;
            padding: 12px 15px;
            margin: 8px 0;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-size: 15px;
            transition: all 0.3s;
        }
        .sidebar a:hover {
            background: #495057;
            color: white;
        }
        .sidebar a.active {
            background: #0d6efd;
            color: white;
        }
        .sidebar h4 {
            color: white;
            padding-bottom: 15px;
            border-bottom: 2px solid #495057;
            margin-bottom: 20px;
        }
        .content {
            flex-grow: 1;
            padding: 40px;
            margin-left: 250px;
            width: calc(100% - 250px);
        }
        .card {
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            border: none;
            margin-bottom: 20px;
        }
        .password-toggle {
            cursor: pointer;
            color: #007bff;
            font-size: 0.875rem;
            margin-top: 5px;
            display: inline-block;
        }
        .password-toggle:hover {
            text-decoration: underline;
        }
        .profile-header {
            background: linear-gradient(90deg, #343a40 0%, #495057 100%);
            color: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
        }
    </style>
</head>
<body>

    <!-- Sidebar SAMA dengan dashboard dan view admin -->
    <div class="sidebar">
        <h4><i class="fas fa-user-shield me-2"></i>Admin Panel</h4>
        <hr style="border-color: #495057; margin: 15px 0;">
        <a href="admin_dashboard.php"><i class="fas fa-home me-2"></i>Dashboard</a>
        <a href="admin_profile.php" class="active"><i class="fas fa-user me-2"></i>Profile</a>
        <a href="add_admin.php"><i class="fas fa-plus-circle me-2"></i>Add Admin</a>
        <a href="view_admin.php"><i class="fas fa-users me-2"></i>View Admins</a>
        <a href="view_ngo.php"><i class="fas fa-building me-2"></i>View NGO Register</a>
        <a href="create_news.php"><i class="fas fa-newspaper me-2"></i>Create News</a>
        <a href="view_news.php"><i class="fas fa-list me-2"></i>View News</a>
        <a href="report.php"><i class="fas fa-chart-bar me-2"></i>Reports</a>
        <hr style="border-color: #495057; margin: 20px 0;">
        <a href="logout.php" class="text-danger"><i class="fas fa-sign-out-alt me-2"></i>Logout</a>
    </div>

    <!-- Main Content -->
    <div class="content">
        <!-- Header -->
        <div class="profile-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h3 class="mb-1"><i class="fas fa-user-circle me-2"></i>Admin Profile</h3>
                    <p class="mb-0">Manage your account information and settings</p>
                </div>
                <div>
                    <span class="badge bg-light text-dark fs-6 p-2">
                        <i class="fas fa-user-tag me-1"></i> Role: <?= htmlspecialchars($row['Role']); ?>
                    </span>
                </div>
            </div>
        </div>

        <?php if ($msg) : ?>
            <div class="alert alert-info alert-dismissible fade show" role="alert">
                <i class="fas fa-info-circle me-2"></i>
                <?= $msg ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-lg-8">
                <div class="card shadow p-4">
                    <h5 class="mb-4"><i class="fas fa-edit me-2"></i>Edit Profile Information</h5>
                    
                    <form method="POST">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Full Name:</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-user"></i></span>
                                    <input type="text" name="name" value="<?= htmlspecialchars($row['FullName']); ?>" class="form-control" required>
                                </div>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Email Address:</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                    <input type="email" name="email" value="<?= htmlspecialchars($row['Email']); ?>" class="form-control" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Phone Number:</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-phone"></i></span>
                                    <input type="text" name="phone" value="<?= htmlspecialchars($row['Phone'] ?? ''); ?>" class="form-control">
                                </div>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Admin Role:</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-user-tag"></i></span>
                                    <input type="text" value="<?= htmlspecialchars($row['Role']); ?>" class="form-control" disabled readonly>
                                </div>
                                <small class="text-muted">Role cannot be changed from profile page</small>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label class="form-label fw-bold">Change Password (Optional):</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                <input type="password" name="password" id="password" class="form-control" placeholder="Leave blank to keep current password">
                                <button class="btn btn-outline-secondary" type="button" onclick="togglePassword()">
                                    <i class="fas fa-eye" id="toggleIcon"></i>
                                </button>
                            </div>
                            <div class="password-toggle mt-2" onclick="togglePassword()">
                                <i class="fas fa-eye me-1"></i> Click to Show/Hide Password
                            </div>
                            <small class="text-muted">Leave empty if you don't want to change password</small>
                        </div>

                        <div class="d-flex justify-content-between mt-4">
                            <div>
                                <button type="submit" name="update" class="btn btn-primary px-4">
                                    <i class="fas fa-save me-2"></i> Update Profile
                                </button>
                                <a href="admin_dashboard.php" class="btn btn-secondary ms-2">
                                    <i class="fas fa-arrow-left me-2"></i> Back to Dashboard
                                </a>
                            </div>
                            <a href="#" class="btn btn-outline-danger">
                                <i class="fas fa-trash-alt me-2"></i> Delete Account
                            </a>
                        </div>
                    </form>
                </div>
            </div>
            
            <div class="col-lg-4">
                <div class="card shadow p-4">
                    <h5 class="mb-4"><i class="fas fa-user-circle me-2"></i>Profile Summary</h5>
                    
                    <div class="text-center mb-4">
                        <div class="rounded-circle bg-primary d-inline-flex align-items-center justify-content-center mb-3" 
                             style="width: 100px; height: 100px; font-size: 40px; color: white;">
                            <?php echo strtoupper(substr($row['FullName'], 0, 1)); ?>
                        </div>
                        <h5><?= htmlspecialchars($row['FullName']); ?></h5>
                        <p class="text-muted"><?= htmlspecialchars($row['Email']); ?></p>
                    </div>
                    
                    <div class="list-group list-group-flush">
                        <div class="list-group-item d-flex justify-content-between">
                            <span><i class="fas fa-id-badge me-2 text-primary"></i>Admin ID</span>
                            <span class="fw-bold">#<?= htmlspecialchars($user_id); ?></span>
                        </div>
                        <div class="list-group-item d-flex justify-content-between">
                            <span><i class="fas fa-phone me-2 text-primary"></i>Phone</span>
                            <span class="fw-bold"><?= htmlspecialchars($row['Phone'] ?? 'Not set'); ?></span>
                        </div>
                        <div class="list-group-item d-flex justify-content-between">
                            <span><i class="fas fa-user-tag me-2 text-primary"></i>Role</span>
                            <span class="badge bg-primary"><?= htmlspecialchars($row['Role']); ?></span>
                        </div>
                        <div class="list-group-item d-flex justify-content-between">
                            <span><i class="fas fa-calendar-alt me-2 text-primary"></i>Member Since</span>
                            <span class="fw-bold">-</span>
                        </div>
                    </div>
                    
                    <div class="mt-4">
                        <h6><i class="fas fa-shield-alt me-2"></i>Security Tips</h6>
                        <ul class="small text-muted">
                            <li>Use a strong, unique password</li>
                            <li>Enable two-factor authentication if available</li>
                            <li>Never share your login credentials</li>
                            <li>Log out when using public computers</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        function togglePassword() {
            var passwordField = document.getElementById("password");
            var toggleIcon = document.getElementById("toggleIcon");
            
            if (passwordField.type === "password") {
                passwordField.type = "text";
                toggleIcon.classList.remove("fa-eye");
                toggleIcon.classList.add("fa-eye-slash");
            } else {
                passwordField.type = "password";
                toggleIcon.classList.remove("fa-eye-slash");
                toggleIcon.classList.add("fa-eye");
            }
        }
        
        // Auto-dismiss alert after 5 seconds
        setTimeout(function() {
            var alert = document.querySelector('.alert');
            if(alert) {
                var bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            }
        }, 5000);
    </script>

</body>
</html>