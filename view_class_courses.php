<?php
session_start();
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}
include 'config/db.php';
include 'includes/header.php';

$class_id = intval($_GET['class_id'] ?? 0);
if ($class_id <= 0) {
    echo "<div class='alert alert-danger'>Invalid class ID.</div>";
    include 'includes/footer.php';
    exit;
}

// Get class details
$classStmt = $conn->prepare("SELECT c.class_name, p.programme_name, c.semester
                             FROM classes c
                             JOIN programmes p ON c.programme_id = p.id
                             WHERE c.id = ?");
$classStmt->bind_param("i", $class_id);
$classStmt->execute();
$classResult = $classStmt->get_result();
$class = $classResult->fetch_assoc();
$classStmt->close();

if (!$class) {
    echo "<div class='alert alert-danger'>Class not found.</div>";
    include 'includes/footer.php';
    exit;
}

// Get assigned courses
$query = "SELECT cc.id, cr.course_code, cr.course_name 
          FROM class_courses cc 
          JOIN courses cr ON cc.course_id = cr.id 
          WHERE cc.class_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $class_id);
$stmt->execute();
$result = $stmt->get_result();
?>

<h3>Assigned Courses for <?= htmlspecialchars($class['class_name']) ?> - <?= htmlspecialchars($class['programme_name']) ?> (<?= htmlspecialchars($class['semester']) ?>)</h3>

<a href="assign_courses.php" class="btn btn-secondary mb-3">← Back to Assign Courses</a>

<?php if ($result->num_rows > 0): ?>
    <table class="table table-striped table-bordered">
        <thead>
            <tr>
                <th>#</th>
                <th>Course Code</th>
                <th>Course Name</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php $i = 1; while ($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><?= $i++ ?></td>
                    <td><?= htmlspecialchars($row['course_code']) ?></td>
                    <td><?= htmlspecialchars($row['course_name']) ?></td>
                    <td>
                        <a href="edit_class_course.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-warning"><i class="fas fa-edit"></i></a>
                        <a href="delete_class_course.php?id=<?= $row['id'] ?>" onclick="return confirm('Remove this course from class?')" class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></a>
                    </td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
<?php else: ?>
    <div class="alert alert-info">No courses assigned to this class yet.</div>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
