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
    $stmt = $conn->prepare("DELETE FROM programmes WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();

    log_action("Deleted programme ID $id", $conn);
}
header("Location: programmes.php");
exit;
