<?php
// db_connect.php

$servername = "localhost";  // Usually localhost
$username = "root";
$password = "";
$dbname = "al";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Optional: set charset for UTF-8 support
$conn->set_charset("utf8mb4");

// Now $conn can be used for queries
?>
