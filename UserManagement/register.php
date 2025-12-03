<?php
// ============================
// ENABLE ERROR REPORTING
// ============================
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once "connection.php";

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $role = $_POST['role'];
    $fullname = $_POST['fullname'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $address = $_POST['address'] ?? null;  // volunteer & NGO only
    $registrationNo = $_POST['registrationNo'] ?? null; // NGO only
    $password = $_POST['password'];

    // ============================
    // ADMIN REGISTRATION
    // ============================
    if ($role == "admin") {
        $sql = "INSERT INTO Admin (FullName, Email, PasswordHash, Phone, Role, CreatedAt)
                VALUES (?, ?, ?, ?, 'admin', GETDATE())";

        $params = array($fullname, $email, $password, $phone);
        $stmt = sqlsrv_query($conn, $sql, $params);

        if ($stmt) {
            $message = "Admin registered successfully!";
        } else {
            $message = "Error registering admin: " . print_r(sqlsrv_errors(), true);
        }
    }

    // ============================
    // NGO REGISTRATION
    // ============================
    if ($role == "ngo") {
        $sql = "INSERT INTO NGO (NGOName, RegistrationNo, Email, Phone, Address, PasswordHash, CreatedAt)
                VALUES (?, ?, ?, ?, ?, ?, GETDATE())";

        $params = array($fullname, $registrationNo, $email, $phone, $address, $password);
        $stmt = sqlsrv_query($conn, $sql, $params);

        if ($stmt) {
            $message = "NGO registered successfully!";
        } else {
            $message = "Error registering NGO: " . print_r(sqlsrv_errors(), true);
        }
    }

    // ============================
    // VOLUNTEER REGISTRATION
    // ============================
    if ($role == "volunteer") {
        $sql = "INSERT INTO Volunteer (FullName, Email, Phone, Address, PasswordHash)
                VALUES (?, ?, ?, ?, ?)";

        $params = array($fullname, $email, $phone, $address, $password);
        $stmt = sqlsrv_query($conn, $sql, $params);

        if ($stmt) {
            $message = "Volunteer registered successfully!";
        } else {
            $message = "Error registering volunteer: " . print_r(sqlsrv_errors(), true);
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Register User</title>
    <style>
        body { font-family: Arial; background: #f4f4f4; }
        .container {
            width: 400px; background: white; padding: 20px;
            margin: 30px auto; border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.2);
        }
        input, select {
            width: 100%; padding: 10px; margin: 10px 0;
            border: 1px solid #ccc; border-radius: 5px;
        }
        button {
            width: 100%; padding: 10px;
            background: #28a745; color: white;
            border: none; border-radius: 5px; cursor: pointer;
        }
        button:hover { background: #218838; }
        .message { color: blue; text-align: center; }
    </style>

    <script>
        // Show/Hide fields based on role
        function updateForm() {
            let role = document.getElementById("role").value;

            document.getElementById("registrationNo_group").style.display =
                role === "ngo" ? "block" : "none";

            document.getElementById("address_group").style.display =
                (role === "ngo" || role === "volunteer") ? "block" : "none";
        }
    </script>
</head>

<body>

<div class="container">
    <h2>Register User</h2>

    <?php if ($message) { ?>
        <p class="message"><?= $message ?></p>
    <?php } ?>

    <form method="POST" action="">
        <label>Select Role:</label>
        <select id="role" name="role" onchange="updateForm()" required>
            <option value="">-- Choose Role --</option>
            <option value="admin">Admin</option>
            <option value="ngo">NGO</option>
            <option value="volunteer">Volunteer</option>
        </select>

        <input type="text" name="fullname" placeholder="Full Name / NGO Name" required>
        <input type="email" name="email" placeholder="Email" required>
        <input type="text" name="phone" placeholder="Phone" required>

        <!-- NGO ONLY -->
        <div id="registrationNo_group" style="display:none;">
            <input type="text" name="registrationNo" placeholder="NGO Registration Number">
        </div>

        <!-- NGO & VOLUNTEER -->
        <div id="address_group" style="display:none;">
            <input type="text" name="address" placeholder="Address">
        </div>

        <input type="password" name="password" placeholder="Password" required>

        <button type="submit">Register</button>
    </form>
</div>

</body>
</html>
