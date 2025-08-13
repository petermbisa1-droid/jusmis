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
    echo '<div class="alert alert-warning my-4">Grade upload is currently <strong>locked</strong> because no active grading period is ongoing. Please contact admin.</div>';
    include 'includes/footer.php';
    exit;
}

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_FILES['ca_file']) || $_FILES['ca_file']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = "Please upload a valid CSV file.";
    }

    $course_id = intval($_POST['course_id'] ?? 0);
    $session_id = intval($_POST['session_id'] ?? 0);
    $class_id = intval($_POST['class_id'] ?? 0);
    $academic_year_id = intval($_POST['academic_year_id'] ?? 0);
    $grading_period_id = intval($_POST['grading_period_id'] ?? 0);
    $uploaded_by = $_SESSION['user']['id'] ?? null;

    if (!$course_id || !$session_id || !$class_id || !$academic_year_id || !$grading_period_id) {
        $errors[] = "Course, Session, Class, Academic Year, and Grading Calendar are required.";
    }

    if (empty($errors)) {
        // Validate academic_year_id exists
        $stmt = $conn->prepare("SELECT id FROM academic_years WHERE id = ?");
        $stmt->bind_param("i", $academic_year_id);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows === 0) {
            $errors[] = "Selected Academic Year does not exist.";
        }
        $stmt->close();

        // Validate grading_period_id exists
        $stmt = $conn->prepare("SELECT id FROM grading_calendar WHERE id = ?");
        $stmt->bind_param("i", $grading_period_id);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows === 0) {
            $errors[] = "Selected Grading Calendar does not exist.";
        }
        $stmt->close();
    }

    if (empty($errors)) {
        $fileTmpPath = $_FILES['ca_file']['tmp_name'];

        if (($handle = fopen($fileTmpPath, 'r')) !== false) {
            $header = fgetcsv($handle);
            $expectedHeaders = ['full_name', 'registration_number', 'cw1', 'cw2', 'mid'];

            if ($header !== $expectedHeaders) {
                $errors[] = "Invalid CSV header. Expected: " . implode(',', $expectedHeaders);
            } else {
                $successCount = 0;
                $failCount = 0;
                $failDetails = [];

                while (($data = fgetcsv($handle)) !== false) {
                    [$full_name, $reg_number, $cw1, $cw2, $mid] = $data;

                    $cw1 = intval($cw1);
                    $cw2 = intval($cw2);
                    $mid = intval($mid);

                    // Lookup student registered for the course, session, and class
                    $stmt = $conn->prepare("
                        SELECT s.id
                        FROM students s
                        JOIN registrations r ON r.student_id = s.id
                        JOIN registration_courses rc ON rc.registration_id = r.id
                        WHERE s.registration_number = ?
                          AND r.session_id = ?
                          AND r.class_id = ?
                          AND rc.course_id = ?
                          AND s.status = 'active'
                        LIMIT 1
                    ");
                    $stmt->bind_param("siii", $reg_number, $session_id, $class_id, $course_id);
                    $stmt->execute();
                    $stmt->bind_result($student_id);
                    $found = $stmt->fetch();
                    $stmt->close();

                    if (!$found) {
                        $failCount++;
                        $failDetails[] = "Student $reg_number not found, inactive, or not registered for course.";
                        continue;
                    }

                    // Check if grade exists for this student/course/session/class
                    $checkStmt = $conn->prepare("SELECT id FROM grades WHERE student_id = ? AND course_id = ? AND session_id = ? AND class_id = ?");
                    $checkStmt->bind_param("iiii", $student_id, $course_id, $session_id, $class_id);
                    $checkStmt->execute();
                    $checkStmt->bind_result($grade_id);
                    $exists = $checkStmt->fetch();
                    $checkStmt->close();

                    if ($exists) {
                        // Update existing grade WITHOUT grade_letter and remarks
                        $updateStmt = $conn->prepare("
                            UPDATE grades SET cw1 = ?, cw2 = ?, mid = ?, 
                            academic_year_id = ?, grading_period_id = ?, updated_at = NOW(), uploaded_by = ?
                            WHERE id = ?
                        ");
                        $updateStmt->bind_param("iiiiiii", $cw1, $cw2, $mid, $academic_year_id, $grading_period_id, $uploaded_by, $grade_id);
                        $ok = $updateStmt->execute();
                        $updateStmt->close();
                    } else {
                        // Insert new grade WITHOUT grade_letter and remarks
                        $insertStmt = $conn->prepare("
                            INSERT INTO grades (
                                student_id, course_id, session_id, class_id, academic_year_id, grading_period_id,
                                cw1, cw2, mid, uploaded_by,
                                uploaded_at, updated_at
                            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                        ");
                        $insertStmt->bind_param(
                            "iiiiiiiiis",
                            $student_id,
                            $course_id,
                            $session_id,
                            $class_id,
                            $academic_year_id,
                            $grading_period_id,
                            $cw1,
                            $cw2,
                            $mid,
                            $uploaded_by
                        );
                        $ok = $insertStmt->execute();
                        $insertStmt->close();
                    }

                    if ($ok) {
                        $successCount++;
                    } else {
                        $failCount++;
                        $failDetails[] = "Failed to save grade for $reg_number.";
                    }
                }

                fclose($handle);

                $success = "$successCount grades uploaded successfully.";
                if ($failCount > 0) {
                    $errors[] = "$failCount records failed to upload.";
                }
            }
        } else {
            $errors[] = "Unable to open uploaded file.";
        }
    }
}

// Fetch dropdown data for the form
$courses = $conn->query("SELECT id, course_name FROM courses ORDER BY course_name");
$sessions = $conn->query("SELECT id, session_name FROM sessions ORDER BY session_name DESC");
$classes = $conn->query("SELECT id, class_name FROM classes ORDER BY class_name");
$academic_years = $conn->query("SELECT id, year_name FROM academic_years ORDER BY year_name DESC");
$grading_calendars = $conn->query("SELECT id, grading_period_name FROM grading_calendar WHERE is_active = 1 ORDER BY start_date DESC");
?>

<h2>Upload Continuous Assessment Grades (CSV)</h2>

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
        <label>Academic Year</label>
        <select name="academic_year_id" class="form-select" required>
            <option value="">Select Academic Year</option>
            <?php while ($row = $academic_years->fetch_assoc()): ?>
                <option value="<?= $row['id'] ?>" <?= (isset($_POST['academic_year_id']) && $_POST['academic_year_id'] == $row['id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($row['year_name']) ?>
                </option>
            <?php endwhile; ?>
        </select>
    </div>

    <div class="mb-3">
        <label>Grading Calendar</label>
        <select name="grading_period_id" class="form-select" required>
            <option value="">Select Grading Calendar</option>
            <?php while ($row = $grading_calendars->fetch_assoc()): ?>
                <option value="<?= $row['id'] ?>" <?= (isset($_POST['grading_period_id']) && $_POST['grading_period_id'] == $row['id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($row['grading_period_name']) ?>
                </option>
            <?php endwhile; ?>
        </select>
    </div>

    <div class="mb-3">
        <label>Upload CSV File</label>
        <input type="file" name="ca_file" class="form-control" accept=".csv" required>
        <small>CSV Format: full_name,registration_number,cw1,cw2,mid</small>
    </div>

    <button type="submit" class="btn btn-primary">Upload Grades</button>
</form>

<?php include 'includes/footer.php'; ?>
