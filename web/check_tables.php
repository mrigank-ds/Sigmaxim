<?php
$serverName = "localhost";
$connectionInfo = array(
    "Database"=>"sdsweb-d10", 
    "UID"=>"SDSUSER_D10", 
    "PWD"=>"Admin123!",
    "ReturnDatesAsStrings" => true
);
$conn = sqlsrv_connect($serverName, $connectionInfo);

if ($conn) {
    $sql = "SELECT COUNT(*) as table_count FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_TYPE = 'BASE TABLE'";
    $stmt = sqlsrv_query($conn, $sql);
    
    if ($stmt) {
        $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        echo "Number of tables in database: " . $row['table_count'] . "<br>";
        
        // Check for key Drupal tables
        $drupal_tables = ['router', 'config', 'key_value', 'cache_bootstrap'];
        foreach ($drupal_tables as $table) {
            $sql = "SELECT COUNT(*) as exists_flag FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = '$table'";
            $stmt2 = sqlsrv_query($conn, $sql);
            $row2 = sqlsrv_fetch_array($stmt2, SQLSRV_FETCH_ASSOC);
            echo "Table '$table': " . ($row2['exists_flag'] ? "EXISTS" : "MISSING") . "<br>";
        }
    }
    sqlsrv_close($conn);
} else {
    echo "Connection failed<br>";
    print_r(sqlsrv_errors());
}
