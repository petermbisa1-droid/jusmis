<?php
require_once __DIR__ . '/vendor/autoload.php';
include 'config/db.php';

// Filters
$academic_year_id = $_GET['academic_year_id'] ?? '';
$session_id = $_GET['session_id'] ?? '';
$course_id = $_GET['course_id'] ?? '';
$filter_type = $_GET['filter_type'] ?? '';
$filter_date = $_GET['filter_date'] ?? '';

// SQL Query
$sql = "
SELECT 
    st.registration_number,
    st.full_name,
    ay.year_name AS academic_year,
    se.session_name,
    c.course_code,
    c.course_name,
    a.attendance_date,
    a.start_time,
    a.end_time,
    a.status
FROM attendance a
JOIN registration_courses rc ON a.registration_course_id = rc.id
JOIN registrations r ON rc.registration_id = r.id
JOIN students st ON r.student_id = st.id
JOIN courses c ON rc.course_id = c.id
JOIN academic_years ay ON r.academic_year_id = ay.id
JOIN sessions se ON r.session_id = se.id
WHERE 1 = 1
";

if (!empty($academic_year_id)) $sql .= " AND ay.id = " . intval($academic_year_id);
if (!empty($session_id)) $sql .= " AND se.id = " . intval($session_id);
if (!empty($course_id)) $sql .= " AND c.id = " . intval($course_id);
if (!empty($filter_date) && !empty($filter_type)) {
    $fd = $conn->real_escape_string($filter_date);
    switch ($filter_type) {
        case 'day': $sql .= " AND a.attendance_date = '$fd'"; break;
        case 'week': $sql .= " AND YEARWEEK(a.attendance_date, 1) = YEARWEEK('$fd', 1)"; break;
        case 'month': $sql .= " AND MONTH(a.attendance_date) = MONTH('$fd') AND YEAR(a.attendance_date) = YEAR('$fd')"; break;
    }
}
$sql .= " ORDER BY a.attendance_date DESC, a.start_time DESC";
$result = $conn->query($sql);

// TCPDF setup
$pdf = new TCPDF('L', PDF_UNIT, 'A4', true, 'UTF-8', false);
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor('Jubilee University');
$pdf->SetTitle('Student Attendance Report');
$pdf->SetMargins(10, 15, 10);
$pdf->AddPage();
$pdf->SetFont('helvetica', '', 9);

// Title
$pdf->SetFont('', 'B', 12);
$pdf->Cell(0, 10, '📘 Student Attendance Report', 0, 1, 'L');
$pdf->Ln(2);
$pdf->SetFont('', '', 9);

// Adjusted Column Widths (Total = 250mm)
$w = [
    10,  // SN
    28,  // Reg No
    45,  // Full Name
    20,  // Academic Year
    20,  // Session
    22,  // Course Code
    35,  // Course Name
    20,  // Date
    16,  // Start
    16,  // End
    18   // Status
];

$headers = ['SN', 'Reg No', 'Full Name', 'Academic Year', 'Session', 'Course Code', 'Course Name', 'Date', 'Start', 'End', 'Status'];

// Header Row
$pdf->SetFillColor(230, 230, 230);
$pdf->SetTextColor(0);
$pdf->SetDrawColor(180, 180, 180);
$pdf->SetLineWidth(0.3);
$pdf->SetFont('', 'B');

foreach ($headers as $i => $header) {
    $pdf->MultiCell($w[$i], 7, $header, 1, 'C', 1, 0, '', '', true, 0, false, true, 7, 'M');
}
$pdf->Ln();

// Data Rows
$pdf->SetFont('', '');
$sn = 1;
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $bg = $row['status'] == 'present' ? [212, 237, 218] : [248, 215, 218];
        $pdf->SetFillColor(...$bg);
        $pdf->SetTextColor(0);

        $data = [
            $sn++,
            $row['registration_number'],
            $row['full_name'],
            $row['academic_year'],
            $row['session_name'],
            $row['course_code'],
            $row['course_name'],
            $row['attendance_date'],
            $row['start_time'],
            $row['end_time'],
            ucfirst($row['status'])
        ];

        foreach ($data as $i => $value) {
            $pdf->MultiCell($w[$i], 6, $value, 1, 'L', 1, 0, '', '', true, 0, false, true, 6, 'M');
        }
        $pdf->Ln();
    }
} else {
    $pdf->MultiCell(array_sum($w), 10, 'No attendance records found.', 1, 'C', 0, 1);
}

// Output
$pdf->Output('attendance_report.pdf', 'I');
