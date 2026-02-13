<?php
// includes/area_mappings/helpers/AreaMappingActionHelper.php

require_once __DIR__ . '/AreaMappingSignHelper.php';

class AreaMappingActionHelper
{
    /**
     * Add or update an area mapping
     */
    public static function addMapping($input)
    {
        $room_number = AreaMappingFetchHelper::normalizeRoomNumber($input['room'] ?? $input['room_number'] ?? null);
        $areaSelector = $input['area_selector'] ?? null;
        $mappingType = $input['mapping_type'] ?? null;
        $id = $input['id'] ?? null;
        $item_sku = $input['item_sku'] ?? null;
        $category_id = $input['category_id'] ?? null;
        $hasDisplayOrder = array_key_exists('display_order', $input);
        $displayOrder = $hasDisplayOrder ? $input['display_order'] : null;
        $linkUrl = $input['link_url'] ?? null;
        $linkLabel = $input['link_label'] ?? null;
        $linkIcon = $input['link_icon'] ?? null;
        $linkImage = $input['link_image'] ?? null;
        $contentTarget = $input['content_target'] ?? null;
        $contentImage = $input['content_image'] ?? null;

        if ($room_number === null || !$areaSelector || !$mappingType) {
            throw new Exception('Room number, area selector, and mapping type are required');
        }

        // Basic validation by type
        if ($mappingType === 'item' && !$id && !$item_sku)
            throw new Exception('Item ID or SKU is required for item mapping');
        if ($mappingType === 'category' && !$category_id)
            throw new Exception('Category ID is required for category mapping');
        if ($mappingType === 'link' && !$linkUrl)
            throw new Exception('Link URL is required for link mapping');
        if (in_array($mappingType, ['content', 'page', 'modal']) && !$contentTarget)
            throw new Exception('Content target is required for content mapping');
        if (in_array($mappingType, ['button', 'action']) && !$linkUrl && !$contentTarget)
            throw new Exception('Target is required for button/action mapping');

        // Auto-placement logic
        $areaSelector = self::resolveAutoAreaSelector($room_number, $areaSelector);

        // Check if exists
        $existing = Database::queryOne(
            "SELECT id, display_order FROM area_mappings WHERE room_number = ? AND area_selector = ? AND is_active = 1",
            [$room_number, $areaSelector]
        );

        if ($existing) {
            $effectiveOrder = $hasDisplayOrder ? $displayOrder : ($existing['display_order'] ?? 0);
            if ($effectiveOrder <= 0) {
                $row = Database::queryOne("SELECT COALESCE(MAX(display_order),0) AS max_order FROM area_mappings WHERE room_number = ?", [$room_number]);
                $effectiveOrder = isset($row['max_order']) ? ((int) $row['max_order']) + 1 : 1;
            }
            Database::execute(
                "UPDATE area_mappings 
                 SET mapping_type = ?, item_sku = ?, category_id = ?, link_url = ?, link_label = ?, link_icon = ?, link_image = ?, content_target = ?, content_image = ?, display_order = ?, is_active = 1
                 WHERE id = ?",
                [$mappingType, $item_sku, $category_id, $linkUrl, $linkLabel, $linkIcon, $linkImage, $contentTarget, $contentImage, $effectiveOrder, $existing['id']]
            );
            self::maybeRecordSignAsset((int) $existing['id'], $room_number, $contentImage, $linkImage, 'mapping_update');
            return ['message' => 'Area mapping updated successfully', 'id' => $existing['id']];
        } else {
            if (!$hasDisplayOrder || $displayOrder <= 0) {
                $row = Database::queryOne("SELECT COALESCE(MAX(display_order),0) AS max_order FROM area_mappings WHERE room_number = ?", [$room_number]);
                $displayOrder = isset($row['max_order']) ? ((int) $row['max_order']) + 1 : 1;
            }
            Database::execute(
                "INSERT INTO area_mappings (room_number, area_selector, mapping_type, item_sku, category_id, link_url, link_label, link_icon, link_image, content_target, content_image, display_order, is_active) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)",
                [$room_number, $areaSelector, $mappingType, $item_sku, $category_id, $linkUrl, $linkLabel, $linkIcon, $linkImage, $contentTarget, $contentImage, $displayOrder]
            );
            $newId = (int) Database::lastInsertId();
            self::maybeRecordSignAsset($newId, $room_number, $contentImage, $linkImage, 'mapping_create');
            return ['message' => 'Area mapping added successfully', 'id' => $newId];
        }
    }

