<?php
// Error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'professional') {
    header("Location: login.html");
    exit();
}

include 'db_connect.php';

$user_id = $_SESSION['user_id'];
$message = "";
$error = "";

// Create clients table if it doesn't exist yet
$create_table = "CREATE TABLE IF NOT EXISTS professional_clients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    professional_id INT NOT NULL,
    client_id INT NOT NULL,
    status ENUM('pending', 'active', 'inactive') NOT NULL DEFAULT 'pending',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (professional_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (client_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_relationship (professional_id, client_id)
)";
$conn->query($create_table);

// Handle add client action
if (isset($_POST['action']) && $_POST['action'] == 'add_client') {
    $client_email = $_POST['client_email'];
    
    // Check if user exists
    $check_user = $conn->prepare("SELECT id, fullname, role FROM users WHERE email = ?");
    $check_user->bind_param("s", $client_email);
    $check_user->execute();
    $user_result = $check_user->get_result();
    
    if ($user_result->num_rows > 0) {
        $client = $user_result->fetch_assoc();
        
        // Check if the user is a regular user (not a professional)
        if ($client['role'] == 'user') {
            try {
                // Check if relationship already exists
                $check_relation = $conn->prepare("SELECT * FROM professional_clients 
                                                WHERE professional_id = ? AND client_id = ?");
                $check_relation->bind_param("ii", $user_id, $client['id']);
                $check_relation->execute();
                $relation_result = $check_relation->get_result();
                
                if ($relation_result->num_rows == 0) {
                    // Add client
                    $add_client = $conn->prepare("INSERT INTO professional_clients 
                                                (professional_id, client_id, status) 
                                                VALUES (?, ?, 'active')");
                    $add_client->bind_param("ii", $user_id, $client['id']);
                    
                    if ($add_client->execute()) {
    $message = "Client {$client['fullname']} has been added successfully!";
    
    // Create chat connection if it doesn't exist
    $check_chat = $conn->prepare("SELECT id FROM chat_connections WHERE professional_id = ? AND client_id = ?");
    $check_chat->bind_param("ii", $user_id, $client['id']);
    $check_chat->execute();
    $chat_result = $check_chat->get_result();
    
    if ($chat_result->num_rows == 0) {
        // Create chat connection
        $create_chat = $conn->prepare("INSERT INTO chat_connections (professional_id, client_id) VALUES (?, ?)");
        $create_chat->bind_param("ii", $user_id, $client['id']);
        $create_chat->execute();
    }
} else {
    $error = "Error adding client: " . $conn->error;
}
                } else {
                    $error = "This user is already in your client list.";
                }
            } catch (Exception $e) {
                $error = "Error: " . $e->getMessage();
            }
        } else {
            $error = "This email belongs to a professional, not a client user.";
        }
    } else {
        $error = "No user found with this email.";
    }
}

// Handle remove client action
if (isset($_GET['action']) && $_GET['action'] == 'remove' && isset($_GET['client_id'])) {
    $client_id = intval($_GET['client_id']);
    
    $remove_client = $conn->prepare("DELETE FROM professional_clients 
                                   WHERE professional_id = ? AND client_id = ?");
    $remove_client->bind_param("ii", $user_id, $client_id);
    
    if ($remove_client->execute()) {
        $message = "Client has been removed successfully.";
    } else {
        $error = "Error removing client: " . $conn->error;
    }
}

// Handle update notes action
if (isset($_POST['action']) && $_POST['action'] == 'update_notes') {
    $client_id = intval($_POST['client_id']);
    $notes = $_POST['notes'];
    
    $update_notes = $conn->prepare("UPDATE professional_clients 
                                  SET notes = ? 
                                  WHERE professional_id = ? AND client_id = ?");
    $update_notes->bind_param("sii", $notes, $user_id, $client_id);
    
    if ($update_notes->execute()) {
        $message = "Client notes have been updated successfully.";
    } else {
        $error = "Error updating notes: " . $conn->error;
    }
}

// Handle update status action
if (isset($_GET['action']) && $_GET['action'] == 'status' && isset($_GET['client_id']) && isset($_GET['status'])) {
    $client_id = intval($_GET['client_id']);
    $status = $_GET['status'];
    
    if (in_array($status, ['pending', 'active', 'inactive'])) {
        $update_status = $conn->prepare("UPDATE professional_clients 
                                       SET status = ? 
                                       WHERE professional_id = ? AND client_id = ?");
        $update_status->bind_param("sii", $status, $user_id, $client_id);
        
        if ($update_status->execute()) {
            $message = "Client status has been updated successfully.";
        } else {
            $error = "Error updating status: " . $conn->error;
        }
    } else {
        $error = "Invalid status value.";
    }
}

// Get all clients for this professional
$clients_query = "SELECT pc.*, u.fullname, u.email, u.created_at as user_since 
                FROM professional_clients pc 
                JOIN users u ON pc.client_id = u.id 
                WHERE pc.professional_id = ? 
                ORDER BY pc.status, pc.created_at DESC";
$clients_stmt = $conn->prepare($clients_query);
$clients_stmt->bind_param("i", $user_id);
$clients_stmt->execute();
$clients_result = $clients_stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Client Management - Mental Health Support</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        .client-card {
            border: 1px solid #eee;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            background-color: white;
            position: relative;
        }
        .client-card.active {
            border-left: 5px solid #2ecc71;
        }
        .client-card.pending {
            border-left: 5px solid #f39c12;
        }
        .client-card.inactive {
            border-left: 5px solid #95a5a6;
            background-color: #f9f9f9;
        }
        .client-actions {
            position: absolute;
            top: 15px;
            right: 15px;
            display: flex;
            gap: 10px;
        }
        .status-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            color: white;
            margin-left: 10px;
        }
        .status-badge.active {
            background-color: #2ecc71;
        }
        .status-badge.pending {
            background-color: #f39c12;
        }
        .status-badge.inactive {
            background-color: #95a5a6;
        }
        .dropdown {
            position: relative;
            display: inline-block;
        }
        .dropdown-content {
            display: none;
            position: absolute;
            right: 0;
            background-color: white;
            min-width: 160px;
            box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.2);
            z-index: 1;
            border-radius: 5px;
        }
        .dropdown-content a {
            color: black;
            padding: 8px 12px;
            text-decoration: none;
            display: block;
            font-size: 14px;
        }
        .dropdown-content a:hover {
            background-color: #f1f1f1;
        }
        .dropdown:hover .dropdown-content {
            display: block;
        }
        .client-notes {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #eee;
        }
        .view-client {
            display: inline-block;
            margin-top: 10px;
            font-weight: 600;
            color: #3498db;
            text-decoration: none;
        }
        .view-client:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body style="background-color: #f5f7fa; align-items: flex-start; padding-top: 30px;">
    <div class="dashboard-container">
        <div class="welcome-header">
            <div>
                <h1><i class="fas fa-user-friends" style="color: #3498db;"></i> Client Management</h1>
                <p>Add and manage your clients to provide personalized support</p>
            </div>
            <a href="prof_dash.php" class="action-button secondary" style="text-decoration: none;">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>
        
        <?php if($message): ?>
        <div style="background-color: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
            <?php echo $message; ?>
        </div>
        <?php endif; ?>
        
        <?php if($error): ?>
        <div style="background-color: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
            <?php echo $error; ?>
        </div>
        <?php endif; ?>
        
<div class="card">
    <h2><i class="fas fa-user-plus"></i> Add New Client</h2>
    <p>Enter the email of the user you want to add as a client.</p>
    
    <form action="manage_clients.php" method="POST" style="margin-top: 20px; max-width: 500px;">
        <input type="hidden" name="action" value="add_client">
        <div style="display: flex; flex-direction: column; gap: 15px;">
            <div style="position: relative; width: 100%;">
                <i class="fas fa-envelope" style="position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: #888;"></i>
                <input type="email" name="client_email" placeholder="Enter client's email address" required 
                       style="width: 100%; padding: 12px 15px 12px 45px; border: 1px solid #ccc; border-radius: 10px; 
                              font-size: 16px; box-shadow: 0 2px 5px rgba(0,0,0,0.05);">
            </div>
            <div style="text-align: center;">
                <button type="submit" class="action-button" 
                        style="margin: 0; padding: 8px 20px; border-radius: 5px; font-size: 14px; 
                               width: auto; display: inline-block;">
                    <i class="fas fa-plus"></i> Add Client
                </button>
            </div>
        </div>
    </form>
</div>
        
        <div class="card">
            <h2><i class="fas fa-users"></i> Your Clients</h2>
            
            <?php if($clients_result->num_rows > 0): ?>
                <p>You have <?php echo $clients_result->num_rows; ?> client(s) in your management list.</p>
                
                <div style="margin-top: 20px;">
                    <?php while($client = $clients_result->fetch_assoc()): ?>
                        <div class="client-card <?php echo $client['status']; ?>">
                            <div class="client-actions">
                                <div class="dropdown">
                                    <button class="action-button secondary" style="padding: 5px 10px; font-size: 0.9rem;">
                                        <i class="fas fa-ellipsis-v"></i>
                                    </button>
                                    <div class="dropdown-content">
                                        <?php if($client['status'] != 'active'): ?>
                                            <a href="manage_clients.php?action=status&client_id=<?php echo $client['client_id']; ?>&status=active">
                                                <i class="fas fa-check"></i> Set as Active
                                            </a>
                                        <?php endif; ?>
                                        
                                        <?php if($client['status'] != 'pending'): ?>
                                            <a href="manage_clients.php?action=status&client_id=<?php echo $client['client_id']; ?>&status=pending">
                                                <i class="fas fa-clock"></i> Set as Pending
                                            </a>
                                        <?php endif; ?>
                                        
                                        <?php if($client['status'] != 'inactive'): ?>
                                            <a href="manage_clients.php?action=status&client_id=<?php echo $client['client_id']; ?>&status=inactive">
                                                <i class="fas fa-pause"></i> Set as Inactive
                                            </a>
                                        <?php endif; ?>
                                        
                                        <a href="#" onclick="document.getElementById('edit-notes-<?php echo $client['client_id']; ?>').style.display = 'block'; return false;">
                                            <i class="fas fa-sticky-note"></i> Edit Notes
                                        </a>
                                        
                                        <a href="manage_clients.php?action=remove&client_id=<?php echo $client['client_id']; ?>" onclick="return confirm('Are you sure you want to remove this client?');">
                                            <i class="fas fa-trash"></i> Remove Client
                                        </a>
                                    </div>
                                </div>
                            </div>
                            
                            <h3>
                                <?php echo htmlspecialchars($client['fullname']); ?>
                                <span class="status-badge <?php echo $client['status']; ?>">
                                    <?php echo ucfirst($client['status']); ?>
                                </span>
                            </h3>
                            
                            <p><strong>Email:</strong> <?php echo htmlspecialchars($client['email']); ?></p>
                            <p><strong>User Since:</strong> <?php echo date('M d, Y', strtotime($client['user_since'])); ?></p>
                            <p><strong>Added as Client:</strong> <?php echo date('M d, Y', strtotime($client['created_at'])); ?></p>
                            
                            <?php if($client['notes']): ?>
                                <div class="client-notes">
                                    <strong><i class="fas fa-sticky-note"></i> Notes:</strong>
                                    <p><?php echo nl2br(htmlspecialchars($client['notes'])); ?></p>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Hidden form for editing notes -->
                            <div id="edit-notes-<?php echo $client['client_id']; ?>" style="display: none; margin-top: 15px; border-top: 1px solid #eee; padding-top: 15px;">
                                <form action="manage_clients.php" method="POST">
                                    <input type="hidden" name="action" value="update_notes">
                                    <input type="hidden" name="client_id" value="<?php echo $client['client_id']; ?>">
                                    
                                    <div style="margin-bottom: 10px;">
                                        <label for="notes-<?php echo $client['client_id']; ?>" style="display: block; margin-bottom: 5px; font-weight: 600;">
                                            <i class="fas fa-sticky-note"></i> Client Notes:
                                        </label>
                                        <textarea id="notes-<?php echo $client['client_id']; ?>" name="notes" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; min-height: 100px;"><?php echo htmlspecialchars($client['notes']); ?></textarea>
                                    </div>
                                    
                                    <div style="display: flex; gap: 10px;">
                                        <button type="submit" class="action-button" style="padding: 5px 10px; font-size: 0.9rem;">
                                            <i class="fas fa-save"></i> Save Notes
                                        </button>
                                        <button type="button" class="action-button secondary" style="padding: 5px 10px; font-size: 0.9rem;" onclick="document.getElementById('edit-notes-<?php echo $client['client_id']; ?>').style.display = 'none';">
                                            <i class="fas fa-times"></i> Cancel
                                        </button>
                                    </div>
                                </form>
                            </div>
                            
                            <a href="#" class="view-client">
                                <i class="fas fa-chart-line"></i> View Client Progress
                            </a>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <p>You don't have any clients yet. Add your first client using the form above.</p>
            <?php endif; ?>
        </div>
        
        <footer style="text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; color: #7f8c8d;">
            <p>&copy; 2025 Mental Health Support. All rights reserved.</p>
        </footer>
    </div>
</body>
</html>