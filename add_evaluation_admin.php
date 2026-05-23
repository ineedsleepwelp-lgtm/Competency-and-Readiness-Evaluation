<?php
session_start();
include __DIR__ . '/db_connect.php';
include __DIR__ . '/ai_helper.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') { header("Location: index.php"); exit(); }

$admin_id = $_SESSION['user_id'];

// Fetch All Students
$students = $conn->query("SELECT * FROM users WHERE role='student_teacher' ORDER BY fullname ASC");

// Handle Form Submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_grade'])) {
    $student_id = intval($_POST['student_id']);
    $eval_title = $_POST['eval_title'];
    $score = $_POST['score'];
    $notes = $_POST['notes'];
    
    // Insert with submission_id = 0 (Direct Observation)
    $stmt = $conn->prepare("INSERT INTO evaluations (user_id, evaluator_id, submission_id, evaluation_title, competency_score, readiness_notes, status, upload_date) VALUES (?, ?, 0, ?, ?, ?, 'accepted', NOW())");
    $stmt->bind_param("iisss", $student_id, $admin_id, $eval_title, $score, $notes);
    
    if($stmt->execute()) {
        $new_eval_id = $conn->insert_id;
        header("Location: admin_view_evaluation.php?id=$new_eval_id");
        exit();
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Add Manual Evaluation</title>
    <link rel="stylesheet" href="css/style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .eval-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 25px;
            align-items: start;
        }

        @media (max-width: 1000px) {
            .eval-grid { grid-template-columns: 1fr; }
        }

        .chat-container { 
            display: flex; 
            flex-direction: column; 
            height: 500px; 
            background: #fdfbfb; 
            border-radius: 0 0 16px 16px; 
            border:1px solid #ddd; 
            border-top:none; 
        }
        .chat-history { 
            flex: 1; 
            overflow-y: auto; 
            padding: 20px; 
            border-bottom: 1px solid #eee; 
        }
        .chat-input-area { 
            padding: 15px; 
            background: #fff; 
            display: flex; 
            gap: 10px; 
            border-radius: 0 0 16px 16px; 
        }
        
        .message { margin-bottom: 15px; display: flex; flex-direction: column; }
        .message.user { align-items: flex-end; }
        .message.ai { align-items: flex-start; }
        
        .bubble { 
            max-width: 88%; 
            padding: 12px 16px; 
            border-radius: 12px; 
            font-size: 15px; 
            line-height: 1.6; 
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
        }
        
        .message.user .bubble { background: #3498db; color: white; border-bottom-right-radius: 2px; }
        .message.ai .bubble { background: #f1f3f5; color: #2d3436; border-bottom-left-radius: 2px; border: 1px solid #e1e1e1; }
        
        .sender-name { 
            font-size: 12px; 
            font-weight: bold; 
            margin-bottom: 4px; 
            opacity: 0.7; 
            margin-left: 2px;
        }
        
        .typing-indicator { display: none; padding: 15px; font-style: italic; color: #888; font-size: 13px; }

        #chatInput {
            font-size: 14px; 
        }
    </style>
</head>
<body>

    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <div class="top-header">
            <div class="greeting-box">
                <h2>Direct Observation</h2>
                <div class="date-box">Create Manual Evaluation</div>
            </div>
            <button id="themeToggle" class="theme-toggle"><i class="fas fa-moon"></i> Dark Mode</button>
        </div>

        <div class="eval-grid">
            
            <form method="POST">
                <div class="card">
                    <div class="ai-header-banner" style="background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%); margin-bottom: 20px;">
                        <div class="ai-header-content">
                            <h3><i class="fas fa-edit"></i> Evaluation Details</h3>
                            <p>Enter your observation notes below.</p>
                        </div>
                    </div>

                    <div class="eval-body">
                        
                        <div class="modern-input-group">
                            <label class="modern-label">Select Student</label>
                            <select name="student_id" id="studentSelect" class="modern-input" required style="padding:15px; font-size:14px;">
                                <option value="">-- Choose a Student --</option>
                                <?php while($s = $students->fetch_assoc()): ?>
                                    <option value="<?php echo $s['id']; ?>"><?php echo htmlspecialchars($s['fullname']); ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <div class="modern-input-group">
                            <label class="modern-label">Evaluation Title</label>
                            <input type="text" name="eval_title" id="evalTitle" class="modern-input" value="Direct Observation: <?php echo date("M d, Y"); ?>" required style="font-size:14px;">
                        </div>

                        <div class="modern-input-group">
                            <label class="modern-label">Competency Score (1-10)</label>
                            <div class="score-wrapper">
                                <input type="number" name="score" class="score-circle-input" min="1" max="10" placeholder="-" required>
                                <div style="font-size:14px; opacity:0.8; margin-left:10px;">
                                    1-4: Developing | 5-7: Proficient | 8-10: Distinguished
                                </div>
                            </div>
                        </div>

                        <div class="modern-input-group">
                            <label class="modern-label">Professional Feedback & Notes</label>
                            <textarea name="notes" id="evalNotes" class="modern-textarea" rows="12" placeholder="Type your observation here... (The AI can help you refine this!)" required style="font-size:15px; line-height:1.6;"></textarea>
                        </div>

                        <div class="action-footer">
                            <button type="submit" name="submit_grade" class="btn-submit-premium" style="width:100%;">
                                <i class="fas fa-save"></i> Save Evaluation
                            </button>
                        </div>

                    </div>
                </div>
            </form>

            <div class="eval-card" style="border:2px solid #3498db; height: fit-content;">
                <div class="ai-header-banner" style="background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);">
                    <div class="ai-header-content">
                        <h3><i class="fas fa-robot"></i> Copilot Assistant</h3>
                        <p>I can help you phrase feedback or check standards.</p>
                    </div>
                </div>
                
                <div class="chat-container">
                    <div class="chat-history" id="chatHistory">
                        <div class="message ai">
                            <div class="sender-name">Copilot</div>
                            <div class="bubble">Hello! Start typing your observation notes on the left, and ask me to "Refine this" or "Check against Domain 1".</div>
                        </div>
                    </div>
                    
                    <div class="typing-indicator" id="typingIndicator">
                        <i class="fas fa-circle-notch fa-spin"></i> Copilot is thinking...
                    </div>

                    <div style="padding:10px 15px; background:#f9f9f9; border-top:1px solid #eee;">
                        <input type="file" id="chatFile" style="font-size:12px;">
                    </div>

                    <div class="chat-input-area">
                        <input type="text" id="chatInput" class="modern-input" placeholder="Ask Copilot..." style="margin-bottom:0; border-radius:20px;">
                        <button type="button" onclick="sendMessage()" class="btn-ai-glow" style="background:#3498db; color:white; width:auto; border-radius:50%; padding:12px;">
                            <i class="fas fa-paper-plane"></i>
                        </button>
                    </div>
                </div>
            </div>

        </div>
    </div>
    
    <script src="js/script.js?v=<?php echo time(); ?>"></script>
    <script>
        const chatInput = document.getElementById('chatInput');
        const chatHistory = document.getElementById('chatHistory');
        const typingIndicator = document.getElementById('typingIndicator');
        const chatFile = document.getElementById('chatFile');
        const studentSelect = document.getElementById('studentSelect');
        const evalTitle = document.getElementById('evalTitle');
        const evalNotes = document.getElementById('evalNotes');

        chatHistory.scrollTop = chatHistory.scrollHeight;

        chatInput.addEventListener("keypress", function(e) { if (e.key === "Enter") { e.preventDefault(); sendMessage(); }});

        function sendMessage() {
            const message = chatInput.value.trim();
            if (message === "") return;

            addMessageToUI('You', message, 'user');
            chatInput.value = '';
            typingIndicator.style.display = 'block';
            chatHistory.scrollTop = chatHistory.scrollHeight;

            let studentName = studentSelect.selectedIndex > 0 ? studentSelect.options[studentSelect.selectedIndex].text : "Unknown Student";
            let currentNotes = evalNotes.value;
            let currentTitle = evalTitle.value;

            let contextData = `TASK: Admin Manual Eval. STUDENT: ${studentName}. TITLE: ${currentTitle}. NOTES: "${currentNotes}"`;

            const formData = new FormData();
            formData.append('message', message);
            formData.append('context', contextData);
            formData.append('mode', 'consultant'); 

            if (chatFile.files.length > 0) {
                formData.append('chat_file', chatFile.files[0]);
                addMessageToUI('System', '📎 Analyzing file...', 'ai');
                chatFile.value = ''; 
            }

            fetch('api_chat.php', { method: 'POST', body: formData })
            .then(response => response.json())
            .then(data => {
                typingIndicator.style.display = 'none';
                addMessageToUI('Copilot', data.reply, 'ai');
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
            nameDiv.className = 'sender-name'; 
            nameDiv.innerText = sender;
            
            const bubbleDiv = document.createElement('div');
            bubbleDiv.className = 'bubble'; 
            bubbleDiv.innerHTML = text.replace(/\n/g, "<br>");
            
            msgDiv.appendChild(nameDiv); 
            msgDiv.appendChild(bubbleDiv);
            
            chatHistory.appendChild(msgDiv);
            chatHistory.scrollTop = chatHistory.scrollHeight;
        }
    </script>
</body>
</html>