    /**
     * Update an existing mapping by ID
     */
    public static function updateMapping($input)
    {
        $id = $input['id'] ?? null;
        if (!$id)
            throw new Exception('Mapping ID is required');

        $room_number = AreaMappingFetchHelper::normalizeRoomNumber($input['room'] ?? $input['room_number'] ?? null);

        $fields = [
            'room_number' => $room_number,
            'mapping_type' => $input['mapping_type'] ?? null,
            'area_selector' => $input['area_selector'] ?? null,
            'item_sku' => $input['item_sku'] ?? null,
            'category_id' => $input['category_id'] ?? null,
            'link_url' => $input['link_url'] ?? null,
            'link_label' => $input['link_label'] ?? null,
            'link_icon' => $input['link_icon'] ?? null,
            'link_image' => $input['link_image'] ?? null,
            'content_target' => $input['content_target'] ?? null,
            'content_image' => $input['content_image'] ?? null,
            'display_order' => $input['display_order'] ?? null
        ];

        if (array_key_exists('is_active', $input)) {
            $fields['is_active'] = !empty($input['is_active']) ? 1 : 0;
        }

        $updateFields = [];
        $values = [];
        foreach ($fields as $col => $val) {
            if ($val !== null) {
                if ($col === 'area_selector')
                    $val = self::resolveAutoAreaSelector($room_number, $val);
                $updateFields[] = "`$col` = ?";
                $values[] = $val;
            }
        }

        if (empty($updateFields))
            throw new Exception('No fields to update');

        $values[] = $id;
        $result = Database::execute("UPDATE area_mappings SET " . implode(', ', $updateFields) . " WHERE id = ?", $values);
        if ($result > 0) {
            $effectiveRoom = $room_number;
            if ($effectiveRoom === null || $effectiveRoom === '') {
                $row = Database::queryOne("SELECT room_number FROM area_mappings WHERE id = ? LIMIT 1", [$id]);
                $effectiveRoom = (string) ($row['room_number'] ?? '');
            }
            if ($effectiveRoom !== '') {
                self::maybeRecordSignAsset(
                    (int) $id,
                    $effectiveRoom,
                    (string) ($input['content_image'] ?? ''),
                    (string) ($input['link_image'] ?? ''),
                    'mapping_update'
                );
            }
        }

        return $result > 0
            ? ['success' => true, 'message' => 'Area mapping updated successfully']
            : ['success' => true, 'message' => 'No changes made or mapping not found'];
    }

    /**
     * Soft delete a mapping
     */
    public static function deleteMapping($id)
    {
        if (!$id)
            throw new Exception('Mapping ID is required');
        // Idempotent delete: if it's already inactive or missing, treat as success so the UI can self-heal.
        $row = Database::queryOne("SELECT id, is_active FROM area_mappings WHERE id = ? LIMIT 1", [$id]);
        if (!$row) {
            return ['success' => true, 'action' => 'noop', 'message' => 'Mapping already removed'];
        }
        $isActive = (int)($row['is_active'] ?? 0) === 1;
        if ($isActive) {
            $result = Database::execute("UPDATE area_mappings SET is_active = 0 WHERE id = ? LIMIT 1", [$id]);
            return $result > 0
                ? ['success' => true, 'action' => 'deactivated', 'message' => 'Area mapping deactivated']
                : ['success' => true, 'action' => 'noop', 'message' => 'Mapping already removed'];
        }

        // If already inactive, hard-delete so it disappears from raw listings (list_room_raw includes inactive rows).
        $deleted = Database::execute("DELETE FROM area_mappings WHERE id = ? LIMIT 1", [$id]);
        if ($deleted < 1) {
            return ['success' => false, 'action' => 'noop', 'message' => 'Failed to permanently delete mapping'];
        }
        return ['success' => true, 'action' => 'deleted', 'message' => 'Area mapping permanently deleted'];
    }

