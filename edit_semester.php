<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'superuser') {
    header('Location: login.php');
    exit;
}
include 'config/db.php';

$message = '';
$id = intval($_GET['id'] ?? 0);

if ($id <= 0) {
    header("Location: semesters.php");
    exit;
}

// Fetch existing data
$stmt = $conn->prepare("SELECT * FROM semesters WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$semester = $result->fetch_assoc();

if (!$semester) {
    header("Location: semesters.php");
    exit;
}

// Update semester
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $updated_by = $_SESSION['user']['full_name'];

    $stmt = $conn->prepare("UPDATE semesters SET name = ?, updated_by = ? WHERE id = ?");
    $stmt->bind_param("ssi", $name, $updated_by, $id);
    if ($stmt->execute()) {
        $message = "Semester updated successfully.";
    } else {
        $message = "Failed to update semester.";
    }
}

?>

<?php include 'includes/header.php'; ?>

<h3 class="mb-4">Edit Semester</h3>

<?php if ($message): ?>
    <div class="alert alert-info"> <?= htmlspecialchars($message) ?> </div>
<?php endif; ?>

<form method="POST" class="bg-dark p-4 rounded">
    <div class="mb-3">
        <label class="form-label">Semester Name</label>
        <input type="text" name="name" class="form-control" required value="<?= htmlspecialchars($semester['name']) ?>">
    </div>
    <button class="btn btn-light"><i class="fas fa-save"></i> Save Changes</button>
</form>

<?php include 'includes/footer.php'; ?>
