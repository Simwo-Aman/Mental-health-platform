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

// Create uploads directory if it doesn't exist
$upload_dir = "uploads/resources/";
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Create resources table if it doesn't exist yet
$create_table = "CREATE TABLE IF NOT EXISTS mental_health_resources (
    id INT AUTO_INCREMENT PRIMARY KEY,
    professional_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    category VARCHAR(100) NOT NULL,
    description TEXT NOT NULL,
    content TEXT NOT NULL,
    resource_type ENUM('text', 'file', 'video', 'link') NOT NULL DEFAULT 'text',
    file_path VARCHAR(255) NULL,
    video_url VARCHAR(255) NULL,
    external_link VARCHAR(255) NULL,
    is_published BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (professional_id) REFERENCES users(id) ON DELETE CASCADE
)";
$conn->query($create_table);

// Handle create/update resource
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'save_resource') {
    $title = $_POST['title'];
    $category = $_POST['category'];
    $description = $_POST['description'];
    $resource_type = $_POST['resource_type'];
    $is_published = isset($_POST['is_published']) ? 1 : 0;
    
    // Initialize variables
    $content = "";
    $file_path = null;
    $video_url = null;
    $external_link = null;
    
    // Process different resource types
    switch($resource_type) {
        case 'text':
            $content = $_POST['content'];
            break;
            
        case 'file':
            // Check if this is an update with existing file
            if(isset($_POST['existing_file']) && empty($_FILES['resource_file']['name'])) {
                $file_path = $_POST['existing_file'];
            } 
            // Process new file upload
            elseif(!empty($_FILES['resource_file']['name'])) {
                $file_name = time() . '_' . basename($_FILES['resource_file']['name']);
                $target_file = $upload_dir . $file_name;
                
                // Check file size (limit to 10MB)
                if ($_FILES['resource_file']['size'] > 10000000) {
                    $error = "Sorry, your file is too large. Maximum size is 10MB.";
                    goto skip_resource_save;
                }
                
                // Move the uploaded file
                if (move_uploaded_file($_FILES['resource_file']['tmp_name'], $target_file)) {
                    $file_path = $target_file;
                } else {
                    $error = "Sorry, there was an error uploading your file.";
                    goto skip_resource_save;
                }
            } else {
                $error = "Please select a file to upload.";
                goto skip_resource_save;
            }
            break;
            
        case 'video':
            $video_url = $_POST['video_url'];
            // Basic validation for YouTube or Vimeo URLs
            if (!preg_match('/(youtube\.com|youtu\.be|vimeo\.com)/', $video_url)) {
                $error = "Please enter a valid YouTube or Vimeo URL.";
                goto skip_resource_save;
            }
            break;
            
        case 'link':
            $external_link = $_POST['external_link'];
            // Basic URL validation
            if (!filter_var($external_link, FILTER_VALIDATE_URL)) {
                $error = "Please enter a valid URL.";
                goto skip_resource_save;
            }
            break;
    }
    
    // For update or create...
    if (isset($_POST['resource_id']) && !empty($_POST['resource_id'])) {
        // Update existing resource
        $resource_id = intval($_POST['resource_id']);
        
        // Verify resource belongs to this professional
        $check_owner = $conn->prepare("SELECT id FROM mental_health_resources WHERE id = ? AND professional_id = ?");
        $check_owner->bind_param("ii", $resource_id, $user_id);
        $check_owner->execute();
        $result = $check_owner->get_result();
        
        if ($result->num_rows > 0) {
            $update_sql = "UPDATE mental_health_resources 
                           SET title = ?, category = ?, description = ?, content = ?, 
                           resource_type = ?, file_path = ?, video_url = ?, external_link = ?,
                           is_published = ? 
                           WHERE id = ?";
            $stmt = $conn->prepare($update_sql);
            $stmt->bind_param("ssssssssii", $title, $category, $description, $content, 
                             $resource_type, $file_path, $video_url, $external_link,
                             $is_published, $resource_id);
            
            if ($stmt->execute()) {
                $message = "Resource updated successfully!";
            } else {
                $error = "Error updating resource: " . $conn->error;
            }
        } else {
            $error = "You don't have permission to edit this resource.";
        }
    } else {
        // Create new resource
        $insert_sql = "INSERT INTO mental_health_resources 
                      (professional_id, title, category, description, content, 
                       resource_type, file_path, video_url, external_link, is_published) 
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($insert_sql);
        $stmt->bind_param("issssssssi", $user_id, $title, $category, $description, $content, 
                         $resource_type, $file_path, $video_url, $external_link, $is_published);
        
        if ($stmt->execute()) {
            $message = "Resource created successfully!";
        } else {
            $error = "Error creating resource: " . $conn->error;
        }
    }
}
skip_resource_save:

