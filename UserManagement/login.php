<?php
// ============================
// ERROR REPORTING (WAJIB UNTUK DEBUG)
// ============================
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once "connection.php"; // file connection kamu

// ============================
// LOGIN PROCESS
// ============================
$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $email = trim($_POST['email']);
    $password = $_POST['password'];

    // Validate input
    if (empty($email) || empty($password)) {
        $message = "Please enter both email and password.";
    } else {
        
        // --- CHECK ADMIN TABLE ---
        $sql = "SELECT AdminID AS ID, FullName, Email, PasswordHash, 'admin' AS Role
                FROM Admin WHERE Email = ?";
        $stmt = sqlsrv_query($conn, $sql, array($email));

        if ($stmt && sqlsrv_has_rows($stmt)) {
            $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
            
            // Check if password hash is bcrypt format (starts with $2y$)
            $storedHash = $row["PasswordHash"];
            
            if (substr($storedHash, 0, 4) === '$2y$') {
                // Password is bcrypt hashed - use password_verify()
                if (password_verify($password, $storedHash)) {
                    $_SESSION["user_id"] = $row["ID"];
                    $_SESSION["name"] = $row["FullName"];
                    $_SESSION["email"] = $row["Email"];
                    $_SESSION["role"] = "admin";
                    header("Location: admin_dashboard.php");
                    exit;
                }
            } else {
                // Password is plain text or other format - backward compatibility
                if ($password === $storedHash) {
                    $_SESSION["user_id"] = $row["ID"];
                    $_SESSION["name"] = $row["FullName"];
                    $_SESSION["email"] = $row["Email"];
                    $_SESSION["role"] = "admin";
                    
                    // Auto-upgrade to bcrypt (optional)
                    $newHash = password_hash($password, PASSWORD_BCRYPT);
                    $update_sql = "UPDATE Admin SET PasswordHash = ? WHERE AdminID = ?";
                    sqlsrv_query($conn, $update_sql, array($newHash, $row["ID"]));
                    
                    header("Location: admin_dashboard.php");
                    exit;
                }
            }
        }

        // --- CHECK NGO TABLE ---
        $sql = "SELECT NGOID AS ID, NGOName AS FullName, Email, PasswordHash, 'ngo' AS Role
                FROM NGO WHERE Email = ?";
        $stmt = sqlsrv_query($conn, $sql, array($email));

        if ($stmt && sqlsrv_has_rows($stmt)) {
            $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
            $storedHash = $row["PasswordHash"];
            
            if (substr($storedHash, 0, 4) === '$2y$') {
                if (password_verify($password, $storedHash)) {
                    $_SESSION["user_id"] = $row["ID"];
                    $_SESSION["name"] = $row["FullName"];
                    $_SESSION["email"] = $row["Email"];
                    $_SESSION["role"] = "ngo";
                    header("Location: ngo_dashboard.php");
                    exit;
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
                    
                    header("Location: ngo_dashboard.php");
                    exit;
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
                    header("Location: volunteer_dashboard.php");
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
                    
                    header("Location: volunteer_dashboard.php");
                    exit;
                }
            }
        }

        // kalau semua fail
        $message = "Invalid email or password.";
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
        
        @media (max-width: 480px) {
            .container {
                padding: 30px 20px;
            }
            
            h2 {
                font-size: 24px;
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
        <div class="error"><?= htmlspecialchars($message) ?></div>
    <?php } ?>

    <form action="" method="POST" id="loginForm">
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
        
        <div class="register-link">
            Don't have an account? 
            <a href="register.php">Register here</a>
        </div>
    </form>
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
    
    // Form validation
    document.getElementById('loginForm').addEventListener('submit', function(e) {
        const email = document.getElementById('email').value.trim();
        const password = document.getElementById('password').value.trim();
        
        if (!email || !password) {
            e.preventDefault();
            alert('Please fill in all fields');
            return false;
        }
        
        // Email validation
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(email)) {
            e.preventDefault();
            alert('Please enter a valid email address');
            return false;
        }
    });
</script>

</body>
</html>