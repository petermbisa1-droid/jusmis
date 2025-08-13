<?php
session_start();
include 'config/db.php';
include 'includes/header.php';

// Access control
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['role'], ['superuser', 'admin', 'lecturer'])) {
    header('Location: login.php');
    exit;
}

// Filters
$filters = [
    'course_id' => $_GET['course_id'] ?? '',
    'session_id' => $_GET['session_id'] ?? '',
    'class_id' => $_GET['class_id'] ?? '',
    'academic_year_id' => $_GET['academic_year_id'] ?? '',
    'grading_period_id' => $_GET['grading_period_id'] ?? '',
];

// Pagination setup
$limit = 10;
$page = (isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0) ? intval($_GET['page']) : 1;
$offset = ($page - 1) * $limit;

// Fetch dropdown data
$courses = $conn->query("SELECT id, course_name FROM courses ORDER BY course_name");
$sessions = $conn->query("SELECT id, session_name FROM sessions ORDER BY session_name DESC");
$classes = $conn->query("SELECT id, class_name FROM classes ORDER BY class_name");
$academic_years = $conn->query("SELECT id, year_name FROM academic_years ORDER BY year_name DESC");
$grading_calendars = $conn->query("SELECT id, grading_period_name FROM grading_calendar ORDER BY start_date DESC");

// Prepare data variables
$grades = [];
$totalRecords = 0;

// Build WHERE clause dynamically if filters applied
$whereClauses = [];
$params = [];
$types = '';

foreach ($filters as $key => $value) {
    if ($value !== '') {
        $whereClauses[] = "g.$key = ?";
        $params[] = $value;
        $types .= 'i';
    }
}

$whereSQL = ($whereClauses) ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

// Count total records for pagination
$countSql = "SELECT COUNT(*) FROM grades g $whereSQL";
$countStmt = $conn->prepare($countSql);
if ($params) {
    $countStmt->bind_param($types, ...$params);
}
$countStmt->execute();
$countStmt->bind_result($totalRecords);
$countStmt->fetch();
$countStmt->close();

// Calculate total pages with a minimum of 1 page
$totalPages = ($totalRecords > 0) ? ceil($totalRecords / $limit) : 1;

// Adjust current page if it exceeds total pages
if ($page > $totalPages) {
    $page = $totalPages;
    $offset = ($page - 1) * $limit;
}

// Fetch grades with related info and approval audit logs
$sql = "
    SELECT
        g.id as grade_id,
        s.full_name,
        s.registration_number,
        c.course_name,
        cl.class_name,
        ay.year_name,
        g.cw1, g.cw2, g.mid, g.exam, g.final_score, g.grade_letter, g.remarks,
        u.username AS uploaded_by,
        ag.id AS approved_id,
        approver.username AS approved_by,
        ag.approved_at
    FROM grades g
    JOIN students s ON g.student_id = s.id
    JOIN courses c ON g.course_id = c.id
    JOIN classes cl ON g.class_id = cl.id
    JOIN academic_years ay ON g.academic_year_id = ay.id
    LEFT JOIN users u ON g.uploaded_by = u.id
    LEFT JOIN approved_grades ag ON g.id = ag.grade_id
    LEFT JOIN users approver ON ag.approved_by = approver.id
    $whereSQL
    ORDER BY s.full_name ASC
    LIMIT ? OFFSET ?
";

$stmt = $conn->prepare($sql);

if ($params) {
    $types_with_limit = $types . 'ii';
    $params_with_limit = array_merge($params, [$limit, $offset]);
    $stmt->bind_param($types_with_limit, ...$params_with_limit);
} else {
    $stmt->bind_param('ii', $limit, $offset);
}

$stmt->execute();
$result = $stmt->get_result();
$grades = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

function buildQueryString($exclude = []) {
    $qs = [];
    foreach ($_GET as $key => $value) {
        if (!in_array($key, $exclude) && $value !== '') {
            $qs[] = urlencode($key) . '=' . urlencode($value);
        }
    }
    return implode('&', $qs);
}
?>

<h2>View Grades</h2>

