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
        /* --- FIXED SIDEBAR & LAYOUT CSS --- */
        body { display: flex !important; min-height: 100vh; overflow-x: hidden; margin: 0; background: #f4f7f6; }
        
        /* Force sidebar to act as a normal flex item, locking it to the left */
        .sidebar { width: 250px !important; flex-shrink: 0 !important; position: relative !important; z-index: 1000; min-height: 100vh; }
        
        /* Remove any hidden margins from the external style.css to close the gap */
        .main-content { flex: 1 !important; margin-left: 0 !important; padding: 30px !important; width: calc(100% - 250px) !important; transition: none !important; }
        
        /* Grid Layout */
        .three-col-grid { display: grid; grid-template-columns: 260px 1fr 350px; gap: 20px; align-items: start; margin-top: 15px; }
        @media (max-width: 1200px) { .three-col-grid { grid-template-columns: 1fr; } }
        
        /* Panels and Cards */
        .info-panel { background: #fff; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); padding: 20px; margin-bottom: 20px; border-left: 4px solid #3498db; }
        .info-panel.ai-panel { border-left-color: #9b59b6; }
        .info-panel.human-panel { border-left-color: #2ecc71; }
        .info-panel h3 { font-size: 12px; color: #7f8c8d; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 10px; border-bottom: 1px solid #f0f0f0; padding-bottom: 8px; }
        .info-panel p { font-size: 14px; color: #333; line-height: 1.5; margin:0; }
        .status-badge { display: inline-block; padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: bold; background: #f1c40f; color: #fff; margin-top: 10px; }
        
        .eval-card { box-shadow: 0 4px 12px rgba(0,0,0,0.05); margin-bottom: 0; background: #fff; border-radius: 8px; overflow: hidden; }
        .ai-header-banner { padding: 20px; color: white; }
        .ai-header-banner h3 { margin: 0 0 5px 0; font-size: 18px; }
        .ai-header-banner p { margin: 0; font-size: 13px; opacity: 0.9; }
        
        /* Chat UI */
        .chat-container { display: flex; flex-direction: column; height: 500px; background: #fdfbfb; }
        .chat-history { flex: 1; overflow-y: auto; padding: 20px; border-bottom: 1px solid #eee; }
        .chat-input-area { padding: 15px; background: #fff; display: flex; gap: 10px; align-items: center; }
        .message { margin-bottom: 15px; display: flex; flex-direction: column; }
        .message.user { align-items: flex-end; }
        .message.ai { align-items: flex-start; }
        .bubble { max-width: 85%; padding: 12px 16px; border-radius: 12px; font-size: 14px; line-height: 1.5; position: relative; }
        .message.user .bubble { background: linear-gradient(135deg, #8e44ad 0%, #9b59b6 100%); color: white; border-bottom-right-radius: 2px; }
        .message.ai .bubble { background: #e9ecef; color: #333; border-bottom-left-radius: 2px; }
        .sender-name { font-size: 11px; margin-bottom: 4px; opacity: 0.6; }
        .typing-indicator { display: none; padding: 10px 20px; font-style: italic; color: #888; font-size: 12px; background:#fff;}

        /* Form elements */
        .modern-input-group { margin-bottom: 15px; }
        .modern-label { display: block; font-weight: bold; margin-bottom: 5px; font-size: 13px; color: #555; }
        .modern-input, .modern-textarea { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; font-family: inherit; }
        .btn-submit-premium { background: #3498db; color: white; border: none; padding: 12px; border-radius: 4px; cursor: pointer; font-weight: bold; }
        .btn-ai-glow { background: #8e44ad; color: white; border: none; padding: 10px 15px; border-radius: 20px; cursor: pointer; }

        /* Loading Overlay CSS */
        #aiLoadingOverlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.85); z-index: 99999; color: white; flex-direction: column; justify-content: center; align-items: center; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
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

        <?php if($msg) echo "<p style='color:green; background:#e8f8f5; padding:10px; border-radius:5px;'>$msg</p>"; ?>

        <div class="three-col-grid">
            <div class="column-left">
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
                    <h3><i class="fas fa-info-circle"></i> Submission Guidelines</h3>
                    <p style="font-size: 13px; color: #666;">Ensure your lesson plan covers all required competencies. Use the AI Copilot on the right to brainstorm or refine your content before submitting.</p>
                </div>
            </div>

            <div class="column-middle">
                <form method="POST" enctype="multipart/form-data" id="mainForm">
                    <div class="eval-card">
                        <div class="ai-header-banner" style="background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%);">
                            <h3><i class="fas fa-pen-nib"></i> Lesson Editor</h3>
                            <p>Draft your work here.</p>
                        </div>
                        <div style="padding: 20px;">
                            <div class="modern-input-group">
                                <label class="modern-label">Title</label>
                                <input type="text" name="title" class="modern-input" value="<?php echo htmlspecialchars($title_val); ?>" required>
                            </div>
                            <div class="modern-input-group">
                                <label class="modern-label">Content (Context for AI)</label>
                                <textarea name="description" id="editorContent" class="modern-textarea" rows="16" placeholder="Start typing your lesson plan..."><?php echo htmlspecialchars($desc_val); ?></textarea>
                            </div>
                            <div class="modern-input-group">
                                <label class="modern-label">Attach File Evidence</label>
                                <input type="file" name="file" class="modern-input">
                            </div>
                            <button type="submit" name="upload_file" class="btn-submit-premium" style="width: 100%;"><i class="fas fa-paper-plane"></i> Submit Portfolio</button>
                        </div>
                    </div>
                </form>
            </div>

            <div class="column-right">
                <div class="eval-card" style="border: 2px solid #8e44ad;">
                    <div class="ai-header-banner" style="background: linear-gradient(135deg, #8e44ad 0%, #9b59b6 100%);">
                        <h3><i class="fas fa-robot"></i> Support Copilot</h3>
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
                                    <div class="bubble">Hello! I'm here to support you. Ask me questions about your lesson plan or the PPST.</div>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="typing-indicator" id="typingIndicator">
                            <i class="fas fa-circle-notch fa-spin"></i> Copilot is thinking...
                        </div>
                        
                        <form method="POST" id="chatForm" style="margin: 0; border-top: 1px solid #eee;">
                            <div class="chat-input-area" style="border-top: 1px solid #eee;">
                                <input type="hidden" name="context" id="hiddenContext">
                                <input type="text" id="chatInput" class="modern-input" placeholder="Ask a question..." required style="margin-bottom:0; border-radius:20px;">
                                <button type="button" onclick="sendMessage()" class="btn-ai-glow"><i class="fas fa-paper-plane"></i></button>
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
        <p style="color:#aaa;">Please wait while the Copilot analyzes your request. Do not refresh.</p>
    </div>

    <script>
        const chatHistory = document.getElementById('chatHistory');
        const chatInput = document.getElementById('chatInput');
        const editorContent = document.getElementById('editorContent');
        const typingIndicator = document.getElementById('typingIndicator');

        // 1. Send Message via Background Fetch (No Reload)
        function sendMessage() {
            const message = chatInput.value.trim();
            const contextText = editorContent.value; 

            if (message === "") return;

            // Display user message immediately
            addMessageToUI('You', message, 'user');
            chatInput.value = '';
            typingIndicator.style.display = 'block';

            const payload = {
                message: message,
                context: contextText,
                mode: 'mentor'
            };

            // Send to api_chat.php (The background worker)
            fetch('api_chat.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload) 
            })
            .then(response => response.json()) 
            .then(data => {
                typingIndicator.style.display = 'none';
            if(data.error === "SESSION_EXPIRED") {
                 addMessageToUI('System', 'Your session has expired. Please refresh the page to continue.', 'ai');
            // Optional: window.location.reload(); // Uncomment this to force-refresh automatically
            } else if(data.reply) {
                 addMessageToUI('Copilot', data.reply, 'ai');
            } else {
                 addMessageToUI('System', 'Error: ' + (data.error || 'Unknown error'), 'ai');
            }
            })
            .catch(error => {
                typingIndicator.style.display = 'none';
                addMessageToUI('System', 'Network error. Check your connection.', 'ai');
            });

        // 2. Helper to inject HTML into the chat history
        function addMessageToUI(sender, text, type) {
            const msgDiv = document.createElement('div');
            msgDiv.classList.add('message', type);
            msgDiv.innerHTML = `
                <div class="sender-name">${sender}</div>
                <div class="bubble">${text.replace(/\n/g, "<br>")}</div>
            `;
            chatHistory.appendChild(msgDiv);
            chatHistory.scrollTop = chatHistory.scrollHeight;
        }
    </script>
</body>
</html>
