<?php
session_start();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Session Debug</title>
</head>
<body>
    <h1>Session Variables</h1>
    <pre><?php print_r($_SESSION); ?></pre>
    
    <h2>Test Authentication</h2>
    <?php
    $is_api_authenticated = false;
    
    echo "<p>Checking admin_api_verified: ";
    echo isset($_SESSION['admin_api_verified']) ? "SET = " . ($_SESSION['admin_api_verified'] ? 'TRUE' : 'FALSE') : "NOT SET";
    echo "</p>";
    
    echo "<p>Checking AdminID: ";
    echo isset($_SESSION['AdminID']) ? "SET = " . $_SESSION['AdminID'] : "NOT SET";
    echo "</p>";
    
    echo "<p>Checking FullName: ";
    echo isset($_SESSION['FullName']) ? "SET = " . $_SESSION['FullName'] : "NOT SET";
    echo "</p>";
    
    echo "<p>Checking user_id: ";
    echo isset($_SESSION['user_id']) ? "SET = " . $_SESSION['user_id'] : "NOT SET";
    echo "</p>";
    
    // Check multiple possible API authentication indicators
    if (isset($_SESSION['admin_api_verified']) && $_SESSION['admin_api_verified'] === true) {
        $is_api_authenticated = true;
        echo "<p style='color: green;'>✓ API authenticated via admin_api_verified</p>";
    } elseif (isset($_SESSION['AdminID']) || isset($_SESSION['FullName'])) {
        $is_api_authenticated = true;
        echo "<p style='color: green;'>✓ API authenticated via AdminID/FullName</p>";
    } else {
        echo "<p style='color: red;'>✗ NOT API authenticated</p>";
    }
    
    echo "<p>Final is_api_authenticated: " . ($is_api_authenticated ? 'TRUE' : 'FALSE') . "</p>";
    ?>
    
    <h2>Actions</h2>
    <p><a href="admin_dashboard.php">Try Admin Dashboard</a></p>
    <p><a href="debug_session.php?logout=1">Clear Session</a></p>
    
    <?php
    if (isset($_GET['logout'])) {
        session_destroy();
        echo "<p>Session cleared. <a href='debug_session.php'>Refresh</a></p>";
    }
    ?>
</body>
</html>