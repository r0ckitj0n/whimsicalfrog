<?php
/**
 * Cart Upsell Simulator Logic
 */

function simulate_shopper_upsells($seedProfile, $limit)
{
    $data = wf_generate_cart_upsell_rules();
    $items_list = $data['items'] ?? [];
    $categoryList = array_unique(array_column($items_list, 'category'));

    $rand = fn($arr, $fb = null) => $arr ? $arr[random_int(0, count($arr) - 1)] : $fb;

    $profile = [
        'preferredCategory' => $seedProfile['preferredCategory'] ?? $rand($categoryList, ''),
        'budget' => $seedProfile['budget'] ?? $rand(['low','mid','high'], 'mid'),
        'intent' => $seedProfile['intent'] ?? $rand(['gift','personal','replacement','upgrade'], 'personal'),
        'device' => $seedProfile['device'] ?? $rand(['mobile','desktop'], 'mobile'),
        'region' => $seedProfile['region'] ?? 'US',
    ];

    $cartSkus = [];
    $pref = (string)$profile['preferredCategory'];
    if ($pref !== '' && isset($data['metadata']['category_leaders'][$pref])) {
        $cartSkus[] = $data['metadata']['category_leaders'][$pref];
    }

    $resolved = wf_resolve_cart_upsells($cartSkus, $limit);
    $recommendations = $resolved['upsells'] ?? [];

    return [
        'profile' => $profile,
        'cart_skus' => $cartSkus,
        'recommendations' => $recommendations,
        'criteria' => ['source' => 'simulated', 'limit' => $limit]
    ];
}
