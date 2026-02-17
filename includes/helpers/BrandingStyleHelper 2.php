<?php
/**
 * includes/helpers/BrandingStyleHelper.php
 * Helper for generating CSS variable style blocks from tokens
 */

class BrandingStyleHelper
{
    public static function buildStyleBlock(array $tokens, string $styleId = 'wf-branding-vars'): string
    {
        $declarations = [];
        $map = [
            'business_brand_primary' => ['--token-brand-primary'],
            'business_brand_secondary' => ['--token-brand-secondary'],
            'business_brand_accent' => ['--token-brand-accent'],
            'business_brand_background' => ['--token-surface-background'],
            'business_brand_text' => ['--token-surface-text'],
            'business_base_font_size' => ['--token-base-font-size'],
            'business_base_line_height' => ['--token-base-line-height'],
            'business_base_font_weight' => ['--token-base-font-weight'],
            'business_heading_font_weight' => ['--token-heading-font-weight'],
            'business_toast_text' => ['--token-toast-text'],
            'business_public_header_bg' => ['--token-public-header-bg'],
            'business_public_header_text' => ['--token-public-header-text'],
            'business_public_modal_bg' => ['--token-public-modal-bg'],
            'business_public_modal_text' => ['--token-public-modal-text'],
            'business_public_page_bg' => ['--token-public-page-bg'],
            'business_public_page_text' => ['--token-public-page-text'],
            'business_button_primary_bg' => ['--token-button-primary-bg', '--token-button-primary-border'],
            'business_button_primary_hover' => ['--token-button-primary-hover-bg', '--token-button-primary-hover-border'],
            'business_button_secondary_bg' => ['--token-button-secondary-bg', '--token-button-secondary-border'],
            'business_button_secondary_hover' => ['--token-button-secondary-hover-bg', '--token-button-secondary-hover-border'],
            'business_button_primary_text' => ['--token-button-primary-text'],
            'business_button_secondary_text' => ['--token-button-secondary-text'],
            'business_button_height' => ['--token-button-height'],
            'business_icon_button_bg' => ['--token-icon-button-bg'],
            'business_icon_button_hover' => ['--token-icon-button-hover'],
            'business_admin_modal_radius' => ['--token-admin-modal-radius'],
            'business_admin_modal_body_padding' => ['--token-admin-modal-body-padding'],
            'business_admin_modal_shadow' => ['--token-admin-modal-shadow'],
            // Transition tokens
            'business_transition_fast' => ['--token-transition-fast'],
            'business_transition_normal' => ['--token-transition-normal'],
            'business_transition_smooth' => ['--token-transition-smooth'],
            // Shadow tokens
            'business_shadow_sm' => ['--token-shadow-sm'],
            'business_shadow_md' => ['--token-shadow-md'],
            'business_shadow_lg' => ['--token-shadow-lg'],
            // Scrollbar tokens
            'business_scrollbar_thumb' => ['--token-scrollbar-thumb'],
            'business_scrollbar_track' => ['--token-scrollbar-track'],
            'business_scrollbar_width' => ['--token-scrollbar-width'],
            // Hover lift tokens
            'business_hover_lift' => ['--token-hover-lift'],
            'business_hover_lift_lg' => ['--token-hover-lift-lg'],
        ];

        foreach ($map as $tokenKey => $cssVars) {
            $value = isset($tokens[$tokenKey]) ? trim((string) $tokens[$tokenKey]) : '';
            if ($value === '')
                continue;
            $safeValue = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
            foreach ($cssVars as $cssVar) {
                $declarations[] = "$cssVar: $safeValue;";
            }
        }

        if (!empty($tokens['business_brand_font_primary'])) {
            $declarations[] = '--token-font-primary: ' . htmlspecialchars(trim((string) $tokens['business_brand_font_primary']), ENT_QUOTES, 'UTF-8') . ';';
        }
        if (!empty($tokens['business_brand_font_secondary'])) {
            $declarations[] = '--token-font-secondary: ' . htmlspecialchars(trim((string) $tokens['business_brand_font_secondary']), ENT_QUOTES, 'UTF-8') . ';';
        }
        if (!empty($tokens['business_brand_font_title_primary'])) {
            $declarations[] = '--token-font-title-primary: ' . htmlspecialchars(trim((string) $tokens['business_brand_font_title_primary']), ENT_QUOTES, 'UTF-8') . ';';
        }
        if (!empty($tokens['business_brand_font_title_secondary'])) {
            $declarations[] = '--token-font-title-secondary: ' . htmlspecialchars(trim((string) $tokens['business_brand_font_title_secondary']), ENT_QUOTES, 'UTF-8') . ';';
        }

        if (empty($declarations))
            return '';

        $body = ":root\n{\n" . implode("\n", $declarations) . "\n}\n";
        return '<style id="' . htmlspecialchars($styleId, ENT_QUOTES, 'UTF-8') . '">' . $body . "</style>\n";
    }
}
