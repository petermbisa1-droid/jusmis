<?php
require __DIR__ . '/vendor/autoload.php';  // Composer autoload for TCPDF
include 'config/db.php';

// Fetch Filters
$from_date = isset($_GET['from_date']) ? $_GET['from_date'] : '';
$to_date = isset($_GET['to_date']) ? $_GET['to_date'] : '';
$staff_id = isset($_GET['staff_id']) ? intval($_GET['staff_id']) : 0;

// Build WHERE conditions dynamically
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

// Initialize TCPDF in Portrait
$pdf = new \TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
$pdf->SetCreator('Jubilee University');
$pdf->SetAuthor('Jubilee University');
$pdf->SetTitle('Staff Attendance Report');
$pdf->SetHeaderData('', 0, 'Jubilee University', 'Staff Attendance Report');
$pdf->setHeaderFont(['helvetica', '', 12]);
$pdf->setFooterFont(['helvetica', '', 10]);
$pdf->SetMargins(10, 30, 10);
$pdf->SetHeaderMargin(10);
$pdf->SetFooterMargin(10);
$pdf->SetAutoPageBreak(TRUE, 20);
$pdf->AddPage();
$pdf->SetFont('helvetica', '', 10);

// Clear any accidental output buffering
if (ob_get_length()) ob_end_clean();

// Title Block
$pdf->SetFont('helvetica', 'B', 14);
$pdf->Cell(0, 10, 'Staff Attendance Report', 0, 1, 'C');
$pdf->Ln(2);

// Filter Info
$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(0, 6, 'From: ' . ($from_date ?: 'ALL') . '  To: ' . ($to_date ?: 'ALL'), 0, 1, 'C');
if ($staff_id) {
    $pdf->Cell(0, 6, 'Filtered by Staff ID: ' . $staff_id, 0, 1, 'C');
}
$pdf->Ln(5);

// Define Column Widths (Portrait A4)
$colWidths = [
    'sn' => 10,
    'full_name' => 45,
    'position' => 35,
    'department' => 35,
    'check_in' => 35,
    'check_out' => 35,
];

// Table Header
$pdf->SetFont('helvetica', 'B', 10);
$pdf->SetFillColor(220, 220, 220);

$pdf->MultiCell($colWidths['sn'], 8, 'SN', 1, 'C', 1, 0);
$pdf->MultiCell($colWidths['full_name'], 8, 'Full Name', 1, 'C', 1, 0);
$pdf->MultiCell($colWidths['position'], 8, 'Position', 1, 'C', 1, 0);
$pdf->MultiCell($colWidths['department'], 8, 'Department', 1, 'C', 1, 0);
$pdf->MultiCell($colWidths['check_in'], 8, 'Check-In Time', 1, 'C', 1, 0);
$pdf->MultiCell($colWidths['check_out'], 8, 'Check-Out Time', 1, 'C', 1, 1);

// Table Data Rows
$pdf->SetFont('helvetica', '', 9);
$sn = 1;

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $pdf->MultiCell($colWidths['sn'], 7, $sn++, 1, 'C', 0, 0);
        $pdf->MultiCell($colWidths['full_name'], 7, $row['full_name'], 1, 'L', 0, 0);
        $pdf->MultiCell($colWidths['position'], 7, $row['position'], 1, 'L', 0, 0);
        $pdf->MultiCell($colWidths['department'], 7, $row['department'], 1, 'L', 0, 0);
        $pdf->MultiCell($colWidths['check_in'], 7, date('Y-m-d H:i', strtotime($row['check_in_time'])), 1, 'C', 0, 0);
        $pdf->MultiCell($colWidths['check_out'], 7, $row['check_out_time'] ? date('Y-m-d H:i', strtotime($row['check_out_time'])) : '-', 1, 'C', 0, 1);
    }

    // Total Records Count
    $pdf->Ln(3);
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(0, 6, 'Total Records: ' . ($sn - 1), 0, 1, 'R');
} else {
    $pdf->MultiCell(array_sum($colWidths), 7, 'No Attendance Records Found.', 1, 'C', 0, 1);
}

// Output PDF
$pdf->Output('staff_attendance_report.pdf', 'I');
exit;
?>
