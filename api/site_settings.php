<?php
/**
 * Compatibility shim for /api/site_settings.php.
 *
 * Missing endpoints on IONOS return Sedo parking HTML (HTTP 404). Expose a
 * minimal JSON payload from BusinessSettings / site helpers so older clients
 * do not poison ApiClient with HTML error bodies.
 */
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/bootstrap.php';
wf_bootstrap();

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/site_settings.php';
@require_once __DIR__ . '/../includes/business_settings_helper.php';

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

try {
    $site_settings = [
        'name' => function_exists('wf_site_name') ? wf_site_name() : '',
        'tagline' => function_exists('wf_site_tagline') ? wf_site_tagline() : '',
        'logo_path' => function_exists('wf_brand_logo_path') ? wf_brand_logo_path() : '',
        'app_url' => function_exists('wf_app_url') ? wf_app_url() : '',
        'business_email' => function_exists('wf_business_email') ? wf_business_email() : '',
        'social_links' => function_exists('wf_social_links') ? wf_social_links() : [],
    ];

    if (class_exists('BusinessSettings') && method_exists('BusinessSettings', 'getAllSettings')) {
        $all = BusinessSettings::getAllSettings();
        if (is_array($all)) {
            $site_settings = array_merge($site_settings, $all);
        }
    }

    echo json_encode([
        'success' => true,
        'site_settings' => $site_settings,
        'timestamp' => time(),
    ]);
} catch (Throwable $e) {
    error_log('[site_settings] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to load site settings',
    ]);
}
