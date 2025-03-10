<?php
include 'config.php';
if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['notification_id']) && isset($_POST['csrf_token'])) {
    if ($_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("CSRF token validation failed");
    }
    
    $notification_id = intval($_POST['notification_id']);
    $update_query = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ?");
    $update_query->bind_param("i", $notification_id);
    $update_query->execute();
    
    echo "success";
}
?>