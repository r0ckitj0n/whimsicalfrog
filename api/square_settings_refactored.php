<?php
/**
 * REFACTORED Square Settings API - Demonstrates centralized function usage
 * 
 * This is a demonstration of how the original square_settings.php can be 
 * improved using our centralized helpers.
 * 
 * IMPROVEMENTS DEMONSTRATED:
 * - Session management with SessionManager
 * - Database operations with Database class
 * - HTTP responses with Response class
 * - cURL operations with HttpClient
 * - JSON input parsing with Response::getJsonInput()
 * - Logging with Logger class
 * - File operations with FileHelper (if needed)
 */

// Include centralized functions (includes all helpers)
require_once __DIR__ . '/../includes/functions.php';
require_once 'business_settings_helper.php';

// Initialize session securely
session_init();

// Set CORS headers using Response helper
Response::setCorsHeaders();

// Validate HTTP method
Response::validateMethod(['GET', 'POST', 'PUT', 'DELETE']);

// Get database connection using centralized Database class
$db = Database::getInstance();

try {
    $action = $_GET['action'] ?? $_POST['action'] ?? '';
    
    // Log API call for analytics
    Logger::apiCall('square_settings', $action, [
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        'ip' => Response::getClientIP()
    ]);
    
    switch ($action) {
        case 'get_settings':
            getSquareSettings($db);
            break;
            
        case 'save_settings':
            saveSquareSettings($db);
            break;
            
        case 'test_connection':
            testSquareConnection($db);
            break;
            
        case 'sync_items':
            syncItemsToSquare($db);
            break;
            
        case 'get_sync_status':
            getSyncStatus($db);
            break;
            
        case 'import_from_square':
            importFromSquare($db);
            break;
            
        default:
            Response::error('Invalid action', ['available_actions' => [
                'get_settings', 'save_settings', 'test_connection', 
                'sync_items', 'get_sync_status', 'import_from_square'
            ]], 400);
            break;
    }
    
} catch (Exception $e) {
    Logger::exception($e, [
        'action' => $action ?? 'unknown',
        'request_method' => $_SERVER['REQUEST_METHOD']
    ]);
    Response::serverError('An unexpected error occurred', ['error_id' => uniqid()]);
}

function getSquareSettings($db) {
    try {
        $defaults = [
            'square_enabled' => false,
            'square_environment' => 'sandbox',
            'square_application_id' => '',
            'square_access_token' => '',
            'square_location_id' => '',
            'square_webhook_signature_key' => '',
            'auto_sync_enabled' => false,
            'sync_direction' => 'to_square',
            'sync_frequency' => 'manual',
            'sync_fields' => ['name', 'description', 'price', 'category', 'stock'],
            'price_sync_enabled' => true,
            'inventory_sync_enabled' => true,
            'category_mapping' => [],
            'last_sync' => null,
            'sync_errors' => []
        ];
        
        // Use centralized database query method
        $dbSettings = $db->queryAll(
            "SELECT setting_key, setting_value FROM business_settings WHERE category = 'square'",
            [],
            PDO::FETCH_KEY_PAIR
        );
        
        // Merge with defaults
        $settings = array_merge($defaults, $dbSettings);
        
        // Process JSON and boolean fields
        $jsonFields = ['sync_fields', 'category_mapping', 'sync_errors'];
        foreach ($jsonFields as $field) {
            if (isset($settings[$field]) && is_string($settings[$field])) {
                $settings[$field] = json_decode($settings[$field], true) ?: [];
            }
        }
        
        $boolFields = ['square_enabled', 'auto_sync_enabled', 'price_sync_enabled', 'inventory_sync_enabled'];
        foreach ($boolFields as $field) {
            if (isset($settings[$field])) {
                $settings[$field] = in_array(strtolower($settings[$field]), ['true', '1', 1, true], true);
            }
        }
        
        Logger::info('Square settings retrieved', ['settings_count' => count($settings)]);
        Response::success($settings, 'Square settings retrieved successfully');
        
    } catch (Exception $e) {
        Logger::databaseError('Failed to retrieve Square settings', $e);
        Response::serverError('Failed to retrieve settings');
    }
}

