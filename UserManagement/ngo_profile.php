<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != "ngo") {
    header("Location: login.php");
    exit();
}

require_once "connection.php";

$user_id = $_SESSION['user_id'];
$message = "";

// ============================
// FETCH NGO DATA
// ============================
$sql = "SELECT NGOID, NGOName, RegistrationNo, Email, Phone, Address, PasswordHash, Status 
        FROM NGO WHERE NGOID = ?";
$params = array($user_id);
$stmt = sqlsrv_query($conn, $sql, $params);

if ($stmt === false) {
    die(print_r(sqlsrv_errors(), true));
}

$row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

// ============================
// HANDLE PROFILE UPDATE
// ============================
if (isset($_POST['update'])) {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $address = $_POST['address'] ?? null;
    
    // Handle status toggle
    $status = isset($_POST['status']) && $_POST['status'] == 'on' ? 'active' : 'inactive';
    
    // Password update is optional
    if (!empty($_POST['password'])) {
        $password = $_POST['password'];
        if (strlen($password) < 6) {
            $message = "❌ Password must be at least 6 characters long";
        } else {
            $passwordHash = password_hash($password, PASSWORD_BCRYPT);
            $update_sql = "UPDATE NGO SET NGOName = ?, Email = ?, Phone = ?, Address = ?, PasswordHash = ?, Status = ? WHERE NGOID = ?";
            $update_params = array($name, $email, $phone, $address, $passwordHash, $status, $user_id);
        }
    } else {
        $update_sql = "UPDATE NGO SET NGOName = ?, Email = ?, Phone = ?, Address = ?, Status = ? WHERE NGOID = ?";
        $update_params = array($name, $email, $phone, $address, $status, $user_id);
    }
    
    // Execute update if no password validation error
    if (empty($message) || strpos($message, '❌') === false) {
        $update_stmt = sqlsrv_query($conn, $update_sql, $update_params);
        
        if ($update_stmt === false) {
            $message = "❌ Failed to update profile: " . print_r(sqlsrv_errors(), true);
        } else {
            $_SESSION['name'] = $name; // update session
            $message = "✅ Profile updated successfully!";
            
            // Refresh the data
            $row['NGOName'] = $name;
            $row['Email'] = $email;
            $row['Phone'] = $phone;
            $row['Address'] = $address;
            $row['Status'] = $status;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>NGO Profile - VolunteerHub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f8f9fa;
            min-height: 100vh;
            display: flex;
        }
        
        .sidebar {
            width: 250px;
            background: #2c3e50;
            padding: 20px;
            min-height: 100vh;
            position: fixed;
        }
        
        .sidebar h4 {
            color: white;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #34495e;
        }
        
        .sidebar a {
            display: block;
            padding: 12px 15px;
            margin: 5px 0;
            color: #ecf0f1;
            text-decoration: none;
            border-radius: 5px;
            transition: all 0.3s;
        }
        
        .sidebar a:hover {
            background: #34495e;
            color: white;
        }
        
        .sidebar a.active {
            background: #3498db;
            color: white;
        }
        
        .content {
            flex: 1;
            padding: 40px;
            margin-left: 250px;
            overflow-y: auto;
        }
        
        .container {
            max-width: 700px;
            margin: 0 auto;
            background: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
        }
        
        h2 {
            color: #2c3e50;
            margin-bottom: 30px;
            font-size: 28px;
            border-bottom: 2px solid #3498db;
            padding-bottom: 10px;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #555;
            font-weight: 500;
            font-size: 14px;
        }
        
        .form-control {
            width: 100%;
            padding: 14px;
            border: 2px solid #e1e5e9;
            border-radius: 10px;
            font-size: 16px;
            transition: all 0.3s;
            background: #f8f9fa;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #3498db;
            background: #fff;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
        }
        
        .readonly-field {
            background-color: #f5f5f5 !important;
            color: #666 !important;
            cursor: not-allowed;
        }
        
        .message {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 25px;
            text-align: center;
            font-weight: 500;
        }
        
        .success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-block;
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, #2980b9 0%, #1c6ea4 100%);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(52, 152, 219, 0.3);
        }
        
        .btn-secondary {
            background: #95a5a6;
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-secondary:hover {
            background: #7f8c8d;
        }
        
        .password-container {
            position: relative;
        }
        
        .toggle-password {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #777;
            cursor: pointer;
            font-size: 18px;
        }
        
        /* Status Toggle Switch */
        .status-toggle {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .toggle-label {
            font-weight: 500;
            min-width: 100px;
        }
        
        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 70px;
            height: 34px;
        }
        
        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        
        .toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #e74c3c;
            transition: .4s;
            border-radius: 34px;
        }
        
        .toggle-slider:before {
            position: absolute;
            content: "";
            height: 26px;
            width: 26px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }
        
        input:checked + .toggle-slider {
            background-color: #2ecc71;
        }
        
        input:checked + .toggle-slider:before {
            transform: translateX(36px);
        }
        
        .status-text {
            font-weight: 600;
            font-size: 16px;
            min-width: 80px;
        }
        
        .status-active {
            color: #27ae60;
        }
        
        .status-inactive {
            color: #e74c3c;
        }
        
        .ngo-badge {
            display: inline-block;
            background: #3498db;
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 500;
            margin-left: 10px;
        }
        
        .profile-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
        }
        
        .profile-title {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                position: relative;
                min-height: auto;
                margin-bottom: 20px;
            }
            
            .content {
                margin-left: 0;
                padding: 20px;
            }
            
            .container {
                padding: 20px;
            }
            
            .profile-header {
                flex-direction: column;
                gap: 15px;
                align-items: flex-start;
            }
        }
    </style>
