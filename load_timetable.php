<?php
include 'config/db.php';

$session_id = intval($_GET['session_id']);
$class_id = intval($_GET['class_id']);
$mode = $_GET['mode'];

$stmt = $conn->prepare("
    SELECT t.*, c.course_code, c.course_name
    FROM timetable t
    JOIN courses c ON t.course_id = c.id
    WHERE t.session_id = ? AND t.class_id = ? AND t.mode = ?
    ORDER BY FIELD(day, 'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'), start_time
");
$stmt->bind_param("iis", $session_id, $class_id, $mode);
$stmt->execute();
$result = $stmt->get_result();

$timetable = [];
while ($row = $result->fetch_assoc()) {
    $timetable[] = [
        'day' => $row['day'],
        'start_time' => $row['start_time'],
        'end_time' => $row['end_time'],
        'course_id' => $row['course_id'],
        'course_code' => $row['course_code'],
        'course_name' => $row['course_name'],
        'classroom' => $row['classroom'],
        'lecturer_initials' => $row['lecturer_initials']
    ];
}

echo json_encode($timetable);
?>
