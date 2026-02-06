<?php
// API: Manage Shop Encouragement Phrases
// Supports action routing via ?action= query parameter:
//   GET  ?action=list  -> { success: true, phrases: [{ id, text }, ...] }
//   POST ?action=add   -> { success: true, id: number } with JSON { text: string }
//   POST ?action=delete -> { success: true } with JSON { id: number }
//   POST (legacy)      -> { success: true } with JSON { phrases: string[] }

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/business_settings_helper.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = strtolower(trim($_GET['action'] ?? ''));

/**
 * Load encouragement phrases from business_settings, migrating plain strings to structured format
 * @return array Array of { id: int, text: string }
 */
function loadEncouragementPhrases(): array
{
    $raw = BusinessSettings::get('shop_encouragement_phrases', '[]');
    if (is_string($raw)) {
        $decoded = json_decode($raw, true);
        $data = is_array($decoded) ? $decoded : [];
    } else {
        $data = is_array($raw) ? $raw : [];
    }

    // Migrate: if array contains plain strings, convert to structured format
    $phrases = [];
    $needsMigration = false;
    foreach ($data as $index => $item) {
        if (is_string($item)) {
            // Plain string format - migrate to structured
            $needsMigration = true;
            $trimmed = trim($item);
            if ($trimmed !== '') {
                $phrases[] = [
                    'id' => $index + 1,
                    'text' => $trimmed
                ];
            }
        } elseif (is_array($item) && isset($item['text'])) {
            // Already structured format
            $phrases[] = [
                'id' => (int) ($item['id'] ?? $index + 1),
                'text' => trim((string) $item['text'])
            ];
        }
    }

    // Reassign sequential IDs and save if migrated
    if ($needsMigration && !empty($phrases)) {
        $phrases = reindexPhrases($phrases);
        saveEncouragementPhrases($phrases);
    }

    return $phrases;
}

/**
 * Re-index phrases with sequential IDs starting from 1
 */
function reindexPhrases(array $phrases): array
{
    $result = [];
    $id = 1;
    foreach ($phrases as $item) {
        if (is_array($item) && isset($item['text']) && trim((string) $item['text']) !== '') {
            $result[] = [
                'id' => $id++,
                'text' => trim((string) $item['text'])
            ];
        }
    }
    return $result;
}

/**
 * Save encouragement phrases to business_settings
 */
