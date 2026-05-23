<?php
session_start();
include __DIR__ . '/db_connect.php';
include __DIR__ . '/ai_helper.php';

function extractTextForEvaluation($filePath) {
    // A. Verify File Existence
    if (!file_exists($filePath)) {
        return "[SYSTEM ERROR: File not found. Tried path: " . $filePath . "]";
    }

    $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    $text = "";

    // B. Handle DOCX
    if ($ext === 'docx') {
        if (class_exists('ZipArchive')) {
            $zip = new ZipArchive;
            if ($zip->open($filePath) === TRUE) {
                if (($index = $zip->locateName('word/document.xml')) !== false) {
                    $xml = $zip->getFromIndex($index);
                    $dom = new DOMDocument;
                    $dom->loadXML($xml, LIBXML_NOENT | LIBXML_XINCLUDE | LIBXML_NOERROR | LIBXML_NOWARNING);
                    $text = strip_tags($dom->saveXML());
                } else {
                    $text = "[SYSTEM ERROR: DOCX opened, but 'word/document.xml' was missing. File may be empty.]";
                }
                $zip->close();
            } else {
                $text = "[SYSTEM ERROR: Could not unzip DOCX. File may be corrupted.]";
            }
        } else {
            $text = "[SYSTEM ERROR: ZipArchive PHP extension missing.]";
        }
    } 
    // C. Handle TXT
    elseif ($ext === 'txt') {
        $text = file_get_contents($filePath);
    }
    // D. Handle Unsupported
    else {
        $text = "[SYSTEM NOTE: File type .$ext not supported for auto-read. Please read manually.]";
    }

    return substr(trim($text), 0, 15000);
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') { header("Location: index.php"); exit(); }

$admin_id = $_SESSION['user_id'];
$sub_id = isset($_GET['sub_id']) ? intval($_GET['sub_id']) : 0;
$ai_suggestion = "";
$suggested_score = "";

// Fetch Submission
$sub_q = $conn->query("SELECT s.*, u.fullname FROM submissions s JOIN users u ON s.user_id = u.id WHERE s.id=$sub_id");
if($sub_q->num_rows == 0) { header("Location: admin_dashboard.php"); exit(); }
$sub = $sub_q->fetch_assoc();

// 2. HANDLE FORM SUBMISSION
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    if (isset($_POST['generate_ai'])) {
        
        $context = "Title: " . $sub['title'] . "\nStudent Context/Description: " . $sub['description'];
        
        $file_content_msg = "";
        
        if (!empty($sub['file_path'])) {
            $raw_path = $sub['file_path'];
            
            $clean_rel_path = ltrim(str_replace(['../', '..\\'], '', $raw_path), '/\\');
            
            $target_full_path = __DIR__ . '/' . $clean_rel_path;

            if (!file_exists($target_full_path)) {
                 $target_full_path = realpath($clean_rel_path); 
            }
            
            $extracted_text = extractTextForEvaluation($target_full_path);
            
            $file_content_msg = "\n\n=== [SYSTEM EVIDENCE FILE] ===\n";
            $file_content_msg .= "File Name: " . basename($raw_path) . "\n";
            $file_content_msg .= "Content Extraction: \n" . $extracted_text;
            $file_content_msg .= "\n==============================\n";
        } else {
            $file_content_msg = "\n[SYSTEM: No file was attached to this submission.]\n";
        }

        $full_prompt = "You are an evaluator. Grade this submission based on the Context and the Evidence File provided below.\n\n" . $context . $file_content_msg;
        
        $raw_ai_text = generateAIResponse($full_prompt, 'evaluator');
        
        if (preg_match('/\[SCORE:\s*(\d+)\]/', $raw_ai_text, $matches)) {
            $suggested_score = $matches[1]; 
            $ai_suggestion = trim(str_replace($matches[0], "", $raw_ai_text));
        } else { 
            $ai_suggestion = $raw_ai_text; 
        }

    // --- SUBMIT FINAL GRADE ---
    } elseif (isset($_POST['submit_grade'])) {
        $eval_title = $_POST['eval_title']; 
        $score = $_POST['score']; 
        $notes = $_POST['notes']; 
        $student_id = $sub['user_id'];
        $target_file = $sub['file_path'];

        if (!empty($_FILES['file']['name'])) {
            $target_dir = "uploads/";
            $target_file = $target_dir . "admin_" . time() . "_" . basename($_FILES["file"]["name"]);
            move_uploaded_file($_FILES["file"]["tmp_name"], $target_file);
        }

        $stmt = $conn->prepare("INSERT INTO evaluations (user_id, evaluator_id, submission_id, evaluation_title, competency_score, readiness_notes, file_path, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')");
        $stmt->bind_param("iiisiss", $student_id, $admin_id, $sub_id, $eval_title, $score, $notes, $target_file);
        
        if($stmt->execute()) {
            $new_eval_id = $conn->insert_id;
            $conn->query("UPDATE submissions SET status='evaluated' WHERE id=$sub_id");
            header("Location: admin_view_evaluation.php?id=$new_eval_id"); 
            exit();
        }
    }
}

