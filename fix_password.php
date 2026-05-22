<?php
$conn = new mysqli('localhost', 'root', '', 'competency_db');

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// The account to fix
$email = 'aljonmontecastrotimbreza@gmail.com';
$new_pass = 'admin123';

// Create a proper hash
$hashed_password = password_hash($new_pass, PASSWORD_DEFAULT);

// Update the database
$sql = "UPDATE users SET password = ? WHERE email = ? OR username = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("sss", $hashed_password, $email, $email);

if ($stmt->execute()) {
    echo "<h1>Success!</h1>";
    echo "<p>Password for <b>$email</b> has been reset.</p>";
    echo "<p>The new hash is: $hashed_password</p>";
    echo "<p><a href='index.php'>Go back to Login</a></p>";
} else {
    echo "Error updating record: " . $conn->error;
}

$conn->close();
?>