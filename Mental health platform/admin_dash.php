<?php
// Include the admin authentication file
include 'admin_auth.php';

// Get counts for dashboard stats
$user_count_query = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'user'");
$user_count = $user_count_query->fetch_assoc()['count'];

$professional_count_query = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'professional'");
$professional_count = $professional_count_query->fetch_assoc()['count'];

$pending_verification_query = $conn->query("SELECT COUNT(*) as count FROM professional_profiles WHERE verified = 0 OR verified IS NULL");
$pending_verification_count = $pending_verification_query->fetch_assoc()['count'];

$resources_count_query = $conn->query("SELECT COUNT(*) as count FROM mental_health_resources");
$resources_count = $resources_count_query->fetch_assoc()['count'];

$appointments_count_query = $conn->query("SELECT COUNT(*) as count FROM appointments");
$appointments_count = $appointments_count_query->fetch_assoc()['count'];

// Get recent user registrations
$recent_users_query = $conn->query("SELECT id, fullname, email, role, created_at FROM users ORDER BY created_at DESC LIMIT 5");

// Get professionals pending verification
$pending_verification_query = $conn->query("SELECT pp.*, u.fullname, u.email, u.created_at 
                                         FROM professional_profiles pp
                                         JOIN users u ON pp.user_id = u.id
                                         WHERE pp.verified = 0 OR pp.verified IS NULL
                                         ORDER BY u.created_at DESC
                                         LIMIT 5");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Mental Health Support</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.1);
            padding: 20px;
            text-align: center;
            transition: all 0.3s ease;
        }
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }
        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: #3498db;
            margin: 10px 0;
        }
        .feature-card {
            border: 1px solid #eee;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            background-color: white;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 20px;
        }
        .feature-card:hover {
            border-color: #3498db;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        .feature-icon {
            font-size: 36px;
            color: #3498db;
            min-width: 60px;
            text-align: center;
        }
        .status-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            color: white;
            margin-left: 10px;
        }
        .status-badge.admin {
            background-color: #e74c3c;
        }
        .status-badge.professional {
            background-color: #3498db;
        }
        .status-badge.user {
            background-color: #2ecc71;
        }
        .status-badge.pending {
            background-color: #f39c12;
        }
        .btn-verify {
            background-color: #2ecc71;
            color: white;
            border: none;
            border-radius: 5px;
            padding: 5px 10px;
            font-size: 0.9rem;
            cursor: pointer;
        }
        .btn-verify:hover {
            background-color: #27ae60;
        }
    </style>
</head>
<body style="background-color: #f5f7fa; align-items: flex-start; padding-top: 30px;">
    <div class="dashboard-container">
        <div class="welcome-header">
            <div>
                <h1><i class="fas fa-user-shield" style="color: #e74c3c;"></i> Admin Dashboard</h1>
                <p>Welcome back, Administrator <?php echo htmlspecialchars($_SESSION['fullname']); ?>!</p>
            </div>
            <a href="logout.php" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
        
        <!-- Stats Overview -->
        <div class="card">
            <h2><i class="fas fa-chart-line"></i> Platform Overview</h2>
            <div class="stats-container">
    <div class="stat-card">
        <i class="fas fa-users" style="font-size: 24px; color: #3498db;"></i>
        <div class="stat-number"><?php echo $user_count; ?></div>
        <div>Normal Users</div>
    </div>
    <div class="stat-card">
        <i class="fas fa-user-md" style="font-size: 24px; color: #3498db;"></i>
        <div class="stat-number"><?php echo $professional_count; ?></div>
        <div>Professionals</div>
    </div>
    <div class="stat-card">
        <i class="fas fa-user-check" style="font-size: 24px; color: #3498db;"></i>
        <div class="stat-number"><?php echo $pending_verification_count; ?></div>
        <div>Pending Verification</div>
    </div>
    <div class="stat-card">
        <i class="fas fa-book-medical" style="font-size: 24px; color: #3498db;"></i>
        <div class="stat-number"><?php echo $resources_count; ?></div>
        <div>Total Resources</div>
    </div>
    <div class="stat-card">
        <i class="fas fa-calendar-check" style="font-size: 24px; color: #3498db;"></i>
        <div class="stat-number"><?php echo $appointments_count; ?></div>
        <div>Appointments</div>
    </div>
</div>
        </div>
        
        <!-- Admin Features -->
        <div class="card">
    <h2><i class="fas fa-tools"></i> Administration Tools</h2>
    <p>Manage users and resources on your mental health platform.</p>
    
    <div style="margin-top: 20px;">
        <a href="admin_users.php" class="feature-card" style="text-decoration: none; color: inherit;">
            <div class="feature-icon">
                <i class="fas fa-users-cog"></i>
            </div>
            <div>
                <h3>User Management</h3>
                <p>Manage user accounts, roles, and permissions.</p>
            </div>
        </a>
        
        <a href="admin_resources.php" class="feature-card" style="text-decoration: none; color: inherit;">
            <div class="feature-icon">
                <i class="fas fa-book-medical"></i>
            </div>
            <div>
                <h3>Resource Management</h3>
                <p>Review, edit, and moderate mental health resources.</p>
            </div>
        </a>


