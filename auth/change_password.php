<?php
require_once "../auth/auth_check.php";
require_once "../config/db.php";
require_once "../includes/header.php";

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_password = $_POST['current_password'] ?? '';
    $new_password     = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $user_id          = $_SESSION['user_id'];

    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error = "All fields are required.";
    } elseif ($new_password !== $confirm_password) {
        $error = "New password and confirmation do not match.";
    } elseif (strlen($new_password) < 6) {
        $error = "New password must be at least 6 characters long.";
    } else {
        // Fetch current password hash from database
        $stmt = $conn->prepare("SELECT password_hash FROM users WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($user = $res->fetch_assoc()) {
            if (password_verify($current_password, $user['password_hash'])) {
                // Hash new password and update
                $new_hash = password_hash($new_password, PASSWORD_BCRYPT);
                $update_stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?");
                $update_stmt->bind_param("si", $new_hash, $user_id);

                if ($update_stmt->execute()) {
                    $success = "Password updated successfully!";
                } else {
                    $error = "Failed to update password. Please try again.";
                }
                $update_stmt->close();
            } else {
                $error = "Incorrect current password.";
            }
        }
        $stmt->close();
    }
}
?>

<div style="max-width: 450px; margin: 40px auto; padding: 25px; background: #ffffff; border-radius: 8px; border: 1px solid #cbd5e1; box-shadow: 0 4px 6px rgba(0,0,0,0.05);">
    <h2 style="margin-top: 0; color: #0f172a; font-size: 1.4rem;">Change Password</h2>

    <?php if (!empty($error)): ?>
        <div style="background-color: #fef2f2; color: #dc2626; border: 1px solid #fecaca; padding: 10px; border-radius: 6px; font-size: 0.9rem; margin-bottom: 15px;">
            <?= htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($success)): ?>
        <div style="background-color: #ecfdf5; color: #047857; border: 1px solid #a7f3d0; padding: 10px; border-radius: 6px; font-size: 0.9rem; margin-bottom: 15px;">
            <?= htmlspecialchars($success); ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="">
        <div style="margin-bottom: 15px;">
            <label style="display: block; margin-bottom: 5px; font-weight: 600; font-size: 0.88rem; color: #334155;">Current Password</label>
            <input type="password" name="current_password" required style="width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px; box-sizing: border-box;">
        </div>

        <div style="margin-bottom: 15px;">
            <label style="display: block; margin-bottom: 5px; font-weight: 600; font-size: 0.88rem; color: #334155;">New Password</label>
            <input type="password" name="new_password" required style="width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px; box-sizing: border-box;">
        </div>

        <div style="margin-bottom: 20px;">
            <label style="display: block; margin-bottom: 5px; font-weight: 600; font-size: 0.88rem; color: #334155;">Confirm New Password</label>
            <input type="password" name="confirm_password" required style="width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px; box-sizing: border-box;">
        </div>

        <button type="submit" style="width: 100%; padding: 11px; background-color: #2563eb; color: #ffffff; border: none; border-radius: 6px; font-weight: 600; cursor: pointer;">
            Update Password
        </button>
    </form>
</div>