<?php
// Simulate Admin UI test email by invoking api/email_test.php as a POST request
// Usage: php scripts/dev/email_ui_test.php [recipient or empty to use default]

try {
    $recipient = $argv[1] ?? '';

    // Simulate web server environment
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = [];
    if ($recipient !== '') {
        $_POST['to'] = $recipient;
    }

    // Capture output
    ob_start();
    require __DIR__ . '/../../api/email_test.php';
    $output = ob_get_clean();

    // Print the JSON response from the endpoint
    if ($output !== null && $output !== '') {
        echo $output, "\n";
    } else {
        echo json_encode(['success' => false, 'error' => 'No output from email_test endpoint']), "\n";
    }
} catch (Throwable $e) {
    fwrite(STDERR, 'ERROR: ' . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n");
    exit(1);
}
