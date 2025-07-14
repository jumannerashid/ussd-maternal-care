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
$editing_worker = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $worker_id = $_POST['id'] ?? '';
    $full_name = trim($_POST['full_name']);
    $phone = preg_replace('/\D/', '', $_POST['phone_number']); // Remove non-digits
    $phone = preg_replace('/^255/', '0', $phone); // Convert +255 to 0
    $role = strtolower($_POST['role'] ?? '');
    $clinic_id = intval($_POST['clinic_id'] ?? 0);

    // Validation
    if (empty($full_name)) {
        $errors[] = "Worker name is required";
    }
    if (empty($phone) || strlen($phone) !== 10 || !preg_match('/^0[76]\d{8}$/', $phone)) {
        $errors[] = "Invalid Tanzanian phone number format (e.g., 0756598665)";
    }
    if (empty($role)) {
        $errors[] = "Role is required";
    }
    if ($clinic_id <= 0) {
        $errors[] = "Clinic selection is invalid";
    }

    if (empty($errors)) {
        if ($action === 'add') {
            $stmt = $conn->prepare("INSERT INTO health_workers(full_name, phone_number, role, clinic_id, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())");
            $stmt->bind_param("sssi", $full_name, $phone, $role, $clinic_id);
        } elseif ($action === 'edit') {
            $stmt = $conn->prepare("UPDATE health_workers SET full_name = ?, phone_number = ?, role = ?, clinic_id = ?, updated_at = NOW() WHERE id = ?");
            $stmt->bind_param("sssii", $full_name, $phone, $role, $clinic_id, $worker_id);
        }

        if ($stmt->execute()) {
            header("Location: health_workers.php");
            exit;
        } else {
            $errors[] = "Database operation failed";
        }
        $stmt->close();
    }
}

