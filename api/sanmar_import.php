<?php
// SanMar Import API (admin only)
header('Content-Type: application/json');
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/auth_helper.php';
require_once __DIR__ . '/../includes/importers/sanmar_colors_importer.php';

AuthHelper::requireAdmin();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    Response::error('Method not allowed', null, 405);
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
