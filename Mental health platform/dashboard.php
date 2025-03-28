<?php
// Error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.html");
    exit();
}

// Include database connection to fetch user data
include 'db_connect.php';

// Fetch user information
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

// Get user's most recent mood (if any)
$mood_stmt = $conn->prepare("SELECT mood_type, created_at FROM mood_logs 
                            WHERE user_id = ? 
                            ORDER BY created_at DESC LIMIT 1");
$mood_stmt->bind_param("i", $user_id);
$mood_stmt->execute();
$mood_result = $mood_stmt->get_result();
$recent_mood = $mood_result->fetch_assoc();

// Count total mood entries for the user
$count_stmt = $conn->prepare("SELECT COUNT(*) as total FROM mood_logs WHERE user_id = ?");
$count_stmt->bind_param("i", $user_id);
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$mood_count = $count_result->fetch_assoc()['total'];

// Get upcoming appointments
$appointments_stmt = $conn->prepare("SELECT COUNT(*) as count FROM appointments 
                                   WHERE client_id = ? 
                                   AND status = 'scheduled' 
                                   AND appointment_date >= CURRENT_DATE");
$appointments_stmt->bind_param("i", $user_id);
$appointments_stmt->execute();
$appointment_count = $appointments_stmt->get_result()->fetch_assoc()['count'];

// Get count of assigned professionals
$prof_stmt = $conn->prepare("SELECT COUNT(*) as count FROM professional_clients 
                           WHERE client_id = ? AND status = 'active'");
$prof_stmt->bind_param("i", $user_id);
$prof_stmt->execute();
$professional_count = $prof_stmt->get_result()->fetch_assoc()['count'];
// Get unread message count
$unread_stmt = $conn->prepare("SELECT COUNT(*) as count FROM chat_messages WHERE receiver_id = ? AND is_read = 0");
$unread_stmt->bind_param("i", $user_id);
$unread_stmt->execute();
$unread_result = $unread_stmt->get_result();
$unread_count = $unread_result->num_rows > 0 ? $unread_result->fetch_assoc()['count'] : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mental Health Support - Dashboard</title>
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
        }
        .feature-card:hover {
            border-color: #3498db;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>
<body style="background-color: #f5f7fa; align-items: flex-start; padding-top: 30px;">
    <div class="dashboard-container">
        <div class="welcome-header">
            <div>
                <h1><i class="fas fa-brain" style="color: #3498db;"></i> Mental Health Dashboard</h1>
                <p>Welcome back, <?php echo htmlspecialchars($_SESSION['fullname']); ?>!</p>
            </div>
            <a href="logout.php" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
        
        <div class="user-info card">
            <h2><i class="fas fa-user-circle"></i> Your Profile</h2>
            <div style="display: flex; flex-wrap: wrap; gap: 20px; margin-top: 15px;">
                <div style="flex: 1; min-width: 200px;">
                    <p><strong><i class="fas fa-id-card"></i> Name:</strong><br> <?php echo htmlspecialchars($user['fullname']); ?></p>
                    <p><strong><i class="fas fa-envelope"></i> Email:</strong><br> <?php echo htmlspecialchars($user['email']); ?></p>
                </div>
                <div style="flex: 1; min-width: 200px;">
                    <p><strong><i class="fas fa-calendar-alt"></i> Member Since:</strong><br> 
                       <?php echo isset($user['created_at']) ? date('F j, Y', strtotime($user['created_at'])) : 'Not available'; ?>
                    </p>
                    <p><strong><i class="fas fa-chart-line"></i> Mood Entries:</strong><br> <?php echo $mood_count; ?> logs recorded</p>
                </div>
            </div>
        </div>
        
        <!-- Stats Overview -->
        <div class="card">
            <h2><i class="fas fa-chart-line"></i> Your Stats</h2>
            <div class="stats-container">
                <a href="track_mood.php" class="stat-card" style="text-decoration: none; color: inherit;">
                    <i class="fas fa-heartbeat" style="font-size: 24px; color: #3498db;"></i>
                    <div class="stat-number"><?php echo $mood_count; ?></div>
                    <div>Mood Entries</div>
                </a>
                <a href="manage_appointments.php" class="stat-card" style="text-decoration: none; color: inherit;">
                    <i class="fas fa-calendar-check" style="font-size: 24px; color: #3498db;"></i>
                    <div class="stat-number"><?php echo $appointment_count; ?></div>
                    <div>Upcoming Sessions</div>
                </a>
                <a href="resources.php" class="stat-card" style="text-decoration: none; color: inherit;">
                    <i class="fas fa-book-medical" style="font-size: 24px; color: #3498db;"></i>
                    <div class="stat-number">
                        <?php 
                        // Count published resources
                        $resources_query = $conn->query("SELECT COUNT(*) as count FROM mental_health_resources WHERE is_published = 1");
                        echo $resources_query->fetch_assoc()['count']; 
                        ?>
                    </div>
                    <div>Available Resources</div>
                </a>
                <a href="my_professionals.php" class="stat-card" style="text-decoration: none; color: inherit;">
                    <i class="fas fa-user-md" style="font-size: 24px; color: #3498db;"></i>
                    <div class="stat-number"><?php echo $professional_count; ?></div>
                    <div>Your Professionals</div>
                </a>
            </div>
        </div>
        
        <div class="card">
            <h2><i class="fas fa-heartbeat"></i> Wellness Check</h2>
            
            <?php if($recent_mood): ?>
                <p>Your last recorded mood: 
                    <strong>
                        <?php 
                        switch($recent_mood['mood_type']) {
                            case 'Great':
                                echo '<span style="color: #3498db;"><i class="fas fa-smile"></i> Great</span>';
                                break;
                            case 'Good':
                                echo '<span style="color: #2ecc71;"><i class="fas fa-meh"></i> Good</span>';
                                break;
                            case 'Not Great':
                                echo '<span style="color: #f39c12;"><i class="fas fa-frown"></i> Not Great</span>';
                                break;
                            case 'Struggling':
                                echo '<span style="color: #e74c3c;"><i class="fas fa-sad-tear"></i> Struggling</span>';
                                break;
                            default:
                                echo htmlspecialchars($recent_mood['mood_type']);
                        }
                        ?>
                    </strong> 
                    on <?php echo date('F j, Y', strtotime($recent_mood['created_at'])); ?>
                </p>
            <?php else: ?>
                <p>How are you feeling today? Tracking your mood can help identify patterns and triggers.</p>
            <?php endif; ?>
            
            <div style="margin: 20px 0;">
                <a href="track_mood.php" class="action-button" style="display: inline-block; text-decoration: none;">
                    <i class="fas fa-plus-circle"></i> Record Your Mood
                </a>
            </div>
            
            <p>Regularly tracking your mood helps you and mental health professionals understand your well-being patterns.</p>
        </div>
        
        <!-- Feature Cards -->
        <div class="card">
            <h2><i class="fas fa-tools"></i> Your Tools</h2>
            <p>Access tools and resources to support your mental health journey.</p>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px; margin-top: 20px;">
                <a href="track_mood.php" class="feature-card" style="text-decoration: none; color: inherit;">
                    <h3><i class="fas fa-chart-line" style="color: #3498db;"></i> Mood Tracking</h3>
                    <p>Track your moods and emotions over time to identify patterns and triggers.</p>
                    <div style="margin-top: 15px;">
                        <span class="action-button secondary" style="padding: 5px 10px; font-size: 0.9rem;">
                            Track Now
                        </span>
                    </div>
                </a>
                

<a href="available_professionals.php" class="feature-card" style="text-decoration: none; color: inherit;">
    <h3><i class="fas fa-user-md" style="color: #3498db;"></i> Find Professionals</h3>
    <p>Browse and connect with verified mental health professionals.</p>
    <div style="margin-top: 15px;">
        <span class="action-button secondary" style="padding: 5px 10px; font-size: 0.9rem;">
            Browse Professionals
        </span>
    </div>
</a>
                
                <a href="resources.php" class="feature-card" style="text-decoration: none; color: inherit;">
                    <h3><i class="fas fa-book-medical" style="color: #3498db;"></i> Resource Library</h3>
                    <p>Access a collection of mental health resources created by professionals.</p>
                    <div style="margin-top: 15px;">
                        <span class="action-button secondary" style="padding: 5px 10px; font-size: 0.9rem;">
                            Browse Resources
                        </span>
                    </div>
                </a>
                
                <a href="manage_appointments.php" class="feature-card" style="text-decoration: none; color: inherit;">
                    <h3><i class="fas fa-calendar-alt" style="color: #3498db;"></i> Appointments</h3>
                    <p>Schedule and manage appointments with mental health professionals.</p>
                    <div style="margin-top: 15px;">
                        <span class="action-button secondary" style="padding: 5px 10px; font-size: 0.9rem;">
                            <?php echo $appointment_count > 0 ? 'View Appointments' : 'Schedule Session'; ?>
                        </span>
                    </div>
                </a>
                <a href="chat.php" class="feature-card" style="text-decoration: none; color: inherit;">
    <h3>
        <i class="fas fa-comments" style="color: #3498db;"></i> 
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
    <p>Chat with your mental health professionals securely.</p>
    <div style="margin-top: 15px;">
        <span class="action-button secondary" style="padding: 5px 10px; font-size: 0.9rem;">Open Chat</span>
    </div>
</a>
                
               
            </div>
        </div>
        
        <!-- Upcoming Appointments -->
        <?php
        // Get upcoming appointments
        $upcoming_query = $conn->prepare("SELECT a.*, u.fullname as professional_name 
                                        FROM appointments a
                                        JOIN users u ON a.professional_id = u.id
                                        WHERE a.client_id = ?
                                        AND a.status = 'scheduled'
                                        AND a.appointment_date >= CURRENT_DATE
                                        ORDER BY a.appointment_date, a.appointment_time
                                        LIMIT 3");
        $upcoming_query->bind_param("i", $user_id);
        $upcoming_query->execute();
        $upcoming_result = $upcoming_query->get_result();
        
        if($upcoming_result->num_rows > 0):
        ?>
        <div class="card">
            <h2><i class="fas fa-calendar"></i> Upcoming Appointments</h2>
            <div style="margin-top: 20px;">
                <?php while($appointment = $upcoming_result->fetch_assoc()): ?>
                    <div style="border: 1px solid #eee; border-radius: 8px; padding: 15px; margin-bottom: 15px; background-color: white;">
                        <h3><?php echo date('l, F j, Y', strtotime($appointment['appointment_date'])); ?> at <?php echo date('h:i A', strtotime($appointment['appointment_time'])); ?></h3>
                        <p><strong>Professional:</strong> <?php echo htmlspecialchars($appointment['professional_name']); ?></p>
                        <p><strong>Duration:</strong> <?php echo $appointment['duration']; ?> minutes</p>
                        
                        <?php if(strpos($appointment['notes'], '[PRIVATE]') === false && !empty($appointment['notes'])): ?>
                            <div style="margin-top: 10px; padding-top: 10px; border-top: 1px solid #eee;">
                                <p><strong>Notes:</strong> <?php echo nl2br(htmlspecialchars($appointment['notes'])); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endwhile; ?>
                
                <a href="manage_appointments.php" class="action-button" style="display: inline-block; margin-top: 10px; text-decoration: none;">
                    <i class="fas fa-calendar-alt"></i> View All Appointments
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