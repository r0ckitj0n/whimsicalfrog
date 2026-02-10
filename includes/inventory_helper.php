<?php

require_once __DIR__ . '/Constants.php';
require_once __DIR__ . '/auth_helper.php';

/**
 * InventoryHelper centralizes shared inventory utilities (soft delete, archival filters, etc.).
 * Callers are expected to include api/config.php prior to using this helper so the Database class is available.
 */
class InventoryHelper
{
    /** @var bool */
    private static $archiveColumnsEnsured = false;

    /**
     * Ensure the items table contains the columns required for archival operations.
     * Safe to call multiple times per request; the ALTER TABLE will only run once when needed.
     */
    public static function ensureArchiveColumns(): void
    {
        if (self::$archiveColumnsEnsured) {
            return;
        }

        try {
            $columns = Database::queryAll('SHOW COLUMNS FROM items');
        } catch (\Throwable $e) {
            error_log('[InventoryHelper] Unable to inspect items columns: ' . $e->getMessage());
            return;
        }

        $existing = [];
        foreach ($columns as $column) {
            if (!isset($column['Field'])) {
                continue;
            }
            $existing[strtolower((string) $column['Field'])] = true;
        }

        $alterParts = [];
        if (!isset($existing['is_archived'])) {
            $alterParts[] = 'ADD COLUMN is_archived TINYINT(1) NOT NULL DEFAULT 0 AFTER description';
        }
        if (!isset($existing['archived_at'])) {
            $alterParts[] = 'ADD COLUMN archived_at DATETIME NULL DEFAULT NULL AFTER is_archived';
        }
        if (!isset($existing['archived_by'])) {
            $alterParts[] = 'ADD COLUMN archived_by VARCHAR(255) NULL DEFAULT NULL AFTER archived_at';
        }

        if (!empty($alterParts)) {
            try {
                Database::execute('ALTER TABLE items ' . implode(', ', $alterParts));
            } catch (\Throwable $e) {
                error_log('[InventoryHelper] Failed to ensure archive columns: ' . $e->getMessage());
            }
        }

        self::$archiveColumnsEnsured = true;
    }

    /**
     * Mark an item as archived (soft delete) instead of removing it permanently.
     */
    public static function softDeleteItem(string $sku, ?string $archivedBy = null): bool
    {
        self::ensureArchiveColumns();

        $actor = $archivedBy ?? self::defaultActor() ?? WF_Constants::ROLE_SYSTEM;

        $sql = 'UPDATE items SET is_archived = 1, archived_at = NOW(), archived_by = ? WHERE sku = ?';
        $result = Database::execute($sql, [$actor, $sku]);

        if ($result === false || (int) $result === 0) {
            // Treat already-archived records as success but still verify the item exists.
            $row = Database::queryOne('SELECT sku FROM items WHERE sku = ?', [$sku]);
            return !empty($row);
        }

        return true;
    }

    /**
     * Restore an archived item so it becomes visible again.
     */
    public static function restoreItem(string $sku): bool
    {
        self::ensureArchiveColumns();

        $sql = 'UPDATE items SET is_archived = 0, archived_at = NULL, archived_by = NULL WHERE sku = ?';
        $result = Database::execute($sql, [$sku]);

        if ($result === false || (int) $result === 0) {
            $row = Database::queryOne('SELECT sku FROM items WHERE sku = ?', [$sku]);
            return !empty($row);
        }

        return true;
    }

    /**
     * Permanently remove an item. Optionally require that it is already archived first.
     */
    public static function hardDeleteItem(string $sku, bool $requireArchived = true): bool
    {
        self::ensureArchiveColumns();

        if ($requireArchived) {
            $row = Database::queryOne('SELECT is_archived FROM items WHERE sku = ?', [$sku]);
            if (!$row) {
                return false;
            }
            if ((int) ($row['is_archived'] ?? 0) !== 1) {
                // Refuse to delete active items when protection is enabled.
                return false;
            }
        }

        $result = Database::execute('DELETE FROM items WHERE sku = ?', [$sku]);
        return $result !== false && (int) $result > 0;
    }

    /**
     * Determine a friendly actor string for archival metadata.
     */
    public static function defaultActor(): ?string
    {
        if (!class_exists('AuthHelper')) {
            return null;
        }

        $user = AuthHelper::getCurrentUser();
        if (is_array($user)) {
            return $user['username']
                ?? $user['email']
                ?? $user['name']
                ?? null;
        }

        return null;
    }

