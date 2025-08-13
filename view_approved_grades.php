<?php
session_start();
include 'config/db.php';
include 'includes/header.php';

// Access control
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['role'], ['superuser', 'admin', 'lecturer'])) {
    header('Location: login.php');
    exit;
}

// Helper: log audit trail
function logAuditTrail($conn, $user, $role, $activity) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown IP';
    $agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown Agent';

    $stmt = $conn->prepare("INSERT INTO audit_trail (user_name, role, activity, created_at, ip_address, user_agent) VALUES (?, ?, ?, NOW(), ?, ?)");
    $stmt->bind_param("sssss", $user, $role, $activity, $ip, $agent);
    $stmt->execute();
    $stmt->close();
}

// Handle final score update (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['grade_id'], $_POST['final_score'])) {
    $gradeId = intval($_POST['grade_id']);
    $newFinalScore = floatval($_POST['final_score']);

    // Fetch related approved grade + grading calendar open status
    $sqlCheck = "
        SELECT ag.final_score, gc.start_date, gc.end_date
        FROM approved_grades ag
        JOIN grading_calendar gc ON ag.grading_period_id = gc.id
        WHERE ag.id = ?
    ";
    $stmtCheck = $conn->prepare($sqlCheck);
    $stmtCheck->bind_param("i", $gradeId);
    $stmtCheck->execute();
    $resultCheck = $stmtCheck->get_result();
    $row = $resultCheck->fetch_assoc();
    $stmtCheck->close();

    if (!$row) {
        $_SESSION['error'] = "Approved grade not found.";
    } else {
        $oldFinalScore = floatval($row['final_score']);
        $now = new DateTime();
        $start = new DateTime($row['start_date']);
        $end = new DateTime($row['end_date']);

        // Check if grading calendar is open now
        if ($now >= $start && $now <= $end) {
            if ($newFinalScore != $oldFinalScore) {
                // Update approved_grades final_score only
                $stmtUpdate = $conn->prepare("UPDATE approved_grades SET final_score = ?, updated_at = NOW() WHERE id = ?");
                $stmtUpdate->bind_param("di", $newFinalScore, $gradeId);
                if ($stmtUpdate->execute()) {
                    $stmtUpdate->close();

                    // Log audit trail
                    $user = $_SESSION['user']['username'] ?? 'Unknown';
                    $role = $_SESSION['user']['role'] ?? 'Unknown';
                    $activity = "User $user ($role) updated final score for approved grade ID $gradeId from $oldFinalScore to $newFinalScore";
                    logAuditTrail($conn, $user, $role, $activity);

                    $_SESSION['success'] = "Final score updated successfully.";
                } else {
                    $_SESSION['error'] = "Failed to update final score.";
                }
            } else {
                $_SESSION['error'] = "Final score unchanged.";
            }
        } else {
            $_SESSION['error'] = "Grading period is not open. Cannot update final score.";
        }
    }

    header("Location: view_approved_grades.php?" . $_SERVER['QUERY_STRING']);
    exit;
}

// Filters
$filters = [
    'academic_year_id' => $_GET['academic_year_id'] ?? '',
    'session_id' => $_GET['session_id'] ?? '',
    'course_id' => $_GET['course_id'] ?? '',
    'class_id' => $_GET['class_id'] ?? '',
];

// Pagination
$limit = 10;
$page = (isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0) ? intval($_GET['page']) : 1;
$offset = ($page - 1) * $limit;

// Fetch dropdown data for filters
$courses = $conn->query("SELECT id, course_name FROM courses ORDER BY course_name");
$sessions = $conn->query("SELECT id, session_name FROM sessions ORDER BY session_name DESC");
$classes = $conn->query("SELECT id, class_name FROM classes ORDER BY class_name");
$academic_years = $conn->query("SELECT id, year_name FROM academic_years ORDER BY year_name DESC");

// Build WHERE clause and params dynamically
$whereClauses = [];
$params = [];
$types = '';

foreach ($filters as $key => $value) {
    if ($value !== '') {
        $whereClauses[] = "ag.$key = ?";
        $params[] = $value;
        $types .= 'i';
    }
}

$whereSQL = '';
if ($whereClauses) {
    $whereSQL = "WHERE " . implode(' AND ', $whereClauses);
}

// Count total records
$countSql = "SELECT COUNT(*) FROM approved_grades ag $whereSQL";
$countStmt = $conn->prepare($countSql);
if ($params) {
    $countStmt->bind_param($types, ...$params);
}
$countStmt->execute();
$countStmt->bind_result($totalRecords);
$countStmt->fetch();
$countStmt->close();

$totalPages = ceil($totalRecords / $limit);

// Fetch paginated approved grades with joined info
$sql = "
    SELECT 
        ag.id as approved_grade_id,
        s.full_name,
        s.registration_number,
        c.course_name,
        cl.class_name,
        ay.year_name,
        ag.final_score,
        ag.grade_letter,
        ag.remarks,
        gc.start_date,
        gc.end_date
    FROM approved_grades ag
    JOIN students s ON ag.student_id = s.id
    JOIN courses c ON ag.course_id = c.id
    JOIN classes cl ON ag.class_id = cl.id
    JOIN academic_years ay ON ag.academic_year_id = ay.id
    JOIN grading_calendar gc ON ag.grading_period_id = gc.id
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

<h2>View Approved Grades</h2>

<?php if (isset($_SESSION['success'])): ?>
    <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
<?php endif; ?>
<?php if (isset($_SESSION['error'])): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
<?php endif; ?>

<form method="GET" class="mb-4">
    <div class="row g-3">
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

        <div class="col-md-2 d-flex align-items-end">
            <button type="submit" class="btn btn-primary w-100">Filter</button>
        </div>
    </div>
</form>

<?php if ($grades): ?>
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
                    <th>Final Score</th>
                    <th>Grade Letter</th>
                    <th>Remarks</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $sn = $offset + 1;
                $now = new DateTime();
                foreach ($grades as $grade):
                    $calendarStart = new DateTime($grade['start_date']);
                    $calendarEnd = new DateTime($grade['end_date']);
                    $isCalendarOpen = ($now >= $calendarStart && $now <= $calendarEnd);
                ?>
                <tr>
                    <td><?= $sn++ ?></td>
                    <td><?= htmlspecialchars($grade['full_name']) ?></td>
                    <td><?= htmlspecialchars($grade['registration_number']) ?></td>
                    <td><?= htmlspecialchars($grade['course_name']) ?></td>
                    <td><?= htmlspecialchars($grade['class_name']) ?></td>
                    <td><?= htmlspecialchars($grade['year_name']) ?></td>
                    <td>
                        <?php if ($isCalendarOpen): ?>
                            <form method="POST" class="d-flex gap-2 align-items-center" style="max-width:150px;">
                                <input type="hidden" name="grade_id" value="<?= $grade['approved_grade_id'] ?>">
                                <input type="number" step="0.01" min="0" max="100" name="final_score" value="<?= htmlspecialchars($grade['final_score']) ?>" class="form-control form-control-sm" required>
                                <button type="submit" class="btn btn-sm btn-success" title="Update Final Score">Save</button>
                            </form>
                        <?php else: ?>
                            <?= htmlspecialchars($grade['final_score']) ?>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($grade['grade_letter']) ?></td>
                    <td><?= htmlspecialchars($grade['remarks']) ?></td>
                    <td>
                        <?php if (!$isCalendarOpen): ?>
                            <small class="text-muted">Update disabled (grading period closed)</small>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
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
<?php else: ?>
    <p>No approved grades found matching the selected criteria.</p>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
