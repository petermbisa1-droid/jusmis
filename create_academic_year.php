<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'superuser') {
    header('Location: login.php');
    exit;
}
include 'config/db.php';
include 'includes/logger.php';

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $year_name = trim($_POST['year_name']);
    $created_by = $_SESSION['user']['full_name'] ?? 'Unknown';

    // Check for duplicate
    $check = $conn->prepare("SELECT COUNT(*) FROM academic_years WHERE year_name = ?");
    $check->bind_param("s", $year_name);
    $check->execute();
    $check->bind_result($exists);
    $check->fetch();
    $check->close();

    if ($exists > 0) {
        $message = "Academic year already exists.";
    } else {
        $stmt = $conn->prepare("INSERT INTO academic_years (year_name, created_by) VALUES (?, ?)");
        $stmt->bind_param("ss", $year_name, $created_by);
        if ($stmt->execute()) {
            log_action("Created academic year: $year_name", $conn);
            header("Location: academic_years.php");
            exit;
        } else {
            $message = "Failed to create academic year.";
        }
    }
}
?>

<?php include 'includes/header.php'; ?>

<h4>Add Academic Year</h4>

<?php if ($message): ?>
    <div class="alert alert-warning"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<form method="POST" class="bg-dark p-4 rounded">
    <div class="mb-3">
        <label class="form-label">Academic Year Name</label>
        <input type="text" name="year_name" class="form-control" required placeholder="e.g. 2024/2025">
    </div>
    <button class="btn btn-light"><i class="fas fa-save"></i> Save</button>
    <a href="academic_years.php" class="btn btn-secondary">Cancel</a>
</form>

<?php include 'includes/footer.php'; ?>
