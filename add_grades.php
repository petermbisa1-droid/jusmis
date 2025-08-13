<?php
session_start();
include 'config/db.php';
include 'includes/header.php';

// Access control
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['role'], ['superuser', 'admin', 'lecturer'])) {
    header('Location: login.php');
    exit;
}

// Check grading calendar lock (active grading period ongoing)
$today = date('Y-m-d');
$stmt = $conn->prepare("SELECT COUNT(*) FROM grading_calendar WHERE is_active = 1 AND ? BETWEEN start_date AND end_date");
$stmt->bind_param("s", $today);
$stmt->execute();
$stmt->bind_result($count);
$stmt->fetch();
$stmt->close();

if ($count == 0) {
    echo '<div class="alert alert-warning my-4">Grade entry is currently <strong>locked</strong> because no active grading period is ongoing. Please contact admin.</div>';
    include 'includes/footer.php';
    exit;
}

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $course_id = intval($_POST['course_id'] ?? 0);
    $session_id = intval($_POST['session_id'] ?? 0);
    $class_id = intval($_POST['class_id'] ?? 0);
    $student_id = intval($_POST['student_id'] ?? 0);
    $academic_year_id = intval($_POST['academic_year_id'] ?? 0);
    $grading_period_id = intval($_POST['grading_period_id'] ?? 0);
    $cw1 = intval($_POST['cw1'] ?? 0);
    $cw2 = intval($_POST['cw2'] ?? 0);
    $mid = intval($_POST['mid'] ?? 0);
    $uploaded_by = $_SESSION['user']['id'] ?? null;

    // Validate required fields
    if (!$course_id || !$session_id || !$class_id || !$student_id) {
        $errors[] = "Course, Session, Class, and Student must be selected.";
    }
    if (!$academic_year_id) {
        $errors[] = "Academic Year must be selected.";
    }
    if (!$grading_period_id) {
        $errors[] = "Grading Period must be selected.";
    }

    if (empty($errors)) {
        // Calculate final score (40% of sum of cw1+cw2+mid)
        $total_ca = $cw1 + $cw2 + $mid;
        $final_score = round($total_ca * 0.4);

        // Assign grade letter and remarks
        if ($final_score >= 75 && $final_score <= 100) {
            $grade_letter = 'A'; // Distinction
            $remarks = 'Distinction';
        } elseif ($final_score >= 65) {
            $grade_letter = 'B'; // Credit
            $remarks = 'Credit';
        } elseif ($final_score >= 45) {
            $grade_letter = 'C'; // Pass
            $remarks = 'Pass';
        } else {
            $grade_letter = 'F'; // Fail
            $remarks = 'Fail';
        }

        // Check if grade exists to update or insert
        $checkStmt = $conn->prepare("SELECT id FROM grades WHERE student_id = ? AND course_id = ? AND session_id = ? AND class_id = ? AND academic_year_id = ? AND grading_period_id = ?");
        $checkStmt->bind_param("iiiiii", $student_id, $course_id, $session_id, $class_id, $academic_year_id, $grading_period_id);
        $checkStmt->execute();
        $checkStmt->store_result();

        if ($checkStmt->num_rows > 0) {
            // Update existing grade
            $checkStmt->bind_result($grade_id);
            $checkStmt->fetch();

            $updateStmt = $conn->prepare("UPDATE grades SET cw1 = ?, cw2 = ?, mid = ?, grade_letter = ?, remarks = ?, updated_at = NOW(), uploaded_by = ? WHERE id = ?");
            $updateStmt->bind_param("iiissii", $cw1, $cw2, $mid, $grade_letter, $remarks, $uploaded_by, $grade_id);

            if ($updateStmt->execute()) {
                $success = "Grades updated successfully.";
            } else {
                $errors[] = "Failed to update grades: " . $conn->error;
            }
            $updateStmt->close();
        } else {
            // Insert new grade
            $insertStmt = $conn->prepare("INSERT INTO grades (student_id, course_id, session_id, academic_year_id, class_id, grading_period_id, cw1, cw2, mid, grade_letter, remarks, uploaded_by, uploaded_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
            $insertStmt->bind_param("iiiiiiissssi", $student_id, $course_id, $session_id, $academic_year_id, $class_id, $grading_period_id, $cw1, $cw2, $mid, $grade_letter, $remarks, $uploaded_by);

            if ($insertStmt->execute()) {
                $success = "Grades added successfully.";
            } else {
                $errors[] = "Failed to add grades: " . $conn->error;
            }
            $insertStmt->close();
        }
        $checkStmt->close();
    }
}

