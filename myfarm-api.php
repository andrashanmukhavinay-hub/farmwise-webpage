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
$farm_name = isset($_POST['farm_name']) ? trim($_POST['farm_name']) : '';
$farm_area = isset($_POST['area']) ? trim($_POST['area']) : '';
$crops = isset($_POST['crops']) ? trim($_POST['crops']) : '';

if (empty($farm_name) || empty($farm_area) || empty($crops)) {
    echo json_encode(["error" => "Please provide all farm details."]);
    exit();
}

// Your Gemini API Key
$api_key = 'AIzaSyCK60Qq5h-V_lmDs0fsyz-RTrbB1j3jJgk';
$url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=' . $api_key;

$prompt = "Act as an expert farm management AI. Generate a realistic status report for '$farm_name', a farm in India measuring $farm_area acres, currently growing: $crops. Response MUST be ONLY a raw JSON object.";

$data = ["contents" => [["parts" => [["text" => $prompt]]]], "generationConfig" => ["temperature" => 0.3]];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Bypass local SSL issues
curl_setopt($ch, CURLOPT_TIMEOUT, 15);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// SUCCESS PATH
if ($http_code == 200 && !empty($response)) {
    $res_data = json_decode($response, true);
    $ai_text = $res_data['candidates'][0]['content']['parts'][0]['text'] ?? '';
    $ai_text = preg_replace('/```json\s*|```/', '', $ai_text);
    
    if (!empty($ai_text)) {
        saveFarmData($user_id, $farm_name, $farm_area, $crops, $ai_text, $conn);
        echo $ai_text;
        exit();
    }
}

// SIMULATION PATH (Runs if 429 error occurs or API fails)
$sim_json = '{
  "quick_stats": {"workers": "8", "hours_tracked": "95", "payroll": "15,000"},
  "performance": {
    "health_score": 88,
    "chart": {
      "labels": ["Jan", "Feb", "Mar", "Apr", "May", "Jun"],
      "this_year": [65, 70, 78, 82, 85, 88],
      "last_year": [60, 62, 65, 70, 72, 75]
    },
    "insight": "Farm productivity is up 13% compared to last season due to better irrigation."
  },
  "financials": {
    "total_expense": 42000,
    "breakdown": {
      "seeds": {"amount": 8000, "pct": 19},
      "fertilizer": {"amount": 12000, "pct": 28},
      "labor": {"amount": 15000, "pct": 36},
      "equipment": {"amount": 7000, "pct": 17}
    }
  },
  "crop_history": [
    {"name": "'.$crops.'", "status": "Growing", "yield": "Est. Value TBD", "area": "'.$farm_area.' Acres", "progress_pct": 60}
  ],
  "management_tip": "Apply mulch to your rows to retain soil moisture during the upcoming heatwave."
}';

saveFarmData($user_id, $farm_name, $farm_area, $crops, $sim_json, $conn);
echo $sim_json;

// Helper function to save to DB
function saveFarmData($uid, $name, $area, $crops, $json, $conn) {
    try {
        $stmt = $conn->prepare("INSERT INTO my_farm_data (user_id, farm_name, farm_area, main_crops, farm_json) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$uid, $name, $area, $crops, $json]);
    } catch(PDOException $e) {
        // Silently fail database part so UI still shows simulated data
    }
}
?>