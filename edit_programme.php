<?php
// edit_programme.php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'superuser') {
    header('Location: login.php');
    exit;
}
include 'config/db.php';
include 'includes/logger.php';

$id = intval($_GET['id'] ?? 0);
if (!$id) die('Invalid ID');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $programme_name = trim($_POST['programme_name']);
    $code = trim($_POST['code']);
    $updated_by = $_SESSION['user']['username'];

    $stmt = $conn->prepare("UPDATE programmes SET programme_name = ?, code = ?, updated_by = ? WHERE id = ?");
    $stmt->bind_param("sssi", $programme_name, $code, $updated_by, $id);
    $stmt->execute();
    $stmt->close();

    log_action("Updated programme: $programme_name ($code)", $conn);

    header("Location: programmes.php");
    exit;
}

$stmt = $conn->prepare("SELECT * FROM programmes WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$data = $result->fetch_assoc();
$stmt->close();

include 'includes/header.php';
?>
<h3>Edit Programme</h3>
<form method="POST">
  <div class="mb-3">
    <label>Name</label>
    <input type="text" name="programme_name" value="<?= htmlspecialchars($data['programme_name']) ?>" class="form-control" required>
  </div>
  <div class="mb-3">
    <label>Code</label>
    <input type="text" name="code" value="<?= htmlspecialchars($data['code']) ?>" class="form-control" required>
  </div>
  <button class="btn btn-success">Update</button>
</form>
<?php include 'includes/footer.php'; ?>
