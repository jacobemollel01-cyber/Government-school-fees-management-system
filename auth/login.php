<?php
ini_set('session.cookie_lifetime', 0);
ini_set('session.use_strict_mode', 1);

session_start();
require_once "../config/db.php";

// 1. Ensure users table exists
$table_sql = "
CREATE TABLE IF NOT EXISTS users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('Admin', 'Bursar') NOT NULL DEFAULT 'Bursar',
    status ENUM('Active', 'Disabled') NOT NULL DEFAULT 'Active',
    last_login DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";
$conn->query($table_sql);

// Seed Default Admin Account if table is empty
$check_users = $conn->query("SELECT COUNT(*) AS total FROM users");
if ($check_users && $check_users->fetch_assoc()['total'] == 0) {
    $default_name = "System Administrator";
    $default_user = "admin";
    $default_email = "admin@school.com";
    $default_pass = password_hash("admin123password", PASSWORD_BCRYPT);
    $default_role = "Admin";

    $seed_stmt = $conn->prepare("INSERT INTO users (full_name, username, email, password_hash, role, status) VALUES (?, ?, ?, ?, ?, 'Active')");
    $seed_stmt->bind_param("sssss", $default_name, $default_user, $default_email, $default_pass, $default_role);
    $seed_stmt->execute();
    $seed_stmt->close();
}

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: ../dashboard.php");
    exit;
}

// 2. Authentication Logic
$error = $_GET['error'] ?? '';
$success = $_GET['success'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($username) || empty($password)) {
        $error = "Please provide both your username/email and password.";
    } else {
        $stmt = $conn->prepare("SELECT user_id, full_name, username, password_hash, role, status FROM users WHERE username = ? OR email = ?");
        $stmt->bind_param("ss", $username, $username);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($user = $res->fetch_assoc()) {
            if ($user['status'] !== 'Active') {
                $error = "This account is currently inactive. Please check with your administrator.";
            } elseif (password_verify($password, $user['password_hash'])) {
                session_regenerate_id(true);

                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['last_activity'] = time();

                $log_stmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE user_id = ?");
                $log_stmt->bind_param("i", $user['user_id']);
                $log_stmt->execute();
                $log_stmt->close();

                header("Location: ../dashboard.php");
                exit;
            } else {
                $error = "The credentials entered do not match our records.";
            }
        } else {
            $error = "The credentials entered do not match our records.";
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
    <title>Welcome Back - School Management System</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        }

        body {
            background-color: #f1f5f9;
            color: #0f172a;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .portal-card {
            width: 100%;
            max-width: 900px;
            background: #ffffff;
            border-radius: 20px;
            overflow: hidden;
            display: grid;
            grid-template-columns: 1fr 1fr;
            box-shadow: 0 20px 40px -15px rgba(15, 23, 42, 0.08), 0 0 1px 1px rgba(15, 23, 42, 0.04);
        }

        /* Left Side Panel */
        .brand-side {
            background: linear-gradient(145deg, #0f172a 0%, #1e293b 60%, #1e3a8a 100%);
            padding: 48px 40px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            color: #ffffff;
            position: relative;
        }

        .brand-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.12);
            padding: 6px 14px;
            border-radius: 30px;
            font-size: 0.8rem;
            letter-spacing: 0.5px;
            width: fit-content;
            color: #93c5fd;
        }

        .brand-message h1 {
            font-size: 1.85rem;
            font-weight: 700;
            line-height: 1.3;
            margin-bottom: 12px;
            color: #f8fafc;
        }

        .brand-message p {
            color: #94a3b8;
            font-size: 0.95rem;
            line-height: 1.6;
        }

        .brand-footer {
            font-size: 0.8rem;
            color: #64748b;
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .brand-footer .designer-tag {
            color: #93c5fd;
            font-weight: 500;
            letter-spacing: 0.3px;
        }

        /* Right Side Form */
        .form-side {
            padding: 48px 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .form-header {
            margin-bottom: 32px;
        }

        .form-header h2 {
            font-size: 1.5rem;
            font-weight: 700;
            color: #0f172a;
        }

        .form-header p {
            font-size: 0.9rem;
            color: #64748b;
            margin-top: 4px;
        }

        .alert-box {
            padding: 12px 16px;
            border-radius: 10px;
            font-size: 0.85rem;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 10px;
            line-height: 1.4;
        }

        .alert-error {
            background-color: #fef2f2;
            color: #991b1b;
            border: 1px solid #fee2e2;
        }

        .alert-success {
            background-color: #f0fdf4;
            color: #166534;
            border: 1px solid #dcfce7;
        }

        .input-group {
            margin-bottom: 20px;
        }

        .input-group label {
            display: block;
            font-size: 0.85rem;
            font-weight: 600;
            color: #334155;
            margin-bottom: 8px;
        }

        .input-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }

        .input-wrapper input {
            width: 100%;
            padding: 12px 42px 12px 16px;
            border: 1.5px solid #e2e8f0;
            border-radius: 10px;
            font-size: 0.95rem;
            color: #0f172a;
            background-color: #f8fafc;
            transition: all 0.2s ease;
            outline: none;
        }

        .input-wrapper input:focus {
            background-color: #ffffff;
            border-color: #2563eb;
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.1);
        }

        /* Eye Icon Styling */
        .toggle-password {
            position: absolute;
            right: 12px;
            background: none;
            border: none;
            cursor: pointer;
            color: #64748b;
            padding: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: color 0.2s ease;
        }

        .toggle-password:hover {
            color: #0f172a;
        }

        .toggle-password svg {
            width: 20px;
            height: 20px;
        }

        .btn-login {
            width: 100%;
            padding: 13px;
            background-color: #2563eb;
            color: #ffffff;
            border: none;
            border-radius: 10px;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            margin-top: 8px;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.25);
        }

        .btn-login:hover {
            background-color: #1d4ed8;
            box-shadow: 0 6px 16px rgba(37, 99, 235, 0.35);
            transform: translateY(-1px);
        }

        .btn-login:active {
            transform: translateY(0);
        }

        @media (max-width: 768px) {
            .portal-card {
                grid-template-columns: 1fr;
            }
            .brand-side {
                padding: 32px 24px;
                min-height: 200px;
            }
            .form-side {
                padding: 32px 24px;
            }
        }
    </style>
