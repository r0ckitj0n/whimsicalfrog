<?php
// components/admin_order_editor.php

if (!function_exists('renderAdminOrderEditor')) {
    function renderAdminOrderEditor(string $mode, ?array $order = null)
    {
        $isEdit = ($mode === 'edit');
        $pageUrl = '/admin/orders';
        $id = htmlspecialchars((string)($order['id'] ?? ''), ENT_QUOTES, 'UTF-8');
        $status = htmlspecialchars((string)($order['status'] ?? 'pending'), ENT_QUOTES, 'UTF-8');
        $customerName = htmlspecialchars((string)($order['customer_name'] ?? ''), ENT_QUOTES, 'UTF-8');
        $customerEmail = htmlspecialchars((string)($order['customer_email'] ?? ''), ENT_QUOTES, 'UTF-8');
        $totalAmount = htmlspecialchars(number_format((float)($order['total_amount'] ?? 0), 2, '.', ''), ENT_QUOTES, 'UTF-8');
        $createdAt = htmlspecialchars((string)($order['created_at'] ?? ''), ENT_QUOTES, 'UTF-8');
        ?>
<div id="orderModalOuter" class="admin-modal-overlay fixed inset-0 bg-black/50 flex items-start justify-center overflow-y-auto" data-action="close-order-editor-on-overlay" role="dialog" aria-modal="true" aria-hidden="false">
  <div class="admin-modal relative mt-8 bg-white rounded-lg shadow-xl w-full max-w-4xl">
    <div class="modal-header flex justify-between items-center p-2 border-b border-gray-100">
      <h2 class="text-lg font-bold text-green-700"><?= $isEdit ? 'Edit Order' : 'Add New Order' ?><?= ($isEdit && $id) ? ' (#' . $id . ')' : '' ?></h2>
      <a href="<?= $pageUrl ?>" class="modal-close-btn" aria-label="Close" data-action="close-order-editor">×</a>
    </div>
    <div class="modal-body p-4">
      <form id="orderForm" method="POST" action="#" class="flex flex-col gap-6">
        <input type="hidden" name="action" value="<?= $isEdit ? 'update' : 'add' ?>">
        <?php if ($isEdit && $id): ?><input type="hidden" name="orderId" value="<?= $id ?>"><?php endif; ?>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
          <div>
            <label for="orderIdDisplay" class="block text-gray-700">Order ID</label>
            <input type="text" id="orderIdDisplay" class="mt-1 block w-full p-2 border border-gray-300 rounded" value="<?= $id ?>" <?= $isEdit ? 'readonly' : '' ?>>
          </div>
          <div>
            <label for="orderStatus" class="block text-gray-700">Status</label>
            <select id="orderStatus" name="status" class="mt-1 block w-full p-2 border border-gray-300 rounded">
              <?php $statuses = ['pending','paid','shipped','completed','cancelled'];
        foreach ($statuses as $s) {
            $sE = htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
            $sel = ($status === $sE) ? 'selected' : '';
            echo "<option value=\"$sE\" $sel>".ucfirst($sE)."</option>";
        } ?>
            </select>
          </div>
          <div>
            <label for="customerName" class="block text-gray-700">Customer Name</label>
            <input type="text" id="customerName" name="customer_name" class="mt-1 block w-full p-2 border border-gray-300 rounded" value="<?= $customerName ?>">
          </div>
          <div>
            <label for="customerEmail" class="block text-gray-700">Customer Email</label>
            <input type="email" id="customerEmail" name="customer_email" class="mt-1 block w-full p-2 border border-gray-300 rounded" value="<?= $customerEmail ?>">
          </div>
          <div>
            <label for="totalAmount" class="block text-gray-700">Total Amount ($)</label>
            <input type="number" step="0.01" min="0" id="totalAmount" name="total_amount" class="mt-1 block w-full p-2 border border-gray-300 rounded" value="<?= $totalAmount ?>">
          </div>
          <div>
            <label for="createdAt" class="block text-gray-700">Created At</label>
            <input type="text" id="createdAt" class="mt-1 block w-full p-2 border border-gray-300 rounded" value="<?= $createdAt ?>" readonly>
          </div>
        </div>
        <div class="mt-6 flex justify-end gap-2">
          <button type="button" class="btn" data-action="close-order-editor">Cancel</button>
          <button type="submit" class="btn btn-primary" data-action="save-order">Save Changes</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php }
    }
?>
