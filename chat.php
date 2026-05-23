<?php
error_reporting(0);
header("Content-Type: application/json");
session_start();

// Allow EITHER an admin or a student to use the chat
if (!isset($_SESSION['user_id'])) {
    echo json_encode(["error" => "SESSION_EXPIRED"]);
    exit;
}

// Log who is talking (so we know if the session is actually valid)
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role']; 
// ... proceed with loading the API key and processing
