<?php
session_start();
require 'db.php';
header('Content-Type: application/json');

// 1. Security Check
$user_id = $_SESSION['user_id'] ?? 0;
$logged_in = $_SESSION['logged_in'] ?? false;

if ($user_id == 0 || !$logged_in) {
    echo json_encode([
        "count" => 0, 
        "latest_title" => "Session Expired",
        "latest_alert" => "Please log in to see live farm updates."
    ]);
    exit();
}

try {
    // 2. Fetch Notification Count and Latest Announcement in a single block
    // Count from announcements + user-specific pest diagnoses
    $stmt_count = $conn->prepare("
        SELECT 
            (SELECT COUNT(*) FROM announcements) + 
            (SELECT COUNT(*) FROM pest_diagnoses WHERE user_id = ?) as total_unread
    ");
    $stmt_count->execute([$user_id]);
    $count_res = $stmt_count->fetch(PDO::FETCH_ASSOC);
    $total_count = (int)$count_res['total_unread'];

    // 3. Fetch the absolute newest alert message for the Dashboard banner
    $stmt_msg = $conn->prepare("SELECT title, message FROM announcements ORDER BY created_at DESC LIMIT 1");
    $stmt_msg->execute();
    $msg_res = $stmt_msg->fetch(PDO::FETCH_ASSOC);
    
    // Default fallback messages if table is empty
    $latest_title = $msg_res ? $msg_res['title'] : "Alert Announcement";
    $latest_message = $msg_res ? $msg_res['message'] : "No active alerts. Your farm is secure.";

    // 4. Return JSON payload
    echo json_encode([
        "count" => $total_count,
        "latest_title" => $latest_title,
        "latest_alert" => $latest_message,
        "status" => "success"
    ]);

} catch(PDOException $e) {
    // Error handling for the AJAX poller
    echo json_encode([
        "count" => 0, 
        "latest_title" => "System Status",
        "latest_alert" => "Connection to alert server interrupted.",
        "status" => "error"
    ]);
}
?>