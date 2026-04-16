<?php
session_start();
require 'db.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'admin') { exit('Unauthorized'); }

// Handle ADD
if (isset($_POST['add_scheme'])) {
    $name = $_POST['name'];
    $type = $_POST['type'];
    $benefit = $_POST['benefit'];
    $status = $_POST['status'];
    $desc = $_POST['description'];

    try {
        $stmt = $conn->prepare("INSERT INTO govt_schemes (name, type, description, benefit_amount, status) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$name, $type, $desc, $benefit, $status]);
        header("Location: admin-dashboard.php?added");
    } catch(PDOException $e) { die($e->getMessage()); }
}

// Handle DELETE
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    try {
        $stmt = $conn->prepare("DELETE FROM govt_schemes WHERE id = ?");
        $stmt->execute([$id]);
        header("Location: admin-dashboard.php?deleted");
    } catch(PDOException $e) { die($e->getMessage()); }
}
?>