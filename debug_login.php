<?php
// debug_login.php
$conn = new mysqli('localhost', 'root', '', 'competency_db');

if ($conn->connect_error) {
    die("Connection Failed: " . $conn->connect_error);
}

$email = 'aljonmontecastrotimbreza@gmail.com';
$password_input = 'admin123';

echo "<h2>Login Debugger</h2>";
echo "Testing email: <strong>$email</strong><br>";

// 1. Check if user exists
$stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    echo "✅ User found in database.<br>";
    echo "Stored Password Hash: " . $row['password'] . "<br>";
    echo "Stored Role: " . $row['role'] . "<br><br>";

    // 2. Test Password Verify
    if (password_verify($password_input, $row['password'])) {
        echo "✅ password_verify() PASSED.<br>";
    } else {
        echo "❌ password_verify() FAILED.<br>";
        echo "Trying plain text comparison... ";
        if ($row['password'] == $password_input) {
             echo "✅ Plain text match! (Your system does not use hashing).";
        } else {
             echo "❌ Plain text also failed.";
        }
    }
} else {
    echo "❌ User NOT found. Check the 'username' column in your database.";
}
?>