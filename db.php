<?php
$host = "localhost";
$port = "5433";
$dbname = "victimdisaster";
$user = "postgres";
$pass = "0212";

try {
    $conn = new PDO("pgsql:host=$host;port=$port;dbname=$dbname;", $user, $pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "PostgreSQL connected successfully!";
} catch (PDOException $e) {
    echo "Connection failed: " . $e->getMessage();
}
?>
