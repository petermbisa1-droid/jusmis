<?php
session_start();
include 'config/db.php';
include 'includes/header.php';

// AUTH check
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['role'], ['superuser', 'registrar', 'vicechancellor', 'academic'])) {
    die("Unauthorized access.");
}

// Filters
$from_date = isset($_GET['from_date']) ? $_GET['from_date'] : '';
$to_date = isset($_GET['to_date']) ? $_GET['to_date'] : '';
$staff_id = isset($_GET['staff_id']) ? $_GET['staff_id'] : '';

// Pagination setup
$limit = 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? intval($_GET['page']) : 1;
$offset = ($page - 1) * $limit;

// Count total entries
$count_query = "SELECT COUNT(*) FROM staff_attendance sa INNER JOIN staff s ON sa.staff_id = s.id WHERE 1 ";
$params = [];
$types = "";

if ($from_date && $to_date) {
    $count_query .= " AND DATE(sa.check_in_time) BETWEEN ? AND ? ";
    $types .= "ss";
    $params[] = $from_date;
    $params[] = $to_date;
} elseif ($from_date) {
    $count_query .= " AND DATE(sa.check_in_time) >= ? ";
    $types .= "s";
    $params[] = $from_date;
} elseif ($to_date) {
    $count_query .= " AND DATE(sa.check_in_time) <= ? ";
    $types .= "s";
    $params[] = $to_date;
}

if ($staff_id) {
    $count_query .= " AND sa.staff_id = ? ";
    $types .= "i";
    $params[] = $staff_id;
}

$stmt = $conn->prepare($count_query);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$stmt->bind_result($total_records);
$stmt->fetch();
$stmt->close();

$total_pages = ceil($total_records / $limit);

// Fetch Attendance Records
$fetch_query = "
    SELECT sa.id, s.full_name, s.position, s.department, sa.check_in_time, sa.check_out_time
    FROM staff_attendance sa
    INNER JOIN staff s ON sa.staff_id = s.id
    WHERE 1
";

$params = [];
$types = "";

if ($from_date && $to_date) {
    $fetch_query .= " AND DATE(sa.check_in_time) BETWEEN ? AND ? ";
    $types .= "ss";
    $params[] = $from_date;
    $params[] = $to_date;
} elseif ($from_date) {
    $fetch_query .= " AND DATE(sa.check_in_time) >= ? ";
    $types .= "s";
    $params[] = $from_date;
} elseif ($to_date) {
    $fetch_query .= " AND DATE(sa.check_in_time) <= ? ";
    $types .= "s";
    $params[] = $to_date;
}

if ($staff_id) {
    $fetch_query .= " AND sa.staff_id = ? ";
    $types .= "i";
    $params[] = $staff_id;
}

$fetch_query .= " ORDER BY sa.check_in_time DESC LIMIT ? OFFSET ? ";
$types .= "ii";
$params[] = $limit;
$params[] = $offset;

