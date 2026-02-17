<?php
if (!defined('INCLUDED_FROM_INDEX')) {
    define('INCLUDED_FROM_INDEX', true);
}
require_once __DIR__ . '/includes/business_settings_helper.php';

$__wf_skip_footer = true;

$business_name = BusinessSettings::getBusinessName();
$title = 'Store Policies';

// Prefer current business settings keys, then legacy aliases.
$storePolicies = BusinessSettings::get('business_store_policies_content', '');
if (!is_string($storePolicies) || trim(strip_tags($storePolicies)) === '') {
    $storePolicies = BusinessSettings::get('store_policies_content', '');
}

$policyReturn = BusinessSettings::get('business_return_policy', '');
if (!is_string($policyReturn) || trim(strip_tags($policyReturn)) === '') {
    $policyReturn = BusinessSettings::get('business_policy_return', '');
}

$policyShipping = BusinessSettings::get('business_shipping_policy', '');
if (!is_string($policyShipping) || trim(strip_tags($policyShipping)) === '') {
    $policyShipping = BusinessSettings::get('business_policy_shipping', '');
}

$policyWarranty = BusinessSettings::get('business_warranty_policy', '');
if (!is_string($policyWarranty) || trim(strip_tags($policyWarranty)) === '') {
    $policyWarranty = BusinessSettings::get('business_policy_warranty', '');
}

$renderSection = function ($heading, $body) {
    if (!is_string($body) || trim(strip_tags($body)) === '') {
        return '';
    }
    return '<h3>' . htmlspecialchars($heading) . '</h3><div>' . $body . '</div>';
};

$default = '';
if (
    trim(strip_tags((string) $policyReturn)) === '' &&
    trim(strip_tags((string) $policyShipping)) === '' &&
    trim(strip_tags((string) $policyWarranty)) === ''
) {
    $default = '<p>We want you to love your purchase from ' . htmlspecialchars($business_name) . '.</p>'
             . '<h3>Returns & Exchanges</h3><p>Custom items are typically final sale, but please contact us within 14 days if there is an issue and we will make it right.</p>'
             . '<h3>Shipping</h3><p>Orders usually ship within 3-5 business days. You will receive tracking information when your order ships.</p>'
             . '<h3>Warranty</h3><p>We stand by our craftsmanship. If your item arrives damaged or defective, contact us within 7 days for support.</p>';
}
?>
<div class="page-content container mx-auto px-4 pt-8 pb-0">
  <div class="prose max-w-none">
    <div class="wf-cloud-card">
      <div class="content leading-relaxed text-gray-800">
        <h1 class="wf-cloud-title"><?php echo htmlspecialchars($title); ?></h1>
        <?php
        if (is_string($storePolicies) && trim(strip_tags($storePolicies)) !== '') {
            echo $storePolicies;
        } else {
            echo $renderSection('Returns & Exchanges', $policyReturn);
            echo $renderSection('Shipping', $policyShipping);
            echo $renderSection('Warranty', $policyWarranty);
            if ($default !== '') {
                echo $default;
            }
        }
        ?>
      </div>
    </div>
  </div>
</div>
