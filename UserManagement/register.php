<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once "connection.php";

$message = "";

// ============================
// FETCH NGO LIST FOR DROPDOWN
// ============================
$ngoList = [];
$ngoQuery = "SELECT NGOID, NGOName FROM NGO ORDER BY NGOName ASC";
$ngoResult = sqlsrv_query($conn, $ngoQuery);

if ($ngoResult) {
    while ($row = sqlsrv_fetch_array($ngoResult, SQLSRV_FETCH_ASSOC)) {
        $ngoList[] = $row;
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $role = $_POST['role'];
    $fullname = $_POST['fullname'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $address = $_POST['address'] ?? null;
    $password = $_POST['password'];

    // ============================
    // ADMIN REGISTRATION
    // ============================
    if ($role == "admin") {

        $sql = "INSERT INTO Admin (FullName, Email, PasswordHash, Phone, Role, CreatedAt)
                VALUES (?, ?, ?, ?, 'admin', GETDATE())";

        $params = array($fullname, $email, $password, $phone);

        $stmt = sqlsrv_query($conn, $sql, $params);

        $message = $stmt ? "Admin registered successfully!" :
            "Error: " . print_r(sqlsrv_errors(), true);
    }

    // ============================
    // NGO REGISTRATION
    // ============================
    if ($role == "ngo") {

        $query = "SELECT TOP 1 RegistrationNo FROM NGO ORDER BY NGOID DESC";
        $result = sqlsrv_query($conn, $query);

        $newRegNo = "REG001";

        if ($result && $row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC)) {
            $lastReg = $row['RegistrationNo'];
            $num = (int)substr($lastReg, 3);
            $num++;
            $newRegNo = "REG" . str_pad($num, 3, "0", STR_PAD_LEFT);
        }

        $sql = "INSERT INTO NGO (NGOName, RegistrationNo, Email, Phone, Address, PasswordHash, CreatedAt)
                VALUES (?, ?, ?, ?, ?, ?, GETDATE())";

        $params = array($fullname, $newRegNo, $email, $phone, $address, $password);

        $stmt = sqlsrv_query($conn, $sql, $params);

        $message = $stmt ? "NGO registered successfully! Generated ID: $newRegNo" :
            "Error: " . print_r(sqlsrv_errors(), true);
    }

    // ============================
    // VOLUNTEER REGISTRATION
    // ============================
    if ($role == "volunteer") {

        $skill = $_POST['skill'];
        $assignedNGO = $_POST['assignedNGO'];  // NGO selected by volunteer

        $sql = "INSERT INTO Volunteer (FullName, Email, Phone, Address, PasswordHash, SkillCategory, AssignedNGO)
                VALUES (?, ?, ?, ?, ?, ?, ?)";

        $params = array($fullname, $email, $phone, $address, $password, $skill, $assignedNGO);

        $stmt = sqlsrv_query($conn, $sql, $params);

        $message = $stmt ? "Volunteer registered successfully!" :
            "Error: " . print_r(sqlsrv_errors(), true);
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
            width: 100%; padding: 10px; background: #28a745;
            color: white; border: none; border-radius: 5px;
            cursor: pointer;
        }
        button:hover { background: #218838; }
        .message { color: blue; text-align: center; font-weight: bold; }
    </style>

    <script>
        function updateForm() {
            let role = document.getElementById("role").value;

            // Address for NGO + Volunteer
            document.getElementById("address_group").style.display =
                (role === "ngo" || role === "volunteer") ? "block" : "none";

            // Skill for Volunteer ONLY
            document.getElementById("skill_group").style.display =
                (role === "volunteer") ? "block" : "none";

            // NGO selection (Volunteer only)
            document.getElementById("ngo_group").style.display =
                (role === "volunteer") ? "block" : "none";
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

        <!-- ADDRESS -->
        <div id="address_group" style="display:none;">
            <input type="text" name="address" placeholder="Address">
        </div>

        <!-- SKILL -->
        <div id="skill_group" style="display:none;">
            <label>Skill Category:</label>
            <select name="skill">
                <option value="">-- Select Skill --</option>
                <option value="Medical">Medical</option>
                <option value="Helper">Helper</option>
                <option value="Rescue">Rescue</option>
                <option value="Logistics">Logistics</option>
                <option value="Technical">Technical</option>
                <option value="Driver">Driver</option>
                <option value="Food Supply">Food Supply</option>
            </select>
        </div>

        <!-- NGO CHOICE -->
        <div id="ngo_group" style="display:none;">
            <label>Select NGO:</label>
            <select name="assignedNGO">
                <option value="">-- Choose NGO --</option>
                <?php foreach ($ngoList as $ngo) { ?>
                    <option value="<?= $ngo['NGOID'] ?>">
                        <?= $ngo['NGOName'] ?>
                    </option>
                <?php } ?>
            </select>
        </div>

        <input type="password" name="password" placeholder="Password" required>

        <button type="submit">Register</button>
    </form>
</div>

</body>
</html>
