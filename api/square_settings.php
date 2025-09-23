<?php
// Square Settings API - Configuration and synchronization for Square integration
require_once 'config.php';
require_once 'business_settings_helper.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

try {
    try {
        Database::getInstance();
    } catch (Exception $e) {
        error_log("Database connection failed: " . $e->getMessage());
        throw $e;
    }

    $action = $_GET['action'] ?? $_POST['action'] ?? '';

    switch ($action) {
        case 'get_settings':
            getSquareSettings($pdo);
            break;

        case 'save_settings':
            saveSquareSettings($pdo);
            break;

        case 'test_connection':
            testSquareConnection($pdo);
            break;

        case 'sync_items':
            syncItemsToSquare($pdo);
            break;

        case 'get_sync_status':
            getSyncStatus($pdo);
            break;

        case 'import_from_square':
            importFromSquare($pdo);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

function getSquareSettings($pdo)
{
    $defaults = [
        'square_enabled' => false,
        'square_environment' => 'sandbox', // sandbox or production
        'square_application_id' => '',
        'square_access_token' => '',
        'square_location_id' => '',
        'square_webhook_signature_key' => '',
        'auto_sync_enabled' => false,
        'sync_direction' => 'to_square', // to_square, from_square, bidirectional
        'sync_frequency' => 'manual', // manual, hourly, daily, weekly
        'sync_fields' => json_encode(['name', 'description', 'price', 'category', 'stock']),
        'price_sync_enabled' => true,
        'inventory_sync_enabled' => true,
        'category_mapping' => json_encode([]),
        'last_sync' => null,
        'sync_errors' => json_encode([])
    ];

    // Get settings from database
    $rows = Database::queryAll("SELECT setting_key, setting_value FROM business_settings WHERE category = 'square'");
    $dbSettings = [];
    foreach ($rows as $r) {
        $dbSettings[$r['setting_key']] = $r['setting_value'];
    }

    // Merge with defaults
    $settings = array_merge($defaults, $dbSettings);

    // Convert JSON strings back to arrays
    $jsonFields = ['sync_fields', 'category_mapping', 'sync_errors'];
    foreach ($jsonFields as $field) {
        if (isset($settings[$field]) && is_string($settings[$field])) {
            $settings[$field] = json_decode($settings[$field], true) ?: [];
        }
    }

    // Convert boolean values
    $boolFields = ['square_enabled', 'auto_sync_enabled', 'price_sync_enabled', 'inventory_sync_enabled'];
    foreach ($boolFields as $field) {
        if (isset($settings[$field])) {
            $settings[$field] = in_array(strtolower($settings[$field]), ['true', '1', 1, true], true);
        }
    }

    echo json_encode([
        'success' => true,
        'settings' => $settings
    ]);
}

function saveSquareSettings($pdo)
{
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        echo json_encode(['success' => false, 'message' => 'Invalid input data']);
        return;
    }

    $allowedSettings = [
        'square_enabled', 'square_environment', 'square_application_id',
        'square_access_token', 'square_location_id', 'square_webhook_signature_key',
        'auto_sync_enabled', 'sync_direction', 'sync_frequency', 'sync_fields',
        'price_sync_enabled', 'inventory_sync_enabled', 'category_mapping'
    ];

    Database::beginTransaction();

    try {
        $sql = "INSERT INTO business_settings (category, setting_key, setting_value, setting_type, display_name, description) 
            VALUES ('square', ?, ?, ?, ?, ?) 
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = CURRENT_TIMESTAMP";

        $savedCount = 0;

        foreach ($input as $key => $value) {
            if (!in_array($key, $allowedSettings)) {
                continue;
            }

            // Convert arrays to JSON
            if (is_array($value)) {
                $value = json_encode($value);
                $type = 'json';
            } elseif (is_bool($value)) {
                $value = $value ? 'true' : 'false';
                $type = 'boolean';
            } else {
                $type = 'text';
            }

            $displayName = ucwords(str_replace('_', ' ', $key));
            $description = getSettingDescription($key);

            $affected = Database::execute($sql, [$key, $value, $type, $displayName, $description]);
            if ($affected > 0) {
                $savedCount++;
            }
        }

        Database::commit();

        echo json_encode([
            'success' => true,
            'message' => "Saved {$savedCount} Square settings successfully"
        ]);

    } catch (Exception $e) {
        Database::rollBack();
        echo json_encode(['success' => false, 'message' => 'Failed to save settings: ' . $e->getMessage()]);
    }
}

function testSquareConnection($pdo)
{
    $settings = getSquareSettingsArray($pdo);

    if (!$settings['square_enabled']) {
        echo json_encode(['success' => false, 'message' => 'Square integration is not enabled']);
        return;
    }

    if (empty($settings['square_access_token']) || empty($settings['square_application_id'])) {
        echo json_encode(['success' => false, 'message' => 'Square credentials are not configured']);
        return;
    }

    try {
        $baseUrl = $settings['square_environment'] === 'production'
            ? 'https://connect.squareup.com'
            : 'https://connect.squareupsandbox.com';

        $response = makeSquareAPICall($baseUrl . '/v2/locations', $settings['square_access_token']);

        if ($response && isset($response['locations'])) {
            $locations = $response['locations'];
            echo json_encode([
                'success' => true,
                'message' => 'Connection successful',
                'locations' => $locations,
                'location_count' => count($locations)
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to retrieve locations']);
        }

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Connection failed: ' . $e->getMessage()]);
    }
}

function syncItemsToSquare($pdo)
{
    $settings = getSquareSettingsArray($pdo);

    if (!$settings['square_enabled']) {
        echo json_encode(['success' => false, 'message' => 'Square integration is not enabled']);
        return;
    }

    try {
        // Get items from our database
        $items = Database::queryAll("SELECT * FROM items WHERE 1=1 ORDER BY sku");

        $baseUrl = $settings['square_environment'] === 'production'
            ? 'https://connect.squareup.com'
            : 'https://connect.squareupsandbox.com';

        $syncResults = [];
        $successCount = 0;
        $errorCount = 0;

        foreach ($items as $item) {
            try {
                $squareItem = convertItemToSquareFormat($item, $settings);

                // Check if item exists in Square
                $existingItem = findSquareItemBySKU($baseUrl, $settings['square_access_token'], $item['sku']);

                if ($existingItem) {
                    // Update existing item
                    $response = updateSquareItem($baseUrl, $settings['square_access_token'], $existingItem['id'], $squareItem);
                    $action = 'updated';
                } else {
                    // Create new item
                    $response = createSquareItem($baseUrl, $settings['square_access_token'], $squareItem);
                    $action = 'created';
                }

                if ($response) {
                    $syncResults[] = [
                        'sku' => $item['sku'],
                        'action' => $action,
                        'status' => 'success'
                    ];
                    $successCount++;
                } else {
                    $syncResults[] = [
                        'sku' => $item['sku'],
                        'action' => $action,
                        'status' => 'failed',
                        'error' => 'Unknown error'
                    ];
                    $errorCount++;
                }

            } catch (Exception $e) {
                $syncResults[] = [
                    'sku' => $item['sku'],
                    'action' => 'sync',
                    'status' => 'failed',
                    'error' => $e->getMessage()
                ];
                $errorCount++;
            }
        }

        // Update last sync time
        updateLastSyncTime($pdo);

        echo json_encode([
            'success' => true,
            'message' => "Sync completed: {$successCount} successful, {$errorCount} failed",
            'results' => $syncResults,
            'summary' => [
                'total_items' => count($items),
                'successful' => $successCount,
                'failed' => $errorCount
            ]
        ]);

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Sync failed: ' . $e->getMessage()]);
    }
}

function getSyncStatus($pdo)
{
    $row = Database::queryOne("SELECT setting_value FROM business_settings WHERE category = 'square' AND setting_key = 'last_sync'");
    $lastSync = $row ? $row['setting_value'] : null;

    $row2 = Database::queryOne("SELECT setting_value FROM business_settings WHERE category = 'square' AND setting_key = 'sync_errors'");
    $syncErrors = $row2 ? $row2['setting_value'] : null;
    $syncErrors = $syncErrors ? json_decode($syncErrors, true) : [];

    // Get item counts
    $cRow = Database::queryOne("SELECT COUNT(*) AS c FROM items");
    $totalItems = $cRow ? (int)$cRow['c'] : 0;

    echo json_encode([
        'success' => true,
        'status' => [
            'last_sync' => $lastSync,
            'total_items' => $totalItems,
            'recent_errors' => array_slice($syncErrors, -5), // Last 5 errors
            'error_count' => count($syncErrors)
        ]
    ]);
}

function importFromSquare($pdo)
{
    $settings = getSquareSettingsArray($pdo);

    if (!$settings['square_enabled']) {
        echo json_encode(['success' => false, 'message' => 'Square integration is not enabled']);
        return;
    }

    try {
        $baseUrl = $settings['square_environment'] === 'production'
            ? 'https://connect.squareup.com'
            : 'https://connect.squareupsandbox.com';

        // Get all items from Square
        $squareItems = getAllSquareItems($baseUrl, $settings['square_access_token']);

        $importResults = [];
        $successCount = 0;
        $errorCount = 0;

        foreach ($squareItems as $squareItem) {
            try {
                $localItem = convertSquareItemToLocalFormat($squareItem);

                // Check if item exists locally
                $existsRow = Database::queryOne("SELECT sku FROM items WHERE sku = ?", [$localItem['sku']]);
                $exists = $existsRow ? $existsRow['sku'] : false;

                if ($exists) {
                    // Update existing item
                    Database::execute(
                        "UPDATE items SET name = ?, description = ?, retailPrice = ?, category = ?, stockLevel = ? WHERE sku = ?",
                        [
                            $localItem['name'],
                            $localItem['description'],
                            $localItem['retailPrice'],
                            $localItem['category'],
                            $localItem['stockLevel'],
                            $localItem['sku']
                        ]
                    );
                    $action = 'updated';
                } else {
                    // Create new item
                    Database::execute(
                        "INSERT INTO items (sku, name, description, retailPrice, category, stockLevel) VALUES (?, ?, ?, ?, ?, ?)",
                        [
                            $localItem['sku'],
                            $localItem['name'],
                            $localItem['description'],
                            $localItem['retailPrice'],
                            $localItem['category'],
                            $localItem['stockLevel']
                        ]
                    );
                    $action = 'created';
                }

                $importResults[] = [
                    'sku' => $localItem['sku'],
                    'action' => $action,
                    'status' => 'success'
                ];
                $successCount++;

            } catch (Exception $e) {
                $importResults[] = [
                    'square_id' => $squareItem['id'] ?? 'unknown',
                    'action' => 'import',
                    'status' => 'failed',
                    'error' => $e->getMessage()
                ];
                $errorCount++;
            }
        }

        updateLastSyncTime($pdo);

        echo json_encode([
            'success' => true,
            'message' => "Import completed: {$successCount} successful, {$errorCount} failed",
            'results' => $importResults,
            'summary' => [
                'total_square_items' => count($squareItems),
                'successful' => $successCount,
                'failed' => $errorCount
            ]
        ]);

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Import failed: ' . $e->getMessage()]);
    }
}

// Helper Functions

function getSquareSettingsArray($pdo)
{
    $rows = Database::queryAll("SELECT setting_key, setting_value FROM business_settings WHERE category = 'square'");
    $settings = [];
    foreach ($rows as $r) {
        $settings[$r['setting_key']] = $r['setting_value'];
    }

    // Convert boolean values
    $boolFields = ['square_enabled', 'auto_sync_enabled', 'price_sync_enabled', 'inventory_sync_enabled'];
    foreach ($boolFields as $field) {
        if (isset($settings[$field])) {
            $settings[$field] = in_array(strtolower($settings[$field]), ['true', '1'], true);
        }
    }

    return $settings;
}

function makeSquareAPICall($url, $accessToken, $data = null, $method = 'GET')
{
    $ch = curl_init();

    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json',
            'Square-Version: 2023-10-18'
        ]
    ]);

    if ($method === 'POST' && $data) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    } elseif ($method === 'PUT' && $data) {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode >= 200 && $httpCode < 300) {
        return json_decode($response, true);
    } else {
        throw new Exception("Square API error: HTTP {$httpCode} - {$response}");
    }
}

