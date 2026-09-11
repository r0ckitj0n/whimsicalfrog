<?php
/**
 * Early landing-room (Room A) boot payload for HTML injection.
 * Lets the SPA paint doors/coords without waiting on XHR waterfalls.
 */

class LandingBootHelper
{
    public const DEFAULT_BG = '/images/backgrounds/realistic/realistic-roomA-frogs.webp';

    /**
     * Build the critical landing payload + image preload hrefs.
     *
     * @return array{payload: array, preload_hrefs: string[]}
     */
    public static function buildForRoomA(): array
    {
        $bg = self::resolveBackgroundUrl();
        $destinations = self::loadDestinations();
        $coordinates = self::loadCoordinates();
        $roomSettings = self::loadRoomSettings();

        $payload = [
            'bg' => $bg,
            'destinations' => $destinations,
            'coordinates' => $coordinates,
            'roomSettings' => $roomSettings,
        ];

        $preload = [$bg];
        foreach ($destinations as $dest) {
            $image = isset($dest['image']) ? (string) $dest['image'] : '';
            if ($image === '') {
                continue;
            }
            if ($image[0] !== '/') {
                $image = '/' . ltrim($image, '/');
            }
            $preload[] = $image;
            if (preg_match('/\.png$/i', $image)) {
                $webp = preg_replace('/\.png$/i', '.webp', $image);
                if (is_string($webp) && $webp !== '' && self::publicFileExists($webp)) {
                    $preload[] = $webp;
                }
            }
        }

        return [
            'payload' => $payload,
            'preload_hrefs' => array_values(array_unique(array_filter($preload))),
        ];
    }

    public static function resolveBackgroundUrl(): string
    {
        $bg = '';
        if (function_exists('get_active_background')) {
            $bg = (string) get_active_background('A');
        }
        if ($bg === '') {
            try {
                $row = Database::queryOne(
                    "SELECT background_url FROM room_settings WHERE room_number = ? LIMIT 1",
                    ['A']
                );
                if (!empty($row['background_url'])) {
                    $bg = (string) $row['background_url'];
                }
            } catch (Throwable $e) {
                $bg = '';
            }
        }
        if ($bg === '') {
            return self::DEFAULT_BG;
        }
        return '/' . ltrim($bg, '/');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function loadDestinations(): array
    {
        try {
            if (!class_exists('AreaMappingSitemapHelper', false)) {
                require_once __DIR__ . '/../area_mappings/helpers/AreaMappingSitemapHelper.php';
            }
            $rows = AreaMappingSitemapHelper::getDoorSignDestinationsForRoom('A');
            return is_array($rows) ? array_values($rows) : [];
        } catch (Throwable $e) {
            error_log('[LandingBootHelper] destinations failed: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function loadCoordinates(): array
    {
        try {
            if (!class_exists('AreaMappingSchemaHelper', false)) {
                require_once __DIR__ . '/../area_mappings/helpers/AreaMappingSchemaHelper.php';
            }
            if (!class_exists('AreaMappingFetchHelper', false)) {
                require_once __DIR__ . '/../area_mappings/helpers/AreaMappingFetchHelper.php';
            }

            $room = AreaMappingFetchHelper::normalizeRoomNumber('A');
            $map = null;
            if (AreaMappingSchemaHelper::hasColumn('room_maps', 'coordinates')) {
                $where = 'room_number = ?';
                if (AreaMappingSchemaHelper::hasColumn('room_maps', 'is_active')) {
                    $where .= ' AND is_active = 1';
                }
                $orderExpr = AreaMappingSchemaHelper::roomMapsRecencyOrderExpr();
                $map = Database::queryOne(
                    "SELECT coordinates FROM room_maps WHERE $where ORDER BY {$orderExpr} LIMIT 1",
                    [$room]
                );
            }

            return self::normalizeCoordinates($map['coordinates'] ?? '[]');
        } catch (Throwable $e) {
            error_log('[LandingBootHelper] coordinates failed: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * @param mixed $rawCoords
     * @return list<array<string, mixed>>
     */
    public static function normalizeCoordinates($rawCoords): array
    {
        $coords = $rawCoords;
        for ($i = 0; $i < 4 && is_string($coords); $i++) {
            $decoded = json_decode($coords, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                break;
            }
            $coords = $decoded;
        }

        if (is_array($coords) && isset($coords['rectangles']) && is_string($coords['rectangles'])) {
            $decodedRects = json_decode($coords['rectangles'], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $coords['rectangles'] = $decodedRects;
            }
        }
        if (is_array($coords) && isset($coords['polygons']) && is_string($coords['polygons'])) {
            $decodedPolygons = json_decode($coords['polygons'], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $coords['polygons'] = $decodedPolygons;
            }
        }

        $list = [];
        if (is_array($coords)) {
            $hasNamedBuckets = false;
            foreach (['rectangles', 'polygons', 'coordinates'] as $bucketKey) {
                if (isset($coords[$bucketKey]) && is_array($coords[$bucketKey])) {
                    $hasNamedBuckets = true;
                    foreach ($coords[$bucketKey] as $row) {
                        $list[] = $row;
                    }
                }
            }
            if (!$hasNamedBuckets && array_values($coords) === $coords) {
                $list = $coords;
            }
        }

        $normalized = [];
        $idx = 0;
        foreach ($list as $coord) {
            if (!is_array($coord)) {
                continue;
            }
            if (!isset($coord['selector']) || $coord['selector'] === '') {
                $coord['selector'] = $coord['id'] ?? ('area-' . ($idx + 1));
            }
            $normalized[] = $coord;
            $idx++;
        }

        return array_values($normalized);
    }

    /**
     * @return array<string, mixed>
     */
    private static function loadRoomSettings(): array
    {
        try {
            $room = Database::queryOne("SELECT * FROM room_settings WHERE room_number = ? LIMIT 1", ['A']);
            if (!is_array($room) || $room === []) {
                return [
                    'render_context' => 'fullscreen',
                    'target_aspect_ratio' => null,
                    'icon_panel_color' => 'transparent',
                    'icon_vertical_alignment' => 'middle',
                ];
            }
            return [
                'render_context' => $room['render_context'] ?? 'fullscreen',
                'target_aspect_ratio' => $room['target_aspect_ratio'] ?? null,
                'icon_panel_color' => $room['icon_panel_color'] ?? 'transparent',
                'icon_vertical_alignment' => $room['icon_vertical_alignment'] ?? 'middle',
            ];
        } catch (Throwable $e) {
            error_log('[LandingBootHelper] room settings failed: ' . $e->getMessage());
            return [
                'render_context' => 'fullscreen',
                'target_aspect_ratio' => null,
                'icon_panel_color' => 'transparent',
                'icon_vertical_alignment' => 'middle',
            ];
        }
    }

    private static function publicFileExists(string $publicPath): bool
    {
        $rel = ltrim($publicPath, '/');
        $abs = dirname(__DIR__, 1) . '/../' . $rel;
        // includes/helpers -> repo root is dirname(__DIR__, 2)
        $abs = dirname(__DIR__, 2) . '/' . $rel;
        return is_file($abs);
    }
}
