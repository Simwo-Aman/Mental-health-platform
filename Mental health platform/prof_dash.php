<?php
// Error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'professional') {
    header("Location: login.html");
    exit();
}

// Include database connection to fetch user data
include 'db_connect.php';

// Fetch professional information
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT u.*, pp.specialty, pp.license_number, pp.verified 
                        FROM users u 
                        LEFT JOIN professional_profiles pp ON u.id = pp.user_id 
                        WHERE u.id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$professional = $result->fetch_assoc();

// Fetch count of users (for display purposes)
$user_count_query = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'user'");
$user_count = $user_count_query->fetch_assoc()['count'];

// Fetch count of clients
$client_count_query = $conn->prepare("SELECT COUNT(*) as count FROM professional_clients WHERE professional_id = ? AND status = 'active'");
$client_count_query->bind_param("i", $user_id);
$client_count_query->execute();
$client_count = $client_count_query->get_result()->fetch_assoc()['count'];

// Fetch count of resources
$resource_count_query = $conn->prepare("SELECT COUNT(*) as count FROM mental_health_resources WHERE professional_id = ?");
$resource_count_query->bind_param("i", $user_id);
$resource_count_query->execute();
$resource_count = $resource_count_query->get_result()->fetch_assoc()['count'];

// Fetch upcoming appointments
$upcoming_query = $conn->prepare("SELECT COUNT(*) as count FROM appointments 
                               WHERE professional_id = ? 
                               AND status = 'scheduled' 
                               AND appointment_date >= CURRENT_DATE");
$upcoming_query->bind_param("i", $user_id);
$upcoming_query->execute();
$appointment_count = $upcoming_query->get_result()->fetch_assoc()['count'];
// Get unread message count
$unread_stmt = $conn->prepare("SELECT COUNT(*) as count FROM chat_messages WHERE receiver_id = ? AND is_read = 0");
$unread_stmt->bind_param("i", $user_id);
$unread_stmt->execute();
$unread_result = $unread_stmt->get_result();
$unread_count = $unread_result->num_rows > 0 ? $unread_result->fetch_assoc()['count'] : 0;

// Fetch recent clients (for display purposes)
$recent_clients = $conn->query("SELECT pc.*, u.fullname, u.email, u.created_at
                              FROM professional_clients pc
                              JOIN users u ON pc.client_id = u.id
                              WHERE pc.professional_id = $user_id
                              ORDER BY pc.id DESC LIMIT 5");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Professional Dashboard - Mental Health Support</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        /* Additional styles specific to professional dashboard */
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
    </style>
</head>
<body style="background-color: #f5f7fa; align-items: flex-start; padding-top: 30px;">
    <div class="dashboard-container">
        <div class="welcome-header">
            <div>
                <h1><i class="fas fa-brain" style="color: #3498db;"></i> Professional Dashboard</h1>
                <p>Welcome back, Dr. <?php echo htmlspecialchars($_SESSION['fullname']); ?>!</p>
            </div>
            <a href="logout.php" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
        
        <div class="user-info card">
            <h2><i class="fas fa-user-md"></i> Professional Profile 
                <?php if(isset($professional['verified']) && $professional['verified']): ?>
                    <span class="verification-badge"><i class="fas fa-check"></i> Verified</span>
                <?php else: ?>
                    <span class="verification-badge unverified-badge"><i class="fas fa-exclamation-circle"></i> Unverified</span>
                <?php endif; ?>
            </h2>
            <div style="display: flex; flex-wrap: wrap; gap: 20px; margin-top: 15px;">
                <div style="flex: 1; min-width: 200px;">
                    <p><strong><i class="fas fa-id-card"></i> Name:</strong><br> 
                        <?php echo htmlspecialchars($professional['fullname']); ?>
                    </p>
                    <p><strong><i class="fas fa-envelope"></i> Email:</strong><br> 
                        <?php echo htmlspecialchars($professional['email']); ?>
                    </p>
                </div>
                <div style="flex: 1; min-width: 200px;">
                    <p><strong><i class="fas fa-stethoscope"></i> Specialty:</strong><br> 
                        <?php echo isset($professional['specialty']) ? htmlspecialchars($professional['specialty']) : 'Not specified'; ?>
                    </p>
                    <p><strong><i class="fas fa-certificate"></i> License:</strong><br> 
                        <?php echo isset($professional['license_number']) ? htmlspecialchars($professional['license_number']) : 'Not specified'; ?>
                    </p>
                </div>
            </div>
            <a href="professional_profile.php" class="action-button secondary" style="display: inline-block; margin-top: 15px; text-decoration: none;">
                <i class="fas fa-edit"></i> Edit Profile
            </a>
        </div>
        
        <!-- Stats Overview -->
        <div class="card">
            <h2><i class="fas fa-chart-line"></i> Overview</h2>
            <div class="stats-container">
                <a href="manage_clients.php" class="stat-card" style="text-decoration: none; color: inherit;">
                    <i class="fas fa-user-friends" style="font-size: 24px; color: #3498db;"></i>
                    <div class="stat-number"><?php echo $client_count; ?></div>
                    <div>Active Clients</div>
                </a>
                <a href="manage_appointments.php" class="stat-card" style="text-decoration: none; color: inherit;">
                    <i class="fas fa-calendar-check" style="font-size: 24px; color: #3498db;"></i>
                    <div class="stat-number"><?php echo $appointment_count; ?></div>
                    <div>Upcoming Sessions</div>
                </a>
                <a href="manage_resources.php" class="stat-card" style="text-decoration: none; color: inherit;">
                    <i class="fas fa-book-medical" style="font-size: 24px; color: #3498db;"></i>
                    <div class="stat-number"><?php echo $resource_count; ?></div>
                    <div>Resources</div>
                </a>
                
            </div>
        </div>
        
        <!-- Professional Features -->
        <div class="card">
            <h2><i class="fas fa-tools"></i> Professional Tools</h2>
            <p>Access specialized tools to support your practice and clients.</p>
            
            <div style="margin-top: 20px;">
                <a href="manage_clients.php" class="feature-card" style="text-decoration: none; color: inherit;">
                    <div class="feature-icon">
                        <i class="fas fa-user-friends"></i>
                    </div>
                    <div>
                        <h3>Client Management</h3>
                        <p>Add and manage your clients, track client progress, and maintain client notes.</p>
                    </div>
                </a>
                
                <a href="manage_appointments.php" class="feature-card" style="text-decoration: none; color: inherit;">
                    <div class="feature-icon">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <div>
                        <h3>Appointment Scheduling</h3>
                        <p>Schedule sessions with clients, manage your availability, and keep track of appointments.</p>
                    </div>
                </a>
                <a href="chat.php" class="feature-card" style="text-decoration: none; color: inherit;">
    <div class="feature-icon">
        <i class="fas fa-comments"></i>
    </div>
    <div>
        <h3>
            Messages
            <?php if($unread_count > 0): ?>
                <span style="display: inline-block; 
                          background-color: #e74c3c; 
                          color: white; 
                          border-radius: 50%; 
                          padding: 2px 8px; 
                          font-size: 0.8rem; 
                          margin-left: 5px;">
                    <?php echo $unread_count; ?>
                </span>
            <?php endif; ?>
        </h3>
        <p>Chat securely with your clients and provide support between sessions.</p>
    </div>
</a>
                
                <a href="manage_resources.php" class="feature-card" style="text-decoration: none; color: inherit;">
                    <div class="feature-icon">
                        <i class="fas fa-book-medical"></i>
                    </div>
                    <div>
                        <h3>Resource Management</h3>
                        <p>Create and share mental health resources, articles, and guidelines with your clients.</p>
                    </div>
                </a>
                
                <a href="resources.php" class="feature-card" style="text-decoration: none; color: inherit;">
                    <div class="feature-icon">
                        <i class="fas fa-book"></i>
                    </div>
                    <div>
                        <h3>All Resources</h3>
                        <p>View all published resources from other professionals on the platform.</p>
                    </div>
                </a>
            </div>
        </div>
        
        <!-- Recent Clients -->
        <?php if($recent_clients->num_rows > 0): ?>
        <div class="card">
            <h2><i class="fas fa-history"></i> Recent Clients</h2>
            <div style="margin-top: 20px;">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr>
                            <th style="text-align: left; padding: 10px; border-bottom: 1px solid #ddd;">Name</th>
                            <th style="text-align: left; padding: 10px; border-bottom: 1px solid #ddd;">Email</th>
                            <th style="text-align: left; padding: 10px; border-bottom: 1px solid #ddd;">Status</th>
                            <th style="text-align: left; padding: 10px; border-bottom: 1px solid #ddd;">Added On</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($client = $recent_clients->fetch_assoc()): ?>
                            <tr>
                                <td style="padding: 10px; border-bottom: 1px solid #eee;"><?php echo htmlspecialchars($client['fullname']); ?></td>
                                <td style="padding: 10px; border-bottom: 1px solid #eee;"><?php echo htmlspecialchars($client['email']); ?></td>
                                <td style="padding: 10px; border-bottom: 1px solid #eee;">
                                    <?php
                                    switch($client['status']) {
                                        case 'active':
                                            echo '<span style="color: #2ecc71; font-weight: bold;">Active</span>';
                                            break;
                                        case 'pending':
                                            echo '<span style="color: #f39c12; font-weight: bold;">Pending</span>';
                                            break;
                                        case 'inactive':
                                            echo '<span style="color: #95a5a6; font-weight: bold;">Inactive</span>';
                                            break;
                                        default:
                                            echo htmlspecialchars($client['status']);
                                    }
                                    ?>
                                </td>
                                <td style="padding: 10px; border-bottom: 1px solid #eee;"><?php echo date('M d, Y', strtotime($client['created_at'])); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                
                <a href="manage_clients.php" class="action-button" style="display: inline-block; margin-top: 20px; text-decoration: none;">
                    <i class="fas fa-users"></i> View All Clients
                </a>
            </div>
        </div>
        <?php endif; ?>
        
        <footer style="text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; color: #7f8c8d;">
            <p>&copy; 2025 Mental Health Support. All rights reserved.</p>
            <p style="font-size: 14px; margin-top: 5px;">
                <a href="#" style="color: #7f8c8d; margin: 0 10px;">Privacy Policy</a> | 
                <a href="#" style="color: #7f8c8d; margin: 0 10px;">Terms of Service</a> | 
                <a href="#" style="color: #7f8c8d; margin: 0 10px;">Contact Us</a>
            </p>
        </footer>
    </div>
</body>
</html>