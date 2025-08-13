<?php
// fetch_registered_students.php
include 'config/db.php';

$course_id = intval($_GET['course_id'] ?? 0);
$session_id = intval($_GET['session_id'] ?? 0);
$class_id = intval($_GET['class_id'] ?? 0);

header('Content-Type: application/json');

if (!$course_id || !$session_id || !$class_id) {
    echo json_encode([]);
    exit;
}

// Get students registered in the given course, class, session via registrations + class_courses + grades or direct join

// We assume:
// - registrations table has student_id, class_id, session_id
// - class_courses links class_id and course_id

// Query students who are registered in the class and session, and class_courses links class + course

$query = "
SELECT DISTINCT s.id, s.full_name
FROM students s
INNER JOIN registrations r ON s.id = r.student_id
INNER JOIN class_courses cc ON r.class_id = cc.class_id
WHERE cc.course_id = ? AND r.session_id = ? AND r.class_id = ?
AND s.status = 'active'
ORDER BY s.full_name
";

$stmt = $conn->prepare($query);
$stmt->bind_param("iii", $course_id, $session_id, $class_id);
$stmt->execute();
$result = $stmt->get_result();

$students = [];
while ($row = $result->fetch_assoc()) {
    $students[] = ['id' => $row['id'], 'full_name' => $row['full_name']];
}
$stmt->close();

echo json_encode($students);
