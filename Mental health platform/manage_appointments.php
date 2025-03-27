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

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];
$message = "";
$error = "";

// Create appointments table if it doesn't exist
$create_table = "CREATE TABLE IF NOT EXISTS appointments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    professional_id INT NOT NULL,
    client_id INT NOT NULL,
    appointment_date DATE NOT NULL,
    appointment_time TIME NOT NULL,
    duration INT NOT NULL DEFAULT 60,
    status ENUM('scheduled', 'completed', 'cancelled') NOT NULL DEFAULT 'scheduled',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (professional_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (client_id) REFERENCES users(id) ON DELETE CASCADE
)";
$conn->query($create_table);

// Handle appointment actions
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['action'])) {
        // Schedule new appointment
        if ($_POST['action'] == 'schedule') {
            $client_id = isset($_POST['client_id']) ? intval($_POST['client_id']) : 0;
            $professional_id = isset($_POST['professional_id']) ? intval($_POST['professional_id']) : 0;
            $appointment_date = $_POST['appointment_date'];
            $appointment_time = $_POST['appointment_time'];
            $duration = isset($_POST['duration']) ? intval($_POST['duration']) : 60;
            $notes = isset($_POST['notes']) ? $_POST['notes'] : '';
            
            // Determine client and professional based on user role
            if ($user_role == 'professional') {
                $professional_id = $user_id;
                
                // Verify the client exists and is a user
                $check_client = $conn->prepare("SELECT id, role FROM users WHERE id = ?");
                $check_client->bind_param("i", $client_id);
                $check_client->execute();
                $client_result = $check_client->get_result();
                
                if ($client_result->num_rows == 0 || $client_result->fetch_assoc()['role'] != 'user') {
                    $error = "Invalid client selected.";
                    goto skip_appointment_creation;
                }
            } else {
                $client_id = $user_id;
                
                // Verify the professional exists and is a professional
                $check_prof = $conn->prepare("SELECT id, role FROM users WHERE id = ?");
                $check_prof->bind_param("i", $professional_id);
                $check_prof->execute();
                $prof_result = $check_prof->get_result();
                
                if ($prof_result->num_rows == 0 || $prof_result->fetch_assoc()['role'] != 'professional') {
                    $error = "Invalid professional selected.";
                    goto skip_appointment_creation;
                }
            }
            
            // Validate date and time (must be in the future)
            $appointment_datetime = $appointment_date . ' ' . $appointment_time;
            if (strtotime($appointment_datetime) <= time()) {
                $error = "Appointment date and time must be in the future.";
                goto skip_appointment_creation;
            }
            
            // Check for conflicts
            $check_conflicts = $conn->prepare("SELECT id FROM appointments 
                                             WHERE professional_id = ? 
                                             AND appointment_date = ? 
                                             AND appointment_time = ? 
                                             AND status = 'scheduled'");
            $check_conflicts->bind_param("iss", $professional_id, $appointment_date, $appointment_time);
            $check_conflicts->execute();
            $conflicts_result = $check_conflicts->get_result();
            
            if ($conflicts_result->num_rows > 0) {
                $error = "This time slot is already booked. Please select a different time.";
                goto skip_appointment_creation;
            }
            
            // Create the appointment
            $create_appointment = $conn->prepare("INSERT INTO appointments 
                                                (professional_id, client_id, appointment_date, appointment_time, duration, notes) 
                                                VALUES (?, ?, ?, ?, ?, ?)");
            $create_appointment->bind_param("iissss", $professional_id, $client_id, $appointment_date, $appointment_time, $duration, $notes);
            
            if ($create_appointment->execute()) {
                $message = "Appointment scheduled successfully!";
            } else {
                $error = "Error scheduling appointment: " . $conn->error;
            }
        }
        // Cancel appointment
        else if ($_POST['action'] == 'cancel') {
            $appointment_id = intval($_POST['appointment_id']);
            
            // Verify the appointment belongs to this user
            if ($user_role == 'professional') {
                $check_owner = $conn->prepare("SELECT id FROM appointments WHERE id = ? AND professional_id = ?");
                $check_owner->bind_param("ii", $appointment_id, $user_id);
            } else {
                $check_owner = $conn->prepare("SELECT id FROM appointments WHERE id = ? AND client_id = ?");
                $check_owner->bind_param("ii", $appointment_id, $user_id);
            }
            
            $check_owner->execute();
            $owner_result = $check_owner->get_result();
            
            if ($owner_result->num_rows > 0) {
                $cancel_appointment = $conn->prepare("UPDATE appointments SET status = 'cancelled' WHERE id = ?");
                $cancel_appointment->bind_param("i", $appointment_id);
                
                if ($cancel_appointment->execute()) {
                    $message = "Appointment cancelled successfully.";
                } else {
                    $error = "Error cancelling appointment: " . $conn->error;
                }
            } else {
                $error = "You don't have permission to cancel this appointment.";
            }
        }
        // Complete appointment
        else if ($_POST['action'] == 'complete' && $user_role == 'professional') {
            $appointment_id = intval($_POST['appointment_id']);
            
            // Verify the appointment belongs to this professional
            $check_owner = $conn->prepare("SELECT id FROM appointments WHERE id = ? AND professional_id = ?");
            $check_owner->bind_param("ii", $appointment_id, $user_id);
            $check_owner->execute();
            $owner_result = $check_owner->get_result();
            
            if ($owner_result->num_rows > 0) {
                $complete_appointment = $conn->prepare("UPDATE appointments SET status = 'completed' WHERE id = ?");
                $complete_appointment->bind_param("i", $appointment_id);
                
                if ($complete_appointment->execute()) {
                    $message = "Appointment marked as completed.";
                } else {
                    $error = "Error updating appointment: " . $conn->error;
                }
            } else {
                $error = "You don't have permission to update this appointment.";
            }
        }
        // Update appointment notes
        else if ($_POST['action'] == 'update_notes' && $user_role == 'professional') {
            $appointment_id = intval($_POST['appointment_id']);
            $notes = $_POST['notes'];
            
            // Verify the appointment belongs to this professional
            $check_owner = $conn->prepare("SELECT id FROM appointments WHERE id = ? AND professional_id = ?");
            $check_owner->bind_param("ii", $appointment_id, $user_id);
            $check_owner->execute();
            $owner_result = $check_owner->get_result();
            
            if ($owner_result->num_rows > 0) {
                $update_notes = $conn->prepare("UPDATE appointments SET notes = ? WHERE id = ?");
                $update_notes->bind_param("si", $notes, $appointment_id);
                
                if ($update_notes->execute()) {
                    $message = "Appointment notes updated successfully.";
                } else {
                    $error = "Error updating notes: " . $conn->error;
                }
            } else {
                $error = "You don't have permission to update this appointment.";
            }
        }
    }
}
skip_appointment_creation:

// Get appointments based on user role
if ($user_role == 'professional') {
    $appointments_query = "SELECT a.*, u.fullname as client_name
                         FROM appointments a
                         JOIN users u ON a.client_id = u.id
                         WHERE a.professional_id = ?
                         ORDER BY a.appointment_date, a.appointment_time";
    $appointments_stmt = $conn->prepare($appointments_query);
    $appointments_stmt->bind_param("i", $user_id);
} else {
    $appointments_query = "SELECT a.*, u.fullname as professional_name
                         FROM appointments a
                         JOIN users u ON a.professional_id = u.id
                         WHERE a.client_id = ?
                         ORDER BY a.appointment_date, a.appointment_time";
    $appointments_stmt = $conn->prepare($appointments_query);
    $appointments_stmt->bind_param("i", $user_id);
}

$appointments_stmt->execute();
$appointments_result = $appointments_stmt->get_result();

// Get clients list for professional
$clients = [];
if ($user_role == 'professional') {
    $clients_query = "SELECT pc.client_id, u.fullname
                    FROM professional_clients pc
                    JOIN users u ON pc.client_id = u.id
                    WHERE pc.professional_id = ? AND pc.status = 'active'
                    ORDER BY u.fullname";
    $clients_stmt = $conn->prepare($clients_query);
    $clients_stmt->bind_param("i", $user_id);
    $clients_stmt->execute();
    $clients_result = $clients_stmt->get_result();
    
    while ($row = $clients_result->fetch_assoc()) {
        $clients[$row['client_id']] = $row['fullname'];
    }
}

// Get professionals list for users
$professionals = [];
if ($user_role == 'user') {
    $professionals_query = "SELECT u.id, u.fullname
                          FROM users u
                          JOIN professional_clients pc ON u.id = pc.professional_id
                          WHERE pc.client_id = ? AND pc.status = 'active'
                          ORDER BY u.fullname";
    $professionals_stmt = $conn->prepare($professionals_query);
    $professionals_stmt->bind_param("i", $user_id);
    $professionals_stmt->execute();
    $professionals_result = $professionals_stmt->get_result();
    
    while ($row = $professionals_result->fetch_assoc()) {
        $professionals[$row['id']] = $row['fullname'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Appointments - Mental Health Support</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        .appointment-card {
            border: 1px solid #eee;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            position: relative;
        }
        .appointment-card.scheduled {
            border-left: 5px solid #3498db;
        }
        .appointment-card.completed {
            border-left: 5px solid #2ecc71;
        }
        .appointment-card.cancelled {
            border-left: 5px solid #e74c3c;
            opacity: 0.8;
        }
        .appointment-actions {
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
        .status-badge.scheduled {
            background-color: #3498db;
        }
        .status-badge.completed {
            background-color: #2ecc71;
        }
        .status-badge.cancelled {
            background-color: #e74c3c;
        }
        .appointment-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin: 15px 0;
        }
        .appointment-notes {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #eee;
        }
        .tab-container {
            margin-top: 20px;
        }
        .tab-buttons {
            display: flex;
            border-bottom: 1px solid #ddd;
            margin-bottom: 20px;
        }
        .tab-button {
            padding: 10px 20px;
            cursor: pointer;
            background: none;
            border: none;
            font-weight: 600;
            opacity: 0.7;
        }
        .tab-button.active {
            opacity: 1;
            border-bottom: 3px solid #3498db;
        }
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
        }
    </style>
</head>
<body style="background-color: #f5f7fa; align-items: flex-start; padding-top: 30px;">
    <div class="dashboard-container">
        <div class="welcome-header">
            <div>
                <h1><i class="fas fa-calendar-alt" style="color: #3498db;"></i> Appointment Management</h1>
                <p><?php echo $user_role == 'professional' ? 'Schedule and manage appointments with your clients' : 'Book and manage your appointments with mental health professionals'; ?></p>
            </div>
            <a href="<?php echo $user_role == 'professional' ? 'prof_dash.php' : 'dashboard.php'; ?>" class="action-button secondary" style="text-decoration: none;">
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
        
        <div class="card tab-container">
            <div class="tab-buttons">
                <button class="tab-button active" data-tab="upcoming">
                    <i class="fas fa-calendar-day"></i> Upcoming Appointments
                </button>
                <button class="tab-button" data-tab="schedule">
                    <i class="fas fa-plus-circle"></i> Schedule New Appointment
                </button>
                <button class="tab-button" data-tab="past">
                    <i class="fas fa-history"></i> Past Appointments
                </button>
            </div>
            
            <!-- Upcoming Appointments Tab -->
            <div id="upcoming" class="tab-content active">
                <h2>Upcoming Appointments</h2>
                
                <?php 
                $has_upcoming = false;
                $appointments_result->data_seek(0);
                while($appointment = $appointments_result->fetch_assoc()):
                    // Filter for upcoming appointments
                    $appointment_datetime = $appointment['appointment_date'] . ' ' . $appointment['appointment_time'];
                    if (strtotime($appointment_datetime) > time() && $appointment['status'] == 'scheduled'):
                        $has_upcoming = true;
                ?>
                    <div class="appointment-card scheduled">
                        <div class="appointment-actions">
                            <form action="manage_appointments.php" method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="cancel">
                                <input type="hidden" name="appointment_id" value="<?php echo $appointment['id']; ?>">
                                <button type="submit" class="action-button secondary" style="padding: 5px 10px; font-size: 0.9rem;" onclick="return confirm('Are you sure you want to cancel this appointment?');">
                                    <i class="fas fa-times"></i> Cancel
                                </button>
                            </form>
                            
                            <?php if($user_role == 'professional'): ?>
                                <form action="manage_appointments.php" method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="complete">
                                    <input type="hidden" name="appointment_id" value="<?php echo $appointment['id']; ?>">
                                    <button type="submit" class="action-button" style="padding: 5px 10px; font-size: 0.9rem;">
                                        <i class="fas fa-check"></i> Complete
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                        
                        <h3>
                            Appointment on <?php echo date('l, F j, Y', strtotime($appointment['appointment_date'])); ?>
                            <span class="status-badge scheduled">Scheduled</span>
                        </h3>
                        
                        <div class="appointment-meta">
                            <div>
                                <strong>Time:</strong> <?php echo date('h:i A', strtotime($appointment['appointment_time'])); ?>
                            </div>
                            <div>
                                <strong>Duration:</strong> <?php echo $appointment['duration']; ?> minutes
                            </div>
                            <div>
                                <strong><?php echo $user_role == 'professional' ? 'Client' : 'Professional'; ?>:</strong> 
                                <?php echo htmlspecialchars($user_role == 'professional' ? $appointment['client_name'] : $appointment['professional_name']); ?>
                            </div>
                        </div>
                        
                        <?php if($appointment['notes'] && ($user_role == 'professional' || ($user_role == 'user' && strpos($appointment['notes'], '[PRIVATE]') === false))): ?>
                            <div class="appointment-notes">
                                <strong>Notes:</strong>
                                <p><?php echo nl2br(htmlspecialchars(str_replace('[PRIVATE]', '', $appointment['notes']))); ?></p>
                            </div>
                        <?php endif; ?>
                        
                        <?php if($user_role == 'professional'): ?>
                            <button onclick="document.getElementById('edit-notes-<?php echo $appointment['id']; ?>').style.display = 'block';" class="action-button secondary" style="margin-top: 15px; padding: 5px 10px; font-size: 0.9rem;">
                                <i class="fas fa-edit"></i> Edit Notes
                            </button>
                            
                            <div id="edit-notes-<?php echo $appointment['id']; ?>" style="display: none; margin-top: 15px;">
                                <form action="manage_appointments.php" method="POST">
                                    <input type="hidden" name="action" value="update_notes">
                                    <input type="hidden" name="appointment_id" value="<?php echo $appointment['id']; ?>">
                                    
                                    <div style="margin-bottom: 10px;">
                                        <label for="notes-<?php echo $appointment['id']; ?>" style="display: block; margin-bottom: 5px; font-weight: 600;">
                                            Appointment Notes:
                                        </label>
                                        <textarea id="notes-<?php echo $appointment['id']; ?>" name="notes" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; min-height: 100px;"><?php echo htmlspecialchars($appointment['notes']); ?></textarea>
                                        <small style="color: #6c757d;">Add [PRIVATE] at the beginning of notes you don't want clients to see</small>
                                    </div>
                                    
                                    <div>
                                        <button type="submit" class="action-button" style="padding: 5px 10px; font-size: 0.9rem;">
                                            <i class="fas fa-save"></i> Save Notes
                                        </button>
                                        <button type="button" onclick="document.getElementById('edit-notes-<?php echo $appointment['id']; ?>').style.display = 'none';" class="action-button secondary" style="padding: 5px 10px; font-size: 0.9rem;">
                                            <i class="fas fa-times"></i> Cancel
                                        </button>
                                    </div>
                                </form>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php 
                    endif;
                endwhile;
                
                if (!$has_upcoming):
                ?>
                    <div style="text-align: center; padding: 30px 0;">
                        <i class="fas fa-calendar-times" style="font-size: 48px; color: #bdc3c7; margin-bottom: 20px;"></i>
                        <h3>No upcoming appointments</h3>
                        <p>You don't have any scheduled appointments coming up</p>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Schedule Appointment Tab -->
            <div id="schedule" class="tab-content">
                <h2>Schedule New Appointment</h2>
                
                <?php if(($user_role == 'professional' && empty($clients)) || ($user_role == 'user' && empty($professionals))): ?>
                    <div style="text-align: center; padding: 30px 0;">
                        <i class="fas fa-user-friends" style="font-size: 48px; color: #bdc3c7; margin-bottom: 20px;"></i>
                        <h3>No <?php echo $user_role == 'professional' ? 'clients' : 'professionals'; ?> available</h3>
                        <p>
                            <?php if($user_role == 'professional'): ?>
                                You need to add active clients before you can schedule appointments
                                <br><br>
                                <a href="manage_clients.php" class="action-button" style="text-decoration: none;">
                                    <i class="fas fa-user-plus"></i> Manage Clients
                                </a>
                            <?php else: ?>
                                You need to be connected with a mental health professional first
                            <?php endif; ?>
                        </p>
                    </div>
                <?php else: ?>
                    <form action="manage_appointments.php" method="POST" style="margin-top: 20px;">
                        <input type="hidden" name="action" value="schedule">
                        
                        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px; margin-bottom: 20px;">
                            <!-- Client/Professional Selection -->
                            <div class="form-group">
                                <label for="<?php echo $user_role == 'professional' ? 'client_id' : 'professional_id'; ?>" style="display: block; margin-bottom: 5px; font-weight: 600;">
                                    Select <?php echo $user_role == 'professional' ? 'Client' : 'Professional'; ?>:
                                </label>
                                <select id="<?php echo $user_role == 'professional' ? 'client_id' : 'professional_id'; ?>" name="<?php echo $user_role == 'professional' ? 'client_id' : 'professional_id'; ?>" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
                                    <option value="">-- Select <?php echo $user_role == 'professional' ? 'a client' : 'a professional'; ?> --</option>
                                    
                                    <?php if($user_role == 'professional'): ?>
                                        <?php foreach($clients as $id => $name): ?>
                                            <option value="<?php echo $id; ?>"><?php echo htmlspecialchars($name); ?></option>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <?php foreach($professionals as $id => $name): ?>
                                            <option value="<?php echo $id; ?>"><?php echo htmlspecialchars($name); ?></option>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </select>
                            </div>
                            
                            <!-- Date Selection -->
                            <div class="form-group">
                                <label for="appointment_date" style="display: block; margin-bottom: 5px; font-weight: 600;">
                                    Appointment Date:
                                </label>
                                <?php
                                    // Set minimum date to tomorrow
                                    $min_date = date('Y-m-d', strtotime('+1 day'));
                                ?>
                                <input type="date" id="appointment_date" name="appointment_date" min="<?php echo $min_date; ?>" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
                            </div>
                            
                            <!-- Time Selection -->
                            <div class="form-group">
                                <label for="appointment_time" style="display: block; margin-bottom:
                                <label for="appointment_time" style="display: block; margin-bottom: 5px; font-weight: 600;">
                                    Appointment Time:
                                </label>
                                <select id="appointment_time" name="appointment_time" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
                                    <option value="">-- Select a time --</option>
                                    <option value="09:00:00">9:00 AM</option>
                                    <option value="10:00:00">10:00 AM</option>
                                    <option value="11:00:00">11:00 AM</option>
                                    <option value="12:00:00">12:00 PM</option>
                                    <option value="13:00:00">1:00 PM</option>
                                    <option value="14:00:00">2:00 PM</option>
                                    <option value="15:00:00">3:00 PM</option>
                                    <option value="16:00:00">4:00 PM</option>
                                    <option value="17:00:00">5:00 PM</option>
                                </select>
                            </div>
                            
                            <!-- Duration Selection -->
                            <div class="form-group">
                                <label for="duration" style="display: block; margin-bottom: 5px; font-weight: 600;">
                                    Duration:
                                </label>
                                <select id="duration" name="duration" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
                                    <option value="30">30 minutes</option>
                                    <option value="60" selected>60 minutes</option>
                                    <option value="90">90 minutes</option>
                                    <option value="120">120 minutes</option>
                                </select>
                            </div>
                        </div>
                        
                        <!-- Notes Section -->
                        <div class="form-group" style="margin-bottom: 20px;">
                            <label for="notes" style="display: block; margin-bottom: 5px; font-weight: 600;">
                                Notes (optional):
                            </label>
                            <textarea id="notes" name="notes" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; min-height: 100px;" placeholder="Add any notes or specific topics to discuss during the appointment..."></textarea>
                            <?php if($user_role == 'professional'): ?>
                                <small style="color: #6c757d;">Add [PRIVATE] at the beginning of notes you don't want clients to see</small>
                            <?php endif; ?>
                        </div>
                        
                        <button type="submit" class="action-button">
                            <i class="fas fa-calendar-plus"></i> Schedule Appointment
                        </button>
                    </form>
                <?php endif; ?>
            </div>
            
            <!-- Past Appointments Tab -->
            <div id="past" class="tab-content">
                <h2>Past Appointments</h2>
                
                <?php 
                $has_past = false;
                $appointments_result->data_seek(0);
                while($appointment = $appointments_result->fetch_assoc()):
                    // Filter for past appointments
                    $appointment_datetime = $appointment['appointment_date'] . ' ' . $appointment['appointment_time'];
                    if (strtotime($appointment_datetime) <= time() || $appointment['status'] != 'scheduled'):
                        $has_past = true;
                ?>
                    <div class="appointment-card <?php echo $appointment['status']; ?>">
                        <h3>
                            Appointment on <?php echo date('l, F j, Y', strtotime($appointment['appointment_date'])); ?>
                            <span class="status-badge <?php echo $appointment['status']; ?>">
                                <?php echo ucfirst($appointment['status']); ?>
                            </span>
                        </h3>
                        
                        <div class="appointment-meta">
                            <div>
                                <strong>Time:</strong> <?php echo date('h:i A', strtotime($appointment['appointment_time'])); ?>
                            </div>
                            <div>
                                <strong>Duration:</strong> <?php echo $appointment['duration']; ?> minutes
                            </div>
                            <div>
                                <strong><?php echo $user_role == 'professional' ? 'Client' : 'Professional'; ?>:</strong> 
                                <?php echo htmlspecialchars($user_role == 'professional' ? $appointment['client_name'] : $appointment['professional_name']); ?>
                            </div>
                        </div>
                        
                        <?php if($appointment['notes'] && ($user_role == 'professional' || ($user_role == 'user' && strpos($appointment['notes'], '[PRIVATE]') === false))): ?>
                            <div class="appointment-notes">
                                <strong>Notes:</strong>
                                <p><?php echo nl2br(htmlspecialchars(str_replace('[PRIVATE]', '', $appointment['notes']))); ?></p>
                            </div>
                        <?php endif; ?>
                        
                        <?php if($user_role == 'professional' && $appointment['status'] == 'completed'): ?>
                            <button onclick="document.getElementById('edit-past-notes-<?php echo $appointment['id']; ?>').style.display = 'block';" class="action-button secondary" style="margin-top: 15px; padding: 5px 10px; font-size: 0.9rem;">
                                <i class="fas fa-edit"></i> Edit Notes
                            </button>
                            
                            <div id="edit-past-notes-<?php echo $appointment['id']; ?>" style="display: none; margin-top: 15px;">
                                <form action="manage_appointments.php" method="POST">
                                    <input type="hidden" name="action" value="update_notes">
                                    <input type="hidden" name="appointment_id" value="<?php echo $appointment['id']; ?>">
                                    
                                    <div style="margin-bottom: 10px;">
                                        <label for="past-notes-<?php echo $appointment['id']; ?>" style="display: block; margin-bottom: 5px; font-weight: 600;">
                                            Session Notes:
                                        </label>
                                        <textarea id="past-notes-<?php echo $appointment['id']; ?>" name="notes" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; min-height: 100px;"><?php echo htmlspecialchars($appointment['notes']); ?></textarea>
                                        <small style="color: #6c757d;">Add [PRIVATE] at the beginning of notes you don't want clients to see</small>
                                    </div>
                                    
                                    <div>
                                        <button type="submit" class="action-button" style="padding: 5px 10px; font-size: 0.9rem;">
                                            <i class="fas fa-save"></i> Save Notes
                                        </button>
                                        <button type="button" onclick="document.getElementById('edit-past-notes-<?php echo $appointment['id']; ?>').style.display = 'none';" class="action-button secondary" style="padding: 5px 10px; font-size: 0.9rem;">
                                            <i class="fas fa-times"></i> Cancel
                                        </button>
                                    </div>
                                </form>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php 
                    endif;
                endwhile;
                
                if (!$has_past):
                ?>
                    <div style="text-align: center; padding: 30px 0;">
                        <i class="fas fa-history" style="font-size: 48px; color: #bdc3c7; margin-bottom: 20px;"></i>
                        <h3>No past appointments</h3>
                        <p>You don't have any completed or cancelled appointments yet</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <footer style="text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; color: #7f8c8d;">
            <p>&copy; 2025 Mental Health Support. All rights reserved.</p>
        </footer>
    </div>
    
    <script>
        // Tab switching functionality
        document.addEventListener('DOMContentLoaded', function() {
            const tabButtons = document.querySelectorAll('.tab-button');
            const tabContents = document.querySelectorAll('.tab-content');
            
            tabButtons.forEach(button => {
                button.addEventListener('click', function() {
                    // Get the tab to show
                    const tabId = this.getAttribute('data-tab');
                    
                    // Remove active class from all buttons and contents
                    tabButtons.forEach(btn => btn.classList.remove('active'));
                    tabContents.forEach(content => content.classList.remove('active'));
                    
                    // Add active class to current button and content
                    this.classList.add('active');
                    document.getElementById(tabId).classList.add('active');
                });
            });
        });
    </script>
</body>
</html>