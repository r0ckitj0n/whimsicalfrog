<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth_helper.php';
require_once __DIR__ . '/../includes/helpers/ImageUploadHelper.php';
require_once __DIR__ . '/../includes/secret_store.php';

@ini_set('max_execution_time', '180');
@set_time_limit(180);

header('Content-Type: application/json; charset=utf-8');

function wf_ai_prompt_tables_init(): void
{
    Database::execute("CREATE TABLE IF NOT EXISTS ai_prompt_templates (
        id INT AUTO_INCREMENT PRIMARY KEY,
        template_key VARCHAR(120) NOT NULL UNIQUE,
        template_name VARCHAR(180) NOT NULL,
        description TEXT NULL,
        context_type VARCHAR(80) NOT NULL DEFAULT 'generic',
        prompt_text LONGTEXT NOT NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_context_active (context_type, is_active)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    Database::execute("CREATE TABLE IF NOT EXISTS ai_prompt_variables (
        id INT AUTO_INCREMENT PRIMARY KEY,
        variable_key VARCHAR(120) NOT NULL UNIQUE,
        display_name VARCHAR(180) NOT NULL,
        description TEXT NULL,
        sample_value VARCHAR(255) NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    Database::execute("CREATE TABLE IF NOT EXISTS ai_generation_history (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        template_key VARCHAR(120) NOT NULL,
        prompt_text LONGTEXT NOT NULL,
        variables_json LONGTEXT NULL,
        provider VARCHAR(80) NULL,
        model VARCHAR(180) NULL,
        status VARCHAR(40) NOT NULL DEFAULT 'queued',
        output_type VARCHAR(80) NULL,
        output_path VARCHAR(255) NULL,
        room_number VARCHAR(40) NULL,
        error_message TEXT NULL,
        created_by VARCHAR(120) NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_template_created (template_key, created_at),
        INDEX idx_status_created (status, created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function wf_ai_prompt_seed_defaults(): void
{
    $defaultTemplate = <<<'PROMPT'
A high-quality 3D cartoon render for room {{room_number}}.
Room name: {{room_name}}.
Door label: {{door_label}}.
Display order: {{display_order}}.
Room description/context: {{room_description}}.

Create a themed {{room_theme}} corner inside the whimsical frog’s cottage.

The area features prominent {{display_furniture_style}} intended for future product placement.
CRITICAL CONSTRAINT: The main surfaces of these displays must remain completely flat and empty.
Include small, non-obtrusive {{thematic_accent_decorations}} placed intermittently as separators/bookends, leaving clear open spaces between accents.

The signature fedora-wearing 3D cartoon frog is present as the proprietor. He is depicted {{frog_action}}, surveying his shop with pride.

Atmosphere: {{vibe_adjectives}}.
Color palette: {{color_scheme}}.
Background walls/ceiling include decorative oversized 3D {{background_thematic_elements}} that reinforce the room's function.

Art style: modern 3D children's cartoon animation (Pixar-esque).
Surfaces: smooth, vibrant, saturated colors, clean presentation.
Text constraint: strictly NO TEXT anywhere in the image.
Lighting: bright and inviting, highlighting empty display surface textures for product insertion.
PROMPT;

    Database::execute(
        "INSERT INTO ai_prompt_templates (template_key, template_name, description, context_type, prompt_text, is_active)
         VALUES (?, ?, ?, ?, ?, 1)
         ON DUPLICATE KEY UPDATE
            description = VALUES(description),
            context_type = VALUES(context_type),
            prompt_text = VALUES(prompt_text),
            is_active = VALUES(is_active)",
        [
            'room_staging_empty_shelves_v1',
            'Whimsical Frog Room (Empty Shelves)',
            'Generates a room background with empty staging surfaces and thematic separators.',
            'room_generation',
            $defaultTemplate
        ]
    );

    $variables = [
        ['room_number', 'Room Number', 'Room identifier that the generated image belongs to.', '7'],
        ['room_name', 'Room Name', 'Human-friendly room name from the room setup form.', 'Holiday Collection'],
        ['door_label', 'Door Label', 'Short label displayed on the room door.', 'Holidays'],
        ['display_order', 'Display Order', 'Room order index in navigation.', '10'],
        ['room_description', 'Room Description', 'Freeform room description from room setup.', 'A cozy holiday gift room with warm seasonal accents.'],
        ['room_theme', 'Room Theme / Business Type', 'General room purpose (cozy cafe, magical apothecary, artisan bakery).', 'cozy cafe'],
        ['display_furniture_style', 'Display Furniture Style', 'Type of empty display structures used in the room.', 'tiered light-wood shelving units'],
        ['thematic_accent_decorations', 'Thematic Accent Decorations', 'Small non-product separators/bookends placed intermittently.', 'tiny potted succulents and miniature ceramic milk jugs'],
        ['frog_action', 'Generic Thematic Action', 'What the frog proprietor is doing in-scene.', 'wiping down the empty counter with a cloth'],
        ['vibe_adjectives', 'Vibe Adjectives', 'Atmosphere mood words.', 'refreshing and bright'],
        ['color_scheme', 'Color Scheme Combinations', 'Dominant color pairings for the scene.', "robin's egg blue and soft orange"],
        ['background_thematic_elements', 'Background Thematic Elements', 'Large decor elements on walls/ceiling to establish context.', 'giant floating fruit shapes'],
    ];

    foreach ($variables as $v) {
        Database::execute(
            "INSERT INTO ai_prompt_variables (variable_key, display_name, description, sample_value, is_active)
             VALUES (?, ?, ?, ?, 1)
             ON DUPLICATE KEY UPDATE
                display_name = VALUES(display_name),
                description = VALUES(description),
                sample_value = VALUES(sample_value),
                is_active = VALUES(is_active)",
            [$v[0], $v[1], $v[2], $v[3]]
        );
    }
}

function wf_get_current_user_id(): ?string
{
    $user = AuthHelper::getCurrentUser();
    if (!is_array($user)) {
        return null;
    }
    $id = (string) ($user['user_id'] ?? $user['id'] ?? $user['username'] ?? '');
    return $id !== '' ? $id : null;
}

function wf_log_generation_history(array $payload): ?int
{
    try {
        Database::execute(
            'INSERT INTO ai_generation_history (template_key, prompt_text, variables_json, provider, model, status, output_type, output_path, room_number, error_message, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                (string) ($payload['template_key'] ?? ''),
                (string) ($payload['prompt_text'] ?? ''),
                (string) ($payload['variables_json'] ?? '{}'),
                (string) ($payload['provider'] ?? ''),
                (string) ($payload['model'] ?? ''),
                (string) ($payload['status'] ?? 'queued'),
                (string) ($payload['output_type'] ?? 'room_background'),
                (string) ($payload['output_path'] ?? ''),
                (string) ($payload['room_number'] ?? ''),
                (string) ($payload['error_message'] ?? ''),
                (string) ($payload['created_by'] ?? '')
            ]
        );
        return (int) Database::lastInsertId();
    } catch (Throwable $e) {
        error_log('generate_room_image history log failed: ' . $e->getMessage());
        return null;
    }
}

function wf_openai_generate_image(string $apiKey, string $model, string $prompt, string $size): array
{
    $baseBody = [
        'model' => $model,
        'prompt' => $prompt,
        'size' => $size,
        'n' => 1
    ];
    $attemptBodies = [
        $baseBody + ['response_format' => 'b64_json'],
        $baseBody
    ];

    $lastError = 'OpenAI image generation failed';
    foreach ($attemptBodies as $attemptIndex => $body) {
        $ch = curl_init('https://api.openai.com/v1/images/generations');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json'
            ],
            CURLOPT_TIMEOUT => 120,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_POSTFIELDS => json_encode($body, JSON_UNESCAPED_SLASHES)
        ]);

        $raw = curl_exec($ch);
        $curlErr = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($raw === false) {
            throw new RuntimeException('OpenAI request failed: ' . $curlErr);
        }

        $decoded = json_decode($raw, true);
        if ($status >= 400) {
            $message = is_array($decoded) ? (($decoded['error']['message'] ?? '') ?: ($decoded['error'] ?? '')) : '';
            $fallback = is_string($raw) ? $raw : 'Unknown OpenAI error';
            $lastError = 'OpenAI image generation failed (HTTP ' . $status . '): ' . ($message ?: $fallback);

            $isResponseFormatIssue = $attemptIndex === 0
                && is_string($message)
                && stripos($message, 'response_format') !== false;
            if ($isResponseFormatIssue) {
                continue;
            }
            throw new RuntimeException($lastError);
        }

        if (!is_array($decoded)) {
            throw new RuntimeException('OpenAI image response was not valid JSON');
        }

        return $decoded;
    }

    throw new RuntimeException($lastError);
}

function wf_download_image_to_temp(string $url): string
{
    $tmp = tempnam(sys_get_temp_dir(), 'wf-img-url-');
    if (!$tmp) {
        throw new RuntimeException('Unable to create temporary file for image download');
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 120,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_FOLLOWLOCATION => true
    ]);
    $body = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);

    if ($body === false || $status >= 400) {
        @unlink($tmp);
        throw new RuntimeException('Failed to download generated image: ' . ($err ?: ('HTTP ' . $status)));
    }

    file_put_contents($tmp, $body);
    return $tmp;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    Response::json(['success' => true]);
}