<form method="GET" class="mb-4">
    <div class="row g-3">
        <div class="col-md-2">
            <label>Course</label>
            <select name="course_id" class="form-select">
                <option value="">All Courses</option>
                <?php while ($row = $courses->fetch_assoc()): ?>
                    <option value="<?= $row['id'] ?>" <?= ($filters['course_id'] == $row['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($row['course_name']) ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>

        <div class="col-md-2">
            <label>Session</label>
            <select name="session_id" class="form-select">
                <option value="">All Sessions</option>
                <?php while ($row = $sessions->fetch_assoc()): ?>
                    <option value="<?= $row['id'] ?>" <?= ($filters['session_id'] == $row['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($row['session_name']) ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>

        <div class="col-md-2">
            <label>Class</label>
            <select name="class_id" class="form-select">
                <option value="">All Classes</option>
                <?php while ($row = $classes->fetch_assoc()): ?>
                    <option value="<?= $row['id'] ?>" <?= ($filters['class_id'] == $row['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($row['class_name']) ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>

        <div class="col-md-2">
            <label>Academic Year</label>
            <select name="academic_year_id" class="form-select">
                <option value="">All Years</option>
                <?php while ($row = $academic_years->fetch_assoc()): ?>
                    <option value="<?= $row['id'] ?>" <?= ($filters['academic_year_id'] == $row['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($row['year_name']) ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>

        <div class="col-md-2">
            <label>Grading Calendar</label>
            <select name="grading_period_id" class="form-select">
                <option value="">All Calendars</option>
                <?php while ($row = $grading_calendars->fetch_assoc()): ?>
                    <option value="<?= $row['id'] ?>" <?= ($filters['grading_period_id'] == $row['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($row['grading_period_name']) ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>

        <div class="col-md-2 d-flex align-items-end">
            <button type="submit" class="btn btn-primary w-100">Filter</button>
        </div>
    </div>
</form>

<?php if (!empty($grades)): ?>
    <div class="table-responsive">
        <table class="table table-striped table-bordered align-middle">
            <thead>
                <tr>
                    <th>SN</th>
                    <th>Student Name</th>
                    <th>Registration No.</th>
                    <th>Course</th>
                    <th>Class</th>
                    <th>Academic Year</th>
                    <th>CW1</th>
                    <th>CW2</th>
                    <th>Mid</th>
                    <th>Exam</th>
                    <th>Final Score</th>
                    <th>Grade Letter</th>
                    <th>Remarks</th>
                    <th>Status</th>
                    <th>Audit Log</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php $sn = $offset + 1; foreach ($grades as $grade): ?>
                    <tr>
                        <td><?= $sn++ ?></td>
                        <td><?= htmlspecialchars($grade['full_name']) ?></td>
                        <td><?= htmlspecialchars($grade['registration_number']) ?></td>
                        <td><?= htmlspecialchars($grade['course_name']) ?></td>
                        <td><?= htmlspecialchars($grade['class_name']) ?></td>
                        <td><?= htmlspecialchars($grade['year_name']) ?></td>
                        <td><?= htmlspecialchars($grade['cw1']) ?></td>
                        <td><?= htmlspecialchars($grade['cw2']) ?></td>
                        <td><?= htmlspecialchars($grade['mid']) ?></td>
                        <td><?= htmlspecialchars($grade['exam']) ?></td>
                        <td><?= htmlspecialchars($grade['final_score']) ?></td>
                        <td><?= htmlspecialchars($grade['grade_letter']) ?></td>
                        <td><?= htmlspecialchars($grade['remarks']) ?></td>
                        <td>
                            <?php if ($grade['approved_id']): ?>
                                <span class="badge bg-success">Approved</span>
                            <?php else: ?>
                                <span class="badge bg-warning text-dark">Pending</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($grade['approved_id']): ?>
                                By: <?= htmlspecialchars($grade['approved_by']) ?><br>
                                On: <?= htmlspecialchars($grade['approved_at']) ?>
                            <?php else: ?>
                                --
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="edit_grade.php?id=<?= $grade['grade_id'] ?>" class="btn btn-sm btn-primary <?= $grade['approved_id'] ? 'disabled' : '' ?>">Edit</a>
                            <a href="delete_grade.php?id=<?= $grade['grade_id'] ?>" class="btn btn-sm btn-danger <?= $grade['approved_id'] ? 'disabled' : '' ?>" onclick="return confirm('Are you sure?')">Delete</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination Controls -->
    <nav aria-label="Page navigation">
        <ul class="pagination justify-content-center">
            <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                <a class="page-link" href="?<?= buildQueryString(['page']) ?>&page=<?= $page - 1 ?>">Previous</a>
            </li>
            <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                <li class="page-item <?= ($p == $page) ? 'active' : '' ?>">
                    <a class="page-link" href="?<?= buildQueryString(['page']) ?>&page=<?= $p ?>"><?= $p ?></a>
                </li>
            <?php endfor; ?>
            <li class="page-item <?= ($page >= $totalPages) ? 'disabled' : '' ?>">
                <a class="page-link" href="?<?= buildQueryString(['page']) ?>&page=<?= $page + 1 ?>">Next</a>
            </li>
        </ul>
    </nav>
<?php elseif (array_filter($filters)): ?>
    <p>No grades found matching the selected criteria.</p>
<?php else: ?>
    <p>Please select filters and click "Filter" to view grades.</p>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
