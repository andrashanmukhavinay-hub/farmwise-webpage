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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>My Farm - FarmWise</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
        body { background-color: #f1f5f9; color: #1e293b; min-height: 100vh; overflow-x: hidden; }

        .app-container { width: 100%; max-width: 1400px; margin: 0 auto; display: flex; flex-direction: column; min-height: 100vh; }

        /* Header (Green Theme) */
        .header-bg { background: linear-gradient(135deg, #16a34a 0%, #15803d 100%); color: white; padding: 25px 50px; border-bottom-left-radius: 30px; border-bottom-right-radius: 30px; box-shadow: 0 10px 30px rgba(22, 163, 74, 0.15); }
        .top-nav { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; }
        .back-btn { color: white; text-decoration: none; font-size: 18px; display: flex; align-items: center; gap: 10px; font-weight: 600; transition: 0.2s; }
        .back-btn:hover { opacity: 0.8; }
        
        .header-content { display: flex; align-items: center; gap: 20px; }
        .icon-circle { width: 60px; height: 60px; background: rgba(255,255,255,0.2); border-radius: 50%; display: flex; justify-content: center; align-items: center; font-size: 30px; border: 2px solid rgba(255,255,255,0.4); }
        .header-text h1 { font-size: 28px; font-weight: 800; margin-bottom: 5px; }
        .header-text p { font-size: 15px; opacity: 0.9; }

        /* Main Layout */
        .main-wrapper { display: grid; grid-template-columns: 350px 1fr; gap: 30px; padding: 40px 50px; align-items: start; }

        /* --- LEFT COLUMN: Form --- */
        .sidebar { position: sticky; top: 30px; }
        .form-card { background: white; padding: 30px; border-radius: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.03); border: 1px solid #f1f5f9; }
        .form-card h3 { font-size: 18px; color: #1e293b; margin-bottom: 25px; display: flex; align-items: center; gap: 10px; }
        .form-card h3 i { color: #16a34a; }
        
        .form-group { margin-bottom: 20px; }
        .form-group label { display: flex; align-items: center; gap: 8px; font-size: 13px; font-weight: 700; color: #475569; margin-bottom: 8px; }
        .input-box { width: 100%; padding: 14px 15px; border: 1px solid #cbd5e1; border-radius: 12px; font-size: 14px; outline: none; background: #f8fafc; transition: 0.3s; }
        .input-box:focus { border-color: #16a34a; background: white; box-shadow: 0 0 0 3px rgba(22, 163, 74, 0.1); }

        .submit-btn { width: 100%; background: #16a34a; color: white; border: none; padding: 16px; border-radius: 14px; font-size: 16px; font-weight: 700; cursor: pointer; transition: 0.3s; box-shadow: 0 4px 15px rgba(22, 163, 74, 0.3); margin-top: 10px; }
        .submit-btn:hover { background: #15803d; transform: translateY(-2px); }

        /* --- RIGHT COLUMN: Dashboard --- */
        .results-dashboard { display: flex; flex-direction: column; gap: 20px; }

        .empty-state { background: white; border-radius: 20px; padding: 80px 40px; text-align: center; border: 2px dashed #e2e8f0; color: #94a3b8; height: 100%; min-height: 500px; display: flex; flex-direction: column; justify-content: center; }
        .empty-state i { font-size: 60px; margin-bottom: 20px; color: #cbd5e1; }
        
        .loader-card { display: none; background: white; border-radius: 20px; padding: 80px 40px; text-align: center; box-shadow: 0 4px 15px rgba(0,0,0,0.03); height: 100%; min-height: 500px; flex-direction: column; justify-content: center; }
        .loader-card i { font-size: 60px; color: #16a34a; margin-bottom: 20px; }

        #results-area { display: none; display: flex; flex-direction: column; gap: 25px; }

        /* 1. Hero Card (Top Green Box) */
        .hero-card { background: linear-gradient(135deg, #16a34a 0%, #15803d 100%); color: white; border-radius: 24px; padding: 35px; box-shadow: 0 10px 25px rgba(22, 163, 74, 0.2); display: flex; flex-direction: column; gap: 20px; }
        .hero-top { display: flex; justify-content: space-between; align-items: flex-start; }
        .hero-title h2 { font-size: 28px; font-weight: 800; margin-bottom: 5px; }
        .hero-title p { font-size: 14px; opacity: 0.9; display: flex; align-items: center; gap: 5px; }
        .hero-icon { font-size: 40px; opacity: 0.3; }
        
        .hero-tags { display: flex; gap: 10px; flex-wrap: wrap; }
        .tag { background: rgba(255,255,255,0.2); padding: 6px 15px; border-radius: 20px; font-size: 13px; font-weight: 600; border: 1px solid rgba(255,255,255,0.3); }

        .quick-stats { display: flex; justify-content: space-between; background: rgba(0,0,0,0.15); padding: 15px 25px; border-radius: 16px; }
        .qs-item { text-align: center; }
        .qs-item h4 { font-size: 20px; font-weight: 800; }
        .qs-item p { font-size: 11px; text-transform: uppercase; letter-spacing: 1px; opacity: 0.8; margin-top: 2px; }

        /* Action Buttons Row */
        .action-row { display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; }
        .action-btn { padding: 15px; border-radius: 16px; color: white; font-weight: 700; display: flex; flex-direction: column; align-items: center; gap: 8px; font-size: 13px; cursor: pointer; transition: 0.2s; border: none; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
        .action-btn:hover { transform: translateY(-2px); }
        .btn-g { background: #10b981; } .btn-b { background: #3b82f6; } .btn-p { background: #8b5cf6; }
        .action-btn i { font-size: 20px; }

        /* Card Base */
        .card { background: white; border-radius: 20px; padding: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.02); border: 1px solid #f1f5f9; }
        .card-header { display: flex; align-items: center; gap: 10px; font-size: 16px; font-weight: 800; color: #1e293b; margin-bottom: 20px; }

        /* Performance Section */
        .health-score-box { background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 16px; padding: 20px; display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .hs-text p { font-size: 13px; color: #166534; font-weight: 700; text-transform: uppercase; margin-bottom: 5px; }
        .hs-text h1 { font-size: 36px; color: #15803d; font-weight: 800; display: flex; align-items: baseline; gap: 5px; }
        .hs-text h1 span { font-size: 16px; font-weight: 600; color: #22c55e; }
        .hs-icon { width: 50px; height: 50px; background: #22c55e; color: white; border-radius: 50%; display: flex; justify-content: center; align-items: center; font-size: 24px; box-shadow: 0 4px 10px rgba(34, 197, 94, 0.3); }
        
        .chart-container { width: 100%; height: 200px; position: relative; margin-bottom: 15px; }
        .insight-box { background: #eff6ff; border-left: 4px solid #3b82f6; padding: 15px; border-radius: 12px; font-size: 13px; color: #1e40af; display: flex; gap: 10px; }

        /* Financial Section */
        .fin-total-box { background: #16a34a; color: white; padding: 25px; border-radius: 16px; text-align: center; margin-bottom: 25px; box-shadow: 0 4px 15px rgba(22, 163, 74, 0.3); }
        .fin-total-box p { font-size: 13px; font-weight: 600; opacity: 0.9; margin-bottom: 5px; text-transform: uppercase; }
        .fin-total-box h1 { font-size: 40px; font-weight: 800; }

        .fin-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 30px; align-items: center; }
        
        .expense-bars { display: flex; flex-direction: column; gap: 15px; }
        .exp-row { display: flex; flex-direction: column; gap: 6px; }
        .exp-labels { display: flex; justify-content: space-between; font-size: 13px; font-weight: 700; color: #475569; }
        .exp-labels span:last-child { color: #1e293b; }
        .exp-track { width: 100%; height: 8px; background: #f1f5f9; border-radius: 4px; overflow: hidden; }
        .exp-fill { height: 100%; border-radius: 4px; transition: 1s; }

        .pie-container { height: 200px; position: relative; }

        /* Crop History Section */
        .history-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 15px; }
        .history-card { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 16px; padding: 20px; }
        .hc-top { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; }
        .hc-top h4 { font-size: 16px; font-weight: 800; color: #1e293b; }
        .hc-badge { padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; }
        .badge-grow { background: #dcfce7; color: #16a34a; }
        .badge-harv { background: #fef08a; color: #b45309; }

        .hc-stats { display: flex; justify-content: space-between; margin-bottom: 15px; }
        .hc-stat p { font-size: 11px; color: #64748b; font-weight: 600; text-transform: uppercase; margin-bottom: 2px; }
        .hc-stat h5 { font-size: 14px; color: #334155; font-weight: 700; }

        .hc-prog-track { width: 100%; height: 6px; background: #e2e8f0; border-radius: 3px; overflow: hidden; }
        .hc-prog-fill { height: 100%; background: #8b5cf6; border-radius: 3px; }

        /* Pro Tip */
        .pro-tip { background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 16px; padding: 20px; display: flex; gap: 15px; margin-top: 10px; }
        .tip-icon { width: 40px; height: 40px; background: #22c55e; color: white; border-radius: 50%; display: flex; justify-content: center; align-items: center; font-size: 18px; flex-shrink: 0; }
        .tip-text h4 { font-size: 15px; font-weight: 800; color: #166534; margin-bottom: 4px; }
        .tip-text p { font-size: 13px; color: #15803d; line-height: 1.5; }

        @media (max-width: 1024px) {
            .main-wrapper { grid-template-columns: 1fr; padding: 20px; gap: 20px; }
            .header-bg { padding: 20px; border-radius: 0 0 20px 20px; }
            .sidebar { position: static; }
            .fin-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

    <div class="app-container">
        
        <div class="header-bg">
            <div class="top-nav">
                <a href="farmer-dashboard.php" class="back-btn"><i class="fa-solid fa-arrow-left"></i> Dashboard</a>
                <i class="fa-solid fa-rotate-right" style="font-size: 20px; cursor:pointer;" onclick="location.reload()"></i>
            </div>
            <div class="header-content">
                <div class="icon-circle"><i class="fa-solid fa-tractor"></i></div>
                <div class="header-text">
                    <h1>My Farm Hub</h1>
                    <p>Profile, crop history, and financial tracking</p>
                </div>
            </div>
        </div>

        <div class="main-wrapper">
            
            <div class="sidebar">
                <div class="form-card">
                    <h3><i class="fa-solid fa-pen-to-square"></i> Configure Farm Profile</h3>
                    <form onsubmit="getFarmData(event)">
                        
                        <div class="form-group">
                            <label><i class="fa-solid fa-house-chimney-window"></i> Farm Name</label>
                            <input type="text" id="fname" class="input-box" placeholder="e.g. Green Valley Farm" required>
                        </div>

                        <div class="form-group">
                            <label><i class="fa-solid fa-ruler-combined"></i> Total Area (Acres)</label>
                            <input type="number" id="area" step="0.1" class="input-box" placeholder="e.g. 7" required>
                        </div>

                        <div class="form-group">
                            <label><i class="fa-solid fa-seedling"></i> Main Crops Grown</label>
                            <input type="text" id="crops" class="input-box" placeholder="e.g. Wheat, Rice, Cotton" required>
                        </div>

                        <button type="submit" class="submit-btn" id="submitBtn">Generate Farm Report</button>
                    </form>
                </div>
            </div>

            <div class="results-dashboard">
                
                <div id="empty-state" class="empty-state">
                    <i class="fa-solid fa-tractor"></i>
                    <h3>Welcome to Your Farm Hub</h3>
                    <p>Enter your farm details on the left to generate your complete operational dashboard.</p>
                </div>

                <div id="loader" class="loader-card">
                    <i class="fa-solid fa-microchip fa-beat-fade"></i>
                    <h3>Compiling Farm Data...</h3>
                    <p style="color: #64748b;">Building charts and financial models.</p>
                </div>

                <div id="results-area">
                    
                    <div class="hero-card">
                        <div class="hero-top">
                            <div class="hero-title">
                                <h2 id="res-fname">--</h2>
                                <p><i class="fa-solid fa-location-dot"></i> Your Farm Overview</p>
                            </div>
                            <i class="fa-solid fa-leaf hero-icon"></i>
                        </div>
                        
                        <div class="hero-tags" id="res-tags">
                            </div>

                        <div class="quick-stats">
                            <div class="qs-item">
                                <h4 id="qs-workers">--</h4>
                                <p>Workers</p>
                            </div>
                            <div class="qs-item">
                                <h4 id="qs-hours">--</h4>
                                <p>Total Hours</p>
                            </div>
                            <div class="qs-item">
                                <h4 id="qs-pay">--</h4>
                                <p>Est. Payroll</p>
                            </div>
                        </div>
                    </div>

                    <div class="action-row">
                        <button class="action-btn btn-g"><i class="fa-solid fa-seedling"></i> Add Crop</button>
                        <button class="action-btn btn-b"><i class="fa-solid fa-indian-rupee-sign"></i> Add Expense</button>
                        <button class="action-btn btn-p"><i class="fa-solid fa-users"></i> Add Worker</button>
                    </div>

                    <div class="card">
                        <div class="card-header"><i class="fa-solid fa-chart-line" style="color:#16a34a;"></i> Performance Grading</div>
                        
                        <div class="health-score-box">
                            <div class="hs-text">
                                <p>Farm Health Score</p>
                                <h1><span id="res-score">--</span><span>/ 100</span></h1>
                            </div>
                            <div class="hs-icon"><i class="fa-solid fa-star"></i></div>
                        </div>

                        <div class="chart-container">
                            <canvas id="perfChart"></canvas>
                        </div>

                        <div class="insight-box">
                            <i class="fa-solid fa-circle-info" style="margin-top:2px;"></i>
                            <span id="res-insight">--</span>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header"><i class="fa-solid fa-wallet" style="color:#2563eb;"></i> Financial Tracking</div>
                        
                        <div class="fin-total-box">
                            <p>Total Current Season Expense</p>
                            <h1 id="res-total-exp">₹--</h1>
                        </div>

                        <div class="fin-grid">
                            <div class="expense-bars" id="exp-bars">
                                </div>
                            <div class="pie-container">
                                <canvas id="pieChart"></canvas>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header"><i class="fa-solid fa-clock-rotate-left" style="color:#8b5cf6;"></i> Crop History</div>
                        <div class="history-grid" id="res-history">
                            </div>
                    </div>

                    <div class="pro-tip">
                        <div class="tip-icon"><i class="fa-solid fa-lightbulb"></i></div>
                        <div class="tip-text">
                            <h4>Farm Management Tip</h4>
                            <p id="res-tip">--</p>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <script>
        let perfChartInstance = null;
        let pieChartInstance = null;

        function getFarmData(e) {
            e.preventDefault();

            document.getElementById('empty-state').style.display = 'none';
            document.getElementById('results-area').style.display = 'none';
            document.getElementById('loader').style.display = 'flex';
            
            const btn = document.getElementById('submitBtn');
            btn.disabled = true;
            btn.innerText = 'Analyzing...';

            const formData = new FormData();
            formData.append('farm_name', document.getElementById('fname').value);
            formData.append('area', document.getElementById('area').value);
            formData.append('crops', document.getElementById('crops').value);

            fetch('myfarm-api.php', {
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
                buildUI(data, document.getElementById('fname').value, document.getElementById('area').value, document.getElementById('crops').value);
            })
            .catch(error => {
                alert('Error connecting to AI. Please try again.');
                location.reload();
            });
        }

        function buildUI(data, fName, fArea, fCrops) {
            // 1. Hero
            document.getElementById('res-fname').innerText = fName;
            
            // Generate Tags based on inputs
            const cropsArray = fCrops.split(',');
            let tagsHtml = `<div class="tag">${fArea} Acres</div>`;
            cropsArray.forEach(crop => {
                if(crop.trim() !== '') tagsHtml += `<div class="tag">${crop.trim()}</div>`;
            });
            document.getElementById('res-tags').innerHTML = tagsHtml;

            document.getElementById('qs-workers').innerText = data.quick_stats.workers;
            document.getElementById('qs-hours').innerText = data.quick_stats.hours_tracked;
            document.getElementById('qs-pay').innerText = `₹${data.quick_stats.payroll}`;

            // 2. Performance
            document.getElementById('res-score').innerText = data.performance.health_score;
            document.getElementById('res-insight').innerText = data.performance.insight;

            // Line Chart
            if (perfChartInstance) perfChartInstance.destroy();
            const ctxPerf = document.getElementById('perfChart').getContext('2d');
            perfChartInstance = new Chart(ctxPerf, {
                type: 'line',
                data: {
                    labels: data.performance.chart.labels,
                    datasets: [
                        {
                            label: 'This Year',
                            data: data.performance.chart.this_year,
                            borderColor: '#16a34a',
                            backgroundColor: 'rgba(22, 163, 74, 0.1)',
                            borderWidth: 3, fill: true, tension: 0.4
                        },
                        {
                            label: 'Last Year',
                            data: data.performance.chart.last_year,
                            borderColor: '#3b82f6',
                            borderWidth: 2, fill: false, tension: 0.4, borderDash: [5, 5]
                        }
                    ]
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    plugins: { legend: { position: 'bottom' } }
                }
            });

            // 3. Financials
            document.getElementById('res-total-exp').innerText = `₹${data.financials.total_expense.toLocaleString('en-IN')}`;
            
            const b = data.financials.breakdown;
            const colors = { seeds: '#10b981', fertilizer: '#3b82f6', labor: '#f59e0b', equipment: '#8b5cf6' };
            
            let barsHtml = '';
            let pieLabels = [];
            let pieData = [];
            let pieColors = [];

            for (const [key, val] of Object.entries(b)) {
                let color = colors[key] || '#cbd5e1';
                let label = key.charAt(0).toUpperCase() + key.slice(1);
                
                barsHtml += `
                    <div class="exp-row">
                        <div class="exp-labels">
                            <span style="display:flex; align-items:center; gap:8px;">
                                <div style="width:10px;height:10px;background:${color};border-radius:2px;"></div>
                                ${label}
                            </span>
                            <span>₹${val.amount.toLocaleString('en-IN')} (${val.pct}%)</span>
                        </div>
                        <div class="exp-track"><div class="exp-fill" style="width:${val.pct}%; background:${color};"></div></div>
                    </div>
                `;
                pieLabels.push(label);
                pieData.push(val.amount);
                pieColors.push(color);
            }
            document.getElementById('exp-bars').innerHTML = barsHtml;

            // Pie Chart
            if (pieChartInstance) pieChartInstance.destroy();
            const ctxPie = document.getElementById('pieChart').getContext('2d');
            pieChartInstance = new Chart(ctxPie, {
                type: 'doughnut',
                data: {
                    labels: pieLabels,
                    datasets: [{ data: pieData, backgroundColor: pieColors, borderWidth: 0 }]
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    cutout: '60%'
                }
            });

            // 4. History
            const histHtml = data.crop_history.map(item => {
                const isGrowing = item.status.toLowerCase().includes('grow');
                const badgeClass = isGrowing ? 'badge-grow' : 'badge-harv';
                return `
                    <div class="history-card">
                        <div class="hc-top">
                            <h4>${item.name}</h4>
                            <div class="hc-badge ${badgeClass}">${item.status}</div>
                        </div>
                        <div class="hc-stats">
                            <div class="hc-stat"><p>Yield</p><h5>${item.yield}</h5></div>
                            <div class="hc-stat"><p>Area</p><h5>${item.area}</h5></div>
                            <div class="hc-stat"><p>Prog</p><h5>${item.progress_pct}%</h5></div>
                        </div>
                        <div class="hc-prog-track"><div class="hc-prog-fill" style="width:${item.progress_pct}%"></div></div>
                    </div>
                `;
            }).join('');
            document.getElementById('res-history').innerHTML = histHtml;

            // 5. Tip
            document.getElementById('res-tip').innerText = data.management_tip;

            // Reveal
            document.getElementById('loader').style.display = 'none';
            document.getElementById('results-area').style.display = 'flex';
            
            const btn = document.getElementById('submitBtn');
            btn.disabled = false;
            btn.innerText = 'Refresh Report';
        }
    </script>
</body>
</html>