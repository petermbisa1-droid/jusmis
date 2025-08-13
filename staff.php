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
    $where .= ' AND (full_name LIKE ? OR email LIKE ? OR phone LIKE ? OR role LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $types .= 'ssss';
}

$countSql = "SELECT COUNT(*) FROM staff $where";
$countStmt = $conn->prepare($countSql);
if ($params) {
    $countStmt->bind_param($types, ...$params);
}
$countStmt->execute();
$countStmt->bind_result($total);
$countStmt->fetch();
$countStmt->close();

$totalPages = ceil($total / $limit);

$dataSql = "SELECT id, full_name, email, phone, position, department, role FROM staff $where ORDER BY created_at DESC LIMIT ?, ?";
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

<h3>Staff Management</h3>

<form method="GET" class="mb-3">
  <div class="input-group">
    <input type="text" name="search" class="form-control" placeholder="Search staff..." value="<?= htmlspecialchars($search) ?>">
    <button class="btn btn-light"><i class="fas fa-search"></i></button>
  </div>
</form>

<table class="table table-striped table-dark text-white">
  <thead>
    <tr>
      <th>#</th><th>Name</th><th>Email</th><th>Phone</th><th>Position</th><th>Department</th><th>Role</th><th>Actions</th>
    </tr>
  </thead>
  <tbody>
    <?php $i = $offset + 1; while ($row = $result->fetch_assoc()): ?>
      <tr>
        <td><?= $i++ ?></td>
        <td><?= htmlspecialchars($row['full_name']) ?></td>
        <td><?= htmlspecialchars($row['email']) ?></td>
        <td><?= htmlspecialchars($row['phone']) ?></td>
        <td><?= htmlspecialchars($row['position']) ?></td>
        <td><?= htmlspecialchars($row['department']) ?></td>
        <td><?= htmlspecialchars($row['role']) ?></td>
        <td>
          <a href="edit_staff.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-info"><i class="fas fa-edit"></i></a>
          <a href="delete_staff.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete staff permanently?')">
            <i class="fas fa-trash"></i></a>
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

<a href="create_staff.php" class="btn btn-light"><i class="fas fa-plus"></i> Add Staff</a>

<?php include 'includes/footer.php'; ?>
