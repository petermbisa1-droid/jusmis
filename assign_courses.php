<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit;
}

include 'config/db.php';
include 'includes/logger.php';

$classId = $_GET['class_id'] ?? null;

// Handle course assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['courses'], $_POST['class_id'])) {
    $classId = $_POST['class_id'];
    $user = $_SESSION['user']['full_name'] ?? 'System';

    foreach ($_POST['courses'] as $courseId) {
        // Prevent duplicates
        $checkSql = "SELECT id FROM class_courses WHERE class_id = ? AND course_id = ?";
        $checkStmt = $conn->prepare($checkSql);
        $checkStmt->bind_param('ii', $classId, $courseId);
        $checkStmt->execute();
        $checkStmt->store_result();

        if ($checkStmt->num_rows === 0) {
            $insertSql = "INSERT INTO class_courses (class_id, course_id, created_at) VALUES (?, ?, NOW())";
            $insertStmt = $conn->prepare($insertSql);
            $insertStmt->bind_param('ii', $classId, $courseId);
            $insertStmt->execute();
            $insertStmt->close();
        }
        $checkStmt->close();
    }

    log_action("Assigned courses to class ID $classId", $conn);
    header("Location: assign_courses.php?class_id=$classId&success=1");
    exit;
}

// Get all classes
$classes = $conn->query("SELECT c.id, c.class_name, p.programme_name, c.semester FROM classes c JOIN programmes p ON c.programme_id = p.id ORDER BY c.created_at DESC")->fetch_all(MYSQLI_ASSOC);

// Get all courses
$courses = $conn->query("SELECT id, course_code, course_name FROM courses ORDER BY course_name ASC")->fetch_all(MYSQLI_ASSOC);

// Get already assigned courses for selected class
$assignedCourses = [];
if ($classId) {
    $assignedResult = $conn->prepare("SELECT course_id FROM class_courses WHERE class_id = ?");
    $assignedResult->bind_param("i", $classId);
    $assignedResult->execute();
    $assignedCoursesRaw = $assignedResult->get_result()->fetch_all(MYSQLI_ASSOC);
    foreach ($assignedCoursesRaw as $row) {
        $assignedCourses[] = $row['course_id'];
    }
}
?>

<?php include 'includes/header.php'; ?>

<h3>Assign Courses to Class</h3>

<form method="GET" class="mb-3">
    <label for="class_id">Select Class:</label>
    <select name="class_id" id="class_id" class="form-select" onchange="this.form.submit()" required>
        <option value="">-- Select Class --</option>
        <?php foreach ($classes as $class): ?>
            <option value="<?= $class['id'] ?>" <?= $class['id'] == $classId ? 'selected' : '' ?>>
                <?= htmlspecialchars($class['class_name']) ?> (<?= $class['programme_name'] ?> - <?= $class['semester'] ?>)
            </option>
        <?php endforeach; ?>
    </select>
</form>

<?php if ($classId): ?>
    <form method="POST">
        <input type="hidden" name="class_id" value="<?= $classId ?>">
        <h5>Available Courses</h5>
        <div class="row">
            <?php foreach ($courses as $course): ?>
                <div class="col-md-4">
                    <div class="form-check">
                        <input
                            class="form-check-input"
                            type="checkbox"
                            name="courses[]"
                            value="<?= $course['id'] ?>"
                            id="course_<?= $course['id'] ?>"
                            <?= in_array($course['id'], $assignedCourses) ? 'disabled' : '' ?>
                        >
                        <label class="form-check-label" for="course_<?= $course['id'] ?>">
                            <?= htmlspecialchars($course['course_code']) ?> - <?= htmlspecialchars($course['course_name']) ?>
                            <?php if (in_array($course['id'], $assignedCourses)): ?>
                                <span class="badge bg-secondary">Assigned</span>
                            <?php endif; ?>
                        </label>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <button class="btn btn-success mt-3"><i class="fas fa-save"></i> Assign Selected Courses</button>
    </form>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
