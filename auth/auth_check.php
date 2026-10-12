<?php
ini_set('session.cookie_lifetime', 0);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Redirect to login if unauthenticated
if (!isset($_SESSION['user_id'])) {
    header("Location: /school-fees/auth/login.php?error=" . urlencode("Please log in to access this page."));
    exit;
}

// 2. Session Inactivity Timeout (15 minutes)
$timeout_duration = 900; 

if (isset($_SESSION['last_activity'])) {
    $elapsed_time = time() - $_SESSION['last_activity'];
    if ($elapsed_time > $timeout_duration) {
        session_unset();
        session_destroy();
        header("Location: /school-fees/auth/login.php?error=" . urlencode("Session expired due to inactivity. Please log in again."));
        exit;
    }
}
$_SESSION['last_activity'] = time();

// 3. Helper Function to Restrict Pages by Role
function checkRole($allowed_roles = []) {
    $user_role = $_SESSION['role'] ?? '';
    
    if (!in_array($user_role, $allowed_roles)) {
        // If a Bursar tries to access an Admin page, send them back to the main dashboard
        header("Location: /school-fees/dashboard.php?error=" . urlencode("Access denied. Admin privileges required."));
        exit;
    }
}
?>