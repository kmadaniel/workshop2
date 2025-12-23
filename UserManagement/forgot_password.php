<?php
session_start();
require_once "connection.php";

$error = "";
$success = "";

if($_SERVER['REQUEST_METHOD'] == 'POST'){
    $email = trim($_POST['email']);
    $last4phone = trim($_POST['last4phone']);
    
    if(empty($email) || empty($last4phone)){
        $error = "Please fill in all fields";
    } else {
        // Check dalam semua tables (Admin, NGO, Volunteer)
        $tables = [
            ['table' => 'Admin', 'id' => 'AdminID', 'name' => 'FullName', 'phone' => 'Phone'],
            ['table' => 'NGO', 'id' => 'NGOID', 'name' => 'NGOName', 'phone' => 'Phone'],
            ['table' => 'Volunteer', 'id' => 'VolunteerID', 'name' => 'FullName', 'phone' => 'Phone']
        ];
        
        $userFound = false;
        $userData = [];
        
        foreach($tables as $tableInfo){
            $sql = "SELECT * FROM " . $tableInfo['table'] . " WHERE Email = ?";
            $stmt = sqlsrv_query($conn, $sql, array($email));
            
            if($stmt && sqlsrv_has_rows($stmt)){
                $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
                $phone = $row['Phone'] ?? '';
                
                // Check last 4 digits phone
                if(!empty($phone) && substr($phone, -4) == $last4phone){
                    $userFound = true;
                    $userData = [
                        'table' => $tableInfo['table'],
                        'id' => $row[$tableInfo['id']],
                        'name' => $row[$tableInfo['name']],
                        'email' => $row['Email']
                    ];
                    break;
                }
            }
        }
        
        if($userFound){
            $_SESSION['reset_user'] = $userData;
            header("Location: reset_password.php");
            exit();
        } else {
            $error = "Invalid email or last 4 digits of phone number";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Forgot Password</title>
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
        
        .info-box {
            background-color: #e8f4fd;
            color: #0366d6;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            border-left: 4px solid #667eea;
        }
        
        .info-box h4 {
            margin-bottom: 10px;
            color: #333;
        }
        
        .info-box ul {
            padding-left: 20px;
            margin-bottom: 10px;
        }
        
        .info-box li {
            margin-bottom: 5px;
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
        input[type="text"] {
            width: 100%;
            padding: 14px;
            border: 2px solid #e1e5e9;
            border-radius: 10px;
            font-size: 16px;
            transition: all 0.3s;
            background: #f8f9fa;
        }
        
        input[type="email"]:focus,
        input[type="text"]:focus {
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
        
        .back-link {
            text-align: center;
            margin-top: 20px;
        }
        
        .back-link a {
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
        }
        
        .back-link a:hover {
            text-decoration: underline;
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
    <h2><i class="fas fa-key"></i> Forgot Password</h2>
    
    <?php if($error): ?>
        <div class="error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    
    <?php if($success): ?>
        <div class="success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>
    
    <div class="info-box">
        <h4><i class="fas fa-info-circle"></i> How to Reset Password</h4>
        <ul>
            <li>Enter your registered email</li>
            <li>Enter <strong>last 4 digits</strong> of your phone number</li>
            <li>If verified, you can set a new password</li>
        </ul>
        <p style="font-size: 12px; color: #666;">
            <i class="fas fa-exclamation-triangle"></i> Contact administrator if you don't remember your phone number
        </p>
    </div>
    
    <form method="POST">
        <div class="form-group">
            <label for="email">Email Address</label>
            <input type="email" id="email" name="email" 
                   placeholder="Enter your registered email" 
                   required>
        </div>
        
        <div class="form-group">
            <label for="last4phone">Last 4 Digits of Phone Number</label>
            <input type="text" id="last4phone" name="last4phone" 
                   placeholder="e.g., 3456" 
                   maxlength="4" 
                   pattern="[0-9]{4}"
                   title="Enter 4 digits only"
                   required>
        </div>
        
        <button type="submit">
            <i class="fas fa-check"></i> Verify & Continue
        </button>
    </form>
    
    <div class="back-link">
        <a href="login.php">
            <i class="fas fa-arrow-left"></i> Back to Login
        </a>
    </div>
</div>

<script>
    // Format phone input to accept only numbers
    document.getElementById('last4phone').addEventListener('input', function(e) {
        this.value = this.value.replace(/[^0-9]/g, '');
    });
    
    // Form validation
    document.querySelector('form').addEventListener('submit', function(e) {
        const email = document.getElementById('email').value.trim();
        const phone = document.getElementById('last4phone').value.trim();
        
        if(!email || !phone) {
            e.preventDefault();
            alert('Please fill in all fields');
            return false;
        }
        
        if(phone.length !== 4) {
            e.preventDefault();
            alert('Please enter exactly 4 digits for phone number');
            return false;
        }
        
        const submitBtn = document.querySelector('button[type="submit"]');
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Verifying...';
        
        return true;
    });
</script>

</body>
</html>