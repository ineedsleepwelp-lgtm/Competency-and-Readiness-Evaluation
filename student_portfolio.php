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
        $title_val = ""; 
        $desc_val = ""; 
        $conn->query("DELETE FROM chat_logs WHERE user_id=$user_id");
    }
}

$my_files = $conn->query("SELECT * FROM submissions WHERE user_id=$user_id ORDER BY upload_date DESC");
$chat_history = $conn->query("SELECT * FROM chat_logs WHERE user_id=$user_id ORDER BY created_at ASC");
?>

<!DOCTYPE html>
<html>
<head>
    <title>Student Workspace</title>
    <link rel="stylesheet" href="css/style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* --- MINI-SIDEBAR CSS (Icon-Only Default) --- */
        body {
            display: flex !important;
            min-height: 100vh;
            overflow-x: hidden;
            margin: 0;
        }
        
        /* Base Sidebar (Expanded State) */
        .sidebar {
            width: 250px;
            transition: all 0.3s ease-in-out !important;
            flex-shrink: 0 !important; 
            position: relative !important; 
            z-index: 1000;
            overflow: visible; /* Needed for tooltips to show outside */
        }
        
        .sidebar-header {
            display: flex;
            align-items: center;
            padding: 20px;
            gap: 15px;
            color: white;
        }

        .sidebar a {
            display: flex;
            align-items: center;
            white-space: nowrap;
        }
        
        .sidebar a i { margin-right: 10px; width: 20px; text-align: center; }

        /* --- COLLAPSED STATE (Mini Sidebar) --- */
        body.sidebar-collapsed .sidebar {
            width: 70px !important; /* Shrunks to icon width */
        }

        /* Hide text when collapsed */
        body.sidebar-collapsed .sidebar .nav-text,
        body.sidebar-collapsed .sidebar-header .sidebar-title {
            display: none;
        }

        /* Center icons when collapsed */
        body.sidebar-collapsed .sidebar a,
        body.sidebar-collapsed .sidebar-header {
            justify-content: center;
            padding-left: 0;
            padding-right: 0;
        }
        
        body.sidebar-collapsed .sidebar a i {
            margin-right: 0;
            font-size: 1.2rem;
        }

        body.sidebar-collapsed .sidebar-header i { font-size: 24px; }

        /* Hover Tooltips for Mini Sidebar */
        body.sidebar-collapsed .sidebar a { position: relative; }
        body.sidebar-collapsed .sidebar a:hover::after {
            content: attr(title);
            position: absolute;
            left: 75px;
            background: #2c3e50;
            color: white;
            padding: 5px 10px;
            border-radius: 4px;
            white-space: nowrap;
            font-size: 12px;
            z-index: 1001;
            pointer-events: none;
        }

        /* Main Content adjustment */
        .main-content {
            transition: all 0.3s ease-in-out !important;
            flex: 1 !important;
            width: 100% !important;
        }

        /* Hamburger Menu Button */
        .menu-toggle-btn {
            background: transparent;
            border: none;
            color: #2c3e50;
            font-size: 24px;
            cursor: pointer;
            margin-right: 15px;
            padding: 5px;
            transition: color 0.3s ease;
            display: inline-block !important; 
        }
        .menu-toggle-btn:hover { color: #8e44ad; }


        /* --- 3-COLUMN LAYOUT CSS --- */
        .three-col-grid {
            display: grid;
            grid-template-columns: 260px 1fr 350px; 
            gap: 20px;
            align-items: start;
            margin-top: 15px;
        }

        @media (max-width: 1200px) {
            .three-col-grid { grid-template-columns: 1fr; } 
        }

        .info-panel {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            padding: 20px;
            margin-bottom: 20px;
            border-left: 4px solid #3498db;
        }
        .info-panel.ai-panel { border-left-color: #9b59b6; }
        .info-panel.human-panel { border-left-color: #2ecc71; }
        
        .info-panel h3 { font-size: 12px; color: #7f8c8d; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 10px; border-bottom: 1px solid #f0f0f0; padding-bottom: 8px; }
        .info-panel p { font-size: 14px; color: #333; line-height: 1.5; }
        .status-badge { display: inline-block; padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: bold; background: #f1c40f; color: #fff; margin-top: 10px; }

        .eval-card { box-shadow: 0 4px 12px rgba(0,0,0,0.05); margin-bottom: 0; }
        .chat-container { display: flex; flex-direction: column; height: 600px; background: #fdfbfb; border-radius: 0 0 16px 16px; }
        .chat-history { flex: 1; overflow-y: auto; padding: 20px; border-bottom: 1px solid #eee; }
        .chat-input-area { padding: 15px; background: #fff; display: flex; gap: 10px; border-radius: 0 0 16px 16px; }
        .message { margin-bottom: 15px; display: flex; flex-direction: column; }
        .message.user { align-items: flex-end; }
        .message.ai { align-items: flex-start; }
        .bubble { max-width: 85%; padding: 12px 16px; border-radius: 12px; font-size: 14px; line-height: 1.5; position: relative; }
        .message.user .bubble { background: linear-gradient(135deg, #8e44ad 0%, #9b59b6 100%); color: white; border-bottom-right-radius: 2px; }
        .message.ai .bubble { background: #e9ecef; color: #333; border-bottom-left-radius: 2px; }
        .sender-name { font-size: 11px; margin-bottom: 4px; opacity: 0.6; }
        .typing-indicator { display: none; padding: 10px; font-style: italic; color: #888; font-size: 12px; }
    </style>
</head>
<body class="sidebar-collapsed">

    <div class="sidebar">
        <div class="sidebar-header">
            <i class="fas fa-graduation-cap"></i>
            <span class="sidebar-title" style="font-size: 18px; font-weight: bold;">CORE<br><span style="font-size: 12px; font-weight: normal;">Evaluation</span></span>
        </div>
        
        <a href="dashboard.php" title="Dashboard">
            <i class="fas fa-chart-line"></i> <span class="nav-text">Dashboard</span>
        </a>
        <a href="user_evaluation.php" title="My Evaluations">
            <i class="fas fa-clipboard-check"></i> <span class="nav-text">My Evaluations</span>
        </a>
        <a href="student_portfolio.php" class="active" title="My Portfolio">
            <i class="fas fa-folder"></i> <span class="nav-text">My Portfolio</span>
        </a>
        <a href="profile.php" title="My Profile">
            <i class="fas fa-user"></i> <span class="nav-text">My Profile</span>
        </a>
        <a href="logout.php" class="logout-btn" title="Logout" style="margin-top: auto; background-color: #c0392b; text-align: center;">
            <i class="fas fa-sign-out-alt"></i> <span class="nav-text">Logout</span>
        </a>
    </div>

    <div class="main-content">
        <div class="top-header">
            <div class="greeting-box" style="display: flex; align-items: center;">
                <button id="sidebarToggle" class="menu-toggle-btn" title="Toggle Sidebar">
                    <i class="fas fa-bars"></i>
                </button>
                <div>
                    <h2><span id="greetingText">Workspace:</span> <?php echo htmlspecialchars($_SESSION['fullname']); ?></h2>
                </div>
            </div>
            <button id="themeToggle" class="theme-toggle"><i class="fas fa-moon"></i> Dark Mode</button>
        </div>

        <?php if($msg) echo "<p style='color:green; background:#e8f8f5; padding:10px; border-radius:5px;'>$msg</p>"; ?>

        <div class="three-col-grid">
            
            <div class="column-left">
                <div class="info-panel ai-panel">
                    <h3><i class="fas fa-robot"></i> AI Pre-Evaluation</h3>
                    <p>The AI Copilot will generate a preliminary analysis of your portfolio based on the PPST once submitted.</p>
                    <span class="status-badge" style="background:#e67e22;">Pending Submission</span>
                </div>

                <div class="info-panel human-panel">
                    <h3><i class="fas fa-user-tie"></i> Official Evaluation</h3>
                    <p>Your Cooperating Teacher and Supervisor reviews will appear here.</p>
                    <span class="status-badge" style="background:#95a5a6;">Awaiting Review</span>
                </div>
                
                <div class="info-panel" style="border-left-color: #34495e;">
                    <h3><i class="fas fa-info-circle"></i> Submission Guidelines</h3>
                    <p style="font-size: 12px; color: #666;">Ensure your lesson plan covers all required competencies. Use the AI Copilot on the right to brainstorm or refine your content before submitting.</p>
                </div>
            </div>

            <div class="column-middle">
                <form method="POST" enctype="multipart/form-data" id="mainForm">
                    <div class="eval-card">
                        <div class="ai-header-banner" style="background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%);">
                            <div class="ai-header-content">
                                <h3><i class="fas fa-pen-nib"></i> Lesson Editor</h3>
                                <p>Draft your work here.</p>
                            </div>
                        </div>
                        <div class="eval-body">
                            <div class="modern-input-group">
                                <label class="modern-label">Title</label>
                                <input type="text" name="title" class="modern-input" value="<?php echo htmlspecialchars($title_val); ?>" required>
                            </div>
                            <div class="modern-input-group">
                                <label class="modern-label">Content (Context for AI)</label>
                                <textarea name="description" id="editorContent" class="modern-textarea" rows="20" placeholder="Start typing your lesson plan..."><?php echo htmlspecialchars($desc_val); ?></textarea>
                            </div>
                            <div class="modern-input-group">
                                <label class="modern-label">Attach File Evidence</label>
                                <input type="file" id="fileUploadInput" name="file" class="modern-input">
                            </div>
                            <div class="action-footer">
                                <button type="submit" name="upload_file" class="btn-submit-premium" style="width: 100%;"><i class="fas fa-paper-plane"></i> Submit Portfolio</button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>

            <div class="column-right">
                <div class="eval-card" style="border:2px solid #8e44ad; height: 100%;">
                    <div class="ai-header-banner" style="background: linear-gradient(135deg, #8e44ad 0%, #9b59b6 100%);">
                        <div class="ai-header-content">
                            <h3><i class="fas fa-robot"></i> Support Copilot</h3>
                        </div>
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

                        <form method="POST" action="" id="chatForm" style="margin: 0;">
                            <div class="chat-input-area">
                                <input type="hidden" name="context" id="hiddenContext">
                                <input type="hidden" name="send_chat" value="1"> 
                                <input type="text" name="message" id="chatInput" class="modern-input" placeholder="Ask a question..." required style="margin-bottom:0; border-radius:20px;">
                                <button type="submit" class="btn-ai-glow" style="background:#8e44ad; color:white; border-radius:50%; padding:12px; border:none; cursor:pointer;"><i class="fas fa-paper-plane"></i></button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <script src="js/script.js?v=<?php echo time(); ?>"></script>
    <script>
        const chatHistory = document.getElementById('chatHistory');
        const chatForm = document.getElementById('chatForm');
        const hiddenContext = document.getElementById('hiddenContext');
        const editorContent = document.getElementById('editorContent');
        const typingIndicator = document.getElementById('typingIndicator');
        const sidebarToggle = document.getElementById('sidebarToggle');

<<<<<<< HEAD
        if(sidebarToggle) {
            sidebarToggle.addEventListener('click', function() {
                document.body.classList.toggle('sidebar-collapsed'); 
=======
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
>>>>>>> 4d4130f88f513ab1c8ef963d37ad709fd48d1402
            });
        }

        if(chatHistory) { chatHistory.scrollTop = chatHistory.scrollHeight; }

        if(chatForm) {
            chatForm.addEventListener('submit', function() {
                hiddenContext.value = editorContent.value;
                typingIndicator.style.display = 'block';
            });
        }
    </script>
</body>
</html>
