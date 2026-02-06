<?php
/**
 * Business Settings API Helper
 * Extracted from api/business_settings.php to reduce size.
 */

require_once __DIR__ . '/Constants.php';
require_once __DIR__ . '/auth_helper.php';
require_once __DIR__ . '/branding_tokens_helper.php';

function current_admin_identifier(): string
{
    try {
        $user = AuthHelper::getCurrentUser();
        if (is_array($user)) {
            if (!empty($user['email']))
                return (string) $user['email'];
            if (!empty($user['username']))
                return (string) $user['username'];
            if (!empty($user['name']))
                return (string) $user['name'];
        }
    } catch (Throwable $e) {
    }
    return WF_Constants::ROLE_ADMIN;
}

function normalize_branding_payload(array $settings, array $brandingTokenKeys): array
{
    $tokens = [];
    foreach ($settings as $key => $value) {
        if (!in_array($key, $brandingTokenKeys, true))
            continue;
        if ($key === 'business_brand_palette') {
            $tokens[$key] = is_array($value) ? BrandingTokens::encodePaletteArray($value) : (string) $value;
            continue;
        }
        $tokens[$key] = is_array($value) ? json_encode($value) : (string) $value;
    }
    return $tokens;
}

function save_branding_tokens(array $updates): array
{
    if (empty($updates))
        return BrandingTokens::getTokens();
    $current = BrandingTokens::getTokens();
    $merged = array_merge($current, $updates);
    BrandingTokens::saveTokens($merged, current_admin_identifier());
    return $merged;
}

function handle_upsert_settings($input, $post, $brandingTokenKeys)
{
    $category = $input['category'] ?? $post['category'] ?? WF_Constants::SETTINGS_CATEGORY_ECOMMERCE;
    $settings = $input['settings'] ?? $input;
    if (!is_array($settings)) {
        $settingsJson = $post['settings'] ?? '';
        $settings = !empty($settingsJson) ? json_decode($settingsJson, true) : null;
    }

    if (!is_array($settings) || empty($settings)) {
        Response::error('Settings map is required', null, 400);
    }

    $brandingUpdates = normalize_branding_payload($settings, $brandingTokenKeys);
    foreach ($brandingTokenKeys as $brandingKey)
        unset($settings[$brandingKey]);

    $saved = 0;
    if (!empty($settings)) {
        Database::beginTransaction();
        try {
            $sql = "INSERT INTO business_settings (category, setting_key, setting_value, setting_type, display_name, description)
                VALUES (?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), setting_type = VALUES(setting_type), updated_at = CURRENT_TIMESTAMP";

            foreach ($settings as $key => $value) {
                $type = 'text';
                if (is_bool($value)) {
                    $value = $value ? 'true' : 'false';
                    $type = 'boolean';
                } elseif (is_numeric($value)) {
                    $type = 'number';
                    $value = (string) $value;
                } elseif (is_array($value)) {
                    $type = 'json';
                    $value = json_encode($value);
                } else {
                    $value = (string) $value;
                }

                $displayName = ucwords(str_replace('_', ' ', (string) $key));
                if (Database::execute($sql, [$category, (string) $key, $value, $type, $displayName, 'Business setting ' . $key]) > 0)
                    $saved++;
            }
            Database::commit();
        } catch (Exception $e) {
            Database::rollBack();
            throw $e;
        }
    }

    $updatedTokens = !empty($brandingUpdates) ? save_branding_tokens($brandingUpdates) : null;
    if (class_exists('BusinessSettings'))
        BusinessSettings::clearCache();

    Response::success([
        'message' => "Upserted {$saved} settings successfully",
        'updated_count' => $saved,
        'category' => $category,
        'branding_tokens' => $updatedTokens,
    ]);
}

function handle_get_business_info()
{
    if (!class_exists('BusinessSettings')) {
        require_once __DIR__ . '/../includes/business_settings_helper.php';
    }

    $get = fn($k, $d = '') => BusinessSettings::get($k, $d);
    $tokens = BrandingTokens::getTokens();

    $info = [
        'business_name' => BusinessSettings::getBusinessName(),
        'business_email' => BusinessSettings::getBusinessEmail(),
        'business_phone' => $get('business_phone'),
        'business_hours' => $get('business_hours'),
        'business_address' => BusinessSettings::getBusinessAddressLine1(),
        'business_address2' => BusinessSettings::getBusinessAddressLine2(),
        'business_city' => BusinessSettings::getBusinessCity(),
        'business_state' => BusinessSettings::getBusinessState(),
        'business_postal' => BusinessSettings::getBusinessPostal(),
        'business_country' => $get('business_country', 'US'),
        'business_owner' => $get('business_owner'),
        'business_site_url' => $get('business_site_url'),
        'business_logo' => $get('business_logo'),
        'business_tagline' => $get('site_tagline'),
        'business_description' => $get('business_description'),
        'business_support_email' => $get('business_support_email'),
        'business_support_phone' => $get('business_support_phone'),
        'business_tax_id' => $get('business_tax_id'),
        'business_timezone' => $get('business_timezone', 'America/New_York'),
        'business_currency' => $get('business_currency', 'USD'),
        'business_locale' => $get('business_locale', 'en-US'),
        'business_terms_url' => $get('business_terms_url'),
        'business_privacy_url' => $get('business_privacy_url'),
        'business_footer_note' => $get('business_footer_note'),
        'business_footer_html' => $get('business_footer_html'),
        'business_return_policy' => $get('business_return_policy'),
        'business_shipping_policy' => $get('business_shipping_policy'),
        'business_warranty_policy' => $get('business_warranty_policy'),
        'business_policy_url' => $get('business_policy_url'),
        'business_privacy_policy_content' => $get('business_privacy_policy_content'),
        'business_terms_service_content' => $get('business_terms_service_content'),
        'business_store_policies_content' => $get('business_store_policies_content'),
        'about_page_title' => $get('about_page_title'),
        'about_page_content' => $get('about_page_content'),
    ];

    Response::success(array_merge($info, $tokens, [
        '_branding_meta' => [
            'updated_at' => BrandingTokens::getLastUpdatedAt(),
            'updated_by' => BrandingTokens::getLastUpdatedBy(),
        ]
    ]));
}
