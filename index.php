<?php
session_start();

include __DIR__ . '/db_connect.php'; 

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] == 'admin') header("Location: admin_dashboard.php");
    elseif ($_SESSION['role'] == 'supervisor') header("Location: supervisor_dashboard.php");
    else header("Location: dashboard.php");
    exit();
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $identifier = $_POST['identifier']; 
    $password = $_POST['password'];

    // 1. Check BOTH Email AND Username columns
    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ? OR username = ?");
    $stmt->bind_param("ss", $identifier, $identifier);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        
        // 2. Verify Password
        if (password_verify($password, $user['password'])) {
            
            if($user['status'] == 'suspended') {
                $error = "Your account has been suspended. Please contact the administrator.";
            } else {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['fullname'] = $user['fullname'];
                
                // Redirect based on Role
                if ($user['role'] == 'admin') {
                    header("Location: admin_dashboard.php");
                } elseif ($user['role'] == 'supervisor') {
                    header("Location: supervisor_dashboard.php");
                } else {
                    header("Location: dashboard.php");
                }
                exit();
            }
        } else {
            $error = "Incorrect password.";
        }
    } else {
        $error = "It seems that you don't have an account with us. Create now.";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Login - Competency Evaluation</title>
    <link rel="stylesheet" href="css/style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background-color: #f4f6f9;
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
            margin: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .login-card {
            background: white;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 400px;
            text-align: center;
        }
        .logo-area { font-size: 40px; color: #3498db; margin-bottom: 20px; }
        .login-title { font-size: 24px; font-weight: bold; margin-bottom: 10px; color: #333; }
        .login-subtitle { font-size: 14px; color: #7f8c8d; margin-bottom: 30px; }
        
        .form-group { margin-bottom: 20px; text-align: left; }
        .form-control {
            width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px;
            box-sizing: border-box;
        }
        
        .btn-login {
            width: 100%; padding: 12px;
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            color: white; border: none; border-radius: 6px;
            font-size: 16px; font-weight: bold; cursor: pointer; transition: all 0.3s;
        }
        .btn-login:hover { transform: translateY(-2px); box-shadow: 0 4px 10px rgba(52, 152, 219, 0.3); }
        
        .alert-error {
            background: #fadbd8; color: #c0392b; padding: 10px;
            border-radius: 6px; font-size: 14px; margin-bottom: 20px; border: 1px solid #e74c3c;
        }
        .footer-links { margin-top: 20px; font-size: 13px; }
        .footer-links a { color: #3498db; text-decoration: none; }
        .footer-links a:hover { text-decoration: underline; }
    </style>
</head>
<body>

    <div class="login-card">
        <div class="logo-area"><i class="fas fa-graduation-cap"></i></div>
        <div class="login-title">Welcome Back</div>
        <div class="login-subtitle">Sign in to access your account</div>

        <?php if($error): ?>
            <div class="alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label style="font-weight:bold; font-size:12px; color:#555; text-transform:uppercase; display:block; margin-bottom:5px;">Email or Username</label>
                <input type="text" name="identifier" class="form-control" placeholder="Enter email or username" required autofocus>
            </div>

            <div class="form-group">
                <label style="font-weight:bold; font-size:12px; color:#555; text-transform:uppercase; display:block; margin-bottom:5px;">Password</label>
                <input type="password" name="password" class="form-control" placeholder="Enter your password" required>
            </div>

            <button type="submit" class="btn-login">Login</button>
        </form>

        <div class="footer-links">
            <a href="register.php">Create an Account</a>
        </div>
    </div>

</body>
</html>