<?php
// Include the admin authentication file
include 'admin_auth.php';

$message = "";
$error = "";

// Handle user actions
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'])) {
    if ($_POST['action'] == 'delete' && isset($_POST['user_id'])) {
        $user_id = intval($_POST['user_id']);
        
        // Check if user exists and is not an admin
        $check_user = $conn->prepare("SELECT id, role FROM users WHERE id = ?");
        $check_user->bind_param("i", $user_id);
        $check_user->execute();
        $user_result = $check_user->get_result();
        
        if ($user_result->num_rows > 0) {
            $user = $user_result->fetch_assoc();
            
            // Don't allow deleting admin users through this interface
            if ($user['role'] == 'admin') {
                $error = "Cannot delete administrator accounts through this interface.";
            } else {
                // Start transaction for deleting user and related data
                $conn->begin_transaction();
                
                try {
                    // Delete based on user role
                    if ($user['role'] == 'professional') {
                        // Delete from professional_profiles
                        $stmt1 = $conn->prepare("DELETE FROM professional_profiles WHERE user_id = ?");
                        $stmt1->bind_param("i", $user_id);
                        $stmt1->execute();
                        
                        // Delete professional clients
                        $stmt2 = $conn->prepare("DELETE FROM professional_clients WHERE professional_id = ?");
                        $stmt2->bind_param("i", $user_id);
                        $stmt2->execute();
                        
                        // Delete resources
                        $stmt3 = $conn->prepare("DELETE FROM mental_health_resources WHERE professional_id = ?");
                        $stmt3->bind_param("i", $user_id);
                        $stmt3->execute();
                        
                        // Delete appointments
                        $stmt4 = $conn->prepare("DELETE FROM appointments WHERE professional_id = ?");
                        $stmt4->bind_param("i", $user_id);
                        $stmt4->execute();
                    } else if ($user['role'] == 'user') {
                        // Delete user_profiles
                        $stmt1 = $conn->prepare("DELETE FROM user_profiles WHERE user_id = ?");
                        $stmt1->bind_param("i", $user_id);
                        $stmt1->execute();
                        
                        // Delete professional clients
                        $stmt2 = $conn->prepare("DELETE FROM professional_clients WHERE client_id = ?");
                        $stmt2->bind_param("i", $user_id);
                        $stmt2->execute();
                        
                        // Delete appointments
                        $stmt3 = $conn->prepare("DELETE FROM appointments WHERE client_id = ?");
                        $stmt3->bind_param("i", $user_id);
                        $stmt3->execute();
                        
                        // Delete mood logs
                        $stmt4 = $conn->prepare("DELETE FROM mood_logs WHERE user_id = ?");
                        $stmt4->bind_param("i", $user_id);
                        $stmt4->execute();
                    }
                    
                    // Delete chat connections
                    $stmt5 = $conn->prepare("DELETE FROM chat_connections WHERE professional_id = ? OR client_id = ?");
                    $stmt5->bind_param("ii", $user_id, $user_id);
                    $stmt5->execute();
                    
                    // Delete chat messages
                    $stmt6 = $conn->prepare("DELETE FROM chat_messages WHERE sender_id = ? OR receiver_id = ?");
                    $stmt6->bind_param("ii", $user_id, $user_id);
                    $stmt6->execute();
                    
                    // Finally, delete the user
                    $stmt7 = $conn->prepare("DELETE FROM users WHERE id = ?");
                    $stmt7->bind_param("i", $user_id);
                    $stmt7->execute();
                    
                    // Commit the transaction
                    $conn->commit();
                    $message = "User has been deleted successfully.";
                } catch (Exception $e) {
                    // Rollback on error
                    $conn->rollback();
                    $error = "Error deleting user: " . $e->getMessage();
                }
            }
        } else {
            $error = "User not found.";
        }
    } else if ($_POST['action'] == 'change_role' && isset($_POST['user_id']) && isset($_POST['new_role'])) {
        $user_id = intval($_POST['user_id']);
        $new_role = $_POST['new_role'];
        
        // Validate role
        if (!in_array($new_role, ['user', 'professional'])) {
            $error = "Invalid role selected.";
        } else {
            // Check if user exists and is not an admin
            $check_user = $conn->prepare("SELECT id, role FROM users WHERE id = ?");
            $check_user->bind_param("i", $user_id);
            $check_user->execute();
            $user_result = $check_user->get_result();
            
            if ($user_result->num_rows > 0) {
                $user = $user_result->fetch_assoc();
                
                // Don't allow changing admin roles
                if ($user['role'] == 'admin') {
                    $error = "Cannot change administrator role through this interface.";
                } else {
                    // Update user role
                    $update_role = $conn->prepare("UPDATE users SET role = ? WHERE id = ?");
                    $update_role->bind_param("si", $new_role, $user_id);
                    
                    if ($update_role->execute()) {
                        $message = "User role has been updated successfully.";
                    } else {
                        $error = "Error updating user role: " . $conn->error;
                    }
                }
            } else {
                $error = "User not found.";
            }
        }
    }
}

