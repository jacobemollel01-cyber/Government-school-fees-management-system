<?php
require_once "config/db.php";

$username = "bursar";
$email = "bursar@school.com";
$password = "bursar123password";
$full_name = "School Bursar";
$role = "Bursar";

// Generate proper BCRYPT hash
$password_hash = password_hash($password, PASSWORD_BCRYPT);

// Check if username already exists
$check = $conn->prepare("SELECT user_id FROM users WHERE username = ?");
$check->bind_param("s", $username);
$check->execute();
$res = $check->get_result();

if ($res->num_rows > 0) {
    // Update existing bursar password
    $stmt = $conn->prepare("UPDATE users SET password_hash = ?, status = 'Active' WHERE username = ?");
    $stmt->bind_param("ss", $password_hash, $username);
    $stmt->execute();
    echo "Successfully updated password for <strong>bursar</strong>!";
} else {
    // Insert new bursar account
    $stmt = $conn->prepare("INSERT INTO users (full_name, username, email, password_hash, role, status) VALUES (?, ?, ?, ?, ?, 'Active')");
    $stmt->bind_param("sssss", $full_name, $username, $email, $password_hash, $role);
    $stmt->execute();
    echo "Successfully created account for <strong>bursar</strong>!";
}
?>