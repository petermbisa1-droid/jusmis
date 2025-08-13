<?php
session_start();
include 'config/db.php';

// Access control: roles allowed
$allowedRoles = ['admin', 'superuser', 'finance'];
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['role'], $allowedRoles)) {
    header('Location: login.php');
    exit;
}

// Search filters
$search_student = $_GET['search_student'] ?? '';
$search_invoice = $_GET['search_invoice'] ?? '';
$search_date_from = $_GET['search_date_from'] ?? '';
$search_date_to = $_GET['search_date_to'] ?? '';

// Pagination
$itemsPerPage = 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? intval($_GET['page']) : 1;
$offset = ($page - 1) * $itemsPerPage;

// Build WHERE clauses and params for filtering invoices
$whereClauses = [];
$params = [];
$types = '';

if ($search_student !== '') {
    $whereClauses[] = 'i.student_id = ?';
    $params[] = $search_student;
    $types .= 'i';
}

if ($search_invoice !== '') {
    $whereClauses[] = 'i.invoice_number LIKE ?';
    $params[] = '%' . $search_invoice . '%';
    $types .= 's';
}

if ($search_date_from !== '') {
    $whereClauses[] = 'i.created_at >= ?';
    $params[] = $search_date_from . ' 00:00:00';
    $types .= 's';
}

if ($search_date_to !== '') {
    $whereClauses[] = 'i.created_at <= ?';
    $params[] = $search_date_to . ' 23:59:59';
    $types .= 's';
}

$whereSQL = '';
if ($whereClauses) {
    $whereSQL = 'WHERE ' . implode(' AND ', $whereClauses);
}

// Count total invoices for pagination
$countSql = "SELECT COUNT(*) FROM invoices i $whereSQL";
$stmtCount = $conn->prepare($countSql);
if ($params) {
    $stmtCount->bind_param($types, ...$params);
}
$stmtCount->execute();
$stmtCount->bind_result($totalInvoices);
$stmtCount->fetch();
$stmtCount->close();

$totalPages = ceil($totalInvoices / $itemsPerPage);

// Fetch invoices with pagination and filters, joining students
$sql = "
SELECT i.*, s.full_name,
    IFNULL(paid.total_paid, 0) AS total_paid,
    (i.total_amount - IFNULL(paid.total_paid, 0)) AS balance
FROM invoices i
JOIN students s ON i.student_id = s.id
LEFT JOIN (
    SELECT invoice_id, SUM(amount_paid) AS total_paid
    FROM payments
    GROUP BY invoice_id
) paid ON i.id = paid.invoice_id
$whereSQL
ORDER BY i.created_at DESC
LIMIT ? OFFSET ?
";

$stmt = $conn->prepare($sql);

if ($params) {
    $typesWithLimit = $types . 'ii';
    $paramsWithLimit = array_merge($params, [$itemsPerPage, $offset]);
    $stmt->bind_param($typesWithLimit, ...$paramsWithLimit);
} else {
    $stmt->bind_param('ii', $itemsPerPage, $offset);
}

$stmt->execute();
$result = $stmt->get_result();

// Fetch all students for filter dropdown
$students = $conn->query("SELECT id, full_name FROM students ORDER BY full_name");

include 'includes/header.php';
?>

<div class="container my-4">
    <h2>Invoices & Payment Entry</h2>

    <form method="GET" class="row g-3 mb-4">
        <div class="col-md-4">
            <label for="search_student" class="form-label">Filter by Student</label>
            <select name="search_student" id="search_student" class="form-select">
                <option value="">All Students</option>
                <?php while ($stu = $students->fetch_assoc()): ?>
                <option value="<?= $stu['id'] ?>" <?= ($search_student == $stu['id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($stu['full_name']) ?>
                </option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="col-md-4">
            <label for="search_invoice" class="form-label">Filter by Invoice Number</label>
            <input type="text" id="search_invoice" name="search_invoice" class="form-control" value="<?= htmlspecialchars($search_invoice) ?>" placeholder="e.g. INV-JU-001" />
        </div>
        <div class="col-md-2">
            <label for="search_date_from" class="form-label">From Date</label>
            <input type="date" id="search_date_from" name="search_date_from" class="form-control" value="<?= htmlspecialchars($search_date_from) ?>" />
        </div>
        <div class="col-md-2">
            <label for="search_date_to" class="form-label">To Date</label>
            <input type="date" id="search_date_to" name="search_date_to" class="form-control" value="<?= htmlspecialchars($search_date_to) ?>" />
        </div>
        <div class="col-12 mt-2">
            <button type="submit" class="btn btn-primary">Filter</button>
            <a href="payment_entry.php" class="btn btn-secondary ms-2">Reset</a>
        </div>
    </form>

    <table class="table table-bordered table-striped">
        <thead>
            <tr>
                <th>SN</th>
                <th>Invoice Number</th>
                <th>Student</th>
                <th>Total Amount</th>
                <th>Amount Paid</th>
                <th>Balance</th>
                <th>Issued On</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($result->num_rows === 0): ?>
            <tr>
                <td colspan="8" class="text-center">No invoices found.</td>
            </tr>
            <?php else: 
                $sn = $offset + 1;
                while ($row = $result->fetch_assoc()):
                    $balanceClass = ($row['balance'] > 0) ? 'text-danger fw-bold' : 'text-success fw-bold';
            ?>
            <tr>
                <td><?= $sn++ ?></td>
                <td><?= htmlspecialchars($row['invoice_number']) ?></td>
                <td><?= htmlspecialchars($row['full_name']) ?></td>
                <td><?= number_format($row['total_amount'], 2) ?></td>
                <td><?= number_format($row['total_paid'], 2) ?></td>
                <td class="<?= $balanceClass ?>"><?= number_format($row['balance'], 2) ?></td>
                <td><?= htmlspecialchars(date('Y-m-d', strtotime($row['created_at']))) ?></td>
                <td>
                    <a href="enter_payment.php?invoice_id=<?= $row['id'] ?>" class="btn btn-success btn-sm">Enter Payment</a>
                    <a href="reverse_payment.php?invoice_id=<?= $row['id'] ?>" class="btn btn-danger btn-sm ms-1"
                       onclick="return confirm('Are you sure you want to reverse payment(s) for this invoice?');">Reverse Payment</a>
                </td>
            </tr>
            <?php endwhile; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <?php if ($totalPages > 1): ?>
    <nav aria-label="Page navigation">
        <ul class="pagination justify-content-center">
            <?php if ($page > 1): ?>
            <li class="page-item">
                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page-1])) ?>" aria-label="Previous">&laquo; Prev</a>
            </li>
            <?php else: ?>
            <li class="page-item disabled"><span class="page-link">&laquo; Prev</span></li>
            <?php endif; ?>

            <?php
            $start = max(1, $page - 2);
            $end = min($totalPages, $page + 2);
            for ($i = $start; $i <= $end; $i++): ?>
            <li class="page-item <?= ($i == $page) ? 'active' : '' ?>">
                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"><?= $i ?></a>
            </li>
            <?php endfor; ?>

            <?php if ($page < $totalPages): ?>
            <li class="page-item">
                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page+1])) ?>" aria-label="Next">Next &raquo;</a>
            </li>
            <?php else: ?>
            <li class="page-item disabled"><span class="page-link">Next &raquo;</span></li>
            <?php endif; ?>
        </ul>
    </nav>
    <?php endif; ?>

</div>

<?php
$stmt->close();
include 'includes/footer.php'; // Footer can safely use $conn here
$conn->close();
?>
