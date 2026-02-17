<?php
/**
 * includes/helpers/WebsiteConfigHelper.php
 * Helper class for website configuration logic
 */

class WebsiteConfigHelper {
    public static function getGroupedConfig($category = '') {
        if ($category) {
            $configs = Database::queryAll("SELECT * FROM website_configs WHERE category = ? AND is_active = 1 ORDER BY setting_key", [$category]);
        } else {
            $configs = Database::queryAll("SELECT * FROM website_configs WHERE is_active = 1 ORDER BY category, setting_key");
        }

        $grouped = [];
        foreach ($configs as $config) {
            $grouped[$config['category']][] = $config;
        }
        return $grouped;
    }

    public static function updateConfig($data) {
        $category = $data['category'] ?? '';
        $setting_key = $data['setting_key'] ?? '';
        $setting_value = $data['setting_value'] ?? '';
        $setting_type = $data['setting_type'] ?? 'string';

        if (empty($category) || empty($setting_key)) {
            throw new Exception('Category and setting key are required');
        }

        if ($setting_type === 'boolean') {
            $setting_value = $setting_value ? 'true' : 'false';
        }

        if ($setting_type === 'json' && !empty($setting_value)) {
            json_decode($setting_value);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Invalid JSON format');
            }
        }

        $exists = Database::queryOne("SELECT id FROM website_configs WHERE category = ? AND setting_key = ?", [$category, $setting_key]);

        if ($exists) {
            return Database::execute("UPDATE website_configs SET setting_value = ?, setting_type = ?, updated_at = CURRENT_TIMESTAMP WHERE category = ? AND setting_key = ?", [$setting_value, $setting_type, $category, $setting_key]);
        } else {
            return Database::execute("INSERT INTO website_configs (category, setting_key, setting_value, setting_type) VALUES (?, ?, ?, ?)", [$category, $setting_key, $setting_value, $setting_type]);
        }
    }

    public static function getCSSOutput() {
        $variables = Database::queryAll("SELECT variable_name, variable_value FROM css_variables WHERE is_active = 1 ORDER BY category, variable_name");
        $css = ":root {\n";
        foreach ($variables as $var) {
            $css .= "    {$var['variable_name']}: {$var['variable_value']};\n";
        }
        $css .= "}\n\n";

        $components = Database::queryAll("SELECT component_name, custom_css FROM ui_components WHERE is_active = 1 AND custom_css != '' ORDER BY component_name");
        foreach ($components as $component) {
            if (!empty($component['custom_css'])) {
                $css .= "/* {$component['component_name']} */\n" . $component['custom_css'] . "\n\n";
            }
        }
        return $css;
    }
}
