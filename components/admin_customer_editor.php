<?php
// components/admin_customer_editor.php

if (!function_exists('renderAdminCustomerEditor')) {
    function renderAdminCustomerEditor(string $mode, ?array $customer = null)
    {
        $isEdit = ($mode === 'edit');
        $pageUrl = '/admin/customers';
        $id = htmlspecialchars((string)($customer['id'] ?? ''), ENT_QUOTES, 'UTF-8');
        $firstName = htmlspecialchars((string)($customer['firstName'] ?? ''), ENT_QUOTES, 'UTF-8');
        $lastName = htmlspecialchars((string)($customer['lastName'] ?? ''), ENT_QUOTES, 'UTF-8');
        $email = htmlspecialchars((string)($customer['email'] ?? ''), ENT_QUOTES, 'UTF-8');
        $username = htmlspecialchars((string)($customer['username'] ?? ''), ENT_QUOTES, 'UTF-8');
        $role = htmlspecialchars((string)($customer['role'] ?? 'customer'), ENT_QUOTES, 'UTF-8');
        ?>
<div id="customerModalOuter" class="admin-modal-overlay fixed inset-0 bg-black/50 flex items-start justify-center overflow-y-auto" data-action="close-customer-editor-on-overlay" role="dialog" aria-modal="true" aria-hidden="false">
  <div class="admin-modal relative mt-8 bg-white rounded-lg shadow-xl w-full max-w-4xl">
    <div class="modal-header flex justify-between items-center p-2 border-b border-gray-100">
      <h2 class="text-lg font-bold text-green-700"><?= $isEdit ? 'Edit Customer' : 'Add New Customer' ?><?= ($isEdit && $id) ? ' (#' . $id . ')' : '' ?></h2>
      <a href="<?= $pageUrl ?>" class="modal-close-btn" aria-label="Close" data-action="close-customer-editor">×</a>
    </div>
    <div class="modal-body p-4">
      <form id="customerForm" method="POST" action="#" class="flex flex-col gap-6">
        <input type="hidden" name="action" value="<?= $isEdit ? 'update' : 'add' ?>">
        <?php if ($isEdit && $id): ?><input type="hidden" name="customerId" value="<?= $id ?>"><?php endif; ?>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
          <div>
            <label for="firstName" class="block text-gray-700">First Name</label>
            <input type="text" id="firstName" name="firstName" class="mt-1 block w-full p-2 border border-gray-300 rounded" value="<?= $firstName ?>" required>
          </div>
          <div>
            <label for="lastName" class="block text-gray-700">Last Name</label>
            <input type="text" id="lastName" name="lastName" class="mt-1 block w-full p-2 border border-gray-300 rounded" value="<?= $lastName ?>" required>
          </div>
          <div>
            <label for="username" class="block text-gray-700">Username</label>
            <input type="text" id="username" name="username" class="mt-1 block w-full p-2 border border-gray-300 rounded" value="<?= $username ?>" required>
          </div>
          <div>
            <label for="email" class="block text-gray-700">Email</label>
            <input type="email" id="email" name="email" class="mt-1 block w-full p-2 border border-gray-300 rounded" value="<?= $email ?>" required>
          </div>
          <div>
            <label for="role" class="block text-gray-700">Role</label>
            <select id="role" name="role" class="mt-1 block w-full p-2 border border-gray-300 rounded">
              <?php $roles = ['customer','admin'];
        foreach ($roles as $r) {
            $rE = htmlspecialchars($r, ENT_QUOTES, 'UTF-8');
            $sel = ($role === $rE) ? 'selected' : '';
            echo "<option value=\"$rE\" $sel>".ucfirst($rE)."</option>";
        } ?>
            </select>
          </div>
        </div>
        <div class="mt-6 flex justify-end gap-2">
          <button type="button" class="btn" data-action="close-customer-editor">Cancel</button>
          <button type="submit" class="btn btn-primary" data-action="save-customer">Save Changes</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php }
    }
?>
