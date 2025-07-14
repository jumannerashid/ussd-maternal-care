<?php
// Enable error reporting for debugging (remove in production)
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

// Handle form submissions
$errors = [];
$search_results = [];
$editing_appointment = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // Search Mother
    if ($action === 'search_mother') {
        $search_term = trim($_POST['search_term']);
        $search_type = $_POST['search_type'];
        $appointment_id = $_POST['appointment_id'] ?? null;
        
        if (empty($search_term)) {
            $errors[] = "Search term is required";
        } else {
            switch ($search_type) {
                case 'name':
                    $stmt = $conn->prepare("SELECT id, full_name FROM pregnant_women WHERE full_name LIKE ? LIMIT 10");
                    $search_term = "%" . $search_term . "%";
                    break;
                case 'phone':
                    $stmt = $conn->prepare("SELECT id, full_name FROM pregnant_women WHERE contact_number = ?");
                    break;
                default:
                    $errors[] = "Invalid search type";
                    break;
            }
            
            if (isset($stmt)) {
                $stmt->bind_param("s", $search_term);
                $stmt->execute();
                $search_results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                $stmt->close();
            }
        }
    }
    
    // Add/Edit Appointment
    if (in_array($action, ['add', 'edit'])) {
        $mother_id = intval($_POST['mother_id'] ?? 0);
        $clinic_id = intval($_POST['clinic_id'] ?? 0);
        $appointment_date = $_POST['appointment_date'] ?? '';
        $status = strtolower($_POST['status'] ?? '');
        
        // Validation
        if (empty($mother_id)) {
            $errors[] = "Mother selection required";
        }
        if (empty($clinic_id)) {
            $errors[] = "Clinic selection required";
        }
        if (empty($appointment_date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $appointment_date)) {
            $errors[] = "Invalid date format (YYYY-MM-DD)";
        }
        if (empty($status)) {
            $errors[] = "Status is required";
        }
        
        if (empty($errors)) {
            if ($action === 'add') {
                $stmt = $conn->prepare("
                    INSERT INTO appointments(mother_id, clinic_id, appointment_date, status, created_at, updated_at) 
                    VALUES (?, ?, ?, ?, NOW(), NOW())
                ");
                $stmt->bind_param("iiss", $mother_id, $clinic_id, $appointment_date, $status);
            } elseif ($action === 'edit') {
                $appointment_id = $_POST['appointment_id'];
                $stmt = $conn->prepare("
                    UPDATE appointments 
                    SET mother_id = ?, clinic_id = ?, appointment_date = ?, status = ?, updated_at = NOW() 
                    WHERE id = ?
                ");
                $stmt->bind_param("iissi", $mother_id, $clinic_id, $appointment_date, $status, $appointment_id);
            }
            
            if ($stmt->execute()) {
                header("Location: appointments.php");
                exit;
            } else {
                $errors[] = "Database operation failed";
            }
            $stmt->close();
        }
    }
}

// Fetch appointments with related data
$stmt = $conn->prepare("
    SELECT a.id, p.full_name AS mother_name, c.name AS clinic_name, a.appointment_date, a.status 
    FROM appointments a 
    JOIN pregnant_women p ON a.mother_id = p.id 
    JOIN clinics c ON a.clinic_id = c.id 
    ORDER BY a.created_at DESC
");
$stmt->execute();
$appointments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Fetch clinics for dropdown
$stmt = $conn->prepare("SELECT id, name FROM clinics WHERE status = 'active' ORDER BY name ASC");
$stmt->execute();
$clinics = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Fetch mothers for dropdown
$stmt = $conn->prepare("SELECT id, full_name FROM pregnant_women ORDER BY full_name ASC");
$stmt->execute();
$mothers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$conn->close();

// Get appointment for editing
if (isset($_GET['edit'])) {
    $appointment_id = intval($_GET['edit']);
    foreach ($appointments as $appointment) {
        if ($appointment['id'] === $appointment_id) {
            $editing_appointment = $appointment;
            break;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointments Management - Maternal Care Connect</title>

    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600&family=Poppins:wght@500;600;700&display=swap" rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        :root {
            --primary: #007bff;
            --success: #28a745;
            --danger: #dc3545;
            --light: #f8f9fa;
            --dark: #343a40;
        }

        body {
            font-family: 'Nunito', sans-serif;
            background: var(--light);
            margin: 0;
            padding: 0;
        }

        .sidebar {
            position: fixed;
            top: 0;
            bottom: 0;
            left: 0;
            width: 280px;
            background: linear-gradient(180deg, #2d3436 0%, #212529 100%);
            color: white;
            transition: width 0.3s cubic-bezier(0.25, 0.46, 0.45, 0.94);
            overflow-y: auto;
        }

        .sidebar.collapsed {
            width: 70px;
        }

        .sidebar .logo {
            padding: 20px 15px;
            background: #1a1a1d;
            border-bottom: 1px solid #4a4a4a;
            font-family: 'Poppins', sans-serif;
            font-size: 18px;
        }

        .sidebar .nav-link {
            display: flex;
            align-items: center;
            padding: 12px 20px;
            color: rgba(255, 255, 255, 0.8);
            transition: all 0.3s ease;
            border-left: 3px solid transparent;
        }

        .sidebar .nav-link:hover {
            background: rgba(255, 255, 255, 0.1);
            color: white;
        }

        .sidebar .nav-link.active {
            background: rgba(255, 255, 255, 0.2);
            border-left-color: var(--primary);
        }

        .content {
            margin-left: 280px;
            padding: 30px;
            transition: margin-left 0.3s cubic-bezier(0.25, 0.46, 0.45, 0.94);
        }

        .content.collapsed {
            margin-left: 70px;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .header h1 {
            font-family: 'Poppins', sans-serif;
            font-weight: 600;
            color: var(--dark);
            margin: 0;
        }

        .toggle-btn {
            background: var(--primary);
            border: none;
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            transition: background 0.3s ease;
        }

        .toggle-btn:hover {
            background: #0056b3;
        }

        /* Metrics Section */
        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }

        .metric-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        .metric-card h3 {
            font-size: 18px;
            font-weight: 600;
            margin: 0;
        }

        .metric-card p {
            font-size: 24px;
            font-weight: 700;
            margin: 10px 0;
        }

        /* Table Styles */
        .table-container {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }

        .table thead th {
            font-weight: 600;
            color: var(--dark);
        }

        .table tbody tr:hover {
            background: #f1f1f1;
        }

        /* Form Styles */
        .form-container {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }

        .form-floating {
            margin-bottom: 20px;
            position: relative;
        }

        .form-floating input,
        .form-floating select {
            border: 2px solid #e9ecef;
            border-radius: 6px;
            padding: 12px;
            font-size: 14px;
        }

        .form-floating input:focus,
        .form-floating select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.25);
        }

        @media (max-width: 768px) {
            .content {
                margin-left: 0;
            }

            .sidebar {
                width: 0 !important;
                overflow: hidden;
            }

            .form-container {
                padding: 20px;
            }
        }
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
        <div class="header">
            <h1 class="text-dark mb-0">Appointments Management</h1>
            <button class="toggle-btn btn btn-primary" onclick="toggleSidebar()">
                <i class="fas fa-bars"></i>
            </button>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <?php foreach ($errors as $error): ?>
                    <div><?= htmlspecialchars($error) ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Search Form -->
        <?php if (isset($_GET['edit']) && empty($search_results)): ?>
            <div class="search-form">
                <form method="POST">
                    <input type="hidden" name="action" value="search_mother">
                    <input type="hidden" name="appointment_id" value="<?= $editing_appointment['id'] ?>">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <select name="search_type" class="form-control" required>
                                <option value="name">Search by Mother's Name</option>
                                <option value="phone">Search by Phone Number</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <input type="text" name="search_term" value="<?= htmlspecialchars($editing_appointment['mother_name'] ?? '') ?>" class="form-control" required>
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-success w-100"><i class="fas fa-search"></i> Search</button>
                        </div>
                    </div>
                </form>
            </div>
        <?php endif; ?>

        <!-- Appointments List -->
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Mother</th>
                        <th>Clinic</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($appointments as $appointment): ?>
                        <tr>
                            <td><?= htmlspecialchars($appointment['id']) ?></td>
                            <td><?= htmlspecialchars($appointment['mother_name']) ?></td>
                            <td><?= htmlspecialchars($appointment['clinic_name']) ?></td>
                            <td><?= htmlspecialchars($appointment['appointment_date']) ?></td>
                            <td><?= ucfirst(htmlspecialchars($appointment['status'])) ?></td>
                            <td>
                                <button class="btn btn-sm btn-success edit-appointment-btn"
        data-id="<?= htmlspecialchars((string)($appointment['id'] ?? '')) ?>"
        data-mother-id="<?= htmlspecialchars((string)($appointment['mother_id'] ?? '')) ?>"
        data-clinic-id="<?= htmlspecialchars((string)($appointment['clinic_id'] ?? '')) ?>"
        data-appointment-date="<?= htmlspecialchars((string)($appointment['appointment_date'] ?? '')) ?>"
        data-status="<?= htmlspecialchars((string)($appointment['status'] ?? '')) ?>"
        data-bs-toggle="modal"
        data-bs-target="#editAppointmentModal">
    <i class="fas fa-edit"></i>
</button>
                                <form method="POST" style="display:inline" onsubmit="return confirm('Are you sure?')">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="appointment_id" value="<?= $appointment['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-danger">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($appointments)): ?>
                        <tr>
                            <td colspan="6">No appointments found</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Add Appointment Modal -->
    <div class="modal fade" id="addAppointmentModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Appointment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="addAppointmentForm" method="POST">
                    <input type="hidden" name="action" value="add">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Mother</label>
                            <select class="form-select" name="mother_id" required>
                                <option value="">Select Mother</option>
                                <?php foreach ($mothers as $mother): ?>
                                    <option value="<?= $mother['id'] ?>"><?= $mother['full_name'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Clinic</label>
                            <select class="form-select" name="clinic_id" required>
                                <option value="">Select Clinic</option>
                                <?php foreach ($clinics as $clinic): ?>
                                    <option value="<?= $clinic['id'] ?>"><?= $clinic['name'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Appointment Date</label>
                            <input type="date" class="form-control" name="appointment_date" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status" required>
                                <option value="">Select Status</option>
                                <option value="scheduled">Scheduled</option>
                                <option value="completed">Completed</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Appointment Modal -->
    <div class="modal fade" id="editAppointmentModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Appointment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="editAppointmentForm" method="POST">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="appointment_id">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Mother</label>
                            <select class="form-select" name="mother_id" required>
                                <option value="">Select Mother</option>
                                <?php foreach ($mothers as $mother): ?>
                                    <option value="<?= $mother['id'] ?>"><?= $mother['full_name'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Clinic</label>
                            <select class="form-select" name="clinic_id" required>
                                <option value="">Select Clinic</option>
                                <?php foreach ($clinics as $clinic): ?>
                                    <option value="<?= $clinic['id'] ?>"><?= $clinic['name'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Appointment Date</label>
                            <input type="date" class="form-control" name="appointment_date" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status" required>
                                <option value="">Select Status</option>
                                <option value="scheduled">Scheduled</option>
                                <option value="completed">Completed</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Dependencies -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Toggle Sidebar
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const content = document.getElementById('content');
            sidebar.classList.toggle('collapsed');
            content.classList.toggle('collapsed');
        }

        // Populate Edit Modal
        $('.edit-appointment-btn').click(function () {
            const appointment = $(this).data();
            $('#editAppointmentForm').find('[name="appointment_id"]').val(appointment.id);
            $('#editAppointmentForm').find('[name="mother_id"]').val(appointment.motherId);
            $('#editAppointmentForm').find('[name="clinic_id"]').val(appointment.clinicId);
            $('#editAppointmentForm').find('[name="appointment_date"]').val(appointment.appointmentDate);
            $('#editAppointmentForm').find('[name="status"]').val(appointment.status);
        });
    </script>
</body>
</html>