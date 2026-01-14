<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once "connection.php";

$message = "";

// ============================
// PASSWORD STRENGTH VALIDATION FUNCTION
// ============================
function validatePasswordStrength($password) {
    $errors = [];
    
    // Minimum 12 characters (NIST recommendation)
    if (strlen($password) < 12) {
        $errors[] = "Password must be at least 12 characters long";
    }
    
    // Maximum length (to prevent DoS attacks)
    if (strlen($password) > 128) {
        $errors[] = "Password cannot exceed 128 characters";
    }
    
    // Complexity requirements
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = "Password must contain at least one uppercase letter";
    }
    
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = "Password must contain at least one lowercase letter";
    }
    
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = "Password must contain at least one number";
    }
    
    if (!preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password)) {
        $errors[] = "Password must contain at least one special character";
    }
    
    // Check for repeated characters (more than 3 same chars in a row)
    if (preg_match('/(.)\1{3,}/', $password)) {
        $errors[] = "Password contains too many repeated characters";
    }
    
    // Check for sequential characters (3+ in sequence)
    $lowerPassword = strtolower($password);
    $sequentialPatterns = [
        '123', '234', '345', '456', '567', '678', '789', '890',
        'abc', 'bcd', 'cde', 'def', 'efg', 'fgh', 'ghi', 'hij',
        'ijk', 'jkl', 'klm', 'lmn', 'mno', 'nop', 'opq', 'pqr',
        'qrs', 'rst', 'stu', 'tuv', 'uvw', 'vwx', 'wxy', 'xyz',
        'qwe', 'wer', 'ert', 'rty', 'tyu', 'yui', 'uio', 'iop',
        'asd', 'sdf', 'dfg', 'fgh', 'ghj', 'hjk', 'jkl',
        'zxc', 'xcv', 'cvb', 'vbn', 'bnm'
    ];
    
    foreach ($sequentialPatterns as $pattern) {
        if (strpos($lowerPassword, $pattern) !== false) {
            $errors[] = "Password contains obvious sequences";
            break;
        }
    }
    
    // Common password blacklist (top 100 weak passwords)
    $commonPasswords = [
        'password', '123456', '123456789', '12345678', '12345',
        '1234567', 'password1', '123123', '111111', 'qwerty',
        'abc123', 'qwerty123', 'admin', 'letmein', 'welcome',
        'monkey', 'dragon', 'sunshine', 'master', 'hello',
        'freedom', 'whatever', 'qazwsx', 'trustno1', '654321',
        'jordan23', 'harley', 'password123', '1234', 'superman',
        '1q2w3e4r', 'iloveyou', '123qwe', 'zaq12wsx', 'football',
        'baseball', 'welcome1', 'princess', 'login', 'solo',
        '1qaz2wsx', 'qwertyuiop', 'ashley', 'mustang', '121212',
        'starwars', 'bailey', 'access', 'flower', '555555',
        'passw0rd', 'shadow', 'lovely', '7777777', 'michael',
        '!@#$%^&*', 'jennifer', 'joshua', 'ginger', 'matthew',
        'abcd1234', 'tigger', '123456a', 'samsung', 'chocolate',
        'michelle', 'daniel', 'mercedes', 'oliver', 'andrew',
        'angel', 'buster', 'jessica', 'amanda', 'orange',
        'dallas', 'enter', 'pepper', 'jordan', 'lakers',
        'justin', 'banana', 'driver', 'marine', 'fishing',
        'hannah', 'thomas', 'soccer', 'purple', 'maggie',
        'peanut', 'austin', '131313', 'madison', 'charlie',
        'secret', '888888', 'diamond', 'william', 'butter',
        'robert', 'computer', 'corvette', 'coffee', 'batman'
    ];
    
    if (in_array(strtolower($password), $commonPasswords)) {
        $errors[] = "Password is too common. Please choose a stronger password";
    }
    
    // Check if password contains username/email parts (if available)
    if (isset($_POST['email'])) {
        $emailParts = explode('@', $_POST['email']);
        $username = strtolower($emailParts[0]);
        
        if (strlen($username) >= 3 && strpos(strtolower($password), $username) !== false) {
            $errors[] = "Password should not contain your username/email";
        }
    }
    
    return $errors;
}

