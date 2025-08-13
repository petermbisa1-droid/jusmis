<?php
session_start();
include 'config/db.php';
include 'includes/header.php';

if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['role'], ['superuser', 'registrar', 'vicechancellor', 'academic'])) {
    die("Unauthorized access.");
}

// Filters
$academic_year_id = $_GET['academic_year_id'] ?? '';
$session_id = $_GET['session_id'] ?? '';
$course_id = $_GET['course_id'] ?? '';
$filter_type = $_GET['filter_type'] ?? '';
$filter_date = $_GET['filter_date'] ?? '';

// Fetch dropdowns
$academicYears = $conn->query("SELECT id, year_name FROM academic_years ORDER BY year_name DESC");
$sessions = $conn->query("SELECT id, session_name FROM sessions ORDER BY session_name ASC");
$courses = $conn->query("SELECT id, course_code, course_name FROM courses ORDER BY course_code ASC");

// Build SQL
$sql = "
SELECT 
    st.registration_number,
    st.full_name,
    ay.year_name AS academic_year,
    se.session_name,
    c.course_code,
    c.course_name,
    a.attendance_date,
    a.start_time,
    a.end_time,
    a.status
FROM attendance a
JOIN registration_courses rc ON a.registration_course_id = rc.id
JOIN registrations r ON rc.registration_id = r.id
JOIN students st ON r.student_id = st.id
JOIN courses c ON rc.course_id = c.id
JOIN academic_years ay ON r.academic_year_id = ay.id
JOIN sessions se ON r.session_id = se.id
WHERE 1 = 1
";

// Apply filters
if (!empty($academic_year_id)) {
    $sql .= " AND ay.id = " . intval($academic_year_id);
}
if (!empty($session_id)) {
    $sql .= " AND se.id = " . intval($session_id);
}
if (!empty($course_id)) {
    $sql .= " AND c.id = " . intval($course_id);
}
if (!empty($filter_date) && !empty($filter_type)) {
    switch ($filter_type) {
        case 'day':
            $sql .= " AND a.attendance_date = '" . $conn->real_escape_string($filter_date) . "'";
            break;
        case 'week':
            $sql .= " AND YEARWEEK(a.attendance_date, 1) = YEARWEEK('" . $conn->real_escape_string($filter_date) . "', 1)";
            break;
        case 'month':
            $sql .= " AND MONTH(a.attendance_date) = MONTH('" . $conn->real_escape_string($filter_date) . "')
                      AND YEAR(a.attendance_date) = YEAR('" . $conn->real_escape_string($filter_date) . "')";
            break;
    }
}

$sql .= " ORDER BY a.attendance_date DESC, a.start_time DESC";
$result = $conn->query($sql);
?>

<div class="container" style="padding: 20px;">
    <h2>📘 Student Attendance Monitoring</h2>

    <form method="get" style="margin-bottom: 20px;">
        <!-- Academic Year -->
        <label for="academic_year_id">Academic Year:</label>
        <select name="academic_year_id" id="academic_year_id">
            <option value="">-- All --</option>
            <?php while ($ay = $academicYears->fetch_assoc()): ?>
                <option value="<?= $ay['id'] ?>" <?= $ay['id'] == $academic_year_id ? 'selected' : '' ?>>
                    <?= htmlspecialchars($ay['year_name']) ?>
                </option>
            <?php endwhile; ?>
        </select>

        <!-- Session -->
        <label for="session_id" style="margin-left: 20px;">Session:</label>
        <select name="session_id" id="session_id">
            <option value="">-- All --</option>
            <?php while ($se = $sessions->fetch_assoc()): ?>
                <option value="<?= $se['id'] ?>" <?= $se['id'] == $session_id ? 'selected' : '' ?>>
                    <?= htmlspecialchars($se['session_name']) ?>
                </option>
            <?php endwhile; ?>
        </select>

        <!-- Course -->
        <label for="course_id" style="margin-left: 20px;">Course:</label>
        <select name="course_id" id="course_id">
            <option value="">-- All --</option>
            <?php while ($co = $courses->fetch_assoc()): ?>
                <option value="<?= $co['id'] ?>" <?= $co['id'] == $course_id ? 'selected' : '' ?>>
                    <?= htmlspecialchars($co['course_code']) ?> - <?= htmlspecialchars($co['course_name']) ?>
                </option>
            <?php endwhile; ?>
        </select>

        <!-- Date Filter -->
        <label for="filter_type" style="margin-left: 20px;">By:</label>
        <select name="filter_type" id="filter_type">
            <option value="">-- Date Type --</option>
            <option value="day" <?= $filter_type == 'day' ? 'selected' : '' ?>>Day</option>
            <option value="week" <?= $filter_type == 'week' ? 'selected' : '' ?>>Week</option>
            <option value="month" <?= $filter_type == 'month' ? 'selected' : '' ?>>Month</option>
        </select>

        <input type="date" name="filter_date" id="filter_date" value="<?= htmlspecialchars($filter_date) ?>" />

        <button type="submit" style="margin-left: 20px;">Filter</button>
        <a href="export_student_attendance_pdf.php?academic_year_id=<?= $academic_year_id ?>&session_id=<?= $session_id ?>&course_id=<?= $course_id ?>&filter_type=<?= $filter_type ?>&filter_date=<?= $filter_date ?>" target="_blank" style="margin-left: 20px;">
            <button type="button">Export PDF</button>
        </a>
    </form>

    <table border="1" cellpadding="8" cellspacing="0" width="100%" style="color: black;">
        <thead style="background-color: #f0f0f0;">
            <tr>
                <th>Reg. Number</th>
                <th>Full Name</th>
                <th>Academic Year</th>
                <th>Session</th>
                <th>Course Code</th>
                <th>Course Name</th>
                <th>Date</th>
                <th>Start Time</th>
                <th>End Time</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
        <?php if ($result && $result->num_rows > 0): ?>
            <?php while ($row = $result->fetch_assoc()): ?>
                <tr style="background-color: <?= $row['status'] == 'present' ? '#d4edda' : '#f8d7da' ?>;">
                    <td><?= htmlspecialchars($row['registration_number']) ?></td>
                    <td><?= htmlspecialchars($row['full_name']) ?></td>
                    <td><?= htmlspecialchars($row['academic_year']) ?></td>
                    <td><?= htmlspecialchars($row['session_name']) ?></td>
                    <td><?= htmlspecialchars($row['course_code']) ?></td>
                    <td><?= htmlspecialchars($row['course_name']) ?></td>
                    <td><?= htmlspecialchars($row['attendance_date']) ?></td>
                    <td><?= htmlspecialchars($row['start_time']) ?></td>
                    <td><?= htmlspecialchars($row['end_time']) ?></td>
                    <td><strong><?= ucfirst($row['status']) ?></strong></td>
                </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr><td colspan="10" style="text-align:center;">No attendance records found.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include 'includes/footer.php'; ?>
