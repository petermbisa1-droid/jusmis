<?php
session_start();
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

include 'config/db.php';

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: staff.php');
    exit;
}

// Find staff to delete
$stmt = $conn->prepare("SELECT email FROM staff WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if (!$staff = $result->fetch_assoc()) {
    header('Location: staff.php');
    exit;
}

// Delete from staff table
$delStaffStmt = $conn->prepare("DELETE FROM staff WHERE id = ?");
$delStaffStmt->bind_param("i", $id);

if ($delStaffStmt->execute()) {
    // Delete corresponding user by username (= email)
    $delUserStmt = $conn->prepare("DELETE FROM users WHERE username = ?");
    $delUserStmt->bind_param("s", $staff['email']);
    $delUserStmt->execute();

    header('Location: staff.php?deleted=1');
    exit;
} else {
    echo "Error deleting staff member.";
}
?>
