<?php
// Admin-only documentation access with pretty URL: /admin/docs.php?path=...
// Delegates streaming to api/admin_file_proxy.php

require_once __DIR__ . '/../api/config.php';
require_once __DIR__ . '/../includes/auth_helper.php';

AuthHelper::requireAdmin(403, 'Admin access required');

$path = isset($_GET['path']) ? (string)$_GET['path'] : '';
$_GET['path'] = $path; // normalize for the proxy

require __DIR__ . '/../api/admin_file_proxy.php';
