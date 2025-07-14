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
try {
    $conn = new mysqli($host, $username, $password, $dbname);
    if ($conn->connect_error) {
        throw new Exception("Database connection failed: " . $conn->connect_error);
    }
} catch (Exception $e) {
    error_log("Database Error: " . $e->getMessage());
    die("Database connection failed. Please try again later.");
}

// Initialize variables
$full_name = '';
$email = '';
$errors = [];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // Validation
    if (empty($full_name)) {
        $errors[] = "Full name is required.";
    }
    if (empty($email)) {
        $errors[] = "Email is required.";
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    }
    if (empty($password)) {
        $errors[] = "Password is required.";
    }
    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match.";
    }

    // Check if email already exists
    if (empty($errors)) {
        $stmt = $conn->prepare("SELECT id FROM administrators WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $errors[] = "Email is already registered.";
        }
        $stmt->close();
    }

    // Insert new admin
    if (empty($errors)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO administrators (full_name, email, password) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $full_name, $email, $hashed_password);
        if ($stmt->execute()) {
            $_SESSION['success'] = "Admin account created successfully!";
            header("Location: login.php");
            exit;
        } else {
            $errors[] = "Failed to create account. Please try again.";
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
    <title>Create Admin - Maternal Care Connect</title>

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

        .register-btn {
            background: var(--primary);
            border: none;
            padding: 12px;
            border-radius: 8px;
            transition: background 0.3s ease;
        }

        .register-btn:hover {
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
                        <p class="text-muted">Create Administrator Account</p>
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
                            <input type="text" class="form-control" id="full_name" 
                                   name="full_name" placeholder="Full Name" value="<?= htmlspecialchars($full_name) ?>" required>
                            <label for="full_name">Full Name</label>
                        </div>

                        <div class="form-floating">
                            <input type="email" class="form-control" id="email" 
                                   name="email" placeholder="Email Address" value="<?= htmlspecialchars($email) ?>" required>
                            <label for="email">Email Address</label>
                        </div>

                        <div class="form-floating">
                            <input type="password" class="form-control" id="password" 
                                   name="password" placeholder="Password" required>
                            <label for="password">Password</label>
                        </div>

                        <div class="form-floating mb-3">
                            <input type="password" class="form-control" id="confirm_password" 
                                   name="confirm_password" placeholder="Confirm Password" required>
                            <label for="confirm_password">Confirm Password</label>
                        </div>

                        <button class="btn register-btn w-100 mb-3" type="submit">
                            <i class="fas fa-user-plus me-2"></i> Create Account
                        </button>

                        <p class="text-center text-muted mb-0">
                            Already have an account? 
                            <a href="login.php" class="text-primary fw-bold">Sign in</a>
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