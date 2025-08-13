<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'superuser') {
    header("Location: login.php");
    exit;
}

include 'config/db.php';
include 'includes/logger.php';

$id = intval($_GET['id'] ?? 0);
$message = '';

if ($id <= 0) {
    die("Invalid session ID.");
}

// Fetch current session
$stmt = $conn->prepare("SELECT * FROM sessions WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$sessionData = $result->fetch_assoc();
$stmt->close();

if (!$sessionData) {
    die("Session not found.");
}

// Fetch academic years for dropdown
$yearResult = $conn->query("SELECT id, year_name FROM academic_years ORDER BY year_name DESC");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $academic_year_id = intval($_POST['academic_year_id']);
    $session_name = trim($_POST['session_name']);
    $updated_by = $_SESSION['user']['full_name'];

    // Check for duplication
    $check = $conn->prepare("SELECT COUNT(*) FROM sessions WHERE academic_year_id = ? AND session_name = ? AND id != ?");
    $check->bind_param("isi", $academic_year_id, $session_name, $id);
    $check->execute();
    $check->bind_result($exists);
    $check->fetch();
    $check->close();

    if ($exists > 0) {
        $message = "A session with that name already exists for this academic year.";
    } else {
        $stmt = $conn->prepare("UPDATE sessions SET academic_year_id = ?, session_name = ?, updated_by = ? WHERE id = ?");
        $stmt->bind_param("issi", $academic_year_id, $session_name, $updated_by, $id);
        if ($stmt->execute()) {
            log_action("Updated session '$session_name'", $conn);
            header("Location: sessions.php");
            exit;
        } else {
            $message = "Failed to update session.";
        }
    }
}

include 'includes/header.php';
?>

<h4>Edit Session</h4>

<?php if ($message): ?>
    <div class="alert alert-warning"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<form method="POST" class="bg-dark p-4 rounded">
    <div class="mb-3">
        <label class="form-label">Academic Year</label>
        <select name="academic_year_id" class="form-select" required>
            <option value="">-- Select Academic Year --</option>
            <?php while ($row = $yearResult->fetch_assoc()): ?>
                <option value="<?= $row['id'] ?>" <?= ($row['id'] == $sessionData['academic_year_id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($row['year_name']) ?>
                </option>
            <?php endwhile; ?>
        </select>
    </div>

    <div class="mb-3">
        <label class="form-label">Session Name</label>
        <input type="text" name="session_name" class="form-control" required
               value="<?= htmlspecialchars($sessionData['session_name']) ?>">
    </div>

    <button class="btn btn-light"><i class="fas fa-save"></i> Save Changes</button>
    <a href="sessions.php" class="btn btn-secondary">Cancel</a>
</form>

<?php include 'includes/footer.php'; ?>