// Fetch health workers with created_at/updated_at
$stmt = $conn->prepare("
    SELECT hw.id, hw.full_name, hw.phone_number, hw.role, c.name AS clinic_name, hw.created_at, hw.updated_at 
    FROM health_workers hw 
    JOIN clinics c ON hw.clinic_id = c.id 
    ORDER BY hw.created_at DESC
");
$stmt->execute();
$workers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Fetch clinics for dropdown
$stmt = $conn->prepare("SELECT id, name FROM clinics WHERE status = 'active' ORDER BY name ASC");
$stmt->execute();
$clinics = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$conn->close();

// Get worker for editing
if (isset($_GET['edit'])) {
    $worker_id = intval($_GET['edit']);
    foreach ($workers as $worker) {
        if ($worker['id'] === $worker_id) {
            $editing_worker = $worker;
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
    <title>Health Workers Management - Maternal Care Connect</title>

    <!-- Bootstrap 5.3 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">

    <style>
        body { font-family: 'Nunito', sans-serif; background: #f8f9fa; }
        .sidebar { position: fixed; top: 0; bottom: 0; left: 0; width: 280px; background: #212529; color: white; transition: width 0.3s ease; overflow-y: auto; z-index: 1000; }
        .sidebar.collapsed { width: 70px; }
        .content { margin-left: 280px; padding: 2rem; transition: margin-left 0.3s ease; }
        .content.collapsed { margin-left: 70px; }
        @media (max-width: 992px) { .sidebar { width: 0; } .content { margin-left: 0; } }
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
            <h1 class="text-primary fw-bold">Health Workers Management</h1>
            <button class="btn btn-primary" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addWorkerModal"><i class="fas fa-plus me-2"></i> Add Health Worker</button>
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

        <!-- Health Workers Table -->
        <div class="table-container bg-white rounded shadow p-4">
            <h3 class="mb-3">Health Workers List</h3>
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Phone</th>
                        <th>Role</th>
                        <th>Clinic</th>
                        <th>Created</th>
                        <th>Updated</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($workers as $worker): ?>
                        <tr>
                            <td><?= htmlspecialchars($worker['id']) ?></td>
                            <td><?= htmlspecialchars($worker['full_name']) ?></td>
                            <td><?= htmlspecialchars($worker['phone_number'] ?: 'N/A') ?></td>
                            <td><?= ucfirst(htmlspecialchars($worker['role']) ?: 'N/A') ?></td>
                            <td><?= htmlspecialchars($worker['clinic_name']) ?></td>
                            <td><?= htmlspecialchars($worker['created_at'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($worker['updated_at'] ?? 'N/A') ?></td>
                            <td>
                                <button class="btn btn-sm btn-primary edit-worker-btn"
    data-id="<?= htmlspecialchars($worker['id'] ?? '') ?>"
    data-full-name="<?= htmlspecialchars($worker['full_name'] ?? '') ?>"
    data-phone="<?= htmlspecialchars($worker['phone_number'] ?? '') ?>"
    data-role="<?= htmlspecialchars($worker['role'] ?? '') ?>"
    data-clinic="<?= htmlspecialchars($worker['clinic_id'] ?? '') ?>"
    data-bs-toggle="modal"
    data-bs-target="#editWorkerModal">
    <i class="fas fa-edit"></i>
</button>
                                <form method="POST" style="display:inline" onsubmit="return confirm('Are you sure?')">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $worker['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-danger">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($workers)): ?>
                        <tr>
                            <td colspan="8">No health workers found</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Add Health Worker Modal -->
    <div class="modal fade" id="addWorkerModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Health Worker</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="addWorkerForm" method="POST">
                    <input type="hidden" name="action" value="add">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Full Name</label>
                            <input type="text" class="form-control" name="full_name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Phone Number</label>
                            <input type="tel" class="form-control" name="phone_number" placeholder="0756598665" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Role</label>
                            <select class="form-select" name="role" required>
                                <option value="">Select Role</option>
                                <option value="nurse">Nurse</option>
                                <option value="doctor">Doctor</option>
                                <option value="midwife">Midwife</option>
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
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Health Worker Modal -->
    <div class="modal fade" id="editWorkerModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Health Worker</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="editWorkerForm" method="POST">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Full Name</label>
                            <input type="text" class="form-control" name="full_name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Phone Number</label>
                            <input type="tel" class="form-control" name="phone_number" placeholder="0756598665" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Role</label>
                            <select class="form-select" name="role" required>
                                <option value="">Select Role</option>
                                <option value="nurse">Nurse</option>
                                <option value="doctor">Doctor</option>
                                <option value="midwife">Midwife</option>
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
        $('.edit-worker-btn').click(function() {
            const worker = $(this).data();
            $('#editWorkerForm').find('[name="id"]').val(worker.id);
            $('#editWorkerForm').find('[name="full_name"]').val(worker.fullName);
            $('#editWorkerForm').find('[name="phone_number"]').val(worker.phone);
            $('#editWorkerForm').find('[name="role"]').val(worker.role);
            $('#editWorkerForm').find('[name="clinic_id"]').val(worker.clinic);
        });
    </script>
</body>
</html>

<?php
// --- SMS Functions (Proven Method) ---
function sendSms($phone_number, $message) {
    $token = getBearerToken();
    if (!$token) {
        error_log("SMS Failed: Authentication required.");
        return false;
    }

    $url = "https://mambosms.co.tz/api/v1/sms/single"; // Correct URL from your test
    $data = [
        "sender_id" => "InfoNotice", // Your approved sender ID
        "message" => $message,
        "mobile" => $phone_number // Local format (no +255)
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-Type: application/json",
        "Authorization: Bearer " . $token
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    $response = curl_exec($ch);
    
    if (curl_errno($ch)) {
        error_log("cURL Error: " . curl_error($ch));
        curl_close($ch);
        return false;
    }
    curl_close($ch);

    $responseData = json_decode($response, true);
    if (isset($responseData['status']) && $responseData['status'] === "success") {
        return true;
    } else {
        error_log("SMS Error: " . ($responseData['message'] ?? "Unknown error"));
        return false;
    }
}

// --- Authentication (Proven Method) ---
function getBearerToken() {
    $url = "https://mambosms.co.tz/api/v1/login"; // URL that worked for you
    $data = [
        "phone_number" => "0756598665", // Nurse’s phone number (your credentials)
        "password" => "LuisPedro19"     // Nurse’s password
    ];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    $response = curl_exec($ch);
    curl_close($ch);

    $responseData = json_decode($response, true);
    if (isset($responseData['success']) && $responseData['success'] == 1) {
        return $responseData['data']['token'];
    } else {
        error_log("Auth Failed: " . ($responseData['message'] ?? "No response"));
        return null;
    }
}
?>