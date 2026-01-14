<?php
// ============================
// ERROR REPORTING
// ============================
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once "connection.php";

// ============================
// SIMPLE SECURITY CONFIG
// ============================
define('MAX_FAILED_ATTEMPTS', 3);
define('LOCKOUT_TIME', 15); // 15 minutes

// ============================
// CREATE LOCK TABLE IF NOT EXISTS
// ============================
$createTable = "
IF NOT EXISTS (SELECT * FROM sysobjects WHERE name='LoginLocks' AND xtype='U')
BEGIN
    CREATE TABLE LoginLocks (
        id INT IDENTITY(1,1) PRIMARY KEY,
        email NVARCHAR(255),
        failed_attempts INT DEFAULT 0,
        lock_until DATETIME NULL,
        last_attempt DATETIME DEFAULT GETDATE()
    )
    CREATE INDEX idx_login_locks_email ON LoginLocks(email);
END
";
sqlsrv_query($conn, $createTable);

// ============================
// CHECK IF ACCOUNT IS LOCKED
// ============================
function isAccountLocked($email, $conn) {
    $sql = "SELECT failed_attempts, lock_until 
            FROM LoginLocks 
            WHERE email = ?";
    $stmt = sqlsrv_query($conn, $sql, array($email));
    
    if ($stmt && sqlsrv_has_rows($stmt)) {
        $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        
        // Check if account is locked
        if ($row['lock_until'] !== null) {
            $lockUntil = strtotime($row['lock_until']->format('Y-m-d H:i:s'));
            $currentTime = time();
            
            if ($currentTime < $lockUntil) {
                // Still locked
                $remainingMinutes = ceil(($lockUntil - $currentTime) / 60);
                return [
                    'locked' => true,
                    'minutes' => $remainingMinutes,
                    'until' => date('H:i:s', $lockUntil)
                ];
            } else {
                // Lock expired, reset
                $resetSql = "UPDATE LoginLocks 
                            SET failed_attempts = 0, lock_until = NULL 
                            WHERE email = ?";
                sqlsrv_query($conn, $resetSql, array($email));
            }
        }
        
        // Check if reached max attempts
        if ($row['failed_attempts'] >= MAX_FAILED_ATTEMPTS) {
            // Lock account for 15 minutes
            $lockSql = "UPDATE LoginLocks 
                       SET lock_until = DATEADD(minute, " . LOCKOUT_TIME . ", GETDATE())
                       WHERE email = ?";
            sqlsrv_query($conn, $lockSql, array($email));
            
            return [
                'locked' => true,
                'minutes' => LOCKOUT_TIME,
                'until' => date('H:i:s', time() + (LOCKOUT_TIME * 60))
            ];
        }
    }
    
    return ['locked' => false];
}

// ============================
// RECORD FAILED ATTEMPT
// ============================
function recordFailedAttempt($email, $conn) {
    $sql = "IF EXISTS (SELECT 1 FROM LoginLocks WHERE email = ?)
            BEGIN
                UPDATE LoginLocks 
                SET failed_attempts = failed_attempts + 1, 
                    last_attempt = GETDATE()
                WHERE email = ?
            END
            ELSE
            BEGIN
                INSERT INTO LoginLocks (email, failed_attempts, last_attempt) 
                VALUES (?, 1, GETDATE())
            END";
    
    sqlsrv_query($conn, $sql, array($email, $email, $email));
}

// ============================
// RESET FAILED ATTEMPTS (on successful login)
// ============================
function resetFailedAttempts($email, $conn) {
    $sql = "UPDATE LoginLocks 
            SET failed_attempts = 0, lock_until = NULL 
            WHERE email = ?";
    sqlsrv_query($conn, $sql, array($email));
}

