<?php
require __DIR__ . '/config.php';
header('Content-Type: application/json');
http_response_code(200);
echo json_encode([
  'ok' => true,
  'time' => date('c'),
]);
