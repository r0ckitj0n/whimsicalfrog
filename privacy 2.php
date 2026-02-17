<?php
if (!defined('INCLUDED_FROM_INDEX')) {
    define('INCLUDED_FROM_INDEX', true);
}
require_once __DIR__ . '/includes/business_settings_helper.php';

$__wf_skip_footer = true;

$business_name = BusinessSettings::getBusinessName();
$title = 'Privacy Policy';

// Prefer current business settings key, then legacy keys.
$content = BusinessSettings::get('business_privacy_policy_content', '');
if (!is_string($content) || trim(strip_tags($content)) === '') {
    $content = BusinessSettings::get('privacy_policy_content', '');
}
if (!is_string($content) || trim(strip_tags($content)) === '') {
    $content = BusinessSettings::get('business_privacy_html', '');
}
if (!is_string($content) || trim(strip_tags($content)) === '') {
    $content = '<p>Your privacy is important to us. This Privacy Policy explains how ' . htmlspecialchars($business_name) . ' collects, uses, and safeguards your information when you visit our website and make purchases.</p><h3>Information We Collect</h3><p>We collect information you provide directly, such as contact details and order information, and limited technical data to improve site performance.</p><h3>How We Use Information</h3><p>To process orders, provide support, improve our services, and comply with legal obligations.</p><h3>Contact</h3><p>If you have questions about this policy, please contact us at ' . htmlspecialchars(BusinessSettings::getBusinessEmail()) . '.</p>';
}
?>
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
