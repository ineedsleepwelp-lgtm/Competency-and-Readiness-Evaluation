<?php
include 'db_connect.php';

// Force the database to add the missing file_path column
$sql = "ALTER TABLE evaluations ADD COLUMN file_path VARCHAR(255) DEFAULT NULL";

if ($conn->query($sql) === TRUE) {
    echo "<h1 style='color:green;'>SUCCESS! The file_path column was added.</h1>";
} else {
    echo "<h1 style='color:red;'>FAILED: " . $conn->error . "</h1>";
}
?>