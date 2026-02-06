<?php
/**
 * Theme Words API
 * Following .windsurfrules: < 300 lines.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth_helper.php';
require_once __DIR__ . '/../includes/theme_words/initializer.php';
require_once __DIR__ . '/../includes/theme_words/manager.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS')
    exit(0);

AuthHelper::requireAdmin();

try {
    $db = Database::getInstance();
    ensure_theme_words_tables($db);

    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $action = $_GET['action'] ?? $_POST['action'] ?? ($input['action'] ?? 'list');

    switch ($action) {
        case 'log_usage':
            $variantId = (int) ($input['variant_id'] ?? 0);
            $themeWordId = (int) ($input['theme_word_id'] ?? 0);
            $res = wf_theme_words_increment_usage($db, $themeWordId, $variantId, $input['variant_text'] ?? '', $input['context'] ?? '', $input['source'] ?? '');
            Response::success(['result' => $res]);
            break;

        case 'list':
            Response::success(['words' => get_theme_words_list($db)]);
            break;

        case 'add_word':
            $base = trim((string) ($input['word'] ?? $input['base_word'] ?? ''));
            if ($base === '')
                Response::validationError(['word' => 'Required']);
            $categoryId = (int) ($input['category_id'] ?? 0);
            $category = trim((string) ($input['category'] ?? 'General'));
            $sql = "INSERT INTO theme_words (base_word, category, category_id, definition, tags, is_active) VALUES (?, ?, ?, ?, ?, ?)";
            Database::execute($sql, [$base, $category, $categoryId ?: null, $input['definition'] ?? '', normalize_tags($input['tags'] ?? ''), $input['is_active'] ?? 1]);
            $newId = Database::lastInsertId();

            // Handle variants
            if (!empty($input['variants']) && is_array($input['variants'])) {
                foreach ($input['variants'] as $vText) {
                    $vText = trim((string) $vText);
                    if ($vText !== '') {
                        Database::execute("INSERT INTO theme_word_variants (theme_word_id, variant_text) VALUES (?, ?)", [$newId, $vText]);
                    }
                }
            }
            Response::updated(['id' => $newId]);
            break;

        case 'update_word':
            $id = (int) ($input['id'] ?? 0);
            if ($id <= 0)
                Response::error('ID required');
            $base = trim((string) ($input['word'] ?? $input['base_word'] ?? ''));
            $categoryId = (int) ($input['category_id'] ?? 0);
            $category = trim((string) ($input['category'] ?? 'General'));
            $sql = "UPDATE theme_words SET base_word=?, category=?, category_id=?, definition=?, tags=?, is_active=? WHERE id=?";
            Database::execute($sql, [$base, $category, $categoryId ?: null, $input['definition'] ?? '', normalize_tags($input['tags']), $input['is_active'] ?? 1, $id]);

            // Sync variants: this is a bit more complex. 
            // For now, let's just handle simple replacement if variants are sent.
            if (isset($input['variants']) && is_array($input['variants'])) {
                // Delete existing ones not in the new list (or just reload them)
                // Actually, the simplest way is to delete all and re-insert, but that loses usage counts.
                // Let's do a better sync.
                $currentVariants = Database::queryAll("SELECT id, variant_text FROM theme_word_variants WHERE theme_word_id = ?", [$id]);
                $currentMap = [];
                foreach ($currentVariants as $cv)
                    $currentMap[$cv['variant_text']] = $cv['id'];

                $newVariants = array_map('trim', $input['variants']);
                $newVariants = array_unique(array_filter($newVariants));

                // Add new ones
                foreach ($newVariants as $nv) {
                    if (!isset($currentMap[$nv])) {
                        Database::execute("INSERT INTO theme_word_variants (theme_word_id, variant_text) VALUES (?, ?)", [$id, $nv]);
                    }
                }

                // Delete removed ones
                foreach ($currentMap as $vt => $vid) {
                    if (!in_array($vt, $newVariants)) {
                        Database::execute("DELETE FROM theme_word_variants WHERE id = ?", [$vid]);
                    }
                }
            }
            Response::updated(['id' => $id]);
            break;

        case 'delete_word':
            Database::execute("DELETE FROM theme_words WHERE id = ?", [$input['id'] ?? $_GET['id']]);
            Response::updated(['id' => $input['id'] ?? $_GET['id']]);
            break;

        case 'list_categories':
            Response::success(['categories' => get_theme_word_categories($db)]);
            break;

        case 'add_category':
            $id = add_theme_word_category($db, $input);
            Response::updated(['id' => $id]);
            break;

        case 'update_category':
            update_theme_word_category($db, $input);
            Response::updated(['id' => $input['id']]);
            break;

        case 'delete_category':
            delete_theme_word_category($db, $input['id'] ?? $_GET['id']);
            Response::updated(['id' => $input['id'] ?? $_GET['id']]);
            break;

        default:
            Response::error('Unknown action', ['action' => $action], 400);
    }
} catch (Throwable $e) {
    Response::serverError($e->getMessage());
}
