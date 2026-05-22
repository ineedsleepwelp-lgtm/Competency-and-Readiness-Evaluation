<?php
// FILE: D:\XAMP\htdocs\CORE\ai_helper.php

function generateAIResponse($context_text, $mode = 'evaluator') {
    
    // ---------------------------------------------------------
    // PASTE YOUR REAL API KEY HERE
    // ---------------------------------------------------------
    $apiKey = "YOUR_GOOGLE_GEMINI_API_KEY_HERE"; 
    // ---------------------------------------------------------

    // UPDATED URL: Using 'gemini-2.0-flash' which is available in your list
    $apiUrl = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=" . $apiKey;

    // 1. EVALUATOR MODE (For Supervisors & Admins)
    if ($mode == 'evaluator') {
        $prompt = "
        You are an expert Cooperating Teacher and Pedagogy Evaluator.
        Task: Evaluate the following student submission based on the PPST (Philippine Professional Standards for Teachers).
        
        Context: $context_text
        
        Generate a report (max 150 words):
        1. Recommended Score (1-10).
        2. Aligned PPST Domain.
        3. Professional feedback (Strengths & Improvements).
        Do not use markdown formatting (no bold/italics), just plain text.
        ";
    } 
    // 2. MENTOR MODE (For Students)
    elseif ($mode == 'mentor') {
        $prompt = "
        You are a supportive PPST Mentor for student teachers.
        Task: The student wants to submit the following evidence. Provide specific advice on how to describe it to better match PPST criteria.
        
        Student's Draft Description: $context_text
        
        Provide advice (max 150 words):
        1. Which PPST Domain does this best fit?
        2. What keywords should they include in their description?
        3. How can they prove they met the criteria?
        Keep it encouraging and actionable. Do not use markdown formatting.
        ";
    }

    $data = [
        "contents" => [
            [
                "parts" => [
                    ["text" => $prompt]
                ]
            ]
        ]
    ];

    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

    // FIX FOR XAMPP SSL ISSUES
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false); 

    $response = curl_exec($ch);
    
    if(curl_errno($ch)){ 
        return "CURL Error: " . curl_error($ch); 
    }
    
    curl_close($ch);

    $json = json_decode($response, true);
    
    if (isset($json['candidates'][0]['content']['parts'][0]['text'])) {
        return $json['candidates'][0]['content']['parts'][0]['text'];
    } else {
        if(isset($json['error']['message'])) {
            return "API Error: " . $json['error']['message'];
        }
        return "Unknown Error. Response: " . $response;
    }
}
?>