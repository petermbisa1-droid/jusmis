<?php
session_start();

if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['role'], ['superuser', 'registrar'])) {
    header('Location: login.php');
    exit;
}

include 'config/db.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$errors = [];
$success = '';
$promotionResults = [];

function selected($value, $option) {
    return $value == $option ? 'selected' : '';
}

function run_promotion_logic($conn, $session_id, $class_id, $academic_year_id) {
    $promotionResults = [];

    // Get current class info (year, semester, program_code)
    $stmtClass = $conn->prepare("
        SELECT c.year, c.semester, p.code AS program_code 
        FROM classes c
        JOIN programmes p ON c.programme_id = p.id
        WHERE c.id = ? LIMIT 1
    ");
    $stmtClass->bind_param("i", $class_id);
    $stmtClass->execute();
    $stmtClass->bind_result($current_year, $current_semester, $program_code);
    if (!$stmtClass->fetch()) {
        $stmtClass->close();
        return ['error' => 'Invalid class ID for promotion'];
    }
    $stmtClass->close();

    // Determine next class
    if ($current_semester == 1) {
        $next_semester = 2;
        $next_year = $current_year;
    } else {
        $next_semester = 1;
        $next_year = $current_year + 1;
    }

    $stmtNextClass = $conn->prepare("
        SELECT c.id FROM classes c
        JOIN programmes p ON c.programme_id = p.id
        WHERE p.code = ? AND c.year = ? AND c.semester = ?
        LIMIT 1
    ");
    $stmtNextClass->bind_param("sii", $program_code, $next_year, $next_semester);
    $stmtNextClass->execute();
    $stmtNextClass->bind_result($next_class_id);
    if (!$stmtNextClass->fetch()) {
        $stmtNextClass->close();
        $next_class_id = null; // No next class found
    } else {
        $stmtNextClass->close();
    }

    // Get all distinct students in current class/session/year
    $stmtStudents = $conn->prepare("
        SELECT DISTINCT student_id FROM approved_grades 
        WHERE session_id = ? AND class_id = ? AND academic_year_id = ?
    ");
    $stmtStudents->bind_param("iii", $session_id, $class_id, $academic_year_id);
    $stmtStudents->execute();
    $studentsResult = $stmtStudents->get_result();

    while ($student = $studentsResult->fetch_assoc()) {
        $student_id = $student['student_id'];

        // Check if student has failing grades (remarks = 'Fail')
        $stmtFail = $conn->prepare("
            SELECT COUNT(*) FROM approved_grades
            WHERE student_id = ? AND session_id = ? AND class_id = ? AND academic_year_id = ? AND remarks = 'Fail'
        ");
        $stmtFail->bind_param("iiii", $student_id, $session_id, $class_id, $academic_year_id);
        $stmtFail->execute();
        $stmtFail->bind_result($fail_count);
        $stmtFail->fetch();
        $stmtFail->close();

        if ($fail_count > 0) {
            $new_class_id = $class_id; // stays in current class
            $promotion_status = 'Fail';
        } else {
            $new_class_id = $next_class_id ?? $class_id;
            $promotion_status = 'Pass';
        }

        // Insert or update promotion record
        $stmtCheckPromo = $conn->prepare("
            SELECT id FROM student_promotions
            WHERE student_id = ? AND session_id = ? AND academic_year_id = ?
        ");
        $stmtCheckPromo->bind_param("iii", $student_id, $session_id, $academic_year_id);
        $stmtCheckPromo->execute();
        $stmtCheckPromo->store_result();

        $now = date('Y-m-d H:i:s');

        if ($stmtCheckPromo->num_rows > 0) {
            $stmtCheckPromo->bind_result($promotion_id);
            $stmtCheckPromo->fetch();
            $stmtCheckPromo->close();

            $stmtUpdate = $conn->prepare("
                UPDATE student_promotions 
                SET old_class_id = ?, new_class_id = ?, promotion_status = ?, updated_at = ?
                WHERE id = ?
            ");
            $stmtUpdate->bind_param("iissi", $class_id, $new_class_id, $promotion_status, $now, $promotion_id);
            $stmtUpdate->execute();
            $stmtUpdate->close();
        } else {
            $stmtCheckPromo->close();

            $stmtInsert = $conn->prepare("
                INSERT INTO student_promotions 
                (student_id, session_id, academic_year_id, old_class_id, new_class_id, promotion_status, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmtInsert->bind_param("iiiissss", $student_id, $session_id, $academic_year_id, $class_id, $new_class_id, $promotion_status, $now, $now);
            $stmtInsert->execute();
            $stmtInsert->close();
        }

        $promotionResults[] = [
            'student_id' => $student_id,
            'old_class_id' => $class_id,
            'new_class_id' => $new_class_id,
            'status' => $promotion_status,
        ];
    }

    return $promotionResults;
}

// Handle form submit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_grades'])) {
    $session_id = (int)($_POST['session_id'] ?? 0);
    $class_id = (int)($_POST['class_id'] ?? 0);
    $academic_year_id = (int)($_POST['academic_year_id'] ?? 0);

    // Get active grading period id
    $grading_period_id = 0;
    $stmtActiveCal = $conn->prepare("SELECT id FROM grading_calendar WHERE is_active = 1 AND session_id = ? AND academic_year_id = ? LIMIT 1");
    $stmtActiveCal->bind_param("ii", $session_id, $academic_year_id);
    $stmtActiveCal->execute();
    $stmtActiveCal->bind_result($grading_period_id);
    $stmtActiveCal->fetch();
    $stmtActiveCal->close();

    if (!$session_id || !$class_id || !$academic_year_id || !$grading_period_id) {
        $errors[] = "All filters (Session, Class, Academic Year) must be selected and active grading calendar must exist.";
    } else {
        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("
                SELECT id, student_id, course_id, cw1, cw2, mid, exam, final_score, grade_letter, remarks
                FROM grades
                WHERE session_id = ? AND class_id = ? AND academic_year_id = ? AND grading_period_id = ?
            ");
            $stmt->bind_param('iiii', $session_id, $class_id, $academic_year_id, $grading_period_id);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 0) {
                $errors[] = "No grades found to approve for the selected filters.";
            } else {
                $approved_count = 0;
                while ($row = $result->fetch_assoc()) {
                    $checkStmt = $conn->prepare("SELECT id FROM approved_grades WHERE grade_id = ?");
                    $checkStmt->bind_param('i', $row['id']);
                    $checkStmt->execute();
                    $checkStmt->store_result();

                    $uploaded_by = $_SESSION['user']['id'] ?? null;
                    $now = date('Y-m-d H:i:s');

                    if ($checkStmt->num_rows > 0) {
                        $checkStmt->bind_result($approved_id);
                        $checkStmt->fetch();

                        $updateStmt = $conn->prepare("
                            UPDATE approved_grades SET cw1 = ?, cw2 = ?, mid = ?, exam = ?, final_score = ?, 
                            grade_letter = ?, remarks = ?, updated_at = ?, approved_by = ?
                            WHERE id = ?
                        ");
                        $updateStmt->bind_param(
                            'iiiissssii',
                            $row['cw1'], $row['cw2'], $row['mid'], $row['exam'], $row['final_score'],
                            $row['grade_letter'], $row['remarks'], $now, $uploaded_by, $approved_id
                        );
                        $updateStmt->execute();
                        $updateStmt->close();

                        $checkStmt->close();
                    } else {
                        $checkStmt->close();

                        $insertStmt = $conn->prepare("
                            INSERT INTO approved_grades 
                            (grade_id, student_id, course_id, session_id, class_id, academic_year_id, grading_period_id, cw1, cw2, mid, exam, final_score, grade_letter, remarks, approved_by, approved_at)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                        ");
                        $insertStmt->bind_param(
                            'iiiiiiiiiissssss',
                            $row['id'], $row['student_id'], $row['course_id'], $session_id, $class_id, $academic_year_id, $grading_period_id,
                            $row['cw1'], $row['cw2'], $row['mid'], $row['exam'], $row['final_score'],
                            $row['grade_letter'], $row['remarks'], $uploaded_by, $now
                        );
                        $insertStmt->execute();
                        $insertStmt->close();
                    }
                    $approved_count++;
                }

                // Run promotion logic automatically
                $promotionResults = run_promotion_logic($conn, $session_id, $class_id, $academic_year_id);

                $success = "$approved_count grades approved and finalized successfully.";
                $conn->commit();
            }
            $stmt->close();
        } catch (Exception $e) {
            $conn->rollback();
            $errors[] = "An error occurred: " . $e->getMessage();
        }
    }
}

// Load dropdown options for form
$sessionsRes = $conn->query("SELECT id, session_name FROM sessions WHERE is_active=1 ORDER BY session_name");
$classesRes = $conn->query("SELECT id, class_name FROM classes WHERE is_active=1 ORDER BY class_name");
$academicYearsRes = $conn->query("SELECT id, year_name FROM academic_years ORDER BY year_name DESC");

include 'includes/header.php';
?>

<style>
/* Sidebar styles */
.container-flex {
    display: flex;
    min-height: 100vh;
    background: #f8f9fa;
}
.sidebar {
    width: 200px;
    background-color: #007bff; /* Bootstrap primary blue */
    padding: 15px;
}
.sidebar a {
    display: block;
    color: white;
    padding: 10px;
    text-decoration: none;
    font-weight: 500;
}
.sidebar a:hover, .sidebar a.active {
    background-color: #0056b3;
    color: white !important;
    font-weight: bold;
}
.content {
    flex-grow: 1;
    padding: 20px;
    background: white;
}
</style>
    <main class="content">
        <h3>Batch Grade Approval and Student Promotion</h3>

        <?php if ($errors): ?>
            <div class="alert alert-danger">
                <ul>
                    <?php foreach ($errors as $e) echo "<li>" . htmlspecialchars($e) . "</li>"; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <?php if (!empty($promotionResults) && !isset($promotionResults['error'])): ?>
            <div class="card mb-4">
                <div class="card-header">Promotion Summary</div>
                <div class="card-body" style="max-height: 300px; overflow-y: auto;">
                    <table class="table table-sm table-bordered mb-0">
                        <thead>
                        <tr>
                            <th>Student ID</th>
                            <th>Old Class</th>
                            <th>New Class</th>
                            <th>Status</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($promotionResults as $promo): ?>
                            <tr>
                                <td><?= htmlspecialchars($promo['student_id']) ?></td>
                                <td><?= htmlspecialchars($promo['old_class_id']) ?></td>
                                <td><?= htmlspecialchars($promo['new_class_id']) ?></td>
                                <td><?= htmlspecialchars($promo['status']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php elseif (isset($promotionResults['error'])): ?>
            <div class="alert alert-warning"><?= htmlspecialchars($promotionResults['error']) ?></div>
        <?php endif; ?>

        <form method="POST" class="card p-4">
            <div class="row g-3">
                <div class="col-md-4">
                    <label for="academic_year_id" class="form-label">Academic Year</label>
                    <select name="academic_year_id" id="academic_year_id" class="form-select" required>
                        <option value="">Select Academic Year</option>
                        <?php
                        // Reset pointer before fetching again (in case of multiple uses)
                        $academicYearsRes->data_seek(0);
                        while ($row = $academicYearsRes->fetch_assoc()): ?>
                            <option value="<?= $row['id'] ?>" <?= selected($_POST['academic_year_id'] ?? '', $row['id']) ?>>
                                <?= htmlspecialchars($row['year_name']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="session_id" class="form-label">Session</label>
                    <select name="session_id" id="session_id" class="form-select" required>
                        <option value="">Select Session</option>
                        <?php
                        $sessionsRes->data_seek(0);
                        while ($row = $sessionsRes->fetch_assoc()): ?>
                            <option value="<?= $row['id'] ?>" <?= selected($_POST['session_id'] ?? '', $row['id']) ?>>
                                <?= htmlspecialchars($row['session_name']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="class_id" class="form-label">Class</label>
                    <select name="class_id" id="class_id" class="form-select" required>
                        <option value="">Select Class</option>
                        <?php
                        $classesRes->data_seek(0);
                        while ($row = $classesRes->fetch_assoc()): ?>
                            <option value="<?= $row['id'] ?>" <?= selected($_POST['class_id'] ?? '', $row['id']) ?>>
                                <?= htmlspecialchars($row['class_name']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-12 mt-3">
                    <button type="submit" name="approve_grades" class="btn btn-success">
                        <i class="fas fa-check-circle"></i> Batch Approve Grades & Promote
                    </button>
                </div>
            </div>
        </form>
    </main>
</div>

<?php include 'includes/footer.php'; ?>
