<?php

// CSS File Editor API - Read and write CSS files
// Allows in-browser editing of CSS files with security validation

require_once __DIR__ . '/api_bootstrap.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/auth_helper.php';

Response::setCorsHeaders(['*'], ['GET', 'POST', 'OPTIONS']);

try {
    // All operations require admin
    AuthHelper::requireAdmin();

    $method = Response::getMethod();

    switch ($method) {
        case 'GET':
            handleGet();
            break;
        case 'POST':
            handlePost();
            break;
        case 'OPTIONS':
            http_response_code(200);
            exit;
        default:
            Response::methodNotAllowed();
    }
} catch (Throwable $e) {
    error_log('[css_file_editor.php] ' . $e->getMessage());
    Response::serverError('Server error');
}

// --- Handlers ---------------------------------------------------------------

function handleGet(): void
{
    $file = $_GET['file'] ?? '';

    if (empty($file)) {
        Response::validationError(['file' => 'File path is required']);
    }

    // Security: Validate path is within src/styles directory
    // Project root is one level up from /api/
    $projectRoot = realpath(__DIR__ . '/..');
    $stylesDir = $projectRoot . '/src/styles';

    // Build the full path - file should be relative to project root (e.g., "src/styles/components/about.css")
    $fullPath = $projectRoot . '/' . ltrim($file, '/');

    // Resolve to absolute path
    $requestedPath = realpath($fullPath);

    // Check if file exists
    if (!$requestedPath || !file_exists($requestedPath)) {
        Response::notFound('File not found: ' . $file);
    }

    // Validate it's within src/styles directory
    if (strpos($requestedPath, realpath($stylesDir)) !== 0) {
        Response::forbidden('Access denied: File must be within src/styles directory');
    }

    // Read file contents
    $content = file_get_contents($requestedPath);

    if ($content === false) {
        Response::serverError('Failed to read file');
    }

    Response::success([
        'content' => $content,
        'path' => $file,
        'absolutePath' => $requestedPath
    ]);
}

function handlePost(): void
{
    $data = Response::getPostData(true) ?? [];

    $file = trim((string) ($data['file'] ?? ''));
    $content = $data['content'] ?? '';

    if (empty($file)) {
        Response::validationError(['file' => 'File path is required']);
    }

    // Security: Validate path is within src/styles directory
    $projectRoot = realpath(__DIR__ . '/..');
    $stylesDir = $projectRoot . '/src/styles';

    // Build the full path
    $fullPath = $projectRoot . '/' . ltrim($file, '/');

    // Resolve to absolute path
    $requestedPath = realpath($fullPath);

    if (!$requestedPath || !file_exists($requestedPath)) {
        Response::notFound('File not found');
    }

    if (strpos($requestedPath, realpath($stylesDir)) !== 0) {
        Response::forbidden('Access denied: File must be within src/styles directory');
    }

    // Create backup before saving
    $backupPath = $requestedPath . '.backup.' . date('YmdHis');
    if (!copy($requestedPath, $backupPath)) {
        error_log('[css_file_editor.php] Failed to create backup: ' . $backupPath);
    }

    // Write new content
    $result = file_put_contents($requestedPath, $content);

    if ($result === false) {
        Response::serverError('Failed to save file');
    }

    Response::success([
        'saved' => true,
        'bytes' => $result,
        'backup' => basename($backupPath)
    ], 'File saved successfully');
}
