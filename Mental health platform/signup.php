<?php
// Maximum error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start a session
session_start();

// Include database connection
include 'db_connect.php';

// For debugging - log the request method
$log_file = fopen("signup_log.txt", "a");
fwrite($log_file, date("Y-m-d H:i:s") . " - Request method: " . $_SERVER["REQUEST_METHOD"] . "\n");

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Log received data
    fwrite($log_file, "POST data received: " . print_r($_POST, true) . "\n");
    
    // Get form data
    $fullname = $_POST['fullname'];
    $email = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role = isset($_POST['role']) ? $_POST['role'] : 'user'; // Default to 'user' if not specified
    
    // Log processed data
    fwrite($log_file, "Processed data - Name: $fullname, Email: $email, Role: $role\n");
    
    try {
        // Check if email already exists
        $check_sql = "SELECT * FROM users WHERE email = ?";
        $check_stmt = $conn->prepare($check_sql);
        
        if (!$check_stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        $check_stmt->bind_param("s", $email);
        $check_stmt->execute();
        $result = $check_stmt->get_result();
        
        fwrite($log_file, "Email check - Rows found: " . $result->num_rows . "\n");
        
        if ($result->num_rows > 0) {
            fwrite($log_file, "Email already exists\n");
            echo "<script>alert('Email already exists!'); window.location.href='index.html';</script>";
        } else {
            // Insert new user with role
            $insert_sql = "INSERT INTO users (fullname, email, password, role) VALUES (?, ?, ?, ?)";
            $insert_stmt = $conn->prepare($insert_sql);
            
            if (!$insert_stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            
            $insert_stmt->bind_param("ssss", $fullname, $email, $password, $role);
            
            fwrite($log_file, "Attempting to insert new user\n");
            
            if ($insert_stmt->execute()) {
                $user_id = $conn->insert_id;
                fwrite($log_file, "Insert successful. User ID: $user_id\n");
                
                // Create user profile
                if ($role == 'user') {
                    $profile_sql = "INSERT INTO user_profiles (user_id) VALUES (?)";
                    $profile_stmt = $conn->prepare($profile_sql);
                    $profile_stmt->bind_param("i", $user_id);
                    $profile_stmt->execute();
                } 
                // Create professional profile
                else if ($role == 'professional') {
                    $prof_sql = "INSERT INTO professional_profiles (user_id, specialty) VALUES (?, 'Not specified')";
                    $prof_stmt = $conn->prepare($prof_sql);
                    $prof_stmt->bind_param("i", $user_id);
                    $prof_stmt->execute();
                }
                
                // Set session variables
                $_SESSION['user_id'] = $user_id;
                $_SESSION['fullname'] = $fullname;
                $_SESSION['role'] = $role;
                
                fwrite($log_file, "Session set. Role is $role. Redirecting to appropriate dashboard\n");
                
                // Redirect based on role with correct filename
                if ($role == 'professional') {
                    header("Location: prof_dash.php");
                } else {
                    header("Location: dashboard.php");
                }
                exit();
            } else {
                throw new Exception("Execute failed: " . $insert_stmt->error);
            }
        }
    } catch (Exception $e) {
        fwrite($log_file, "Error: " . $e->getMessage() . "\n");
        echo "Error: " . $e->getMessage();
    }
} else {
    fwrite($log_file, "Not a POST request\n");
}

fclose($log_file);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Signup Processing</title>
</head>
<body>
    <p>If you see this page, the form submission had an issue. <a href="index.html">Return to signup page</a></p>
</body>
</html>