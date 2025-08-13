<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

ob_start();
session_start();
include 'config/db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $validPassword = false; // Initialize to avoid undefined variable warning

    $username = trim($_POST['username']);
    $input_password = $_POST['password'];

    // 1. Fetch user from users table
    $stmt = $conn->prepare("SELECT * FROM users WHERE username=? LIMIT 1");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($user = $result->fetch_assoc()) {
        $db_password = $user['password'];

        // 2. Verify password
        if (strpos($db_password, '$2y$') === 0) {
            $validPassword = password_verify($input_password, $db_password);
        } else {
            if (md5($input_password) === $db_password) {
                // Upgrade to bcrypt
                $newHash = password_hash($input_password, PASSWORD_DEFAULT);
                $updateStmt = $conn->prepare("UPDATE users SET password=? WHERE id=?");
                $updateStmt->bind_param("si", $newHash, $user['id']);
                $updateStmt->execute();
                $validPassword = true;
            }
        }

        if ($validPassword) {
            // Force password change check
            if ($user['force_password_change'] == 1) {
                $_SESSION['force_change_user_id'] = $user['id'];
                header("Location: change_password.php");
                exit;
            }

            $role = strtolower($user['role']);
            $sessionUser = [
                'id' => $user['id'],
                'username' => $user['username'],
                'role' => $role,
                'name' => $user['username'],  // fallback
                'student_id' => null,
                'staff_id' => null
            ];

            if ($role === 'lecturer') {
                // Lecturer fetch by email = username
                $stmt2 = $conn->prepare("SELECT id, full_name FROM staff WHERE email = ? LIMIT 1");
                $stmt2->bind_param("s", $user['username']);
                $stmt2->execute();
                $stmt2->bind_result($staff_id, $full_name);
                if ($stmt2->fetch()) {
                    $sessionUser['staff_id'] = $staff_id;
                    $sessionUser['name'] = $full_name;
                }
                $stmt2->close();

            } elseif ($role === 'student') {
                // 1. Try to get student linked by user_id
                $stmt2 = $conn->prepare("SELECT id, full_name FROM students WHERE user_id = ? LIMIT 1");
                $stmt2->bind_param("i", $user['id']);
                $stmt2->execute();
                $result2 = $stmt2->get_result();

                if ($student = $result2->fetch_assoc()) {
                    $sessionUser['student_id'] = $student['id'];
                    $sessionUser['name'] = $student['full_name'];
                } else {
                    // 2. If no linked student, try to auto-link by registration_number = username
                    $stmt2->close();

                    $stmt3 = $conn->prepare("SELECT id, full_name FROM students WHERE registration_number = ? LIMIT 1");
                    $stmt3->bind_param("s", $user['username']);
                    $stmt3->execute();
                    $result3 = $stmt3->get_result();

                    if ($student = $result3->fetch_assoc()) {
                        // Update user_id to link
                        $updateStmt = $conn->prepare("UPDATE students SET user_id = ? WHERE id = ?");
                        $updateStmt->bind_param("ii", $user['id'], $student['id']);
                        $updateStmt->execute();
                        $updateStmt->close();

                        $sessionUser['student_id'] = $student['id'];
                        $sessionUser['name'] = $student['full_name'];
                    } else {
                        $error = "Student record not found. Please contact admin.";
                    }
                    $stmt3->close();
                }
                $stmt2->close();

            } else {
                // Superuser or other roles
                $sessionUser['name'] = $user['full_name'];
            }

            if (empty($error)) {
                $_SESSION['user'] = $sessionUser;

                // Redirect based on role
                if ($role === 'student') {
                    header("Location: student/student_dashboard.php");
                } elseif ($role === 'lecturer') {
                    header("Location: lecturer/dashboard.php");
                } else {
                    header("Location: dashboard.php");
                }
                exit;
            }
        } else {
            $error = "Invalid credentials!";
        }
    } else {
        $error = "Invalid credentials!";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Login - Jubilee University</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #427dc9; color: white; }
    </style>
</head>
<body class="d-flex justify-content-center align-items-center vh-100">
    <div class="col-md-4">
        <h3 class="text-center">Jubilee University</h3>
        <form method="POST" class="bg-dark p-4 rounded">
            <div class="mb-3">
                <label>Username</label>
                <input type="text" name="username" class="form-control" required />
            </div>
            <div class="mb-3">
                <label>Password</label>
                <input type="password" name="password" class="form-control" required />
            </div>
            <?php if (!empty($error)) echo "<p class='text-danger'>$error</p>"; ?>
            <button class="btn btn-light w-100">Login</button>
        </form>
    </div>
</body>
</html>
