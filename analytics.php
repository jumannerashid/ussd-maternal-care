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

// Fetch key metrics
$total_registered = 0;
$transport_issues = 0;
$near_delivery = 0;
$missed_appointments = 0;

// Total Registered Pregnant Women
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM pregnant_women");
$stmt->execute();
$total_registered = $stmt->get_result()->fetch_assoc()['count'];

// Women with No Transport Access
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM pregnant_women WHERE transport_access = 'No'");
$stmt->execute();
$transport_issues = $stmt->get_result()->fetch_assoc()['count'];

// Women Near Delivery (less than 2 months to give birth)
$stmt = $conn->prepare("
    SELECT COUNT(*) as count 
    FROM pregnant_women 
    WHERE expected_birth_date <= DATE_ADD(NOW(), INTERVAL 2 MONTH)
");
$stmt->execute();
$near_delivery = $stmt->get_result()->fetch_assoc()['count'];

// Missed Appointments
$stmt = $conn->prepare("
    SELECT COUNT(DISTINCT pw.id) as count 
    FROM pregnant_women pw
    JOIN appointments a ON pw.id = a.mother_id
    WHERE a.status = 'Missed'
");
$stmt->execute();
$missed_appointments = $stmt->get_result()->fetch_assoc()['count'];

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pregnant Women Analytics</title>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
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
            font-family: 'Poppins', sans-serif;
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

        .content {
            margin-left: 280px;
            padding: 30px;
            transition: margin-left 0.3s cubic-bezier(0.25, 0.46, 0.45, 0.94);
        }

        .content.collapsed {
            margin-left: 70px;
        }

        .metric-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            cursor: pointer;
            transition: transform 0.3s ease;
            text-align: center;
        }

        .metric-icon {
            font-size: 48px;
            margin-bottom: 15px;
        }

        .table-container {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            margin-top: 30px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            background: #f8f9fa;
            color: #343a40;
            padding: 15px;
            border-bottom: 2px solid #dee2e6;
        }

        td {
            padding: 12px;
            vertical-align: middle;
            border-bottom: 1px solid #dee2e6;
        }

        tr:hover {
            background: #f1f1f1;
        }

        @media (max-width: 768px) {
            .content {
                margin-left: 0;
            }
            .sidebar {
                width: 0 !important;
                overflow: hidden;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="logo">
            <i class="fas fa-heartbeat"></i>
            <span class="ms-2">Maternal Health</span>
        </div>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a href="dashboard.php" class="nav-link">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li class="nav-item active">
                <a href="#" class="nav-link active">
                    <i class="fas fa-chart-line"></i>
                    <span>Analytics</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="clinics.php" class="nav-link">
                    <i class="fas fa-hospital"></i>
                    <span>Clinics</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="health_workers.php" class="nav-link">
                    <i class="fas fa-users"></i>
                    <span>Health Workers</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="appointments.php" class="nav-link">
                    <i class="fas fa-calendar-check"></i>
                    <span>Appointments</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="user_settings.php" class="nav-link">
                    <i class="fas fa-user-cog"></i>
                    <span>User Settings</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="logout.php" class="nav-link">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="content" id="content">
        <div class="header mb-4">
            <h1 class="text-dark">Pregnant Women Analytics</h1>
            <button class="toggle-btn btn btn-primary" onclick="toggleSidebar()">
                <i class="fas fa-bars"></i>
            </button>
        </div>

        <!-- Metrics Row -->
        <div class="row g-3 mx-3">
            <div class="col-md-3">
                <a href="?filter=total_registered" class="metric-card text-dark">
                    <i class="fas fa-users metric-icon" style="color: var(--primary)"></i>
                    <h3><?= $total_registered ?></h3>
                    <p>Total Registered</p>
                </a>
            </div>
            
            <div class="col-md-3">
                <a href="?filter=no_transport" class="metric-card text-dark">
                    <i class="fas fa-car-side metric-icon" style="color: var(--danger)"></i>
                    <h3><?= $transport_issues ?></h3>
                    <p>No Transport Access</p>
                </a>
            </div>
            
            <div class="col-md-3">
                <a href="?filter=near_delivery" class="metric-card text-dark">
                    <i class="fas fa-baby metric-icon" style="color: var(--warning)"></i>
                    <h3><?= $near_delivery ?></h3>
                    <p>Near Delivery</p>
                </a>
            </div>
            
            <div class="col-md-3">
                <a href="?filter=missed_appointments" class="metric-card text-dark">
                    <i class="fas fa-calendar-times metric-icon" style="color: var(--success)"></i>
                    <h3><?= $missed_appointments ?></h3>
                    <p>Missed Appointments</p>
                </a>
            </div>
        </div>

        <!-- Filtered Pregnant Women List -->
        <div class="table-container mx-3">
            <h3 class="mb-3">
                <?= ucwords(str_replace('_', ' ', $_GET['filter'] ?? 'all')) ?> Pregnant Women
                <a href="analytics.php" class="btn btn-sm btn-primary float-end">
                    <i class="fas fa-redo"></i> Reset Filter
                </a>
            </h3>
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Contact</th>
                        <th>Due Date</th>
                        <th>Transport</th>
                        <th>Clinic</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    
// Reconnect to fetch filtered data
$conn = new mysqli($host, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

// Fetch filtered pregnant women based on GET parameters
$filter = $_GET['filter'] ?? 'all';
$pregnant_women = [];

switch ($filter) {
    case 'total_registered':
        // Total Registered Pregnant Women
        $stmt = $conn->prepare("SELECT * FROM pregnant_women");
        $stmt->execute();
        $pregnant_women = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        break;
    case 'no_transport':
        // Women with No Transport Access
        $stmt = $conn->prepare("SELECT * FROM pregnant_women WHERE transport_access = 'No'");
        $stmt->execute();
        $pregnant_women = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        break;
    case 'near_delivery':
        // Women Near Delivery (less than 2 months to give birth)
        $stmt = $conn->prepare("
            SELECT * 
            FROM pregnant_women 
            WHERE expected_birth_date <= DATE_ADD(NOW(), INTERVAL 2 MONTH)
        ");
        $stmt->execute();
        $pregnant_women = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        break;
    case 'missed_appointments':
        // Missed Appointments
        $stmt = $conn->prepare("
            SELECT DISTINCT pw.*
            FROM pregnant_women pw
            JOIN appointments a ON pw.id = a.mother_id
            WHERE a.status = 'Missed'
        ");
        $stmt->execute();
        $pregnant_women = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        break;
    default:
        // Default: All Pregnant Women
        $stmt = $conn->prepare("SELECT * FROM pregnant_women");
        $stmt->execute();
        $pregnant_women = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

$conn->close();
?>

<!-- Filtered Pregnant Women List -->
<div class="table-container mx-3">
    <h3 class="mb-3">
        <?= ucwords(str_replace('_', ' ', $filter)) ?> Pregnant Women
        <a href="analytics.php" class="btn btn-sm btn-primary float-end">
            <i class="fas fa-redo"></i> Reset Filter
        </a>
    </h3>
    <table class="table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Contact</th>
                <th>Due Date</th>
                <th>Transport</th>
                <th>Clinic</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($pregnant_women as $woman): ?>
                <tr>
                    <td><?= htmlspecialchars($woman['id']) ?></td>
                    <td><?= htmlspecialchars($woman['full_name']) ?></td>
                    <td><?= htmlspecialchars($woman['contact_number']) ?></td>
                    <td>
                        <?php if (!empty($woman['expected_birth_date'])): ?>
                            <?= htmlspecialchars($woman['expected_birth_date']) ?>
                        <?php else: ?>
                            N/A
                        <?php endif; ?>
                    </td>
                    <td>
                        <?= $woman['transport_access'] === 'Yes' 
                            ? '<i class="fas fa-check text-success"></i>' 
                            : '<i class="fas fa-times text-danger"></i>' 
                        ?>
                    </td>
                    <td><?= htmlspecialchars($woman['clinic_name'] ?? 'Unassigned') ?></td>
                    <td>
                        <a href="view_pregnant_woman.php?id=<?= $woman['id'] ?>" class="btn btn-sm btn-info">
                            <i class="fas fa-eye"></i>
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($pregnant_women)): ?>
                <tr><td colspan="7">No records found</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<script>
    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        const content = document.getElementById('content');
        sidebar.classList.toggle('collapsed');
        content.classList.toggle('collapsed');
    }
</script>
</body>
</html>