function saveSquareSettings($db) {
    try {
        // Use centralized JSON input parsing
        $input = Response::getJsonInput();
        if (!$input) {
            Response::error('No input data provided', null, 400);
            return;
        }
        
        $allowedSettings = [
            'square_enabled', 'square_environment', 'square_application_id', 
            'square_access_token', 'square_location_id', 'square_webhook_signature_key',
            'auto_sync_enabled', 'sync_direction', 'sync_frequency', 'sync_fields',
            'price_sync_enabled', 'inventory_sync_enabled', 'category_mapping'
        ];
        
        // Use centralized transaction handling
        $db->beginTransaction();
        
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
            
            // Use centralized execute method
            $result = $db->execute("
                INSERT INTO business_settings (category, setting_key, setting_value, setting_type, display_name, description) 
                VALUES ('square', ?, ?, ?, ?, ?) 
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = CURRENT_TIMESTAMP
            ", [$key, $value, $type, $displayName, $description]);
            
            if ($result) {
                $savedCount++;
            }
        }
        
        $db->commit();
        
        Logger::userAction('square_settings_saved', [
            'settings_count' => $savedCount,
            'user_ip' => Response::getClientIP()
        ]);
        
        Response::success([
            'saved_count' => $savedCount
        ], "Saved {$savedCount} Square settings successfully");
        
    } catch (Exception $e) {
        $db->rollback();
        Logger::databaseError('Failed to save Square settings', $e);
        Response::serverError('Failed to save settings');
    }
}

function testSquareConnection($db) {
    try {
        $settings = getSquareSettingsArray($db);
        
        if (!$settings['square_enabled']) {
            Response::error('Square integration is not enabled', null, 400);
            return;
        }
        
        if (empty($settings['square_access_token']) || empty($settings['square_application_id'])) {
            Response::validationError('Square credentials are not configured');
            return;
        }
        
        $baseUrl = $settings['square_environment'] === 'production' 
            ? 'https://connect.squareup.com'
            : 'https://connect.squareupsandbox.com';
        
        // Use centralized HTTP client instead of manual cURL
        $httpClient = HttpClient::create()
            ->setAuth($settings['square_access_token'])
            ->setHeader('Square-Version', '2023-10-18');
        
        $response = $httpClient->get($baseUrl . '/v2/locations');
        
        if ($response->isSuccess()) {
            $data = $response->json();
            
            if ($data && isset($data['locations'])) {
                $locations = $data['locations'];
                
                Logger::info('Square connection test successful', [
                    'environment' => $settings['square_environment'],
                    'location_count' => count($locations)
                ]);
                
                Response::success([
                    'locations' => $locations,
                    'location_count' => count($locations),
                    'environment' => $settings['square_environment']
                ], 'Connection successful');
            } else {
                Response::error('Invalid response from Square API');
            }
        } else {
            Logger::warning('Square connection test failed', [
                'status_code' => $response->getStatusCode(),
                'response_body' => $response->getBody()
            ]);
            
            Response::error('Square API connection failed', [
                'status_code' => $response->getStatusCode(),
                'error_details' => $response->getBody()
            ]);
        }
        
    } catch (Exception $e) {
        Logger::exception($e, ['action' => 'test_square_connection']);
        Response::serverError('Connection test failed');
    }
}

