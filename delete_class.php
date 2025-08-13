<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'superuser') {
    header('Location: login.php');
    exit;
}
include 'config/db.php';
include 'includes/logger.php';

$id = $_GET['id'] ?? null;
if (!$id) {
    header('Location: classes.php');
    exit;
}

// Optionally, you can check if this class is linked elsewhere before deleting

$stmt = $conn->prepare("DELETE FROM classes WHERE id = ?");
$stmt->bind_param('i', $id);

if ($stmt->execute()) {
    log_action("Deleted class with ID: $id", $conn);
}

$stmt->close();

header('Location: classes.php');
exit;
