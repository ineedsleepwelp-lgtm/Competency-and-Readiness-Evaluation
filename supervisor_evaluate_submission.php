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
    } elseif ($ext === 'pdf') {
        return "[SYSTEM ALERT: PDF uploaded. AI cannot natively read PDFs. Review manually.]";
    } else {
        $text = "[SYSTEM NOTE: File type .$ext not supported for auto-read.]";
    }
    return substr(trim($text), 0, 15000);
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') { header("Location: index.php"); exit(); }

$admin_id = $_SESSION['user_id'];
$sub_id = isset($_GET['sub_id']) ? intval($_GET['sub_id']) : 0;

$sub_q = $conn->query("SELECT s.*, u.fullname FROM submissions s JOIN users u ON s.user_id = u.id WHERE s.id=$sub_id");
if($sub_q->num_rows == 0) { header("Location: admin_dashboard.php"); exit(); }
$sub = $sub_q->fetch_assoc();

// Default Empty AI Rubric Values
$ai_scores = ['obj' => '', 'con' => '', 'meth' => '', 'ass' => '', 'fmt' => '', 'total' => ''];
$ai_feedback = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['generate_ai'])) {
        $context = "Title: " . $sub['title'] . "\nStudent Context: " . $sub['description'];
        $file_content_msg = "";
        
        if (!empty($sub['file_path'])) {
            $raw_path = $sub['file_path'];
            $clean_rel_path = ltrim(str_replace(['../', '..\\'], '', $raw_path), '/\\');
            $target_full_path = __DIR__ . '/' . $clean_rel_path;
            if (!file_exists($target_full_path)) { $target_full_path = realpath($clean_rel_path); }
            $extracted_text = extractTextForEvaluation($target_full_path);
            $file_content_msg = "\n\n=== [EVIDENCE FILE] ===\n" . $extracted_text . "\n==============================\n";
        }
        
        // STRICT JSON RUBRIC PROMPT
        $full_prompt = "You are a master curriculum evaluator grading a lesson plan. Use this exact 100-point rubric:
        1. Objectives (Max 20) - clear, measurable HOTS.
        2. Content (Max 20) - accurate, relevant.
        3. Methodology (Max 30) - engaging, well-structured.
        4. Assessment (Max 20) - aligns with objectives.
        5. Formatting (Max 10) - professional standard.
        
        Student Work: " . $context . $file_content_msg . "
        
        RETURN ONLY VALID JSON EXACTLY LIKE THIS FORMAT:
        {
            \"obj\": 18,
            \"con\": 15,
            \"meth\": 25,
            \"ass\": 15,
            \"fmt\": 10,
            \"total\": 83,
            \"feedback\": \"Detailed feedback...\"
        }";

        $raw_ai_text = generateAIResponse($full_prompt, 'evaluator');
        $raw_ai_text = preg_replace('/```json|```/', '', $raw_ai_text); // Clean markdown
        $parsed = json_decode(trim($raw_ai_text), true);

        if ($parsed && isset($parsed['total'])) {
            $ai_scores = $parsed;
            $ai_feedback = $parsed['feedback'];
        } else {
            $ai_feedback = "AI failed to return proper math. Raw output: " . $raw_ai_text;
        }

    } elseif (isset($_POST['submit_grade'])) {
        $eval_title = $_POST['eval_title']; 
        $score = $_POST['human_total']; 
        
        // Combine rubric scores and feedback into the notes for DB storage
        $notes = "RUBRIC BREAKDOWN:\nObjectives: " . $_POST['human_obj'] . "/20\nContent: " . $_POST['human_con'] . "/20\nMethodology: " . $_POST['human_meth'] . "/30\nAssessment: " . $_POST['human_ass'] . "/20\nFormatting: " . $_POST['human_fmt'] . "/10\n\nFEEDBACK:\n" . $_POST['notes']; 
        
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
$file_url = htmlspecialchars($sub['file_path']);
$actual_path = __DIR__ . '/' . ltrim(str_replace(['../', '..\\'], '', $sub['file_path']), '/\\');
$file_is_missing = !empty($sub['file_path']) && !file_exists($actual_path);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Administrator Evaluation</title>
    <link rel="stylesheet" href="css/style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { display: flex; height: 100vh; overflow: hidden; margin: 0; background: #f4f7f6; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .main-content { flex: 1; padding: 30px; overflow-y: auto; height: 100vh; box-sizing: border-box; }
        .eval-grid { display: flex; flex-wrap: wrap; gap: 25px; margin-top: 20px; align-items: flex-start; }
        .column-left { flex: 1; min-width: 500px; display: flex; flex-direction: column; gap: 20px; }
        .column-right { width: 380px; flex-shrink: 0; }
        .card { background: #fff; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); padding: 25px; }
        .eval-card { background: #fff; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); overflow: hidden; }
        .ai-header-banner { padding: 20px; color: white; display: flex; justify-content: space-between; align-items: center; }
        
        /* DUAL RUBRIC CSS */
        .dual-rubric { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px; }
        .rubric-side { background: #f8f9fa; border: 1px solid #e2e8f0; border-radius: 8px; padding: 15px; }
        .rubric-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; font-size: 13px; }
        .rubric-input { width: 60px; padding: 6px; border: 1px solid #ccc; border-radius: 4px; text-align: center; font-weight: bold; }
        .ai-input { background: #eef2f5; color: #7f8c8d; border-color: #d1d8e0; }
        .rubric-total { font-size: 18px; font-weight: bold; text-align: right; padding-top: 10px; border-top: 2px solid #ddd; margin-top: 10px; }
        
        .chat-container { display: flex; flex-direction: column; height: 600px; background: #fdfbfb; }
        .chat-history { flex: 1; overflow-y: auto; padding: 20px; border-bottom: 1px solid #eee; }
        .chat-input-area { padding: 15px; background: #fff; display: flex; gap: 10px; align-items: center; border-top: 1px solid #eee; }
        .message { margin-bottom: 15px; display: flex; flex-direction: column; }
        .message.user { align-items: flex-end; }
        .message.ai { align-items: flex-start; }
        .bubble { max-width: 85%; padding: 12px 16px; border-radius: 12px; font-size: 14px; line-height: 1.5; }
        .message.user .bubble { background: linear-gradient(135deg, #3498db 0%, #2980b9 100%); color: white; border-bottom-right-radius: 2px; }
        .message.ai .bubble { background: #e9ecef; color: #333; border-bottom-left-radius: 2px; }
        .sender-name { font-size: 11px; margin-bottom: 4px; opacity: 0.6; }
        .modern-input-group { margin-bottom: 15px; }
        .modern-label { display: block; font-weight: bold; margin-bottom: 5px; font-size: 13px; color: #555; }
        .modern-textarea { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; font-family: inherit; }
        .btn-submit-premium { background: #3498db; color: white; border: none; padding: 12px; border-radius: 4px; cursor: pointer; font-weight: bold; width: 100%; }
        .btn-ai-glow { background: #3498db; color: white; border: none; padding: 10px 15px; border-radius: 50%; cursor: pointer; }
        
        #aiLoadingOverlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.85); z-index: 99999; color: white; flex-direction: column; justify-content: center; align-items: center; }
        .ai-spinner { border: 6px solid #f3f3f3; border-top: 6px solid #3498db; border-radius: 50%; width: 60px; height: 60px; animation: spin 1s linear infinite; margin-bottom: 20px; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
    </style>
</head>
<body>

    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <div class="top-header" style="display:flex; justify-content:space-between; align-items:center; background:#fff; padding:15px 20px; border-radius:8px; margin-bottom:20px; box-shadow:0 2px 5px rgba(0,0,0,0.05);">
            <div class="greeting-box">
                <h2 style="margin:0;">Administrator Evaluation</h2>
                <div class="date-box" style="opacity:0.7; font-size:14px;"><?php echo htmlspecialchars($sub['fullname']); ?></div>
            </div>
            <button id="themeToggle" class="theme-toggle" style="background:#ecf0f1; border:none; padding:8px 15px; border-radius:20px; cursor:pointer;"><i class="fas fa-moon"></i> Dark Mode</button>
        </div>

        <div class="eval-grid">
            <div class="column-left">
                <div class="card">
                    <h3 style="margin-top:0; border-bottom:1px solid #eee; padding-bottom:15px;"><i class="fas fa-user-graduate"></i> Submission Context</h3>
                    <input type="hidden" id="studentContext" value="<?php echo htmlspecialchars($sub['description']); ?>">
                    <div style="font-weight:bold; font-size:16px; margin-bottom:10px;"><?php echo htmlspecialchars($sub['title']); ?></div>
                    <div style="background:#f9f9f9; padding:10px; border-radius:4px; font-size:14px; margin-bottom:15px;"><?php echo nl2br(htmlspecialchars($sub['description'])); ?></div>
                    
                    <?php if (empty($sub['file_path'])): ?>
                        <div style="padding:15px; background:#f8f9fa; text-align:center; border-radius:6px; border:1px dashed #ccc; color:#6c757d;">No file attached.</div>
                    <?php else: ?>
                        <a href="<?php echo $file_url; ?>" download class="btn-submit-premium" style="display:block; text-align:center; text-decoration:none; background:#2c3e50;"><i class="fas fa-download"></i> Download Evidence File</a>
                    <?php endif; ?>
                </div>

                <form method="POST" enctype="multipart/form-data" id="mainForm">
                    <div class="eval-card">
                        <div class="ai-header-banner" style="background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);">
                            <div class="ai-header-content"><h3 style="margin:0;"><i class="fas fa-magic"></i> AI 100-Point Auto-Evaluate</h3></div>
                            <button type="submit" name="generate_ai" class="btn-ai-glow" style="border-radius: 4px; background:#3498db; padding: 8px 15px;">Run AI Analysis</button>
                        </div>
                        
                        <div class="eval-body" style="padding:25px;">
                            <input type="hidden" name="eval_title" value="Admin Official Evaluation">
                            
                            <div class="dual-rubric">
                                <div class="rubric-side">
                                    <h4 style="margin-top:0; color:#2c3e50; border-bottom:1px solid #ccc; padding-bottom:5px;">AI Suggested Score</h4>
                                    <div class="rubric-row"><span>1. Objectives (20)</span> <input type="text" class="rubric-input ai-input" id="ai_obj" value="<?= $ai_scores['obj'] ?>" readonly></div>
                                    <div class="rubric-row"><span>2. Content (20)</span> <input type="text" class="rubric-input ai-input" id="ai_con" value="<?= $ai_scores['con'] ?>" readonly></div>
                                    <div class="rubric-row"><span>3. Methodology (30)</span> <input type="text" class="rubric-input ai-input" id="ai_meth" value="<?= $ai_scores['meth'] ?>" readonly></div>
                                    <div class="rubric-row"><span>4. Assessment (20)</span> <input type="text" class="rubric-input ai-input" id="ai_ass" value="<?= $ai_scores['ass'] ?>" readonly></div>
                                    <div class="rubric-row"><span>5. Formatting (10)</span> <input type="text" class="rubric-input ai-input" id="ai_fmt" value="<?= $ai_scores['fmt'] ?>" readonly></div>
                                    <div class="rubric-total" style="color:#2980b9;">AI Total: <span id="ai_total"><?= empty($ai_scores['total']) ? '0' : $ai_scores['total'] ?></span>/100</div>
                                </div>

                                <div class="rubric-side" style="border-color:#3498db; background:#f0f8ff;">
                                    <h4 style="margin-top:0; color:#2980b9; border-bottom:1px solid #3498db; padding-bottom:5px;">Administrator Official</h4>
                                    <div class="rubric-row"><span>1. Objectives (20)</span> <input type="number" name="human_obj" class="rubric-input human-calc" id="h_obj" max="20" required></div>
                                    <div class="rubric-row"><span>2. Content (20)</span> <input type="number" name="human_con" class="rubric-input human-calc" id="h_con" max="20" required></div>
                                    <div class="rubric-row"><span>3. Methodology (30)</span> <input type="number" name="human_meth" class="rubric-input human-calc" id="h_meth" max="30" required></div>
                                    <div class="rubric-row"><span>4. Assessment (20)</span> <input type="number" name="human_ass" class="rubric-input human-calc" id="h_ass" max="20" required></div>
                                    <div class="rubric-row"><span>5. Formatting (10)</span> <input type="number" name="human_fmt" class="rubric-input human-calc" id="h_fmt" max="10" required></div>
                                    <input type="hidden" name="human_total" id="h_total_input">
                                    <div class="rubric-total" style="color:#27ae60;">Official Total: <span id="h_total_display">0</span>/100</div>
                                    <button type="button" onclick="copyAIScores()" style="width:100%; padding:5px; margin-top:10px; background:#bdc3c7; border:none; border-radius:4px; cursor:pointer;"><i class="fas fa-arrow-right"></i> Copy AI Scores</button>
                                </div>
                            </div>

                            <div class="modern-input-group">
                                <label class="modern-label">Detailed Feedback</label>
                                <textarea name="notes" id="feedback_box" class="modern-textarea" rows="8"><?php echo htmlspecialchars($ai_feedback); ?></textarea>
                            </div>
                            <button type="submit" name="submit_grade" class="btn-submit-premium">Submit Official 100-Point Grade</button>
                        </div>
                    </div>
                </form>
            </div>

            <div class="column-right">
                <div class="eval-card" style="border:2px solid #3498db;">
                    <div class="ai-header-banner" style="background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);">
                        <div class="ai-header-content"><h3 style="margin:0;"><i class="fas fa-robot"></i> Consultant Chat</h3></div>
                    </div>
                    <div class="chat-container">
                        <div class="chat-history" id="chatHistory">
                            <?php if ($chat_history->num_rows > 0): while($chat = $chat_history->fetch_assoc()): ?>
                                <div class="message <?php echo $chat['sender']; ?>">
                                    <div class="sender-name"><?php echo ($chat['sender'] == 'user') ? 'You' : 'Consultant'; ?></div>
                                    <div class="bubble"><?php echo nl2br(htmlspecialchars($chat['message'])); ?></div>
                                </div>
                            <?php endwhile; else: ?>
                                <div class="message ai"><div class="sender-name">Consultant</div><div class="bubble">Hello Admin. I can read the student's context description. How can I help?</div></div>
                            <?php endif; ?>
                        </div>
                        <div class="typing-indicator" id="typingIndicator"><i class="fas fa-circle-notch fa-spin"></i> Consulting...</div>
                        
                        <div class="chat-input-area">
                            <input type="text" id="chatInput" class="modern-input" placeholder="Ask a question..." required style="margin-bottom:0; border-radius:20px;">
                            <button type="button" id="sendChatBtn" onclick="sendMessage()" class="btn-ai-glow" style="background:#3498db;"><i class="fas fa-paper-plane"></i></button>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <div id="aiLoadingOverlay">
        <div class="ai-spinner"></div>
        <h2>AI is Calculating 100-Point Rubric...</h2>
    </div>

    <script>
        // Rubric Auto-Calculator Math
        const humanInputs = document.querySelectorAll('.human-calc');
        const hTotalDisplay = document.getElementById('h_total_display');
        const hTotalInput = document.getElementById('h_total_input');

        function calculateTotal() {
            let total = 0;
            humanInputs.forEach(input => {
                total += Number(input.value) || 0;
            });
            hTotalDisplay.innerText = total;
            hTotalInput.value = total;
        }

        humanInputs.forEach(input => {
            input.addEventListener('input', calculateTotal);
        });

        function copyAIScores() {
            document.getElementById('h_obj').value = document.getElementById('ai_obj').value;
            document.getElementById('h_con').value = document.getElementById('ai_con').value;
            document.getElementById('h_meth').value = document.getElementById('ai_meth').value;
            document.getElementById('h_ass').value = document.getElementById('ai_ass').value;
            document.getElementById('h_fmt').value = document.getElementById('ai_fmt').value;
            calculateTotal();
        }

        // Chat Script
        const chatHistory = document.getElementById('chatHistory');
        const chatInput = document.getElementById('chatInput');
        const sendBtn = document.getElementById('sendChatBtn');
        const studentContext = document.getElementById('studentContext');
        const typingIndicator = document.getElementById('typingIndicator');

        if(chatHistory) { chatHistory.scrollTop = chatHistory.scrollHeight; }

        if(chatInput) {
            chatInput.addEventListener("keydown", function(event) {
                if (event.key === "Enter") { 
                    event.preventDefault(); 
                    sendMessage(); 
                    return false;
                }
            });
        }

        function sendMessage() {
            const message = chatInput.value.trim();
            if (message === "") return;

            chatInput.disabled = true; sendBtn.disabled = true;

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
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload) 
            })
            .then(response => response.json())
            .then(data => {
                if(data.error) { addMessageToUI('System Error', data.error, 'ai'); } 
                else if(data.reply) { addMessageToUI('Consultant', data.reply, 'ai'); }
            })
            .catch(error => { addMessageToUI('System Error', 'Connection Error.', 'ai'); })
            .finally(() => {
                typingIndicator.style.display = 'none';
                chatInput.disabled = false; sendBtn.disabled = false;
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
            if (e.target.id === 'mainForm' && e.submitter.name === 'generate_ai') {
                document.getElementById('aiLoadingOverlay').style.display = 'flex';
            }
        });
    </script>
</body>
</html>
</html>
