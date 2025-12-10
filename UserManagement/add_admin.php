<?php
session_start();
if(!isset($_SESSION['name']) || $_SESSION['role'] != "admin"){
    header("Location: login.php");
    exit();
}

// Initialize variables
$fullname = $email = $phone = $role = "";
$error = "";
$success = "";

// Database connection
$serverName = "localhost";
$connectionOptions = array(
    "Database" => "UserManagement",
    "Uid" => "yanadb",
    "PWD" => "yana123"
);

$conn = sqlsrv_connect($serverName, $connectionOptions);

if($conn === false) {
    die(print_r(sqlsrv_errors(), true));
}

// Check if form is submitted
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get form data
    $fullname = trim($_POST['fullname']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $phone = trim($_POST['phone']);
    $role = $_POST['role'];
    
    // Validation
    if(empty($fullname) || empty($email) || empty($password) || empty($confirm_password) || empty($role)) {
        $error = "All required fields must be filled!";
    } elseif(!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format!";
    } elseif($password !== $confirm_password) {
        $error = "Passwords do not match!";
    } elseif(strlen($password) < 6) {
        $error = "Password must be at least 6 characters!";
    } else {
        // Check if email already exists
        $checkSql = "SELECT COUNT(*) as count FROM dbo.Admin WHERE Email = ?";
        $checkParams = array($email);
        $checkStmt = sqlsrv_query($conn, $checkSql, $checkParams);
        
        if($checkStmt === false) {
            $error = "Database error!";
        } else {
            $row = sqlsrv_fetch_array($checkStmt, SQLSRV_FETCH_ASSOC);
            if($row['count'] > 0) {
                $error = "Email already exists!";
            } else {
                // Hash the password
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                
                // Insert new admin
                $insertSql = "INSERT INTO dbo.Admin (FullName, Email, PasswordHash, Phone, Role, CreatedAt) 
                              VALUES (?, ?, ?, ?, ?, GETDATE())";
                $insertParams = array($fullname, $email, $password_hash, $phone, $role);
                $insertStmt = sqlsrv_query($conn, $insertSql, $insertParams);
                
                if($insertStmt === false) {
                    $error = "Failed to add admin: " . print_r(sqlsrv_errors(), true);
                } else {
                    $success = "Admin added successfully!";
                    // Reset form
                    $fullname = $email = $phone = $role = "";
                    
                    // Redirect to view page after 2 seconds
                    header("refresh:2;url=view_admin.php?add=success");
                }
                sqlsrv_free_stmt($insertStmt);
            }
            sqlsrv_free_stmt($checkStmt);
        }
    }
}

sqlsrv_close($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add New Admin - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            display: flex;
            background-color: #f8f9fa;
        }
        .sidebar {
            width: 250px;
            height: 100vh;
            background: #343a40;
            padding: 20px;
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
            padding: 30px;
            margin-left: 250px;
            width: calc(100% - 250px);
        }
        .card {
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            border: none;
            margin-bottom: 20px;
        }
        .card-header {
            background: #343a40;
            color: white;
            border-radius: 10px 10px 0 0 !important;
            padding: 15px 20px;
        }
        .form-control:focus {
            border-color: #0d6efd;
            box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.25);
        }
        .required:after {
            content: " *";
            color: red;
        }
        .password-toggle {
            cursor: pointer;
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
        }
        .password-wrapper {
            position: relative;
        }
    </style>
