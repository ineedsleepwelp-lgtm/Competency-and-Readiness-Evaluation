<?php
$current_page = basename($_SERVER['PHP_SELF']);
$user_role = $_SESSION['role'] ?? '';

if (!function_exists('isActive')) {
    function isActive($page, $current) {
        return $page === $current ? 'class="active"' : '';
    }
}
?>

<div class="sidebar" style="width: 250px; flex-shrink: 0; background: #2c3e50; min-height: 100vh; height: 100%; display: flex; flex-direction: column;">
    <div style="padding: 20px; color: white;">
        <h2 style="font-size: 18px; margin:0;"><i class="fas fa-graduation-cap"></i> CORE Evaluation</h2>
    </div>
    
    <?php if ($user_role === 'admin'): ?>
        <a href="admin_dashboard.php" <?= isActive('admin_dashboard.php', $current_page) ?>><i class="fas fa-tachometer-alt"></i> Dashboard</a>
        <a href="admin_manage.php" <?= isActive('admin_manage.php', $current_page) ?>><i class="fas fa-users"></i> Manage Users</a>
        <a href="admin_evaluations.php" <?= isActive('admin_evaluations.php', $current_page) ?>><i class="fas fa-file-alt"></i> Evaluations</a>

    <?php elseif ($user_role === 'supervisor'): ?>
        <a href="supervisor_dashboard.php" <?= isActive('supervisor_dashboard.php', $current_page) ?>><i class="fas fa-tachometer-alt"></i> Dashboard</a>
        <a href="profile.php" <?= isActive('profile.php', $current_page) ?>><i class="fas fa-user"></i> My Profile</a>

    <?php elseif ($user_role === 'student_teacher'): ?>
        <a href="dashboard.php" <?= isActive('dashboard.php', $current_page) ?>><i class="fas fa-chart-line"></i> Dashboard</a>
        <a href="user_evaluations.php" <?= isActive('user_evaluations.php', $current_page) ?>><i class="fas fa-clipboard-check"></i> My Evaluations</a>
        <a href="student_portfolio.php" <?= isActive('student_portfolio.php', $current_page) ?>><i class="fas fa-folder-open"></i> My Portfolio</a>
        <a href="profile.php" <?= isActive('profile.php', $current_page) ?>><i class="fas fa-user"></i> My Profile</a>
    <?php endif; ?>

    <a href="logout.php" class="logout-btn" style="padding: 15px; background: #c0392b; color: white; text-decoration: none; display: flex; align-items: center; justify-content: center; margin-top: 5px;">
        <i class="fas fa-sign-out-alt" style="margin-right: 8px;"></i> Logout
    </a>
</div>
