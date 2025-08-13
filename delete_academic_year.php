<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'superuser') {
    header("Location: login.php");
    exit;
}

include 'config/db.php';
include 'includes/logger.php';

$id = intval($_GET['id'] ?? 0);
if ($id > 0) {
    $stmt = $conn->prepare("DELETE FROM academic_years WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();

    log_action("Deleted academic year ID $id", $conn);
}

header("Location: academic_years.php");
exit;
