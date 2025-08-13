<?php
require_once('vendor/autoload.php'); // Make sure TCPDF is autoloaded
include 'config/db.php';

$class_id = $_GET['class_id'] ?? '';
$academic_year_id = $_GET['academic_year_id'] ?? '';
$session_id = $_GET['session_id'] ?? '';

if (!$class_id || !$academic_year_id || !$session_id) {
    die('Missing required parameters.');
}

// Initialize TCPDF
$pdf = new TCPDF('L', PDF_UNIT, 'A4', true, 'UTF-8', false);
$pdf->SetCreator('Senate Report System');
$pdf->SetAuthor('Your University');
$pdf->SetTitle('Senate Report');
$pdf->SetMargins(10, 10, 10);
$pdf->AddPage();

// --- HEADER SECTION with Logo & Titles ---
$logoPath = 'assets/images/university_logo.png'; // Update path as needed
if (file_exists($logoPath)) {
    $pdf->Image($logoPath, 10, 10, 30); // (x, y, width)
}

$pdf->SetY(15);
$pdf->SetFont('helvetica', 'B', 16);
$pdf->Cell(0, 10, 'JUBILEE UNIVERSITY', 0, 1, 'C');

$pdf->SetFont('helvetica', '', 12);
$pdf->Cell(0, 7, 'Office of the Registrar', 0, 1, 'C');

$pdf->SetFont('helvetica', 'B', 14);
$pdf->Cell(0, 10, 'Pre-Senate Report', 0, 1, 'C');

// Fetch Class Name from classes.class_name
$class_sql = "SELECT class_name FROM classes WHERE id = ?";
$stmt = $conn->prepare($class_sql);
$stmt->bind_param('i', $class_id);
$stmt->execute();
$stmt->bind_result($class_name);
$stmt->fetch();
$stmt->close();

$pdf->SetFont('helvetica', '', 11);
$pdf->Cell(0, 7, "Class: $class_name", 0, 1, 'C');

$pdf->Ln(10);

// --- FETCH COURSES ---
$courses_sql = "
    SELECT DISTINCT c.id, c.course_code, c.course_name
    FROM grades g
    JOIN courses c ON g.course_id = c.id
    JOIN students s ON g.student_id = s.id
    WHERE s.class_id = ? AND g.academic_year_id = ? AND g.session_id = ?
    ORDER BY c.course_code
";
$stmt = $conn->prepare($courses_sql);
$stmt->bind_param('iii', $class_id, $academic_year_id, $session_id);
$stmt->execute();
$courses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// --- Course Reference Table ---
$html = '<h3>Course Reference</h3>';
$html .= '<table border="1" cellpadding="3" cellspacing="0" width="50%">';
$html .= '<thead><tr>
            <th width="10%" align="center"><b>SN</b></th>
            <th width="25%" align="center"><b>Course Code</b></th>
            <th width="65%" align="center"><b>Course Name</b></th>
          </tr></thead><tbody>';

$sn = 1;
foreach ($courses as $course) {
    $html .= '<tr>';
    $html .= '<td align="center" width="10%">' . $sn++ . '</td>';
    $html .= '<td align="center" width="25%">' . htmlspecialchars($course['course_code']) . '</td>';
    $html .= '<td align="left" width="65%">' . htmlspecialchars($course['course_name']) . '</td>';
    $html .= '</tr>';
}
$html .= '</tbody></table><br><br>';

// --- FETCH GRADES ---
$grades_sql = "
    SELECT s.id AS student_id, s.full_name, s.registration_number,
           p.programme_name, g.course_id, g.cw1, g.exam, g.final_score, g.grade_letter
    FROM grades g
    JOIN students s ON g.student_id = s.id
    JOIN programmes p ON s.programme_id = p.id
    WHERE s.class_id = ? AND g.academic_year_id = ? AND g.session_id = ?
    ORDER BY s.full_name, g.course_id
";
$stmt = $conn->prepare($grades_sql);
$stmt->bind_param('iii', $class_id, $academic_year_id, $session_id);
$stmt->execute();
$grades_result = $stmt->get_result();
$stmt->close();

$students = [];
$failCounts = [];