// Get filter parameters
$role_filter = isset($_GET['role']) ? $_GET['role'] : 'all';
$search_term = isset($_GET['search']) ? $_GET['search'] : '';

// Build query based on filters
$query = "SELECT * FROM users WHERE 1=1";

$params = [];
$param_types = "";

// Apply role filter
if ($role_filter == 'user') {
    $query .= " AND role = 'user'";
} elseif ($role_filter == 'professional') {
    $query .= " AND role = 'professional'";
} elseif ($role_filter == 'admin') {
    $query .= " AND role = 'admin'";
}

// Apply search filter
if (!empty($search_term)) {
    $search_param = "%$search_term%";
    $query .= " AND (fullname LIKE ? OR email LIKE ?)";
    $params[] = $search_param;
    $params[] = $search_param;
    $param_types .= "ss";
}

// Add sorting
$query .= " ORDER BY created_at DESC";

// Prepare and execute query
$stmt = $conn->prepare($query);

if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

// Count users by role
$total_users_query = $conn->query("SELECT COUNT(*) as count FROM users");
$total_users = $total_users_query->fetch_assoc()['count'];

$regular_users_query = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'user'");
$regular_users = $regular_users_query->fetch_assoc()['count'];

$professional_users_query = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'professional'");
$professional_users = $professional_users_query->fetch_assoc()['count'];

