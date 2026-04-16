<?php
session_start();
require 'db.php';
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'admin') { header("Location: admin-login.php"); exit(); }

$users = $conn->query("SELECT id, name, email, created_at FROM users ORDER BY created_at DESC")->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Manage Farmers | Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: sans-serif; background: #f1f5f9; padding: 40px; }
        .card { background: white; padding: 25px; border-radius: 15px; box-shadow: 0 4px 10px rgba(0,0,0,0.05); }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { text-align: left; padding: 15px; border-bottom: 1px solid #eee; }
        .view-btn { background: #10b981; color: white; padding: 8px 15px; border-radius: 6px; text-decoration: none; font-size: 13px; font-weight: bold; }
    </style>
</head>
<body>
    <div class="card">
        <h2>Farmer Directory</h2>
        <table>
            <thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Joined</th><th>Action</th></tr></thead>
            <tbody>
                <?php foreach($users as $u): ?>
                <tr>
                    <td>#<?php echo $u['id']; ?></td>
                    <td><?php echo htmlspecialchars($u['name']); ?></td>
                    <td><?php echo htmlspecialchars($u['email']); ?></td>
                    <td><?php echo date('M d, Y', strtotime($u['created_at'])); ?></td>
                    <td><a href="user-activity.php?id=<?php echo $u['id']; ?>" class="view-btn">View Activity Logs</a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html>