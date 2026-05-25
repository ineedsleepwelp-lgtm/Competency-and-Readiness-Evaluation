<?php
session_start();
include __DIR__ . '/db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student_teacher') { header("Location: index.php"); exit(); }
$user_id = $_SESSION['user_id'];
$stu_name = $_SESSION['fullname'] ?? 'Student';
?>
<!DOCTYPE html>
<html>
<head>
    <title>My Evaluations</title>
    <link rel="stylesheet" href="css/style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { display: flex; height: 100vh; overflow: hidden; margin: 0; background: #f4f7f6; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .main-content { flex: 1; padding: 30px; overflow-y: auto; height: 100vh; box-sizing: border-box; }
        .card { background: #fff; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); padding: 25px; margin-bottom: 25px; }
        .table-responsive { overflow-x: auto; margin-top: 15px; }
        table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 8px; overflow: hidden; }
        th, td { padding: 15px; text-align: left; border-bottom: 1px solid #eee; font-size: 14px; }
        th { background: #f8f9fa; color: #2c3e50; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5px; font-size: 12px; }
        tr:hover { background: #fdfdfd; }
        .btn-view { background: #3498db; color: white; padding: 8px 15px; border-radius: 4px; text-decoration: none; font-weight: bold; font-size: 12px; display: inline-block; transition: 0.2s; }
        .btn-view:hover { background: #2980b9; }
        .btn-edit { background: #f39c12; color: white; padding: 8px 15px; border-radius: 4px; text-decoration: none; font-weight: bold; font-size: 12px; display: inline-block; transition: 0.2s; }
        .btn-edit:hover { background: #d35400; }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    <div class="main-content">
        
        <div class="top-header" style="background:#fff; padding:15px 20px; border-radius:8px; margin-bottom:20px; box-shadow:0 2px 5px rgba(0,0,0,0.05); border-left: 5px solid #3498db;">
            <h2 style="margin:0;"><i class="fas fa-clipboard-check"></i> Welcome, <?php echo htmlspecialchars($stu_name); ?></h2>
            <div style="font-size:13px; color:#7f8c8d; margin-top:5px;"><?php echo date("l, F j, Y"); ?></div>
        </div>

        <div class="card" style="border-top: 3px solid #f39c12;">
            <h3 style="color:#d35400; margin-top:0;"><i class="fas fa-hourglass-half"></i> Pending Submissions (Awaiting Grade)</h3>
            <p style="font-size:13px; color:#666; margin-top:-5px;">You can still edit these submissions before your Evaluator grades them.</p>
            <div class="table-responsive">
                <table>
                    <tr>
                        <th>Date Submitted</th>
                        <th>Submission Title</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                    <?php
                    $pending = $conn->query("SELECT * FROM submissions WHERE user_id=$user_id AND status='pending' ORDER BY created_at DESC");
                    if ($pending->num_rows > 0):
                        while($p = $pending->fetch_assoc()):
                    ?>
                        <tr>
                            <td><?php echo date("M d, Y", strtotime($p['created_at'])); ?></td>
                            <td><strong><?php echo htmlspecialchars($p['title']); ?></strong></td>
                            <td><span style="background:#fef5e7; color:#d35400; padding:4px 8px; border-radius:4px; font-size:11px; font-weight:bold;">Under Review</span></td>
                            <td><a href="student_portfolio.php?edit_id=<?php echo $p['id']; ?>" class="btn-edit"><i class="fas fa-edit"></i> Edit Submission</a></td>
                        </tr>
                    <?php endwhile; else: ?>
                        <tr><td colspan="4" style="text-align:center; color:#7f8c8d;">You have no pending submissions.</td></tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>

        <div class="card" style="border-top: 3px solid #27ae60;">
            <h3 style="color:#27ae60; margin-top:0;"><i class="fas fa-certificate"></i> Completed Official Evaluations</h3>
            <div class="table-responsive">
                <table>
                    <tr>
                        <th>Date Graded</th>
                        <th>Evaluation Title</th>
                        <th>Official Score</th>
                        <th>Action</th>
                    </tr>
                    <?php
                    $completed = $conn->query("SELECT * FROM evaluations WHERE user_id=$user_id ORDER BY upload_date DESC");
                    if ($completed->num_rows > 0):
                        while($c = $completed->fetch_assoc()):
                    ?>
                        <tr>
                            <td><?php echo date("M d, Y", strtotime($c['upload_date'])); ?></td>
                            <td><strong><?php echo htmlspecialchars($c['evaluation_title']); ?></strong></td>
                            <td><span style="font-weight:bold; color:#27ae60; font-size:16px;"><?php echo htmlspecialchars($c['competency_score']); ?></span><span style="color:#7f8c8d;">/100</span></td>
                            <td><a href="view_evaluation.php?id=<?php echo $c['id']; ?>" class="btn-view" style="background:#2c3e50;"><i class="fas fa-eye"></i> View Feedback</a></td>
                        </tr>
                    <?php endwhile; else: ?>
                        <tr><td colspan="4" style="text-align:center; color:#7f8c8d;">You have no completed evaluations yet.</td></tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>

    </div>
</body>
</html>
