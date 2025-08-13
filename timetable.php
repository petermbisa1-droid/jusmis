<?php
include 'config/db.php';
include 'includes/header.php';

// Modes of study
$modes = ['normal' => 'Normal', 'evening' => 'Evening', 'weekend' => 'Weekend', 'blended' => 'Blended'];

// Fetch active courses
$courses = [];
$result = $conn->query("SELECT id, course_code, course_name FROM courses WHERE is_active = 1 ORDER BY course_code");
while ($row = $result->fetch_assoc()) {
    $courses[] = $row;
}

// Build course lookup map for quick code/name lookup
$course_map = [];
foreach ($courses as $c) {
    $course_map[$c['id']] = ['code' => $c['course_code'], 'name' => $c['course_name']];
}

// Selected mode - default to 'normal'
$selected_mode = $_GET['mode'] ?? 'normal';
if (!array_key_exists($selected_mode, $modes)) {
    $selected_mode = 'normal';
}

$message = '';
$message_class = '';

// --- NEW: Fetch current active session and academic year ---
$activeSessionId = null;
$activeAcademicYearId = null;

$res = $conn->query("SELECT id FROM sessions WHERE is_active = 1 LIMIT 1");
if ($row = $res->fetch_assoc()) {
    $activeSessionId = $row['id'];
}
$res = $conn->query("SELECT id FROM academic_years WHERE is_active = 1 LIMIT 1");
if ($row = $res->fetch_assoc()) {
    $activeAcademicYearId = $row['id'];
}

if (!$activeSessionId || !$activeAcademicYearId) {
    die("Active session or academic year not configured.");
}

// Debug log file path
$debug_log_file = __DIR__ . '/conflict_debug.log';

function debug_log($msg) {
    global $debug_log_file;
    file_put_contents($debug_log_file, date('[Y-m-d H:i:s] ') . $msg . PHP_EOL, FILE_APPEND);
}

