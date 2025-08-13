<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'superuser') {
    header('Location: login.php');
    exit;
}
include 'config/db.php';
include 'includes/logger.php';

$id = $_GET['id'] ?? null;
if (!$id) {
    header('Location: classes.php');
    exit;
}

$errors = [];
$success = '';

// Fetch existing class data
$stmt = $conn->prepare("SELECT class_name, programme_id, academic_year_id, session_id FROM classes WHERE id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$result = $stmt->get_result();
$class = $result->fetch_assoc();
$stmt->close();

if (!$class) {
    header('Location: classes.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $class_name = trim($_POST['class_name']);
    $programme_id = $_POST['programme_id'];
    $academic_year_id = $_POST['academic_year_id'];
    $session_id = $_POST['session_id'];
    // Safely get user's full name or fallback
    $updated_by = $_SESSION['user']['full_name'] ?? ($_SESSION['user']['name'] ?? 'Unknown User');

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

    // Auto-assign semester based on class_name (case-insensitive)
    $semester = 1; // default
    if (stripos($class_name, 'semester 2') !== false) {
        $semester = 2;
    } elseif (stripos($class_name, 'semester 1') !== false) {
        $semester = 1;
    }

    // Check duplicate class excluding current record
    $stmt = $conn->prepare("SELECT id FROM classes WHERE class_name = ? AND programme_id = ? AND academic_year_id = ? AND session_id = ? AND id != ?");
    $stmt->bind_param("siiii", $class_name, $programme_id, $academic_year_id, $session_id, $id);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        $errors[] = 'Another class with this name exists for the selected Programme, Academic Year, and Session.';
    }
    $stmt->close();

    if (!$errors) {
        $stmt = $conn->prepare("UPDATE classes SET class_name = ?, programme_id = ?, academic_year_id = ?, session_id = ?, semester = ?, updated_by = ?, updated_at = NOW() WHERE id = ?");
        $stmt->bind_param("siiisii", $class_name, $programme_id, $academic_year_id, $session_id, $semester, $updated_by, $id);
        if ($stmt->execute()) {
            log_action("Updated class: $class_name (ID: $id)", $conn);
            $success = 'Class updated successfully.';
            // Refresh class data after update
            $class['class_name'] = $class_name;
            $class['programme_id'] = $programme_id;
            $class['academic_year_id'] = $academic_year_id;
            $class['session_id'] = $session_id;
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

<h3>Edit Class</h3>

<?php if ($errors): ?>
    <div class="alert alert-danger">
        <ul>
            <?php foreach ($errors as $err) echo "<li>$err</li>"; ?>
        </ul>
    </div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success"><?= $success ?></div>
<?php endif; ?>

<form method="POST">
    <div class="mb-3">
        <label for="class_name" class="form-label">Class Name</label>
        <input type="text" id="class_name" name="class_name" class="form-control" required value="<?= htmlspecialchars($class['class_name']) ?>">
        <small class="form-text text-muted">Include "Semester 1" or "Semester 2" in the class name for automatic semester assignment.</small>
    </div>
    <div class="mb-3">
        <label for="programme_id" class="form-label">Programme</label>
        <select id="programme_id" name="programme_id" class="form-select" required>
            <option value="">Select Programme</option>
            <?php while ($row = $programmes->fetch_assoc()): ?>
                <option value="<?= $row['id'] ?>" <?= ($class['programme_id'] == $row['id']) ? 'selected' : '' ?>><?= htmlspecialchars($row['programme_name']) ?></option>
            <?php endwhile; ?>
        </select>
    </div>
    <div class="mb-3">
        <label for="academic_year_id" class="form-label">Academic Year</label>
        <select id="academic_year_id" name="academic_year_id" class="form-select" required>
            <option value="">Select Academic Year</option>
            <?php while ($row = $academic_years->fetch_assoc()): ?>
                <option value="<?= $row['id'] ?>" <?= ($class['academic_year_id'] == $row['id']) ? 'selected' : '' ?>><?= htmlspecialchars($row['year_name']) ?></option>
            <?php endwhile; ?>
        </select>
    </div>
    <div class="mb-3">
        <label for="session_id" class="form-label">Session</label>
        <select id="session_id" name="session_id" class="form-select" required>
            <option value="">Select Session</option>
            <?php while ($row = $sessions->fetch_assoc()): ?>
                <option value="<?= $row['id'] ?>" <?= ($class['session_id'] == $row['id']) ? 'selected' : '' ?>><?= htmlspecialchars($row['session_name']) ?></option>
            <?php endwhile; ?>
        </select>
    </div>
    <button type="submit" class="btn btn-primary">Update Class</button>
    <a href="classes.php" class="btn btn-secondary">Cancel</a>
</form>

<?php include 'includes/footer.php'; ?>
