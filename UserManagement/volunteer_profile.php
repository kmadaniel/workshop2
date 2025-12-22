<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != "volunteer") {
    header("Location: login.php");
    exit();
}

require_once "connection.php";

$user_id = $_SESSION['user_id'];
$msg = "";
$msg_type = "";

// ============================
// FETCH VOLUNTEER DATA WITH NGO INFO
// ============================
$sql = "SELECT 
            v.VolunteerID, 
            v.FullName, 
            v.Email, 
            v.Phone, 
            v.Address, 
            v.PasswordHash, 
            v.SkillCategory,
            v.AssignedNGO,
            n.NGOName,
            n.RegistrationNo
        FROM Volunteer v
        LEFT JOIN NGO n ON v.AssignedNGO = n.NGOID
        WHERE v.VolunteerID = ?";
        
$params = array($user_id);
$stmt = sqlsrv_query($conn, $sql, $params);

if ($stmt === false) {
    die(print_r(sqlsrv_errors(), true));
}

$row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

// ============================
// FETCH AVAILABLE NGOS FOR DROPDOWN
// ============================
$ngoList = [];
$ngoQuery = "SELECT NGOID, NGOName FROM NGO WHERE Status = 'active' ORDER BY NGOName ASC";
$ngoResult = sqlsrv_query($conn, $ngoQuery);

if ($ngoResult) {
    while ($ngo = sqlsrv_fetch_array($ngoResult, SQLSRV_FETCH_ASSOC)) {
        $ngoList[] = $ngo;
    }
}

