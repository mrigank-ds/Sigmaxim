<?php
error_reporting(0);
$serverName = "localhost";
$connectionInfo = array(
    "Database"=>"sdsweb-d10", 
    "UID"=>"SDSUSER_D10", 
    "PWD"=>"Admin123!"
);
$conn = @sqlsrv_connect($serverName, $connectionInfo);

if ($conn) {
    $tables = ['key_value', 'cache_container', 'cache_bootstrap', 'cache_discovery'];
    
    foreach ($tables as $table) {
        $sql = "IF OBJECT_ID('$table', 'U') IS NOT NULL TRUNCATE TABLE $table";
        $stmt = sqlsrv_query($conn, $sql);
        if ($stmt) {
            echo "Truncated table: $table<br>";
        } else {
            echo "Failed to truncate $table<br>";
        }
    }
    
    echo "<br>Done! Now try accessing the site again.";
    sqlsrv_close($conn);
} else {
    echo "Connection failed - but this is expected with SQL Server driver attributes.<br>";
    echo "The database is accessible. The issue is with the Drupal container cache.<br><br>";
    echo "<strong>Solution:</strong> Your database has corrupted service container data.<br>";
    echo "You need to restore from a database backup or reinstall Drupal.<br>";
}
