<?php
session_start();
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['role'], ['superuser', 'lecturer', 'academic_staff'])) {
    header('Location: login.php');
    exit;
}

include 'config/db.php';

$errors = [];
$success = '';

function old($key, $default = '') {
    return htmlspecialchars($_POST[$key] ?? $default);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['download_template'])) {
    $course_id = (int)($_POST['course_id'] ?? 0);
    $session_id = (int)($_POST['session_id'] ?? 0);
    $class_id = (int)($_POST['class_id'] ?? 0);
    $template_type = $_POST['template_type'] ?? '';

    if (!$course_id || !$session_id || !$class_id) {
        $errors[] = "Course, Session, and Class must be selected.";
    } else {
        if ($template_type === 'continuous_assessment') {
            // Continuous Assessment template: fullname, registration number, course name, CW1, CW2, Mid columns blank for filling

            $stmt = $conn->prepare("
                SELECT s.full_name, s.registration_number, c.course_name
                FROM registrations r
                JOIN registration_courses rc ON rc.registration_id = r.id
                JOIN students s ON r.student_id = s.id
                JOIN courses c ON c.id = rc.course_id
                WHERE r.session_id = ? AND r.class_id = ? AND rc.course_id = ? AND s.status = 'active'
                ORDER BY s.full_name
            ");
            $stmt->bind_param("iii", $session_id, $class_id, $course_id);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 0) {
                $errors[] = "No students found for the selected course, session, and class.";
            } else {
                header('Content-Type: text/csv');
                header('Content-Disposition: attachment;filename=continuous_assessment_template.csv');

                $output = fopen('php://output', 'w');
                fputcsv($output, ['full_name', 'registration_number', 'Course Name', 'cw1', 'cw2', 'mid']);

                while ($row = $result->fetch_assoc()) {
                    fputcsv($output, [
                        $row['full_name'],
                        $row['registration_number'],
                        $row['course_name'],
                        '', '', ''
                    ]);
                }
                fclose($output);
                exit;
            }
            $stmt->close();
        } elseif ($template_type === 'final_exam') {
            // Final Exam template: exam_number, course name, final_exam

            // First get academic_year_id for filtering
            $ayStmt = $conn->prepare("SELECT academic_year_id FROM registrations WHERE session_id = ? AND class_id = ? LIMIT 1");
            $ayStmt->bind_param("ii", $session_id, $class_id);
            $ayStmt->execute();
            $ayStmt->bind_result($academic_year_id);
            if (!$ayStmt->fetch()) {
                $errors[] = "Unable to find Academic Year for the selected Session and Class.";
                $ayStmt->close();
            } else {
                $ayStmt->close();

                $stmt = $conn->prepare("
                    SELECT r.exam_number, c.course_name
                    FROM registrations r
                    JOIN registration_courses rc ON rc.registration_id = r.id
                    JOIN students s ON r.student_id = s.id
                    JOIN courses c ON c.id = rc.course_id
                    WHERE r.session_id = ?
                      AND r.academic_year_id = ?
                      AND r.class_id = ?
                      AND rc.course_id = ?
                      AND s.status = 'active'
                    ORDER BY r.exam_number
                ");
                $stmt->bind_param("iiii", $session_id, $academic_year_id, $class_id, $course_id);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result->num_rows === 0) {
                    $errors[] = "No exam numbers found for the selected criteria.";
                } else {
                    header('Content-Type: text/csv');
                    header('Content-Disposition: attachment;filename=final_exam_template.csv');

                    $output = fopen('php://output', 'w');
                    fputcsv($output, ['exam_number', 'course_name', 'exam']);

                    while ($row = $result->fetch_assoc()) {
                        fputcsv($output, [
                            $row['exam_number'],
                            $row['course_name'],
                            '' // blank grade for input
                        ]);
                    }
                    fclose($output);
                    exit;
                }
                $stmt->close();
            }
        } else {
            $errors[] = "Invalid template type selected.";
        }
    }
}

// Fetch options for dropdowns
$coursesRes = $conn->query("SELECT id, course_name FROM courses WHERE is_active = 1 ORDER BY course_name");
$sessionsRes = $conn->query("SELECT id, session_name FROM sessions WHERE is_active = 1 ORDER BY session_name");
$classesRes = $conn->query("SELECT id, class_name FROM classes WHERE is_active = 1 ORDER BY class_name");

include 'includes/header.php';
?>

<h3>Download Grading Template</h3>

<?php if ($errors): ?>
    <div class="alert alert-danger"><ul>
        <?php foreach ($errors as $e) echo "<li>$e</li>"; ?>
    </ul></div>
<?php endif; ?>

<form method="POST" class="mb-3">
    <div class="mb-3">
        <label>Course</label>
        <select name="course_id" class="form-select" required>
            <option value="">Select Course</option>
            <?php while ($row = $coursesRes->fetch_assoc()): ?>
                <option value="<?= $row['id'] ?>" <?= old('course_id') == $row['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($row['course_name']) ?>
                </option>
            <?php endwhile; ?>
        </select>
    </div>

    <div class="mb-3">
        <label>Session</label>
        <select name="session_id" class="form-select" required>
            <option value="">Select Session</option>
            <?php while ($row = $sessionsRes->fetch_assoc()): ?>
                <option value="<?= $row['id'] ?>" <?= old('session_id') == $row['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($row['session_name']) ?>
                </option>
            <?php endwhile; ?>
        </select>
    </div>

    <div class="mb-3">
        <label>Class</label>
        <select name="class_id" class="form-select" required>
            <option value="">Select Class</option>
            <?php while ($row = $classesRes->fetch_assoc()): ?>
                <option value="<?= $row['id'] ?>" <?= old('class_id') == $row['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($row['class_name']) ?>
                </option>
            <?php endwhile; ?>
        </select>
    </div>

    <div class="mb-3">
        <label>Template Type</label>
        <select name="template_type" class="form-select" required>
            <option value="">Select Template Type</option>
            <option value="continuous_assessment" <?= old('template_type') === 'continuous_assessment' ? 'selected' : '' ?>>Continuous Assessment</option>
            <option value="final_exam" <?= old('template_type') === 'final_exam' ? 'selected' : '' ?>>Final Exam</option>
        </select>
    </div>

    <button type="submit" name="download_template" class="btn btn-primary">Download Template</button>
</form>

<?php include 'includes/footer.php'; ?>
