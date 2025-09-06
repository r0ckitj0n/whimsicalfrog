<?php
// CSS Rules API – centralized CRUD for global_css_rules
// Uses API bootstrap, Database singleton, Response helper, and AuthHelper

require_once __DIR__ . '/api_bootstrap.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';

// Broaden CORS to include PUT/DELETE for this endpoint
Response::setCorsHeaders(['*'], ['GET','POST','PUT','DELETE','OPTIONS']);

try {
    // All operations require admin
    AuthHelper::requireAdmin();

    $pdo = Database::getInstance();
    $method = Response::getMethod();

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
        case 'OPTIONS':
            // Handled by setCorsHeaders; exit cleanly
            http_response_code(200);
            exit;
        default:
            Response::methodNotAllowed();
    }
} catch (Throwable $e) {
    // Ensure we never leak details to client
    error_log('[css_rules.php] ' . $e->getMessage());
    Response::serverError('Server error');
}

// --- Handlers ---------------------------------------------------------------

function handleGet(PDO $pdo): void
{
    $id = isset($_GET['id']) ? (int)$_GET['id'] : null;
    $category = isset($_GET['category']) ? trim((string)$_GET['category']) : null;
    $activeOnly = isset($_GET['active_only']) && ($_GET['active_only'] === '1' || strtolower((string)$_GET['active_only']) === 'true');

    if ($id) {
        $stmt = $pdo->prepare('SELECT * FROM global_css_rules WHERE id = ?');
        $stmt->execute([$id]);
        $rule = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$rule) {
            Response::notFound('CSS rule not found');
        }
        Response::success(['rule' => $rule]);
    }

    $where = [];
    $params = [];
    if ($category !== null && $category !== '') {
        $where[] = 'category = ?';
        $params[] = $category;
    }
    if ($activeOnly) {
        $where[] = 'is_active = 1';
    }

    $sql = 'SELECT * FROM global_css_rules';
    if (!empty($where)) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY category, rule_name';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rules = $stmt->fetchAll(PDO::FETCH_ASSOC);

    Response::success(['rules' => $rules]);
}

function handlePost(PDO $pdo): void
{
    $data = Response::getPostData(true) ?? [];

    $ruleName = trim((string)($data['rule_name'] ?? ''));
    $cssProperty = trim((string)($data['css_property'] ?? ''));
    $cssValue = trim((string)($data['css_value'] ?? ''));
    $category = trim((string)($data['category'] ?? 'general'));
    $isActive = isset($data['is_active']) ? (int)!!$data['is_active'] : 1;

    $errors = [];
    if ($ruleName === '') { $errors['rule_name'] = 'Required'; }
    if ($cssProperty === '') { $errors['css_property'] = 'Required'; }
    if ($cssValue === '') { $errors['css_value'] = 'Required'; }

    if (!empty($errors)) {
        Response::validationError($errors);
    }

    $stmt = $pdo->prepare('INSERT INTO global_css_rules (rule_name, css_property, css_value, category, is_active, created_at, updated_at) VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)');
    $stmt->execute([$ruleName, $cssProperty, $cssValue, $category, $isActive]);

    $id = (int)$pdo->lastInsertId();
    Response::success(['id' => $id], 'CSS rule created');
}

function handlePut(PDO $pdo): void
{
    $input = file_get_contents('php://input');
    $data = [];
    if ($input !== false && $input !== '') {
        $decoded = json_decode($input, true);
        if (is_array($decoded)) {
            $data = $decoded;
        } else {
            // accept x-www-form-urlencoded
            parse_str($input, $data);
        }
    }

    $id = isset($data['id']) ? (int)$data['id'] : 0;
    if ($id <= 0) {
        Response::validationError(['id' => 'Valid id required']);
    }

    $fields = [];
    $params = [];
    $map = [
        'rule_name' => 'rule_name',
        'css_property' => 'css_property',
        'css_value' => 'css_value',
        'category' => 'category',
        'is_active' => 'is_active',
    ];
    foreach ($map as $key => $col) {
        if (array_key_exists($key, $data)) {
            $fields[] = "$col = ?";
            if ($key === 'is_active') {
                $params[] = (int)!!$data[$key];
            } else {
                $params[] = trim((string)$data[$key]);
            }
        }
    }

    if (empty($fields)) {
        Response::validationError('No fields to update');
    }

    $sql = 'UPDATE global_css_rules SET ' . implode(', ', $fields) . ', updated_at = CURRENT_TIMESTAMP WHERE id = ?';
    $params[] = $id;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    Response::success(null, 'CSS rule updated');
}

function handleDelete(PDO $pdo): void
{
    $input = file_get_contents('php://input');
    $data = [];
    if ($input !== false && $input !== '') {
        $decoded = json_decode($input, true);
        if (is_array($decoded)) {
            $data = $decoded;
        } else {
            parse_str($input, $data);
        }
    }

    $id = isset($data['id']) ? (int)$data['id'] : 0;
    $hard = isset($data['hard']) && ($data['hard'] === '1' || $data['hard'] === 1 || strtolower((string)$data['hard']) === 'true');

    if ($id <= 0) {
        Response::validationError(['id' => 'Valid id required']);
    }

    if ($hard) {
        $stmt = $pdo->prepare('DELETE FROM global_css_rules WHERE id = ?');
        $stmt->execute([$id]);
        Response::success(null, 'CSS rule deleted');
    } else {
        $stmt = $pdo->prepare('UPDATE global_css_rules SET is_active = 0, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
        $stmt->execute([$id]);
        Response::success(null, 'CSS rule soft-deleted');
    }
}
