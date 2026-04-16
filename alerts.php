<?php
session_start();
require 'db.php';
if (!isset($_SESSION['logged_in'])) { header("Location: farmer-login.php"); exit(); }

$user_id = $_SESSION['user_id'];

// Initial load of history
$stmt = $conn->prepare("SELECT * FROM announcements ORDER BY created_at DESC LIMIT 15");
$stmt->execute();
$announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Helper function for time formatting
function time_ago($timestamp) {
    $time_ago = strtotime($timestamp);
    $cur_time = time();
    $time_elapsed = $cur_time - $time_ago;
    $seconds = $time_elapsed;
    $minutes = round($time_elapsed / 60);
    $hours = round($time_elapsed / 3600);
    $days = round($time_elapsed / 86400);

    if ($seconds <= 60) return "Just now";
    else if ($minutes <= 60) return ($minutes == 1) ? "1 minute ago" : "$minutes mins ago";
    else if ($hours <= 24) return ($hours == 1) ? "1 hour ago" : "$hours hours ago";
    else return date("M d, Y", $time_ago);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Real-Time Alerts - FarmWise</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <script>
        if (localStorage.getItem('farmwise_theme') === 'dark') {
            document.documentElement.classList.add('dark-mode');
        }
    </script>

    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
        body { background: #f1f5f9; color: #1e293b; padding: 40px 20px; transition: background-color 0.3s, color 0.3s; }
        
        .container { max-width: 800px; margin: 0 auto; }
        
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .header h1 { font-size: 28px; font-weight: 800; }
        .back-dash { text-decoration: none; color: #059669; font-weight: 700; display: flex; align-items: center; gap: 8px; transition: 0.2s; }
        .back-dash:hover { transform: translateX(-5px); color: #047857; }

        .alert-list { display: flex; flex-direction: column; gap: 15px; }

        .alert-card { 
            background: white; 
            padding: 20px; 
            border-radius: 18px; 
            display: flex; 
            gap: 20px; 
            border-left: 6px solid #22c55e; 
            box-shadow: 0 4px 12px rgba(0,0,0,0.03); 
            transition: 0.3s;
            position: relative;
        }
        
        /* Severity Colors */
        .alert-card.high { border-left-color: #ef4444; background: #fff1f2; }
        .alert-card.medium { border-left-color: #f59e0b; background: #fffbeb; }
        .alert-card.low { border-left-color: #3b82f6; background: #eff6ff; }

        .icon { font-size: 24px; color: #64748b; flex-shrink: 0; margin-top: 2px; }
        .high .icon { color: #ef4444; }
        .medium .icon { color: #d97706; }
        
        .alert-content { flex: 1; }
        .alert-content h3 { margin-bottom: 5px; font-size: 18px; font-weight: 800; color: #1e293b; transition: color 0.3s; }
        .alert-content p { color: #475569; font-size: 14px; line-height: 1.5; transition: color 0.3s; }
        
        .alert-time { font-size: 11px; font-weight: 700; text-transform: uppercase; color: #94a3b8; margin-top: 12px; display: block; letter-spacing: 0.5px; }

        /* =========================================
           GLOBAL DARK MODE STYLES
           ========================================= */
        html.dark-mode body { background-color: #0f172a; color: #f8fafc; }
        
        html.dark-mode .alert-card { background-color: #1e293b; border-color: #334155; box-shadow: none; }
        
        html.dark-mode .alert-card.high { background-color: #450a0a; border-left-color: #ef4444; }
        html.dark-mode .alert-card.medium { background-color: #422006; border-left-color: #f59e0b; }
        html.dark-mode .alert-card.low { background-color: #172554; border-left-color: #3b82f6; }

        html.dark-mode .alert-content h3 { color: #f8fafc; }
        html.dark-mode .alert-content p { color: #94a3b8; }
        html.dark-mode .alert-time { color: #64748b; }

        @media (max-width: 600px) {
            body { padding: 20px 15px; }
            .header h1 { font-size: 22px; }
            .alert-card { gap: 15px; padding: 15px; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Alert Center</h1>
            <a href="farmer-dashboard.php" class="back-dash"><i class="fa-solid fa-arrow-left"></i> Dashboard</a>
        </div>

        <div class="alert-list" id="alert-container">
            <?php if(empty($announcements)): ?>
                <div style="text-align:center; padding:50px; color:#94a3b8;">
                    <i class="fa-solid fa-bell-slash" style="font-size:40px; margin-bottom:15px; opacity:0.3;"></i>
                    <p>No alerts at this time.</p>
                </div>
            <?php else: ?>
                <?php foreach($announcements as $a): ?>
                <div class="alert-card <?php echo strtolower($a['severity']); ?>">
                    <div class="icon">
                        <?php 
                            if($a['severity'] == 'high') echo '<i class="fa-solid fa-triangle-exclamation"></i>';
                            else echo '<i class="fa-solid fa-circle-exclamation"></i>';
                        ?>
                    </div>
                    <div class="alert-content">
                        <h3><?php echo htmlspecialchars($a['title']); ?></h3>
                        <p><?php echo htmlspecialchars($a['message']); ?></p>
                        <span class="alert-time"><?php echo time_ago($a['created_at']); ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // REAL-TIME UPDATER: Refresh the list every 15 seconds to catch new alerts
        function refreshAlerts() {
            fetch('alerts.php')
                .then(res => res.text())
                .then(html => {
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'text/html');
                    const newList = doc.getElementById('alert-container').innerHTML;
                    document.getElementById('alert-container').innerHTML = newList;
                })
                .catch(err => console.warn("Alert update paused."));
        }

        setInterval(refreshAlerts, 15000);
    </script>
</body>
</html>