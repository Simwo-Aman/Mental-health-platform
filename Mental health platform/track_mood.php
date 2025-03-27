<?php
// Error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.html");
    exit();
}

include 'db_connect.php';

$message = "";
$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user_id = $_SESSION['user_id'];
    $mood_type = $_POST['mood_type'];
    $notes = isset($_POST['notes']) ? $_POST['notes'] : '';
    
    // Validate mood type
    $valid_moods = ['Great', 'Good', 'Not Great', 'Struggling'];
    if (in_array($mood_type, $valid_moods)) {
        // Insert mood into database
        $stmt = $conn->prepare("INSERT INTO mood_logs (user_id, mood_type, notes) VALUES (?, ?, ?)");
        $stmt->bind_param("iss", $user_id, $mood_type, $notes);
        
        if ($stmt->execute()) {
            $message = "Your mood has been logged successfully!";
        } else {
            $error = "Error logging mood: " . $conn->error;
        }
    } else {
        $error = "Invalid mood type selected.";
    }
}

// Get recent mood entries for this user
$user_id = $_SESSION['user_id'];
$mood_history = $conn->prepare("SELECT mood_type, notes, created_at FROM mood_logs 
                              WHERE user_id = ? 
                              ORDER BY created_at DESC LIMIT 10");
$mood_history->bind_param("i", $user_id);
$mood_history->execute();
$result = $mood_history->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Track Your Mood - Mental Health Support</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
</head>
<body style="background-color: #f5f7fa; align-items: flex-start; padding-top: 30px;">
    <div class="dashboard-container">
        <div class="welcome-header">
            <div>
                <h1><i class="fas fa-heartbeat" style="color: #3498db;"></i> Mood Tracker</h1>
                <p>Track how you're feeling to identify patterns and improve your mental health</p>
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
            <h2><i class="fas fa-plus-circle"></i> Log Your Current Mood</h2>
            <p>How are you feeling right now? Tracking regularly helps identify patterns.</p>
            
            <form action="track_mood.php" method="POST">
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 15px; margin: 20px 0;">
                    <div>
                        <input type="radio" id="mood_great" name="mood_type" value="Great" required>
                        <label for="mood_great" class="mood-option" style="display: flex; flex-direction: column; align-items: center; padding: 15px; border: 2px solid #eee; border-radius: 10px; cursor: pointer;">
                            <i class="fas fa-smile fa-3x" style="color: #3498db; margin-bottom: 10px;"></i>
                            <span>Great</span>
                        </label>
                    </div>
                    
                    <div>
                        <input type="radio" id="mood_good" name="mood_type" value="Good">
                        <label for="mood_good" class="mood-option" style="display: flex; flex-direction: column; align-items: center; padding: 15px; border: 2px solid #eee; border-radius: 10px; cursor: pointer;">
                            <i class="fas fa-meh fa-3x" style="color: #2ecc71; margin-bottom: 10px;"></i>
                            <span>Good</span>
                        </label>
                    </div>
                    
                    <div>
                        <input type="radio" id="mood_not_great" name="mood_type" value="Not Great">
                        <label for="mood_not_great" class="mood-option" style="display: flex; flex-direction: column; align-items: center; padding: 15px; border: 2px solid #eee; border-radius: 10px; cursor: pointer;">
                            <i class="fas fa-frown fa-3x" style="color: #f39c12; margin-bottom: 10px;"></i>
                            <span>Not Great</span>
                        </label>
                    </div>
                    
                    <div>
                        <input type="radio" id="mood_struggling" name="mood_type" value="Struggling">
                        <label for="mood_struggling" class="mood-option" style="display: flex; flex-direction: column; align-items: center; padding: 15px; border: 2px solid #eee; border-radius: 10px; cursor: pointer;">
                            <i class="fas fa-sad-tear fa-3x" style="color: #e74c3c; margin-bottom: 10px;"></i>
                            <span>Struggling</span>
                        </label>
                    </div>
                </div>
                
                <div style="margin: 20px 0;">
                    <label for="notes" style="display: block; margin-bottom: 10px; font-weight: 600;"><i class="fas fa-pen"></i> Notes (optional):</label>
                    <textarea id="notes" name="notes" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; min-height: 100px;" placeholder="Add any thoughts or feelings you'd like to record..."></textarea>
                </div>
                
                <button type="submit" class="action-button" style="margin-top: 10px;">
                    <i class="fas fa-save"></i> Save Mood
                </button>
            </form>
        </div>
        
        <div class="card">
            <h2><i class="fas fa-history"></i> Your Mood History</h2>
            <p>Review your recent mood entries to identify patterns.</p>
            
            <?php if($result->num_rows > 0): ?>
                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse; margin-top: 20px;">
                        <thead>
                            <tr>
                                <th style="text-align: left; padding: 10px; border-bottom: 1px solid #ddd;">Date & Time</th>
                                <th style="text-align: left; padding: 10px; border-bottom: 1px solid #ddd;">Mood</th>
                                <th style="text-align: left; padding: 10px; border-bottom: 1px solid #ddd;">Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($row = $result->fetch_assoc()): ?>
                                <tr>
                                    <td style="padding: 10px; border-bottom: 1px solid #eee;">
                                        <?php echo date('M d, Y - h:i A', strtotime($row['created_at'])); ?>
                                    </td>
                                    <td style="padding: 10px; border-bottom: 1px solid #eee;">
                                        <?php 
                                        switch($row['mood_type']) {
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
                                                echo htmlspecialchars($row['mood_type']);
                                        }
                                        ?>
                                    </td>
                                    <td style="padding: 10px; border-bottom: 1px solid #eee;">
                                        <?php echo nl2br(htmlspecialchars($row['notes'])); ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p>You haven't logged any moods yet. Start tracking above!</p>
            <?php endif; ?>
        </div>
        
        <footer style="text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; color: #7f8c8d;">
            <p>&copy; 2025 Mental Health Support. All rights reserved.</p>
        </footer>
    </div>
    
    <script>
        // Enhance the mood selection radio buttons
        document.addEventListener('DOMContentLoaded', function() {
            const moodOptions = document.querySelectorAll('.mood-option');
            const moodRadios = document.querySelectorAll('input[name="mood_type"]');
            
            moodOptions.forEach(option => {
                option.addEventListener('click', function() {
                    // Reset all borders
                    moodOptions.forEach(opt => {
                        opt.style.borderColor = '#eee';
                        opt.style.backgroundColor = 'white';
                    });
                    
                    // Highlight selected option
                    this.style.borderColor = '#3498db';
                    this.style.backgroundColor = '#f0f8ff';
                });
            });
            
            // Initialize with the selected option if any
            moodRadios.forEach(radio => {
                if (radio.checked) {
                    const label = document.querySelector(`label[for="${radio.id}"]`);
                    label.style.borderColor = '#3498db';
                    label.style.backgroundColor = '#f0f8ff';
                }
            });
        });
    </script>
</body>
</html>