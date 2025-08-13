<?php
session_start();
include 'config/db.php';
include 'includes/header.php';

if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$user = $_SESSION['user'];
$user_id = $user['id'];
$user_role = $user['role'];

// Fetch active session and academic year
$stmt = $conn->prepare("SELECT id, academic_year_id FROM sessions WHERE is_active = 1 LIMIT 1");
$stmt->execute();
$stmt->bind_result($active_session_id, $active_academic_year_id);
$stmt->fetch();
$stmt->close();

if (!$active_session_id) {
    echo '<div class="alert alert-warning">No active session found.</div>';
    include 'includes/footer.php';
    exit;
}

// For lecturers and superusers: handle upload
if (($user_role === 'lecturer' || $user_role === 'superuser') && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_assignment'])) {
    // Sanitize inputs
    $course_id = intval($_POST['course_id']);
    $title = trim($_POST['title']);
    $deadline = $_POST['deadline'];

    // Validate file upload
    if (isset($_FILES['assignment_file']) && $_FILES['assignment_file']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['assignment_file']['tmp_name'];
        $fileName = basename($_FILES['assignment_file']['name']);
        $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $allowedExts = ['pdf', 'doc', 'docx', 'zip'];

        if (!in_array($fileExt, $allowedExts)) {
            echo '<div class="alert alert-danger">Invalid file type. Allowed: pdf, doc, docx, zip.</div>';
        } else {
            $newFileName = uniqid('assignment_', true) . '.' . $fileExt;
            $uploadDir = 'uploads/assignments/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            $destPath = $uploadDir . $newFileName;

            if (move_uploaded_file($fileTmpPath, $destPath)) {
                // Insert assignment record
                $stmt = $conn->prepare("INSERT INTO assignments (course_id, title, file_path, deadline, uploaded_by, session_id, academic_year_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
                $stmt->bind_param('isssiii', $course_id, $title, $destPath, $deadline, $user_id, $active_session_id, $active_academic_year_id);
                if ($stmt->execute()) {
                    echo '<div class="alert alert-success">Assignment uploaded successfully.</div>';
                } else {
                    echo '<div class="alert alert-danger">Failed to save assignment.</div>';
                }
                $stmt->close();
            } else {
                echo '<div class="alert alert-danger">Failed to move uploaded file.</div>';
            }
        }
    } else {
        echo '<div class="alert alert-danger">No file uploaded or upload error.</div>';
    }
}

// Fetch lecturer's courses or all courses if superuser
if ($user_role === 'lecturer') {
    // Lecturer courses (courses where lecturer_id = $user_id)
    $stmt = $conn->prepare("SELECT id, course_code, course_name FROM courses WHERE lecturer_id = ? AND is_active = 1 ORDER BY course_code");
    $stmt->bind_param('i', $user_id);
} else {
    // Superuser sees all active courses
    $stmt = $conn->prepare("SELECT id, course_code, course_name FROM courses WHERE is_active = 1 ORDER BY course_code");
}
$stmt->execute();
$result = $stmt->get_result();
$courses = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Fetch assignments for the active session and academic year and courses relevant to the user
$course_ids = array_column($courses, 'id');
if (count($course_ids) > 0) {
    $placeholders = implode(',', array_fill(0, count($course_ids), '?'));
    $types = str_repeat('i', count($course_ids) + 2);
    $params = array_merge([$active_session_id, $active_academic_year_id], $course_ids);

    $sql = "SELECT a.id, a.course_id, a.title, a.file_path, a.deadline, c.course_code, c.course_name 
            FROM assignments a
            JOIN courses c ON a.course_id = c.id
            WHERE a.session_id = ? AND a.academic_year_id = ? AND a.course_id IN ($placeholders)
            ORDER BY a.deadline ASC";

    $stmt = $conn->prepare($sql);

    $tmp = [];
    foreach ($params as $key => $val) {
        $tmp[$key] = &$params[$key];
    }
    array_unshift($tmp, $types);

    call_user_func_array([$stmt, 'bind_param'], $tmp);
    $stmt->execute();
    $assignments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    $assignments = [];
}

// Display interface below ?>

<div class="container my-4">
    <h2>Assignments</h2>

    <?php if ($user_role === 'lecturer' || $user_role === 'superuser'): ?>
    <div class="card mb-4">
        <div class="card-header">Upload New Assignment</div>
        <div class="card-body">
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="upload_assignment" value="1" />
                <div class="mb-3">
                    <label for="course_id" class="form-label">Course</label>
                    <select name="course_id" id="course_id" class="form-select" required>
                        <option value="">Select Course</option>
                        <?php foreach ($courses as $course): ?>
                            <option value="<?= $course['id'] ?>">
                                <?= htmlspecialchars($course['course_code'] . ' - ' . $course['course_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label for="title" class="form-label">Assignment Title</label>
                    <input type="text" name="title" id="title" class="form-control" required />
                </div>
                <div class="mb-3">
                    <label for="deadline" class="form-label">Submission Deadline</label>
                    <input type="datetime-local" name="deadline" id="deadline" class="form-control" required />
                </div>
                <div class="mb-3">
                    <label for="assignment_file" class="form-label">Upload File</label>
                    <input type="file" name="assignment_file" id="assignment_file" class="form-control" required />
                </div>
                <button type="submit" class="btn btn-primary">Upload Assignment</button>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <h3>Available Assignments</h3>
    <?php if (empty($assignments)): ?>
        <div class="alert alert-info">No assignments available for your courses in the current session.</div>
    <?php else: ?>
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>Course</th>
                    <th>Title</th>
                    <th>Deadline</th>
                    <th>File</th>
                    <?php if ($user_role === 'student'): ?>
                    <th>Submit</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($assignments as $assignment): ?>
                <tr>
                    <td><?= htmlspecialchars($assignment['course_code'] . ' - ' . $assignment['course_name']) ?></td>
                    <td><?= htmlspecialchars($assignment['title']) ?></td>
                    <td><?= htmlspecialchars(date('Y-m-d H:i', strtotime($assignment['deadline']))) ?></td>
                    <td><a href="<?= htmlspecialchars($assignment['file_path']) ?>" download>Download</a></td>
                    <?php if ($user_role === 'student'): ?>
                        <td><a href="submit_assignment.php?assignment_id=<?= $assignment['id'] ?>" class="btn btn-sm btn-success">Submit</a></td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

</div>

<?php include 'includes/footer.php'; ?>