</head>
<body>

    <!-- Sidebar -->
    <div class="sidebar">
        <h4><i class="fas fa-user-shield me-2"></i>Admin Panel</h4>
        <hr style="border-color: #495057; margin: 15px 0;">
        <a href="admin_dashboard.php"><i class="fas fa-home me-2"></i>Dashboard</a>
        <a href="admin_profile.php"><i class="fas fa-user me-2"></i>Profile</a>
        <a href="add_admin.php" class="active"><i class="fas fa-plus-circle me-2"></i>Add Admin</a>
        <a href="view_admin.php"><i class="fas fa-users me-2"></i>View Admins</a>
        <a href="view_ngo.php"><i class="fas fa-building me-2"></i>View NGO Register</a>
        <a href="report.php"><i class="fas fa-chart-bar me-2"></i>Reports</a>
        <hr style="border-color: #495057; margin: 20px 0;">
        <a href="logout.php" class="text-danger"><i class="fas fa-sign-out-alt me-2"></i>Logout</a>
    </div>

    <!-- Main Content -->
    <div class="content">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="mb-1">Add New Admin</h2>
                <p class="text-muted">Create a new administrator account</p>
            </div>
            <div>
                <span class="badge bg-info fs-6 p-2">Logged in as: <?php echo $_SESSION['name']; ?></span>
            </div>
        </div>

        <!-- Success/Error Messages -->
        <?php if($success): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                <?php echo $success; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i>
                <?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Add Admin Form Card -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-user-plus me-2"></i>Admin Information</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <div class="row">
                        <!-- Full Name -->
                        <div class="col-md-6 mb-3">
                            <label class="form-label required">Full Name</label>
                            <input type="text" class="form-control" name="fullname" 
                                   value="<?php echo htmlspecialchars($fullname); ?>" 
                                   placeholder="Enter full name" required>
                            <div class="form-text">Enter the admin's full name</div>
                        </div>

                        <!-- Email -->
                        <div class="col-md-6 mb-3">
                            <label class="form-label required">Email Address</label>
                            <input type="email" class="form-control" name="email" 
                                   value="<?php echo htmlspecialchars($email); ?>" 
                                   placeholder="admin@example.com" required>
                            <div class="form-text">Must be a valid email address</div>
                        </div>

                        <!-- Password -->
                        <div class="col-md-6 mb-3">
                            <label class="form-label required">Password</label>
                            <div class="password-wrapper">
                                <input type="password" class="form-control" name="password" 
                                       id="password" placeholder="Enter password" required>
                                <span class="password-toggle" onclick="togglePassword('password')">
                                    <i class="fas fa-eye"></i>
                                </span>
                            </div>
                            <div class="form-text">Minimum 6 characters</div>
                        </div>

                        <!-- Confirm Password -->
                        <div class="col-md-6 mb-3">
                            <label class="form-label required">Confirm Password</label>
                            <div class="password-wrapper">
                                <input type="password" class="form-control" name="confirm_password" 
                                       id="confirm_password" placeholder="Confirm password" required>
                                <span class="password-toggle" onclick="togglePassword('confirm_password')">
                                    <i class="fas fa-eye"></i>
                                </span>
                            </div>
                            <div class="form-text">Re-enter the same password</div>
                        </div>

                        <!-- Phone -->
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Phone Number</label>
                            <input type="text" class="form-control" name="phone" 
                                   value="<?php echo htmlspecialchars($phone); ?>" 
                                   placeholder="e.g., 01122334455">
                            <div class="form-text">Optional phone number</div>
                        </div>

                        <!-- Role -->
                        <div class="col-md-6 mb-3">
                            <label class="form-label required">Role</label>
                            <select class="form-control" name="role" required>
                                <option value="">Select Role</option>
                                <option value="Admin" <?php echo ($role == 'Admin') ? 'selected' : ''; ?>>Admin</option>
                                <option value="SuperAdmin" <?php echo ($role == 'SuperAdmin') ? 'selected' : ''; ?>>Super Admin</option>
                            </select>
                            <div class="form-text">
                                <span class="badge bg-primary">Admin</span> - Normal privileges
                                <span class="badge bg-danger ms-2">Super Admin</span> - Full privileges
                            </div>
                        </div>
                    </div>

                    <!-- Form Actions -->
                    <div class="d-flex justify-content-between mt-4">
                        <div>
                            <a href="view_admin.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left me-2"></i>Back to List
                            </a>
                        </div>
                        <div>
                            <button type="reset" class="btn btn-warning me-2">
                                <i class="fas fa-redo me-2"></i>Reset Form
                            </button>
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-save me-2"></i>Add Admin
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Information Card -->
        <div class="card">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Important Notes</h5>
            </div>
            <div class="card-body">
                <ul class="list-group list-group-flush">
                    <li class="list-group-item">
                        <i class="fas fa-shield-alt text-primary me-2"></i>
                        <strong>Super Admin</strong> has full system access and can delete other admins
                    </li>
                    <li class="list-group-item">
                        <i class="fas fa-user-check text-success me-2"></i>
                        <strong>Admin</strong> has limited access and cannot delete other admins
                    </li>
                    <li class="list-group-item">
                        <i class="fas fa-lock text-danger me-2"></i>
                        Passwords are securely hashed using PHP's <code>password_hash()</code> function
                    </li>
                    <li class="list-group-item">
                        <i class="fas fa-envelope text-warning me-2"></i>
                        Email must be unique - cannot add duplicate email addresses
                    </li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Toggle password visibility
        function togglePassword(inputId) {
            const input = document.getElementById(inputId);
            const icon = input.nextElementSibling.querySelector('i');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }

        // Password strength indicator
        document.getElementById('password').addEventListener('input', function() {
            const password = this.value;
            const strengthText = document.getElementById('password-strength');
            
            if (!strengthText) {
                const div = document.createElement('div');
                div.id = 'password-strength';
                div.className = 'mt-2';
                this.parentNode.parentNode.appendChild(div);
            }
            
            let strength = 0;
            let message = '';
            let color = 'danger';
            
            if (password.length >= 6) strength++;
            if (/[A-Z]/.test(password)) strength++;
            if (/[0-9]/.test(password)) strength++;
            if (/[^A-Za-z0-9]/.test(password)) strength++;
            
            switch(strength) {
                case 0:
                case 1:
                    message = 'Weak';
                    color = 'danger';
                    break;
                case 2:
                    message = 'Fair';
                    color = 'warning';
                    break;
                case 3:
                    message = 'Good';
                    color = 'info';
                    break;
                case 4:
                    message = 'Strong';
                    color = 'success';
                    break;
            }
            
            document.getElementById('password-strength').innerHTML = `
                <div class="progress" style="height: 5px;">
                    <div class="progress-bar bg-${color}" style="width: ${strength * 25}%"></div>
                </div>
                <small class="text-${color}">Password strength: ${message}</small>
            `;
        });

        // Confirm password match
        document.getElementById('confirm_password').addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirm = this.value;
            const matchText = document.getElementById('password-match');
            
            if (!matchText) {
                const div = document.createElement('div');
                div.id = 'password-match';
                div.className = 'mt-2';
                this.parentNode.parentNode.appendChild(div);
            }
            
            if (confirm === '') {
                document.getElementById('password-match').innerHTML = '';
            } else if (password === confirm) {
                document.getElementById('password-match').innerHTML = `
                    <small class="text-success"><i class="fas fa-check-circle"></i> Passwords match</small>
                `;
            } else {
                document.getElementById('password-match').innerHTML = `
                    <small class="text-danger"><i class="fas fa-times-circle"></i> Passwords don't match</small>
                `;
            }
        });

        // Auto-dismiss alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
    </script>
</body>
</html>