// ============================
// HANDLE PROFILE UPDATE
// ============================
if (isset($_POST['update'])) {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $address = $_POST['address'] ?? null;
    $skill = $_POST['skill'];
    
    // Check if volunteer can change NGO
    $assignedNGO = $row['AssignedNGO'];
    if (isset($_POST['assignedNGO']) && !empty($_POST['assignedNGO'])) {
        $assignedNGO = $_POST['assignedNGO'];
    }
    
    // Password update is optional
    if (!empty($_POST['password'])) {
        $password = $_POST['password'];
        if (strlen($password) < 6) {
            $msg = "Password must be at least 6 characters long!";
            $msg_type = "error";
        } else {
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $update_sql = "UPDATE Volunteer SET 
                            FullName = ?, 
                            Email = ?, 
                            Phone = ?, 
                            Address = ?, 
                            PasswordHash = ?, 
                            SkillCategory = ?,
                            AssignedNGO = ?
                          WHERE VolunteerID = ?";
            $update_params = array($name, $email, $phone, $address, $passwordHash, $skill, $assignedNGO, $user_id);
        }
    } else {
        $update_sql = "UPDATE Volunteer SET 
                        FullName = ?, 
                        Email = ?, 
                        Phone = ?, 
                        Address = ?, 
                        SkillCategory = ?,
                        AssignedNGO = ?
                      WHERE VolunteerID = ?";
        $update_params = array($name, $email, $phone, $address, $skill, $assignedNGO, $user_id);
    }
    
    // Execute update if no password validation error
    if (empty($msg) || $msg_type != "error") {
        $update_stmt = sqlsrv_query($conn, $update_sql, $update_params);
        
        if ($update_stmt === false) {
            $msg = "Failed to update profile: " . print_r(sqlsrv_errors(), true);
            $msg_type = "error";
        } else {
            $_SESSION['name'] = $name;
            $msg = "Profile updated successfully!";
            $msg_type = "success";
            
            // Refresh the data
            $row['FullName'] = $name;
            $row['Email'] = $email;
            $row['Phone'] = $phone;
            $row['Address'] = $address;
            $row['SkillCategory'] = $skill;
            $row['AssignedNGO'] = $assignedNGO;
            
            // Refresh NGO name if changed
            if ($assignedNGO != $row['AssignedNGO']) {
                $ngoQuery = "SELECT NGOName FROM NGO WHERE NGOID = ?";
                $ngoParams = array($assignedNGO);
                $ngoStmt = sqlsrv_query($conn, $ngoQuery, $ngoParams);
                if ($ngoStmt && $ngoRow = sqlsrv_fetch_array($ngoStmt, SQLSRV_FETCH_ASSOC)) {
                    $row['NGOName'] = $ngoRow['NGOName'];
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Volunteer Profile - VolunteerHub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Keep Original Sidebar Styles */
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
            background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%);
            padding: 20px;
            min-height: 100vh;
            position: fixed;
        }
        
        .sidebar h4 {
            color: white;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid rgba(255,255,255,0.3);
        }
        
        .sidebar a {
            display: block;
            padding: 12px 15px;
            margin: 5px 0;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            transition: all 0.3s;
            background: rgba(255,255,255,0.1);
        }
        
        .sidebar a:hover {
            background: rgba(255,255,255,0.2);
            transform: translateX(5px);
        }
        
        .sidebar a.active {
            background: rgba(255,255,255,0.3);
            font-weight: 500;
        }
        
        /* NEW: Admin-Style Main Content */
        .content {
            flex: 1;
            padding: 30px;
            margin-left: 250px;
            overflow-y: auto;
            min-height: 100vh;
        }
        
        /* Profile Header - Admin Style */
        .profile-header {
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 25px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            border: 1px solid #e9ecef;
        }

        .profile-header h2 {
            color: #2c3e50;
            margin-bottom: 10px;
            font-size: 24px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* Toast Notification */
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
        }

        .toast {
            background: white;
            border-radius: 8px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
            padding: 15px 20px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 15px;
            min-width: 300px;
            animation: slideInRight 0.3s ease;
            border-left: 4px solid #27ae60;
        }

        .toast.toast-success {
            border-left-color: #27ae60;
        }

        .toast.toast-error {
            border-left-color: #e74c3c;
        }

        .toast-content {
            flex: 1;
        }

        .toast-close {
            background: none;
            border: none;
            color: #999;
            cursor: pointer;
            font-size: 18px;
        }

        /* Profile Cards */
        .profile-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            border: 1px solid #e9ecef;
            margin-bottom: 20px;
            height: 100%;
        }

        .profile-card h5 {
            color: #2c3e50;
            margin-bottom: 20px;
            font-size: 18px;
            display: flex;
            align-items: center;
            gap: 10px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f1f1f1;
        }

        .profile-avatar {
            width: 120px;
            height: 120px;
            margin: 0 auto 20px;
            border-radius: 50%;
            background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 48px;
            color: white;
            font-weight: bold;
        }

        .info-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .info-item {
            padding: 12px 0;
            border-bottom: 1px solid #f1f1f1;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .info-item:last-child {
            border-bottom: none;
        }

        .info-label {
            color: #7f8c8d;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .info-value {
            color: #2c3e50;
            font-weight: 500;
        }

        /* Form Styles */
        .form-label {
            color: #2c3e50;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .input-group {
            border-radius: 8px;
            overflow: hidden;
            border: 1px solid #e0e0e0;
        }

        .input-group:focus-within {
            border-color: #27ae60;
            box-shadow: 0 0 0 2px rgba(39, 174, 96, 0.2);
        }

        .input-group-text {
            background: #f8f9fa;
            border: none;
            color: #7f8c8d;
        }

        .form-control {
            border: none;
            padding: 12px 15px;
        }

        .form-control:focus {
            box-shadow: none;
        }

        .password-toggle-btn {
            border: none;
            background: #f8f9fa;
            color: #7f8c8d;
            cursor: pointer;
        }

        .password-toggle-btn:hover {
            color: #27ae60;
        }

        /* NGO Info Box */
        .ngo-info-box {
            background: linear-gradient(135deg, #e8f5e9 0%, #f1f8e9 100%);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 25px;
            border: 1px solid #c8e6c9;
        }

        .ngo-info-title {
            color: #2e7d32;
            font-weight: 600;
            margin-bottom: 15px;
            font-size: 18px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .ngo-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }

        .ngo-info-item {
            display: flex;
            flex-direction: column;
        }

        .ngo-info-label {
            font-size: 12px;
            color: #666;
            margin-bottom: 5px;
        }

        .ngo-info-value {
            font-size: 16px;
            font-weight: 500;
            color: #333;
        }

        /* Skill Badge */
        .skill-badge {
            display: inline-block;
            background: #e3f2fd;
            color: #1976d2;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 500;
        }

        /* Security Tips */
        .security-tips {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 10px;
            padding: 20px;
            margin-top: 20px;
            border-left: 4px solid #27ae60;
        }

        .security-tips h6 {
            color: #2c3e50;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .security-tips ul {
            padding-left: 20px;
            margin: 0;
        }

        .security-tips li {
            color: #7f8c8d;
            margin-bottom: 8px;
            font-size: 14px;
        }

        /* Volunteer Badge */
        .volunteer-badge {
            display: inline-block;
            background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%);
            color: white;
            padding: 8px 20px;
            border-radius: 25px;
            font-size: 16px;
            font-weight: 500;
            margin-left: 15px;
        }

        /* Profile Title */
        .profile-title {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
        }

        @keyframes slideInRight {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        /* Responsive for Sidebar */
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
            
            .ngo-info-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

    <!-- KEEP ORIGINAL SIDEBAR -->
   <div class="sidebar">
        <h4>Volunteer Panel</h4>
        <a href="volunteer_dashboard.php" class="active">🏠 Dashboard</a>
        <a href="volunteer_profile.php">👤 Profile</a>
        <a href="volunteer_assigned.php">🔍 View Opportunities</a>
        <a href="my_tasks.php">📋 My Tasks</a>
        <a href="volunteer_reports.php">📊 My Reports</a>
        <a href="logout.php" style="background: rgba(231, 76, 60, 0.2);">🚪 Logout</a>
    </div>

    <!-- NEW: Admin-Style Main Content -->
    <div class="content">
        <!-- Toast Notification -->
        <?php if ($msg): ?>
        <div class="toast-container">
            <div class="toast <?php echo $msg_type == 'success' ? 'toast-success' : 'toast-error'; ?>">
                <div class="toast-content">
                    <strong><?php echo $msg_type == 'success' ? 'Success!' : 'Error!'; ?></strong>
                    <p style="margin: 5px 0 0 0; font-size: 14px;"><?php echo htmlspecialchars($msg); ?></p>
                </div>
                <button class="toast-close" onclick="this.parentElement.remove()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
        <?php endif; ?>

        <!-- Profile Header -->
        <div class="profile-header">
            <div class="profile-title">
                <h2>
                    <i class="fas fa-user-edit"></i>
                    Edit Volunteer Profile
                </h2>
                <span class="volunteer-badge">VOLUNTEER</span>
            </div>
            <p class="text-muted mb-0">Manage your volunteer information and settings</p>
            <div class="mt-3">
                <span class="skill-badge">
                    <i class="fas fa-star me-1"></i>
                    Skill: <?= htmlspecialchars($row['SkillCategory'] ?? 'Not specified') ?>
                </span>
            </div>
        </div>

        <!-- NGO Information Box -->
        <div class="ngo-info-box">
            <div class="ngo-info-title">
                <i class="fas fa-building"></i>
                Assigned NGO Information
            </div>
            <div class="ngo-info-grid">
                <div class="ngo-info-item">
                    <span class="ngo-info-label">NGO Name</span>
                    <span class="ngo-info-value"><?= htmlspecialchars($row['NGOName'] ?? 'Not assigned') ?></span>
                </div>
                <div class="ngo-info-item">
                    <span class="ngo-info-label">Registration No.</span>
                    <span class="ngo-info-value"><?= htmlspecialchars($row['RegistrationNo'] ?? 'N/A') ?></span>
                </div>
                <div class="ngo-info-item">
                    <span class="ngo-info-label">Your Role</span>
                    <span class="ngo-info-value">Volunteer (View Only)</span>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Left Column: Edit Form -->
            <div class="col-lg-8">
                <div class="profile-card">
                    <h5><i class="fas fa-edit"></i> Edit Profile Information</h5>
                    
                    <form method="POST" id="profileForm">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Full Name <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-user"></i>
                                    </span>
                                    <input type="text" name="name" value="<?= htmlspecialchars($row['FullName']); ?>" 
                                           class="form-control" required>
                                </div>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email Address <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-envelope"></i>
                                    </span>
                                    <input type="email" name="email" value="<?= htmlspecialchars($row['Email']); ?>" 
                                           class="form-control" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Phone Number <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-phone"></i>
                                    </span>
                                    <input type="text" name="phone" value="<?= htmlspecialchars($row['Phone'] ?? ''); ?>" 
                                           class="form-control" required>
                                </div>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Address</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-map-marker-alt"></i>
                                    </span>
                                    <input type="text" name="address" value="<?= htmlspecialchars($row['Address'] ?? ''); ?>" 
                                           class="form-control" placeholder="Enter your address">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Skill Category <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-star"></i>
                                    </span>
                                    <select name="skill" class="form-control" required>
                                        <option value="">-- Select Your Skill --</option>
                                        <option value="Medical" <?= ($row['SkillCategory'] == 'Medical') ? 'selected' : '' ?>>Medical</option>
                                        <option value="Helper" <?= ($row['SkillCategory'] == 'Helper') ? 'selected' : '' ?>>Helper</option>
                                        <option value="Rescue" <?= ($row['SkillCategory'] == 'Rescue') ? 'selected' : '' ?>>Rescue</option>
                                        <option value="Logistics" <?= ($row['SkillCategory'] == 'Logistics') ? 'selected' : '' ?>>Logistics</option>
                                        <option value="Technical" <?= ($row['SkillCategory'] == 'Technical') ? 'selected' : '' ?>>Technical</option>
                                        <option value="Driver" <?= ($row['SkillCategory'] == 'Driver') ? 'selected' : '' ?>>Driver</option>
                                        <option value="Food Supply" <?= ($row['SkillCategory'] == 'Food Supply') ? 'selected' : '' ?>>Food Supply</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Change Assigned NGO (Optional)</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-exchange-alt"></i>
                                    </span>
                                    <select name="assignedNGO" class="form-control">
                                        <option value="">-- Keep Current NGO --</option>
                                        <?php foreach ($ngoList as $ngo) { ?>
                                            <option value="<?= htmlspecialchars($ngo['NGOID']) ?>" 
                                                <?= ($row['AssignedNGO'] == $ngo['NGOID']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($ngo['NGOName']) ?>
                                            </option>
                                        <?php } ?>
                                    </select>
                                </div>
                                <small class="text-muted mt-2 d-block">
                                    Note: Changing NGO will affect your dashboard view and available opportunities
                                </small>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label class="form-label">Change Password (Optional)</label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-lock"></i>
                                </span>
                                <input type="password" name="password" id="password" 
                                       class="form-control" placeholder="Enter new password (min 6 characters)">
                                <button class="btn password-toggle-btn" type="button" onclick="togglePassword()">
                                    <i class="fas fa-eye" id="toggleIcon"></i>
                                </button>
                            </div>
                            <small class="text-muted mt-2 d-block">Leave empty if you don't want to change password</small>
                        </div>

                        <div class="d-flex justify-content-between align-items-center mt-4 pt-3 border-top">
                            <div>
                                <button type="submit" name="update" class="btn btn-success px-4">
                                    <i class="fas fa-save me-2"></i> Update Profile
                                </button>
                                <a href="volunteer_dashboard.php" class="btn btn-outline-secondary ms-2">
                                    <i class="fas fa-arrow-left me-2"></i> Back to Dashboard
                                </a>
                            </div>
                            <a href="#" class="btn btn-outline-danger" onclick="return confirm('Are you sure you want to delete your account? This action cannot be undone.')">
                                <i class="fas fa-trash-alt me-2"></i> Delete Account
                            </a>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Right Column: Profile Summary -->
            <div class="col-lg-4">
                <div class="profile-card">
                    <h5><i class="fas fa-user-circle"></i> Profile Summary</h5>
                    
                    <div class="text-center mb-4">
                        <div class="profile-avatar">
                            <?php echo strtoupper(substr($row['FullName'], 0, 1)); ?>
                        </div>
                        <h5 class="mb-1"><?= htmlspecialchars($row['FullName']); ?></h5>
                        <p class="text-muted"><?= htmlspecialchars($row['Email']); ?></p>
                        <span class="skill-badge"><?= htmlspecialchars($row['SkillCategory'] ?? 'Not set') ?></span>
                    </div>
                    
                    <ul class="info-list">
                        <li class="info-item">
                            <span class="info-label">
                                <i class="fas fa-id-badge text-success"></i>
                                Volunteer ID
                            </span>
                            <span class="info-value">#<?= htmlspecialchars($user_id); ?></span>
                        </li>
                        <li class="info-item">
                            <span class="info-label">
                                <i class="fas fa-phone text-success"></i>
                                Phone
                            </span>
                            <span class="info-value"><?= htmlspecialchars($row['Phone'] ?? 'Not set'); ?></span>
                        </li>
                        <li class="info-item">
                            <span class="info-label">
                                <i class="fas fa-building text-success"></i>
                                Assigned NGO
                            </span>
                            <span class="info-value"><?= htmlspecialchars($row['NGOName'] ?? 'Not assigned'); ?></span>
                        </li>
                        <li class="info-item">
                            <span class="info-label">
                                <i class="fas fa-calendar-alt text-success"></i>
                                Member Since
                            </span>
                            <span class="info-value">-</span>
                        </li>
                    </ul>
                    
                    <div class="security-tips">
                        <h6><i class="fas fa-shield-alt"></i> Security Tips</h6>
                        <ul>
                            <li>Use a strong, unique password</li>
                            <li>Never share your login credentials</li>
                            <li>Log out when using public computers</li>
                            <li>Update your skills regularly</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Password toggle
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

        // Auto-remove toast after 5 seconds
        setTimeout(function() {
            var toast = document.querySelector('.toast');
            if(toast) {
                toast.style.animation = 'slideInRight 0.3s ease reverse';
                setTimeout(() => toast.remove(), 300);
            }
        }, 5000);

        // Form validation
        document.getElementById('profileForm').addEventListener('submit', function(e) {
            const name = document.querySelector('input[name="name"]').value.trim();
            const email = document.querySelector('input[name="email"]').value.trim();
            const phone = document.querySelector('input[name="phone"]').value.trim();
            const skill = document.querySelector('select[name="skill"]').value;
            const password = document.getElementById('password').value.trim();
            
            if (!name || !email || !phone || !skill) {
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
            
            // NGO change confirmation
            const ngoSelect = document.querySelector('select[name="assignedNGO"]');
            const currentNGO = "<?= $row['AssignedNGO'] ?>";
            
            if (ngoSelect.value && ngoSelect.value !== currentNGO) {
                const newNGOName = ngoSelect.options[ngoSelect.selectedIndex].text;
                if (!confirm(`Are you sure you want to change your assigned NGO to "${newNGOName}"?`)) {
                    e.preventDefault();
                    return false;
                }
            }
            
            return true;
        });
    </script>

</body>
</html>