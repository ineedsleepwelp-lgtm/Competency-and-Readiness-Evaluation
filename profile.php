<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION['user_id'])) { header("Location: index.php"); exit(); }

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role']; 
$msg = "";

// 1. Fetch user data with Supervisor Join
$sql_user = "SELECT u.*, s.fullname as supervisor_name FROM users u LEFT JOIN users s ON u.assigned_supervisor_id = s.id WHERE u.id = $user_id";
$user = $conn->query($sql_user)->fetch_assoc();
$initials = strtoupper(substr($user['fullname'], 0, 1));

// 2. Handle Profile Updates
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $fullname = $_POST['fullname'];
    $age = !empty($_POST['age']) ? $_POST['age'] : NULL;
    $gender = $_POST['gender'];
    $birthdate = !empty($_POST['birthdate']) ? $_POST['birthdate'] : NULL;
    
    $profile_pic_path = $user['profile_pic']; 
    
    // Handle Photo Removal
    if (isset($_POST['remove_photo']) && $_POST['remove_photo'] == '1') {
        if (!empty($user['profile_pic']) && file_exists($user['profile_pic'])) {
            unlink($user['profile_pic']); 
        }
        $profile_pic_path = NULL; 
    } 
    // Handle Camera Snapshot (Base64)
    elseif (!empty($_POST['captured_image_data'])) {
        $img = $_POST['captured_image_data'];
        $img = str_replace('data:image/png;base64,', '', $img);
        $img = str_replace(' ', '+', $img);
        $data = base64_decode($img);
        $target_dir = "uploads/profiles/";
        if (!is_dir($target_dir)) { mkdir($target_dir, 0777, true); }
        $target_file = $target_dir . "user_" . $user_id . "_" . time() . ".png";
        if (file_put_contents($target_file, $data)) {
            if (!empty($user['profile_pic']) && file_exists($user['profile_pic'])) { unlink($user['profile_pic']); }
            $profile_pic_path = $target_file;
        }
    }
    // Handle Standard File Upload
    elseif (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] == 0) {
        $target_dir = "uploads/profiles/";
        if (!is_dir($target_dir)) { mkdir($target_dir, 0777, true); }
        $file_extension = pathinfo($_FILES["profile_pic"]["name"], PATHINFO_EXTENSION);
        $target_file = $target_dir . "user_" . $user_id . "_" . time() . "." . $file_extension;
        if (move_uploaded_file($_FILES["profile_pic"]["tmp_name"], $target_file)) {
            if (!empty($user['profile_pic']) && file_exists($user['profile_pic'])) { unlink($user['profile_pic']); }
            $profile_pic_path = $target_file;
        }
    }

    // 3. Update Database based on Role
    if ($role == 'student_teacher') {
        $course = $_POST['course'];
        $college = $_POST['college'];
        $year_level = $_POST['year_level'];
        $section = $_POST['section'];
        $sql = "UPDATE users SET fullname=?, age=?, gender=?, birthdate=?, course=?, college=?, year_level=?, section=?, profile_pic=? WHERE id=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sisssssssi", $fullname, $age, $gender, $birthdate, $course, $college, $year_level, $section, $profile_pic_path, $user_id);
    } else {
        $sql = "UPDATE users SET fullname=?, age=?, gender=?, birthdate=?, profile_pic=? WHERE id=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sisssi", $fullname, $age, $gender, $birthdate, $profile_pic_path, $user_id);
    }
    
    if ($stmt->execute()) {
        $msg = "Profile updated successfully!";
        $_SESSION['fullname'] = $fullname;
        $user = $conn->query($sql_user)->fetch_assoc(); // Refresh
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>My Profile</title>
    <link rel="stylesheet" href="css/style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        #cameraModal {
            display: none; position: fixed; z-index: 9999; left: 0; top: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.9); justify-content: center; align-items: center; flex-direction: column;
        }
        #videoPreview { width: 90%; max-width: 450px; border-radius: 12px; border: 4px solid #fff; }
        .cam-btns { margin-top: 20px; display: flex; gap: 15px; }
        .btn-cap { background: #2ecc71; color: white; border: none; padding: 12px 25px; border-radius: 50px; cursor: pointer; font-weight: bold; }
        .btn-can { background: #e74c3c; color: white; border: none; padding: 12px 25px; border-radius: 50px; cursor: pointer; font-weight: bold; }
    </style>
</head>
<body>

    <div id="cameraModal">
        <video id="videoPreview" autoplay playsinline></video>
        <div class="cam-btns">
            <button type="button" class="btn-cap" onclick="takeSnapshot()"><i class="fas fa-camera"></i> Capture</button>
            <button type="button" class="btn-can" onclick="stopCamera()">Cancel</button>
        </div>
        <canvas id="captureCanvas" style="display:none;"></canvas>
    </div>

<?php include 'sidebar.php'; ?>

    <div class="main-content">
        <div class="top-header"><h2>Profile Settings</h2></div>

        <?php if($msg) echo "<div class='alert' style='background:#d4edda; color:#155724; padding:15px; border-radius:8px; margin-bottom:20px;'>$msg</div>"; ?>

        <div class="profile-container">
            <div class="identity-card">
                <div class="avatar-circle" id="avatarPreview" 
                     style="<?php if(!empty($user['profile_pic'])) echo "background-image: url('".$user['profile_pic']."'); background-size: cover;"; ?>">
                    <?php if(empty($user['profile_pic'])) echo $initials; ?>
                </div>
                
                <div style="margin-top: 15px;">
                    <button type="button" onclick="openCamera()" style="border:none; background:none; color:#3498db; cursor:pointer;">
                        <i class="fas fa-video"></i> Camera
                    </button> | 
                    <button type="button" onclick="document.getElementById('profilePicInput').click();" style="border:none; background:none; color:#3498db; cursor:pointer;">
                        <i class="fas fa-upload"></i> Upload
                    </button>
                </div>

                <p id="removePhotoBtn" onclick="removePhoto()" style="color:#e74c3c; cursor:pointer; font-weight:bold; font-size:12px; <?php if(empty($user['profile_pic'])) echo 'display: none;'; ?>">
                    <i class="fas fa-trash"></i> Remove Photo
                </p>

                <h3><?php echo htmlspecialchars($user['fullname']); ?></h3>
                <span class="user-role-badge"><?php echo ucfirst(str_replace('_', ' ', $role)); ?></span>
                
                <div style="text-align: left; font-size: 14px; margin-top: 20px;">
                    <p><strong>User ID:</strong> <?php echo htmlspecialchars($user['username']); ?></p>
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($user['email']); ?></p>
                    <?php if($role == 'student_teacher'): ?>
                        <p><strong>Supervisor:</strong> <?php echo htmlspecialchars($user['supervisor_name'] ?? 'Not Assigned'); ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="form-card">
                <form method="POST" enctype="multipart/form-data">
                    <input type="file" name="profile_pic" id="profilePicInput" accept="image/*" style="display: none;" onchange="previewImage(event)">
                    <input type="hidden" name="remove_photo" id="removePhotoFlag" value="0">
                    <input type="hidden" name="captured_image_data" id="capturedImageData">

                    <div class="form-section-title">Personal Information</div>
                    <div class="form-grid">
                        <div class="full-width">
                            <label>Full Name</label>
                            <input type="text" name="fullname" class="modern-input" value="<?php echo htmlspecialchars($user['fullname']); ?>" required>
                        </div>
                        <div>
                            <label>Birthdate</label>
                            <input type="date" id="birthdate" name="birthdate" class="modern-input" value="<?php echo htmlspecialchars($user['birthdate']); ?>">
                        </div>
                        <div>
                            <label>Age</label>
                            <input type="number" id="age" name="age" class="modern-input" value="<?php echo htmlspecialchars($user['age']); ?>">
                        </div>
                        <div>
                            <label>Gender</label>
                            <select name="gender" class="modern-input">
                                <option value="Male" <?php if($user['gender']=='Male') echo 'selected'; ?>>Male</option>
                                <option value="Female" <?php if($user['gender']=='Female') echo 'selected'; ?>>Female</option>
                            </select>
                        </div>
                    </div>

                    <?php if($role == 'student_teacher'): ?>
                        <div class="form-section-title">Academic Details</div>
                        <div class="form-grid">
                            <div class="full-width"><label>College</label><input type="text" name="college" class="modern-input" value="<?php echo htmlspecialchars($user['college']); ?>"></div>
                            <div class="full-width"><label>Course</label><input type="text" name="course" class="modern-input" value="<?php echo htmlspecialchars($user['course']); ?>"></div>
                            <div><label>Year Level</label><input type="text" name="year_level" class="modern-input" value="<?php echo htmlspecialchars($user['year_level']); ?>"></div>
                            <div><label>Section</label><input type="text" name="section" class="modern-input" value="<?php echo htmlspecialchars($user['section']); ?>"></div>
                        </div>
                    <?php endif; ?>

                    <div style="margin-top: 30px; text-align: right;"><button type="submit" class="btn-submit">Update Profile</button></div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // --- Age Auto-Calculation ---
        document.getElementById('birthdate').addEventListener('change', function() {
            const birthDate = new Date(this.value);
            const today = new Date();
            let age = today.getFullYear() - birthDate.getFullYear();
            const m = today.getMonth() - birthDate.getMonth();
            if (m < 0 || (m === 0 && today.getDate() < birthDate.getDate())) { age--; }
            document.getElementById('age').value = age >= 0 ? age : 0;
        });

        // --- Camera Logic ---
        let stream = null;
        async function openCamera() {
            const modal = document.getElementById('cameraModal');
            modal.style.display = 'flex';
            try {
                stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: "user" } });
                document.getElementById('videoPreview').srcObject = stream;
            } catch (err) { alert("Camera access denied."); stopCamera(); }
        }
        function stopCamera() {
            if (stream) stream.getTracks().forEach(t => t.stop());
            document.getElementById('cameraModal').style.display = 'none';
        }
        function takeSnapshot() {
            const canvas = document.getElementById('captureCanvas');
            const video = document.getElementById('videoPreview');
            canvas.width = video.videoWidth; canvas.height = video.videoHeight;
            canvas.getContext('2d').drawImage(video, 0, 0);
            const dataUrl = canvas.toDataURL('image/png');
            document.getElementById('capturedImageData').value = dataUrl;
            document.getElementById('avatarPreview').style.backgroundImage = `url(${dataUrl})`;
            document.getElementById('avatarPreview').innerHTML = '';
            document.getElementById('removePhotoBtn').style.display = 'block';
            stopCamera();
        }
        function previewImage(e) {
            const reader = new FileReader();
            reader.onload = () => {
                document.getElementById('avatarPreview').style.backgroundImage = `url(${reader.result})`;
                document.getElementById('avatarPreview').innerHTML = '';
                document.getElementById('removePhotoBtn').style.display = 'block';
            };
            reader.readAsDataURL(e.target.files[0]);
        }
        function removePhoto() {
            document.getElementById('avatarPreview').style.backgroundImage = 'none';
            document.getElementById('avatarPreview').innerHTML = '<?php echo $initials; ?>';
            document.getElementById('removePhotoFlag').value = '1';
            document.getElementById('removePhotoBtn').style.display = 'none';
        }
    </script>
</body>
</html>