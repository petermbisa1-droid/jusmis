<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'superuser') {
    header("Location: login.php");
    exit;
}

include 'config/db.php';
include 'includes/logger.php';

// Pagination and search
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;
$search = trim($_GET['search'] ?? '');

$where = "WHERE 1";
$params = [];
$types = "";

if ($search !== '') {
    $where .= " AND (s.session_name LIKE ? OR a.year_name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $types .= "ss";
}

// Count total
$countSql = "SELECT COUNT(*) FROM sessions s JOIN academic_years a ON s.academic_year_id = a.id $where";
$countStmt = $conn->prepare($countSql);
if ($types) $countStmt->bind_param($types, ...$params);
$countStmt->execute();
$countStmt->bind_result($total);
$countStmt->fetch();
$countStmt->close();

$totalPages = ceil($total / $limit);

// Fetch data with academic year
$dataSql = "SELECT s.*, a.year_name FROM sessions s JOIN academic_years a ON s.academic_year_id = a.id $where ORDER BY a.year_name DESC, s.session_name ASC LIMIT ?, ?";
$dataStmt = $conn->prepare($dataSql);
if ($types) {
    $types .= "ii";
    $params[] = $offset;
    $params[] = $limit;
    $dataStmt->bind_param($types, ...$params);
} else {
    $dataStmt->bind_param("ii", $offset, $limit);
}
$dataStmt->execute();
$result = $dataStmt->get_result();

include 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4>Sessions</h4>
    <a href="create_session.php" class="btn btn-light">
        <i class="fas fa-plus"></i> Add Session
    </a>
</div>

<form method="GET" class="mb-3">
    <div class="input-group">
        <input type="text" name="search" class="form-control" placeholder="Search sessions or academic years..."
               value="<?= htmlspecialchars($search) ?>">
        <button class="btn btn-light"><i class="fas fa-search"></i></button>
    </div>
</form>

<table class="table table-dark table-striped">
    <thead>
        <tr>
            <th>#</th>
            <th>Session Name</th>
            <th>Academic Year</th>
            <th>Status</th>
            <th>Created</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php $i = $offset + 1;
        while ($row = $result->fetch_assoc()): ?>
        <tr>
            <td><?= $i++ ?></td>
            <td><?= htmlspecialchars($row['session_name']) ?></td>
            <td><?= htmlspecialchars($row['year_name']) ?></td>
            <td>
                <?= $row['is_active'] ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>' ?>
            </td>
            <td><?= htmlspecialchars($row['created_at']) ?></td>
            <td>
                <a href="edit_session.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-info">
                    <i class="fas fa-edit"></i>
                </a>
                
                <?php if ($row['is_active']): ?>
                    <button class="btn btn-sm btn-secondary" disabled>
                        <i class="fas fa-check-circle"></i> Active
                    </button>
                <?php else: ?>
                    <a href="activate_session.php?id=<?= $row['id'] ?>" 
                       class="btn btn-sm btn-primary"
                       onclick="return confirm('Activate this session? This will deactivate any other active session.')">
                        <i class="fas fa-bolt"></i> Activate
                    </a>
                <?php endif; ?>

                <a href="delete_session.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-danger"
                   onclick="return confirm('Delete this session?')">
                    <i class="fas fa-trash"></i>
                </a>
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

<?php include 'includes/footer.php'; ?>
