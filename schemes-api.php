<?php
session_start();
require 'db.php'; // Ensure DB connection is available
header('Content-Type: application/json');

$query = $_POST['query'] ?? '';
$scheme_name = $_POST['scheme_name'] ?? 'General';

if (empty($query)) {
    echo json_encode(["error" => "Please ask a question."]);
    exit();
}

$api_key = 'AIzaSyCK60Qq5h-V_lmDs0fsyz-RTrbB1j3jJgk';
$url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=' . $api_key;

$prompt = "You are a Government Scheme Consultant for Indian farmers. 
Farmer Query: '$query' regarding the '$scheme_name'.
Provide a concise, professional response explaining eligibility, required documents, and next steps. 
Use simple text. Do not use asterisks (*) or markdown formatting. Keep it under 4 sentences.";

$data = [
    "contents" => [["parts" => [["text" => $prompt]]]],
    "generationConfig" => ["temperature" => 0.4, "maxOutputTokens" => 300]
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 
curl_setopt($ch, CURLOPT_TIMEOUT, 15); // Wait max 15 seconds so it doesn't freeze

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// If the API returns successfully
if ($http_code == 200 && !empty($response)) {
    $res_data = json_decode($response, true);
    $ai_reply = $res_data['candidates'][0]['content']['parts'][0]['text'] ?? "";
    
    if (!empty($ai_reply)) {
        echo json_encode(["reply" => trim($ai_reply)]);
        exit();
    }
}

// SIMULATION FALLBACK (If the Google API fails, is busy, or hits a limit)
$lower_query = strtolower($query);
if (strpos($lower_query, 'acre') !== false || strpos($lower_query, 'land') !== false) {
    $fallback_reply = "Yes, for the $scheme_name, farmers with landholdings are generally eligible. You will need to provide your land ownership records (Patta/Khata), Aadhar card, and bank passbook to your local CSC center.";
} else {
    $fallback_reply = "Based on general guidelines for $scheme_name, you need an active bank account linked to your Aadhar. Please visit your nearest agriculture office or Common Service Center (CSC) to apply with your documents.";
}

echo json_encode(["reply" => $fallback_reply]);
?>