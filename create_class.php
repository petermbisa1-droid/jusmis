<?php
session_start();
if (!isset($_SESSION['user']) || ($_SESSION['user']['role'] ?? '') !== 'superuser') {
    header('Location: login.php');
    exit;
}
include 'config/db.php';
include 'includes/logger.php';

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $class_name = trim($_POST['class_name']);
    $programme_id = $_POST['programme_id'];
    $academic_year_id = $_POST['academic_year_id'];
    $session_id = $_POST['session_id'];

    // Safely get full_name from session or fallback
    $created_by = $_SESSION['user']['full_name'] ?? 'Unknown User';

    // Basic validation
    if ($class_name === '') {
        $errors[] = 'Class name is required.';
    }
    if (empty($programme_id)) {
        $errors[] = 'Programme must be selected.';
    }
    if (empty($academic_year_id)) {
        $errors[] = 'Academic Year must be selected.';
    }
    if (empty($session_id)) {
        $errors[] = 'Session must be selected.';
    }

    // Auto-assign Year and Semester based on class_name (case-insensitive)
    $year_number = 1;  // default year
    $semester = 'Semester 1';  // default semester string

    if (preg_match('/year\s*(\d+)/i', $class_name, $yearMatch)) {
        $year_number = (int)$yearMatch[1];
    }

    if (preg_match('/semester\s*(\d+)/i', $class_name, $semMatch)) {
        $semesterNum = (int)$semMatch[1];
        $semester = 'Semester ' . $semesterNum;
    }

    // Check duplicate class in same programme, academic year, session
    $stmt = $conn->prepare("SELECT id FROM classes WHERE class_name = ? AND programme_id = ? AND academic_year_id = ? AND session_id = ?");
    $stmt->bind_param("siii", $class_name, $programme_id, $academic_year_id, $session_id);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        $errors[] = 'Class with this name already exists for the selected Programme, Academic Year, and Session.';
    }
    $stmt->close();

    if (!$errors) {
        $stmt = $conn->prepare("INSERT INTO classes (class_name, year, programme_id, academic_year_id, session_id, semester, is_active, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, 1, ?, NOW())");
        $stmt->bind_param("siiisss", $class_name, $year_number, $programme_id, $academic_year_id, $session_id, $semester, $created_by);

        if ($stmt->execute()) {
            log_action("Created class: $class_name", $conn);

            // Insert into audit_trail table safely
            $user_name = $_SESSION['user']['full_name'] ?? 'Unknown User';
            $role = $_SESSION['user']['role'] ?? 'Unknown Role';
            $activity = "Created class: $class_name, Year: $year_number, Semester: $semester";

            $audit_stmt = $conn->prepare("INSERT INTO audit_trail (user_name, role, activity, created_at) VALUES (?, ?, ?, NOW())");
            $audit_stmt->bind_param("sss", $user_name, $role, $activity);
            $audit_stmt->execute();
            $audit_stmt->close();

            $success = 'Class created successfully.';
            // Clear form inputs after success
            $class_name = '';
            $programme_id = $academic_year_id = $session_id = null;
        } else {
            $errors[] = 'Database error: ' . $stmt->error;
        }
        $stmt->close();
    }
}

// Fetch programmes, academic years, sessions for selects
$programmes = $conn->query("SELECT id, programme_name FROM programmes WHERE is_active = 1 ORDER BY programme_name ASC");
$academic_years = $conn->query("SELECT id, year_name FROM academic_years WHERE is_active = 1 ORDER BY year_name DESC");
$sessions = $conn->query("SELECT id, session_name FROM sessions WHERE is_active = 1 ORDER BY session_name ASC");

include 'includes/header.php';
?>

<h3>Add New Class</h3>

<?php if ($errors): ?>
    <div class="alert alert-danger">
        <ul>
            <?php foreach ($errors as $err) echo "<li>" . htmlspecialchars($err) . "</li>"; ?>
        </ul>
    </div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<form method="POST">
    <div class="mb-3">
        <label for="class_name" class="form-label">Class Name</label>
        <input type="text" id="class_name" name="class_name" class="form-control" required value="<?= htmlspecialchars($class_name ?? '') ?>">
        <small class="form-text text-muted">Include "Year X Semester Y" in the class name for automatic year and semester assignment.</small>
    </div>
    <div class="mb-3">
        <label for="programme_id" class="form-label">Programme</label>
        <select id="programme_id" name="programme_id" class="form-select" required>
            <option value="">Select Programme</option>
            <?php while ($row = $programmes->fetch_assoc()): ?>
                <option value="<?= $row['id'] ?>" <?= (isset($programme_id) && $programme_id == $row['id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($row['programme_name']) ?>
                </option>
            <?php endwhile; ?>
        </select>
    </div>
    <div class="mb-3">
        <label for="academic_year_id" class="form-label">Academic Year</label>
        <select id="academic_year_id" name="academic_year_id" class="form-select" required>
            <option value="">Select Academic Year</option>
            <?php while ($row = $academic_years->fetch_assoc()): ?>
                <option value="<?= $row['id'] ?>" <?= (isset($academic_year_id) && $academic_year_id == $row['id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($row['year_name']) ?>
                </option>
            <?php endwhile; ?>
        </select>
    </div>
    <div class="mb-3">
        <label for="session_id" class="form-label">Session</label>
        <select id="session_id" name="session_id" class="form-select" required>
            <option value="">Select Session</option>
            <?php while ($row = $sessions->fetch_assoc()): ?>
                <option value="<?= $row['id'] ?>" <?= (isset($session_id) && $session_id == $row['id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($row['session_name']) ?>
                </option>
            <?php endwhile; ?>
        </select>
    </div>
    <button type="submit" class="btn btn-primary">Create Class</button>
    <a href="classes.php" class="btn btn-secondary">Cancel</a>
</form>

<?php include 'includes/footer.php'; ?>
