<?php
session_start();
ini_set('display_errors', 0);
error_reporting(0);

include 'config/db.php';
require 'vendor/autoload.php'; // TCPDF via Composer

use TCPDF;

if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['role'], ['superuser', 'registrar', 'vicechancellor', 'academic'])) {
    die("Unauthorized access.");
}

$session_id = isset($_GET['session_id']) ? intval($_GET['session_id']) : 0;
$academic_year_id = isset($_GET['academic_year_id']) ? intval($_GET['academic_year_id']) : 0;
$class_id = isset($_GET['class_id']) ? intval($_GET['class_id']) : 0;

if (!$session_id || !$academic_year_id || !$class_id) {
    die("Missing required parameters.");
}

// Fetch Names
function fetchSingleValue($conn, $query, $id) {
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->bind_result($value);
    $stmt->fetch();
    $stmt->close();
    return $value;
}

$session_name = fetchSingleValue($conn, "SELECT session_name FROM sessions WHERE id = ?", $session_id);
$academic_year_name = fetchSingleValue($conn, "SELECT year_name FROM academic_years WHERE id = ?", $academic_year_id);
$class_name = fetchSingleValue($conn, "SELECT class_name FROM classes WHERE id = ?", $class_id);

// Fetch Students in Class with Phone
$query = "
    SELECT s.full_name, s.sex AS gender, s.email, s.phone
    FROM registrations r
    INNER JOIN students s ON r.student_id = s.id
    WHERE r.class_id = ? AND r.session_id = ? AND r.academic_year_id = ?
    ORDER BY s.full_name ASC
";

$stmt = $conn->prepare($query);
$stmt->bind_param('iii', $class_id, $session_id, $academic_year_id);
$stmt->execute();
$result = $stmt->get_result();

// Initialize TCPDF in Landscape
$pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
$pdf->SetCreator('Jubilee University');
$pdf->SetAuthor('Jubilee University');
$pdf->SetTitle('Class List Report');
$pdf->SetHeaderData('', 0, 'Jubilee University', "Class List Report", [0,64,255], [0,64,128]);
$pdf->setHeaderFont(Array('helvetica', '', 12));
$pdf->setFooterFont(Array('helvetica', '', 10));
$pdf->SetMargins(10, 30, 10);
$pdf->SetHeaderMargin(10);
$pdf->SetFooterMargin(10);
$pdf->SetAutoPageBreak(TRUE, 20);
$pdf->AddPage('L');
$pdf->SetFont('helvetica', '', 11);

// Title Block
$pdf->SetFont('helvetica', 'B', 14);
$pdf->Cell(0, 10, 'Class List Report', 0, 1, 'C');
$pdf->Ln(2);

$pdf->SetFont('helvetica', '', 11);
$pdf->Cell(0, 6, 'Class: ' . $class_name, 0, 1, 'L');
$pdf->Cell(0, 6, 'Session: ' . $session_name, 0, 1, 'L');
$pdf->Cell(0, 6, 'Academic Year: ' . $academic_year_name, 0, 1, 'L');
$pdf->Ln(5);

// Define Column Widths including Phone
$colWidths = [
    'sn' => 15,
    'full_name' => 70,
    'gender' => 20,
    'email' => 80,
    'phone' => 50,
];

// Draw Table Header
$pdf->SetFont('helvetica', 'B', 11);
$pdf->SetFillColor(220, 220, 220);

$pdf->MultiCell($colWidths['sn'], 8, 'SN', 1, 'C', 1, 0);
$pdf->MultiCell($colWidths['full_name'], 8, 'Full Name', 1, 'C', 1, 0);
$pdf->MultiCell($colWidths['gender'], 8, 'Gender', 1, 'C', 1, 0);
$pdf->MultiCell($colWidths['email'], 8, 'Email', 1, 'C', 1, 0);
$pdf->MultiCell($colWidths['phone'], 8, 'Phone', 1, 'C', 1, 1);

// Draw Table Rows
$pdf->SetFont('helvetica', '', 10);
$sn = 1;

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $pdf->MultiCell($colWidths['sn'], 7, $sn++, 1, 'C', 0, 0);
        $pdf->MultiCell($colWidths['full_name'], 7, $row['full_name'], 1, 'L', 0, 0);
        $pdf->MultiCell($colWidths['gender'], 7, $row['gender'], 1, 'C', 0, 0);
        $pdf->MultiCell($colWidths['email'], 7, $row['email'], 1, 'L', 0, 0);
        $pdf->MultiCell($colWidths['phone'], 7, $row['phone'], 1, 'L', 0, 1);
    }
} else {
    $pdf->MultiCell(array_sum($colWidths), 7, 'No students found in this class.', 1, 'C', 0, 1);
}

// Output PDF
$pdf->Output('class_list_report.pdf', 'I');
exit;
?>
