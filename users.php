<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'superuser') {
    header('Location: login.php');
    exit;
}
include 'config/db.php';
include 'includes/logger.php';

// Pagination setup
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;

// Search filters
$search = trim($_GET['search'] ?? '');

// Build count/query
$where = 'WHERE 1';
$params = [];
if ($search !== '') {
    $where .= ' AND (full_name LIKE ? OR username LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$countStmt = $conn->prepare("SELECT COUNT(*) FROM users $where");
if ($params) $countStmt->bind_param(str_repeat('s', count($params)), ...$params);
$countStmt->execute();
$countStmt->bind_result($total);
$countStmt->fetch();
$countStmt->close();

$totalPages = ceil($total / $limit);

$dataStmt = $conn->prepare("SELECT id, full_name, username, role, is_active FROM users $where ORDER BY created_at DESC LIMIT ?, ?");
if ($params) {
    $types = str_repeat('s', count($params)) . 'ii';
    $paramsWithLimit = [...$params, $offset, $limit];
    $dataStmt->bind_param($types, ...$paramsWithLimit);
} else {
    $dataStmt->bind_param('ii', $offset, $limit);
}
$dataStmt->execute();
$result = $dataStmt->get_result();

$loggedInUserId = $_SESSION['user']['id'];

include 'includes/header.php';
?>

<h3>User Management</h3>

<form method="GET" class="mb-3">
  <div class="input-group">
    <input type="text" name="search" class="form-control" placeholder="Search users..."
           value="<?= htmlspecialchars($search) ?>">
    <button class="btn btn-light"><i class="fas fa-search"></i></button>
  </div>
</form>

<table class="table table-striped table-dark text-white">
  <thead>
    <tr>
      <th>#</th><th>Name</th><th>Username</th><th>Role</th><th>Status</th><th>Actions</th>
    </tr>
  </thead>
  <tbody>
    <?php $i = $offset + 1;
    while ($row = $result->fetch_assoc()): ?>
      <tr>
        <td><?= $i++ ?></td>
        <td><?= htmlspecialchars($row['full_name']) ?></td>
        <td><?= htmlspecialchars($row['username']) ?></td>
        <td><?= htmlspecialchars($row['role']) ?></td>
        <td><?= $row['is_active'] ? 'Active' : 'Inactive' ?></td>
        <td>
          <a href="edit_user.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-info" title="Edit"><i class="fas fa-edit"></i></a>

          <?php if ($row['is_active']): ?>
            <a href="toggle_user.php?id=<?= $row['id'] ?>&off=1" class="btn btn-sm btn-warning" title="Deactivate">
              <i class="fas fa-user-slash"></i>
            </a>
          <?php else: ?>
            <a href="toggle_user.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-success" title="Reactivate">
              <i class="fas fa-user-check"></i>
            </a>
          <?php endif; ?>

          <a href="delete_user.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-danger"
             onclick="return confirm('Delete user permanently?')" title="Delete"><i class="fas fa-trash"></i></a>

          <!-- Change Password button -->
          <a href="change_password.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-primary" title="Change Password">
            <i class="fas fa-key"></i>
          </a>

          <!-- Reset Password button, only for superuser and not self -->
          <?php if ($_SESSION['user']['role'] === 'superuser' && $row['id'] != $loggedInUserId): ?>
            <a href="change_password.php?id=<?= $row['id'] ?>" 
               class="btn btn-sm btn-danger" 
               title="Reset Password"
               onclick="return confirm('Are you sure you want to RESET this user\'s password?');"
            >
              <i class="fas fa-sync-alt"></i>
            </a>
          <?php endif; ?>
        </td>
      </tr>
    <?php endwhile; ?>
  </tbody>
</table>

<!-- Pagination -->
<nav>
  <ul class="pagination justify-content-center">
    <?php for ($p = 1; $p <= $totalPages; $p++): ?>
      <li class="page-item <?= $p === $page ? 'active' : '' ?>">
        <a class="page-link" href="?page=<?= $p ?>&search=<?= urlencode($search) ?>"><?= $p ?></a>
      </li>
    <?php endfor; ?>
  </ul>
</nav>

<?php include 'includes/footer.php'; ?>
