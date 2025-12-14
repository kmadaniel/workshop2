<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once "connection.php";

$message = "";

// ============================
// FETCH NGO LIST FOR DROPDOWN
// ============================
$ngoList = [];
$ngoQuery = "SELECT NGOID, NGOName FROM NGO ORDER BY NGOName ASC";
$ngoResult = sqlsrv_query($conn, $ngoQuery);

if ($ngoResult) {
    while ($row = sqlsrv_fetch_array($ngoResult, SQLSRV_FETCH_ASSOC)) {
        $ngoList[] = $row;
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $role = $_POST['role'];
    $fullname = trim($_POST['fullname']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $address = $_POST['address'] ?? null;
    $password = $_POST['password'];
    
    // Hash password dengan bcrypt
    $passwordHash = password_hash($password, PASSWORD_BCRYPT);

    // ============================
    // NGO REGISTRATION
    // ============================
    if ($role == "ngo") {
        // Generate Registration No
        $query = "SELECT TOP 1 RegistrationNo FROM NGO ORDER BY NGOID DESC";
        $result = sqlsrv_query($conn, $query);

        $newRegNo = "REG001";

        if ($result && $row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC)) {
            $lastReg = $row['RegistrationNo'];
            $num = (int)substr($lastReg, 3);
            $num++;
            $newRegNo = "REG" . str_pad($num, 3, "0", STR_PAD_LEFT);
        }

        // Check if NGO email already exists
        $checkSql = "SELECT NGOID FROM NGO WHERE Email = ?";
        $checkParams = array($email);
        $checkStmt = sqlsrv_query($conn, $checkSql, $checkParams);
        
        if (sqlsrv_has_rows($checkStmt)) {
            $message = "Error: Email already registered as NGO";
        } else {
            // NGO INSERT QUERY
            $sql = "INSERT INTO NGO (NGOName, RegistrationNo, Email, Phone, Address, PasswordHash, CreatedAt) 
                    VALUES (?, ?, ?, ?, ?, ?, GETDATE())";
            
            $params = array($fullname, $newRegNo, $email, $phone, $address, $passwordHash);
            
            $stmt = sqlsrv_query($conn, $sql, $params);
            
            if ($stmt) {
                $message = "✅ NGO registered successfully! Registration No: <strong>$newRegNo</strong>";
                // Clear form after successful registration
                $_POST = array();
            } else {
                $error = sqlsrv_errors();
                $message = "❌ Error: " . $error[0]['message'];
            }
        }
    }

    // ============================
    // VOLUNTEER REGISTRATION
    // ============================
    if ($role == "volunteer") {
        $skill = $_POST['skill'];
        $assignedNGO = $_POST['assignedNGO'];

        // Check if volunteer email already exists
        $checkSql = "SELECT VolunteerID FROM Volunteer WHERE Email = ?";
        $checkParams = array($email);
        $checkStmt = sqlsrv_query($conn, $checkSql, $checkParams);
        
        if (sqlsrv_has_rows($checkStmt)) {
            $message = "❌ Error: Email already registered as volunteer";
        } else {
            // VOLUNTEER INSERT QUERY (NO CreatedAt)
            $sql = "INSERT INTO Volunteer (FullName, Email, Phone, Address, PasswordHash, SkillCategory, AssignedNGO) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)";
            
            $params = array($fullname, $email, $phone, $address, $passwordHash, $skill, $assignedNGO);
            
            $stmt = sqlsrv_query($conn, $sql, $params);
            
            if ($stmt) {
                $message = "✅ Volunteer registered successfully!";
                $_POST = array();
            } else {
                $error = sqlsrv_errors();
                $message = "❌ Error: " . $error[0]['message'];
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Register - VolunteerHub</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        
        .container {
            width: 100%;
            max-width: 500px;
            background: rgba(255, 255, 255, 0.95);
            padding: 40px 30px;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }
        
        h2 {
            text-align: center;
            color: #333;
            margin-bottom: 30px;
            font-size: 28px;
            font-weight: 600;
        }
        
        .message {
            background-color: #e7f4ff;
            color: #0366d6;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
            border: 1px solid #b3d9ff;
            font-size: 14px;
        }
        
        .error-message {
            background-color: #fee;
            color: #c33;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
            border: 1px solid #fcc;
            font-size: 14px;
        }
        
        .success-message {
            background-color: #d4edda;
            color: #155724;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
            border: 1px solid #c3e6cb;
            font-size: 14px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #555;
            font-weight: 500;
            font-size: 14px;
        }
        
        input[type="text"],
        input[type="email"],
        input[type="password"],
        input[type="tel"],
        select {
            width: 100%;
            padding: 14px;
            border: 2px solid #e1e5e9;
            border-radius: 10px;
            font-size: 16px;
            transition: all 0.3s;
            background: #f8f9fa;
        }
        
        input[type="text"]:focus,
        input[type="email"]:focus,
        input[type="password"]:focus,
        input[type="tel"]:focus,
        select:focus {
            outline: none;
            border-color: #667eea;
            background: #fff;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
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
            padding: 0;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .conditional-field {
            display: none;
            animation: fadeIn 0.3s ease-in;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .button-group {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        
        button[type="submit"] {
            flex: 1;
            padding: 15px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        button[type="submit"]:hover {
            transform: translateY(-2px);
            box-shadow: 0 7px 14px rgba(102, 126, 234, 0.25);
        }
        
        .btn-back {
            flex: 1;
            padding: 15px;
            background: #f8f9fa;
            color: #495057;
            border: 2px solid #e1e5e9;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            text-align: center;
        }
        
        .btn-back:hover {
            background: #e9ecef;
            transform: translateY(-2px);
            box-shadow: 0 5px 10px rgba(0, 0, 0, 0.1);
        }
        
        .login-link {
            text-align: center;
            margin-top: 25px;
            color: #666;
            font-size: 14px;
        }
        
        .login-link a {
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
        }
        
        .login-link a:hover {
            text-decoration: underline;
        }
        
        .logo {
            text-align: center;
            margin-bottom: 20px;
        }
        
        .logo h1 {
            color: #667eea;
            font-size: 32px;
            font-weight: 700;
        }
        
        .role-buttons {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .role-btn {
            flex: 1;
            padding: 12px;
            border: 2px solid #e1e5e9;
            border-radius: 10px;
            background: #f8f9fa;
            cursor: pointer;
            text-align: center;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .role-btn:hover {
            background: #e9ecef;
        }
        
        .role-btn.active {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }
        
        .required {
            color: #ff4757;
        }
        
        .password-strength {
            height: 5px;
            margin-top: 5px;
            border-radius: 3px;
            background: #eee;
            overflow: hidden;
        }
        
        .strength-meter {
            height: 100%;
            width: 0%;
            transition: width 0.3s, background 0.3s;
        }
        
        .weak { background: #ff4757; width: 30%; }
        .fair { background: #ffa502; width: 60%; }
        .good { background: #2ed573; width: 100%; }
        
        @media (max-width: 480px) {
            .container {
                padding: 30px 20px;
            }
            
            h2 {
                font-size: 24px;
            }
            
            .role-buttons {
                flex-direction: column;
            }
            
            .button-group {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>

<div class="container">
    <div class="logo">
        <h1>VolunteerHub</h1>
    </div>
    
    <h2>Create Account</h2>

    <?php if ($message) { ?>
        <div class="<?= 
            strpos($message, '✅') !== false ? 'success-message' : 
            (strpos($message, '❌') !== false ? 'error-message' : 'message') 
        ?>">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php } ?>

    <form method="POST" action="" id="registerForm">
        <!-- Role Selection -->
        <div class="form-group">
            <label>Register as: <span class="required">*</span></label>
            <div class="role-buttons">
                <div class="role-btn" data-role="ngo">NGO</div>
                <div class="role-btn" data-role="volunteer">Volunteer</div>
            </div>
            <input type="hidden" id="role" name="role" required>
        </div>

        <!-- Basic Information -->
        <div class="form-group">
            <label for="fullname">
                <span id="name-label">Full Name</span> <span class="required">*</span>
            </label>
            <input type="text" id="fullname" name="fullname" 
                   placeholder="Enter your full name" 
                   value="<?= isset($_POST['fullname']) ? htmlspecialchars($_POST['fullname']) : '' ?>" 
                   required>
        </div>
        
        <div class="form-group">
            <label for="email">Email Address <span class="required">*</span></label>
            <input type="email" id="email" name="email" 
                   placeholder="Enter your email" 
                   value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>" 
                   required>
        </div>
        
        <div class="form-group">
            <label for="phone">Phone Number <span class="required">*</span></label>
            <input type="tel" id="phone" name="phone" 
                   placeholder="Enter your phone number" 
                   value="<?= isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : '' ?>" 
                   required>
        </div>

        <!-- Address (for NGO & Volunteer) -->
        <div class="form-group conditional-field" id="address_group">
            <label for="address">Address</label>
            <input type="text" id="address" name="address" 
                   placeholder="Enter your address"
                   value="<?= isset($_POST['address']) ? htmlspecialchars($_POST['address']) : '' ?>">
        </div>

        <!-- Skill (for Volunteer only) -->
        <div class="form-group conditional-field" id="skill_group">
            <label for="skill">Skill Category <span class="required">*</span></label>
            <select id="skill" name="skill">
                <option value="">-- Select Your Skill --</option>
                <option value="Medical" <?= (isset($_POST['skill']) && $_POST['skill'] == 'Medical') ? 'selected' : '' ?>>Medical</option>
                <option value="Helper" <?= (isset($_POST['skill']) && $_POST['skill'] == 'Helper') ? 'selected' : '' ?>>Helper</option>
                <option value="Rescue" <?= (isset($_POST['skill']) && $_POST['skill'] == 'Rescue') ? 'selected' : '' ?>>Rescue</option>
                <option value="Logistics" <?= (isset($_POST['skill']) && $_POST['skill'] == 'Logistics') ? 'selected' : '' ?>>Logistics</option>
                <option value="Technical" <?= (isset($_POST['skill']) && $_POST['skill'] == 'Technical') ? 'selected' : '' ?>>Technical</option>
                <option value="Driver" <?= (isset($_POST['skill']) && $_POST['skill'] == 'Driver') ? 'selected' : '' ?>>Driver</option>
                <option value="Food Supply" <?= (isset($_POST['skill']) && $_POST['skill'] == 'Food Supply') ? 'selected' : '' ?>>Food Supply</option>
            </select>
        </div>

        <!-- NGO Selection (for Volunteer only) -->
        <div class="form-group conditional-field" id="ngo_group">
            <label for="assignedNGO">Select NGO <span class="required">*</span></label>
            <select id="assignedNGO" name="assignedNGO">
                <option value="">-- Choose an NGO --</option>
                <?php foreach ($ngoList as $ngo) { ?>
                    <option value="<?= htmlspecialchars($ngo['NGOID']) ?>" 
                        <?= (isset($_POST['assignedNGO']) && $_POST['assignedNGO'] == $ngo['NGOID']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($ngo['NGOName']) ?>
                    </option>
                <?php } ?>
            </select>
        </div>

        <!-- Password -->
        <div class="form-group">
            <label for="password">Password <span class="required">*</span></label>
            <div class="password-container">
                <input type="password" id="password" name="password" 
                       placeholder="Enter your password (min 6 characters)" 
                       required>
                <button type="button" class="toggle-password" onclick="togglePassword()">👁️</button>
            </div>
            <div class="password-strength">
                <div class="strength-meter" id="strengthMeter"></div>
            </div>
            <small style="color: #666; font-size: 12px; display: block; margin-top: 5px;">
                Password will be securely hashed using bcrypt
            </small>
        </div>

        <!-- Buttons Group -->
        <div class="button-group">
            <button type="submit" id="submitBtn">Create Account</button>
            <a href="main_page.php" class="btn-back">← Back to Main Page</a>
        </div>
        
        <div class="login-link">
            Already have an account? 
            <a href="login.php">Login here</a>
        </div>
    </form>
</div>

<script>
    // Role selection with buttons
    document.querySelectorAll('.role-btn').forEach(button => {
        button.addEventListener('click', function() {
            const role = this.getAttribute('data-role');
            
            // Update active state
            document.querySelectorAll('.role-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            this.classList.add('active');
            
            // Update hidden input
            document.getElementById('role').value = role;
            
            // Update form based on role
            updateForm(role);
        });
    });

    function updateForm(role) {
        // Update label for name field
        const nameLabel = document.getElementById('name-label');
        nameLabel.textContent = role === 'ngo' ? 'NGO Name' : 'Full Name';
        
        // Show/hide fields based on role
        const addressGroup = document.getElementById('address_group');
        const skillGroup = document.getElementById('skill_group');
        const ngoGroup = document.getElementById('ngo_group');
        
        // Reset all conditional fields
        [addressGroup, skillGroup, ngoGroup].forEach(group => {
            group.style.display = 'none';
            group.querySelectorAll('input, select').forEach(input => {
                if (input.getAttribute('name') !== 'address') {
                    input.required = false;
                }
            });
        });
        
        // Show address for both NGO and Volunteer
        if (role === 'ngo' || role === 'volunteer') {
            addressGroup.style.display = 'block';
        }
        
        // Show skill and NGO selection only for Volunteer
        if (role === 'volunteer') {
            skillGroup.style.display = 'block';
            ngoGroup.style.display = 'block';
            
            // Make these fields required
            document.getElementById('skill').required = true;
            document.getElementById('assignedNGO').required = true;
        }
    }

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
    
    // Password strength checker
    document.getElementById('password').addEventListener('input', function(e) {
        const password = e.target.value;
        const strengthMeter = document.getElementById('strengthMeter');
        
        // Reset
        strengthMeter.className = 'strength-meter';
        strengthMeter.style.width = '0%';
        
        if (password.length === 0) return;
        
        let strength = 0;
        
        // Length check
        if (password.length >= 6) strength++;
        if (password.length >= 8) strength++;
        
        // Character variety
        if (/[A-Z]/.test(password)) strength++; // uppercase
        if (/[0-9]/.test(password)) strength++; // numbers
        if (/[^A-Za-z0-9]/.test(password)) strength++; // special chars
        
        // Update meter
        if (strength <= 2) {
            strengthMeter.classList.add('weak');
        } else if (strength <= 4) {
            strengthMeter.classList.add('fair');
        } else {
            strengthMeter.classList.add('good');
        }
    });
    
    // Form validation
    document.getElementById('registerForm').addEventListener('submit', function(e) {
        const role = document.getElementById('role').value;
        const fullname = document.getElementById('fullname').value.trim();
        const email = document.getElementById('email').value.trim();
        const phone = document.getElementById('phone').value.trim();
        const password = document.getElementById('password').value.trim();
        const submitBtn = document.getElementById('submitBtn');
        
        // Disable button to prevent double submission
        submitBtn.disabled = true;
        submitBtn.innerHTML = 'Creating Account...';
        
        // Basic validation
        if (!role) {
            e.preventDefault();
            alert('Please select a role (NGO or Volunteer)');
            submitBtn.disabled = false;
            submitBtn.innerHTML = 'Create Account';
            return false;
        }
        
        if (!fullname || !email || !phone || !password) {
            e.preventDefault();
            alert('Please fill in all required fields');
            submitBtn.disabled = false;
            submitBtn.innerHTML = 'Create Account';
            return false;
        }
        
        // Email validation
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(email)) {
            e.preventDefault();
            alert('Please enter a valid email address');
            submitBtn.disabled = false;
            submitBtn.innerHTML = 'Create Account';
            return false;
        }
        
        // Phone validation (basic)
        const phoneRegex = /^[0-9\-\+\s\(\)]{10,}$/;
        if (!phoneRegex.test(phone.replace(/\s/g, ''))) {
            e.preventDefault();
            alert('Please enter a valid phone number (at least 10 digits)');
            submitBtn.disabled = false;
            submitBtn.innerHTML = 'Create Account';
            return false;
        }
        
        // Password validation
        if (password.length < 6) {
            e.preventDefault();
            alert('Password must be at least 6 characters long');
            submitBtn.disabled = false;
            submitBtn.innerHTML = 'Create Account';
            return false;
        }
        
        // Role-specific validation
        if (role === 'volunteer') {
            const skill = document.getElementById('skill').value;
            const assignedNGO = document.getElementById('assignedNGO').value;
            
            if (!skill || !assignedNGO) {
                e.preventDefault();
                alert('Please select your skill category and assigned NGO');
                submitBtn.disabled = false;
                submitBtn.innerHTML = 'Create Account';
                return false;
            }
        }
        
        // If all validations pass, allow form submission
        return true;
    });
    
    // Auto-select role if coming back with error
    window.addEventListener('DOMContentLoaded', function() {
        const roleInput = document.getElementById('role');
        if (roleInput.value) {
            // Find and activate the corresponding role button
            const roleBtn = document.querySelector(`.role-btn[data-role="${roleInput.value}"]`);
            if (roleBtn) {
                roleBtn.click();
            }
        }
    });
</script>

</body>
</html>