$admin_users_query = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'admin'");
$admin_users = $admin_users_query->fetch_assoc()['count'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - Admin Panel</title>
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
        .user-card {
            border: 1px solid #eee;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 15px;
            background-color: white;
        }
        .user-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }
        .user-actions {
            display: flex;
            gap: 10px;
        }
        .badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 0.8rem;
            color: white;
            margin-left: 8px;
        }
        .badge-admin {
            background-color: #e74c3c;
        }
        .badge-professional {
            background-color: #3498db;
        }
        .badge-user {
            background-color: #2ecc71;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
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
                <h1><i class="fas fa-users-cog" style="color: #3498db;"></i> User Management</h1>
                <p>Manage platform users and their roles</p>
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
            <h2><i class="fas fa-chart-bar"></i> User Statistics</h2>
            
            <div class="stats-grid">
                <div class="stat-box">
                    <div class="stat-value"><?php echo $total_users; ?></div>
                    <div>Total Users</div>
                </div>
                <div class="stat-box">
                    <div class="stat-value"><?php echo $regular_users; ?></div>
                    <div>Regular Users</div>
                </div>
                <div class="stat-box">
                    <div class="stat-value"><?php echo $professional_users; ?></div>
                    <div>Professionals</div>
                </div>
                <div class="stat-box">
                    <div class="stat-value"><?php echo $admin_users; ?></div>
                    <div>Administrators</div>
                </div>
            </div>
        </div>
        
        <div class="card">
            <div class="filter-bar">
                <div class="filter-tabs">
                    <a href="admin_users.php?role=all<?php echo !empty($search_term) ? '&search='.urlencode($search_term) : ''; ?>" class="filter-tab <?php echo $role_filter == 'all' ? 'active' : ''; ?>">
                        All Users
                    </a>
                    <a href="admin_users.php?role=user<?php echo !empty($search_term) ? '&search='.urlencode($search_term) : ''; ?>" class="filter-tab <?php echo $role_filter == 'user' ? 'active' : ''; ?>">
                        Regular Users
                    </a>
                    <a href="admin_users.php?role=professional<?php echo !empty($search_term) ? '&search='.urlencode($search_term) : ''; ?>" class="filter-tab <?php echo $role_filter == 'professional' ? 'active' : ''; ?>">
                        Professionals
                    </a>
                    <a href="admin_users.php?role=admin<?php echo !empty($search_term) ? '&search='.urlencode($search_term) : ''; ?>" class="filter-tab <?php echo $role_filter == 'admin' ? 'active' : ''; ?>">
                        Admins
                    </a>
                </div>
                
                <form class="search-box" action="admin_users.php" method="GET">
                    <input type="hidden" name="role" value="<?php echo $role_filter; ?>">
                    <input type="text" name="search" placeholder="Search by name or email" value="<?php echo htmlspecialchars($search_term); ?>">
                    <button type="submit" class="action-button secondary" style="margin-top: 0; padding: 8px;">
                        <i class="fas fa-search"></i>
                    </button>
                    <?php if(!empty($search_term)): ?>
                        <a href="admin_users.php?role=<?php echo $role_filter; ?>" class="action-button secondary" style="margin-top: 0; padding: 8px; text-decoration: none;">
                            <i class="fas fa-times"></i>
                        </a>
                    <?php endif; ?>
                </form>
            </div>
            
            <h2>
                <i class="fas fa-users"></i> 
                <?php
                switch($role_filter) {
                    case 'user':
                        echo 'Regular Users';
                        break;
                    case 'professional':
                        echo 'Professional Users';
                        break;
                    case 'admin':
                        echo 'Administrators';
                        break;
                    default:
                        echo 'All Users';
                }
                ?>
                <span style="font-size: 1rem; font-weight: normal; margin-left: 10px;">
                    (<?php echo $result->num_rows; ?> results)
                </span>
            </h2>
            
            <?php if($result->num_rows > 0): ?>
                <?php while($user = $result->fetch_assoc()): ?>
                    <div class="user-card">
                        <div class="user-header">
                            <div>
                                <h3>
                                    <?php echo htmlspecialchars($user['fullname']); ?>
                                    <span class="badge badge-<?php echo $user['role']; ?>">
                                        <?php echo ucfirst($user['role']); ?>
                                    </span>
                                </h3>
                                <p><?php echo htmlspecialchars($user['email']); ?></p>
                                <p><small>Member since: <?php echo date('F j, Y', strtotime($user['created_at'])); ?></small></p>
                            </div>
                            
                            <div class="user-actions">
                                <?php if($user['role'] != 'admin'): ?>
                                    <button type="button" class="action-button secondary" style="padding: 5px 10px; font-size: 0.9rem;" 
                                            onclick="openChangeRoleModal(<?php echo $user['id']; ?>, '<?php echo $user['role']; ?>')">
                                        <i class="fas fa-exchange-alt"></i> Change Role
                                    </button>
                                    
                                    <button type="button" class="action-button" style="background-color: #e74c3c; padding: 5px 10px; font-size: 0.9rem;" 
                                            onclick="openDeleteModal(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['fullname']); ?>')">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                <?php endif; ?>
                                
                                <?php if($user['role'] == 'professional'): ?>
                                    <a href="admin_prof_details.php?id=<?php echo $user['id']; ?>" class="action-button secondary" style="padding: 5px 10px; font-size: 0.9rem; text-decoration: none;">
                                        <i class="fas fa-eye"></i> Details
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p style="text-align: center; padding: 20px;">
                    <i class="fas fa-info-circle"></i> No users found matching your criteria.
                </p>
            <?php endif; ?>
        </div>
        
        <footer style="text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; color: #7f8c8d;">
            <p>&copy; 2025 Mental Health Support. All rights reserved.</p>
        </footer>
    </div>
    
    <!-- Change Role Modal -->
    <div id="changeRoleModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Change User Role</h3>
                <span class="close-modal" onclick="closeModal('changeRoleModal')">&times;</span>
            </div>
            <form action="admin_users.php" method="POST">
                <input type="hidden" name="action" value="change_role">
                <input type="hidden" name="user_id" id="role_user_id" value="">
                
                <div style="margin-bottom: 15px;">
                    <label for="new_role" style="display: block; margin-bottom: 5px; font-weight: 600;">
                        Select New Role:
                    </label>
                    <select id="new_role" name="new_role" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
                        <option value="user">Regular User</option>
                        <option value="professional">Professional</option>
                    </select>
                </div>
                
                <div style="display: flex; justify-content: space-between; margin-top: 20px;">
                    <button type="button" class="action-button secondary" onclick="closeModal('changeRoleModal')">
                        Cancel
                    </button>
                    <button type="submit" class="action-button">
                        <i class="fas fa-save"></i> Change Role
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Delete User Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Delete User</h3>
                <span class="close-modal" onclick="closeModal('deleteModal')">&times;</span>
            </div>
            <p>Are you sure you want to delete this user? This action cannot be undone.</p>
            <p id="deleteUserName" style="font-weight: bold;"></p>
            <p><strong>Warning:</strong> Deleting this user will also remove all associated data.</p>
            
            <form action="admin_users.php" method="POST">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="user_id" id="delete_user_id" value="">
                
                <div style="display: flex; justify-content: space-between; margin-top: 20px;">
                    <button type="button" class="action-button secondary" onclick="closeModal('deleteModal')">
                        Cancel
                    </button>
                    <button type="submit" class="action-button" style="background-color: #e74c3c;">
                        <i class="fas fa-trash"></i> Permanently Delete
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // Modal functionality
        function openChangeRoleModal(userId, currentRole) {
            document.getElementById('role_user_id').value = userId;
            document.getElementById('new_role').value = currentRole;
            document.getElementById('changeRoleModal').style.display = 'flex';
        }
        
        function openDeleteModal(userId, userName) {
            document.getElementById('delete_user_id').value = userId;
            document.getElementById('deleteUserName').innerText = userName;
            document.getElementById('deleteModal').style.display = 'flex';
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        // Close modal when clicking outside the content
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        }
    </script>
</body>
</html>