<?php
require_once dirname(__DIR__) . '/api/config.php';
require_once dirname(__DIR__) . '/includes/vite_helper.php';

// Detect modal context
$isModal = (isset($_GET['modal']) && $_GET['modal'] == '1');

// In modal context, output minimal header and mark body as embedded
if ($isModal) {
    $page = 'admin/ai-tools';
    // Ensure the marketing/tools entry loads inside the iframe so button wiring works in dev and prod
    $extraViteEntry = 'src/entries/admin-marketing.js';
    require_once dirname(__DIR__) . '/partials/modal_header.php';
}

// When not in modal, include full admin layout and navbar
if (!$isModal) {
    if (!defined('WF_LAYOUT_BOOTSTRAPPED')) {
        $page = 'admin';
        include dirname(__DIR__) . '/partials/header.php';
        if (!function_exists('__wf_admin_ai_tools_footer_shutdown')) {
            function __wf_admin_ai_tools_footer_shutdown() { @include __DIR__ . '/../partials/footer.php'; }
        }
        register_shutdown_function('__wf_admin_ai_tools_footer_shutdown');
    }
    // Show under Marketing section tabs for now
    $section = 'marketing';
    include_once dirname(__DIR__) . '/components/admin_nav_tabs.php';
}
?>

<div class="admin-marketing-page">
  <div class="admin-card">
    <h3 class="admin-card-title<?php echo $isModal ? ' hidden' : '' ?>">🤖 AI Tools</h3>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
      <div class="border rounded p-3">
        <button data-action="open-suggestions-manager" class="btn btn-primary w-full">🤖 Suggestions Manager</button>
        <div class="text-sm text-gray-600 mt-2">Generate AI content, price, and cost for an item, review/edit, then apply.</div>
      </div>
      <div class="border rounded p-3">
        <button data-action="open-social-manager" class="btn btn-secondary w-full">📱 Social Accounts Manager</button>
        <div class="text-sm text-gray-600 mt-2">Connect accounts and manage posts.</div>
      </div>
      <div class="border rounded p-3">
        <button data-action="open-intent-heuristics-manager" class="btn btn-secondary w-full">🧠 Intent Heuristics Config</button>
        <div class="text-sm text-gray-600 mt-2">Tune upsell scoring (weights, budgets, keywords, seasonality).</div>
      </div>
      <div class="border rounded p-3">
        <button data-action="open-automation-manager" class="btn btn-secondary w-full">⚙️ Automation Manager</button>
        <div class="text-sm text-gray-600 mt-2">Set up flows and triggers.</div>
      </div>
      <div class="border rounded p-3 md:col-span-2">
        <button data-action="open-ai-provider-parent" class="btn btn-secondary w-full">🤖 AI Settings</button>
        <div class="text-sm text-gray-600 mt-2">Configure provider, models, credentials, and behavior.</div>
      </div>
    </div>
  </div>
</div>

<?php if (!$isModal): ?>
  <?php echo vite_entry('src/entries/admin-marketing.js'); ?>
<?php endif; ?>
