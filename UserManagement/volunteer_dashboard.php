<?php
session_start();
if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "volunteer") {
    header("Location: login.php");
    exit;
}
?>
<h1>Welcome Helper: <?php echo $_SESSION["name"]; ?></h1>
<a href="logout.php">Logout</a>
