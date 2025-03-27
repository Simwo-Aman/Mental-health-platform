<?php
// Error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.html");
    exit();
}

// Include database connection
include 'db_connect.php';

// Get admin user information
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ? AND role = 'admin'");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

// Verify this is truly an admin
if ($result->num_rows === 0) {
    // Clear the session and redirect
    session_destroy();
    header("Location: login.html");
    exit();
}

$admin = $result->fetch_assoc();
?>