</head>
<body>

    <!-- Sidebar -->
    <div class="sidebar">
        <h4>NGO Panel</h4>
        <a href="ngo_dashboard.php">🏠 Dashboard</a>
        <a href="ngo_profile.php">👤 Profile</a>
         <a href="view_volunteers.php">👥 My Volunteers</a>
         <a href="create_news.php">📝 Apply Story Activity</a> 
        <a href="post_opportunity.php">📢 Post Opportunity</a>
        <a href="view_opportunities.php">📋 View Opportunities</a>
        <a href="logout.php" class="text-danger">🚪 Logout</a>
    </div>

    <!-- Main Content -->
    <div class="content">
        <div class="container">
            <div class="profile-header">
                <div class="profile-title">
                    <h2>Edit NGO Profile</h2>
                    <span class="ngo-badge">NGO</span>
                </div>
                <div class="status-text <?= $row['Status'] == 'active' ? 'status-active' : 'status-inactive' ?>">
                    Status: <?= ucfirst($row['Status']) ?>
                </div>
            </div>
            
            <?php if ($message) { ?>
                <div class="message <?= strpos($message, '✅') !== false ? 'success' : 'error' ?>">
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php } ?>
            
            <form method="POST" action="" id="profileForm">
                <div class="form-group">
                    <label>Registration Number</label>
                    <input type="text" value="<?= htmlspecialchars($row['RegistrationNo'] ?? '') ?>" 
                           class="form-control readonly-field" readonly>
                </div>
                
                <div class="form-group">
                    <label>NGO Name <span style="color: #e74c3c;">*</span></label>
                    <input type="text" name="name" value="<?= htmlspecialchars($row['NGOName']) ?>" 
                           class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label>Email Address <span style="color: #e74c3c;">*</span></label>
                    <input type="email" name="email" value="<?= htmlspecialchars($row['Email']) ?>" 
                           class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label>Phone Number <span style="color: #e74c3c;">*</span></label>
                    <input type="tel" name="phone" value="<?= htmlspecialchars($row['Phone']) ?>" 
                           class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label>Address</label>
                    <textarea name="address" class="form-control" rows="3"><?= htmlspecialchars($row['Address'] ?? '') ?></textarea>
                </div>
                
                <!-- Status Toggle -->
                <div class="form-group">
                    <label>Account Status</label>
                    <div class="status-toggle">
                        <span class="toggle-label">Status:</span>
                        <label class="toggle-switch">
                            <input type="checkbox" name="status" id="statusToggle" 
                                <?= ($row['Status'] == 'active') ? 'checked' : '' ?>>
                            <span class="toggle-slider"></span>
                        </label>
                        <span class="status-text" id="statusText">
                            <?= ucfirst($row['Status']) ?>
                        </span>
                    </div>
                    <small style="color: #666; font-size: 12px; display: block; margin-top: 5px;">
                        Green = Active, Red = Inactive
                    </small>
                </div>
                
                <div class="form-group">
                    <label>Change Password (Optional)</label>
                    <div class="password-container">
                        <input type="password" name="password" id="password" 
                               class="form-control" placeholder="Enter new password (min 6 characters)">
                        <button type="button" class="toggle-password" onclick="togglePassword()">👁️</button>
                    </div>
                    <small style="color: #666; font-size: 12px; display: block; margin-top: 5px;">
                        Leave empty to keep current password
                    </small>
                </div>
                
                <div class="form-group" style="display: flex; gap: 15px; margin-top: 30px;">
                    <button type="submit" name="update" class="btn-primary">Update Profile</button>
                    <a href="ngo_dashboard.php" class="btn-secondary">Back to Dashboard</a>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // Toggle password visibility
        function togglePassword() {
            const passwordField = document.getElementById('password');
            const toggleButton = document.querySelector('.toggle-password');
            
            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                toggleButton.textContent = '🙈';
            } else {
                passwordField.type = 'password';
                toggleButton.textContent = '👁️';
            }
        }
        
        // Status toggle functionality
        const statusToggle = document.getElementById('statusToggle');
        const statusText = document.getElementById('statusText');
        
        statusToggle.addEventListener('change', function() {
            if (this.checked) {
                statusText.textContent = 'Active';
                statusText.className = 'status-text status-active';
            } else {
                statusText.textContent = 'Inactive';
                statusText.className = 'status-text status-inactive';
            }
        });
        
        // Form validation
        document.getElementById('profileForm').addEventListener('submit', function(e) {
            const name = document.querySelector('input[name="name"]').value.trim();
            const email = document.querySelector('input[name="email"]').value.trim();
            const phone = document.querySelector('input[name="phone"]').value.trim();
            const password = document.getElementById('password').value.trim();
            
            if (!name || !email || !phone) {
                e.preventDefault();
                alert('Please fill in all required fields');
                return false;
            }
            
            // Email validation
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                e.preventDefault();
                alert('Please enter a valid email address');
                return false;
            }
            
            // Phone validation
            const phoneRegex = /^[0-9\-\+\s\(\)]{10,}$/;
            if (!phoneRegex.test(phone.replace(/\s/g, ''))) {
                e.preventDefault();
                alert('Please enter a valid phone number');
                return false;
            }
            
            // Password validation (if provided)
            if (password !== '' && password.length < 6) {
                e.preventDefault();
                alert('Password must be at least 6 characters long');
                return false;
            }
            
            // Confirm status change
            const currentStatus = "<?= $row['Status'] ?>";
            const newStatus = statusToggle.checked ? 'active' : 'inactive';
            
            if (currentStatus !== newStatus) {
                const confirmMessage = `Are you sure you want to change your account status to "${newStatus}"?`;
                if (!confirm(confirmMessage)) {
                    e.preventDefault();
                    return false;
                }
            }
            
            return true;
        });
        
        // Initialize status display
        window.addEventListener('DOMContentLoaded', function() {
            // Set initial status text color
            if (statusToggle.checked) {
                statusText.textContent = 'Active';
                statusText.className = 'status-text status-active';
            } else {
                statusText.textContent = 'Inactive';
                statusText.className = 'status-text status-inactive';
            }
        });
    </script>

</body>
</html>