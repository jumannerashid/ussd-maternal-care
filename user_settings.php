<?php
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

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_password = $_POST['current_password'];
    $new_email = trim($_POST['new_email']);
    $new_password = trim($_POST['new_password']);

    // Fetch admin details
    $stmt = $conn->prepare("SELECT email, password FROM administrators WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['admin_id']);
    $stmt->execute();
    $admin = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // Validate current password
    if (!password_verify($current_password, $admin['password'])) {
        $errors[] = "Current password is incorrect";
    }

    // Validate new email
    if (!empty($new_email)) {
        if (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Invalid email format";
        } else {
            $stmt = $conn->prepare("SELECT id FROM administrators WHERE email = ? AND id != ?");
            $stmt->bind_param("si", $new_email, $_SESSION['admin_id']);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                $errors[] = "Email already exists";
            }
        }
    }

    // Validate new password
    if (!empty($new_password)) {
        if (strlen($new_password) < 12) {
            $errors[] = "Password must be at least 12 characters";
        }
        if (!preg_match('/[A-Z]/', $new_password)) {
            $errors[] = "Password needs an uppercase letter";
        }
        if (!preg_match('/[a-z]/', $new_password)) {
            $errors[] = "Password needs a lowercase letter";
        }
        if (!preg_match('/[0-9]/', $new_password)) {
            $errors[] = "Password needs a number";
        }
        if (!preg_match('/[^A-Za-z0-9]/', $new_password)) {
            $errors[] = "Password needs a special character";
        }
    }

    // Update if no errors
    if (empty($errors)) {
        $success = true;

        // Update email if provided
        if (!empty($new_email)) {
            $stmt = $conn->prepare("UPDATE administrators SET email = ?, updated_at = NOW() WHERE id = ?");
            $stmt->bind_param("si", $new_email, $_SESSION['admin_id']);
            $stmt->execute();
        }

        // Update password if provided
        if (!empty($new_password)) {
            $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);
            $stmt = $conn->prepare("UPDATE administrators SET password = ?, updated_at = NOW() WHERE id = ?");
            $stmt->bind_param("si", $hashed_password, $_SESSION['admin_id']);
            $stmt->execute();
        }

        if ($success) {
            header("Location: user_settings.php?success=1");
            exit;
        }
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Settings</title>

    <!-- Bootstrap 5.3 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">

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
            z-index: 1000;
        }

        .sidebar.collapsed {
            width: 70px;
        }

        .content {
            margin-left: 280px;
            padding: 30px;
            transition: margin-left 0.3s cubic-bezier(0.25, 0.46, 0.45, 0.94);
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

        .settings-card {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            max-width: 600px;
            margin: 0 auto;
        }

        .form-floating {
            margin-bottom: 20px;
            position: relative;
        }

        .form-floating input {
            border: 2px solid #e9ecef;
            border-radius: 6px;
            padding: 12px;
            font-size: 14px;
        }

        .form-floating input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 2px rgba(0, 123, 255, 0.25);
        }

        .toggle-password {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #6c757d;
        }

        .toggle-password:hover {
            color: var(--primary);
        }

        .btn-primary {
            background: var(--primary);
            border: none;
            padding: 12px 25px;
            border-radius: 6px;
            cursor: pointer;
            transition: background 0.3s ease;
        }

        .btn-primary:hover {
            background: #0056b3;
        }

        .alert {
            border-radius: 6px;
            padding: 15px;
            margin-bottom: 25px;
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
            <li class="nav-item">
                <a href="dashboard.php" class="nav-link text-white">
                    <i class="fas fa-tachometer-alt me-3"></i>
                    <span class="d-none d-md-inline">Dashboard</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="clinics.php" class="nav-link text-white">
                    <i class="fas fa-hospital me-3"></i>
                    <span class="d-none d-md-inline">Clinics</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="health_workers.php" class="nav-link text-white">
                    <i class="fas fa-users me-3"></i>
                    <span class="d-none d-md-inline">Health Workers</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="appointments.php" class="nav-link text-white">
                    <i class="fas fa-calendar-check me-3"></i>
                    <span class="d-none d-md-inline">Appointments</span>
                </a>
            </li>
            <li class="nav-item active">
                <a href="#" class="nav-link text-white active">
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
        <div class="settings-card">
            <h3 class="text-center mb-4">User Settings</h3>

            <?php if (isset($_GET['success'])): ?>
                <div class="alert alert-success">
                    Your settings have been updated successfully!
                </div>
            <?php endif; ?>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?= htmlspecialchars($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-floating mb-3">
                    <input type="password" class="form-control" id="current_password" name="current_password" required>
                    <label for="current_password">Current Password</label>
                </div>

                <div class="form-floating mb-3">
                    <input type="email" class="form-control" id="new_email" name="new_email" value="<?= htmlspecialchars($_POST['new_email'] ?? '') ?>">
                    <label for="new_email">New Email (Optional)</label>
                </div>

                <div class="form-floating mb-3">
                    <input type="password" class="form-control" id="new_password" name="new_password">
                    <label for="new_password">New Password (Optional)</label>
                    <i class="fas fa-eye-slash toggle-password" data-target="#new_password"></i>
                </div>

                <button type="submit" class="btn btn-primary w-100">Save Changes</button>
            </form>
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

        // Password Visibility Toggle
        document.querySelectorAll('.toggle-password').forEach(btn => {
            btn.addEventListener('click', () => {
                const input = btn.parentElement.querySelector('input');
                input.type = input.type === 'password' ? 'text' : 'password';
                btn.querySelector('i').classList.toggle('fa-eye-slash');
            });
        });
    </script>
</body>
</html>