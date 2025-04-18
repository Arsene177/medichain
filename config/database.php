<?php
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'medichain');

// Attempt to connect to MySQL database
$conn = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD);

// Check connection
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Create database if it doesn't exist
$sql = "CREATE DATABASE IF NOT EXISTS " . DB_NAME;
if (mysqli_query($conn, $sql)) {
    // Select the database
    mysqli_select_db($conn, DB_NAME);
} else {
    die("Error creating database: " . mysqli_error($conn));
}

// Check if tables exist, if not, import the database.sql file
$result = mysqli_query($conn, "SHOW TABLES");
if (mysqli_num_rows($result) == 0) {
    // Read and execute the database.sql file
    $sql = file_get_contents(__DIR__ . '/../database.sql');
    if (mysqli_multi_query($conn, $sql)) {
        do {
            // Store or discard the result
            if ($result = mysqli_store_result($conn)) {
                mysqli_free_result($result);
            }
        } while (mysqli_next_result($conn));
    } else {
        die("Error importing database structure: " . mysqli_error($conn));
    }
}
?> 