<?php
// Error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include database connection
include 'db_connect.php';

// Create messages table
$create_messages_table = "
CREATE TABLE IF NOT EXISTS chat_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    message TEXT NOT NULL,
    is_read BOOLEAN DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX (sender_id, receiver_id),
    INDEX (created_at)
)";

// Create chat connections table
$create_connections_table = "
CREATE TABLE IF NOT EXISTS chat_connections (
    id INT AUTO_INCREMENT PRIMARY KEY,
    professional_id INT NOT NULL,
    client_id INT NOT NULL,
    last_message_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (professional_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (client_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_connection (professional_id, client_id)
)";

// Execute queries and display results
echo "<html>
<head>
    <title>Setup Chat Tables</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; margin: 20px; }
        .container { max-width: 800px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 5px; }
        h1 { color: #3498db; }
        .success { color: #2ecc71; padding: 10px; background-color: #d4edda; border-radius: 5px; margin: 10px 0; }
        .error { color: #e74c3c; padding: 10px; background-color: #f8d7da; border-radius: 5px; margin: 10px 0; }
        .btn { display: inline-block; padding: 10px 15px; background-color: #3498db; color: white; text-decoration: none; border-radius: 5px; margin-top: 15px; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>Setting Up Chat Tables</h1>";

// Create messages table
if ($conn->query($create_messages_table) === TRUE) {
    echo "<div class='success'>Chat messages table created successfully or already exists.</div>";
} else {
    echo "<div class='error'>Error creating chat messages table: " . $conn->error . "</div>";
}

// Create connections table
if ($conn->query($create_connections_table) === TRUE) {
    echo "<div class='success'>Chat connections table created successfully or already exists.</div>";
} else {
    echo "<div class='error'>Error creating chat connections table: " . $conn->error . "</div>";
}

// Populate chat_connections from professional_clients table
$populate_connections = "
INSERT IGNORE INTO chat_connections (professional_id, client_id)
SELECT professional_id, client_id 
FROM professional_clients 
WHERE status = 'active'";

if ($conn->query($populate_connections) === TRUE) {
    $affected_rows = $conn->affected_rows;
    echo "<div class='success'>Chat connections populated from existing professional-client relationships. ($affected_rows connections added)</div>";
} else {
    echo "<div class='error'>Error populating chat connections: " . $conn->error . "</div>";
}

echo "<a href='dashboard.php' class='btn'>Return to Dashboard</a>
    </div>
</body>
</html>";
?>