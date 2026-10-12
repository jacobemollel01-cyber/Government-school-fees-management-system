<?php
session_start();
$_SESSION = array();

// Destroy session cookies
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

session_destroy();

// Redirect back to login with success message
header("Location: login.php?success=" . urlencode("Logged out successfully."));
exit;
?>