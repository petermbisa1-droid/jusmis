<?php
session_start();
include 'config/db.php';
include 'includes/header.php';

$errors = [];
$success = '';

$sessions = $conn->query("SELECT id, session_name FROM sessions ORDER BY session_name DESC");
$academic_years = $conn->query("SELECT id, year_name FROM academic_years ORDER BY year_name DESC");

$selected_session = $_POST['session_id'] ?? '';
$selected_year = $_POST['academic_year_id'] ?? '';

$students = [];

function getSessionCode($session_name) {
    if (preg_match('/1/', $session_name)) return '1';
    if (preg_match('/2/', $session_name)) return '2';
    return '0';
}

function generateUniqueExamNumber($existing, $prefix) {
    do {
        $random = rand(100, 999);
        $new_exam_number = $prefix . $random;
    } while (in_array($new_exam_number, $existing));
    return $new_exam_number;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (isset($_POST['load_students']) || isset($_POST['generate_exam_numbers'])) {
        if (!$selected_session || !$selected_year) {
            $errors[] = "Please select academic year and session.";
        } else {
            $stmt = $conn->prepare("
                SELECT r.id AS registration_id, s.full_name, s.registration_number, r.exam_number
                FROM registrations r
                JOIN students s ON r.student_id = s.id
                WHERE r.session_id = ? AND r.academic_year_id = ? AND s.status = 'active'
                ORDER BY s.full_name ASC
            ");
            $stmt->bind_param("ii", $selected_session, $selected_year);
            $stmt->execute();
            $result = $stmt->get_result();
            $students = $result->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
        }
    }

    if (isset($_POST['generate_exam_numbers']) && empty($errors) && $students) {
        $year_short = '';
        $stmt = $conn->prepare("SELECT year_name FROM academic_years WHERE id = ?");
        $stmt->bind_param("i", $selected_year);
        $stmt->execute();
        $stmt->bind_result($year_name);
        if ($stmt->fetch()) {
            // Extract last 2 digits of a 4-digit year like 2025 => 25
            if (preg_match('/\b(\d{4})\b/', $year_name, $matches)) {
                $year_full = $matches[1];
                $year_short = substr($year_full, -2);
            } else {
                $year_short = substr($year_name, -2);
            }
        }
        $stmt->close();

        $session_name = '';
        $stmt = $conn->prepare("SELECT session_name FROM sessions WHERE id = ?");
        $stmt->bind_param("i", $selected_session);
        $stmt->execute();
        $stmt->bind_result($session_name);
        $stmt->fetch();
        $stmt->close();

        $session_code = getSessionCode($session_name);

        if (!$year_short || $session_code === '0') {
            $errors[] = "Could not determine academic year short code or session code.";
        } else {
            $prefix = "JUEX{$year_short}{$session_code}";

            $stmt = $conn->prepare("
                SELECT exam_number FROM registrations
                WHERE academic_year_id = ? AND session_id = ? AND exam_number LIKE CONCAT(?, '%')
            ");
            $stmt->bind_param("iis", $selected_year, $selected_session, $prefix);
            $stmt->execute();
            $result = $stmt->get_result();
            $existing_exam_numbers = array_column($result->fetch_all(MYSQLI_ASSOC), 'exam_number');
            $stmt->close();

            $updateStmt = $conn->prepare("UPDATE registrations SET exam_number = ? WHERE id = ?");

            foreach ($students as $index => $student) {
                if (empty($student['exam_number'])) {
                    $new_exam_num = generateUniqueExamNumber($existing_exam_numbers, $prefix);
                    $existing_exam_numbers[] = $new_exam_num;

                    $updateStmt->bind_param("si", $new_exam_num, $student['registration_id']);
                    $updateStmt->execute();

                    $students[$index]['exam_number'] = $new_exam_num;
                }
            }
            $updateStmt->close();
            $success = "Exam numbers generated successfully for students without exam numbers.";
        }
    }
}
?>

<h2>Bulk Generate Exam Numbers by Academic Year & Session</h2>

<?php if ($errors): ?>
    <div class="alert alert-danger">
        <ul><?php foreach ($errors as $e) echo "<li>" . htmlspecialchars($e ?? '') . "</li>"; ?></ul>
    </div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success ?? '') ?></div>
<?php endif; ?>

<form method="POST" class="mb-4">
    <div class="row g-3">
        <div class="col-md-6">
            <label>Academic Year</label>
            <select name="academic_year_id" class="form-select" required>
                <option value="">Select Academic Year</option>
                <?php while ($row = $academic_years->fetch_assoc()): ?>
                    <option value="<?= $row['id'] ?>" <?= ($selected_year == $row['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($row['year_name'] ?? '') ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>

        <div class="col-md-6">
            <label>Session</label>
            <select name="session_id" class="form-select" required>
                <option value="">Select Session</option>
                <?php while ($row = $sessions->fetch_assoc()): ?>
                    <option value="<?= $row['id'] ?>" <?= ($selected_session == $row['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($row['session_name'] ?? '') ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>
    </div>

    <div class="mt-3 d-flex gap-2">
        <button type="submit" name="load_students" class="btn btn-primary">Load Registered Students</button>
        <button type="submit" name="generate_exam_numbers" class="btn btn-success">Generate Exam Numbers</button>
    </div>
</form>

<?php if ($students): ?>
    <table class="table table-bordered table-striped mt-4">
        <thead>
            <tr>
                <th>SN</th>
                <th>Student Name</th>
                <th>Registration Number</th>
                <th>Exam Number</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($students as $idx => $stu): ?>
                <tr>
                    <td><?= $idx + 1 ?></td>
                    <td><?= htmlspecialchars($stu['full_name'] ?? '') ?></td>
                    <td><?= htmlspecialchars($stu['registration_number'] ?? '') ?></td>
                    <td><?= htmlspecialchars($stu['exam_number'] ?? '') ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php elseif (isset($_POST['load_students'])): ?>
    <p class="alert alert-info mt-4">No registered students found for the selected filters.</p>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
