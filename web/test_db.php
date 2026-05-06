<?php
// Test SQL Server connection
echo "Testing SQL Server connection...\n<br>";

// Check if sqlsrv extension is loaded
if (extension_loaded('sqlsrv')) {
    echo "✓ sqlsrv extension is loaded\n<br>";
} else {
    echo "✗ sqlsrv extension is NOT loaded\n<br>";
    echo "You need to enable sqlsrv extension in php.ini\n<br>";
}

// Check if pdo_sqlsrv extension is loaded
if (extension_loaded('pdo_sqlsrv')) {
    echo "✓ pdo_sqlsrv extension is loaded\n<br>";
} else {
    echo "✗ pdo_sqlsrv extension is NOT loaded\n<br>";
}

// Try to connect
$serverName = "localhost";
$connectionInfo = array("Database"=>"sdsweb-d10", "UID"=>"SDSUSER_D10", "PWD"=>"Admin123!");
$conn = sqlsrv_connect($serverName, $connectionInfo);

if ($conn) {
    echo "✓ Connection established\n<br>";
    sqlsrv_close($conn);
} else {
    echo "✗ Connection failed\n<br>";
    echo "Error: " . print_r(sqlsrv_errors(), true);
}
