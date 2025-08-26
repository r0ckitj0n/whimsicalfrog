<?php
// Send a test email using current email configuration
// POST JSON: { "to": "address@example.com", "subject"?: string, "message"?: string }

header('Content-Type: application/json');
header('Cache-Control: no-store');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode([ 'success' => false, 'error' => 'Method Not Allowed' ]);
        exit;
    }

    // Accept JSON or form-encoded
    $raw = file_get_contents('php://input');
    $data = [];
    if (!empty($raw)) {
        $json = json_decode($raw, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($json)) {
            $data = $json;
        }
    }
    if (empty($data)) {
        $data = $_POST;
    }

    $to = trim((string)($data['to'] ?? ''));
    if ($to === '') {
        http_response_code(400);
        echo json_encode([ 'success' => false, 'error' => 'Missing required field: to' ]);
        exit;
    }

    $subject = trim((string)($data['subject'] ?? 'WhimsicalFrog Test Email'));
    $message = (string)($data['message'] ?? '<p>This is a test email from WhimsicalFrog admin settings.</p>');

    // Use central configuration and sender
    require_once __DIR__ . '/email_config.php';

    $ok = sendEmail($to, $subject, $message, strip_tags($message));
    if ($ok) {
        echo json_encode([ 'success' => true ]);
    } else {
        http_response_code(500);
        echo json_encode([ 'success' => false, 'error' => 'sendEmail returned false' ]);
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([ 'success' => false, 'error' => $e->getMessage() ]);
}
