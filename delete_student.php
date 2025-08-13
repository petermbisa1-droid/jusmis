<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'superuser') {
    header('Location: login.php');
    exit;
}

include 'config/db.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id <= 0) {
    header("Location: view_student.php?error=Invalid+student+ID");
    exit;
}

// Check if the student exists and is active
$check = $conn->prepare("SELECT id FROM students WHERE id = ? AND status = 'active'");
$check->bind_param("i", $id);
$check->execute();
$check->store_result();

if ($check->num_rows === 0) {
    $check->close();
    header("Location: view_student.php?error=Student+not+found+or+already+inactive");
    exit;
}
$check->close();

// Soft delete the student
$del = $conn->prepare("UPDATE students SET status = 'inactive' WHERE id = ?");
$del->bind_param("i", $id);
$del->execute();
$del->close();

header("Location: view_student.php?msg=Student+deleted+successfully");
exit;
