<?php
session_start();
include 'includes/header.php';
include 'config/db.php';

// Role check
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['role'], ['admin', 'superuser', 'finance'])) {
    header('Location: login.php');
    exit;
}

$message = '';

// Handle fee setting submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $academic_year_id = intval($_POST['academic_year_id']);
    $class_id = intval($_POST['class_id']);
    $fee_type = trim($_POST['fee_type']);
    $amount = floatval($_POST['amount']);

    // Check if fee already exists
    $check = $conn->prepare("SELECT COUNT(*) FROM fee_settings WHERE academic_year_id = ? AND class_id = ? AND fee_type = ?");
    $check->bind_param("iis", $academic_year_id, $class_id, $fee_type);
    $check->execute();
    $check->bind_result($count);
    $check->fetch();
    $check->close();

    if ($count == 0) {
        $stmt = $conn->prepare("INSERT INTO fee_settings (academic_year_id, class_id, fee_type, amount) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iisd", $academic_year_id, $class_id, $fee_type, $amount);
        if ($stmt->execute()) {
            $message = "Fee setting saved successfully.";
        } else {
            $message = "Failed to save fee setting.";
        }
        $stmt->close();
    } else {
        $message = "Fee setting already exists.";
    }
}

// Fetch dropdown data
$academic_years = $conn->query("SELECT id, year_name FROM academic_years ORDER BY year_name DESC");
$classes = $conn->query("SELECT id, class_name FROM classes ORDER BY class_name");
$fee_settings = $conn->query("SELECT fs.*, ay.year_name, c.class_name FROM fee_settings fs JOIN academic_years ay ON fs.academic_year_id = ay.id JOIN classes c ON fs.class_id = c.id ORDER BY ay.year_name DESC, c.class_name");
?>

<div class="container mt-4">
    <h2>Fee Settings</h2>

    <?php if ($message): ?>
        <div class="alert alert-info"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <form method="POST" class="row g-3 mb-4">
        <div class="col-md-4">
            <label>Academic Year</label>
            <select name="academic_year_id" class="form-select" required>
                <option value="">-- Select Academic Year --</option>
                <?php while ($row = $academic_years->fetch_assoc()): ?>
                    <option value="<?= $row['id'] ?>"><?= htmlspecialchars($row['year_name']) ?></option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="col-md-4">
            <label>Class</label>
            <select name="class_id" class="form-select" required>
                <option value="">-- Select Class --</option>
                <?php while ($row = $classes->fetch_assoc()): ?>
                    <option value="<?= $row['id'] ?>"><?= htmlspecialchars($row['class_name']) ?></option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="col-md-4">
            <label>Fee Type</label>
            <input type="text" name="fee_type" class="form-control" required>
        </div>
        <div class="col-md-4">
            <label>Amount</label>
            <input type="number" name="amount" step="0.01" class="form-control" required>
        </div>
        <div class="col-12">
            <button type="submit" class="btn btn-primary">Save Fee Setting</button>
        </div>
    </form>

    <h3>Existing Fee Settings</h3>
    <table class="table table-striped table-bordered">
        <thead>
            <tr>
                <th>#</th>
                <th>Academic Year</th>
                <th>Class</th>
                <th>Fee Type</th>
                <th>Amount</th>
            </tr>
        </thead>
        <tbody>
            <?php $sn = 1; while ($row = $fee_settings->fetch_assoc()): ?>
                <tr>
                    <td><?= $sn++ ?></td>
                    <td><?= htmlspecialchars($row['year_name']) ?></td>
                    <td><?= htmlspecialchars($row['class_name']) ?></td>
                    <td><?= htmlspecialchars($row['fee_type']) ?></td>
                    <td><?= number_format($row['amount'], 2) ?></td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>

<?php include 'includes/footer.php'; ?>
