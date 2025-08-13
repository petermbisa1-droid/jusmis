<?php
include 'config/db.php';

$username = 'testuser';
$new_password = 'TestPass123!';

$new_hash = password_hash($new_password, PASSWORD_DEFAULT);

$stmt = $conn->prepare("UPDATE users SET password=? WHERE username=?");
$stmt->bind_param("ss", $new_hash, $username);

if ($stmt->execute()) {
    echo "Password for user '$username' updated successfully.\n";
    echo "New hash: $new_hash\n";
} else {
    echo "Failed to update password: " . $conn->error . "\n";
}
?>
