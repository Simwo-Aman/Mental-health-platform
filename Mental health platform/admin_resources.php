<?php
// Include the admin authentication file
include 'admin_auth.php';

$message = "";
$error = "";

// Handle resource actions
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'])) {
    if ($_POST['action'] == 'delete' && isset($_POST['resource_id'])) {
        $resource_id = intval($_POST['resource_id']);
        
        // Check if resource exists
        $check_resource = $conn->prepare("SELECT id, file_path FROM mental_health_resources WHERE id = ?");
        $check_resource->bind_param("i", $resource_id);
        $check_resource->execute();
        $resource_result = $check_resource->get_result();
        
        if ($resource_result->num_rows > 0) {
            $resource = $resource_result->fetch_assoc();
            
            // Delete resource file if it exists
            if (!empty($resource['file_path']) && file_exists($resource['file_path'])) {
                unlink($resource['file_path']);
            }
            
            // Delete resource from database
            $delete_resource = $conn->prepare("DELETE FROM mental_health_resources WHERE id = ?");
            $delete_resource->bind_param("i", $resource_id);
            
            if ($delete_resource->execute()) {
                $message = "Resource has been deleted successfully.";
            } else {
                $error = "Error deleting resource: " . $conn->error;
            }
        } else {
            $error = "Resource not found.";
        }
    } elseif ($_POST['action'] == 'toggle_publish' && isset($_POST['resource_id'])) {
        $resource_id = intval($_POST['resource_id']);
        
        // Get current publish status
        $get_status = $conn->prepare("SELECT is_published FROM mental_health_resources WHERE id = ?");
        $get_status->bind_param("i", $resource_id);
        $get_status->execute();
        $status_result = $get_status->get_result();
        
        if ($status_result->num_rows > 0) {
            $resource = $status_result->fetch_assoc();
            $new_status = $resource['is_published'] ? 0 : 1;
            
            // Update publish status
            $update_status = $conn->prepare("UPDATE mental_health_resources SET is_published = ? WHERE id = ?");
            $update_status->bind_param("ii", $new_status, $resource_id);
            
            if ($update_status->execute()) {
                $status_text = $new_status ? "published" : "unpublished";
                $message = "Resource has been $status_text successfully.";
            } else {
                $error = "Error updating resource status: " . $conn->error;
            }
        } else {
            $error = "Resource not found.";
        }
    }
}

