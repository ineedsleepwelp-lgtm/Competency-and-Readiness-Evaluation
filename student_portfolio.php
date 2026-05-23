<?php
session_start();
include __DIR__ . '/db_connect.php';
include_once __DIR__ . '/ai_helper.php'; 

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student_teacher') { header("Location: index.php"); exit(); }

$user_id = $_SESSION['user_id'];
$msg = "";
$title_val = ""; $desc_val = "";

// --- AI Chat Submission Internally ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['send_chat'])) {
    $message = $_POST['message'] ?? '';
    $context = $_POST['context'] ?? '';
    $mode = 'mentor';

    if (!empty($message)) {
        $stmt = $conn->prepare("INSERT INTO chat_logs (user_id, sender, message) VALUES (?, 'user', ?)");
        $stmt->bind_param("is", $user_id, $message);
        $stmt->execute();

        $full_prompt = "CONTEXT:\n$context\n\nUSER QUESTION:\n$message";
        $ai_response = generateAIResponse($full_prompt, $mode);

        $stmt = $conn->prepare("INSERT INTO chat_logs (user_id, sender, message) VALUES (?, 'ai', ?)");
        $stmt->bind_param("is", $user_id, $ai_response);
        $stmt->execute();

        header("Location: student_portfolio.php");
        exit();
    }
}

// --- Portfolio File Submission ---
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
        body { display: flex !important; min-height: 100vh; margin: 0; }
        .sidebar { width: 250px; flex-shrink: 0; }
        .main-content { flex: 1; padding: 20px; }
        .three-col-grid { display: grid; grid-template-columns: 260px 1fr 350px; gap: 20px; align-items: start; }
        @media (max-width: 1200px) { .three-col-grid { grid-template-columns: 1fr; } }
        .info-panel { background: #fff; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); padding: 20px; margin-bottom: 20px; border-left: 4px solid #3498db; }
        .info-panel.ai-panel { border-left-color: #9b59b6; }
        .info-panel.human-panel { border-left-color: #2ecc71; }
        .chat-container { display: flex; flex-direction: column; height: 600px; background: #fdfbfb; }
        .chat-history { flex: 1; overflow-y: auto; padding: 20px; border-bottom: 1px solid #eee; }
        .chat-input-area { padding: 15px; background: #fff; display: flex; gap: 10px; }
        
        /* Loading Overlay */
        #aiLoadingOverlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.85); z-index: 99999; color: white; flex-direction: column; justify-content: center; align-items: center; font-family: sans-serif; }
        .ai-spinner { border: 6px solid #f3f3f3; border-top: 6px solid #8e44ad; border-radius: 50%; width: 60px; height: 60px; animation: spin 1s linear infinite; margin-bottom: 20px; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
    </style>
</head>
<body>

    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <div class="top-header">
            <h2>Workspace: <?php echo htmlspecialchars($_SESSION['fullname']); ?></h2>
            <button id="themeToggle" class="theme-toggle"><i class="fas fa-moon"></i> Dark Mode</button>
        </div>

        <?php if($msg) echo "<p style='color:green; background:#e8f8f5; padding:10px; border-radius:5px;'>$msg</p>"; ?>

        <div class="three-col-grid">
            <div class="column-left">
                <div class="info-panel ai-panel">
                    <h3>AI Pre-Evaluation</h3>
                    <p>Analysis status: Pending Submission.</p>
                </div>
            </div>

            <div class="column-middle">
                <form method="POST" enctype="multipart/form-data" id="mainForm">
                    <div class="eval-card">
                        <div class="eval-body" style="padding:20px;">
                            <label>Title</label>
                            <input type="text" name="title" class="modern-input" value="<?php echo htmlspecialchars($title_val); ?>" required>
                            <label>Content</label>
                            <textarea name="description" id="editorContent" class="modern-textarea" rows="15"><?php echo htmlspecialchars($desc_val); ?></textarea>
                            <label>Attach Evidence</label>
                            <input type="file" name="file" class="modern-input">
                            <button type="submit" name="upload_file" class="btn-submit-premium">Submit Portfolio</button>
                        </div>
                    </div>
                </form>
            </div>

            <div class="column-right">
                <div class="eval-card" style="height: 100%;">
                    <div class="chat-container">
                        <div class="chat-history" id="chatHistory">
                            <?php while($chat = $chat_history->fetch_assoc()): ?>
                                <div class="message <?php echo $chat['sender']; ?>">
                                    <div class="sender-name"><?php echo ($chat['sender'] == 'user') ? 'You' : 'Copilot'; ?></div>
                                    <div class="bubble"><?php echo nl2br(htmlspecialchars($chat['message'])); ?></div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                        <form method="POST" id="chatForm">
                            <div class="chat-input-area">
                                <input type="hidden" name="context" id="hiddenContext">
                                <input type="hidden" name="send_chat" value="1">
                                <input type="text" name="message" id="chatInput" class="modern-input" placeholder="Ask a question..." required>
                                <button type="submit" class="btn-ai-glow"><i class="fas fa-paper-plane"></i></button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="aiLoadingOverlay">
        <div class="ai-spinner"></div>
        <h2>AI is Processing...</h2>
    </div>

    <script>
        const editorContent = document.getElementById('editorContent');
        const hiddenContext = document.getElementById('hiddenContext');
        const chatForm = document.getElementById('chatForm');

        // Capture AI Loading Screen
        document.addEventListener('submit', function(e) {
            if (e.submitter && (e.submitter.name === 'upload_file' || e.submitter.name === 'send_chat')) {
                document.getElementById('aiLoadingOverlay').style.display = 'flex';
                // Sync context for chat
                if(hiddenContext) hiddenContext.value = editorContent.value;
            }
        });
    </script>
</body>
</html>
