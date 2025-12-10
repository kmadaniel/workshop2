<?php
include "../db.php";

$id = $_GET['id'] ?? null;

if (!$id) {
    die("Invalid Victim ID.");
}

// Fetch victim data
$stmt = $conn->prepare("SELECT * FROM victim WHERE victim_id = ?");
$stmt->execute([$id]);
$victim = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$victim) {
    die("Victim not found.");
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'];
    $age = $_POST['age'];
    $gender = $_POST['gender'];
    $district = $_POST['district'];

    $update = $conn->prepare("UPDATE victim SET name = ?, age = ?, gender = ?, district = ? WHERE victim_id = ?");
    $update->execute([$name, $age, $gender, $district, $id]);

    header("Location: list_victims.php");
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Edit Victim</title>
</head>
<body>
<h2>Edit Victim</h2>
<form method="POST">
    <label>Name:</label><br>
    <input type="text" name="name" value="<?= htmlspecialchars($victim['name']) ?>" required><br><br>

    <label>Age:</label><br>
    <input type="number" name="age" value="<?= $victim['age'] ?>" required><br><br>

    <label>Gender:</label><br>
    <select name="gender">
        <option value="Male" <?= $victim['gender']=='Male'?'selected':'' ?>>Male</option>
        <option value="Female" <?= $victim['gender']=='Female'?'selected':'' ?>>Female</option>
    </select><br><br>

    <label>District:</label><br>
    <select name="district">
        <option value="Melaka Tengah" <?= $victim['district']=='Melaka Tengah'?'selected':'' ?>>Melaka Tengah</option>
        <option value="Jasin" <?= $victim['district']=='Jasin'?'selected':'' ?>>Jasin</option>
        <option value="Alor Gajah" <?= $victim['district']=='Alor Gajah'?'selected':'' ?>>Alor Gajah</option>
    </select><br><br>

    <button type="submit">Update Victim</button>
</form>
</body>
</html>
