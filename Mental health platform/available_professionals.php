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

// Handle connect request
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'connect') {
    $professional_id = intval($_POST['professional_id']);
    
    // Check if connection already exists
    $check_connection = $conn->prepare("SELECT * FROM professional_clients 
                                      WHERE professional_id = ? AND client_id = ?");
    $check_connection->bind_param("ii", $professional_id, $user_id);
    $check_connection->execute();
    $result = $check_connection->get_result();
    
    if ($result->num_rows > 0) {
        $connection = $result->fetch_assoc();
        if ($connection['status'] == 'pending' || $connection['status'] == 'active') {
            $error = "You've already requested a connection with this professional.";
        } else if ($connection['status'] == 'inactive') {
            // Update inactive connection to pending
            $update_connection = $conn->prepare("UPDATE professional_clients 
                                              SET status = 'pending' 
                                              WHERE professional_id = ? AND client_id = ?");
            $update_connection->bind_param("ii", $professional_id, $user_id);
            
            if ($update_connection->execute()) {
                $message = "Connection request sent! The professional will review your request.";
            } else {
                $error = "Error sending connection request: " . $conn->error;
            }
        }
    } else {
        // Create new connection
        $create_connection = $conn->prepare("INSERT INTO professional_clients 
                                          (professional_id, client_id, status) 
                                          VALUES (?, ?, 'pending')");
        $create_connection->bind_param("ii", $professional_id, $user_id);
        
        if ($create_connection->execute()) {
            $message = "Connection request sent! The professional will review your request.";
        } else {
            $error = "Error sending connection request: " . $conn->error;
        }
    }
}

// Get filter parameters
$specialty_filter = isset($_GET['specialty']) ? $_GET['specialty'] : '';
$search_term = isset($_GET['search']) ? $_GET['search'] : '';

// Get available professionals
$query = "SELECT u.id, u.fullname, u.email, pp.specialty, pp.education, pp.experience, pp.verified,
         (SELECT status FROM professional_clients 
          WHERE professional_id = u.id AND client_id = ? LIMIT 1) as connection_status
         FROM users u
         JOIN professional_profiles pp ON u.id = pp.user_id
         WHERE u.role = 'professional'";

$params = [$user_id];
$param_types = "i";

// Apply specialty filter
if (!empty($specialty_filter)) {
    $query .= " AND pp.specialty LIKE ?";
    $params[] = "%$specialty_filter%";
    $param_types .= "s";
}

// Apply search filter
if (!empty($search_term)) {
    $query .= " AND (u.fullname LIKE ? OR pp.specialty LIKE ?)";
    $params[] = "%$search_term%";
    $params[] = "%$search_term%";
    $param_types .= "ss";
}

// Add sorting - verified professionals first, then by name
$query .= " ORDER BY pp.verified DESC, u.fullname ASC";

$stmt = $conn->prepare($query);
$stmt->bind_param($param_types, ...$params);
$stmt->execute();
$professionals_result = $stmt->get_result();

// Get list of specialties for filtering
$specialties_query = $conn->query("SELECT DISTINCT specialty FROM professional_profiles WHERE specialty IS NOT NULL AND specialty != ''");
$specialties = [];
while ($specialty = $specialties_query->fetch_assoc()) {
    if (!empty($specialty['specialty'])) {
        $specialties[] = $specialty['specialty'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Available Professionals - Mental Health Support</title>
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
        .search-box {
            flex: 1;
            max-width: 500px;
            display: flex;
            gap: 5px;
        }
        .search-box input {
            flex: 1;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        .professional-card {
            border: 1px solid #eee;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            background-color: white;
            transition: all 0.3s ease;
        }
        .professional-card:hover {
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        .professional-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }
        .verification-badge {
            display: inline-block;
            background-color: #2ecc71;
            color: white;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 0.8rem;
            margin-left: 10px;
        }
        .unverified-badge {
            background-color: #f39c12;
        }
        .specialty-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin: 10px 0;
        }
        .specialty-tag {
            background-color: #f1f1f1;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.8rem;
            cursor: pointer;
        }
        .specialty-tag:hover {
            background-color: #e0e0e0;
        }
        .connection-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 0.8rem;
            color: white;
            margin-left: 10px;
        }
        .connection-badge.active {
            background-color: #2ecc71;
        }
        .connection-badge.pending {
            background-color: #f39c12;
        }
        .connection-badge.inactive {
            background-color: #95a5a6;
        }
    </style>
</head>
<body style="background-color: #f5f7fa; align-items: flex-start; padding-top: 30px;">
    <div class="dashboard-container">
        <div class="welcome-header">
            <div>
                <h1><i class="fas fa-user-md" style="color: #3498db;"></i> Available Professionals</h1>
                <p>Find and connect with mental health professionals</p>
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
           
            
            <?php if(count($specialties) > 0): ?>
                <div class="specialty-tags">
                    <strong style="margin-right: 10px;">Filter by specialty:</strong>
                    <?php foreach($specialties as $specialty): ?>
                        <a href="available_professionals.php?specialty=<?php echo urlencode($specialty); ?><?php echo !empty($search_term) ? '&search='.urlencode($search_term) : ''; ?>" 
                           class="specialty-tag" style="text-decoration: none; color: inherit;">
                            <?php echo htmlspecialchars($specialty); ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <h2><i class="fas fa-user-md"></i> Mental Health Professionals</h2>
            
            <?php if($professionals_result->num_rows > 0): ?>
                <?php while($professional = $professionals_result->fetch_assoc()): ?>
                    <div class="professional-card">
                        <div class="professional-header">
                            <div>
                                <h3>
                                    <?php echo htmlspecialchars($professional['fullname']); ?>
                                    
                                    <?php if($professional['verified']): ?>
                                        <span class="verification-badge">
                                            <i class="fas fa-check"></i> Verified
                                        </span>
                                    <?php else: ?>
                                        <span class="verification-badge unverified-badge">
                                            <i class="fas fa-exclamation-circle"></i> Pending Verification
                                        </span>
                                    <?php endif; ?>
                                    
                                    <?php if($professional['connection_status']): ?>
                                        <span class="connection-badge <?php echo $professional['connection_status']; ?>">
                                            <?php echo ucfirst($professional['connection_status']); ?>
                                        </span>
                                    <?php endif; ?>
                                </h3>
                                
                                <?php if(!empty($professional['specialty'])): ?>
                                    <p><strong>Specialty:</strong> <?php echo htmlspecialchars($professional['specialty']); ?></p>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Connection Button -->
                            <div>
                                <?php if($professional['connection_status'] == 'active'): ?>
                                    <span class="action-button secondary" style="cursor: default; opacity: 0.7;">
                                        <i class="fas fa-link"></i> Connected
                                    </span>
                                <?php elseif($professional['connection_status'] == 'pending'): ?>
                                    <span class="action-button secondary" style="cursor: default; opacity: 0.7;">
                                        <i class="fas fa-clock"></i> Request Pending
                                    </span>
                                <?php else: ?>
                                    <form action="available_professionals.php" method="POST">
                                        <input type="hidden" name="action" value="connect">
                                        <input type="hidden" name="professional_id" value="<?php echo $professional['id']; ?>">
                                        <button type="submit" class="action-button">
                                            <i class="fas fa-user-plus"></i> Request Connection
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px; margin-top: 15px;">
                            <?php if(!empty($professional['education'])): ?>
                                <div>
                                    <h4><i class="fas fa-graduation-cap"></i> Education</h4>
                                    <p><?php echo nl2br(htmlspecialchars($professional['education'])); ?></p>
                                </div>
                            <?php endif; ?>
                            
                            <?php if(!empty($professional['experience'])): ?>
                                <div>
                                    <h4><i class="fas fa-briefcase"></i> Experience</h4>
                                    <p><?php echo nl2br(htmlspecialchars($professional['experience'])); ?></p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div style="text-align: center; padding: 40px 0;">
                    <i class="fas fa-search" style="font-size: 48px; color: #bdc3c7; margin-bottom: 20px;"></i>
                    <h3>No professionals found</h3>
                    <p>Try adjusting your search criteria or check back later.</p>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="card" style="margin-top: 20px;">
            <h3><i class="fas fa-info-circle"></i> About Professional Connections</h3>
            <p>When you send a connection request:</p>
            <ul style="margin-left: 20px; margin-top: 10px;">
                <li>The professional will review your request</li>
                <li>Once approved, you'll be able to schedule appointments</li>
                <li>You'll gain access to resources shared by the professional</li>
                <li>You can message them directly for support</li>
            </ul>
            <p style="margin-top: 15px;"><strong>Note:</strong> Only verified professionals can accept connection requests.</p>
        </div>
        
        <footer style="text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; color: #7f8c8d;">
            <p>&copy; 2025 Mental Health Support. All rights reserved.</p>
        </footer>
    </div>
</body>
</html>