<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'superuser') {
    header('Location: login.php');
    exit;
}

include 'config/db.php';
include 'includes/logger.php';

$id = intval($_GET['id'] ?? 0);
if (!$id) {
    header('Location: users.php');
    exit;
}

$errors = [];
$full_name = '';
$username = '';
$role = '';

$stmt = $conn->prepare("SELECT full_name, username, role FROM users WHERE id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$stmt->bind_result($full_name, $username, $role);
if (!$stmt->fetch()) {
    $stmt->close();
    header('Location: users.php');
    exit;
}
$stmt->close();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_full_name = trim($_POST['full_name']);
    $new_username = trim($_POST['username']);
    $new_role = $_POST['role'];
    $updated_by = $_SESSION['user']['full_name'];

    if ($new_full_name === '') $errors[] = "Full name is required.";
    if ($new_username === '') $errors[] = "Username is required.";
    if (!in_array($new_role, ['superuser','admin','staff'])) $errors[] = "Invalid role selected.";

    // Check duplicate username excluding current
    if (!$errors) {
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
        $stmt->bind_param('si', $new_username, $id);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $errors[] = "Username already exists.";
        }
        $stmt->close();
    }

    if (!$errors) {
        $stmt = $conn->prepare("UPDATE users SET full_name = ?, username = ?, role = ?, updated_by = ?, updated_at = NOW() WHERE id = ?");
        $stmt->bind_param('ssssi', $new_full_name, $new_username, $new_role, $updated_by, $id);
        if ($stmt->execute()) {
            log_action("Updated user id $id ($new_username)", $conn);
            header('Location: users.php?msg=User updated successfully');
            exit;
        } else {
            $errors[] = "Failed to update user.";
        }
        $stmt->close();
    }
}

include 'includes/header.php';
?>

<h3>Edit User</h3>

<?php if ($errors): ?>
    <div class="alert alert-danger">
        <ul><?php foreach($errors as $e) echo "<li>".htmlspecialchars($e)."</li>"; ?></ul>
    </div>
<?php endif; ?>

<form method="POST" class="bg-dark p-4 rounded">
  <div class="mb-3">
    <label class="form-label">Full Name</label>
    <input type="text" name="full_name" class="form-control" required value="<?= htmlspecialchars($full_name) ?>">
  </div>
  <div class="mb-3">
    <label class="form-label">Username</label>
    <input type="text" name="username" class="form-control" required value="<?= htmlspecialchars($username) ?>">
  </div>
  <div class="mb-3">
    <label class="form-label">Role</label>
    <select name="role" class="form-select" required>
      <option value="superuser" <?= $role === 'superuser' ? 'selected' : '' ?>>Superuser</option>
      <option value="admin" <?= $role === 'admin' ? 'selected' : '' ?>>Admin</option>
      <option value="staff" <?= $role === 'staff' ? 'selected' : '' ?>>Staff</option>
    </select>
  </div>
  <button class="btn btn-light"><i class="fas fa-save"></i> Save Changes</button>
  <a href="users.php" class="btn btn-outline-light ms-2">Cancel</a>
</form>

<?php include 'includes/footer.php'; ?>
