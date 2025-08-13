<?php
session_start();
include 'config/db.php';

if (!isset($_GET['invoice_id']) || !is_numeric($_GET['invoice_id'])) {
    header('Location: payment_entry.php');
    exit;
}

$invoice_id = intval($_GET['invoice_id']);

// Get invoice & student info
$stmt = $conn->prepare("
    SELECT i.*, s.full_name 
    FROM invoices i 
    JOIN students s ON i.student_id = s.id 
    WHERE i.id = ?
");
$stmt->bind_param('i', $invoice_id);
$stmt->execute();
$result = $stmt->get_result();
$invoice = $result->fetch_assoc();
$stmt->close();

if (!$invoice) {
    header('Location: payment_entry.php?error=Invoice not found');
    exit;
}

// Fetch payment history
$history = $conn->prepare("SELECT amount_paid, payment_date, receipt_number FROM payments WHERE invoice_id = ? ORDER BY payment_date DESC");
$history->bind_param('i', $invoice_id);
$history->execute();
$payment_result = $history->get_result();

// Calculate total paid
$total_paid = 0;
while ($row = $payment_result->fetch_assoc()) {
    $total_paid += $row['amount_paid'];
    $payment_history[] = $row;
}
$balance = $invoice['total_amount'] - $total_paid;

// Reset pointer to display history
$history->execute();
$payment_result = $history->get_result();

// Handle payment submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $amount_paid = floatval($_POST['amount_paid'] ?? 0);
    $receipt_number = trim($_POST['receipt_number'] ?? '');

    if ($amount_paid <= 0 || $amount_paid > $balance) {
        $error = "Enter a valid payment amount (must be > 0 and not exceed balance).";
    } elseif (empty($receipt_number)) {
        $error = "Receipt number is required.";
    } else {
        $stmt = $conn->prepare("INSERT INTO payments (invoice_id, amount_paid, receipt_number, payment_date) VALUES (?, ?, ?, NOW())");
        $stmt->bind_param('ids', $invoice_id, $amount_paid, $receipt_number);
        if ($stmt->execute()) {
            $stmt->close();
            $conn->close();
            header("Location: payment_entry.php?success=Payment recorded successfully");
            exit;
        } else {
            $error = "Failed to save payment. Try again.";
        }
    }
}

include 'includes/header.php';
?>

<div class="container my-4">
    <h2>Enter Payment</h2>

    <div class="card mb-4">
        <div class="card-body">
            <p><strong>Invoice Number:</strong> <?= htmlspecialchars($invoice['invoice_number']) ?></p>
            <p><strong>Student:</strong> <?= htmlspecialchars($invoice['full_name']) ?></p>
            <p><strong>Total Amount:</strong> <?= number_format($invoice['total_amount'], 2) ?></p>
            <p><strong>Total Paid:</strong> <?= number_format($total_paid, 2) ?></p>
            <p><strong>Balance:</strong> <?= number_format($balance, 2) ?></p>
        </div>
    </div>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" class="mb-4">
        <div class="mb-3">
            <label for="amount_paid" class="form-label">Amount Paid</label>
            <input type="number" step="0.01" name="amount_paid" id="amount_paid" class="form-control" required>
        </div>

        <div class="mb-3">
            <label for="receipt_number" class="form-label">Receipt Number</label>
            <input type="text" name="receipt_number" id="receipt_number" class="form-control" required>
        </div>

        <button type="submit" class="btn btn-success">Save Payment</button>
        <a href="payment_entry.php" class="btn btn-secondary ms-2">Back</a>
    </form>

    <?php if ($payment_result->num_rows > 0): ?>
    <h4>Payment History</h4>
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>Receipt No.</th>
                <th>Amount Paid</th>
                <th>Payment Date</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($payment = $payment_result->fetch_assoc()): ?>
            <tr>
                <td><?= htmlspecialchars($payment['receipt_number']) ?></td>
                <td><?= number_format($payment['amount_paid'], 2) ?></td>
                <td><?= htmlspecialchars(date('Y-m-d H:i', strtotime($payment['payment_date']))) ?></td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
