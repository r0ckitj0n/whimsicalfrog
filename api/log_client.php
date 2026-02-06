<?php
/**
 * Log Client API - Proxy to website_logs.php
 */
$_POST['action'] = 'ingest_client_logs';
require_once __DIR__ . '/website_logs.php';
