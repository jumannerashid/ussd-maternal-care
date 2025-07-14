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

// Handle form submissions and exports
$errors = [];
$clinic_filter = intval($_GET['clinic'] ?? 0);
$export_type = $_GET['export'] ?? '';

// Fetch clinics for dropdown
$stmt = $conn->prepare("SELECT id, name FROM clinics WHERE status = 'active' ORDER BY name ASC");
$stmt->execute();
$clinics = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Fetch metrics (total vaccinated/scheduled)
function get_metrics($clinic_id) {
    global $conn;
    $stmt = $conn->prepare("
        SELECT 
            (SELECT COUNT(*) 
             FROM vaccination_schedule vs 
             JOIN pregnant_women pw ON vs.mother_id = pw.id 
             WHERE vs.status = 'completed' 
               AND (? = 0 OR pw.clinic_id = ?)) AS total_vaccinated,
            (SELECT COUNT(*) 
             FROM vaccination_schedule vs 
             JOIN pregnant_women pw ON vs.mother_id = pw.id 
             WHERE vs.status = 'scheduled' 
               AND (? = 0 OR pw.clinic_id = ?)) AS total_scheduled
    ");
    $stmt->bind_param("iiii", $clinic_id, $clinic_id, $clinic_id, $clinic_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

// Fetch vaccines with clinic ID
function get_vaccines($clinic_id) {
    global $conn;
    $stmt = $conn->prepare("
        SELECT vs.id, pw.full_name AS mother_name, 
               c.id AS clinic_id, c.name AS clinic_name, 
               vs.vaccine_type, vs.scheduled_date, vs.status 
        FROM vaccination_schedule vs 
        JOIN pregnant_women pw ON vs.mother_id = pw.id 
        JOIN clinics c ON pw.clinic_id = c.id 
        WHERE (? = 0 OR pw.clinic_id = ?)
        ORDER BY vs.scheduled_date DESC
    ");
    $stmt->bind_param("ii", $clinic_id, $clinic_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Export to PDF
if ($export_type === 'pdf') {
    require_once('tcpdf/tcpdf.php'); // Ensure TCPDF is installed

    $metrics = get_metrics($clinic_filter);
    $vaccines = get_vaccines($clinic_filter);
    $clinic_name = $clinic_filter ? get_clinic_name($clinic_filter) : 'All Clinics';

    $pdf = new TCPDF();
    $pdf->AddPage();
    $pdf->SetFont('helvetica', '', 12);
    
    $html = "<h2>Vaccination Records - $clinic_name</h2>
             <p>Total Vaccinated: {$metrics['total_vaccinated']}</p>
             <p>Scheduled Vaccines: {$metrics['total_scheduled']}</p>
             <table border='1'>
                 <tr>
                     <th>ID</th>
                     <th>Mother</th>
                     <th>Clinic</th>
                     <th>Vaccine</th>
                     <th>Date</th>
                     <th>Status</th>
                 </tr>";

    foreach ($vaccines as $v) {
        $html .= "<tr>
                     <td>{$v['id']}</td>
                     <td>{$v['mother_name']}</td>
                     <td>{$v['clinic_name']}</td>
                     <td>{$v['vaccine_type']}</td>
                     <td>{$v['scheduled_date']}</td>
                     <td>" . ucfirst($v['status']) . "</td>
                  </tr>";
    }

    $html .= "</table>";
    $pdf->writeHTML($html, true, false, true, false, '');
    $pdf->Output('vaccination_records.pdf', 'D');
    exit;
}

// Get clinic name helper
function get_clinic_name($clinic_id) {
    global $conn;
    $stmt = $conn->prepare("SELECT name FROM clinics WHERE id = ?");
    $stmt->bind_param("i", $clinic_id);
    $stmt->execute();
    $clinic = $stmt->get_result()->fetch_assoc();
    return $clinic ? $clinic['name'] : 'Unknown';
}

// Fetch data
$metrics = get_metrics($clinic_filter);
$vaccines = get_vaccines($clinic_filter);
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vaccine Management - Maternal Care Connect</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <style>
        :root { --primary: #007bff; --success: #28a745; --warning: #ffc107; --danger: #dc3545; }
        body { font-family: 'Nunito', sans-serif; background: #f8f9fa; }
        .sidebar { position: fixed; top: 0; bottom: 0; left: 0; width: 280px; background: #212529; color: white; transition: width 0.3s ease; }
        .content { margin-left: 280px; padding: 2rem; transition: margin-left 0.3s ease; }
        @media (max-width: 992px) { .sidebar { width: 0; } .content { margin-left: 0; } }
        .metric-card { background: white; border-radius: 12px; padding: 20px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); margin: 10px 0; }
        .badge-status { font-size: 0.9rem; padding: 8px 12px; }
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
            <h1 class="text-primary fw-bold">Vaccine Management</h1>
            <div class="d-flex gap-2">
                <button class="btn btn-primary" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
                <select class="form-select" id="clinicFilter" style="width: 250px">
                    <option value="0">All Clinics</option>
                    <?php foreach ($clinics as $clinic): ?>
                        <option value="<?= $clinic['id'] ?>" <?= $clinic_filter == $clinic['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($clinic['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <a href="vaccines.php?export=pdf&clinic=<?= $clinic_filter ?>" class="btn btn-success">
                    <i class="fas fa-file-pdf me-2"></i> Export PDF
                </a>
            </div>
        </div>

        <!-- Metrics Section -->
        <div class="row g-3 mb-4">
            <div class="col-md-6">
                <div class="metric-card">
                    <h5 class="text-muted">Total Vaccinated</h5>
                    <h2 class="text-success"><?= $metrics['total_vaccinated'] ?></h2>
                </div>
            </div>
            <div class="col-md-6">
                <div class="metric-card">
                    <h5 class="text-muted">Scheduled Vaccines</h5>
                    <h2 class="text-warning"><?= $metrics['total_scheduled'] ?></h2>
                </div>
            </div>
        </div>

        <!-- Vaccines Table -->
        <div class="card">
            <div class="card-header d-flex justify-content-between">
                <h5 class="mb-0">Vaccination Schedule</h5>
                <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#addVaccineModal">
                    <i class="fas fa-plus me-2"></i> Schedule Vaccine
                </button>
            </div>
            <div class="card-body">
                <table id="vaccinesTable" class="table table-hover table-bordered">
                    <thead class="table-dark">
                        <tr>
                            <th>ID</th>
                            <th>Mother</th>
                            <th>Clinic</th>
                            <th>Vaccine</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($vaccines as $v): ?>
                            <tr>
                                <td><?= htmlspecialchars($v['id']) ?></td>
                                <td><?= htmlspecialchars($v['mother_name']) ?></td>
                                <td><?= htmlspecialchars($v['clinic_name']) ?></td>
                                <td><?= htmlspecialchars($v['vaccine_type']) ?></td>
                                <td><?= htmlspecialchars($v['scheduled_date']) ?></td>
                                <td>
                                    <span class="badge badge-status bg-<?= $v['status'] === 'completed' ? 'success' : ($v['status'] === 'scheduled' ? 'warning' : 'danger') ?>">
                                        <?= ucfirst(htmlspecialchars($v['status'])) ?>
                                    </span>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-primary edit-vaccine-btn"
                                            data-id="<?= $v['id'] ?>"
                                            data-mother="<?= $v['mother_name'] ?>"
                                            data-clinic="<?= $v['clinic_id'] ?>"
                                            data-vaccine="<?= $v['vaccine_type'] ?>"
                                            data-date="<?= $v['scheduled_date'] ?>"
                                            data-status="<?= $v['status'] ?>"
                                            data-bs-toggle="modal"
                                            data-bs-target="#editVaccineModal">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($vaccines)): ?>
                            <tr><td colspan="7">No vaccinations scheduled</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Add Vaccine Modal -->
    <div class="modal fade" id="addVaccineModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Schedule Vaccine</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="addVaccineForm">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Mother (Search)</label>
                            <input type="text" class="form-control" id="searchMother" placeholder="Name/Phone/ID" required>
                            <div id="searchResults" class="list-group mt-2" style="max-height: 150px; overflow-y: auto"></div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Vaccine Type</label>
                            <select class="form-select" name="vaccine_type" required>
                                <option value="">Select Vaccine</option>
                                <option value="Tetanus Toxoid 1">Tetanus Toxoid 1</option>
                                <option value="Tetanus Toxoid 2">Tetanus Toxoid 2</option>
                                <option value="Influenza">Influenza</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Scheduled Date</label>
                            <input type="date" class="form-control" name="scheduled_date" required>
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

    <!-- Edit Vaccine Modal -->
    <div class="modal fade" id="editVaccineModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Update Vaccine</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="editVaccineForm">
                    <input type="hidden" name="id">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status" required>
                                <option value="scheduled">Scheduled</option>
                                <option value="completed">Completed</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Reassign Clinic</label>
                            <select class="form-select" name="clinic_id" required>
                                <option value="0">Keep Current Clinic</option>
                                <?php foreach ($clinics as $clinic): ?>
                                    <option value="<?= $clinic['id'] ?>"><?= htmlspecialchars($clinic['name']) ?></option>
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

        // Initialize DataTable
        $(document).ready(() => {
            $('#vaccinesTable').DataTable({
                pageLength: 10,
                lengthMenu: [5, 10, 25, 50],
                order: [[4, 'desc']]
            });
        });

        // Real-time Clinic Filter
        $('#clinicFilter').change(function() {
            const clinicId = $(this).val();
            window.location.href = `vaccines.php?clinic=${clinicId}`;
        });

        // Populate Edit Modal
        $('.edit-vaccine-btn').click(function() {
            const data = $(this).data();
            $('#editVaccineForm').find('[name="id"]').val(data.id);
            $('#editVaccineForm').find('[name="status"]').val(data.status);
            $('#editVaccineForm').find('[name="clinic_id"]').val(data.clinic); // Use clinic ID from data
        });

        // Handle Add Form Submission
        $('#addVaccineForm').submit(function(e) {
            e.preventDefault();
            const motherId = $('#searchMother').attr('data-id');
            const formData = {
                action: 'add',
                mother_id: motherId,
                vaccine_type: $('select[name="vaccine_type"]').val(),
                scheduled_date: $('input[name="scheduled_date"]').val()
            };

            $.post('vaccine_actions.php', formData, function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert(response.error);
                }
            }, 'json');
        });

        // Handle Edit Form Submission
        $('#editVaccineForm').submit(function(e) {
            e.preventDefault();
            const formData = $(this).serialize() + '&action=edit';
            
            $.post('vaccine_actions.php', formData, function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert(response.error);
                }
            }, 'json');
        });
    </script>
</body>
</html>