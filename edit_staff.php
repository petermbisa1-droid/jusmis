<?php
session_start();
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

include 'includes/header.php';
include 'config/db.php';

$error = '';
$success = '';

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: staff.php');
    exit;
}

// Fetch existing staff record
$stmt = $conn->prepare("SELECT * FROM staff WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if (!$staff = $result->fetch_assoc()) {
    header('Location: staff.php');
    exit;
}

$current_user = $_SESSION['user']['username'] ?? 'system';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name  = trim($_POST['full_name']);
    $email      = trim($_POST['email']);
    $phone      = trim($_POST['phone']);
    $position   = trim($_POST['position']);
    $department = trim($_POST['department']);
    $role       = $_POST['role'];
    $updated_at = date('Y-m-d H:i:s');

    // Check for duplicate email or phone in other records
    $checkStmt = $conn->prepare("SELECT id FROM staff WHERE (email = ? OR phone = ?) AND id != ?");
    $checkStmt->bind_param("ssi", $email, $phone, $id);
    $checkStmt->execute();
    $checkStmt->store_result();

    if ($checkStmt->num_rows > 0) {
        $error = "Another staff member with this email or phone already exists.";
    } else {
        // Update staff
        $updateStmt = $conn->prepare("UPDATE staff SET full_name=?, email=?, phone=?, position=?, department=?, role=?, updated_by=?, updated_at=? WHERE id=?");
        $updateStmt->bind_param("ssssssssi", $full_name, $email, $phone, $position, $department, $role, $current_user, $updated_at, $id);

        if ($updateStmt->execute()) {
            // Also update the users table for username, full_name, role
            $userUpdateStmt = $conn->prepare("UPDATE users SET full_name=?, username=?, role=?, updated_by=?, updated_at=? WHERE username = ?");
            $userUpdateStmt->bind_param("ssssss", $full_name, $email, $role, $current_user, $updated_at, $staff['email']);
            $userUpdateStmt->execute();

            $success = "Staff updated successfully.";
            // Refresh staff data to show updated info in the form
            $staff['full_name'] = $full_name;
            $staff['email'] = $email;
            $staff['phone'] = $phone;
            $staff['position'] = $position;
            $staff['department'] = $department;
            $staff['role'] = $role;
        } else {
            $error = "Failed to update staff.";
        }
    }
}
?>

<div class="container mt-4">
    <h4>Edit Staff</h4>

    <?php if ($success): ?>
        <div class="alert alert-success"><?= $success ?></div>
    <?php elseif ($error): ?>
        <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>

    <form method="POST" action="edit_staff.php?id=<?= $id ?>">
        <div class="row">
            <div class="col-md-6 mb-3">
                <label for="full_name">Full Name</label>
                <input type="text" name="full_name" id="full_name" class="form-control" required value="<?= htmlspecialchars($staff['full_name']) ?>">
            </div>

            <div class="col-md-6 mb-3">
                <label for="email">Email (Username)</label>
                <input type="email" name="email" id="email" class="form-control" required value="<?= htmlspecialchars($staff['email']) ?>">
            </div>

            <div class="col-md-6 mb-3">
                <label for="phone">Phone</label>
                <input type="text" name="phone" id="phone" class="form-control" required value="<?= htmlspecialchars($staff['phone']) ?>">
            </div>

            <div class="col-md-6 mb-3">
                <label for="position">Position</label>
                <input type="text" name="position" id="position" class="form-control" required value="<?= htmlspecialchars($staff['position']) ?>">
            </div>

            <div class="col-md-6 mb-3">
                <label for="department">Department</label>
                <input type="text" name="department" id="department" class="form-control" required value="<?= htmlspecialchars($staff['department']) ?>">
            </div>

            <div class="col-md-6 mb-3">
                <label for="role">Role</label>
                <select name="role" id="role" class="form-control" required>
                    <option value="">-- Select Role --</option>
                    <option value="superuser" <?= $staff['role'] === 'superuser' ? 'selected' : '' ?>>Superuser</option>
                    <option value="registrar" <?= $staff['role'] === 'registrar' ? 'selected' : '' ?>>Registrar</option>
                    <option value="vicechancellor" <?= $staff['role'] === 'vicechancellor' ? 'selected' : '' ?>>Vice Chancellor</option>
                    <option value="dean" <?= $staff['role'] === 'dean' ? 'selected' : '' ?>>Dean</option>
                    <option value="lecturer" <?= $staff['role'] === 'lecturer' ? 'selected' : '' ?>>Lecturer</option>
                    <option value="admissions" <?= $staff['role'] === 'admissions' ? 'selected' : '' ?>>Admissions</option>
                    <option value="quality_assurance" <?= $staff['role'] === 'quality_assurance' ? 'selected' : '' ?>>Quality Assurance</option>
                    <option value="human_resource" <?= $staff['role'] === 'human_resource' ? 'selected' : '' ?>>Human Resource</option>
                    <option value="finance" <?= $staff['role'] === 'finance' ? 'selected' : '' ?>>Finance</option>
                    <option value="librarian" <?= $staff['role'] === 'librarian' ? 'selected' : '' ?>>Librarian</option>
                </select>
            </div>
        </div>

        <button type="submit" class="btn btn-primary">
            <i class="fas fa-save"></i> Update Staff
        </button>
        <a href="staff.php" class="btn btn-secondary">Cancel</a>
    </form>
</div>

<?php include 'includes/footer.php'; ?>
