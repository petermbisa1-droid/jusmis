<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'superuser') {
    header('Location: login.php');
    exit;
}

include 'config/db.php';
include 'includes/logger.php';

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: courses.php');
    exit;
}

$stmt = $conn->prepare("SELECT course_code FROM courses WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$stmt->bind_result($course_code);
if (!$stmt->fetch()) {
    $stmt->close();
    header('Location: courses.php');
    exit;
}
$stmt->close();

$del = $conn->prepare("DELETE FROM courses WHERE id = ?");
$del->bind_param("i", $id);

if ($del->execute()) {
    log_action("Deleted course $course_code", $conn);
    header('Location: courses.php?deleted=1');
    exit;
} else {
    echo "Failed to delete course.";
}