// ============================
// LOGIN PROCESS
// ============================
$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    if (empty($email) || empty($password)) {
        $message = "Please enter both email and password.";
    } else {
        // Check if account is locked
        $lockCheck = isAccountLocked($email, $conn);
        
        if ($lockCheck['locked']) {
            $message = "⚠️ Account locked. Try again in " . $lockCheck['minutes'] . " minutes (until " . $lockCheck['until'] . ")";
        } else {
            // --- CHECK ADMIN TABLE ---
            $sql = "SELECT AdminID AS ID, FullName, Email, PasswordHash, 'admin' AS Role
                    FROM Admin WHERE Email = ?";
            $stmt = sqlsrv_query($conn, $sql, array($email));

            if ($stmt && sqlsrv_has_rows($stmt)) {
                $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
                $storedHash = $row["PasswordHash"];
                
                if (substr($storedHash, 0, 4) === '$2y$') {
                    if (password_verify($password, $storedHash)) {
                        $_SESSION["user_id"] = $row["ID"];
                        $_SESSION["name"] = $row["FullName"];
                        $_SESSION["email"] = $row["Email"];
                        $_SESSION["role"] = "admin";
                        
                        // Reset failed attempts
                        resetFailedAttempts($email, $conn);
                        
                        header("Location: admin_dashboard.php");
                        exit;
                    } else {
                        recordFailedAttempt($email, $conn);
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
                        
                        resetFailedAttempts($email, $conn);
                        
                        header("Location: admin_dashboard.php");
                        exit;
                    } else {
                        recordFailedAttempt($email, $conn);
                    }
                }
            }

            // --- CHECK NGO TABLE ---
            $sql = "SELECT NGOID AS ID, NGOName AS FullName, Email, PasswordHash, status, 'ngo' AS Role
                    FROM NGO WHERE Email = ?";
            $stmt = sqlsrv_query($conn, $sql, array($email));

            if ($stmt && sqlsrv_has_rows($stmt)) {
                $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
                $storedHash = $row["PasswordHash"];
                $ngoStatus = $row["status"] ?? 'Approved';
                
                // CHECK NGO STATUS
                if (strtolower($ngoStatus) == 'pending') {
                    $message = "⚠️ Your NGO account is pending admin approval. Please wait for approval.";
                } elseif (strtolower($ngoStatus) == 'rejected') {
                    $message = "❌ Your NGO registration has been rejected. Please contact administrator.";
                } else {
                    // CHECK PASSWORD
                    if (substr($storedHash, 0, 4) === '$2y$') {
                        if (password_verify($password, $storedHash)) {
                            $_SESSION["user_id"] = $row["ID"];
                            $_SESSION["name"] = $row["FullName"];
                            $_SESSION["email"] = $row["Email"];
                            $_SESSION["role"] = "ngo";
                            
                            resetFailedAttempts($email, $conn);
                            
                            header("Location: ngo_dashboard.php");
                            exit;
                        } else {
                            recordFailedAttempt($email, $conn);
                        }
                    } else {
                        if ($password === $storedHash) {
                            $_SESSION["user_id"] = $row["ID"];
                            $_SESSION["name"] = $row["FullName"];
                            $_SESSION["email"] = $row["Email"];
                            $_SESSION["role"] = "ngo";
                            
                            // Auto-upgrade to bcrypt
                            $newHash = password_hash($password, PASSWORD_BCRYPT);
                            $update_sql = "UPDATE NGO SET PasswordHash = ? WHERE NGOID = ?";
                            sqlsrv_query($conn, $update_sql, array($newHash, $row["ID"]));
                            
                            resetFailedAttempts($email, $conn);
                            
                            header("Location: ngo_dashboard.php");
                            exit;
                        } else {
                            recordFailedAttempt($email, $conn);
                        }
                    }
                }
            }

            // --- CHECK VOLUNTEER TABLE ---
            $sql = "SELECT VolunteerID AS ID, FullName, Email, PasswordHash, 'volunteer' AS Role
                    FROM Volunteer WHERE Email = ?";
            $stmt = sqlsrv_query($conn, $sql, array($email));

            if ($stmt && sqlsrv_has_rows($stmt)) {
                $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
                $storedHash = $row["PasswordHash"];
                
                if (substr($storedHash, 0, 4) === '$2y$') {
                    if (password_verify($password, $storedHash)) {
                        $_SESSION["user_id"] = $row["ID"];
                        $_SESSION["name"] = $row["FullName"];
                        $_SESSION["email"] = $row["Email"];
                        $_SESSION["role"] = "volunteer";
                        
                        resetFailedAttempts($email, $conn);
                        
                        header("Location: volunteer_dashboard.php");
                        exit;
                    } else {
                        recordFailedAttempt($email, $conn);
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
                        
                        resetFailedAttempts($email, $conn);
                        
                        header("Location: volunteer_dashboard.php");
                        exit;
                    } else {
                        recordFailedAttempt($email, $conn);
                    }
                }
            }

            // Check if account is locked after failed attempt
            $lockCheck = isAccountLocked($email, $conn);
            if ($lockCheck['locked']) {
                $message = "⚠️ Account locked. Try again in " . $lockCheck['minutes'] . " minutes (until " . $lockCheck['until'] . ")";
            } else {
                // Get current failed attempts count
                $countSql = "SELECT failed_attempts FROM LoginLocks WHERE email = ?";
                $countStmt = sqlsrv_query($conn, $countSql, array($email));
                
                $attemptsLeft = MAX_FAILED_ATTEMPTS;
                if ($countStmt && sqlsrv_has_rows($countStmt)) {
                    $countRow = sqlsrv_fetch_array($countStmt, SQLSRV_FETCH_ASSOC);
                    $attemptsLeft = MAX_FAILED_ATTEMPTS - $countRow['failed_attempts'];
                }
                
                if ($attemptsLeft > 0) {
                    $message = "Invalid email or password. " . $attemptsLeft . " attempts left.";
                } else {
                    $message = "Invalid email or password. Account will be locked after next failed attempt.";
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
            max-width: 400px;
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
        
        .security-info {
            background-color: #f8f9fa;
            border: 1px solid #e1e5e9;
            border-radius: 8px;
            padding: 10px;
            margin-top: 15px;
            font-size: 12px;
            color: #666;
            text-align: center;
        }
        
        @media (max-width: 480px) {
            .container {
                padding: 30px 20px;
            }
            
            h2 {
                font-size: 24px;
            }
        }
    </style>
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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

    <!-- Security Info -->
    <div class="security-info">
        <i class="fas fa-shield-alt"></i> Security: 3 failed attempts = 15 minute lockout
    </div>

    <form action="" method="POST" id="loginForm">
        <div class="form-group">
            <label for="email"><i class="fas fa-envelope"></i> Email Address</label>
            <input type="email" id="email" name="email" 
                   placeholder="Enter your email" 
                   value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>" 
                   required
                   autocomplete="email">
        </div>
        
        <div class="form-group">
            <label for="password"><i class="fas fa-lock"></i> Password</label>
            <div class="password-container">
                <input type="password" id="password" name="password" 
                       placeholder="Enter your password" 
                       required
                       autocomplete="current-password"
                       minlength="6">
                <button type="button" class="toggle-password" onclick="togglePassword()">
                    <i class="fas fa-eye"></i>
                </button>
            </div>
        </div>
        
        <button type="submit" id="submitBtn">
            <i class="fas fa-sign-in-alt"></i> Login
        </button>
        
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
</div>

<script>
    function togglePassword() {
        const passwordField = document.getElementById('password');
        const toggleButton = document.querySelector('.toggle-password i');
        
        if (passwordField.type === 'password') {
            passwordField.type = 'text';
            toggleButton.className = 'fas fa-eye-slash';
        } else {
            passwordField.type = 'password';
            toggleButton.className = 'fas fa-eye';
        }
    }
    
    // Form validation
    document.getElementById('loginForm').addEventListener('submit', function(e) {
        const email = document.getElementById('email').value.trim();
        const password = document.getElementById('password').value.trim();
        const submitBtn = document.getElementById('submitBtn');
        
        // Disable button to prevent double click
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Logging in...';
        
        if (!email || !password) {
            e.preventDefault();
            alert('Please fill in all fields');
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fas fa-sign-in-alt"></i> Login';
            return false;
        }
        
        // Email validation
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(email)) {
            e.preventDefault();
            alert('Please enter a valid email address');
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fas fa-sign-in-alt"></i> Login';
            return false;
        }
        
        // Password minimum length
        if (password.length < 6) {
            e.preventDefault();
            alert('Password must be at least 6 characters');
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fas fa-sign-in-alt"></i> Login';
            return false;
        }
        
        return true;
    });
    
    // Reset button if user goes back
    window.addEventListener('pageshow', function(event) {
        if (event.persisted) {
            const submitBtn = document.getElementById('submitBtn');
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fas fa-sign-in-alt"></i> Login';
        }
    });
    
    // Auto-focus on email field
    document.addEventListener('DOMContentLoaded', function() {
        document.getElementById('email').focus();
    });
</script>

</body>
</html>