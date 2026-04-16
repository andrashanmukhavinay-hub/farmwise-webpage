<?php
session_start();
require 'db.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'admin') {
    header("Location: admin-login.php");
    exit();
}

try {
    // 1. Platform Stats
    $total_users = $conn->query("SELECT COUNT(*) FROM users")->fetchColumn() ?: 0;
    $total_pest = $conn->query("SELECT COUNT(*) FROM pest_diagnoses")->fetchColumn() ?: 0;
    $total_schemes = $conn->query("SELECT COUNT(*) FROM govt_schemes")->fetchColumn() ?: 0;
    $total_alerts = $conn->query("SELECT COUNT(*) FROM announcements")->fetchColumn() ?: 0;

    // 2. Recent Pest Reports
    $pest_stmt = $conn->query("SELECT crop_name, disease_name, created_at FROM pest_diagnoses ORDER BY created_at DESC LIMIT 5");
    $recent_pests = $pest_stmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. Existing Schemes
    $schemes = $conn->query("SELECT * FROM govt_schemes ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);

    // 4. NEW: Fetch Farmers and their last activity
    $user_stmt = $conn->query("
        SELECT u.id, u.name, u.email, 
        (SELECT timestamp FROM activity_logs WHERE user_id = u.id ORDER BY timestamp DESC LIMIT 1) as last_seen
        FROM users u ORDER BY last_seen DESC
    ");
    $farmers = $user_stmt->fetchAll(PDO::FETCH_ASSOC);

} catch(PDOException $e) { $db_error = $e->getMessage(); }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard | FarmWise</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', sans-serif; }
        body { background-color: #f1f5f9; display: flex; min-height: 100vh; color: #1e293b; }
        
        .sidebar { width: 260px; background: #1e293b; color: white; padding: 30px 20px; position: fixed; height: 100%; }
        .logo { font-size: 22px; font-weight: 800; color: #10b981; margin-bottom: 40px; display: flex; align-items: center; gap: 10px; }
        .nav-item { color: #94a3b8; text-decoration: none; padding: 12px 15px; border-radius: 8px; display: flex; align-items: center; gap: 12px; margin-bottom: 5px; transition: 0.3s; }
        .nav-item.active { background: #10b981; color: white; }

        .main { margin-left: 260px; flex: 1; padding: 40px; }
        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 30px; }
        .stat-box { background: white; padding: 25px; border-radius: 15px; border-bottom: 4px solid #10b981; box-shadow: 0 4px 6px rgba(0,0,0,0.02); }
        .stat-box h3 { font-size: 28px; margin-bottom: 5px; }
        .stat-box p { color: #64748b; font-size: 12px; font-weight: 700; text-transform: uppercase; }

        .panel { background: white; padding: 25px; border-radius: 15px; box-shadow: 0 4px 6px rgba(0,0,0,0.02); margin-bottom: 30px; border: 1px solid #e2e8f0; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th { text-align: left; color: #94a3b8; font-size: 11px; text-transform: uppercase; padding-bottom: 10px; border-bottom: 2px solid #f1f5f9; }
        td { padding: 12px 0; border-bottom: 1px solid #f8fafc; font-size: 14px; }
        
        .badge { padding: 4px 10px; border-radius: 6px; font-size: 11px; font-weight: 800; }
        .badge-success { background: #dcfce7; color: #166534; }
        .badge-active { background: #10b981; color: white; }
        
        .alert-ui { background: #fef2f2; border: 1px solid #fecaca; border-radius: 12px; padding: 20px; }
        input, textarea, select { width: 100%; padding: 12px; border: 1px solid #e2e8f0; border-radius: 8px; margin-bottom: 10px; outline: none; background: white; }
        .btn-send { width: 100%; background: #dc2626; color: white; border: none; padding: 14px; border-radius: 8px; font-weight: 700; cursor: pointer; }
        .btn-add { background: #10b981; color: white; border: none; padding: 10px 20px; border-radius: 8px; font-weight: 700; cursor: pointer; }
        .view-logs-btn { color: #3b82f6; text-decoration: none; font-weight: 700; font-size: 12px; }
    </style>
</head>
<body>

    <div class="sidebar">
        <div class="logo"><i class="fa-solid fa-seedling"></i> FarmWise</div>
        <a href="admin-dashboard.php" class="nav-item active"><i class="fa-solid fa-gauge"></i> Dashboard</a>
        <a href="logout.php" class="nav-item" style="margin-top:auto;"><i class="fa-solid fa-power-off"></i> Logout</a>
    </div>

    <div class="main">
        <h1 style="margin-bottom: 25px;">Admin Control Center</h1>

        <div class="stats-grid">
            <div class="stat-box"><h3><?php echo $total_users; ?></h3><p>Total Farmers</p></div>
            <div class="stat-box"><h3><?php echo $total_pest; ?></h3><p>Pest Outbreaks</p></div>
            <div class="stat-box"><h3><?php echo $total_schemes; ?></h3><p>Active Schemes</p></div>
            <div class="stat-box"><h3><?php echo $total_alerts; ?></h3><p>Broadcasts</p></div>
        </div>

        <div class="panel">
            <h2>Active Farmers & History</h2>
            <table>
                <thead>
                    <tr>
                        <th>Farmer Name</th>
                        <th>Email Address</th>
                        <th>Last Activity</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($farmers as $f): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($f['name']); ?></strong></td>
                        <td><?php echo htmlspecialchars($f['email']); ?></td>
                        <td><?php echo $f['last_seen'] ? date('M d, H:i A', strtotime($f['last_seen'])) : '<span style="color:#94a3b8">Never</span>'; ?></td>
                        <td>
                            <a href="user-activity.php?id=<?php echo $f['id']; ?>" class="view-logs-btn">
                                <i class="fa-solid fa-clock-rotate-left"></i> View Full Search History
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div style="display: grid; grid-template-columns: 1.5fr 1fr; gap: 30px;">
            <div class="panel">
                <h2>Recent Diagnoses</h2>
                <table>
                    <thead><tr><th>Crop</th><th>Disease</th><th>Logged</th></tr></thead>
                    <tbody>
                        <?php foreach($recent_pests as $p): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($p['crop_name']); ?></strong></td>
                            <td style="color:#dc2626; font-weight:600;"><?php echo htmlspecialchars($p['disease_name']); ?></td>
                            <td style="color:#94a3b8;"><?php echo date('M d, H:i', strtotime($p['created_at'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="panel">
                <h2>Emergency Broadcast</h2>
                <div class="alert-ui">
                    <form action="send-broadcast.php" method="POST">
                        <input type="text" name="title" placeholder="Warning Title" required>
                        <textarea name="msg" rows="4" placeholder="Message to farmers..." required></textarea>
                        <button type="submit" class="btn-send">Send Broadcast</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="panel">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h2>Manage Government Schemes</h2>
                <button class="btn-add" onclick="document.getElementById('schemeForm').style.display='block'">+ Add New Scheme</button>
            </div>

            <div id="schemeForm" style="display: none; background: #f8fafc; padding: 25px; border-radius: 12px; margin-bottom: 25px; border: 1px solid #e2e8f0;">
                <form action="manage-schemes-logic.php" method="POST">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        <input type="text" name="name" placeholder="Scheme Name" required>
                        <select name="type" required>
                            <option value="Subsidy">Subsidy</option>
                            <option value="Loan">Loan</option>
                            <option value="Insurance">Insurance</option>
                        </select>
                        <input type="text" name="benefit" placeholder="Benefit Amount (e.g. ₹5,000)" required>
                        <input type="text" name="status" placeholder="Status (e.g. Open)" required>
                        <textarea name="description" rows="2" placeholder="Description..." style="grid-column: span 2;" required></textarea>
                    </div>
                    <button type="submit" name="add_scheme" class="btn-add">Publish Scheme</button>
                    <button type="button" onclick="document.getElementById('schemeForm').style.display='none'" style="background:none; border:none; color:#64748b; margin-left:15px; cursor:pointer;">Cancel</button>
                </form>
            </div>

            <table>
                <thead><tr><th>Scheme Name</th><th>Type</th><th>Benefit</th><th>Status</th><th>Action</th></tr></thead>
                <tbody>
                    <?php foreach($schemes as $s): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($s['name']); ?></strong></td>
                        <td><?php echo $s['type']; ?></td>
                        <td style="color:#10b981; font-weight:700;"><?php echo $s['benefit_amount']; ?></td>
                        <td><span class="badge badge-success"><?php echo $s['status']; ?></span></td>
                        <td><a href="manage-schemes-logic.php?delete=<?php echo $s['id']; ?>" onclick="return confirm('Delete?')" style="color:#ef4444;"><i class="fa-solid fa-trash"></i></a></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>