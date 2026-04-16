<?php
session_start();
require 'db.php';

// Check if logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: farmer-login.php");
    exit();
}

$type = $_GET['type'] ?? '';
$id = (int)($_GET['id'] ?? 0);
$user_id = $_SESSION['user_id'];

$report = null;
$title = "";
$theme_gradient = "linear-gradient(135deg, #6366f1 0%, #4338ca 100%)";
$accent = "#818cf8";

try {
    if ($type == 'market') {
        $stmt = $conn->prepare("SELECT region as header, crops_monitored as sub, market_json as json, created_at FROM market_insights WHERE id = ? AND user_id = ?");
        $title = "Market Intelligence";
        $theme_gradient = "linear-gradient(135deg, #2563eb 0%, #1e40af 100%)";
        $accent = "#3b82f6";
    } elseif ($type == 'pest') {
        $stmt = $conn->prepare("SELECT disease_name as header, image_path as sub, treatment_advice as json, created_at FROM pest_diagnoses WHERE id = ? AND user_id = ?");
        $title = "Pest Diagnosis";
        $theme_gradient = "linear-gradient(135deg, #ef4444 0%, #991b1b 100%)";
        $accent = "#f87171";
    } elseif ($type == 'yield') {
        $stmt = $conn->prepare("SELECT crop_type as header, land_area as sub, prediction_json as json, created_at FROM yield_predictions WHERE id = ? AND user_id = ?");
        $title = "Yield Prediction";
        $theme_gradient = "linear-gradient(135deg, #8b5cf6 0%, #5b21b6 100%)";
        $accent = "#a78bfa";
    } elseif ($type == 'resource') {
        $stmt = $conn->prepare("SELECT crop_name as header, land_area as sub, optimization_json as json, created_at FROM resource_optimizations WHERE id = ? AND user_id = ?");
        $title = "Resource Optimization";
        $theme_gradient = "linear-gradient(135deg, #0d9488 0%, #0f766e 100%)";
        $accent = "#2dd4bf";
    }

    if (isset($stmt)) {
        $stmt->execute([$id, $user_id]);
        $report = $stmt->fetch(PDO::FETCH_ASSOC);
    }
} catch(PDOException $e) {
    die("<div style='padding:20px; color:red;'>Database Error: " . $e->getMessage() . "</div>");
}