// Handle form submission to save timetable with conflict checks
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_timetable'])) {
    $mode = $_POST['mode'] ?? '';
    if (!array_key_exists($mode, $modes)) {
        $message = "Invalid mode of study selected.";
        $message_class = 'alert-danger';
    } else {
        $ids = $_POST['id'] ?? [];
        $days = $_POST['day'] ?? [];
        $start_times = $_POST['start_time'] ?? [];
        $end_times = $_POST['end_time'] ?? [];
        $course_ids = $_POST['course_id'] ?? [];
        $classrooms = $_POST['classroom'] ?? [];
        $lecturer_initials_arr = $_POST['lecturer_initials'] ?? [];

        $valid = true;
        $errors = [];

        // Prepare conflict checking base SQL (excluding id condition, will add dynamically)
        $conflict_check_sql_base = "
            SELECT t.*, c.course_code, c.course_name
            FROM timetable t
            LEFT JOIN courses c ON t.course_id = c.id
            WHERE t.mode = ? 
              AND t.day = ? 
              AND (
                (t.start_time < ? AND t.end_time > ?)
              )
              AND (
                LOWER(t.classroom) = LOWER(?)
                OR LOWER(c.course_code) = LOWER(?)
                OR LOWER(c.course_name) = LOWER(?)
                OR LOWER(t.lecturer_initials) = LOWER(?)
              )
              AND t.session_id = ?   -- NEW: filter by active session
              AND t.academic_year_id = ?  -- NEW: filter by active academic year
        ";

        for ($i = 0; $i < count($days); $i++) {
            $id = isset($ids[$i]) && is_numeric($ids[$i]) ? intval($ids[$i]) : null;

            $day = trim($days[$i]);
            $start_time = trim($start_times[$i]);
            $end_time = trim($end_times[$i]);
            $course_id = intval($course_ids[$i]);
            $classroom = trim($classrooms[$i]);
            $lecturer_initials = trim($lecturer_initials_arr[$i]);

            if (empty($day) || empty($start_time) || empty($end_time) || empty($course_id)) {
                $valid = false;
                $errors[] = "Row " . ($i + 1) . ": Missing required fields.";
                continue;
            }
            if ($start_time >= $end_time) {
                $valid = false;
                $errors[] = "Row " . ($i + 1) . ": Start time must be before end time.";
                continue;
            }

            $course_code_to_check = $course_map[$course_id]['code'] ?? '';
            $course_name_to_check = $course_map[$course_id]['name'] ?? '';

            if ($id !== null) {
                $conflict_check_sql = $conflict_check_sql_base . " AND t.id != ?";
                $stmtConflict = $conn->prepare($conflict_check_sql);
                if (!$stmtConflict) {
                    $valid = false;
                    $errors[] = "Row " . ($i + 1) . ": Failed to prepare conflict check statement.";
                    continue;
                }
                $stmtConflict->bind_param(
                    'ssssssssiii',
                    $mode,
                    $day,
                    $end_time,
                    $start_time,
                    $classroom,
                    $course_code_to_check,
                    $course_name_to_check,
                    $lecturer_initials,
                    $activeSessionId,        // NEW param
                    $activeAcademicYearId,   // NEW param
                    $id
                );
            } else {
                $stmtConflict = $conn->prepare($conflict_check_sql_base);
                if (!$stmtConflict) {
                    $valid = false;
                    $errors[] = "Row " . ($i + 1) . ": Failed to prepare conflict check statement.";
                    continue;
                }
                $stmtConflict->bind_param(
                    'ssssssssii',
                    $mode,
                    $day,
                    $end_time,
                    $start_time,
                    $classroom,
                    $course_code_to_check,
                    $course_name_to_check,
                    $lecturer_initials,
                    $activeSessionId,        // NEW param
                    $activeAcademicYearId    // NEW param
                );
            }

            debug_log("Checking conflict for row " . ($i + 1) . " with params: mode='{$mode}', day='{$day}', start_time<'{$end_time}', end_time>'{$start_time}', classroom='{$classroom}', course_code='{$course_code_to_check}', course_name='{$course_name_to_check}', lecturer_initials='{$lecturer_initials}', session_id={$activeSessionId}, academic_year_id={$activeAcademicYearId}, exclude_id=" . ($id ?? 'null'));

            $stmtConflict->execute();
            $conflict_res = $stmtConflict->get_result();

            while ($conflict_row = $conflict_res->fetch_assoc()) {
                $conflictMsg = "Row " . ($i + 1) . ": Conflict detected with existing entry - ";
                $conflictMsg .= "Day {$conflict_row['day']}, ";
                $conflictMsg .= "Time {$conflict_row['start_time']} to {$conflict_row['end_time']}, ";
                $conflictMsg .= "Course Code {$conflict_row['course_code']}, ";
                $conflictMsg .= "Classroom {$conflict_row['classroom']}, ";
                $conflictMsg .= "Lecturer {$conflict_row['lecturer_initials']}.";
                $errors[] = $conflictMsg;
                $valid = false;

                debug_log("New Entry: " . json_encode([
                    'id' => $id,
                    'day' => $day,
                    'start_time' => $start_time,
                    'end_time' => $end_time,
                    'course_id' => $course_id,
                    'classroom' => $classroom,
                    'lecturer_initials' => $lecturer_initials,
                ]));
                debug_log("Conflicting Existing Entry: " . json_encode($conflict_row));
            }
            $stmtConflict->close();
        }

        if ($valid) {
            // Delete existing timetable entries for this mode, session, academic year (overwrite)
            $stmtDel = $conn->prepare("DELETE FROM timetable WHERE mode = ? AND session_id = ? AND academic_year_id = ?");
            $stmtDel->bind_param('sii', $mode, $activeSessionId, $activeAcademicYearId);
            $stmtDel->execute();
            $stmtDel->close();

            // Insert new entries with session_id and academic_year_id
            $insert_stmt = $conn->prepare("INSERT INTO timetable (mode, day, start_time, end_time, course_id, classroom, lecturer_initials, session_id, academic_year_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            for ($i = 0; $i < count($days); $i++) {
                $day = trim($days[$i]);
                $start_time = trim($start_times[$i]);
                $end_time = trim($end_times[$i]);
                $course_id = intval($course_ids[$i]);
                $classroom = trim($classrooms[$i]);
                $lecturer_initials = trim($lecturer_initials_arr[$i]);

                if (empty($day) || empty($start_time) || empty($end_time) || empty($course_id)) {
                    continue;
                }

                $insert_stmt->bind_param(
                    'ssssissii',
                    $mode,
                    $day,
                    $start_time,
                    $end_time,
                    $course_id,
                    $classroom,
                    $lecturer_initials,
                    $activeSessionId,        // NEW
                    $activeAcademicYearId    // NEW
                );
                $insert_stmt->execute();
            }
            $insert_stmt->close();

            $message = "Timetable saved successfully for mode: " . htmlspecialchars($modes[$mode]) . " (Session and Academic Year auto-assigned)";
            $message_class = 'alert-success';
        } else {
            $message = "<strong>Conflicts detected:</strong><br>" . implode("<br>", $errors);
            $message_class = 'alert-danger';
        }
    }
}

