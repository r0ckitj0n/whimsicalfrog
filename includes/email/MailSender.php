<?php

/**
 * Handles legacy PHP mail() function sending
 */
class MailSender
{
    public static function send($to, $subject, $body, $options, $charset)
    {
        $headers = [];

        if ($options['is_html']) {
            $headers[] = 'Content-Type: text/html; charset=' . $charset;
        } else {
            $headers[] = 'Content-Type: text/plain; charset=' . $charset;
        }

        if ($options['from_email']) {
            $from = $options['from_name'] ?
                $options['from_name'] . ' <' . $options['from_email'] . '>' :
                $options['from_email'];
            $headers[] = 'From: ' . $from;
        }

        if ($options['reply_to']) {
            $headers[] = 'Reply-To: ' . $options['reply_to'];
        }

        if (!empty($options['cc'])) {
            $headers[] = 'Cc: ' . (is_array($options['cc']) ? implode(', ', $options['cc']) : $options['cc']);
        }

        if (!empty($options['bcc'])) {
            $headers[] = 'Bcc: ' . (is_array($options['bcc']) ? implode(', ', $options['bcc']) : $options['bcc']);
        }

        $headers[] = 'X-Mailer: WhimsicalFrog Email Helper';
        $headers[] = 'MIME-Version: 1.0';

        $headerString = implode("\r\n", $headers);

        $result = mail($to, $subject, $body, $headerString);

        if (!$result) {
            throw new Exception('mail() function returned false');
        }

        return true;
    }
}
