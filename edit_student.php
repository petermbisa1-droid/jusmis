<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'superuser') {
    header('Location: login.php');
    exit;
}

include 'config/db.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) die("Invalid student ID");

$stmt = $conn->prepare("SELECT * FROM students WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$student) die("Student not found");

$success = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $mode_of_study = $_POST['mode_of_study'] ?? '';

    if (!$full_name) $errors[] = "Full name is required.";
    if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Invalid email.";

    if (!$errors) {
        $stmt = $conn->prepare("UPDATE students SET full_name = ?, email = ?, phone = ?, mode_of_study = ? WHERE id = ?");
        $stmt->bind_param("ssssi", $full_name, $email, $phone, $mode_of_study, $id);
        $stmt->execute();
        $stmt->close();

        $success = "Student record updated.";
    }
}

include 'includes/header.php';
?>

<h3>Edit Student</h3>

<?php if ($errors): ?>
  <div class="alert alert-danger"><ul>
    <?php foreach ($errors as $e) echo "<li>$e</li>"; ?>
  </ul></div>
<?php endif; ?>

<?php if ($success): ?>
  <div class="alert alert-success"><?= $success ?></div>
<?php endif; ?>

<form method="POST">
  <div class="mb-3">
    <label>Registration Number</label>
    <input class="form-control" value="<?= htmlspecialchars($student['registration_number']) ?>" disabled>
  </div>

  <div class="mb-3">
    <label>Full Name</label>
    <input name="full_name" class="form-control" value="<?= htmlspecialchars($_POST['full_name'] ?? $student['full_name']) ?>" required>
  </div>

  <div class="mb-3">
    <label>Email</label>
    <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($_POST['email'] ?? $student['email']) ?>">
  </div>

  <div class="mb-3">
    <label>Phone</label>
    <input name="phone" class="form-control" value="<?= htmlspecialchars($_POST['phone'] ?? $student['phone']) ?>">
  </div>

  <div class="mb-3">
    <label>Mode of Study</label>
    <select name="mode_of_study" class="form-select" required>
      <?php foreach(['Normal','Evening','Weekend','Blended'] as $mode): ?>
        <option value="<?= $mode ?>" <?= ($student['mode_of_study'] === $mode) ? 'selected' : '' ?>><?= $mode ?></option>
      <?php endforeach; ?>
    </select>
  </div>

  <button class="btn btn-primary">Update Student</button>
</form>

<?php include 'includes/footer.php'; ?>
