<?php
// Prevent any HTML errors from breaking the JSON response
error_reporting(0);
ini_set('display_errors', 0);
header("Content-Type: application/json");
session_start();

// 1. Safe Session Check - Return JSON, NEVER redirect
if (!isset($_SESSION['user_id'])) {
    echo json_encode(["error" => "SESSION_EXPIRED", "reply" => "Session expired. Please refresh the page."]);
    exit;
}

include __DIR__ . '/db_connect.php'; 

// 2. Handle BOTH JSON (Student) and FormData (Admin/Supervisor) inputs
$input = json_decode(file_get_contents("php://input"), true);
$userMessage = trim($input['message'] ?? $_POST['message'] ?? '');
$context = $input['context'] ?? $_POST['context'] ?? '';
$user_id = $_SESSION['user_id'];

if (empty($userMessage)) {
    echo json_encode(["reply" => "Please enter a message."]);
    exit;
}

// 3. Save User's Message
$stmt = $conn->prepare("INSERT INTO chat_logs (user_id, sender, message) VALUES (?, 'user', ?)");
$stmt->bind_param("is", $user_id, $userMessage);
$stmt->execute();

// 4. Load Secrets
if (file_exists(__DIR__ . '/.env')) {
    $lines = file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        $parts = explode('=', $line, 2);
        if(count($parts) == 2) {
            $_ENV[trim($parts[0])] = trim($parts[1]);
        }
    }
}
$apiKey = getenv('GEMINI_API_KEY') ?: ($_ENV['GEMINI_API_KEY'] ?? '');

if (empty($apiKey)) {
    echo json_encode(["reply" => "System configuration error: API Key missing."]);
    exit;
}

// 5. Call Google AI
$url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=" . $apiKey;
$prompt = "Context:\n" . $context . "\n\nUser: " . $userMessage;
$data = ["contents" => [["parts" => [["text" => $prompt]]]]];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 
$response = curl_exec($ch);
curl_close($ch);

$decoded = json_decode($response, true);

// 6. Return Reply & Save to DB
if (isset($decoded['candidates'][0]['content']['parts'][0]['text'])) {
    $reply = $decoded['candidates'][0]['content']['parts'][0]['text'];
    
    $stmt2 = $conn->prepare("INSERT INTO chat_logs (user_id, sender, message) VALUES (?, 'ai', ?)");
    $stmt2->bind_param("is", $user_id, $reply);
    $stmt2->execute();

    echo json_encode(["reply" => $reply]);
} else {
    echo json_encode(["reply" => "AI Error: Could not generate a response. Please try again."]);
}
?>
