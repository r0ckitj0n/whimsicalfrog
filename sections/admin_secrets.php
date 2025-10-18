<?php
// sections/admin_secrets.php — Primary implementation for Secrets Management

require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/secret_store.php';
require_once dirname(__DIR__) . '/includes/csrf.php';

requireAdmin(false);

// Flash helper
if (!isset($_SESSION)) {
    @session_start();
}
function flash_take(string $key): ?string
{
    $val = $_SESSION[$key] ?? null;
    unset($_SESSION[$key]);
    return $val;
}

$errors = [];
$notices = [];

// Ensure secrets table exists
try {
    secret_table_ensure(secret_db());
} catch (Exception $e) {
    $errors[] = 'Failed to ensure secrets table exists: ' . htmlspecialchars($e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf'] ?? '';
    if (!csrf_validate('admin_secrets', $token)) {
        $errors[] = 'Invalid request token. Please try again.';
    } else {
        $action = $_POST['action'] ?? '';
        $key = trim((string)($_POST['key'] ?? ''));
        if ($action === 'create_or_update') {
            $value = (string)($_POST['value'] ?? '');
            if ($key === '' || !preg_match('/^[A-Za-z0-9._:\\-]{1,191}$/', $key)) {
                $errors[] = 'Please provide a valid key (letters, numbers, dot, dash, underscore, colon). Max 191 chars.';
            } else {
                if (secret_set($key, $value)) {
                    $_SESSION['flash_success'] = 'Secret saved.';
                    header('Location: /admin/secrets');
                    exit;
                } else {
                    $errors[] = 'Failed to save secret.';
                }
            }
        } elseif ($action === 'delete') {
            if ($key === '') {
                $errors[] = 'Missing key.';
            } else {
                if (secret_delete($key)) {
                    $_SESSION['flash_success'] = 'Secret deleted.';
                    header('Location: /admin/secrets');
                    exit;
                } else {
                    $errors[] = 'Failed to delete secret.';
                }
            }
        }
    }
}

// Load current secrets (names only)
$secrets = [];
try {
    $pdo = secret_db();
    $stmt = $pdo->query('SELECT `key`, updated_at, created_at FROM secrets ORDER BY `key`');
    $secrets = $stmt ? $stmt->fetchAll() : []; // Database class sets default fetch mode
} catch (Exception $e) {
    $errors[] = 'Could not load secrets list: ' . htmlspecialchars($e->getMessage());
}

$csrf = csrf_token('admin_secrets');
$flash = flash_take('flash_success');
?>

<div class="admin-secrets page-content">
  <h1 class="text-2xl font-bold mb-4">Secrets</h1>

  <div class="mb-4 text-sm text-gray-700">
    <p>
      Manage encrypted application secrets. Values are encrypted at rest. For safety, values are not displayed.
      Use the form below to create or update secrets by key. Deleting a secret cannot be undone.
    </p>
  </div>

  <?php if (!empty($flash)): ?>
    <div class="wf-alert wf-alert-success mb-4"><?= htmlspecialchars($flash) ?></div>
  <?php endif; ?>

  <?php if (!empty($errors)): ?>
    <div class="wf-alert wf-alert-error mb-4">
      <ul class="list-disc pl-5">
        <?php foreach ($errors as $e): ?>
          <li><?= htmlspecialchars($e) ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <section class="wf-card p-4 mb-8">
    <h2 class="text-xl font-semibold mb-3">Add or Update Secret</h2>
    <form method="post" class="space-y-3">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
      <input type="hidden" name="action" value="create_or_update">
      <div>
        <label class="block font-medium mb-1" for="secret-key">Key</label>
        <input id="secret-key" name="key" type="text" class="w-full border rounded px-3 py-2" placeholder="e.g. SMTP_PASSWORD" maxlength="191" required>
      </div>
      <div>
        <label class="block font-medium mb-1" for="secret-value">Value</label>
        <textarea id="secret-value" name="value" class="w-full border rounded px-3 py-2" rows="3" placeholder="Enter secret value" required></textarea>
      </div>
      <div>
        <button type="submit" class="btn btn-primary">Save Secret</button>
      </div>
    </form>
  </section>

  <section class="wf-card p-4">
    <h2 class="text-xl font-semibold mb-3">Existing Secrets</h2>
    <?php if (empty($secrets)): ?>
      <p class="text-gray-600">No secrets stored yet.</p>
    <?php else: ?>
      <div class="overflow-x-auto">
        <table class="min-w-full border border-gray-200">
          <thead>
            <tr class="bg-gray-50 text-left">
              <th class="px-3 py-2 border-b">Key</th>
              <th class="px-3 py-2 border-b">Updated</th>
              <th class="px-3 py-2 border-b">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($secrets as $row): ?>
              <tr>
                <td class="px-3 py-2 border-b font-mono text-sm"><?= htmlspecialchars($row['key']) ?></td>
                <td class="px-3 py-2 border-b text-sm"><?= htmlspecialchars($row['updated_at'] ?? $row['created_at'] ?? '') ?></td>
                <td class="px-3 py-2 border-b">
                  <form method="post" class="inline-block admin-secret-delete-form" data-key="<?= htmlspecialchars($row['key']) ?>">
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="key" value="<?= htmlspecialchars($row['key']) ?>">
                    <button type="submit" class="btn btn-danger">Delete</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>
</div>

