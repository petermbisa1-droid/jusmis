<?php
session_start();
include 'config/db.php';
include 'includes/header.php';

if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['role'], ['superuser', 'registrar', 'vicechancellor', 'academic'])) {
    die("Unauthorized access.");
}

// Fetch Sessions
$sessions = $conn->query("SELECT id, session_name FROM sessions WHERE is_active = 1");
$session_id = isset($_GET['session_id']) ? intval($_GET['session_id']) : 0;

// Fetch Academic Years
$academic_years = $conn->query("SELECT id, year_name FROM academic_years WHERE is_active = 1");
$academic_year_id = isset($_GET['academic_year_id']) ? intval($_GET['academic_year_id']) : 0;

// Fetch Classes based on filters
$class_query = "SELECT id, class_name FROM classes WHERE 1";
if ($session_id) {
    $class_query .= " AND session_id = $session_id";
}
if ($academic_year_id) {
    $class_query .= " AND academic_year_id = $academic_year_id";
}
$classes = $conn->query($class_query);
$class_id = isset($_GET['class_id']) ? intval($_GET['class_id']) : 0;

// Pagination
$limit = 10;
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$offset = ($page - 1) * $limit;

// Count Total Students
$count_stmt = $conn->prepare("
    SELECT COUNT(*) 
    FROM registrations r
    INNER JOIN students s ON r.student_id = s.id
    INNER JOIN classes c ON r.class_id = c.id
    WHERE 1
    " . ($class_id ? " AND c.id = ?" : "")
);
if ($class_id) {
    $count_stmt->bind_param("i", $class_id);
}
$count_stmt->execute();
$count_stmt->bind_result($total);
$count_stmt->fetch();
$count_stmt->close();

$total_pages = ceil($total / $limit);

// Fetch Students List
$query = "
    SELECT s.full_name, s.sex AS gender, s.email, c.class_name, se.session_name, ay.year_name
    FROM registrations r
    INNER JOIN students s ON r.student_id = s.id
    INNER JOIN classes c ON r.class_id = c.id
    INNER JOIN sessions se ON c.session_id = se.id
    INNER JOIN academic_years ay ON c.academic_year_id = ay.id
    WHERE 1
    " . ($class_id ? " AND c.id = ?" : "") . "
    ORDER BY s.full_name ASC LIMIT ? OFFSET ?
";
$stmt = $conn->prepare($query);
if ($class_id) {
    $stmt->bind_param("iii", $class_id, $limit, $offset);
} else {
    $stmt->bind_param("ii", $limit, $offset);
}
$stmt->execute();
$result = $stmt->get_result();
?>

<div class="container my-4">
    <h2>Class List Report</h2>

    <form method="get" class="row g-3 mb-3">
        <div class="col-md-3">
            <label>Session</label>
            <select name="session_id" class="form-select" onchange="this.form.submit()">
                <option value="">-- All Sessions --</option>
                <?php while ($row = $sessions->fetch_assoc()): ?>
                    <option value="<?= $row['id'] ?>" <?= $session_id == $row['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($row['session_name']) ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label>Academic Year</label>
            <select name="academic_year_id" class="form-select" onchange="this.form.submit()">
                <option value="">-- All Academic Years --</option>
                <?php while ($row = $academic_years->fetch_assoc()): ?>
                    <option value="<?= $row['id'] ?>" <?= $academic_year_id == $row['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($row['year_name']) ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label>Class</label>
            <select name="class_id" class="form-select">
                <option value="">-- All Classes --</option>
                <?php while ($row = $classes->fetch_assoc()): ?>
                    <option value="<?= $row['id'] ?>" <?= $class_id == $row['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($row['class_name']) ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="col-md-3 d-flex align-items-end">
            <button type="submit" class="btn btn-primary">Filter</button>
        </div>
    </form>

    <div class="mb-3">
        <a href="export_class_list_pdf.php?session_id=<?= $session_id ?>&academic_year_id=<?= $academic_year_id ?>&class_id=<?= $class_id ?>" class="btn btn-danger">Export PDF</a>
    </div>

    <table class="table table-bordered bg-white">
        <thead>
            <tr>
                <th>#</th>
                <th>Full Name</th>
                <th>Gender</th>
                <th>Email</th>
                <th>Class</th>
                <th>Session</th>
                <th>Academic Year</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($result->num_rows === 0): ?>
                <tr><td colspan="7" class="text-center">No records found.</td></tr>
            <?php else: $sn = $offset + 1; ?>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?= $sn++ ?></td>
                        <td><?= htmlspecialchars($row['full_name']) ?></td>
                        <td><?= htmlspecialchars($row['gender']) ?></td>
                        <td><?= htmlspecialchars($row['email']) ?></td>
                        <td><?= htmlspecialchars($row['class_name']) ?></td>
                        <td><?= htmlspecialchars($row['session_name']) ?></td>
                        <td><?= htmlspecialchars($row['year_name']) ?></td>
                    </tr>
                <?php endwhile; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- Pagination -->
    <nav>
        <ul class="pagination">
            <?php for ($p = 1; $p <= $total_pages; $p++): ?>
                <li class="page-item <?= $p == $page ? 'active' : '' ?>">
                    <a class="page-link" href="?page=<?= $p ?>&session_id=<?= $session_id ?>&academic_year_id=<?= $academic_year_id ?>&class_id=<?= $class_id ?>"><?= $p ?></a>
                </li>
            <?php endfor; ?>
        </ul>
    </nav>
</div>

<?php
$stmt->close();
include 'includes/footer.php';
?>