    /**
     * Swap two mappings
     */
    public static function swapMappings($id1, $id2)
    {
        if (!$id1 || !$id2)
            throw new Exception('Both IDs are required for swapping');

        Database::beginTransaction();
        try {
            $m1 = Database::queryOne("SELECT * FROM area_mappings WHERE id = ? AND is_active = 1", [$id1]);
            $m2 = Database::queryOne("SELECT * FROM area_mappings WHERE id = ? AND is_active = 1", [$id2]);

            if (!$m1 || !$m2)
                throw new Exception('One or both mappings not found');

            $updateSql = "UPDATE area_mappings SET 
                mapping_type = ?, 
                item_sku = ?, 
                category_id = ?, 
                link_url = ?, 
                link_label = ?, 
                link_icon = ?, 
                link_image = ?, 
                content_target = ?, 
                content_image = ?
                WHERE id = ?";

            Database::execute($updateSql, [
                $m2['mapping_type'],
                $m2['item_sku'],
                $m2['category_id'],
                $m2['link_url'],
                $m2['link_label'],
                $m2['link_icon'],
                $m2['link_image'],
                $m2['content_target'],
                $m2['content_image'],
                $id1
            ]);

            Database::execute($updateSql, [
                $m1['mapping_type'],
                $m1['item_sku'],
                $m1['category_id'],
                $m1['link_url'],
                $m1['link_label'],
                $m1['link_icon'],
                $m1['link_image'],
                $m1['content_target'],
                $m1['content_image'],
                $id2
            ]);

            Database::commit();
            return ['message' => 'Area mappings swapped successfully'];
        } catch (Exception $e) {
            Database::rollBack();
            throw $e;
        }
    }

    /**
     * Resolve auto-placement tokens (-beginning-, -end-)
     */
    private static function resolveAutoAreaSelector($room_number, $selectorRaw)
    {
        if ($selectorRaw !== '-beginning-' && $selectorRaw !== '-end-')
            return $selectorRaw;

        $coordsRow = Database::queryOne("SELECT coordinates FROM room_maps WHERE room_number = ? ORDER BY updated_at DESC LIMIT 1", [$room_number]);
        $coords = $coordsRow ? json_decode($coordsRow['coordinates'] ?? '[]', true) : [];
        if (is_array($coords) && isset($coords['polygons']))
            $coords = $coords['polygons'];
        $totalSlots = is_array($coords) ? count($coords) : 0;

        if ($totalSlots < 1)
            return '.area-1';

        $existing = Database::queryAll("SELECT area_selector FROM area_mappings WHERE room_number = ? AND is_active = 1", [$room_number]);
        $taken = [];
        foreach ($existing as $row) {
            if (preg_match('/\.area-(\d+)/', $row['area_selector'], $m))
                $taken[(int) $m[1]] = true;
        }

        if ($selectorRaw === '-beginning-') {
            for ($i = 1; $i <= $totalSlots; $i++) {
                if (!isset($taken[$i]))
                    return '.area-' . $i;
            }
        } else {
            for ($i = $totalSlots; $i >= 1; $i--) {
                if (!isset($taken[$i]))
                    return '.area-' . $i;
            }
        }

        return '.area-1';
    }

    private static function maybeRecordSignAsset(int $mappingId, string $roomNumber, ?string $contentImage, ?string $linkImage, string $source): void
    {
        $imageUrl = trim((string) ($contentImage ?: $linkImage ?: ''));
        if ($imageUrl === '') {
            return;
        }
        if (!AreaMappingSignHelper::isSignImageUrl($imageUrl)) {
            return;
        }
        try {
            AreaMappingSignHelper::recordAssetForMapping($mappingId, $roomNumber, $imageUrl, null, null, $source, true);
        } catch (Exception $e) {
            error_log('shortcut sign asset record failed: ' . $e->getMessage());
        }
    }
}