function convertItemToSquareFormat($item, $settings)
{
    $syncFields = json_decode($settings['sync_fields'], true) ?: [];

    $squareItem = [
        'type' => 'ITEM',
        'item_data' => [
            'name' => $item['name'],
            'description' => $item['description'] ?? '',
            'category_id' => null, // Will be mapped from category
            'variations' => [
                [
                    'type' => 'ITEM_VARIATION',
                    'item_variation_data' => [
                        'name' => 'Regular',
                        'sku' => $item['sku'],
                        'pricing_type' => 'FIXED_PRICING'
                    ]
                ]
            ]
        ]
    ];

    // Add price if enabled
    if (in_array('price', $syncFields) && $settings['price_sync_enabled'] && isset($item['retailPrice'])) {
        $squareItem['item_data']['variations'][0]['item_variation_data']['price_money'] = [
            'amount' => (int)($item['retailPrice'] * 100), // Convert to cents
            'currency' => 'USD'
        ];
    }

    return $squareItem;
}

function convertSquareItemToLocalFormat($squareItem)
{
    $variation = $squareItem['item_data']['variations'][0] ?? null;

    return [
        'sku' => $variation['item_variation_data']['sku'] ?? 'SQ-' . substr($squareItem['id'], -8),
        'name' => $squareItem['item_data']['name'] ?? 'Imported Item',
        'description' => $squareItem['item_data']['description'] ?? '',
        'retailPrice' => isset($variation['item_variation_data']['price_money']['amount'])
            ? $variation['item_variation_data']['price_money']['amount'] / 100
            : 0,
        'category' => 'Imported',
        'stockLevel' => 0 // Square doesn't always provide inventory
    ];
}

