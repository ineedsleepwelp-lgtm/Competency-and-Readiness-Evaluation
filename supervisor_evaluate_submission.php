<?php
session_start();
include __DIR__ . '/db_connect.php';
include __DIR__ . '/ai_helper.php';

function extractTextForEvaluation($filePath) {
    if (!file_exists($filePath)) { return "[SYSTEM ERROR: File not found.]"; }
    $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    $text = "";
    if ($ext === 'docx') {
        if (class_exists('ZipArchive')) {
            $zip = new ZipArchive;
            if ($zip->open($filePath) === TRUE) {
                if (($index = $zip->locateName('word/document.xml')) !== false) {
                    $xml = $zip->getFromIndex($index);
                    $dom = new DOMDocument;
                    $dom->loadXML($xml, LIBXML_NOENT | LIBXML_XINCLUDE | LIBXML_NOERROR | LIBXML_NOWARNING);
                    $text = strip_tags($dom->saveXML());
                }
                $zip->close();
            }
        }
    } elseif ($ext === 'txt') {
        $text = file_get_contents($filePath);
    } else {
        $text = "[SYSTEM NOTE: File type .$ext not supported for auto-read.]";
    }
    return substr(trim($text), 0, 15000);
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'supervisor') { header("Location: index.php"); exit(); }

$supervisor_id = $_SESSION['user_id'];
$sub_id = isset($_GET['sub_id']) ? intval($_GET['sub_id']) : 0;
$ai_suggestion = "";
$suggested_score = "";

