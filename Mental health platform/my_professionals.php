<?php
// Error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    header("Location: login.html");
    exit();
}

include 'db_connect.php';

$user_id = $_SESSION['user_id'];
$message = "";
$error = "";

// Get all professionals for this client
$professionals_query = "SELECT pc.*, u.fullname, u.email, pp.specialty, pp.verified,
                        pc.created_at as connection_date
                       FROM professional_clients pc 
                       JOIN users u ON pc.professional_id = u.id 
                       LEFT JOIN professional_profiles pp ON u.id = pp.user_id
                       WHERE pc.client_id = ? 
                       ORDER BY pc.status, pc.created_at DESC";
$professionals_stmt = $conn->prepare($professionals_query);
$professionals_stmt->bind_param("i", $user_id);
$professionals_stmt->execute();
$professionals_result = $professionals_stmt->get_result();

// Handle disconnect request if client wants to remove connection
if (isset($_GET['action']) && $_GET['action'] == 'disconnect' && isset($_GET['professional_id'])) {
    $professional_id = intval($_GET['professional_id']);
    
    // Check if the connection exists
    $check_connection = $conn->prepare("SELECT id FROM professional_clients 
                                      WHERE professional_id = ? AND client_id = ?");
    $check_connection->bind_param("ii", $professional_id, $user_id);
    $check_connection->execute();
    $connection_result = $check_connection->get_result();
    
    if ($connection_result->num_rows > 0) {
        // Option 1: Set status to inactive (preserves history)
        $update_status = $conn->prepare("UPDATE professional_clients SET status = 'inactive' 
                                      WHERE professional_id = ? AND client_id = ?");
        $update_status->bind_param("ii", $professional_id, $user_id);
        
        /* 
        // Option 2: Remove connection entirely (use this instead of the above if you want complete removal)
        $update_status = $conn->prepare("DELETE FROM professional_clients 
                                      WHERE professional_id = ? AND client_id = ?");
        $update_status->bind_param("ii", $professional_id, $user_id);
        */
        
        if ($update_status->execute()) {
            $message = "Connection with professional has been updated to inactive.";
        } else {
            $error = "Error updating professional connection: " . $conn->error;
        }
    } else {
        $error = "Connection with this professional not found.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Mental Health Professionals - Mental Health Support</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        .professional-card {
            border: 1px solid #eee;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            background-color: white;
            position: relative;
        }
        .professional-card.active {
            border-left: 5px solid #2ecc71;
        }
        .professional-card.pending {
            border-left: 5px solid #f39c12;
        }
        .professional-card.inactive {
            border-left: 5px solid #95a5a6;
            background-color: #f9f9f9;
        }
        .professional-actions {
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
        .verification-badge {
            display: inline-block;
            background-color: #2ecc71;
            color: white;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            margin-left: 10px;
        }
        .unverified-badge {
            background-color: #e74c3c;
        }
    </style>
</head>
<body style="background-color: #f5f7fa; align-items: flex-start; padding-top: 30px;">
    <div class="dashboard-container">
        <div class="welcome-header">
            <div>
                <h1><i class="fas fa-user-md" style="color: #3498db;"></i> My Mental Health Professionals</h1>
                <p>View and manage your connections with mental health professionals</p>
            </div>
            <a href="dashboard.php" class="action-button secondary" style="text-decoration: none;">
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
            <h2><i class="fas fa-user-md"></i> Your Connected Professionals</h2>
            
            <?php if($professionals_result->num_rows > 0): ?>
                <p>You have <?php echo $professionals_result->num_rows; ?> professional connection(s).</p>
                
                <div style="margin-top: 20px;">
                    <?php while($professional = $professionals_result->fetch_assoc()): ?>
                        <div class="professional-card <?php echo $professional['status']; ?>">
                            <div class="professional-actions">
                                <?php if($professional['status'] == 'active'): ?>
                                <a href="manage_appointments.php" class="action-button" style="padding: 5px 10px; font-size: 0.9rem; text-decoration: none;">
                                    <i class="fas fa-calendar-plus"></i> Schedule Session
                                </a>
                                <?php endif; ?>
                                
                                <?php if($professional['status'] != 'inactive'): ?>
                                <a href="my_professionals.php?action=disconnect&professional_id=<?php echo $professional['professional_id']; ?>" 
                                   class="action-button secondary" 
                                   style="padding: 5px 10px; font-size: 0.9rem; text-decoration: none;"
                                   onclick="return confirm('Are you sure you want to disable this connection? This will prevent future appointments with this professional.');">
                                    <i class="fas fa-user-slash"></i> Disconnect
                                </a>
                                <?php endif; ?>
                            </div>
                            
                            <h3>
                                <?php echo htmlspecialchars($professional['fullname']); ?>
                                <span class="status-badge <?php echo $professional['status']; ?>">
                                    <?php echo ucfirst($professional['status']); ?>
                                </span>
                                
                                <?php if(isset($professional['verified'])): ?>
                                    <?php if($professional['verified']): ?>
                                        <span class="verification-badge"><i class="fas fa-check"></i> Verified</span>
                                    <?php else: ?>
                                        <span class="verification-badge unverified-badge"><i class="fas fa-exclamation-circle"></i> Unverified</span>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </h3>
                            
                            <p><strong>Email:</strong> <?php echo htmlspecialchars($professional['email']); ?></p>
                            
                            <?php if(!empty($professional['specialty'])): ?>
                                <p><strong>Specialty:</strong> <?php echo htmlspecialchars($professional['specialty']); ?></p>
                            <?php endif; ?>
                            
                            <p><strong>Connection Since:</strong> <?php echo date('M d, Y', strtotime($professional['connection_date'])); ?></p>
                            
                            <?php if($professional['status'] == 'active'): ?>
                                <div style="margin-top: 15px;">
                                    <a href="manage_appointments.php" class="action-button secondary" style="display: inline-block; text-decoration: none; margin-right: 10px;">
                                        <i class="fas fa-calendar-alt"></i> View Appointments
                                    </a>
                                    
                                    <a href="resources.php" class="action-button secondary" style="display: inline-block; text-decoration: none;">
                                        <i class="fas fa-book-medical"></i> View Resources
                                    </a>
                                </div>
                            <?php elseif($professional['status'] == 'pending'): ?>
                                <p style="margin-top: 15px; color: #f39c12;">
                                    <i class="fas fa-info-circle"></i> This professional has added you as a client, but your connection is still pending.
                                </p>
                            <?php elseif($professional['status'] == 'inactive'): ?>
                                <p style="margin-top: 15px; color: #95a5a6;">
                                    <i class="fas fa-info-circle"></i> Your connection with this professional is currently inactive.
                                </p>
                            <?php endif; ?>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div style="text-align: center; padding: 40px 0;">
                    <i class="fas fa-user-md" style="font-size: 48px; color: #bdc3c7; margin-bottom: 20px;"></i>
                    <h3>No Professional Connections</h3>
                    <p>You don't have any connections with mental health professionals yet.</p>
                    <p>A professional needs to add you as a client for you to see them here.</p>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="card">
            <h2><i class="fas fa-info-circle"></i> About Professional Connections</h2>
            <p>Connections with mental health professionals allow you to:</p>
            <ul style="margin-left: 20px; margin-top: 10px;">
                <li>Schedule and attend appointments</li>
                <li>Access personalized resources</li>
                <li>Receive professional guidance for your mental health journey</li>
            </ul>
            
            <div style="margin-top: 20px; padding: 15px; background-color: #f8f9fa; border-radius: 5px;">
                <p><strong>How connections work:</strong></p>
                <p>Mental health professionals must add you as their client using your email address. 
                Once added, they will appear in this list. You can schedule appointments with active professionals.</p>
            </div>
        </div>
        
        <footer style="text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; color: #7f8c8d;">
            <p>&copy; 2025 Mental Health Support. All rights reserved.</p>
        </footer>
    </div>
</body>
</html>