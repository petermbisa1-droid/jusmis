<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'superuser') {
    header("Location: login.php");
    exit;
}

include 'config/db.php';
include 'includes/logger.php';

$page = max(1, intval($_GET['page'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;
$search = trim($_GET['search'] ?? '');

$where = "WHERE 1";
$params = [];
$types = "";

if ($search !== '') {
    $where .= " AND (year_name LIKE ?)";
    $params[] = "%$search%";
    $types .= "s";
}

// Count total
$countSql = "SELECT COUNT(*) FROM academic_years $where";
$countStmt = $conn->prepare($countSql);
if ($types) $countStmt->bind_param($types, ...$params);
$countStmt->execute();
$countStmt->bind_result($total);
$countStmt->fetch();
$countStmt->close();

$totalPages = ceil($total / $limit);

// Fetch data
$dataSql = "SELECT * FROM academic_years $where ORDER BY year_name DESC LIMIT ?, ?";
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
    <h4>Academic Years</h4>
    <a href="create_academic_year.php" class="btn btn-light">
        <i class="fas fa-plus"></i> Add Academic Year
    </a>
</div>

<form method="GET" class="mb-3">
    <div class="input-group">
        <input type="text" name="search" class="form-control" placeholder="Search year..."
               value="<?= htmlspecialchars($search) ?>">
        <button class="btn btn-light"><i class="fas fa-search"></i></button>
    </div>
</form>

<table class="table table-dark table-striped">
    <thead>
        <tr>
            <th>#</th>
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
            <td><?= htmlspecialchars($row['year_name']) ?></td>
            <td>
                <?= $row['is_active'] ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>' ?>
            </td>
            <td><?= htmlspecialchars($row['created_at']) ?></td>
            <td>
                <a href="edit_academic_year.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-info">
                    <i class="fas fa-edit"></i>
                </a>

                <?php if ($row['is_active']): ?>
                    <button class="btn btn-sm btn-secondary" disabled>
                        <i class="fas fa-check-circle"></i> Active
                    </button>
                <?php else: ?>
                    <a href="activate_academic_year.php?id=<?= $row['id'] ?>" 
                       class="btn btn-sm btn-primary"
                       onclick="return confirm('Are you sure you want to activate this academic year? This will deactivate the currently active one.')">
                        <i class="fas fa-bolt"></i> Activate
                    </a>
                <?php endif; ?>

                <a href="delete_academic_year.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-danger"
                   onclick="return confirm('Are you sure you want to delete this year?')">
                    <i class="fas fa-trash"></i>
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
