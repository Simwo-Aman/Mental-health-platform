<?php
// Error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include database connection
include 'db_connect.php';

echo '<html><head><title>Connection Status Update</title>';
echo '<style>
    body { font-family: Arial, sans-serif; line-height: 1.6; margin: 20px; }
    .container { max-width: 800px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 5px; }
    h1 { color: #3498db; }
    .success { color: #2ecc71; padding: 10px; background-color: #d4edda; border-radius: 5px; margin: 10px 0; }
    .error { color: #e74c3c; padding: 10px; background-color: #f8d7da; border-radius: 5px; margin: 10px 0; }
    .btn { display: inline-block; padding: 10px 15px; background-color: #3498db; color: white; text-decoration: none; border-radius: 5px; margin-top: 15px; }
</style>';
echo '</head><body><div class="container">';
echo '<h1>Connection Status Update</h1>';

// 1. Activate all pending connections
$activate_connections = $conn->query("UPDATE professional_clients SET status = 'active' WHERE status = 'pending'");

if ($activate_connections) {
    $affected_rows = $conn->affected_rows;
    echo "<div class='success'><strong>Success!</strong> All pending professional-client connections have been activated. ($affected_rows connections updated)</div>";
} else {
    echo "<div class='error'><strong>Error:</strong> Unable to update connections: " . $conn->error . "</div>";
}

// 2. Verify all professionals
$verify_professionals = $conn->query("UPDATE professional_profiles SET verified = 1 WHERE verified = 0 OR verified IS NULL");

if ($verify_professionals) {
    $affected_rows_prof = $conn->affected_rows;
    echo "<div class='success'><strong>Success!</strong> All professionals have been verified. ($affected_rows_prof profiles updated)</div>";
} else {
    echo "<div class='error'><strong>Error:</strong> Unable to verify professionals: " . $conn->error . "</div>";
}

// 3. Update the add_client function in manage_clients.php to set status to 'active' by default
echo "<div class='success'>
<strong>Important:</strong> For future client connections, you should modify the manage_clients.php file. <br>
Find this line:<br>
<code>VALUES (?, ?, 'pending')</code><br><br>
Change it to:<br>
<code>VALUES (?, ?, 'active')</code><br><br>
This will make all future connections active by default.
</div>";

echo '<a href="dashboard.php" class="btn">Return to Dashboard</a>';
echo '</div></body></html>';
?>