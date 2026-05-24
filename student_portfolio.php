<?php
session_start();
include __DIR__ . '/db_connect.php';
include_once __DIR__ . '/ai_helper.php'; 

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student_teacher') { header("Location: index.php"); exit(); }

$user_id = $_SESSION['user_id'];
$msg = "";
$title_val = ""; $desc_val = "";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['upload_file'])) {
    $title_val = $_POST['title'];
    $desc_val = $_POST['description'];
    
    $target_file = ""; 
    if (!empty($_FILES['file']['name'])) {
        $target_dir = "uploads/";
        $target_file = $target_dir . $user_id . "_evidence_" . time() . "_" . basename($_FILES["file"]["name"]);
        move_uploaded_file($_FILES["file"]["tmp_name"], $target_file);
    }

    $chat_transcript = "";
    $get_chats = $conn->query("SELECT * FROM chat_logs WHERE user_id=$user_id ORDER BY created_at ASC");
    if ($get_chats->num_rows > 0) {
        while ($chat = $get_chats->fetch_assoc()) {
            $sender_name = ($chat['sender'] == 'user') ? 'Student' : 'AI Copilot';
            $chat_transcript .= "[" . $chat['created_at'] . "] " . $sender_name . ":\n" . $chat['message'] . "\n\n";
        }
    }

    $stmt = $conn->prepare("INSERT INTO submissions (user_id, title, description, file_path, chat_transcript) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("issss", $user_id, $title_val, $desc_val, $target_file, $chat_transcript);
    
    if($stmt->execute()) { 
        $msg = "Success! Your work and chat history have been submitted."; 
        $title_val = ""; $desc_val = ""; 
        $conn->query("DELETE FROM chat_logs WHERE user_id=$user_id");
    }
}

$chat_history = $conn->query("SELECT * FROM chat_logs WHERE user_id=$user_id ORDER BY created_at ASC");
?>
<!DOCTYPE html>
<html>
<head>
    <title>Student Workspace</title>
    <link rel="stylesheet" href="css/style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* INDESTRUCTIBLE FLEXBOX DASHBOARD LAYOUT */
        body { display: flex; height: 100vh; overflow: hidden; margin: 0; background: #f4f7f6; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .main-content { flex: 1; padding: 30px; overflow-y: auto; height: 100vh; box-sizing: border-box; }
        
        .eval-grid { display: flex; flex-wrap: wrap; gap: 25px; margin-top: 20px; align-items: flex-start; }
        .column-left { flex: 1; min-width: 400px; display: flex; flex-direction: column; gap: 20px; }
        .column-right { width: 380px; flex-shrink: 0; }
        @media (max-width: 1100px) { .column-right { width: 100%; } }
        
        .card { background: #fff; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); padding: 25px; }
        .eval-card { background: #fff; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); overflow: hidden; }
        
        .info-panel { background: #fff; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); padding: 20px; border-left: 4px solid #3498db; margin-bottom: 20px; }
        .info-panel.ai-panel { border-left-color: #9b59b6; }
        .info-panel.human-panel { border-left-color: #2ecc71; }
        .info-panel h3 { font-size: 12px; color: #7f8c8d; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 10px; border-bottom: 1px solid #f0f0f0; padding-bottom: 8px; }
        .info-panel p { font-size: 14px; color: #333; line-height: 1.5; margin:0; }
        .status-badge { display: inline-block; padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: bold; background: #f1c40f; color: #fff; margin-top: 10px; }
        
        .ai-header-banner { padding: 20px; color: white; display: flex; justify-content: space-between; align-items: center; }
        .ai-header-banner h3 { margin: 0 0 5px 0; font-size: 18px; }
        .ai-header-banner p { margin: 0; font-size: 13px; opacity: 0.9; }
        
        .chat-container { display: flex; flex-direction: column; height: 600px; background: #fdfbfb; }
        .chat-history { flex: 1; overflow-y: auto; padding: 20px; border-bottom: 1px solid #eee; }
        .chat-input-area { padding: 15px; background: #fff; display: flex; gap: 10px; align-items: center; border-top: 1px solid #eee; }
        .message { margin-bottom: 15px; display: flex; flex-direction: column; }
        .message.user { align-items: flex-end; }
        .message.ai { align-items: flex-start; }
        .bubble { max-width: 85%; padding: 12px 16px; border-radius: 12px; font-size: 14px; line-height: 1.5; position: relative; }
        .message.user .bubble { background: linear-gradient(135deg, #8e44ad 0%, #9b59b6 100%); color: white; border-bottom-right-radius: 2px; }
        .message.ai .bubble { background: #e9ecef; color: #333; border-bottom-left-radius: 2px; }
        .sender-name { font-size: 11px; margin-bottom: 4px; opacity: 0.6; }
        .typing-indicator { display: none; padding: 10px 20px; font-style: italic; color: #888; font-size: 12px; background:#fff;}
        
        .modern-input-group { margin-bottom: 15px; }
        .modern-label { display: block; font-weight: bold; margin-bottom: 5px; font-size: 13px; color: #555; }
        .modern-input, .modern-textarea { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; font-family: inherit; }
        .btn-submit-premium { background: #3498db; color: white; border: none; padding: 12px; border-radius: 4px; cursor: pointer; font-weight: bold; width: 100%; }
        .btn-ai-glow { background: #8e44ad; color: white; border: none; padding: 10px 15px; border-radius: 50%; cursor: pointer; }

        #aiLoadingOverlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.85); z-index: 99999; color: white; flex-direction: column; justify-content: center; align-items: center; }
        .ai-spinner { border: 6px solid #f3f3f3; border-top: 6px solid #8e44ad; border-radius: 50%; width: 60px; height: 60px; animation: spin 1s linear infinite; margin-bottom: 20px; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
    </style>
</head>
<body>

    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <div class="top-header" style="display:flex; justify-content:space-between; align-items:center; background:#fff; padding:15px 20px; border-radius:8px; margin-bottom:20px; box-shadow:0 2px 5px rgba(0,0,0,0.05);">
            <div class="greeting-box">
                <h2 style="margin:0;"><span style="opacity:0.7;">Workspace:</span> <?php echo htmlspecialchars($_SESSION['fullname']); ?></h2>
            </div>
            <button id="themeToggle" class="theme-toggle" style="background:#ecf0f1; border:none; padding:8px 15px; border-radius:20px; cursor:pointer;"><i class="fas fa-moon"></i> Dark Mode</button>
        </div>

        <?php if($msg) echo "<p style='color:green; background:#e8f8f5; padding:10px; border-radius:5px;'>$msg</p>"; ?>

        <div class="eval-grid">
            <div style="width: 260px; flex-shrink:0;">
    <div class="info-panel ai-panel">
        <h3><i class="fas fa-robot"></i> AI Pre-Evaluation</h3>
        <p>The AI Copilot will generate a preliminary analysis of your portfolio based on the PPST once submitted.</p>
        <div class="status-badge" style="background:#e67e22;">Pending Submission</div>
    </div>

    <div class="info-panel human-panel">
        <h3><i class="fas fa-user-tie"></i> Official Evaluation</h3>
        <p>Your Cooperating Teacher and Supervisor reviews will appear here.</p>
        <div class="status-badge" style="background:#95a5a6;">Awaiting Review</div>
    </div>
    
    <div class="info-panel" style="border-left-color: #34495e;">
        <h3><i class="fas fa-list-ol"></i> 100-Point Grading Rubric</h3>
        <p style="font-size: 12px; color: #666; margin-bottom: 8px;">Ensure your lesson plan covers these required competencies:</p>
        <ul style="font-size: 12px; color: #444; padding-left: 15px; margin: 0; line-height: 1.6;">
            <li><strong>Objectives (20 pts):</strong> Clear, measurable, HOTS aligned.</li>
            <li><strong>Content (20 pts):</strong> Accurate, relevant to curriculum.</li>
            <li><strong>Methodology (30 pts):</strong> Engaging strategies, well-structured flow.</li>
            <li><strong>Assessment (20 pts):</strong> Valid tools aligned with objectives.</li>
            <li><strong>Formatting (10 pts):</strong> Professional standard and mechanics.</li>
        </ul>
    </div>
</div>
