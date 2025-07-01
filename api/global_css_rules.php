<?php
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
    
    // Default Global CSS Rules
    $defaultRules = [
        // Brand Colors
        ['rule_name' => 'primary-color', 'css_value' => '#87ac3a', 'category' => 'brand', 'description' => 'Main brand color (WhimsicalFrog green)'],
        ['rule_name' => 'primary-color-hover', 'css_value' => '#6b8e23', 'category' => 'brand', 'description' => 'Primary color hover state'],
        ['rule_name' => 'secondary-color', 'css_value' => '#f3f4f6', 'category' => 'brand', 'description' => 'Secondary background color'],
        ['rule_name' => 'accent-color', 'css_value' => '#3b82f6', 'category' => 'brand', 'description' => 'Accent color for highlights'],
        
        // Typography
        ['rule_name' => 'body-font-family', 'css_value' => '"Inter", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif', 'category' => 'typography', 'description' => 'Main body font family'],
        ['rule_name' => 'heading-font-family', 'css_value' => '"Inter", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif', 'category' => 'typography', 'description' => 'Heading font family'],
        ['rule_name' => 'body-font-size', 'css_value' => '14px', 'category' => 'typography', 'description' => 'Base body font size'],
        ['rule_name' => 'heading-font-size', 'css_value' => '1.5rem', 'category' => 'typography', 'description' => 'Main heading font size'],
        
        // Buttons
        ['rule_name' => 'button-bg-primary', 'css_value' => '#87ac3a', 'category' => 'buttons', 'description' => 'Primary button background'],
        ['rule_name' => 'button-bg-primary-hover', 'css_value' => '#6b8e23', 'category' => 'buttons', 'description' => 'Primary button hover background'],
        ['rule_name' => 'button-bg-secondary', 'css_value' => '#f3f4f6', 'category' => 'buttons', 'description' => 'Secondary button background'],
        ['rule_name' => 'button-text-primary', 'css_value' => '#ffffff', 'category' => 'buttons', 'description' => 'Primary button text color'],
        ['rule_name' => 'button-text-secondary', 'css_value' => '#374151', 'category' => 'buttons', 'description' => 'Secondary button text color'],
        ['rule_name' => 'button-border-radius', 'css_value' => '6px', 'category' => 'buttons', 'description' => 'Button border radius'],
        
        // Layout
        ['rule_name' => 'content-max-width', 'css_value' => '1200px', 'category' => 'layout', 'description' => 'Maximum content width'],
        ['rule_name' => 'section-spacing', 'css_value' => '2rem', 'category' => 'layout', 'description' => 'Section spacing'],
        ['rule_name' => 'element-spacing', 'css_value' => '1rem', 'category' => 'layout', 'description' => 'Element spacing'],
        ['rule_name' => 'border-radius', 'css_value' => '8px', 'category' => 'layout', 'description' => 'Standard border radius'],
        
        // Navigation
        ['rule_name' => 'nav-bg-color', 'css_value' => '#ffffff', 'category' => 'navigation', 'description' => 'Navigation background color'],
        ['rule_name' => 'nav-text-color', 'css_value' => '#374151', 'category' => 'navigation', 'description' => 'Navigation text color'],
        ['rule_name' => 'nav-link-hover', 'css_value' => '#87ac3a', 'category' => 'navigation', 'description' => 'Navigation link hover color'],
        
        // Forms
        ['rule_name' => 'form-input-border', 'css_value' => '#d1d5db', 'category' => 'forms', 'description' => 'Form input border color'],
        ['rule_name' => 'form-input-focus', 'css_value' => '#87ac3a', 'category' => 'forms', 'description' => 'Form input focus color'],
        ['rule_name' => 'form-input-bg', 'css_value' => '#ffffff', 'category' => 'forms', 'description' => 'Form input background'],
        ['rule_name' => 'form-label-color', 'css_value' => '#374151', 'category' => 'forms', 'description' => 'Form label text color'],
        
        // Modals
        ['rule_name' => 'modal-bg-color', 'css_value' => '#ffffff', 'category' => 'modals', 'description' => 'Modal background color'],
        ['rule_name' => 'modal-border-radius', 'css_value' => '12px', 'category' => 'modals', 'description' => 'Modal border radius'],
        ['rule_name' => 'modal-shadow', 'css_value' => '0 25px 50px -12px rgba(0, 0, 0, 0.25)', 'category' => 'modals', 'description' => 'Modal box shadow'],
        ['rule_name' => 'modal-overlay-bg', 'css_value' => 'rgba(0, 0, 0, 0.6)', 'category' => 'modals', 'description' => 'Modal overlay background'],
        
        // Admin Interface
        ['rule_name' => 'admin-header-bg', 'css_value' => '#f8fafc', 'category' => 'admin', 'description' => 'Admin header background'],
        ['rule_name' => 'admin-sidebar-bg', 'css_value' => '#ffffff', 'category' => 'admin', 'description' => 'Admin sidebar background'],
        ['rule_name' => 'admin-content-bg', 'css_value' => '#ffffff', 'category' => 'admin', 'description' => 'Admin content background'],
        ['rule_name' => 'admin-border-color', 'css_value' => '#e5e7eb', 'category' => 'admin', 'description' => 'Admin border color'],
        
        // Order Forms
        ['rule_name' => 'order-form-section-bg', 'css_value' => '#f9fafb', 'category' => 'admin', 'description' => 'Order form section background'],
        ['rule_name' => 'order-item-row-bg', 'css_value' => '#ffffff', 'category' => 'admin', 'description' => 'Order item row background'],
        ['rule_name' => 'order-item-border', 'css_value' => '#e5e7eb', 'category' => 'admin', 'description' => 'Order item border color'],
        ['rule_name' => 'order-form-spacing', 'css_value' => '1.5rem', 'category' => 'admin', 'description' => 'Order form section spacing']
    ];
    
    return $css;
}
?> 