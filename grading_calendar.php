<?php
session_start();
include 'config/db.php';

// Check user permission (example: only admin/superuser can manage)
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['role'], ['superuser', 'admin'])) {
    header('Location: login.php');
    exit;
}

$errors = [];
$success = '';

// Fetch active academic year and session
$activeYearRes = $conn->query("SELECT id, year_name FROM academic_years WHERE is_active = 1 LIMIT 1");
$activeYear = $activeYearRes->fetch_assoc() ?? null;

$activeSessionRes = $conn->query("SELECT id, session_name FROM sessions WHERE is_active = 1 LIMIT 1");
$activeSession = $activeSessionRes->fetch_assoc() ?? null;

// Fetch all academic years and sessions for dropdown
$yearsRes = $conn->query("SELECT id, year_name FROM academic_years ORDER BY year_name DESC");
$sessionsRes = $conn->query("SELECT id, session_name FROM sessions ORDER BY session_name DESC");

// Handle form POST to add/update grading calendar period
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['grading_period_name'] ?? '');
    $academic_year_id = (int)($_POST['academic_year_id'] ?? 0);
    $session_id = (int)($_POST['session_id'] ?? 0);
    $start_date = $_POST['start_date'] ?? '';
    $end_date = $_POST['end_date'] ?? '';
    $description = trim($_POST['description'] ?? '');
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    // Basic validation
    if (!$name || !$academic_year_id || !$session_id || !$start_date || !$end_date) {
        $errors[] = "All fields except description are required.";
    } elseif ($start_date > $end_date) {
        $errors[] = "Start date cannot be later than end date.";
    }

    if (!$errors) {
        // Optionally: Deactivate other active periods for this year+session if this one is active
        if ($is_active) {
            $conn->query("UPDATE grading_calendar SET is_active = 0 WHERE academic_year_id = $academic_year_id AND session_id = $session_id");
        }

        // Insert new grading period
        $stmt = $conn->prepare("INSERT INTO grading_calendar 
            (grading_period_name, academic_year_id, session_id, start_date, end_date, description, is_active, created_at, updated_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
        $stmt->bind_param("siisssi", $name, $academic_year_id, $session_id, $start_date, $end_date, $description, $is_active);

        if ($stmt->execute()) {
            $success = "Grading period added successfully.";
        } else {
            $errors[] = "Database error: " . $stmt->error;
        }
        $stmt->close();
    }
}

// Fetch grading calendar entries with year and session names
$gradingCalendarRes = $conn->query("SELECT gc.*, ay.year_name, s.session_name 
    FROM grading_calendar gc
    JOIN academic_years ay ON gc.academic_year_id = ay.id
    JOIN sessions s ON gc.session_id = s.id
    ORDER BY gc.start_date DESC");

// Check if grade entry pages should be locked now:
// If there is an active grading period for current date, allow grade entry; otherwise lock
$today = date('Y-m-d');
$lockGradeEntry = true;

$activeGradingRes = $conn->prepare("SELECT COUNT(*) FROM grading_calendar WHERE is_active = 1 AND ? BETWEEN start_date AND end_date");
$activeGradingRes->bind_param("s", $today);
$activeGradingRes->execute();
$activeGradingRes->bind_result($countActivePeriods);
$activeGradingRes->fetch();
$activeGradingRes->close();

if ($countActivePeriods > 0) {
    $lockGradeEntry = false; // grade entry allowed
}

include 'includes/header.php';
?>

<h2>Grading Calendar Management</h2>

<?php if ($errors): ?>
    <div class="alert alert-danger">
        <ul><?php foreach ($errors as $e) echo "<li>$e</li>"; ?></ul>
    </div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<?php if ($lockGradeEntry): ?>
    <div class="alert alert-warning">
        Grade entry and uploads are currently <strong>locked</strong> because no active grading period is ongoing.
        Only template downloads are available.
    </div>
<?php else: ?>
    <div class="alert alert-success">
        Grade entry and uploads are <strong>enabled</strong> for the current grading period.
    </div>
<?php endif; ?>

<form method="POST" class="mb-4">
    <div class="mb-3">
        <label for="grading_period_name">Grading Period Name</label>
        <input type="text" id="grading_period_name" name="grading_period_name" class="form-control" required>
    </div>

    <div class="mb-3">
        <label for="academic_year_id">Academic Year</label>
        <select id="academic_year_id" name="academic_year_id" class="form-select" required>
            <option value="">Select Year</option>
            <?php
            $yearsRes->data_seek(0); // rewind result set
            while ($row = $yearsRes->fetch_assoc()): ?>
                <option value="<?= $row['id'] ?>" <?= ($activeYear && $row['id'] == $activeYear['id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($row['year_name']) ?>
                </option>
            <?php endwhile; ?>
        </select>
    </div>

    <div class="mb-3">
        <label for="session_id">Session</label>
        <select id="session_id" name="session_id" class="form-select" required>
            <option value="">Select Session</option>
            <?php
            $sessionsRes->data_seek(0);
            while ($row = $sessionsRes->fetch_assoc()): ?>
                <option value="<?= $row['id'] ?>" <?= ($activeSession && $row['id'] == $activeSession['id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($row['session_name']) ?>
                </option>
            <?php endwhile; ?>
        </select>
    </div>

    <div class="mb-3">
        <label for="start_date">Start Date</label>
        <input type="date" id="start_date" name="start_date" class="form-control" required>
    </div>

    <div class="mb-3">
        <label for="end_date">End Date</label>
        <input type="date" id="end_date" name="end_date" class="form-control" required>
    </div>

    <div class="mb-3">
        <label for="description">Description (optional)</label>
        <textarea id="description" name="description" class="form-control"></textarea>
    </div>

    <div class="form-check mb-3">
        <input type="checkbox" id="is_active" name="is_active" class="form-check-input" checked>
        <label for="is_active" class="form-check-label">Set as Active Grading Period (Only one active period per Year+Session)</label>
    </div>

    <button type="submit" class="btn btn-primary">Add Grading Period</button>
</form>

<h3>Existing Grading Periods</h3>

<table class="table table-striped">
    <thead>
        <tr>
            <th>Name</th>
            <th>Academic Year</th>
            <th>Session</th>
            <th>Start Date</th>
            <th>End Date</th>
            <th>Description</th>
            <th>Active</th>
        </tr>
    </thead>
    <tbody>
        <?php while ($row = $gradingCalendarRes->fetch_assoc()): ?>
            <tr>
                <td><?= htmlspecialchars($row['grading_period_name']) ?></td>
                <td><?= htmlspecialchars($row['year_name']) ?></td>
                <td><?= htmlspecialchars($row['session_name']) ?></td>
                <td><?= htmlspecialchars($row['start_date']) ?></td>
                <td><?= htmlspecialchars($row['end_date']) ?></td>
                <td><?= htmlspecialchars($row['description']) ?></td>
                <td><?= $row['is_active'] ? 'Yes' : 'No' ?></td>
            </tr>
        <?php endwhile; ?>
    </tbody>
</table>

<?php include 'includes/footer.php'; ?>
