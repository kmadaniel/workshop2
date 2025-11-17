<?php
include "config.php";

if(isset($_POST['save'])) {
    $date = $_POST['date'];
    $quantity = $_POST['quantity_sent'];
    $comments = $_POST['comments'];

    $sql = "INSERT INTO distribution (date, quantity_sent, status, comments)
            VALUES ('$date', '$quantity', 'Pending', '$comments')";

    if($conn->query($sql)) {
        echo "<script>alert('Distribution Created!');window.location='distribution_list.php';</script>";
    } else {
        echo "Error: " . $conn->error;
    }
}
?>

<link rel="stylesheet" href="style/style.css">
<div class="container">
    <h2>Create Distribution</h2>

    <form method="POST">
        <label>Date</label>
        <input type="date" name="date" required>

        <label>Quantity Sent</label>
        <input type="number" name="quantity_sent" required>

        <label>Comments</label>
        <textarea name="comments"></textarea>

        <button type="submit" name="save">Create</button>
    </form>
</div>
