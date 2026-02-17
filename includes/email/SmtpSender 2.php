<?php

/**
 * Handles SMTP email sending using PHPMailer
 */
class SmtpSender
{
    private static $mailer = null;

    public static function preflight($config)
    {
        if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
            throw new Exception('PHPMailer not available. Install via Composer or use mail() function.');
        }
        $m = new PHPMailer\PHPMailer\PHPMailer(true);
        $m->isSMTP();
        $m->Host = $config['smtp_host'];
        $m->SMTPAuth = (bool) $config['smtp_auth'];
        $m->Username = $config['smtp_username'];
        $m->Password = $config['smtp_password'];
        $m->Port = $config['smtp_port'];

        if ($config['smtp_encryption'] === 'ssl') {
            $m->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        } elseif ($config['smtp_encryption'] === 'tls') {
            $m->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        }

        if (isset($config['smtp_timeout'])) {
            $m->Timeout = (int) $config['smtp_timeout'];
        }

        if (isset($config['smtp_debug'])) {
            $m->SMTPDebug = (int) $config['smtp_debug'];
            $m->Debugoutput = function ($str, $level) {
                error_log('PHPMailer SMTP debug(' . $level . '): ' . $str);
            };
        }

        if (!empty($config['smtp_allow_self_signed'])) {
            $m->SMTPOptions = [
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                ]
            ];
        }

        $m->CharSet = $config['charset'];
        $ok = $m->smtpConnect();
        if (!$ok) {
            throw new Exception('SMTP connect failed');
        }
        // @reason: Best-effort cleanup - connection already verified, close errors are non-fatal
        try {
            $m->smtpClose();
        } catch (\Throwable $__) {
        }
        return true;
    }

    public static function send($to, $subject, $body, $options, $config)
    {
        if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
            throw new Exception('PHPMailer not available.');
        }

        if (self::$mailer === null) {
            self::$mailer = new PHPMailer\PHPMailer\PHPMailer(true);
            self::configureMailer(self::$mailer, $config);
        }

        self::$mailer->clearAllRecipients();
        self::$mailer->clearAttachments();

        if (!empty($config['return_path'])) {
            self::$mailer->Sender = $config['return_path'];
        }
        self::$mailer->setFrom($options['from_email'], $options['from_name']);

        if ($options['reply_to']) {
            self::$mailer->addReplyTo($options['reply_to']);
        }

        if (is_array($to)) {
            foreach ($to as $email) {
                self::$mailer->addAddress($email);
            }
        } else {
            self::$mailer->addAddress($to);
        }

        foreach (['cc', 'bcc'] as $type) {
            if (!empty($options[$type])) {
                $list = is_array($options[$type]) ? $options[$type] : [$options[$type]];
                foreach ($list as $email) {
                    $method = 'add' . strtoupper($type);
                    self::$mailer->$method($email);
                }
            }
        }

        self::$mailer->isHTML($options['is_html']);
        self::$mailer->Subject = $subject;
        self::$mailer->Body = $body;
        self::$mailer->AltBody = $options['is_html'] ? self::htmlToText($body, $config['charset']) : $body;

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

    private static function configureMailer($mailer, $config)
    {
        $mailer->isSMTP();
        $mailer->Host = $config['smtp_host'];
        $mailer->SMTPAuth = (bool) $config['smtp_auth'];
        $mailer->Username = $config['smtp_username'];
        $mailer->Password = $config['smtp_password'];
        $mailer->Port = $config['smtp_port'];

        if ($config['smtp_encryption'] === 'ssl') {
            $mailer->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        } elseif ($config['smtp_encryption'] === 'tls') {
            $mailer->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        }

        if (isset($config['smtp_timeout'])) {
            $mailer->Timeout = (int) $config['smtp_timeout'];
        }

        $mailer->CharSet = $config['charset'];
    }

    private static function htmlToText($html, $charset)
    {
        $text = preg_replace('/<\/(p|div|h[1-6]|li)>/i', "\n", $html);
        $text = preg_replace('/<(br|br\/)\s*>/i', "\n", $text);
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, $charset);
        $text = preg_replace("/\n{3,}/", "\n\n", $text);
        return trim($text);
    }
}
