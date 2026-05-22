<?php
session_start();
include __DIR__ . '/db_connect.php';

// Security
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') { header("Location: index.php"); exit(); }

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$msg = "";
$error = "";

$user_q = $conn->query("SELECT * FROM users WHERE id=$id");
if($user_q->num_rows == 0) { header("Location: admin_manage.php"); exit(); }
$user = $user_q->fetch_assoc();

// Handle Update
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    $username = $_POST['username'];
    $fullname = $_POST['fullname'];
    $age = $_POST['age']; 
    $gender = $_POST['gender'];
    $birthdate = $_POST['birthdate'];
    $course = $_POST['course'];
    $college = $_POST['college'];
    $year_level = $_POST['year_level'];
    $section = $_POST['section'];
    $supervisor_id = $_POST['supervisor_id']; 
    $role = $_POST['role'];
    $status = $_POST['status'];

    if ($id == $_SESSION['user_id'] && $status == 'suspended') {
        $error = "You cannot suspend your own account.";
    } else {
        $new_pass = $_POST['new_password'];
        $sql = "UPDATE users SET username=?, fullname=?, age=?, gender=?, birthdate=?, course=?, college=?, year_level=?, section=?, assigned_supervisor_id=?, role=?, status=?";
        $params = [$username, $fullname, $age, $gender, $birthdate, $course, $college, $year_level, $section, $supervisor_id, $role, $status];
        $types = "ssissssssiss";

        // Logic: If Admin sets a new password, we hash it AND clear the reset_request flag
        if (!empty($new_pass)) {
            $hashed = password_hash($new_pass, PASSWORD_DEFAULT);
            $sql .= ", password=?, reset_request=0"; 
            $types .= "s";
            $params[] = $hashed;
        }

        $sql .= " WHERE id=?";
        $types .= "i";
        $params[] = $id;

        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        
        if($stmt->execute()) {
            $msg = "User record updated successfully!";
            $user = $conn->query("SELECT * FROM users WHERE id=$id")->fetch_assoc();
        } else {
            $error = "Error updating record: " . $conn->error;
        }
    }
}

$supervisors = $conn->query("SELECT id, fullname FROM users WHERE role='supervisor' OR role='admin'");
?>

