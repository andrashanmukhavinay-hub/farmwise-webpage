<?php
// tracker.php
function logActivity($conn, $user_id, $page_name, $action = "Visited Page") {
    try {
        $ip = $_SERVER['REMOTE_ADDR'];
        $stmt = $conn->prepare("INSERT INTO activity_logs (user_id, page_name, action_performed, ip_address) VALUES (?, ?, ?, ?)");
        $stmt->execute([$user_id, $page_name, $action, $ip]);
    } catch (PDOException $e) {
        // Fail silently to not disturb the user experience
    }
}
?>