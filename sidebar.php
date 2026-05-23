<?php
$current_page = basename($_SERVER['PHP_SELF']);
$user_role = $_SESSION['role'] ?? '';

// Helper function to highlight the active tab
function isActive($page, $current) {
    return $page === $current ? 'class="active"' : '';
}
?>

<div class="sidebar">
    <div style="padding: 20px; color: white;">
        <h2>CORE System</h2>
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

    <a href="logout.php" class="logout-btn" style="margin-top: auto;"><i class="fas fa-sign-out-alt"></i> Logout</a>
</div>s