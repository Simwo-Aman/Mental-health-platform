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

// Get selected category (if any)
$selected_category = isset($_GET['category']) ? $_GET['category'] : '';

// Resource categories
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

// Build the query for resources
$resources_query = "SELECT r.*, u.fullname AS author_name 
                   FROM mental_health_resources r
                   JOIN users u ON r.professional_id = u.id
                   WHERE r.is_published = 1 ";

$params = [];
$param_types = "";

// Add category filter if selected
if ($selected_category) {
    $resources_query .= "AND r.category = ? ";
    $params[] = $selected_category;
    $param_types .= "s";
}

// Order by most recent
$resources_query .= "ORDER BY r.created_at DESC";

$stmt = $conn->prepare($resources_query);

if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}

$stmt->execute();
$resources_result = $stmt->get_result();

// Get category counts for the sidebar
$category_counts = [];
foreach ($categories as $cat_id => $cat_name) {
    $count_stmt = $conn->prepare("SELECT COUNT(*) as count FROM mental_health_resources WHERE category = ? AND is_published = 1");
    $count_stmt->bind_param("s", $cat_id);
    $count_stmt->execute();
    $count_result = $count_stmt->get_result();
    $category_counts[$cat_id] = $count_result->fetch_assoc()['count'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mental Health Resources - Mental Health Support</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        .resources-container {
            display: grid;
            grid-template-columns: 250px 1fr;
            gap: 30px;
        }
        .sidebar {
            background: white;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            padding: 20px;
            height: fit-content;
        }
        .category-link {
            display: flex;
            justify-content: space-between;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 5px;
            color: #333;
            text-decoration: none;
            transition: all 0.2s ease;
        }
        .category-link:hover {
            background-color: #f0f8ff;
        }
        .category-link.active {
            background-color: #3498db;
            color: white;
        }
        .category-count {
            background-color: #eee;
            border-radius: 12px;
            padding: 2px 8px;
            font-size: 0.8rem;
            color: #333;
        }
        .category-link.active .category-count {
            background-color: white;
            color: #3498db;
        }
        .resource-card {
            border: 1px solid #eee;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            background-color: white;
            transition: all 0.3s ease;
        }
        .resource-card:hover {
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            transform: translateY(-3px);
        }
        .resource-category {
            display: inline-block;
            background-color: #3498db;
            color: white;
            padding: 3px 10px;
            border-radius: 15px;
            font-size: 0.8rem;
            margin-bottom: 10px;
        }
        .resource-meta {
            display: flex;
            justify-content: space-between;
            margin-top: 15px;
            font-size: 0.9rem;
            color: #7f8c8d;
        }
    </style>
</head>
<body style="background-color: #f5f7fa; align-items: flex-start; padding-top: 30px;">
    <div class="dashboard-container">
        <div class="welcome-header">
            <div>
                <h1><i class="fas fa-book-medical" style="color: #3498db;"></i> Mental Health Resources</h1>
                <p>Browse our collection of resources to support your mental wellness journey</p>
            </div>
            <a href="<?php echo $user_role == 'professional' ? 'prof_dash.php' : 'dashboard.php'; ?>" class="action-button secondary" style="text-decoration: none;">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>
        
        <div class="resources-container">
            <!-- Categories Sidebar -->
            <div class="sidebar">
                <h3><i class="fas fa-tags"></i> Categories</h3>
                
                <a href="resources.php" class="category-link <?php echo $selected_category == '' ? 'active' : ''; ?>">
                    <span><i class="fas fa-layer-group"></i> All Resources</span>
                    <span class="category-count"><?php echo $resources_result->num_rows; ?></span>
                </a>
                
                <?php foreach($categories as $cat_id => $cat_name): ?>
                    <?php if($category_counts[$cat_id] > 0): ?>
                        <a href="resources.php?category=<?php echo urlencode($cat_id); ?>" class="category-link <?php echo $selected_category == $cat_id ? 'active' : ''; ?>">
                            <span><?php echo htmlspecialchars($cat_name); ?></span>
                            <span class="category-count"><?php echo $category_counts[$cat_id]; ?></span>
                        </a>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
            
            <!-- Resources List -->
            <div>
                <?php if($selected_category): ?>
                    <div style="margin-bottom: 20px;">
                        <h3>
                            Resources in <?php echo htmlspecialchars($categories[$selected_category]); ?>
                        </h3>
                    </div>
                <?php endif; ?>
                
                <?php if($resources_result->num_rows > 0): ?>
                    <?php while($resource = $resources_result->fetch_assoc()): ?>
                        <div class="resource-card">
                            <span class="resource-category">
                                <?php 
                                echo isset($categories[$resource['category']]) 
                                    ? $categories[$resource['category']] 
                                    : htmlspecialchars($resource['category']); 
                                ?>
                            </span>
                            
                            <h3><?php echo htmlspecialchars($resource['title']); ?></h3>
                            
                            <p><?php echo htmlspecialchars($resource['description']); ?></p>
                            
                            <div class="resource-meta">
                                <span>By <?php echo htmlspecialchars($resource['author_name']); ?></span>
                                <span><?php echo date('M d, Y', strtotime($resource['created_at'])); ?></span>
                            </div>
                            
                            <a href="view_resource.php?id=<?php echo $resource['id']; ?>" class="action-button" style="display: inline-block; margin-top: 15px; text-decoration: none;">
                                <i class="fas fa-book-open"></i> Read Resource
                            </a>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div style="text-align: center; padding: 40px 0;">
                        <i class="fas fa-search" style="font-size: 48px; color: #bdc3c7; margin-bottom: 20px;"></i>
                        <h3>No resources found</h3>
                        <?php if($selected_category): ?>
                            <p>No resources available in the selected category</p>
                        <?php else: ?>
                            <p>There are currently no published resources</p>
                        <?php endif; ?>
                        <a href="resources.php" class="action-button" style="display: inline-block; margin-top: 20px; text-decoration: none;">
                            <i class="fas fa-sync"></i> View All Resources
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <footer style="text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; color: #7f8c8d;">
            <p>&copy; 2025 Mental Health Support. All rights reserved.</p>
        </footer>
    </div>
</body>
</html>