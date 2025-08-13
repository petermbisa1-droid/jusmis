<?php
session_start();
include 'config/db.php'; // updated path
include 'includes/header.php'; // Include header

if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['role'], ['lecturer', 'superuser'])) {
    die("Unauthorized access.");
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Invalid submission ID.");
}

$submission_id = intval($_GET['id']);

$message = '';

// Handle form submission to update grade/feedback
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $grade = trim($_POST['grade']);
    $feedback = trim($_POST['feedback']);

    if ($grade === '') {
        $grade = null;
    }

    $updateStmt = $conn->prepare("UPDATE assignment_submissions SET grade = ?, feedback = ? WHERE id = ?");
    $updateStmt->bind_param("ssi", $grade, $feedback, $submission_id);

    if ($updateStmt->execute()) {
        $message = "Grade and feedback updated successfully.";
    } else {
        $message = "Error updating record: " . $updateStmt->error;
    }
    $updateStmt->close();
}

// Fetch submission details for display
$stmt = $conn->prepare("
    SELECT asub.id, s.full_name, a.title, asub.file_path, asub.submitted_at, asub.grade, asub.feedback
    FROM assignment_submissions asub
    INNER JOIN students s ON asub.student_id = s.id
    INNER JOIN assignments a ON asub.assignment_id = a.id
    WHERE asub.id = ?
");
$stmt->bind_param("i", $submission_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Submission not found.");
}

$submission = $result->fetch_assoc();
$stmt->close();
?>

<div class="container my-4">
    <h2>Grade Submission</h2>

    <?php if ($message): ?>
        <div class="alert alert-info"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <p><strong>Student:</strong> <?= htmlspecialchars($submission['full_name']) ?></p>
    <p><strong>Assignment:</strong> <?= htmlspecialchars($submission['title']) ?></p>
    <p><strong>Submitted At:</strong> <?= htmlspecialchars($submission['submitted_at']) ?></p>
    <p><strong>File:</strong> <a href="<?= htmlspecialchars($submission['file_path']) ?>" target="_blank" style="color:black;">View File</a></p>

    <form method="post" action="">
        <div class="mb-3">
            <label for="grade" class="form-label">Grade</label>
            <input type="text" name="grade" id="grade" class="form-control" value="<?= htmlspecialchars($submission['grade'] ?? '') ?>" placeholder="Enter grade or leave blank" />
        </div>
        <div class="mb-3">
            <label for="feedback" class="form-label">Feedback</label>
            <textarea name="feedback" id="feedback" class="form-control" rows="5" placeholder="Enter feedback"><?= htmlspecialchars($submission['feedback'] ?? '') ?></textarea>
        </div>
        <button type="submit" class="btn btn-success">Save</button>
        <a href="view_submissions.php" class="btn btn-secondary">Back to Submissions</a>
    </form>
</div>

<?php include 'includes/footer.php'; ?>