$chat_history = $conn->query("SELECT * FROM chat_logs WHERE user_id=$admin_id ORDER BY created_at ASC");
?>

<!DOCTYPE html>
<html>
<head>
    <title>Admin Evaluation</title>
    <link rel="stylesheet" href="css/style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .chat-container { display: flex; flex-direction: column; height: 400px; background: #fdfbfb; border-radius: 0 0 16px 16px; border:1px solid #ddd; border-top:none; }
        .chat-history { flex: 1; overflow-y: auto; padding: 15px; border-bottom: 1px solid #eee; }
        .chat-input-area { padding: 10px; background: #fff; display: flex; gap: 10px; border-radius: 0 0 16px 16px; }
        .message { margin-bottom: 10px; display: flex; flex-direction: column; }
        .message.user { align-items: flex-end; }
        .message.ai { align-items: flex-start; }
        .bubble { max-width: 85%; padding: 10px 14px; border-radius: 12px; font-size: 13px; line-height: 1.4; }
        .message.user .bubble { background: #3498db; color: white; border-bottom-right-radius: 2px; }
        .message.ai .bubble { background: #ecf0f1; color: #2c3e50; border-bottom-left-radius: 2px; }
        .sender-name { font-size: 10px; margin-bottom: 2px; opacity: 0.6; }
        .typing-indicator { display: none; padding: 10px; font-style: italic; color: #888; font-size: 12px; }
    </style>
</head>
<body>
<?php include 'sidebar.php'; ?>

    <div class="main-content">
        <div class="top-header">
            <div class="greeting-box">
                <h2>Admin Evaluation</h2>
                <div class="date-box"><?php echo htmlspecialchars($sub['fullname']); ?></div>
            </div>
            <button id="themeToggle" class="theme-toggle"><i class="fas fa-moon"></i> Dark Mode</button>
        </div>

        <div class="eval-grid">
            
            <div class="context-card">
                <h3 style="margin-top:0; border-bottom:1px solid var(--border-color); padding-bottom:15px;">
                    <i class="fas fa-user-graduate"></i> Submission
                </h3>
                
                <input type="hidden" id="studentContext" value="<?php echo htmlspecialchars($sub['description']); ?>">
                <input type="hidden" id="studentFilePath" value="<?php echo htmlspecialchars($sub['file_path']); ?>">
                
                <div class="modern-input-group">
                    <div class="context-label">Title</div>
                    <div style="font-weight:bold;"><?php echo htmlspecialchars($sub['title']); ?></div>
                </div>
                <div class="modern-input-group">
                    <div class="context-label">Context</div>
                    <div class="context-box"><?php echo nl2br(htmlspecialchars($sub['description'])); ?></div>
                </div>
                <a href="<?php echo $sub['file_path']; ?>" target="_blank" class="btn btn-view" style="display:block; text-align:center;">View Evidence File</a>
            </div>

            <div>
                <form method="POST" enctype="multipart/form-data" style="margin-bottom:30px;">
                    <div class="eval-card">
                        <div class="ai-header-banner">
                            <div class="ai-header-content"><h3><i class="fas fa-magic"></i> AI Auto-Evaluate</h3><p>Generate score & report.</p></div>
                            <button type="submit" name="generate_ai" class="btn-ai-glow">Analyze</button>
                        </div>
                        <div class="eval-body">
                            <div class="modern-input-group"><label class="modern-label">Evaluation Title</label><input type="text" name="eval_title" class="modern-input" value="Admin Eval: <?php echo htmlspecialchars($sub['title']); ?>" required></div>
                            <div class="modern-input-group"><label class="modern-label">Score (1-10)</label>
                                <div class="score-wrapper"><input type="number" name="score" class="score-circle-input" min="1" max="10" value="<?php echo htmlspecialchars($suggested_score); ?>" required>
                                <div style="font-size:13px; opacity:0.7;"><?php if($suggested_score) echo "<span style='color:green; font-weight:bold;'>AI Suggestion Applied</span><br>"; ?> 1-4: Developing | 8-10: Distinguished</div></div>
                            </div>
                            <div class="modern-input-group"><label class="modern-label">Notes</label><textarea name="notes" class="modern-textarea" rows="6"><?php echo htmlspecialchars($ai_suggestion); ?></textarea></div>
                            <div class="action-footer"><button type="submit" name="submit_grade" class="btn-submit-premium">Submit Final Grade</button></div>
                        </div>
                    </div>
                </form>

                <div class="eval-card" style="border:2px solid #3498db;">
                    <div class="ai-header-banner" style="background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);">
                        <div class="ai-header-content"><h3><i class="fas fa-robot"></i> Consultant Chat</h3><p>Ask about standards or the student file.</p></div>
                    </div>
                    <div class="chat-container">
                        <div class="chat-history" id="chatHistory">
                            <?php if ($chat_history->num_rows > 0): while($chat = $chat_history->fetch_assoc()): ?>
                                <div class="message <?php echo $chat['sender']; ?>">
                                    <div class="sender-name"><?php echo ($chat['sender'] == 'user') ? 'You' : 'Consultant'; ?></div>
                                    <div class="bubble"><?php echo nl2br(htmlspecialchars($chat['message'])); ?></div>
                                </div>
                            <?php endwhile; else: ?>
                                <div class="message ai"><div class="bubble">Hello Admin. I can read the student's attached file and description. How can I help?</div></div>
                            <?php endif; ?>
                        </div>
                        <div class="typing-indicator" id="typingIndicator"><i class="fas fa-circle-notch fa-spin"></i> Consulting...</div>
                        <div style="padding:0 10px; background:#f9f9f9; border-top:1px solid #eee;">
                            <input type="file" id="chatFile" style="font-size:11px; padding:5px;">
                        </div>
                        <div class="chat-input-area">
                            <input type="text" id="chatInput" class="modern-input" placeholder="Ask a question..." style="margin-bottom:0; border-radius:20px;">
                            <button type="button" onclick="sendMessage()" class="btn-ai-glow" style="background:#3498db; color:white; width:auto; border-radius:50%; padding:12px;"><i class="fas fa-paper-plane"></i></button>
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
        const studentContext = document.getElementById('studentContext').value;
        const studentFilePath = document.getElementById('studentFilePath').value; 
        const typingIndicator = document.getElementById('typingIndicator');
        const chatFile = document.getElementById('chatFile');

        chatHistory.scrollTop = chatHistory.scrollHeight;

        chatInput.addEventListener("keypress", function(e) { if (e.key === "Enter") { e.preventDefault(); sendMessage(); }});

        function sendMessage() {
            const message = chatInput.value.trim();
            if (message === "") return;

            addMessageToUI('You', message, 'user');
            chatInput.value = '';
            typingIndicator.style.display = 'block';
            chatHistory.scrollTop = chatHistory.scrollHeight;

            const formData = new FormData();
            formData.append('message', message);
            formData.append('context', "STUDENT SUBMISSION:\n" + studentContext);
            formData.append('mode', 'consultant'); 
            formData.append('student_file_path', studentFilePath); 

            if (chatFile.files.length > 0) {
                formData.append('chat_file', chatFile.files[0]);
                addMessageToUI('System', '📎 Analyzing your attached file...', 'ai');
                chatFile.value = ''; 
            }

            fetch('api_chat.php', { method: 'POST', body: formData })
            .then(response => response.json())
            .then(data => {
                typingIndicator.style.display = 'none';
                addMessageToUI('Consultant', data.reply, 'ai');
            })
            .catch(error => {
                typingIndicator.style.display = 'none';
                addMessageToUI('System', 'Connection Error', 'ai');
            });
        }

        function addMessageToUI(sender, text, type) {
            const msgDiv = document.createElement('div');
            msgDiv.classList.add('message', type);
            const nameDiv = document.createElement('div');
            nameDiv.className = 'sender-name'; nameDiv.innerText = sender;
            const bubbleDiv = document.createElement('div');
            bubbleDiv.className = 'bubble'; bubbleDiv.innerHTML = text.replace(/\n/g, "<br>");
            msgDiv.appendChild(nameDiv); msgDiv.appendChild(bubbleDiv);
            chatHistory.appendChild(msgDiv);
            chatHistory.scrollTop = chatHistory.scrollHeight;
        }
    </script>
    
    <div id="aiLoadingOverlay">
    <div class="ai-spinner"></div>
    <h2 style="margin:0; color:white;">AI is Processing...</h2>
    <p style="color:#aaa;">Please wait while the Copilot analyzes the document. Do not refresh.</p>
</div>

<script>
    // Listen for ANY form submission on the page
    document.addEventListener('submit', function(e) {
        // If the button clicked was the "Analyze" or "Submit Portfolio" button
        if (e.submitter && (e.submitter.name === 'generate_ai' || e.submitter.name === 'upload_file')) {
            document.getElementById('aiLoadingOverlay').style.display = 'flex';
        }
    });
</script>
</body>
</html>