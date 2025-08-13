<?php
session_start();
include 'config/db.php';
include 'includes/header.php';

// AUTH check
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['role'], ['superuser', 'registrar', 'vicechancellor', 'academic'])) {
    die("Unauthorized access.");
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $staff_id = intval($_POST['staff_id']);
    $check_in = $_POST['check_in_time'];
    $check_out = $_POST['check_out_time'];

    if ($staff_id && $check_in && $check_out) {
        $stmt = $conn->prepare("INSERT INTO staff_attendance (staff_id, check_in_time, check_out_time) VALUES (?, ?, ?)");
        $stmt->bind_param("iss", $staff_id, $check_in, $check_out);
        if ($stmt->execute()) {
            $message = "Attendance recorded successfully.";
        } else {
            $message = "Failed to record attendance.";
        }
        $stmt->close();
    } else {
        $message = "All fields are required.";
    }
}

// Pagination
$limit = 10;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $limit;

// Fetch Staff for selection
$staffRes = $conn->query("SELECT id, full_name, position, department FROM staff ORDER BY full_name ASC");

// Fetch Attendance Records
$countRes = $conn->query("SELECT COUNT(*) as total FROM staff_attendance");
$total = $countRes->fetch_assoc()['total'];
$total_pages = ceil($total / $limit);

$attendanceRes = $conn->query("
    SELECT sa.id, s.full_name, s.position, s.department, sa.check_in_time, sa.check_out_time
    FROM staff_attendance sa
    INNER JOIN staff s ON sa.staff_id = s.id
    ORDER BY sa.check_in_time DESC
    LIMIT $limit OFFSET $offset
");
?>

<div class="container my-4">
    <h2>Staff Attendance Recording</h2>

    <?php if (isset($message)): ?>
        <div class="alert alert-info"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <form method="post" class="row g-3 mb-4">
        <div class="col-md-4">
            <label for="staff_id" class="form-label">Select Staff</label>
            <select name="staff_id" id="staff_id" class="form-select" required>
                <option value="">-- Select Staff --</option>
                <?php while ($staff = $staffRes->fetch_assoc()): ?>
                    <option value="<?= $staff['id'] ?>">
                        <?= htmlspecialchars($staff['full_name']) ?> (<?= htmlspecialchars($staff['position']) ?>)
                    </option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label for="check_in_time" class="form-label">Check-in Time</label>
            <input type="datetime-local" name="check_in_time" id="check_in_time" class="form-control" required>
        </div>
        <div class="col-md-3">
            <label for="check_out_time" class="form-label">Check-out Time</label>
            <input type="datetime-local" name="check_out_time" id="check_out_time" class="form-control" required>
        </div>
        <div class="col-md-2 d-flex align-items-end">
            <button type="submit" class="btn btn-primary w-100">Record</button>
        </div>
    </form>

    <h4>Attendance Records</h4>
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
            </tr>
        </thead>
        <tbody>
            <?php
            $sn = $offset + 1;
            if ($attendanceRes->num_rows > 0):
                while ($row = $attendanceRes->fetch_assoc()):
                    $check_in = new DateTime($row['check_in_time']);
                    $check_out = new DateTime($row['check_out_time']);
                    $interval = $check_in->diff($check_out);
                    $hours = $interval->h + ($interval->i / 60);

                    // Deduct 1 hour lunch if applicable (between 12:00 - 13:00)
                    $lunch_start = (clone $check_in)->setTime(12, 0);
                    $lunch_end = (clone $check_in)->setTime(13, 0);
                    if ($check_in < $lunch_end && $check_out > $lunch_start) {
                        $hours = max(0, $hours - 1);
                    }
            ?>
                <tr>
                    <td><?= $sn++ ?></td>
                    <td><?= htmlspecialchars($row['full_name']) ?></td>
                    <td><?= htmlspecialchars($row['position']) ?></td>
                    <td><?= htmlspecialchars($row['department']) ?></td>
                    <td><?= htmlspecialchars($row['check_in_time']) ?></td>
                    <td><?= htmlspecialchars($row['check_out_time']) ?></td>
                    <td><?= number_format($hours, 2) ?> hrs</td>
                </tr>
            <?php endwhile; else: ?>
                <tr><td colspan="7" class="text-center">No attendance records found.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- Pagination -->
    <nav>
        <ul class="pagination">
            <?php for ($p = 1; $p <= $total_pages; $p++): ?>
                <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                    <a class="page-link" href="?page=<?= $p ?>"><?= $p ?></a>
                </li>
            <?php endfor; ?>
        </ul>
    </nav>
</div>

<?php
include 'includes/footer.php';
?>
