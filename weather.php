<?php
session_start();
require 'db.php';

// 1. Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: farmer-login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$api_key = "c69c3a7ef3fa6cd68abdbd7fc08ce1db"; // Your Live API Key

// =====================================================================
// STEP 1: SMART GEOLOCATION ROUTER
// =====================================================================
if (!isset($_GET['lat']) && !isset($_GET['lon']) && !isset($_GET['fallback'])) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Locating... FarmWise</title>
        <style>
            body { background: linear-gradient(135deg, #059669 0%, #047857 100%); color: white; display: flex; flex-direction: column; justify-content: center; align-items: center; height: 100vh; font-family: -apple-system, sans-serif; margin: 0;}
            .loader-circle { border: 4px solid rgba(255,255,255,0.2); border-top: 4px solid white; border-radius: 50%; width: 50px; height: 50px; animation: spin 1s linear infinite; margin-bottom: 25px; }
            h2 { font-size: 20px; font-weight: 700; margin-bottom: 10px; }
            p { font-size: 14px; opacity: 0.8; }
            @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        </style>
    </head>
    <body>
        <div class="loader-circle"></div>
        <h2>Finding your farm's location...</h2>
        <p>Connecting to live weather satellites</p>
        <script>
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(
                    function(position) {
                        window.location.href = "weather.php?lat=" + position.coords.latitude + "&lon=" + position.coords.longitude;
                    },
                    function(error) {
                        window.location.href = "weather.php?fallback=true";
                    },
                    { timeout: 10000 }
                );
            } else {
                window.location.href = "weather.php?fallback=true";
            }
        </script>
    </body>
    </html>
    <?php
    exit(); 
}

// =====================================================================
// STEP 2: FETCH REAL WEATHER DATA WITH ERROR CATCHING
// =====================================================================

$temp = "--";
$condition = "Loading...";
$humidity = "--";
$wind_speed = "--";
$rain_risk = "--";
$weather_icon = "fa-cloud-sun";
$bg_icon = "fa-cloud";
$location_name = "Unknown Location";
$daily_forecasts = [];

function mapWeatherIcon($api_icon_code) {
    $icon_map = [
        '01d' => 'fa-sun', '01n' => 'fa-moon', '02d' => 'fa-cloud-sun', '02n' => 'fa-cloud-moon',
        '03d' => 'fa-cloud', '03n' => 'fa-cloud', '04d' => 'fa-cloud', '04n' => 'fa-cloud',
        '09d' => 'fa-cloud-rain', '09n' => 'fa-cloud-rain', '10d' => 'fa-cloud-showers-heavy', '10n' => 'fa-cloud-showers-heavy',
        '11d' => 'fa-bolt', '11n' => 'fa-bolt', '13d' => 'fa-snowflake', '13n' => 'fa-snowflake',
        '50d' => 'fa-smog', '50n' => 'fa-smog'
    ];
    return $icon_map[$api_icon_code] ?? 'fa-cloud-sun';
}

