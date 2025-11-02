<?php
if (!defined('INCLUDED_FROM_INDEX')) {
    define('INCLUDED_FROM_INDEX', true);
}
require_once __DIR__ . '/api/business_settings_helper.php';

$__wf_skip_footer = true;

$businessName = BusinessSettings::getBusinessName();
$title = 'Privacy Policy';
// Prefer new unified key; fallback to legacy key
$content = BusinessSettings::get('privacy_policy_content', '');
if (!is_string($content) || trim(strip_tags($content)) === '') {
    $content = BusinessSettings::get('business_privacy_html', '');
}
if (!is_string($content) || trim(strip_tags($content)) === '') {
    $content = '<p>Your privacy is important to us. This Privacy Policy explains how ' . htmlspecialchars($businessName) . ' collects, uses, and safeguards your information when you visit our website and make purchases.</p><h3>Information We Collect</h3><p>We collect information you provide directly, such as contact details and order information, and limited technical data to improve site performance.</p><h3>How We Use Information</h3><p>To process orders, provide support, improve our services, and comply with legal obligations.</p><h3>Contact</h3><p>If you have questions about this policy, please contact us at ' . htmlspecialchars(BusinessSettings::getBusinessEmail()) . '.</p>';
}
?>
<style>
  body[data-page='privacy']{overflow:hidden}
  body[data-page='privacy'] .page-content{height:calc(100vh - var(--wf-header-height));height:calc(100dvh - var(--wf-header-height));max-height:calc(100vh - var(--wf-header-height));max-height:calc(100dvh - var(--wf-header-height));padding-bottom:0!important;margin-bottom:0!important;overflow:visible!important}
  body[data-page='privacy'] .prose{height:100%;max-height:100%;margin-bottom:0!important}
  body[data-page='privacy'] .wf-cloud-card{height:100%;max-height:100%;min-height:0;margin-bottom:0}
  body[data-page='privacy'] .wf-cloud-card .content{padding-top:56px;padding-bottom:20px}
  @media (max-width:520px){ body[data-page='privacy'] .wf-cloud-card .content{padding-top:48px} }
  @media (min-width:1400px){ body[data-page='privacy'] .wf-cloud-card .content{padding-top:60px} }
</style>
<div class="page-content container mx-auto px-4 pt-8 pb-0">
  <div class="prose max-w-none">
    <div class="wf-cloud-card">
      <div class="content leading-relaxed text-gray-800">
        <h1 class="wf-cloud-title"><?php echo htmlspecialchars($title); ?></h1>
        <?php echo is_string($content) ? $content : ''; ?>
      </div>
    </div>
  </div>
</div>
