<?php
// Cron Manager (migrated to sections/tools)
require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once dirname(__DIR__, 2) . '/api/config.php';
require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/includes/secret_store.php';

$__wf_included_layout = false;
if (!function_exists('__wf_admin_root_footer_shutdown')) {
    $page = 'admin';
    include dirname(__DIR__, 2) . '/partials/header.php';
    $__wf_included_layout = true;
}

function cm_generate_token() { return bin2hex(random_bytes(24)); }
$tokenKey = 'maintenance_admin_token';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'rotate_token') {
    $new = cm_generate_token();
    if (!secret_set($tokenKey, $new)) {
        $rotateError = 'Failed to rotate token. Please try again.';
    } else {
        $rotateSuccess = 'Token rotated successfully.';
    }
}
$currentToken = secret_get($tokenKey);
if (!$currentToken) { $currentToken = cm_generate_token(); secret_set($tokenKey, $currentToken); }

$baseUrl = (defined('WF_PUBLIC_BASE') && WF_PUBLIC_BASE) ? ('https://whimsicalfrog.us' . WF_PUBLIC_BASE) : 'https://whimsicalfrog.us';
$webCronUrl = $baseUrl . '/api/maintenance.php?action=prune_sessions&days=2&admin_token=' . urlencode($currentToken);
?>
<div class="page-content admin-container">
  <div class="settings-section technical-section card-theme-red section--narrow-max">
    <div class="section-header">
      <h2 class="section-title">Cron & Scheduled Tasks</h2>
      <p class="section-description">Configure and verify scheduled maintenance tasks (e.g., pruning old PHP sessions).</p>
    </div>

    <div class="section-content">
      <?php if (!empty($rotateSuccess)): ?>
        <div class="notice notice-success notice--spaced">✅ <?php echo htmlspecialchars($rotateSuccess, ENT_QUOTES, 'UTF-8'); ?></div>
      <?php endif; ?>
      <?php if (!empty($rotateError)): ?>
        <div class="notice notice-error notice--spaced">⚠️ <?php echo htmlspecialchars($rotateError, ENT_QUOTES, 'UTF-8'); ?></div>
      <?php endif; ?>

      <div class="admin-card">
        <h3 class="admin-card-title">Token Management</h3>
        <p>For security, the maintenance token is stored in the Secret Store and used in the Web Cron URL below.</p>
        <p><strong>Current token (masked):</strong> <code><?php echo htmlspecialchars(substr($currentToken, 0, 4) . '•••' . substr($currentToken, -4), ENT_QUOTES, 'UTF-8'); ?></code></p>
        <form method="post" class="mt-2">
          <input type="hidden" name="action" value="rotate_token" />
          <button type="submit" class="btn btn-secondary">Rotate Token</button>
          <small class="text-gray-600 helper--ml-8">After rotation, update your hosting scheduler with the new URL below.</small>
        </form>
      </div>

      <div class="admin-card">
        <h3 class="admin-card-title">Web Cron (URL Trigger)</h3>
        <p>Most shared hosts provide a "web cron" or "URL cron" scheduler. Use the following URL to schedule daily session pruning:</p>
        <pre class="pre--nowrap-scroll"><code><?php echo htmlspecialchars($webCronUrl, ENT_QUOTES, 'UTF-8'); ?></code></pre>
        <ul class="admin-list">
          <li>Schedule: Daily at 3:10 AM (or your preferred time)</li>
          <li>Action: HTTP GET to the URL above</li>
          <li>Expected Response: JSON with { success: true, action: 'prune_sessions', deleted: N }</li>
        </ul>
        <div class="flex gap-2 mt-3">
          <a href="<?php echo htmlspecialchars($webCronUrl, ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-primary" target="_blank" rel="noopener">Run Now (opens in new tab)</a>
          <button class="btn btn-secondary" id="copyWebCronUrlBtn" type="button">Copy URL</button>
        </div>
      </div>

      <div class="admin-card mt-4">
        <h3 class="admin-card-title">System Cron (SSH)</h3>
        <p>If you have SSH access, install a system-level cron that calls the maintenance script directly:</p>
        <pre><code>SESSION_DIR=/var/www/html/sessions /scripts/maintenance/prune_sessions.sh 2</code></pre>
        <p>Add to your crontab (runs daily at 3:10 AM):</p>
        <pre><code>10 3 * * * SESSION_DIR=/var/www/html/sessions /scripts/maintenance/prune_sessions.sh 2 >> /var/log/prune_sessions.log 2>&1</code></pre>
      </div>

      <div class="admin-card mt-4">
        <h3 class="admin-card-title">Status & Diagnostics</h3>
        <p>Click "Run Now" above to confirm successful execution. You should see a JSON response indicating how many session files were deleted.</p>
      </div>
    </div>
  </div>
</div>
<script>
  (function() {
    const btn = document.getElementById('copyWebCronUrlBtn');
    if (btn) {
      btn.addEventListener('click', async () => {
        const el = '<?php echo addslashes($webCronUrl); ?>';
        try {
          await navigator.clipboard.writeText(el);
          alert('Web cron URL copied to clipboard');
        } catch (e) {
          alert('Copy failed. Please select and copy the URL manually.');
        }
      });
    }
  })();
</script>
<?php if ($__wf_included_layout) { include dirname(__DIR__, 2) . '/partials/footer.php'; } ?>
