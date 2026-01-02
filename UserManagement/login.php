<?php
// ============================
// ERROR REPORTING
// ============================
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once "connection.php";

// ============================
// LOGIN PROCESS
// ============================
$message = "";
$debug_info = "";

// Check if we're coming from a system choice
$system_choice = $_GET['system'] ?? $_POST['system'] ?? '';
$return_to = $_GET['return_to'] ?? '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $system_choice = $_POST['system'] ?? $system_choice;

    if (empty($email) || empty($password)) {
        $message = "Please enter both email and password.";
    } else {
        
        // --- DEBUG INFO ---
        $debug_info .= "=== LOGIN ATTEMPT ===\n";
        $debug_info .= "Email: " . $email . "\n";
        $debug_info .= "Password length: " . strlen($password) . "\n";
        $debug_info .= "System choice: " . $system_choice . "\n";
        
        // --- CHECK ADMIN TABLE ---
        $sql = "SELECT AdminID AS ID, FullName, Email, PasswordHash, 'admin' AS Role
                FROM Admin WHERE Email = ?";
        $stmt = sqlsrv_query($conn, $sql, array($email));

        if ($stmt && sqlsrv_has_rows($stmt)) {
            $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
            $debug_info .= "Found in Admin table: " . $row["FullName"] . "\n";
            
            $storedHash = $row["PasswordHash"];
            $debug_info .= "Stored hash type: " . (substr($storedHash, 0, 4) === '$2y$' ? 'BCRYPT' : 'PLAIN') . "\n";
            
            if (substr($storedHash, 0, 4) === '$2y$') {
                if (password_verify($password, $storedHash)) {
                    $_SESSION["user_id"] = $row["ID"];
                    $_SESSION["name"] = $row["FullName"];
                    $_SESSION["email"] = $row["Email"];
                    $_SESSION["role"] = "admin";
                    $debug_info .= "Admin login SUCCESS\n";
                    header("Location: admin_dashboard.php");
                    exit;
                } else {
                    $debug_info .= "Admin password verification FAILED\n";
                }
            } else {
                if ($password === $storedHash) {
                    $_SESSION["user_id"] = $row["ID"];
                    $_SESSION["name"] = $row["FullName"];
                    $_SESSION["email"] = $row["Email"];
                    $_SESSION["role"] = "admin";
                    
                    // Auto-upgrade to bcrypt
                    $newHash = password_hash($password, PASSWORD_BCRYPT);
                    $update_sql = "UPDATE Admin SET PasswordHash = ? WHERE AdminID = ?";
                    sqlsrv_query($conn, $update_sql, array($newHash, $row["ID"]));
                    
                    $debug_info .= "Admin login SUCCESS (plain text, upgraded to bcrypt)\n";
                    header("Location: admin_dashboard.php");
                    exit;
                } else {
                    $debug_info .= "Admin plain text password FAILED\n";
                }
            }
        } else {
            $debug_info .= "Not found in Admin table\n";
        }

        // --- CHECK NGO TABLE ---
        $sql = "SELECT NGOID AS ID, NGOName AS FullName, Email, PasswordHash, status, 'ngo' AS Role
                FROM NGO WHERE Email = ?";
        $stmt = sqlsrv_query($conn, $sql, array($email));

        if ($stmt) {
            if (sqlsrv_has_rows($stmt)) {
                $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
                $debug_info .= "\n=== NGO LOGIN ATTEMPT ===\n";
                $debug_info .= "NGO Name: " . $row["FullName"] . "\n";
                $debug_info .= "NGO Status: " . $row["status"] . "\n";
                $debug_info .= "Stored Hash: " . $row["PasswordHash"] . "\n";
                $debug_info .= "Hash Length: " . strlen($row["PasswordHash"]) . "\n";
                
                $storedHash = $row["PasswordHash"];
                $ngoStatus = $row["status"] ?? 'Approved';
                
                // CHECK IF PASSWORD IS NULL OR EMPTY
                if (empty($storedHash)) {
                    $debug_info .= "ERROR: PasswordHash is NULL or EMPTY in database!\n";
                    $message = "Account error: Password not set. Please contact administrator.";
                } else {
                    // CHECK NGO STATUS
                    if ($ngoStatus == 'Pending') {
                        $message = "⚠️ Your NGO account is pending admin approval. Please wait for approval.";
                        $debug_info .= "Login blocked: Account PENDING\n";
                    } elseif ($ngoStatus == 'Rejected') {
                        $message = "❌ Your NGO registration has been rejected. Please contact administrator.";
                        $debug_info .= "Login blocked: Account REJECTED\n";
                    } elseif ($ngoStatus == 'Approved' || $ngoStatus == '' || $ngoStatus == 'active') {
                        
                        $loginSuccess = false;
                        $hashType = "UNKNOWN";
                        
                        // CHECK HASH TYPE - FIXED FOR CORRUPTED HASHES
                        if (substr($storedHash, 0, 4) === '$2y$') {
                            $hashType = "BCRYPT";
                            $debug_info .= "Hash type: BCRYPT\n";
                            
                            // VERIFY BCRYPT PASSWORD
                            if (password_verify($password, $storedHash)) {
                                $loginSuccess = true;
                                $debug_info .= "✓ BCRYPT password verification SUCCESS\n";
                            } else {
                                $debug_info .= "✗ BCRYPT password verification FAILED\n";
                            }
                        } 
                        // CHECK IF HASH IS CORRUPTED (contains comma or wrong format)
                        elseif (strpos($storedHash, ',') !== false || substr($storedHash, 0, 4) === '$2x3') {
                            $hashType = "CORRUPTED";
                            $debug_info .= "Hash type: CORRUPTED/DAMAGED\n";
                            $debug_info .= "WARNING: Hash appears to be corrupted!\n";
                            
                            // Try plain text comparison as fallback
                            if ($password === $storedHash) {
                                $loginSuccess = true;
                                $debug_info .= "✓ Corrupted hash - plain text comparison SUCCESS\n";
                            } else {
                                // Try extracting actual password if hash is concatenated
                                $parts = explode(',', $storedHash);
                                if (count($parts) > 1) {
                                    foreach ($parts as $part) {
                                        if (trim($part) === $password) {
                                            $loginSuccess = true;
                                            $debug_info .= "✓ Found password in corrupted hash parts\n";
                                            break;
                                        }
                                    }
                                }
                            }
                            
                            // Always fix corrupted hash if login succeeds
                            if ($loginSuccess) {
                                $newHash = password_hash($password, PASSWORD_BCRYPT);
                                $update_sql = "UPDATE NGO SET PasswordHash = ? WHERE NGOID = ?";
                                $update_stmt = sqlsrv_query($conn, $update_sql, array($newHash, $row["ID"]));
                                $debug_info .= "Fixed corrupted hash: " . ($update_stmt ? "SUCCESS" : "FAILED") . "\n";
                            }
                        }
                        // CHECK IF IT'S MD5 HASH
                        elseif (strlen($storedHash) == 32 && ctype_xdigit($storedHash)) {
                            $hashType = "MD5";
                            $debug_info .= "Hash type: MD5 (32 chars hex)\n";
                            
                            if (md5($password) === $storedHash) {
                                $loginSuccess = true;
                                $debug_info .= "✓ MD5 password verification SUCCESS\n";
                                
                                // Upgrade to bcrypt
                                $newHash = password_hash($password, PASSWORD_BCRYPT);
                                $update_sql = "UPDATE NGO SET PasswordHash = ? WHERE NGOID = ?";
                                $update_result = sqlsrv_query($conn, $update_sql, array($newHash, $row["ID"]));
                                $debug_info .= "Upgraded MD5 to BCRYPT: " . ($update_result ? "SUCCESS" : "FAILED") . "\n";
                            }
                        }
                        // CHECK IF IT'S SHA1 HASH
                        elseif (strlen($storedHash) == 40 && ctype_xdigit($storedHash)) {
                            $hashType = "SHA1";
                            $debug_info .= "Hash type: SHA1 (40 chars hex)\n";
                            
                            if (sha1($password) === $storedHash) {
                                $loginSuccess = true;
                                $debug_info .= "✓ SHA1 password verification SUCCESS\n";
                                
                                // Upgrade to bcrypt
                                $newHash = password_hash($password, PASSWORD_BCRYPT);
                                $update_sql = "UPDATE NGO SET PasswordHash = ? WHERE NGOID = ?";
                                sqlsrv_query($conn, $update_sql, array($newHash, $row["ID"]));
                            }
                        }
                        // ASSUME PLAIN TEXT
                        else {
                            $hashType = "PLAIN_TEXT";
                            $debug_info .= "Hash type: PLAIN TEXT (assuming)\n";
                            
                            // DIRECT COMPARISON
                            if ($password === $storedHash) {
                                $loginSuccess = true;
                                $debug_info .= "✓ Plain text password match SUCCESS\n";
                                
                                // UPGRADE TO BCRYPT
                                $newHash = password_hash($password, PASSWORD_BCRYPT);
                                $update_sql = "UPDATE NGO SET PasswordHash = ? WHERE NGOID = ?";
                                $update_stmt = sqlsrv_query($conn, $update_sql, array($newHash, $row["ID"]));
                                
                                if ($update_stmt) {
                                    $debug_info .= "✓ Password upgraded to BCRYPT in database\n";
                                } else {
                                    $debug_info .= "✗ FAILED to upgrade password. Error: " . print_r(sqlsrv_errors(), true) . "\n";
                                }
                            } else {
                                $debug_info .= "✗ Plain text password match FAILED\n";
                            }
                        }
                        
                        if ($loginSuccess) {
                            $_SESSION["user_id"] = $row["ID"];
                            $_SESSION["name"] = $row["FullName"];
                            $_SESSION["email"] = $row["Email"];
                            $_SESSION["role"] = "ngo";
                            
                            $debug_info .= "✓ NGO LOGIN SUCCESSFUL - Redirecting to dashboard\n";
                            error_log("NGO LOGIN SUCCESS: " . $email . " | Hash type: " . $hashType);
                            
                            header("Location: ngo_dashboard.php");
                            exit;
                        } else {
                            $debug_info .= "✗ All password verification methods FAILED\n";
                        }
                    }
                }
            } else {
                $debug_info .= "Not found in NGO table\n";
            }
        } else {
            $debug_info .= "NGO query failed: " . print_r(sqlsrv_errors(), true) . "\n";
        }

        // --- CHECK VOLUNTEER TABLE ---
        $sql = "SELECT VolunteerID AS ID, FullName, Email, PasswordHash, 'volunteer' AS Role
                FROM Volunteer WHERE Email = ?";
        $stmt = sqlsrv_query($conn, $sql, array($email));

        if ($stmt && sqlsrv_has_rows($stmt)) {
            $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
            $debug_info .= "Found in Volunteer table: " . $row["FullName"] . "\n";
            
            $storedHash = $row["PasswordHash"];
            
            if (substr($storedHash, 0, 4) === '$2y$') {
                if (password_verify($password, $storedHash)) {
                    $_SESSION["user_id"] = $row["ID"];
                    $_SESSION["name"] = $row["FullName"];
                    $_SESSION["email"] = $row["Email"];
                    $_SESSION["role"] = "volunteer";
                    $debug_info .= "Volunteer login SUCCESS\n";
                    
                    // ============================================================
                    // MODIFIED: Check which system to redirect to
                    // ============================================================
                    if ($system_choice === 'distribution') {
                        // Redirect to YOUR distribution system
                        header("Location: http://10.147.17.154:8000/distribution_module/login_callback.php?volunteer_id=" . $row["ID"]);
                    } else {
                        // Default: Redirect to original volunteer dashboard
                        header("Location: volunteer_dashboard.php");
                    }
                    exit;
                }
            } else {
                if ($password === $storedHash) {
                    $_SESSION["user_id"] = $row["ID"];
                    $_SESSION["name"] = $row["FullName"];
                    $_SESSION["email"] = $row["Email"];
                    $_SESSION["role"] = "volunteer";
                    
                    // Auto-upgrade to bcrypt
                    $newHash = password_hash($password, PASSWORD_BCRYPT);
                    $update_sql = "UPDATE Volunteer SET PasswordHash = ? WHERE VolunteerID = ?";
                    sqlsrv_query($conn, $update_sql, array($newHash, $row["ID"]));
                    
                    $debug_info .= "Volunteer login SUCCESS (plain text, upgraded to bcrypt)\n";
                    
                    // ============================================================
                    // MODIFIED: Check which system to redirect to
                    // ============================================================
                    if ($system_choice === 'distribution') {
                        // Redirect to YOUR distribution system
                        header("Location: http://10.147.17.154:8000/distribution_module/login_callback.php?volunteer_id=" . $row["ID"]);
                    } else {
                        // Default: Redirect to original volunteer dashboard
                        header("Location: volunteer_dashboard.php");
                    }
                    exit;
                }
            }
        } else {
            $debug_info .= "Not found in Volunteer table\n";
        }

        // Jika semua gagal
        if (empty($message)) {
            $message = "Invalid email or password.";
        }
        
        // Log debug info
        error_log("LOGIN FAILED - " . $email . "\n" . $debug_info);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login Page</title>
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
            max-width: 450px;
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
            margin-bottom: 25px;
            font-size: 28px;
            font-weight: 600;
        }
        
        .system-choice {
            background: #f0f7ff;
            border: 1px solid #c2e0ff;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
        }
        
        .system-choice h3 {
            color: #0366d6;
            font-size: 16px;
            margin-bottom: 10px;
            text-align: center;
        }
        
        .system-options {
            display: flex;
            gap: 10px;
            margin-bottom: 10px;
        }
        
        .system-option {
            flex: 1;
            text-align: center;
        }
        
        .system-option input[type="radio"] {
            display: none;
        }
        
        .system-option label {
            display: block;
            padding: 12px 10px;
            background: white;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            color: #555;
            transition: all 0.3s;
        }
        
        .system-option input[type="radio"]:checked + label {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }
        
        .system-option label:hover {
            border-color: #667eea;
            transform: translateY(-2px);
        }
        
        .system-note {
            font-size: 12px;
            color: #666;
            text-align: center;
            margin-top: 5px;
        }
        
        .error {
            background-color: #fee;
            color: #c33;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
            border: 1px solid #fcc;
            font-size: 14px;
        }
        
        .warning {
            background-color: #fff3cd;
            color: #856404;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
            border: 1px solid #ffeaa7;
            font-size: 14px;
        }
        
        .info {
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
        
        input[type="email"],
        input[type="password"] {
            width: 100%;
            padding: 14px;
            border: 2px solid #e1e5e9;
            border-radius: 10px;
            font-size: 16px;
            transition: all 0.3s;
            background: #f8f9fa;
        }
        
        input[type="email"]:focus,
        input[type="password"]:focus {
            outline: none;
            border-color: #667eea;
            background: #fff;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        button[type="submit"] {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 10px;
        }
        
        button[type="submit"]:hover {
            transform: translateY(-2px);
            box-shadow: 0 7px 14px rgba(102, 126, 234, 0.25);
        }
        
        button[type="submit"]:active {
            transform: translateY(0);
        }
        
        .register-link {
            text-align: center;
            margin-top: 25px;
            color: #666;
            font-size: 14px;
        }
        
        .register-link a {
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
        }
        
        .register-link a:hover {
            text-decoration: underline;
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
        
        .logo {
            text-align: center;
            margin-bottom: 20px;
        }
        
        .logo h1 {
            color: #667eea;
            font-size: 32px;
            font-weight: 700;
        }
        
        .nag-info {
            background-color: #e8f4fd;
            color: #0366d6;
            padding: 10px;
            border-radius: 8px;
            margin-top: 15px;
            font-size: 13px;
            border-left: 4px solid #667eea;
        }
        
        .debug-toggle {
            margin-top: 15px;
            text-align: center;
        }
        
        .debug-toggle button {
            background: #6c757d;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 12px;
            cursor: pointer;
        }
        
        .debug-info {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 10px;
            margin-top: 10px;
            font-family: monospace;
            font-size: 11px;
            white-space: pre-wrap;
            max-height: 300px;
            overflow-y: auto;
            display: none;
        }
        
        @media (max-width: 480px) {
            .container {
                padding: 30px 20px;
            }
            
            h2 {
                font-size: 24px;
            }
            
            .system-options {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>

<div class="container">
    <div class="logo">
        <h1>LoginSection</h1>
    </div>
    
    <h2>Login to Your Account</h2>

    <?php if ($message) { ?>
        <div class="<?= 
            strpos($message, '⚠️') !== false ? 'warning' : 
            (strpos($message, '❌') !== false ? 'error' : 
            (strpos($message, '✅') !== false ? 'info' : 'error')) 
        ?>">
            <?= htmlspecialchars($message) ?>
        </div>
        
        <?php if (strpos($message, 'pending admin approval') !== false): ?>
            <div class="nag-info">
                <strong>ℹ️ Note:</strong> NGO registrations require admin approval. 
                You will be able to login once your account is approved.
            </div>
        <?php endif; ?>
    <?php } ?>

    <form action="" method="POST" id="loginForm">
        <!-- System Choice Section -->
        <div class="system-choice">
            <h3>Choose System to Access:</h3>
            <div class="system-options">
                <div class="system-option">
                    <input type="radio" id="system_main" name="system" value="main" 
                           <?= ($system_choice === 'main' || empty($system_choice)) ? 'checked' : '' ?>>
                    <label for="system_main">Main System</label>
                </div>
                <div class="system-option">
                    <input type="radio" id="system_distribution" name="system" value="distribution"
                           <?= $system_choice === 'distribution' ? 'checked' : '' ?>>
                    <label for="system_distribution">Distribution System</label>
                </div>
            </div>
            <div class="system-note">
                Volunteers: Select "Distribution System" to access distribution management
            </div>
        </div>

        <div class="form-group">
            <label for="email">Email Address</label>
            <input type="email" id="email" name="email" 
                   placeholder="Enter your email" 
                   value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>" 
                   required>
        </div>
        
        <div class="form-group">
            <label for="password">Password</label>
            <div class="password-container">
                <input type="password" id="password" name="password" 
                       placeholder="Enter your password" required>
                <button type="button" class="toggle-password" onclick="togglePassword()">👁️</button>
            </div>
        </div>
        
        <button type="submit">Login</button>
        
        <div class="forgot-link" style="text-align: center; margin-top: 15px;">
            <a href="forgot_password.php" style="color: #667eea; text-decoration: none; font-size: 14px;">
                <i class="fas fa-key" style="margin-right: 5px;"></i> Forgot Password?
            </a>
        </div>

        <div class="register-link">
            Don't have an account? 
            <a href="register.php">Register here</a>
        </div>
    </form>
    
    <!-- Debug Section -->
    <div class="debug-toggle">
        <button type="button" onclick="toggleDebug()">Show Debug Info</button>
    </div>
    <div class="debug-info" id="debugInfo">
        <?php echo htmlspecialchars($debug_info ?? 'No debug information available.'); ?>
    </div>
</div>

<script>
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
    
    function toggleDebug() {
        const debugInfo = document.getElementById('debugInfo');
        const debugBtn = document.querySelector('.debug-toggle button');
        
        if (debugInfo.style.display === 'none' || debugInfo.style.display === '') {
            debugInfo.style.display = 'block';
            debugBtn.textContent = 'Hide Debug Info';
        } else {
            debugInfo.style.display = 'none';
            debugBtn.textContent = 'Show Debug Info';
        }
    }
    
    // Form validation
    document.getElementById('loginForm').addEventListener('submit', function(e) {
        const email = document.getElementById('email').value.trim();
        const password = document.getElementById('password').value.trim();
        const systemChoice = document.querySelector('input[name="system"]:checked').value;
        const submitBtn = document.querySelector('button[type="submit"]');
        
        // Disable button untuk prevent double click
        submitBtn.disabled = true;
        submitBtn.innerHTML = 'Logging in...';
        
        if (!email || !password) {
            e.preventDefault();
            alert('Please fill in all fields');
            submitBtn.disabled = false;
            submitBtn.innerHTML = 'Login';
            return false;
        }
        
        // Email validation
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(email)) {
            e.preventDefault();
            alert('Please enter a valid email address');
            submitBtn.disabled = false;
            submitBtn.innerHTML = 'Login';
            return false;
        }
        
        // Show system confirmation for distribution system
        if (systemChoice === 'distribution') {
            const confirmMsg = "You are logging into the Distribution System.\n\n" +
                             "After successful login, you will be redirected to:\n" +
                             "http://10.147.17.154:8000/distribution_module/\n\n" +
                             "Continue?";
            
            if (!confirm(confirmMsg)) {
                e.preventDefault();
                submitBtn.disabled = false;
                submitBtn.innerHTML = 'Login';
                return false;
            }
        }
        
        return true;
    });
    
    // Reset button text jika user tekan back
    window.addEventListener('pageshow', function(event) {
        if (event.persisted) {
            const submitBtn = document.querySelector('button[type="submit"]');
            submitBtn.disabled = false;
            submitBtn.innerHTML = 'Login';
        }
    });
    
    // Auto-show debug jika ada error message
    <?php if ($message && strpos($message, 'Invalid') !== false): ?>
    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(() => {
            toggleDebug();
        }, 500);
    });
    <?php endif; ?>
    
    // Auto-detect volunteer emails and suggest distribution system
    document.getElementById('email').addEventListener('blur', function() {
        const email = this.value.trim().toLowerCase();
        
        // Common volunteer email patterns
        const volunteerPatterns = [
            '@volunteer.',
            '@ngo.',
            '.vol@',
            'volunteer@',
            'vol@',
            'v@'
        ];
        
        let isLikelyVolunteer = false;
        for (const pattern of volunteerPatterns) {
            if (email.includes(pattern)) {
                isLikelyVolunteer = true;
                break;
            }
        }
        
        if (isLikelyVolunteer) {
            // Check if distribution system is not already selected
            const distributionRadio = document.getElementById('system_distribution');
            if (!distributionRadio.checked) {
                // Ask if they want to use distribution system
                if (confirm("This looks like a volunteer email. Would you like to login to the Distribution System?")) {
                    distributionRadio.checked = true;
                }
            }
        }
    });
    
    // Auto-select system based on return_to parameter
    <?php if ($return_to === 'distribution'): ?>
    document.addEventListener('DOMContentLoaded', function() {
        document.getElementById('system_distribution').checked = true;
    });
    <?php endif; ?>
</script>

</body>
</html>