</head>
<body>

<div class="portal-card">

    <!-- Left Dark Blue Accent Panel with Architecture & Project Branding -->
    <div class="brand-side">
        <div class="brand-badge">
            <span>🏛️</span> School Management & Fees Portal
        </div>

        <div class="brand-message">
            <h1>Robust architecture for seamless school records.</h1>
            <p>Powered by a secure relational database architecture designed to streamline student accounts, financial logs, and administrative tracking.</p>
        </div>

        <div class="brand-footer">
            <span>Protected Portal &bull; Encrypted Access</span>
            <span class="designer-tag">Designed by Jacob</span>
        </div>
    </div>

    <!-- Right Login Form Side -->
    <div class="form-side">
        <div class="form-header">
            <h2>Welcome back</h2>
            <p>Please enter your account details</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert-box alert-error">
                <span>⚠️</span> <?= htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="alert-box alert-success">
                <span>✨</span> <?= htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="input-group">
                <label for="username">Username or Email</label>
                <div class="input-wrapper">
                    <input type="text" id="username" name="username" required autocomplete="username" placeholder="e.g. admin or bursar">
                </div>
            </div>

            <div class="input-group">
                <label for="password">Password</label>
                <div class="input-wrapper">
                    <input type="password" id="password" name="password" required autocomplete="current-password" placeholder="••••••••">
                    <button type="button" class="toggle-password" id="togglePasswordBtn" aria-label="Toggle password visibility">
                        <!-- Eye Icon -->
                        <svg id="eyeIcon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        </svg>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn-login">Sign In</button>
        </form>
    </div>

</div>

<script>
    const togglePasswordBtn = document.getElementById('togglePasswordBtn');
    const passwordInput = document.getElementById('password');
    const eyeIcon = document.getElementById('eyeIcon');

    togglePasswordBtn.addEventListener('click', function () {
        const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
        passwordInput.setAttribute('type', type);

        if (type === 'text') {
            // Eye Off SVG (slash through eye)
            eyeIcon.innerHTML = `
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858-5.908a10.01 10.01 0 013.122-.563c4.478 0 8.268 2.943 9.542 7a10.025 10.025 0 01-4.132 5.411m-4.692-4.692a3 3 0 00-4.243-4.243" />
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3l18 18" />
            `;
        } else {
            // Standard Eye SVG
            eyeIcon.innerHTML = `
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
            `;
        }
    });
</script>

</body>
</html>