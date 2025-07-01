<?php
/**
 * Centralized Email Helper
 * Handles email sending with both mail() and PHPMailer support
 */

class EmailHelper {
    private static $config = [
        'smtp_enabled' => false,
        'smtp_host' => '',
        'smtp_port' => 587,
        'smtp_username' => '',
        'smtp_password' => '',
        'smtp_encryption' => 'tls',
        'from_email' => '',
        'from_name' => '',
        'reply_to' => '',
        'charset' => 'UTF-8'
    ];

    private static $mailer = null;

    /**
     * Configure email settings
     */
    public static function configure($config) {
        self::$config = array_merge(self::$config, $config);
    }

    /**
     * Send email using configured method
     */
    public static function send($to, $subject, $body, $options = []) {
        $options = array_merge([
            'from_email' => self::$config['from_email'],
            'from_name' => self::$config['from_name'],
            'reply_to' => self::$config['reply_to'],
            'is_html' => true,
            'attachments' => [],
            'cc' => [],
            'bcc' => []
        ], $options);

        try {
            $result = false;
            if (self::$config['smtp_enabled']) {
                $result = self::sendWithSMTP($to, $subject, $body, $options);
            } else {
                $result = self::sendWithMail($to, $subject, $body, $options);
            }
            
            // Log successful email
            if ($result && class_exists('DatabaseLogger')) {
                $emailType = 'general';
                if (strpos($subject, 'Order Confirmation') !== false) {
                    $emailType = 'order_confirmation';
                } elseif (strpos($subject, 'New Order Received') !== false) {
                    $emailType = 'admin_notification';
                } elseif (strpos($subject, 'Welcome') !== false) {
                    $emailType = 'welcome';
                } elseif (strpos($subject, 'Password Reset') !== false) {
                    $emailType = 'password_reset';
                }
                
                DatabaseLogger::logEmail(
                    is_array($to) ? implode(', ', $to) : $to,
                    $options['from_email'],
                    $subject,
                    $emailType,
                    'sent'
                );
            }
            
            return $result;
        } catch (Exception $e) {
            // Log failed email
            if (class_exists('DatabaseLogger')) {
                DatabaseLogger::logEmail(
                    is_array($to) ? implode(', ', $to) : $to,
                    $options['from_email'],
                    $subject,
                    'general',
                    'failed',
                    $e->getMessage()
                );
            }
            
            Logger::error('Email send failed', [
                'to' => $to,
                'subject' => $subject,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Send email using PHP mail() function
     */
    private static function sendWithMail($to, $subject, $body, $options) {
        $headers = [];
        
        // Set content type
        if ($options['is_html']) {
            $headers[] = 'Content-Type: text/html; charset=' . self::$config['charset'];
        } else {
            $headers[] = 'Content-Type: text/plain; charset=' . self::$config['charset'];
        }
        
        // Set from header
        if ($options['from_email']) {
            $from = $options['from_name'] ? 
                $options['from_name'] . ' <' . $options['from_email'] . '>' : 
                $options['from_email'];
            $headers[] = 'From: ' . $from;
        }
        
        // Set reply-to header
        if ($options['reply_to']) {
            $headers[] = 'Reply-To: ' . $options['reply_to'];
        }
        
        // Set CC
        if (!empty($options['cc'])) {
            $headers[] = 'Cc: ' . (is_array($options['cc']) ? implode(', ', $options['cc']) : $options['cc']);
        }
        
        // Set BCC
        if (!empty($options['bcc'])) {
            $headers[] = 'Bcc: ' . (is_array($options['bcc']) ? implode(', ', $options['bcc']) : $options['bcc']);
        }
        
        // Additional headers
        $headers[] = 'X-Mailer: WhimsicalFrog Email Helper';
        $headers[] = 'MIME-Version: 1.0';
        
        $headerString = implode("\r\n", $headers);
        
        $result = mail($to, $subject, $body, $headerString);
        
        if (!$result) {
            throw new Exception('mail() function returned false');
        }
        
        return true;
    }

    /**
     * Send email using SMTP (PHPMailer)
     */
    private static function sendWithSMTP($to, $subject, $body, $options) {
        if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
            throw new Exception('PHPMailer not available. Install via Composer or use mail() function.');
        }

        if (self::$mailer === null) {
            self::$mailer = new PHPMailer\PHPMailer\PHPMailer(true);
            
            // SMTP configuration
            self::$mailer->isSMTP();
            self::$mailer->Host = self::$config['smtp_host'];
            self::$mailer->SMTPAuth = true;
            self::$mailer->Username = self::$config['smtp_username'];
            self::$mailer->Password = self::$config['smtp_password'];
            self::$mailer->Port = self::$config['smtp_port'];
            
            if (self::$config['smtp_encryption'] === 'ssl') {
                self::$mailer->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
            } elseif (self::$config['smtp_encryption'] === 'tls') {
                self::$mailer->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            }
            
            self::$mailer->CharSet = self::$config['charset'];
        }

        // Clear previous recipients
        self::$mailer->clearAllRecipients();
        self::$mailer->clearAttachments();
        
        // Set sender
        self::$mailer->setFrom($options['from_email'], $options['from_name']);
        
        // Set reply-to
        if ($options['reply_to']) {
            self::$mailer->addReplyTo($options['reply_to']);
        }
        
        // Set recipients
        if (is_array($to)) {
            foreach ($to as $email) {
                self::$mailer->addAddress($email);
            }
        } else {
            self::$mailer->addAddress($to);
        }
        
        // Set CC
        if (!empty($options['cc'])) {
            $ccList = is_array($options['cc']) ? $options['cc'] : [$options['cc']];
            foreach ($ccList as $cc) {
                self::$mailer->addCC($cc);
            }
        }
        
        // Set BCC
        if (!empty($options['bcc'])) {
            $bccList = is_array($options['bcc']) ? $options['bcc'] : [$options['bcc']];
            foreach ($bccList as $bcc) {
                self::$mailer->addBCC($bcc);
            }
        }
        
        // Set content
        self::$mailer->isHTML($options['is_html']);
        self::$mailer->Subject = $subject;
        self::$mailer->Body = $body;
        
        // Add attachments
        if (!empty($options['attachments'])) {
            foreach ($options['attachments'] as $attachment) {
                if (is_string($attachment)) {
                    self::$mailer->addAttachment($attachment);
                } elseif (is_array($attachment)) {
                    self::$mailer->addAttachment(
                        $attachment['path'],
                        $attachment['name'] ?? '',
                        $attachment['encoding'] ?? 'base64',
                        $attachment['type'] ?? ''
                    );
                }
            }
        }
        
        return self::$mailer->send();
    }

    /**
     * Send template-based email
     */
    public static function sendTemplate($template, $to, $subject, $variables = [], $options = []) {
        $templatePath = __DIR__ . '/../templates/email/' . $template . '.php';
        
        if (!file_exists($templatePath)) {
            throw new Exception("Email template not found: $template");
        }
        
        // Extract variables for template
        extract($variables);
        
        // Capture template output
        ob_start();
        include $templatePath;
        $body = ob_get_clean();
        
        return self::send($to, $subject, $body, $options);
    }

    /**
     * Send order confirmation email
     */
    public static function sendOrderConfirmation($orderData, $customerData, $orderItems) {
        $variables = [
            'order' => $orderData,
            'customer' => $customerData,
            'items' => $orderItems
        ];
        
        $subject = 'Order Confirmation - #' . $orderData['id'];
        
        return self::sendTemplate('order_confirmation', $customerData['email'], $subject, $variables);
    }

    /**
     * Send admin notification email
     */
    public static function sendAdminNotification($orderData, $customerData, $orderItems, $adminEmail = null) {
        $variables = [
            'order' => $orderData,
            'customer' => $customerData,
            'items' => $orderItems
        ];
        
        $subject = 'New Order Received - #' . $orderData['id'];
        $adminEmail = $adminEmail ?: self::$config['from_email'];
        
        return self::sendTemplate('admin_notification', $adminEmail, $subject, $variables);
    }

    /**
     * Send password reset email
     */
    public static function sendPasswordReset($email, $resetToken, $userName = '') {
        $variables = [
            'reset_token' => $resetToken,
            'user_name' => $userName,
            'reset_url' => self::getBaseUrl() . '/reset-password.php?token=' . $resetToken
        ];
        
        $subject = 'Password Reset Request';
        
        return self::sendTemplate('password_reset', $email, $subject, $variables);
    }

    /**
     * Send welcome email
     */
    public static function sendWelcome($email, $userName, $activationToken = null) {
        $variables = [
            'user_name' => $userName,
            'activation_token' => $activationToken,
            'activation_url' => $activationToken ? 
                self::getBaseUrl() . '/activate.php?token=' . $activationToken : null
        ];
        
        $subject = 'Welcome to WhimsicalFrog!';
        
        return self::sendTemplate('welcome', $email, $subject, $variables);
    }

    /**
     * Test email configuration
     */
    public static function test($testEmail, $testMessage = 'This is a test email.') {
        $subject = 'Email Configuration Test';
        $body = $testMessage . "\n\nSent at: " . date('Y-m-d H:i:s');
        
        return self::send($testEmail, $subject, $body, ['is_html' => false]);
    }

    /**
     * Get base URL for email links
     */
    private static function getBaseUrl() {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $protocol . $host;
    }

    /**
     * Validate email address
     */
    public static function isValidEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Create email from business settings
     */
    public static function createFromBusinessSettings($pdo) {
        try {
            $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM business_settings WHERE category = 'email'");
            $stmt->execute();
            $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            
            $config = [
                'smtp_enabled' => ($settings['smtp_enabled'] ?? 'false') === 'true',
                'smtp_host' => $settings['smtp_host'] ?? '',
                'smtp_port' => (int)($settings['smtp_port'] ?? 587),
                'smtp_username' => $settings['smtp_username'] ?? '',
                'smtp_password' => $settings['smtp_password'] ?? '',
                'smtp_encryption' => $settings['smtp_encryption'] ?? 'tls',
                'from_email' => $settings['from_email'] ?? '',
                'from_name' => $settings['from_name'] ?? 'WhimsicalFrog',
                'reply_to' => $settings['reply_to'] ?? ''
            ];
            
            self::configure($config);
            return true;
        } catch (Exception $e) {
            Logger::error('Failed to load email settings from database', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Log email activity
     */
    public static function logEmail($to, $subject, $status = 'sent', $error = null, $orderId = null) {
        try {
            $pdo = Database::getInstance();
            
            $stmt = $pdo->prepare("
                INSERT INTO email_logs (to_email, from_email, subject, status, error_message, order_id, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $to,
                self::$config['from_email'],
                $subject,
                $status,
                $error,
                $orderId
            ]);
            
            return true;
        } catch (Exception $e) {
            Logger::error('Failed to log email', ['error' => $e->getMessage()]);
            return false;
        }
    }
}

// Convenience functions
function send_email($to, $subject, $body, $options = []) {
    return EmailHelper::send($to, $subject, $body, $options);
}

function send_order_confirmation($orderData, $customerData, $orderItems) {
    return EmailHelper::sendOrderConfirmation($orderData, $customerData, $orderItems);
}

function send_admin_notification($orderData, $customerData, $orderItems, $adminEmail = null) {
    return EmailHelper::sendAdminNotification($orderData, $customerData, $orderItems, $adminEmail);
}

function test_email($testEmail, $testMessage = 'This is a test email.') {
    return EmailHelper::test($testEmail, $testMessage);
} 