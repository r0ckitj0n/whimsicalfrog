<?php
if (!defined('INCLUDED_FROM_INDEX')) {
    define('INCLUDED_FROM_INDEX', true);
}
require_once __DIR__ . '/api/business_settings_helper.php';

$__wf_skip_footer = true;

$businessName = BusinessSettings::getBusinessName();
$title = 'Terms of Service';
$content = BusinessSettings::get('terms_of_service_content', '');
if (!is_string($content) || trim(strip_tags($content)) === '') {
    $content = BusinessSettings::get('business_terms_html', '');
}
if (!is_string($content) || trim(strip_tags($content)) === '') {
    $content = '<p>Welcome to ' . htmlspecialchars($businessName) . '. By accessing or using our website, you agree to these Terms of Service.</p><h3>Purchases</h3><p>All orders are subject to availability and confirmation of the order price. Custom items may have additional lead time.</p><h3>Returns</h3><p>See our store policy for details regarding returns, exchanges, and warranties.</p><h3>Contact</h3><p>For questions regarding these terms, contact us at ' . htmlspecialchars(BusinessSettings::getBusinessEmail()) . '.</p>';
}
?>
<style>
  body[data-page='terms']{overflow:hidden}
  body[data-page='terms'] .page-content{height:calc(100vh - var(--wf-header-height));max-height:calc(100vh - var(--wf-header-height));padding-bottom:0!important;margin-bottom:0!important;overflow:visible!important}
  body[data-page='terms'] .prose{height:100%;max-height:100%;margin-bottom:0!important}
  body[data-page='terms'] .wf-cloud-card{height:100%;max-height:100%;min-height:0;margin-bottom:0}
  body[data-page='terms'] .wf-cloud-card .content{padding-top:56px;padding-bottom:20px}
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