// Handle delete resource
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $resource_id = intval($_GET['id']);
    
    // Verify resource belongs to this professional
    $check_owner = $conn->prepare("SELECT id FROM mental_health_resources WHERE id = ? AND professional_id = ?");
    $check_owner->bind_param("ii", $resource_id, $user_id);
    $check_owner->execute();
    $result = $check_owner->get_result();
    
    if ($result->num_rows > 0) {
        $delete_sql = "DELETE FROM mental_health_resources WHERE id = ?";
        $stmt = $conn->prepare($delete_sql);
        $stmt->bind_param("i", $resource_id);
        
        if ($stmt->execute()) {
            $message = "Resource deleted successfully!";
        } else {
            $error = "Error deleting resource: " . $conn->error;
        }
    } else {
        $error = "You don't have permission to delete this resource.";
    }
}

// Handle toggle publish status
if (isset($_GET['action']) && $_GET['action'] == 'toggle_publish' && isset($_GET['id'])) {
    $resource_id = intval($_GET['id']);
    
    // Verify resource belongs to this professional
    $check_owner = $conn->prepare("SELECT id, is_published FROM mental_health_resources WHERE id = ? AND professional_id = ?");
    $check_owner->bind_param("ii", $resource_id, $user_id);
    $check_owner->execute();
    $result = $check_owner->get_result();
    
    if ($result->num_rows > 0) {
        $resource = $result->fetch_assoc();
        $new_status = $resource['is_published'] ? 0 : 1;
        
        $update_sql = "UPDATE mental_health_resources SET is_published = ? WHERE id = ?";
        $stmt = $conn->prepare($update_sql);
        $stmt->bind_param("ii", $new_status, $resource_id);
        
        if ($stmt->execute()) {
            $status_text = $new_status ? "published" : "unpublished";
            $message = "Resource $status_text successfully!";
        } else {
            $error = "Error updating resource status: " . $conn->error;
        }
    } else {
        $error = "You don't have permission to modify this resource.";
    }
}

// Get editing resource if specified
$editing_resource = null;
if (isset($_GET['action']) && $_GET['action'] == 'edit' && isset($_GET['id'])) {
    $resource_id = intval($_GET['id']);
    
    $get_resource = $conn->prepare("SELECT * FROM mental_health_resources WHERE id = ? AND professional_id = ?");
    $get_resource->bind_param("ii", $resource_id, $user_id);
    $get_resource->execute();
    $result = $get_resource->get_result();
    
    if ($result->num_rows > 0) {
        $editing_resource = $result->fetch_assoc();
    } else {
        $error = "Resource not found or you don't have permission to edit it.";
    }
}

// Get all resources for this professional
$resources_query = "SELECT * FROM mental_health_resources 
                   WHERE professional_id = ? 
                   ORDER BY updated_at DESC";
$resources_stmt = $conn->prepare($resources_query);
$resources_stmt->bind_param("i", $user_id);
$resources_stmt->execute();
$resources_result = $resources_stmt->get_result();

