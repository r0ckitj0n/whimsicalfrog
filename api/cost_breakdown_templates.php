<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

// Use centralized authentication
// Admin authentication with token fallback for API access
    $isAdmin = false;
    
    // Check admin authentication using centralized helper
    AuthHelper::requireAdmin();

// Authentication is handled by requireAdmin() above
$userData = getCurrentUser();

try {
    try { $pdo = Database::getInstance(); } catch (Exception $e) { error_log("Database connection failed: " . $e->getMessage()); throw $e; }
    
    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? $_POST['action'] ?? '';
    
    switch ($method) {
        case 'GET':
            if ($action === 'list') {
                // Get all templates
                $category = $_GET['category'] ?? '';
                $sql = "SELECT * FROM cost_breakdown_templates";
                $params = [];
                
                if (!empty($category)) {
                    $sql .= " WHERE category = ?";
                    $params[] = $category;
                }
                
                $sql .= " ORDER BY category, template_name";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $templates = $stmt->fetchAll();
                
                // Parse JSON fields
                foreach ($templates as &$template) {
                    $template['materials'] = json_decode($template['materials'] ?? '[]', true);
                    $template['labor'] = json_decode($template['labor'] ?? '[]', true);
                    $template['energy'] = json_decode($template['energy'] ?? '[]', true);
                    $template['equipment'] = json_decode($template['equipment'] ?? '[]', true);
                }
                
                echo json_encode(['success' => true, 'templates' => $templates]);
                
            } elseif ($action === 'get' && isset($_GET['id'])) {
                // Get specific template
                $stmt = $pdo->prepare("SELECT * FROM cost_breakdown_templates WHERE id = ?");
                $stmt->execute([$_GET['id']]);
                $template = $stmt->fetch();
                
                if ($template) {
                    // Parse JSON fields
                    $template['materials'] = json_decode($template['materials'] ?? '[]', true);
                    $template['labor'] = json_decode($template['labor'] ?? '[]', true);
                    $template['energy'] = json_decode($template['energy'] ?? '[]', true);
                    $template['equipment'] = json_decode($template['equipment'] ?? '[]', true);
                    
                    echo json_encode(['success' => true, 'template' => $template]);
                } else {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'error' => 'Template not found']);
                }
                
            } else {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Invalid action for GET request']);
            }
            break;
            
        case 'POST':
            $input = json_decode(file_get_contents('php://input'), true);
            
            if ($action === 'create') {
                // Create new template
                $stmt = $pdo->prepare("
                    INSERT INTO cost_breakdown_templates 
                    (template_name, description, category, materials, labor, energy, equipment) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                
                $stmt->execute([
                    $input['template_name'],
                    $input['description'] ?? '',
                    $input['category'] ?? '',
                    json_encode($input['materials'] ?? []),
                    json_encode($input['labor'] ?? []),
                    json_encode($input['energy'] ?? []),
                    json_encode($input['equipment'] ?? [])
                ]);
                
                $templateId = $pdo->lastInsertId();
                echo json_encode(['success' => true, 'template_id' => $templateId, 'message' => 'Template created successfully']);
                
            } elseif ($action === 'save_from_breakdown') {
                // Save current cost breakdown as template
                $templateName = $input['template_name'] ?? '';
                $description = $input['description'] ?? '';
                $category = $input['category'] ?? '';
                $sku = $input['sku'] ?? '';
                
                if (empty($templateName)) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Template name is required']);
                    exit;
                }
                
                // Get current cost breakdown data for the SKU
                $costData = [
                    'materials' => [],
                    'labor' => [],
                    'energy' => [],
                    'equipment' => []
                ];
                
                if (!empty($sku)) {
                    // Fetch existing cost breakdown data
                    // Materials table uses 'name' field, others use 'description'
                    $stmt = $pdo->prepare("SELECT name, cost FROM inventory_materials WHERE sku = ?");
                    $stmt->execute([$sku]);
                    $costData['materials'] = $stmt->fetchAll();
                    
                    $costTables = ['labor', 'energy', 'equipment'];
                    foreach ($costTables as $table) {
                        $tableName = "inventory_$table";
                        $stmt = $pdo->prepare("SELECT description as name, cost FROM $tableName WHERE sku = ?");
                        $stmt->execute([$sku]);
                        $costData[$table] = $stmt->fetchAll();
                    }
                }
                
                // Create the template
                $stmt = $pdo->prepare("
                    INSERT INTO cost_breakdown_templates 
                    (template_name, description, category, materials, labor, energy, equipment) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                
                $stmt->execute([
                    $templateName,
                    $description,
                    $category,
                    json_encode($costData['materials']),
                    json_encode($costData['labor']),
                    json_encode($costData['energy']),
                    json_encode($costData['equipment'])
                ]);
                
                $templateId = $pdo->lastInsertId();
                echo json_encode(['success' => true, 'template_id' => $templateId, 'message' => 'Template saved successfully']);
                
            } else {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Invalid action for POST request']);
            }
            break;
            
        case 'PUT':
            $input = json_decode(file_get_contents('php://input'), true);
            $templateId = $input['id'] ?? '';
            
            if (empty($templateId)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Template ID is required']);
                exit;
            }
            
            // Update template
            $stmt = $pdo->prepare("
                UPDATE cost_breakdown_templates 
                SET template_name = ?, description = ?, category = ?, 
                    materials = ?, labor = ?, energy = ?, equipment = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            
            $result = $stmt->execute([
                $input['template_name'],
                $input['description'] ?? '',
                $input['category'] ?? '',
                json_encode($input['materials'] ?? []),
                json_encode($input['labor'] ?? []),
                json_encode($input['energy'] ?? []),
                json_encode($input['equipment'] ?? []),
                $templateId
            ]);
            
            if ($result) {
                echo json_encode(['success' => true, 'message' => 'Template updated successfully']);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Failed to update template']);
            }
            break;
            
        case 'DELETE':
            $templateId = $_GET['id'] ?? '';
            
            if (empty($templateId)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Template ID is required']);
                exit;
            }
            
            // Delete template
            $stmt = $pdo->prepare("DELETE FROM cost_breakdown_templates WHERE id = ?");
            $result = $stmt->execute([$templateId]);
            
            if ($result) {
                echo json_encode(['success' => true, 'message' => 'Template deleted successfully']);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Failed to delete template']);
            }
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Method not allowed']);
            break;
    }
    
} catch (Exception $e) {
    error_log("Error in cost_breakdown_templates.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Internal server error occurred.']);
}
?> 