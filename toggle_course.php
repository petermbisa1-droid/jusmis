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

$stmt = $conn->prepare("SELECT is_active, course_code FROM courses WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$stmt->bind_result($is_active, $course_code);
if (!$stmt->fetch()) {
    $stmt->close();
    header('Location: courses.php');
    exit;
}
$stmt->close();

$new_status = $is_active ? 0 : 1;

$upd = $conn->prepare("UPDATE courses SET is_active = ?, updated_at = NOW(), updated_by = ? WHERE id = ?");
$upd->bind_param("isi", $new_status, $_SESSION['user']['full_name'], $id);

if ($upd->execute()) {
    log_action(($new_status ? "Activated" : "Deactivated") . " course $course_code", $conn);
}

header('Location: courses.php');
exit;
