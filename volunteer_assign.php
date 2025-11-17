<?php
include "config.php";
$distribution_id = $_GET['id'];

$vol = $conn->query("SELECT * FROM volunteer");

if (isset($_POST['assign'])) {
    // Make sure volunteer_id is set and valid
    if (empty($_POST['volunteer_id']) || empty($_POST['role'])) {
        echo "<script>alert('Please select a volunteer and specify a role.');</script>";
    } else {
        $volunteer = intval($_POST['volunteer_id']);  // Sanitize as integer
        $role = $conn->real_escape_string($_POST['role']); // Escape string for safety

        $sql = "INSERT INTO distribution_volunteer (volunteer_id, distribution_id, role)
                VALUES ($volunteer, $distribution_id, '$role')";

        if ($conn->query($sql)) {
            echo "<script>alert('Volunteer Assigned!');window.location='distribution_view.php?id=$distribution_id';</script>";
        } else {
            echo "Error: " . $conn->error;
        }
    }
}

?>

<link rel="stylesheet" href="styles.css">

<div class="container">
    <h2>Assign Volunteer</h2>

    <form method="POST">
        <label>Select Volunteer</label>
        <select name="volunteer_id" required>
            <option disabled selected>Choose</option>
            <?php while($v = $vol->fetch_assoc()): ?>
            <option value="<?= $v['volunteer_id'] ?>"><?= $v['name'] ?></option>
            <?php endwhile; ?>
        </select>

        <label>Role</label>
        <input type="text" name="role" required>

        <button name="assign">Assign</button>
    </form>
</div>
