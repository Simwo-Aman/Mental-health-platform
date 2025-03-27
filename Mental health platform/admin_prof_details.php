<?php
// Include the admin authentication file
include 'admin_auth.php';

$message = "";
$error = "";

// Get professional ID from URL
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: admin_verification.php");
    exit();
}

$professional_id = intval($_GET['id']);

// Handle actions
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'])) {
    if ($_POST['action'] == 'verify') {
        // Verify the professional
        $stmt = $conn->prepare("UPDATE professional_profiles SET verified = 1 WHERE user_id = ?");
        $stmt->bind_param("i", $professional_id);
        
        if ($stmt->execute()) {
            $message = "Professional successfully verified!";
        } else {
            $error = "Error verifying professional: " . $conn->error;
        }
    } elseif ($_POST['action'] == 'unverify') {
        // Unverify the professional
        $stmt = $conn->prepare("UPDATE professional_profiles SET verified = 0 WHERE user_id = ?");
        $stmt->bind_param("i", $professional_id);
        
        if ($stmt->execute()) {
            $message = "Professional verification status has been revoked.";
        } else {
            $error = "Error updating verification status: " . $conn->error;
        }
    } elseif ($_POST['action'] == 'delete') {
        // Delete the professional account
        // Start a transaction since we need to delete from multiple tables
        $conn->begin_transaction();
        
        try {
            // Delete from professional_profiles
            $stmt1 = $conn->prepare("DELETE FROM professional_profiles WHERE user_id = ?");
            $stmt1->bind_param("i", $professional_id);
            $stmt1->execute();
            
            // Delete from users table
            $stmt2 = $conn->prepare("DELETE FROM users WHERE id = ?");
            $stmt2->bind_param("i", $professional_id);
            $stmt2->execute();
            
            // Commit the transaction
            $conn->commit();
            
            // Redirect back to verification page
            header("Location: admin_verification.php?message=Professional account has been deleted.");
            exit();
        } catch (Exception $e) {
            // Rollback in case of error
            $conn->rollback();
            $error = "Error deleting professional account: " . $e->getMessage();
        }
    }
}