<!DOCTYPE html>
<html>
<head>
    <title>Edit User (Admin)</title>
    <link rel="stylesheet" href="css/style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body.dark-mode input[readonly] {
            background-color: #444 !important;
            color: #fff !important;
            border: 1px solid #555;
        }
        
        input[readonly] {
            background-color: #e9ecef;
            cursor: not-allowed;
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <h2>Competency and Readiness Evaluation</h2>
        <a href="admin_dashboard.php"><i class="fas fa-chart-line"></i> Dashboard</a>
        <a href="admin_manage.php" class="active"><i class="fas fa-users"></i> Manage Users</a>
        <a href="admin_evaluations.php"><i class="fas fa-clipboard-list"></i> Evaluations</a>
        <a href="logout.php" class="logout-btn">Logout</a>
    </div>

    <div class="main-content">
        
        <div class="top-header">
            <div class="greeting-box">
                <h2>Edit User Record</h2>
                <div class="date-box"><?php echo htmlspecialchars($user['fullname']); ?></div>
            </div>
            <button id="themeToggle" class="theme-toggle"><i class="fas fa-moon"></i> Dark Mode</button>
        </div>
        
        <?php if($user['reset_request'] == 1): ?>
        <div style="background:#e74c3c; color:white; padding:15px; border-radius:5px; margin-bottom:20px;">
            <i class="fas fa-exclamation-circle"></i> <strong>Reset Requested:</strong> This user has requested a password reset. Please enter a new password below to resolve this.
        </div>
        <?php endif; ?>

        <?php if($msg) echo "<p style='color:green; background:var(--input-bg); padding:10px; border:1px solid #2ecc71; border-radius:4px;'>$msg</p>"; ?>
        <?php if($error) echo "<p style='color:red; background:var(--input-bg); padding:10px; border:1px solid #e74c3c; border-radius:4px;'>$error</p>"; ?>

        <div class="card">
            <form method="POST">
                
                <div class="form-group" style="border-left: 5px solid #f1c40f; padding-left: 15px; background: rgba(241, 196, 15, 0.1); padding: 15px;">
                    <h4 style="color:#d35400; margin-top:0;">Admin Controls</h4>
                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px;">
                         <div>
                            <label>System Role</label>
                            <select name="role" class="form-control">
                                <option value="student_teacher" <?php if($user['role']=='student_teacher') echo 'selected'; ?>>Student Teacher</option>
                                <option value="supervisor" <?php if($user['role']=='supervisor') echo 'selected'; ?>>Supervisor</option>
                                <option value="admin" <?php if($user['role']=='admin') echo 'selected'; ?>>Admin</option>
                            </select>
                        </div>
                        <div>
                            <label>Account Status</label>
                            <select name="status" class="form-control">
                                <option value="active" <?php if($user['status']=='active') echo 'selected'; ?>>Active</option>
                                <option value="suspended" <?php if($user['status']=='suspended') echo 'selected'; ?>>Suspended</option>
                            </select>
                        </div>
                        <div>
                            <label>Assign Supervisor</label>
                            <select name="supervisor_id" class="form-control">
                                <option value="0">-- None Assigned --</option>
                                <?php 
                                $supervisors->data_seek(0);
                                while($sup = $supervisors->fetch_assoc()): 
                                ?>
                                    <option value="<?php echo $sup['id']; ?>" <?php if($user['assigned_supervisor_id'] == $sup['id']) echo 'selected'; ?>>
                                        <?php echo htmlspecialchars($sup['fullname']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div style="<?php echo ($user['reset_request'] == 1) ? 'border: 2px solid #e74c3c; padding:5px; border-radius:4px;' : ''; ?>">
                            <label>Reset Password <?php if($user['reset_request'] == 1) echo '<small style="color:red">(Required)</small>'; ?></label>
                            <input type="text" name="new_password" class="form-control" placeholder="Enter new password to reset">
                        </div>
                    </div>
                </div>

                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px; margin-top:20px;">
                    <div>
                        <h4 style="border-bottom:1px solid var(--border-color); padding-bottom:5px;">Personal Information</h4>
                        <div class="form-group"><label>Username</label><input type="text" name="username" class="form-control" value="<?php echo htmlspecialchars($user['username']); ?>"></div>
                        <div class="form-group"><label>Full Name</label><input type="text" name="fullname" class="form-control" value="<?php echo htmlspecialchars($user['fullname']); ?>" required></div>
                        
                        <div class="form-group">
                            <label>Birthdate</label>
                            <input type="date" name="birthdate" id="birthdate" class="form-control" value="<?php echo htmlspecialchars($user['birthdate']); ?>" onchange="calculateAge()">
                        </div>
                        <div class="form-group">
                            <label>Age (Auto-calculated)</label>
                            <input type="number" name="age" id="age" class="form-control" value="<?php echo htmlspecialchars($user['age']); ?>" readonly>
                        </div>

                        <div class="form-group"><label>Gender</label>
                            <select name="gender" class="form-control">
                                <option value="Male" <?php if($user['gender']=='Male') echo 'selected'; ?>>Male</option>
                                <option value="Female" <?php if($user['gender']=='Female') echo 'selected'; ?>>Female</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <h4 style="border-bottom:1px solid var(--border-color); padding-bottom:5px;">Academic Details</h4>
                        <div class="form-group"><label>College</label><input type="text" name="college" class="form-control" value="<?php echo htmlspecialchars($user['college']); ?>"></div>
                        <div class="form-group"><label>Course</label><input type="text" name="course" class="form-control" value="<?php echo htmlspecialchars($user['course']); ?>"></div>
                        <div class="form-group"><label>Year Level</label><input type="text" name="year_level" class="form-control" value="<?php echo htmlspecialchars($user['year_level']); ?>"></div>
                        <div class="form-group"><label>Section</label><input type="text" name="section" class="form-control" value="<?php echo htmlspecialchars($user['section']); ?>"></div>
                    </div>
                </div>

                <div style="margin-top:20px; text-align:right;">
                    <a href="admin_manage.php" class="btn-remove" style="background:#7f8c8d; color:white; padding:10px 20px; text-decoration:none; border-radius:4px; margin-right:10px;">Cancel</a>
                    <button type="submit" class="btn-submit" style="padding:10px 30px; cursor:pointer;">Update User Record</button>
                </div>
            </form>
        </div>
    </div>
    
    <script src="js/script.js?v=<?php echo time(); ?>"></script>
    
    <script>
        function calculateAge() {
            const birthInput = document.getElementById('birthdate').value;
            const ageInput = document.getElementById('age');

            if (birthInput) {
                const birthDate = new Date(birthInput);
                const today = new Date();
                
                let age = today.getFullYear() - birthDate.getFullYear();
                const monthDiff = today.getMonth() - birthDate.getMonth();
                
                if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
                    age--;
                }
                ageInput.value = age;
            }
        }
    </script>
</body>
</html>