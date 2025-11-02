<?php
if (!defined('INCLUDED_FROM_INDEX')) {
    define('INCLUDED_FROM_INDEX', true);
}
require_once __DIR__ . '/api/business_settings_helper.php';

$__wf_skip_footer = true;

$businessName = BusinessSettings::getBusinessName();
$title = 'Store Policies';
$storePolicies = BusinessSettings::get('store_policies_content', '');
$policyReturn = BusinessSettings::get('business_policy_return', '');
$policyShipping = BusinessSettings::get('business_policy_shipping', '');
$policyWarranty = BusinessSettings::get('business_policy_warranty', '');

$renderSection = function ($heading, $body) {
    if (!is_string($body) || trim(strip_tags($body)) === '') return '';
    return '<h3>' . htmlspecialchars($heading) . '</h3><div>' . $body . '</div>';
};

$default = '';
if ($policyReturn === '' && $policyShipping === '' && $policyWarranty === '') {
    $default = '<p>We want you to love your purchase from ' . htmlspecialchars($businessName) . '.</p>'
             . '<h3>Returns & Exchanges</h3><p>Custom items are typically final sale, but please contact us within 14 days if there is an issue and we will make it right.</p>'
             . '<h3>Shipping</h3><p>Orders usually ship within 3–5 business days. You will receive tracking information when your order ships.</p>'
             . '<h3>Warranty</h3><p>We stand by our craftsmanship. If your item arrives damaged or defective, contact us within 7 days for support.</p>';
}
?>
<style>
  body[data-page='policy']{overflow:hidden}
  body[data-page='policy'] .page-content{height:calc(100vh - var(--wf-header-height));height:calc(100dvh - var(--wf-header-height));max-height:calc(100vh - var(--wf-header-height));max-height:calc(100dvh - var(--wf-header-height));padding-bottom:0!important;margin-bottom:0!important;overflow:visible!important}
  body[data-page='policy'] .prose{height:100%;max-height:100%;margin-bottom:0!important}
  body[data-page='policy'] .wf-cloud-card{height:100%;max-height:100%;min-height:0;margin-bottom:0}
  body[data-page='policy'] .wf-cloud-card .content{padding-top:56px;padding-bottom:20px}
  @media (max-width:520px){ body[data-page='policy'] .wf-cloud-card .content{padding-top:48px} }
  @media (min-width:1400px){ body[data-page='policy'] .wf-cloud-card .content{padding-top:60px} }
</style>
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
              if ($default) echo $default;
          }
        ?>
      </div>
    </div>
  </div>
</div>
