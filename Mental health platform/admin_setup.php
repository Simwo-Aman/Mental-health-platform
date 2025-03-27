<?php
// Error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include database connection
include 'db_connect.php';

// Start HTML output
echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Setup - Mental Health Support</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        .setup-container {
            max-width: 800px;
            margin: 30px auto;
            background: white;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            padding: 30px;
        }
        .step {
            margin-bottom: 25px;
            padding-bottom: 25px;
            border-bottom: 1px solid #eee;
        }
        .step:last-child {
            border-bottom: none;
        }
        .step-header {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
        }
        .step-number {
            background-color: #3498db;
            color: white;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            margin-right: 15px;
            font-weight: bold;
        }
        .step-title {
            font-size: 1.2rem;
            font-weight: 600;
        }
        .success {
            color: #2ecc71;
            font-weight: 600;
        }
        .error {
            color: #e74c3c;
            font-weight: 600;
        }
    </style>
</head>
<body style="background-color: #f5f7fa;">
    <div class="setup-container">
        <h1><i class="fas fa-user-shield" style="color: #e74c3c;"></i> Admin Setup</h1>
        <p>This script will set up administrator functionality for your Mental Health Support platform.</p>';

// Step 1: Update users table to support the 'admin' role
echo '<div class="step">
        <div class="step-header">
            <div class="step-number">1</div>
            <div class="step-title">Updating users table</div>
        </div>';

// Check if 'role' column exists and can store 'admin'
$check_role_column = $conn->query("SHOW COLUMNS FROM users LIKE 'role'");

if ($check_role_column->num_rows > 0) {
    $role_column = $check_role_column->fetch_assoc();
    
    // Check if 'role' column type includes 'admin'
    if (strpos($role_column['Type'], 'admin') !== false) {
        echo '<p class="success"><i class="fas fa-check-circle"></i> Users table already supports admin role.</p>';
    } else {
        // Alter column to add 'admin' role
        $alter_role = $conn->query("ALTER TABLE users MODIFY COLUMN role ENUM('user', 'professional', 'admin') NOT NULL DEFAULT 'user'");
        
        if ($alter_role) {
            echo '<p class="success"><i class="fas fa-check-circle"></i> Successfully updated users table to support admin role.</p>';
        } else {
            echo '<p class="error"><i class="fas fa-times-circle"></i> Error updating users table: ' . $conn->error . '</p>';
        }
    }
} else {
    echo '<p class="error"><i class="fas fa-times-circle"></i> Role column not found in users table.</p>';
}

// Step 2: Create admin user
echo '</div>
      <div class="step">
        <div class="step-header">
            <div class="step-number">2</div>
            <div class="step-title">Creating admin user</div>
        </div>';

// Check if admin user exists
$check_admin = $conn->query("SELECT * FROM users WHERE role = 'admin'");

if ($check_admin->num_rows > 0) {
    $admin = $check_admin->fetch_assoc();
    echo '<p class="success"><i class="fas fa-check-circle"></i> Admin user already exists: <strong>' . htmlspecialchars($admin['email']) . '</strong></p>';
} else {
    // Process form submission to create admin
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'create_admin') {
        $admin_name = $_POST['admin_name'];
        $admin_email = $_POST['admin_email'];
        $admin_password = password_hash($_POST['admin_password'], PASSWORD_DEFAULT);
        
        // Create admin user
        $create_admin = $conn->prepare("INSERT INTO users (fullname, email, password, role) VALUES (?, ?, ?, 'admin')");
        $create_admin->bind_param("sss", $admin_name, $admin_email, $admin_password);
        
        if ($create_admin->execute()) {
            echo '<p class="success"><i class="fas fa-check-circle"></i> Admin user created successfully! You can now login with these credentials.</p>';
        } else {
            echo '<p class="error"><i class="fas fa-times-circle"></i> Error creating admin user: ' . $conn->error . '</p>';
        }
    } else {
        // Display form to create admin user
        echo '<p>No admin user exists. Create one using the form below:</p>
        <form method="POST" style="margin-top: 15px;">
            <input type="hidden" name="action" value="create_admin">
            <div style="margin-bottom: 15px;">
                <label for="admin_name" style="display: block; margin-bottom: 5px; font-weight: 600;">Admin Name:</label>
                <input type="text" id="admin_name" name="admin_name" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
            </div>
            <div style="margin-bottom: 15px;">
                <label for="admin_email" style="display: block; margin-bottom: 5px; font-weight: 600;">Admin Email:</label>
                <input type="email" id="admin_email" name="admin_email" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
            </div>
            <div style="margin-bottom: 15px;">
                <label for="admin_password" style="display: block; margin-bottom: 5px; font-weight: 600;">Admin Password:</label>
                <input type="password" id="admin_password" name="admin_password" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
            </div>
            <button type="submit" class="action-button" style="width: auto;">Create Admin User</button>
        </form>';
    }
}

