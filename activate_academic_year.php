<?php
session_start();
include 'config/db.php';

$id = intval($_GET['id'] ?? 0);
$user = $_SESSION['user']['full_name'] ?? 'System';

// Deactivate all
$conn->query("UPDATE academic_years SET is_active = 0");

// Activate selected
$stmt = $conn->prepare("UPDATE academic_years SET is_active = 1, updated_by = ? WHERE id = ?");
$stmt->bind_param("si", $user, $id);
$stmt->execute();

header("Location: academic_years.php");
exit;
