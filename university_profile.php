<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'superuser') {
    header("Location: login.php");
    exit;
}

include 'config/db.php';
include 'includes/logger.php';

$message = '';

// Handle update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $address = trim($_POST['address']);
    $phone = trim($_POST['phone']);
    $updated_by = $_SESSION['user']['full_name'];

    $stmt = $conn->prepare("UPDATE university_profile SET name = ?, address = ?, phone = ?, updated_by = ? WHERE id = 1");
    $stmt->bind_param("ssss", $name, $address, $phone, $updated_by);

    if ($stmt->execute()) {
        $message = "University profile updated successfully.";
        log_action("Updated university profile", $conn);
    } else {
        $message = "Failed to update profile.";
    }
}

// Fetch current data
$result = $conn->query("SELECT * FROM university_profile WHERE id = 1");
$profile = $result->fetch_assoc();
?>

<?php include 'includes/header.php'; ?>

<h3 class="mb-4">University Profile Settings</h3>

<?php if ($message): ?>
    <div class="alert alert-info"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<form method="POST" class="bg-dark p-4 rounded">
    <div class="mb-3">
        <label class="form-label">University Name</label>
        <input type="text" name="name" class="form-control" required value="<?= htmlspecialchars($profile['name']) ?>">
    </div>
    <div class="mb-3">
        <label class="form-label">Address</label>
        <textarea name="address" class="form-control" rows="3" required><?= htmlspecialchars($profile['address']) ?></textarea>
    </div>
    <div class="mb-3">
        <label class="form-label">Phone</label>
        <input type="text" name="phone" class="form-control" required value="<?= htmlspecialchars($profile['phone']) ?>">
    </div>
    <button class="btn btn-light"><i class="fas fa-save"></i> Save Changes</button>
</form>

<p class="mt-3 text-muted">
    Last updated by: <strong><?= htmlspecialchars($profile['updated_by'] ?? 'N/A') ?></strong>
</p>

<?php include 'includes/footer.php'; ?>
