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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Field Monitoring - FarmWise</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <script>
        if (localStorage.getItem('farmwise_theme') === 'dark') {
            document.documentElement.classList.add('dark-mode');
        }
    </script>

    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: -apple-system, BlinkMacSystemFont, sans-serif; }
        body { background-color: #f1f5f9; color: #1e293b; transition: background-color 0.3s, color 0.3s; }
        .app-container { width: 100%; max-width: 1400px; margin: 0 auto; min-height: 100vh; display: flex; flex-direction: column; }
        
        /* Header */
        .header-bg { background: linear-gradient(135deg, #059669 0%, #064e3b 100%); color: white; padding: 30px 50px; border-bottom-left-radius: 30px; border-bottom-right-radius: 30px; transition: 0.3s; }
        .top-nav { display: flex; justify-content: space-between; align-items: center; }
        .back-btn { color: white; text-decoration: none; font-size: 16px; font-weight: 600; display: flex; align-items: center; gap: 10px; transition: 0.2s;}
        .back-btn:hover { opacity: 0.8; }
        
        /* Notification Badge */
        .bell-link { color:white; text-decoration:none; position: relative; transition: 0.2s;}
        .notification-badge { 
            position: absolute; top: -5px; right: -8px; 
            background-color: #ef4444; border-radius: 50%; 
            width: 16px; height: 16px; font-size: 10px; 
            display: flex; align-items: center; justify-content: center; 
            border: 2px solid #059669; font-weight: bold; color: white;
        }

        .header-content { display: flex; align-items: center; gap: 20px; margin-top: 20px; }
        .header-icon { width: 60px; height: 60px; background: rgba(255,255,255,0.2); border-radius: 50%; display: flex; justify-content: center; align-items: center; font-size: 30px; }

        /* Layout */
        .main-wrapper { display: grid; grid-template-columns: 400px 1fr; gap: 30px; padding: 40px 50px; flex: 1; }

        /* Left Side */
        .sidebar { position: sticky; top: 30px; display: flex; flex-direction: column; gap: 20px; }
        .upload-card { background: white; padding: 40px 30px; border-radius: 20px; text-align: center; border: 2px dashed #cbd5e1; cursor: pointer; transition: 0.3s; }
        .upload-card:hover { border-color: #059669; background: #ecfdf5; }
        .upload-card i { font-size: 40px; color: #059669; margin-bottom: 15px; }

        /* Right Side Cards */
        .card { background: white; border-radius: 20px; padding: 25px; margin-bottom: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.03); border: 1px solid #e2e8f0; transition: 0.3s; }
        .card-title { font-size: 16px; font-weight: 800; color: #334155; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; transition: color 0.3s; }

        /* Growth Stage Card */
        .stage-box { display: flex; justify-content: space-between; align-items: center; background: #f0fdf4; padding: 20px; border-radius: 16px; margin-bottom: 20px; transition: background-color 0.3s; }
        .stage-text h1 { font-size: 32px; color: #065f46; transition: color 0.3s; }
        .prog-track { width: 100%; height: 12px; background: #e2e8f0; border-radius: 10px; overflow: hidden; margin: 15px 0; }
        .prog-fill { height: 100%; background: #10b981; transition: 1s; width: 0%; }
        .stage-stats { display: flex; gap: 30px; font-size: 13px; color: #64748b; font-weight: 600; }

        /* Vertical Stepper */
        .stepper { display: flex; flex-direction: column; gap: 10px; margin-top: 20px; }
        .step { display: flex; align-items: center; gap: 15px; }
        .step-circle { width: 32px; height: 32px; border-radius: 50%; display: flex; justify-content: center; align-items: center; font-size: 14px; border: 2px solid #e2e8f0; color: #cbd5e1; position: relative; z-index: 2; background: white; transition: 0.3s; }
        .step.done .step-circle { background: #10b981; color: white; border-color: #10b981; }
        .step.current .step-circle { background: #059669; color: white; border-color: #059669; box-shadow: 0 0 10px rgba(5,150,105,0.3); }
        .step-label { font-size: 14px; font-weight: 600; color: #94a3b8; }
        .step.current .step-label { color: #065f46; font-weight: 800; }
        .step.done .step-label { color: #334155; }

        /* Summary Counters */
        .summary-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin-bottom: 20px; }
        .sum-item { padding: 15px; border-radius: 12px; text-align: center; border: 1px solid transparent; transition: 0.3s; }
        .sum-red { background: #fef2f2; color: #dc2626; border-color: #fecaca; }
        .sum-orange { background: #fff7ed; color: #f97316; border-color: #ffedd5; }
        .sum-green { background: #f0fdf4; color: #16a34a; border-color: #dcfce7; }
        .sum-item h1 { font-size: 24px; }
        .sum-item p { font-size: 11px; font-weight: 700; text-transform: uppercase; }

        /* Observation Cards */
        .obs-card { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 16px; padding: 20px; margin-bottom: 15px; transition: 0.3s; }
        .obs-top { display: flex; justify-content: space-between; margin-bottom: 10px; }
        .obs-title { font-weight: 800; font-size: 16px; color: #1e293b; transition: color 0.3s; }
        .obs-badge { font-size: 11px; padding: 4px 10px; border-radius: 8px; font-weight: 700; }
        .obs-rec { background: #fff1f2; color: #e11d48; padding: 12px; border-radius: 10px; font-size: 13px; font-weight: 600; margin-top: 15px; border-left: 4px solid #e11d48; transition: 0.3s; }

        .loader { display: none; text-align: center; padding: 100px; color: #059669; }

        /* =========================================
           GLOBAL DARK MODE STYLES
           ========================================= */
        html.dark-mode body { background-color: #121212; color: #f8fafc; }
        html.dark-mode .header-bg { background: linear-gradient(135deg, #064e3b 0%, #022c22 100%); }
        html.dark-mode .notification-badge { border-color: #064e3b !important; }
        
        html.dark-mode .upload-card, 
        html.dark-mode .card { 
            background-color: #1e1e1e !important; 
            border-color: #333 !important; 
            box-shadow: none !important; 
        }
        html.dark-mode .upload-card:hover { background-color: #064e3b !important; }

        html.dark-mode .card-title,
        html.dark-mode .obs-title,
        html.dark-mode .step.done .step-label { color: #f8fafc !important; }

        html.dark-mode .stage-box { background-color: #022c22 !important; }
        html.dark-mode .stage-text h1 { color: #10b981 !important; }
        html.dark-mode .prog-track { background-color: #333 !important; }
        
        html.dark-mode .step-circle { background-color: #121212 !important; border-color: #333 !important; }
        html.dark-mode .step.current .step-label { color: #10b981 !important; }

        html.dark-mode .obs-card { background-color: #121212 !important; border-color: #333 !important; }
        html.dark-mode .obs-rec { background-color: #450a0a !important; color: #fecaca !important; border-left-color: #ef4444 !important; }
        
        html.dark-mode .sum-red { background-color: #450a0a !important; border-color: #991b1b !important; color: #fca5a5 !important; }
        html.dark-mode .sum-orange { background-color: #431407 !important; border-color: #9a3412 !important; color: #fdba74 !important; }
        html.dark-mode .sum-green { background-color: #022c22 !important; border-color: #064e3b !important; color: #6ee7b7 !important; }

        @media (max-width: 900px) {
            .main-wrapper { grid-template-columns: 1fr; padding: 20px; }
            .sidebar { position: relative; top: 0; }
            .header-bg { padding: 20px; border-radius: 0 0 20px 20px; }
        }
    </style>
</head>
<body>
    <div class="app-container">
        <div class="header-bg">
            <div class="top-nav">
                <a href="farmer-dashboard.php" class="back-btn"><i class="fa-solid fa-arrow-left"></i><span>Dashboard</span></a>
                <a href="alerts.php" class="bell-link">
                    <i class="fa-regular fa-bell" style="font-size: 20px;">
                        <div class="notification-badge" id="unread-count-badge" style="display: <?php echo ($unread_count > 0) ? 'flex' : 'none'; ?>;">
                            <?php echo $unread_count; ?>
                        </div>
                    </i>
                </a>
            </div>
            <div class="header-content">
                <div class="header-icon"><i class="fa-solid fa-eye"></i></div>
                <div>
                    <h1>Field Monitoring</h1>
                    <p>Visual crop tracking and health diagnostics</p>
                </div>
            </div>
        </div>

        <div class="main-wrapper">
            <div class="sidebar">
                <div class="upload-card" onclick="document.getElementById('fileIn').click()">
                    <i class="fa-solid fa-cloud-arrow-up"></i>
                    <h3>Capture Field Image</h3>
                    <p>AI will detect stage & health</p>
                    <input type="file" id="fileIn" style="display:none" accept="image/*" onchange="runMonitoring(event)">
                </div>
                <div id="pBox" style="display:none">
                    <img id="pImg" style="width:100%; border-radius:20px; box-shadow:0 10px 20px rgba(0,0,0,0.1)">
                </div>
            </div>

            <div class="results-col">
                <div class="loader" id="ldr">
                    <i class="fa-solid fa-microchip fa-spin fa-3x"></i><br><br>
                    <h2>Analyzing Field Conditions...</h2>
                </div>
                
                <div id="res-ui" style="display:none">
                    <div class="card">
                        <div class="card-title"><i class="fa-solid fa-seedling"></i> Crop Growth Stage</div>
                        <div class="stage-box">
                            <div class="stage-text">
                                <p style="font-size:12px; color:#10b981; font-weight:700;">CURRENT STAGE</p>
                                <h1 id="currStage">--</h1>
                            </div>
                            <div style="text-align:right">
                                <h1 id="progPct" style="color:#10b981">--%</h1>
                            </div>
                        </div>
                        <div class="prog-track"><div class="prog-fill" id="fillBar"></div></div>
                        <div class="stage-stats">
                            <span>Time in Stage: <strong id="timeStage">--</strong></span>
                            <span>Last Updated: <strong id="lastUpdate">--</strong></span>
                        </div>
                        <div class="stepper" id="stepper"></div>
                    </div>

                    <div class="card">
                        <div class="card-title"><i class="fa-solid fa-triangle-exclamation"></i> Selected Issues & Observations</div>
                        <div class="summary-grid">
                            <div class="sum-item sum-red"><h1 id="cntIssues">0</h1><p>Issues</p></div>
                            <div class="sum-item sum-orange"><h1 id="cntWarn">0</h1><p>Warnings</p></div>
                            <div class="sum-item sum-green"><h1 id="cntRes">0</h1><p>Resolved</p></div>
                        </div>
                        <div id="obsList"></div>
                    </div>
                    
                    <div class="card" style="background:#f0fdf4; border:1px solid #bbf7d0" id="tipsCard">
                         <div class="card-title"><i class="fa-solid fa-lightbulb" style="color:#16a34a"></i> Monitoring Tips</div>
                         <ul id="tipList" style="padding-left:20px; font-size:14px; color:#15803d; line-height:1.6"></ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function runMonitoring(e) {
            const file = e.target.files[0];
            if(!file) return;

            document.getElementById('pImg').src = URL.createObjectURL(file);
            document.getElementById('pBox').style.display = 'block';
            document.getElementById('ldr').style.display = 'block';
            document.getElementById('res-ui').style.display = 'none';

            const fd = new FormData();
            fd.append('image', file);

            fetch('monitoring-api.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                document.getElementById('ldr').style.display = 'none';
                document.getElementById('res-ui').style.display = 'block';
                
                document.getElementById('currStage').innerText = data.current_stage;
                document.getElementById('progPct').innerText = data.progress_pct + '%';
                document.getElementById('fillBar').style.width = data.progress_pct + '%';
                document.getElementById('timeStage').innerText = data.days_in_stage;
                document.getElementById('lastUpdate').innerText = data.last_updated;

                // Stepper
                let sHtml = '';
                data.stages.forEach(s => {
                    let cls = s.status === 'done' ? 'done' : (s.status === 'current' ? 'current' : '');
                    let icon = s.status === 'done' ? '<i class="fa-solid fa-check"></i>' : (s.status === 'current' ? '<i class="fa-solid fa-clock"></i>' : '');
                    sHtml += `<div class="step ${cls}"><div class="step-circle">${icon}</div><div class="step-label">${s.name}</div></div>`;
                });
                document.getElementById('stepper').innerHTML = sHtml;

                // Counters
                document.getElementById('cntIssues').innerText = data.summary.issues;
                document.getElementById('cntWarn').innerText = data.summary.warnings;
                document.getElementById('cntRes').innerText = data.summary.resolved;

                // Observations
                let oHtml = '';
                data.observations.forEach(o => {
                    oHtml += `
                        <div class="obs-card">
                            <div class="obs-top">
                                <span class="obs-title">${o.title}</span>
                                <span class="obs-badge" style="background:#fef3c7; color:#92400e">${o.severity.toUpperCase()} SEVERITY</span>
                            </div>
                            <p style="font-size:13px; color:#64748b">${o.desc}</p>
                            <div class="obs-rec"><i class="fa-solid fa-hand-holding-medical"></i> RECOMMENDATION: ${o.rec}</div>
                            <p style="font-size:11px; color:#94a3b8; margin-top:10px;"><i class="fa-regular fa-calendar"></i> ${o.date}</p>
                        </div>`;
                });
                document.getElementById('obsList').innerHTML = oHtml;

                // Tips
                document.getElementById('tipList').innerHTML = data.tips.map(t => `<li>${t}</li>`).join('');
                
                // Dark mode adjustment for tips card background
                if(document.documentElement.classList.contains('dark-mode')) {
                    document.getElementById('tipsCard').style.backgroundColor = '#022c22';
                    document.getElementById('tipsCard').style.borderColor = '#064e3b';
                }
            })
            .catch(error => {
                document.getElementById('ldr').style.display = 'none';
                alert('Analysis failed. Please check your internet connection or try a different image.');
            });
        }

        // Real-time Badge Polling
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
                }).catch(e => console.log('Alert check silent failure'));
        }
        setInterval(updateBadge, 10000);
    </script>
</body>
</html>