<?php

// Simple secured DB import endpoint
$requiredToken = 'whfdeploytoken';
if (!isset($_GET['token']) || $_GET['token'] !== $requiredToken) {
    http_response_code(403);
    exit('Forbidden');
}

require_once __DIR__ . '/../db_import_sql.php';
