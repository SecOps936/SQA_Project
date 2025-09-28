<?php
// Connect to database using environment variables
 $host = getenv('mysql');
 $dbname = getenv('school_quakity');
 $user = getenv('');
 $pass = getenv('');

// Create connection
 $conn = new mysqli($host, $user, $pass, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
echo "Connected successfully";

?>
