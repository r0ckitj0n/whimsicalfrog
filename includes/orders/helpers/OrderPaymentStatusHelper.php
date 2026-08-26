<?php

require_once __DIR__ . '/../../Constants.php';

class OrderPaymentStatusHelper
{
    /**
     * Customers cannot self-report payment_status. Only admins (POS) may set it.
     * Square checkouts are marked paid after a successful server-side charge.
     */
    public static function resolve(array $input): string
    {
        $default = ((string) ($input['payment_method'] ?? '')) === WF_Constants::PAYMENT_METHOD_SQUARE
            ? WF_Constants::PAYMENT_STATUS_PAID
            : WF_Constants::PAYMENT_STATUS_PENDING;

        $requested = trim((string) ($input['payment_status'] ?? ''));
        if ($requested === '') {
            return $default;
        }

        $isAdmin = function_exists('isAdmin') && isAdmin();
        return $isAdmin ? $requested : $default;
    }
}
