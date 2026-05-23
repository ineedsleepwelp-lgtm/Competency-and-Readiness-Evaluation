<?php
// Force errors to show so we don't get a blank screen if something else happens!
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();
include __DIR__ . '/db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') { header("Location: index.php"); exit(); }

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$msg = ""; $error = "";

$user_q = $conn->query("SELECT * FROM users WHERE id=$id");
if($user_q->num_rows == 0) { header("Location: admin_manage.php"); exit(); }
$user = $user_q->fetch_assoc();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $fullname = trim($_POST['fullname']);
    $email    = trim($_POST['email']);
    $gender   = $_POST['gender'];
    
    // Fix: Properly handle empty dates to prevent database crashes
    $birthdate = !empty($_POST['birthdate']) ? $_POST['birthdate'] : NULL;
    
    $college  = trim($_POST['college']);
    $course   = trim($_POST['course']);
    $year_level = trim($_POST['year_level']);
    $section  = trim($_POST['section']);
    $address  = trim($_POST['address']);
    $partner_school = trim($_POST['partner_school']);
    $role     = $_POST['role'];
    $status   = $_POST['status'];
    $supervisor_id = intval($_POST['supervisor_id']);

    $age = 0;
    if (!empty($birthdate)) {
        try {
            $dob = new DateTime($birthdate);
            $now = new DateTime('today');
            $age = $dob->diff($now)->y;
        } catch(Exception $e) { 
            $age = 0; 
        }
    }

    $new_pass = $_POST['new_password'];
    $sql = "UPDATE users SET username=?, fullname=?, email=?, age=?, gender=?, birthdate=?, college=?, course=?, year_level=?, section=?, address=?, partner_school=?, assigned_supervisor_id=?, role=?, status=?";
    $types = "ssssssssssssiss";
    $params = [$username, $fullname, $email, $age, $gender, $birthdate, $college, $course, $year_level, $section, $address, $partner_school, $supervisor_id, $role, $status];

    if (!empty($new_pass)) {
        $hashed = password_hash($new_pass, PASSWORD_DEFAULT);
        $sql .= ", password=?, reset_request=0"; 
        $types .= "s"; $params[] = $hashed;
    }
    $sql .= " WHERE id=?"; $types .= "i"; $params[] = $id;

    try {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        if($stmt->execute()) {
            $msg = "Administrator record updated successfully!";
            $user = $conn->query("SELECT * FROM users WHERE id=$id")->fetch_assoc();
        } else { 
            $error = "Error: " . $conn->error; 
        }
    } catch (mysqli_sql_exception $e) {
        // This catches DB errors and prints them in a red box instead of a 500 error!
        $error = "Database Error: " . $e->getMessage();
    }
}

$supervisors = $conn->query("SELECT id, fullname FROM users WHERE role='supervisor' OR role='admin'");
?>

