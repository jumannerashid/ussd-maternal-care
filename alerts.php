<?php
// alert.php

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

// Redirect if not authenticated
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

// Database credentials
$host = '173.252.167.30';
$dbname = 'technol4_maternal_health';
$username = 'technol4_health_user';
$password = 'health_user';

// Database connection
try {
    $conn = new mysqli($host, $username, $password, $dbname);
    if ($conn->connect_error) {
        throw new Exception("Database connection failed: " . $conn->connect_error);
    }
} catch (Exception $e) {
    error_log("Database Error: " . $e->getMessage());
    die("Database connection failed. Please try again later.");
}

// Fetch clinics for dropdown
$stmt = $conn->prepare("SELECT id, name FROM clinics WHERE status = 'active' ORDER BY name ASC");
$stmt->execute();
$clinics = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Fetch mothers with upcoming vaccines/appointments (≤3 days)
function get_upcoming() {
    global $conn;
    $stmt = $conn->prepare("
        (SELECT 'vaccine' AS type, vs.id, vs.mother_id, vs.scheduled_date, 
                pw.full_name, c.name AS clinic
         FROM vaccination_schedule vs
         JOIN pregnant_women pw ON vs.mother_id = pw.id
         JOIN clinics c ON pw.clinic_id = c.id
         WHERE vs.status = 'scheduled'
           AND DATEDIFF(vs.scheduled_date, CURDATE()) BETWEEN 0 AND 3)
        UNION
        (SELECT 'appointment' AS type, a.id, a.mother_id, a.appointment_date, 
                pw.full_name, c.name AS clinic
         FROM appointments a
         JOIN pregnant_women pw ON a.mother_id = pw.id
         JOIN clinics c ON pw.clinic_id = c.id
         WHERE a.status = 'scheduled'
           AND DATEDIFF(a.appointment_date, CURDATE()) BETWEEN 0 AND 3)
        ORDER BY scheduled_date ASC
    ");
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Fetch mothers due in 3 months (90 days)
function get_due_soon() {
    global $conn;
    $stmt = $conn->prepare("
        SELECT *, DATEDIFF(expected_birth_date, CURDATE()) AS days_remaining
        FROM pregnant_women
        WHERE DATEDIFF(expected_birth_date, CURDATE()) BETWEEN 0 AND 90
    ");
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Get data
$upcoming = get_upcoming();
$due_soon = get_due_soon();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maternal Care Alerts</title>
    
    <!-- Bootstrap 5.3 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- DataTables -->
    <link href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css" rel="stylesheet">

    <style>
        :root {
            --primary: #2c7be5;
            --success: #19b378;
            --warning: #f7b924;
            --danger: #e53e3e;
            --dark: #2d3436;
        }
        body { font-family: 'Inter', sans-serif; background: #f8fafc; }
        .sidebar { position: fixed; top: 0; bottom: 0; left: 0; width: 280px; background: linear-gradient(180deg, #2d3436 0%, #212529 100%); color: white; transition: width 0.3s ease; }
        .content { margin-left: 280px; padding: 2rem; transition: margin-left 0.3s ease; }
        @media (max-width: 992px) { .sidebar { width: 0; } .content { margin-left: 0; } }
        .card { border: none; border-radius: 15px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); }
        .status-badge { font-size: 0.9rem; padding: 8px 16px; border-radius: 20px; }
        .modal-content { border-radius: 18px; }
    </style>
</head>
<body>
   <!-- Sidebar -->
<div class="sidebar" id="sidebar">
    <div class="logo p-3">
        <i class="fas fa-heartbeat me-3"></i>
        <span class="d-none d-md-inline fw-bold">Maternal Health</span>
    </div>
    <ul class="nav flex-column mt-4 px-3">
        <li class="nav-item <?= (basename($_SERVER['PHP_SELF']) == 'dashboard.php') ? 'active' : '' ?>">
            <a href="dashboard.php" class="nav-link text-white">
                <i class="fas fa-tachometer-alt me-3"></i>
                <span class="d-none d-md-inline">Dashboard</span>
            </a>
        </li>
        <li class="nav-item <?= (basename($_SERVER['PHP_SELF']) == 'clinics.php') ? 'active' : '' ?>">
            <a href="clinics.php" class="nav-link text-white">
                <i class="fas fa-hospital me-3"></i>
                <span class="d-none d-md-inline">Clinics</span>
            </a>
        </li>
        <li class="nav-item <?= (basename($_SERVER['PHP_SELF']) == 'health_workers.php') ? 'active' : '' ?>">
            <a href="health_workers.php" class="nav-link text-white">
                <i class="fas fa-users me-3"></i>
                <span class="d-none d-md-inline">Health Workers</span>
            </a>
        </li>
        <li class="nav-item <?= (basename($_SERVER['PHP_SELF']) == 'appointments.php') ? 'active' : '' ?>">
            <a href="appointments.php" class="nav-link text-white">
                <i class="fas fa-calendar-check me-3"></i>
                <span class="d-none d-md-inline">Appointments</span>
            </a>
        </li>
        <li class="nav-item <?= (basename($_SERVER['PHP_SELF']) == 'vaccines.php') ? 'active' : '' ?>">
            <a href="vaccines.php" class="nav-link text-white">
                <i class="fas fa-syringe me-3"></i>
                <span class="d-none d-md-inline">Vaccines</span>
            </a>
        </li>
        <li class="nav-item <?= (basename($_SERVER['PHP_SELF']) == 'alerts.php') ? 'active' : '' ?>">
            <a href="alerts.php" class="nav-link text-white">
                <i class="fas fa-exclamation-triangle me-3"></i>
                <span class="d-none d-md-inline">Alerts</span>
            </a>
        </li>
        <li class="nav-item <?= (basename($_SERVER['PHP_SELF']) == 'user_settings.php') ? 'active' : '' ?>">
            <a href="user_settings.php" class="nav-link text-white">
                <i class="fas fa-user-cog me-3"></i>
                <span class="d-none d-md-inline">User Settings</span>
            </a>
        </li>
        <li class="nav-item">
            <a href="logout.php" class="nav-link text-white">
                <i class="fas fa-sign-out-alt me-3"></i>
                <span class="d-none d-md-inline">Logout</span>
            </a>
        </li>
    </ul>
</div>

    <!-- Main Content -->
    <div class="content" id="content">
        <div class="d-flex justify-content-between align-items-center mb-5">
            <h1 class="text-primary fw-bold">Maternal Care Alerts</h1>
            <div class="d-flex gap-3">
                <button class="btn btn-primary rounded-pill px-4" onclick="toggleSidebar()">
                    <i class="fas fa-bars"></i>
                </button>
                <button class="btn btn-danger rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#sendRemindersModal">
                    <i class="fas fa-bell me-2"></i> Send Bulk Reminders
                </button>
            </div>
        </div>

        <!-- Metrics Section -->
        <div class="row g-4 mb-4">
            <div class="col-md-6">
                <div class="card metric-card">
                    <div class="card-body">
                        <h5 class="card-title text-muted">Upcoming Appointments/Vaccines</h5>
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Mother</th>
                                    <th>Type</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($upcoming as $item): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($item['full_name']) ?></td>
                                        <td><?= ucfirst($item['type']) ?></td>
                                        <td><?= htmlspecialchars($item['scheduled_date'] ?? $item['appointment_date']) ?></td>
                                        <td>
    <?php 
        $status = isset($item['status']) ? $item['status'] : 'unknown';

        if ($status === 'completed') {
            $badgeClass = 'success';
        } elseif ($status === 'scheduled') {
            $badgeClass = 'primary';
        } else {
            $badgeClass = 'danger';
        }
    ?>
    <span class="badge status-badge bg-<?= htmlspecialchars($badgeClass) ?>">
        <?= ucfirst(htmlspecialchars($status)) ?>
    </span>
</td>
                                        <td>
                                            <button class="btn btn-sm btn-info view-mother-btn"
                                                    data-id="<?= $item['mother_id'] ?>"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#motherModal">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($upcoming)): ?>
                                    <tr><td colspan="5">No upcoming alerts</td></tr>
                                <?php endif; ?>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card metric-card">
                    <div class="card-body">
                        <h5 class="card-title text-muted">Mothers Due in 3 Months</h5>
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Mother</th>
                                    <th>Due Date</th>
                                    <th>Days Remaining</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($due_soon as $mother): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($mother['full_name']) ?></td>
                                        <td><?= htmlspecialchars($mother['expected_birth_date']) ?></td>
                                        <td><?= htmlspecialchars($mother['days_remaining']) ?> days</td>
                                        <td>
                                            <button class="btn btn-sm btn-info view-mother-btn"
                                                    data-id="<?= $mother['id'] ?>"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#motherModal">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($due_soon)): ?>
                                    <tr><td colspan="4">No mothers due soon</td></tr>
                                <?php endif; ?>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Send Reminders Modal -->
    <div class="modal fade" id="sendRemindersModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header border-bottom-0">
                    <h5 class="modal-title text-primary fw-bold">Send Bulk Reminders</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted">Select group to notify:</p>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="reminderType" id="upcomingGroup" value="upcoming" checked>
                        <label class="form-check-label" for="upcomingGroup">
                            Upcoming Appointments/Vaccines (≤3 days)
                        </label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="reminderType" id="birthGroup" value="birth">
                        <label class="form-check-label" for="birthGroup">
                            Mothers Due in 3 Months
                        </label>
                    </div>
                </div>
                <div class="modal-footer border-top-0">
                    <button type="button" class="btn btn-secondary rounded-pill" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger rounded-pill" id="sendBulkReminders">
                        <i class="fas fa-paper-plane me-2"></i> Send Reminders
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Mother Details Modal -->
    <div class="modal fade" id="motherModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header border-bottom-0">
                    <h5 class="modal-title text-primary fw-bold">Mother Profile</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="motherDetails">
                    <!-- Content loaded via AJAX -->
                </div>
                <div class="modal-footer border-top-0">
                    <button type="button" class="btn btn-secondary rounded-pill" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Dependencies -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>

    <script>
        // Toggle Sidebar
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const content = document.getElementById('content');
            sidebar.classList.toggle('collapsed');
            content.classList.toggle('collapsed');
        }

        // Load Mother Details via AJAX
        $('.view-mother-btn').click(function() {
            const motherId = $(this).data('id');
            
            $.ajax({
                url: 'get_mother.php',
                method: 'GET',
                data: { id: motherId },
                success: function(data) {
                    $('#motherDetails').html(data);
                },
                error: function() {
                    $('#motherDetails').html('<div class="alert alert-danger">Failed to load details</div>');
                }
            });
        });

        // Bulk SMS Reminder Handler
        $('#sendBulkReminders').click(function() {
            const reminderType = $('input[name="reminderType"]:checked').val();
            
            if (confirm(`Send reminders to all ${reminderType === 'upcoming' ? 'upcoming appointments/vaccines' : 'mothers due in 3 months'}?`)) {
                $.ajax({
                    url: 'send_reminder.php',
                    method: 'POST',
                    data: { type: reminderType },
                    success: function(response) {
                        if (response.success) {
                            alert(`Sent ${response.count} reminders successfully`);
                        } else {
                            alert('Failed to send reminders. Check logs.');
                        }
                    }
                });
            }
        });
    </script>
</body>
</html>