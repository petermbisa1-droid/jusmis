<?php
require 'vendor/autoload.php';
include 'config/db.php';

use TCPDF;

// Same Filter Logic as student_enrolment_report.php
$academic_year_id = isset($_GET['academic_year_id']) ? intval($_GET['academic_year_id']) : 0;
$session_id = isset($_GET['session_id']) ? intval($_GET['session_id']) : 0;
$class_id = isset($_GET['class_id']) ? intval($_GET['class_id']) : 0;
$programme_id = isset($_GET['programme_id']) ? intval($_GET['programme_id']) : 0;

$where = "WHERE 1=1";
$params = [];
$types = '';

if ($academic_year_id) { $where .= " AND r.academic_year_id = ?"; $params[] = $academic_year_id; $types .= 'i'; }
if ($session_id) { $where .= " AND r.session_id = ?"; $params[] = $session_id; $types .= 'i'; }
if ($class_id) { $where .= " AND r.class_id = ?"; $params[] = $class_id; $types .= 'i'; }
if ($programme_id) { $where .= " AND r.programme_id = ?"; $params[] = $programme_id; $types .= 'i'; }

$query = "
    SELECT s.full_name, s.registration_number, s.phone, c.class_name, p.programme_name, se.session_name, ay.year_name
    FROM registrations r
    INNER JOIN students s ON r.student_id = s.id
    INNER JOIN classes c ON r.class_id = c.id
    INNER JOIN programmes p ON r.programme_id = p.id
    INNER JOIN sessions se ON r.session_id = se.id
    INNER JOIN academic_years ay ON r.academic_year_id = ay.id
    $where
    ORDER BY s.full_name ASC
";

$stmt = $conn->prepare($query);
if ($params) $stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

// TCPDF Init
$pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
$pdf->SetTitle('Student Enrolment Report');
$pdf->AddPage();
$pdf->SetFont('helvetica', '', 10);

$html = '<h2 style="text-align:center;">Student Enrolment Report</h2>';
$html .= '<table border="1" cellpadding="5">
<thead>
<tr style="background-color:#f2f2f2;">
<th>SN</th><th>Full Name</th><th>Reg. Number</th><th>Phone</th><th>Class</th><th>Programme</th><th>Session</th><th>Academic Year</th>
</tr>
</thead><tbody>';

$sn = 1;
while ($row = $result->fetch_assoc()) {
    $html .= '<tr>
        <td align="center">' . $sn++ . '</td>
        <td>' . htmlspecialchars($row['full_name']) . '</td>
        <td>' . htmlspecialchars($row['registration_number']) . '</td>
        <td>' . htmlspecialchars($row['phone']) . '</td>
        <td>' . htmlspecialchars($row['class_name']) . '</td>
        <td>' . htmlspecialchars($row['programme_name']) . '</td>
        <td>' . htmlspecialchars($row['session_name']) . '</td>
        <td>' . htmlspecialchars($row['year_name']) . '</td>
    </tr>';
}

if ($sn === 1) {
    $html .= '<tr><td colspan="8" align="center">No records found.</td></tr>';
}

$html .= '</tbody></table>';

$pdf->writeHTML($html, true, false, false, false, '');
$pdf->Output('student_enrolment_report.pdf', 'I');
exit;
?>
