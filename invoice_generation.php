<?php
include 'includes/header.php';
include 'config/db.php';

// Generate Invoice Number
function generateInvoiceNumber($conn) {
    $result = $conn->query("SELECT MAX(id) AS last_id FROM invoices");
    $row = $result->fetch_assoc();
    $nextId = $row['last_id'] + 1;
    return 'INV-JU-' . str_pad($nextId, 5, '0', STR_PAD_LEFT);
}

// Handle Invoice Generation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['student_id'])) {
    $student_id = intval($_POST['student_id']);
    $academic_year_id = intval($_POST['academic_year_id']);
    $class_id = intval($_POST['class_id']);

    // Calculate Total Fee from Fee Settings
    $stmt = $conn->prepare("SELECT SUM(amount) FROM fee_settings WHERE academic_year_id=? AND class_id=?");
    $stmt->bind_param("ii", $academic_year_id, $class_id);
    $stmt->execute();
    $stmt->bind_result($total_amount);
    $stmt->fetch();
    $stmt->close();

    // Insert Invoice
    $invoice_number = generateInvoiceNumber($conn);
    $stmt = $conn->prepare("INSERT INTO invoices (invoice_number, student_id, academic_year_id, class_id, total_amount) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("siiid", $invoice_number, $student_id, $academic_year_id, $class_id, $total_amount);
    if ($stmt->execute()) {
        $message = "Invoice Generated: $invoice_number";
    }
    $stmt->close();
}

// Fetch Students Dropdown
$students = $conn->query("SELECT s.id, s.full_name, c.class_name FROM students s JOIN classes c ON s.class_id = c.id");
$academic_years = $conn->query("SELECT id, year_name FROM academic_years");
$classes = $conn->query("SELECT id, class_name FROM classes");
?>

<div class="container mt-4">
    <h2>Generate Invoice</h2>
    <?php if (isset($message)): ?>
        <div class="alert alert-success"><?= $message ?></div>
    <?php endif; ?>

    <form method="POST" class="row g-3">
        <div class="col-md-4">
            <label>Student</label>
            <select name="student_id" class="form-select" required>
                <option value="">Select Student</option>
                <?php while ($row = $students->fetch_assoc()): ?>
                    <option value="<?= $row['id'] ?>"><?= htmlspecialchars($row['full_name'] . " - " . $row['class_name']) ?></option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="col-md-4">
            <label>Academic Year</label>
            <select name="academic_year_id" class="form-select" required>
                <option value="">Select Year</option>
                <?php while ($row = $academic_years->fetch_assoc()): ?>
                    <option value="<?= $row['id'] ?>"><?= htmlspecialchars($row['year_name']) ?></option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="col-md-4">
            <label>Class</label>
            <select name="class_id" class="form-select" required>
                <option value="">Select Class</option>
                <?php while ($row = $classes->fetch_assoc()): ?>
                    <option value="<?= $row['id'] ?>"><?= htmlspecialchars($row['class_name']) ?></option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="col-12">
            <button type="submit" class="btn btn-success">Generate Invoice</button>
        </div>
    </form>
</div>

<?php include 'includes/footer.php'; ?>
