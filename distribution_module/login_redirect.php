<?php
// This page redirects to the external login system
// Or provides a simple login form if needed

// Option 1: Redirect to external login
header("Location: http://10.147.17.30:8000/login.php");
exit;

// Option 2: Create a local login form
?>
<!DOCTYPE html>
<html>
<head>
    <title>Volunteer Login</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f5f5f5; padding: 50px; }
        .login-box { background: white; padding: 30px; max-width: 400px; margin: 0 auto; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h2 { text-align: center; color: #333; }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 5px; color: #555; }
        input[type="email"], input[type="password"] { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; }
        button { width: 100%; padding: 10px; background: #3498db; color: white; border: none; border-radius: 5px; cursor: pointer; }
        button:hover { background: #2980b9; }
        .error { color: #e74c3c; text-align: center; margin-bottom: 15px; }
    </style>
</head>
<body>
    <div class="login-box">
        <h2>Volunteer Login</h2>
        <p style="text-align: center; color: #666; margin-bottom: 20px;">
            Please login using the external system<br>
            or use demo credentials below:
        </p>
        <form action="login_handler.php" method="POST">
            <div class="form-group">
                <label>Email:</label>
                <input type="email" name="email" required>
            </div>
            <div class="form-group">
                <label>Password:</label>
                <input type="password" name="password" required>
            </div>
            <button type="submit">Login</button>
        </form>
        <p style="text-align: center; margin-top: 20px; color: #888;">
            Demo: Try any email with password "demo123"
        </p>
    </div>
</body>
</html>