while ($row = $grades_result->fetch_assoc()) {
    $sid = $row['student_id'];
    if (!isset($students[$sid])) {
        $students[$sid] = [
            'full_name' => $row['full_name'],
            'registration_number' => $row['registration_number'],
            'programme_name' => $row['programme_name'],
            'grades' => [],
        ];
        $failCounts[$sid] = 0;
    }
    $students[$sid]['grades'][$row['course_id']] = $row;

    if (is_numeric($row['final_score']) && $row['final_score'] < 45) {
        $failCounts[$sid]++;
    }
}

// Summary Counters
$totalStudents = count($students);
$rcCount = 0;
$supCount = 0;
$rsCount = 0;
$ppCount = 0;

foreach ($failCounts as $sid => $failCount) {
    if ($failCount == 0) {
        $ppCount++;
    } elseif ($failCount <= 3) {
        $supCount++;
    } else {
        $rsCount++;
    }
}

// --- Main Grades Table ---
$html .= '<table border="1" cellpadding="3" cellspacing="0" width="100%">
<thead>
<tr>
    <th rowspan="2" align="center" width="3%"><b>#</b></th>
    <th rowspan="2" align="left" width="23%"><b>Student Name</b></th>
    <th rowspan="2" align="center" width="12%"><b>Reg #</b></th>';

foreach ($courses as $course) {
    $html .= '<th colspan="3" align="center" width="9%"><b>' . htmlspecialchars($course['course_code']) . '</b></th>';
}

$html .= '<th rowspan="2" align="center" width="5%"><b>AVG</b></th>';
$html .= '</tr><tr>';

foreach ($courses as $course) {
    $html .= '<th align="center" width="3%">CW</th><th align="center" width="3%">EX</th><th align="center" width="3%">FG</th>';
}

$html .= '</tr></thead><tbody>';

$sn = 1;
foreach ($students as $student) {
    $totalFinal = 0;
    $courseCount = count($courses);

    $html .= '<tr>';
    $html .= '<td align="center" width="3%">' . $sn++ . '</td>';
    $html .= '<td align="left" width="23%">' . htmlspecialchars($student['full_name']) . '</td>';
    $html .= '<td align="center" width="12%">' . htmlspecialchars($student['registration_number']) . '</td>';

    foreach ($courses as $course) {
        $grade = $student['grades'][$course['id']] ?? null;
        $cw = $grade['cw1'] ?? '-';
        $exam = $grade['exam'] ?? '-';
        $fg = $grade['final_score'] ?? '-';
        if (is_numeric($fg)) $totalFinal += $fg;

        $html .= '<td align="center" width="3%">' . $cw . '</td>';
        $html .= '<td align="center" width="3%">' . $exam . '</td>';
        $html .= '<td align="center" width="3%">' . $fg . '</td>';
    }

    $avg = $courseCount ? round($totalFinal / $courseCount, 2) : 0;
    $html .= '<td align="center" width="5%">' . $avg . '</td>';
    $html .= '</tr>';
}

$html .= '</tbody></table>';

// --- Summary Table ---
$pdf->SetFont('helvetica', '', 10);  // Set font size to 10

$html .= '<br><br><h3 style="font-size:12px;">Summary</h3>';  // Slightly bigger heading for summary
$html .= '<table border="1" cellpadding="5" cellspacing="0" width="40%" style="font-size:10px;">';
$html .= '<tbody>';
$html .= '<tr><td width="60%"><b>All Students</b></td><td width="40%" align="center">' . $totalStudents . '</td></tr>';
$html .= '<tr><td><b>RC - Repeat Course</b></td><td align="center">N/A</td></tr>';
$html .= '<tr><td><b>SUP - Supplementary Exams</b></td><td align="center">' . $supCount . '</td></tr>';
$html .= '<tr><td><b>RS - Repeat Semester</b></td><td align="center">' . $rsCount . '</td></tr>';
$html .= '<tr><td><b>PP - Pass and Proceed</b></td><td align="center">' . $ppCount . '</td></tr>';
$html .= '</tbody></table>';

$pdf->writeHTML($html, true, false, true, false, '');
$pdf->Output('Senate_Report.pdf', 'D');
?>
