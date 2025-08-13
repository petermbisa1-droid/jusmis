<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'superuser') {
    header('Location: login.php');
    exit;
}
include 'config/db.php';
include 'includes/logger.php';

$id = intval($_GET['id'] ?? 0);
$off = isset($_GET['off']) ? 0 : 1;

if ($id) {
    $updated_by = $_SESSION['user']['username'];
    $stmt = $conn->prepare("UPDATE programmes SET is_active = ?, updated_by = ? WHERE id = ?");
    $stmt->bind_param("isi", $off, $updated_by, $id);
    $stmt->execute();
    $stmt->close();

    $status = $off ? 'Activated' : 'Deactivated';
    log_action("$status programme ID $id", $conn);
}
header("Location: programmes.php");
exit;
