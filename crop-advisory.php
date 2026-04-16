<?php
session_start();
require 'db.php';
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: farmer-login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crop Advisory - FarmWise</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <script>
        if (localStorage.getItem('farmwise_theme') === 'dark') {
            document.documentElement.classList.add('dark-mode');
        }
    </script>

    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
        body { background-color: #f1f5f9; color: #1e293b; min-height: 100vh; overflow-x: hidden; transition: background-color 0.3s, color 0.3s; }

        .header-bg { background: linear-gradient(135deg, #00b050 0%, #008a3e 100%); color: white; padding: 25px 40px; border-bottom-left-radius: 20px; border-bottom-right-radius: 20px; transition: 0.3s; }
        .top-nav { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .back-btn { color: white; text-decoration: none; font-size: 20px; display: flex; align-items: center; gap: 10px; transition: 0.2s; }
        .back-btn:hover { opacity: 0.8; }
        
        .header-content { display: flex; flex-direction: column; align-items: center; text-align: center; margin-bottom: 10px; }
        .icon-circle { width: 60px; height: 60px; background: rgba(255,255,255,0.2); border-radius: 50%; display: flex; justify-content: center; align-items: center; font-size: 30px; margin-bottom: 15px; }
        .header-content h1 { font-size: 24px; font-weight: 800; }
        .header-content p { font-size: 14px; opacity: 0.9; margin-top: 5px; }

        /* Two-Column Grid for Desktop */
        .main-wrapper { max-width: 1200px; margin: 30px auto; padding: 0 20px; display: grid; grid-template-columns: 350px 1fr; gap: 30px; align-items: start; }

        /* Left Side: The Form */
        .form-card { background: white; padding: 25px; border-radius: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); transition: 0.3s; }
        .form-card h3 { font-size: 16px; color: #334155; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; transition: color 0.3s; }
        
        .form-group { margin-bottom: 20px; }
        .form-group label { display: flex; align-items: center; gap: 8px; font-size: 13px; font-weight: 600; color: #475569; margin-bottom: 8px; transition: color 0.3s; }
        .form-group label i { color: #00b050; }
        .input-box { width: 100%; padding: 12px 15px; border: 1px solid #cbd5e1; border-radius: 12px; font-size: 14px; outline: none; background: #f8fafc; transition: 0.3s; color: inherit; }
        .input-box:focus { border-color: #00b050; background: white; }

        .submit-btn { width: 100%; background: #00b050; color: white; border: none; padding: 15px; border-radius: 12px; font-size: 15px; font-weight: 700; cursor: pointer; transition: 0.3s; box-shadow: 0 4px 10px rgba(0, 176, 80, 0.3); }
        .submit-btn:hover { background: #008a3e; transform: translateY(-2px); }
        .submit-btn:disabled { background: #94a3b8; cursor: not-allowed; transform: none; box-shadow: none; }
        
        /* Right Side: The Results */
        .results-area { display: flex; flex-direction: column; gap: 20px; }
        .results-header { display: flex; justify-content: space-between; align-items: center; font-size: 16px; font-weight: 700; color: #1e293b; padding: 0 10px; transition: color 0.3s;}
        .results-header span.count { font-size: 13px; color: #64748b; font-weight: 500; }

        /* Loading Spinner */
        .loader { display: none; text-align: center; padding: 50px; color: #00b050; }
        .loader i { font-size: 40px; margin-bottom: 15px; }

        /* Crop Cards (Dynamic Output) */
        .crop-card { background: white; border-radius: 20px; padding: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.04); border: 1px solid #f1f5f9; transition: 0.3s; }
        .crop-top { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px; border-bottom: 1px solid #f1f5f9; padding-bottom: 15px; transition: border-color 0.3s;}
        .crop-title { display: flex; align-items: center; gap: 15px; }
        .crop-icon { width: 50px; height: 50px; background: #f0fdf4; color: #22c55e; border-radius: 12px; display: flex; justify-content: center; align-items: center; font-size: 24px; transition: 0.3s; }
        .crop-title h2 { font-size: 18px; font-weight: 800; color: #1e293b; transition: color 0.3s; }
        .match-badge { background: #dcfce7; color: #16a34a; padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: 700; display: flex; align-items: center; gap: 5px; border: 1px solid #bbf7d0; transition: 0.3s;}
        
        .crop-stats { display: flex; gap: 40px; margin-bottom: 20px; }
        .stat { display: flex; flex-direction: column; gap: 4px; }
        .stat-label { font-size: 12px; color: #64748b; font-weight: 600; text-transform: uppercase; transition: color 0.3s;}
        .stat-val { font-size: 15px; font-weight: 700; color: #334155; transition: color 0.3s;}

        .tips-list { list-style: none; margin-bottom: 20px; }
        .tips-list li { font-size: 13px; color: #475569; margin-bottom: 8px; display: flex; align-items: flex-start; gap: 8px; line-height: 1.4; transition: color 0.3s;}
        .tips-list li::before { content: "•"; color: #22c55e; font-weight: bold; font-size: 16px; }

        /* Information Boxes */
        .info-box { padding: 15px; border-radius: 12px; display: flex; gap: 15px; margin-bottom: 12px; transition: 0.3s; }
        .info-box.blue { background: #eff6ff; border-left: 4px solid #3b82f6; }
        .info-box.green { background: #f0fdf4; border-left: 4px solid #22c55e; }
        .info-box.orange { background: #fff7ed; border-left: 4px solid #f97316; }
        .info-icon { font-size: 20px; }
        .blue .info-icon { color: #3b82f6; }
        .green .info-icon { color: #22c55e; }
        .orange .info-icon { color: #f97316; }
        .info-text h4 { font-size: 13px; font-weight: 700; margin-bottom: 3px; color: #1e293b; transition: color 0.3s; }
        .info-text p { font-size: 12px; color: #475569; line-height: 1.4; transition: color 0.3s;}

        /* Expert Advice Box */
        .expert-advice { background: #00b050; color: white; padding: 20px; border-radius: 16px; display: flex; gap: 15px; align-items: flex-start; margin-top: 10px; box-shadow: 0 4px 15px rgba(0, 176, 80, 0.2); }
        .expert-advice i { font-size: 24px; background: rgba(255,255,255,0.2); padding: 10px; border-radius: 50%; }
        .expert-advice h4 { font-size: 15px; font-weight: 800; margin-bottom: 5px; }
        .expert-advice p { font-size: 13px; line-height: 1.5; opacity: 0.95; }

        /* Empty State */
        .empty-state { text-align: center; padding: 60px 20px; background: white; border-radius: 20px; border: 2px dashed #cbd5e1; color: #94a3b8; transition: 0.3s; }
        .empty-state i { font-size: 40px; margin-bottom: 15px; color: #cbd5e1; transition: color 0.3s;}

        /* =========================================
           GLOBAL DARK MODE STYLES
           ========================================= */
        html.dark-mode body { background-color: #121212; color: #f8fafc; }
        html.dark-mode .header-bg { background: linear-gradient(135deg, #064e3b 0%, #022c22 100%); }
        
        html.dark-mode .form-card, 
        html.dark-mode .crop-card { 
            background-color: #1e1e1e !important; 
            border-color: #333 !important; 
            box-shadow: none !important; 
        }

        html.dark-mode .empty-state {
            background-color: #1e1e1e !important; 
            border-color: #333 !important; 
        }

        html.dark-mode .input-box { background-color: #121212 !important; border-color: #333 !important; color: #f8fafc !important; }
        html.dark-mode .input-box:focus { border-color: #00b050 !important; }

        html.dark-mode h3, html.dark-mode h2, 
        html.dark-mode .results-header, 
        html.dark-mode .stat-val, 
        html.dark-mode .info-text h4 { color: #f8fafc !important; }
        
        html.dark-mode label, 
        html.dark-mode .stat-label, 
        html.dark-mode .tips-list li, 
        html.dark-mode .info-text p { color: #94a3b8 !important; }

        html.dark-mode .crop-top { border-bottom-color: #333 !important; }
        html.dark-mode .crop-icon { background-color: #064e3b !important; }
        html.dark-mode .match-badge { background-color: #064e3b !important; border-color: #059669 !important; color: #6ee7b7 !important; }
        
        html.dark-mode .info-box.blue { background-color: #172554 !important; border-color: #3b82f6 !important; }
        html.dark-mode .info-box.green { background-color: #064e3b !important; border-color: #10b981 !important; }
        html.dark-mode .info-box.orange { background-color: #431407 !important; border-color: #f97316 !important; }

        html.dark-mode .expert-advice { background-color: #065f46 !important; box-shadow: none !important; }


        @media (max-width: 768px) {
            .main-wrapper { grid-template-columns: 1fr; padding: 0 15px; margin-top: 20px; }
            .header-bg { padding: 20px; }
            .crop-stats { flex-direction: column; gap: 15px; }
        }
    </style>
</head>
<body>

    <div class="header-bg">
        <div class="top-nav">
            <a href="farmer-dashboard.php" class="back-btn"><i class="fa-solid fa-arrow-left"></i><span>Dashboard</span></a>
            <a href="alerts.php" style="color: white; text-decoration: none;"><i class="fa-regular fa-bell" style="font-size: 20px;"></i></a>
        </div>
        <div class="header-content">
            <div class="icon-circle"><i class="fa-solid fa-leaf"></i></div>
            <h1>Smart Crop Recommendations</h1>
            <p>Based on your field conditions</p>
        </div>
    </div>

    <div class="main-wrapper">
        <div class="form-column">
            <div class="form-card">
                <h3><i class="fa-solid fa-clipboard-list" style="color:#00b050;"></i> Enter Field Details</h3>
                <form id="cropForm" onsubmit="getRecommendations(event)">
                    <div class="form-group">
                        <label><i class="fa-solid fa-location-dot"></i> District</label>
                        <input type="text" id="district" class="input-box" placeholder="e.g. Pune, Maharashtra" required>
                    </div>

                    <div class="form-group">
                        <label><i class="fa-solid fa-mound"></i> Soil Type</label>
                        <select id="soil" class="input-box" required>
                            <option value="">Select Soil Type</option>
                            <option value="Black Soil">Black Soil</option>
                            <option value="Red Soil">Red Soil</option>
                            <option value="Alluvial Soil">Alluvial Soil</option>
                            <option value="Laterite Soil">Laterite Soil</option>
                            <option value="Sandy Soil">Sandy/Loam Soil</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label><i class="fa-solid fa-droplet"></i> Water Availability</label>
                        <select id="water" class="input-box" required>
                            <option value="">Select Water Availability</option>
                            <option value="High (Irrigated)">High (Fully Irrigated)</option>
                            <option value="Medium (Rainfed/Borewell)">Medium (Rainfed/Borewell)</option>
                            <option value="Low (Dryland)">Low (Dryland/Scarcity)</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label><i class="fa-solid fa-ruler-combined"></i> Land Area (In acres)</label>
                        <input type="number" id="area" step="0.1" class="input-box" placeholder="Enter area in acres" required>
                    </div>

                    <button type="submit" class="submit-btn" id="submitBtn">Get Recommendations</button>
                </form>
            </div>
        </div>

        <div class="results-column">
            
            <div id="empty-state" class="empty-state">
                <i class="fa-solid fa-seedling"></i>
                <h3>Ready to Analyze</h3>
                <p>Fill out the form on the left to get AI-powered crop recommendations.</p>
            </div>

            <div id="loader" class="loader">
                <i class="fa-solid fa-microchip fa-beat-fade"></i>
                <h3>FarmWise AI is analyzing your field...</h3>
                <p style="font-size: 13px; color: #64748b;">Processing soil and climate data</p>
            </div>

            <div id="results-area" class="results-area" style="display: none;">
                <div class="results-header">
                    <span><i class="fa-solid fa-award" style="color:#00b050; margin-right:5px;"></i> Recommended Crops</span>
                    <span class="count" id="crop-count">0 crops found</span>
                </div>
                
                <div id="cards-container"></div>
                
                <div class="expert-advice" id="expert-advice">
                    <i class="fa-solid fa-user-tie"></i>
                    <div>
                        <h4>Expert Advice</h4>
                        <p id="advice-text"></p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function getRecommendations(e) {
            e.preventDefault();

            // UI Changes
            document.getElementById('empty-state').style.display = 'none';
            document.getElementById('results-area').style.display = 'none';
            document.getElementById('loader').style.display = 'block';
            document.getElementById('submitBtn').disabled = true;
            document.getElementById('submitBtn').innerText = 'Analyzing...';

            // Gather Data
            const formData = new FormData();
            formData.append('district', document.getElementById('district').value);
            formData.append('soil', document.getElementById('soil').value);
            formData.append('water', document.getElementById('water').value);
            formData.append('area', document.getElementById('area').value);

            // Send to AI
            fetch('crop-api.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    alert(data.error);
                    resetUI();
                    return;
                }

                // Build the UI with the JSON Data
                document.getElementById('crop-count').innerText = `${data.crops.length} crops found`;
                document.getElementById('advice-text').innerText = data.expert_advice;
                
                const container = document.getElementById('cards-container');
                container.innerHTML = ''; // Clear old results

                data.crops.forEach(crop => {
                    const cardHTML = `
                    <div class="crop-card">
                        <div class="crop-top">
                            <div class="crop-title">
                                <div class="crop-icon"><i class="fa-solid fa-leaf"></i></div>
                                <h2>${crop.name}</h2>
                            </div>
                            <div class="match-badge"><i class="fa-solid fa-bolt"></i> ${crop.match}</div>
                        </div>

                        <div class="crop-stats">
                            <div class="stat">
                                <span class="stat-label">Expected Yield</span>
                                <span class="stat-val">${crop.yield}</span>
                            </div>
                            <div class="stat">
                                <span class="stat-label">Sowing Time</span>
                                <span class="stat-val">${crop.sowing}</span>
                            </div>
                        </div>

                        <ul class="tips-list">
                            ${crop.tips.map(tip => `<li>${tip}</li>`).join('')}
                        </ul>

                        <div class="info-box blue">
                            <i class="fa-solid fa-droplet info-icon"></i>
                            <div class="info-text"><h4>Irrigation</h4><p>${crop.irrigation}</p></div>
                        </div>
                        <div class="info-box green">
                            <i class="fa-solid fa-sack-dollar info-icon"></i>
                            <div class="info-text"><h4>Fertilizer</h4><p>${crop.fertilizer}</p></div>
                        </div>
                        <div class="info-box orange">
                            <i class="fa-solid fa-shield-cat info-icon"></i>
                            <div class="info-text"><h4>Pesticide</h4><p>${crop.pesticide}</p></div>
                        </div>
                    </div>`;
                    
                    container.insertAdjacentHTML('beforeend', cardHTML);
                });

                // Show Results
                document.getElementById('loader').style.display = 'none';
                document.getElementById('results-area').style.display = 'flex';
                document.getElementById('submitBtn').disabled = false;
                document.getElementById('submitBtn').innerText = 'Get Recommendations';
            })
            .catch(error => {
                alert('Error connecting to the AI. Please try again.');
                resetUI();
            });
        }

        function resetUI() {
            document.getElementById('loader').style.display = 'none';
            document.getElementById('empty-state').style.display = 'block';
            document.getElementById('submitBtn').disabled = false;
            document.getElementById('submitBtn').innerText = 'Get Recommendations';
        }
    </script>
</body>
</html>