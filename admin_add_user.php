<?php
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);
session_start();
include 'db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') { header("Location: index.php"); exit(); }

$msg = ""; $error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $fullname = trim($_POST['fullname']);
    $email    = trim($_POST['email']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role     = $_POST['role'];
    $status   = $_POST['status'];
    
    // Academic & OJT Fields
    $college        = trim($_POST['college']);
    $course         = trim($_POST['course']);
    $year_level     = trim($_POST['year_level']);
    $section        = trim($_POST['section']);
    $birthdate      = $_POST['birthdate'];
    $gender         = $_POST['gender'];
    $address        = trim($_POST['address']);
    $partner_school = trim($_POST['partner_school']);

    $age = 0;
    if (!empty($birthdate)) {
        $dob = new DateTime($birthdate);
        $now = new DateTime('today');
        $age = $dob->diff($now)->y;
    }

    $stmt = $conn->prepare("INSERT INTO users (username, fullname, email, password, role, status, college, course, year_level, section, birthdate, age, gender, address, partner_school) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssssssssssisss", $username, $fullname, $email, $password, $role, $status, $college, $course, $year_level, $section, $birthdate, $age, $gender, $address, $partner_school);
    
    if ($stmt->execute()) { $msg = "User added successfully!"; } 
    else { $error = "Error: " . $conn->error; }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Add User</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        .form-group { display: flex; flex-direction: column; margin-bottom: 15px; }
        .full-width { grid-column: span 2; }
    </style>
</head>
<body>
    <div class="sidebar">
        <h2>Competency Evaluation</h2>
        <a href="admin_dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
        <a href="admin_manage.php" class="active"><i class="fas fa-users"></i> Manage Users</a>
    </div>

    <div class="main-content">
        <div class="card">
            <h2>Add New User</h2>
            <?php if($msg) echo "<div style='color:green;'>$msg</div>"; ?>
            <?php if($error) echo "<div style='color:red;'>$error</div>"; ?>
            
            <form method="POST">
                <div class="form-grid">
                    <div class="form-group"><label>Username</label><input type="text" name="username" class="form-control" required></div>
                    <div class="form-group"><label>Password</label><input type="text" name="password" class="form-control" required></div>
                    
                    <div class="form-group"><label>Full Name</label><input type="text" name="fullname" class="form-control" required></div>
                    <div class="form-group"><label>Email</label><input type="email" name="email" class="form-control" required></div>
                    
                    <div class="form-group"><label>College</label><input type="text" name="college" class="form-control" required></div>
                    <div class="form-group"><label>Course</label><input type="text" name="course" class="form-control" required></div>
                    
                    <div class="form-group"><label>Year Level</label><input type="text" name="year_level" class="form-control" required></div>
                    <div class="form-group"><label>Section</label><input type="text" name="section" class="form-control" required></div>
                    
                    <div class="form-group"><label>Birthdate</label><input type="date" name="birthdate" class="form-control" required></div>
                    <div class="form-group"><label>Gender</label>
                        <select name="gender" class="form-control"><option>Male</option><option>Female</option></select>
                    </div>
                    
                    <div class="form-group full-width"><label>Address</label><input type="text" name="address" class="form-control" required></div>
                    <div class="form-group full-width"><label>Partner School</label><input type="text" name="partner_school" class="form-control" required></div>
                    
                    <div class="form-group"><label>Role</label>
                        <select name="role" class="form-control">
                            <option value="student_teacher">Student Teacher</option>
                            <option value="supervisor">Supervisor</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    <div class="form-group"><label>Status</label>
                        <select name="status" class="form-control"><option value="active">Active</option><option value="suspended">Suspended</option></select>
                    </div>
                </div>
                <button type="submit" class="btn-submit">Add User</button>
            </form>
        </div>
    </div>
</body>
</html>