<?php
// Include the admin authentication file
include 'admin_auth.php';

$message = "";
$error = "";

// Handle verification action
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'])) {
    if ($_POST['action'] == 'verify' && isset($_POST['user_id'])) {
        $user_id = intval($_POST['user_id']);
        
        // Update verification status
        $stmt = $conn->prepare("UPDATE professional_profiles SET verified = 1 WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        
        if ($stmt->execute()) {
            $message = "Professional successfully verified!";
        } else {
            $error = "Error verifying professional: " . $conn->error;
        }
    } else if ($_POST['action'] == 'reject' && isset($_POST['user_id'])) {
        $user_id = intval($_POST['user_id']);
        $rejection_reason = isset($_POST['rejection_reason']) ? $_POST['rejection_reason'] : '';
        
        // In a real system, you might want to send an email notification to the professional
        // with the rejection reason
        
        // For now, we'll just mark them as explicitly rejected in the system
        $stmt = $conn->prepare("UPDATE professional_profiles SET verified = 0, rejection_reason = ? WHERE user_id = ?");
        $stmt->bind_param("si", $rejection_reason, $user_id);
        
        if ($stmt->execute()) {
            $message = "Professional verification rejected.";
        } else {
            $error = "Error rejecting verification: " . $conn->error;
        }
    }
}

// Get filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'pending';
$search_term = isset($_GET['search']) ? $_GET['search'] : '';

// Build query based on filters
$query = "SELECT pp.*, u.fullname, u.email, u.created_at 
          FROM professional_profiles pp
          JOIN users u ON pp.user_id = u.id
          WHERE 1=1";

$params = [];
$param_types = "";

// Apply status filter
if ($status_filter == 'pending') {
    $query .= " AND (pp.verified = 0 OR pp.verified IS NULL)";
} elseif ($status_filter == 'verified') {
    $query .= " AND pp.verified = 1";
} elseif ($status_filter == 'rejected') {
    $query .= " AND pp.verified = 0 AND pp.rejection_reason IS NOT NULL";
}

// Apply search filter
if (!empty($search_term)) {
    $search_param = "%$search_term%";
    $query .= " AND (u.fullname LIKE ? OR u.email LIKE ? OR pp.specialty LIKE ? OR pp.license_number LIKE ?)";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $param_types .= "ssss";
}

$query .= " ORDER BY u.created_at DESC";

// Prepare and execute query
$stmt = $conn->prepare($query);

if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

// Count total professionals
$total_count_query = $conn->query("SELECT COUNT(*) as count FROM professional_profiles");
$total_count = $total_count_query->fetch_assoc()['count'];

// Count pending verifications
$pending_count_query = $conn->query("SELECT COUNT(*) as count FROM professional_profiles WHERE verified = 0 OR verified IS NULL");
$pending_count = $pending_count_query->fetch_assoc()['count'];