function syncItemsToSquare($db) {
    try {
        $settings = getSquareSettingsArray($db);
        
        if (!$settings['square_enabled']) {
            Response::error('Square integration is not enabled');
            return;
        }
        
        // Get items to sync using centralized database query
        $items = $db->queryAll("
            SELECT sku, name, description, retailPrice, category, stockLevel 
            FROM items 
            WHERE status = 'active'
        ");
        
        if (empty($items)) {
            Response::success(['synced_count' => 0], 'No items to sync');
            return;
        }
        
        $baseUrl = $settings['square_environment'] === 'production' 
            ? 'https://connect.squareup.com'
            : 'https://connect.squareupsandbox.com';
        
        // Use centralized HTTP client
        $httpClient = HttpClient::create()
            ->setAuth($settings['square_access_token'])
            ->setHeader('Square-Version', '2023-10-18');
        
        $syncResults = [];
        $successCount = 0;
        $errorCount = 0;
        
        foreach ($items as $item) {
            try {
                // Convert item to Square format
                $squareItem = convertItemToSquareFormat($item, $settings);
                
                // Check if item already exists in Square
                $existingItem = findSquareItemBySKU($httpClient, $baseUrl, $item['sku']);
                
                if ($existingItem) {
                    // Update existing item
                    $response = $httpClient->put(
                        $baseUrl . '/v2/catalog/object/' . $existingItem['id'],
                        [
                            'idempotency_key' => uniqid(),
                            'object' => $squareItem
                        ]
                    );
                } else {
                    // Create new item
                    $response = $httpClient->post(
                        $baseUrl . '/v2/catalog/object',
                        [
                            'idempotency_key' => uniqid(),
                            'object' => $squareItem
                        ]
                    );
                }
                
                if ($response->isSuccess()) {
                    $successCount++;
                    $syncResults[] = ['sku' => $item['sku'], 'status' => 'success'];
                } else {
                    $errorCount++;
                    $syncResults[] = [
                        'sku' => $item['sku'], 
                        'status' => 'error', 
                        'error' => $response->getBody()
                    ];
                }
                
            } catch (Exception $e) {
                $errorCount++;
                $syncResults[] = [
                    'sku' => $item['sku'], 
                    'status' => 'error', 
                    'error' => $e->getMessage()
                ];
            }
        }
        
        // Update last sync time using centralized database method
        $db->execute("
            INSERT INTO business_settings (category, setting_key, setting_value, setting_type, display_name, description) 
            VALUES ('square', 'last_sync', ?, 'text', 'Last Sync', 'Last synchronization timestamp') 
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = CURRENT_TIMESTAMP
        ", [date('Y-m-d H:i:s')]);
        
        Logger::info('Square sync completed', [
            'total_items' => count($items),
            'successful' => $successCount,
            'failed' => $errorCount
        ]);
        
        Response::success([
            'total_items' => count($items),
            'successful' => $successCount,
            'failed' => $errorCount,
            'results' => $syncResults
        ], "Sync completed: {$successCount} successful, {$errorCount} failed");
        
    } catch (Exception $e) {
        Logger::exception($e, ['action' => 'sync_items_to_square']);
        Response::serverError('Sync failed');
    }
}

function getSyncStatus($db) {
    try {
        $status = $db->queryRow("
            SELECT setting_value as last_sync 
            FROM business_settings 
            WHERE category = 'square' AND setting_key = 'last_sync'
        ");
        
        $lastSync = $status['last_sync'] ?? null;
        
        Response::success([
            'last_sync' => $lastSync,
            'last_sync_formatted' => $lastSync ? date('M j, Y g:i A', strtotime($lastSync)) : 'Never',
            'sync_status' => $lastSync ? 'completed' : 'pending'
        ], 'Sync status retrieved');
        
    } catch (Exception $e) {
        Logger::databaseError('Failed to get sync status', $e);
        Response::serverError('Failed to get sync status');
    }
}

function importFromSquare($db) {
    try {
        $settings = getSquareSettingsArray($db);
        
        if (!$settings['square_enabled']) {
            Response::error('Square integration is not enabled');
            return;
        }
        
        $baseUrl = $settings['square_environment'] === 'production' 
            ? 'https://connect.squareup.com'
            : 'https://connect.squareupsandbox.com';
        
        // Use centralized HTTP client
        $httpClient = HttpClient::create()
            ->setAuth($settings['square_access_token'])
            ->setHeader('Square-Version', '2023-10-18');
        
        $response = $httpClient->get($baseUrl . '/v2/catalog/list?types=ITEM');
        
        if (!$response->isSuccess()) {
            Response::error('Failed to retrieve items from Square', [
                'status_code' => $response->getStatusCode(),
                'error' => $response->getBody()
            ]);
            return;
        }
        
        $data = $response->json();
        $squareItems = $data['objects'] ?? [];
        
        if (empty($squareItems)) {
            Response::success(['imported_count' => 0], 'No items found in Square');
            return;
        }
        
        $importResults = [];
        $successCount = 0;
        $errorCount = 0;
        
        $db->beginTransaction();
        
        foreach ($squareItems as $squareItem) {
            try {
                $localItem = convertSquareItemToLocalFormat($squareItem);
                
                // Check if item already exists
                $existing = $db->queryRow("SELECT id FROM items WHERE sku = ?", [$localItem['sku']]);
                
                if ($existing) {
                    // Update existing item
                    $db->execute("
                        UPDATE items 
                        SET name = ?, description = ?, retailPrice = ?, category = ? 
                        WHERE sku = ?
                    ", [
                        $localItem['name'],
                        $localItem['description'],
                        $localItem['retailPrice'],
                        $localItem['category'],
                        $localItem['sku']
                    ]);
                } else {
                    // Insert new item
                    $db->execute("
                        INSERT INTO items (sku, name, description, retailPrice, category, stockLevel, status) 
                        VALUES (?, ?, ?, ?, ?, ?, 'active')
                    ", [
                        $localItem['sku'],
                        $localItem['name'],
                        $localItem['description'],
                        $localItem['retailPrice'],
                        $localItem['category'],
                        $localItem['stockLevel']
                    ]);
                }
                
                $successCount++;
                $importResults[] = ['sku' => $localItem['sku'], 'status' => 'success'];
                
            } catch (Exception $e) {
                $errorCount++;
                $importResults[] = [
                    'sku' => $localItem['sku'] ?? 'unknown',
                    'status' => 'error',
                    'error' => $e->getMessage()
                ];
            }
        }
        
        $db->commit();
        
        Logger::info('Square import completed', [
            'total_square_items' => count($squareItems),
            'successful' => $successCount,
            'failed' => $errorCount
        ]);
        
        Response::success([
            'total_square_items' => count($squareItems),
            'successful' => $successCount,
            'failed' => $errorCount,
            'results' => $importResults
        ], "Import completed: {$successCount} successful, {$errorCount} failed");
        
    } catch (Exception $e) {
        $db->rollback();
        Logger::exception($e, ['action' => 'import_from_square']);
        Response::serverError('Import failed');
    }
}

// Helper Functions (updated to use centralized helpers)

function getSquareSettingsArray($db) {
    $settings = $db->queryAll(
        "SELECT setting_key, setting_value FROM business_settings WHERE category = 'square'",
        [],
        PDO::FETCH_KEY_PAIR
    );
    
    // Convert boolean values
    $boolFields = ['square_enabled', 'auto_sync_enabled', 'price_sync_enabled', 'inventory_sync_enabled'];
    foreach ($boolFields as $field) {
        if (isset($settings[$field])) {
            $settings[$field] = in_array(strtolower($settings[$field]), ['true', '1'], true);
        }
    }
    
    return $settings;
}

function convertItemToSquareFormat($item, $settings) {
    $syncFields = json_decode($settings['sync_fields'], true) ?: [];
    
    $squareItem = [
        'type' => 'ITEM',
        'item_data' => [
            'name' => $item['name'],
            'description' => $item['description'] ?? '',
            'category_id' => null,
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
            'amount' => (int)($item['retailPrice'] * 100),
            'currency' => 'USD'
        ];
    }
    
    return $squareItem;
}

function convertSquareItemToLocalFormat($squareItem) {
    $variation = $squareItem['item_data']['variations'][0] ?? null;
    
    return [
        'sku' => $variation['item_variation_data']['sku'] ?? 'SQ-' . substr($squareItem['id'], -8),
        'name' => $squareItem['item_data']['name'] ?? 'Imported Item',
        'description' => $squareItem['item_data']['description'] ?? '',
        'retailPrice' => isset($variation['item_variation_data']['price_money']['amount']) 
            ? $variation['item_variation_data']['price_money']['amount'] / 100 
            : 0,
        'category' => 'Imported',
        'stockLevel' => 0
    ];
}

function findSquareItemBySKU($httpClient, $baseUrl, $sku) {
    try {
        $response = $httpClient->post($baseUrl . '/v2/catalog/search', [
            'object_types' => ['ITEM'],
            'query' => [
                'text_query' => [
                    'filter' => [
                        'sku' => $sku
                    ]
                ]
            ]
        ]);
        
        if ($response->isSuccess()) {
            $data = $response->json();
            if (isset($data['objects']) && count($data['objects']) > 0) {
                return $data['objects'][0];
            }
        }
        
        return null;
        
    } catch (Exception $e) {
        Logger::warning('Failed to find Square item by SKU', ['sku' => $sku, 'error' => $e->getMessage()]);
        return null;
    }
}

function getSettingDescription($key) {
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