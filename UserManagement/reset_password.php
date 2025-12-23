<?php
session_start();
require_once "connection.php";

// Check jika user sudah verified
if(!isset($_SESSION['reset_user'])){
    header("Location: forgot_password_simple.php");
    exit();
}

$error = "";
$success = "";

if($_SERVER['REQUEST_METHOD'] == 'POST'){
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    if(empty($new_password) || empty($confirm_password)){
        $error = "Please fill in all fields";
    } elseif(strlen($new_password) < 6){
        $error = "Password must be at least 6 characters";
    } elseif($new_password != $confirm_password){
        $error = "Passwords do not match";
    } else {
        // Hash password baru
        $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);
        
        $userData = $_SESSION['reset_user'];
        $table = $userData['table'];
        $id_field = ($table == 'Admin') ? 'AdminID' : 
                   (($table == 'NGO') ? 'NGOID' : 'VolunteerID');
        
        $sql = "UPDATE $table SET PasswordHash = ? WHERE $id_field = ?";
        $params = array($hashed_password, $userData['id']);
        $stmt = sqlsrv_query($conn, $sql, $params);
        
        if($stmt){
            $success = "Password reset successfully!";
            // Clear session
            unset($_SESSION['reset_user']);
            // Auto redirect to login after 3 seconds
            header("refresh:3;url=login.php");
        } else {
            $error = "Failed to reset password. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reset Password</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
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
        
        .user-info {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            text-align: center;
            border-left: 4px solid #28a745;
        }
        
        .user-info h4 {
            color: #28a745;
            margin-bottom: 5px;
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
        
        .success {
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
        
        input[type="password"] {
            width: 100%;
            padding: 14px;
            border: 2px solid #e1e5e9;
            border-radius: 10px;
            font-size: 16px;
            transition: all 0.3s;
            background: #f8f9fa;
        }
        
        input[type="password"]:focus {
            outline: none;
            border-color: #667eea;
            background: #fff;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        button[type="submit"] {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
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
            box-shadow: 0 7px 14px rgba(40, 167, 69, 0.25);
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
    <h2><i class="fas fa-lock"></i> Set New Password</h2>
    
    <?php if(isset($_SESSION['reset_user'])): ?>
    <div class="user-info">
        <h4><i class="fas fa-user-check"></i> Verified</h4>
        <p>User: <strong><?php echo htmlspecialchars($_SESSION['reset_user']['name']); ?></strong></p>
        <p>Email: <strong><?php echo htmlspecialchars($_SESSION['reset_user']['email']); ?></strong></p>
    </div>
    <?php endif; ?>
    
    <?php if($error): ?>
        <div class="error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    
    <?php if($success): ?>
        <div class="success">
            <?php echo htmlspecialchars($success); ?><br>
            <small>Redirecting to login page in 3 seconds...</small>
        </div>
    <?php else: ?>
        <form method="POST">
            <div class="form-group">
                <label for="new_password">New Password</label>
                <div class="password-container">
                    <input type="password" id="new_password" name="new_password" 
                           placeholder="Enter new password (min 6 characters)" 
                           required>
                    <button type="button" class="toggle-password" onclick="togglePassword('new_password')">👁️</button>
                </div>
            </div>
            
            <div class="form-group">
                <label for="confirm_password">Confirm New Password</label>
                <div class="password-container">
                    <input type="password" id="confirm_password" name="confirm_password" 
                           placeholder="Confirm new password" 
                           required>
                    <button type="button" class="toggle-password" onclick="togglePassword('confirm_password')">👁️</button>
                </div>
            </div>
            
            <button type="submit">
                <i class="fas fa-save"></i> Reset Password
            </button>
        </form>
    <?php endif; ?>
</div>

<script>
    function togglePassword(fieldId) {
        const field = document.getElementById(fieldId);
        if (field.type === 'password') {
            field.type = 'text';
        } else {
            field.type = 'password';
        }
    }
    
    // Password strength check
    document.getElementById('new_password').addEventListener('input', function(e) {
        const password = e.target.value;
        const strength = checkPasswordStrength(password);
        // You can add visual feedback here
    });
    
    function checkPasswordStrength(password) {
        let strength = 0;
        
        if(password.length >= 6) strength++;
        if(/[A-Z]/.test(password)) strength++;
        if(/[0-9]/.test(password)) strength++;
        if(/[^A-Za-z0-9]/.test(password)) strength++;
        
        return strength;
    }
    
    // Form validation
    document.querySelector('form')?.addEventListener('submit', function(e) {
        const newPass = document.getElementById('new_password').value;
        const confirmPass = document.getElementById('confirm_password').value;
        
        if(newPass.length < 6) {
            e.preventDefault();
            alert('Password must be at least 6 characters');
            return false;
        }
        
        if(newPass !== confirmPass) {
            e.preventDefault();
            alert('Passwords do not match');
            return false;
        }
        
        const submitBtn = document.querySelector('button[type="submit"]');
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
        
        return true;
    });
</script>

</body>
</html>