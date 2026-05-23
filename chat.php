<?php

// 1. Security & Headers
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");

// 2. Get the user's message
$input = json_decode(file_get_contents("php://input"), true);
$userMessage = $input['message'] ?? '';

if (empty($userMessage)) {
    echo json_encode(["error" => "No message provided"]);
    exit;
}

https://aistudio.google.com/app/apikey
$apiKey = "AIzaSyAEEmOrUmhKh50yKxiYLNH8u3v-powzODo"; 

$systemInstruction = "You are the CORE Assistant (Competency and Readiness Evaluation Assistant). 
Your job is to help students with their academic evaluations. 
You are polite, professional, and encouraging. 
If a student asks about the system, explain that this dashboard tracks their competency progress and recent submissions.";


// 4. Send Request to Google Gemini API
$url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=" . $apiKey;

$data = [
    "contents" => [
        [
            "parts" => [
                ["text" => $systemInstruction . "\n\nUser: " . $userMessage]
            ]
        ]
    ]
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);

$response = curl_exec($ch);

if (curl_errno($ch)) {
    echo json_encode(["error" => "Connection Error: " . curl_error($ch)]);
} else {
    $decoded = json_decode($response, true);
    
    // Extract the answer from Gemini's specific JSON structure
    if (isset($decoded['candidates'][0]['content']['parts'][0]['text'])) {
        $botReply = $decoded['candidates'][0]['content']['parts'][0]['text'];
        echo json_encode(["reply" => $botReply]);
    } else {
        // Log the error for you to see in the browser console
        echo json_encode(["error" => "API Error", "details" => $decoded]);
    }
}
curl_close($ch);
?>
