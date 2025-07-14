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

// Fetch dashboard metrics
$total_pregnant_women = 0;
$upcoming_appointments = 0;
$active_clinics = 0;
$recent_registrations = [];
$monthly_registrations = [];
$clinic_performance = [];

// Total Pregnant Women
$stmt = $conn->prepare("SELECT COUNT(*) FROM pregnant_women");
$stmt->execute();
$stmt->bind_result($total_pregnant_women);
$stmt->fetch();
$stmt->close();

// Upcoming Appointments
$stmt = $conn->prepare("SELECT COUNT(*) FROM appointments WHERE appointment_date >= CURDATE() AND status = 'Scheduled'");
$stmt->execute();
$stmt->bind_result($upcoming_appointments);
$stmt->fetch();
$stmt->close();

// Active Clinics (using 'status' column instead of 'active')
$stmt = $conn->prepare("SELECT COUNT(*) FROM clinics WHERE status = 'Active'");
$stmt->execute();
$stmt->bind_result($active_clinics);
$stmt->fetch();
$stmt->close();

// Recent Registrations
$stmt = $conn->prepare("SELECT id, full_name, created_at FROM pregnant_women ORDER BY created_at DESC LIMIT 5");
$stmt->execute();
$recent_registrations = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Monthly Registration Trend
$stmt = $conn->prepare("
    SELECT DATE_FORMAT(created_at, '%Y-%m') AS month, COUNT(*) AS count 
    FROM pregnant_women 
    GROUP BY DATE_FORMAT(created_at, '%Y-%m') 
    ORDER BY month ASC
");
$stmt->execute();
$monthly_registrations = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Clinic Performance
$stmt = $conn->prepare("
    SELECT c.name, COUNT(a.id) AS appointments 
    FROM clinics c 
    LEFT JOIN appointments a ON c.id = a.clinic_id 
    GROUP BY c.id
");
$stmt->execute();
$clinic_performance = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Maternal Care Connect</title>

    <!-- Bootstrap 5.3 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        :root {
            --primary: #007bff;
            --secondary: #6c757d;
            --success: #28a745;
            --danger: #dc3545;
            --warning: #ffc107;
            --light: #f8f9fa;
            --dark: #343a40;
            --accent: #ff6b6b;
        }

        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #ebebeb 100%);
            font-family: 'Nunito', sans-serif;
            min-height: 100vh;
        }

        .sidebar {
            position: fixed;
            top: 0;
            bottom: 0;
            left: 0;
            width: 280px;
            background: var(--dark);
            color: white;
            transition: width 0.3s ease;
            overflow-y: auto;
        }

        .sidebar.collapsed {
            width: 70px;
        }

        .sidebar .logo {
            padding: 1rem;
            display: flex;
            align-items: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .sidebar .logo i {
            font-size: 1.5rem;
            margin-right: 10px;
        }

        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.8);
            padding: 1rem;
            transition: all 0.3s ease;
            border-left: 3px solid transparent;
        }

        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            border-left-color: var(--primary);
        }

        .content {
            margin-left: 280px;
            padding: 2rem;
            transition: margin-left 0.3s ease;
        }

        .content.collapsed {
            margin-left: 70px;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
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
            padding: 0.75rem 1.5rem;
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
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .metric-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            padding: 1.5rem;
            transition: transform 0.3s ease;
        }

        .metric-card:hover {
            transform: translateY(-5px);
        }

        .metric-card h5 {
            color: var(--secondary);
            margin-bottom: 1rem;
            font-size: 1rem;
        }

        .metric-card p {
            font-family: 'Poppins', sans-serif;
            font-size: 1.75rem;
            color: var(--dark);
            margin: 0;
        }

        .metric-icon {
            font-size: 2rem;
            margin-bottom: 1rem;
            color: var(--primary);
        }

        /* Charts Section */
        .charts-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .chart-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            padding: 1.5rem;
            height: 400px;
            overflow: hidden;
        }

        .chart-wrapper {
            position: relative;
            height: 300px;
        }

        /* Recent Activity */
        .recent-activity {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            padding: 1.5rem;
        }

        .recent-activity table {
            width: 100%;
            border-collapse: collapse;
        }

        .recent-activity th,
        .recent-activity td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid #dee2e6;
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
        <div class="header">
            <h1>Dashboard</h1>
            <button class="toggle-btn" onclick="toggleSidebar()">
                <i class="fas fa-bars"></i>
            </button>
        </div>

        <!-- Metrics Section -->
        <div class="metrics-grid">
            <div class="metric-card">
                <i class="fas fa-female metric-icon"></i>
                <h5>Total Pregnant Women</h5>
                <p><?= $total_pregnant_women ?></p>
            </div>
            <div class="metric-card">
                <i class="fas fa-calendar-alt metric-icon"></i>
                <h5>Upcoming Appointments</h5>
                <p><?= $upcoming_appointments ?></p>
            </div>
            <div class="metric-card">
                <i class="fas fa-clinic-medical metric-icon"></i>
                <h5>Active Clinics</h5>
                <p><?= $active_clinics ?></p>
            </div>
        </div>

        <!-- Charts Section -->
        <div class="charts-container">
            <div class="chart-card">
                <h5 class="mb-3">Monthly Registration Trend</h5>
                <div class="chart-wrapper">
                    <canvas id="monthlyTrend"></canvas>
                </div>
            </div>
            <div class="chart-card">
                <h5 class="mb-3">Clinic Performance</h5>
                <div class="chart-wrapper">
                    <canvas id="clinicPerformance"></canvas>
                </div>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="recent-activity">
            <h5 class="mb-3">Recent Registrations</h5>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Date Registered</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_registrations as $registration): ?>
                        <tr>
                            <td><?= htmlspecialchars($registration['id']) ?></td>
                            <td><?= htmlspecialchars($registration['full_name']) ?></td>
                            <td><?= htmlspecialchars($registration['created_at']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        // Toggle Sidebar
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const content = document.getElementById('content');
            sidebar.classList.toggle('collapsed');
            content.classList.toggle('collapsed');
        }

        // Monthly Registration Trend Chart
        const monthlyTrendCtx = document.getElementById('monthlyTrend').getContext('2d');
        new Chart(monthlyTrendCtx, {
            type: 'line',
            data: {
                labels: <?= json_encode(array_column($monthly_registrations, 'month')) ?>,
                datasets: [{
                    label: 'Registrations',
                    data: <?= json_encode(array_column($monthly_registrations, 'count')) ?>,
                    borderColor: 'var(--primary)',
                    backgroundColor: 'rgba(0, 123, 255, 0.1)',
                    tension: 0.4,
                    pointRadius: 6,
                    pointHoverRadius: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.05)' } },
                    x: { grid: { display: false } }
                }
            }
        });

        // Clinic Performance Chart
        const clinicPerformanceCtx = document.getElementById('clinicPerformance').getContext('2d');
        new Chart(clinicPerformanceCtx, {
            type: 'bar',
            data: {
                labels: <?= json_encode(array_column($clinic_performance, 'name')) ?>,
                datasets: [{
                    label: 'Appointments',
                    data: <?= json_encode(array_column($clinic_performance, 'appointments')) ?>,
                    backgroundColor: ['var(--primary)', 'var(--success)', 'var(--danger)', 'var(--warning)'],
                    borderRadius: 8,
                    barThickness: 20
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } }
            }
        });
    </script>
</body>
</html>