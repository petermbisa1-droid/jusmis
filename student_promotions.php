<?php
session_start();

if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['role'], ['superuser', 'registrar'])) {
    header('Location: login.php');
    exit;
}

include 'config/db.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Load related data for display
$sessionsRes = $conn->query("SELECT id, session_name FROM sessions ORDER BY session_name");
$classesRes = $conn->query("SELECT id, class_name FROM classes ORDER BY class_name");
$academicYearsRes = $conn->query("SELECT id, year_name FROM academic_years ORDER BY year_name DESC");

function getNameById($array, $id, $key='id', $value='name') {
    foreach ($array as $item) {
        if ($item[$key] == $id) {
            return $item[$value];
        }
    }
    return null;
}

// Load promotions with joins to show names (optional optimization: could join in query)
$sql = "
    SELECT sp.*, s.session_name, c.class_name AS old_class_name, c2.class_name AS new_class_name, ay.year_name
    FROM student_promotions sp
    LEFT JOIN sessions s ON sp.session_id = s.id
    LEFT JOIN classes c ON sp.old_class_id = c.id
    LEFT JOIN classes c2 ON sp.new_class_id = c2.id
    LEFT JOIN academic_years ay ON sp.academic_year_id = ay.id
    ORDER BY sp.created_at DESC
";
$result = $conn->query($sql);

include 'includes/header.php';
?>
    <main class="content">
        <h3>Student Promotions History</h3>

        <?php if (!$result || $result->num_rows === 0): ?>
            <div class="alert alert-info">No promotion records found.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Student ID</th>
                            <th>Session</th>
                            <th>Academic Year</th>
                            <th>Old Class</th>
                            <th>New Class</th>
                            <th>Status</th>
                            <th>Created At</th>
                            <th>Updated At</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $count = 1;
                        while ($row = $result->fetch_assoc()):
                        ?>
                            <tr>
                                <td><?= $count++ ?></td>
                                <td><?= htmlspecialchars($row['student_id']) ?></td>
                                <td><?= htmlspecialchars($row['session_name']) ?></td>
                                <td><?= htmlspecialchars($row['year_name']) ?></td>
                                <td><?= htmlspecialchars($row['old_class_name']) ?></td>
                                <td><?= htmlspecialchars($row['new_class_name']) ?></td>
                                <td>
                                    <?php if ($row['promotion_status'] === 'Pass'): ?>
                                        <span class="badge bg-success">Pass</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger"><?= htmlspecialchars($row['promotion_status']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($row['created_at']) ?></td>
                                <td><?= htmlspecialchars($row['updated_at']) ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </main>
</div>

<?php include 'includes/footer.php'; ?>
