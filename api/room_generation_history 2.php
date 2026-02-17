<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth_helper.php';

header('Content-Type: application/json; charset=utf-8');

Response::validateMethod('GET');
AuthHelper::requireAdmin(403, 'Admin access required');

try {
    Database::getInstance();

    $roomNumber = trim((string) ($_GET['room_number'] ?? $_GET['room'] ?? ''));
    if ($roomNumber === '' || !preg_match('/^[0-9A-Za-z]+$/', $roomNumber)) {
        Response::error('room_number is required (alphanumeric)', null, 422);
    }

    $promptRow = Database::queryOne(
        "SELECT id, template_key, prompt_text, variables_json, provider, model, output_type, created_at
         FROM ai_generation_history
         WHERE room_number = ?
           AND status = 'succeeded'
           AND TRIM(COALESCE(prompt_text, '')) <> ''
           AND (output_type = 'room_background' OR TRIM(COALESCE(output_type, '')) = '')
         ORDER BY created_at ASC, id ASC
         LIMIT 1",
        [$roomNumber]
    );

    if (!$promptRow) {
        Response::error('No successful room background generation prompt found for this room', null, 404);
    }

    Response::success([
        'prompt' => [
            'id' => (int) ($promptRow['id'] ?? 0),
            'template_key' => (string) ($promptRow['template_key'] ?? ''),
            'prompt_text' => (string) ($promptRow['prompt_text'] ?? ''),
            'variables_json' => isset($promptRow['variables_json']) ? (string) $promptRow['variables_json'] : null,
            'provider' => isset($promptRow['provider']) ? (string) $promptRow['provider'] : null,
            'model' => isset($promptRow['model']) ? (string) $promptRow['model'] : null,
            'output_type' => isset($promptRow['output_type']) ? (string) $promptRow['output_type'] : null,
            'created_at' => isset($promptRow['created_at']) ? (string) $promptRow['created_at'] : null
        ]
    ]);
} catch (Throwable $e) {
    Response::error($e->getMessage(), null, 500);
}
