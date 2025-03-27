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

$resource_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];

// Get the resource
$resource_query = "SELECT r.*, u.fullname AS author_name 
                  FROM mental_health_resources r
                  JOIN users u ON r.professional_id = u.id
                  WHERE r.id = ?";

if ($user_role != 'professional') {
    // Regular users can only see published resources
    $resource_query .= " AND r.is_published = 1";
} else {
    // Professionals can see their own unpublished resources
    $resource_query .= " AND (r.is_published = 1 OR r.professional_id = ?)";
}

$stmt = $conn->prepare($resource_query);

if ($user_role == 'professional') {
    $stmt->bind_param("ii", $resource_id, $user_id);
} else {
    $stmt->bind_param("i", $resource_id);
}

$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    header("Location: " . ($user_role == 'professional' ? 'prof_dash.php' : 'dashboard.php'));
    exit();
}

$resource = $result->fetch_assoc();

// Get resource categories (hardcoded for simplicity, same as in manage_resources.php)
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

// Function to convert plain text to HTML with paragraphs
function textToHtml($text) {
    // Convert line breaks to paragraphs
    $paragraphs = explode("\n\n", $text);
    $html = '';
    
    foreach ($paragraphs as $paragraph) {
        if (trim($paragraph)) {
            // Handle lists (lines starting with - or *)
            if (preg_match('/^[\-\*]/', trim($paragraph))) {
                $lines = explode("\n", $paragraph);
                $html .= "<ul>";
                
                foreach ($lines as $line) {
                    $line = trim($line);
                    if (preg_match('/^[\-\*]\s*(.+)$/', $line, $matches)) {
                        $html .= "<li>" . htmlspecialchars($matches[1]) . "</li>";
                    }
                }
                
                $html .= "</ul>";
            } 
            // Handle headers (lines starting with # or ##)
            else if (preg_match('/^#{1,3}\s+(.+)$/', trim($paragraph), $matches)) {
                $level = substr_count(trim($paragraph), '#', 0, 3);
                $html .= "<h" . ($level + 2) . ">" . htmlspecialchars($matches[1]) . "</h" . ($level + 2) . ">";
            }
            // Regular paragraphs
            else {
                $html .= "<p>" . nl2br(htmlspecialchars($paragraph)) . "</p>";
            }
        }
    }
    
    return $html;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($resource['title']); ?> - Mental Health Support</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        .resource-content {
            line-height: 1.8;
            font-size: 1.1rem;
        }
        .resource-content h3 {
            margin-top: 30px;
            color: #3498db;
        }
        .resource-content h4 {
            margin-top: 25px;
            color: #2c3e50;
        }
        .resource-content ul, .resource-content ol {
            margin-bottom: 20px;
            padding-left: 25px;
        }
        .resource-content li {
            margin-bottom: 10px;
        }
        .resource-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin: 20px 0;
            padding: 15px;
            background-color: #f8f9fa;
            border-radius: 8px;
        }
        .category-badge {
            display: inline-block;
            background-color: #3498db;
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.9rem;
        }
        .author-info {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        .related-resources {
            margin-top: 40px;
        }
        .related-card {
            border: 1px solid #eee;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            transition: all 0.3s ease;
        }
        .related-card:hover {
            border-color: #3498db;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>
<body style="background-color: #f5f7fa; align-items: flex-start; padding-top: 30px;">
    <div class="dashboard-container">
        <div class="welcome-header">
            <div>
                <h1><i class="fas fa-book-medical" style="color: #3498db;"></i> Mental Health Resource</h1>
                <p>Educational material to support your mental wellness journey</p>
            </div>
            <a href="<?php echo $user_role == 'professional' ? 'manage_resources.php' : 'resources.php'; ?>" class="action-button secondary" style="text-decoration: none;">
                <i class="fas fa-arrow-left"></i> Back to <?php echo $user_role == 'professional' ? 'Resource Management' : 'Resources'; ?>
            </a>
        </div>
        
        <?php if(!$resource['is_published'] && $user_role == 'professional'): ?>
        <div style="background-color: #fff3cd; color: #856404; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
            <i class="fas fa-exclamation-triangle"></i> This resource is currently <strong>unpublished</strong> and only visible to you. 
            <a href="manage_resources.php?action=toggle_publish&id=<?php echo $resource['id']; ?>" style="color: #856404; text-decoration: underline;">Publish it</a> to make it visible to users.
        </div>
        <?php endif; ?>
        
        <div class="card">
            <h2><?php echo htmlspecialchars($resource['title']); ?></h2>
            
            <div class="resource-meta">
                <div>
                    <strong>Category:</strong> 
                    <span class="category-badge">
                        <?php 
                        echo isset($categories[$resource['category']]) 
                            ? $categories[$resource['category']] 
                            : htmlspecialchars($resource['category']); 
                        ?>
                    </span>
                </div>
                <div>
                    <strong>Created by:</strong> <?php echo htmlspecialchars($resource['author_name']); ?>
                </div>
                <div>
                    <strong>Last Updated:</strong> <?php echo date('M d, Y', strtotime($resource['updated_at'])); ?>
                </div>
            </div>
            
            <div style="margin-bottom: 20px;">
                <p><strong><?php echo htmlspecialchars($resource['description']); ?></strong></p>
            </div>
            
            <div class="resource-content">
                <?php if($resource['resource_type'] == 'text'): ?>
                    <!-- Text content -->
                    <?php echo textToHtml($resource['content']); ?>
                
                <?php elseif($resource['resource_type'] == 'file'): ?>
                    <!-- File download -->
                    <div style="text-align: center; margin: 30px 0; padding: 30px; border: 1px dashed #3498db; border-radius: 10px;">
                        <i class="fas fa-file-alt" style="font-size: 48px; color: #3498db; margin-bottom: 15px;"></i>
                        <h3>Download Resource</h3>
                        <p>This resource is available as a downloadable file.</p>
                        <a href="<?php echo htmlspecialchars($resource['file_path']); ?>" class="action-button" style="text-decoration: none; margin-top: 15px; display: inline-block;" download>
                            <i class="fas fa-download"></i> Download File
                        </a>
                        <p style="margin-top: 15px; font-size: 0.9rem; color: #7f8c8d;">
                            File name: <?php echo basename($resource['file_path']); ?>
                        </p>
                    </div>
                
                <?php elseif($resource['resource_type'] == 'video'): ?>
                    <!-- Video embed -->
                    <div style="text-align: center; margin: 30px 0;">
                        <?php
                        $video_id = '';
                        $video_url = $resource['video_url'];
                        
                        // Extract YouTube video ID
                        if (preg_match('/youtube\.com\/watch\?v=([^&]+)/', $video_url, $matches) || 
                            preg_match('/youtu\.be\/([^&]+)/', $video_url, $matches)) {
                            $video_id = $matches[1];
                            echo '<div style="position: relative; padding-bottom: 56.25%; height: 0; overflow: hidden; max-width: 100%; border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.1);">';
                            echo '<iframe style="position: absolute; top: 0; left: 0; width: 100%; height: 100%;" src="https://www.youtube.com/embed/' . htmlspecialchars($video_id) . '" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>';
                            echo '</div>';
                        } 
                        // Extract Vimeo video ID
                        elseif (preg_match('/vimeo\.com\/(\d+)/', $video_url, $matches)) {
                            $video_id = $matches[1];
                            echo '<div style="position: relative; padding-bottom: 56.25%; height: 0; overflow: hidden; max-width: 100%; border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.1);">';
                            echo '<iframe style="position: absolute; top: 0; left: 0; width: 100%; height: 100%;" src="https://player.vimeo.com/video/' . htmlspecialchars($video_id) . '" frameborder="0" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen></iframe>';
                            echo '</div>';
                        } else {
                            echo '<p>Unable to embed video. <a href="' . htmlspecialchars($video_url) . '" target="_blank">Click here</a> to view the video.</p>';
                        }
                        ?>
                        <p style="margin-top: 15px; font-size: 0.9rem;">
                            <a href="<?php echo htmlspecialchars($resource['video_url']); ?>" target="_blank" style="color: #3498db;">
                                <i class="fas fa-external-link-alt"></i> Open video in new tab
                            </a>
                        </p>
                    </div>
                
                <?php elseif($resource['resource_type'] == 'link'): ?>
                    <!-- External link -->
                    <div style="text-align: center; margin: 30px 0; padding: 30px; border: 1px dashed #3498db; border-radius: 10px;">
                        <i class="fas fa-link" style="font-size: 48px; color: #3498db; margin-bottom: 15px;"></i>
                        <h3>External Resource</h3>
                        <p>This resource is hosted on an external website.</p>
                        <a href="<?php echo htmlspecialchars($resource['external_link']); ?>" class="action-button" style="text-decoration: none; margin-top: 15px; display: inline-block;" target="_blank">
                            <i class="fas fa-external-link-alt"></i> Visit Resource
                        </a>
                        <p style="margin-top: 15px; font-size: 0.9rem; color: #7f8c8d;">
                            URL: <?php echo htmlspecialchars($resource['external_link']); ?>
                        </p>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="author-info">
                <h3><i class="fas fa-user-md"></i> About the Author</h3>
                <p>This resource was created by <?php echo htmlspecialchars($resource['author_name']); ?>, a mental health professional on our platform.</p>
                
                <?php if($user_role == 'professional' && $resource['professional_id'] == $user_id): ?>
                <div style="margin-top: 20px;">
                    <a href="manage_resources.php?action=edit&id=<?php echo $resource['id']; ?>" class="action-button" style="text-decoration: none;">
                        <i class="fas fa-edit"></i> Edit This Resource
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <?php
        // Get related resources in the same category
        $related_query = "SELECT id, title, description 
                        FROM mental_health_resources 
                        WHERE category = ? 
                        AND id != ? 
                        AND is_published = 1 
                        LIMIT 3";
        $related_stmt = $conn->prepare($related_query);
        $related_stmt->bind_param("si", $resource['category'], $resource['id']);
        $related_stmt->execute();
        $related_result = $related_stmt->get_result();
        
        if($related_result->num_rows > 0):
        ?>
        <div class="card related-resources">
            <h3><i class="fas fa-link"></i> Related Resources</h3>
            <p>Explore more resources in the <?php echo isset($categories[$resource['category']]) ? $categories[$resource['category']] : $resource['category']; ?> category:</p>
            
            <div style="margin-top: 20px;">
                <?php while($related = $related_result->fetch_assoc()): ?>
                <a href="view_resource.php?id=<?php echo $related['id']; ?>" class="related-card" style="display: block; text-decoration: none; color: inherit;">
                    <h4><?php echo htmlspecialchars($related['title']); ?></h4>
                    <p><?php echo htmlspecialchars(substr($related['description'], 0, 100)) . (strlen($related['description']) > 100 ? '...' : ''); ?></p>
                </a>
                <?php endwhile; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <footer style="text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; color: #7f8c8d;">
            <p>&copy; 2025 Mental Health Support. All rights reserved.</p>
        </footer>
    </div>
</body>
</html>