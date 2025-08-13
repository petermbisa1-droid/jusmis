<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
include 'config/db.php';

if (!isset($_SESSION['force_change_user_id'])) {
    header('Location: login.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (!$new_password || !$confirm_password) {
        $error = "Please fill in all fields.";
    } elseif ($new_password !== $confirm_password) {
        $error = "Passwords do not match.";
    } elseif (strlen($new_password) < 8) {
        $error = "Password must be at least 8 characters.";
    } else {
        $user_id = $_SESSION['force_change_user_id'];
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

        $stmt = $conn->prepare("UPDATE users SET password = ?, force_password_change = 0 WHERE id = ?");
        $stmt->bind_param("si", $hashed_password, $user_id);

        if ($stmt->execute()) {
            // Remove the force change session, login user, redirect
            unset($_SESSION['force_change_user_id']);

            // Fetch user info to store in session
            $res = $conn->prepare("SELECT * FROM users WHERE id = ?");
            $res->bind_param("i", $user_id);
            $res->execute();
            $result = $res->get_result();
            $user = $result->fetch_assoc();

            $_SESSION['user'] = $user;

            // Redirect to dashboard based on role
            if ($user['role'] === 'student') {
                header("Location: student/student_dashboard.php");
            } else {
                header("Location: dashboard.php");
            }
            exit;
        } else {
            $error = "Failed to update password.";
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Change Password - Jubilee University</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #427dc9; color: white; }
    </style>
</head>
<body class="d-flex justify-content-center align-items-center vh-100">
    <div class="col-md-4">
        <h3 class="text-center">Change Password</h3>
        <form method="POST" class="bg-dark p-4 rounded">
            <div class="mb-3">
                <label>New Password</label>
                <input type="password" name="new_password" class="form-control" required minlength="8" />
            </div>
            <div class="mb-3">
                <label>Confirm New Password</label>
                <input type="password" name="confirm_password" class="form-control" required minlength="8" />
            </div>
            <?php if ($error): ?>
                <p class="text-danger"><?= htmlspecialchars($error) ?></p>
            <?php elseif ($success): ?>
                <p class="text-success"><?= htmlspecialchars($success) ?></p>
            <?php endif; ?>
            <button class="btn btn-light w-100">Change Password</button>
        </form>
    </div>
</body>
</html>
