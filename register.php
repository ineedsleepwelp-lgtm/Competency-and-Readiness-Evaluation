<?php
include 'db_connect.php';

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $fullname = $_POST['fullname'];
    $email = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    
    // FORCE DEFAULT ROLE: Everyone starts as a Student Teacher
    $role = 'student_teacher'; 

    // Check if email exists
    $check = $conn->prepare("SELECT email FROM users WHERE email = ?");
    $check->bind_param("s", $email);
    $check->execute();
    $check->store_result();

    if ($check->num_rows > 0) {
        $message = "Email already registered!";
    } else {
        $stmt = $conn->prepare("INSERT INTO users (fullname, email, password, role) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $fullname, $email, $password, $role);
        
        if ($stmt->execute()) {
            // Redirect to login page after success
            header("Location: index.php");
            exit();
        } else {
            $message = "Error: " . $conn->error;
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Register</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="container">
        <h2>Create Account</h2>
        <?php if($message) echo "<p class='error'>$message</p>"; ?>
        <form method="POST" action="">
            <input type="text" name="fullname" placeholder="Full Name" required>
            <input type="email" name="email" placeholder="Email" required>
            <input type="password" name="password" placeholder="Password" required>
            
            <button type="submit">Sign Up</button>
        </form>
        <p>Already have an account? <a href="index.php">Login here</a></p>
    </div>
</body>
</html>