<?php
/**
 * Cart Button Text Manager
 * Handles variations for Add-to-Cart button texts stored in business_settings.
 */

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/business_settings_helper.php';

class CartButtonTextManager
{

    /**
     * Load cart texts, migrating legacy formats if needed.
     */
    public static function load(): array
    {
        $raw = BusinessSettings::get('cart_button_texts', '[]');
        $data = is_string($raw) ? json_decode($raw, true) : (is_array($raw) ? $raw : []);
        if (!is_array($data))
            $data = [];

        $texts = [];
        $needsMigration = false;
        foreach ($data as $index => $item) {
            if (is_string($item)) {
                $needsMigration = true;
                if (trim($item) !== '') {
                    $texts[] = ['id' => $index + 1, 'text' => trim($item), 'is_active' => true];
                }
            } elseif (is_array($item) && isset($item['text'])) {
                $texts[] = [
                    'id' => (int) ($item['id'] ?? $index + 1),
                    'text' => trim((string) $item['text']),
                    'is_active' => (bool) ($item['is_active'] ?? true)
                ];
            }
        }

        if ($needsMigration && !empty($texts)) {
            $texts = self::reindex($texts);
            self::save($texts);
        }

        return $texts;
    }

    /**
     * Re-index texts with sequential IDs.
     */
    public static function reindex(array $texts): array
    {
        $result = [];
        $id = 1;
        foreach ($texts as $item) {
            if (is_array($item) && isset($item['text']) && trim((string) $item['text']) !== '') {
                $result[] = [
                    'id' => $id++,
                    'text' => trim((string) $item['text']),
                    'is_active' => (bool) ($item['is_active'] ?? true)
                ];
            }
        }
        return $result;
    }

    /**
     * Save texts to business_settings with schema-aware logic.
     */
    public static function save(array $texts): void
    {
        $stored = json_encode($texts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $params = [
            ':category' => 'messages',
            ':key' => 'cart_button_texts',
            ':value' => $stored,
            ':type' => 'json',
            ':display_name' => 'Cart Button Text Variations',
            ':description' => 'Phrases randomly used for Add to Cart buttons',
        ];

        // Schema probing
        $cols = [];
        try {
            $rows = Database::queryAll('SHOW COLUMNS FROM business_settings');
            foreach ($rows as $r)
                if (isset($r['Field']))
                    $cols[] = (string) $r['Field'];
        } catch (Throwable $e) {
        }

        $hasType = in_array('setting_type', $cols, true);
        $hasDisplay = in_array('display_name', $cols, true);
        $hasDesc = in_array('description', $cols, true);
        $hasUpdated = in_array('updated_at', $cols, true);

        $exists = false;
        try {
            $res = Database::queryOne('SELECT 1 FROM business_settings WHERE setting_key = :key LIMIT 1', [':key' => $params[':key']]);
            $exists = !empty($res);
        } catch (Throwable $e) {
        }

        if ($exists) {
            $set = ['setting_value = :value'];
            if ($hasType)
                $set[] = 'setting_type = :type';
            if ($hasDisplay)
                $set[] = 'display_name = :display_name';
            if ($hasDesc)
                $set[] = 'description = :description';
            if ($hasUpdated)
                $set[] = 'updated_at = CURRENT_TIMESTAMP';

            $sql = 'UPDATE business_settings SET ' . implode(', ', $set) . ' WHERE setting_key = :key';
            $updParams = [':key' => $params[':key'], ':value' => $params[':value']];
            if ($hasType)
                $updParams[':type'] = $params[':type'];
            if ($hasDisplay)
                $updParams[':display_name'] = $params[':display_name'];
            if ($hasDesc)
                $updParams[':description'] = $params[':description'];
            Database::execute($sql, $updParams);
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

            $sql = 'INSERT INTO business_settings (' . implode(', ', $insCols) . ') VALUES (' . implode(', ', $insVals) . ')';
            $inParams = [':category' => $params[':category'], ':key' => $params[':key'], ':value' => $params[':value']];
            if ($hasType)
                $inParams[':type'] = $params[':type'];
            if ($hasDisplay)
                $inParams[':display_name'] = $params[':display_name'];
            if ($hasDesc)
                $inParams[':description'] = $params[':description'];
            Database::execute($sql, $inParams);
        }
        BusinessSettings::clearCache();
    }

    /**
     * Add a new text variation.
     */
    public static function add(string $text): int
    {
        $text = trim($text);
        if ($text === '')
            throw new Exception('Text is required');
        $texts = self::load();
        foreach ($texts as $item)
            if (strcasecmp($item['text'], $text) === 0)
                throw new Exception('Phrase already exists');

        $maxId = 0;
        foreach ($texts as $item)
            if ($item['id'] > $maxId)
                $maxId = $item['id'];
        $newId = $maxId + 1;
        $texts[] = ['id' => $newId, 'text' => $text, 'is_active' => true];
        if (count($texts) > 100) {
            $texts = array_slice($texts, -100);
            $texts = self::reindex($texts);
        }
        self::save($texts);
        return $newId;
    }

    /**
     * Update an existing text variation.
     */
    public static function update(int $id, string $text): void
    {
        $text = trim($text);
        if ($text === '')
            throw new Exception('Text is required');
        $texts = self::load();
        $found = false;
        foreach ($texts as $item)
            if ($item['id'] !== $id && strcasecmp($item['text'], $text) === 0)
                throw new Exception('Phrase already exists');
        foreach ($texts as &$item) {
            if ($item['id'] === $id) {
                $item['text'] = $text;
                $found = true;
                break;
            }
        }
        if (!$found)
            throw new Exception('Text not found');
        self::save($texts);
    }

    /**
     * Delete a text variation.
     */
    public static function delete(int $id): void
    {
        $texts = self::load();
        $newTexts = [];
        $found = false;
        foreach ($texts as $item) {
            if ($item['id'] === $id)
                $found = true;
            else
                $newTexts[] = $item;
        }
        if (!$found)
            throw new Exception('Text not found');
        self::save(self::reindex($newTexts));
    }

    /**
     * Legacy: Replace all texts at once.
     */
    public static function replaceAll(array $rawTexts): int
    {
        $norm = [];
        $seen = [];
        $id = 1;
        foreach ($rawTexts as $t) {
            $s = trim((string) $t);
            $low = strtolower($s);
            if ($s !== '' && !isset($seen[$low])) {
                $seen[$low] = true;
                $norm[] = ['id' => $id++, 'text' => $s, 'is_active' => true];
            }
            if (count($norm) >= 100)
                break;
        }
        self::save($norm);
        return count($norm);
    }
}
