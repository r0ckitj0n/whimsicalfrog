<?php
// includes/orders/helpers/OrderPaymentHelper.php

class OrderPaymentHelper
{
    /**
     * Process Square payment
     */
    public static function processSquarePayment($token, $amount, $address = null)
    {
        $squareEnabled = (bool) BusinessSettings::get('square_enabled', false);
        $squareEnv = (string) BusinessSettings::get('square_environment', 'sandbox');

        $credentials = self::getSquareCredentials($squareEnv);
        if (!$squareEnabled || empty($credentials['token']) || empty($credentials['location_id'])) {
            self::logError('Square not properly configured', [
                'env' => $squareEnv,
                'enabled' => $squareEnabled,
                'has_token' => !empty($credentials['token']),
                'has_location' => !empty($credentials['location_id'])
            ]);
            throw new Exception('Payment configuration error');
        }

        $baseUrl = $squareEnv === 'production' ? 'https://connect.squareup.com' : 'https://connect.squareupsandbox.com';
        $amountCents = (int) round($amount * 100);

        if ($amountCents <= 0) {
            throw new Exception('Invalid amount for payment');
        }

        $payload = [
            'idempotency_key' => uniqid('whf_', true),
            'source_id' => $token,
            'amount_money' => [
                'amount' => $amountCents,
                'currency' => BusinessSettings::get('currency_code', 'USD')
            ],
            'location_id' => $credentials['location_id'],
            'autocomplete' => true,
        ];

        if ($address && is_array($address)) {
            $payload['shipping_address'] = [
                'address_line_1' => $address['address_line_1'] ?? '',
                'address_line_2' => $address['address_line_2'] ?? '',
                'locality' => $address['city'] ?? '',
                'administrative_district_level_1' => $address['state'] ?? '',
                'postal_code' => $address['zip_code'] ?? ''
            ];
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $baseUrl . '/v2/payments',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $credentials['token'],
                'Content-Type: application/json',
                'Square-Version: 2023-10-18'
            ],
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload)
        ]);

        $resp = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        // curl_close() is deprecated in PHP 8.5 (no effect since PHP 8.0)

        if ($resp === false || $httpCode < 200 || $httpCode >= 300) {
            self::logError('Square payment HTTP failure', [
                'httpCode' => $httpCode,
                'curlErr' => $curlErr,
                'resp' => $resp
            ]);
            throw new Exception('Payment failed');
        }

        $data = json_decode($resp, true);
        if (($data['payment']['status'] ?? '') !== 'COMPLETED') {
            self::logError('Square payment not completed', ['resp' => $data]);
            throw new Exception('Payment not completed');
        }

        return $data['payment']['id'] ?? null;
    }

    private static function getSquareCredentials($env)
    {
        $tokenKey = ($env === 'production') ? 'square_production_access_token' : 'square_sandbox_access_token';
        $locKey = ($env === 'production') ? 'square_production_location_id' : 'square_sandbox_location_id';

        $token = '';
        if (function_exists('secret_get')) {
            // @reason: Secret store access is optional - fallback to BusinessSettings
            try {
                $token = secret_get($tokenKey) ?: secret_get('square_access_token');
            } catch (Exception $e) {
            }
        }
        if (empty($token)) {
            $token = BusinessSettings::get('square_access_token', '');
        }

        $locationId = (string) BusinessSettings::get($locKey, '');
        if (empty($locationId)) {
            $locationId = (string) BusinessSettings::get('square_location_id', '');
        }

        return ['token' => $token, 'location_id' => $locationId];
    }

    private static function logError($message, $context)
    {
        if (class_exists('Logger')) {
            Logger::debug($message, $context);
        }
        if (class_exists('ErrorLogger')) {
            ErrorLogger::logApiError('square-payments', $message, $context);
        }
    }
}
