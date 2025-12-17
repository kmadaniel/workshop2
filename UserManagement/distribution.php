<?php
// report.php - Redirect to internal reporting server

// Permanent redirect (301) or temporary redirect (302)
header("HTTP/1.1 301 Moved Permanently");
header("Location: http://10.147.17.154:8000/distribution_module/distribution_main.php");

// Make sure no content is sent before headers
exit();
?>