$stmt = $conn->prepare($fetch_query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

// Fetch staff list for filter dropdown
$staff_list = $conn->query("SELECT id, full_name FROM staff ORDER BY full_name ASC");
?>

<div class="container mt-4">
    <h2 class="mb-4">Staff Attendance Report</h2>

    <form method="get" class="row g-3 mb-4">
        <div class="col-md-3">
            <label for="from_date" class="form-label">From Date</label>
            <input type="date" id="from_date" name="from_date" class="form-control" value="<?= htmlspecialchars($from_date) ?>">
        </div>
        <div class="col-md-3">
            <label for="to_date" class="form-label">To Date</label>
            <input type="date" id="to_date" name="to_date" class="form-control" value="<?= htmlspecialchars($to_date) ?>">
        </div>
        <div class="col-md-3">
            <label for="staff_id" class="form-label">Staff</label>
            <select id="staff_id" name="staff_id" class="form-select">
                <option value="">All Staff</option>
                <?php while ($staff = $staff_list->fetch_assoc()): ?>
                    <option value="<?= $staff['id'] ?>" <?= $staff['id'] == $staff_id ? 'selected' : '' ?>>
                        <?= htmlspecialchars($staff['full_name']) ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="col-md-3 d-flex align-items-end">
            <button type="submit" class="btn btn-primary me-2">Filter</button>
            <a href="staff_attendance_report.php" class="btn btn-secondary">Reset</a>
        </div>
    </form>

    <div class="mb-3">
        <a href="export_attendance_excel.php?from_date=<?= urlencode($from_date) ?>&to_date=<?= urlencode($to_date) ?>&staff_id=<?= urlencode($staff_id) ?>" class="btn btn-success me-2">Export to Excel</a>
        <a href="export_attendance_pdf.php?from_date=<?= urlencode($from_date) ?>&to_date=<?= urlencode($to_date) ?>&staff_id=<?= urlencode($staff_id) ?>" class="btn btn-danger">Export to PDF</a>
    </div>

    <table class="table table-bordered table-striped bg-white">
        <thead>
            <tr>
                <th>SN</th>
                <th>Full Name</th>
                <th>Position</th>
                <th>Department</th>
                <th>Check-in Time</th>
                <th>Check-out Time</th>
                <th>Hours Worked</th>
                <th>Overtime</th>
                <th>Early In</th>
                <th>Early Out</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($result->num_rows === 0): ?>
                <tr><td colspan="10" class="text-center">No records found.</td></tr>
            <?php else: ?>
                <?php 
                $sn = $offset + 1;
                while ($row = $result->fetch_assoc()):
                    $check_in = new DateTime($row['check_in_time']);
                    $check_out = new DateTime($row['check_out_time']);

                    $work_hours = $check_in->diff($check_out)->h + ($check_in->diff($check_out)->i / 60);
                    // Deduct 1 hour lunch break (between 12:00-13:00) if time is sufficient
                    $lunch_start = new DateTime($check_in->format('Y-m-d') . ' 12:00:00');
                    $lunch_end = new DateTime($check_in->format('Y-m-d') . ' 13:00:00');

                    if ($check_in < $lunch_end && $check_out > $lunch_start) {
                        $work_hours -= 1;
                    }

                    $scheduled_start = new DateTime($check_in->format('Y-m-d') . ' 08:00:00');
                    $scheduled_end = new DateTime($check_in->format('Y-m-d') . ' 17:00:00');

                    $early_in = ($check_in < $scheduled_start) ? $scheduled_start->diff($check_in)->format('%H:%I') : '-';
                    $early_out = ($check_out < $scheduled_end) ? $scheduled_end->diff($check_out)->format('%H:%I') : '-';
                    $overtime = ($check_out > $scheduled_end) ? $check_out->diff($scheduled_end)->format('%H:%I') : '-';
                ?>
                    <tr>
                        <td><?= $sn++ ?></td>
                        <td><?= htmlspecialchars($row['full_name']) ?></td>
                        <td><?= htmlspecialchars($row['position']) ?></td>
                        <td><?= htmlspecialchars($row['department']) ?></td>
                        <td><?= htmlspecialchars($row['check_in_time']) ?></td>
                        <td><?= htmlspecialchars($row['check_out_time']) ?></td>
                        <td><?= number_format($work_hours, 2) ?> hrs</td>
                        <td><?= $overtime ?></td>
                        <td><?= $early_in ?></td>
                        <td><?= $early_out ?></td>
                    </tr>
                <?php endwhile; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- Pagination -->
    <nav>
        <ul class="pagination">
            <?php for ($p = 1; $p <= $total_pages; $p++): ?>
                <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                    <a class="page-link" href="?page=<?= $p ?>&from_date=<?= urlencode($from_date) ?>&to_date=<?= urlencode($to_date) ?>&staff_id=<?= urlencode($staff_id) ?>"><?= $p ?></a>
                </li>
            <?php endfor; ?>
        </ul>
    </nav>
</div>

<?php include 'includes/footer.php'; ?>
<?php $conn->close(); ?>
