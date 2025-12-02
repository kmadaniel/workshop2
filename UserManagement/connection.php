<?php
$serverName = "localhost";
$connectionOptions = array(
    "Database" => "UserManagement",
    "Uid" => "yanadb",
    "PWD" => "yana123",
    "CharacterSet" => "UTF-8"
);
$conn = sqlsrv_connect($serverName, $connectionOptions);

if($conn){
    echo "Connection successful!";
} else {
    die(print_r(sqlsrv_errors(), true));
}
?>
