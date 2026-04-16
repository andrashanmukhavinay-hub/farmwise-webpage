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
        SELECT (SELECT COUNT(*) FROM announcements) + 
               (SELECT COUNT(*) FROM pest_diagnoses WHERE user_id = ?) as total_unread
    ");
    $stmt_count->execute([$user_id]);
    $alert_data = $stmt_count->fetch(PDO::FETCH_ASSOC);
    $unread_count = $alert_data['total_unread'] ?? 0;
} catch(PDOException $e) {
    $unread_count = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Yield Prediction - FarmWise</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <script>
        if (localStorage.getItem('farmwise_theme') === 'dark') {
            document.documentElement.classList.add('dark-mode');
        }
    </script>

    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
        body { background-color: #f1f5f9; color: #1e293b; min-height: 100vh; overflow-x: hidden; transition: background-color 0.3s, color 0.3s; }

        .app-container { width: 100%; max-width: 1400px; margin: 0 auto; display: flex; flex-direction: column; min-height: 100vh; }

        /* Header (Purple Theme) */
        .header-bg { background: linear-gradient(135deg, #9333ea 0%, #6b21a8 100%); color: white; padding: 25px 50px; border-bottom-left-radius: 30px; border-bottom-right-radius: 30px; box-shadow: 0 10px 30px rgba(147, 51, 234, 0.15); transition: 0.3s; }
        .top-nav { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; }
        .back-btn { color: white; text-decoration: none; font-size: 18px; display: flex; align-items: center; gap: 10px; font-weight: 600; transition: 0.2s; }
        .back-btn:hover { opacity: 0.8; }
        
        /* Notification Badge */
        .bell-link { color:white; text-decoration:none; position: relative; transition: 0.2s;}
        .notification-badge { 
            position: absolute; top: -5px; right: -8px; 
            background-color: #ef4444; border-radius: 50%; 
            width: 18px; height: 18px; font-size: 10px; 
            display: flex; align-items: center; justify-content: center; 
            border: 2px solid #9333ea; font-weight: bold; color: white;
        }

        .header-content { display: flex; align-items: center; gap: 20px; }
        .icon-circle { width: 60px; height: 60px; background: rgba(255,255,255,0.2); border-radius: 50%; display: flex; justify-content: center; align-items: center; font-size: 30px; border: 2px solid rgba(255,255,255,0.4); }
        .header-text h1 { font-size: 28px; font-weight: 800; letter-spacing: 0.5px; margin-bottom: 5px; }
        .header-text p { font-size: 15px; opacity: 0.9; }

        /* Main Layout */
        .main-wrapper { display: grid; grid-template-columns: 400px 1fr; gap: 30px; padding: 40px 50px; align-items: start; flex: 1; }

        /* Left Side: Form */
        .sidebar { position: sticky; top: 30px; }
        .form-card { background: white; padding: 30px; border-radius: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.03); border: 1px solid #f1f5f9; transition: 0.3s; }
        .form-card h3 { font-size: 18px; color: #1e293b; margin-bottom: 25px; display: flex; align-items: center; gap: 10px; transition: color 0.3s; }
        
        .form-group { margin-bottom: 20px; }
        .form-group label { display: flex; align-items: center; gap: 8px; font-size: 13px; font-weight: 700; color: #475569; margin-bottom: 8px; transition: color 0.3s; }
        .input-box { width: 100%; padding: 14px 15px; border: 1px solid #cbd5e1; border-radius: 12px; font-size: 14px; outline: none; background: #f8fafc; transition: 0.3s; color: inherit; }
        .input-box:focus { border-color: #9333ea; background: white; }

        .submit-btn { width: 100%; background: #9333ea; color: white; border: none; padding: 16px; border-radius: 14px; font-size: 16px; font-weight: 700; cursor: pointer; transition: 0.3s; box-shadow: 0 4px 15px rgba(147, 51, 234, 0.3); margin-top: 10px; }
        .submit-btn:hover { background: #7e22ce; transform: translateY(-2px); }
        .submit-btn:disabled { background: #94a3b8; cursor: not-allowed; transform: none; box-shadow: none; }

        /* Right Side: Results */
        .results-dashboard { display: flex; flex-direction: column; gap: 20px; }
        .empty-state, .loader-card { background: white; border-radius: 20px; padding: 80px 40px; text-align: center; border: 2px dashed #e2e8f0; color: #94a3b8; min-height: 500px; display: flex; flex-direction: column; justify-content: center; align-items: center; transition: 0.3s; }
        .loader-card { display: none; border-style: solid; }
        .loader-card i { font-size: 60px; color: #9333ea; margin-bottom: 20px; }

        #results-area { display: none; flex-direction: column; gap: 20px; }

        .yield-hero { background: linear-gradient(135deg, #a855f7 0%, #7e22ce 100%); color: white; border-radius: 24px; padding: 40px 30px; text-align: center; box-shadow: 0 10px 30px rgba(147, 51, 234, 0.2); }
        .yield-hero h1 { font-size: 60px; font-weight: 800; line-height: 1; margin-bottom: 5px; }

        .stats-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .stat-card { background: white; padding: 25px; border-radius: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.02); border: 1px solid #f1f5f9; display: flex; align-items: center; gap: 20px; transition: 0.3s; }
        .score-circle { width: 70px; height: 70px; border-radius: 50%; border: 6px solid #22c55e; display: flex; justify-content: center; align-items: center; font-size: 22px; font-weight: 800; color: #1e293b; transition: 0.3s;}

        .progress-card, .comp-card { background: white; padding: 25px; border-radius: 20px; border: 1px solid #f1f5f9; transition: 0.3s; }
        .prog-track, .bar-track { width: 100%; height: 12px; background: #f1f5f9; border-radius: 10px; overflow: hidden; transition: 0.3s;}
        .prog-fill, .bar-fill { height: 100%; transition: width 1s ease-in-out; }

        .rec-card { background: white; border-radius: 16px; padding: 20px; border: 1px solid #f1f5f9; display: flex; gap: 15px; transition: 0.3s; }
        .rec-icon { width: 40px; height: 40px; background: #f3e8ff; color: #9333ea; border-radius: 10px; display: flex; justify-content: center; align-items: center; font-size: 18px; flex-shrink: 0; }

        .pro-tip { background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 16px; padding: 20px; display: flex; gap: 15px; transition: 0.3s; }

        /* =========================================
           GLOBAL DARK MODE STYLES
           ========================================= */
        html.dark-mode body { background-color: #121212; color: #f8fafc; }
        html.dark-mode .header-bg { background: linear-gradient(135deg, #3b0764 0%, #1e1b4b 100%); }
        html.dark-mode .notification-badge { border-color: #3b0764 !important; }

        html.dark-mode .form-card, 
        html.dark-mode .stat-card,
        html.dark-mode .progress-card, 
        html.dark-mode .comp-card, 
        html.dark-mode .rec-card,
        html.dark-mode .empty-state, 
        html.dark-mode .loader-card { 
            background-color: #1e1e1e !important; 
            border-color: #333 !important; 
            box-shadow: none !important; 
        }

        html.dark-mode .input-box { background-color: #121212 !important; border-color: #333 !important; color: #f8fafc !important; }
        html.dark-mode .input-box:focus { border-color: #9333ea !important; }

        html.dark-mode h3, html.dark-mode .comp-title, html.dark-mode .rec-text h4, html.dark-mode .score-circle { color: #f8fafc !important; }
        html.dark-mode label, html.dark-mode .prog-desc, html.dark-mode .rec-text p, html.dark-mode .stat-card h4 { color: #94a3b8 !important; }

        html.dark-mode .prog-track, html.dark-mode .bar-track, html.dark-mode .score-circle { background-color: #121212 !important; border-color: #333 !important; }
        html.dark-mode .rec-item { background-color: #121212 !important; }
        html.dark-mode .rec-icon { background-color: #3b0764 !important; color: #d8b4fe !important; }

        html.dark-mode .pro-tip { background-color: #064e3b !important; border-color: #065f46 !important; }
        html.dark-mode .tip-text h4 { color: #6ee7b7 !important; }
        html.dark-mode .tip-text p { color: #d1fae5 !important; }

        @media (max-width: 1024px) {
            .main-wrapper { grid-template-columns: 1fr; padding: 20px; }
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
                <div class="icon-circle"><i class="fa-solid fa-chart-pie"></i></div>
                <div class="header-text">
                    <h1>Yield Prediction</h1>
                    <p>AI harvest estimates based on field data</p>
                </div>
            </div>
        </div>

        <div class="main-wrapper">
            <div class="sidebar">
                <div class="form-card">
                    <h3><i class="fa-solid fa-seedling" style="color:#9333ea;"></i> Field Parameters</h3>
                    <form onsubmit="getPrediction(event)">
                        <div class="form-group">
                            <label><i class="fa-solid fa-leaf"></i> Crop Type</label>
                            <input type="text" id="crop" class="input-box" placeholder="e.g. Wheat" required autocomplete="off">
                        </div>
                        <div class="form-group">
                            <label><i class="fa-solid fa-ruler-combined"></i> Area (Acres)</label>
                            <input type="number" id="area" step="0.1" class="input-box" placeholder="e.g. 5.0" required>
                        </div>
                        <div class="form-group">
                            <label><i class="fa-solid fa-mound"></i> Soil Condition</label>
                            <select id="soil" class="input-box" required>
                                <option value="Healthy">Healthy / Nutrient Rich</option>
                                <option value="Moderate">Moderate / Needs Treatment</option>
                                <option value="Poor">Poor / Depleted</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label><i class="fa-solid fa-droplet"></i> Irrigation</label>
                            <select id="water" class="input-box" required>
                                <option value="Drip">Drip Irrigation</option>
                                <option value="Flood">Flood / Manual</option>
                                <option value="Rainfed">Rainfed Only</option>
                            </select>
                        </div>
                        <button type="submit" class="submit-btn" id="submitBtn">Predict Yield</button>
                    </form>
                </div>
            </div>

            <div class="results-dashboard">
                <div id="empty-state" class="empty-state">
                    <i class="fa-solid fa-chart-line"></i>
                    <h3>Awaiting Data</h3>
                    <p>Enter your field parameters on the left to calculate your predicted harvest.</p>
                </div>

                <div id="loader" class="loader-card">
                    <i class="fa-solid fa-microchip fa-beat-fade"></i>
                    <h3>Generating Prediction...</h3>
                    <p>Analyzing soil health and resource patterns.</p>
                </div>

                <div id="results-area" style="display: none;">
                    <div class="yield-hero">
                        <p>Expected Yield</p>
                        <h1 id="res-yield">--</h1>
                        <h3 id="res-unit">--</h3>
                        <div class="yield-badge" id="res-badge">--</div>
                    </div>

                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="score-circle" id="res-score">--</div>
                            <div><h4>Predictability</h4><p>AI Confidence</p></div>
                        </div>
                        <div class="stat-card">
                            <div><h4>Risk Level</h4><div class="risk-badge" id="res-risk">--</div></div>
                        </div>
                    </div>

                    <div class="progress-card">
                        <div class="prog-header"><span>Favorable Conditions</span><span id="res-fav-txt">--%</span></div>
                        <div class="prog-track"><div class="prog-fill" id="res-fav-bar"></div></div>
                    </div>

                    <div class="comp-card">
                        <div class="comp-title"><i class="fa-solid fa-chart-simple"></i> Comparison</div>
                        <div class="bar-row">
                            <div class="bar-label"><span>Your Prediction</span><span id="comp-you-txt">--</span></div>
                            <div class="bar-track"><div class="bar-fill fill-purple" id="comp-you-bar"></div></div>
                        </div>
                        <div class="bar-row">
                            <div class="bar-label"><span>Regional Avg</span><span id="comp-avg-txt">--</span></div>
                            <div class="bar-track"><div class="bar-fill fill-gray" id="comp-avg-bar"></div></div>
                        </div>
                    </div>

                    <div id="res-recs"></div>

                    <div class="pro-tip">
                        <div class="tip-icon"><i class="fa-solid fa-star"></i></div>
                        <div class="tip-text"><h4>Pro Tip</h4><p id="res-tip">--</p></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function getPrediction(e) {
            e.preventDefault();
            document.getElementById('empty-state').style.display = 'none';
            document.getElementById('results-area').style.display = 'none';
            document.getElementById('loader').style.display = 'flex';
            
            const btn = document.getElementById('submitBtn');
            btn.disabled = true;
            btn.innerText = 'Analyzing...';

            const formData = new FormData();
            formData.append('crop', document.getElementById('crop').value);
            formData.append('area', document.getElementById('area').value);
            formData.append('soil', document.getElementById('soil').value);
            formData.append('water', document.getElementById('water').value);

            fetch('yield-api.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.error) { alert(data.error); location.reload(); return; }
                buildUI(data);
            })
            .catch(err => { alert('Prediction interrupted.'); location.reload(); });
        }

        function buildUI(data) {
            document.getElementById('res-yield').innerText = data.expected_yield;
            document.getElementById('res-unit').innerText = data.unit;
            document.getElementById('res-badge').innerText = data.comparison_badge;
            document.getElementById('res-score').innerText = data.predictability_score;
            
            const rb = document.getElementById('res-risk');
            rb.className = 'risk-badge ' + (data.risk_level === 'Low' ? 'risk-low' : 'risk-med');
            rb.innerText = data.risk_level;

            document.getElementById('res-fav-txt').innerText = data.favorable_conditions_pct + '%';
            document.getElementById('res-fav-bar').style.width = data.favorable_conditions_pct + '%';

            document.getElementById('comp-you-txt').innerText = data.expected_yield + ' ' + data.unit;
            document.getElementById('comp-avg-txt').innerText = data.regional_average + ' ' + data.unit;
            
            let max = Math.max(parseFloat(data.expected_yield), parseFloat(data.regional_average)) * 1.2;
            document.getElementById('comp-you-bar').style.width = (parseFloat(data.expected_yield)/max*100) + '%';
            document.getElementById('comp-avg-bar').style.width = (parseFloat(data.regional_average)/max*100) + '%';

            document.getElementById('res-recs').innerHTML = data.recommendations.map(r => `
                <div class="rec-card">
                    <div class="rec-icon"><i class="fa-solid fa-lightbulb"></i></div>
                    <div class="rec-text"><h4>${r.title}</h4><p>${r.desc}</p><span class="rec-impact">Impact: ${r.impact}</span></div>
                </div>`).join('');

            document.getElementById('res-tip').innerText = data.pro_tip;
            document.getElementById('loader').style.display = 'none';
            document.getElementById('results-area').style.display = 'flex';
            btn = document.getElementById('submitBtn');
            btn.disabled = false;
            btn.innerText = 'Recalculate Yield';
        }

        function updateBadge() {
            fetch('check-alerts.php')
                .then(res => res.json())
                .then(data => {
                    const badge = document.getElementById('unread-count-badge');
                    if (badge && data.count !== undefined) {
                        if (data.count > 0) { badge.innerText = data.count; badge.style.display = 'flex'; }
                        else { badge.style.display = 'none'; }
                    }
                }).catch(e => console.log('Sync paused'));
        }
        setInterval(updateBadge, 10000);
    </script>
</body>
</html>