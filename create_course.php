<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'superuser') {
    header('Location: login.php');
    exit;
}

include 'config/db.php';
include 'includes/logger.php';

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $course_code = trim($_POST['course_code']);
    $course_name = trim($_POST['course_name']);
    $credit_hours = intval($_POST['credit_hours']);
    $type = $_POST['type'];
    $lecturer_id = $_POST['lecturer_id'] !== '' ? intval($_POST['lecturer_id']) : null;
    $created_by = $_SESSION['user']['full_name'];
    
    // Validate inputs
    if ($course_code === '' || $course_name === '' || !in_array($type, ['core', 'elective']) || $credit_hours <= 0) {
        $errors[] = "Please fill all required fields correctly.";
    }
    
    // Duplication check for course_code
    $check = $conn->prepare("SELECT 1 FROM courses WHERE course_code = ?");
    $check->bind_param("s", $course_code);
    $check->execute();
    $check->store_result();
    if ($check->num_rows > 0) {
        $errors[] = "Course code already exists.";
    }
    $check->close();
    
    if (!$errors) {
        $query = "INSERT INTO courses (course_code, course_name, credit_hours, type, lecturer_id, created_by, is_active) 
                  VALUES (?, ?, ?, ?, ?, ?, 1)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ssisss", $course_code, $course_name, $credit_hours, $type, $lecturer_id, $created_by);
        if ($stmt->execute()) {
            log_action("Created course $course_code", $conn);
            header("Location: courses.php");
            exit;
        } else {
            $errors[] = "Error saving course: " . $conn->error;
        }
        $stmt->close();
    }
}

// Fetch all staff for lecturer dropdown
$lecturers = [];
$lecturerSql = $conn->query("SELECT id, full_name FROM staff ORDER BY full_name");
while ($row = $lecturerSql->fetch_assoc()) {
    $lecturers[] = $row;
}

include 'includes/header.php';
?>

<h3>Create Course</h3>

<?php if ($errors): ?>
    <div class="alert alert-danger">
        <ul><?php foreach ($errors as $e) echo "<li>$e</li>"; ?></ul>
    </div>
<?php endif; ?>

<form method="POST" class="bg-dark text-white p-4 rounded" novalidate>
    <div class="mb-3">
        <label>Course Code <span class="text-danger">*</span></label>
        <input type="text" name="course_code" class="form-control" required value="<?= htmlspecialchars($_POST['course_code'] ?? '') ?>">
    </div>
    <div class="mb-3">
        <label>Course Name <span class="text-danger">*</span></label>
        <input type="text" name="course_name" class="form-control" required value="<?= htmlspecialchars($_POST['course_name'] ?? '') ?>">
    </div>
    <div class="mb-3">
        <label>Credit Hours <span class="text-danger">*</span></label>
        <input type="number" name="credit_hours" class="form-control" min="1" required value="<?= htmlspecialchars($_POST['credit_hours'] ?? '') ?>">
    </div>
    <div class="mb-3">
        <label>Type <span class="text-danger">*</span></label>
        <select name="type" class="form-control" required>
            <option value="">-- Select Type --</option>
            <option value="core" <?= (($_POST['type'] ?? '') === 'core') ? 'selected' : '' ?>>Core</option>
            <option value="elective" <?= (($_POST['type'] ?? '') === 'elective') ? 'selected' : '' ?>>Elective</option>
        </select>
    </div>
    <div class="mb-3">
        <label>Assign Lecturer (optional)</label>
        <select name="lecturer_id" class="form-control">
            <option value="">-- None --</option>
            <?php foreach ($lecturers as $lec): ?>
                <option value="<?= $lec['id'] ?>" <?= (($_POST['lecturer_id'] ?? '') == $lec['id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($lec['full_name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <button class="btn btn-light"><i class="fas fa-save"></i> Save Course</button>
    <a href="courses.php" class="btn btn-secondary">Cancel</a>
</form>

<?php include 'includes/footer.php'; ?>