// Fetch timetable entries for selected mode, session, academic year
$timetable_entries = [];
$stmt = $conn->prepare("SELECT t.id, t.day, t.start_time, t.end_time, t.course_id, t.classroom, t.lecturer_initials, c.course_code, c.course_name 
                        FROM timetable t 
                        LEFT JOIN courses c ON t.course_id = c.id 
                        WHERE t.mode = ? AND t.session_id = ? AND t.academic_year_id = ?
                        ORDER BY FIELD(t.day, 'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'), t.start_time");
$stmt->bind_param('sii', $selected_mode, $activeSessionId, $activeAcademicYearId);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $timetable_entries[] = $row;
}
$stmt->close();
?>

<div class="container my-4">
    <h2>Timetable Management Interface</h2>

    <?php if ($message): ?>
        <div class="alert <?= $message_class ?>"><?= $message ?></div>
    <?php endif; ?>

    <!-- Mode of Study Selection -->
    <form method="GET" class="mb-3 d-inline-block">
        <label for="mode-select">Select Mode of Study: </label>
        <select name="mode" id="mode-select" class="form-select d-inline-block w-auto mx-2" onchange="this.form.submit()">
            <?php foreach ($modes as $key => $label): ?>
                <option value="<?= $key ?>" <?= $key === $selected_mode ? 'selected' : '' ?>><?= $label ?></option>
            <?php endforeach; ?>
        </select>
    </form>

    <!-- Timetable Form -->
    <form method="POST" onsubmit="return validateForm();">
        <input type="hidden" name="mode" value="<?= htmlspecialchars($selected_mode) ?>">
        <input type="hidden" name="save_timetable" value="1">

        <table class="table table-bordered text-center">
            <thead class="table-primary">
                <tr>
                    <th>Day</th>
                    <th>Start Time</th>
                    <th>End Time</th>
                    <th>Course</th>
                    <th>Classroom</th>
                    <th>Lecturer Initials</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="timetable-body">
                <?php if (count($timetable_entries) > 0): ?>
                    <?php foreach ($timetable_entries as $entry): ?>
                        <tr>
                            <input type="hidden" name="id[]" value="<?= htmlspecialchars($entry['id']) ?>">
                            <td>
                                <select name="day[]" class="form-select form-select-sm" required>
                                    <?php foreach (['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'] as $day): ?>
                                        <option value="<?= $day ?>" <?= $day === $entry['day'] ? 'selected' : '' ?>><?= $day ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td><input type="time" name="start_time[]" class="form-control form-control-sm" value="<?= $entry['start_time'] ?>" required></td>
                            <td><input type="time" name="end_time[]" class="form-control form-control-sm" value="<?= $entry['end_time'] ?>" required></td>
                            <td>
                                <select name="course_id[]" class="form-select form-select-sm" required>
                                    <?php foreach ($courses as $course): ?>
                                        <option value="<?= $course['id'] ?>" <?= $course['id'] == $entry['course_id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($course['course_code'] . ' - ' . $course['course_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td><input type="text" name="classroom[]" class="form-control form-control-sm" value="<?= htmlspecialchars($entry['classroom']) ?>"></td>
                            <td><input type="text" name="lecturer_initials[]" class="form-control form-control-sm" value="<?= htmlspecialchars($entry['lecturer_initials']) ?>"></td>
                            <td><button type="button" class="btn btn-danger btn-sm delete-row">Delete</button></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="7">No timetable entries found for this mode. Add new entries below.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>

        <button type="button" class="btn btn-success mb-3" id="add-row-btn">Add Row</button><br>
        <button type="submit" class="btn btn-primary">Save Timetable</button>
    </form>
</div>

<script>
// Add a new empty row for timetable entry
document.getElementById('add-row-btn').addEventListener('click', () => {
    const tbody = document.getElementById('timetable-body');

    const days = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];
    const courses = <?= json_encode($courses) ?>;

    const tr = document.createElement('tr');

    tr.innerHTML = `
        <input type="hidden" name="id[]" value="">
        <td>
            <select name="day[]" class="form-select form-select-sm" required>
                ${days.map(d => `<option value="${d}">${d}</option>`).join('')}
            </select>
        </td>
        <td><input type="time" name="start_time[]" class="form-control form-control-sm" required></td>
        <td><input type="time" name="end_time[]" class="form-control form-control-sm" required></td>
        <td>
            <select name="course_id[]" class="form-select form-select-sm" required>
                ${courses.map(c => `<option value="${c.id}">${c.course_code} - ${c.course_name}</option>`).join('')}
            </select>
        </td>
        <td><input type="text" name="classroom[]" class="form-control form-control-sm"></td>
        <td><input type="text" name="lecturer_initials[]" class="form-control form-control-sm"></td>
        <td><button type="button" class="btn btn-danger btn-sm delete-row">Delete</button></td>
    `;

    if (tbody.children.length === 1 && tbody.children[0].children[0].colSpan == 7) {
        tbody.innerHTML = '';
    }

    tbody.appendChild(tr);
});