function findSquareItemBySKU($baseUrl, $accessToken, $sku)
{
    try {
        $url = $baseUrl . '/v2/catalog/search';
        $data = [
            'object_types' => ['ITEM'],
            'query' => [
                'text_query' => [
                    'filter' => [
                        'sku' => $sku
                    ]
                ]
            ]
        ];

        $response = makeSquareAPICall($url, $accessToken, $data, 'POST');

        if (isset($response['objects']) && count($response['objects']) > 0) {
            return $response['objects'][0];
        }

        return null;

    } catch (Exception $e) {
        return null;
    }
}

function createSquareItem($baseUrl, $accessToken, $itemData)
{
    $url = $baseUrl . '/v2/catalog/object';
    $data = [
        'idempotency_key' => uniqid(),
        'object' => $itemData
    ];

    return makeSquareAPICall($url, $accessToken, $data, 'POST');
}

function updateSquareItem($baseUrl, $accessToken, $itemId, $itemData)
{
    $url = $baseUrl . '/v2/catalog/object/' . $itemId;
    $itemData['id'] = $itemId;
    $itemData['version'] = time(); // Simplified versioning

    $data = [
        'idempotency_key' => uniqid(),
        'object' => $itemData
    ];

    return makeSquareAPICall($url, $accessToken, $data, 'PUT');
}

