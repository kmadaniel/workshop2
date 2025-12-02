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

    $email = $_POST['email'];
    $password = $_POST['password'];

    // --- CHECK ADMIN TABLE ---
    $sql = "SELECT AdminID AS ID, FullName, Email, PasswordHash, 'admin' AS Role
            FROM Admin WHERE Email = ?";
    $stmt = sqlsrv_query($conn, $sql, array($email));

    if ($stmt && sqlsrv_has_rows($stmt)) {
        $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        if ($password == $row["PasswordHash"]) { // change to password_verify() if hashed
            $_SESSION["user_id"] = $row["ID"];
            $_SESSION["name"] = $row["FullName"];
            $_SESSION["role"] = "admin";
            header("Location: admin_dashboard.php");
            exit;
        }
    }

    // --- CHECK NGO TABLE ---
    $sql = "SELECT NGOID AS ID, NGOName AS FullName, Email, PasswordHash, 'ngo' AS Role
            FROM NGO WHERE Email = ?";
    $stmt = sqlsrv_query($conn, $sql, array($email));

    if ($stmt && sqlsrv_has_rows($stmt)) {
        $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        if ($password == $row["PasswordHash"]) {
            $_SESSION["user_id"] = $row["ID"];
            $_SESSION["name"] = $row["FullName"];
            $_SESSION["role"] = "ngo";
            header("Location: ngo_dashboard.php");
            exit;
        }
    }

    // --- CHECK VOLUNTEER TABLE ---
    $sql = "SELECT VolunteerID AS ID, FullName, Email, PasswordHash, 'volunteer' AS Role
            FROM Volunteer WHERE Email = ?";
    $stmt = sqlsrv_query($conn, $sql, array($email));

    if ($stmt && sqlsrv_has_rows($stmt)) {
        $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        if ($password == $row["PasswordHash"]) {
            $_SESSION["user_id"] = $row["ID"];
            $_SESSION["name"] = $row["FullName"];
            $_SESSION["role"] = "volunteer";
            header("Location: volunteer_dashboard.php");
            exit;
        }
    }

    // kalau semua fail
    $message = "Invalid email or password.";
}

?>

<!DOCTYPE html>
<html>
<head>
    <title>Login Page</title>
    <style>
        body { font-family: Arial; background: #f4f4f4; }
        .container {
            width: 350px; background: white; padding: 20px;
            margin: 80px auto; border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.2);
        }
        input {
            width: 100%; padding: 10px; margin: 10px 0;
            border: 1px solid #ccc; border-radius: 5px;
        }
        button {
            width: 100%; padding: 10px;
            background: #007bff; color: white;
            border: none; border-radius: 5px; cursor: pointer;
        }
        button:hover { background: #0056b3; }
        .error { color: red; }
    </style>
</head>
<body>

<div class="container">
    <h2>Login</h2>

    <?php if ($message) { ?>
        <p class="error"><?= $message ?></p>
    <?php } ?>

    <form action="" method="POST">
        <input type="email" name="email" placeholder="Enter email" required>
        <input type="password" name="password" placeholder="Enter password" required>
        <button type="submit">Login</button>
    </form>
</div>

</body>
</html>
