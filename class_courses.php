<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'superuser') {
    header('Location: login.php');
    exit;
}
include 'config/db.php';
include 'includes/logger.php';

$page = max(1, intval($_GET['page'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;

$search = trim($_GET['search'] ?? '');
$where = 'WHERE 1';
$params = [];
$types = '';

if ($search !== '') {
    $where .= ' AND (cl.class_name LIKE ? OR co.course_name LIKE ?)';
    $types .= 'ss';
    $params = ["%$search%", "%$search%"];
}

// Count total records for pagination
$countSql = "SELECT COUNT(*) FROM class_courses cc
    JOIN classes cl ON cc.class_id = cl.id
    JOIN courses co ON cc.course_id = co.id
    $where";
$countStmt = $conn->prepare($countSql);
if ($params) {
    $countStmt->bind_param($types, ...$params);
}
$countStmt->execute();
$countStmt->bind_result($total);
$countStmt->fetch();
$countStmt->close();

$totalPages = ceil($total / $limit);

// Fetch records
$dataSql = "SELECT cc.id, cl.class_name, co.course_name, cc.is_active
    FROM class_courses cc
    JOIN classes cl ON cc.class_id = cl.id
    JOIN courses co ON cc.course_id = co.id
    $where ORDER BY cc.created_at DESC LIMIT ?, ?";
$dataStmt = $conn->prepare($dataSql);

if ($params) {
    $types .= 'ii';
    $params[] = $offset;
    $params[] = $limit;
    $dataStmt->bind_param($types, ...$params);
} else {
    $dataStmt->bind_param('ii', $offset, $limit);
}

$dataStmt->execute();
$result = $dataStmt->get_result();

include 'includes/header.php';
?>

<h3>Class Courses</h3>

<form method="GET" class="mb-3">
    <div class="input-group">
        <input type="text" name="search" class="form-control" placeholder="Search by class or course..." value="<?= htmlspecialchars($search) ?>">
        <button class="btn btn-light"><i class="fas fa-search"></i></button>
    </div>
</form>

<table class="table table-striped table-dark text-white">
    <thead>
        <tr>
            <th>#</th>
            <th>Class Name</th>
            <th>Course Name</th>
            <th>Status</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php $i = $offset + 1; while ($row = $result->fetch_assoc()): ?>
            <tr>
                <td><?= $i++ ?></td>
                <td><?= htmlspecialchars($row['class_name']) ?></td>
                <td><?= htmlspecialchars($row['course_name']) ?></td>
                <td>
                    <?php if ($row['is_active']): ?>
                        <span class="badge bg-success">Active</span>
                    <?php else: ?>
                        <span class="badge bg-secondary">Inactive</span>
                    <?php endif; ?>
                </td>
                <td>
                    <a href="edit_class_course.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-info"><i class="fas fa-edit"></i></a>
                    <a href="delete_class_course.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this assignment?')"><i class="fas fa-trash"></i></a>
                    <a href="toggle_class_course.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-warning"><i class="fas fa-toggle-on"></i></a>
                </td>
            </tr>
        <?php endwhile; ?>
    </tbody>
</table>

<nav>
    <ul class="pagination justify-content-center">
        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
            <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                <a class="page-link" href="?page=<?= $p ?>&search=<?= urlencode($search) ?>"><?= $p ?></a>
            </li>
        <?php endfor; ?>
    </ul>
</nav>

<a href="create_class_course.php" class="btn btn-light"><i class="fas fa-plus"></i> Add Class Course</a>

<?php include 'includes/footer.php'; ?>
