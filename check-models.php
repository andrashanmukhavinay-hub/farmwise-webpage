<?php
$api_key = 'AIzaSyCK60Qq5h-V_lmDs0fsyz-RTrbB1j3jJgk';
$url = 'https://generativelanguage.googleapis.com/v1beta/models?key=' . $api_key;

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 

echo "<h2>Models available for your API Key:</h2>";

$response = curl_exec($ch);
$error = curl_error($ch);
curl_close($ch);

if ($error) {
    echo "Error: " . $error;
} else {
    $data = json_decode($response, true);
    if (isset($data['models'])) {
        echo "<ul>";
        foreach ($data['models'] as $model) {
            // Only show models that support text generation
            if (in_array('generateContent', $model['supportedGenerationMethods'])) {
                $clean_name = str_replace('models/', '', $model['name']);
                echo "<li style='font-size: 18px; margin-bottom: 5px;'><strong>" . $clean_name . "</strong></li>";
            }
        }
        echo "</ul>";
    } else {
        echo "<pre>" . htmlspecialchars($response) . "</pre>";
    }
}
?>