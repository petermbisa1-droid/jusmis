<?php
session_start();
include 'config/db.php';
include 'includes/header.php';

if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['role'], ['superuser', 'registrar', 'vicechancellor', 'academic'])) {
    die("Unauthorized access.");
}

// Filters
$academic_year_id = isset($_GET['academic_year_id']) ? intval($_GET['academic_year_id']) : 0;
$session_id = isset($_GET['session_id']) ? intval($_GET['session_id']) : 0;
$class_id = isset($_GET['class_id']) ? intval($_GET['class_id']) : 0;
$programme_id = isset($_GET['programme_id']) ? intval($_GET['programme_id']) : 0;

// Pagination
$limit = 10;
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$offset = ($page - 1) * $limit;

// Fetch Filters Data
$academic_years = $conn->query("SELECT id, year_name FROM academic_years");
$sessions = $conn->query("SELECT id, session_name FROM sessions");
$classes = $conn->query("SELECT id, class_name FROM classes");
$programmes = $conn->query("SELECT id, programme_name FROM programmes");

// Build WHERE conditions
$where = "WHERE 1=1";
$params = [];
$types = '';

if ($academic_year_id) {
    $where .= " AND r.academic_year_id = ?";
    $params[] = $academic_year_id;
    $types .= 'i';
}
if ($session_id) {
    $where .= " AND r.session_id = ?";
    $params[] = $session_id;
    $types .= 'i';
}
if ($class_id) {
    $where .= " AND r.class_id = ?";
    $params[] = $class_id;
    $types .= 'i';
}
if ($programme_id) {
    $where .= " AND c.programme_id = ?";
    $params[] = $programme_id;
    $types .= 'i';
}

// Total Records for Pagination
$count_query = "SELECT COUNT(*) FROM registrations r INNER JOIN students s ON r.student_id = s.id INNER JOIN classes c ON r.class_id = c.id $where";
$stmt = $conn->prepare($count_query);
if ($params) $stmt->bind_param($types, ...$params);
$stmt->execute();
$stmt->bind_result($total_records);
$stmt->fetch();
$stmt->close();

// Fetch Paginated Data
$query = "
    SELECT s.full_name, s.registration_number, s.phone, c.class_name, p.programme_name, se.session_name, ay.year_name
    FROM registrations r
    INNER JOIN students s ON r.student_id = s.id
    INNER JOIN classes c ON r.class_id = c.id
    INNER JOIN programmes p ON c.programme_id = p.id
    INNER JOIN sessions se ON r.session_id = se.id
    INNER JOIN academic_years ay ON r.academic_year_id = ay.id
    $where
    ORDER BY s.full_name ASC
    LIMIT $limit OFFSET $offset
";
$stmt = $conn->prepare($query);
if ($params) $stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
?>

<div class="container mt-4">
    <h2>Student Enrolment Report</h2>
    <form method="GET" class="row g-3 mb-4">
        <div class="col-md-2">
            <label for="academic_year_id" class="form-label">Academic Year</label>
            <select name="academic_year_id" id="academic_year_id" class="form-select">
                <option value="">All</option>
                <?php while($ay = $academic_years->fetch_assoc()): ?>
                    <option value="<?= $ay['id']; ?>" <?= ($ay['id'] == $academic_year_id) ? 'selected' : ''; ?>><?= $ay['year_name']; ?></option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label for="session_id" class="form-label">Session</label>
            <select name="session_id" id="session_id" class="form-select">
                <option value="">All</option>
                <?php while($se = $sessions->fetch_assoc()): ?>
                    <option value="<?= $se['id']; ?>" <?= ($se['id'] == $session_id) ? 'selected' : ''; ?>><?= $se['session_name']; ?></option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label for="class_id" class="form-label">Class</label>
            <select name="class_id" id="class_id" class="form-select">
                <option value="">All</option>
                <?php while($cl = $classes->fetch_assoc()): ?>
                    <option value="<?= $cl['id']; ?>" <?= ($cl['id'] == $class_id) ? 'selected' : ''; ?>><?= $cl['class_name']; ?></option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label for="programme_id" class="form-label">Programme</label>
            <select name="programme_id" id="programme_id" class="form-select">
                <option value="">All</option>
                <?php while($pr = $programmes->fetch_assoc()): ?>
                    <option value="<?= $pr['id']; ?>" <?= ($pr['id'] == $programme_id) ? 'selected' : ''; ?>><?= $pr['programme_name']; ?></option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="col-md-2 align-self-end">
            <button type="submit" class="btn btn-primary">Filter</button>
            <a href="export_enrolment_pdf.php?<?= http_build_query($_GET); ?>" target="_blank" class="btn btn-success">Export PDF</a>
        </div>
    </form>

    <table class="table table-bordered">
        <thead>
            <tr>
                <th>SN</th>
                <th>Full Name</th>
                <th>Reg. Number</th>
                <th>Phone</th>
                <th>Class</th>
                <th>Programme</th>
                <th>Session</th>
                <th>Academic Year</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($result->num_rows > 0): 
                $sn = $offset + 1;
                while ($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><?= $sn++; ?></td>
                    <td><?= htmlspecialchars($row['full_name']); ?></td>
                    <td><?= htmlspecialchars($row['registration_number']); ?></td>
                    <td><?= htmlspecialchars($row['phone']); ?></td>
                    <td><?= htmlspecialchars($row['class_name']); ?></td>
                    <td><?= htmlspecialchars($row['programme_name']); ?></td>
                    <td><?= htmlspecialchars($row['session_name']); ?></td>
                    <td><?= htmlspecialchars($row['year_name']); ?></td>
                </tr>
            <?php endwhile; else: ?>
                <tr><td colspan="8" class="text-center">No records found.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- Pagination -->
    <?php
    $total_pages = ceil($total_records / $limit);
    if ($total_pages > 1): ?>
        <nav>
            <ul class="pagination">
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <li class="page-item <?= ($i == $page) ? 'active' : ''; ?>">
                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"><?= $i; ?></a>
                    </li>
                <?php endfor; ?>
            </ul>
        </nav>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
