<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'superuser') {
    header('Location: login.php');
    exit;
}
include 'config/db.php';
include 'includes/logger.php';

$errors = [];
$success = '';
$currentUser = $_SESSION['user']['username'] ?? 'unknown';

// Fetch classes for dropdown
$classRes = $conn->query("SELECT id, class_name, year, semester FROM classes WHERE is_active = 1 ORDER BY year, semester");

// Handle promotion form submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $class_id = (int)($_POST['class_id'] ?? 0);
    if (!$class_id) {
        $errors[] = "Please select a class to promote students from.";
    } else {
        // Get current class info (year, semester)
        $cls = $conn->prepare("SELECT year, semester FROM classes WHERE id = ?");
        $cls->bind_param("i", $class_id);
        $cls->execute();
        $cls->bind_result($currentYear, $currentSemester);
        if (!$cls->fetch()) {
            $errors[] = "Invalid class selected.";
        }
        $cls->close();

        if (!$errors) {
            // Determine next year and semester
            if ($currentSemester == 1) {
                $nextSemester = 2;
                $nextYear = $currentYear;
            } else {
                // Assuming only semesters 1 and 2
                $nextSemester = 1;
                $nextYear = $currentYear + 1;
            }

            // Find the class_id for the nextYear/nextSemester combination
            $nextClassStmt = $conn->prepare("SELECT id FROM classes WHERE year = ? AND semester = ? LIMIT 1");
            $nextClassStmt->bind_param("ii", $nextYear, $nextSemester);
            $nextClassStmt->execute();
            $nextClassStmt->bind_result($nextClassId);
            if (!$nextClassStmt->fetch()) {
                $errors[] = "No class found for next year $nextYear and semester $nextSemester.";
            }
            $nextClassStmt->close();

            if (!$errors) {
                // Select students to promote
                $sel = $conn->prepare("SELECT id, registration_number, year, semester FROM students WHERE class_id = ? AND status = 'Active'");
                $sel->bind_param("i", $class_id);
                $sel->execute();
                $res = $sel->get_result();

                $promotedCount = 0;
                $conn->begin_transaction();
                while ($student = $res->fetch_assoc()) {
                    // If student already at max year/semester - optional graduation logic could go here
                    // For now, just promote

                    // Update student record
                    $upd = $conn->prepare("UPDATE students SET class_id = ?, year = ?, semester = ?, updated_by = ?, updated_at = NOW() WHERE id = ?");
                    $upd->bind_param("iiisi", $nextClassId, $nextYear, $nextSemester, $currentUser, $student['id']);
                    if ($upd->execute()) {
                        $promotedCount++;
                        log_action("Promoted student {$student['registration_number']} to year $nextYear semester $nextSemester", $conn);
                    }
                    $upd->close();
                }
                $conn->commit();
                $success = "Promotion complete. $promotedCount students promoted from year $currentYear semester $currentSemester to year $nextYear semester $nextSemester.";
            }
        }
    }
}

include 'includes/header.php';
?>

<h3>Promote Students</h3>

<?php if ($errors): ?>
    <div class="alert alert-danger"><ul>
        <?php foreach ($errors as $e) echo "<li>$e</li>"; ?>
    </ul></div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success"><?= $success ?></div>
<?php endif; ?>

<form method="POST">
  <div class="mb-3">
    <label>Select Class to Promote From</label>
    <select name="class_id" class="form-select" required>
      <option value="">-- Select Class --</option>
      <?php while ($cls = $classRes->fetch_assoc()): ?>
        <option value="<?= $cls['id'] ?>" <?= (isset($_POST['class_id']) && $_POST['class_id'] == $cls['id']) ? 'selected' : '' ?>>
          <?= htmlspecialchars($cls['class_name']) ?> (Year: <?= $cls['year'] ?>, Semester: <?= $cls['semester'] ?>)
        </option>
      <?php endwhile; ?>
    </select>
  </div>
  <button type="submit" class="btn btn-primary">Promote Students</button>
</form>

<?php include 'includes/footer.php'; ?>
