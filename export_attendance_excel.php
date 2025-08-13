<?php
include 'config/db.php';

header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=staff_attendance_report.xls");

// Fetch Filters
$from_date = isset($_GET['from_date']) ? $_GET['from_date'] : '';
$to_date = isset($_GET['to_date']) ? $_GET['to_date'] : '';
$staff_id = isset($_GET['staff_id']) ? intval($_GET['staff_id']) : 0;

// Build WHERE
$where = "WHERE 1=1";
$params = [];
$types = '';

if ($from_date) {
    $where .= " AND DATE(sa.check_in_time) >= ?";
    $params[] = $from_date;
    $types .= 's';
}
if ($to_date) {
    $where .= " AND DATE(sa.check_in_time) <= ?";
    $params[] = $to_date;
    $types .= 's';
}
if ($staff_id) {
    $where .= " AND s.id = ?";
    $params[] = $staff_id;
    $types .= 'i';
}

// Fetch Data
$sql = "
    SELECT s.full_name, s.position, s.department, sa.check_in_time, sa.check_out_time
    FROM staff_attendance sa
    INNER JOIN staff s ON sa.staff_id = s.id
    $where
    ORDER BY sa.check_in_time ASC
";
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// Excel Table
echo "<table border='1'>";
echo "<tr>
        <th>SN</th>
        <th>Full Name</th>
        <th>Position</th>
        <th>Department</th>
        <th>Check-in Time</th>
        <th>Check-out Time</th>
    </tr>";
$sn = 1;
while ($row = $result->fetch_assoc()) {
    echo "<tr>
        <td>{$sn}</td>
        <td>{$row['full_name']}</td>
        <td>{$row['position']}</td>
        <td>{$row['department']}</td>
        <td>{$row['check_in_time']}</td>
        <td>{$row['check_out_time']}</td>
    </tr>";
    $sn++;
}
echo "</table>";
?>
