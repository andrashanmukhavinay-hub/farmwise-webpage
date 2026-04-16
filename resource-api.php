<?php
session_start();
require 'db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(["error" => "Not logged in"]);
    exit();
}

$user_id = $_SESSION['user_id'];

// Get POST data
$crop = isset($_POST['crop']) ? trim($_POST['crop']) : '';
$area = isset($_POST['area']) ? trim($_POST['area']) : '';
$water = isset($_POST['water']) ? trim($_POST['water']) : '';
$fert = isset($_POST['fert']) ? trim($_POST['fert']) : '';

if (empty($crop) || empty($area) || empty($water) || empty($fert)) {
    echo json_encode(["error" => "Please fill in all fields."]);
    exit();
}

// Your Gemini API Key
$api_key = 'AIzaSyCK60Qq5h-V_lmDs0fsyz-RTrbB1j3jJgk';
$url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=' . $api_key;

// PROMPT ENGINEERING: Force a specific JSON structure for the Resource Dashboard
$prompt = "Act as an expert agricultural economist and agronomist. I am growing $crop on $area acres. My current water approach is: $water. My current fertilizer approach is: $fert.
Calculate resource optimization strategies for an Indian farming context.
You MUST respond with ONLY a raw JSON object and absolutely no markdown formatting or text outside the JSON. Use this exact structure:
{
  \"total_savings_inr\": \"Numeric value formatted with commas (e.g. 9,500)\",
  \"water\": {
    \"current_label\": \"e.g. 150 mm/week\",
    \"optimal_label\": \"e.g. 100 mm/week\",
    \"current_val\": 150,
    \"optimal_val\": 100,
    \"saved_pct\": 33,
    \"recommendations\": [
      {\"title\": \"Actionable Step 1\", \"desc\": \"Brief description\", \"savings\": \"₹2000/acre\"},
      {\"title\": \"Actionable Step 2\", \"desc\": \"Brief description\", \"savings\": \"₹1500/acre\"}
    ]
  },
  \"fertilizer\": {
    \"n_current\": 120, \"n_optimal\": 80,
    \"p_current\": 60, \"p_optimal\": 45,
    \"k_current\": 50, \"k_optimal\": 30,
    \"saved_pct\": 25,
    \"timing_advice\": \"1 sentence on when to apply for maximum absorption.\",
    \"types\": [
      {\"name\": \"Fertilizer Name (e.g. Neem Coated Urea)\", \"usage\": \"When/how to apply\"},
      {\"name\": \"Fertilizer Name\", \"usage\": \"When/how to apply\"}
    ]
  },
  \"sustainability\": \"A 2-sentence summary of how this helps the environment (e.g., soil health, carbon footprint).\"
}";

$data = [
    "contents" => [["parts" => [["text" => $prompt]]]],
    "generationConfig" => ["temperature" => 0.2] // Low temp for accurate mathematical JSON
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

if ($http_code == 200) {
    $response_data = json_decode($response, true);
    if (isset($response_data['candidates'][0]['content']['parts'][0]['text'])) {
        $ai_text = $response_data['candidates'][0]['content']['parts'][0]['text'];
        
        $ai_text = preg_replace('/```json\s*/', '', $ai_text);
        $ai_text = preg_replace('/```\s*/', '', $ai_text);
        $ai_text = trim($ai_text);

        // Save JSON to Database WITH ERROR CATCHING
        try {
            $stmt = $conn->prepare("INSERT INTO resource_optimizations (user_id, crop_name, land_area, water_usage, fert_usage, optimization_json) VALUES (:uid, :crop, :area, :water, :fert, :json)");
            $stmt->bindParam(':uid', $user_id);
            $stmt->bindParam(':crop', $crop);
            $stmt->bindParam(':area', $area);
            $stmt->bindParam(':water', $water);
            $stmt->bindParam(':fert', $fert);
            $stmt->bindParam(':json', $ai_text);
            $stmt->execute();
        } catch(PDOException $e) {
            echo json_encode(["error" => "Database Error: " . $e->getMessage()]);
            exit();
        }

        echo $ai_text;
    } else {
        echo json_encode(["error" => "Could not generate optimization."]);
    }
} else {
    echo json_encode(["error" => "API Connection Failed. HTTP Code: $http_code"]);
}
?>