// Step 3: Update login.php to handle admin redirection
echo '</div>
      <div class="step">
        <div class="step-header">
            <div class="step-number">3</div>
            <div class="step-title">Updating login system</div>
        </div>';

// Check if login.php file exists
if (file_exists('login.php')) {
    // Read the file content
    $login_content = file_get_contents('login.php');
    
    // Check if the file already contains admin redirection
    if (strpos($login_content, 'admin_dash.php') !== false) {
        echo '<p class="success"><i class="fas fa-check-circle"></i> Login system already supports admin redirection.</p>';
    } else {
        // Create a backup of the original file
        file_put_contents('login.php.bak', $login_content);
        
        // Update the file to include admin redirection
        $updated_content = str_replace(
            "if (\$user['role'] == 'professional') {
            header(\"Location: prof_dash.php\");
        } else {
            header(\"Location: dashboard.php\");
        }",
            "if (\$user['role'] == 'professional') {
            header(\"Location: prof_dash.php\");
        } elseif (\$user['role'] == 'admin') {
            header(\"Location: admin_dash.php\");
        } else {
            header(\"Location: dashboard.php\");
        }",
            $login_content
        );
        
        // Write the updated content back to the file
        if (file_put_contents('login.php', $updated_content)) {
            echo '<p class="success"><i class="fas fa-check-circle"></i> Login system updated to support admin redirection. Original file backed up as login.php.bak</p>';
        } else {
            echo '<p class="error"><i class="fas fa-times-circle"></i> Error updating login.php file. Please add admin redirection code manually.</p>';
            echo '<p>Update the following code in login.php:</p>';
            echo '<pre style="background: #f8f9fa; padding: 10px; border-radius: 5px; overflow-x: auto;">
if ($user[\'role\'] == \'professional\') {
    header("Location: prof_dash.php");
} <strong>elseif ($user[\'role\'] == \'admin\') {
    header("Location: admin_dash.php");
}</strong> else {
    header("Location: dashboard.php");
}
</pre>';
        }
    }
} else {
    echo '<p class="error"><i class="fas fa-times-circle"></i> Login.php file not found. Please add admin redirection code manually.</p>';
}

// Step 4: Create admin_auth.php file
echo '</div>
      <div class="step">
        <div class="step-header">
            <div class="step-number">4</div>
            <div class="step-title">Creating admin authentication file</div>
        </div>';

$admin_auth_content = '<?php
// Error reporting for debugging
error_reporting(E_ALL);
ini_set(\'display_errors\', 1);

session_start();
if (!isset($_SESSION[\'user_id\']) || $_SESSION[\'role\'] !== \'admin\') {
    header("Location: login.html");
    exit();
}

// Include database connection
include \'db_connect.php\';

// Get admin user information
$user_id = $_SESSION[\'user_id\'];
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ? AND role = \'admin\'");
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
?>';

// Write admin_auth.php file
if (file_exists('admin_auth.php')) {
    echo '<p class="success"><i class="fas fa-check-circle"></i> Admin authentication file already exists.</p>';
} else {
    if (file_put_contents('admin_auth.php', $admin_auth_content)) {
        echo '<p class="success"><i class="fas fa-check-circle"></i> Admin authentication file created successfully.</p>';
    } else {
        echo '<p class="error"><i class="fas fa-times-circle"></i> Error creating admin authentication file.</p>';
    }
}

// Step 5: Create admin dashboard file
echo '</div>
      <div class="step">
        <div class="step-header">
            <div class="step-number">5</div>
            <div class="step-title">Creating admin dashboard file</div>
        </div>';

if (file_exists('admin_dash.php')) {
    echo '<p class="success"><i class="fas fa-check-circle"></i> Admin dashboard file already exists.</p>';
} else {
    // We'll check if we were successful in creating admin_dash.php via other methods
    echo '<p>Creating admin dashboard file... admin_dash.php will be created through the admin interface setup.</p>';
}

// Final instructions
echo '</div>
      <div class="step">
        <div class="step-header">
            <div class="step-number"><i class="fas fa-flag-checkered"></i></div>
            <div class="step-title">Setup Complete</div>
        </div>
        
        <p class="success"><i class="fas fa-check-circle"></i> Administrator setup is complete!</p>
        
        <p>You can now access the administrator dashboard by logging in with your admin credentials.</p>
        
        <div style="margin-top: 20px; text-align: center;">
            <a href="login.html" class="action-button" style="text-decoration: none;">
                <i class="fas fa-sign-in-alt"></i> Go to Login
            </a>
        </div>
    </div>
    
    <div style="text-align: center; margin: 20px auto; max-width: 800px; color: #7f8c8d;">
        <p>&copy; 2025 Mental Health Support. All rights reserved.</p>
    </div>
</body>
</html>';
?>