function getAllSquareItems($baseUrl, $accessToken)
{
    $url = $baseUrl . '/v2/catalog/list?types=ITEM';
    $response = makeSquareAPICall($url, $accessToken);

    return $response['objects'] ?? [];
}

function updateLastSyncTime($pdo)
{
    Database::execute(
        "INSERT INTO business_settings (category, setting_key, setting_value, setting_type, display_name, description) 
        VALUES ('square', 'last_sync', ?, 'text', 'Last Sync', 'Last synchronization timestamp') 
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = CURRENT_TIMESTAMP",
        [date('Y-m-d H:i:s')]
    );
}

function getSettingDescription($key)
{
    $descriptions = [
        'square_enabled' => 'Enable Square integration',
        'square_environment' => 'Square environment (sandbox or production)',
        'square_application_id' => 'Square Application ID from developer dashboard',
        'square_access_token' => 'Square Access Token for API access',
        'square_location_id' => 'Square Location ID for inventory management',
        'square_webhook_signature_key' => 'Webhook signature key for secure callbacks',
        'auto_sync_enabled' => 'Enable automatic synchronization',
        'sync_direction' => 'Synchronization direction (to_square, from_square, bidirectional)',
        'sync_frequency' => 'How often to sync automatically',
        'sync_fields' => 'Fields to synchronize between systems',
        'price_sync_enabled' => 'Enable price synchronization',
        'inventory_sync_enabled' => 'Enable inventory/stock synchronization',
        'category_mapping' => 'Mapping between local categories and Square categories'
    ];

    return $descriptions[$key] ?? 'Square integration setting';
}

?> 