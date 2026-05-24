<?php
// Determine if we are on Localhost or Live Server
if ($_SERVER['REMOTE_ADDR'] == '127.0.0.1' || $_SERVER['REMOTE_ADDR'] == '::1') {
    // LOCALHOST SETTINGS (XAMPP)
    $servername = "localhost";
    $username = "root";
    $password = "";
    $dbname = "competency_db"; 
} else {
    // LIVE SETTINGS (InfinityFree)
    $servername = "sql106.infinityfree.com";
    $username = "if0_41484863";
    $password = "YOUR_INFINITY_PASSWORD"; // Put your actual InfinityFree password here
    $dbname = "if0_41484863_competency_db";
}

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// 1. We create the hash first
$new_pass = "admin123"; 
$hashed_password = password_hash($new_pass, PASSWORD_BCRYPT);

// 2. The SQL Query (Check that your table is actually named 'users')
// Note: I added quotes around the variables to prevent syntax errors
$sql = "UPDATE users SET password='$hashed_password' WHERE username='10396886'";

if ($conn->query($sql) === TRUE) {
    echo "<h3>Success!</h3>";
    echo "The password for <b>aljonmontecastrotimbreza</b> is now: <b>admin123</b>";
    echo "<br><br><b>Important:</b> Delete this reset.php file now.";
} else {
    echo "Error updating record: " . $conn->error;
}

$conn->close();
?>