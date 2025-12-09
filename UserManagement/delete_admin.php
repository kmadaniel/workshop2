<?php
session_start();
if(!isset($_SESSION['name']) || $_SESSION['role'] != "admin"){
    header("Location: login.php");
    exit();
}

// Check if ID is provided
if(!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: view_admin.php?delete=error");
    exit();
}

$adminId = $_GET['id'];

// Database connection
$serverName = "localhost";
$connectionOptions = array(
    "Database" => "UserManagement",
    "Uid" => "yanadb",
    "PWD" => "yana123"
);

$conn = sqlsrv_connect($serverName, $connectionOptions);

if($conn === false) {
    die(print_r(sqlsrv_errors(), true));
}

// Prevent deleting yourself
$currentAdminName = $_SESSION['name'];
$checkSql = "SELECT FullName FROM dbo.Admin WHERE AdminID = ?";
$checkParams = array($adminId);
$checkStmt = sqlsrv_query($conn, $checkSql, $checkParams);

if($checkStmt === false) {
    header("Location: view_admin.php?delete=error");
    exit();
}

$adminData = sqlsrv_fetch_array($checkStmt, SQLSRV_FETCH_ASSOC);
sqlsrv_free_stmt($checkStmt);

if($adminData && $adminData['FullName'] == $currentAdminName) {
    // Cannot delete yourself
    header("Location: view_admin.php?delete=self");
    exit();
}

// Delete the admin
$deleteSql = "DELETE FROM dbo.Admin WHERE AdminID = ?";
$deleteParams = array($adminId);
$deleteStmt = sqlsrv_query($conn, $deleteSql, $deleteParams);

if($deleteStmt === false) {
    header("Location: view_admin.php?delete=error");
} else {
    header("Location: view_admin.php?delete=success");
}

sqlsrv_free_stmt($deleteStmt);
sqlsrv_close($conn);
exit();
?>