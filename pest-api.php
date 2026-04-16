<?php
session_start();
require 'db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(["error" => "Not logged in"]);
    exit();
}

$user_id = $_SESSION['user_id'];

// Check if an image was uploaded
if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(["error" => "Please upload a valid image of the crop."]);
    exit();
}

$file = $_FILES['image'];
$mimeType = $file['type'];

// 1. Save the image permanently to the "uploads" folder
$upload_dir = 'uploads/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}
// Create a unique name for the image so it doesn't overwrite others
$filename = uniqid('pest_') . '_' . basename($file['name']);
$target_path = $upload_dir . $filename;
move_uploaded_file($file['tmp_name'], $target_path);

// 2. Prepare the image for Gemini AI
$fileData = file_get_contents($target_path);
$base64Data = base64_encode($fileData);

// Your Gemini API Key
$api_key = 'AIzaSyCK60Qq5h-V_lmDs0fsyz-RTrbB1j3jJgk';
$url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=' . $api_key;

// PROMPT ENGINEERING: Force Gemini to act as a botanist and return ONLY JSON
$prompt = "Analyze this image of a plant or crop. Identify any pests, diseases, or nutrient deficiencies. 
You MUST respond with ONLY a raw JSON object and absolutely no other text, markdown, or code blocks. Use this exact structure:
{
  \"disease_name\": \"Name of disease/pest (e.g. Leaf Blight (Alternaria))\",
  \"crop_name\": \"Likely crop (e.g. Tomato)\",
  \"symptoms\": \"Brief description of visual symptoms\",
  \"risk_level\": \"High, Medium, or Low\",
  \"confidence\": \"95\",
  \"immediate_action\": \"1 sentence urgent action warning (or null if healthy)\",
  \"treatment_steps\": [\"Step 1\", \"Step 2\", \"Step 3\"],
  \"chemical_options\": [
    {\"name\": \"Chemical name\", \"dosage\": \"Amount per acre/liter\", \"timing\": \"When to apply\"}
  ],
  \"organic_options\": [\"Organic method 1\", \"Organic method 2\"],
  \"preventive_measures\": [\"Measure 1\", \"Measure 2\"],
  \"expert_tip\": \"One professional tip for recovery.\"
}";

$data = [
    "contents" => [
        [
            "parts" => [
                ["text" => $prompt],
                [
                    "inlineData" => [
                        "mimeType" => $mimeType,
                        "data" => $base64Data
                    ]
                ]
            ]
        ]
    ],
    "generationConfig" => ["temperature" => 0.2] // Low temp for highly accurate, factual JSON
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
        
        // Clean up markdown wrapping if Gemini ignores the prompt instruction
        $ai_text = preg_replace('/```json\s*/', '', $ai_text);
        $ai_text = preg_replace('/```\s*/', '', $ai_text);
        $ai_text = trim($ai_text);
        
        // Parse the JSON so we can extract specific fields for the database
        $json_obj = json_decode($ai_text, true);

        if ($json_obj) {
            
            // Clean up confidence score (remove '%' if AI included it)
            $confidence_raw = $json_obj['confidence'] ?? "0";
            $confidence_score = (float) filter_var($confidence_raw, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);

            // 3. Save to Database using the CORRECT column names!
            try {
                $stmt = $conn->prepare("INSERT INTO pest_diagnoses (user_id, image_path, disease_name, confidence_score, treatment_advice) VALUES (:uid, :image, :disease, :confidence, :advice)");
                
                $stmt->execute([
                    ':uid' => $user_id,
                    ':image' => $target_path, // Saves 'uploads/pest_12345.jpg'
                    ':disease' => $json_obj['disease_name'],
                    ':confidence' => $confidence_score,
                    ':advice' => $ai_text // Saves the full JSON report
                ]);
            } catch(PDOException $e) {
                // If the DB fails, tell the frontend exactly why
                echo json_encode(["error" => "Database Error: " . $e->getMessage()]);
                exit();
            }

            echo $ai_text; // Return pure JSON to frontend
        } else {
             echo json_encode(["error" => "AI failed to format response."]);
        }
    } else {
        echo json_encode(["error" => "Could not analyze the image."]);
    }
} else {
    echo json_encode(["error" => "API Connection Failed. HTTP Code: $http_code"]);
}
?>