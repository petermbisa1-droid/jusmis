<?php
session_start();
include 'config/db.php';
include 'includes/header.php';

// Access control
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['role'], ['superuser', 'admin', 'lecturer'])) {
    header('Location: login.php');
    exit;
}

// Check grading calendar lock
$today = date('Y-m-d');
$stmt = $conn->prepare("SELECT COUNT(*) FROM grading_calendar WHERE is_active = 1 AND ? BETWEEN start_date AND end_date");
$stmt->bind_param("s", $today);
$stmt->execute();
$stmt->bind_result($count);
$stmt->fetch();
$stmt->close();

if ($count == 0) {
    echo '<div class="alert alert-warning my-4">Final exam grade upload is currently <strong>locked</strong> because no active grading period is ongoing. Please contact admin.</div>';
    include 'includes/footer.php';
    exit;
}

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_FILES['exam_file']) || $_FILES['exam_file']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = "Please upload a valid CSV file.";
    }

    $course_id = intval($_POST['course_id'] ?? 0);
    $session_id = intval($_POST['session_id'] ?? 0);
    $class_id = intval($_POST['class_id'] ?? 0);
    $uploaded_by = $_SESSION['user']['id'] ?? null;

    if (!$course_id || !$session_id || !$class_id) {
        $errors[] = "Course, Session, and Class are required.";
    }

    if (empty($errors)) {
        $fileTmpPath = $_FILES['exam_file']['tmp_name'];

        if (($handle = fopen($fileTmpPath, 'r')) !== false) {
            // Expected CSV header: exam_number,exam
            $header = fgetcsv($handle);
            $expectedHeaders = ['exam_number', 'exam'];
            if ($header !== $expectedHeaders) {
                $errors[] = "Invalid CSV header. Expected: " . implode(',', $expectedHeaders);
            } else {
                $successCount = 0;
                $failCount = 0;
                $failDetails = [];

                while (($data = fgetcsv($handle)) !== false) {
                    if (count($data) < 2) continue;

                    $exam_number = trim($data[0]);
                    $final_score = trim($data[1]);

                    // Lookup student by registrations.exam_number, session, class, active student
                    $stmt = $conn->prepare("
                        SELECT s.id FROM students s
                        JOIN registrations r ON r.student_id = s.id
                        WHERE r.exam_number = ? AND r.session_id = ? AND r.class_id = ? AND s.status = 'active'
                        LIMIT 1
                    ");
                    $stmt->bind_param("sii", $exam_number, $session_id, $class_id);
                    $stmt->execute();
                    $stmt->bind_result($student_id);
                    $found = $stmt->fetch();
                    $stmt->close();

                    if (!$found) {
                        $failCount++;
                        $failDetails[] = "- Student with exam_number '{$exam_number}' not found or not registered in selected session/class.";
                        continue;
                    }

                    // Check if grade exists for student/course/session/class
                    $checkStmt = $conn->prepare("SELECT id FROM grades WHERE student_id = ? AND course_id = ? AND session_id = ? AND class_id = ?");
                    $checkStmt->bind_param("iiii", $student_id, $course_id, $session_id, $class_id);
                    $checkStmt->execute();
                    $checkStmt->store_result();

                    if ($checkStmt->num_rows > 0) {
                        $checkStmt->bind_result($grade_id);
                        $checkStmt->fetch();

                        $updateStmt = $conn->prepare("UPDATE grades SET exam = ?, updated_at = NOW(), uploaded_by = ? WHERE id = ?");
                        $updateStmt->bind_param("iii", $final_score, $uploaded_by, $grade_id);
                        $ok = $updateStmt->execute();
                        $updateStmt->close();
                    } else {
                        $insertStmt = $conn->prepare("INSERT INTO grades (student_id, course_id, session_id, class_id, exam, uploaded_by, uploaded_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())");
                        $insertStmt->bind_param("iiiiii", $student_id, $course_id, $session_id, $class_id, $final_score, $uploaded_by);
                        $ok = $insertStmt->execute();
                        $insertStmt->close();
                    }
                    $checkStmt->close();

                    if ($ok) {
                        $successCount++;
                    } else {
                        $failCount++;
                        $failDetails[] = "- Failed to save final exam grade for student {$exam_number}.";
                    }
                }

                fclose($handle);

                $success = "$successCount final exam grades uploaded successfully.";
                if ($failCount > 0) {
                    $errors[] = "$failCount records failed to upload.";
                    // Optional: log $failDetails somewhere or display less verbosely
                }
            }
        } else {
            $errors[] = "Unable to open uploaded file.";
        }
    }
}

// Fetch courses, sessions, classes for form dropdowns
$courses = $conn->query("SELECT id, course_name FROM courses ORDER BY course_name");
$sessions = $conn->query("SELECT id, session_name FROM sessions ORDER BY session_name DESC");
$classes = $conn->query("SELECT id, class_name FROM classes ORDER BY class_name");
?>

<h2>Upload Final Exam Grades (CSV)</h2>

<?php if ($errors): ?>
    <div class="alert alert-danger">
        <ul><?php foreach ($errors as $e) echo "<li>" . htmlspecialchars($e) . "</li>"; ?></ul>
    </div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<form method="POST" enctype="multipart/form-data" class="mb-4">
    <div class="mb-3">
        <label>Course</label>
        <select name="course_id" class="form-select" required>
            <option value="">Select Course</option>
            <?php while ($row = $courses->fetch_assoc()): ?>
                <option value="<?= $row['id'] ?>" <?= (isset($_POST['course_id']) && $_POST['course_id'] == $row['id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($row['course_name']) ?>
                </option>
            <?php endwhile; ?>
        </select>
    </div>

    <div class="mb-3">
        <label>Session</label>
        <select name="session_id" class="form-select" required>
            <option value="">Select Session</option>
            <?php while ($row = $sessions->fetch_assoc()): ?>
                <option value="<?= $row['id'] ?>" <?= (isset($_POST['session_id']) && $_POST['session_id'] == $row['id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($row['session_name']) ?>
                </option>
            <?php endwhile; ?>
        </select>
    </div>

    <div class="mb-3">
        <label>Class</label>
        <select name="class_id" class="form-select" required>
            <option value="">Select Class</option>
            <?php while ($row = $classes->fetch_assoc()): ?>
                <option value="<?= $row['id'] ?>" <?= (isset($_POST['class_id']) && $_POST['class_id'] == $row['id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($row['class_name']) ?>
                </option>
            <?php endwhile; ?>
        </select>
    </div>

    <div class="mb-3">
        <label>Upload CSV File</label>
        <input type="file" name="exam_file" class="form-control" accept=".csv" required>
        <small>CSV Format: exam_number,exam</small>
    </div>

    <button type="submit" class="btn btn-primary">Upload Final Exam Grades</button>
</form>

<?php include 'includes/footer.php'; ?>
