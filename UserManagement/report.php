<?php
// report.php - Redirect to internal reporting server

// Permanent redirect (301) or temporary redirect (302)
header("HTTP/1.1 301 Moved Permanently");
header("Location: http://10.147.17.8:8000/workshop2/reports.php");

// Make sure no content is sent before headers
exit();
?>