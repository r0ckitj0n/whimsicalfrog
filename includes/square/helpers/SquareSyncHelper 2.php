<?php
// includes/square/helpers/SquareSyncHelper.php

class SquareSyncHelper
{
    /**
     * Synchronize local items to Square
     */
    public static function syncItems($pdo, $settings)
    {
        require_once __DIR__ . '/SquareSyncTasks.php';
        $env = $settings['environment'];
        $baseUrl = $env === 'production' ? 'https://connect.squareup.com' : 'https://connect.squareupsandbox.com';
        $accessToken = $settings['access_token'];
        $locationId = $settings['location_id'];

        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $limit = isset($input['limit']) ? (int) $input['limit'] : 5;
        $offset = isset($input['offset']) ? (int) $input['offset'] : 0;

        // 1. Sync Categories
        $categoryMap = SquareSyncTasks::syncCategories($baseUrl, $accessToken);

        // 2. Get items
        $countRow = Database::queryOne("SELECT COUNT(*) as c FROM items");
        $total_items = $countRow ? (int) $countRow['c'] : 0;
        $items = Database::queryAll("SELECT * FROM items ORDER BY sku LIMIT ? OFFSET ?", [$limit, $offset]);

        $syncResults = [];
        $successCount = 0;
        $errorCount = 0;

        foreach ($items as $item) {
            try {
                $squareItem = SquareApiHelper::convertToSquareFormat($item, $settings['sync_fields']);

                if (!empty($item['category']) && isset($categoryMap[$item['category']])) {
                    $squareItem['item_data']['category_id'] = $categoryMap[$item['category']];
                }

                $existingItem = SquareApiHelper::findBySKU($baseUrl, $accessToken, $item['sku']);
                $squareItemId = null;
                $variationId = null;
                $currentImageIds = [];

                if ($existingItem) {
                    $squareItemId = $existingItem['id'];
                    $currentVersion = $existingItem['version'];
                    $currentImageIds = $existingItem['item_data']['image_ids'] ?? [];
                    if (!empty($currentImageIds)) {
                        $squareItem['item_data']['image_ids'] = $currentImageIds;
                    }

                    if (isset($existingItem['item_data']['variations'][0])) {
                        $existingVar = $existingItem['item_data']['variations'][0];
                        $variationId = $existingVar['id'];
                        $squareItem['item_data']['variations'][0]['id'] = $existingVar['id'];
                        $squareItem['item_data']['variations'][0]['version'] = $existingVar['version'];
                    }

                    $response = SquareApiHelper::upsertObject($baseUrl, $accessToken, array_merge($squareItem, ['id' => $squareItemId, 'version' => $currentVersion]));
                    $action = 'updated';
                } else {
                    $response = SquareApiHelper::upsertObject($baseUrl, $accessToken, $squareItem);
                    $squareItemId = $response['catalog_object']['id'] ?? null;
                    $variationId = $response['catalog_object']['item_data']['variations'][0]['id'] ?? null;
                    $action = 'created';
                }

                if ($response) {
                    // 3. Sync Image
                    $imageAction = 'skipped';
                    $shouldUpload = true;

                    if ($squareItemId && !empty($currentImageIds)) {
                        $existingImages = SquareApiHelper::batchRetrieveObjects($baseUrl, $accessToken, $currentImageIds);
                        $localImagePath = self::getPrimaryLocalImage($item['sku']);

                        if ($localImagePath) {
                            $localFilename = basename($localImagePath);
                            foreach ($existingImages as $sqImg) {
                                $sqCaption = $sqImg['image_data']['caption'] ?? '';
                                $sqName = $sqImg['item_data']['name'] ?? '';
                                if ($sqCaption === $localFilename || $sqName === $localFilename) {
                                    $shouldUpload = false;
                                    $imageAction = 'exists_remote';
                                    break;
                                }
                            }
                        }
                    }

                    if ($shouldUpload && $squareItemId) {
                        $imageAction = self::syncItemImage($pdo, $baseUrl, $accessToken, $item['sku'], $squareItemId);
                    }

                    // 4. Sync Inventory if enabled
                    $inventoryAction = 'skipped';
                    if ($variationId && $settings['inventory_sync_enabled'] && !empty($locationId)) {
                        $inventoryAction = SquareSyncTasks::syncInventory($baseUrl, $accessToken, $locationId, $variationId, (int) $item['stock_quantity']);
                    }

                    $syncResults[] = [
                        'sku' => $item['sku'],
                        'action' => $action,
                        'image_action' => $imageAction,
                        'inventory_action' => $inventoryAction,
                        'status' => 'success'
                    ];
                    $successCount++;
                }
            } catch (Exception $e) {
                $syncResults[] = ['sku' => $item['sku'], 'status' => 'failed', 'error' => $e->getMessage()];
                $errorCount++;
            }
        }

        self::updateLastSyncTime();
        self::logDiagnostics($env, $offset, $limit, $successCount, $errorCount, $total_items, $syncResults);

        return [
            'success' => true,
            'results' => $syncResults,
            'pagination' => [
                'has_more' => ($offset + count($items)) < $total_items,
                'next_offset' => $offset + $limit,
                'total_items' => $total_items
            ],
            'summary' => ['total' => $total_items, 'success' => $successCount, 'failed' => $errorCount]
        ];
    }

