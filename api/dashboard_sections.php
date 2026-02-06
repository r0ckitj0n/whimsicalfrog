<?php
/**
 * Dashboard Sections Management API
 * Following .windsurfrules: < 300 lines.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth_helper.php';
require_once __DIR__ . '/../includes/dashboard/initializer.php';

@ini_set('display_errors', 0);
@ini_set('html_errors', 0);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS')
    exit(0);

if (!((strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost') !== false) || (strpos($_SERVER['HTTP_HOST'] ?? '', '127.0.0.1') !== false))) {
    AuthHelper::requireAdmin();
}

try {
    $db = Database::getInstance();
    wf_ensure_dashboard_sections_table($db);

    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $action = $_GET['action'] ?? $_POST['action'] ?? $input['action'] ?? 'get_sections';

    switch ($action) {
        case 'get_sections':
            $available = wf_get_available_sections();
            $sections = Database::queryAll('SELECT * FROM dashboard_sections ORDER BY display_order ASC');
            foreach ($sections as &$s) {
                if (isset($available[$s['section_key']])) {
                    $s['section_info'] = $available[$s['section_key']];
                    $s['display_title'] = $s['custom_title'] ?: $available[$s['section_key']]['title'];
                }
            }
            Response::success(['sections' => $sections, 'available_sections' => $available]);
            break;

        case 'update_sections':
            if (!isset($input['sections']))
                Response::error('Missing sections');
            $db->beginTransaction();
            Database::execute('DELETE FROM dashboard_sections');
            $stmt = $db->prepare('INSERT INTO dashboard_sections (section_key, display_order, is_active, show_title, show_description, custom_title, custom_description, width_class) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
            foreach ($input['sections'] as $s) {
                // Determine width class - handle both enum styles
                $width = $s['width_class'] ?? $s['width'] ?? 'half-width';
                if ($width === 'full')
                    $width = 'full-width';
                if ($width === 'half')
                    $width = 'half-width';
                if ($width === 'third')
                    $width = 'third-width';

                $stmt->execute([
                    $s['section_key'],
                    $s['display_order'] ?? 0,
                    isset($s['is_active']) ? ($s['is_active'] ? 1 : 0) : 1,
                    isset($s['show_title']) ? ($s['show_title'] ? 1 : 0) : 1,
                    isset($s['show_description']) ? ($s['show_description'] ? 1 : 0) : 1,
                    $s['custom_title'] ?? null,
                    $s['custom_description'] ?? null,
                    $width
                ]);
            }
            $db->commit();
            Response::success(['message' => 'Updated successfully']);
            break;

        case 'update_width':
            if (!isset($input['section_key']) || !isset($input['width_class']))
                Response::error('Missing parameters');
            Database::execute('UPDATE dashboard_sections SET width_class = ? WHERE section_key = ?', [$input['width_class'], $input['section_key']]);
            Response::success(['message' => 'Width updated']);
            break;

        case 'toggle_visibility':
            if (!isset($input['section_key']))
                Response::error('Missing section_key');
            $current = Database::queryOne('SELECT is_active FROM dashboard_sections WHERE section_key = ?', [$input['section_key']]);
            $newStatus = ($current && $current['is_active']) ? 0 : 1;
            Database::execute('UPDATE dashboard_sections SET is_active = ? WHERE section_key = ?', [$newStatus, $input['section_key']]);
            Response::success(['message' => 'Visibility toggled', 'is_active' => $newStatus]);
            break;

        case 'reset_defaults':
            $db->beginTransaction();
            Database::execute('DELETE FROM dashboard_sections');
            $defaults = [
                ['key' => 'order_fulfillment', 'width' => 'full-width'],
                ['key' => 'recent_orders', 'width' => 'half-width'],
                ['key' => 'metrics', 'width' => 'half-width'],
                ['key' => 'low_stock', 'width' => 'half-width'],
                ['key' => 'inventory_summary', 'width' => 'half-width'],
                ['key' => 'customer_summary', 'width' => 'half-width'],
                ['key' => 'marketing_tools', 'width' => 'half-width'],
                ['key' => 'reports_summary', 'width' => 'half-width']
            ];
            foreach ($defaults as $i => $d) {
                Database::execute('INSERT INTO dashboard_sections (section_key, display_order, width_class) VALUES (?, ?, ?)', [$d['key'], $i + 1, $d['width']]);
            }
            $db->commit();
            Response::success(['message' => 'Defaults restored']);
            break;

        default:
            Response::error('Invalid action', null, 400);
    }
} catch (Exception $e) {
    Response::serverError($e->getMessage());
}
