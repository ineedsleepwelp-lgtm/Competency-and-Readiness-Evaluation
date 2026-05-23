<?php
header("Content-Type: application/json");
session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(["error" => "Session expired. Please log in again."]);
    exit;
}

$input = json_decode(file_get_contents("php://input"), true);
$userMessage = $input['message'] ?? '';
$context = $input['context'] ?? '';

// Grab the key safely from Railway's secret vault!
$apiKey = getenv('GEMINI_API_KEY') ?: ($_ENV['GEMINI_API_KEY'] ?? '');

if (empty($apiKey)) {
    echo json_encode(["error" => "API Key is missing from Railway Variables!"]);
    exit;
}

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
// Bypass SSL issues on some servers
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 

$response = curl_exec($ch);
curl_close($ch);

$decoded = json_decode($response, true);

if (isset($decoded['candidates'][0]['content']['parts'][0]['text'])) {
    echo json_encode(["reply" => $decoded['candidates'][0]['content']['parts'][0]['text']]);
} else {
    if(isset($decoded['error']['message'])) {
         echo json_encode(["error" => "Google API Error: " . $decoded['error']['message']]);
    } else {
         echo json_encode(["error" => "Unknown API Error occurred."]);
    }
}
?>