    /**
     * Append the standard active/archived filter to a WHERE clause builder array.
     *
     * @param array  $where_conditions Array of SQL snippets that will later be joined with AND
     * @param string $alias           Table alias for the items table (defaults to 'i')
     * @param string $status          One of: 'active' (default), 'archived', 'all'
     */
    public static function addStatusCondition(array &$where_conditions, string $alias = 'i', string $status = WF_Constants::ITEM_STATUS_ACTIVE): void
    {
        if ($status === 'all') {
            return;
        }

        $prefix = $alias !== '' ? "$alias." : '';
        if ($status === WF_Constants::ITEM_STATUS_ARCHIVED) {
            $where_conditions[] = "{$prefix}is_archived = 1";
        } else {
            $where_conditions[] = "{$prefix}is_archived = 0";
        }
    }

    /**
     * Resolve the current edit item based on modal mode and SKU
     */
    public static function resolveEditItem(?string $mode, ?string $sku): ?array
    {
        if (!$mode || $mode === WF_Constants::ACTION_ADD) {
            if ($mode === WF_Constants::ACTION_ADD) {
                $lastSku = Database::queryOne("SELECT sku FROM items WHERE sku LIKE 'WF-GEN-%' ORDER BY sku DESC LIMIT 1");
                $lastNum = $lastSku ? (int) substr($lastSku['sku'], -3) : 0;
                return ['sku' => 'WF-GEN-' . str_pad($lastNum + 1, 3, '0', STR_PAD_LEFT)];
            }
            return null;
        }

        if (!$sku)
            return null;
        return Database::queryOne("SELECT * FROM items WHERE sku = ?", [$sku]) ?: null;
    }

    /**
     * Get all categories from authoritative and legacy sources
     */
    public static function getCategories(): array
    {
        $categories = [];
        try {
            $catRows = Database::queryAll("SELECT name FROM categories ORDER BY name");
            foreach ($catRows as $row) {
                $name = $row['name'] ?? '';
                if ($name !== '')
                    $categories[strtolower($name)] = $name;
            }
        } catch (\Throwable $e) {
        }

        try {
            $legacyRows = Database::queryAll("SELECT DISTINCT category FROM items WHERE category IS NOT NULL AND category <> '' ORDER BY category");
            foreach ($legacyRows as $row) {
                $name = $row['category'] ?? '';
                if ($name !== '')
                    $categories[strtolower($name)] = $name;
            }
        } catch (\Throwable $e) {
        }

        return array_values($categories);
    }

    /**
     * Fetch filtered and sorted inventory items
     */
    public static function getInventoryItems(array $filters, string $sortBy, string $sortDir): array
    {
        $sortMap = [
            'name' => 'i.name',
            'category' => 'i.category',
            'sku' => 'i.sku',
            'stock' => 'i.stock_quantity',
            'reorder' => 'i.reorder_point',
            'cost' => 'i.cost_price',
            'retail' => 'i.retail_price',
            'images' => 'image_count',
        ];

        $validDir = (strtolower($sortDir) === 'desc') ? 'DESC' : 'ASC';
        $orderColumn = $sortMap[strtolower($sortBy)] ?? 'i.sku';
        $orderClause = $orderColumn . ' ' . $validDir . ', i.sku ASC';

        $where_conditions = ['1=1'];
        self::addStatusCondition($where_conditions, 'i', $filters['status'] ?? WF_Constants::ITEM_STATUS_ACTIVE);
        $queryParams = [];

        if (!empty($filters['search'])) {
            $where_conditions[] = "(i.name LIKE :search OR i.sku LIKE :search OR i.description LIKE :search)";
            $queryParams[':search'] = '%' . $filters['search'] . '%';
        }
        if (!empty($filters['category'])) {
            $where_conditions[] = "i.category = :category";
            $queryParams[':category'] = $filters['category'];
        }
        if (!empty($filters['stock'])) {
            $stockCondition = match ($filters['stock']) {
                'low' => "i.stock_quantity <= i.reorder_point AND i.stock_quantity > 0",
                'out' => "i.stock_quantity = 0",
                'in' => "i.stock_quantity > 0",
                default => "1=1"
            };
            $where_conditions[] = $stockCondition;
        }

        $sql = "SELECT i.sku, i.name, COALESCE(cat.name, i.category) AS category, i.description,
                       i.status, i.cost_price, i.retail_price, i.stock_quantity, i.reorder_point,
                       i.weight_oz, i.package_length_in, i.package_width_in, i.package_height_in,
                       i.image_url, i.category_id, i.is_active, i.is_archived, i.archived_at, i.archived_by,
                       COALESCE(img_count.image_count, 0) as image_count 
                FROM items i 
                LEFT JOIN categories cat ON i.category_id = cat.id
                LEFT JOIN (
                    SELECT sku, COUNT(*) as image_count 
                    FROM item_images 
                    GROUP BY sku
                ) img_count ON i.sku = img_count.sku 
                WHERE " . implode(' AND ', $where_conditions) . " 
                ORDER BY " . $orderClause;

        $items = Database::queryAll($sql, $queryParams);
        return self::enrichItemsWithImages($items);
    }

