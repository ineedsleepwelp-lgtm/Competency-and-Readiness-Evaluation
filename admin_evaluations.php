<?php
session_start();
include __DIR__ . '/db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') { header("Location: index.php"); exit(); }

// Handle Delete
if (isset($_GET['delete_id'])) {
    $del_id = intval($_GET['delete_id']);
    $conn->query("DELETE FROM evaluations WHERE id=$del_id");
    header("Location: admin_evaluations.php"); exit();
}

// Fetch All Evaluations
$query = "
    SELECT e.*, u.fullname AS student_name, u.role, s.title AS submission_title
    FROM evaluations e
    JOIN users u ON e.user_id = u.id
    LEFT JOIN submissions s ON e.submission_id = s.id
    ORDER BY e.upload_date DESC
";
$result = $conn->query($query);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Manage Evaluations</title>
    <link rel="stylesheet" href="css/style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>

    <div class="sidebar">
        <h2>Competency & Readiness</h2>
        <a href="admin_dashboard.php"><i class="fas fa-chart-line"></i> Dashboard</a>
        <a href="admin_manage.php"><i class="fas fa-users"></i> Manage Users</a>
        <a href="admin_evaluations.php" class="active"><i class="fas fa-clipboard-list"></i> Evaluations</a>
        <a href="logout.php" class="logout-btn">Logout</a>
    </div>

    <div class="main-content">
        <div class="top-header">
            <div class="greeting-box">
                <h2>Evaluations List</h2>
                <div class="date-box">All Records</div>
            </div>
            <button id="themeToggle" class="theme-toggle"><i class="fas fa-moon"></i> Dark Mode</button>
        </div>

        <div class="card">
            <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid var(--border-color); padding-bottom:15px; margin-bottom:20px;">
                <h3 style="margin:0;"><i class="fas fa-history" style="color:#3498db;"></i> Evaluation History</h3>
                <a href="add_evaluation_admin.php" class="btn btn-view" style="background:#3498db; color:white; padding:10px 20px; font-weight:bold;">
                <i class="fas fa-plus-circle"></i> Add Evaluation
                </a>    
            </div>

            <div style="overflow-x:auto;">
                <table class="modern-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Student</th>
                            <th>Submission Title</th>
                            <th>Score</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if($result->num_rows > 0): ?>
                            <?php while($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo date("M d, Y", strtotime($row['upload_date'])); ?></td>
                                <td><strong><?php echo htmlspecialchars($row['student_name']); ?></strong></td>
                                <td>
                                    <?php if($row['submission_title']): ?>
                                        <i class="fas fa-file-alt" style="opacity:0.5;"></i> <?php echo htmlspecialchars($row['submission_title']); ?>
                                    <?php else: ?>
                                        <em style="color:#f39c12;">(Direct Observation)</em>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php 
                                        $score = $row['competency_score'];
                                        $color = ($score >= 8) ? '#27ae60' : (($score >= 5) ? '#f39c12' : '#e74c3c');
                                    ?>
                                    <span style="color:<?php echo $color; ?>; font-weight:bold;"><?php echo $score; ?>/10</span>
                                </td>
                                <td>
                                    <a href="admin_view_evaluation.php?id=<?php echo $row['id']; ?>" class="btn btn-view" title="View Report">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="admin_evaluations.php?delete_id=<?php echo $row['id']; ?>" class="btn btn-remove" onclick="return confirm('Delete this evaluation?');">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="5" style="text-align:center;">No evaluations found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <script src="js/script.js?v=<?php echo time(); ?>"></script>
</body>
</html>