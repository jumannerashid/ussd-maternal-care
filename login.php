<?php
// Enable error reporting for debugging (remove in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

// Redirect to dashboard if already logged in
if (isset($_SESSION['admin_id'])) {
    header("Location: https://maternal.technologygenius14.com/dashboard.php");
    exit;
}

// Database credentials
$host = '173.252.167.30';
$dbname = 'technol4_maternal_health';
$username = 'technol4_health_user';
$password = 'health_user';

// Database connection
$conn = new mysqli($host, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

// Initialize variables
$email = '';
$errors = [];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    // Validation
    if (empty($email)) {
        $errors[] = "Email is required.";
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    }
    if (empty($password)) {
        $errors[] = "Password is required.";
    }

    if (empty($errors)) {
        // Check credentials
        $stmt = $conn->prepare("SELECT id, full_name, password FROM administrators WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            $errors[] = "Invalid email or password.";
        } else {
            $admin = $result->fetch_assoc();
            if (!password_verify($password, $admin['password'])) {
                $errors[] = "Invalid email or password.";
            } else {
                // Authentication successful
                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['admin_name'] = $admin['full_name'];
                $_SESSION['last_login'] = time();
                header("Location: https://maternal.technologygenius14.com/dashboard.php");
                exit;
            }
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Maternal Care Connect</title>

    <!-- Bootstrap 5.3 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">

    <style>
        :root {
            --primary: #007bff;
            --danger: #dc3545;
            --light: #f8f9fa;
            --dark: #343a40;
        }

        body {
            background: linear-gradient(135deg, #343a40 0%, #212529 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .auth-card {
            max-width: 420px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            background: white;
            padding: 2rem;
            transition: transform 0.3s ease;
        }

        .auth-card:hover {
            transform: translateY(-5px);
        }

        .form-floating {
            margin-bottom: 1.5rem;
        }

        .login-btn {
            background: var(--primary);
            border: none;
            padding: 12px;
            border-radius: 8px;
            transition: background 0.3s ease;
        }

        .login-btn:hover {
            background: #0056b3;
            transform: scale(1.02);
        }

        .error-box {
            background: var(--danger);
            color: white;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1.5rem;
        }

        .footer-text {
            color: rgba(255, 255, 255, 0.7);
            position: fixed;
            bottom: 20px;
            left: 0;
            right: 0;
            text-align: center;
        }

        @media (max-width: 480px) {
            .auth-card {
                padding: 1.5rem;
            }
            .form-floating input {
                font-size: 14px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6 col-xl-5">
                <div class="auth-card">
                    <div class="text-center mb-4">
                        <i class="fas fa-heartbeat fa-3x text-primary mb-3"></i>
                        <h2 class="text-dark fw-bold">Maternal Care Connect</h2>
                        <p class="text-muted">Administrator Login</p>
                    </div>

                    <?php if (!empty($errors)): ?>
                        <div class="error-box">
                            <ul class="mb-0">
                                <?php foreach ($errors as $error): ?>
                                    <li><i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="">
                        <div class="form-floating">
                            <input type="email" class="form-control" id="email" 
                                   name="email" placeholder="name@example.com" value="<?= htmlspecialchars($email) ?>" required>
                            <label for="email">Email Address</label>
                        </div>

                        <div class="form-floating mb-3">
                            <input type="password" class="form-control" id="password" 
                                   name="password" placeholder="Password" required>
                            <label for="password">Password</label>
                        </div>

                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="rememberMe">
                                <label class="form-check-label text-muted" for="rememberMe">
                                    Remember me
                                </label>
                            </div>
                            <a href="#" class="text-primary">Forgot password?</a>
                        </div>

                        <button class="btn login-btn w-100 mb-3" type="submit">
                            <i class="fas fa-sign-in-alt me-2"></i> Sign In
                        </button>

                        <p class="text-center text-muted mb-0">
                            Don't have an account? 
                            <a href="create_admin.php" class="text-primary fw-bold">Sign up</a>
                        </p>
                    </form>
                </div>
                <footer class="footer-text">
                    <small>© 2025 Maternal Care Connect. All rights reserved.</small>
                </footer>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>