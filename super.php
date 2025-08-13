<?php
include 'config/db.php';

// New superuser details
$full_name = "Super User";
$username = "superuser";  // keep same username to replace old account
$password_plain = "supersecret123";  // set your new password here
$role = "superuser";
$created_by = "system";
$created_at = date('Y-m-d H:i:s');

// Hash the password securely
$hashed_password = password_hash($password_plain, PASSWORD_DEFAULT);

// First, delete existing superuser with this username (if any)
$delStmt = $conn->prepare("DELETE FROM users WHERE username = ?");
$delStmt->bind_param("s", $username);
$delStmt->execute();

// Insert new superuser record
$insertStmt = $conn->prepare("INSERT INTO users (full_name, username, password, role, created_by, created_at, is_active) VALUES (?, ?, ?, ?, ?, ?, 1)");
$insertStmt->bind_param("ssssss", $full_name, $username, $hashed_password, $role, $created_by, $created_at);

if ($insertStmt->execute()) {
    echo "Superuser account created successfully.<br>";
    echo "Username: $username<br>";
    echo "Password: $password_plain<br>";
} else {
    echo "Error creating superuser: " . $conn->error;
}
?>
