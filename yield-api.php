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
$soil = isset($_POST['soil']) ? trim($_POST['soil']) : '';
$water = isset($_POST['water']) ? trim($_POST['water']) : '';
$fert = isset($_POST['fert']) ? trim($_POST['fert']) : '';

if (empty($crop) || empty($area) || empty($soil) || empty($water) || empty($fert)) {
    echo json_encode(["error" => "Please fill in all fields."]);
    exit();
}

// Your Gemini API Key
$api_key = 'AIzaSyCK60Qq5h-V_lmDs0fsyz-RTrbB1j3jJgk';
$url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=' . $api_key;

// PROMPT ENGINEERING: Force a specific JSON structure for the UI
$prompt = "Act as an expert agricultural data scientist. I am growing $crop on $area acres of land. My soil condition is $soil, my irrigation method is $water, and my fertilizer usage is $fert. 
Predict the crop yield and provide improvement recommendations. 
You MUST respond with ONLY a raw JSON object and absolutely no markdown formatting. Use this exact structure:
{
  \"expected_yield\": \"Numeric value (e.g. 4.8)\",
  \"unit\": \"tons/acre\",
  \"comparison_badge\": \"e.g., +12% above regional average\",
  \"predictability_score\": 82,
  \"risk_level\": \"Low\", \"Medium\", or \"High\",
  \"favorable_conditions_pct\": 87,
  \"regional_average\": \"Numeric value (e.g. 4.2)\",
  \"comparison_insight\": \"Brief 1-sentence insight on why my yield differs from average.\",
  \"recommendations\": [
    {\"title\": \"Actionable Step 1\", \"desc\": \"Brief description of what to do\", \"impact\": \"+5-8% yield\"},
    {\"title\": \"Actionable Step 2\", \"desc\": \"Brief description of what to do\", \"impact\": \"+2-4% yield\"}
  ],
  \"pro_tip\": \"One highly specific expert tip for this crop.\"
}";

$data = [
    "contents" => [["parts" => [["text" => $prompt]]]],
    "generationConfig" => ["temperature" => 0.3] // Low temperature for consistent JSON data
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
        
        // Clean up markdown wrapping
        $ai_text = preg_replace('/```json\s*/', '', $ai_text);
        $ai_text = preg_replace('/```\s*/', '', $ai_text);
        $ai_text = trim($ai_text);

        // Save JSON to Database WITH ERROR CATCHING
        try {
            $stmt = $conn->prepare("INSERT INTO yield_predictions (user_id, crop_type, land_area, soil_condition, irrigation, fertilizer, prediction_json) VALUES (:uid, :crop, :area, :soil, :water, :fert, :json)");
            $stmt->bindParam(':uid', $user_id);
            $stmt->bindParam(':crop', $crop);
            $stmt->bindParam(':area', $area);
            $stmt->bindParam(':soil', $soil);
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
        echo json_encode(["error" => "Could not generate prediction."]);
    }
} else {
    echo json_encode(["error" => "API Connection Failed. HTTP Code: $http_code"]);
}
?>