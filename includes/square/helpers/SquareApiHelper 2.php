<?php
// includes/square/helpers/SquareApiHelper.php

class SquareApiHelper
{
    /**
     * Make an authenticated call to the Square API
     */
    public static function makeCall($url, $accessToken, $data = null, $method = 'GET')
    {
        $ch = curl_init();
        $options = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json',
                'Square-Version: 2023-10-18'
            ]
        ];

        if ($method === 'POST') {
            $options[CURLOPT_POST] = true;
            $options[CURLOPT_POSTFIELDS] = json_encode($data);
        } elseif ($method === 'PUT') {
            $options[CURLOPT_CUSTOMREQUEST] = 'PUT';
            $options[CURLOPT_POSTFIELDS] = json_encode($data);
        }

        curl_setopt_array($ch, $options);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        // curl_close() is deprecated in PHP 8.5 (no effect since PHP 8.0)

        if ($error) throw new Exception("Square API cURL Error: " . $error);
        if ($httpCode < 200 || $httpCode >= 300) {
            throw new Exception("Square API Error (HTTP $httpCode): " . $response);
        }

        return json_decode($response, true);
    }

    /**
     * Convert local item to Square catalog format
     */
    public static function convertToSquareFormat($item, $syncFields)
    {
        $squareItem = [
            'type' => 'ITEM',
            'id' => '#new',
            'item_data' => [
                'name' => $item['name'],
                'description' => $item['description'] ?? '',
                'variations' => [
                    [
                        'type' => 'ITEM_VARIATION',
                        'id' => '#new_var',
                        'item_variation_data' => [
                            'name' => 'Regular',
                            'sku' => $item['sku'],
                            'pricing_type' => 'FIXED_PRICING',
                            'track_inventory' => true
                        ]
                    ]
                ]
            ]
        ];

        if (in_array('price', $syncFields) && isset($item['retail_price'])) {
            $squareItem['item_data']['variations'][0]['item_variation_data']['price_money'] = [
                'amount' => (int)($item['retail_price'] * 100),
                'currency' => 'USD'
            ];
        }

        return $squareItem;
    }

    /**
     * Convert Square item to local database format
     */
    public static function convertToLocalFormat($squareItem)
    {
        $variation = $squareItem['item_data']['variations'][0] ?? null;
        return [
            'sku' => $variation['item_variation_data']['sku'] ?? 'SQ-' . substr($squareItem['id'], -8),
            'name' => $squareItem['item_data']['name'] ?? 'Imported Item',
            'description' => $squareItem['item_data']['description'] ?? '',
            'retail_price' => isset($variation['item_variation_data']['price_money']['amount'])
                ? $variation['item_variation_data']['price_money']['amount'] / 100
                : 0,
            'category' => 'Imported',
            'stock_quantity' => 0
        ];
    }

    /**
     * Retrieve multiple catalog objects by ID
     */
    public static function batchRetrieveObjects($baseUrl, $accessToken, $objectIds)
    {
        if (empty($objectIds)) return [];
        $data = ['object_ids' => $objectIds, 'include_related_objects' => false];
        $res = self::makeCall($baseUrl . '/v2/catalog/batch-retrieve', $accessToken, $data, 'POST');
        return $res['objects'] ?? [];
    }

    /**
     * Create or Update a Square catalog object
     */
    public static function upsertObject($baseUrl, $accessToken, $object)
    {
        $data = ['idempotency_key' => uniqid(), 'object' => $object];
        return self::makeCall($baseUrl . '/v2/catalog/object', $accessToken, $data, 'POST');
    }
    public static function findBySKU($baseUrl, $accessToken, $sku)
    {
        $data = [
            'object_types' => ['ITEM_VARIATION'],
            'query' => ['exact_query' => ['attribute_name' => 'sku', 'attribute_value' => (string)$sku]]
        ];

        $res = self::makeCall($baseUrl . '/v2/catalog/search', $accessToken, $data, 'POST');
        if (isset($res['objects'][0]['item_variation_data']['id'])) {
            $id = $res['objects'][0]['item_variation_data']['id'];
            $itemRes = self::makeCall($baseUrl . '/v2/catalog/object/' . $id, $accessToken);
            return $itemRes['object'] ?? null;
        }
        return null;
    }

    /**
     * Update Square diagnostics status in business_settings
     */
    public static function updateDiagnostics($status, $env, $message)
    {
        $timestamp = date('Y-m-d H:i:s');
        $rows = [
            ['last_diag_status', $status, 'Last Diagnostics Status'],
            ['last_diag_env', $env, 'Last Diagnostics Environment'],
            ['last_diag_timestamp', $timestamp, 'Last Diagnostics Timestamp'],
            ['last_diag_message', $message, 'Last Diagnostics Message'],
        ];

        $sql = "INSERT INTO business_settings (category, setting_key, setting_value, setting_type, display_name) 
                VALUES ('square', ?, ?, 'text', ?) 
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = CURRENT_TIMESTAMP";

        foreach ($rows as $row) {
            Database::execute($sql, [$row[0], $row[1], $row[2]]);
        }
    }
}
