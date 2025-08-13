<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'superuser') {
    header('Location: login.php');
    exit;
}

include 'config/db.php';         // Database connection
include 'includes/logger.php';  // Contains the log_action() function

$programme_name = '';
$code = '';
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $programme_name = trim($_POST['programme_name']);
    $code = strtoupper(trim($_POST['code']));
    $created_by = $_SESSION['user']['username'];

    if ($programme_name === '' || $code === '') {
        $error = "Programme name and code are required.";
    } else {
        // Check for duplication
        $check = $conn->prepare("SELECT COUNT(*) FROM programmes WHERE programme_name = ? OR code = ?");
        $check->bind_param("ss", $programme_name, $code);
        $check->execute();
        $check->bind_result($exists);
        $check->fetch();
        $check->close();

        if ($exists > 0) {
            $error = "Programme with the same name or code already exists.";
        } else {
            $stmt = $conn->prepare("INSERT INTO programmes (programme_name, code, is_active, created_by) VALUES (?, ?, 1, ?)");
            $stmt->bind_param("sss", $programme_name, $code, $created_by);
            $stmt->execute();
            $stmt->close();

            // Log to audit_trail table
            log_action("Created new programme: $programme_name ($code)", $conn);

            $success = "Programme added successfully.";
            $programme_name = $code = ''; // Reset form
        }
    }
}

include 'includes/header.php';
?>

<h3>Add New Programme</h3>

<?php if ($error): ?>
  <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php elseif ($success): ?>
  <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<form method="POST" class="bg-dark text-white p-4 rounded">
  <div class="mb-3">
    <label class="form-label">Programme Name</label>
    <input type="text" name="programme_name" class="form-control" value="<?= htmlspecialchars($programme_name) ?>" required>
  </div>
  <div class="mb-3">
    <label class="form-label">Code</label>
    <input type="text" name="code" class="form-control" value="<?= htmlspecialchars($code) ?>" required>
  </div>
  <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> Save Programme</button>
  <a href="programmes.php" class="btn btn-secondary">Back</a>
</form>

<?php include 'includes/footer.php'; ?>