// Fetch dropdown data
$courses = $conn->query("SELECT id, course_name FROM courses ORDER BY course_name");
$sessions = $conn->query("SELECT id, session_name FROM sessions ORDER BY session_name DESC");
$classes = $conn->query("SELECT id, class_name FROM classes ORDER BY class_name");
$academic_years = $conn->query("SELECT id, year_name FROM academic_years ORDER BY year_name DESC");
$grading_periods = $conn->query("SELECT id, grading_period_name FROM grading_calendar WHERE is_active = 1 ORDER BY start_date");
?>

<h2>Add Continuous Assessment Grades (CW1, CW2, Mid)</h2>

<?php if ($errors): ?>
    <div class="alert alert-danger">
        <ul><?php foreach ($errors as $e) echo "<li>" . htmlspecialchars($e) . "</li>"; ?></ul>
    </div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<form method="POST" class="mb-4" id="gradesForm">
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
        <label>Grading Period</label>
        <select name="grading_period_id" class="form-select" required>
            <option value="">Select Grading Period</option>
            <?php while ($row = $grading_periods->fetch_assoc()): ?>
                <option value="<?= $row['id'] ?>" <?= (isset($_POST['grading_period_id']) && $_POST['grading_period_id'] == $row['id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($row['grading_period_name']) ?>
                </option>
            <?php endwhile; ?>
        </select>
    </div>

    <div class="mb-3">
        <label>Student</label>
        <select name="student_id" class="form-select" required>
            <option value="">Select Student</option>
            <!-- dynamically loaded -->
        </select>
    </div>

    <div class="mb-3">
        <label>CW1 (0-100)</label>
        <input type="number" name="cw1" min="0" max="100" class="form-control" value="<?= htmlspecialchars($_POST['cw1'] ?? '') ?>" required>
    </div>

    <div class="mb-3">
        <label>CW2 (0-100)</label>
        <input type="number" name="cw2" min="0" max="100" class="form-control" value="<?= htmlspecialchars($_POST['cw2'] ?? '') ?>" required>
    </div>

    <div class="mb-3">
        <label>Mid Semester (0-100)</label>
        <input type="number" name="mid" min="0" max="100" class="form-control" value="<?= htmlspecialchars($_POST['mid'] ?? '') ?>" required>
    </div>

    <button type="submit" class="btn btn-primary">Add/Update Grades</button>
</form>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
$(function() {
    function loadStudents() {
        var course_id = $('select[name="course_id"]').val();
        var session_id = $('select[name="session_id"]').val();
        var class_id = $('select[name="class_id"]').val();

        if (course_id && session_id && class_id) {
            $.ajax({
                url: 'fetch_registered_students.php',
                data: { course_id: course_id, session_id: session_id, class_id: class_id },
                dataType: 'json',
                success: function(data) {
                    var studentSelect = $('select[name="student_id"]');
                    studentSelect.empty();
                    studentSelect.append('<option value="">Select Student</option>');
                    if (data.length === 0) {
                        studentSelect.append('<option disabled>No registered students found</option>');
                    } else {
                        $.each(data, function(i, student) {
                            var selected = '';
                            <?php if (isset($_POST['student_id'])): ?>
                            if (student.id == <?= json_encode((int)$_POST['student_id']) ?>) selected = 'selected';
                            <?php endif; ?>
                            studentSelect.append('<option value="' + student.id + '" '+ selected +'>' + student.full_name + '</option>');
                        });
                    }
                }
            });
        } else {
            $('select[name="student_id"]').empty().append('<option value="">Select Student</option>');
        }
    }

    $('select[name="course_id"], select[name="session_id"], select[name="class_id"]').change(loadStudents);

    <?php if (isset($_POST['course_id'], $_POST['session_id'], $_POST['class_id'])): ?>
        loadStudents();
    <?php endif; ?>
});
</script>

<?php include 'includes/footer.php'; ?>
