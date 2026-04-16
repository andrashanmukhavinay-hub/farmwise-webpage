<?php
// Start the session to verify the user is logged in
session_start();
require 'db.php'; // Ensure your database connection file is included

// Check if the user is logged in; if not, redirect them back to the login page
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: farmer-login.php");
    exit();
}

// Fetch active alerts for the notification badge and banner
$user_id = $_SESSION['user_id'];
try {
    // 1. Fetch Badge Count
    $stmt_count = $conn->prepare("
        SELECT COUNT(*) as total FROM (
            SELECT id FROM announcements
            UNION ALL
            SELECT id FROM pest_diagnoses WHERE user_id = ?
        ) as combined
    ");
    $stmt_count->execute([$user_id]);
    $alert_data = $stmt_count->fetch(PDO::FETCH_ASSOC);
    $unread_count = $alert_data['total'] ?? 0;

    // 2. Fetch Latest Announcement for the Banner
    $stmt_msg = $conn->prepare("SELECT title, message FROM announcements ORDER BY created_at DESC LIMIT 1");
    $stmt_msg->execute();
    $latest_msg = $stmt_msg->fetch(PDO::FETCH_ASSOC);
    
    $display_title = $latest_msg ? $latest_msg['title'] : "Alert Announcement";
    $display_msg = $latest_msg ? $latest_msg['message'] : "No active alerts. Your farm is secure.";
} catch(PDOException $e) {
    $unread_count = 0;
    $display_title = "Alert Announcement";
    $display_msg = "Dashboard active.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>FarmWise Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        /* Base Reset */
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
        
        body { background-color: #f1f5f9; color: #1e293b; min-height: 100vh; overflow-x: hidden; }

        /* Full Screen App Container */
        .app-container { width: 100%; max-width: 1400px; margin: 0 auto; min-height: 100vh; display: flex; flex-direction: column; }

        /* Top Green Section (Header + Weather) */
        .top-bg { background: linear-gradient(135deg, #00b050 0%, #008a3e 100%); color: white; border-bottom-left-radius: 30px; border-bottom-right-radius: 30px; padding: 30px 50px 50px 50px; position: relative; z-index: 1; box-shadow: 0 10px 30px rgba(0, 176, 80, 0.15); }

        /* Header NavBar */
        .navbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .logo-area { display: flex; align-items: center; gap: 12px; font-size: 24px; font-weight: 800; letter-spacing: 0.5px; }
        .logo-area i { font-size: 28px; }
        .nav-icons { display: flex; gap: 20px; font-size: 22px; align-items: center; }
        
        /* UPDATED: Nav icon styling for redirection */
        .nav-icons a { color: white; text-decoration: none; position: relative; display: flex; align-items: center; transition: opacity 0.2s; }
        .nav-icons a:hover { opacity: 0.8; }
        .nav-icons i { cursor: pointer; }
        
        /* UPDATED: Badge styling to show numeric count */
        .notification-badge { 
            position: absolute; top: -5px; right: -8px; 
            background-color: #ef4444; border-radius: 50%; 
            width: 18px; height: 18px; font-size: 11px; 
            display: flex; align-items: center; justify-content: center; 
            border: 2px solid #00b050; font-weight: bold; 
        }

        /* Weather Widget - Desktop Wide Layout */
        .weather-widget { display: flex; justify-content: space-between; align-items: flex-end; background: rgba(255, 255, 255, 0.1); padding: 25px 35px; border-radius: 20px; backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.2); }
        .weather-left { display: flex; flex-direction: column; gap: 10px; }
        .weather-location { font-size: 15px; font-weight: 600; display: flex; align-items: center; gap: 8px; opacity: 0.9; text-transform: uppercase; letter-spacing: 1px; }
        .temp-display { display: flex; align-items: center; gap: 20px; }
        .temp-display i { font-size: 55px; color: #facc15; filter: drop-shadow(0 0 10px rgba(250, 204, 21, 0.3)); }
        .temp-text h2 { font-size: 48px; font-weight: 800; line-height: 1; }
        .temp-text p { font-size: 16px; font-weight: 500; opacity: 0.9; margin-top: 5px; }
        
        .weather-details { display: flex; gap: 30px; text-align: left; }
        .weather-stat { display: flex; flex-direction: column; gap: 5px; }
        .weather-stat span.label { font-size: 12px; opacity: 0.8; text-transform: uppercase; letter-spacing: 1px; }
        .weather-stat span.val { font-size: 18px; font-weight: 700; }

        /* Main Content Area */
        .main-content { padding: 0 50px 50px 50px; margin-top: -25px; position: relative; z-index: 10; flex: 1; }

        /* Top Actions (Alert + AI Button side by side on desktop) */
        .top-actions { display: grid; grid-template-columns: 1fr 1fr; gap: 25px; margin-bottom: 30px; }

        /* Alert Card */
        .alert-card { background: white; border-left: 5px solid #f97316; border-radius: 16px; padding: 20px 25px; display: flex; align-items: center; gap: 15px; box-shadow: 0 10px 25px rgba(0,0,0,0.04); margin-bottom: 0; text-decoration: none; color: inherit; transition: 0.3s; cursor: pointer; }
        .alert-card:hover { transform: translateY(-3px); box-shadow: 0 15px 30px rgba(0,0,0,0.08); }
        .alert-card i { color: #f97316; font-size: 24px; }
        .alert-text h4 { font-size: 15px; color: #9a3412; margin-bottom: 5px; font-weight: 700; transition: 0.3s; }
        .alert-text p { font-size: 13px; color: #475569; line-height: 1.5; transition: 0.3s; }

        /* AI Assistant Button */
        .ai-btn { display: flex; align-items: center; justify-content: space-between; background: linear-gradient(90deg, #10b981 0%, #059669 100%); color: white; padding: 20px 25px; border-radius: 16px; text-decoration: none; box-shadow: 0 10px 25px rgba(16, 185, 129, 0.25); margin-bottom: 0; transition: transform 0.2s, box-shadow 0.2s; }
        .ai-btn:hover { transform: translateY(-3px); box-shadow: 0 15px 30px rgba(16, 185, 129, 0.3); }
        .ai-btn-content { display: flex; align-items: center; gap: 18px; }
        .ai-icon-bg { background: rgba(255,255,255,0.25); width: 50px; height: 50px; border-radius: 14px; display: flex; justify-content: center; align-items: center; font-size: 24px; }
        .ai-text h3 { font-size: 18px; font-weight: 700; margin-bottom: 4px; }
        .ai-text p { font-size: 13px; opacity: 0.9; font-weight: 500; }
        .ai-btn > i { font-size: 18px; }

        /* Dashboard Grid Layout */
        .dashboard-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 25px; }

        /* Category Blocks */
        .category { background: white; padding: 25px; border-radius: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.03); }
        .category-title { font-size: 14px; font-weight: 800; color: #334155; margin-bottom: 15px; margin-left: 5px; text-transform: uppercase; letter-spacing: 1px; border-bottom: 2px solid #f1f5f9; padding-bottom: 10px; }

        /* Menu Cards */
        .menu-card { display: flex; align-items: center; justify-content: space-between; background: white; padding: 15px; border-radius: 14px; text-decoration: none; color: inherit; margin-bottom: 12px; border: 1px solid #f1f5f9; transition: all 0.2s ease; }
        .menu-card:last-child { margin-bottom: 0; }
        .menu-card:hover { border-color: #cbd5e1; transform: translateY(-3px); box-shadow: 0 8px 15px rgba(0,0,0,0.04); background: #f8fafc; }
        
        .menu-left { display: flex; align-items: center; gap: 15px; }
        .icon-box { width: 45px; height: 45px; border-radius: 12px; display: flex; justify-content: center; align-items: center; font-size: 20px; }
        .menu-text h4 { font-size: 15px; font-weight: 700; color: #1e293b; margin-bottom: 4px; }
        .menu-text p { font-size: 12px; color: #64748b; }
        .menu-card > i { color: #cbd5e1; font-size: 16px; transition: color 0.2s; }
        .menu-card:hover > i { color: #94a3b8; }

        /* Specific Icon Colors */
        .bg-blue { background: #eff6ff; color: #3b82f6; }
        .bg-green { background: #f0fdf4; color: #22c55e; }
        .bg-red { background: #fef2f2; color: #ef4444; }
        .bg-purple { background: #faf5ff; color: #a855f7; }
        .bg-teal { background: #f0fdfa; color: #14b8a6; }
        .bg-indigo { background: #eef2ff; color: #6366f1; }
        .bg-orange { background: #fff7ed; color: #f97316; }
        .bg-gray { background: #f8fafc; color: #64748b; }

        /* Mobile Responsiveness */
        @media (max-width: 768px) {
            .top-bg { padding: 25px 20px 40px 20px; border-radius: 0 0 25px 25px; }
            .navbar { margin-bottom: 20px; }
            .weather-widget { flex-direction: column; align-items: flex-start; gap: 20px; padding: 20px; }
            .weather-details { width: 100%; justify-content: space-between; }
            .main-content { padding: 0 20px 30px 20px; }
            .top-actions { grid-template-columns: 1fr; gap: 15px; margin-bottom: 25px; }
            .dashboard-grid { grid-template-columns: 1fr; gap: 20px; }
            .category { padding: 20px 15px; }
        }
    </style>
</head>
<body>

    <div class="app-container">
        
        <div class="top-bg">
            <div class="navbar">
                <div class="logo-area">
                    <i class="fa-solid fa-seedling"></i> FarmWise
                </div>
                <div class="nav-icons">
                    <a href="alerts.php">
                        <i class="fa-regular fa-bell">
                            <div class="notification-badge" id="unread-count-badge" style="display: <?php echo ($unread_count > 0) ? 'flex' : 'none'; ?>;">
                                <?php echo $unread_count; ?>
                            </div>
                        </i>
                    </a>
                    <a href="settings.php"><i class="fa-regular fa-circle-user"></i></a>
                </div>
            </div>

            <div class="weather-widget">
                <div class="weather-left">
                    <div class="weather-location">
                        <i class="fa-solid fa-location-dot"></i> <span id="loc-text">Locating you...</span>
                    </div>
                    <div class="temp-display">
                        <i id="weather-icon" class="fa-solid fa-sun"></i>
                        <div class="temp-text">
                            <h2 id="temp-text">--°C</h2>
                            <p id="condition-text">Fetching...</p>
                        </div>
                    </div>
                </div>
                
                <div class="weather-details">
                    <div class="weather-stat">
                        <span class="label">Humidity</span>
                        <span class="val" id="hum-text">--%</span>
                    </div>
                    <div class="weather-stat">
                        <span class="label">Wind</span>
                        <span class="val" id="wind-text">-- km/h</span>
                    </div>
                    <div class="weather-stat">
                        <span class="label">Rain</span>
                        <span class="val" id="rain-text">-- mm</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="main-content">
            
            <div class="top-actions">
                <a href="alerts.php" class="alert-card">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                    <div class="alert-text">
                        <h4 id="live-alert-title"><?php echo htmlspecialchars($display_title); ?></h4>
                        <p id="live-alert-text"><?php echo htmlspecialchars($display_msg); ?></p>
                    </div>
                </a>

                <a href="ai-assistant.php" class="ai-btn">
                    <div class="ai-btn-content">
                        <div class="ai-icon-bg">
                            <i class="fa-solid fa-robot"></i>
                        </div>
                        <div class="ai-text">
                            <h3>Ask AI Assistant</h3>
                            <p>Text, voice & image queries</p>
                        </div>
                    </div>
                    <i class="fa-solid fa-chevron-right"></i>
                </a>
            </div>

            <div class="dashboard-grid">
                <div class="category">
                    <div class="category-title">Advisory & Intelligence</div>
                    
                    <a href="weather.php" class="menu-card">
                        <div class="menu-left">
                            <div class="icon-box bg-blue"><i class="fa-solid fa-cloud-sun-rain"></i></div>
                            <div class="menu-text">
                                <h4>Weather Intelligence</h4>
                                <p>Forecast, alerts & farming advice</p>
                            </div>
                        </div>
                        <i class="fa-solid fa-chevron-right"></i>
                    </a>

                    <a href="crop-advisory.php" class="menu-card">
                        <div class="menu-left">
                            <div class="icon-box bg-green"><i class="fa-solid fa-leaf"></i></div>
                            <div class="menu-text">
                                <h4>Crop Advisory</h4>
                                <p>Sowing, fertilizer & growth management</p>
                            </div>
                        </div>
                        <i class="fa-solid fa-chevron-right"></i>
                    </a>

                    <a href="pest-disease.php" class="menu-card">
                        <div class="menu-left">
                            <div class="icon-box bg-red"><i class="fa-solid fa-bug"></i></div>
                            <div class="menu-text">
                                <h4>Pest & Disease Management</h4>
                                <p>Image diagnosis & treatment</p>
                            </div>
                        </div>
                        <i class="fa-solid fa-chevron-right"></i>
                    </a>
                </div>

                <div class="category">
                    <div class="category-title">Farm Planning & Insights</div>
                    
                    <a href="yield-prediction.php" class="menu-card">
                        <div class="menu-left">
                            <div class="icon-box bg-purple"><i class="fa-solid fa-chart-simple"></i></div>
                            <div class="menu-text">
                                <h4>Yield Prediction</h4>
                                <p>Data-driven harvest estimates</p>
                            </div>
                        </div>
                        <i class="fa-solid fa-chevron-right"></i>
                    </a>

                    <a href="resource-optimization.php" class="menu-card">
                        <div class="menu-left">
                            <div class="icon-box bg-teal"><i class="fa-solid fa-droplet"></i></div>
                            <div class="menu-text">
                                <h4>Resource Optimization</h4>
                                <p>Water, fertilizer & soil planning</p>
                            </div>
                        </div>
                        <i class="fa-solid fa-chevron-right"></i>
                    </a>
                </div>

                <div class="category">
                    <div class="category-title">Market & Financial</div>
                    
                    <a href="market-intelligence.php" class="menu-card">
                        <div class="menu-left">
                            <div class="icon-box bg-green"><i class="fa-solid fa-arrow-trend-up"></i></div>
                            <div class="menu-text">
                                <h4>Market Intelligence</h4>
                                <p>Prices, trends & demand forecasts</p>
                            </div>
                        </div>
                        <i class="fa-solid fa-chevron-right"></i>
                    </a>

                    <a href="reports-analytics.php" class="menu-card">
                        <div class="menu-left">
                            <div class="icon-box bg-indigo"><i class="fa-solid fa-file-invoice-dollar"></i></div>
                            <div class="menu-text">
                                <h4>Reports & Analytics</h4>
                                <p>Performance & financial insights</p>
                            </div>
                        </div>
                        <i class="fa-solid fa-chevron-right"></i>
                    </a>
                </div>

                <div class="category">
                    <div class="category-title">Farm Management</div>
                    
                    <a href="my-farm.php" class="menu-card">
                        <div class="menu-left">
                            <div class="icon-box bg-teal"><i class="fa-solid fa-house-chimney-window"></i></div>
                            <div class="menu-text">
                                <h4>My Farm</h4>
                                <p>Profile, crop history & land info</p>
                            </div>
                        </div>
                        <i class="fa-solid fa-chevron-right"></i>
                    </a>

                    <a href="field-monitoring.php" class="menu-card">
                        <div class="menu-left">
                            <div class="icon-box bg-green"><i class="fa-solid fa-satellite-dish"></i></div>
                            <div class="menu-text">
                                <h4>Field Monitoring</h4>
                                <p>Crop health & IoT integration</p>
                            </div>
                        </div>
                        <i class="fa-solid fa-chevron-right"></i>
                    </a>
                </div>

                <div class="category">
                    <div class="category-title">Communication & Support</div>
                    
                    <a href="alerts.php" class="menu-card">
                        <div class="menu-left">
                            <div class="icon-box bg-red"><i class="fa-regular fa-bell"></i></div>
                            <div class="menu-text">
                                <h4>Alerts & Notifications</h4>
                                <p>Weather, pest & market updates</p>
                            </div>
                        </div>
                        <i class="fa-solid fa-chevron-right"></i>
                    </a>

                    <a href="expert-connect.php" class="menu-card">
                        <div class="menu-left">
                            <div class="icon-box bg-purple"><i class="fa-regular fa-comments"></i></div>
                            <div class="menu-text">
                                <h4>Expert Connect</h4>
                                <p>Chat with agricultural experts</p>
                            </div>
                        </div>
                        <i class="fa-solid fa-chevron-right"></i>
                    </a>

                    <a href="community.php" class="menu-card">
                        <div class="menu-left">
                            <div class="icon-box bg-blue"><i class="fa-solid fa-users"></i></div>
                            <div class="menu-text">
                                <h4>Community Forum</h4>
                                <p>Posts, discussions & shares</p>
                            </div>
                        </div>
                        <i class="fa-solid fa-chevron-right"></i>
                    </a>
                </div>

                <div class="category">
                    <div class="category-title">Governance & Settings</div>
                    
                    <a href="govt-schemes.php" class="menu-card">
                        <div class="menu-left">
                            <div class="icon-box bg-orange"><i class="fa-solid fa-building-columns"></i></div>
                            <div class="menu-text">
                                <h4>Government Schemes</h4>
                                <p>Subsidies, loans & insurance</p>
                            </div>
                        </div>
                        <i class="fa-solid fa-chevron-right"></i>
                    </a>

                    <a href="settings.php" class="menu-card">
                        <div class="menu-left">
                            <div class="icon-box bg-gray"><i class="fa-solid fa-gear"></i></div>
                            <div class="menu-text">
                                <h4>Settings</h4>
                                <p>Profile, notifications & privacy</p>
                            </div>
                        </div>
                        <i class="fa-solid fa-chevron-right"></i>
                    </a>
                    
                    <a href="logout.php" class="menu-card">
                        <div class="menu-left">
                            <div class="icon-box bg-red"><i class="fa-solid fa-right-from-bracket"></i></div>
                            <div class="menu-text">
                                <h4>Logout</h4>
                                <p>Sign out of your account</p>
                            </div>
                        </div>
                        <i class="fa-solid fa-chevron-right"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script>
        // --- REAL-TIME ALERT UPDATER ---
        function pollAlerts() {
            fetch('check-alerts.php')
                .then(res => res.json())
                .then(data => {
                    // Update the red notification badge on the bell icon
                    const badge = document.getElementById('unread-count-badge');
                    if (data.count !== undefined) {
                        if (data.count > 0) {
                            badge.innerText = data.count;
                            badge.style.display = 'flex';
                        } else {
                            badge.style.display = 'none';
                        }
                    }

                    // Update the dynamic text in the Alert Banner
                    if (data.latest_title) {
                        document.getElementById('live-alert-title').innerText = data.latest_title;
                    }
                    if (data.latest_alert) {
                        document.getElementById('live-alert-text').innerText = data.latest_alert;
                    }
                })
                .catch(err => console.log('Live update paused.'));
        }
        
        // Polling runs every 10 seconds for real-time responsiveness
        setInterval(pollAlerts, 10000);

        // --- WEATHER WIDGET LOGIC ---
        async function getWeatherData(lat, lon) {
            try {
                const weatherURL = `https://api.open-meteo.com/v1/forecast?latitude=${lat}&longitude=${lon}&current=temperature_2m,relative_humidity_2m,precipitation,wind_speed_10m,weather_code,is_day&timezone=auto`;
                
                const response = await fetch(weatherURL);
                const data = await response.json();
                const current = data.current;

                document.getElementById('temp-text').innerText = Math.round(current.temperature_2m) + '°C';
                document.getElementById('hum-text').innerText = current.relative_humidity_2m + '%';
                document.getElementById('wind-text').innerText = Math.round(current.wind_speed_10m) + ' km/h';
                document.getElementById('rain-text').innerText = current.precipitation + ' mm';

                let condition = "Clear";
                let iconClass = "fa-solid fa-sun";
                if(current.weather_code >= 1 && current.weather_code <= 3) { condition = "Partly Cloudy"; iconClass = "fa-solid fa-cloud-sun"; }
                if(current.weather_code >= 51 && current.weather_code <= 67) { condition = "Raining"; iconClass = "fa-solid fa-cloud-rain"; }
                if(current.weather_code >= 71) { condition = "Snow"; iconClass = "fa-regular fa-snowflake"; }
                if(current.weather_code >= 95) { condition = "Thunderstorm"; iconClass = "fa-solid fa-cloud-bolt"; }
                
                document.getElementById('condition-text').innerText = condition;
                document.getElementById('weather-icon').className = iconClass;

                const geoURL = `https://api.bigdatacloud.net/data/reverse-geocode-client?latitude=${lat}&longitude=${lon}&localityLanguage=en`;
                const geoRes = await fetch(geoURL);
                const geoData = await geoRes.json();
                
                if(geoData.city) {
                    document.getElementById('loc-text').innerText = geoData.city + ', ' + geoData.principalSubdivision;
                } else {
                    document.getElementById('loc-text').innerText = "Location Found";
                }

            } catch (error) {
                console.error("Weather fetch error:", error);
                document.getElementById('loc-text').innerText = "Unable to load weather";
            }
        }

        window.addEventListener('load', () => {
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(
                    (position) => {
                        getWeatherData(position.coords.latitude, position.coords.longitude);
                    },
                    (error) => {
                        getWeatherData(18.5204, 73.8567);
                        document.getElementById('loc-text').innerText = "Pune, Maharashtra (Default)";
                    }
                );
            } else {
                getWeatherData(18.5204, 73.8567);
            }
        });
    </script>
</body>
</html>