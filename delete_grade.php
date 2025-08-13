<?php
session_start();
include 'config/db.php';

// Access control
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['role'], ['superuser', 'admin', 'lecturer'])) {
    header('Location: login.php');
    exit;
}

$grade_id = $_GET['id'] ?? '';

if (!$grade_id || !is_numeric($grade_id)) {
    die("Invalid Grade ID.");
}

// Fetch grade with grading period info
$stmt = $conn->prepare("
    SELECT g.id, gc.start_date, gc.end_date, gc.approved
    FROM grades g
    JOIN grading_calendar gc ON g.grading_period_id = gc.id
    WHERE g.id = ?
");
$stmt->bind_param('i', $grade_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Grade not found.");
}

$grade = $result->fetch_assoc();
$current_date = date('Y-m-d');

$isOpen = ($current_date >= $grade['start_date'] && $current_date <= $grade['end_date']);
$isApproved = $grade['approved'] == 1;

if (!$isOpen || $isApproved) {
    die("Deletion is not allowed. Grading Calendar is closed or grades are approved.");
}

// Proceed to delete
$delete = $conn->prepare("DELETE FROM grades WHERE id = ?");
$delete->bind_param('i', $grade_id);
$delete->execute();

header("Location: view_grades.php?success=Grade Deleted Successfully");
exit;
?>
