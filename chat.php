<?php
// 1. Catch ALL server errors and push them directly to the chat interface
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    echo json_encode(['error' => "PHP Error: $errstr in $errfile on line $errline"]);
    exit;
});
set_exception_handler(function($e) {
    echo json_encode(['error' => "PHP Exception: " . $e->getMessage()]);
    exit;
});

error_reporting(0);
header("Content-Type: application/json");
session_start();

// 2. Validate Session
if (!isset($_SESSION['user_id'])) {
    echo json_encode(["error" => "SESSION_EXPIRED: Please refresh the page and log in again."]);
    exit;
}

// 3. Read the incoming Chat Data
$input = json_decode(file_get_contents("php://input"), true);
$userMessage = $input['message'] ?? '';
$context = $input['context'] ?? '';

// Load local secrets if testing on XAMPP
if (file_exists(__DIR__ . '/.env')) {
    $lines = file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        list($name, $value) = explode('=', $line, 2);
        $_ENV[trim($name)] = trim($value);
    }
}

// 4. Safely grab the Google API Key
$apiKey = getenv('GEMINI_API_KEY') ?: ($_ENV['GEMINI_API_KEY'] ?? '');

if (empty($apiKey)) {
    echo json_encode(["error" => "API Key is missing! Please check your Railway Variables."]);
    exit;
}

// 5. Build the Google AI Request
$url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=" . $apiKey;
$prompt = "You are a helpful teaching assistant. Context: \n" . $context . "\n\nUser Question: " . $userMessage;

$data = [
    "contents" => [
        ["parts" => [["text" => $prompt]]]
    ]
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 

$response = curl_exec($ch);
$curl_error = curl_error($ch);
curl_close($ch);

// 6. Handle specific cURL or Network failures
if ($response === false) {
    echo json_encode(["error" => "Server cURL Error: " . $curl_error]);
    exit;
}

$decoded = json_decode($response, true);

// 7. Handle completely invalid Google responses
if ($decoded === null) {
    echo json_encode(["error" => "Google returned invalid JSON. Raw response: " . substr($response, 0, 100)]);
    exit;
}

// 8. Output the final success or specific Google error
if (isset($decoded['candidates'][0]['content']['parts'][0]['text'])) {
    echo json_encode(["reply" => $decoded['candidates'][0]['content']['parts'][0]['text']]);
} else {
    if(isset($decoded['error']['message'])) {
         echo json_encode(["error" => "Google API Error: " . $decoded['error']['message']]);
    } else {
         echo json_encode(["error" => "Unknown API Error. Received: " . json_encode($decoded)]);
    }
}
?>
