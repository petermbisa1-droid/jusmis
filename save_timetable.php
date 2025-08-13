<?php
include 'config/db.php';

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);

$session_id = intval($data['session_id']);
$class_id = intval($data['class_id']);
$mode = $conn->real_escape_string($data['mode']);
$entries = $data['entries'];

if (empty($entries)) {
    echo json_encode(["success" => false, "message" => "No timetable entries provided."]);
    exit;
}

// Check for Duplicate Time Conflicts within the Submitted Entries
$conflicts = [];
$time_slots = [];

foreach ($entries as $entry) {
    $key = $entry['day'] . '-' . $entry['start_time'] . '-' . $entry['end_time'];
    if (in_array($key, $time_slots)) {
        $conflicts[] = "Duplicate slot found: " . $entry['day'] . " " . $entry['start_time'] . " - " . $entry['end_time'];
    } else {
        $time_slots[] = $key;
    }
}

if (!empty($conflicts)) {
    echo json_encode(["success" => false, "message" => implode("\n", $conflicts)]);
    exit;
}

// Proceed to Save Timetable
$conn->begin_transaction();

try {
    // Delete existing entries for the same class/session/mode
    $stmt = $conn->prepare("DELETE FROM timetable WHERE session_id = ? AND class_id = ? AND mode = ?");
    $stmt->bind_param("iis", $session_id, $class_id, $mode);
    $stmt->execute();
    $stmt->close();

    // Insert new entries
    $stmt = $conn->prepare("
        INSERT INTO timetable (session_id, class_id, mode, day, start_time, end_time, course_id, classroom, lecturer_initials) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    foreach ($entries as $entry) {
        $stmt->bind_param(
            "iissssiss",
            $session_id,
            $class_id,
            $mode,
            $entry['day'],
            $entry['start_time'],
            $entry['end_time'],
            $entry['course_id'],
            $entry['classroom'],
            $entry['lecturer_initials']
        );
        $stmt->execute();
    }

    $stmt->close();
    $conn->commit();

    echo json_encode(["success" => true, "message" => "Timetable saved successfully."]);

} catch(Exception $e) {
    $conn->rollback();
    echo json_encode(["success" => false, "message" => "Error saving timetable: " . $e->getMessage()]);
}
?>