<a href="admin_reports.php" class="feature-card" style="text-decoration: none; color: inherit;">
    <div class="feature-icon">
        <i class="fas fa-file-pdf"></i>
    </div>
    <div>
        <h3>Generate Reports</h3>
        <p>Create and download PDF reports for platform activity, professionals, and connections.</p>
    </div>
</a>
    </div>
</div>
        
        <!-- Recent Users -->
        <div class="card">
            <h2><i class="fas fa-user-plus"></i> Recent User Registrations</h2>
            
            <?php if($recent_users_query->num_rows > 0): ?>
                <div style="overflow-x: auto; margin-top: 20px;">
                    <table style="width: 100%; border-collapse: collapse;">
    <thead>
        <tr>
            <th style="text-align: left; padding: 10px; border-bottom: 1px solid #ddd;">Name</th>
            <th style="text-align: left; padding: 10px; border-bottom: 1px solid #ddd;">Email</th>
            <th style="text-align: left; padding: 10px; border-bottom: 1px solid #ddd;">Role</th>
            <th style="text-align: left; padding: 10px; border-bottom: 1px solid #ddd;">Registered On</th>
        </tr>
    </thead>
    <tbody>
        <?php while($user = $recent_users_query->fetch_assoc()): ?>
            <tr>
                <td style="padding: 10px; border-bottom: 1px solid #eee;">
                    <?php echo htmlspecialchars($user['fullname']); ?>
                </td>
                <td style="padding: 10px; border-bottom: 1px solid #eee;">
                    <?php echo htmlspecialchars($user['email']); ?>
                </td>
                <td style="padding: 10px; border-bottom: 1px solid #eee;">
                    <span class="status-badge <?php echo $user['role']; ?>">
                        <?php echo ucfirst($user['role']); ?>
                    </span>
                </td>
                <td style="padding: 10px; border-bottom: 1px solid #eee;">
                    <?php echo date('M d, Y g:i A', strtotime($user['created_at'])); ?>
                </td>
            </tr>
        <?php endwhile; ?>
    </tbody>
</table>
                </div>
                
                <a href="admin_users.php" class="action-button" style="display: inline-block; margin-top: 15px; text-decoration: none;">
                    <i class="fas fa-users"></i> View All Users
                </a>
            <?php else: ?>
                <p>No recent user registrations found.</p>
            <?php endif; ?>
        </div>
        
        <!-- Professionals Pending Verification -->
        <div class="card">
            <h2><i class="fas fa-user-check"></i> Professionals Pending Verification</h2>
            
            <?php if($pending_verification_query->num_rows > 0): ?>
                <div style="overflow-x: auto; margin-top: 20px;">
                    <table style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr>
                                <th style="text-align: left; padding: 10px; border-bottom: 1px solid #ddd;">Name</th>
                                <th style="text-align: left; padding: 10px; border-bottom: 1px solid #ddd;">Email</th>
                                <th style="text-align: left; padding: 10px; border-bottom: 1px solid #ddd;">Specialty</th>
                                <th style="text-align: left; padding: 10px; border-bottom: 1px solid #ddd;">License</th>
                                <th style="text-align: left; padding: 10px; border-bottom: 1px solid #ddd;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($professional = $pending_verification_query->fetch_assoc()): ?>
                                <tr>
                                    <td style="padding: 10px; border-bottom: 1px solid #eee;">
                                        <?php echo htmlspecialchars($professional['fullname']); ?>
                                    </td>
                                    <td style="padding: 10px; border-bottom: 1px solid #eee;">
                                        <?php echo htmlspecialchars($professional['email']); ?>
                                    </td>
                                    <td style="padding: 10px; border-bottom: 1px solid #eee;">
                                        <?php echo htmlspecialchars($professional['specialty'] ?: 'Not specified'); ?>
                                    </td>
                                    <td style="padding: 10px; border-bottom: 1px solid #eee;">
                                        <?php echo htmlspecialchars($professional['license_number'] ?: 'Not provided'); ?>
                                    </td>
                                    <td style="padding: 10px; border-bottom: 1px solid #eee;">
                                        <form action="admin_verification.php" method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="verify">
                                            <input type="hidden" name="user_id" value="<?php echo $professional['user_id']; ?>">
                                            <button type="submit" class="btn-verify">
                                                <i class="fas fa-check"></i> Verify
                                            </button>
                                        </form>
                                        <a href="admin_prof_details.php?id=<?php echo $professional['user_id']; ?>" class="action-button secondary" style="padding: 5px 10px; font-size: 0.9rem; text-decoration: none; margin-left: 5px;">
                                            <i class="fas fa-eye"></i> Details
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                
                <a href="admin_verification.php" class="action-button" style="display: inline-block; margin-top: 15px; text-decoration: none;">
                    <i class="fas fa-user-check"></i> Manage Verifications
                </a>
            <?php else: ?>
                <p>No professionals pending verification.</p>
            <?php endif; ?>
        </div>
        
        <footer style="text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; color: #7f8c8d;">
            <p>&copy; 2025 Mental Health Support. All rights reserved.</p>
            <p style="font-size: 14px; margin-top: 5px;">
                <a href="#" style="color: #7f8c8d; margin: 0 10px;">Admin Panel</a> | 
                <a href="#" style="color: #7f8c8d; margin: 0 10px;">System Status</a> | 
                <a href="#" style="color: #7f8c8d; margin: 0 10px;">Help</a>
            </p>
        </footer>
    </div>
</body>
</html>