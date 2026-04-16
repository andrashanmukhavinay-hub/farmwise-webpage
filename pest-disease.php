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
    <title>Pest & Disease Diagnosis</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <script>
        if (localStorage.getItem('farmwise_theme') === 'dark') {
            document.documentElement.classList.add('dark-mode');
        }
    </script>

    <style>
        /* Base Reset */
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
        body { background-color: #f1f5f9; color: #1e293b; min-height: 100vh; overflow-x: hidden; transition: background-color 0.3s, color 0.3s; }

        /* Full Page Container */
        .app-container { width: 100%; max-width: 1400px; margin: 0 auto; display: flex; flex-direction: column; min-height: 100vh; }

        /* Header (Red Theme) */
        .header-bg { background: linear-gradient(135deg, #dc2626 0%, #991b1b 100%); color: white; padding: 25px 50px; border-bottom-left-radius: 30px; border-bottom-right-radius: 30px; box-shadow: 0 10px 30px rgba(220, 38, 38, 0.15); transition: 0.3s; }
        .top-nav { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; }
        .back-btn { color: white; text-decoration: none; font-size: 18px; display: flex; align-items: center; gap: 10px; font-weight: 600; transition: opacity 0.2s; }
        .back-btn:hover { opacity: 0.8; }
        
        /* Notification Badge */
        .bell-link { color:white; text-decoration:none; position: relative; transition: 0.2s;}
        .notification-badge { 
            position: absolute; top: -5px; right: -8px; 
            background-color: #f9fafb; border-radius: 50%; 
            width: 18px; height: 18px; font-size: 11px; 
            display: flex; align-items: center; justify-content: center; 
            border: 2px solid #dc2626; font-weight: bold; color: #dc2626;
        }

        .header-content { display: flex; align-items: center; gap: 20px; }
        .icon-circle { width: 60px; height: 60px; background: rgba(255,255,255,0.2); border-radius: 50%; display: flex; justify-content: center; align-items: center; font-size: 30px; border: 2px solid rgba(255,255,255,0.4); }
        .header-text h1 { font-size: 28px; font-weight: 800; letter-spacing: 0.5px; margin-bottom: 5px; }
        .header-text p { font-size: 15px; opacity: 0.9; }

        /* Main Layout */
        .main-wrapper { display: grid; grid-template-columns: 400px 1fr; gap: 30px; padding: 40px 50px; align-items: start; flex: 1; }

        /* Left Side */
        .sidebar { display: flex; flex-direction: column; gap: 20px; position: sticky; top: 30px; }
        .upload-card { background: white; border: 2px dashed #cbd5e1; border-radius: 20px; padding: 50px 30px; text-align: center; cursor: pointer; transition: all 0.3s; box-shadow: 0 4px 15px rgba(0,0,0,0.02); }
        .upload-card:hover { border-color: #dc2626; background: #fef2f2; transform: translateY(-3px); }
        .upload-card i { font-size: 50px; color: #94a3b8; margin-bottom: 20px; transition: 0.3s; }
        
        .preview-card { display: none; background: white; border-radius: 20px; padding: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); text-align: center; transition: 0.3s; }
        .preview-img-box { width: 100%; height: 250px; border-radius: 12px; overflow: hidden; margin-bottom: 15px; background: #f1f5f9; }
        .preview-img-box img { width: 100%; height: 100%; object-fit: cover; }

        /* Right Side: Results Dashboard */
        .results-dashboard { display: flex; flex-direction: column; gap: 20px; }
        .empty-state, .loader-card { background: white; border-radius: 20px; padding: 80px 40px; text-align: center; border: 2px dashed #e2e8f0; color: #94a3b8; min-height: 400px; display: flex; flex-direction: column; align-items: center; justify-content: center; transition: 0.3s; }
        .loader-card { display: none; border-style: solid; box-shadow: 0 4px 15px rgba(0,0,0,0.03); }
        .loader-card i { font-size: 60px; color: #dc2626; margin-bottom: 20px; }

        #results-area { display: none; grid-template-columns: repeat(2, 1fr); gap: 20px; }
        .full-width { grid-column: 1 / -1; }

        .card { background: white; border-radius: 20px; padding: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.03); border: 1px solid #f1f5f9; display: flex; flex-direction: column; transition: 0.3s; }
        .card-header { display: flex; align-items: center; gap: 12px; margin-bottom: 20px; font-size: 18px; font-weight: 800; color: #1e293b; border-bottom: 2px solid #f1f5f9; padding-bottom: 15px; transition: color 0.3s, border-color 0.3s; }
        
        .disease-name { font-size: 32px; font-weight: 800; color: #1e293b; transition: color 0.3s; }
        .diag-stats { display: flex; gap: 40px; background: #f8fafc; padding: 20px; border-radius: 16px; margin-bottom: 25px; transition: 0.3s; }
        .d-stat h5 { font-size: 18px; color: #334155; font-weight: 700; transition: color 0.3s; }

        .risk-badge { padding: 8px 16px; border-radius: 20px; font-size: 14px; font-weight: 700; display: flex; align-items: center; gap: 8px; transition: 0.3s; }
        .risk-high { background: #fee2e2; color: #dc2626; border: 1px solid #fecaca; }
        .risk-medium { background: #fef08a; color: #b45309; border: 1px solid #fde047; }
        .risk-low { background: #dcfce7; color: #16a34a; border: 1px solid #bbf7d0; }

        .num-circle { width: 28px; height: 28px; background: #dc2626; color: white; border-radius: 50%; display: flex; justify-content: center; align-items: center; font-size: 14px; font-weight: 800; flex-shrink: 0; }
        .chem-card { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 16px; padding: 20px; margin-bottom: 15px; transition: 0.3s; }

        .expert-tip { background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 20px; padding: 25px; display: flex; gap: 20px; align-items: center; transition: 0.3s; }
        .expert-icon { width: 50px; height: 50px; background: #22c55e; color: white; border-radius: 50%; display: flex; justify-content: center; align-items: center; font-size: 24px; flex-shrink: 0; }

        /* =========================================
           GLOBAL DARK MODE STYLES
           ========================================= */
        html.dark-mode body { background-color: #121212; color: #f8fafc; }
        html.dark-mode .header-bg { background: linear-gradient(135deg, #7f1d1d 0%, #450a0a 100%); }
        html.dark-mode .notification-badge { border-color: #7f1d1d !important; background-color: #f8fafc; color: #7f1d1d; }
        
        html.dark-mode .upload-card, 
        html.dark-mode .preview-card, 
        html.dark-mode .card,
        html.dark-mode .empty-state, 
        html.dark-mode .loader-card { 
            background-color: #1e1e1e !important; 
            border-color: #333 !important; 
            box-shadow: none !important; 
        }
        html.dark-mode .upload-card:hover { background-color: #2d1a1a !important; border-color: #ef4444 !important; }

        html.dark-mode .card-header, 
        html.dark-mode .disease-name,
        html.dark-mode .d-stat h5 { color: #f8fafc !important; border-bottom-color: #333 !important; }
        
        html.dark-mode .diag-stats, 
        html.dark-mode .chem-card,
        html.dark-mode .preview-img-box { background-color: #121212 !important; border-color: #333 !important; }
        
        html.dark-mode .expert-tip { background-color: #064e3b !important; border-color: #065f46 !important; }
        html.dark-mode .expert-text h4 { color: #6ee7b7 !important; }
        html.dark-mode .expert-text p { color: #d1fae5 !important; }

        html.dark-mode .num-item p,
        html.dark-mode .bullet-list li,
        html.dark-mode .d-stat p { color: #94a3b8 !important; }

        @media (max-width: 1024px) {
            .main-wrapper { grid-template-columns: 1fr; padding: 20px; }
            .header-bg { padding: 20px; border-radius: 0 0 20px 20px; }
            .sidebar { position: static; }
            #results-area { grid-template-columns: 1fr; }
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
                <div class="icon-circle"><i class="fa-solid fa-bug-slash"></i></div>
                <div class="header-text">
                    <h1>Pest & Disease Diagnosis</h1>
                    <p>AI-powered image analysis and treatment plans</p>
                </div>
            </div>
        </div>

        <div class="main-wrapper">
            <div class="sidebar">
                <input type="file" id="imageInput" accept="image/*" style="display: none;" onchange="handleImageUpload(event)">
                
                <div id="upload-area" class="upload-card" onclick="document.getElementById('imageInput').click()">
                    <i class="fa-solid fa-camera-viewfinder"></i>
                    <h3>Upload Crop Photo</h3>
                    <p>Capture the affected part of the plant for analysis.</p>
                </div>

                <div id="preview-area" class="preview-card">
                    <div class="preview-img-box">
                        <img id="preview-img" src="" alt="Uploaded Crop">
                    </div>
                    <button class="btn-reupload" onclick="document.getElementById('imageInput').click()">
                        <i class="fa-solid fa-upload"></i> Different Image
                    </button>
                </div>
            </div>

            <div class="results-dashboard">
                <div id="empty-state" class="empty-state">
                    <i class="fa-solid fa-microscope"></i>
                    <h3>Awaiting Image</h3>
                    <p>Upload a photo on the left to generate an AI diagnosis.</p>
                </div>

                <div id="loader" class="loader-card">
                    <i class="fa-solid fa-microchip fa-beat-fade"></i>
                    <h3>Analyzing Crop Pathogens...</h3>
                    <p>Scanning against thousands of known plant diseases.</p>
                </div>

                <div id="results-area">
                    <div id="urgent-box" class="full-width" style="display:none;"></div>

                    <div class="card full-width">
                        <div class="overview-top">
                            <div>
                                <div class="disease-title" style="color: #94a3b8; font-weight: 700; font-size: 13px; text-transform: uppercase;">AI Diagnosis Result</div>
                                <div class="disease-name" id="res-disease">--</div>
                            </div>
                            <div class="action-btns" style="display:flex; gap:10px;">
                                <button class="btn-fill" style="background:#1e293b; color:white; padding:10px 18px; border-radius:12px; border:none; cursor:pointer;" onclick="window.print()"><i class="fa-solid fa-print"></i> Print</button>
                            </div>
                        </div>

                        <div class="diag-stats">
                            <div class="d-stat"><p>Crop</p><h5 id="res-crop">--</h5></div>
                            <div class="d-stat"><p>Symptoms</p><h5 id="res-symptoms">--</h5></div>
                        </div>

                        <div class="status-row" style="display:flex; gap:20px; align-items:center;">
                            <div class="risk-badge" id="res-risk">--</div>
                            <div class="confidence" style="color:#10b981; font-weight:700;">AI Confidence: <span id="res-conf">--</span></div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header"><i class="fa-solid fa-list-check" style="color:#dc2626;"></i> Treatment Steps</div>
                        <div class="num-list" id="res-steps"></div>
                    </div>

                    <div class="card">
                        <div class="card-header"><i class="fa-solid fa-flask" style="color:#3b82f6;"></i> Chemical Solutions</div>
                        <div id="res-chemicals"></div>
                    </div>

                    <div class="card">
                        <div class="card-header"><i class="fa-solid fa-seedling" style="color:#22c55e;"></i> Organic Options</div>
                        <ul class="bullet-list green" id="res-organic" style="list-style:none; padding-left:5px;"></ul>
                    </div>

                    <div class="card">
                        <div class="card-header"><i class="fa-solid fa-shield-halved" style="color:#f59e0b;"></i> Prevention</div>
                        <ul class="bullet-list yellow" id="res-prevent" style="list-style:none; padding-left:5px;"></ul>
                    </div>

                    <div class="full-width">
                        <div class="expert-tip">
                            <div class="expert-icon"><i class="fa-solid fa-lightbulb"></i></div>
                            <div class="expert-text">
                                <h4>Agronomist Pro-Tip</h4>
                                <p id="res-tip">--</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function handleImageUpload(event) {
            const file = event.target.files[0];
            if (!file) return;

            document.getElementById('upload-area').style.display = 'none';
            document.getElementById('preview-area').style.display = 'block';
            document.getElementById('empty-state').style.display = 'none';
            document.getElementById('results-area').style.display = 'none';
            document.getElementById('loader').style.display = 'flex';

            const reader = new FileReader();
            reader.onload = function(e) { document.getElementById('preview-img').src = e.target.result; }
            reader.readAsDataURL(file);

            const formData = new FormData();
            formData.append('image', file);

            fetch('pest-api.php', { method: 'POST', body: formData })
            .then(response => response.json())
            .then(data => {
                if (data.error) { alert(data.error); location.reload(); return; }
                buildUI(data);
            })
            .catch(error => { alert('AI analysis interrupted.'); location.reload(); });
        }

        function buildUI(data) {
            document.getElementById('res-disease').innerText = data.disease_name;
            document.getElementById('res-crop').innerText = data.crop_name;
            document.getElementById('res-symptoms').innerText = data.symptoms;
            document.getElementById('res-conf').innerText = data.confidence;
            
            const rb = document.getElementById('res-risk');
            rb.className = 'risk-badge';
            if(data.risk_level.toLowerCase().includes('high')) rb.classList.add('risk-high');
            else if(data.risk_level.toLowerCase().includes('medium')) rb.classList.add('risk-medium');
            else rb.classList.add('risk-low');
            rb.innerText = data.risk_level.toUpperCase();

            document.getElementById('res-steps').innerHTML = data.treatment_steps.map((s, i) => `
                <div class="num-item" style="display:flex; gap:10px; margin-bottom:10px;">
                    <div class="num-circle">${i+1}</div><p style="font-size:14px;">${s}</p>
                </div>`).join('');

            document.getElementById('res-chemicals').innerHTML = data.chemical_options.map(c => `
                <div class="chem-card" style="padding:15px; border-radius:12px; margin-bottom:10px;">
                    <h4 style="font-size:15px; margin-bottom:5px;">${c.name}</h4>
                    <p style="font-size:12px; opacity:0.8;">Dosage: ${c.dosage} | Timing: ${c.timing}</p>
                </div>`).join('');

            document.getElementById('res-organic').innerHTML = data.organic_options.map(o => `<li style="font-size:14px; margin-bottom:8px;"><i class="fa-solid fa-circle-check" style="color:#22c55e; margin-right:8px;"></i>${o}</li>`).join('');
            document.getElementById('res-prevent').innerHTML = data.preventive_measures.map(p => `<li style="font-size:14px; margin-bottom:8px;"><i class="fa-solid fa-shield" style="color:#f59e0b; margin-right:8px;"></i>${p}</li>`).join('');
            document.getElementById('res-tip').innerText = data.expert_tip;

            document.getElementById('loader').style.display = 'none';
            document.getElementById('results-area').style.display = 'grid';
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
                }).catch(e => console.log('Badge sync paused'));
        }
        setInterval(updateBadge, 10000);
    </script>
</body>
</html>