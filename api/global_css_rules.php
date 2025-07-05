<?php

require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

// Check if this is a public CSS generation request
$action = $_GET['action'] ?? $_POST['action'] ?? '';
$isPublicAction = ($action === 'generate_css');

if (!$isPublicAction) {
    // Use centralized authentication for admin actions with admin token fallback
    $isAdmin = false;
    
    // Check admin authentication using centralized helper
    AuthHelper::requireAdmin();
    
    $userData = getCurrentUser();
} else {
    // Allow public access for CSS generation
    $userData = null;
}
$method = $_SERVER['REQUEST_METHOD'];

try {
    $pdo = Database::getInstance();

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
// handlePost function moved to api_handlers_extended.php for centralization

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
    $groupedRules = [];
    
    // Group rules by selector
    foreach ($rules as $rule) {
        $selector = '';
        
        // Extract selector from rule_name - handle both formats:
        // 1. ".selector { property }" format
        // 2. ".selector" format (simple selector)
        if (preg_match('/^(.+?)\s*\{\s*(.+?)\s*\}$/', $rule['rule_name'], $matches)) {
            $selector = trim($matches[1]);
        } else {
            // Simple selector format - just use the rule_name as selector
            $selector = trim($rule['rule_name']);
        }
        
        if (!empty($selector)) {
            if (!isset($groupedRules[$selector])) {
                $groupedRules[$selector] = [];
            }
            
            $groupedRules[$selector][] = [
                'property' => $rule['css_property'],
                'value' => $rule['css_value'],
                'category' => $rule['category']
            ];
        }
    }
    
    // Generate CSS from grouped rules
    $currentCategory = '';
    foreach ($groupedRules as $selector => $properties) {
        // Add category comment
        if (!empty($properties) && $properties[0]['category'] !== $currentCategory) {
            $currentCategory = $properties[0]['category'];
            $css .= "/* " . ucfirst($currentCategory) . " */\n";
        }
        
        $css .= "{$selector} {\n";
        foreach ($properties as $prop) {
            $css .= "    {$prop['property']}: {$prop['value']};\n";
        }
        $css .= "}\n\n";
    }
    
    return $css;
}

function handleGet($pdo) {
    $action = $_GET['action'] ?? '';
    
    switch ($action) {
        case 'generate_css':
            // Generate CSS content for public use
            try {
                $rules = $pdo->query("
                    SELECT rule_name, css_property, css_value, category 
                    FROM global_css_rules 
                    WHERE is_active = 1 
                    ORDER BY category, rule_name
                ")->fetchAll(PDO::FETCH_ASSOC);
                
                $cssContent = generateCSSContent($rules);
                
                echo json_encode([
                    'success' => true,
                    'css_content' => $cssContent
                ]);
            } catch (Exception $e) {
                echo json_encode([
                    'success' => false,
                    'error' => 'Failed to generate CSS: ' . $e->getMessage()
                ]);
            }
            break;
            
        case 'get_all':
            // Get all CSS rules for admin
            try {
                $rules = $pdo->query("
                    SELECT * FROM global_css_rules 
                    ORDER BY category, rule_name
                ")->fetchAll(PDO::FETCH_ASSOC);
                
                echo json_encode([
                    'success' => true,
                    'rules' => $rules
                ]);
            } catch (Exception $e) {
                echo json_encode([
                    'success' => false,
                    'error' => 'Failed to get CSS rules: ' . $e->getMessage()
                ]);
            }
            break;
            
        default:
            echo json_encode([
                'success' => false,
                'error' => 'Invalid action'
            ]);
    }
}
?> 