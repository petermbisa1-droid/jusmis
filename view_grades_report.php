<?php
session_start();
include 'config/db.php';

include 'includes/header.php';

$class_id = $_GET['class_id'] ?? '';
$academic_year_id = $_GET['academic_year_id'] ?? '';
$session_id = $_GET['session_id'] ?? '';

$classesRes = $conn->query("SELECT id, class_name FROM classes WHERE is_active = 1 ORDER BY class_name");
$academicYearsRes = $conn->query("SELECT id, year_name FROM academic_years ORDER BY year_name DESC");
$sessionsRes = $conn->query("SELECT id, session_name FROM sessions WHERE is_active = 1 ORDER BY session_name");

$filters = [];
$params = [];
$types = '';

if ($class_id !== '') {
    $filters[] = 's.class_id = ?';
    $params[] = $class_id;
    $types .= 'i';
}

if ($academic_year_id !== '') {
    $filters[] = 'g.academic_year_id = ?';
    $params[] = $academic_year_id;
    $types .= 'i';
}

if ($session_id !== '') {
    $filters[] = 'g.session_id = ?';
    $params[] = $session_id;
    $types .= 'i';
}

$whereSQL = $filters ? 'WHERE ' . implode(' AND ', $filters) : '';

$showReport = $class_id && $academic_year_id && $session_id;
$students = [];
$reportAvailable = false;
$courses = [];

if ($showReport) {
    $courses_sql = "
        SELECT DISTINCT c.id, c.course_code, c.course_name
        FROM grades g
        JOIN courses c ON g.course_id = c.id
        JOIN students s ON g.student_id = s.id
        $whereSQL
        ORDER BY c.course_code
    ";
    $stmt = $conn->prepare($courses_sql);
    if ($params) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $courses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $grades_sql = "
        SELECT s.id AS student_id, s.full_name, s.registration_number, s.sex,
               p.programme_name, g.course_id, g.cw1, g.exam, g.final_score, g.grade_letter, g.remarks
        FROM grades g
        JOIN students s ON g.student_id = s.id
        JOIN programmes p ON s.programme_id = p.id
        $whereSQL
        ORDER BY s.full_name, g.course_id
    ";
    $stmt = $conn->prepare($grades_sql);
    if ($params) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $grades_result = $stmt->get_result();
    $stmt->close();

    while ($row = $grades_result->fetch_assoc()) {
        $reportAvailable = true;
        $sid = $row['student_id'];
        if (!isset($students[$sid])) {
            $students[$sid] = [
                'full_name' => $row['full_name'],
                'registration_number' => $row['registration_number'],
                'sex' => $row['sex'],
                'programme_name' => $row['programme_name'],
                'grades' => [],
            ];
        }
        $students[$sid]['grades'][$row['course_id']] = $row;
    }
}
?>

<div class="container mt-4">
    <div class="card p-4">
        <h4 class="mb-3">Senate Report Filters</h4>
        <form method="GET">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Class</label>
                    <select name="class_id" class="form-select" required>
                        <option value="">Select Class</option>
                        <?php while ($row = $classesRes->fetch_assoc()): ?>
                            <option value="<?= $row['id'] ?>" <?= $class_id == $row['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($row['class_name']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Academic Year</label>
                    <select name="academic_year_id" class="form-select" required>
                        <option value="">Select Academic Year</option>
                        <?php while ($row = $academicYearsRes->fetch_assoc()): ?>
                            <option value="<?= $row['id'] ?>" <?= $academic_year_id == $row['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($row['year_name']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Session</label>
                    <select name="session_id" class="form-select" required>
                        <option value="">Select Session</option>
                        <?php while ($row = $sessionsRes->fetch_assoc()): ?>
                            <option value="<?= $row['id'] ?>" <?= $session_id == $row['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($row['session_name']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="col-12">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter"></i> Filter Report
                    </button>
                </div>
            </div>
        </form>
    </div>

    <?php if ($showReport && !$reportAvailable): ?>
        <div class="alert alert-warning mt-4 d-flex align-items-center">
            <i class="fas fa-exclamation-circle me-2"></i>
            No report data found for the selected Class, Academic Year, and Session.
        </div>
    <?php endif; ?>

    <?php if ($reportAvailable): ?>
        <div class="d-flex justify-content-between align-items-center mt-4 mb-3">
            <h4>Senate Report Viewer</h4>
            <a href="download_senate_report.php?class_id=<?= $class_id ?>&academic_year_id=<?= $academic_year_id ?>&session_id=<?= $session_id ?>" class="btn btn-success">
                <i class="fas fa-download"></i> Download Report (PDF)
            </a>
        </div>

        <div class="table-responsive">
            <table class="table table-bordered table-striped table-hover">
                <thead class="table-dark">
                    <tr>
                        <th rowspan="2">#</th>
                        <th rowspan="2">Student Name</th>
                        <th rowspan="2">Registration #</th>
                        <th rowspan="2">Gender</th>
                        <?php foreach ($courses as $course): ?>
                            <th colspan="3"> <?= htmlspecialchars($course['course_code']) ?><br><?= htmlspecialchars($course['course_name']) ?></th>
                        <?php endforeach; ?>
                        <th rowspan="2">AVG</th>
                        <th rowspan="2">Remark</th>
                    </tr>
                    <tr>
                        <?php foreach ($courses as $course): ?>
                            <th>CW</th><th>EX</th><th>FG</th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $sn = 1;
                    foreach ($students as $student):
                        $totalFinal = 0;
                        $courseCount = count($courses);
                    ?>
                        <tr>
                            <td><?= $sn++ ?></td>
                            <td><?= htmlspecialchars($student['full_name']) ?></td>
                            <td><?= htmlspecialchars($student['registration_number']) ?></td>
                            <td><?= htmlspecialchars($student['sex']) ?></td>
                            <?php foreach ($courses as $course):
                                $grade = $student['grades'][$course['id']] ?? null;
                                $cw = $grade['cw1'] ?? '-';
                                $exam = $grade['exam'] ?? '-';
                                $fg = $grade['final_score'] ?? '-';
                                if (is_numeric($fg)) $totalFinal += $fg;
                            ?>
                                <td><?= $cw ?></td>
                                <td><?= $exam ?></td>
                                <td><?= $fg ?></td>
                            <?php endforeach; ?>
                            <?php
                                $avg = $courseCount ? round($totalFinal / $courseCount, 2) : 0;
                                $remark = ($avg >= 75) ? 'Distinction' : (($avg >= 65) ? 'Credit' : (($avg >= 45) ? 'Pass' : 'Fail'));
                            ?>
                            <td><?= $avg ?></td>
                            <td><?= $remark ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
