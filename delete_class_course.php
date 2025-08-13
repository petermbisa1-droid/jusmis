<?php
session_start();
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}
include 'config/db.php';
include 'includes/logger.php';

$id = intval($_GET['id'] ?? 0);

// Get assignment to determine redirect
$stmt = $conn->prepare("SELECT class_id FROM class_courses WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$assignment = $result->fetch_assoc();
$stmt->close();

if (!$assignment) {
    echo "<div class='alert alert-danger'>Assignment not found.</div>";
    exit;
}

$class_id = $assignment['class_id'];

$delStmt = $conn->prepare("DELETE FROM class_courses WHERE id = ?");
$delStmt->bind_param("i", $id);
if ($delStmt->execute()) {
    log_action("Deleted class-course ID $id", $conn);
}
header("Location: view_class_courses.php?class_id=$class_id");
exit;
