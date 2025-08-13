<?php
session_start();

if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['role'], ['superuser', 'admin'])) {
    exit("Unauthorized access.");
}

include 'config/db.php';

$limit = 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0 ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Get total logs count
$totalResult = $conn->query("SELECT COUNT(*) AS total FROM audit_trail");
$totalRow = $totalResult->fetch_assoc();
$totalRecords = $totalRow['total'] ?? 0;
$totalPages = ceil($totalRecords / $limit);

// Fetch logs for current page
$query = "SELECT * FROM audit_trail ORDER BY created_at DESC LIMIT $limit OFFSET $offset";
$result = $conn->query($query);

echo '<table class="table table-striped table-bordered">';
echo '<thead class="table-dark">';
echo '<tr>';
echo '<th>SN</th>';
echo '<th>ID</th>';
echo '<th>User</th>';
echo '<th>Role</th>';
echo '<th>Activity</th>';
echo '<th>Timestamp</th>';
echo '<th>IP Address</th>';
echo '<th>User Agent</th>';
echo '</tr>';
echo '</thead>';

echo '<tbody>';
$sn = $offset + 1;
while ($row = $result->fetch_assoc()) {
    echo '<tr>';
    echo '<td>' . $sn++ . '</td>';
    echo '<td>' . htmlspecialchars($row['id'] ?? '') . '</td>';
    echo '<td>' . htmlspecialchars($row['user_name'] ?? '') . '</td>';
    echo '<td>' . htmlspecialchars($row['role'] ?? '') . '</td>';
    echo '<td>' . htmlspecialchars($row['activity'] ?? '') . '</td>';
    echo '<td>' . htmlspecialchars($row['created_at'] ?? '') . '</td>';
    echo '<td>' . htmlspecialchars($row['ip_address'] ?? '') . '</td>';
    echo '<td>' . htmlspecialchars($row['user_agent'] ?? '') . '</td>';
    echo '</tr>';
}
echo '</tbody>';

echo '<tfoot>';
echo '<tr><td colspan="8" class="text-center">';
if ($page > 1) {
    echo '<button onclick="loadPage(' . ($page - 1) . ')" class="btn btn-sm btn-primary me-2">Prev</button>';
}
if ($page < $totalPages) {
    echo '<button onclick="loadPage(' . ($page + 1) . ')" class="btn btn-sm btn-primary">Next</button>';
}
echo " <small>Page $page of $totalPages</small>";
echo '</td></tr>';
echo '</tfoot>';

echo '</table>';
?>
<script>
function loadPage(page) {
    fetch('fetch_logs.php?page=' + page)
        .then(response => response.text())
        .then(html => {
            document.getElementById('logsContainer').innerHTML = html;
        });
}
</script>
