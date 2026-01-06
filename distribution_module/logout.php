<?php
session_start();
session_destroy();
header("Location: http://10.147.17.30:8000/login.php");
exit;
?>