if (!$report) {
    die("<div style='padding:50px; text-align:center; font-family:sans-serif;'>
            <h2>Report Not Found</h2>
            <p>This report might have been deleted or you don't have access.</p>
            <a href='reports-analytics.php'>Back to Analytics</a>
         </div>");
}

$data = json_decode($report['json'], true);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Analysis - FarmWise</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;800&display=swap" rel="stylesheet">
    
    <script>
        if (localStorage.getItem('farmwise_theme') === 'dark') {
            document.documentElement.classList.add('dark-mode');
        }
    </script>

    <style>
        :root { --accent: <?php echo $accent; ?>; }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Plus Jakarta Sans', sans-serif; }
        
        body { background: #f8fafc; color: #1e293b; padding: 20px; line-height: 1.6; transition: background-color 0.3s, color 0.3s; }
        
        .viewer-container { max-width: 1100px; margin: 0 auto; background: white; border-radius: 30px; overflow: hidden; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.1); transition: background-color 0.3s; }
        
        .top-banner { background: <?php echo $theme_gradient; ?>; padding: 80px 50px; color: white; position: relative; }
        
        .back-btn { position: absolute; top: 30px; left: 30px; color: white; text-decoration: none; font-size: 14px; font-weight: 600; background: rgba(255,255,255,0.2); backdrop-filter: blur(10px); padding: 10px 20px; border-radius: 12px; border: 1px solid rgba(255,255,255,0.3); transition: 0.3s; display: flex; align-items: center; gap: 8px; }
        .back-btn:hover { background: white; color: black; transform: translateX(-5px); }
        
        .top-banner h1 { font-size: 42px; font-weight: 800; letter-spacing: -1px; margin-bottom: 15px; }
        .meta-strip { display: flex; gap: 25px; font-size: 16px; opacity: 0.95; }
        
        .content-body { padding: 50px; }
        .report-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 25px; }
        
        .info-card { background: #ffffff; border-radius: 24px; padding: 30px; border: 1px solid #f1f5f9; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02); transition: 0.3s; position: relative; overflow: hidden; }
        .info-card::before { content: ''; position: absolute; top: 0; left: 0; width: 4px; height: 100%; background: var(--accent); opacity: 0.5; }
        
        .label { color: var(--accent); text-transform: uppercase; font-size: 12px; font-weight: 800; letter-spacing: 1.5px; display: block; margin-bottom: 12px; }
        .value { font-size: 18px; font-weight: 700; color: #334155; transition: color 0.3s; }
        
        ul { list-style: none; margin-top: 15px; }
        li { background: #fdfdfd; margin-bottom: 10px; padding: 15px 20px; border-radius: 15px; font-size: 14px; display: flex; align-items: center; gap: 12px; border: 1px solid #f1f5f9; font-weight: 600; color: #475569; transition: all 0.3s; }
        li i { color: var(--accent); font-size: 16px; }
        
        .report-img { width: 100%; max-height: 500px; object-fit: cover; border-radius: 25px; border: 8px solid #fff; box-shadow: 0 10px 30px rgba(0,0,0,0.1); margin-top: 10px; }
        .full-width { grid-column: 1 / -1; }
        
        .footer-actions { padding: 40px 50px; background: #f8fafc; border-top: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; transition: background-color 0.3s, border-color 0.3s; }
        .print-btn { background: #1e293b; color: white; border: none; padding: 18px 35px; border-radius: 18px; cursor: pointer; font-weight: 700; display: flex; align-items: center; gap: 12px; transition: 0.3s; }
        .print-btn:hover { background: #000; transform: scale(1.03); }

        /* =========================================
           GLOBAL DARK MODE STYLES
           ========================================= */
        html.dark-mode body { background-color: #0f172a; color: #f8fafc; }
        html.dark-mode .viewer-container { background-color: #1e293b; box-shadow: none; }
        
        html.dark-mode .info-card { background-color: #1e293b !important; border-color: #334155 !important; }
        html.dark-mode .value { color: #f8fafc !important; }
        
        html.dark-mode li { background-color: #0f172a !important; border-color: #334155 !important; color: #94a3b8 !important; }
        html.dark-mode .report-img { border-color: #1e293b !important; }
        
        html.dark-mode .footer-actions { background-color: #1e293b !important; border-top-color: #334155 !important; }
        html.dark-mode .footer-actions p { color: #94a3b8 !important; }
        html.dark-mode .print-btn { background-color: #334155; }
        html.dark-mode .print-btn:hover { background-color: #475569; }

        @media print { .back-btn, .print-btn, .footer-actions { display: none !important; } body { padding: 0; } .viewer-container { box-shadow: none; border-radius: 0; } }
        
        @media (max-width: 768px) {
            .top-banner { padding: 60px 30px; }
            .top-banner h1 { font-size: 30px; }
            .content-body { padding: 30px; }
            .meta-strip { flex-direction: column; gap: 10px; }
        }
    </style>
</head>
<body>

    <div class="viewer-container">
        <div class="top-banner">
            <a href="reports-analytics.php" class="back-btn"><i class="fa-solid fa-chevron-left"></i> History</a>
            <h1><?php echo $title; ?> Report</h1>
            <div class="meta-strip">
                <span><i class="fa-solid fa-location-dot"></i> <?php echo htmlspecialchars($report['header']); ?></span>
                <span><i class="fa-solid fa-calendar-check"></i> <?php echo date('d M Y', strtotime($report['created_at'])); ?></span>
            </div>
        </div>

        <div class="content-body">
            <?php if ($type == 'pest' && !empty($report['sub'])): ?>
                <div class="info-card full-width" style="margin-bottom: 30px;">
                    <span class="label">Specimen Image</span>
                    <img src="<?php echo htmlspecialchars($report['sub']); ?>" class="report-img">
                </div>
            <?php endif; ?>

            <div class="report-grid">
                <?php 
                foreach ($data as $key => $value) {
                    if(is_null($value) || empty($value)) continue;

                    echo "<div class='info-card'>";
                    echo "<span class='label'>" . str_replace('_', ' ', $key) . "</span>";
                    
                    if (is_array($value)) {
                        echo "<ul>";
                        foreach ($value as $v) {
                            echo "<li><i class='fa-solid fa-circle-check'></i> ";
                            if (is_array($v)) {
                                $parts = [];
                                foreach ($v as $subKey => $subVal) {
                                    $parts[] = "<strong>" . ucfirst($subKey) . ":</strong> " . (is_array($subVal) ? implode(', ', $subVal) : $subVal);
                                }
                                echo implode(' — ', $parts);
                            } else {
                                echo htmlspecialchars($v);
                            }
                            echo "</li>";
                        }
                        echo "</ul>";
                    } else {
                        echo "<div class='value'>" . nl2br(htmlspecialchars($value)) . "</div>";
                    }
                    echo "</div>";
                }
                ?>
            </div>
        </div>

        <div class="footer-actions">
            <div>
                <p style="font-weight: 800; color: #475569;">FarmWise AI Analytics</p>
                <p style="font-size: 12px; color: #94a3b8;">Official Agricultural Record</p>
            </div>
            <button class="print-btn" onclick="window.print()"><i class="fa-solid fa-file-pdf"></i> Save PDF</button>
        </div>
    </div>

</body>
</html>