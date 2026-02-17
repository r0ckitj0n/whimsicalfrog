<?php
// SanMar Import API (admin only)
header('Content-Type: application/json');
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/auth_helper.php';
require_once __DIR__ . '/../includes/importers/sanmar_colors_importer.php';

// Decode JSON body once so it can be reused by auth and action handlers
$json = [];

// Enforce admin auth with dev admin_token fallback for iframe usage
try {
    $rawBody = file_get_contents('php://input');
    if ($rawBody !== false && $rawBody !== '') {
        $json = json_decode($rawBody, true) ?: [];
    }
    $token = $_GET['admin_token'] ?? $_POST['admin_token'] ?? ($json['admin_token'] ?? null);
    if (!$token || $token !== (AuthHelper::ADMIN_TOKEN ?? 'whimsical_admin_2024')) {
        AuthHelper::requireAdmin();
    }
} catch (Throwable $____) {
    AuthHelper::requireAdmin();
}

try {
    Database::getInstance();
    $action = $_GET['action'] ?? $_POST['action'] ?? '';

    switch ($action) {
        case 'import_colors':
            $stats = wf_import_sanmar_colors();
            Response::success(['stats' => $stats], 'SanMar colors imported');
            break;
        case 'migrate_strip_prefix':
            Database::beginTransaction();
            try {
                $migration = wf_sanmar_migrate_strip_prefix_and_backfill_codes();
                Database::commit();
            } catch (Throwable $e) {
                Database::rollBack();
                throw $e;
            }
            Response::success(['migration' => $migration], 'SanMar migration complete');
            break;

        default:
            Response::error('Invalid action', null, 400);
    }
} catch (Throwable $e) {
    Response::error($e->getMessage(), null, 500);
}