// Get resource categories (hardcoded for simplicity, could be from DB)
$categories = [
    'anxiety' => 'Anxiety Management',
    'depression' => 'Depression Support',
    'stress' => 'Stress Reduction',
    'mindfulness' => 'Mindfulness Techniques',
    'relationships' => 'Relationship Skills',
    'self_care' => 'Self-Care Practices',
    'trauma' => 'Trauma Recovery',
    'addiction' => 'Addiction Support',
    'general' => 'General Mental Health'
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Resources - Mental Health Support</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        .resource-card {
            border: 1px solid #eee;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            background-color: white;
            position: relative;
        }
        .resource-card.published {
            border-left: 5px solid #2ecc71;
        }
        .resource-card.unpublished {
            border-left: 5px solid #95a5a6;
            background-color: #f9f9f9;
        }
        .resource-actions {
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
        .status-badge.published {
            background-color: #2ecc71;
        }
        .status-badge.unpublished {
            background-color: #95a5a6;
        }
        textarea.content-editor {
            width: 100%;
            min-height: 300px;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            font-size: 1rem;
        }
        .tab-buttons {
            display: flex;
            margin-bottom: 20px;
            border-bottom: 1px solid #ddd;
        }
        .tab-button {
            padding: 10px 20px;
            background: none;
            border: none;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            border-bottom: 3px solid transparent;
            opacity: 0.7;
        }
        .tab-button.active {
            border-bottom-color: #3498db;
            opacity: 1;
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
                <h1><i class="fas fa-book-medical" style="color: #3498db;"></i> Resource Management</h1>
                <p>Create and manage mental health resources for your clients</p>
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
            <div class="tab-buttons">
                <button class="tab-button <?php echo !$editing_resource ? 'active' : ''; ?>" data-tab="resources-list">
                    <i class="fas fa-list"></i> Your Resources
                </button>
                <button class="tab-button <?php echo $editing_resource ? 'active' : ''; ?>" data-tab="create-resource">
                    <i class="fas fa-plus-circle"></i> <?php echo $editing_resource ? 'Edit Resource' : 'Create New Resource'; ?>
                </button>
            </div>
            
            <!-- Resources List Tab -->
            <div id="resources-list" class="tab-content <?php echo !$editing_resource ? 'active' : ''; ?>">
                <h2><i class="fas fa-book"></i> Your Mental Health Resources</h2>
                
                <?php if($resources_result->num_rows > 0): ?>
                    <p>You have created <?php echo $resources_result->num_rows; ?> resource(s).</p>
                    
                    <div style="margin-top: 20px;">
                        <?php while($resource = $resources_result->fetch_assoc()): ?>
                            <div class="resource-card <?php echo $resource['is_published'] ? 'published' : 'unpublished'; ?>">
                                <div class="resource-actions">
                                    <a href="manage_resources.php?action=edit&id=<?php echo $resource['id']; ?>" class="action-button secondary" style="padding: 5px 10px; font-size: 0.9rem; text-decoration: none;">
                                        <i class="fas fa-edit"></i> Edit
                                    </a>
                                    <a href="manage_resources.php?action=toggle_publish&id=<?php echo $resource['id']; ?>" class="action-button <?php echo $resource['is_published'] ? 'secondary' : ''; ?>" style="padding: 5px 10px; font-size: 0.9rem; text-decoration: none;">
                                        <i class="fas <?php echo $resource['is_published'] ? 'fa-eye-slash' : 'fa-eye'; ?>"></i> 
                                        <?php echo $resource['is_published'] ? 'Unpublish' : 'Publish'; ?>
                                    </a>
                                    <a href="manage_resources.php?action=delete&id=<?php echo $resource['id']; ?>" class="action-button secondary" style="padding: 5px 10px; font-size: 0.9rem; text-decoration: none;" onclick="return confirm('Are you sure you want to delete this resource?');">
                                        <i class="fas fa-trash"></i> Delete
                                    </a>
                                </div>
                                
                                <h3>
                                    <?php echo htmlspecialchars($resource['title']); ?>
                                    <span class="status-badge <?php echo $resource['is_published'] ? 'published' : 'unpublished'; ?>">
                                        <?php echo $resource['is_published'] ? 'Published' : 'Draft'; ?>
                                    </span>
                                </h3>
                                
                                <p><strong>Category:</strong> 
                                    <?php 
                                    echo isset($categories[$resource['category']]) 
                                        ? $categories[$resource['category']] 
                                        : htmlspecialchars($resource['category']); 
                                    ?>
                                </p>
                                
                                <p><strong>Type:</strong> 
                                    <?php 
                                    switch($resource['resource_type']) {
                                        case 'text':
                                            echo '<i class="fas fa-file-alt"></i> Text Content';
                                            break;
                                        case 'file':
                                            echo '<i class="fas fa-file"></i> Downloadable File';
                                            break;
                                        case 'video':
                                            echo '<i class="fas fa-video"></i> Video';
                                            break;
                                        case 'link':
                                            echo '<i class="fas fa-link"></i> External Link';
                                            break;
                                        default:
                                            echo htmlspecialchars($resource['resource_type']);
                                    }
                                    ?>
                                </p>
                                
                                <p><strong>Description:</strong> <?php echo htmlspecialchars($resource['description']); ?></p>
                                
                                <p><strong>Last Updated:</strong> <?php echo date('M d, Y \a\t h:i A', strtotime($resource['updated_at'])); ?></p>
                                
                                <a href="view_resource.php?id=<?php echo $resource['id']; ?>" class="action-button" style="display: inline-block; margin-top: 10px; text-decoration: none;">
                                    <i class="fas fa-eye"></i> Preview Resource
                                </a>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <p>You haven't created any resources yet. Click on the "Create New Resource" tab to get started.</p>
                <?php endif; ?>
            </div>
            
            <!-- Create/Edit Resource Tab -->
            <div id="create-resource" class="tab-content <?php echo $editing_resource ? 'active' : ''; ?>">
                <h2>
                    <i class="fas <?php echo $editing_resource ? 'fa-edit' : 'fa-plus-circle'; ?>"></i> 
                    <?php echo $editing_resource ? 'Edit Resource' : 'Create New Resource'; ?>
                </h2>
                
                <p>Create helpful mental health resources that can be shared with your clients and the community.</p>
                
                <form action="manage_resources.php" method="POST" enctype="multipart/form-data" style="margin-top: 20px;">
                    <input type="hidden" name="action" value="save_resource">
                    <?php if($editing_resource): ?>
                        <input type="hidden" name="resource_id" value="<?php echo $editing_resource['id']; ?>">
                    <?php endif; ?>
                    
                    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px; margin-bottom: 20px;">
                        <div>
                            <div class="form-group" style="margin-bottom: 15px;">
                                <label for="title" style="display: block; margin-bottom: 5px; font-weight: 600;">Resource Title:</label>
                                <input type="text" id="title" name="title" value="<?php echo $editing_resource ? htmlspecialchars($editing_resource['title']) : ''; ?>" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
                            </div>
                            
                            <div class="form-group" style="margin-bottom: 15px;">
                                <label for="category" style="display: block; margin-bottom: 5px; font-weight: 600;">Category:</label>
                                <select id="category" name="category" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
                                    <option value="">Select a category</option>
                                    <?php foreach($categories as $value => $label): ?>
                                        <option value="<?php echo $value; ?>" <?php echo ($editing_resource && $editing_resource['category'] == $value) ? 'selected' : ''; ?>>
                                            <?php echo $label; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group" style="margin-bottom: 15px;">
                                <label for="resource_type" style="display: block; margin-bottom: 5px; font-weight: 600;">Resource Type:</label>
                                <select id="resource_type" name="resource_type" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;" onchange="toggleResourceFields()">
                                    <option value="text" <?php echo ($editing_resource && $editing_resource['resource_type'] == 'text') ? 'selected' : ''; ?>>Text Content</option>
                                    <option value="file" <?php echo ($editing_resource && $editing_resource['resource_type'] == 'file') ? 'selected' : ''; ?>>File Upload (PDF, DOC, etc.)</option>
                                    <option value="video" <?php echo ($editing_resource && $editing_resource['resource_type'] == 'video') ? 'selected' : ''; ?>>Video (YouTube, Vimeo)</option>
                                    <option value="link" <?php echo ($editing_resource && $editing_resource['resource_type'] == 'link') ? 'selected' : ''; ?>>External Link</option>
                                </select>
                            </div>
                        </div>
                        
                        <div>
                            <div class="form-group" style="margin-bottom: 15px;">
                                <label for="description" style="display: block; margin-bottom: 5px; font-weight: 600;">Short Description:</label>
                                <textarea id="description" name="description" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; min-height: 100px;"><?php echo $editing_resource ? htmlspecialchars($editing_resource['description']) : ''; ?></textarea>
                                <small style="color: #6c757d;">Briefly describe what this resource is about (max 200 words)</small>
                            </div>
                            
                            <div class="form-group" style="margin-top: 15px;">
                                <label style="font-weight: 600; display: flex; align-items: center;">
                                    <input type="checkbox" name="is_published" <?php echo ($editing_resource && $editing_resource['is_published']) ? 'checked' : ''; ?> style="margin-right: 10px;">
                                    Publish immediately
                                </label>
                                <small style="color: #6c757d;">Unpublished resources are only visible to you</small>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Text content field (default) -->
                    <div id="text-content-field" class="resource-field" style="margin-bottom: 20px;">
                        <label for="content" style="display: block; margin-bottom: 5px; font-weight: 600;">Resource Content:</label>
                        <textarea id="content" name="content" class="content-editor" <?php echo ($editing_resource && $editing_resource['resource_type'] != 'text') ? 'style="display: none;"' : ''; ?>><?php echo $editing_resource ? htmlspecialchars($editing_resource['content']) : ''; ?></textarea>
                        <small style="color: #6c757d;">Write the full content of your resource. You can use basic formatting.</small>
                    </div>

                    <!-- File upload field -->
                    <div id="file-upload-field" class="resource-field" style="margin-bottom: 20px; <?php echo (!$editing_resource || $editing_resource['resource_type'] != 'file') ? 'display: none;' : ''; ?>">
                        <label for="resource_file" style="display: block; margin-bottom: 5px; font-weight: 600;">Upload File (PDF, DOC, DOCX, etc.):</label>
                        <input type="file" id="resource_file" name="resource_file" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
                        <?php if($editing_resource && !empty($editing_resource['file_path'])): ?>
                            <p style="margin-top: 10px;">Current file: <a href="<?php echo htmlspecialchars($editing_resource['file_path']); ?>" target="_blank"><?php echo basename($editing_resource['file_path']); ?></a></p>
                            <input type="hidden" name="existing_file" value="<?php echo htmlspecialchars($editing_resource['file_path']); ?>">
                        <?php endif; ?>
                        <small style="color: #6c757d;">Upload resources like worksheets, guides, or other documents. Max file size: 10MB.</small>
                    </div>

                    <!-- Video URL field -->
                    <div id="video-url-field" class="resource-field" style="margin-bottom: 20px; <?php echo (!$editing_resource || $editing_resource['resource_type'] != 'video') ? 'display: none;' : ''; ?>">
                        <label for="video_url" style="display: block; margin-bottom: 5px; font-weight: 600;">Video URL (YouTube or Vimeo):</label>
                        <input type="url" id="video_url" name="video_url" value="<?php echo ($editing_resource && !empty($editing_resource['video_url'])) ? htmlspecialchars($editing_resource['video_url']) : ''; ?>" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;" placeholder="https://www.youtube.com/watch?v=...">
                        <small style="color: #6c757d;">Paste the full URL of the YouTube or Vimeo video.</small>
                    </div>

                    <!-- External Link field -->
                    <div id="external-link-field" class="resource-field" style="margin-bottom: 20px; <?php echo (!$editing_resource || $editing_resource['resource_type'] != 'link') ? 'display: none;' : ''; ?>">
                        <label for="external_link" style="display: block; margin-bottom: 5px; font-weight: 600;">External Resource Link:</label>
                        <input type="url" id="external_link" name="external_link" value="<?php echo ($editing_resource && !empty($editing_resource['external_link'])) ? htmlspecialchars($editing_resource['external_link']) : ''; ?>" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;" placeholder="https://...">
                        <small style="color: #6c757d;">Link to an external resource, article, or website.</small>
                    </div>
                    
                    <div style="display: flex; gap: 10px;">
                        <button type="submit" class="action-button">
                            <i class="fas fa-save"></i> <?php echo $editing_resource ? 'Update Resource' : 'Save Resource'; ?>
                        </button>
                        
                        <?php if($editing_resource): ?>
                            <a href="manage_resources.php" class="action-button secondary" style="text-decoration: none;">
                                <i class="fas fa-times"></i> Cancel Editing
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
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
            
            // Initialize resource fields
            toggleResourceFields();
        });
        
        function toggleResourceFields() {
            const resourceType = document.getElementById('resource_type').value;
            const resourceFields = document.querySelectorAll('.resource-field');
            
            // Hide all resource fields first
            resourceFields.forEach(field => {
                field.style.display = 'none';
            });
            
            // Show the appropriate field based on selected type
            switch(resourceType) {
                case 'text':
                    document.getElementById('text-content-field').style.display = 'block';
                    break;
                case 'file':
                    document.getElementById('file-upload-field').style.display = 'block';
                    break;
                case 'video':
                    document.getElementById('video-url-field').style.display = 'block';
                    break;
                case 'link':
                    document.getElementById('external-link-field').style.display = 'block';
                    break;
            }
        }
    </script>
</body>
</html>