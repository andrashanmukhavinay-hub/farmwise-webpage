<?php
session_start();
require 'db.php';
header('Content-Type: application/json');

// 1. Security Check
if (!isset($_SESSION['user_id'])) {
    echo json_encode(["error" => "Not logged in"]);
    exit();
}

$user_id = $_SESSION['user_id'];
$user_message = isset($_POST['message']) ? trim($_POST['message']) : '';
$expert = isset($_POST['expert']) ? trim($_POST['expert']) : 'Dr. Rajesh Kumar';
$specialty = isset($_POST['specialty']) ? trim($_POST['specialty']) : 'Crop Management';

if (empty($user_message)) {
    echo json_encode(["error" => "Message cannot be empty"]);
    exit();
}

// 2. Save User's Message to Database (Uses 'message_text' column)
try {
    $stmt = $conn->prepare("INSERT INTO expert_chats (user_id, expert_name, message_text, sender_type) VALUES (?, ?, ?, 'user')");
    $stmt->execute([$user_id, $expert, $user_message]);
} catch(PDOException $e) {
    echo json_encode(["error" => "Database error saving your message: " . $e->getMessage()]);
    exit();
}

// 3. Connect to Gemini AI
$api_key = 'AIzaSyCK60Qq5h-V_lmDs0fsyz-RTrbB1j3jJgk';
$url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=' . $api_key;

$prompt = "You are $expert, an agricultural expert specializing in $specialty. 
A farmer in India is asking you: '$user_message'. 
Provide a professional, helpful, and concise response (under 3 sentences) as if you are chatting on WhatsApp. Do not use asterisks or bold formatting.";

$data = [
    "contents" => [["parts" => [["text" => $prompt]]]],
    "generationConfig" => ["temperature" => 0.7, "maxOutputTokens" => 150]
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 
curl_setopt($ch, CURLOPT_TIMEOUT, 15);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// 4. Process AI Response
if ($http_code == 200 && !empty($response)) {
    $res_data = json_decode($response, true);
    $ai_reply = $res_data['candidates'][0]['content']['parts'][0]['text'] ?? '';
    
    if (!empty($ai_reply)) {
        saveExpertReply($user_id, $expert, $ai_reply, $conn);
        echo json_encode(["reply" => trim($ai_reply)]);
        exit(); 
    }
}

// 5. SIMULATION FALLBACK (Triggered if API is busy)
$lower_msg = strtolower($user_message); // Using the correct variable name here!

if (strpos($lower_msg, 'hi') !== false || strpos($lower_msg, 'hello') !== false || strpos($lower_msg, 'hlo') !== false) {
    $sim_reply = "Hello! How can I assist you with your $specialty needs today?";
} elseif (strpos($lower_msg, 'pest') !== false || strpos($lower_msg, 'insect') !== false) {
    $sim_reply = "For pest issues, I recommend checking your field for early signs of leaf damage. Try applying Neem Oil extract as an immediate organic deterrent.";
} elseif (strpos($lower_msg, 'water') !== false || strpos($lower_msg, 'rain') !== false) {
    $sim_reply = "Ensure your field has proper drainage if heavy rain is expected. Root rot happens quickly in waterlogged soil.";
} else {
    $sim_reply = "Based on your situation, I advise monitoring your crops closely for the next 48 hours. Let me know if you see any changes.";
}

// Save simulated reply and return it
saveExpertReply($user_id, $expert, $sim_reply, $conn);
echo json_encode(["reply" => $sim_reply]);


// Helper function to save expert reply to database
function saveExpertReply($uid, $exp_name, $reply_text, $db_conn) {
    try {
        // Uses 'message_text' column
        $stmt = $db_conn->prepare("INSERT INTO expert_chats (user_id, expert_name, message_text, sender_type) VALUES (?, ?, ?, 'expert')");
        $stmt->execute([$uid, $exp_name, $reply_text]);
    } catch(PDOException $e) {
        // Silently fail so the chat UI still works
    }
}
?>