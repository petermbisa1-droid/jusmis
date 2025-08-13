<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'superuser') {
    header('Location: login.php');
    exit;
}

include 'config/db.php';

$restore_id = isset($_GET['restore_id']) ? (int)$_GET['restore_id'] : 0;
$messages = [];

if ($restore_id > 0) {
    $check = $conn->prepare("SELECT id FROM students WHERE id = ? AND status = 'inactive'");
    $check->bind_param("i", $restore_id);
    $check->execute();
    $check->store_result();

    if ($check->num_rows > 0) {
        $check->close();
        $upd = $conn->prepare("UPDATE students SET status = 'active' WHERE id = ?");
        $upd->bind_param("i", $restore_id);
        if ($upd->execute()) {
            $messages[] = "Student restored successfully.";
        } else {
            $messages[] = "Failed to restore student.";
        }
        $upd->close();
    } else {
        $messages[] = "Student not found or already active.";
        $check->close();
    }
}

$search = trim($_GET['search'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;

$where = "WHERE s.status = 'inactive'";
$params = [];
$types = '';

if ($search !== '') {
    $where .= " AND (s.registration_number LIKE ? OR s.full_name LIKE ?)";
    $searchParam = "%$search%";
    $params[] = &$searchParam;
    $params[] = &$searchParam;
    $types .= 'ss';
}

$countSql = "SELECT COUNT(*) FROM students s $where";
$countStmt = $conn->prepare($countSql);
if ($params) {
    $countStmt->bind_param($types, ...$params);
}
$countStmt->execute();
$countStmt->bind_result($total);
$countStmt->fetch();
$countStmt->close();

$totalPages = ceil($total / $limit);

$sql = "SELECT s.id, s.registration_number, s.full_name, p.programme_name, c.class_name, s.year, s.semester
        FROM students s
        LEFT JOIN programmes p ON s.programme_id = p.id
        LEFT JOIN classes c ON s.class_id = c.id
        $where
        ORDER BY s.created_at DESC
        LIMIT ? OFFSET ?";

$stmt = $conn->prepare($sql);

if ($params) {
    $types .= "ii";
    $limitParam = $limit;
    $offsetParam = $offset;
    $params[] = &$limitParam;
    $params[] = &$offsetParam;
    $stmt->bind_param($types, ...$params);
} else {
    $stmt->bind_param("ii", $limit, $offset);
}

$stmt->execute();
$res = $stmt->get_result();

include 'includes/header.php';
?>

<h3>Restore Inactive Students</h3>

<?php foreach ($messages as $msg): ?>
  <div class="alert alert-info"><?= htmlspecialchars($msg) ?></div>
<?php endforeach; ?>

<form method="GET" class="mb-3">
  <input type="text" name="search" class="form-control" placeholder="Search by Reg No or Name" value="<?= htmlspecialchars($search) ?>">
</form>

<?php if ($res->num_rows === 0): ?>
  <p>No inactive students found.</p>
<?php else: ?>
  <?php $sn = $offset + 1; ?>
  <table class="table table-bordered">
    <thead>
      <tr>
        <th>S/N</th>
        <th>Reg No</th>
        <th>Name</th>
        <th>Programme</th>
        <th>Class</th>
        <th>Year</th>
        <th>Semester</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php while ($r = $res->fetch_assoc()): ?>
        <tr>
          <td><?= $sn++ ?></td>
          <td><?= htmlspecialchars($r['registration_number']) ?></td>
          <td><?= htmlspecialchars($r['full_name']) ?></td>
          <td><?= htmlspecialchars($r['programme_name']) ?></td>
          <td><?= htmlspecialchars($r['class_name']) ?></td>
          <td><?= htmlspecialchars($r['year']) ?></td>
          <td><?= htmlspecialchars($r['semester']) ?></td>
          <td>
            <a href="?restore_id=<?= $r['id'] ?>&search=<?= urlencode($search) ?>&page=<?= $page ?>"
               class="btn btn-sm btn-success"
               onclick="return confirm('Restore this student?')">Restore</a>
          </td>
        </tr>
      <?php endwhile; ?>
    </tbody>
  </table>

  <nav>
    <ul class="pagination">
      <?php for ($i = 1; $i <= $totalPages; $i++): ?>
        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
          <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>"><?= $i ?></a>
        </li>
      <?php endfor; ?>
    </ul>
  </nav>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
