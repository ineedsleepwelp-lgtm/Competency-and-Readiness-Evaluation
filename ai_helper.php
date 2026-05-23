<?php
error_reporting(0);
header("Content-Type: application/json");
session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(["error" => "SESSION_EXPIRED"]);
    exit;
}

$input = json_decode(file_get_contents("php://input"), true);
$userMessage = $input['message'] ?? '';
$context = $input['context'] ?? '';

// ... (API logic to call Gemini here) ...

// IMPORTANT: Do NOT include any header("Location: ...") here!
// Just echo the result.
echo json_encode(["reply" => $botReply]); 
?>
