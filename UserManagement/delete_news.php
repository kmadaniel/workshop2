<?php
session_start();
if(!isset($_SESSION['name']) || $_SESSION['role'] != "admin"){
    header("Location: login.php");
    exit();
}

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

// Check if NewsID is provided
if(isset($_GET['id']) && is_numeric($_GET['id'])) {
    $newsId = $_GET['id'];
    
    // First, get news details for confirmation message
    $sqlSelect = "SELECT Title FROM dbo.News WHERE NewsID = ?";
    $paramsSelect = array($newsId);
    $stmtSelect = sqlsrv_query($conn, $sqlSelect, $paramsSelect);
    
    if($stmtSelect === false) {
        die(print_r(sqlsrv_errors(), true));
    }
    
    $news = sqlsrv_fetch_array($stmtSelect, SQLSRV_FETCH_ASSOC);
    
    if($news) {
        // Delete the news
        $sqlDelete = "DELETE FROM dbo.News WHERE NewsID = ?";
        $paramsDelete = array($newsId);
        $stmtDelete = sqlsrv_query($conn, $sqlDelete, $paramsDelete);
        
        if($stmtDelete === false) {
            die(print_r(sqlsrv_errors(), true));
        }
        
        $deletedTitle = $news['Title'];
        $success = true;
    } else {
        $error = "News article not found!";
    }
    
    sqlsrv_free_stmt($stmtSelect);
} else {
    $error = "Invalid news ID!";
}

sqlsrv_close($conn);

// Redirect back to view_news.php with success/error message
if(isset($success) && $success) {
    header("Location: view_news.php?delete=success&title=" . urlencode($deletedTitle));
} else {
    header("Location: view_news.php?delete=error&msg=" . urlencode($error ?? "Failed to delete news"));
}
exit();