$sub_q = $conn->query("SELECT s.*, u.fullname FROM submissions s JOIN users u ON s.user_id = u.id WHERE s.id=$sub_id");
if($sub_q->num_rows == 0) { header("Location: supervisor_dashboard.php"); exit(); }
$sub = $sub_q->fetch_assoc();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['generate_ai'])) {
        $context = "Title: " . $sub['title'] . "\nContext: " . $sub['description'];
        $file_content_msg = "";
        
        if (!empty($sub['file_path'])) {
            $raw_path = $sub['file_path']; 
            $clean_rel_path = ltrim(str_replace(['../', '..\\'], '', $raw_path), '/\\');
            $target_full_path = __DIR__ . '/' . $clean_rel_path;
            if (!file_exists($target_full_path)) { $target_full_path = realpath($clean_rel_path); }
            $extracted_text = extractTextForEvaluation($target_full_path);
            
            $file_content_msg = "\n\n=== [EVIDENCE FILE CONTENT] ===\nFile Name: " . basename($raw_path) . "\n" . $extracted_text . "\n==============================\n";
        }
        
        $full_prompt = "You are a Supervisor evaluating a student.\n" . $context . $file_content_msg;
        $raw_ai_text = generateAIResponse($full_prompt, 'evaluator');
        
        if (preg_match('/\[SCORE:\s*(\d+)\]/', $raw_ai_text, $matches)) {
            $suggested_score = $matches[1]; 
            $ai_suggestion = trim(str_replace($matches[0], "", $raw_ai_text));
        } else { 
            $ai_suggestion = $raw_ai_text; 
        }

    } elseif (isset($_POST['submit_grade'])) {
        $eval_title = $_POST['eval_title']; $score = $_POST['score']; $notes = $_POST['notes']; $student_id = $sub['user_id'];
        $target_file = $sub['file_path'];
        
        if (!empty($_FILES['file']['name'])) {
            $target_dir = "uploads/";
            $target_file = $target_dir . "eval_" . time() . "_" . basename($_FILES["file"]["name"]);
            move_uploaded_file($_FILES["file"]["tmp_name"], $target_file);
        }
        
        $stmt = $conn->prepare("INSERT INTO evaluations (user_id, evaluator_id, submission_id, evaluation_title, competency_score, readiness_notes, file_path, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')");
        $stmt->bind_param("iiisiss", $student_id, $supervisor_id, $sub_id, $eval_title, $score, $notes, $target_file);
        
        if($stmt->execute()) { 
            $conn->query("UPDATE submissions SET status='evaluated' WHERE id=$sub_id"); 
            header("Location: supervisor_dashboard.php"); 
            exit(); 
        }
    }
}
$chat_history = $conn->query("SELECT * FROM chat_logs WHERE user_id=$supervisor_id ORDER BY created_at ASC");
?>
<!DOCTYPE html>
<html>
<head>
    <title>Supervisor Evaluation</title>
    <link rel="stylesheet" href="css/style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* --- FIXED SIDEBAR & LAYOUT CSS --- */
        body { display: flex !important; min-height: 100vh; overflow-x: hidden; margin: 0; background: #f4f7f6; }
        .sidebar { width: 250px !important; flex-shrink: 0 !important; position: relative !important; z-index: 1000; min-height: 100vh; }
        .main-content { flex: 1 !important; margin-left: 0 !important; padding: 30px !important; width: calc(100% - 250px) !important; transition: none !important; }
        
        .eval-grid { display: grid; grid-template-columns: 260px 1fr 350px; gap: 20px; align-items: start; margin-top: 15px; }
        @media (max-width: 1200px) { .eval-grid { grid-template-columns: 1fr; } }
        
        .context-card { background: #fff; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); padding: 20px; }
        .eval-card { box-shadow: 0 4px 12px rgba(0,0,0,0.05); margin-bottom: 0; background: #fff; border-radius: 8px; overflow: hidden; }
        .ai-header-banner { padding: 20px; color: white; display: flex; justify-content: space-between; align-items: center; }
        .ai-header-banner h3 { margin: 0 0 5px 0; font-size: 18px; }
        .ai-header-banner p { margin: 0; font-size: 13px; opacity: 0.9; }
        
        .chat-container { display: flex; flex-direction: column; height: 500px; background: #fdfbfb; }
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
        .btn-ai-glow { background: #8e44ad; color: white; border: none; padding: 10px 15px; border-radius: 20px; cursor: pointer; }
        .btn-ai-glow:disabled { background: #ccc; cursor: not-allowed; }

        #aiLoadingOverlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.85); z-index: 99999; color: white; flex-direction: column; justify-content: center; align-items: center; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .ai-spinner { border: 6px solid #f3f3f3; border-top: 6px solid #8e44ad; border-radius: 50%; width: 60px; height: 60px; animation: spin 1s linear infinite; margin-bottom: 20px; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
    </style>
</head>
<body>

    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <div class="top-header" style="display:flex; justify-content:space-between; align-items:center; background:#fff; padding:15px 20px; border-radius:8px; margin-bottom:20px; box-shadow:0 2px 5px rgba(0,0,0,0.05);">
            <div class="greeting-box">
                <h2 style="margin:0;">Supervisor Evaluation</h2>
                <div class="date-box" style="opacity:0.7; font-size:14px;"><?php echo htmlspecialchars($sub['fullname']); ?></div>
            </div>
            <button id="themeToggle" class="theme-toggle" style="background:#ecf0f1; border:none; padding:8px 15px; border-radius:20px; cursor:pointer;"><i class="fas fa-moon"></i> Dark Mode</button>
        </div>

        <div class="eval-grid">
            <div class="context-card">
                <h3 style="margin-top:0; border-bottom:1px solid #eee; padding-bottom:15px;">
                    <i class="fas fa-file-alt"></i> Submission
                </h3>
                <input type="hidden" id="studentContext" value="<?php echo htmlspecialchars($sub['description']); ?>">
                
                <div class="modern-input-group">
                    <div class="modern-label">Title</div>
                    <div style="font-weight:bold;"><?php echo htmlspecialchars($sub['title']); ?></div>
                </div>
                <div class="modern-input-group">
                    <div class="modern-label">Context</div>
                    <div class="context-box" style="background:#f9f9f9; padding:10px; border-radius:4px; font-size:14px;"><?php echo nl2br(htmlspecialchars($sub['description'])); ?></div>
                </div>
                <a href="<?php echo $sub['file_path']; ?>" target="_blank" class="btn btn-view" style="display:block; text-align:center; padding:10px; background:#2c3e50; color:white; text-decoration:none; border-radius:4px;">Download File</a>
            </div>

            <div>
                <form method="POST" enctype="multipart/form-data" style="margin-bottom:30px;">
                    <div class="eval-card">
                        <div class="ai-header-banner" style="background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);">
                            <div class="ai-header-content"><h3><i class="fas fa-magic"></i> AI Auto-Evaluate</h3><p>Generate score & report.</p></div>
                            <button type="submit" name="generate_ai" class="btn-ai-glow" style="background:#3498db;">Analyze</button>
                        </div>
                        <div class="eval-body" style="padding:20px;">
                            <div class="modern-input-group"><label class="modern-label">Evaluation Title</label><input type="text" name="eval_title" class="modern-input" value="Eval: <?php echo htmlspecialchars($sub['title']); ?>" required></div>
                            <div class="modern-input-group"><label class="modern-label">Score (1-10)</label>
                                <div class="score-wrapper"><input type="number" name="score" class="modern-input" min="1" max="10" value="<?php echo htmlspecialchars($suggested_score); ?>" required>
                                <div style="font-size:13px; opacity:0.7; margin-top:5px;"><?php if($suggested_score) echo "<span style='color:green; font-weight:bold;'>AI Suggestion Applied</span><br>"; ?> 1-4: Developing | 8-10: Distinguished</div></div>
                            </div>
                            <div class="modern-input-group"><label class="modern-label">Feedback</label><textarea name="notes" class="modern-textarea" rows="8"><?php echo htmlspecialchars($ai_suggestion); ?></textarea></div>
                            <div class="action-footer"><button type="submit" name="submit_grade" class="btn-submit-premium">Submit Final Grade</button></div>
                        </div>
                    </div>
                </form>

                <div class="eval-card" style="border:2px solid #8e44ad; margin-top:20px;">
                    <div class="ai-header-banner" style="background: linear-gradient(135deg, #8e44ad 0%, #9b59b6 100%);">
                        <div class="ai-header-content"><h3><i class="fas fa-robot"></i> Consultant Chat</h3><p>Ask about standards or the file.</p></div>
                    </div>
                    <div class="chat-container">
                        <div class="chat-history" id="chatHistory">
                            <?php if ($chat_history->num_rows > 0): while($chat = $chat_history->fetch_assoc()): ?>
                                <div class="message <?php echo $chat['sender']; ?>">
                                    <div class="sender-name"><?php echo ($chat['sender'] == 'user') ? 'You' : 'Consultant'; ?></div>
                                    <div class="bubble"><?php echo nl2br(htmlspecialchars($chat['message'])); ?></div>
                                </div>
                            <?php endwhile; else: ?>
                                <div class="message ai"><div class="sender-name">Consultant</div><div class="bubble">Hello Supervisor. I can read the attached description. How can I help?</div></div>
                            <?php endif; ?>
                        </div>
                        <div class="typing-indicator" id="typingIndicator"><i class="fas fa-circle-notch fa-spin"></i> Consulting...</div>
                        
                        <form id="chatForm" onsubmit="event.preventDefault(); sendMessage();" style="margin:0;">
                            <div class="chat-input-area">
                                <input type="text" id="chatInput" class="modern-input" placeholder="Ask a question..." required style="margin-bottom:0; border-radius:20px;">
                                <button type="submit" id="sendChatBtn" class="btn-ai-glow" style="border-radius:50%;"><i class="fas fa-paper-plane"></i></button>
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

    <script src="js/script.js?v=<?php echo time(); ?>"></script>
    <script>
        const chatHistory = document.getElementById('chatHistory');
        const chatInput = document.getElementById('chatInput');
        const sendBtn = document.getElementById('sendChatBtn');
        const studentContext = document.getElementById('studentContext');
        const typingIndicator = document.getElementById('typingIndicator');

        if(chatHistory) { chatHistory.scrollTop = chatHistory.scrollHeight; }

        function sendMessage() {
            const message = chatInput.value.trim();
            if (message === "") return;

            // Lock Input
            chatInput.disabled = true;
            sendBtn.disabled = true;

            addMessageToUI('You', message, 'user');
            chatInput.value = '';
            typingIndicator.style.display = 'block';

            const payload = {
                message: message,
                context: "STUDENT SUBMISSION:\n" + (studentContext ? studentContext.value : ""),
                mode: 'consultant'
            };

            fetch('api_chat.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify(payload) 
            })
            .then(response => response.text())
            .then(text => {
                try {
                    const data = JSON.parse(text);
                    if(data.error) { addMessageToUI('System Error', data.error, 'ai'); } 
                    else if(data.reply) { addMessageToUI('Consultant', data.reply, 'ai'); }
                } catch (e) {
                    addMessageToUI('System Error', 'Server blocked the request or session expired.', 'ai');
                }
            })
            .catch(error => { addMessageToUI('System Error', 'Connection Error.', 'ai'); })
            .finally(() => {
                typingIndicator.style.display = 'none';
                chatInput.disabled = false;
                sendBtn.disabled = false;
                chatInput.focus();
            });
        }

        function addMessageToUI(sender, text, type) {
            const msgDiv = document.createElement('div');
            msgDiv.classList.add('message', type);
            msgDiv.innerHTML = `<div class="sender-name">${sender}</div><div class="bubble">${text.replace(/\n/g, "<br>")}</div>`;
            chatHistory.appendChild(msgDiv);
            chatHistory.scrollTop = chatHistory.scrollHeight;
        }

        document.addEventListener('submit', function(e) {
            if (e.submitter && (e.submitter.name === 'generate_ai' || e.submitter.name === 'submit_grade')) {
                document.getElementById('aiLoadingOverlay').style.display = 'flex';
            }
        });
    </script>
</body>
</html>
