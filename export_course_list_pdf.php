<?php
session_start();
include 'config/db.php';
require 'vendor/autoload.php'; // Ensure TCPDF is installed via Composer

use TCPDF;

if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['role'], ['superuser', 'registrar', 'vicechancellor', 'academic'])) {
    die("Unauthorized access.");
}

$session_id = isset($_GET['session_id']) ? intval($_GET['session_id']) : 0;
$academic_year_id = isset($_GET['academic_year_id']) ? intval($_GET['academic_year_id']) : 0;
$course_id = isset($_GET['course_id']) ? intval($_GET['course_id']) : 0;

if (!$session_id || !$academic_year_id || !$course_id) {
    die("Missing required parameters.");
}

// Fetch Session, Academic Year, Course Name
$session_name = '';
$academic_year_name = '';
$course_name = '';

$stmt = $conn->prepare("SELECT session_name FROM sessions WHERE id = ?");
$stmt->bind_param('i', $session_id);
$stmt->execute();
$stmt->bind_result($session_name);
$stmt->fetch();
$stmt->close();

$stmt = $conn->prepare("SELECT year_name FROM academic_years WHERE id = ?");
$stmt->bind_param('i', $academic_year_id);
$stmt->execute();
$stmt->bind_result($academic_year_name);
$stmt->fetch();
$stmt->close();

$stmt = $conn->prepare("SELECT course_name FROM courses WHERE id = ?");
$stmt->bind_param('i', $course_id);
$stmt->execute();
$stmt->bind_result($course_name);
$stmt->fetch();
$stmt->close();

// Fetch Students in Course
$stmt = $conn->prepare("
    SELECT s.full_name, s.registration_number
    FROM registration_courses rc
    INNER JOIN registrations r ON rc.registration_id = r.id
    INNER JOIN students s ON r.student_id = s.id
    WHERE r.session_id = ? AND r.academic_year_id = ? AND rc.course_id = ?
    ORDER BY s.full_name ASC
");
$stmt->bind_param('iii', $session_id, $academic_year_id, $course_id);
$stmt->execute();
$result = $stmt->get_result();

// Initialize TCPDF
$pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
$pdf->SetCreator('Jubilee University');
$pdf->SetAuthor('Jubilee University');
$pdf->SetTitle('Course List Report');
$pdf->SetHeaderData('', 0, 'Jubilee University', "Course List Report", [0,64,255], [0,64,128]);
$pdf->setHeaderFont(Array('helvetica', '', 12));
$pdf->setFooterFont(Array('helvetica', '', 10));
$pdf->SetMargins(15, 30, 15);
$pdf->SetHeaderMargin(10);
$pdf->SetFooterMargin(10);
$pdf->SetAutoPageBreak(TRUE, 15);
$pdf->AddPage();

// Title Block
$pdf->SetFont('helvetica', 'B', 14);
$pdf->Cell(0, 10, 'Course List Report', 0, 1, 'C');
$pdf->Ln(2);

$pdf->SetFont('helvetica', '', 11);
$pdf->Cell(0, 6, 'Course: ' . $course_name, 0, 1, 'L');
$pdf->Cell(0, 6, 'Session: ' . $session_name, 0, 1, 'L');
$pdf->Cell(0, 6, 'Academic Year: ' . $academic_year_name, 0, 1, 'L');
$pdf->Ln(5);

// Table Column Widths
$w_sn = 20;    // SN width in mm
$w_name = 100; // Full Name width in mm
$w_reg = 50;   // Registration Number width in mm

// Table Headers
$pdf->SetFont('helvetica', 'B', 11);
$pdf->Cell($w_sn, 8, 'SN', 1, 0, 'C');
$pdf->Cell($w_name, 8, 'Full Name', 1, 0, 'C');
$pdf->Cell($w_reg, 8, 'Registration Number', 1, 1, 'C');

// Table Data
$pdf->SetFont('helvetica', '', 10);
$sn = 1;
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $pdf->Cell($w_sn, 7, $sn++, 1, 0, 'C');
        $pdf->Cell($w_name, 7, $row['full_name'], 1, 0, 'L');
        $pdf->Cell($w_reg, 7, $row['registration_number'], 1, 1, 'L');
    }
} else {
    $pdf->Cell($w_sn + $w_name + $w_reg, 7, 'No students registered for this course.', 1, 1, 'C');
}

// Output PDF
$pdf->Output('course_list_report.pdf', 'I');
exit;
?>