// Delete row button handler
document.getElementById('timetable-body').addEventListener('click', e => {
    if (e.target.classList.contains('delete-row')) {
        const tr = e.target.closest('tr');
        tr.remove();

        const tbody = document.getElementById('timetable-body');
        if (tbody.children.length === 0) {
            const trPlaceholder = document.createElement('tr');
            trPlaceholder.innerHTML = `<td colspan="7" class="text-center">No timetable entries found for this mode. Add new entries below.</td>`;
            tbody.appendChild(trPlaceholder);
        }
    }
});

// Validate before form submission
function validateForm() {
    const rows = document.querySelectorAll('#timetable-body tr');
    if(rows.length === 0) {
        alert('Please add at least one timetable entry.');
        return false;
    }
    for (const row of rows) {
        const day = row.querySelector('select[name="day[]"]');
        const startTime = row.querySelector('input[name="start_time[]"]');
        const endTime = row.querySelector('input[name="end_time[]"]');
        const courseId = row.querySelector('select[name="course_id[]"]');

        if (!day || !startTime || !endTime || !courseId) continue;

        if (!day.value || !startTime.value || !endTime.value || !courseId.value) {
            alert('Please fill all required fields in every row.');
            return false;
        }
        if (startTime.value >= endTime.value) {
            alert('Start time must be before end time in every row.');
            return false;
        }
    }
    return true;
}
</script>

<?php include 'includes/footer.php'; ?>
