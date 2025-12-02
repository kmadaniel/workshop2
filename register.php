<?php
include 'db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

    // Insert into accounts table
    $stmt = $conn->prepare("INSERT INTO accounts(username,password) VALUES(:username,:password)");
    $stmt->execute([
        ':username' => $username,
        ':password' => $password
    ]);
    $account_id = $conn->lastInsertId();

    // Insert new location (manual input by victim)
    $stmt_loc = $conn->prepare("
        INSERT INTO location(address, postal_code, city, country)
        VALUES(:address, :postal_code, :city, :country)
        RETURNING location_id
    ");
    $stmt_loc->execute([
        ':address' => $_POST['address'],
        ':postal_code' => $_POST['postal_code'],
        ':city' => $_POST['city'],
        ':country' => $_POST['country']
    ]);
    $location_id = $stmt_loc->fetchColumn(); // get the inserted location_id

    // Insert victim info
    $stmt2 = $conn->prepare("
        INSERT INTO victim(account_id,name,age,gender,phone_num,address,location_id)
        VALUES(:account_id,:name,:age,:gender,:phone_num,:address,:location_id)
    ");
    $stmt2->execute([
        ':account_id' => $account_id,
        ':name' => $_POST['name'],
        ':age' => $_POST['age'],
        ':gender' => $_POST['gender'],
        ':phone_num' => $_POST['phone'],
        ':address' => $_POST['address'], // victim's personal address
        ':location_id' => $location_id
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
        
        <!-- Victim manually types location -->
        <input type="text" name="address" placeholder="Street / House Address" required>
        <input type="text" name="postal_code" placeholder="Postal Code" required>
        <input type="text" name="city" placeholder="City" required>
        <input type="text" name="country" placeholder="Country" required>

        <button type="submit">Register</button>
    </form>
    <p>Already have an account? <a href="login.php">Login here</a></p>
</div>
</body>
</html>
