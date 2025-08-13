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
    $where .= " AND (course_code LIKE ? OR course_name LIKE ? OR type LIKE ?)";
    $params = array_fill(0, 3, "%$search%");
    $types = 'sss';
}

$countStmt = $conn->prepare("SELECT COUNT(*) FROM courses $where");
if ($params) $countStmt->bind_param($types, ...$params);
$countStmt->execute();
$countStmt->bind_result($total);
$countStmt->fetch();
$countStmt->close();

$totalPages = ceil($total / $limit);

$dataSql = "SELECT c.*, s.full_name AS lecturer_name 
            FROM courses c 
            LEFT JOIN staff s ON c.lecturer_id = s.id 
            $where 
            ORDER BY c.created_at DESC 
            LIMIT ?, ?";
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

<h3>Course Management</h3>

<form method="GET" class="mb-3">
  <div class="input-group">
    <input type="text" name="search" class="form-control" placeholder="Search courses..." value="<?= htmlspecialchars($search) ?>">
    <button class="btn btn-light"><i class="fas fa-search"></i></button>
  </div>
</form>

<table class="table table-striped table-dark text-white">
  <thead>
    <tr>
      <th>#</th><th>Code</th><th>Name</th><th>Credits</th><th>Type</th><th>Lecturer</th><th>Status</th><th>Actions</th>
    </tr>
  </thead>
  <tbody>
    <?php $i = $offset + 1; while ($row = $result->fetch_assoc()): ?>
      <tr>
        <td><?= $i++ ?></td>
        <td><?= htmlspecialchars($row['course_code']) ?></td>
        <td><?= htmlspecialchars($row['course_name']) ?></td>
        <td><?= $row['credit_hours'] ?></td>
        <td><?= ucfirst($row['type']) ?></td>
        <td><?= $row['lecturer_name'] ?? '<i>Unassigned</i>' ?></td>
        <td>
          <?php if ($row['is_active']): ?>
            <span class="badge bg-success">Active</span>
          <?php else: ?>
            <span class="badge bg-secondary">Inactive</span>
          <?php endif; ?>
        </td>
        <td>
          <a href="edit_course.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-info"><i class="fas fa-edit"></i></a>
          <a href="delete_course.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this course?')"><i class="fas fa-trash"></i></a>
          <a href="toggle_course.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-warning"><i class="fas fa-toggle-<?= $row['is_active'] ? 'on' : 'off' ?>"></i></a>
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

<a href="create_course.php" class="btn btn-light"><i class="fas fa-plus"></i> Add Course</a>

<?php include 'includes/footer.php'; ?>
