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
    <title>Resource Optimization - FarmWise</title>
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

        /* Header (Teal Theme) */
        .header-bg { background: linear-gradient(135deg, #0d9488 0%, #0369a1 100%); color: white; padding: 25px 50px; border-bottom-left-radius: 30px; border-bottom-right-radius: 30px; box-shadow: 0 10px 30px rgba(13, 148, 136, 0.15); transition: 0.3s; }
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
            border: 2px solid #0d9488; font-weight: bold; color: white;
        }

        .header-content { display: flex; align-items: center; gap: 20px; }
        .icon-circle { width: 60px; height: 60px; background: rgba(255,255,255,0.2); border-radius: 50%; display: flex; justify-content: center; align-items: center; font-size: 30px; border: 2px solid rgba(255,255,255,0.4); }
        .header-text h1 { font-size: 28px; font-weight: 800; margin-bottom: 5px; }
        .header-text p { font-size: 15px; opacity: 0.9; }

        /* Main Layout */
        .main-wrapper { display: grid; grid-template-columns: 400px 1fr; gap: 30px; padding: 40px 50px; align-items: start; }

        /* Left Side: Form */
        .sidebar { position: sticky; top: 30px; }
        .form-card { background: white; padding: 30px; border-radius: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.03); border: 1px solid #f1f5f9; transition: 0.3s; }
        .form-card h3 { font-size: 18px; color: #1e293b; margin-bottom: 25px; display: flex; align-items: center; gap: 10px; transition: color 0.3s; }
        .form-card h3 i { color: #0d9488; }
        
        .form-group { margin-bottom: 20px; }
        .form-group label { display: flex; align-items: center; gap: 8px; font-size: 13px; font-weight: 700; color: #475569; margin-bottom: 8px; transition: color 0.3s; }
        .input-box { width: 100%; padding: 14px 15px; border: 1px solid #cbd5e1; border-radius: 12px; font-size: 14px; outline: none; background: #f8fafc; transition: 0.3s; color: inherit; }
        .input-box:focus { border-color: #0d9488; background: white; }

        .submit-btn { width: 100%; background: #0d9488; color: white; border: none; padding: 16px; border-radius: 14px; font-size: 16px; font-weight: 700; cursor: pointer; transition: 0.3s; box-shadow: 0 4px 15px rgba(13, 148, 136, 0.3); margin-top: 10px; }
        .submit-btn:hover { background: #0f766e; transform: translateY(-2px); }
        .submit-btn:disabled { background: #94a3b8; cursor: not-allowed; transform: none; box-shadow: none; }

        /* Right Side: Results */
        .results-dashboard { display: flex; flex-direction: column; gap: 20px; }
        .empty-state, .loader-card { background: white; border-radius: 20px; padding: 80px 40px; text-align: center; border: 2px dashed #e2e8f0; color: #94a3b8; min-height: 500px; display: flex; flex-direction: column; justify-content: center; align-items: center; transition: 0.3s; }
        .loader-card { display: none; border-style: solid; box-shadow: 0 4px 15px rgba(0,0,0,0.03); }
        .loader-card i { font-size: 60px; color: #0d9488; margin-bottom: 20px; }

        #results-area { display: none; flex-direction: column; gap: 20px; }

        /* Savings Hero */
        .savings-hero { background: linear-gradient(135deg, #16a34a 0%, #15803d 100%); color: white; border-radius: 24px; padding: 35px; text-align: center; box-shadow: 0 10px 25px rgba(22, 163, 74, 0.2); }
        .savings-hero h1 { font-size: 55px; font-weight: 800; margin-bottom: 5px; }
        .s-badge { background: rgba(255,255,255,0.15); padding: 10px 20px; border-radius: 12px; display: flex; flex-direction: column; align-items: center; gap: 5px; border: 1px solid rgba(255,255,255,0.2); flex: 1; }

        /* Progressive Bars */
        .card { background: white; border-radius: 20px; padding: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.02); border: 1px solid #f1f5f9; transition: 0.3s; }
        .card-header { display: flex; align-items: center; gap: 10px; font-size: 16px; font-weight: 800; color: #1e293b; margin-bottom: 20px; transition: color 0.3s; }
        
        .comp-labels { display: flex; justify-content: space-between; font-size: 13px; font-weight: 600; margin-bottom: 8px; color: #64748b; }
        .track-red { width: 100%; height: 12px; background: #fee2e2; border-radius: 10px; overflow: hidden; margin-bottom: 15px; }
        .fill-red { height: 100%; background: #ef4444; transition: 1s; width: 0%; }
        .track-green { width: 100%; height: 12px; background: #dcfce7; border-radius: 10px; overflow: hidden; }
        .fill-green { height: 100%; background: #22c55e; transition: 1s; width: 0%; }

        .saved-banner { display: flex; justify-content: space-between; align-items: center; padding: 15px 20px; border-radius: 12px; color: white; margin-top: 15px; font-weight: 700; }
        .saved-water { background: #3b82f6; }
        .saved-fert { background: #0ea5e9; }

        .rec-item { display: flex; gap: 15px; padding: 15px; background: #f8fafc; border-radius: 12px; margin-bottom: 12px; border: 1px solid #e2e8f0; transition: 0.3s; }
        .rec-icon { width: 40px; height: 40px; background: white; border-radius: 10px; display: flex; justify-content: center; align-items: center; font-size: 18px; color: #0d9488; flex-shrink: 0; transition: 0.3s;}
        .rec-text h4 { font-size: 14px; font-weight: 700; color: #1e293b; transition: color 0.3s;}

        .npk-track { width: 100%; height: 8px; background: #f1f5f9; border-radius: 4px; overflow: hidden; transition: 0.3s;}
        .timing-alert { background: #fff7ed; border-left: 4px solid #f59e0b; padding: 15px; border-radius: 12px; display: flex; gap: 12px; margin-top: 15px; transition: 0.3s;}

        .sustain-card { background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 16px; padding: 20px; display: flex; gap: 15px; align-items: center; transition: 0.3s; }

        /* =========================================
           GLOBAL DARK MODE STYLES
           ========================================= */
        html.dark-mode body { background-color: #121212; color: #f8fafc; }
        html.dark-mode .header-bg { background: linear-gradient(135deg, #134e4a 0%, #0c4a6e 100%); }
        html.dark-mode .notification-badge { border-color: #134e4a !important; }

        html.dark-mode .form-card,
        html.dark-mode .card,
        html.dark-mode .empty-state,
        html.dark-mode .loader-card { 
            background-color: #1e1e1e !important; 
            border-color: #333 !important; 
            box-shadow: none !important; 
        }

        html.dark-mode .input-box { background-color: #121212 !important; border-color: #333 !important; color: #f8fafc !important; }
        html.dark-mode .input-box:focus { border-color: #0d9488 !important; }

        html.dark-mode h3, html.dark-mode .card-header, html.dark-mode .rec-text h4 { color: #f8fafc !important; }
        html.dark-mode label, html.dark-mode .comp-labels, html.dark-mode .rec-text p { color: #94a3b8 !important; }

        html.dark-mode .rec-item { background-color: #121212 !important; border-color: #333 !important; }
        html.dark-mode .rec-icon { background-color: #1e1e1e !important; color: #2dd4bf !important; }

        html.dark-mode .track-red { background-color: #450a0a !important; }
        html.dark-mode .track-green { background-color: #022c22 !important; }
        html.dark-mode .npk-track { background-color: #121212 !important; }

        html.dark-mode .timing-alert { background-color: #431407 !important; border-left-color: #f59e0b !important; }
        html.dark-mode .timing-alert h4 { color: #fed7aa !important; }
        html.dark-mode .timing-alert p { color: #fdba74 !important; }

        html.dark-mode .sustain-card { background-color: #064e3b !important; border-color: #065f46 !important; }
        html.dark-mode .sustain-text h4 { color: #6ee7b7 !important; }
        html.dark-mode .sustain-text p { color: #d1fae5 !important; }

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
                <div class="icon-circle"><i class="fa-solid fa-bolt"></i></div>
                <div class="header-text">
                    <h1>Resource Optimization</h1>
                    <p>Water, Fertilizer & Soil Planning for maximum efficiency</p>
                </div>
            </div>
        </div>

        <div class="main-wrapper">
            
            <div class="sidebar">
                <div class="form-card">
                    <h3><i class="fa-solid fa-sliders"></i> Enter Current Usage</h3>
                    <form onsubmit="getOptimization(event)">
                        
                        <div class="form-group">
                            <label><i class="fa-solid fa-leaf"></i> Crop Name</label>
                            <input type="text" id="crop" class="input-box" placeholder="e.g. Cotton, Rice, Wheat" required autocomplete="off">
                        </div>

                        <div class="form-group">
                            <label><i class="fa-solid fa-ruler-combined"></i> Farm Area (Acres)</label>
                            <input type="number" id="area" step="0.1" class="input-box" placeholder="e.g. 10" required>
                        </div>

                        <div class="form-group">
                            <label><i class="fa-solid fa-droplet"></i> Current Water Method</label>
                            <input type="text" id="water" class="input-box" placeholder="e.g. Flood irrigation" required autocomplete="off">
                        </div>

                        <div class="form-group">
                            <label><i class="fa-solid fa-sack-dollar"></i> Fertilizer Usage</label>
                            <input type="text" id="fert" class="input-box" placeholder="e.g. 3 bags Urea per acre" required autocomplete="off">
                        </div>

                        <button type="submit" class="submit-btn" id="submitBtn">Generate Optimization Plan</button>
                    </form>
                </div>
            </div>

            <div class="results-dashboard">
                
                <div id="empty-state" class="empty-state">
                    <i class="fa-solid fa-leaf"></i>
                    <h3>Optimize Your Resources</h3>
                    <p>Enter your current farming methods on the left to see how much water, fertilizer, and money you can save.</p>
                </div>

                <div id="loader" class="loader-card">
                    <i class="fa-solid fa-microchip fa-beat-fade"></i>
                    <h3>Calculating Optimal Usage...</h3>
                    <p style="color: #64748b;">Analyzing best agricultural practices for your inputs.</p>
                </div>

                <div id="results-area">
                    
                    <div class="savings-hero">
                        <p>Total Estimated Savings</p>
                        <h1 id="res-total-money">₹--</h1>
                        <h4>per cycle / season</h4>
                        <div class="savings-badges">
                            <div class="s-badge"><i class="fa-solid fa-droplet"></i><span>Save Water</span></div>
                            <div class="s-badge"><i class="fa-solid fa-leaf"></i><span>Save Fertilizer</span></div>
                            <div class="s-badge"><i class="fa-solid fa-wallet"></i><span>Increase Profit</span></div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header"><i class="fa-solid fa-droplet" style="color:#3b82f6;"></i> Water Optimization</div>
                        <div class="comparison-box">
                            <div class="comp-labels"><span>Current Usage</span> <span id="water-cur-txt" style="color:#ef4444;">--</span></div>
                            <div class="track-red"><div class="fill-red" id="water-cur-bar"></div></div>
                            <div class="comp-labels"><span>Optimal AI Target</span> <span id="water-opt-txt" style="color:#22c55e;">--</span></div>
                            <div class="track-green"><div class="fill-green" id="water-opt-bar"></div></div>
                        </div>
                        <div class="saved-banner saved-water">
                            <div><div style="font-size:12px; font-weight:600; opacity:0.9;">Est. Water Saved</div><div>Based on AI model</div></div>
                            <h2 id="water-saved-pct">--%</h2>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header"><i class="fa-solid fa-lightbulb" style="color:#0d9488;"></i> Water Saving Recommendations</div>
                        <div id="water-recs"></div>
                    </div>

                    <div class="card">
                        <div class="card-header"><i class="fa-solid fa-flask" style="color:#22c55e;"></i> Fertilizer Optimization (NPK)</div>
                        <div class="npk-grid">
                            <div class="npk-row"><div class="npk-label">Nitrogen (N)</div><div class="npk-bars"><div class="npk-track"><div class="npk-fill-cur" id="n-cur"></div></div><div class="npk-track"><div class="npk-fill-opt" id="n-opt"></div></div></div></div>
                            <div class="npk-row"><div class="npk-label">Phosphorus (P)</div><div class="npk-bars"><div class="npk-track"><div class="npk-fill-cur" id="p-cur"></div></div><div class="npk-track"><div class="npk-fill-opt" id="p-opt"></div></div></div></div>
                            <div class="npk-row"><div class="npk-label">Potassium (K)</div><div class="npk-bars"><div class="npk-track"><div class="npk-fill-cur" id="k-cur"></div></div><div class="npk-track"><div class="npk-fill-opt" id="k-opt"></div></div></div></div>
                        </div>
                        <div class="saved-banner saved-fert">
                            <div><div style="font-size:12px; font-weight:600; opacity:0.9;">Est. Fertilizer Saved</div><div>Avoid over-application</div></div>
                            <h2 id="fert-saved-pct">--%</h2>
                        </div>
                        <div class="timing-alert">
                            <i class="fa-solid fa-clock"></i>
                            <div><h4>Optimal Application Timing</h4><p id="fert-timing">--</p></div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header"><i class="fa-solid fa-box-open" style="color:#22c55e;"></i> Recommended Fertilizer Types</div>
                        <div id="fert-types"></div>
                    </div>

                    <div class="sustain-card">
                        <div class="sustain-icon"><i class="fa-solid fa-earth-asia"></i></div>
                        <div class="sustain-text">
                            <h4>Sustainability Impact</h4>
                            <p id="res-sustainability">--</p>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <script>
        function getOptimization(e) {
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
            formData.append('water', document.getElementById('water').value);
            formData.append('fert', document.getElementById('fert').value);

            fetch('resource-api.php', { method: 'POST', body: formData })
            .then(response => response.json())
            .then(data => {
                if (data.error) { alert(data.error); location.reload(); return; }
                buildUI(data);
            })
            .catch(error => { alert('Error connecting to AI.'); location.reload(); });
        }

        function buildUI(data) {
            document.getElementById('res-total-money').innerText = `₹${data.total_savings_inr}`;
            document.getElementById('water-cur-txt').innerText = data.water.current_label;
            document.getElementById('water-opt-txt').innerText = data.water.optimal_label;
            document.getElementById('water-saved-pct').innerText = `${data.water.saved_pct}%`;
            
            let wCur = parseFloat(data.water.current_val);
            let wOpt = parseFloat(data.water.optimal_val);
            let wMax = Math.max(wCur, wOpt) * 1.1;
            
            setTimeout(() => {
                document.getElementById('water-cur-bar').style.width = `${(wCur/wMax)*100}%`;
                document.getElementById('water-opt-bar').style.width = `${(wOpt/wMax)*100}%`;
            }, 100);

            const wIcons = ['fa-droplet', 'fa-faucet-drip', 'fa-cloud-rain'];
            document.getElementById('water-recs').innerHTML = data.water.recommendations.map((rec, i) => `
                <div class="rec-item">
                    <div class="rec-icon"><i class="fa-solid ${wIcons[i % wIcons.length]}"></i></div>
                    <div class="rec-text"><h4>${rec.title}</h4><p>${rec.desc}</p><span class="rec-tag">Savings: ${rec.savings}</span></div>
                </div>`).join('');

            document.getElementById('fert-saved-pct').innerText = `${data.fertilizer.saved_pct}%`;
            let f = data.fertilizer;
            let nMax = Math.max(f.n_current, f.n_optimal) * 1.1;
            let pMax = Math.max(f.p_current, f.p_optimal) * 1.1;
            let kMax = Math.max(f.k_current, f.k_optimal) * 1.1;

            setTimeout(() => {
                document.getElementById('n-cur').style.width = `${(f.n_current/nMax)*100}%`;
                document.getElementById('n-opt').style.width = `${(f.n_optimal/nMax)*100}%`;
                document.getElementById('p-cur').style.width = `${(f.p_current/pMax)*100}%`;
                document.getElementById('p-opt').style.width = `${(f.p_optimal/pMax)*100}%`;
                document.getElementById('k-cur').style.width = `${(f.k_current/kMax)*100}%`;
                document.getElementById('k-opt').style.width = `${(f.k_optimal/kMax)*100}%`;
            }, 100);

            document.getElementById('fert-timing').innerText = f.timing_advice;
            document.getElementById('fert-types').innerHTML = f.types.map(t => `
                <div class="rec-item" style="border-left: 4px solid #22c55e;">
                    <div class="rec-icon" style="color: #22c55e;"><i class="fa-solid fa-seedling"></i></div>
                    <div class="rec-text"><h4>${t.name}</h4><p style="margin-bottom:0;">${t.usage}</p></div>
                </div>`).join('');

            document.getElementById('res-sustainability').innerText = data.sustainability;
            document.getElementById('loader').style.display = 'none';
            document.getElementById('results-area').style.display = 'flex';
            document.getElementById('submitBtn').disabled = false;
            document.getElementById('submitBtn').innerText = 'Recalculate Optimization';
        }

        // Real-time Badge Update
        function updateBadge() {
            fetch('check-alerts.php')
                .then(res => res.json())
                .then(data => {
                    const badge = document.getElementById('unread-count-badge');
                    if (badge && data.count !== undefined) {
                        if (data.count > 0) { badge.innerText = data.count; badge.style.display = 'flex'; }
                        else { badge.style.display = 'none'; }
                    }
                }).catch(e => console.log('Badge sync paused'));
        }
        setInterval(updateBadge, 10000);
    </script>
</body>
</html>