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

// Fetch current professional data
$stmt = $conn->prepare("SELECT u.*, pp.specialty, pp.license_number, pp.education, pp.experience, pp.verified 
                      FROM users u 
                      LEFT JOIN professional_profiles pp ON u.id = pp.user_id 
                      WHERE u.id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$professional = $result->fetch_assoc();

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get form data
    $fullname = $_POST['fullname'];
    $specialty = $_POST['specialty'];
    $license_number = $_POST['license_number'];
    $education = $_POST['education'];
    $experience = $_POST['experience'];
    
    // Begin transaction
    $conn->begin_transaction();
    
    try {
        // Update users table
        $update_user = $conn->prepare("UPDATE users SET fullname = ? WHERE id = ?");
        $update_user->bind_param("si", $fullname, $user_id);
        $update_user->execute();
        
        // Update professional_profiles table
        $update_profile = $conn->prepare("UPDATE professional_profiles 
                                        SET specialty = ?, license_number = ?, education = ?, experience = ? 
                                        WHERE user_id = ?");
        $update_profile->bind_param("ssssi", $specialty, $license_number, $education, $experience, $user_id);
        $update_profile->execute();
        
        // Commit transaction
        $conn->commit();
        
        // Update session and success message
        $_SESSION['fullname'] = $fullname;
        $message = "Your profile has been updated successfully!";
        
        // Refresh professional data
        $stmt->execute();
        $result = $stmt->get_result();
        $professional = $result->fetch_assoc();
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        $error = "Error updating profile: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Professional Profile - Mental Health Support</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
</head>
<body style="background-color: #f5f7fa; align-items: flex-start; padding-top: 30px;">
    <div class="dashboard-container">
        <div class="welcome-header">
            <div>
                <h1><i class="fas fa-user-md" style="color: #3498db;"></i> Professional Profile</h1>
                <p>Manage your professional information</p>
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
            <h2><i class="fas fa-edit"></i> Edit Your Profile</h2>
            <p>Complete your professional profile to help users find and trust your expertise.</p>
            
            <form action="professional_profile.php" method="POST">
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px; margin-top: 20px;">
                    <!-- Personal Information -->
                    <div>
                        <h3><i class="fas fa-user"></i> Personal Information</h3>
                        
                        <div class="form-group" style="margin-bottom: 15px;">
                            <label for="fullname" style="display: block; margin-bottom: 5px; font-weight: 600;">Full Name:</label>
                            <input type="text" id="fullname" name="fullname" value="<?php echo htmlspecialchars($professional['fullname']); ?>" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
                        </div>
                        
                        <div class="form-group" style="margin-bottom: 15px;">
                            <label for="email" style="display: block; margin-bottom: 5px; font-weight: 600;">Email:</label>
                            <input type="email" id="email" value="<?php echo htmlspecialchars($professional['email']); ?>" disabled style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; background-color: #f9f9f9;">
                            <small style="display: block; margin-top: 5px; color: #6c757d;">Email cannot be changed.</small>
                        </div>
                    </div>
                    
                    <!-- Professional Information -->
                    <div>
                        <h3><i class="fas fa-stethoscope"></i> Professional Information</h3>
                        
                        <div class="form-group" style="margin-bottom: 15px;">
                            <label for="specialty" style="display: block; margin-bottom: 5px; font-weight: 600;">Specialty:</label>
                            <input type="text" id="specialty" name="specialty" value="<?php echo htmlspecialchars($professional['specialty'] ?? ''); ?>" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
                        </div>
                        
                        <div class="form-group" style="margin-bottom: 15px;">
                            <label for="license_number" style="display: block; margin-bottom: 5px; font-weight: 600;">License Number:</label>
                            <input type="text" id="license_number" name="license_number" value="<?php echo htmlspecialchars($professional['license_number'] ?? ''); ?>" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
                        </div>
                    </div>
                </div>
                
                <!-- Education and Experience -->
                <div style="margin-top: 20px;">
                    <h3><i class="fas fa-graduation-cap"></i> Education and Experience</h3>
                    
                    <div class="form-group" style="margin-bottom: 15px;">
                        <label for="education" style="display: block; margin-bottom: 5px; font-weight: 600;">Education:</label>
                        <textarea id="education" name="education" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; min-height: 100px;" placeholder="Enter your educational background (degrees, institutions, etc.)"><?php echo htmlspecialchars($professional['education'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="form-group" style="margin-bottom: 15px;">
                        <label for="experience" style="display: block; margin-bottom: 5px; font-weight: 600;">Experience:</label>
                        <textarea id="experience" name="experience" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; min-height: 100px;" placeholder="Describe your professional experience"><?php echo htmlspecialchars($professional['experience'] ?? ''); ?></textarea>
                    </div>
                </div>
                
                <button type="submit" class="action-button" style="margin-top: 20px;">
                    <i class="fas fa-save"></i> Save Profile
                </button>
            </form>
        </div>
        
        <footer style="text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; color: #7f8c8d;">
            <p>&copy; 2025 Mental Health Support. All rights reserved.</p>
        </footer>
    </div>
</body>
</html>