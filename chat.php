<?php
// We disable errors so PHP doesn't print raw text and ruin the JSON response
error_reporting(0);
ini_set('display_errors', 0);
header("Content-Type: application/json");
session_start();

// RULE: ABSOLUTELY NO header("Location: ...") ALLOWED IN THIS FILE!
// We only check if ANY user is logged in. We do not restrict by role here.
if (!isset($_SESSION['user_id'])) {
    echo json_encode(["error" => "SESSION_EXPIRED"]);
    exit;
}

// Connect to DB to save the chat history
include __DIR__ . '/db_connect.php'; 

$input = json_decode(file_get_contents("php://input"), true);
$userMessage = trim($input['message'] ?? '');
$context = $input['context'] ?? '';
$user_id = $_SESSION['user_id'];

if (empty($userMessage)) {
    echo json_encode(["error" => "Empty message received."]);
    exit;
}

// Save User's Message to Database
$stmt = $conn->prepare("INSERT INTO chat_logs (user_id, sender, message) VALUES (?, 'user', ?)");
$stmt->bind_param("is", $user_id, $userMessage);
$stmt->execute();

// Load environment variables (Local XAMPP support)
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

// Fetch API Key from Railway
$apiKey = getenv('GEMINI_API_KEY') ?: ($_ENV['GEMINI_API_KEY'] ?? '');
if (empty($apiKey)) {
    echo json_encode(["error" => "API Key is missing. Check your Railway settings."]);
    exit;
}

// Call Google AI
$url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=" . $apiKey;
$prompt = "Context: \n" . $context . "\n\nUser Question: " . $userMessage;
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

// Send Reply and Save AI response to Database
if (isset($decoded['candidates'][0]['content']['parts'][0]['text'])) {
    $reply = $decoded['candidates'][0]['content']['parts'][0]['text'];
    
    $stmt2 = $conn->prepare("INSERT INTO chat_logs (user_id, sender, message) VALUES (?, 'ai', ?)");
    $stmt2->bind_param("is", $user_id, $reply);
    $stmt2->execute();

    echo json_encode(["reply" => $reply]);
} else {
    if(isset($decoded['error']['message'])) {
        echo json_encode(["error" => "Google API Error: " . $decoded['error']['message']]);
    } else {
        echo json_encode(["error" => "Unknown Google API Error."]);
    }
}
?>
