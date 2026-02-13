<?php
// includes/area_mappings/helpers/AreaMappingSignHelper.php

require_once __DIR__ . '/AreaMappingFetchHelper.php';

class AreaMappingSignHelper
{
    public static function isSignImageUrl(string $url): bool
    {
        $clean = trim($url);
        if ($clean === '') {
            return false;
        }
        $path = parse_url($clean, PHP_URL_PATH);
        $path = is_string($path) ? $path : $clean;
        return (strpos($path, '/images/signs/') !== false)
            || (strpos($path, 'images/signs/') !== false)
            || (strpos($path, '/signs/') !== false);
    }

    public static function normalizeImageUrl(string $url): string
    {
        $clean = trim($url);
        if ($clean === '') {
            return '';
        }
        if (preg_match('/^https?:\\/\\//i', $clean)) {
            return $clean;
        }
        if (str_starts_with($clean, '/')) {
            return $clean;
        }
        if (str_starts_with($clean, 'images/')) {
            return '/' . $clean;
        }
        return '/images/' . ltrim($clean, '/');
    }

    public static function recordAssetForMapping(
        int $mappingId,
        string $roomNumber,
        string $imageUrl,
        ?string $pngUrl,
        ?string $webpUrl,
        string $source,
        bool $activate = true
    ): array {
        if ($mappingId <= 0) {
            throw new Exception('Mapping ID is required');
        }
        $roomNumber = AreaMappingFetchHelper::normalizeRoomNumber($roomNumber);
        $imageUrl = self::normalizeImageUrl($imageUrl);
        $pngUrl = $pngUrl ? self::normalizeImageUrl($pngUrl) : null;
        $webpUrl = $webpUrl ? self::normalizeImageUrl($webpUrl) : null;

        $mapping = Database::queryOne("SELECT id, room_number FROM area_mappings WHERE id = ? LIMIT 1", [$mappingId]);
        if (!$mapping) {
            throw new Exception('Mapping not found');
        }
        if (AreaMappingFetchHelper::normalizeRoomNumber((string) $mapping['room_number']) !== $roomNumber) {
            throw new Exception('Mapping does not belong to the specified room');
        }

        $existing = Database::queryOne(
            "SELECT * FROM shortcut_sign_assets WHERE mapping_id = ? AND image_url = ? LIMIT 1",
            [$mappingId, $imageUrl]
        );
        if ($existing) {
            if ($activate) {
                Database::execute("UPDATE shortcut_sign_assets SET is_active = 0 WHERE mapping_id = ?", [$mappingId]);
                Database::execute("UPDATE shortcut_sign_assets SET is_active = 1 WHERE id = ? LIMIT 1", [(int) $existing['id']]);
                Database::execute(
                    "UPDATE area_mappings SET content_image = ?, link_image = ? WHERE id = ? LIMIT 1",
                    [$imageUrl, $imageUrl, $mappingId]
                );
            }
            return $existing;
        }

        Database::beginTransaction();
        try {
            if ($activate) {
                Database::execute("UPDATE shortcut_sign_assets SET is_active = 0 WHERE mapping_id = ?", [$mappingId]);
            }
            Database::execute(
                "INSERT INTO shortcut_sign_assets (mapping_id, room_number, image_url, png_url, webp_url, source, is_active)\n                 VALUES (?, ?, ?, ?, ?, ?, ?)",
                [$mappingId, $roomNumber, $imageUrl, $pngUrl, $webpUrl, $source, $activate ? 1 : 0]
            );
            $assetId = (int) Database::lastInsertId();

            if ($activate) {
                Database::execute(
                    "UPDATE area_mappings SET content_image = ?, link_image = ? WHERE id = ? LIMIT 1",
                    [$imageUrl, $imageUrl, $mappingId]
                );
            }
            Database::commit();
            return [
                'id' => $assetId,
                'mapping_id' => $mappingId,
                'room_number' => $roomNumber,
                'image_url' => $imageUrl,
                'png_url' => $pngUrl,
                'webp_url' => $webpUrl,
                'source' => $source,
                'is_active' => $activate ? 1 : 0
            ];
        } catch (Exception $e) {
            Database::rollBack();
            throw $e;
        }
    }

