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

// Get the selected chat partner ID from the URL, if any
$chat_partner_id = isset($_GET['partner_id']) ? intval($_GET['partner_id']) : 0;
$chat_partner_data = null;

// Function to get user display name
function getUserName($conn, $userId) {
    $stmt = $conn->prepare("SELECT fullname FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        return $result->fetch_assoc()['fullname'];
    }
    return "Unknown User";
}

// Check if we have a valid chat partner
if ($chat_partner_id > 0) {
    // Check if this is a valid chat relationship
    if ($user_role == 'professional') {
        $check_chat = $conn->prepare("SELECT cc.*, u.fullname, u.email 
                                     FROM chat_connections cc 
                                     JOIN users u ON cc.client_id = u.id 
                                     WHERE cc.professional_id = ? AND cc.client_id = ?");
        $check_chat->bind_param("ii", $user_id, $chat_partner_id);
    } else {
        $check_chat = $conn->prepare("SELECT cc.*, u.fullname, u.email 
                                     FROM chat_connections cc 
                                     JOIN users u ON cc.professional_id = u.id 
                                     WHERE cc.client_id = ? AND cc.professional_id = ?");
        $check_chat->bind_param("ii", $user_id, $chat_partner_id);
    }
    
    $check_chat->execute();
    $check_result = $check_chat->get_result();
    
    if ($check_result->num_rows == 0) {
        $error = "Invalid chat partner selected.";
        $chat_partner_id = 0;
    } else {
        $chat_partner_data = $check_result->fetch_assoc();
        
        // Mark all messages from this partner as read
        $mark_read = $conn->prepare("UPDATE chat_messages SET is_read = 1 
                                    WHERE sender_id = ? AND receiver_id = ?");
        $mark_read->bind_param("ii", $chat_partner_id, $user_id);
        $mark_read->execute();
    }
}

// Get available chat partners based on user role
if ($user_role == 'professional') {
    $partners_query = "SELECT cc.client_id as partner_id, u.fullname as partner_name, u.email as partner_email,
                       (SELECT COUNT(*) FROM chat_messages 
                        WHERE sender_id = cc.client_id AND receiver_id = ? AND is_read = 0) as unread_count,
                       (SELECT created_at FROM chat_messages 
                        WHERE (sender_id = cc.client_id AND receiver_id = ?) 
                           OR (sender_id = ? AND receiver_id = cc.client_id) 
                        ORDER BY created_at DESC LIMIT 1) as last_message_time
                       FROM chat_connections cc
                       JOIN users u ON cc.client_id = u.id
                       WHERE cc.professional_id = ?
                       ORDER BY last_message_time DESC, partner_name ASC";
    $partners_stmt = $conn->prepare($partners_query);
    $partners_stmt->bind_param("iiii", $user_id, $user_id, $user_id, $user_id);
} else {
    $partners_query = "SELECT cc.professional_id as partner_id, u.fullname as partner_name, u.email as partner_email,
                       (SELECT COUNT(*) FROM chat_messages 
                        WHERE sender_id = cc.professional_id AND receiver_id = ? AND is_read = 0) as unread_count,
                       (SELECT created_at FROM chat_messages 
                        WHERE (sender_id = cc.professional_id AND receiver_id = ?) 
                           OR (sender_id = ? AND receiver_id = cc.professional_id) 
                        ORDER BY created_at DESC LIMIT 1) as last_message_time
                       FROM chat_connections cc
                       JOIN users u ON cc.professional_id = u.id
                       WHERE cc.client_id = ?
                       ORDER BY last_message_time DESC, partner_name ASC";
    $partners_stmt = $conn->prepare($partners_query);
    $partners_stmt->bind_param("iiii", $user_id, $user_id, $user_id, $user_id);
}

$partners_stmt->execute();
$partners_result = $partners_stmt->get_result();

// Handle new message submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'send_message') {
    $receiver_id = intval($_POST['receiver_id']);
    $message_text = trim($_POST['message']);
    
    // Basic validation
    if (empty($message_text)) {
        $error = "Message cannot be empty.";
    } else {
        // Check if this is a valid chat connection
        if ($user_role == 'professional') {
            $check_connection = $conn->prepare("SELECT id FROM chat_connections 
                                               WHERE professional_id = ? AND client_id = ?");
            $check_connection->bind_param("ii", $user_id, $receiver_id);
        } else {
            $check_connection = $conn->prepare("SELECT id FROM chat_connections 
                                               WHERE client_id = ? AND professional_id = ?");
            $check_connection->bind_param("ii", $user_id, $receiver_id);
        }
        
        $check_connection->execute();
        $conn_result = $check_connection->get_result();
        
        if ($conn_result->num_rows > 0) {
            // Insert the message
            $insert_message = $conn->prepare("INSERT INTO chat_messages (sender_id, receiver_id, message) 
                                            VALUES (?, ?, ?)");
            $insert_message->bind_param("iis", $user_id, $receiver_id, $message_text);
            
            if ($insert_message->execute()) {
                // Update the last_message_time in chat_connections
                $update_time = $conn->prepare("UPDATE chat_connections 
                                              SET last_message_time = CURRENT_TIMESTAMP 
                                              WHERE (professional_id = ? AND client_id = ?) 
                                                 OR (professional_id = ? AND client_id = ?)");
                $update_time->bind_param("iiii", $user_id, $receiver_id, $receiver_id, $user_id);
                $update_time->execute();
                
                // Redirect to prevent form resubmission
                header("Location: chat.php?partner_id=" . $receiver_id);
                exit();
            } else {
                $error = "Error sending message: " . $conn->error;
            }
        } else {
            $error = "Invalid chat connection.";
        }
    }
}

// Get chat history with selected partner, if any
$messages = [];
if ($chat_partner_id > 0) {
    $get_messages = $conn->prepare("SELECT * FROM chat_messages 
                                  WHERE (sender_id = ? AND receiver_id = ?) 
                                     OR (sender_id = ? AND receiver_id = ?) 
                                  ORDER BY created_at ASC");
    $get_messages->bind_param("iiii", $user_id, $chat_partner_id, $chat_partner_id, $user_id);
    $get_messages->execute();
    $messages_result = $get_messages->get_result();
    
    while ($row = $messages_result->fetch_assoc()) {
        $messages[] = $row;
    }
}

// Get total unread message count for notification badge
$unread_stmt = $conn->prepare("SELECT COUNT(*) as count FROM chat_messages WHERE receiver_id = ? AND is_read = 0");
$unread_stmt->bind_param("i", $user_id);
$unread_stmt->execute();
$unread_count = $unread_stmt->get_result()->fetch_assoc()['count'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat - Mental Health Support</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        .chat-container {
            display: flex;
            height: 70vh;
            margin-top: 20px;
            border: 1px solid #eee;
            border-radius: 8px;
            overflow: hidden;
        }
        .contacts-list {
            width: 300px;
            background-color: #f8f9fa;
            border-right: 1px solid #eee;
            overflow-y: auto;
        }
        .chat-area {
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        .contact-item {
            padding: 15px;
            border-bottom: 1px solid #eee;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .contact-item:hover {
            background-color: #f0f8ff;
        }
        .contact-item.active {
            background-color: #e3f2fd;
            border-left: 4px solid #3498db;
        }
        .contact-name {
            font-weight: 600;
            margin-bottom: 5px;
        }
        .contact-status {
            font-size: 0.8rem;
            color: #7f8c8d;
        }
        .unread-badge {
            display: inline-block;
            background-color: #3498db;
            color: white;
            border-radius: 50%;
            padding: 2px 8px;
            font-size: 0.8rem;
            float: right;
        }
        .chat-header {
            padding: 15px;
            border-bottom: 1px solid #eee;
            background-color: #f8f9fa;
        }
        .chat-messages {
            flex: 1;
            padding: 15px;
            overflow-y: auto;
            background-color: #f9f9f9;
        }
        .chat-input {
            padding: 15px;
            border-top: 1px solid #eee;
            background-color: white;
        }
        .message {
            margin-bottom: 15px;
            max-width: 70%;
        }
        .message-content {
            padding: 10px 15px;
            border-radius: 18px;
            display: inline-block;
        }
        .message.sent {
            margin-left: auto;
            text-align: right;
        }
        .message.received {
            margin-right: auto;
            text-align: left;
        }
        .message.sent .message-content {
            background-color: #3498db;
            color: white;
            border-bottom-right-radius: 5px;
        }
        .message.received .message-content {
            background-color: #e9e9e9;
            color: #333;
            border-bottom-left-radius: 5px;
        }
        .message-time {
            font-size: 0.75rem;
            color: #7f8c8d;
            margin-top: 5px;
        }
        .message-form {
            display: flex;
            gap: 10px;
        }
        .message-input {
            flex: 1;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 20px;
            outline: none;
        }
        .send-button {
            background-color: #3498db;
            color: white;
            border: none;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            justify-content: center;
            align-items: center;
            cursor: pointer;
        }
        .no-chat-selected {
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            height: 100%;
            background-color: #f9f9f9;
            color: #7f8c8d;
        }
        .no-chat-selected i {
            font-size: 48px;
            margin-bottom: 20px;
            color: #bdc3c7;
        }
        .empty-list {
            padding: 20px;
            text-align: center;
            color: #7f8c8d;
        }
        .empty-list i {
            font-size: 48px;
            margin-bottom: 15px;
            color: #bdc3c7;
        }
    </style>
</head>
<body style="background-color: #f5f7fa; align-items: flex-start; padding-top: 30px;">
    <div class="dashboard-container">
        <div class="welcome-header">
            <div>
                <h1><i class="fas fa-comments" style="color: #3498db;"></i> Chat</h1>
                <p>
                    <?php echo $user_role == 'professional' 
                        ? 'Communicate with your clients securely' 
                        : 'Message your mental health professionals'; ?>
                </p>
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
        
        <div class="card">
            <h2>
                <i class="fas fa-comments"></i> 
                Messages 
                <?php if($unread_count > 0): ?>
                    <span class="unread-badge"><?php echo $unread_count; ?> new</span>
                <?php endif; ?>
            </h2>
            
            <div class="chat-container">
                <!-- Contacts List -->
                <div class="contacts-list">
                    <?php if($partners_result->num_rows > 0): ?>
                        <?php while($partner = $partners_result->fetch_assoc()): ?>
                            <div class="contact-item <?php echo $partner['partner_id'] == $chat_partner_id ? 'active' : ''; ?>"
                                 onclick="window.location.href='chat.php?partner_id=<?php echo $partner['partner_id']; ?>'">
                                <div class="contact-name">
                                    <?php echo htmlspecialchars($partner['partner_name']); ?>
                                    <?php if($partner['unread_count'] > 0): ?>
                                        <span class="unread-badge"><?php echo $partner['unread_count']; ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="contact-status">
                                    <?php 
                                    if($partner['last_message_time']) {
                                        $time_diff = time() - strtotime($partner['last_message_time']);
                                        if($time_diff < 60) {
                                            echo "Just now";
                                        } elseif($time_diff < 3600) {
                                            echo floor($time_diff / 60) . " mins ago";
                                        } elseif($time_diff < 86400) {
                                            echo floor($time_diff / 3600) . " hours ago";
                                        } else {
                                            echo date("M j", strtotime($partner['last_message_time']));
                                        }
                                    } else {
                                        echo "No messages yet";
                                    }
                                    ?>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="empty-list">
                            <i class="fas fa-user-friends"></i>
                            <h3>No chat connections</h3>
                            <p>
                                <?php if($user_role == 'professional'): ?>
                                    You need to add clients first
                                <?php else: ?>
                                    No professionals have added you yet
                                <?php endif; ?>
                            </p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Chat Area -->
                <div class="chat-area">
                    <?php if($chat_partner_id > 0 && $chat_partner_data): ?>
                        <!-- Chat Header -->
                        <div class="chat-header">
                            <h3><?php echo htmlspecialchars($chat_partner_data['fullname']); ?></h3>
                            <small><?php echo htmlspecialchars($chat_partner_data['email']); ?></small>
                        </div>
                        
                        <!-- Chat Messages -->
                        <div class="chat-messages" id="chat-messages">
                            <?php if(count($messages) > 0): ?>
                                <?php foreach($messages as $message): ?>
                                    <div class="message <?php echo $message['sender_id'] == $user_id ? 'sent' : 'received'; ?>">
                                        <div class="message-content">
                                            <?php echo nl2br(htmlspecialchars($message['message'])); ?>
                                        </div>
                                        <div class="message-time">
                                            <?php echo date('M j, Y g:i A', strtotime($message['created_at'])); ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div style="text-align: center; padding: 20px; color: #7f8c8d;">
                                    <p>No messages yet. Start the conversation!</p>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Chat Input -->
                        <div class="chat-input">
                            <form action="chat.php?partner_id=<?php echo $chat_partner_id; ?>" method="POST" class="message-form">
                                <input type="hidden" name="action" value="send_message">
                                <input type="hidden" name="receiver_id" value="<?php echo $chat_partner_id; ?>">
                                <input type="text" name="message" class="message-input" placeholder="Type your message..." autocomplete="off" autofocus>
                                <button type="submit" class="send-button">
                                    <i class="fas fa-paper-plane"></i>
                                </button>
                            </form>
                        </div>
                    <?php else: ?>
                        <div class="no-chat-selected">
                            <i class="fas fa-comments"></i>
                            <h3>Select a conversation</h3>
                            <p>Choose a <?php echo $user_role == 'professional' ? 'client' : 'professional'; ?> from the list</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <footer style="text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; color: #7f8c8d;">
            <p>&copy; 2025 Mental Health Support. All rights reserved.</p>
        </footer>
    </div>
    
    <script>
        // Auto-scroll to bottom of chat messages
        function scrollToBottom() {
            const chatMessages = document.getElementById('chat-messages');
            if (chatMessages) {
                chatMessages.scrollTop = chatMessages.scrollHeight;
            }
        }
        
        // Run on page load
        window.onload = scrollToBottom;
    </script>
</body>
</html>