// ============================
// FETCH NGO LIST FOR DROPDOWN
// ============================
$ngoList = [];
$ngoQuery = "SELECT NGOID, NGOName FROM NGO WHERE status IN ('Approved', 'Active', '') ORDER BY NGOName ASC";
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
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // ============================
    // FORM VALIDATION
    // ============================
    error_log("=== REGISTRATION PROCESS START ===");
    error_log("Role: " . $role);
    error_log("Email: " . $email);
    
    // 1. Validate required fields
    if (empty($role) || empty($fullname) || empty($email) || empty($phone) || empty($password)) {
        $message = "❌ Error: Please fill in all required fields";
    }
    // 2. Validate email format
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "❌ Error: Please enter a valid email address";
    }
    // 3. Validate password confirmation
    elseif ($password !== $confirm_password) {
        $message = "❌ Error: Passwords do not match";
    }
    // 4. Validate password strength
    else {
        $passwordErrors = validatePasswordStrength($password);
        if (!empty($passwordErrors)) {
            $message = "❌ Password Error:<br>• " . implode("<br>• ", $passwordErrors);
        }
    }
    
    // If validation failed, stop processing
    if (!empty($message)) {
        error_log("Validation failed: " . $message);
    } else {
        // ============================
        // SECURE PASSWORD HASHING
        // ============================
        error_log("Password validation passed");
        
        // Generate strong bcrypt hash with increased cost factor
        $options = [
            'cost' => 12, // Increased from default 10 (2^12 iterations)
        ];
        
        $passwordHash = password_hash($password, PASSWORD_BCRYPT, $options);
        
        // Verify hash was created properly
        if ($passwordHash === false) {
            error_log("CRITICAL: Failed to generate bcrypt hash for email: " . $email);
            $message = "❌ System error: Unable to create secure account. Please try again later.";
        } else {
            // Verify the hash can be verified (sanity check)
            if (!password_verify($password, $passwordHash)) {
                error_log("CRITICAL: Hash verification failed for new account: " . $email);
                $message = "❌ System error: Password security check failed. Please try again.";
            } else {
                error_log("Generated bcrypt hash (first 30 chars): " . substr($passwordHash, 0, 30) . "...");
                error_log("Hash length: " . strlen($passwordHash));
                
                // ============================
                // NGO REGISTRATION
                // ============================
                if ($role == "ngo") {
                    // Check if NGO email already exists
                    $checkSql = "SELECT NGOID FROM NGO WHERE Email = ?";
                    $checkParams = array($email);
                    $checkStmt = sqlsrv_query($conn, $checkSql, $checkParams);
                    
                    if ($checkStmt && sqlsrv_has_rows($checkStmt)) {
                        $message = "❌ Error: Email already registered as NGO";
                    } else {
                        // Check if NGO name already exists
                        $checkNameSql = "SELECT NGOID FROM NGO WHERE NGOName = ?";
                        $checkNameStmt = sqlsrv_query($conn, $checkNameSql, array($fullname));
                        
                        if ($checkNameStmt && sqlsrv_has_rows($checkNameStmt)) {
                            $message = "❌ Error: NGO name already registered";
                        } else {
                            // Generate Registration No
                            $query = "SELECT TOP 1 RegistrationNo FROM NGO ORDER BY NGOID DESC";
                            $result = sqlsrv_query($conn, $query);

                            $newRegNo = "REG001";

                            if ($result && $row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC)) {
                                $lastReg = $row['RegistrationNo'];
                                if (!empty($lastReg) && substr($lastReg, 0, 3) === 'REG') {
                                    $num = (int)substr($lastReg, 3);
                                    $num++;
                                    $newRegNo = "REG" . str_pad($num, 3, "0", STR_PAD_LEFT);
                                }
                            }

                            // ✅ FIXED: NGO INSERT QUERY WITHOUT CreatedAt
                            $sql = "INSERT INTO NGO (NGOName, RegistrationNo, Email, Phone, Address, PasswordHash, status) 
                                    VALUES (?, ?, ?, ?, ?, ?, 'Pending')";
                            
                            $params = array($fullname, $newRegNo, $email, $phone, $address, $passwordHash);
                            
                            error_log("Inserting NGO with params: " . print_r(array(
                                'name' => $fullname,
                                'email' => $email,
                                'regno' => $newRegNo
                            ), true));
                            
                            $stmt = sqlsrv_query($conn, $sql, $params);
                            
                            if ($stmt) {
                                // Verify the stored hash
                                $verifySql = "SELECT PasswordHash FROM NGO WHERE Email = ?";
                                $verifyStmt = sqlsrv_query($conn, $verifySql, array($email));
                                
                                if ($verifyStmt && sqlsrv_has_rows($verifyStmt)) {
                                    $verifyRow = sqlsrv_fetch_array($verifyStmt, SQLSRV_FETCH_ASSOC);
                                    $storedHash = $verifyRow['PasswordHash'];
                                    
                                    error_log("Stored hash verified (first 30 chars): " . substr($storedHash, 0, 30) . "...");
                                }
                                
                                $message = "✅ NGO registered successfully! Registration No: <strong>$newRegNo</strong><br><br>
                                           ⏳ <strong>Your account is pending admin approval.</strong><br>
                                           You will be able to login only after your account is approved by an administrator.";
                                
                                // Clear form
                                $_POST = array();
                                
                                error_log("=== NGO REGISTRATION SUCCESS ===");
                            } else {
                                $error = sqlsrv_errors();
                                $message = "❌ Error: " . $error[0]['message'];
                                error_log("SQL Error: " . print_r($error, true));
                            }
                        }
                    }
                }

                // ============================
                // VOLUNTEER REGISTRATION
                // ============================
                if ($role == "volunteer") {
                    // Get skills as array and convert to string
                    $skillsArray = $_POST['skills'] ?? [];
                    
                    // Validate at least one skill selected
                    if (empty($skillsArray)) {
                        $message = "❌ Error: Please select at least one skill";
                    } else {
                        // Check if volunteer email already exists
                        $checkSql = "SELECT VolunteerID FROM Volunteer WHERE Email = ?";
                        $checkParams = array($email);
                        $checkStmt = sqlsrv_query($conn, $checkSql, $checkParams);
                        
                        if ($checkStmt && sqlsrv_has_rows($checkStmt)) {
                            $message = "❌ Error: Email already registered as volunteer";
                        } else {
                            // Convert array to comma-separated string
                            $skills = implode(", ", $skillsArray);
                            $assignedNGO = $_POST['assignedNGO'];

                            // Validate NGO selection
                            if (empty($assignedNGO)) {
                                $message = "❌ Error: Please select an NGO";
                            } else {
                                // ✅ FIXED: VOLUNTEER INSERT QUERY WITHOUT CreatedAt
                                $sql = "INSERT INTO Volunteer (FullName, Email, Phone, Address, PasswordHash, SkillCategory, AssignedNGO) 
                                        VALUES (?, ?, ?, ?, ?, ?, ?)";
                                
                                $params = array($fullname, $email, $phone, $address, $passwordHash, $skills, $assignedNGO);
                                
                                error_log("Inserting Volunteer with params: " . print_r(array(
                                    'name' => $fullname,
                                    'email' => $email,
                                    'skills' => $skills,
                                    'ngo_id' => $assignedNGO
                                ), true));
                                
                                $stmt = sqlsrv_query($conn, $sql, $params);
                                
                                if ($stmt) {
                                    // Log successful registration with audit trail
                                    $logMessage = sprintf(
                                        "VOLUNTEER REGISTERED: %s (%s) - Skills: %s - Assigned to NGO ID: %s",
                                        $fullname,
                                        $email,
                                        $skills,
                                        $assignedNGO
                                    );
                                    error_log($logMessage);
                                    
                                    $message = "✅ Volunteer registered successfully!<br><br>
                                               <strong>Account Details:</strong><br>
                                               • <strong>Name:</strong> " . htmlspecialchars($fullname) . "<br>
                                               • <strong>Skills:</strong> " . htmlspecialchars($skills) . "<br>
                                               • <strong>Status:</strong> Active - You can login immediately";
                                    $_POST = array();
                                    error_log("=== VOLUNTEER REGISTRATION SUCCESS ===");
                                } else {
                                    $error = sqlsrv_errors();
                                    $message = "❌ Error: " . $error[0]['message'];
                                    error_log("SQL Error: " . print_r($error, true));
                                }
                            }
                        }
                    }
                }
            }
        }
    }
    
    error_log("=== REGISTRATION PROCESS END ===");
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
            max-width: 700px;
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
            text-align: left;
            border: 1px solid #b3d9ff;
            font-size: 14px;
        }
        
        .error-message {
            background-color: #fee;
            color: #c33;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: left;
            border: 1px solid #fcc;
            font-size: 14px;
        }
        
        .success-message {
            background-color: #d4edda;
            color: #155724;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: left;
            border: 1px solid #c3e6cb;
            font-size: 14px;
        }
        
        .pending-message {
            background-color: #fff3cd;
            color: #856404;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: left;
            border: 1px solid #ffeaa7;
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
        
        .weak { background: #ff4757; width: 25%; }
        .fair { background: #ffa502; width: 50%; }
        .good { background: #2ed573; width: 75%; }
        .strong { background: #00b894; width: 100%; }
        
        .password-requirements {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-top: 10px;
            border: 1px solid #e1e5e9;
            font-size: 13px;
            color: #666;
        }
        
        .password-requirements h5 {
            margin-top: 0;
            margin-bottom: 10px;
            color: #333;
            font-size: 14px;
        }
        
        .requirement {
            display: flex;
            align-items: center;
            margin-bottom: 5px;
        }
        
        .requirement i {
            margin-right: 8px;
            font-size: 14px;
        }
        
        .requirement.valid {
            color: #00b894;
        }
        
        .requirement.invalid {
            color: #ff4757;
        }
        
        /* Additional info box for pending approval */
        .info-box {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            animation: fadeIn 0.5s ease-in;
        }
        
        .info-box h5 {
            color: #856404;
            margin-top: 0;
            margin-bottom: 10px;
            font-size: 16px;
        }
        
        .info-box p {
            color: #856404;
            margin-bottom: 10px;
            font-size: 14px;
            line-height: 1.5;
        }
        
        .info-box small {
            color: #856404;
            font-size: 12px;
        }
        
        /* Checkbox Styles */
        .checkbox-group {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 10px;
            border: 2px solid #e1e5e9;
        }
        
        .checkbox-row {
            display: flex;
            gap: 15px;
            margin-bottom: 10px;
        }
        
        .checkbox-row:last-child {
            margin-bottom: 0;
        }
        
        .checkbox-label {
            flex: 1;
            display: flex;
            align-items: center;
            cursor: pointer;
            padding: 8px 12px;
            border-radius: 8px;
            transition: all 0.2s;
            position: relative;
            min-height: 60px;
        }
        
        .checkbox-label:hover {
            background: rgba(102, 126, 234, 0.05);
        }
        
        .checkbox-label input[type="checkbox"] {
            position: absolute;
            opacity: 0;
            cursor: pointer;
            width: 0;
            height: 0;
        }
        
        .checkbox-custom {
            position: relative;
            height: 20px;
            width: 20px;
            background-color: white;
            border: 2px solid #ddd;
            border-radius: 4px;
            margin-right: 10px;
            flex-shrink: 0;
            transition: all 0.2s;
        }
        
        .checkbox-label input[type="checkbox"]:checked ~ .checkbox-custom {
            background-color: #667eea;
            border-color: #667eea;
        }
        
        .checkbox-custom:after {
            content: "";
            position: absolute;
            display: none;
            left: 6px;
            top: 2px;
            width: 5px;
            height: 10px;
            border: solid white;
            border-width: 0 2px 2px 0;
            transform: rotate(45deg);
        }
        
        .checkbox-label input[type="checkbox"]:checked ~ .checkbox-custom:after {
            display: block;
        }
        
        /* Skill Item with Description */
        .skill-item {
            display: flex;
            flex-direction: column;
            margin-left: 5px;
        }
        
        .skill-item strong {
            font-size: 14px;
            color: #333;
            margin-bottom: 2px;
        }
        
        .skill-item small {
            font-size: 12px;
            color: #666;
            line-height: 1.3;
        }
        
        /* Two-column layout for form */
        .form-row {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .form-col {
            flex: 1;
        }
        
        @media (max-width: 768px) {
            .container {
                max-width: 95%;
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
            
            .checkbox-row {
                flex-direction: column;
                gap: 8px;
            }
            
            .checkbox-label {
                min-height: 55px;
            }
            
            .form-row {
                flex-direction: column;
                gap: 0;
            }
        }
        
        @media (max-width: 480px) {
            .container {
                padding: 25px 15px;
            }
            
            .logo h1 {
                font-size: 28px;
            }
            
            h2 {
                font-size: 22px;
            }
        }
    </style>
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
            (strpos($message, '❌') !== false ? 'error-message' : 
            (strpos($message, '⏳') !== false ? 'pending-message' : 'message')) 
        ?>">
            <?php 
            // Jika ada HTML tags dalam message, echo terus
            if (strpos($message, '<br>') !== false || strpos($message, '<strong>') !== false) {
                echo $message;
            } else {
                echo htmlspecialchars($message);
            }
            ?>
        </div>
        
        <?php if (strpos($message, 'pending admin approval') !== false): ?>
            <!-- Additional info box for pending approval -->
            <div class="info-box">
                <h5><i class="fas fa-info-circle"></i> What happens next?</h5>
                <p>
                    <strong>1. Admin Review:</strong> An administrator will review your NGO registration<br>
                    <strong>2. Approval:</strong> You will receive an email when your account is approved<br>
                    <strong>3. Login:</strong> You can then login to access the NGO dashboard
                </p>
                <p>
                    <small><em>This process usually takes 1-2 business days.</em></small>
                </p>
            </div>
        <?php endif; ?>
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
        <div class="form-row">
            <div class="form-col">
                <div class="form-group">
                    <label for="fullname">
                        <span id="name-label">Full Name</span> <span class="required">*</span>
                    </label>
                    <input type="text" id="fullname" name="fullname" 
                           placeholder="Enter your full name" 
                           value="<?= isset($_POST['fullname']) ? htmlspecialchars($_POST['fullname']) : '' ?>" 
                           required>
                </div>
            </div>
            
            <div class="form-col">
                <div class="form-group">
                    <label for="email">Email Address <span class="required">*</span></label>
                    <input type="email" id="email" name="email" 
                           placeholder="Enter your email" 
                           value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>" 
                           required>
                </div>
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-col">
                <div class="form-group">
                    <label for="phone">Phone Number <span class="required">*</span></label>
                    <input type="tel" id="phone" name="phone" 
                           placeholder="Enter your phone number" 
                           value="<?= isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : '' ?>" 
                           required>
                </div>
            </div>
            
            <div class="form-col">
                <!-- Address (for NGO & Volunteer) -->
                <div class="form-group conditional-field" id="address_group">
                    <label for="address">Address</label>
                    <input type="text" id="address" name="address" 
                           placeholder="Enter your address"
                           value="<?= isset($_POST['address']) ? htmlspecialchars($_POST['address']) : '' ?>">
                </div>
            </div>
        </div>

        <!-- Skill (for Volunteer only) -->
        <div class="form-group conditional-field" id="skill_group">
            <label>What can you help with? <span class="required">*</span></label>
            <div class="checkbox-group">
                <div class="checkbox-row">
                    <label class="checkbox-label">
                        <input type="checkbox" name="skills[]" value="Medical / First Aid" 
                            <?= (isset($_POST['skills']) && in_array('Medical / First Aid', $_POST['skills'])) ? 'checked' : '' ?>>
                        <span class="checkbox-custom"></span>
                        <div class="skill-item">
                            <strong>Medical / First Aid</strong>
                            <small>Doctor, nurse, paramedic, first aider</small>
                        </div>
                    </label>
                    <label class="checkbox-label">
                        <input type="checkbox" name="skills[]" value="Search & Rescue" 
                            <?= (isset($_POST['skills']) && in_array('Search & Rescue', $_POST['skills'])) ? 'checked' : '' ?>>
                        <span class="checkbox-custom"></span>
                        <div class="skill-item">
                            <strong>Search & Rescue</strong>
                            <small>Find and save people in emergencies</small>
                        </div>
                    </label>
                </div>
                
                <div class="checkbox-row">
                    <label class="checkbox-label">
                        <input type="checkbox" name="skills[]" value="Technical Support" 
                            <?= (isset($_POST['skills']) && in_array('Technical Support', $_POST['skills'])) ? 'checked' : '' ?>>
                        <span class="checkbox-custom"></span>
                        <div class="skill-item">
                            <strong>Technical Support</strong>
                            <small>IT, electrician, technician, repair</small>
                        </div>
                    </label>
                    <label class="checkbox-label">
                        <input type="checkbox" name="skills[]" value="Logistics / Supplies" 
                            <?= (isset($_POST['skills']) && in_array('Logistics / Supplies', $_POST['skills'])) ? 'checked' : '' ?>>
                        <span class="checkbox-custom"></span>
                        <div class="skill-item">
                            <strong>Logistics / Supplies</strong>
                            <small>Manage, pack, and deliver supplies</small>
                        </div>
                    </label>
                </div>
                
                <div class="checkbox-row">
                    <label class="checkbox-label">
                        <input type="checkbox" name="skills[]" value="Driving" 
                            <?= (isset($_POST['skills']) && in_array('Driving', $_POST['skills'])) ? 'checked' : '' ?>>
                        <span class="checkbox-custom"></span>
                        <div class="skill-item">
                            <strong>Driving</strong>
                            <small>Car, van, lorry, or ambulance driver</small>
                        </div>
                    </label>
                    <label class="checkbox-label">
                        <input type="checkbox" name="skills[]" value="Food Services" 
                            <?= (isset($_POST['skills']) && in_array('Food Services', $_POST['skills'])) ? 'checked' : '' ?>>
                        <span class="checkbox-custom"></span>
                        <div class="skill-item">
                            <strong>Food Services</strong>
                            <small>Cooking, packing, or distributing food</small>
                        </div>
                    </label>
                </div>
                
                <div class="checkbox-row">
                    <label class="checkbox-label">
                        <input type="checkbox" name="skills[]" value="Communication" 
                            <?= (isset($_POST['skills']) && in_array('Communication', $_POST['skills'])) ? 'checked' : '' ?>>
                        <span class="checkbox-custom"></span>
                        <div class="skill-item">
                            <strong>Communication</strong>
                            <small>Radio, phone, social media, translator</small>
                        </div>
                    </label>
                    <label class="checkbox-label">
                        <input type="checkbox" name="skills[]" value="Counseling / Support" 
                            <?= (isset($_POST['skills']) && in_array('Counseling / Support', $_POST['skills'])) ? 'checked' : '' ?>>
                        <span class="checkbox-custom"></span>
                        <div class="skill-item">
                            <strong>Counseling / Support</strong>
                            <small>Emotional support, counseling, listening</small>
                        </div>
                    </label>
                </div>
                
                <div class="checkbox-row">
                    <label class="checkbox-label">
                        <input type="checkbox" name="skills[]" value="Admin / Office Work" 
                            <?= (isset($_POST['skills']) && in_array('Admin / Office Work', $_POST['skills'])) ? 'checked' : '' ?>>
                        <span class="checkbox-custom"></span>
                        <div class="skill-item">
                            <strong>Admin / Office Work</strong>
                            <small>Paperwork, data entry, coordination</small>
                        </div>
                    </label>
                    <label class="checkbox-label">
                        <input type="checkbox" name="skills[]" value="Physical Labour" 
                            <?= (isset($_POST['skills']) && in_array('Physical Labour', $_POST['skills'])) ? 'checked' : '' ?>>
                        <span class="checkbox-custom"></span>
                        <div class="skill-item">
                            <strong>Physical Labour</strong>
                            <small>Heavy lifting, setup, cleaning</small>
                        </div>
                    </label>
                </div>
                
                <div class="checkbox-row">
                    <label class="checkbox-label">
                        <input type="checkbox" name="skills[]" value="General Volunteer" 
                            <?= (isset($_POST['skills']) && in_array('General Volunteer', $_POST['skills'])) ? 'checked' : '' ?>>
                        <span class="checkbox-custom"></span>
                        <div class="skill-item">
                            <strong>General Volunteer</strong>
                            <small>Ready to help with any task needed</small>
                        </div>
                    </label>
                </div>
            </div>
            <small style="color: #666; font-size: 13px; margin-top: 5px; display: block;">
                <i class="fas fa-check-circle"></i> Select all that apply to you
            </small>
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
                       placeholder="Enter your password" 
                       required>
                <button type="button" class="toggle-password" onclick="togglePassword('password')">👁️</button>
            </div>
            <div class="password-strength">
                <div class="strength-meter" id="strengthMeter"></div>
            </div>
            
            <!-- Password Requirements -->
            <div class="password-requirements">
                <h5><i class="fas fa-shield-alt"></i> Password Requirements:</h5>
                <div class="requirement invalid" id="req-length">
                    <i class="fas fa-times-circle"></i> At least 12 characters
                </div>
                <div class="requirement invalid" id="req-uppercase">
                    <i class="fas fa-times-circle"></i> At least one uppercase letter
                </div>
                <div class="requirement invalid" id="req-lowercase">
                    <i class="fas fa-times-circle"></i> At least one lowercase letter
                </div>
                <div class="requirement invalid" id="req-number">
                    <i class="fas fa-times-circle"></i> At least one number
                </div>
                <div class="requirement invalid" id="req-special">
                    <i class="fas fa-times-circle"></i> At least one special character
                </div>
                <div class="requirement invalid" id="req-match">
                    <i class="fas fa-times-circle"></i> Passwords must match
                </div>
            </div>
        </div>

        <!-- Confirm Password -->
        <div class="form-group">
            <label for="confirm_password">Confirm Password <span class="required">*</span></label>
            <div class="password-container">
                <input type="password" id="confirm_password" name="confirm_password" 
                       placeholder="Confirm your password" 
                       required>
                <button type="button" class="toggle-password" onclick="togglePassword('confirm_password')">👁️</button>
            </div>
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
        
        <!-- Security Notice -->
        <div style="margin-top: 20px; padding: 10px; background: #f8f9fa; border-radius: 8px; font-size: 12px; color: #666; text-align: center;">
            <i class="fas fa-lock"></i> Your password is securely hashed using bcrypt with 4096 iterations
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
            if (group.querySelector('input, select')) {
                group.querySelectorAll('input, select').forEach(input => {
                    if (input.getAttribute('name') !== 'address') {
                        input.required = false;
                    }
                });
            }
        });
        
        // Show address for both NGO and Volunteer
        if (role === 'ngo' || role === 'volunteer') {
            addressGroup.style.display = 'block';
        }
        
        // Show skill and NGO selection only for Volunteer
        if (role === 'volunteer') {
            skillGroup.style.display = 'block';
            ngoGroup.style.display = 'block';
            
            // Make NGO selection required
            if (document.getElementById('assignedNGO')) {
                document.getElementById('assignedNGO').required = true;
            }
        }
    }

    function togglePassword(fieldId) {
        const passwordField = document.getElementById(fieldId);
        const toggleButton = passwordField.nextElementSibling;
        
        if (passwordField.type === 'password') {
            passwordField.type = 'text';
            toggleButton.textContent = '🙈';
        } else {
            passwordField.type = 'password';
            toggleButton.textContent = '👁️';
        }
    }
    
    // Password strength checker and requirement validator
    function validatePasswordRequirements(password, confirmPassword) {
        let score = 0;
        const requirements = {
            length: password.length >= 12,
            uppercase: /[A-Z]/.test(password),
            lowercase: /[a-z]/.test(password),
            number: /[0-9]/.test(password),
            special: /[!@#$%^&*(),.?":{}|<>]/.test(password),
            match: password === confirmPassword && password.length > 0
        };
        
        // Update requirement indicators
        Object.keys(requirements).forEach(key => {
            const element = document.getElementById('req-' + key);
            if (element) {
                if (requirements[key]) {
                    element.classList.remove('invalid');
                    element.classList.add('valid');
                    element.querySelector('i').className = 'fas fa-check-circle';
                    score++;
                } else {
                    element.classList.remove('valid');
                    element.classList.add('invalid');
                    element.querySelector('i').className = 'fas fa-times-circle';
                }
            }
        });
        
        return { requirements, score, total: Object.keys(requirements).length };
    }
    
    // Update strength meter and requirements in real-time
    function updatePasswordValidation() {
        const password = document.getElementById('password').value;
        const confirmPassword = document.getElementById('confirm_password').value;
        const strengthMeter = document.getElementById('strengthMeter');
        
        const validation = validatePasswordRequirements(password, confirmPassword);
        
        // Update strength meter
        strengthMeter.className = 'strength-meter';
        strengthMeter.style.width = '0%';
        
        if (password.length === 0) {
            strengthMeter.style.width = '0%';
            return;
        }
        
        const percentage = Math.round((validation.score / validation.total) * 100);
        
        if (percentage <= 40) {
            strengthMeter.classList.add('weak');
        } else if (percentage <= 60) {
            strengthMeter.classList.add('fair');
        } else if (percentage <= 80) {
            strengthMeter.classList.add('good');
        } else {
            strengthMeter.classList.add('strong');
        }
        
        strengthMeter.style.width = percentage + '%';
    }
    
    // Event listeners for password validation
    document.getElementById('password').addEventListener('input', updatePasswordValidation);
    document.getElementById('confirm_password').addEventListener('input', updatePasswordValidation);
    
    // Form validation
    document.getElementById('registerForm').addEventListener('submit', function(e) {
        const role = document.getElementById('role').value;
        const fullname = document.getElementById('fullname').value.trim();
        const email = document.getElementById('email').value.trim();
        const phone = document.getElementById('phone').value.trim();
        const password = document.getElementById('password').value;
        const confirmPassword = document.getElementById('confirm_password').value;
        const submitBtn = document.getElementById('submitBtn');
        
        // Disable button to prevent double submission
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating Account...';
        
        // Basic validation
        const errors = [];
        
        if (!role) {
            errors.push('Please select a role (NGO or Volunteer)');
        }
        
        if (!fullname) {
            errors.push('Full name is required');
        }
        
        if (!email) {
            errors.push('Email address is required');
        } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            errors.push('Please enter a valid email address');
        }
        
        if (!phone) {
            errors.push('Phone number is required');
        } else if (!/^[0-9\-\+\s\(\)]{10,}$/.test(phone.replace(/\s/g, ''))) {
            errors.push('Please enter a valid phone number (at least 10 digits)');
        }
        
        if (!password) {
            errors.push('Password is required');
        }
        
        if (!confirmPassword) {
            errors.push('Please confirm your password');
        }
        
        // Check password requirements
        const validation = validatePasswordRequirements(password, confirmPassword);
        if (validation.score < validation.total) {
            // Get specific requirements that failed
            const failedReqs = [];
            if (!validation.requirements.length) failedReqs.push('at least 12 characters');
            if (!validation.requirements.uppercase) failedReqs.push('uppercase letter');
            if (!validation.requirements.lowercase) failedReqs.push('lowercase letter');
            if (!validation.requirements.number) failedReqs.push('number');
            if (!validation.requirements.special) failedReqs.push('special character');
            if (!validation.requirements.match) failedReqs.push('passwords to match');
            
            errors.push('Password requirements not met: ' + failedReqs.join(', '));
        }
        
        // Role-specific validation
        if (role === 'volunteer') {
            const skillCheckboxes = document.querySelectorAll('input[name="skills[]"]:checked');
            const assignedNGO = document.getElementById('assignedNGO') ? document.getElementById('assignedNGO').value : '';
            
            if (skillCheckboxes.length === 0) {
                errors.push('Please select at least one skill category');
            }
            
            if (!assignedNGO) {
                errors.push('Please select your assigned NGO');
            }
        }
        
        // If there are errors, prevent submission
        if (errors.length > 0) {
            e.preventDefault();
            alert('Please fix the following errors:\n\n• ' + errors.join('\n• '));
            submitBtn.disabled = false;
            submitBtn.innerHTML = 'Create Account';
            return false;
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
        
        // Initialize password validation display
        updatePasswordValidation();
    });
    
    // Add hover effect for skill items
    document.querySelectorAll('.checkbox-label').forEach(label => {
        label.addEventListener('mouseenter', function() {
            this.style.backgroundColor = 'rgba(102, 126, 234, 0.08)';
        });
        
        label.addEventListener('mouseleave', function() {
            if (!this.querySelector('input').checked) {
                this.style.backgroundColor = '';
            }
        });
    });
    
    // Reset button if user goes back
    window.addEventListener('pageshow', function(event) {
        if (event.persisted) {
            const submitBtn = document.getElementById('submitBtn');
            submitBtn.disabled = false;
            submitBtn.innerHTML = 'Create Account';
        }
    });
</script>

</body>
</html>