// Get professional details
$stmt = $conn->prepare("SELECT u.*, pp.* 
                       FROM users u 
                       JOIN professional_profiles pp ON u.id = pp.user_id 
                       WHERE u.id = ? AND u.role = 'professional'");
$stmt->bind_param("i", $professional_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: admin_verification.php?error=Professional not found.");
    exit();
}

$professional = $result->fetch_assoc();

// Get client count for this professional
$client_count_query = $conn->prepare("SELECT COUNT(*) as count FROM professional_clients WHERE professional_id = ?");
$client_count_query->bind_param("i", $professional_id);
$client_count_query->execute();
$client_count = $client_count_query->get_result()->fetch_assoc()['count'];

// Get resource count for this professional
$resource_count_query = $conn->prepare("SELECT COUNT(*) as count FROM mental_health_resources WHERE professional_id = ?");
$resource_count_query->bind_param("i", $professional_id);
$resource_count_query->execute();
$resource_count = $resource_count_query->get_result()->fetch_assoc()['count'];

// Get appointment count for this professional
$appointment_count_query = $conn->prepare("SELECT COUNT(*) as count FROM appointments WHERE professional_id = ?");
$appointment_count_query->bind_param("i", $professional_id);
$appointment_count_query->execute();
$appointment_count = $appointment_count_query->get_result()->fetch_assoc()['count'];

// Get recent clients for this professional
$clients_query = $conn->prepare("
    SELECT pc.*, u.fullname, u.email 
    FROM professional_clients pc
    JOIN users u ON pc.client_id = u.id
    WHERE pc.professional_id = ?
    ORDER BY pc.created_at DESC
    LIMIT 5
");
$clients_query->bind_param("i", $professional_id);
$clients_query->execute();
$clients_result = $clients_query->get_result();

// Get recent resources by this professional
$resources_query = $conn->prepare("
    SELECT * FROM mental_health_resources
    WHERE professional_id = ?
    ORDER BY created_at DESC
    LIMIT 5
");
$resources_query->bind_param("i", $professional_id);
$resources_query->execute();
$resources_result = $resources_query->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Professional Details - Admin Panel</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        .profile-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 20px;
        }
        .profile-actions {
            display: flex;
            gap: 10px;
        }
        .profile-section {
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
        }
        .badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 0.8rem;
            color: white;
            margin-left: 8px;
        }
        .badge-verified {
            background-color: #2ecc71;
        }
        .badge-pending {
            background-color: #f39c12;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }
        .stat-box {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            text-align: center;
        }
        .stat-value {
            font-size: 1.8rem;
            font-weight: 700;
            color: #3498db;
            margin-bottom: 5px;
        }
        .detail-row {
            display: flex;
            margin-bottom: 10px;
        }
        .detail-label {
            width: 150px;
            font-weight: 600;
            color: #555;
        }
        .detail-value {
            flex: 1;
        }
        .client-item, .resource-item {
            padding: 12px;
            border: 1px solid #eee;
            border-radius: 5px;
            margin-bottom: 10px;
            background-color: white;
        }
        .resource-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .delete-btn {
            background-color: #e74c3c;
            color: white;
        }
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            justify-content: center;
            align-items: center;
        }
        .modal-content {
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            max-width: 500px;
            width: 100%;
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        .close-modal {
            font-size: 1.5rem;
            cursor: pointer;
        }
    </style>
</head>
<body style="background-color: #f5f7fa; align-items: flex-start; padding-top: 30px;">
    <div class="dashboard-container">
        <div class="welcome-header">
            <div>
                <h1><i class="fas fa-user-md" style="color: #3498db;"></i> Professional Details</h1>
                <p>View detailed information about this professional</p>
            </div>
            <a href="admin_dash.php" class="action-button secondary" style="text-decoration: none;">
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
            <div class="profile-header">
                <h2>
                    <?php echo htmlspecialchars($professional['fullname']); ?>
                    <?php if($professional['verified'] == 1): ?>
                        <span class="badge badge-verified">Verified</span>
                    <?php else: ?>
                        <span class="badge badge-pending">Pending</span>
                    <?php endif; ?>
                </h2>
                
                <div class="profile-actions">
                    <?php if($professional['verified'] != 1): ?>
                        <form action="admin_prof_details.php?id=<?php echo $professional_id; ?>" method="POST" style="display: inline;">
                            <input type="hidden" name="action" value="verify">
                            <button type="submit" class="action-button" style="padding: 5px 10px; font-size: 0.9rem;">
                                <i class="fas fa-check"></i> Verify
                            </button>
                        </form>
                    <?php else: ?>
                        <form action="admin_prof_details.php?id=<?php echo $professional_id; ?>" method="POST" style="display: inline;">
                            <input type="hidden" name="action" value="unverify">
                            <button type="submit" class="action-button secondary" style="padding: 5px 10px; font-size: 0.9rem;">
                                <i class="fas fa-undo"></i> Unverify
                            </button>
                        </form>
                    <?php endif; ?>
                    
                    <button type="button" class="action-button delete-btn" onclick="openDeleteModal()" style="padding: 5px 10px; font-size: 0.9rem;">
                        <i class="fas fa-trash"></i> Delete Account
                    </button>
                </div>
            </div>
            
            <div class="stats-grid">
                <div class="stat-box">
                    <div class="stat-value"><?php echo $client_count; ?></div>
                    <div>Clients</div>
                </div>
                <div class="stat-box">
                    <div class="stat-value"><?php echo $resource_count; ?></div>
                    <div>Resources</div>
                </div>
                <div class="stat-box">
                    <div class="stat-value"><?php echo $appointment_count; ?></div>
                    <div>Appointments</div>
                </div>
                <div class="stat-box">
                    <div class="stat-value"><?php echo date('M Y', strtotime($professional['created_at'])); ?></div>
                    <div>Joined</div>
                </div>
            </div>
            
            <div class="profile-section">
                <h3><i class="fas fa-id-card"></i> Basic Information</h3>
                
                <div class="detail-row">
                    <div class="detail-label">Full Name:</div>
                    <div class="detail-value"><?php echo htmlspecialchars($professional['fullname']); ?></div>
                </div>
                
                <div class="detail-row">
                    <div class="detail-label">Email:</div>
                    <div class="detail-value"><?php echo htmlspecialchars($professional['email']); ?></div>
                </div>
                
                <div class="detail-row">
                    <div class="detail-label">Joined Date:</div>
                    <div class="detail-value"><?php echo date('F j, Y', strtotime($professional['created_at'])); ?></div>
                </div>
                
                <div class="detail-row">
                    <div class="detail-label">Specialty:</div>
                    <div class="detail-value"><?php echo htmlspecialchars($professional['specialty'] ?: 'Not specified'); ?></div>
                </div>
                
                <div class="detail-row">
                    <div class="detail-label">License Number:</div>
                    <div class="detail-value"><?php echo htmlspecialchars($professional['license_number'] ?: 'Not provided'); ?></div>
                </div>
            </div>
            
            <?php if(!empty($professional['education']) || !empty($professional['experience'])): ?>
                <div class="profile-section">
                    <h3><i class="fas fa-graduation-cap"></i> Education & Experience</h3>
                    
                    <?php if(!empty($professional['education'])): ?>
                        <div class="detail-row">
                            <div class="detail-label">Education:</div>
                            <div class="detail-value"><?php echo nl2br(htmlspecialchars($professional['education'])); ?></div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if(!empty($professional['experience'])): ?>
                        <div class="detail-row">
                            <div class="detail-label">Experience:</div>
                            <div class="detail-value"><?php echo nl2br(htmlspecialchars($professional['experience'])); ?></div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <!-- Recent Clients Section -->
            <div class="profile-section">
                <h3><i class="fas fa-users"></i> Recent Clients (<?php echo $client_count; ?> total)</h3>
                
                <?php if($clients_result->num_rows > 0): ?>
                    <div style="margin-top: 15px;">
                        <?php while($client = $clients_result->fetch_assoc()): ?>
                            <div class="client-item">
                                <div><strong><?php echo htmlspecialchars($client['fullname']); ?></strong></div>
                                <div><?php echo htmlspecialchars($client['email']); ?></div>
                                <div><small>Added: <?php echo date('M d, Y', strtotime($client['created_at'])); ?></small></div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                    
                    <?php if($client_count > 5): ?>
                        <div style="text-align: center; margin-top: 10px;">
                            <a href="admin_clients.php?professional_id=<?php echo $professional_id; ?>" class="action-button secondary" style="display: inline-block; text-decoration: none; padding: 5px 15px; font-size: 0.9rem;">
                                <i class="fas fa-users"></i> View All Clients
                            </a>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <p>This professional has no clients yet.</p>
                <?php endif; ?>
            </div>
            
            <!-- Recent Resources Section -->
            <div class="profile-section">
                <h3><i class="fas fa-book-medical"></i> Recent Resources (<?php echo $resource_count; ?> total)</h3>
                
                <?php if($resources_result->num_rows > 0): ?>
                    <div style="margin-top: 15px;">
                        <?php while($resource = $resources_result->fetch_assoc()): ?>
<div class="resource-item">
    <div>
        <div><strong><?php echo htmlspecialchars($resource['title']); ?></strong></div>
        <div><small>Created: <?php echo date('M d, Y', strtotime($resource['created_at'])); ?></small></div>
    </div>
</div>
                        <?php endwhile; ?>
                    </div>
                    
                    <?php if($resource_count > 5): ?>
                        <div style="text-align: center; margin-top: 10px;">
                            <a href="admin_resources.php?professional_id=<?php echo $professional_id; ?>" class="action-button secondary" style="display: inline-block; text-decoration: none; padding: 5px 15px; font-size: 0.9rem;">
                                <i class="fas fa-book"></i> View All Resources
                            </a>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <p>This professional has not created any resources yet.</p>
                <?php endif; ?>
            </div>
        </div>
        
        <footer style="text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; color: #7f8c8d;">
            <p>&copy; 2025 Mental Health Support. All rights reserved.</p>
        </footer>
    </div>
    
    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Delete Professional Account</h3>
                <span class="close-modal" onclick="closeModal()">&times;</span>
            </div>
            <p>Are you sure you want to delete this professional account? This action cannot be undone.</p>
            <p><strong>Warning:</strong> Deleting this account will also remove all associated data including appointments, resources, and client connections.</p>
            
            <form action="admin_prof_details.php?id=<?php echo $professional_id; ?>" method="POST">
                <input type="hidden" name="action" value="delete">
                
                <div style="display: flex; justify-content: space-between; margin-top: 20px;">
                    <button type="button" class="action-button secondary" onclick="closeModal()">
                        Cancel
                    </button>
                    <button type="submit" class="action-button delete-btn">
                        <i class="fas fa-trash"></i> Permanently Delete
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // Modal functionality
        function openDeleteModal() {
            document.getElementById('deleteModal').style.display = 'flex';
        }
        
        function closeModal() {
            document.getElementById('deleteModal').style.display = 'none';
        }
        
        // Close modal when clicking outside the content
        window.onclick = function(event) {
            const modal = document.getElementById('deleteModal');
            if (event.target == modal) {
                closeModal();
            }
        }
    </script>
</body>
</html>