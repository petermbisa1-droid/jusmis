<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'superuser') {
    header("Location: login.php");
    exit;
}

include 'config/db.php';
include 'includes/logger.php';

$message = '';
$academic_year_id = '';
$session_name = '';


// Fetch academic years for dropdown
$yearResult = $conn->query("SELECT id, year_name FROM academic_years ORDER BY year_name DESC");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $academic_year_id = intval($_POST['academic_year_id']);
    $session_name = trim($_POST['session_name']);
    $created_by = $_SESSION['user']['full_name'] ?? 'Unknown';

    if ($academic_year_id <= 0 || empty($session_name)) {
        $message = "Please select academic year and enter session name.";
    } else {
        // Check for duplicate session in the same academic year
        $check = $conn->prepare("SELECT COUNT(*) FROM sessions WHERE academic_year_id = ? AND session_name = ?");
        $check->bind_param("is", $academic_year_id, $session_name);
        $check->execute();
        $check->bind_result($exists);
        $check->fetch();
        $check->close();

        if ($exists > 0) {
            $message = "This session already exists for the selected academic year.";
        } else {
            $stmt = $conn->prepare("INSERT INTO sessions (academic_year_id, session_name, created_by) VALUES (?, ?, ?)");
            $stmt->bind_param("iss", $academic_year_id, $session_name, $created_by);

            if ($stmt->execute()) {
                log_action("Created session '$session_name' for academic year ID $academic_year_id", $conn);
                header("Location: sessions.php");
                exit;
            } else {
                $message = "Failed to create session.";
            }
        }
    }
}

include 'includes/header.php';
?>

<h4>Add New Session</h4>

<?php if ($message): ?>
    <div class="alert alert-warning"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<form method="POST" class="bg-dark p-4 rounded">
    <div class="mb-3">
        <label class="form-label">Academic Year</label>
        <select name="academic_year_id" class="form-select" required>
            <option value="">-- Select Academic Year --</option>
            <?php while ($row = $yearResult->fetch_assoc()): ?>
                <option value="<?= $row['id'] ?>" <?= ($row['id'] == $academic_year_id) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($row['year_name']) ?>
                </option>
            <?php endwhile; ?>
        </select>
    </div>
    <div class="mb-3">
        <label class="form-label">Session Name</label>
        <input type="text" name="session_name" class="form-control" required
               placeholder="e.g. Session 1" value="<?= htmlspecialchars($session_name) ?>">
    </div>
    <button class="btn btn-light"><i class="fas fa-save"></i> Save</button>
    <a href="sessions.php" class="btn btn-secondary">Cancel</a>
</form>

<?php include 'includes/footer.php'; ?>
