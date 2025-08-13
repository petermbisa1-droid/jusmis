<?php
session_start();
include 'config/db.php';

// Access control
$allowedRoles = ['admin', 'superuser', 'finance'];
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['role'], $allowedRoles)) {
    header('Location: login.php');
    exit;
}

$invoice_id = isset($_GET['invoice_id']) ? intval($_GET['invoice_id']) : 0;
if ($invoice_id <= 0) {
    die("Invalid invoice ID.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // User confirmed reversal
    $stmt = $conn->prepare("DELETE FROM payments WHERE invoice_id = ?");
    $stmt->bind_param('i', $invoice_id);
    if ($stmt->execute()) {
        $stmt->close();
        header("Location: payment_entry.php?success=Payments reversed successfully.");
        exit;
    } else {
        $error = "Database error: " . $conn->error;
    }
    $stmt->close();
} else {
    // Fetch invoice info for confirmation display
    $stmt = $conn->prepare("SELECT invoice_number FROM invoices WHERE id = ?");
    $stmt->bind_param('i', $invoice_id);
    $stmt->execute();
    $stmt->bind_result($invoice_number);
    if (!$stmt->fetch()) {
        $stmt->close();
        die("Invoice not found.");
    }
    $stmt->close();
}
?>

<?php include 'includes/header.php'; ?>

<div class="container my-4">
    <h2>Reverse Payments</h2>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <p>Are you sure you want to reverse (delete) <strong>all payments</strong> associated with invoice <strong><?= htmlspecialchars($invoice_number) ?></strong>?</p>

    <form method="post">
        <button type="submit" class="btn btn-danger">Yes, Reverse Payments</button>
        <a href="payment_entry.php" class="btn btn-secondary ms-2">Cancel</a>
    </form>
</div>

<?php include 'includes/footer.php'; ?>
