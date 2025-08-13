<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'superuser') {
    header("Location: login.php");
    exit;
}

include 'config/db.php';
include 'includes/logger.php';

$session_id = intval($_GET['id'] ?? 0);
$updated_by = $_SESSION['user']['full_name'];

if ($session_id <= 0) {
    die("Invalid session ID.");
}

// 1. Deactivate all sessions
$conn->query("UPDATE sessions SET is_active = 0");

// 2. Activate selected session
$stmt = $conn->prepare("UPDATE sessions SET is_active = 1, updated_by = ? WHERE id = ?");
$stmt->bind_param("si", $updated_by, $session_id);
$stmt->execute();
$stmt->close();

// 3. Open Semester 1 and Semester 2 for this session
$semesters = ['Semester 1', 'Semester 2'];
foreach ($semesters as $sem_name) {
    // Check if semester already exists for this session
    $check = $conn->prepare("SELECT id FROM semesters WHERE session_id = ? AND semester_name = ?");
    $check->bind_param("is", $session_id, $sem_name);
    $check->execute();
    $check->store_result();

    if ($check->num_rows === 0) {
        // Insert semester if not present
        $insert = $conn->prepare("INSERT INTO semesters (session_id, semester_name, is_open, created_by) VALUES (?, ?, 1, ?)");
        $insert->bind_param("iss", $session_id, $sem_name, $updated_by);
        $insert->execute();
        $insert->close();
    } else {
        // Reopen semester if it exists
        $update = $conn->prepare("UPDATE semesters SET is_open = 1, updated_by = ? WHERE session_id = ? AND semester_name = ?");
        $update->bind_param("sis", $updated_by, $session_id, $sem_name);
        $update->execute();
        $update->close();
    }
    $check->close();
}

log_action("Activated session ID $session_id and opened Semester 1 & 2", $conn);
header("Location: sessions.php");
exit;
?>
