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
    header('Location: courses.php');
    exit;
}

$errors = [];

$stmt = $conn->prepare("SELECT * FROM courses WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if (!$course = $result->fetch_assoc()) {
    header('Location: courses.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $course_code = trim($_POST['course_code']);
    $course_name = trim($_POST['course_name']);
    $credit_hours = intval($_POST['credit_hours']);
    $type = $_POST['type'];
    $lecturer_id = $_POST['lecturer_id'] !== '' ? intval($_POST['lecturer_id']) : null;
    $updated_by = $_SESSION['user']['full_name'];
    $updated_at = date('Y-m-d H:i:s');

    if ($course_code === '' || $course_name === '' || !in_array($type, ['core', 'elective']) || $credit_hours <= 0) {
        $errors[] = "Please fill all required fields correctly.";
    }

    $dupStmt = $conn->prepare("SELECT id FROM courses WHERE course_code = ? AND id != ?");
    $dupStmt->bind_param("si", $course_code, $id);
    $dupStmt->execute();
    $dupStmt->store_result();
    if ($dupStmt->num_rows > 0) {
        $errors[] = "Course code already exists for another course.";
    }
    $dupStmt->close();

    if (!$errors) {
        $updateStmt = $conn->prepare("UPDATE courses SET course_code=?, course_name=?, credit_hours=?, type=?, lecturer_id=?, updated_by=?, updated_at=? WHERE id=?");
        $updateStmt->bind_param("ssissssi", $course_code, $course_name, $credit_hours, $type, $lecturer_id, $updated_by, $updated_at, $id);

        if ($updateStmt->execute()) {
            log_action("Updated course $course_code", $conn);
            header("Location: courses.php");
            exit;
        } else {
            $errors[] = "Error updating course: " . $conn->error;
        }
    }
}

$lecturers = [];
$lecturerSql = $conn->query("SELECT id, full_name FROM staff ORDER BY full_name");
while ($row = $lecturerSql->fetch_assoc()) {
    $lecturers[] = $row;
}

include 'includes/header.php';
?>

<h3>Edit Course</h3>

<?php if ($errors): ?>
    <div class="alert alert-danger">
        <ul><?php foreach ($errors as $e) echo "<li>$e</li>"; ?></ul>
    </div>
<?php endif; ?>

<form method="POST" class="bg-dark text-white p-4 rounded" novalidate>
    <div class="mb-3">
        <label>Course Code <span class="text-danger">*</span></label>
        <input type="text" name="course_code" class="form-control" required value="<?= htmlspecialchars($_POST['course_code'] ?? $course['course_code']) ?>">
    </div>
    <div class="mb-3">
        <label>Course Name <span class="text-danger">*</span></label>
        <input type="text" name="course_name" class="form-control" required value="<?= htmlspecialchars($_POST['course_name'] ?? $course['course_name']) ?>">
    </div>
    <div class="mb-3">
        <label>Credit Hours <span class="text-danger">*</span></label>
        <input type="number" name="credit_hours" class="form-control" min="1" required value="<?= htmlspecialchars($_POST['credit_hours'] ?? $course['credit_hours']) ?>">
    </div>
    <div class="mb-3">
        <label>Type <span class="text-danger">*</span></label>
        <select name="type" class="form-control" required>
            <option value="">-- Select Type --</option>
            <option value="core" <?= (($_POST['type'] ?? $course['type']) === 'core') ? 'selected' : '' ?>>Core</option>
            <option value="elective" <?= (($_POST['type'] ?? $course['type']) === 'elective') ? 'selected' : '' ?>>Elective</option>
        </select>
    </div>
    <div class="mb-3">
        <label>Assign Lecturer (optional)</label>
        <select name="lecturer_id" class="form-control">
            <option value="">-- None --</option>
            <?php foreach ($lecturers as $lec): ?>
                <option value="<?= $lec['id'] ?>" <?= ((($_POST['lecturer_id'] ?? $course['lecturer_id']) == $lec['id']) ? 'selected' : '') ?>>
                    <?= htmlspecialchars($lec['full_name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <button class="btn btn-primary"><i class="fas fa-save"></i> Update Course</button>
    <a href="courses.php" class="btn btn-secondary">Cancel</a>
</form>

<?php include 'includes/footer.php'; ?>
