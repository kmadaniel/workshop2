<?php
session_start();
include 'db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $stmt = $conn->prepare("SELECT * FROM accounts WHERE username=?");
    $stmt->execute([$_POST['username']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if($user && password_verify($_POST['password'],$user['password'])) {
        $_SESSION['account_id'] = $user['account_id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = strtolower($user['role']); // normalize role

        // Redirect based on role
        if ($_SESSION['role'] === 'admin') {
            header("Location: admin_dashboard.php");
            exit();
        } elseif ($_SESSION['role'] === 'victim') {
            header("Location: dashboard.php");
            exit();
        } else {
            $error = "Unknown role: " . htmlspecialchars($user['role']);
        }
    } else {
        $error = "Invalid username or password!";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Login</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
<div class="container">
    <h2>Login</h2>
    <?php if($error) echo "<p class='error'>$error</p>"; ?>
    <form method="POST" action="">
        <input type="text" name="username" placeholder="Username" required>
        <input type="password" name="password" placeholder="Password" required>
        <button type="submit">Login</button>
    </form>
    <p>Don't have an account? <a href="register.php">Register here</a></p>
</div>
</body>
</html>
