<?php
include 'db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role = 'user';

    // Insert into accounts table
    $stmt = $conn->prepare("INSERT INTO accounts(username,password,role) VALUES(?,?,?)");
    $stmt->execute([$username, $password, $role]);
    $account_id = $conn->lastInsertId();

    // Get needs and location from dropdown
    $needs = $_POST['needs'];
    $location_id = $_POST['location_id'];

    // Insert into victim table
    $stmt2 = $conn->prepare("INSERT INTO victim(account_id,name,age,gender,phone_num,address,needs,location_id) VALUES(?,?,?,?,?,?,?,?)");
    $stmt2->execute([
        $account_id,
        $_POST['name'],
        $_POST['age'],
        $_POST['gender'],
        $_POST['phone'],
        $_POST['address'],
        $needs,
        $location_id
    ]);

    $success = "Registration successful! <a href='login.php'>Login here</a>";
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Victim Registration</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
<div class="container">
    <h2>Victim Registration</h2>
    <?php if(isset($success)) echo "<p style='color:green;'>$success</p>"; ?>
    <form method="POST" action="">
        <input type="text" name="username" placeholder="Username" required>
        <input type="password" name="password" placeholder="Password" required>
        <input type="text" name="name" placeholder="Full Name" required>
        <input type="number" name="age" placeholder="Age" required>
        <select name="gender" required>
            <option value="">Select Gender</option>
            <option value="Male">Male</option>
            <option value="Female">Female</option>
        </select>
        <input type="text" name="phone" placeholder="Phone Number" required>
        <input type="text" name="address" placeholder="Address" required>
        
        <!-- Fixed Location Dropdown -->
        <label>Location:</label><br>
        <select name="location_id" required>
            <option value="">Select Location</option>
            <option value="1">Alor Gajah</option>
            <option value="2">Melaka Tengah</option>
            <option value="3">Jasin</option>
        </select>

        <button type="submit">Register</button>
    </form>
    <p>Already have an account? <a href="login.php">Login here</a></p>
</div>
</body>
</html>
