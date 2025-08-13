<?php
session_start();

// Ensure user is logged in
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit;
}

include 'config/db.php';

// Initialize summary variables
$totalUsers = 0;
$totalRegistrations = 0;
$totalPayments = 0.0;
$paymentsByDay = [];
$chartLabels = [];
$chartData = [];

// Fetch total active users
$userResult = $conn->query("SELECT COUNT(*) AS total FROM users WHERE is_active = 1");
if ($userResult && $row = $userResult->fetch_assoc()) {
    $totalUsers = (int)($row['total'] ?? 0);
}

// Fetch total registrations
$regResult = $conn->query("SELECT COUNT(*) AS total FROM registrations");
if ($regResult && $row = $regResult->fetch_assoc()) {
    $totalRegistrations = (int)($row['total'] ?? 0);
}

// Fetch total payments
$payResult = $conn->query("SELECT SUM(amount_paid) AS total FROM payments");
if ($payResult && $row = $payResult->fetch_assoc()) {
    $totalPayments = (float)($row['total'] ?? 0);
}

// Fetch recent audit trail (last 10 entries)
$auditResult = $conn->query("
    SELECT created_at, user_name, role, activity 
    FROM audit_trail
    ORDER BY created_at DESC
    LIMIT 10
");

// Fetch payments by day for the last 7 days
$paymentsByDayResult = $conn->query("
    SELECT DATE(payment_date) AS pay_date, SUM(amount_paid) AS total
    FROM payments
    WHERE payment_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    GROUP BY pay_date
    ORDER BY pay_date ASC
");

if ($paymentsByDayResult) {
    while ($row = $paymentsByDayResult->fetch_assoc()) {
        $pay_date = $row['pay_date'] ?? null;
        $total = isset($row['total']) ? (float)$row['total'] : 0;
        if ($pay_date) {
            $paymentsByDay[$pay_date] = $total;
        }
    }
}

// Prepare chart data for the past 7 days
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $chartLabels[] = $date;
    $chartData[] = $paymentsByDay[$date] ?? 0;
}

// Get logged-in user's full name
$user = $_SESSION['user'];
$fullName = $user['name'] ?? 'Guest';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Dashboard - Jubilee University</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
<?php include 'includes/header.php'; ?>

<div class="container my-4">
    <h3 class="mb-4">Dashboard - Superuser Panel</h3>
    <p>Welcome, <strong><?= htmlspecialchars($fullName) ?></strong></p>

    <div class="row g-4 mb-4">
        <div class="col-md-4">
            <div class="card text-white bg-primary shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div><i class="fas fa-users fa-3x"></i></div>
                        <div class="text-end">
                            <h2 class="card-title"><?= number_format($totalUsers) ?></h2>
                            <p class="card-text">Active Users</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-white bg-success shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div><i class="fas fa-user-graduate fa-3x"></i></div>
                        <div class="text-end">
                            <h2 class="card-title"><?= number_format($totalRegistrations) ?></h2>
                            <p class="card-text">Total Registrations</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-white bg-warning shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div><i class="fas fa-money-bill-wave fa-3x"></i></div>
                        <div class="text-end">
                            <h2 class="card-title">MWK <?= number_format($totalPayments, 2) ?></h2>
                            <p class="card-text">Total Payments</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <hr />

    <div class="row">
        <div class="col-lg-6 mb-4">
            <h4>Payments - Last 7 Days</h4>
            <canvas id="paymentsChart" height="250"></canvas>
        </div>

        <div class="col-lg-6">
            <h4>Recent Audit Trail</h4>
            <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                <table class="table table-striped table-sm">
                    <thead class="table-dark">
                        <tr>
                            <th>Date/Time</th>
                            <th>User</th>
                            <th>Role</th>
                            <th>Activity</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if ($auditResult && $auditResult->num_rows > 0) {
                            while ($row = $auditResult->fetch_assoc()) {
                                $createdAt = htmlspecialchars($row['created_at'] ?? '');
                                $userName = htmlspecialchars($row['user_name'] ?? '');
                                $role = htmlspecialchars($row['role'] ?? '');
                                $activity = htmlspecialchars($row['activity'] ?? '');
                                echo "<tr>
                                        <td>$createdAt</td>
                                        <td>$userName</td>
                                        <td>$role</td>
                                        <td>$activity</td>
                                      </tr>";
                            }
                        } else {
                            echo '<tr><td colspan="4" class="text-center">No audit trail entries found.</td></tr>';
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

<script>
const ctx = document.getElementById('paymentsChart').getContext('2d');
const paymentsChart = new Chart(ctx, {
    type: 'bar',
    data: {
        labels: <?= json_encode($chartLabels) ?>,
        datasets: [{
            label: 'Payments (MWK)',
            data: <?= json_encode($chartData) ?>,
            backgroundColor: 'rgba(255, 193, 7, 0.7)',
            borderColor: 'rgba(255, 193, 7, 1)',
            borderWidth: 1,
            borderRadius: 4,
            hoverBackgroundColor: 'rgba(255, 193, 7, 0.9)'
        }]
    },
    options: {
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: value => new Intl.NumberFormat().format(value)
                }
            }
        },
        plugins: {
            legend: { display: true, position: 'top' },
            tooltip: {
                callbacks: {
                    label: ctx => 'MWK ' + ctx.parsed.y.toLocaleString()
                }
            }
        }
    }
});
</script>
</body>
</html>
