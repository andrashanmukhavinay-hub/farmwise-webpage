<?php
session_start();
require 'db.php';

// Set header to return JSON
header('Content-Type: application/json');

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(["error" => "Not logged in"]);
    exit();
}

$user_id = $_SESSION['user_id'];
$query = isset($_POST['query']) ? trim($_POST['query']) : '';
$has_media = isset($_FILES['media']) && $_FILES['media']['error'] === UPLOAD_ERR_OK;

// If they just sent a photo/audio with no text, give Gemini a default prompt
if (empty($query) && $has_media) {
    $query = "Please analyze this attached media and provide helpful agricultural advice.";
} elseif (empty($query)) {
    echo json_encode(["response" => "Please ask a question or attach a file."]);
    exit();
}

// =========================================================================
// GOOGLE GEMINI MULTIMODAL API CONNECTION
// =========================================================================

// Your Google Gemini API Key
$api_key = 'AIzaSyCK60Qq5h-V_lmDs0fsyz-RTrbB1j3jJgk';

// Using gemini-2.5-flash as it supports Text, Images, and Audio
$url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=' . $api_key;

// System Prompt
$full_prompt = "You are FarmWise AI, an expert agricultural assistant. Give a concise, helpful, and professional answer. Format your response nicely using markdown: " . $query;

// 1. Start building the payload parts with the text prompt
$parts = [
    ["text" => $full_prompt]
];

// 2. If a file was uploaded (Image or Audio), convert it to Base64 and attach it
if ($has_media) {
    $mimeType = $_FILES['media']['type'];
    $fileData = file_get_contents($_FILES['media']['tmp_name']);
    $base64Data = base64_encode($fileData);

    $parts[] = [
        "inlineData" => [
            "mimeType" => $mimeType,
            "data" => $base64Data
        ]
    ];
}

// 3. Build the final payload
$data = [
    "contents" => [
        ["parts" => $parts]
    ],
    "generationConfig" => [
        "temperature" => 0.7, 
        "maxOutputTokens" => 800
    ]
];

// Initialize cURL 
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

// Bypass local XAMPP SSL certificate issues
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 

// Execute the request
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$ai_response = "";

if ($http_code == 200) {
    // Successfully connected to Google! Parse the JSON response.
    $response_data = json_decode($response, true);
    
    if (isset($response_data['candidates'][0]['content']['parts'][0]['text'])) {
        $ai_response = $response_data['candidates'][0]['content']['parts'][0]['text'];
        
        // Convert Markdown to HTML for the chat UI
        $ai_response = htmlspecialchars($ai_response); 
        $ai_response = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $ai_response); // Bold
        $ai_response = preg_replace('/\*(.*?)\*/', '<em>$1</em>', $ai_response); // Italics
        $ai_response = nl2br($ai_response); // Line breaks
    } else {
        $ai_response = "Sorry, I received an unexpected response format from the server.";
    }
} else {
    // ERROR HANDLING: Grab the exact error message from Google
    $error_details = json_decode($response, true);
    $google_error = isset($error_details['error']['message']) ? $error_details['error']['message'] : 'Unknown Google API Error';
    $ai_response = "<strong>Google API Error (HTTP $http_code):</strong> " . $google_error;
}

// =========================================================================
// SAVE TO DATABASE HISTORY
// =========================================================================
try {
    // Prefix the database history if a media file was attached so the user knows
    $db_query = $has_media ? "[Media Attached] " . $query : $query;
    
    $stmt = $conn->prepare("INSERT INTO chat_history (user_id, user_query, ai_response) VALUES (:user_id, :query, :response)");
    $stmt->bindParam(':user_id', $user_id);
    $stmt->bindParam(':query', $db_query);
    $stmt->bindParam(':response', $ai_response);
    $stmt->execute();
} catch(PDOException $e) {
    // Silently handle DB errors to not interrupt the chat
}

// Return the final response
echo json_encode(["response" => $ai_response]);
?>