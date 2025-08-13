<?php
session_start();
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

include 'includes/header.php';
include 'config/db.php';

$success = "";
$error = "";

// Get current user for auditing
$current_user = $_SESSION['user']['username'] ?? 'system';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name  = trim($_POST['full_name']);
    $email      = trim($_POST['email']);
    $phone      = trim($_POST['phone']);
    $position   = trim($_POST['position']);
    $department = trim($_POST['department']);
    $role       = $_POST['role'];
    $created_at = date('Y-m-d H:i:s');

    // Check for duplicate email or phone in staff table
    $checkStmt = $conn->prepare("SELECT id FROM staff WHERE email = ? OR phone = ?");
    $checkStmt->bind_param("ss", $email, $phone);
    $checkStmt->execute();
    $checkStmt->store_result();

    if ($checkStmt->num_rows > 0) {
        $error = "A staff member with this email or phone already exists.";
    } else {
        // Insert into staff table
        $insertStmt = $conn->prepare("INSERT INTO staff (full_name, email, phone, position, department, role, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $insertStmt->bind_param("ssssssss", $full_name, $email, $phone, $position, $department, $role, $current_user, $created_at);

        if ($insertStmt->execute()) {
            // Generate hashed default password
            $defaultPassword = password_hash("changeme123", PASSWORD_DEFAULT);

            // Insert into users table
            $userStmt = $conn->prepare("INSERT INTO users (full_name, username, password, role, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?)");
            $userStmt->bind_param("ssssss", $full_name, $email, $defaultPassword, $role, $current_user, $created_at);
            $userStmt->execute();

            $success = "Staff created successfully. Default password: <strong>changeme123</strong>";
        } else {
            $error = "Failed to create staff record.";
        }
    }
}
?>

<div class="container mt-4">
    <h4 class="mb-3">Add New Staff</h4>

    <?php if ($success): ?>
        <div class="alert alert-success"><?= $success ?></div>
    <?php elseif ($error): ?>
        <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>

    <form method="POST" action="create_staff.php">
        <div class="row">
            <div class="col-md-6 mb-3">
                <label for="full_name">Full Name</label>
                <input type="text" name="full_name" id="full_name" class="form-control" required>
            </div>

            <div class="col-md-6 mb-3">
                <label for="email">Email (Username)</label>
                <input type="email" name="email" id="email" class="form-control" required>
            </div>

            <div class="col-md-6 mb-3">
                <label for="phone">Phone</label>
                <input type="text" name="phone" id="phone" class="form-control" required>
            </div>

            <div class="col-md-6 mb-3">
                <label for="position">Position</label>
                <input type="text" name="position" id="position" class="form-control" required>
            </div>

            <div class="col-md-6 mb-3">
                <label for="department">Department</label>
                <input type="text" name="department" id="department" class="form-control" required>
            </div>

            <div class="col-md-6 mb-3">
                <label for="role">Role</label>
                <select name="role" id="role" class="form-control" required>
                    <option value="">-- Select Role --</option>
                    <option value="superuser">Superuser</option>
                    <option value="registrar">Registrar</option>
                    <option value="vicechancellor">Vice Chancellor</option>
                    <option value="dean">Dean</option>
                    <option value="lecturer">Lecturer</option>
                    <option value="admissions">Admissions</option>
                    <option value="quality_assurance">Quality Assurance</option>
                    <option value="human_resource">Human Resource</option>
                    <option value="finance">Finance</option>
                    <option value="librarian">Librarian</option>
                </select>
            </div>
        </div>

        <button type="submit" class="btn btn-success">
            <i class="fas fa-user-plus"></i> Create Staff
        </button>
    </form>
</div>

<?php include 'includes/footer.php'; ?>
