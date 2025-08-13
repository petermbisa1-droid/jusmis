<?php
session_start();
include 'config/db.php';
include 'includes/header.php';

if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['role'], ['superuser', 'registrar', 'vicechancellor', 'academic'])) {
    die("Unauthorized access.");
}

// Fetch filter options
$sessions = $conn->query("SELECT id, session_name FROM sessions WHERE is_active = 1");
$academic_years = $conn->query("SELECT id, year_name FROM academic_years WHERE is_active = 1");
$courses = $conn->query("SELECT id, course_name FROM courses");

// Get filters
$session_id = isset($_GET['session_id']) ? intval($_GET['session_id']) : 0;
$academic_year_id = isset($_GET['academic_year_id']) ? intval($_GET['academic_year_id']) : 0;
$course_id = isset($_GET['course_id']) ? intval($_GET['course_id']) : 0;
?>
<div class="container mt-4">
    <h2>Course List Report</h2>
    <form method="GET" class="row g-3 mb-4">
        <div class="col-md-3">
            <label for="session_id" class="form-label">Session</label>
            <select name="session_id" id="session_id" class="form-select">
                <option value="">-- Select Session --</option>
                <?php while($s = $sessions->fetch_assoc()): ?>
                    <option value="<?= $s['id']; ?>" <?= ($s['id'] == $session_id) ? 'selected' : ''; ?>><?= $s['session_name']; ?></option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label for="academic_year_id" class="form-label">Academic Year</label>
            <select name="academic_year_id" id="academic_year_id" class="form-select">
                <option value="">-- Select Academic Year --</option>
                <?php while($ay = $academic_years->fetch_assoc()): ?>
                    <option value="<?= $ay['id']; ?>" <?= ($ay['id'] == $academic_year_id) ? 'selected' : ''; ?>><?= $ay['year_name']; ?></option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label for="course_id" class="form-label">Course</label>
            <select name="course_id" id="course_id" class="form-select">
                <option value="">-- Select Course --</option>
                <?php while($c = $courses->fetch_assoc()): ?>
                    <option value="<?= $c['id']; ?>" <?= ($c['id'] == $course_id) ? 'selected' : ''; ?>><?= $c['course_name']; ?></option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="col-md-3 align-self-end">
            <button type="submit" class="btn btn-primary">Filter</button>
        </div>
    </form>

    <?php if ($session_id && $academic_year_id && $course_id): ?>
        <?php
            // Fetch Course Name
            $course_stmt = $conn->prepare("SELECT course_name FROM courses WHERE id = ?");
            $course_stmt->bind_param('i', $course_id);
            $course_stmt->execute();
            $course_result = $course_stmt->get_result();
            $course_data = $course_result->fetch_assoc();
            $course_name = $course_data ? $course_data['course_name'] : 'Unknown Course';

            // Fetch Students
            $stmt = $conn->prepare("
                SELECT s.full_name, s.registration_number
                FROM registration_courses rc
                INNER JOIN registrations r ON rc.registration_id = r.id
                INNER JOIN students s ON r.student_id = s.id
                WHERE r.session_id = ? AND r.academic_year_id = ? AND rc.course_id = ?
                ORDER BY s.full_name ASC
            ");
            $stmt->bind_param('iii', $session_id, $academic_year_id, $course_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $total_students = $result->num_rows;
        ?>

        <h5>Course: <?= htmlspecialchars($course_name); ?> (<?= $total_students; ?> Students)</h5>

        <?php if ($total_students > 0): ?>
            <table class="table table-bordered mt-3">
                <thead>
                    <tr>
                        <th>SN</th>
                        <th>Full Name</th>
                        <th>Registration Number</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $sn = 1;
                    while($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?= $sn++; ?></td>
                            <td><?= htmlspecialchars($row['full_name']); ?></td>
                            <td><?= htmlspecialchars($row['registration_number']); ?></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>

            <div class="mt-3">
                <a href="export_course_list_pdf.php?session_id=<?= $session_id ?>&academic_year_id=<?= $academic_year_id ?>&course_id=<?= $course_id ?>" class="btn btn-danger">Export to PDF</a>
            </div>

        <?php else: ?>
            <div class="alert alert-warning">No students registered for this course with selected filters.</div>
        <?php endif; ?>
    <?php elseif ($_SERVER['REQUEST_METHOD'] == 'GET'): ?>
        <div class="alert alert-info">Please select Session, Academic Year, and Course to view registered students.</div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
