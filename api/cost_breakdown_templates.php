<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth_helper.php';

header('Content-Type: application/json');

// Use centralized authentication
// Admin authentication with token fallback for API access
$isAdmin = false;

// Check admin authentication using centralized helper
AuthHelper::requireAdmin();

// Authentication is handled by requireAdmin() above
$userData = getCurrentUser();

try {
    try {
        $pdo = Database::getInstance();
    } catch (Exception $e) {
        error_log("Database connection failed: " . $e->getMessage());
        throw $e;
    }

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
                $templates = Database::queryAll($sql, $params);

                // Parse JSON fields
                foreach ($templates as &$template) {
                    $template['materials'] = json_decode($template['materials'] ?? '[]', true);
                    $template['labor'] = json_decode($template['labor'] ?? '[]', true);
                    $template['energy'] = json_decode($template['energy'] ?? '[]', true);
                    $template['equipment'] = json_decode($template['equipment'] ?? '[]', true);
                }

                Response::json(['success' => true, 'templates' => $templates]);

            } elseif ($action === 'get' && isset($_GET['id'])) {
                // Get specific template
                $template = Database::queryOne("SELECT * FROM cost_breakdown_templates WHERE id = ?", [$_GET['id']]);

                if ($template) {
                    // Parse JSON fields
                    $template['materials'] = json_decode($template['materials'] ?? '[]', true);
                    $template['labor'] = json_decode($template['labor'] ?? '[]', true);
                    $template['energy'] = json_decode($template['energy'] ?? '[]', true);
                    $template['equipment'] = json_decode($template['equipment'] ?? '[]', true);

                    Response::json(['success' => true, 'template' => $template]);
                } else {
                    Response::error('Template not found', null, 404);
                }

            } else {
                Response::error('Invalid action for GET request', null, 400);
            }
            break;

        case 'POST':
            $input = json_decode(file_get_contents('php://input'), true);

            if ($action === 'create') {
                // Create new template
                Database::execute("
                    INSERT INTO cost_breakdown_templates 
                    (template_name, description, category, materials, labor, energy, equipment) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ", [
                    $input['template_name'],
                    $input['description'] ?? '',
                    $input['category'] ?? '',
                    json_encode($input['materials'] ?? []),
                    json_encode($input['labor'] ?? []),
                    json_encode($input['energy'] ?? []),
                    json_encode($input['equipment'] ?? [])
                ]);

                $templateId = Database::lastInsertId();
                Response::json(['success' => true, 'template_id' => $templateId, 'message' => 'Template created successfully']);

            } elseif ($action === 'save_from_breakdown') {
                // Save current cost breakdown as template
                $templateName = $input['template_name'] ?? '';
                $description = $input['description'] ?? '';
                $category = $input['category'] ?? '';
                $sku = $input['sku'] ?? '';

                if (empty($templateName)) {
                    Response::error('Template name is required', null, 400);
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
                    $costData['materials'] = Database::queryAll("SELECT name, cost FROM inventory_materials WHERE sku = ?", [$sku]);

                    $costTables = ['labor', 'energy', 'equipment'];
                    foreach ($costTables as $table) {
                        $tableName = "inventory_$table";
                        $costData[$table] = Database::queryAll("SELECT description as name, cost FROM $tableName WHERE sku = ?", [$sku]);
                    }
                }

                // Create the template
                Database::execute("
                    INSERT INTO cost_breakdown_templates 
                    (template_name, description, category, materials, labor, energy, equipment) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ", [
                    $templateName,
                    $description,
                    $category,
                    json_encode($costData['materials']),
                    json_encode($costData['labor']),
                    json_encode($costData['energy']),
                    json_encode($costData['equipment'])
                ]);

                $templateId = Database::lastInsertId();
                Response::json(['success' => true, 'template_id' => $templateId, 'message' => 'Template saved successfully']);

            } else {
                Response::error('Invalid action for POST request', null, 400);
            }
            break;

        case 'PUT':
            $input = json_decode(file_get_contents('php://input'), true);
            $templateId = $input['id'] ?? '';

            if (empty($templateId)) {
                Response::error('Template ID is required', null, 400);
            }

            // Update template
            $affected = Database::execute("
                UPDATE cost_breakdown_templates 
                SET template_name = ?, description = ?, category = ?, 
                    materials = ?, labor = ?, energy = ?, equipment = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ", [
                $input['template_name'],
                $input['description'] ?? '',
                $input['category'] ?? '',
                json_encode($input['materials'] ?? []),
                json_encode($input['labor'] ?? []),
                json_encode($input['energy'] ?? []),
                json_encode($input['equipment'] ?? []),
                $templateId
            ]);

            if ($affected !== false) {
                Response::json(['success' => true, 'message' => 'Template updated successfully']);
            } else {
                Response::error('Failed to update template', null, 500);
            }
            break;

        case 'DELETE':
            $templateId = $_GET['id'] ?? '';

            if (empty($templateId)) {
                Response::error('Template ID is required', null, 400);
            }

            // Delete template
            $affected = Database::execute("DELETE FROM cost_breakdown_templates WHERE id = ?", [$templateId]);
            if ($affected !== false) {
                Response::json(['success' => true, 'message' => 'Template deleted successfully']);
            } else {
                Response::error('Failed to delete template', null, 500);
            }
            break;

        default:
            Response::methodNotAllowed();
            break;
    }

} catch (Exception $e) {
    error_log("Error in cost_breakdown_templates.php: " . $e->getMessage());
    Response::serverError('Internal server error occurred.');
}
?> 