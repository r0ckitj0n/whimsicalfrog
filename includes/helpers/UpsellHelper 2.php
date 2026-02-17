<?php
/**
 * includes/helpers/UpsellHelper.php
 * Helper class for calculating cart upsell rules
 */

class UpsellHelper
{
    /**
     * Generate upsell rules and format them for display
     */
    public static function getUpsellDisplayData()
    {
        $upsellAutoData = [];
        $siteLeaders = [];
        $samplePairs = [];

        if (function_exists('wf_generate_cart_upsell_rules')) {
            try {
                $generated = wf_generate_cart_upsell_rules();
                if (is_array($generated)) {
                    $upsellAutoData = $generated;
                }
                // @reason: Upsell generation is non-critical - graceful degradation to empty recommendations
            } catch (Throwable $e) {
            }
        }

        if (!empty($upsellAutoData) && isset($upsellAutoData['map']) && is_array($upsellAutoData['map'])) {
            $map = $upsellAutoData['map'];
            $items_list = $upsellAutoData['items'] ?? [];

            $defaultLeaders = isset($map['_default']) && is_array($map['_default']) ? array_slice($map['_default'], 0, 3) : [];
            foreach ($defaultLeaders as $leaderSku) {
                $leaderSku = strtoupper(trim((string) $leaderSku));
                if ($leaderSku === '')
                    continue;
                $label = $items_list[$leaderSku]['name'] ?? $leaderSku;
                if ($label !== $leaderSku)
                    $label .= " ($leaderSku)";
                if (!in_array($label, $siteLeaders, true))
                    $siteLeaders[] = $label;
            }

            $pairCount = 0;
            foreach ($map as $sourceSku => $targets) {
                if ($sourceSku === '_default' || !is_array($targets) || !$targets)
                    continue;
                $sourceSku = strtoupper(trim((string) $sourceSku));
                if ($sourceSku === '')
                    continue;

                $sourceLabel = $items_list[$sourceSku]['name'] ?? $sourceSku;
                if ($sourceLabel !== $sourceSku)
                    $sourceLabel .= " ($sourceSku)";

                $recommendations = [];
                foreach (array_slice($targets, 0, 3) as $targetSku) {
                    $targetSku = strtoupper(trim((string) $targetSku));
                    if ($targetSku === '')
                        continue;
                    $targetLabel = $items_list[$targetSku]['name'] ?? $targetSku;
                    if ($targetLabel !== $targetSku)
                        $targetLabel .= " ($targetSku)";
                    $recommendations[] = $targetLabel;
                }

                if ($recommendations) {
                    $samplePairs[] = ['source' => $sourceLabel, 'recommendations' => $recommendations];
                    $pairCount++;
                }
                if ($pairCount >= 3)
                    break;
            }
        }

        return [
            'siteLeaders' => $siteLeaders,
            'samplePairs' => $samplePairs
        ];
    }
}
