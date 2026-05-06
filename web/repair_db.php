<?php
// This script copies the working service container from the new database to the old one

$serverName = "localhost";
$newDB = "sdsweb-d10-new";
$oldDB = "sdsweb-d10";
$username = "SDSUSER_D10";
$password = "Admin123!";

// Connect to new database
$connNew = sqlsrv_connect($serverName, array("Database"=>$newDB, "UID"=>$username, "PWD"=>$password));
// Connect to old database  
$connOld = sqlsrv_connect($serverName, array("Database"=>$oldDB, "UID"=>$username, "PWD"=>$password));

if ($connNew && $connOld) {
    // Tables to copy
    $tables = ['key_value', 'cache_container', 'cache_bootstrap', 'cache_discovery', 'router'];
    
    foreach ($tables as $table) {
        // Truncate old table
        sqlsrv_query($connOld, "TRUNCATE TABLE $table");
        
        // Copy data from new to old
        $sql = "INSERT INTO [$oldDB].dbo.$table SELECT * FROM [$newDB].dbo.$table";
        if (sqlsrv_query($connOld, $sql)) {
            echo "Copied $table<br>";
        } else {
            echo "Failed to copy $table<br>";
        }
    }
    
    echo "<br>Done! Now change settings.php back to 'sdsweb-d10' and test.";
} else {
    echo "Connection failed";
}
