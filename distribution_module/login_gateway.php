<?php
// login_gateway.php - Accepts volunteer IDs directly from main system
session_start();

// Check if volunteer_id is passed directly (from main system)
if (isset($_GET['volunteer_id']) && !empty($_GET['volunteer_id'])) {
    $volunteer_id = intval($_GET['volunteer_id']);
    
    // Store the volunteer ID in session
    $_SESSION['pending_volunteer_id'] = $volunteer_id;
    
    // Redirect to main login system with distribution preference
    header("Location: http://10.147.17.30:8000/login.php?system=distribution&volunteer_id=" . $volunteer_id . "&return_to=distribution");
    exit();
}

// Check if we have a stored volunteer ID in session (from previous redirect)
if (isset($_SESSION['pending_volunteer_id']) && !empty($_SESSION['pending_volunteer_id'])) {
    $volunteer_id = $_SESSION['pending_volunteer_id'];
    
    // Redirect to main login system
    header("Location: http://10.147.17.30:8000/login.php?system=distribution&volunteer_id=" . $volunteer_id . "&return_to=distribution");
    exit();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Distribution System Login</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
            margin: 0;
        }
        
        .gateway-container {
            background: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            text-align: center;
            max-width: 500px;
            width: 100%;
        }
        
        h1 {
            color: #333;
            margin-bottom: 20px;
        }
        
        p {
            color: #666;
            line-height: 1.6;
            margin-bottom: 30px;
        }
        
        .login-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s;
        }
        
        .login-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 7px 14px rgba(102, 126, 234, 0.3);
        }
        
        .info-box {
            background: #f0f7ff;
            border: 1px solid #c2e0ff;
            border-radius: 8px;
            padding: 15px;
            margin: 20px 0;
            text-align: left;
        }
        
        .info-box h3 {
            color: #0366d6;
            margin-top: 0;
            font-size: 16px;
        }
        
        .demo-login {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        
        .demo-login input {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            width: 200px;
            margin-right: 10px;
        }
        
        .demo-login button {
            background: #9b59b6;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
        }
        
        .auto-redirect {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.7; }
            100% { opacity: 1; }
        }
    </style>
</head>
<body>
    <div class="gateway-container">
        <h1>Distribution System Login</h1>
        
        <?php if (isset($_GET['auto_redirect']) && $_GET['auto_redirect'] == 'true'): ?>
            <div class="auto-redirect">
                <strong>Auto-redirecting to main login system...</strong>
                <p>Please wait or click the button below if redirection doesn't happen automatically.</p>
            </div>
            
            <script>
                // Auto-redirect after 3 seconds
                setTimeout(function() {
                    window.location.href = "http://10.147.17.30:8000/login.php?system=distribution&return_to=distribution";
                }, 3000);
            </script>
        <?php endif; ?>
        
        <p>Welcome to the Distribution Management System for Disaster Relief.</p>
        
        <div class="info-box">
            <h3>How to Login:</h3>
            <ol>
                <li>Click the button below to go to the main login system</li>
                <li>Select "Distribution System" option</li>
                <li>Enter your volunteer credentials</li>
                <li>You'll be redirected back to this system</li>
            </ol>
        </div>
        
        <a href="http://10.147.17.30:8000/login.php?system=distribution&return_to=distribution" class="login-btn">
            Go to Login Page
        </a>
        
        <div class="demo-login">
            <h3>Demo Login (for testing):</h3>
            <form action="demo_login.php" method="POST">
                <input type="number" name="volunteer_id" placeholder="Enter Volunteer ID (1-100)" min="1" max="100" required>
                <button type="submit">Demo Login</button>
            </form>
            <p style="font-size: 12px; color: #888; margin-top: 10px;">
                Use any number 1-100 for testing without external login
            </p>
        </div>
    </div>
</body>
</html>