if (isset($_GET['lat']) && isset($_GET['lon'])) {
    $lat = $_GET['lat'];
    $lon = $_GET['lon'];
    $current_url = "https://api.openweathermap.org/data/2.5/weather?lat={$lat}&lon={$lon}&appid={$api_key}&units=metric";
    $forecast_url = "https://api.openweathermap.org/data/2.5/forecast?lat={$lat}&lon={$lon}&appid={$api_key}&units=metric";
} else {
    $stmt = $conn->prepare("SELECT location FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    $fallback_loc = !empty($user['location']) ? $user['location'] : "Bengaluru";
    $city_encoded = urlencode($fallback_loc);
    
    $current_url = "https://api.openweathermap.org/data/2.5/weather?q={$city_encoded}&appid={$api_key}&units=metric";
    $forecast_url = "https://api.openweathermap.org/data/2.5/forecast?q={$city_encoded}&appid={$api_key}&units=metric";
}

// A. Fetch Current Weather
$ch = curl_init($current_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$current_res = curl_exec($ch);
$curl_error = curl_error($ch);
curl_close($ch);

if ($curl_error) {
    $condition = "cURL Error: " . $curl_error;
} else {
    $current_data = json_decode($current_res, true);

    if (isset($current_data['cod']) && $current_data['cod'] == 200) {
        $location_name = $current_data['name']; 
        $temp = round($current_data['main']['temp']);
        $condition = ucwords($current_data['weather'][0]['description']);
        $humidity = $current_data['main']['humidity'];
        $wind_speed = round($current_data['wind']['speed'] * 3.6); 
        
        $api_icon = $current_data['weather'][0]['icon'];
        $weather_icon = mapWeatherIcon($api_icon);
        $bg_icon = ($temp > 28) ? 'fa-sun' : 'fa-cloud';
        
        $rain_risk = ($humidity > 80 || strpos(strtolower($condition), 'rain') !== false) ? rand(70, 100) . '%' : rand(0, 30) . '%';
    } else {
        // THIS WILL SHOW YOU THE EXACT API ERROR
        $condition = "API Error: " . (isset($current_data['message']) ? $current_data['message'] : "Unknown Error");
    }
}

// B. Fetch 5-Day Forecast
$ch2 = curl_init($forecast_url);
curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch2, CURLOPT_SSL_VERIFYPEER, false);
$forecast_res = curl_exec($ch2);
curl_close($ch2);

$forecast_data = json_decode($forecast_res, true);

if (isset($forecast_data['cod']) && $forecast_data['cod'] == "200") {
    foreach ($forecast_data['list'] as $reading) {
        if (strpos($reading['dt_txt'], '12:00:00') !== false) {
            $daily_forecasts[] = [
                'day' => date('D', strtotime($reading['dt_txt'])),
                'temp' => round($reading['main']['temp']),
                'icon' => mapWeatherIcon($reading['weather'][0]['icon'])
            ];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Weather Intelligence - FarmWise</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
        body { background-color: #f1f5f9; color: #1e293b; min-height: 100vh; transition: 0.3s; overflow-x: hidden; }
        .app-container { width: 100%; max-width: 1400px; margin: 0 auto; min-height: 100vh; display: flex; flex-direction: column; background: #f8fafc; transition: 0.3s; }
        .header { background: linear-gradient(135deg, #059669 0%, #047857 100%); color: white; padding: 25px 30px; display: flex; align-items: center; justify-content: space-between; border-bottom-left-radius: 25px; border-bottom-right-radius: 25px; box-shadow: 0 10px 20px rgba(5, 150, 105, 0.15); z-index: 10; }
        .header-left { display: flex; align-items: center; gap: 15px; }
        .back-btn { color: white; text-decoration: none; font-size: 20px; transition: 0.2s; }
        .back-btn:hover { transform: translateX(-3px); }
        .header-title h1 { font-size: 20px; font-weight: 800; }
        .content { padding: 30px; display: flex; flex-direction: column; gap: 25px; flex: 1; }
        .weather-hero { background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); border-radius: 24px; padding: 30px; color: white; text-align: center; box-shadow: 0 15px 30px rgba(37, 99, 235, 0.2); position: relative; overflow: hidden; }
        .wh-bg-icon { position: absolute; right: -20px; top: -20px; font-size: 150px; opacity: 0.1; }
        .wh-location { font-size: 14px; font-weight: 700; margin-bottom: 10px; display: flex; align-items: center; justify-content: center; gap: 8px; text-transform: uppercase; letter-spacing: 1px; color: rgba(255,255,255,0.9);}
        .wh-temp { font-size: 64px; font-weight: 800; margin: 10px 0; line-height: 1; display: flex; justify-content: center; align-items: center; gap: 15px;}
        .wh-condition { font-size: 16px; font-weight: 600; margin-bottom: 25px; color: #fca5a5; /* Red color for error visibility */ }
        .weather-details { display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; background: rgba(255,255,255,0.15); padding: 20px; border-radius: 16px; backdrop-filter: blur(10px); }
        .wd-item { text-align: center; }
        .wd-item i { font-size: 20px; margin-bottom: 8px; opacity: 0.9; }
        .wd-item p { font-size: 12px; opacity: 0.8; margin-bottom: 4px; text-transform: uppercase; font-weight: 600;}
        .wd-item h4 { font-size: 16px; font-weight: 700; }
        .advice-card { background: #f0fdf4; border: 2px solid #10b981; border-radius: 20px; padding: 25px; display: flex; gap: 20px; align-items: flex-start; transition: 0.3s;}
        .ac-icon { width: 50px; height: 50px; background: #10b981; color: white; border-radius: 14px; display: flex; justify-content: center; align-items: center; font-size: 24px; flex-shrink: 0; }
        .ac-text h3 { font-size: 18px; color: #064e3b; margin-bottom: 8px; }
        .ac-text p { font-size: 14px; color: #166534; line-height: 1.5; }
        .section-title { font-size: 18px; font-weight: 800; color: #1e293b; margin-bottom: 15px; transition: 0.3s;}
        .forecast-scroll { display: flex; gap: 15px; overflow-x: auto; padding-bottom: 15px; scrollbar-width: none; }
        .forecast-scroll::-webkit-scrollbar { display: none; }
        .fc-card { background: white; border: 1px solid #e2e8f0; border-radius: 16px; padding: 20px; min-width: 120px; text-align: center; flex-shrink: 0; transition: 0.3s; box-shadow: 0 4px 10px rgba(0,0,0,0.02);}
        .fc-day { font-size: 14px; font-weight: 700; color: #64748b; margin-bottom: 10px; }
        .fc-icon { font-size: 28px; margin-bottom: 10px; }
        .fc-temps { font-size: 16px; font-weight: 800; color: #1e293b; }
        body.dark-mode { background-color: #121212; }
        body.dark-mode .app-container { background-color: #121212; }
        body.dark-mode .fc-card { background-color: #1e1e1e; border-color: #333; }
        body.dark-mode .advice-card { background-color: #022c22; border-color: #065f46; }
        body.dark-mode .ac-text h3 { color: #6ee7b7; }
        body.dark-mode .ac-text p { color: #a7f3d0; }
        body.dark-mode .section-title, body.dark-mode .fc-temps { color: #f8fafc; }
        @media (max-width: 768px) {
            .header { border-radius: 0 0 20px 20px; padding: 20px; }
            .content { padding: 20px; }
            .weather-hero { padding: 25px; }
            .weather-details { grid-template-columns: 1fr 1fr 1fr; gap: 10px; padding: 15px; }
            .wd-item h4 { font-size: 14px; }
            .advice-card { flex-direction: column; gap: 15px; }
        }
    </style>
</head>
<body>

<div class="app-container">
    <div class="header">
        <div class="header-left">
            <a href="farmer-dashboard.php" class="back-btn"><i class="fa-solid fa-arrow-left"></i></a>
            <div class="header-title">
                <h1>Weather Intelligence</h1>
            </div>
        </div>
        <i class="fa-solid fa-cloud-sun" style="font-size: 22px; opacity: 0.8;"></i>
    </div>

    <div class="content">
        <div class="weather-hero">
            <i class="fa-solid <?php echo $bg_icon; ?> wh-bg-icon"></i>
            <div class="wh-location">
                <i class="fa-solid fa-location-crosshairs"></i> <?php echo htmlspecialchars($location_name); ?>
            </div>
            <div class="wh-temp">
                <i class="fa-solid <?php echo $weather_icon; ?>" style="font-size: 40px; opacity: 0.9;"></i>
                <?php echo $temp; ?>°C
            </div>
            
            <div class="wh-condition"><?php echo $condition; ?></div>
            
            <div class="weather-details">
                <div class="wd-item">
                    <i class="fa-solid fa-droplet"></i>
                    <p>Humidity</p>
                    <h4><?php echo $humidity; ?>%</h4>
                </div>
                <div class="wd-item">
                    <i class="fa-solid fa-wind"></i>
                    <p>Wind</p>
                    <h4><?php echo $wind_speed; ?> km/h</h4>
                </div>
                <div class="wd-item">
                    <i class="fa-solid fa-umbrella"></i>
                    <p>Rain Risk</p>
                    <h4><?php echo $rain_risk; ?></h4>
                </div>
            </div>
        </div>

        <div class="advice-card">
            <div class="ac-icon"><i class="fa-solid fa-leaf"></i></div>
            <div class="ac-text">
                <h3>Smart Farming Advice</h3>
                <p>
                    <?php 
                    if(strpos(strtolower($condition), 'rain') !== false || (is_numeric($humidity) && $humidity > 85)) {
                        echo "<strong>Rain Detected:</strong> Delay irrigation and do not apply fertilizers or pesticides as they will wash away.";
                    } elseif(is_numeric($temp) && $temp > 32) {
                        echo "<strong>High Temperature Alert:</strong> Avoid spraying pesticides during midday heat to prevent chemical burn on leaves. Ensure crops are adequately irrigated this evening.";
                    } elseif(is_numeric($wind_speed) && $wind_speed > 20) {
                        echo "<strong>High Winds:</strong> Avoid spraying chemicals to prevent drift. Secure loose equipment around the farm.";
                    } else {
                        echo "<strong>Ideal Conditions:</strong> Weather is currently stable. It is a good time for outdoor farm activities, including fertilizer application and weeding.";
                    }
                    ?>
                </p>
            </div>
        </div>

        <div>
            <h3 class="section-title">5-Day Forecast</h3>
            <div class="forecast-scroll">
                <?php if(!empty($daily_forecasts)): ?>
                    <?php foreach($daily_forecasts as $day): ?>
                    <div class="fc-card">
                        <div class="fc-day"><?php echo $day['day']; ?></div>
                        <i class="fa-solid <?php echo $day['icon']; ?> fc-icon" style="color: <?php echo ($day['temp'] > 28) ? '#eab308' : '#3b82f6'; ?>;"></i>
                        <div class="fc-temps"><?php echo $day['temp']; ?>°</div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="color: #64748b; font-size: 14px;">Forecast data unavailable.</p>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>

<script>
    window.onload = function() {
        const savedTheme = localStorage.getItem('farmwise_theme');
        if (savedTheme === 'dark') {
            document.body.classList.add('dark-mode');
        }
    };
</script>

</body>
</html>