<?php

require_once __DIR__ . '/../Constants.php';

/**
 * Handles template-based email composition and sending
 */
class TemplateSender
{
    /**
     * Send template-based email
     */
    public static function send($template, $to, $subject, $variables = [], $options = [])
    {
        $templatePath = __DIR__ . '/../../templates/email/' . $template . '.php';

        if (!file_exists($templatePath)) {
            $body = '<!DOCTYPE html><html><body>'
                . '<p><strong>Notice:</strong> Missing email template: ' . htmlspecialchars($template) . '.</p>'
                . '<p>This is a fallback message.</p>'
                . '</body></html>';
            
            if (class_exists('Logger')) {
                Logger::warning('Email template missing; using fallback', ['template' => $template]);
            } else {
                error_log('Email template missing; using fallback: ' . $template);
            }
            return EmailHelper::send($to, $subject, $body, $options);
        }

        extract($variables);
        ob_start();
        include $templatePath;
        $body = ob_get_clean();

        return EmailHelper::send($to, $subject, $body, $options);
    }

    /**
     * Specialized order confirmation
     */
    public static function sendOrderConfirmation($orderData, $customerData, $orderItems)
    {
        $variables = [
            'order' => $orderData,
            WF_Constants::ROLE_CUSTOMER => $customerData,
            'items' => $orderItems
        ];
        $subject = 'Order Confirmation - #' . $orderData['id'];
        return self::send(WF_Constants::EMAIL_TYPE_ORDER_CONFIRMATION, $customerData['email'], $subject, $variables);
    }

    /**
     * Specialized admin notification
     */
    public static function sendAdminNotification($orderData, $customerData, $orderItems, $adminEmail = null)
    {
        $variables = [
            'order' => $orderData,
            WF_Constants::ROLE_CUSTOMER => $customerData,
            'items' => $orderItems
        ];
        $subject = 'New Order Received - #' . $orderData['id'];
        return self::send(WF_Constants::EMAIL_TYPE_ADMIN_NOTIFICATION, $adminEmail, $subject, $variables);
    }

    /**
     * Specialized password reset
     */
    public static function sendPasswordReset($email, $resetToken, $userName = '', $baseUrl = '')
    {
        $variables = [
            'reset_token' => $resetToken,
            'user_name' => $userName,
            'reset_url' => $baseUrl . '/reset-password.php?token=' . $resetToken
        ];
        $subject = 'Password Reset Request';
        return self::send(WF_Constants::EMAIL_TYPE_PASSWORD_RESET, $email, $subject, $variables);
    }

    /**
     * Specialized welcome email
     */
    public static function sendWelcome($email, $userName, $activationToken = null, $baseUrl = '')
    {
        $variables = [
            'user_name' => $userName,
            'activation_token' => $activationToken,
            'activation_url' => $activationToken ? $baseUrl . '/activate.php?token=' . $activationToken : null
        ];
        $subject = 'Welcome to WhimsicalFrog!';
        return self::send(WF_Constants::EMAIL_TYPE_WELCOME, $email, $subject, $variables);
    }
}
