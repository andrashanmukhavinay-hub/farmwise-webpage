<?php
// A completely isolated test script
$api_key = 'AIzaSyCK60Qq5h-V_lmDs0fsyz-RTrbB1j3jJgk';
$url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=' . $api_key;

$data = [
    "contents" => [
        ["parts" => [["text" => "Say the exact words: Connection Successful!"]]]
    ]
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 

echo "<h3>Testing Connection to Google...</h3>";

$response = curl_exec($ch);
$error = curl_error($ch);
curl_close($ch);

if ($error) {
    echo "<p style='color:red;'><strong>CRITICAL ERROR:</strong> " . $error . "</p>";
    echo "<p>This means your XAMPP server is blocking outgoing internet requests.</p>";
} else {
    echo "<strong>Raw Response from Google:</strong><br>";
    echo "<pre style='background:#f4f4f4; padding:10px; border:1px solid #ccc;'>" . htmlspecialchars($response) . "</pre>";
}
?>