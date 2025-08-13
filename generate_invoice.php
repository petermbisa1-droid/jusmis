<?php
include 'includes/header.php';
include 'config/db.php';

// Get Academic Years and Classes for Filter Selection
$academic_years = $conn->query("SELECT id, year_name FROM academic_years ORDER BY year_name DESC");
$sessions = $conn->query("SELECT id, session_name FROM sessions ORDER BY session_name");
$classes = $conn->query("SELECT id, class_name FROM classes ORDER BY class_name");

// Handle Invoice Generation POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $student_id = $_POST['student_id'];
    $total_amount = $_POST['total_amount'];
    $academic_year_id = $_POST['academic_year_id'];
    $session_id = $_POST['session_id'];
    $class_id = $_POST['class_id'];

    // Check if student is registered in registrations table
    $checkReg = $conn->prepare("SELECT id FROM registrations WHERE student_id = ? AND academic_year_id = ? AND session_id = ? AND class_id = ?");
    $checkReg->bind_param("iiii", $student_id, $academic_year_id, $session_id, $class_id);
    $checkReg->execute();
    $checkReg->store_result();

    if ($checkReg->num_rows == 0) {
        echo "<div class='alert alert-danger'>This student is not registered for the selected Academic Year, Session, and Class.</div>";
    } else {
        // Check if an invoice already exists for this student/session/class
        $checkInvoice = $conn->prepare("SELECT id FROM invoices WHERE student_id = ? AND academic_year_id = ? AND class_id = ?");
        $checkInvoice->bind_param("iii", $student_id, $academic_year_id, $class_id);
        $checkInvoice->execute();
        $checkInvoice->store_result();

        if ($checkInvoice->num_rows > 0) {
            echo "<div class='alert alert-danger'>An invoice for this student, academic year, and class already exists.</div>";
        } else {
            // Auto-generate Invoice Number
            $result = $conn->query("SELECT MAX(id) AS last_id FROM invoices");
            $row = $result->fetch_assoc();
            $nextId = $row['last_id'] + 1;
            $invoiceNumber = 'INV-JU-' . str_pad($nextId, 3, '0', STR_PAD_LEFT);

            // Insert Invoice
            $stmt = $conn->prepare("INSERT INTO invoices (invoice_number, student_id, total_amount, academic_year_id, session_id, class_id) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sddiii", $invoiceNumber, $student_id, $total_amount, $academic_year_id, $session_id, $class_id);
            $stmt->execute();

            echo "<div class='alert alert-success'>Invoice $invoiceNumber generated successfully!</div>";
        }
    }
}
?>

<div class="container mt-4">
    <h2>Generate Invoice</h2>
    <form method="POST" class="row g-3">
        <div class="col-md-3">
            <label>Academic Year</label>
            <select name="academic_year_id" class="form-select" required>
                <option value="">Select Year</option>
                <?php while ($row = $academic_years->fetch_assoc()): ?>
                    <option value="<?= $row['id'] ?>"><?= htmlspecialchars($row['year_name']) ?></option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label>Session</label>
            <select name="session_id" class="form-select" required>
                <option value="">Select Session</option>
                <?php while ($row = $sessions->fetch_assoc()): ?>
                    <option value="<?= $row['id'] ?>"><?= htmlspecialchars($row['session_name']) ?></option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label>Class</label>
            <select name="class_id" class="form-select" required>
                <option value="">Select Class</option>
                <?php while ($row = $classes->fetch_assoc()): ?>
                    <option value="<?= $row['id'] ?>"><?= htmlspecialchars($row['class_name']) ?></option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label>Total Amount</label>
            <input type="number" step="0.01" name="total_amount" class="form-control" required>
        </div>
        <div class="col-md-6">
            <label>Student</label>
            <select name="student_id" class="form-select" required>
                <option value="">Select Student (Based on Registrations)</option>
                <?php
                // Fetch students who have registrations
                $students = $conn->query("
                    SELECT DISTINCT s.id, s.full_name 
                    FROM students s 
                    JOIN registrations r ON s.id = r.student_id
                    ORDER BY s.full_name
                ");
                while ($row = $students->fetch_assoc()):
                ?>
                    <option value="<?= $row['id'] ?>"><?= htmlspecialchars($row['full_name']) ?></option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="col-12 mt-3">
            <button type="submit" class="btn btn-primary">Generate Invoice</button>
        </div>
    </form>
</div>

<?php include 'includes/footer.php'; ?>
