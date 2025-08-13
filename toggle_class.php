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

// Get current status
$stmt = $conn->prepare("SELECT is_active FROM classes WHERE id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$stmt->bind_result($is_active);
if (!$stmt->fetch()) {
    $stmt->close();
    header('Location: classes.php');
    exit;
}
$stmt->close();

// Toggle status
$new_status = $is_active ? 0 : 1;

$stmt = $conn->prepare("UPDATE classes SET is_active = ?, updated_by = ?, updated_at = NOW() WHERE id = ?");
$user = $_SESSION['user']['full_name'];
$stmt->bind_param('isi', $new_status, $user, $id);

if ($stmt->execute()) {
    log_action("Toggled class ID $id to " . ($new_status ? 'Active' : 'Inactive'), $conn);
}

$stmt->close();

header('Location: classes.php');
exit;
