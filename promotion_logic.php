<?php
function run_promotion_logic($conn, $session_id, $class_id, $academic_year_id) {
    // Array to hold promotion results for reporting
    $promotionResults = [];

    // Get current class info (year, semester, program_code) to determine next class
    $stmtClass = $conn->prepare("
        SELECT c.year, c.semester, p.code AS program_code 
        FROM classes c
        JOIN programmes p ON c.programme_id = p.id
        WHERE c.id = ?
        LIMIT 1
    ");
    $stmtClass->bind_param("i", $class_id);
    $stmtClass->execute();
    $stmtClass->bind_result($current_year, $current_semester, $program_code);
    if (!$stmtClass->fetch()) {
        $stmtClass->close();
        return ['error' => 'Invalid class ID for promotion'];
    }
    $stmtClass->close();

    // Determine next class ID
    if ($current_semester == 1) {
        $next_semester = 2;
        $next_year = $current_year;
    } else {
        $next_semester = 1;
        $next_year = $current_year + 1;
    }

    $stmtNextClass = $conn->prepare("
        SELECT c.id FROM classes c
        JOIN programmes p ON c.programme_id = p.id
        WHERE p.code = ? AND c.year = ? AND c.semester = ?
        LIMIT 1
    ");
    $stmtNextClass->bind_param("sii", $program_code, $next_year, $next_semester);
    $stmtNextClass->execute();
    $stmtNextClass->bind_result($next_class_id);
    if (!$stmtNextClass->fetch()) {
        // No next class found — students remain in current class or handled differently
        $stmtNextClass->close();
        $next_class_id = null;
    } else {
        $stmtNextClass->close();
    }

    // Get all distinct students in current class/session/academic year
    $stmtStudents = $conn->prepare("
        SELECT DISTINCT student_id FROM approved_grades 
        WHERE session_id = ? AND class_id = ? AND academic_year_id = ?
    ");
    $stmtStudents->bind_param("iii", $session_id, $class_id, $academic_year_id);
    $stmtStudents->execute();
    $studentsResult = $stmtStudents->get_result();

    while ($student = $studentsResult->fetch_assoc()) {
        $student_id = $student['student_id'];

        // Check if student has any failing grades in approved_grades for this class/session/year
        $stmtFail = $conn->prepare("
            SELECT COUNT(*) FROM approved_grades
            WHERE student_id = ? AND session_id = ? AND class_id = ? AND academic_year_id = ? AND remarks = 'Fail'
        ");
        $stmtFail->bind_param("iiii", $student_id, $session_id, $class_id, $academic_year_id);
        $stmtFail->execute();
        $stmtFail->bind_result($fail_count);
        $stmtFail->fetch();
        $stmtFail->close();

        if ($fail_count > 0) {
            // Student fails to promote - stays in current class
            $new_class_id = $class_id;
            $promotion_status = 'Fail';
        } else {
            // Student passes and promotes to next class if exists, else stays
            $new_class_id = $next_class_id ?? $class_id;
            $promotion_status = 'Pass';
        }

        // Insert or update student promotion record
        $stmtCheckPromo = $conn->prepare("
            SELECT id FROM student_promotions
            WHERE student_id = ? AND session_id = ? AND academic_year_id = ?
        ");
        $stmtCheckPromo->bind_param("iii", $student_id, $session_id, $academic_year_id);
        $stmtCheckPromo->execute();
        $stmtCheckPromo->store_result();

        $now = date('Y-m-d H:i:s');

        if ($stmtCheckPromo->num_rows > 0) {
            $stmtCheckPromo->bind_result($promotion_id);
            $stmtCheckPromo->fetch();
            $stmtCheckPromo->close();

            $stmtUpdate = $conn->prepare("
                UPDATE student_promotions 
                SET old_class_id = ?, new_class_id = ?, promotion_status = ?, updated_at = ?
                WHERE id = ?
            ");
            $stmtUpdate->bind_param("iissi", $class_id, $new_class_id, $promotion_status, $now, $promotion_id);
            $stmtUpdate->execute();
            $stmtUpdate->close();
        } else {
            $stmtCheckPromo->close();

            $stmtInsert = $conn->prepare("
                INSERT INTO student_promotions 
                (student_id, session_id, academic_year_id, old_class_id, new_class_id, promotion_status, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmtInsert->bind_param("iiiissss", $student_id, $session_id, $academic_year_id, $class_id, $new_class_id, $promotion_status, $now, $now);
            $stmtInsert->execute();
            $stmtInsert->close();
        }

        $promotionResults[] = [
            'student_id' => $student_id,
            'old_class_id' => $class_id,
            'new_class_id' => $new_class_id,
            'status' => $promotion_status,
        ];
    }

    return $promotionResults;
}
