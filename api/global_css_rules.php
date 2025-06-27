<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

// Check if this is a public CSS generation request
$action = $_GET['action'] ?? $_POST['action'] ?? '';
$isPublicAction = ($action === 'generate_css');

if (!$isPublicAction) {
    // Use centralized authentication for admin actions
    requireAdmin();
    $userData = getCurrentUser();
} else {
    // Allow public access for CSS generation
    $userData = null;
}
$method = $_SERVER['REQUEST_METHOD'];

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    switch ($method) {
        case 'GET':
            handleGet($pdo);
            break;
        case 'POST':
            handlePost($pdo);
            break;
        case 'PUT':
            handlePut($pdo);
            break;
        case 'DELETE':
            handleDelete($pdo);
            break;
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}

function handleGet($pdo) {
    $action = $_GET['action'] ?? 'list';
    
    switch ($action) {
        case 'list':
            // Get all CSS rules grouped by category
            $stmt = $pdo->query("
                SELECT * FROM global_css_rules 
                WHERE is_active = 1 
                ORDER BY category, rule_name
            ");
            $rules = $stmt->fetchAll();
            
            // Group by category
            $grouped = [];
            foreach ($rules as $rule) {
                $grouped[$rule['category']][] = $rule;
            }
            
            echo json_encode([
                'success' => true,
                'rules' => $rules,
                'grouped' => $grouped,
                'total' => count($rules)
            ]);
            break;
            
        case 'categories':
            // Get list of categories
            $stmt = $pdo->query("
                SELECT DISTINCT category, COUNT(*) as rule_count 
                FROM global_css_rules 
                WHERE is_active = 1 
                GROUP BY category 
                ORDER BY category
            ");
            $categories = $stmt->fetchAll();
            
            echo json_encode([
                'success' => true,
                'categories' => $categories
            ]);
            break;
            
        case 'generate_css':
            // Generate CSS file content
            $stmt = $pdo->query("
                SELECT * FROM global_css_rules 
                WHERE is_active = 1 
                ORDER BY category, rule_name
            ");
            $rules = $stmt->fetchAll();
            
            $css = generateCSSContent($rules);
            
            echo json_encode([
                'success' => true,
                'css_content' => $css,
                'rules_count' => count($rules)
            ]);
            break;
            
        default:
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
}

function handlePost($pdo) {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'add':
            $ruleName = $_POST['rule_name'] ?? '';
            $cssProperty = $_POST['css_property'] ?? '';
            $cssValue = $_POST['css_value'] ?? '';
            $category = $_POST['category'] ?? '';
            $description = $_POST['description'] ?? '';
            
            if (empty($ruleName) || empty($cssProperty) || empty($cssValue) || empty($category)) {
                echo json_encode(['success' => false, 'error' => 'Missing required fields']);
                return;
            }
            
            $stmt = $pdo->prepare("
                INSERT INTO global_css_rules (rule_name, css_property, css_value, category, description)
                VALUES (?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([$ruleName, $cssProperty, $cssValue, $category, $description]);
            
            echo json_encode([
                'success' => true,
                'message' => 'CSS rule added successfully',
                'rule_id' => $pdo->lastInsertId()
            ]);
            break;
            
        case 'update_bulk':
            $rules = json_decode($_POST['rules'] ?? '[]', true);
            
            if (empty($rules)) {
                echo json_encode(['success' => false, 'error' => 'No rules provided']);
                return;
            }
            
            $pdo->beginTransaction();
            
            try {
                $stmt = $pdo->prepare("
                    UPDATE global_css_rules 
                    SET css_value = ?, updated_at = CURRENT_TIMESTAMP
                    WHERE id = ?
                ");
                
                $updatedCount = 0;
                foreach ($rules as $rule) {
                    if (isset($rule['id']) && isset($rule['css_value'])) {
                        $stmt->execute([$rule['css_value'], $rule['id']]);
                        $updatedCount++;
                    }
                }
                
                $pdo->commit();
                
                echo json_encode([
                    'success' => true,
                    'message' => "Updated $updatedCount CSS rules successfully",
                    'updated_count' => $updatedCount
                ]);
                
            } catch (Exception $e) {
                $pdo->rollBack();
                throw $e;
            }
            break;
            
        default:
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
}

function handlePut($pdo) {
    // Update single rule
    parse_str(file_get_contents("php://input"), $data);
    
    $id = $data['id'] ?? '';
    $cssValue = $data['css_value'] ?? '';
    
    if (empty($id) || empty($cssValue)) {
        echo json_encode(['success' => false, 'error' => 'Missing required fields']);
        return;
    }
    
    $stmt = $pdo->prepare("
        UPDATE global_css_rules 
        SET css_value = ?, updated_at = CURRENT_TIMESTAMP
        WHERE id = ?
    ");
    
    $stmt->execute([$cssValue, $id]);
    
    echo json_encode([
        'success' => true,
        'message' => 'CSS rule updated successfully'
    ]);
}

function handleDelete($pdo) {
    parse_str(file_get_contents("php://input"), $data);
    
    $id = $data['id'] ?? '';
    
    if (empty($id)) {
        echo json_encode(['success' => false, 'error' => 'Missing rule ID']);
        return;
    }
    
    // Soft delete by setting is_active to false
    $stmt = $pdo->prepare("
        UPDATE global_css_rules 
        SET is_active = 0, updated_at = CURRENT_TIMESTAMP
        WHERE id = ?
    ");
    
    $stmt->execute([$id]);
    
    echo json_encode([
        'success' => true,
        'message' => 'CSS rule deleted successfully'
    ]);
}

function generateCSSContent($rules) {
    $css = "/* Global CSS Rules - Generated from Database */\n";
    $css .= "/* Generated on: " . date('Y-m-d H:i:s') . " */\n\n";
    
    $currentCategory = '';
    
    foreach ($rules as $rule) {
        if ($rule['category'] !== $currentCategory) {
            $currentCategory = $rule['category'];
            $css .= "\n/* " . ucfirst($currentCategory) . " */\n";
        }
        
        $css .= ":root {\n";
        $css .= "    --{$rule['rule_name']}: {$rule['css_value']};\n";
        $css .= "}\n\n";
        
        // Also generate utility classes
        $className = str_replace('_', '-', $rule['rule_name']);
        $css .= ".{$className} {\n";
        $css .= "    {$rule['css_property']}: {$rule['css_value']};\n";
        $css .= "}\n\n";
    }
    
    return $css;
}
?> 