function saveEncouragementPhrases(array $phrases): void
{
    $stored = json_encode($phrases, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $baseParams = [
        ':category' => 'messages',
        ':key' => 'shop_encouragement_phrases',
        ':value' => $stored,
        ':type' => 'json',
        ':display_name' => 'Shop Encouragement Phrases',
        ':description' => 'Phrases shown as badges on recommended items',
    ];

    // Probe table columns to adapt statements
    $cols = [];
    try {
        $rows = Database::queryAll('SHOW COLUMNS FROM business_settings');
        foreach ($rows as $r) {
            if (isset($r['Field'])) {
                $cols[] = (string) $r['Field'];
            }
        }
    } catch (Throwable $e) {
        // If SHOW COLUMNS fails, fallback to safest minimal set
    }
    $hasType = in_array('setting_type', $cols, true);
    $hasDisplay = in_array('display_name', $cols, true);
    $hasDesc = in_array('description', $cols, true);
    $hasUpdated = in_array('updated_at', $cols, true);

    // Check if row exists
    $exists = false;
    try {
        $rowExists = Database::queryOne('SELECT 1 AS present FROM business_settings WHERE setting_key = :key LIMIT 1', [':key' => $baseParams[':key']]);
        $exists = isset($rowExists['present']);
    } catch (Throwable $e) {
        // Fallback to UPDATE->INSERT flow
    }

    // Build dynamic SET for UPDATE
    $setParts = ['setting_value = :value'];
    if ($hasType) {
        $setParts[] = 'setting_type = :type';
    }
    if ($hasDisplay) {
        $setParts[] = 'display_name = :display_name';
    }
    if ($hasDesc) {
        $setParts[] = 'description = :description';
    }
    if ($hasUpdated) {
        $setParts[] = 'updated_at = CURRENT_TIMESTAMP';
    }

    if ($exists) {
        $updateSql = 'UPDATE business_settings SET ' . implode(', ', $setParts) . ' WHERE setting_key = :key';
        $updParams = [':key' => $baseParams[':key'], ':value' => $baseParams[':value']];
        if ($hasType) {
            $updParams[':type'] = $baseParams[':type'];
        }
        if ($hasDisplay) {
            $updParams[':display_name'] = $baseParams[':display_name'];
        }
        if ($hasDesc) {
            $updParams[':description'] = $baseParams[':description'];
        }
        Database::execute($updateSql, $updParams);
    } else {
        $insCols = ['category', 'setting_key', 'setting_value'];
        $insVals = [':category', ':key', ':value'];
        if ($hasType) {
            $insCols[] = 'setting_type';
            $insVals[] = ':type';
        }
        if ($hasDisplay) {
            $insCols[] = 'display_name';
            $insVals[] = ':display_name';
        }
        if ($hasDesc) {
            $insCols[] = 'description';
            $insVals[] = ':description';
        }
        if ($hasUpdated) {
            $insCols[] = 'updated_at';
            $insVals[] = 'CURRENT_TIMESTAMP';
        }

        $insertSql = 'INSERT INTO business_settings (' . implode(', ', $insCols) . ') VALUES (' . implode(', ', $insVals) . ')';
        $insParams = [':category' => $baseParams[':category'], ':key' => $baseParams[':key'], ':value' => $baseParams[':value']];
        if ($hasType) {
            $insParams[':type'] = $baseParams[':type'];
        }
        if ($hasDisplay) {
            $insParams[':display_name'] = $baseParams[':display_name'];
        }
        if ($hasDesc) {
            $insParams[':description'] = $baseParams[':description'];
        }
        Database::execute($insertSql, $insParams);
    }

    if (class_exists('BusinessSettings')) {
        BusinessSettings::clearCache();
    }
}

try {
    if ($method === 'OPTIONS') {
        http_response_code(200);
        echo json_encode(['success' => true]);
        exit;
    }

    // === LIST ACTION (GET) ===
    if ($method === 'GET' || ($method === 'GET' && $action === 'list')) {
        $phrases = loadEncouragementPhrases();
        echo json_encode(['success' => true, 'phrases' => $phrases]);
        exit;
    }

    if ($method !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Method not allowed']);
        exit;
    }

    $payload = json_decode(file_get_contents('php://input'), true);
    if (!is_array($payload)) {
        throw new Exception('Invalid JSON');
    }

    // === ADD ACTION ===
    if ($action === 'add') {
        $newText = trim((string) ($payload['text'] ?? ''));
        if ($newText === '') {
            throw new Exception('Text is required');
        }

        $phrases = loadEncouragementPhrases();

        // Check for duplicate
        foreach ($phrases as $item) {
            if (strcasecmp($item['text'], $newText) === 0) {
                throw new Exception('This phrase already exists');
            }
        }

        // Generate new ID (max existing + 1)
        $maxId = 0;
        foreach ($phrases as $item) {
            if ($item['id'] > $maxId) {
                $maxId = $item['id'];
            }
        }

        $phrases[] = [
            'id' => $maxId + 1,
            'text' => $newText
        ];

        // Limit to 100 entries
        if (count($phrases) > 100) {
            $phrases = array_slice($phrases, -100);
            $phrases = reindexPhrases($phrases);
        }

        saveEncouragementPhrases($phrases);
        echo json_encode(['success' => true, 'id' => $maxId + 1]);
        exit;
    }

    // === UPDATE ACTION ===
    if ($action === 'update') {
        $updateId = (int) ($payload['id'] ?? 0);
        $newText = trim((string) ($payload['text'] ?? ''));
        if ($updateId <= 0) {
            throw new Exception('Valid ID is required');
        }
        if ($newText === '') {
            throw new Exception('Text is required');
        }

        $phrases = loadEncouragementPhrases();
        $found = false;

        // Check for duplicate (excluding current item)
        foreach ($phrases as $item) {
            if ($item['id'] !== $updateId && strcasecmp($item['text'], $newText) === 0) {
                throw new Exception('This phrase already exists');
            }
        }

        // Update the item
        foreach ($phrases as &$item) {
            if ($item['id'] === $updateId) {
                $item['text'] = $newText;
                $found = true;
                break;
            }
        }
        unset($item);

        if (!$found) {
            throw new Exception('Phrase not found');
        }

        saveEncouragementPhrases($phrases);
        echo json_encode(['success' => true]);
        exit;
    }

    // === DELETE ACTION ===
    if ($action === 'delete') {
        $deleteId = (int) ($payload['id'] ?? 0);
        if ($deleteId <= 0) {
            throw new Exception('Valid ID is required');
        }

        $phrases = loadEncouragementPhrases();
        $found = false;
        $newPhrases = [];

        foreach ($phrases as $item) {
            if ($item['id'] === $deleteId) {
                $found = true;
            } else {
                $newPhrases[] = $item;
            }
        }

        if (!$found) {
            throw new Exception('Phrase not found');
        }

        // Reindex after deletion
        $newPhrases = reindexPhrases($newPhrases);
        saveEncouragementPhrases($newPhrases);

        echo json_encode(['success' => true]);
        exit;
    }

    // === LEGACY: REPLACE ALL PHRASES ===
    // For backwards compatibility
    $phrases = $payload['phrases'] ?? [];
    if (!is_array($phrases)) {
        throw new Exception('phrases must be an array');
    }

    // Normalize: trim, dedupe, limit 100, convert to structured format
    $norm = [];
    $seen = [];
    $id = 1;
    foreach ($phrases as $p) {
        $s = trim((string) $p);
        $lower = strtolower($s);
        if ($s !== '' && !isset($seen[$lower])) {
            $seen[$lower] = true;
            $norm[] = [
                'id' => $id++,
                'text' => $s
            ];
        }
        if (count($norm) >= 100) {
            break;
        }
    }

    saveEncouragementPhrases($norm);
    echo json_encode(['success' => true, 'count' => count($norm)]);

} catch (Throwable $e) {
    http_response_code(200);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
