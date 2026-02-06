<?php
/**
 * Marketing Helper Logic
 */

class MarketingHelper
{
    private $pdo;

    public function __construct()
    {
        try {
            $this->pdo = Database::getInstance();
        } catch (Exception $e) {
            error_log("MarketingHelper initialization failed: " . $e->getMessage());
            $this->pdo = null;
        }
    }

    public function getMarketingData($sku)
    {
        if (empty($sku) || !$this->pdo) return null;
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM marketing_suggestions WHERE sku = ? ORDER BY created_at DESC LIMIT 1");
            $stmt->execute([$sku]);
            $data = $stmt->fetch();
            if ($data) {
                $jsonFields = ['keywords', 'emotional_triggers', 'selling_points', 'competitive_advantages', 'unique_selling_points', 'value_propositions', 'marketing_channels', 'urgency_factors', 'social_proof_elements', 'call_to_action_suggestions', 'conversion_triggers', 'objection_handlers', 'seo_keywords', 'content_themes', 'customer_benefits', 'pain_points_addressed', 'lifestyle_alignment'];
                foreach ($jsonFields as $field) {
                    if (isset($data[$field])) $data[$field] = json_decode($data[$field], true) ?? [];
                }
            }
            return $data;
        } catch (\Throwable $____) { return null; }
    }

    public function getEnhancedDescription($sku, $fallback = '')
    {
        $data = $this->getMarketingData($sku);
        return ($data && !empty($data['suggested_description'])) ? $data['suggested_description'] : $fallback;
    }

    public function getUpsellLine($sku, $default = '')
    {
        $data = $this->getMarketingData($sku);
        if ($data) {
            $priority = ['customer_benefits', 'unique_selling_points', 'value_propositions', 'selling_points'];
            foreach ($priority as $f) {
                if (!empty($data[$f]) && is_array($data[$f])) {
                    $line = trim((string)($data[$f][0] ?? ''));
                    if ($line !== '') return $line;
                }
            }
        }
        return $default ?: 'Experience premium quality and style!';
    }

    public function getSEOSettings($pageType = 'global')
    {
        if (!$this->pdo) return [];
        $stmt = $this->pdo->prepare("SELECT setting_name, setting_value FROM seo_settings WHERE page_type = ? OR page_type = 'global' ORDER BY page_type DESC");
        $stmt->execute([$pageType]);
        $settings = $stmt->fetchAll();
        $result = [];
        foreach ($settings as $s) {
            if (!isset($result[$s['setting_name']])) $result[$s['setting_name']] = $s['setting_value'];
        }
        return $result;
    }

    public function generatePageSEO($pageType, $item_sku = null)
    {
        $settings = $this->getSEOSettings($pageType);
        $seo = [
            'title' => $settings['page_title'] ?? $settings['site_title'] ?? 'Whimsical Frog',
            'description' => $settings['meta_description'] ?? $settings['site_description'] ?? '',
            'keywords' => $settings['site_keywords'] ?? ''
        ];
        if ($item_sku) {
            $data = $this->getMarketingData($item_sku);
            if ($data) {
                if (!empty($data['suggested_title'])) $seo['title'] = $data['suggested_title'] . ' | ' . $seo['title'];
                if (!empty($data['suggested_description'])) $seo['description'] = $data['suggested_description'];
            }
        }
        return $seo;
    }
}
