<?php
session_start();
include 'config/db.php';
include 'includes/header.php';

if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['role'], ['superuser', 'registrar', 'vicechancellor', 'academic'])) {
    die("Unauthorized access.");
}

// Fetch dropdown data
$academic_years = $conn->query("SELECT id, year_name FROM academic_years WHERE is_active = 1");
$sessions = $conn->query("SELECT id, session_name FROM sessions WHERE is_active = 1");
$courses = $conn->query("
    SELECT DISTINCT rc.id AS rc_id, c.course_name, c.course_code
    FROM registration_courses rc
    JOIN courses c ON rc.course_id = c.id
");

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['attendance'])) {
    $attendance_date = $_POST['attendance_date'];
    $start_time = $_POST['start_time'];
    $end_time = $_POST['end_time'];

    foreach ($_POST['attendance'] as $rc_id => $data) {
        foreach ($data as $student_id => $status) {
            $stmt = $conn->prepare("
                INSERT INTO attendance (registration_course_id, attendance_date, start_time, end_time, status, recorded_by)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("isssss", $rc_id, $attendance_date, $start_time, $end_time, $status, $_SESSION['role']);
            $stmt->execute();
        }
    }

    echo "<p style='color: green;'>Attendance recorded successfully.</p>";
}
?>

<div class="container" style="padding: 20px;">
    <h2>📝 Record Student Attendance</h2>

    <form method="get">
        <label>Academic Year:</label>
        <select name="academic_year_id" required>
            <option value="">Select Academic Year</option>
            <?php while ($row = $academic_years->fetch_assoc()): ?>
                <option value="<?= $row['id'] ?>" <?= ($_GET['academic_year_id'] ?? '') == $row['id'] ? 'selected' : '' ?>>
                    <?= $row['year_name'] ?>
                </option>
            <?php endwhile; ?>
        </select>

        <label style="margin-left: 20px;">Session:</label>
        <select name="session_id" required>
            <option value="">Select Session</option>
            <?php while ($row = $sessions->fetch_assoc()): ?>
                <option value="<?= $row['id'] ?>" <?= ($_GET['session_id'] ?? '') == $row['id'] ? 'selected' : '' ?>>
                    <?= $row['session_name'] ?>
                </option>
            <?php endwhile; ?>
        </select>

        <label style="margin-left: 20px;">Course:</label>
        <select name="registration_course_id" required>
            <option value="">Select Course</option>
            <?php while ($row = $courses->fetch_assoc()): ?>
                <option value="<?= $row['rc_id'] ?>" <?= ($_GET['registration_course_id'] ?? '') == $row['rc_id'] ? 'selected' : '' ?>>
                    <?= $row['course_code'] ?> - <?= $row['course_name'] ?>
                </option>
            <?php endwhile; ?>
        </select>

        <button type="submit" style="margin-left: 20px;">Load Students</button>
    </form>

    <hr>

<?php
if (isset($_GET['academic_year_id'], $_GET['session_id'], $_GET['registration_course_id'])) {
    $year_id = intval($_GET['academic_year_id']);
    $session_id = intval($_GET['session_id']);
    $rc_id = intval($_GET['registration_course_id']);

    $students = $conn->query("
        SELECT 
            st.id AS student_id,
            st.full_name,
            st.registration_number
        FROM registration_courses rc
        JOIN registrations r ON rc.registration_id = r.id
        JOIN students st ON r.student_id = st.id
        WHERE rc.id = $rc_id
          AND rc.academic_year_id = $year_id
          AND rc.session_id = $session_id
    ");

    if ($students->num_rows > 0):
?>
    <form method="post">
        <input type="hidden" name="academic_year_id" value="<?= $year_id ?>">
        <input type="hidden" name="session_id" value="<?= $session_id ?>">
        <input type="hidden" name="registration_course_id" value="<?= $rc_id ?>">

        <label>Date: <input type="date" name="attendance_date" required></label>
        <label style="margin-left: 20px;">Start Time: <input type="time" name="start_time" required></label>
        <label style="margin-left: 20px;">End Time: <input type="time" name="end_time" required></label>

        <table border="1" cellpadding="8" cellspacing="0" width="100%" style="margin-top: 20px; color: black;">
            <thead style="background-color: #f0f0f0;">
                <tr>
                    <th>Reg. No</th>
                    <th>Full Name</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
            <?php while ($row = $students->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($row['registration_number']) ?></td>
                    <td><?= htmlspecialchars($row['full_name']) ?></td>
                    <td>
                        <select name="attendance[<?= $rc_id ?>][<?= $row['student_id'] ?>]" required>
                            <option value="present">Present</option>
                            <option value="absent">Absent</option>
                        </select>
                    </td>
                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>

        <button type="submit" style="margin-top: 20px;">✅ Submit Attendance</button>
    </form>
<?php
    else:
        echo "<p style='color:red;'>No students found for this course and session.</p>";
    endif;
}
?>

</div>

<?php include 'includes/footer.php'; ?>
