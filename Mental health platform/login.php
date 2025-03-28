<?php
include 'db_connect.php';
session_start();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'];
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['fullname'] = $user['fullname'];
        $_SESSION['role'] = $user['role'];
        
        // Redirect based on role with correct filename
        if ($user['role'] == 'professional') {
            header("Location: prof_dash.php");
        } elseif ($user['role'] == 'admin') {
            header("Location: admin_dash.php");
        } else {
            header("Location: dashboard.php");
        }
        exit(); // Make sure to exit after the redirect
    } else {
        echo "<script>alert('Invalid email or password!'); window.location.href='login.html';</script>";
    }
}
?>