Response::validateMethod('POST');
AuthHelper::requireAdmin(403, 'Admin access required');

$templateKeyForLog = '';
$promptForLog = '';
$variablesJsonForLog = '{}';
$roomNumberForLog = '';
$modelForLog = '';
$providerForLog = 'openai';
$createdByForLog = wf_get_current_user_id();

try {
    Database::getInstance();
    wf_ai_prompt_tables_init();
    wf_ai_prompt_seed_defaults();

    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $templateKey = trim((string) ($input['template_key'] ?? 'room_staging_empty_shelves_v1'));
    $roomParam = trim((string) ($input['room_number'] ?? $input['room'] ?? ''));
    $provider = strtolower(trim((string) ($input['provider'] ?? 'openai')));
    $size = trim((string) ($input['size'] ?? '1536x1024'));
    $backgroundNameInput = trim((string) ($input['background_name'] ?? ''));

    if ($roomParam === '' || !preg_match('/^[0-9a-zA-Z]+$/', $roomParam)) {
        Response::error('room_number is required (alphanumeric)', null, 422);
    }

    if ($provider !== 'openai') {
        Response::error('Only provider=openai is supported in this endpoint right now', null, 422);
    }

    $allowedSizes = ['1024x1024', '1536x1024', '1024x1536'];
    if (!in_array($size, $allowedSizes, true)) {
        Response::error('Invalid size. Allowed: ' . implode(', ', $allowedSizes), null, 422);
    }

    if ($templateKey === '') {
        Response::error('template_key is required', null, 422);
    }

    $template = Database::queryOne(
        'SELECT template_key, prompt_text FROM ai_prompt_templates WHERE template_key = ? AND is_active = 1 LIMIT 1',
        [$templateKey]
    );
    if (!$template) {
        Response::error('Template not found or inactive', null, 404);
    }

    $defaults = [];
    $rows = Database::queryAll('SELECT variable_key, sample_value FROM ai_prompt_variables WHERE is_active = 1');
    foreach ($rows as $row) {
        $key = (string) ($row['variable_key'] ?? '');
        if ($key !== '') {
            $defaults[$key] = (string) ($row['sample_value'] ?? '');
        }
    }

    $incomingVariables = is_array($input['variables'] ?? null) ? $input['variables'] : [];
    $resolved = $defaults;
    foreach ($incomingVariables as $key => $value) {
        $resolved[(string) $key] = trim((string) $value);
    }

    $prompt = (string) ($template['prompt_text'] ?? '');
    foreach ($resolved as $key => $value) {
        $prompt = str_replace('{{' . $key . '}}', $value, $prompt);
    }

    $settingsRows = Database::queryAll("SELECT setting_key, setting_value FROM business_settings WHERE category = 'ai'");
    $settings = [];
    foreach ($settingsRows as $row) {
        $settings[(string) $row['setting_key']] = $row['setting_value'];
    }

    $apiKey = (string) (secret_get('openai_api_key') ?? $settings['openai_api_key'] ?? '');
    if ($apiKey === '') {
        throw new RuntimeException('OpenAI API key is missing in AI settings');
    }

    $model = trim((string) ($input['model'] ?? $settings['openai_image_model'] ?? 'gpt-image-1'));
    if ($model === '') {
        $model = 'gpt-image-1';
    }

    $roomType = str_starts_with(strtolower($roomParam), 'room') ? 'room' . substr($roomParam, 4) : 'room' . $roomParam;
    $rawRoomNumber = preg_replace('/^room/i', '', $roomType);
    $roomNumber = strlen($rawRoomNumber) === 1 ? strtoupper($rawRoomNumber) : $rawRoomNumber;

    $templateKeyForLog = $templateKey;
    $promptForLog = $prompt;
    $variablesJsonForLog = (string) json_encode($resolved, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $roomNumberForLog = $roomNumber;
    $modelForLog = $model;
    $providerForLog = $provider;

    $apiResponse = wf_openai_generate_image($apiKey, $model, $prompt, $size);
    $first = $apiResponse['data'][0] ?? null;
    if (!is_array($first)) {
        throw new RuntimeException('OpenAI did not return generated image data');
    }

    $sourcePath = '';
    $tmpFiles = [];

    if (!empty($first['b64_json'])) {
        $bytes = base64_decode((string) $first['b64_json'], true);
        if ($bytes === false) {
            throw new RuntimeException('OpenAI returned invalid base64 image data');
        }
        $tmp = tempnam(sys_get_temp_dir(), 'wf-img-b64-');
        if (!$tmp) {
            throw new RuntimeException('Unable to create temporary image file');
        }
        file_put_contents($tmp, $bytes);
        $sourcePath = $tmp;
        $tmpFiles[] = $tmp;
    } elseif (!empty($first['url'])) {
        $sourcePath = wf_download_image_to_temp((string) $first['url']);
        $tmpFiles[] = $sourcePath;
    } else {
        throw new RuntimeException('OpenAI response did not contain b64_json or url image payload');
    }

    $imagesRoot = realpath(__DIR__ . '/../images') ?: (__DIR__ . '/../images');
    $destDir = $imagesRoot . '/backgrounds';
    ImageUploadHelper::ensureDir($destDir);

    $safeRoom = ImageUploadHelper::slugify($roomType);
    $safeBase = ImageUploadHelper::slugify($backgroundNameInput !== '' ? $backgroundNameInput : ('ai-' . $templateKey));
    $unique = $safeBase . '-' . $safeRoom . '-' . substr(uniqid('', true), -6);
    $pngRel = 'backgrounds/' . $unique . '.png';
    $webpRel = 'backgrounds/' . $unique . '.webp';
    $pngAbs = $imagesRoot . '/' . $pngRel;
    $webpAbs = $imagesRoot . '/' . $webpRel;

    ImageUploadHelper::resizeFillToPng($sourcePath, $pngAbs, 1280, 896);

    if (function_exists('imagewebp')) {
        try {
            ImageUploadHelper::convertToWebP($pngAbs, $webpAbs, 92);
        } catch (Throwable $e) {
            error_log('generate_room_image webp conversion failed: ' . $e->getMessage());
            $webpRel = '';
        }
    } else {
        $webpRel = '';
    }

    foreach ($tmpFiles as $tmpFile) {
        if (is_string($tmpFile) && $tmpFile !== '' && file_exists($tmpFile)) {
            @unlink($tmpFile);
        }
    }

    $backgroundName = $backgroundNameInput !== ''
        ? $backgroundNameInput
        : ('AI ' . str_replace('_', ' ', $templateKey) . ' ' . date('Y-m-d H:i'));

    if (Database::queryOne('SELECT id FROM backgrounds WHERE room_number = ? AND name = ? LIMIT 1', [$roomNumber, $backgroundName])) {
        $backgroundName .= ' ' . date('Ymd-His');
    }

    Database::execute(
        'INSERT INTO backgrounds (room_number, name, image_filename, png_filename, webp_filename, is_active) VALUES (?, ?, ?, ?, ?, 0)',
        [$roomNumber, $backgroundName, $pngRel, $pngRel, $webpRel]
    );

    $backgroundId = (int) Database::lastInsertId();
    $historyId = wf_log_generation_history([
        'template_key' => $templateKey,
        'prompt_text' => $prompt,
        'variables_json' => $variablesJsonForLog,
        'provider' => $provider,
        'model' => $model,
        'status' => 'succeeded',
        'output_type' => 'room_background',
        'output_path' => '/images/' . ($webpRel !== '' ? $webpRel : $pngRel),
        'room_number' => $roomNumber,
        'error_message' => '',
        'created_by' => $createdByForLog
    ]);

    Response::success([
        'background' => [
            'id' => $backgroundId,
            'room_number' => $roomNumber,
            'name' => $backgroundName,
            'image_filename' => $pngRel,
            'webp_filename' => $webpRel,
            'is_active' => 0,
            'image_url' => '/images/' . $pngRel,
            'webp_url' => $webpRel !== '' ? '/images/' . $webpRel : null
        ],
        'history_id' => $historyId,
        'template_key' => $templateKey,
        'provider' => $provider,
        'model' => $model,
        'prompt_text' => $prompt,
        'resolved_variables' => $resolved
    ], 'Room image generated and saved');
} catch (Throwable $e) {
    if ($templateKeyForLog !== '' && $promptForLog !== '') {
        wf_log_generation_history([
            'template_key' => $templateKeyForLog,
            'prompt_text' => $promptForLog,
            'variables_json' => $variablesJsonForLog,
            'provider' => $providerForLog,
            'model' => $modelForLog,
            'status' => 'failed',
            'output_type' => 'room_background',
            'output_path' => '',
            'room_number' => $roomNumberForLog,
            'error_message' => $e->getMessage(),
            'created_by' => $createdByForLog
        ]);
    }
    Response::error($e->getMessage(), null, 500);
}