    /**
     * Import items from Square to local database
     */
    public static function importFromSquare($settings)
    {
        $baseUrl = $settings['environment'] === 'production' ? 'https://connect.squareup.com' : 'https://connect.squareupsandbox.com';
        $accessToken = $settings['access_token'];

        $res = SquareApiHelper::makeCall($baseUrl . '/v2/catalog/list?types=ITEM', $accessToken);
        $squareItems = $res['objects'] ?? [];

        $results = [];
        $success = 0;
        $failed = 0;

        foreach ($squareItems as $sqItem) {
            try {
                $local = SquareApiHelper::convertToLocalFormat($sqItem);

                $sql = "INSERT INTO items (sku, name, description, retail_price, category, stock_quantity) 
                        VALUES (?, ?, ?, ?, ?, ?) 
                        ON DUPLICATE KEY UPDATE name=VALUES(name), description=VALUES(description), retail_price=VALUES(retail_price)";

                Database::execute($sql, [
                    $local['sku'],
                    $local['name'],
                    $local['description'],
                    $local['retail_price'],
                    $local['category'],
                    $local['stock_quantity']
                ]);

                $results[] = ['sku' => $local['sku'], 'status' => 'success'];
                $success++;
            } catch (Exception $e) {
                $results[] = ['id' => $sqItem['id'], 'status' => 'failed', 'error' => $e->getMessage()];
                $failed++;
            }
        }

        self::updateLastSyncTime();
        return ['success' => true, 'results' => $results, 'summary' => ['total' => count($squareItems), 'success' => $success, 'failed' => $failed]];
    }

    private static function syncCategories($baseUrl, $accessToken)
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
     * Get synchronization status
     */
    public static function getSyncStatus()
    {
        $lastSync = Database::queryOne("SELECT setting_value FROM business_settings WHERE category = 'square' AND setting_key = 'last_sync'")['setting_value'] ?? null;
        $syncErrors = json_decode(Database::queryOne("SELECT setting_value FROM business_settings WHERE category = 'square' AND setting_key = 'sync_errors'")['setting_value'] ?? '[]', true) ?: [];
        $total_items_row = Database::queryOne("SELECT COUNT(*) AS c FROM items");
        $total_items = $total_items_row ? (int) $total_items_row['c'] : 0;

        return [
            'success' => true,
            'status' => [
                'last_sync' => $lastSync,
                'total_items' => $total_items,
                'recent_errors' => array_slice($syncErrors, -5),
                'error_count' => count($syncErrors)
            ]
        ];
    }

    /**
     * Test Square connection
     */
    public static function testConnection($settings)
    {
        $env = $settings['environment'];
        $baseUrl = $env === 'production' ? 'https://connect.squareup.com' : 'https://connect.squareupsandbox.com';

        if (!$settings['enabled'])
            throw new Exception('Square integration is not enabled');
        if (empty($settings['access_token']))
            throw new Exception('Square access token is missing');

        try {
            $res = SquareApiHelper::makeCall($baseUrl . '/v2/locations', $settings['access_token']);
            $locations = $res['locations'] ?? [];
            SquareApiHelper::updateDiagnostics('success', $env, 'Connection successful');
            return [
                'success' => true,
                'message' => 'Connection successful',
                'environment' => $env,
                'locations' => $locations,
                'location_count' => count($locations)
            ];
        } catch (Exception $e) {
            SquareApiHelper::updateDiagnostics('error', $env, $e->getMessage());
            throw $e;
        }
    }

