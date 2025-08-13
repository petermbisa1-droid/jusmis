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
    $where .= ' AND (programme_name LIKE ?)';
    $params[] = "%$search%";
    $types .= 's';
}

$countSql = "SELECT COUNT(*) FROM programmes $where";
$countStmt = $conn->prepare($countSql);
if ($params) {
    $countStmt->bind_param($types, ...$params);
}
$countStmt->execute();
$countStmt->bind_result($total);
$countStmt->fetch();
$countStmt->close();

$totalPages = ceil($total / $limit);

$dataSql = "SELECT id, programme_name, code, is_active FROM programmes $where ORDER BY created_at DESC LIMIT ?, ?";
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

<h3>Programmes</h3>

<form method="GET" class="mb-3">
  <div class="input-group">
    <input type="text" name="search" class="form-control" placeholder="Search programmes..." value="<?= htmlspecialchars($search) ?>">
    <button class="btn btn-light"><i class="fas fa-search"></i></button>
  </div>
</form>

<table class="table table-striped table-dark text-white">
  <thead>
    <tr>
      <th>#</th><th>Name</th><th>Code</th><th>Status</th><th>Actions</th>
    </tr>
  </thead>
  <tbody>
    <?php $i = $offset + 1; while ($row = $result->fetch_assoc()): ?>
      <tr>
        <td><?= $i++ ?></td>
        <td><?= htmlspecialchars($row['programme_name']) ?></td>
        <td><?= htmlspecialchars($row['code']) ?></td>
        <td><?= $row['is_active'] ? 'Active' : 'Inactive' ?></td>
        <td>
          <a href="edit_programme.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-info"><i class="fas fa-edit"></i></a>
          <?php if ($row['is_active']): ?>
            <a href="toggle_programme.php?id=<?= $row['id'] ?>&off=1" class="btn btn-sm btn-warning"><i class="fas fa-ban"></i></a>
          <?php else: ?>
            <a href="toggle_programme.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-success"><i class="fas fa-check"></i></a>
          <?php endif; ?>
          <a href="delete_programme.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete programme permanently?')">
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

<a href="create_programme.php" class="btn btn-light"><i class="fas fa-plus"></i> Add Programme</a>

<?php include 'includes/footer.php'; ?>