// Count verified professionals
$verified_count_query = $conn->query("SELECT COUNT(*) as count FROM professional_profiles WHERE verified = 1");
$verified_count = $verified_count_query->fetch_assoc()['count'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Professional Verification - Admin Panel</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        .filter-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 10px;
        }
        .filter-tabs {
            display: flex;
            gap: 10px;
        }
        .filter-tab {
            padding: 8px 16px;
            background-color: #f8f9fa;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            color: #333;
        }
        .filter-tab:hover {
            background-color: #e9ecef;
        }
        .filter-tab.active {
            background-color: #3498db;
            color: white;
        }
        .search-box {
            flex: 1;
            max-width: 400px;
            display: flex;
            gap: 5px;
        }
        .search-box input {
            flex: 1;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        .prof-card {
            border: 1px solid #eee;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 15px;
            background-color: white;
        }
        .prof-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }
        .prof-actions {
            display: flex;
            gap: 10px;
        }
        .prof-details {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }
        .detail-item {
            margin-bottom: 5px;
        }
        .detail-label {
            font-weight: 600;
            color: #555;
        }
        .badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 0.8rem;
            color: white;
        }
        .badge-pending {
            background-color: #f39c12;
        }
        .badge-verified {
            background-color: #2ecc71;
        }
        .badge-rejected {
            background-color: #e74c3c;
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
        .stat-nums {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
        }
        .stat-item {
            flex: 1;
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 10px;
            text-align: center;
        }
        .stat-value {
            font-size: 1.8rem;
            font-weight: 700;
            color: #3498db;
        }
    </style>
</head>
<body style="background-color: #f5f7fa; align-items: flex-start; padding-top: 30px;">
    <div class="dashboard-container">
        <div class="welcome-header">
            <div>
                <h1><i class="fas fa-user-check" style="color: #3498db;"></i> Professional Verification</h1>
                <p>Review and verify professional accounts</p>
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
            <h2><i class="fas fa-chart-bar"></i> Verification Statistics</h2>
            
            <div class="stat-nums">
                <div class="stat-item">
                    <div class="stat-value"><?php echo $total_count; ?></div>
                    <div>Total Professionals</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value"><?php echo $pending_count; ?></div>
                    <div>Pending Verification</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value"><?php echo $verified_count; ?></div>
                    <div>Verified Professionals</div>
                </div>
            </div>
        </div>
        
        <div class="card">
            <div class="filter-bar">
                <div class="filter-tabs">
                    <a href="admin_verification.php?status=pending<?php echo !empty($search_term) ? '&search='.urlencode($search_term) : ''; ?>" class="filter-tab <?php echo $status_filter == 'pending' ? 'active' : ''; ?>">
                        Pending Verification
                    </a>
                    <a href="admin_verification.php?status=verified<?php echo !empty($search_term) ? '&search='.urlencode($search_term) : ''; ?>" class="filter-tab <?php echo $status_filter == 'verified' ? 'active' : ''; ?>">
                        Verified
                    </a>
                    <a href="admin_verification.php?status=all<?php echo !empty($search_term) ? '&search='.urlencode($search_term) : ''; ?>" class="filter-tab <?php echo $status_filter == 'all' ? 'active' : ''; ?>">
                        All Professionals
                    </a>
                </div>
                
                <form class="search-box" action="admin_verification.php" method="GET">
                    <input type="hidden" name="status" value="<?php echo $status_filter; ?>">
                    <input type="text" name="search" placeholder="Search by name, email or specialty" value="<?php echo htmlspecialchars($search_term); ?>">
                    <button type="submit" class="action-button secondary" style="margin-top: 0; padding: 8px;">
                        <i class="fas fa-search"></i>
                    </button>
                    <?php if(!empty($search_term)): ?>
                        <a href="admin_verification.php?status=<?php echo $status_filter; ?>" class="action-button secondary" style="margin-top: 0; padding: 8px; text-decoration: none;">
                            <i class="fas fa-times"></i>
                        </a>
                    <?php endif; ?>
                </form>
            </div>
            
            <h2>
                <?php
                switch($status_filter) {
                    case 'pending':
                        echo '<i class="fas fa-hourglass-half"></i> Professionals Pending Verification';
                        break;
                    case 'verified':
                        echo '<i class="fas fa-check-circle"></i> Verified Professionals';
                        break;
                    default:
                        echo '<i class="fas fa-user-md"></i> All Professionals';
                }
                ?>
                <span style="font-size: 1rem; font-weight: normal; margin-left: 10px;">
                    (<?php echo $result->num_rows; ?> results)
                </span>
            </h2>
            
            <?php if($result->num_rows > 0): ?>
                <?php while($professional = $result->fetch_assoc()): ?>
                    <div class="prof-card">
                        <div class="prof-header">
                            <div>
                                <h3>
                                    <?php echo htmlspecialchars($professional['fullname']); ?>
                                    
                                    <?php if($professional['verified'] == 1): ?>
                                        <span class="badge badge-verified">Verified</span>
                                    <?php elseif(isset($professional['rejection_reason']) && !empty($professional['rejection_reason'])): ?>
                                        <span class="badge badge-rejected">Rejected</span>
                                    <?php else: ?>
                                        <span class="badge badge-pending">Pending</span>
                                    <?php endif; ?>
                                </h3>
                                <p><?php echo htmlspecialchars($professional['email']); ?></p>
                            </div>
                            
                            <div class="prof-actions">
                                <?php if($professional['verified'] != 1): ?>
                                    <form action="admin_verification.php" method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="verify">
                                        <input type="hidden" name="user_id" value="<?php echo $professional['user_id']; ?>">
                                        <button type="submit" class="action-button" style="padding: 5px 10px; font-size: 0.9rem;">
                                            <i class="fas fa-check"></i> Verify
                                        </button>
                                    </form>
                                    
                                    <button type="button" class="action-button secondary" style="padding: 5px 10px; font-size: 0.9rem;" 
                                            onclick="openRejectModal(<?php echo $professional['user_id']; ?>)">
                                        <i class="fas fa-times"></i> Reject
                                    </button>
                                <?php else: ?>
                                    <form action="admin_verification.php" method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="unverify">
                                        <input type="hidden" name="user_id" value="<?php echo $professional['user_id']; ?>">
                                        <button type="submit" class="action-button secondary" style="padding: 5px 10px; font-size: 0.9rem;">
                                            <i class="fas fa-undo"></i> Unverify
                                        </button>
                                    </form>
                                <?php endif; ?>
                                
                                <a href="admin_prof_details.php?id=<?php echo $professional['user_id']; ?>" class="action-button secondary" style="padding: 5px 10px; font-size: 0.9rem; text-decoration: none;">
                                    <i class="fas fa-eye"></i> Details
                                </a>
                            </div>
                        </div>
                        
                        <div class="prof-details">
                            <div>
                                <div class="detail-item">
                                    <span class="detail-label">Specialty:</span> 
                                    <?php echo htmlspecialchars($professional['specialty'] ?: 'Not specified'); ?>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">License:</span> 
                                    <?php echo htmlspecialchars($professional['license_number'] ?: 'Not provided'); ?>
                                </div>
                            </div>
                            <div>
                                <div class="detail-item">
                                    <span class="detail-label">Joined:</span> 
                                    <?php echo date('M d, Y', strtotime($professional['created_at'])); ?>
                                </div>
                                <?php if(isset($professional['rejection_reason']) && !empty($professional['rejection_reason'])): ?>
                                    <div class="detail-item">
                                        <span class="detail-label">Rejection Reason:</span> 
                                        <?php echo htmlspecialchars($professional['rejection_reason']); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <?php if(!empty($professional['education']) || !empty($professional['experience'])): ?>
                            <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #eee;">
                                <?php if(!empty($professional['education'])): ?>
                                    <div class="detail-item">
                                        <span class="detail-label">Education:</span> 
                                        <?php echo nl2br(htmlspecialchars($professional['education'])); ?>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if(!empty($professional['experience'])): ?>
                                    <div class="detail-item" style="margin-top: 10px;">
                                        <span class="detail-label">Experience:</span> 
                                        <?php echo nl2br(htmlspecialchars($professional['experience'])); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p style="text-align: center; padding: 20px;">
                    <i class="fas fa-info-circle"></i> No professionals found matching your criteria.
                </p>
            <?php endif; ?>
        </div>
        
        <footer style="text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; color: #7f8c8d;">
            <p>&copy; 2025 Mental Health Support. All rights reserved.</p>
        </footer>
    </div>
    
    <!-- Rejection Modal -->
    <div id="rejectModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Reject Professional Verification</h3>
                <span class="close-modal" onclick="closeModal()">&times;</span>
            </div>
            <form action="admin_verification.php" method="POST">
                <input type="hidden" name="action" value="reject">
                <input type="hidden" name="user_id" id="reject_user_id" value="">
                
                <div style="margin-bottom: 15px;">
                    <label for="rejection_reason" style="display: block; margin-bottom: 5px; font-weight: 600;">
                        Reason for Rejection:
                    </label>
                    <textarea id="rejection_reason" name="rejection_reason" required
                              style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; min-height: 100px;"
                              placeholder="Please provide a reason for rejecting this professional's verification request."></textarea>
                </div>
                
                <div style="display: flex; justify-content: space-between;">
                    <button type="button" class="action-button secondary" onclick="closeModal()">
                        Cancel
                    </button>
                    <button type="submit" class="action-button" style="background-color: #e74c3c;">
                        <i class="fas fa-times"></i> Reject Verification
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // Modal functionality
        function openRejectModal(userId) {
            document.getElementById('reject_user_id').value = userId;
            document.getElementById('rejectModal').style.display = 'flex';
        }
        
        function closeModal() {
            document.getElementById('rejectModal').style.display = 'none';
        }
        
        // Close modal when clicking outside the content
        window.onclick = function(event) {
            const modal = document.getElementById('rejectModal');
            if (event.target == modal) {
                closeModal();
            }
        }
    </script>
</body>
</html>