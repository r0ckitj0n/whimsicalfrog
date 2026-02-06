<?php
/**
 * TierScalingHelper.php
 * Handles consistent scaling of AI suggestions based on quality tiers.
 */

class TierScalingHelper
{
    public static function getMultiplier($qualityTier)
    {
        $tier = strtolower($qualityTier);
        switch ($tier) {
            case 'premium':
                return 1.15;
            case 'conservative':
            case 'budget':
            case 'economy':
                return 0.85;
            default:
                return 1.0;
        }
    }

    public static function scaleBreakdown(&$breakdown, $multiplier)
    {
        if ($multiplier === 1.0 || empty($breakdown) || !is_array($breakdown)) {
            return;
        }

        $scaleRecursively = function (&$item) use (&$scaleRecursively, $multiplier) {
            if (is_array($item)) {
                foreach ($item as $key => &$value) {
                    if (is_numeric($value)) {
                        $value *= $multiplier;
                    } else {
                        $scaleRecursively($value);
                    }
                }
            }
        };

        $scaleRecursively($breakdown);
    }

    public static function scalePricingComponents(&$components, $multiplier)
    {
        if ($multiplier === 1.0 || empty($components) || !is_array($components)) {
            return;
        }

        foreach ($components as &$comp) {
            if (isset($comp['amount']) && is_numeric($comp['amount'])) {
                $comp['amount'] *= $multiplier;
            }
        }
    }
}
