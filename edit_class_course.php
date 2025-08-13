<?php
session_start();
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}
include 'config/db.php';
include 'includes/logger.php';

$id = intval($_GET['id'] ?? 0);

// Get existing assignment
$stmt = $conn->prepare("SELECT * FROM class_courses WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$assignment = $result->fetch_assoc();
$stmt->close();

if (!$assignment) {
    echo "<div class='alert alert-danger'>Assignment not found.</div>";
    exit;
}

$class_id = $assignment['class_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $course_id = intval($_POST['course_id']);

    // Check for duplicate
    $checkStmt = $conn->prepare("SELECT id FROM class_courses WHERE class_id = ? AND course_id = ? AND id != ?");
    $checkStmt->bind_param("iii", $class_id, $course_id, $id);
    $checkStmt->execute();
    $checkStmt->store_result();
    if ($checkStmt->num_rows > 0) {
        $error = "This course is already assigned to this class.";
    } else {
        $updateStmt = $conn->prepare("UPDATE class_courses SET course_id = ? WHERE id = ?");
        $updateStmt->bind_param("ii", $course_id, $id);
        if ($updateStmt->execute()) {
            log_action("Edited class-course ID $id", $conn);
            header("Location: view_class_courses.php?class_id=$class_id");
            exit;
        } else {
            $error = "Failed to update.";
        }
    }
    $checkStmt->close();
}

// Fetch all courses
$courses = $conn->query("SELECT id, course_name, course_code FROM courses");

include 'includes/header.php';
?>
<h3>Edit Course Assignment</h3>
<?php if (isset($error)): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>
<form method="post">
    <div class="mb-3">
        <label for="course_id" class="form-label">Course</label>
        <select name="course_id" id="course_id" class="form-select" required>
            <?php while ($course = $courses->fetch_assoc()): ?>
                <option value="<?= $course['id'] ?>" <?= $course['id'] == $assignment['course_id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($course['course_code'] . " - " . $course['course_name']) ?>
                </option>
            <?php endwhile; ?>
        </select>
    </div>
    <button class="btn btn-primary" type="submit">Update</button>
    <a href="view_class_courses.php?class_id=<?= $class_id ?>" class="btn btn-secondary">Cancel</a>
</form>
<?php include 'includes/footer.php'; ?>
