<?php
// Maximum error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include database connection
include 'db_connect.php';

// Start the session if needed to access user details
session_start();

// Simple CSS styles
echo '
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Check</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            margin: 20px;
        }
        h1, h2 {
            color: #333;
        }
        table {
            border-collapse: collapse;
            width: 100%;
            margin-bottom: 20px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
        }
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .success {
            color: green;
            font-weight: bold;
        }
        .error {
            color: red;
            font-weight: bold;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
        }
        .back-link {
            display: inline-block;
            margin-top: 20px;
            padding: 10px 15px;
            background-color: #4CAF50;
            color: white;
            text-decoration: none;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Database Status Check</h1>
';

// Check database connection
if ($conn->connect_error) {
    echo '<p class="error">Database connection failed: ' . $conn->connect_error . '</p>';
    exit();
} else {
    echo '<p class="success">Database connection successful.</p>';
    echo '<p>Database name: <strong>' . $dbname . '</strong></p>';
}

// Check if the users table exists
$table_check = $conn->query("SHOW TABLES LIKE 'users'");
if ($table_check->num_rows > 0) {
    echo '<p class="success">Users table exists.</p>';
    
    // Check table structure
    echo '<h2>Users Table Structure:</h2>';
    $columns = $conn->query("DESCRIBE users");
    
    if ($columns->num_rows > 0) {
        echo '<table>';
        echo '<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>';
        
        while ($column = $columns->fetch_assoc()) {
            echo '<tr>';
            echo '<td>' . $column['Field'] . '</td>';
            echo '<td>' . $column['Type'] . '</td>';
            echo '<td>' . $column['Null'] . '</td>';
            echo '<td>' . $column['Key'] . '</td>';
            echo '<td>' . $column['Default'] . '</td>';
            echo '<td>' . $column['Extra'] . '</td>';
            echo '</tr>';
        }
        
        echo '</table>';
    }
    
    // Get user count
    $user_count = $conn->query("SELECT COUNT(*) as count FROM users");
    $count = $user_count->fetch_assoc();
    echo '<p>Total users in database: <strong>' . $count['count'] . '</strong></p>';
    
    // Show all users (limit to 10 for safety)
    $users = $conn->query("SELECT id, fullname, email, created_at FROM users ORDER BY id DESC LIMIT 10");
    
    if ($users->num_rows > 0) {
        echo '<h2>Recent Users (Most Recent First):</h2>';
        echo '<table>';
        echo '<tr><th>ID</th><th>Name</th><th>Email</th><th>Created At</th></tr>';
        
        while ($user = $users->fetch_assoc()) {
            echo '<tr>';
            echo '<td>' . $user['id'] . '</td>';
            echo '<td>' . htmlspecialchars($user['fullname']) . '</td>';
            echo '<td>' . htmlspecialchars($user['email']) . '</td>';
            echo '<td>' . $user['created_at'] . '</td>';
            echo '</tr>';
        }
        
        echo '</table>';
    } else {
        echo '<p>No users found in the database.</p>';
    }
} else {
    echo '<p class="error">Users table does not exist! Here\'s the SQL to create it:</p>';
    echo '<pre>
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fullname VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
    </pre>';
}

echo '<a class="back-link" href="index.html">Back to Main Page</a>';
echo '</div></body></html>';
?>