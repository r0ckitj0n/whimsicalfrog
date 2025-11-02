<?php
ob_start();
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

require_once __DIR__ . '/business_settings_helper.php';
require_once __DIR__ . '/../includes/secret_store.php';

try {
    // Load from database (single source of truth)
    $settings = BusinessSettings::getByCategory('email');
    // Enforce DB availability and required Business Information presence (no static fallbacks)
    if (!class_exists('Database') || !method_exists('Database', 'isAvailableQuick') || !Database::isAvailableQuick(0.6)) {
        throw new Exception('Database is not accessible. This endpoint requires a live DB connection.');
    }
    $be = (string) BusinessSettings::get('business_email', '');
    $bn = (string) BusinessSettings::get('business_name', '');
    if ($be === '' || $bn === '') {
        throw new Exception('Required Business Information is missing: business_email and/or business_name.');
    }
    // Normalize and map keys expected by the admin UI
    // Normalize boolean for smtpEnabled reliably (handles boolean true/false and common string values)
    $smtpEnabledVal = isset($settings['smtp_enabled']) ? $settings['smtp_enabled'] : false;
    if (is_bool($smtpEnabledVal)) {
        $smtpEnabled = $smtpEnabledVal;
    } else {
        $smtpEnabled = in_array(strtolower((string)$smtpEnabledVal), ['true','1','yes'], true);
    }

    $siteUrl = '';
    try {
        $siteUrl = BusinessSettings::getSiteUrl('');
    } catch (\Throwable $__) {
        // leave blank if not configured
    }
    $config = [
        // Always sourced from Business Information (single source of truth)
        'fromEmail'      => $be,
        'fromName'       => $bn,
        'adminEmail'     => (string) BusinessSettings::get('admin_email', ''),
        'supportEmail'   => (string) BusinessSettings::get('business_support_email', ''),
        'businessDomain' => (string) BusinessSettings::get('business_domain', ''),
        'businessWebsite'=> (string) BusinessSettings::get('business_website', ''),
        'siteUrl'        => $siteUrl,
        'bccEmail'       => isset($settings['bcc_email']) ? (string)$settings['bcc_email'] : '',
        'replyTo'        => isset($settings['reply_to']) ? (string)$settings['reply_to'] : '',
        'smtpEnabled'    => $smtpEnabled,
        'smtpHost'       => isset($settings['smtp_host']) ? (string)$settings['smtp_host'] : '',
        'smtpPort'       => isset($settings['smtp_port']) ? (string)$settings['smtp_port'] : '',
        'smtpUsername'   => isset($settings['smtp_username']) ? (string)$settings['smtp_username'] : '',
        // Never return password
        'smtpPassword'   => '',
        'smtpEncryption' => isset($settings['smtp_encryption']) ? (string)$settings['smtp_encryption'] : '',
        // Additional SMTP settings
        'smtpAuth'       => (function($val){
            if ($val === null) return true; // default true
            if (is_bool($val)) return $val;
            $s = strtolower((string)$val);
            return in_array($s, ['1','true','yes','on'], true);
        })(isset($settings['smtp_auth']) ? $settings['smtp_auth'] : null),
        'smtpTimeout'    => isset($settings['smtp_timeout']) ? (string)$settings['smtp_timeout'] : '30',
        'smtpDebug'      => (function($val){
            if ($val === null) return false;
            if (is_bool($val)) return $val;
            $s = strtolower((string)$val);
            return in_array($s, ['1','true','yes','on'], true);
        })(isset($settings['smtp_debug']) ? $settings['smtp_debug'] : null),
        // Advanced headers and DKIM
        'returnPath'     => isset($settings['return_path']) ? (string)$settings['return_path'] : '',
        'dkimDomain'     => isset($settings['dkim_domain']) ? (string)$settings['dkim_domain'] : '',
        'dkimSelector'   => isset($settings['dkim_selector']) ? (string)$settings['dkim_selector'] : '',
        'dkimIdentity'   => isset($settings['dkim_identity']) ? (string)$settings['dkim_identity'] : '',
        'smtpAllowSelfSigned' => (function($val){
            if ($val === null) return false;
            if (is_bool($val)) return $val;
            $s = strtolower((string)$val);
            return in_array($s, ['1','true','yes','on'], true);
        })(isset($settings['smtp_allow_self_signed']) ? $settings['smtp_allow_self_signed'] : null),
        // Display-only business info (for hydration and status)
        'businessEmail'  => $be,
        'businessName'   => $bn,
        // Secret presence flags for UI badges
        'secretUsernamePresent' => (function(){ try { $u = secret_get('smtp_username'); return is_string($u) && trim($u) !== ''; } catch (\Throwable $__) { return false; } })(),
        'secretPasswordPresent' => (function(){ try { $p = secret_get('smtp_password'); return is_string($p) && trim($p) !== ''; } catch (\Throwable $__) { return false; } })(),
        'dkimPrivateKeyPresent' => (function(){ try { $k = secret_get('dkim_private_key'); return is_string($k) && trim($k) !== ''; } catch (\Throwable $__) { return false; } })(),
    ];

    // fromEmail/fromName/adminEmail already sourced from Business Information

    // Effective status summary for UI
    $config['usingSmtp'] = (bool)$config['smtpEnabled'];
    $config['effectiveProvider'] = $config['usingSmtp'] ? 'smtp' : 'mail';
    $config['effectiveFromEmail'] = $config['fromEmail'] ?: $config['businessEmail'] ?: '';
    $config['effectiveFromName'] = $config['fromName'] ?: $config['businessName'] ?: '';

    // Optional debug block: helps diagnose category/key mismatches without changing normal behavior
    $debug = null;
    if (isset($_GET['debug']) && (string)$_GET['debug'] === '1') {
        $all = BusinessSettings::getAll();
        $debug = [
            'availableCategories' => array_keys($all),
            'rawEmailSettings' => isset($all['email']) ? $all['email'] : [],
        ];
    }

    if (function_exists('ob_get_level') && ob_get_level() > 0) {
        @ob_clean();
    }
    echo json_encode([
        'success' => true,
        'config' => $config,
        'debug' => $debug
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Failed to load email configuration: ' . $e->getMessage()
    ]);
}
?> 