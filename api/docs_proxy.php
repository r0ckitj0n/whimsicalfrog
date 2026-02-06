<?php
/**
 * api/docs_proxy.php
 * Entry point for documentation proxy requests.
 */
require_once __DIR__ . '/api_bootstrap.php';
require_once __DIR__ . '/../includes/help_helper.php';

// Handle CORS for local dev
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

wf_handle_docs_proxy();
