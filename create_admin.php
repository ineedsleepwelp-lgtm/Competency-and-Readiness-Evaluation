<?php
// DB Connection
$conn = new mysqli('localhost', 'root', '', 'competency_db');

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Data
$email = 'aljonmontecastrotimbreza@gmail.com';
$pass  = 'admin123';
$role  = 'admin';
$name  = 'Administrator';

// Generate Hash
$hashed_password = password_hash($pass, PASSWORD_DEFAULT);

// Prepare Insert
$stmt = $conn->prepare("INSERT INTO users (username, password, role, full_name) VALUES (?, ?, ?, ?)");
$stmt->bind_param("ssss", $email, $hashed_password, $role, $name);

if ($stmt->execute()) {
    echo "Admin user created successfully with hash: " . $hashed_password;
} else {
    echo "Error: " . $stmt->error;
}

$stmt->close();
$conn->close();
?>