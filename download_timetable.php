<?php
include 'config/db.php';

$mode = $_GET['mode'] ?? 'normal';  // Default mode if not provided

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="timetable_' . $mode . '.csv"');

$output = fopen('php://output', 'w');
fputcsv($output, ['Day', 'Start Time', 'End Time', 'Course Code', 'Course Name', 'Classroom', 'Lecturer Initials']);

// Fetch timetable data by Mode (no class_id involved)
$result = $conn->query("
    SELECT t.*, c.course_code, c.course_name 
    FROM timetable t
    JOIN courses c ON t.course_id = c.id
    WHERE t.mode = '$mode'
    ORDER BY FIELD(t.day, 'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'), t.start_time
");

while ($row = $result->fetch_assoc()) {
    fputcsv($output, [
        $row['day'],
        $row['start_time'],
        $row['end_time'],
        $row['course_code'],
        $row['course_name'],
        $row['classroom'],
        $row['lecturer_initials']
    ]);
}

fclose($output);
?>
