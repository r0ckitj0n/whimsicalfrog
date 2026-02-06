<?php
/**
 * Square Sync Task Helpers for SquareSyncHelper
 */
class SquareSyncTasks
{
    /**
     * Synchronize categories with Square
     */
    public static function syncCategories($baseUrl, $accessToken)
    {
        $categories = Database::queryAll("SELECT DISTINCT category FROM items WHERE category IS NOT NULL AND category != ''");
        $map = [];
        foreach ($categories as $row) {
            $name = $row['category'];
            try {
                $catId = self::findCategoryByName($baseUrl, $accessToken, $name);
                if (!$catId) {
                    $res = SquareApiHelper::makeCall($baseUrl . '/v2/catalog/object', $accessToken, [
                        'idempotency_key' => uniqid('cat_'),
                        'object' => ['type' => 'CATEGORY', 'id' => '#new_cat', 'category_data' => ['name' => $name]]
                    ], 'POST');
                    $catId = $res['catalog_object']['id'] ?? null;
                }
                if ($catId)
                    $map[$name] = $catId;
                // @reason: Category sync failure is non-critical - item syncs without category
            } catch (Exception $e) {
            }
        }
        return $map;
    }

    private static function findCategoryByName($baseUrl, $accessToken, $name)
    {
        $data = ['object_types' => ['CATEGORY'], 'query' => ['exact_query' => ['attribute_name' => 'name', 'attribute_value' => $name]]];
        $res = SquareApiHelper::makeCall($baseUrl . '/v2/catalog/search', $accessToken, $data, 'POST');
        return $res['objects'][0]['id'] ?? null;
    }

    /**
     * Synchronize inventory for a variation
     */
    public static function syncInventory($baseUrl, $accessToken, $locationId, $variationId, $stock_quantity)
    {
        $data = [
            'idempotency_key' => uniqid('inv_'),
            'changes' => [
                [
                    'type' => 'PHYSICAL_COUNT',
                    'physical_count' => [
                        'catalog_object_id' => $variationId,
                        'state' => 'IN_STOCK',
                        'location_id' => $locationId,
                        'quantity' => (string) $stock_quantity,
                        'occurred_at' => date('Y-m-d\TH:i:s\Z')
                    ]
                ]
            ]
        ];
        SquareApiHelper::makeCall($baseUrl . '/v2/inventory/batch-change', $accessToken, $data, 'POST');
        return 'synced';
    }

    /**
     * Upload image to Square
     */
    public static function uploadSquareImage($baseUrl, $accessToken, $filePath, $id)
    {
        $url = $baseUrl . '/v2/catalog/images';
        $mimeType = mime_content_type($filePath) ?: 'image/jpeg';
        $fileName = basename($filePath);

        $meta = [
            'idempotency_key' => uniqid('img_'),
            'object_id' => $id,
            'image' => [
                'type' => 'IMAGE',
                'id' => '#new_image',
                'image_data' => ['caption' => $fileName]
            ]
        ];

        $ch = curl_init();
        $cfile = new CURLFile($filePath, $mimeType, $fileName);
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => ['request' => json_encode($meta), 'image_file' => $cfile],
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $accessToken,
                'Square-Version: 2023-10-18'
            ]
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        // curl_close() is deprecated in PHP 8.5 (no effect since PHP 8.0)

        if ($httpCode >= 200 && $httpCode < 300)
            return 'uploaded';
        error_log("Square Image Upload Failed ($httpCode): " . substr($response, 0, 200));
        return 'failed';
    }
}
