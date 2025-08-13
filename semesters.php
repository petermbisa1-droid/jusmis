<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'superuser') {
    header("Location: login.php");
    exit;
}

include 'config/db.php';
include 'includes/logger.php';

// Fetch sessions for dropdown
$sessions = $conn->query("SELECT id, session_name FROM sessions ORDER BY session_name DESC");

// Handle open/close semester
if (isset($_GET['toggle']) && isset($_GET['id'])) {
    $semester_id = intval($_GET['id']);
    $user = $_SESSION['user']['full_name'];

    // Get current status
    $stmt = $conn->prepare("SELECT is_open FROM semesters WHERE id = ?");
    $stmt->bind_param("i", $semester_id);
    $stmt->execute();
    $stmt->bind_result($is_open);
    $stmt->fetch();
    $stmt->close();

    $new_status = $is_open ? 0 : 1;
    $stmt = $conn->prepare("UPDATE semesters SET is_open = ?, updated_by = ? WHERE id = ?");
    $stmt->bind_param("isi", $new_status, $user, $semester_id);
    $stmt->execute();

    log_action(($new_status ? "Opened" : "Closed") . " semester ID $semester_id", $conn);
    header("Location: semesters.php");
    exit;
}

// Search and Pagination
$search = trim($_GET['search'] ?? '');
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;

$where = '1';
$params = [];
if ($search !== '') {
    $where = "semester_name LIKE ?";
    $params[] = "%$search%";
}

// Count total
$countStmt = $conn->prepare("SELECT COUNT(*) FROM semesters WHERE $where");
if ($params) $countStmt->bind_param(str_repeat('s', count($params)), ...$params);
$countStmt->execute();
$countStmt->bind_result($total);
$countStmt->fetch();
$countStmt->close();
$totalPages = ceil($total / $limit);

// Fetch data
$query = "SELECT semesters.*, sessions.session_name FROM semesters 
          JOIN sessions ON semesters.session_id = sessions.id
          WHERE $where ORDER BY semesters.created_at DESC LIMIT ?, ?";

$paramsWithLimit = [...$params, $offset, $limit];
$stmt = $conn->prepare($query);
if ($params) {
    $types = str_repeat('s', count($params)) . 'ii';
    $stmt->bind_param($types, ...$paramsWithLimit);
} else {
    $stmt->bind_param("ii", $offset, $limit);
}
$stmt->execute();
$result = $stmt->get_result();

include 'includes/header.php';
?>

<h3>Semester Management</h3>

<form method="GET" class="mb-3">
  <div class="input-group">
    <input type="text" name="search" class="form-control" placeholder="Search semesters..." value="<?= htmlspecialchars($search) ?>">
    <button class="btn btn-light"><i class="fas fa-search"></i></button>
  </div>
</form>

<table class="table table-dark table-striped">
  <thead>
    <tr>
      <th>#</th>
      <th>Semester Name</th>
      <th>Session</th>
      <th>Status</th>
      <th>Actions</th>
    </tr>
  </thead>
  <tbody>
    <?php $i = $offset + 1; while ($row = $result->fetch_assoc()): ?>
    <tr>
      <td><?= $i++ ?></td>
      <td><?= htmlspecialchars($row['semester_name']) ?></td>
      <td><?= htmlspecialchars($row['session_name']) ?></td>
      <td><?= $row['is_open'] ? '<span class="text-success">Open</span>' : '<span class="text-danger">Closed</span>' ?></td>
      <td>
        <a href="semesters.php?toggle=1&id=<?= $row['id'] ?>" class="btn btn-sm btn-warning">
          <i class="fas fa-sync-alt"></i> <?= $row['is_open'] ? 'Close' : 'Open' ?>
        </a>
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