// Get filter parameters
$category_filter = isset($_GET['category']) ? $_GET['category'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$professional_id = isset($_GET['professional_id']) ? intval($_GET['professional_id']) : 0;

// Build query based on filters
$query = "SELECT r.*, u.fullname as author_name 
          FROM mental_health_resources r
          JOIN users u ON r.professional_id = u.id
          WHERE 1=1";

$params = [];
$param_types = "";

// Apply category filter
if (!empty($category_filter)) {
    $query .= " AND r.category = ?";
    $params[] = $category_filter;
    $param_types .= "s";
}

// Apply status filter
if ($status_filter == 'published') {
    $query .= " AND r.is_published = 1";
} elseif ($status_filter == 'unpublished') {
    $query .= " AND r.is_published = 0";
}

// Apply professional filter
if ($professional_id > 0) {
    $query .= " AND r.professional_id = ?";
    $params[] = $professional_id;
    $param_types .= "i";
}

// Add sorting
$query .= " ORDER BY r.created_at DESC";

// Prepare and execute query
$stmt = $conn->prepare($query);

if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

// Get resource categories
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

// Count resources statistics
$total_resources_query = $conn->query("SELECT COUNT(*) as count FROM mental_health_resources");
$total_resources = $total_resources_query->fetch_assoc()['count'];

$published_resources_query = $conn->query("SELECT COUNT(*) as count FROM mental_health_resources WHERE is_published = 1");
$published_resources = $published_resources_query->fetch_assoc()['count'];

$unpublished_resources_query = $conn->query("SELECT COUNT(*) as count FROM mental_health_resources WHERE is_published = 0");
$unpublished_resources = $unpublished_resources_query->fetch_assoc()['count'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resource Management - Admin Panel</title>
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
            flex-wrap: wrap;
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
        .resource-card {
            border: 1px solid #eee;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 15px;
            background-color: white;
        }
        .resource-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }
        .resource-actions {
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
        .badge-published {
            background-color: #2ecc71;
        }
        .badge-unpublished {
            background-color: #95a5a6;
        }
        .badge-category {
            background-color: #3498db;
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
        .resource-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin: 15px 0;
        }
        .meta-item {
            display: flex;
            align-items: center;
            gap: 5px;
            color: #7f8c8d;
            font-size: 0.9rem;
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
        .categories-filter {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 15px;
        }
    </style>
</head>
<body style="background-color: #f5f7fa; align-items: flex-start; padding-top: 30px;">
    <div class="dashboard-container">
        <div class="welcome-header">
            <div>
                <h1><i class="fas fa-book-medical" style="color: #3498db;"></i> Resource Management</h1>
                <p>Manage and moderate mental health resources</p>
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
            <h2><i class="fas fa-chart-bar"></i> Resource Statistics</h2>
            
            <div class="stats-grid">
                <div class="stat-box">
                    <div class="stat-value"><?php echo $total_resources; ?></div>
                    <div>Total Resources</div>
                </div>
                <div class="stat-box">
                    <div class="stat-value"><?php echo $published_resources; ?></div>
                    <div>Published</div>
                </div>
                <div class="stat-box">
                    <div class="stat-value"><?php echo $unpublished_resources; ?></div>
                    <div>Unpublished</div>
                </div>
            </div>
        </div>
        
        <div class="card">
            <div class="filter-bar">
                <div class="filter-tabs">
                    <a href="admin_resources.php<?php echo !empty($category_filter) ? '?category='.urlencode($category_filter) : ''; ?>" class="filter-tab <?php echo $status_filter == 'all' ? 'active' : ''; ?>">
                        All Resources
                    </a>
                    <a href="admin_resources.php?status=published<?php echo !empty($category_filter) ? '&category='.urlencode($category_filter) : ''; ?>" class="filter-tab <?php echo $status_filter == 'published' ? 'active' : ''; ?>">
                        Published
                    </a>
                    <a href="admin_resources.php?status=unpublished<?php echo !empty($category_filter) ? '&category='.urlencode($category_filter) : ''; ?>" class="filter-tab <?php echo $status_filter == 'unpublished' ? 'active' : ''; ?>">
                        Unpublished
                    </a>
                </div>
            </div>
            
            <?php if(count($categories) > 0): ?>
                <div class="categories-filter">
                    <?php foreach($categories as $cat_id => $cat_name): ?>
                        <a href="admin_resources.php?category=<?php echo urlencode($cat_id); ?><?php echo !empty($status_filter) && $status_filter != 'all' ? '&status='.$status_filter : ''; ?>" 
                           class="filter-tab <?php echo $category_filter == $cat_id ? 'active' : ''; ?>">
                            <?php echo htmlspecialchars($cat_name); ?>
                        </a>
                    <?php endforeach; ?>
                    
                    <?php if(!empty($category_filter)): ?>
                        <a href="admin_resources.php<?php echo !empty($status_filter) && $status_filter != 'all' ? '?status='.$status_filter : ''; ?>" 
                           class="filter-tab" style="background-color: #e74c3c; color: white;">
                            <i class="fas fa-times"></i> Clear Category
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <h2>
                <i class="fas fa-book-medical"></i> 
                <?php
                if (!empty($category_filter)) {
                    echo isset($categories[$category_filter]) ? $categories[$category_filter] : 'Category';
                    echo ' ';
                }
                
                switch($status_filter) {
                    case 'published':
                        echo 'Published Resources';
                        break;
                    case 'unpublished':
                        echo 'Unpublished Resources';
                        break;
                    default:
                        echo 'All Resources';
                }
                ?>
                <span style="font-size: 1rem; font-weight: normal; margin-left: 10px;">
                    (<?php echo $result->num_rows; ?> results)
                </span>
            </h2>
            
            <?php if($result->num_rows > 0): ?>
                <?php while($resource = $result->fetch_assoc()): ?>
                    <div class="resource-card">
                        <div class="resource-header">
                            <div>
                                <h3>
                                    <?php echo htmlspecialchars($resource['title']); ?>
                                    <span class="badge badge-<?php echo $resource['is_published'] ? 'published' : 'unpublished'; ?>">
                                        <?php echo $resource['is_published'] ? 'Published' : 'Unpublished'; ?>
                                    </span>
                                    <span class="badge badge-category">
                                        <?php echo isset($categories[$resource['category']]) ? $categories[$resource['category']] : $resource['category']; ?>
                                    </span>
                                </h3>
                                <p><?php echo htmlspecialchars($resource['description']); ?></p>
                            </div>
                            
                            <div class="resource-actions">
                                <form action="admin_resources.php" method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="toggle_publish">
                                    <input type="hidden" name="resource_id" value="<?php echo $resource['id']; ?>">
                                    <button type="submit" class="action-button <?php echo $resource['is_published'] ? 'secondary' : ''; ?>" style="padding: 5px 10px; font-size: 0.9rem;">
                                        <i class="fas <?php echo $resource['is_published'] ? 'fa-eye-slash' : 'fa-eye'; ?>"></i> 
                                        <?php echo $resource['is_published'] ? 'Unpublish' : 'Publish'; ?>
                                    </button>
                                </form>
                                
                                <button type="button" class="action-button" style="background-color: #e74c3c; padding: 5px 10px; font-size: 0.9rem;" 
                                        onclick="openDeleteModal(<?php echo $resource['id']; ?>, '<?php echo htmlspecialchars(addslashes($resource['title'])); ?>')">
                                    <i class="fas fa-trash"></i> Delete
                                </button>
                            </div>
                        </div>
                        
                        <div class="resource-meta">
                            <div class="meta-item">
                                <i class="fas fa-user"></i> 
                                <?php echo htmlspecialchars($resource['author_name']); ?>
                            </div>
                            
                            <div class="meta-item">
                                <i class="fas fa-clock"></i> 
                                Created: <?php echo date('M d, Y', strtotime($resource['created_at'])); ?>
                            </div>
                            
                            <div class="meta-item">
                                <i class="fas fa-sync-alt"></i> 
                                Updated: <?php echo date('M d, Y', strtotime($resource['updated_at'])); ?>
                            </div>
                            
                            <div class="meta-item">
                                <i class="fas <?php
                                    switch ($resource['resource_type']) {
                                        case 'text':
                                            echo 'fa-file-alt';
                                            break;
                                        case 'file':
                                            echo 'fa-file';
                                            break;
                                        case 'video':
                                            echo 'fa-video';
                                            break;
                                        case 'link':
                                            echo 'fa-link';
                                            break;
                                        default:
                                            echo 'fa-file-alt';
                                    }
                                ?>"></i> 
                                <?php
                                    switch ($resource['resource_type']) {
                                        case 'text':
                                            echo 'Text Content';
                                            break;
                                        case 'file':
                                            echo 'File: ' . (empty($resource['file_path']) ? 'None' : basename($resource['file_path']));
                                            break;
                                        case 'video':
                                            echo 'Video';
                                            break;
                                        case 'link':
                                            echo 'External Link';
                                            break;
                                        default:
                                            echo ucfirst($resource['resource_type']);
                                    }
                                ?>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p style="text-align: center; padding: 20px;">
                    <i class="fas fa-info-circle"></i> No resources found matching your criteria.
                </p>
            <?php endif; ?>
        </div>
        
        <footer style="text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; color: #7f8c8d;">
            <p>&copy; 2025 Mental Health Support. All rights reserved.</p>
        </footer>
    </div>
    
    <!-- Delete Resource Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Delete Resource</h3>
                <span class="close-modal" onclick="closeModal()">&times;</span>
            </div>
            <p>Are you sure you want to delete this resource? This action cannot be undone.</p>
            <p id="deleteResourceTitle" style="font-weight: bold;"></p>
            
            <form action="admin_resources.php" method="POST">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="resource_id" id="delete_resource_id" value="">
                
                <div style="display: flex; justify-content: space-between; margin-top: 20px;">
                    <button type="button" class="action-button secondary" onclick="closeModal()">
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
        function openDeleteModal(resourceId, resourceTitle) {
            document.getElementById('delete_resource_id').value = resourceId;
            document.getElementById('deleteResourceTitle').innerText = resourceTitle;
            document.getElementById('deleteModal').style.display = 'flex';
        }
        
        function closeModal() {
            document.getElementById('deleteModal').style.display = 'none';
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