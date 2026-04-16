<?php
session_start();
require 'db.php';
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'admin') { exit(); }

$uid = $_GET['id'];
// Get User Details
$user_stmt = $conn->prepare("SELECT name FROM users WHERE id = ?");
$user_stmt->execute([$uid]);
$user = $user_stmt->fetch();

// Get Logs
$log_stmt = $conn->prepare("SELECT * FROM activity_logs WHERE user_id = ? ORDER BY timestamp DESC");
$log_stmt->execute([$uid]);
$logs = $log_stmt->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
    <title>User Activity Logs</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: sans-serif; background: #f8fafc; padding: 40px; }
        .log-container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 20px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); }
        .log-item { display: flex; align-items: center; gap: 15px; padding: 15px; border-left: 4px solid #10b981; background: #f0fdf4; margin-bottom: 10px; border-radius: 0 10px 10px 0; }
        .log-time { font-size: 12px; color: #64748b; font-weight: bold; min-width: 80px; }
        .log-page { font-weight: 700; color: #1e293b; }
    </style>
</head>
<body>
    <div class="log-container">
        <a href="admin-users.php" style="text-decoration:none; color:#64748b;"><i class="fa-solid fa-arrow-left"></i> Back</a>
        <h2 style="margin: 20px 0;">Activity for: <?php echo htmlspecialchars($user['name']); ?></h2>

        <?php if(empty($logs)): ?>
            <p>No activity recorded yet.</p>
        <?php else: ?>
            <?php foreach($logs as $l): ?>
            <div class="log-item">
                <div class="log-time"><?php echo date('H:i:s', strtotime($l['timestamp'])); ?></div>
                <div>
                    <span class="log-page"><?php echo $l['page_name']; ?></span>
                    <p style="font-size: 13px; color: #475569; margin-top: 4px;">Action: <?php echo $l['action_performed']; ?> (IP: <?php echo $l['ip_address']; ?>)</p>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</body>
</html>