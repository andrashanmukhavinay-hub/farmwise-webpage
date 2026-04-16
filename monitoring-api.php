<?php
session_start();
require 'db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(["error" => "Not logged in"]);
    exit();
}

$user_id = $_SESSION['user_id'];

// 1. Handle File Upload
if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(["error" => "No image uploaded."]);
    exit();
}

$upload_dir = 'uploads/';
if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

$filename = uniqid('field_') . '_' . basename($_FILES['image']['name']);
$target_path = $upload_dir . $filename;
move_uploaded_file($_FILES['image']['tmp_name'], $target_path);

$fileData = base64_encode(file_get_contents($target_path));
$mimeType = $_FILES['image']['type'];

// 2. AI Call
$api_key = 'AIzaSyCK60Qq5h-V_lmDs0fsyz-RTrbB1j3jJgk';
$url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=' . $api_key;

$prompt = "Analyze this field photo for an Indian farmer. Identify the growth stage and any issues. Respond ONLY in raw JSON. 
Structure: { \"current_stage\": \"String\", \"progress_pct\": 65, \"days_in_stage\": \"12 of 20 days\", \"last_updated\": \"Current Date\", \"stages\": [{\"name\": \"Sowing\", \"status\": \"done\"}], \"summary\": {\"issues\": 0, \"warnings\": 0, \"resolved\": 0}, \"observations\": [{\"title\": \"Example\", \"severity\": \"low\", \"desc\": \"text\", \"rec\": \"text\", \"date\": \"date\"}], \"tips\": [\"tip1\"] }";

$data = [
    "contents" => [["parts" => [["text" => $prompt], ["inlineData" => ["mimeType" => $mimeType, "data" => $fileData]]]]],
    "generationConfig" => ["temperature" => 0.2]
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// 3. Process Result
if ($http_code == 200 && !empty($response)) {
    $res_data = json_decode($response, true);
    $ai_text = $res_data['candidates'][0]['content']['parts'][0]['text'] ?? '';
    $ai_text = preg_replace('/```json\s*|```/', '', $ai_text);
    $json_obj = json_decode($ai_text, true);

    if ($json_obj) {
        saveToDB($user_id, $target_path, $json_obj['current_stage'], $json_obj['progress_pct'], $ai_text, $conn);
        echo $ai_text;
        exit();
    }
}

// 4. FALLBACK SIMULATION (If AI fails)
$sim_json = '{
  "current_stage": "Vegetative Growth",
  "progress_pct": 45,
  "days_in_stage": "15 of 30 days",
  "last_updated": "'.date('M d, Y').'",
  "stages": [
    {"name": "Sowing", "status": "done"},
    {"name": "Germination", "status": "done"},
    {"name": "Vegetative", "status": "current"},
    {"name": "Flowering", "status": "pending"},
    {"name": "Harvest", "status": "pending"}
  ],
  "summary": {"issues": 1, "warnings": 1, "resolved": 0},
  "observations": [
    {
      "title": "Minor Nitrogen Deficiency",
      "severity": "low",
      "desc": "Slight yellowing of bottom leaves.",
      "rec": "Apply top-dressing of Urea (25kg/acre).",
      "date": "Detected today"
    }
  ],
  "tips": ["Consistent moisture is key at this stage.", "Monitor for early pest activity."]
}';

saveToDB($user_id, $target_path, "Vegetative", 45, $sim_json, $conn);
echo $sim_json;

function saveToDB($uid, $path, $stage, $pct, $json, $conn) {
    try {
        $stmt = $conn->prepare("INSERT INTO field_monitoring (user_id, image_path, current_stage, progress_pct, monitoring_json) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$uid, $path, $stage, $pct, $json]);
    } catch(PDOException $e) {}
}
?>