    /**
     * Enrich items with primary image data
     */
    private static function enrichItemsWithImages(array $items): array
    {
        if (empty($items))
            return [];

        $skus = array_values(array_filter(array_map(fn($item) => $item['sku'] ?? null, $items)));
        if (empty($skus))
            return $items;

        $primaryImages = [];
        $placeholders = implode(',', array_fill(0, count($skus), '?'));
        $imageRows = Database::queryAll(
            "SELECT sku, image_path, alt_text, is_primary, sort_order, id
             FROM item_images
             WHERE sku IN ($placeholders)
             ORDER BY sku ASC, is_primary DESC, sort_order ASC, id ASC",
            $skus
        );

        foreach ($imageRows as $row) {
            $sku = $row['sku'] ?? null;
            if (!$sku)
                continue;

            $resolvedPath = self::resolveExistingInventoryImagePath((string) ($row['image_path'] ?? ''));
            $candidate = [
                'image_path' => $resolvedPath ?: ($row['image_path'] ?? null),
                'alt_text' => $row['alt_text'] ?? null,
                'is_primary' => (bool) ($row['is_primary'] ?? false),
                'sort_order' => (int) ($row['sort_order'] ?? 0),
                '__exists' => $resolvedPath !== null
            ];

            if (!isset($primaryImages[$sku])) {
                $primaryImages[$sku] = $candidate;
                continue;
            }

            // Prefer a thumbnail that resolves to an existing file.
            $existingSelected = !empty($primaryImages[$sku]['__exists']);
            if (!$existingSelected && !empty($candidate['__exists'])) {
                $primaryImages[$sku] = $candidate;
            }
        }

        foreach ($items as &$item) {
            $sku = $item['sku'] ?? null;
            // Use image from item_images if available, otherwise fallback to items.image_url
            if ($sku && isset($primaryImages[$sku])) {
                $resolvedCandidate = $primaryImages[$sku];
                $candidateExists = !empty($resolvedCandidate['__exists']);
                unset($resolvedCandidate['__exists']);
                if ($candidateExists) {
                    $item['primary_image'] = $resolvedCandidate;
                } else {
                    $item['primary_image'] = null;
                }
            } elseif (!empty($item['image_url'])) {
                $fallbackPath = self::resolveExistingInventoryImagePath((string) $item['image_url']) ?? (string) $item['image_url'];
                if (self::resolveExistingInventoryImagePath($fallbackPath) !== null) {
                    $item['primary_image'] = [
                        'image_path' => $fallbackPath,
                        'alt_text' => $item['name'] ?? null,
                        'is_primary' => true,
                        'sort_order' => 0
                    ];
                } else {
                    $item['primary_image'] = null;
                }
            } else {
                $item['primary_image'] = null;
            }
        }

        return $items;
    }

    private static function resolveExistingInventoryImagePath(string $imagePath): ?string
    {
        $normalized = ltrim(trim($imagePath), '/');
        if ($normalized === '') {
            return null;
        }

        $absolute = __DIR__ . '/../' . $normalized;
        if (is_file($absolute)) {
            return $normalized;
        }

        $ext = strtolower((string) pathinfo($normalized, PATHINFO_EXTENSION));
        if ($ext === 'webp') {
            $pngRelative = preg_replace('/\.webp$/i', '.png', $normalized);
            if (is_string($pngRelative) && $pngRelative !== '') {
                $pngAbsolute = __DIR__ . '/../' . $pngRelative;
                if (is_file($pngAbsolute)) {
                    return $pngRelative;
                }
            }
        }

        return null;
    }
}
