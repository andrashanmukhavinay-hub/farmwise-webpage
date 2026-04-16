<?php
session_start();
require 'db.php';

if ($_SERVER["REQUEST_METHOD"] == "POST" && $_SESSION['role'] == 'admin') {
    $title = $_POST['title'];
    $msg = $_POST['msg'];

    try {
        $stmt = $conn->prepare("INSERT INTO announcements (title, message, severity) VALUES (?, ?, 'high')");
        $stmt->execute([$title, $msg]);
        
        header("Location: admin-dashboard.php?status=sent");
    } catch(PDOException $e) {
        echo "Error sending broadcast: " . $e->getMessage();
    }
}
?>