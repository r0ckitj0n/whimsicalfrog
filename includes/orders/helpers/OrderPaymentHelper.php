<?php
// includes/orders/helpers/OrderPaymentHelper.php

require_once __DIR__ . '/../../square/helpers/SquareConfigHelper.php';

class OrderPaymentHelper
{
    /**
     * Process Square payment
     */
    public static function processSquarePayment($token, $amount, $address = null)
    {
        $credentials = self::getSquareCredentials();
        $problems = [];
        if (!$credentials['enabled']) {
            $problems[] = 'Square integration is disabled';
        }
        if (empty($credentials['token'])) {
            if (!empty($credentials['token_secret_unreadable'])) {
                $problems[] = 'Square access token secret is unreadable (re-enter the access token in Square settings)';
            } else {
                $problems[] = 'Square access token is missing';
            }
        }
        if (empty($credentials['location_id'])) {
            $problems[] = 'Square location ID is missing';
        }

        if (!empty($problems)) {
            self::logError('Square not properly configured', [
                'env' => $credentials['environment'] ?? 'sandbox',
                'enabled' => $credentials['enabled'],
                'has_token' => !empty($credentials['token']),
                'has_location' => !empty($credentials['location_id']),
                'token_secret_present' => !empty($credentials['token_secret_present']),
                'token_secret_unreadable' => !empty($credentials['token_secret_unreadable']),
                'problems' => $problems,
            ]);
            throw new Exception('Payment configuration error: ' . implode('; ', $problems));
        }

        $baseUrl = ($credentials['environment'] ?? 'sandbox') === 'production'
            ? 'https://connect.squareup.com'
            : 'https://connect.squareupsandbox.com';
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

    private static function getSquareCredentials()
    {
        // Use SquareConfigHelper so keys are resolved from category='square',
        // avoiding cross-category collisions in generic BusinessSettings::get().
        try {
            if (class_exists('SquareConfigHelper')) {
                $resolved = SquareConfigHelper::getResolvedCredentials();
                return [
                    'enabled' => self::toBool($resolved['enabled'] ?? false),
                    'environment' => (string) ($resolved['environment'] ?? 'sandbox'),
                    'token' => (string) ($resolved['access_token'] ?? ''),
                    'location_id' => (string) ($resolved['location_id'] ?? ''),
                    'token_secret_present' => self::toBool($resolved['access_token_secret_present'] ?? false),
                    'token_secret_unreadable' => self::toBool($resolved['access_token_secret_unreadable'] ?? false),
                ];
            }
        } catch (Exception $e) {
            self::logError('Square resolved credential lookup failed', ['error' => $e->getMessage()]);
        }

        // Legacy fallback (used only if helper resolution is unavailable).
        $squareEnabled = self::toBool(BusinessSettings::get('square_enabled', false));
        $squareEnv = (string) BusinessSettings::get('square_environment', 'sandbox');
        $tokenKey = ($squareEnv === 'production') ? 'square_production_access_token' : 'square_sandbox_access_token';
        $locKey = ($squareEnv === 'production') ? 'square_production_location_id' : 'square_sandbox_location_id';

        $token = '';
        if (function_exists('secret_get')) {
            $token = (string) (secret_get($tokenKey) ?: secret_get('square_access_token') ?: '');
        }
        if ($token === '') {
            $token = (string) BusinessSettings::get($tokenKey, BusinessSettings::get('square_access_token', ''));
        }

        $locationId = (string) BusinessSettings::get($locKey, '');
        if ($locationId === '') {
            $locationId = (string) BusinessSettings::get('square_location_id', '');
        }

        return [
            'enabled' => $squareEnabled,
            'environment' => $squareEnv,
            'token' => $token,
            'location_id' => $locationId,
            'token_secret_present' => false,
            'token_secret_unreadable' => false,
        ];
    }

    private static function toBool($value)
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value) || is_float($value)) {
            return (int) $value === 1;
        }
        $normalized = strtolower(trim((string) $value));
        return in_array($normalized, ['1', 'true', 'yes', 'on', 'y'], true);
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
