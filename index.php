<?php
session_start();

// If user is already logged in, send them straight to the admin dashboard
if (isset($_SESSION['user_id'])) {
    header("Location: admin/admin dashboard.php");
    exit;
}

// Otherwise, send them to the login screen
header("Location: auth/login.php");
exit;
?>