<!DOCTYPE html>
<html>
<head>
    <title>Edit User (Administrator)</title>
    <link rel="stylesheet" href="css/style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="sidebar-collapsed">
    
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <div class="top-header">
            <div class="greeting-box">
                <h2>Edit User Record</h2>
                <div class="date-box"><?php echo htmlspecialchars($user['fullname'] ?? 'User'); ?></div>
            </div>
            <button id="themeToggle" class="theme-toggle"><i class="fas fa-moon"></i> Dark Mode</button>
        </div>

        <?php if($msg) echo "<p style='color:green; background:var(--input-bg); padding:10px; border:1px solid #2ecc71; border-radius:4px;'>$msg</p>"; ?>
        <?php if($error) echo "<p style='color:red; background:var(--input-bg); padding:10px; border:1px solid #e74c3c; border-radius:4px;'>$error</p>"; ?>

        <div class="card">
            <form method="POST">
                <div class="form-group" style="border-left: 5px solid #f1c40f; padding: 15px; background: rgba(241, 196, 15, 0.1);">
                    <h4 style="color:#d35400; margin-top:0;">Administrator Controls</h4>
                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px;">
                        <div><label>System Role</label>
                            <select name="role" class="form-control">
                                <option value="student_teacher" <?php if(($user['role']??'')=='student_teacher') echo 'selected'; ?>>Student Teacher</option>
                                <option value="supervisor" <?php if(($user['role']??'')=='supervisor') echo 'selected'; ?>>Supervisor</option>
                                <option value="admin" <?php if(($user['role']??'')=='admin') echo 'selected'; ?>>Administrator</option>
                            </select>
                        </div>
                        <div><label>Account Status</label>
                            <select name="status" class="form-control">
                                <option value="active" <?php if(($user['status']??'')=='active') echo 'selected'; ?>>Active</option>
                                <option value="suspended" <?php if(($user['status']??'')=='suspended') echo 'selected'; ?>>Suspended</option>
                            </select>
                        </div>
                        <div><label>Assign Supervisor</label>
                            <select name="supervisor_id" class="form-control">
                                <option value="0">-- None Assigned --</option>
                                <?php 
                                if($supervisors) {
                                    $supervisors->data_seek(0);
                                    while($sup = $supervisors->fetch_assoc()): 
                                ?>
                                    <option value="<?php echo $sup['id']; ?>" <?php if(($user['assigned_supervisor_id']??0) == $sup['id']) echo 'selected'; ?>>
                                        <?php echo htmlspecialchars($sup['fullname']??''); ?>
                                    </option>
                                <?php endwhile; } ?>
                            </select>
                        </div>
                        <div><label>Reset Password</label><input type="text" name="new_password" class="form-control" placeholder="Enter new password"></div>
                    </div>
                </div>

                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px; margin-top:20px;">
                    <div>
                        <h4 style="border-bottom:1px solid var(--border-color); padding-bottom:5px;">Personal Information</h4>
                        <div class="form-group"><label>Username</label><input type="text" name="username" class="form-control" value="<?php echo htmlspecialchars($user['username'] ?? ''); ?>"></div>
                        <div class="form-group"><label>Full Name</label><input type="text" name="fullname" class="form-control" value="<?php echo htmlspecialchars($user['fullname'] ?? ''); ?>" required></div>
                        <div class="form-group"><label>Email</label><input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>"></div>
                        <div class="form-group"><label>Birthdate</label><input type="date" name="birthdate" class="form-control" value="<?php echo htmlspecialchars($user['birthdate'] ?? ''); ?>"></div>
                        <div class="form-group"><label>Gender</label>
                            <select name="gender" class="form-control">
                                <option value="" disabled>Select...</option>
                                <option value="Male" <?php if(($user['gender']??'')=='Male') echo 'selected'; ?>>Male</option>
                                <option value="Female" <?php if(($user['gender']??'')=='Female') echo 'selected'; ?>>Female</option>
                            </select>
                        </div>
                        <div class="form-group"><label>Address</label><input type="text" name="address" class="form-control" value="<?php echo htmlspecialchars($user['address'] ?? ''); ?>"></div>
                    </div>
                    <div>
                        <h4 style="border-bottom:1px solid var(--border-color); padding-bottom:5px;">Academic Details</h4>
                        <div class="form-group"><label>College</label><input type="text" name="college" class="form-control" value="<?php echo htmlspecialchars($user['college'] ?? ''); ?>"></div>
                        <div class="form-group"><label>Course</label><input type="text" name="course" class="form-control" value="<?php echo htmlspecialchars($user['course'] ?? ''); ?>"></div>
                        <div class="form-group"><label>Year Level</label><input type="text" name="year_level" class="form-control" value="<?php echo htmlspecialchars($user['year_level'] ?? ''); ?>"></div>
                        <div class="form-group"><label>Section</label><input type="text" name="section" class="form-control" value="<?php echo htmlspecialchars($user['section'] ?? ''); ?>"></div>
                        <div class="form-group"><label>Partner School</label><input type="text" name="partner_school" class="form-control" value="<?php echo htmlspecialchars($user['partner_school'] ?? ''); ?>"></div>
                    </div>
                </div>

                <div style="margin-top:20px; text-align:right;">
                    <a href="admin_manage.php" class="btn-remove" style="background:#7f8c8d; color:white; padding:10px 20px; text-decoration:none; border-radius:4px; margin-right:10px;">Cancel</a>
                    <button type="submit" class="btn-submit" style="padding:10px 30px; cursor:pointer;">Update User Record</button>
                </div>
            </form>
        </div>
    </div>
    <script src="js/script.js"></script>
</body>
</html>
