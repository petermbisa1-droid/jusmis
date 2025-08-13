<?php
session_start();

// Adjust this path if your script location is different from project root
require_once __DIR__ . '/config/db.php';

// Use absolute path for autoload to avoid path issues
$autoloadPath = __DIR__ . '/vendor/autoload.php';

if (!file_exists($autoloadPath)) {
    die("Composer autoload.php not found. Run 'composer install' in your project root.");
}

require_once $autoloadPath;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Debugging: check if class exists
if (!class_exists('PhpOffice\PhpSpreadsheet\Spreadsheet')) {
    die("PhpSpreadsheet class not found. Check your Composer installation.");
}

// Check user role authorization
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['role'], ['superuser', 'registrar', 'vicechancellor', 'academic'])) {
    die("Unauthorized access.");
}

// Get filters safely
$session_id = isset($_GET['session_id']) ? intval($_GET['session_id']) : 0;
$academic_year_id = isset($_GET['academic_year_id']) ? intval($_GET['academic_year_id']) : 0;
$class_id = isset($_GET['class_id']) ? intval($_GET['class_id']) : 0;

// Build query
$query = "
    SELECT s.full_name, s.sex AS gender, s.email, c.class_name, se.session_name, ay.year_name
    FROM registrations r
    INNER JOIN students s ON r.student_id = s.id
    INNER JOIN classes c ON r.class_id = c.id
    INNER JOIN sessions se ON c.session_id = se.id
    INNER JOIN academic_years ay ON c.academic_year_id = ay.id
    WHERE 1=1
";

$params = [];
$types = '';

if ($session_id > 0) {
    $query .= " AND c.session_id = ?";
    $params[] = $session_id;
    $types .= 'i';
}

if ($academic_year_id > 0) {
    $query .= " AND c.academic_year_id = ?";
    $params[] = $academic_year_id;
    $types .= 'i';
}

if ($class_id > 0) {
    $query .= " AND c.id = ?";
    $params[] = $class_id;
    $types .= 'i';
}

$query .= " ORDER BY s.full_name ASC";

$stmt = $conn->prepare($query);
if ($stmt === false) {
    die("Prepare failed: " . $conn->error);
}

if ($params) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

// Create Spreadsheet and set metadata
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Class List Report');

// Set headers in Excel sheet
$headers = ['No', 'Full Name', 'Gender', 'Email', 'Class', 'Session', 'Academic Year'];
$colIndex = 'A';
foreach ($headers as $header) {
    $sheet->setCellValue($colIndex . '1', $header);
    $colIndex++;
}

// Fill data rows
$rowNum = 2;
$sn = 1;
while ($row = $result->fetch_assoc()) {
    $sheet->setCellValue("A$rowNum", $sn++);
    $sheet->setCellValue("B$rowNum", $row['full_name']);
    $sheet->setCellValue("C$rowNum", $row['gender']);
    $sheet->setCellValue("D$rowNum", $row['email']);
    $sheet->setCellValue("E$rowNum", $row['class_name']);
    $sheet->setCellValue("F$rowNum", $row['session_name']);
    $sheet->setCellValue("G$rowNum", $row['year_name']);
    $rowNum++;
}

// Auto size columns
foreach (range('A', 'G') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

// Clear output buffer before sending Excel file
if (ob_get_length()) {
    ob_end_clean();
}

// Send headers to prompt download
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="class_list_report.xlsx"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
