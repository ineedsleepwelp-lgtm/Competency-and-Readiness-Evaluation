<?php
// Live Server Credentials for InfinityFree
$servername = "sql211.infinityfree.com";
$username   = "if0_41987349";
$password   = "RSFSRussr1991"; 
$dbname     = "if0_41987349_competency_db";

// Establish Connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check Connection
if ($conn->connect_error) {
    // We keep the die() here, but in a clean way
    die("Database Connection Error: " . $conn->connect_error);
}

// Ensure the connection uses utf8mb4 to prevent character encoding issues
$conn->set_charset("utf8mb4");
?>