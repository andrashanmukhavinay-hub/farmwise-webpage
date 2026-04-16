<?php
session_start();
require 'db.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: farmer-login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

try {
    // 1. Market History
    $stmt1 = $conn->prepare("SELECT id, region, created_at FROM market_insights WHERE user_id = :uid ORDER BY created_at DESC LIMIT 10");
    $stmt1->execute([':uid' => $user_id]);
    $market_reports = $stmt1->fetchAll(PDO::FETCH_ASSOC);

    // 2. Pest History
    $stmt2 = $conn->prepare("SELECT id, disease_name, created_at FROM pest_diagnoses WHERE user_id = :uid ORDER BY created_at DESC LIMIT 10");
    $stmt2->execute([':uid' => $user_id]);
    $pest_reports = $stmt2->fetchAll(PDO::FETCH_ASSOC);

    // 3. Yield History
    $stmt3 = $conn->prepare("SELECT id, crop_type, created_at FROM yield_predictions WHERE user_id = :uid ORDER BY created_at DESC LIMIT 10");
    $stmt3->execute([':uid' => $user_id]);
    $yield_reports = $stmt3->fetchAll(PDO::FETCH_ASSOC);

    // 4. Resource History
    $stmt4 = $conn->prepare("SELECT id, crop_name, created_at FROM resource_optimizations WHERE user_id = :uid ORDER BY created_at DESC LIMIT 10");
    $stmt4->execute([':uid' => $user_id]);
    $resource_reports = $stmt4->fetchAll(PDO::FETCH_ASSOC);

} catch(PDOException $e) {
    die("Database Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports & Analytics - FarmWise</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <script>
        if (localStorage.getItem('farmwise_theme') === 'dark') {
            document.documentElement.classList.add('dark-mode');
        }
    </script>

    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Plus Jakarta Sans', -apple-system, sans-serif; }
        body { background-color: #f1f5f9; padding: 40px; color: #1e293b; transition: background-color 0.3s, color 0.3s; }
        .container { max-width: 1200px; margin: 0 auto; }
        
        /* Dashboard Redirect Button */
        .nav-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .back-dash { 
            text-decoration: none; color: #475569; font-weight: 700; font-size: 14px; 
            display: flex; align-items: center; gap: 8px; transition: 0.3s;
            background: white; padding: 10px 18px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.05);
        }
        .back-dash:hover { color: #2563eb; transform: translateX(-5px); }

        .header { background: #1e293b; color: white; padding: 40px; border-radius: 24px; margin-bottom: 30px; position: relative; overflow: hidden; box-shadow: 0 10px 25px rgba(30, 41, 59, 0.1); }
        .header h1 { font-size: 32px; margin-bottom: 8px; font-weight: 800; }
        .header p { opacity: 0.8; font-size: 16px; }
        
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 25px; }
        .card { background: white; border-radius: 22px; padding: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.02); transition: 0.3s; border: 1px solid #e2e8f0; }
        .card:hover { transform: translateY(-5px); box-shadow: 0 12px 25px rgba(0,0,0,0.05); }
        
        h3 { font-size: 18px; font-weight: 800; display: flex; align-items: center; gap: 12px; margin-bottom: 20px; }
        
        .table { width: 100%; border-collapse: collapse; }
        .table td { padding: 14px 8px; border-bottom: 1px solid #f1f5f9; font-size: 13px; vertical-align: middle; }
        .table tr:last-child td { border-bottom: none; }
        
        .date-col { color: #94a3b8; font-weight: 600; font-size: 11px; text-transform: uppercase; }
        .btn-view { color: #2563eb; text-decoration: none; font-weight: 800; font-size: 12px; padding: 6px 12px; background: #eff6ff; border-radius: 8px; transition: 0.2s; }
        .btn-view:hover { background: #2563eb; color: white; }

        /* =========================================
           GLOBAL DARK MODE STYLES
           ========================================= */
        html.dark-mode body { background-color: #0f172a; color: #f8fafc; }
        html.dark-mode .back-dash { background: #1e293b; color: #94a3b8; border: 1px solid #334155; }
        html.dark-mode .back-dash:hover { color: #3b82f6; }

        html.dark-mode .card { 
            background-color: #1e293b !important; 
            border-color: #334155 !important; 
            box-shadow: none !important; 
        }

        html.dark-mode .table td { border-bottom-color: #334155; color: #f8fafc; }
        html.dark-mode .date-col { color: #64748b; }
        html.dark-mode .btn-view { background: #1e1b4b; color: #818cf8; }
        html.dark-mode .btn-view:hover { background: #4f46e5; color: white; }

        @media (max-width: 768px) {
            body { padding: 20px; }
            .header { padding: 30px 20px; }
            .grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<div class="container">
    <div class="nav-header">
        <a href="farmer-dashboard.php" class="back-dash">
            <i class="fa-solid fa-arrow-left"></i> Back to Dashboard
        </a>
    </div>

    <div class="header">
        <h1>Historical Reports</h1>
        <p>Review all your AI-powered farming consultations and data analytics</p>
    </div>

    <div class="grid">
        <div class="card">
            <h3 style="color:#3b82f6;"><i class="fa-solid fa-shop"></i> Market Insights</h3>
            <table class="table">
                <?php if(empty($market_reports)): ?>
                    <tr><td style="color:#94a3b8; text-align:center;">No reports yet</td></tr>
                <?php endif; ?>
                <?php foreach($market_reports as $r): ?>
                <tr>
                    <td>
                        <div style="font-weight:700;"><?php echo htmlspecialchars($r['region']); ?></div>
                        <div class="date-col"><?php echo date('M d, Y', strtotime($r['created_at'])); ?></div>
                    </td>
                    <td style="text-align:right;"><a href="view-report.php?type=market&id=<?php echo $r['id']; ?>" class="btn-view">View</a></td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>

        <div class="card">
            <h3 style="color:#ef4444;"><i class="fa-solid fa-bug"></i> Pest Diagnosis</h3>
            <table class="table">
                <?php if(empty($pest_reports)): ?>
                    <tr><td style="color:#94a3b8; text-align:center;">No reports yet</td></tr>
                <?php endif; ?>
                <?php foreach($pest_reports as $r): ?>
                <tr>
                    <td>
                        <div style="font-weight:700;"><?php echo htmlspecialchars($r['disease_name']); ?></div>
                        <div class="date-col"><?php echo date('M d, Y', strtotime($r['created_at'])); ?></div>
                    </td>
                    <td style="text-align:right;"><a href="view-report.php?type=pest&id=<?php echo $r['id']; ?>" class="btn-view">Details</a></td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>

        <div class="card">
            <h3 style="color:#8b5cf6;"><i class="fa-solid fa-chart-pie"></i> Yield Predictions</h3>
            <table class="table">
                <?php if(empty($yield_reports)): ?>
                    <tr><td style="color:#94a3b8; text-align:center;">No reports yet</td></tr>
                <?php endif; ?>
                <?php foreach($yield_reports as $r): ?>
                <tr>
                    <td>
                        <div style="font-weight:700;"><?php echo htmlspecialchars($r['crop_type']); ?></div>
                        <div class="date-col"><?php echo date('M d, Y', strtotime($r['created_at'])); ?></div>
                    </td>
                    <td style="text-align:right;"><a href="view-report.php?type=yield&id=<?php echo $r['id']; ?>" class="btn-view">Review</a></td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>

        <div class="card">
            <h3 style="color:#0d9488;"><i class="fa-solid fa-bolt"></i> Resource Planning</h3>
            <table class="table">
                <?php if(empty($resource_reports)): ?>
                    <tr><td style="color:#94a3b8; text-align:center;">No reports yet</td></tr>
                <?php endif; ?>
                <?php foreach($resource_reports as $r): ?>
                <tr>
                    <td>
                        <div style="font-weight:700;"><?php echo htmlspecialchars($r['crop_name']); ?></div>
                        <div class="date-col"><?php echo date('M d, Y', strtotime($r['created_at'])); ?></div>
                    </td>
                    <td style="text-align:right;"><a href="view-report.php?type=resource&id=<?php echo $r['id']; ?>" class="btn-view">View</a></td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>
    </div>

    <div style="text-align: center; margin-top: 50px; color: #94a3b8; font-size: 12px;">
        <p>Reports are generated in real-time by FarmWise AI Engine</p>
    </div>
</div>

</body>
</html>