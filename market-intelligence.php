<?php
session_start();
require 'db.php';
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: farmer-login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// --- FETCH NOTIFICATION COUNT (For Real-Time Badge) ---
try {
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
} catch(PDOException $e) {
    $unread_count = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Market Intelligence - FarmWise</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <script>
        if (localStorage.getItem('farmwise_theme') === 'dark') {
            document.documentElement.classList.add('dark-mode');
        }
    </script>

    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
        body { background-color: #f1f5f9; color: #1e293b; min-height: 100vh; overflow-x: hidden; transition: background-color 0.3s, color 0.3s; }

        .app-container { width: 100%; max-width: 1400px; margin: 0 auto; display: flex; flex-direction: column; min-height: 100vh; }

        /* Header (Blue Theme) */
        .header-bg { background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%); color: white; padding: 25px 50px; border-bottom-left-radius: 30px; border-bottom-right-radius: 30px; box-shadow: 0 10px 30px rgba(37, 99, 235, 0.15); transition: 0.3s; }
        .top-nav { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; }
        .back-btn { color: white; text-decoration: none; font-size: 18px; display: flex; align-items: center; gap: 10px; font-weight: 600; transition: 0.2s; }
        .back-btn:hover { opacity: 0.8; }
        
        /* Notification Badge */
        .bell-link { color:white; text-decoration:none; position: relative; transition: 0.2s;}
        .notification-badge { 
            position: absolute; top: -5px; right: -8px; 
            background-color: #ef4444; border-radius: 50%; 
            width: 16px; height: 16px; font-size: 10px; 
            display: flex; align-items: center; justify-content: center; 
            border: 2px solid #2563eb; font-weight: bold; color: white;
        }

        .header-content { display: flex; align-items: center; gap: 20px; }
        .icon-circle { width: 60px; height: 60px; background: rgba(255,255,255,0.2); border-radius: 50%; display: flex; justify-content: center; align-items: center; font-size: 30px; border: 2px solid rgba(255,255,255,0.4); }
        .header-text h1 { font-size: 28px; font-weight: 800; margin-bottom: 5px; }
        .header-text p { font-size: 15px; opacity: 0.9; }

        /* Main Layout */
        .main-wrapper { display: grid; grid-template-columns: 350px 1fr; gap: 30px; padding: 40px 50px; align-items: start; }

        /* Left Side: Form */
        .sidebar { position: sticky; top: 30px; }
        .form-card { background: white; padding: 30px; border-radius: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.03); border: 1px solid #f1f5f9; transition: 0.3s; }
        .form-card h3 { font-size: 18px; color: #1e293b; margin-bottom: 25px; display: flex; align-items: center; gap: 10px; transition: color 0.3s; }
        .form-card h3 i { color: #2563eb; }
        
        .form-group { margin-bottom: 20px; }
        .form-group label { display: flex; align-items: center; gap: 8px; font-size: 13px; font-weight: 700; color: #475569; margin-bottom: 8px; transition: color 0.3s; }
        .input-box { width: 100%; padding: 14px 15px; border: 1px solid #cbd5e1; border-radius: 12px; font-size: 14px; outline: none; background: #f8fafc; transition: 0.3s; color: inherit; }
        .input-box:focus { border-color: #2563eb; background: white; box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1); }

        .submit-btn { width: 100%; background: #2563eb; color: white; border: none; padding: 16px; border-radius: 14px; font-size: 16px; font-weight: 700; cursor: pointer; transition: 0.3s; box-shadow: 0 4px 15px rgba(37, 99, 235, 0.3); margin-top: 10px; }
        .submit-btn:hover { background: #1d4ed8; transform: translateY(-2px); }
        .submit-btn:disabled { background: #94a3b8; cursor: not-allowed; transform: none; box-shadow: none; }

        /* Right Side: Results Dashboard */
        .results-dashboard { display: flex; flex-direction: column; gap: 20px; }
        .empty-state, .loader-card { background: white; border-radius: 20px; padding: 80px 40px; text-align: center; border: 2px dashed #e2e8f0; color: #94a3b8; min-height: 500px; display: flex; flex-direction: column; justify-content: center; transition: 0.3s; }
        .loader-card { display: none; border-style: solid; }
        .loader-card i { font-size: 60px; color: #2563eb; margin-bottom: 20px; }

        #results-area { display: none; flex-direction: column; gap: 20px; }
        .section-title { font-size: 16px; font-weight: 800; color: #1e293b; margin: 10px 0; display: flex; align-items: center; gap: 10px; transition: color 0.3s; }
        .section-title i { color: #2563eb; }

        /* Price Cards */
        .price-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px; }
        .price-card { background: white; padding: 20px; border-radius: 16px; box-shadow: 0 4px 10px rgba(0,0,0,0.02); border: 1px solid #f1f5f9; transition: 0.3s; }
        .pc-top { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 15px; }
        .pc-crop { font-size: 16px; font-weight: 800; color: #1e293b; transition: color 0.3s; }
        .pc-unit { font-size: 12px; color: #64748b; font-weight: 600; }
        .pc-price { font-size: 24px; font-weight: 800; color: #1e293b; transition: color 0.3s; }
        
        .trend-badge { display: inline-flex; align-items: center; gap: 5px; padding: 4px 10px; border-radius: 8px; font-size: 12px; font-weight: 700; margin-top: 5px; }
        .trend-up { background: #dcfce7; color: #16a34a; }
        .trend-down { background: #fee2e2; color: #dc2626; }

        /* Charts */
        .chart-card { background: white; padding: 25px; border-radius: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.03); border: 1px solid #f1f5f9; transition: 0.3s; }
        .chart-container { width: 100%; height: 250px; position: relative; margin-bottom: 20px; }
        .insight-box { background: #eff6ff; border-left: 4px solid #3b82f6; padding: 15px; border-radius: 12px; font-size: 13px; color: #1e40af; display: flex; gap: 10px; transition: 0.3s; }

        /* Demand Cards */
        .demand-card { background: white; padding: 20px; border-radius: 16px; box-shadow: 0 4px 10px rgba(0,0,0,0.02); border: 1px solid #f1f5f9; margin-bottom: 15px; transition: 0.3s; }
        .dc-top { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; border-bottom: 1px solid #f1f5f9; padding-bottom: 10px; transition: border-color 0.3s; }
        .dc-crop { font-size: 18px; font-weight: 800; color: #1e293b; transition: color 0.3s; }
        .demand-badge { padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: 700; transition: 0.3s; }
        .demand-high { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .demand-med { background: #fef08a; color: #b45309; border: 1px solid #fde047; }
        .demand-low { background: #fee2e2; color: #dc2626; border: 1px solid #fecaca; }

        .dc-info { display: flex; gap: 15px; flex-direction: column; }
        .dc-row { display: flex; gap: 10px; align-items: flex-start; }
        .dc-text p { font-size: 11px; color: #64748b; font-weight: 600; text-transform: uppercase; margin-bottom: 2px; }
        .dc-text h5 { font-size: 14px; color: #334155; font-weight: 700; transition: color 0.3s; }
        .action-tag { display: inline-block; background: #fff7ed; color: #ea580c; padding: 4px 10px; border-radius: 8px; font-size: 12px; font-weight: 700; margin-top: 5px; border: 1px solid #ffedd5; }

        /* Best Time Card */
        .sell-card { background: linear-gradient(135deg, #16a34a 0%, #15803d 100%); color: white; padding: 25px; border-radius: 20px; box-shadow: 0 10px 25px rgba(22, 163, 74, 0.2); transition: 0.3s; }
        .sell-title { font-size: 18px; font-weight: 800; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
        .sell-item { background: rgba(255,255,255,0.15); padding: 15px; border-radius: 12px; margin-bottom: 12px; border: 1px solid rgba(255,255,255,0.2); display: flex; gap: 15px; align-items: flex-start; }

        /* Market Tip */
        .market-tip { background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 16px; padding: 20px; display: flex; gap: 15px; align-items: center; transition: 0.3s; }
        .tip-icon { width: 45px; height: 45px; background: #3b82f6; color: white; border-radius: 50%; display: flex; justify-content: center; align-items: center; font-size: 20px; flex-shrink: 0; box-shadow: 0 4px 10px rgba(59, 130, 246, 0.3); }
        .tip-text h4 { font-size: 16px; color: #1e40af; margin-bottom: 4px; font-weight: 800; }
        .tip-text p { font-size: 13px; color: #1d4ed8; line-height: 1.5; }

        /* =========================================
           GLOBAL DARK MODE STYLES
           ========================================= */
        html.dark-mode body { background-color: #121212; color: #f8fafc; }
        html.dark-mode .header-bg { background: linear-gradient(135deg, #1e3a8a 0%, #172554 100%); }
        html.dark-mode .notification-badge { border-color: #1e3a8a !important; }
        
        html.dark-mode .form-card,
        html.dark-mode .price-card,
        html.dark-mode .chart-card,
        html.dark-mode .demand-card,
        html.dark-mode .empty-state,
        html.dark-mode .loader-card { 
            background-color: #1e1e1e !important; 
            border-color: #333 !important; 
            box-shadow: none !important; 
        }

        html.dark-mode .input-box { background-color: #121212 !important; border-color: #333 !important; color: #f8fafc !important; }
        html.dark-mode .input-box:focus { border-color: #2563eb !important; }

        html.dark-mode .section-title, 
        html.dark-mode h3, html.dark-mode h4, html.dark-mode h5,
        html.dark-mode .pc-crop, html.dark-mode .pc-price,
        html.dark-mode .dc-crop { color: #f8fafc !important; }
        
        html.dark-mode label, html.dark-mode .pc-unit, html.dark-mode .dc-text p { color: #94a3b8 !important; }
        html.dark-mode .dc-top { border-bottom-color: #333 !important; }

        html.dark-mode .insight-box, html.dark-mode .market-tip { background-color: #1e3a8a !important; border-color: #3b82f6 !important; }
        html.dark-mode .insight-box span, html.dark-mode .tip-text h4, html.dark-mode .tip-text p { color: #bfdbfe !important; }
        
        html.dark-mode .demand-high { background-color: #064e3b !important; color: #6ee7b7 !important; border-color: #059669 !important; }
        html.dark-mode .demand-med { background-color: #422006 !important; color: #fbbf24 !important; border-color: #b45309 !important; }
        html.dark-mode .demand-low { background-color: #450a0a !important; color: #fca5a5 !important; border-color: #991b1b !important; }

        @media (max-width: 1024px) {
            .main-wrapper { grid-template-columns: 1fr; padding: 20px; gap: 20px; }
            .header-bg { padding: 20px; border-radius: 0 0 20px 20px; }
            .sidebar { position: static; }
        }
    </style>
</head>
<body>

    <div class="app-container">
        
        <div class="header-bg">
            <div class="top-nav">
                <a href="farmer-dashboard.php" class="back-btn"><i class="fa-solid fa-arrow-left"></i> Dashboard</a>
                <a href="alerts.php" class="bell-link">
                    <i class="fa-regular fa-bell" style="font-size: 20px;">
                        <div class="notification-badge" id="unread-count-badge" style="display: <?php echo ($unread_count > 0) ? 'flex' : 'none'; ?>;">
                            <?php echo $unread_count; ?>
                        </div>
                    </i>
                </a>
            </div>
            <div class="header-content">
                <div class="icon-circle"><i class="fa-solid fa-shop"></i></div>
                <div class="header-text">
                    <h1>Market Intelligence</h1>
                    <p>Live prices, trends, and demand forecasts</p>
                </div>
            </div>
        </div>

        <div class="main-wrapper">
            
            <div class="sidebar">
                <div class="form-card">
                    <h3><i class="fa-solid fa-map-location-dot"></i> Select Market Details</h3>
                    <form onsubmit="getMarketData(event)">
                        
                        <div class="form-group">
                            <label><i class="fa-solid fa-location-crosshairs"></i> Your Region / State</label>
                            <input type="text" id="region" class="input-box" placeholder="e.g. Punjab, Maharashtra" required autocomplete="off">
                        </div>

                        <div class="form-group">
                            <label><i class="fa-solid fa-seedling"></i> Crops to Monitor</label>
                            <input type="text" id="crops" class="input-box" placeholder="e.g. Wheat, Cotton, Maize" required autocomplete="off">
                        </div>

                        <button type="submit" class="submit-btn" id="submitBtn">Analyze Market</button>
                    </form>
                </div>
            </div>

            <div class="results-dashboard">
                
                <div id="empty-state" class="empty-state">
                    <i class="fa-solid fa-chart-line"></i>
                    <h3>Track Live Markets</h3>
                    <p>Enter your location and crops on the left to get instant price trends and AI selling forecasts.</p>
                </div>

                <div id="loader" class="loader-card">
                    <i class="fa-solid fa-microchip fa-beat-fade"></i>
                    <h3>Analyzing Market Data...</h3>
                    <p style="color: #64748b;">Scanning historical trends and local mandi demand.</p>
                </div>

                <div id="results-area">
                    
                    <div class="section-title"><i class="fa-solid fa-indian-rupee-sign"></i> Current Market Prices</div>
                    <div class="price-grid" id="res-prices"></div>

                    <div class="section-title"><i class="fa-solid fa-chart-area"></i> Price Trends / History</div>
                    <div class="chart-card" id="chartCard">
                        <h4 id="chart-title" style="margin-bottom:15px; color:inherit;">--</h4>
                        <div class="chart-container">
                            <canvas id="priceChart"></canvas>
                        </div>
                        <div class="chart-container">
                            <canvas id="volumeChart"></canvas>
                        </div>
                        <div class="insight-box">
                            <i class="fa-solid fa-circle-info" style="margin-top:2px;"></i>
                            <span id="chart-insight">--</span>
                        </div>
                    </div>

                    <div class="section-title"><i class="fa-solid fa-bullseye"></i> Demand Forecast</div>
                    <div id="res-forecast"></div>

                    <div class="sell-card">
                        <div class="sell-title"><i class="fa-solid fa-clock"></i> Best Time to Sell</div>
                        <div id="res-sell-times"></div>
                    </div>

                    <div class="market-tip">
                        <div class="tip-icon"><i class="fa-solid fa-lightbulb"></i></div>
                        <div class="tip-text">
                            <h4>Market Tip</h4>
                            <p id="res-tip">--</p>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <script>
        let priceChartInstance = null;
        let volumeChartInstance = null;

        function getMarketData(e) {
            e.preventDefault();

            document.getElementById('empty-state').style.display = 'none';
            document.getElementById('results-area').style.display = 'none';
            document.getElementById('loader').style.display = 'flex';
            
            const btn = document.getElementById('submitBtn');
            btn.disabled = true;
            btn.innerText = 'Analyzing...';

            const formData = new FormData();
            formData.append('region', document.getElementById('region').value);
            formData.append('crops', document.getElementById('crops').value);

            fetch('market-api.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    alert(data.error);
                    location.reload();
                    return;
                }
                buildUI(data);
            })
            .catch(error => {
                alert('Error connecting to AI. Please try again.');
                location.reload();
            });
        }

        function buildUI(data) {
            // Price Cards
            const pricesHtml = data.current_prices.map(item => {
                const isUp = item.trend.toLowerCase().includes('up');
                const badgeClass = isUp ? 'trend-up' : 'trend-down';
                const icon = isUp ? 'fa-arrow-trend-up' : 'fa-arrow-trend-down';
                return `
                    <div class="price-card">
                        <div class="pc-top">
                            <div>
                                <div class="pc-crop">${item.crop}</div>
                                <div class="pc-unit">${item.unit}</div>
                            </div>
                            <div class="pc-price">₹${item.price}</div>
                        </div>
                        <div class="trend-badge ${badgeClass}">
                            <i class="fa-solid ${icon}"></i> ${item.change}
                        </div>
                    </div>
                `;
            }).join('');
            document.getElementById('res-prices').innerHTML = pricesHtml;

            // Chart Configuration
            const chartData = data.chart_data;
            document.getElementById('chart-title').innerText = `${chartData.crop_name} - 6 Month Trend`;
            document.getElementById('chart-insight').innerText = chartData.insight;

            if (priceChartInstance) priceChartInstance.destroy();
            if (volumeChartInstance) volumeChartInstance.destroy();

            const isDark = document.documentElement.classList.contains('dark-mode');
            const textColor = isDark ? '#94a3b8' : '#64748b';
            const gridColor = isDark ? '#333' : '#f1f5f9';

            // Price Chart
            const ctxPrice = document.getElementById('priceChart').getContext('2d');
            priceChartInstance = new Chart(ctxPrice, {
                type: 'line',
                data: {
                    labels: chartData.labels,
                    datasets: [{
                        label: 'Price (₹)',
                        data: chartData.prices,
                        borderColor: '#2563eb',
                        backgroundColor: 'rgba(37, 99, 235, 0.1)',
                        borderWidth: 3,
                        fill: true,
                        tension: 0.4,
                        pointBackgroundColor: '#2563eb'
                    }]
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: { 
                        y: { ticks: { color: textColor }, grid: { color: gridColor } },
                        x: { ticks: { color: textColor }, grid: { display: false } }
                    }
                }
            });

            // Volume Chart
            const ctxVol = document.getElementById('volumeChart').getContext('2d');
            volumeChartInstance = new Chart(ctxVol, {
                type: 'bar',
                data: {
                    labels: chartData.labels,
                    datasets: [{
                        label: 'Market Volume',
                        data: chartData.volumes,
                        backgroundColor: '#22c55e',
                        borderRadius: 4
                    }]
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: { 
                        y: { ticks: { color: textColor }, grid: { color: gridColor } },
                        x: { ticks: { color: textColor }, grid: { display: false } }
                    }
                }
            });

            // Demand Forecast
            const forecastHtml = data.demand_forecast.map(item => {
                let demandClass = 'demand-med';
                if(item.demand_level.toLowerCase().includes('high')) demandClass = 'demand-high';
                if(item.demand_level.toLowerCase().includes('low')) demandClass = 'demand-low';
                
                return `
                    <div class="demand-card">
                        <div class="dc-top">
                            <div class="dc-crop">${item.crop}</div>
                            <div class="demand-badge ${demandClass}">${item.demand_level}</div>
                        </div>
                        <div class="dc-info">
                            <div class="dc-row">
                                <i class="fa-solid fa-chart-line"></i>
                                <div class="dc-text"><p>Market Drivers</p><h5>${item.drivers}</h5></div>
                            </div>
                            <div class="dc-row">
                                <i class="fa-solid fa-hand-holding-dollar"></i>
                                <div class="dc-text"><p>Suggested Action</p>
                                    <div class="action-tag">${item.best_action}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            }).join('');
            document.getElementById('res-forecast').innerHTML = forecastHtml;

            // Best Time to Sell
            const sellHtml = data.best_time_to_sell.map(item => `
                <div class="sell-item">
                    <i class="fa-regular fa-clock"></i>
                    <div>
                        <h4>${item.timing}</h4>
                        <p>${item.advice}</p>
                    </div>
                </div>
            `).join('');
            document.getElementById('res-sell-times').innerHTML = sellHtml;

            document.getElementById('res-tip').innerText = data.market_tip;

            document.getElementById('loader').style.display = 'none';
            document.getElementById('results-area').style.display = 'flex';
            
            btn.disabled = false;
            btn.innerText = 'Refresh Market Data';
        }

        // Notification Badge Real-time Update
        function updateBadge() {
            fetch('check-alerts.php')
                .then(res => res.json())
                .then(data => {
                    const badge = document.getElementById('unread-count-badge');
                    if (badge && data.count !== undefined) {
                        if (data.count > 0) {
                            badge.innerText = data.count;
                            badge.style.display = 'flex';
                        } else {
                            badge.style.display = 'none';
                        }
                    }
                }).catch(e => console.log('Silent alert failure'));
        }
        setInterval(updateBadge, 10000);
    </script>
</body>
</html>