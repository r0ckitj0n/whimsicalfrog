<?php
session_start();

// Get the raw POST data
$json = file_get_contents('php://input');
$data = json_decode($json, true);

// If clear flag is set, destroy the session
if (isset($data['clear']) && $data['clear'] === true) {
    session_destroy();
    $_SESSION = array();
    echo json_encode(['success' => true, 'message' => 'Session cleared']);
    exit;
}

// Otherwise, set the session data
if (isset($data)) {
    $_SESSION['user'] = json_encode($data);
    echo json_encode(['success' => true, 'message' => 'Session updated']);
} else {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid data']);
}
?> 