<?php
session_start();
require 'db.php';

// Tell the browser we are sending JSON back
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'You must be logged in to post.']);
    exit();
}

$user_id = $_SESSION['user_id'];
$category = $_POST['category'] ?? 'Question';
$title = trim($_POST['title'] ?? '');
$content = trim($_POST['content'] ?? '');

// Basic validation
if (empty($title) || empty($content)) {
    echo json_encode(['error' => 'Headline and Description are required.']);
    exit();
}

$image_path = null;

// Handle Image Upload (If the user selected an image)
if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
    $upload_dir = 'uploads/';
    
    // Create the uploads folder if it doesn't exist yet
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    // Generate a unique file name to prevent overwriting
    $file_extension = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
    $new_filename = uniqid('post_') . '_' . time() . '.' . $file_extension;
    $target_file = $upload_dir . $new_filename;

    // Move the file from temporary storage to the uploads folder
    if (move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
        $image_path = $target_file;
    } else {
        echo json_encode(['error' => 'Failed to save the uploaded image.']);
        exit();
    }
}

// Insert into Database
try {
    // Note: We explicitly set 'likes' to 0 for new posts
    $stmt = $conn->prepare("INSERT INTO community_posts (user_id, category, title, content, image_path, likes) VALUES (?, ?, ?, ?, ?, 0)");
    $stmt->execute([$user_id, $category, $title, $content, $image_path]);
    
    echo json_encode(['success' => 'Post published successfully!']);
} catch(PDOException $e) {
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>