    /**
     * Log synchronization results to square_diagnostics.log
     */
    private static function logDiagnostics($env, $offset, $limit, $successCount, $errorCount, $total_items, $syncResults)
    {
        try {
            $logsDir = dirname(__DIR__, 3) . '/logs';
            if (!is_dir($logsDir))
                @mkdir($logsDir, 0755, true);
            $logFile = $logsDir . '/square_diagnostics.log';

            $lines = [
                '=== Square Sync Batch ===',
                'Timestamp: ' . date('Y-m-d H:i:s'),
                'Environment: ' . $env,
                "Batch Offset: $offset  Limit: $limit",
                "Batch Result: $successCount successful, $errorCount failed",
                "Total Items in Queue: $total_items  Has More: " . (($offset + count($syncResults)) < $total_items ? 'yes' : 'no'),
                '',
                '--- Item Details ---'
            ];

            foreach ($syncResults as $res) {
                $status = $res['status'] ?? 'unknown';
                $icon = ($status === 'success') ? '✅' : '❌';
                $sku = $res['sku'] ?? 'N/A';
                $action = $res['action'] ?? 'unknown';
                $inv = !empty($res['inventory_action']) ? (' [Inv: ' . $res['inventory_action'] . ']') : '';
                $img = !empty($res['image_action']) ? (' [Img: ' . $res['image_action'] . ']') : '';
                $lines[] = sprintf('%s SKU: %s - %s (%s)%s%s', $icon, $sku, $action, $status, $inv, $img);
                if (!empty($res['error']))
                    $lines[] = '    Error: ' . $res['error'];
            }
            $lines[] = '';
            @file_put_contents($logFile, implode(PHP_EOL, $lines) . PHP_EOL, FILE_APPEND | LOCK_EX);
        } catch (Exception $e) {
            error_log('Square sync logging failed: ' . $e->getMessage());
        }
    }

    /**
     * Get primary local image path for a SKU
     */
    public static function getPrimaryLocalImage($sku)
    {
        $allImages = Database::queryAll("SELECT image_path, is_primary FROM item_images WHERE sku = ? ORDER BY is_primary DESC", [$sku]);
        if (empty($allImages))
            return null;

        $isLegacyFormat = fn($path) => preg_match('/\.(jpg|jpeg|png|gif|bmp)$/i', $path);

        foreach ($allImages as $img) {
            if ($img['is_primary'] && $isLegacyFormat($img['image_path']))
                return $img['image_path'];
        }
        foreach ($allImages as $img) {
            if ($isLegacyFormat($img['image_path']))
                return $img['image_path'];
        }
        return $allImages[0]['image_path'] ?? null;
    }

    public static function syncItemImage($pdo, $baseUrl, $accessToken, $sku, $squareItemId)
    {
        require_once __DIR__ . '/SquareSyncTasks.php';
        $allImages = Database::queryAll("SELECT image_path, is_primary FROM item_images WHERE sku = ? ORDER BY is_primary DESC", [$sku]);
        if (empty($allImages))
            return 'no_local_image';

        $selectedImage = null;
        $isLegacyFormat = fn($path) => preg_match('/\.(jpg|jpeg|png|gif|bmp)$/i', $path);

        foreach ($allImages as $img) {
            if ($img['is_primary'] && $isLegacyFormat($img['image_path'])) {
                $selectedImage = $img;
                break;
            }
        }
        if (!$selectedImage) {
            foreach ($allImages as $img) {
                if ($isLegacyFormat($img['image_path'])) {
                    $selectedImage = $img;
                    break;
                }
            }
        }
        if (!$selectedImage)
            $selectedImage = $allImages[0];
        if (!$selectedImage || empty($selectedImage['image_path']))
            return 'no_valid_image';

        $fsPath = dirname(__DIR__, 3) . '/' . ltrim($selectedImage['image_path'], '/');
        if (!file_exists($fsPath))
            return 'file_not_found';

        return SquareSyncTasks::uploadSquareImage($baseUrl, $accessToken, $fsPath, $squareItemId);
    }

    private static function uploadSquareImage($baseUrl, $accessToken, $filePath, $id)
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

    private static function syncInventory($baseUrl, $accessToken, $locationId, $variationId, $stock_quantity)
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

    private static function updateLastSyncTime()
    {
        Database::execute(
            "INSERT INTO business_settings (category, setting_key, setting_value, setting_type, display_name) 
            VALUES ('square', 'last_sync', ?, 'text', 'Last Sync') 
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = CURRENT_TIMESTAMP",
            [date('Y-m-d H:i:s')]
        );
    }
}
