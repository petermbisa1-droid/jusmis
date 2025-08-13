<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'superuser') {
    header('Location: login.php');
    exit;
}
include 'config/db.php';
include 'includes/logger.php';

$id = intval($_GET['id'] ?? 0);

if ($id) {
    $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
    $stmt->bind_param('i', $id);
    if ($stmt->execute()) {
        log_action("Deleted user id $id", $conn);
    }
    $stmt->close();
}

header('Location: users.php');
exit;
