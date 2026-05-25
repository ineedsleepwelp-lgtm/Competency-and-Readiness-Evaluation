<?php
session_start();
include __DIR__ . '/db_connect.php';
include_once __DIR__ . '/ai_helper.php'; 

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student_teacher') { header("Location: index.php"); exit(); }
$user_id = $_SESSION['user_id'];
$msg = ""; $title_val = ""; $desc_val = ""; 
$is_editing = false;
$edit_id = isset($_GET['edit_id']) ? intval($_GET['edit_id']) : 0;

// LOAD DATA IF EDITING
if ($edit_id > 0) {
    $check = $conn->query("SELECT * FROM submissions WHERE id=$edit_id AND user_id=$user_id AND status='pending'");
    if ($check->num_rows > 0) {
        $edit_data = $check->fetch_assoc();
        $title_val = $edit_data['title'];
        $desc_val = $edit_data['description'];
        $is_editing = true;
    } else {
        $edit_id = 0; // Invalid edit request
    }
}

// SYNCHRONOUS CHAT LOGIC
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['send_chat'])) {
    $message = trim($_POST['message'] ?? '');
    $context = $_POST['context'] ?? '';
    if (!empty($message)) {
        $stmt = $conn->prepare("INSERT INTO chat_logs (user_id, sender, message) VALUES (?, 'user', ?)");
        $stmt->bind_param("is", $user_id, $message); $stmt->execute();
        $ai_response = generateAIResponse("Context:\n$context\n\nUser Question: $message", 'mentor');
        $stmt = $conn->prepare("INSERT INTO chat_logs (user_id, sender, message) VALUES (?, 'ai', ?)");
        $stmt->bind_param("is", $user_id, $ai_response); $stmt->execute();
        
        // Return to same state (edit or new)
        $redirect = "student_portfolio.php";
        if(isset($_POST['edit_id']) && $_POST['edit_id'] > 0) { $redirect .= "?edit_id=" . $_POST['edit_id']; }
        header("Location: " . $redirect); exit();
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['upload_file'])) {
    $title_val = $_POST['title'];
    $desc_val = $_POST['description'];
    $edit_id_post = isset($_POST['edit_id']) ? intval($_POST['edit_id']) : 0;
    
    $target_file = ""; 
    if (!empty($_FILES['file']['name'])) {
        $target_dir = "uploads/";
        $target_file = $target_dir . $user_id . "_evidence_" . time() . "_" . basename($_FILES["file"]["name"]);
        move_uploaded_file($_FILES["file"]["tmp_name"], $target_file);
    }

    if ($edit_id_post > 0) {
        // UPDATE EXISTING
        if ($target_file !== "") {
            $stmt = $conn->prepare("UPDATE submissions SET title=?, description=?, file_path=? WHERE id=? AND user_id=?");
            $stmt->bind_param("sssii", $title_val, $desc_val, $target_file, $edit_id_post, $user_id);
        } else {
            $stmt = $conn->prepare("UPDATE submissions SET title=?, description=? WHERE id=? AND user_id=?");
            $stmt->bind_param("ssii", $title_val, $desc_val, $edit_id_post, $user_id);
        }
        $stmt->execute();
        $msg = "Success! Your submission has been updated.";
        $is_editing = true; $edit_id = $edit_id_post;
    } else {
        // INSERT NEW
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
            $msg = "Success! Your new portfolio has been submitted."; 
            $title_val = ""; $desc_val = ""; 
            $conn->query("DELETE FROM chat_logs WHERE user_id=$user_id");
        }
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
        body { display: flex; height: 100vh; overflow: hidden; margin: 0; background: #f4f7f6; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .main-content { flex: 1; padding: 30px; overflow-y: auto; height: 100vh; box-sizing: border-box; }
        .student-grid { display: grid; grid-template-columns: 280px minmax(350px, 1fr) 380px; gap: 25px; margin-top: 20px; align-items: start; }
        @media (max-width: 1200px) { .student-grid { grid-template-columns: 1fr; } }
        
        .card, .eval-card { background: #fff; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); overflow: hidden; }
        .info-panel { background: #fff; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); padding: 20px; border-left: 4px solid #3498db; margin-bottom: 20px; }
        .info-panel.ai-panel { border-left-color: #9b59b6; }
        .info-panel.human-panel { border-left-color: #2ecc71; }
        .info-panel h3 { font-size: 13px; color: #7f8c8d; text-transform: uppercase; margin-bottom: 10px; border-bottom: 1px solid #f0f0f0; padding-bottom: 8px; }
        
        .ai-header-banner { padding: 20px; color: white; display: flex; justify-content: space-between; align-items: center; }
        .chat-container { display: flex; flex-direction: column; height: 600px; background: #fdfbfb; }
        .chat-history { flex: 1; overflow-y: auto; padding: 20px; border-bottom: 1px solid #eee; }
        .chat-input-area { padding: 15px; background: #fff; display: flex; gap: 10px; align-items: center; border-top: 1px solid #eee; }
        .message { margin-bottom: 15px; display: flex; flex-direction: column; }
        .message.user { align-items: flex-end; }
        .message.ai { align-items: flex-start; }
        .bubble { max-width: 85%; padding: 12px 16px; border-radius: 12px; font-size: 14px; line-height: 1.5; word-wrap: break-word;}
        .message.user .bubble { background: linear-gradient(135deg, #8e44ad 0%, #9b59b6 100%); color: white; border-bottom-right-radius: 2px; }
        .message.ai .bubble { background: #e9ecef; color: #333; border-bottom-left-radius: 2px; }
        .sender-name { font-size: 11px; margin-bottom: 4px; opacity: 0.6; }
        
        .modern-input-group { margin-bottom: 20px; }
        .modern-label { display: block; font-weight: bold; margin-bottom: 5px; font-size: 14px; color: #555; }
        .modern-input, .modern-textarea { width: 100%; padding: 12px; border: 1px solid #ccc; border-radius: 6px; box-sizing: border-box; font-family: inherit; }
        .btn-submit-premium { background: <?php echo $is_editing ? '#e67e22' : '#3498db'; ?>; color: white; border: none; padding: 15px; border-radius: 6px; cursor: pointer; font-weight: bold; width: 100%; font-size: 16px; transition: 0.2s; }
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
            <h2 style="margin:0;"><span style="opacity:0.7;">Workspace:</span> <?php echo htmlspecialchars($_SESSION['fullname']); ?></h2>
            <button id="themeToggle" class="theme-toggle" style="background:#ecf0f1; border:none; padding:8px 15px; border-radius:20px; cursor:pointer;"><i class="fas fa-moon"></i> Dark Mode</button>
        </div>

        <?php if($msg) echo "<p style='color:white; background:#27ae60; padding:15px; border-radius:6px; font-weight:bold;'>$msg</p>"; ?>

        <div class="student-grid">
            <div>
                <div class="info-panel ai-panel">
                    <h3><i class="fas fa-robot"></i> AI Pre-Evaluation</h3>
                    <p>The AI Copilot will generate a preliminary analysis of your portfolio based on the PPST once submitted.</p>
                </div>
                <div class="info-panel human-panel">
                    <h3><i class="fas fa-user-tie"></i> Official Evaluation</h3>
                    <p>Your Cooperating Teacher and Supervisor reviews will appear here.</p>
                </div>
                <div class="info-panel" style="border-left-color: #34495e;">
                    <h3><i class="fas fa-list-ol"></i> 100-Point Grading Rubric</h3>
                    <ul style="font-size: 12px; color: #444; padding-left: 15px; margin: 0; line-height: 1.6;">
                        <li><strong>Objectives (20 pts):</strong> Clear, measurable, HOTS aligned.</li>
                        <li><strong>Content (20 pts):</strong> Accurate, relevant to curriculum.</li>
                        <li><strong>Methodology (30 pts):</strong> Engaging strategies, well-structured flow.</li>
                        <li><strong>Assessment (20 pts):</strong> Valid tools aligned with objectives.</li>
                        <li><strong>Formatting (10 pts):</strong> Professional standard and mechanics.</li>
                    </ul>
                </div>
            </div>

            <div>
                <form method="POST" enctype="multipart/form-data" id="mainForm">
                    <input type="hidden" name="edit_id" value="<?php echo $edit_id; ?>">
                    <div class="eval-card">
                        <div class="ai-header-banner" style="background: linear-gradient(135deg, <?php echo $is_editing ? '#d35400 0%, #e67e22' : '#2c3e50 0%, #3498db'; ?> 100%);">
                            <div class="ai-header-content">
                                <h3 style="margin:0;"><i class="fas <?php echo $is_editing ? 'fa-edit' : 'fa-pen-nib'; ?>"></i> <?php echo $is_editing ? 'Edit Submission' : 'Lesson Editor'; ?></h3>
                            </div>
                        </div>
                        <div style="padding: 25px;">
                            <div class="modern-input-group">
                                <label class="modern-label">Submission Title</label>
                                <input type="text" name="title" class="modern-input" value="<?php echo htmlspecialchars($title_val); ?>" required>
                            </div>
                            <div class="modern-input-group">
                                <label class="modern-label">Context / Description (Analyzed by AI)</label>
                                <textarea name="description" id="editorContent" class="modern-textarea" rows="14" required><?php echo htmlspecialchars($desc_val); ?></textarea>
                            </div>
                            <div class="modern-input-group" style="background: #f8f9fa; padding: 15px; border-radius: 6px; border: 1px dashed #ccc;">
                                <label class="modern-label"><i class="fas fa-paperclip"></i> <?php echo $is_editing ? 'Replace File Evidence (Optional)' : 'Attach File Evidence (.docx, .txt)'; ?></label>
                                <input type="file" name="file" class="modern-input" style="border: none; padding: 0;">
                            </div>
                            <button type="submit" name="upload_file" class="btn-submit-premium">
                                <i class="fas <?php echo $is_editing ? 'fa-save' : 'fa-cloud-upload-alt'; ?>"></i> <?php echo $is_editing ? 'Save Changes' : 'Submit Portfolio'; ?>
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <div>
                <div class="eval-card" style="border: 2px solid #8e44ad;">
                    <div class="ai-header-banner" style="background: linear-gradient(135deg, #8e44ad 0%, #9b59b6 100%);">
                        <h3 style="margin:0;"><i class="fas fa-robot"></i> Support Copilot</h3>
                    </div>
                    <div class="chat-container">
                        <div class="chat-history" id="chatHistory">
                            <?php if ($chat_history->num_rows > 0): ?>
                                <?php while($chat = $chat_history->fetch_assoc()): ?>
                                    <div class="message <?php echo $chat['sender']; ?>">
                                        <div class="sender-name"><?php echo ($chat['sender'] == 'user') ? 'You' : 'Copilot'; ?></div>
                                        <div class="bubble"><?php echo nl2br(htmlspecialchars($chat['message'])); ?></div>
                                    </div>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <div class="message ai">
                                    <div class="sender-name">Copilot</div>
                                    <div class="bubble">Hello! Ask me questions about your lesson plan or the 100-point rubric.</div>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <form method="POST" id="chatForm" style="margin: 0; padding: 0;">
                            <div class="chat-input-area">
                                <input type="hidden" name="edit_id" value="<?php echo $edit_id; ?>">
                                <input type="hidden" name="context" id="hiddenContextForChat">
                                <input type="text" name="message" class="modern-input" placeholder="Ask a question..." required style="margin-bottom:0; border-radius:20px; padding:10px;">
                                <button type="submit" name="send_chat" class="btn-ai-glow"><i class="fas fa-paper-plane"></i></button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <div id="aiLoadingOverlay">
        <div class="ai-spinner"></div>
        <h2 id="loadingText">Processing...</h2>
    </div>

    <script>
        const chatHistory = document.getElementById('chatHistory');
        const editorContent = document.getElementById('editorContent');
        const hiddenContext = document.getElementById('hiddenContextForChat');

        if(chatHistory) { chatHistory.scrollTop = chatHistory.scrollHeight; }

        document.getElementById('chatForm').addEventListener('submit', function() {
            if(editorContent && hiddenContext) {
                let contextStr = "STUDENT DRAFT TEXT:\n" + editorContent.value;
                const fileInput = document.querySelector('input[type="file"]');
                if (fileInput && fileInput.files.length > 0) {
                    contextStr += "\n\n[SYSTEM NOTE: The student attached a file, but it hasn't uploaded yet. If the Draft Text above is empty, tell them you cannot read files until they submit.]";
                }
                hiddenContext.value = contextStr;
            }
        });

        document.addEventListener('submit', function(e) {
            if (e.target.id === 'mainForm') {
                document.getElementById('loadingText').innerText = 'Saving Submission...';
            } else if (e.target.id === 'chatForm') {
                document.getElementById('loadingText').innerText = 'Copilot is thinking...';
            }
            document.getElementById('aiLoadingOverlay').style.display = 'flex';
        });
    </script>
</body>
</html>
