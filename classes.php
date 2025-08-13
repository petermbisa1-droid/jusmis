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
    $where .= ' AND (c.class_name LIKE ? OR p.programme_name LIKE ? OR a.year_name LIKE ? OR s.session_name LIKE ? OR c.semester LIKE ?)';
    $types .= 'sssss';
    $params = array_fill(0, 5, "%$search%");
}

$countSql = "SELECT COUNT(*) FROM classes c
            JOIN programmes p ON c.programme_id = p.id
            JOIN academic_years a ON c.academic_year_id = a.id
            JOIN sessions s ON c.session_id = s.id
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

$dataSql = "SELECT c.id, c.class_name, c.semester, c.is_active, p.programme_name AS programme, a.year_name, s.session_name AS session
            FROM classes c
            JOIN programmes p ON c.programme_id = p.id
            JOIN academic_years a ON c.academic_year_id = a.id
            JOIN sessions s ON c.session_id = s.id
            $where ORDER BY c.created_at DESC LIMIT ?, ?";
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

<h3>Classes</h3>
<form method="GET" class="mb-3">
    <div class="input-group">
        <input type="text" name="search" class="form-control" placeholder="Search classes..." value="<?= htmlspecialchars($search) ?>">
        <button class="btn btn-light"><i class="fas fa-search"></i></button>
    </div>
</form>

<table class="table table-striped table-dark text-white">
    <thead>
        <tr>
            <th>#</th><th>Class Name</th><th>Programme</th><th>Academic Year</th><th>Session</th><th>Semester</th><th>Status</th><th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php $i = $offset + 1; while ($row = $result->fetch_assoc()): ?>
            <tr>
                <td><?= $i++ ?></td>
                <td><?= htmlspecialchars($row['class_name']) ?></td>
                <td><?= htmlspecialchars($row['programme']) ?></td>
                <td><?= htmlspecialchars($row['year_name']) ?></td>
                <td><?= htmlspecialchars($row['session']) ?></td>
                <td><?= htmlspecialchars($row['semester']) ?></td>
                <td>
                    <?php if ($row['is_active']): ?>
                        <span class="badge bg-success">Active</span>
                    <?php else: ?>
                        <span class="badge bg-secondary">Inactive</span>
                    <?php endif; ?>
                </td>
                <td>
                    <a href="edit_class.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-info"><i class="fas fa-edit"></i></a>
                    <a href="delete_class.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this class?')"><i class="fas fa-trash"></i></a>
                    <a href="toggle_class.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-warning"><i class="fas fa-toggle-on"></i></a>
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

<a href="create_class.php" class="btn btn-light"><i class="fas fa-plus"></i> Add Class</a>

<?php include 'includes/footer.php'; ?>
