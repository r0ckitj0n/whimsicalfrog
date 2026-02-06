<?php
/**
 * api/secrets.php
 * Secure Secrets API for Admin Settings
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/auth_helper.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/secret_store.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/helpers/SecretsHelper.php';

AuthHelper::requireAdmin();

$action = $_GET['action'] ?? $_POST['action'] ?? '';
header('Content-Type: application/json');

try {
    switch ($action) {
        case 'save_batch':
            if (!csrf_validate('admin_secrets', $_POST['csrf'] ?? $_GET['csrf'] ?? '')) {
                Response::error('Invalid CSRF token', null, 400);
            }
            $raw = $_POST['payload'] ?? file_get_contents('php://input');
            $map = SecretsHelper::parsePayload($raw);
            $saved = 0; $deleted = 0;
            foreach ($map as $k => $v) {
                $key = trim((string)$k); if ($key === '') continue;
                $val = (string)$v;
                if ($val === '') { if (secret_delete($key)) $deleted++; }
                elseif (secret_set($key, $val)) $saved++;
            }
            echo json_encode(['success' => true, 'saved' => $saved, 'deleted' => $deleted]);
            break;

        case 'rotate_keys':
            if (!csrf_validate('admin_secrets', $_POST['csrf'] ?? $_GET['csrf'] ?? '')) {
                Response::error('Invalid CSRF token', null, 400);
            }
            Database::getInstance();
            echo json_encode(array_merge(['success' => true], SecretsHelper::rotateKeys()));
            break;

        case 'export':
            Database::getInstance();
            $rows = Database::queryAll('SELECT `key` FROM secrets ORDER BY `key` ASC');
            echo json_encode(['success' => true, 'keys' => array_map(fn($r) => $r['key'], $rows ?: [])]);
            break;

        case 'list':
            Database::getInstance();
            $rows = Database::queryAll('SELECT `key`, created_at, updated_at FROM secrets ORDER BY `key` ASC');
            echo json_encode(['success' => true, 'secrets' => array_map(fn($r) => array_merge($r, ['has_value' => true]), $rows ?: [])]);
            break;

        case 'set':
            if (!csrf_validate('admin_secrets', $_POST['csrf'] ?? $_GET['csrf'] ?? '')) {
                Response::error('Invalid CSRF token', null, 400);
            }
            $key = trim($_POST['key'] ?? '');
            if (!$key) Response::error('Key required', null, 400);
            if (secret_set($key, $_POST['value'] ?? '')) echo json_encode(['success' => true]);
            else Response::error('Save failed', null, 500);
            break;

        case 'delete':
            if (!csrf_validate('admin_secrets', $_POST['csrf'] ?? $_GET['csrf'] ?? '')) {
                Response::error('Invalid CSRF token', null, 400);
            }
            $key = trim($_POST['key'] ?? '');
            if (!$key) Response::error('Key required', null, 400);
            if (secret_delete($key) || !secret_has($key)) echo json_encode(['success' => true]);
            else Response::error('Delete failed', null, 500);
            break;

        default:
            Response::error('Invalid action', null, 400);
    }
} catch (Throwable $e) { Response::serverError($e->getMessage()); }
