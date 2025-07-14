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
$editing_clinic = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $clinic_id = $_POST['id'] ?? '';
    $name = trim($_POST['name']);
    $location = trim($_POST['location']);
    $phone = preg_replace('/\D/', '', $_POST['phone_number']);
    $status = strtolower($_POST['status'] ?? 'active'); // Convert status to lowercase

    // Validation
    if (empty($name)) {
        $errors[] = "Clinic name is required";
    }
    if (empty($location)) {
        $errors[] = "Region is required";
    }
    if (!empty($phone) && (strlen($phone) < 10 || strlen($phone) > 15)) {
        $errors[] = "Invalid phone number format";
    }
    if (!in_array($status, ['active', 'inactive'])) { // Ensure status is valid
        $errors[] = "Invalid status value";
    }

    if (empty($errors)) {
        if ($action === 'add') {
            $stmt = $conn->prepare("INSERT INTO clinics(name, location, phone_number, status, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())");
            $stmt->bind_param("ssss", $name, $location, $phone, $status);
        } elseif ($action === 'edit') {
            $stmt = $conn->prepare("UPDATE clinics SET name = ?, location = ?, phone_number = ?, status = ?, updated_at = NOW() WHERE id = ?");
            $stmt->bind_param("sssii", $name, $location, $phone, $status, $clinic_id);
        }

        if ($stmt->execute()) {
            header("Location: clinics.php");
            exit;
        } else {
            $errors[] = "Database operation failed";
        }
        $stmt->close();
    }
}

// Fetch clinics data
$stmt = $conn->prepare("SELECT * FROM clinics ORDER BY created_at DESC");
$stmt->execute();
$clinics = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$conn->close();

// Get clinic for editing
if (isset($_GET['edit'])) {
    $clinic_id = intval($_GET['edit']);
    foreach ($clinics as $clinic) {
        if ($clinic['id'] === $clinic_id) {
            $editing_clinic = $clinic;
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
    <title>Clinics Management - Maternal Care Connect</title>

    <!-- Bootstrap 5.3 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">

    <style>
        body {
            font-family: 'Nunito', sans-serif;
            background: #f8f9fa;
        }

        .sidebar {
            position: fixed;
            top: 0;
            bottom: 0;
            left: 0;
            width: 280px;
            background: #212529;
            color: white;
            transition: width 0.3s ease;
            overflow-y: auto;
            z-index: 1000;
        }

        .sidebar.collapsed {
            width: 70px;
        }

        .content {
            margin-left: 280px;
            padding: 2rem;
            transition: margin-left 0.3s ease;
        }

        .content.collapsed {
            margin-left: 70px;
        }

        @media (max-width: 992px) {
            .sidebar {
                width: 0;
            }
            .content {
                margin-left: 0;
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
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="text-primary fw-bold">Clinic Management</h1>
            <button class="btn btn-primary" onclick="toggleSidebar()">
                <i class="fas fa-bars"></i>
            </button>
            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addClinicModal">
                <i class="fas fa-plus me-2"></i> Add Clinic
            </button>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <!-- Clinics Table -->
        <div class="table-container bg-white rounded shadow p-4">
            <h3 class="mb-3">Clinics List</h3>
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Region</th>
                        <th>Phone</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Updated</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($clinics as $clinic): ?>
                        <tr>
                            <td><?= htmlspecialchars($clinic['id']) ?></td>
                            <td><?= htmlspecialchars($clinic['name']) ?></td>
                            <td><?= htmlspecialchars($clinic['location']) ?></td>
                            <td><?= htmlspecialchars($clinic['phone_number'] ?: 'N/A') ?></td>
                            <td>
                                <span class="badge bg-<?= $clinic['status'] === 'active' ? 'success' : 'danger' ?>">
                                    <?= ucfirst($clinic['status']) ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($clinic['created_at']) ?></td>
                            <td><?= htmlspecialchars($clinic['updated_at']) ?></td>
                            <td>
                                <button class="btn btn-sm btn-primary edit-clinic-btn"
                                        data-id="<?= $clinic['id'] ?>"
                                        data-name="<?= $clinic['name'] ?>"
                                        data-location="<?= $clinic['location'] ?>"
                                        data-phone="<?= $clinic['phone_number'] ?>"
                                        data-status="<?= $clinic['status'] ?>"
                                        data-bs-toggle="modal"
                                        data-bs-target="#editClinicModal">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <form method="POST" style="display:inline" onsubmit="return confirm('Are you sure?')">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $clinic['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-danger">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($clinics)): ?>
                        <tr>
                            <td colspan="8">No clinics found</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Add Clinic Modal -->
    <div class="modal fade" id="addClinicModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Clinic</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="addClinicForm" method="POST">
                    <input type="hidden" name="action" value="add">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Clinic Name</label>
                            <input type="text" class="form-control" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Region</label>
                            <select class="form-select" name="location" required>
                                <option value="">Select Region</option>
                                <?php
                                $regions = [
                                    'Arusha', 'Dar es Salaam', 'Dodoma', 'Geita', 'Iringa', 'Kagera',
                                    'Katavi', 'Kigoma', 'Kilimanjaro', 'Lindi', 'Manyara', 'Mara',
                                    'Mbeya', 'Mjini Magharibi', 'Morogoro', 'Mtwara', 'Mwanza', 'Njombe',
                                    'Pemba North', 'Pemba South', 'Pwani', 'Rukwa', 'Ruvuma', 'Shinyanga',
                                    'Simiyu', 'Singida', 'Songwe', 'Tabora', 'Tanga', 'Unguja North',
                                    'Unguja South', 'Unguja Urban', 'Zanzibar Central/South',
                                    'Zanzibar North', 'Zanzibar Urban/West'
                                ];
                                foreach ($regions as $region): ?>
                                    <option value="<?= $region ?>"><?= $region ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Phone Number</label>
                            <input type="tel" class="form-control" name="phone_number" placeholder="+255712345678">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status" required>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
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

    <!-- Edit Clinic Modal -->
    <div class="modal fade" id="editClinicModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Clinic</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="editClinicForm" method="POST">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Clinic Name</label>
                            <input type="text" class="form-control" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Region</label>
                            <select class="form-select" name="location" required>
                                <option value="">Select Region</option>
                                <?php foreach ($regions as $region): ?>
                                    <option value="<?= $region ?>"><?= $region ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Phone Number</label>
                            <input type="tel" class="form-control" name="phone_number" placeholder="+255712345678">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status" required>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
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
        $('.edit-clinic-btn').click(function() {
            const clinic = $(this).data();
            $('#editClinicForm').find('[name="id"]').val(clinic.id);
            $('#editClinicForm').find('[name="name"]').val(clinic.name);
            $('#editClinicForm').find('[name="location"]').val(clinic.location);
            $('#editClinicForm').find('[name="phone_number"]').val(clinic.phone);
            $('#editClinicForm').find('[name="status"]').val(clinic.status);
        });
    </script>
</body>
</html>