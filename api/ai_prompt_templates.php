<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth_helper.php';

header('Content-Type: application/json; charset=utf-8');

function ai_prompt_templates_init_tables(): void
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

function ai_prompt_templates_seed_defaults(): void
{
    $defaultTemplate = <<<'PROMPT'
A high-quality 3D cartoon render of a themed {{room_theme}} corner inside the whimsical frog’s cottage.

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
            template_name = VALUES(template_name),
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

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    Response::json(['success' => true]);
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';
if ($action === '' && ($_SERVER['REQUEST_METHOD'] ?? '') === 'GET') {
    $action = 'list_templates';
}

$readActions = ['list_templates', 'list_variables', 'list_history'];
if (!in_array($action, $readActions, true)) {
    Response::validateMethod('POST');
}

AuthHelper::requireAdmin(403, 'Admin access required');

ai_prompt_templates_init_tables();
ai_prompt_templates_seed_defaults();

try {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];

    switch ($action) {
        case 'list_templates': {
            $templates = Database::queryAll(
                'SELECT id, template_key, template_name, description, context_type, prompt_text, is_active, created_at, updated_at
                 FROM ai_prompt_templates
                 ORDER BY is_active DESC, template_name ASC'
            );
            Response::json(['success' => true, 'templates' => $templates]);
            break;
        }

        case 'list_variables': {
            $variables = Database::queryAll(
                'SELECT id, variable_key, display_name, description, sample_value, is_active, created_at, updated_at
                 FROM ai_prompt_variables
                 WHERE is_active = 1
                 ORDER BY display_name ASC'
            );
            Response::json(['success' => true, 'variables' => $variables]);
            break;
        }

        case 'save_template': {
            $id = (int) ($input['id'] ?? 0);
            $templateKey = trim((string) ($input['template_key'] ?? ''));
            $templateName = trim((string) ($input['template_name'] ?? ''));
            $description = trim((string) ($input['description'] ?? ''));
            $contextType = trim((string) ($input['context_type'] ?? 'generic'));
            $promptText = trim((string) ($input['prompt_text'] ?? ''));
            $isActive = !empty($input['is_active']) ? 1 : 0;

            if ($templateKey === '' || $templateName === '' || $promptText === '') {
                Response::error('template_key, template_name, and prompt_text are required', null, 422);
            }

            if ($id > 0) {
                Database::execute(
                    'UPDATE ai_prompt_templates
                     SET template_key = ?, template_name = ?, description = ?, context_type = ?, prompt_text = ?, is_active = ?
                     WHERE id = ?',
                    [$templateKey, $templateName, $description, $contextType, $promptText, $isActive, $id]
                );
            } else {
                Database::execute(
                    'INSERT INTO ai_prompt_templates (template_key, template_name, description, context_type, prompt_text, is_active)
                     VALUES (?, ?, ?, ?, ?, ?)',
                    [$templateKey, $templateName, $description, $contextType, $promptText, $isActive]
                );
            }

            Response::json(['success' => true, 'message' => 'Template saved']);
            break;
        }

        case 'delete_template': {
            $id = (int) ($input['id'] ?? 0);
            if ($id <= 0) {
                Response::error('Template id is required', null, 422);
            }

            Database::execute('DELETE FROM ai_prompt_templates WHERE id = ?', [$id]);
            Response::json(['success' => true, 'message' => 'Template deleted']);
            break;
        }

        case 'log_generation': {
            $templateKey = trim((string) ($input['template_key'] ?? ''));
            $promptText = trim((string) ($input['prompt_text'] ?? ''));
            $variablesJson = json_encode($input['variables'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $provider = trim((string) ($input['provider'] ?? ''));
            $model = trim((string) ($input['model'] ?? ''));
            $status = trim((string) ($input['status'] ?? 'queued'));
            $outputType = trim((string) ($input['output_type'] ?? 'room_background'));
            $outputPath = trim((string) ($input['output_path'] ?? ''));
            $roomNumber = trim((string) ($input['room_number'] ?? ''));
            $errorMessage = trim((string) ($input['error_message'] ?? ''));
            $createdBy = null;
            $user = AuthHelper::getCurrentUser();
            if (is_array($user)) {
                $createdBy = (string) ($user['user_id'] ?? $user['id'] ?? $user['username'] ?? '');
            }

            if ($templateKey === '' || $promptText === '') {
                Response::error('template_key and prompt_text are required', null, 422);
            }

            Database::execute(
                'INSERT INTO ai_generation_history (template_key, prompt_text, variables_json, provider, model, status, output_type, output_path, room_number, error_message, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [$templateKey, $promptText, $variablesJson, $provider, $model, $status, $outputType, $outputPath, $roomNumber, $errorMessage, $createdBy]
            );

            Response::json(['success' => true, 'message' => 'Generation history saved']);
            break;
        }

        case 'build_room_prompt': {
            $templateKey = trim((string) ($input['template_key'] ?? ''));
            $roomNumber = trim((string) ($input['room_number'] ?? ''));
            $provider = trim((string) ($input['provider'] ?? ''));
            $model = trim((string) ($input['model'] ?? ''));
            $variables = is_array($input['variables'] ?? null) ? $input['variables'] : [];

            if ($templateKey === '') {
                Response::error('template_key is required', null, 422);
            }

            $template = Database::queryOne(
                'SELECT template_key, prompt_text FROM ai_prompt_templates WHERE template_key = ? LIMIT 1',
                [$templateKey]
            );
            if (!$template) {
                Response::error('Template not found', null, 404);
            }

            $defaults = [];
            $rows = Database::queryAll('SELECT variable_key, sample_value FROM ai_prompt_variables WHERE is_active = 1');
            foreach ($rows as $row) {
                $key = (string) ($row['variable_key'] ?? '');
                if ($key !== '') {
                    $defaults[$key] = (string) ($row['sample_value'] ?? '');
                }
            }

            $resolved = $defaults;
            foreach ($variables as $k => $v) {
                $resolved[(string) $k] = trim((string) $v);
            }

            $prompt = (string) ($template['prompt_text'] ?? '');
            foreach ($resolved as $key => $value) {
                $prompt = str_replace('{{' . $key . '}}', $value, $prompt);
            }

            $user = AuthHelper::getCurrentUser();
            $createdBy = null;
            if (is_array($user)) {
                $createdBy = (string) ($user['user_id'] ?? $user['id'] ?? $user['username'] ?? '');
            }

            Database::execute(
                'INSERT INTO ai_generation_history (template_key, prompt_text, variables_json, provider, model, status, output_type, room_number, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [$templateKey, $prompt, json_encode($resolved, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $provider, $model, 'prompt_built', 'room_background_prompt', $roomNumber, $createdBy]
            );

            Response::json([
                'success' => true,
                'template_key' => $templateKey,
                'prompt_text' => $prompt,
                'resolved_variables' => $resolved,
                'message' => 'Room prompt built and logged'
            ]);
            break;
        }

        case 'list_history': {
            $history = Database::queryAll(
                'SELECT id, template_key, provider, model, status, output_type, output_path, room_number, error_message, created_by, created_at
                 FROM ai_generation_history
                 ORDER BY id DESC
                 LIMIT 100'
            );
            Response::json(['success' => true, 'history' => $history]);
            break;
        }

        default:
            Response::error('Invalid action', null, 400);
    }
} catch (Throwable $e) {
    Response::error($e->getMessage(), null, 500);
}
