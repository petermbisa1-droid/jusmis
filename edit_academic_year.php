<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'superuser') {
    header('Location: login.php');
    exit;
}
include 'config/db.php';
include 'includes/logger.php';

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) {
    header("Location: academic_years.php");
    exit;
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $year_name = trim($_POST['year_name']);
    $updated_by = $_SESSION['user']['full_name'] ?? 'Unknown';

    // Check duplicate (exclude current)
    $check = $conn->prepare("SELECT COUNT(*) FROM academic_years WHERE year_name = ? AND id != ?");
    $check->bind_param("si", $year_name, $id);
    $check->execute();
    $check->bind_result($exists);
    $check->fetch();
    $check->close();

    if ($exists > 0) {
        $message = "Another academic year with the same name exists.";
    } else {
        $stmt = $conn->prepare("UPDATE academic_years SET year_name = ?, updated_by = ? WHERE id = ?");
        $stmt->bind_param("ssi", $year_name, $updated_by, $id);
        if ($stmt->execute()) {
            log_action("Edited academic year ID $id", $conn);
            header("Location: academic_years.php");
            exit;
        } else {
            $message = "Update failed.";
        }
    }
}

// Load current data
$stmt = $conn->prepare("SELECT year_name FROM academic_years WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$stmt->bind_result($year_name);
$stmt->fetch();
$stmt->close();
?>

<?php include 'includes/header.php'; ?>

<h4>Edit Academic Year</h4>

<?php if ($message): ?>
    <div class="alert alert-warning"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<form method="POST" class="bg-dark p-4 rounded">
    <div class="mb-3">
        <label class="form-label">Academic Year Name</label>
        <input type="text" name="year_name" class="form-control" required value="<?= htmlspecialchars($year_name) ?>">
    </div>
    <button class="btn btn-light"><i class="fas fa-save"></i> Update</button>
    <a href="academic_years.php" class="btn btn-secondary">Cancel</a>
</form>

<?php include 'includes/footer.php'; ?>
