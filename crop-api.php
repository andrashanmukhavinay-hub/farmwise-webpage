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
$district = isset($_POST['district']) ? trim($_POST['district']) : '';
$soil = isset($_POST['soil']) ? trim($_POST['soil']) : '';
$water = isset($_POST['water']) ? trim($_POST['water']) : '';
$area = isset($_POST['area']) ? trim($_POST['area']) : '';

if (empty($district) || empty($soil) || empty($water) || empty($area)) {
    echo json_encode(["error" => "Please fill in all fields."]);
    exit();
}

// Your Gemini API Key
$api_key = 'AIzaSyCK60Qq5h-V_lmDs0fsyz-RTrbB1j3jJgk';
$url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=' . $api_key;

// PROMPT ENGINEERING: We force the AI to return ONLY a JSON object so our Javascript can build the UI cards
$prompt = "Act as an expert agronomist. I have a farm in $district, India with $soil soil, $water water availability, and $area acres of land. Recommend 2 to 3 highly suitable crops. 
You MUST respond with ONLY a raw JSON object and absolutely no other text or markdown formatting. Use this exact structure:
{
  \"crops\": [
    {
      \"name\": \"Crop Name (e.g. Rice (Paddy))\",
      \"match\": \"95% Match\",
      \"match_class\": \"high-match\", 
      \"yield\": \"X-Y tons/acre\",
      \"sowing\": \"Month - Month\",
      \"tips\": [\"Tip 1\", \"Tip 2\", \"Tip 3\"],
      \"irrigation\": \"Details...\",
      \"fertilizer\": \"Details...\",
      \"pesticide\": \"Details...\"
    }
  ],
  \"expert_advice\": \"A brief, 2-sentence expert summary of what to focus on.\"
}";

$data = [
    "contents" => [["parts" => [["text" => $prompt]]]],
    "generationConfig" => ["temperature" => 0.4] // Lower temperature for more factual, structured output
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
        
        // Clean up response in case Gemini wrapped it in ```json ... ``` markdown
        $ai_text = preg_replace('/```json\s*/', '', $ai_text);
        $ai_text = preg_replace('/```\s*/', '', $ai_text);
        $ai_text = trim($ai_text);

        // Save to Database
        try {
            $stmt = $conn->prepare("INSERT INTO crop_recommendations (user_id, district, soil_type, water_availability, land_area, recommendation_json) VALUES (:uid, :dist, :soil, :water, :area, :json)");
            $stmt->bindParam(':uid', $user_id);
            $stmt->bindParam(':dist', $district);
            $stmt->bindParam(':soil', $soil);
            $stmt->bindParam(':water', $water);
            $stmt->bindParam(':area', $area);
            $stmt->bindParam(':json', $ai_text);
            $stmt->execute();
        } catch(PDOException $e) {}

        // Send JSON back to browser
        echo $ai_text;
    } else {
        echo json_encode(["error" => "Invalid AI response format."]);
    }
} else {
    echo json_encode(["error" => "API Connection Failed. HTTP Code: $http_code"]);
}
?>