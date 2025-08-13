<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'superuser') {
    header('Location: login.php');
    exit;
}
include 'config/db.php';
include 'includes/logger.php';

$id = intval($_GET['id'] ?? 0);
$off = intval($_GET['off'] ?? 0);

if ($id) {
    $newStatus = $off ? 0 : 1;
    $stmt = $conn->prepare("UPDATE users SET is_active = ?, updated_by = ?, updated_at = NOW() WHERE id = ?");
    $stmt->bind_param('isi', $newStatus, $_SESSION['user']['full_name'], $id);
    if ($stmt->execute()) {
        log_action(($newStatus ? "Reactivated" : "Deactivated") . " user id $id", $conn);
    }
    $stmt->close();
}

header('Location: users.php');
exit;
