<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'superuser') {
    header('Location: login.php');
    exit;
}

include 'config/db.php';
include 'includes/logger.php';

$errors = [];
$success = '';
$currentUser = $_SESSION['user']['username'] ?? 'unknown';

// Fetch programmes and classes
$progRes = $conn->query("SELECT id, programme_name FROM programmes WHERE is_active = 1");
$classRes = $conn->query("SELECT id, class_name, year, semester FROM classes WHERE is_active = 1");

// Helper functions
function old($key, $default = '') {
    return htmlspecialchars($_POST[$key] ?? $default);
}
function selected($key, $value) {
    return (isset($_POST[$key]) && $_POST[$key] == $value) ? 'selected' : '';
}
function checked($key, $value) {
    return (isset($_POST[$key]) && $_POST[$key] == $value) ? 'checked' : '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $registration_number = trim($_POST['registration_number'] ?? '');
    $full_name = trim($_POST['full_name'] ?? '');
    $sex = $_POST['sex'] ?? '';
    $dob = $_POST['dob'] ?? '';
    $nationality = trim($_POST['nationality'] ?? '');
    $postal_address = trim($_POST['postal_address'] ?? '');
    $physical_address = trim($_POST['physical_address'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $mode_of_study = $_POST['mode_of_study'] ?? '';
    $kin_name = trim($_POST['kin_name'] ?? '');
    $kin_address = trim($_POST['kin_address'] ?? '');
    $kin_phone = trim($_POST['kin_phone'] ?? '');
    $kin_email = trim($_POST['kin_email'] ?? '');
    $programme_id = (int) ($_POST['programme_id'] ?? 0);
    $class_id = (int) ($_POST['class_id'] ?? 0);
    $academic = $_POST['academic'] ?? [];
    $other = $_POST['other'] ?? [];

    // Validation
    if (!$registration_number || !$full_name || !$programme_id || !$class_id) {
        $errors[] = 'Registration number, full name, programme, and class are required.';
    }
    if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid student email.';
    }
    if ($kin_email && !filter_var($kin_email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid next of kin email.';
    }

    // Check if student exists
    $chk = $conn->prepare("SELECT id, status FROM students WHERE registration_number = ?");
    $chk->bind_param("s", $registration_number);
    $chk->execute();
    $chk->store_result();
    if ($chk->num_rows > 0) {
        $chk->bind_result($existing_id, $existing_status);
        $chk->fetch();
        if ($existing_status === 'active') {
            $errors[] = "Student with registration number $registration_number is already enrolled and active.";
        } else {
            $errors[] = "Student with registration number $registration_number exists but not active. Please check.";
        }
    }
    $chk->close();

    if (!$errors) {
        try {
            $conn->begin_transaction();

            // Get year and semester from class
            $cls = $conn->prepare("SELECT year, semester FROM classes WHERE id = ?");
            $cls->bind_param("i", $class_id);
            $cls->execute();
            $cls->bind_result($year, $semester);
            if (!$cls->fetch()) {
                throw new Exception("Invalid class selected.");
            }
            $cls->close();

            // Insert user first
            $defaultPassword = 'DefaultPass123';
            $hashed = password_hash($defaultPassword, PASSWORD_DEFAULT);
            $role = 'student';

            $u = $conn->prepare("INSERT INTO users (full_name, username, password, role, is_active, created_by, created_at)
                                 VALUES (?, ?, ?, ?, 1, ?, NOW())");
            if (!$u) {
                throw new Exception("Prepare failed for user insert: " . $conn->error);
            }
            $u->bind_param("sssss", $full_name, $registration_number, $hashed, $role, $currentUser);
            if (!$u->execute()) {
                throw new Exception("User insert failed: " . $u->error);
            }
            $user_id = $u->insert_id;
            $u->close();

            // Insert student linked to user_id
            $stmt = $conn->prepare("INSERT INTO students 
                (registration_number, full_name, sex, date_of_birth, nationality, postal_address, physical_address, phone, email, mode_of_study,
                next_of_kin_name, next_of_kin_address, next_of_kin_phone, next_of_kin_email, programme_id, class_id, year, semester,
                status, role, user_id, created_by, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', 'student', ?, ?, NOW())");

            $stmt->bind_param(
                "ssssssssssssssiiisis",
                $registration_number, $full_name, $sex, $dob, $nationality,
                $postal_address, $physical_address, $phone, $email, $mode_of_study,
                $kin_name, $kin_address, $kin_phone, $kin_email,
                $programme_id, $class_id, $year, $semester,
                $user_id, $currentUser
            );

            if (!$stmt->execute()) {
                throw new Exception("Failed to insert student: " . $stmt->error);
            }
            $student_id = $stmt->insert_id;
            $stmt->close();

            // Insert academic records
            if (!empty($academic)) {
                $ir = $conn->prepare("INSERT INTO student_academic_records
                    (student_id, qualification, center_number, exam_number, subject, grade, year)
                    VALUES (?, ?, ?, ?, ?, ?, ?)");
                foreach ($academic as $row) {
                    $qualification = trim($row['qualification'] ?? '');
                    $center = trim($row['center'] ?? '');
                    $exam = trim($row['exam'] ?? '');
                    $subject = trim($row['subject'] ?? '');
                    $grade = trim($row['grade'] ?? '');
                    $yr = (int)($row['yr'] ?? 0);
                    if ($qualification && $subject) {
                        $ir->bind_param("issssis", $student_id, $qualification, $center, $exam, $subject, $grade, $yr);
                        $ir->execute();
                    }
                }
                $ir->close();
            }

            // Insert other qualifications
            if (!empty($other)) {
                $iq = $conn->prepare("INSERT INTO student_other_qualifications
                    (student_id, qualification_type, institution, year_of_award, work_experience_years)
                    VALUES (?, ?, ?, ?, ?)");
                foreach ($other as $row) {
                    $qualification_type = trim($row['qualification_type'] ?? '');
                    $institution = trim($row['institution'] ?? '');
                    $year_award = (int)($row['year_award'] ?? 0);
                    $work_years = (int)($row['work_years'] ?? 0);
                    if ($qualification_type && $institution) {
                        $iq->bind_param("issii", $student_id, $qualification_type, $institution, $year_award, $work_years);
                        $iq->execute();
                    }
                }
                $iq->close();
            }

            log_action("Enrolled new student $registration_number", $conn);
            $conn->commit();

            // Display credentials
            $success = <<<HTML
<div>
  Student enrolled successfully.<br><br>
  <strong>Username:</strong> <code>$registration_number</code><br>
  <strong style="color:darkred;">Default Password:</strong> 
  <code style="color:red; font-weight:bold;">$defaultPassword</code><br><br>
  <span style="color:#d9534f; font-weight:bold;">
    ⚠️ Please copy and store the password securely. The student will be required to change it on first login.
  </span>
</div>
HTML;

            $_POST = []; // clear form

        } catch (Exception $e) {
            $conn->rollback();
            $errors[] = "Enrollment failed: " . $e->getMessage();
        }
    }
}

include 'includes/header.php';
?>

<h3>Enroll Student</h3>

<?php if ($errors): ?>
    <div class="alert alert-danger"><ul>
        <?php foreach ($errors as $e) echo "<li>$e</li>"; ?>
    </ul></div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success"><?= $success ?></div>
<?php endif; ?>

<form method="POST">
  <!-- Mode of Study -->
  <div class="mb-3">
    <label>Mode of Study</label><br>
    <?php foreach(['Normal','Evening','Weekend','Blended'] as $mode): ?>
      <div class="form-check form-check-inline">
        <input type="radio" name="mode_of_study" value="<?= $mode ?>" class="form-check-input" required <?= checked('mode_of_study', $mode) ?>>
        <label class="form-check-label"><?= $mode ?></label>
      </div>
    <?php endforeach; ?>
  </div>

  <!-- Registration Number -->
  <div class="mb-3">
    <label>Registration Number</label>
    <input name="registration_number" class="form-control" required placeholder="e.g. BBA/10/01/01/01" value="<?= old('registration_number') ?>">
  </div>

  <!-- Full Name -->
  <div class="mb-3">
    <label>Full Name</label>
    <input name="full_name" class="form-control" required value="<?= old('full_name') ?>">
  </div>

  <!-- Sex -->
  <div class="mb-3">
    <label>Sex</label>
    <select name="sex" class="form-select" required>
      <option value="">Select sex</option>
      <option value="Male" <?= selected('sex', 'Male') ?>>Male</option>
      <option value="Female" <?= selected('sex', 'Female') ?>>Female</option>
    </select>
  </div>

  <!-- Date of Birth -->
  <div class="mb-3">
    <label>Date of Birth</label>
    <input type="date" name="dob" class="form-control" required value="<?= old('dob') ?>">
  </div>

  <!-- Nationality -->
  <div class="mb-3">
    <label>Nationality</label>
    <input name="nationality" class="form-control" value="<?= old('nationality') ?>">
  </div>

  <!-- Postal Address -->
  <div class="mb-3">
    <label>Postal Address</label>
    <textarea name="postal_address" class="form-control"><?= old('postal_address') ?></textarea>
  </div>

  <!-- Physical Address -->
  <div class="mb-3">
    <label>Physical Address</label>
    <textarea name="physical_address" class="form-control"><?= old('physical_address') ?></textarea>
  </div>

  <!-- Phone -->
  <div class="mb-3">
    <label>Phone</label>
    <input name="phone" class="form-control" value="<?= old('phone') ?>">
  </div>

  <!-- Email -->
  <div class="mb-3">
    <label>Email</label>
    <input type="email" name="email" class="form-control" value="<?= old('email') ?>">
  </div>

  <h5>Next of Kin</h5>

  <div class="mb-3">
    <label>Name</label>
    <input name="kin_name" class="form-control" value="<?= old('kin_name') ?>">
  </div>

  <div class="mb-3">
    <label>Address</label>
    <textarea name="kin_address" class="form-control"><?= old('kin_address') ?></textarea>
  </div>

  <div class="mb-3">
    <label>Phone</label>
    <input name="kin_phone" class="form-control" value="<?= old('kin_phone') ?>">
  </div>

  <div class="mb-3">
    <label>Email</label>
    <input type="email" name="kin_email" class="form-control" value="<?= old('kin_email') ?>">
  </div>

  <h5>Academic Records</h5>
  <div id="academic-blocks">
    <div class="academic-row mb-2">
      <input name="academic[0][qualification]" placeholder="Qualification" class="form-control mb-1" value="<?= htmlspecialchars($_POST['academic'][0]['qualification'] ?? '') ?>">
      <input name="academic[0][center]" placeholder="Center" class="form-control mb-1" value="<?= htmlspecialchars($_POST['academic'][0]['center'] ?? '') ?>">
      <input name="academic[0][exam]" placeholder="Exam No." class="form-control mb-1" value="<?= htmlspecialchars($_POST['academic'][0]['exam'] ?? '') ?>">
      <input name="academic[0][subject]" placeholder="Subject" class="form-control mb-1" value="<?= htmlspecialchars($_POST['academic'][0]['subject'] ?? '') ?>">
      <input name="academic[0][grade]" placeholder="Grade" class="form-control mb-1" value="<?= htmlspecialchars($_POST['academic'][0]['grade'] ?? '') ?>">
      <input name="academic[0][yr]" type="number" placeholder="Year" class="form-control mb-1" value="<?= htmlspecialchars($_POST['academic'][0]['yr'] ?? '') ?>">
    </div>
  </div>
  <button type="button" class="btn btn-secondary" onclick="addAcademic()">Add More</button>

  <h5>Other Qualifications</h5>
  <div id="other-blocks">
    <div class="other-row mb-2">
      <input name="other[0][qualification_type]" placeholder="Type" class="form-control mb-1" value="<?= htmlspecialchars($_POST['other'][0]['qualification_type'] ?? '') ?>">
      <input name="other[0][institution]" placeholder="Institution" class="form-control mb-1" value="<?= htmlspecialchars($_POST['other'][0]['institution'] ?? '') ?>">
      <input name="other[0][year_award]" type="number" placeholder="Year of Award" class="form-control mb-1" value="<?= htmlspecialchars($_POST['other'][0]['year_award'] ?? '') ?>">
      <input name="other[0][work_years]" type="number" placeholder="Years Experience" class="form-control mb-1" value="<?= htmlspecialchars($_POST['other'][0]['work_years'] ?? '') ?>">
    </div>
  </div>
  <button type="button" class="btn btn-secondary" onclick="addOther()">Add More</button>

  <h5>Programme Selection</h5>
  <div class="mb-3">
    <label>Programme</label>
    <select name="programme_id" class="form-select" required>
      <option value="">Select Programme</option>
      <?php while ($r = $progRes->fetch_assoc()): ?>
        <option value="<?= $r['id'] ?>" <?= selected('programme_id', $r['id']) ?>><?= htmlspecialchars($r['programme_name']) ?></option>
      <?php endwhile; ?>
    </select>
  </div>

  <div class="mb-3">
    <label>Class</label>
    <select name="class_id" class="form-select" required>
      <option value="">Select Class</option>
      <?php while ($r = $classRes->fetch_assoc()): ?>
        <option value="<?= $r['id'] ?>" <?= selected('class_id', $r['id']) ?>><?= htmlspecialchars($r['class_name']) ?></option>
      <?php endwhile; ?>
    </select>
  </div>

  <button type="submit" class="btn btn-primary">Enroll Student</button>
</form>

<script>
let acIdx = 1, otIdx = 1;
function addAcademic() {
  const div = document.createElement('div'); 
  div.className = 'academic-row mb-2';
  div.innerHTML = `<input name="academic[${acIdx}][qualification]" placeholder="Qualification" class="form-control mb-1">
    <input name="academic[${acIdx}][center]" placeholder="Center" class="form-control mb-1">
    <input name="academic[${acIdx}][exam]" placeholder="Exam No." class="form-control mb-1">
    <input name="academic[${acIdx}][subject]" placeholder="Subject" class="form-control mb-1">
    <input name="academic[${acIdx}][grade]" placeholder="Grade" class="form-control mb-1">
    <input name="academic[${acIdx}][yr]" placeholder="Year" type="number" class="form-control mb-1">`;
  document.getElementById('academic-blocks').appendChild(div);
  acIdx++;
}
function addOther() {
  const div = document.createElement('div'); 
  div.className = 'other-row mb-2';
  div.innerHTML = `<input name="other[${otIdx}][qualification_type]" placeholder="Type" class="form-control mb-1">
    <input name="other[${otIdx}][institution]" placeholder="Institution" class="form-control mb-1">
    <input name="other[${otIdx}][year_award]" placeholder="Year of Award" type="number" class="form-control mb-1">
    <input name="other[${otIdx}][work_years]" placeholder="Years Experience" type="number" class="form-control mb-1">`;
  document.getElementById('other-blocks').appendChild(div);
  otIdx++;
}
</script>

<?php include 'includes/footer.php'; ?>
