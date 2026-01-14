<?php
$conn = sqlsrv_connect("localhost", [
    "Database" => "UserManagement",
    "Uid" => "yanadb",
    "PWD" => "yana123"
]);

if (!$conn) {
    die(print_r(sqlsrv_errors(), true));
}

$filename = "C:/Users/User/Desktop/workshop2/backup/UserManagement_" . date("Y-m-d_H-i-s") . ".bak";

$sql = "BACKUP DATABASE UserManagement TO DISK = N'$filename' WITH INIT";

$stmt = sqlsrv_query($conn, $sql);

if ($stmt === false) {
    echo "BACKUP FAIL\n";
    print_r(sqlsrv_errors());
} else {
    echo "BACKUP SUCCESS\n";
    echo "File: $filename\n";
}

sqlsrv_close($conn);
?>
