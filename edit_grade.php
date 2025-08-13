<?php
session_start();
include 'config/db.php';

// Access control
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['role'], ['superuser', 'admin', 'lecturer'])) {
    header('Location: login.php');
    exit;
}

$grade_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($grade_id <= 0) {
    die('Invalid Grade ID.');
}

// Check if grade is approved (locked)
$stmt = $conn->prepare("SELECT ag.id FROM approved_grades ag WHERE ag.grade_id = ?");
$stmt->bind_param('i', $grade_id);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
    die('This grade has been approved and cannot be edited.');
}
$stmt->close();

// Fetch Grade Details
$stmt = $conn->prepare("SELECT * FROM grades WHERE id = ?");
$stmt->bind_param('i', $grade_id);
$stmt->execute();
$result = $stmt->get_result();
$grade = $result->fetch_assoc();
$stmt->close();

if (!$grade) {
    die('Grade not found.');
}

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cw1 = (int)$_POST['cw1'];
    $cw2 = (int)$_POST['cw2'];
    $mid = (int)$_POST['mid'];
    $exam = (int)$_POST['exam'];
    $final_score = $cw1 + $cw2 + $mid + $exam;

    // Simple Grade Calculation
    if ($final_score >= 70) {
        $grade_letter = 'A';
    } elseif ($final_score >= 60) {
        $grade_letter = 'B';
    } elseif ($final_score >= 50) {
        $grade_letter = 'C';
    } elseif ($final_score >= 40) {
        $grade_letter = 'D';
    } elseif ($final_score >= 35) {
        $grade_letter = 'E';
    } else {
        $grade_letter = 'F';
    }

    $remarks = $_POST['remarks'] ?? '';
    $updated_by = $_SESSION['user']['id'] ?? null;

    // Update Grade
    $stmt = $conn->prepare("UPDATE grades SET cw1 = ?, cw2 = ?, mid = ?, exam = ?, final_score = ?, grade_letter = ?, remarks = ?, uploaded_by = ?, updated_at = NOW() WHERE id = ?");
    $stmt->bind_param('iiiiissii', $cw1, $cw2, $mid, $exam, $final_score, $grade_letter, $remarks, $updated_by, $grade_id);

    if ($stmt->execute()) {
        $success = 'Grade updated successfully!';
    } else {
        $errors[] = 'Failed to update grade. Please try again.';
    }
    $stmt->close();

    // Refresh Grade Data
    $stmt = $conn->prepare("SELECT * FROM grades WHERE id = ?");
    $stmt->bind_param('i', $grade_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $grade = $result->fetch_assoc();
    $stmt->close();
}

include 'includes/header.php';
?>

<div class="container mt-4">
    <h3>Edit Grade</h3>

    <?php if ($errors): ?>
        <div class="alert alert-danger">
            <ul><?php foreach ($errors as $e) echo "<li>" . htmlspecialchars($e) . "</li>"; ?></ul>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success"> <?= htmlspecialchars($success) ?> </div>
    <?php endif; ?>

    <form method="POST" class="card p-4">
        <div class="row g-3">
            <div class="col-md-3">
                <label class="form-label">CW1</label>
                <input type="number" name="cw1" class="form-control" value="<?= htmlspecialchars($grade['cw1']) ?>" required>
            </div>

            <div class="col-md-3">
                <label class="form-label">CW2</label>
                <input type="number" name="cw2" class="form-control" value="<?= htmlspecialchars($grade['cw2']) ?>" required>
            </div>

            <div class="col-md-3">
                <label class="form-label">Mid</label>
                <input type="number" name="mid" class="form-control" value="<?= htmlspecialchars($grade['mid']) ?>" required>
            </div>

            <div class="col-md-3">
                <label class="form-label">Exam</label>
                <input type="number" name="exam" class="form-control" value="<?= htmlspecialchars($grade['exam']) ?>" required>
            </div>

            <div class="col-12">
                <label class="form-label">Remarks</label>
                <textarea name="remarks" class="form-control"><?= htmlspecialchars($grade['remarks']) ?></textarea>
            </div>

            <div class="col-12">
                <button type="submit" class="btn btn-success">Update Grade</button>
                <a href="view_grades.php" class="btn btn-secondary">Back</a>
            </div>
        </div>
    </form>
</div>

<?php include 'includes/footer.php'; ?>
