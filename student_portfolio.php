<?php
session_start();
include __DIR__ . '/db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student_teacher') { header("Location: index.php"); exit(); }

$user_id = $_SESSION['user_id'];
$msg = "";
$title_val = ""; $desc_val = "";
// Handle Submission
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['upload_file'])) {
    $title_val = $_POST['title'];
    $desc_val = $_POST['description'];
    
    $target_file = ""; 
    if (!empty($_FILES['file']['name'])) {
        $target_dir = "uploads/";
        $target_file = $target_dir . $user_id . "_evidence_" . time() . "_" . basename($_FILES["file"]["name"]);
        move_uploaded_file($_FILES["file"]["tmp_name"], $target_file);
    }

    // --- NEW LOGIC: Bundle the Chat History ---
    $chat_transcript = "";
    $get_chats = $conn->query("SELECT * FROM chat_logs WHERE user_id=$user_id ORDER BY created_at ASC");
    
    if ($get_chats->num_rows > 0) {
        while ($chat = $get_chats->fetch_assoc()) {
            $sender_name = ($chat['sender'] == 'user') ? 'Student' : 'AI Copilot';
            $chat_transcript .= "[" . $chat['created_at'] . "] " . $sender_name . ":\n" . $chat['message'] . "\n\n";
        }
    }

    // Insert the submission ALONG WITH the chat transcript
    $stmt = $conn->prepare("INSERT INTO submissions (user_id, title, description, file_path, chat_transcript) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("issss", $user_id, $title_val, $desc_val, $target_file, $chat_transcript);
    
    if($stmt->execute()) { 
        $msg = "Success! Your work and chat history have been submitted."; 
        $title_val = ""; 
        $desc_val = ""; 
        
        // --- NEW LOGIC: Clear the active chat screen for the next portfolio ---
        $conn->query("DELETE FROM chat_logs WHERE user_id=$user_id");
    }
}

// Fetch Submissions
$my_files = $conn->query("SELECT * FROM submissions WHERE user_id=$user_id ORDER BY upload_date DESC");

// Fetch Chat History
$chat_history = $conn->query("SELECT * FROM chat_logs WHERE user_id=$user_id ORDER BY created_at ASC");
?>

<!DOCTYPE html>
<html>
<head>
    <title>Student Workspace</title>
    <link rel="stylesheet" href="css/style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .chat-container { display: flex; flex-direction: column; height: 500px; background: #fdfbfb; border-radius: 0 0 16px 16px; }
        .chat-history { flex: 1; overflow-y: auto; padding: 20px; border-bottom: 1px solid #eee; }
        .chat-input-area { padding: 15px; background: #fff; display: flex; gap: 10px; border-radius: 0 0 16px 16px; }
        .message { margin-bottom: 15px; display: flex; flex-direction: column; }
        .message.user { align-items: flex-end; }
        .message.ai { align-items: flex-start; }
        .bubble { max-width: 80%; padding: 12px 16px; border-radius: 12px; font-size: 14px; line-height: 1.5; position: relative; }
        .message.user .bubble { background: linear-gradient(135deg, #8e44ad 0%, #9b59b6 100%); color: white; border-bottom-right-radius: 2px; }
        .message.ai .bubble { background: #e9ecef; color: #333; border-bottom-left-radius: 2px; }
        .sender-name { font-size: 11px; margin-bottom: 4px; opacity: 0.6; }
        .timestamp { font-size: 10px; margin-top: 2px; opacity: 0.5; }
        .typing-indicator { display: none; padding: 10px; font-style: italic; color: #888; font-size: 12px; }
    </style>
</head>
<body>

    <div class="sidebar">
        <h2 style="margin-bottom: 20px;">Competency and<br>Readiness Evaluation</h2>
        
        <a href="dashboard.php">
            <i class="fas fa-chart-line"></i> Dashboard
        </a>
        
        <a href="user_evaluation.php">
            <i class="fas fa-clipboard-check"></i> My Evaluations
        </a>

        <a href="student_portfolio.php" class="active">
            <i class="fas fa-folder"></i> My Portfolio
        </a>

        <a href="profile.php">
            <i class="fas fa-user"></i> My Profile
        </a>

        <a href="logout.php" class="logout-btn" style="margin-top: auto; background-color: #c0392b; text-align: center;">
            Logout
        </a>
    </div>

    <div class="main-content">
        <div class="top-header">
            <div class="greeting-box">
                <h2><span id="greetingText">Welcome,</span> <?php echo htmlspecialchars($_SESSION['fullname']); ?></h2>
                <div id="currentDate" class="date-box">Loading date...</div>
            </div>
            <button id="themeToggle" class="theme-toggle"><i class="fas fa-moon"></i> Dark Mode</button>
        </div>

        <h2 class="header-title">Student Workspace</h2>
        <?php if($msg) echo "<p style='color:green; background:#e8f8f5; padding:10px; border-radius:5px;'>$msg</p>"; ?>

        <div class="eval-grid">
            
            <form method="POST" enctype="multipart/form-data" id="mainForm">
                <div class="eval-card">
                    <div class="ai-header-banner" style="background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%);">
                        <div class="ai-header-content">
                            <h3><i class="fas fa-pen-nib"></i> Lesson Editor</h3>
                            <p>Write your lesson content here.</p>
                        </div>
                    </div>
                    <div class="eval-body">
                        <div class="modern-input-group">
                            <label class="modern-label">Title</label>
                            <input type="text" name="title" class="modern-input" value="<?php echo htmlspecialchars($title_val); ?>" required>
                        </div>
                        <div class="modern-input-group">
                            <label class="modern-label">Content (Context for AI)</label>
                            <textarea name="description" id="editorContent" class="modern-textarea" rows="18" placeholder="Start typing your lesson plan..."><?php echo htmlspecialchars($desc_val); ?></textarea>
                        </div>
                        <div class="modern-input-group">
                            <label class="modern-label">Attach File</label>
                            <input type="file" id="fileUploadInput" name="file" class="modern-input">
                        </div>
                        <div class="action-footer">
                            <button type="submit" name="upload_file" class="btn-submit-premium"><i class="fas fa-paper-plane"></i> Submit to Supervisor</button>
                        </div>
                    </div>
                </div>
            </form>

            <div>
                <div class="eval-card" style="border:2px solid #8e44ad;">
                    <div class="ai-header-banner" style="background: linear-gradient(135deg, #8e44ad 0%, #9b59b6 100%);">
                        <div class="ai-header-content">
                            <h3><i class="fas fa-robot"></i> AI Copilot Chat</h3>
                            <p>Ask questions. History is saved.</p>
                        </div>
                    </div>
                    
                    <div class="chat-container">
                        <div class="chat-history" id="chatHistory">
                            <?php if ($chat_history->num_rows > 0): ?>
                                <?php while($chat = $chat_history->fetch_assoc()): ?>
                                    <div class="message <?php echo $chat['sender']; ?>">
                                        <div class="sender-name">
                                            <?php echo ($chat['sender'] == 'user') ? 'You' : 'Copilot'; ?>
                                        </div>
                                        <div class="bubble">
                                            <?php echo nl2br(htmlspecialchars($chat['message'])); ?>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <div class="message ai">
                                    <div class="sender-name">Copilot</div>
                                    <div class="bubble">Hello! I'm your AI assistant. Upload a file or type a message to start.</div>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="typing-indicator" id="typingIndicator">
                            <i class="fas fa-circle-notch fa-spin"></i> Copilot is thinking...
                        </div>

                        <div class="chat-input-area">
                            <input type="text" id="chatInput" class="modern-input" placeholder="Type a message..." style="margin-bottom:0; border-radius:20px;">
                            <button type="button" onclick="sendMessage()" class="btn-ai-glow" style="background:#8e44ad; color:white; width:auto; border-radius:50%; padding:12px;">
                                <i class="fas fa-paper-plane"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <script src="js/script.js?v=<?php echo time(); ?>"></script>
    <script>
        const chatInput = document.getElementById('chatInput');
        const chatHistory = document.getElementById('chatHistory');
        const editorContent = document.getElementById('editorContent');
        const typingIndicator = document.getElementById('typingIndicator');
        
        const mainFileInput = document.getElementById('fileUploadInput');

        chatHistory.scrollTop = chatHistory.scrollHeight;

        chatInput.addEventListener("keypress", function(event) {
            if (event.key === "Enter") { event.preventDefault(); sendMessage(); }
        });

        function sendMessage() {
            const message = chatInput.value.trim();
            const contextText = editorContent.value; 

            if (message === "") return;

            addMessageToUI('You', message, 'user');
            chatInput.value = '';
            
            typingIndicator.style.display = 'block';
            chatHistory.scrollTop = chatHistory.scrollHeight;

            // 1. Create a clean JSON object instead of FormData
            const payload = {
                message: message,
                context: contextText,
                mode: 'mentor'
            };

            // Note: Natively attaching the PDF via AJAX requires a bit more advanced Base64 
            // encoding in JS, so we are omitting the file upload from the chat temporarily 
            // to test if we can punch through the firewall with just text.

            // 2. Send as application/json
            fetch('api_chat.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify(payload) 
            })
            .then(response => response.text()) 
            .then(text => {
                console.log("RAW SERVER RESPONSE:", text); 
                
                try {
                    const data = JSON.parse(text);
                    typingIndicator.style.display = 'none';
                    if(data.reply) {
                        addMessageToUI('Copilot', data.reply, 'ai');
                    } else {
                        addMessageToUI('System', 'Received blank response from AI.', 'ai');
                    }
                } catch (parseError) {
                    console.error("Server blocked the request:", text);
                    typingIndicator.style.display = 'none';
                    addMessageToUI('System', 'Firewall blocked the request. See console.', 'ai');
                }
            })
            .catch(error => {
                console.error('Fetch Connection Error:', error);
                typingIndicator.style.display = 'none';
                addMessageToUI('System', 'Network error connecting to server.', 'ai');
            });
        }

        function addMessageToUI(sender, text, type) {
            const msgDiv = document.createElement('div');
            msgDiv.classList.add('message', type);
            
            const nameDiv = document.createElement('div');
            nameDiv.classList.add('sender-name');
            nameDiv.innerText = sender;

            const bubbleDiv = document.createElement('div');
            bubbleDiv.classList.add('bubble');
            bubbleDiv.innerHTML = text.replace(/\n/g, "<br>");

            msgDiv.appendChild(nameDiv);
            msgDiv.appendChild(bubbleDiv);
            chatHistory.appendChild(msgDiv);
            chatHistory.scrollTop = chatHistory.scrollHeight;
        }
    </script>
</body>
</html>