    public static function fetchAssets(int $mappingId, string $roomNumber): array
    {
        if ($mappingId <= 0) {
            throw new Exception('Mapping ID is required');
        }
        $roomNumber = AreaMappingFetchHelper::normalizeRoomNumber($roomNumber);
        return Database::queryAll(
            "SELECT id, mapping_id, room_number, image_url, png_url, webp_url, source, is_active, created_at\n             FROM shortcut_sign_assets\n             WHERE mapping_id = ? AND room_number = ?\n             ORDER BY created_at DESC, id DESC",
            [$mappingId, $roomNumber]
        );
    }

    public static function setActiveAsset(int $mappingId, int $assetId, string $roomNumber): array
    {
        if ($mappingId <= 0 || $assetId <= 0) {
            throw new Exception('Mapping ID and asset ID are required');
        }
        $roomNumber = AreaMappingFetchHelper::normalizeRoomNumber($roomNumber);
        $asset = Database::queryOne(
            "SELECT id, image_url FROM shortcut_sign_assets WHERE id = ? AND mapping_id = ? AND room_number = ? LIMIT 1",
            [$assetId, $mappingId, $roomNumber]
        );
        if (!$asset) {
            throw new Exception('Sign image not found');
        }

        Database::beginTransaction();
        try {
            Database::execute("UPDATE shortcut_sign_assets SET is_active = 0 WHERE mapping_id = ?", [$mappingId]);
            Database::execute("UPDATE shortcut_sign_assets SET is_active = 1 WHERE id = ? LIMIT 1", [$assetId]);
            Database::execute(
                "UPDATE area_mappings SET content_image = ?, link_image = ? WHERE id = ? LIMIT 1",
                [$asset['image_url'], $asset['image_url'], $mappingId]
            );
            Database::commit();
        } catch (Exception $e) {
            Database::rollBack();
            throw $e;
        }

        return $asset;
    }

    public static function deleteAsset(int $mappingId, int $assetId, string $roomNumber): array
    {
        if ($mappingId <= 0 || $assetId <= 0) {
            throw new Exception('Mapping ID and asset ID are required');
        }
        $roomNumber = AreaMappingFetchHelper::normalizeRoomNumber($roomNumber);
        $asset = Database::queryOne(
            "SELECT id, image_url, is_active FROM shortcut_sign_assets WHERE id = ? AND mapping_id = ? AND room_number = ? LIMIT 1",
            [$assetId, $mappingId, $roomNumber]
        );
        if (!$asset) {
            throw new Exception('Sign image not found');
        }

        Database::beginTransaction();
        try {
            Database::execute("DELETE FROM shortcut_sign_assets WHERE id = ? LIMIT 1", [$assetId]);
            if ((int) $asset['is_active'] === 1) {
                $next = Database::queryOne(
                    "SELECT id, image_url FROM shortcut_sign_assets WHERE mapping_id = ? AND room_number = ? ORDER BY created_at DESC, id DESC LIMIT 1",
                    [$mappingId, $roomNumber]
                );
                if ($next) {
                    Database::execute("UPDATE shortcut_sign_assets SET is_active = 0 WHERE mapping_id = ?", [$mappingId]);
                    Database::execute("UPDATE shortcut_sign_assets SET is_active = 1 WHERE id = ? LIMIT 1", [(int) $next['id']]);
                    Database::execute(
                        "UPDATE area_mappings SET content_image = ?, link_image = ? WHERE id = ? LIMIT 1",
                        [$next['image_url'], $next['image_url'], $mappingId]
                    );
                } else {
                    Database::execute(
                        "UPDATE area_mappings SET content_image = NULL, link_image = NULL WHERE id = ? LIMIT 1",
                        [$mappingId]
                    );
                }
            }
            Database::commit();
        } catch (Exception $e) {
            Database::rollBack();
            throw $e;
        }

        return self::fetchAssets($mappingId, $roomNumber);
    }
}
