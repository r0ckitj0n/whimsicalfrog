<?php
/**
 * includes/helpers/EmailNotificationHelper.php
 * Helper class for email notification logic
 */

require_once __DIR__ . '/../Constants.php';

class EmailNotificationHelper {
    /**
     * Prepare variables for order confirmation emails
     */
    public static function prepareOrderVariables($order, $orderItems) {
        $customer_name = trim(($order['first_name'] ?? '') . ' ' . ($order['last_name'] ?? ''));
        if (empty($customer_name)) {
            $customer_name = $order['username'] ?? 'Valued Customer';
        }

        $orderDate = date('F j, Y g:i A', strtotime($order['date'] ?? 'now'));
        $orderTotal = '$' . number_format((float)$order['total'], 2);

        $shipping_address = 'Not specified';
        if (!empty($order['shipping_address'])) {
            $addressData = json_decode($order['shipping_address'], true);
            if (is_array($addressData)) {
                $line1 = $addressData['address_line_1'] ?? $addressData['address_line_1'] ?? '';
                $line2 = $addressData['address_line_2'] ?? $addressData['address_line_2'] ?? '';
                $city  = $addressData['city'] ?? '';
                $state = $addressData['state'] ?? '';
                $zip   = $addressData['zip_code'] ?? $addressData['zip_code'] ?? '';
                $addressParts = array_filter([$line1, $line2, $city, $state, $zip]);
                $shipping_address = $addressParts ? implode(', ', $addressParts) : 'Not specified';
            } else {
                $shipping_address = $order['shipping_address'];
            }
        }

        $itemsListHtml = '';
        $itemsListText = '';
        foreach ($orderItems as $item) {
            $item_name = $item['name'] ?? 'Unknown Item';
            $item_sku = $item['sku'] ?? '';
            $itemQuantity = $item['quantity'] ?? 1;
            $item_price = '$' . number_format((float)($item['price'] ?? 0), 2);
            $itemTotal = '$' . number_format($itemQuantity * (float)($item['price'] ?? 0), 2);

            $itemsListHtml .= "<li class='email-list-item'><strong>{$item_name}</strong>";
            if ($item_sku) $itemsListHtml .= " <small class='u-color-666'>({$item_sku})</small>";
            $itemsListHtml .= "<br><span class='u-color-666'>Quantity: {$itemQuantity} × {$item_price} = {$itemTotal}</span></li>";

            $itemsListText .= "- {$item_name}" . ($item_sku ? " ({$item_sku})" : "") . " - Qty: {$itemQuantity} × {$item_price} = {$itemTotal}\n";
        }

        return [
            'customer_name' => $customer_name,
            'customer_email' => $order['email'] ?? 'N/A',
            'order_id' => $order['id'],
            'order.created_at' => $orderDate,
            'order_total' => $orderTotal,
            'items' => $itemsListHtml,
            'items_text' => $itemsListText,
            'shipping_address' => $shipping_address,
            'payment_method' => $order['payment_method'] ?? 'Not specified',
            'shipping_method' => $order['shipping_method'] ?? 'Not specified',
            'status' => $order['status'] ?? WF_Constants::ORDER_STATUS_PROCESSING,
            'payment_status' => $order['payment_status'] ?? WF_Constants::PAYMENT_STATUS_PENDING
        ];
    }

    /**
     * Get email CSS styles
     */
    public static function getEmailStyles() {
        $brandPrimary = BusinessSettings::getPrimaryColor();
        $brandSecondary = BusinessSettings::getSecondaryColor();
        return "
        body.email-body { margin:0; padding:0; background:#ffffff; color:#333; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif; line-height:1.5; }
        .email-wrapper { max-width:600px; margin:0 auto; padding:16px; }
        .email-header { background: {$brandPrimary}; color:#fff; padding:16px; text-align:center; }
        .email-title { margin:0; font-size:20px; }
        .email-subtitle { margin:8px 0 0; font-size:14px; color:#eef; }
        .email-section { margin:16px 0; }
        .email-section-heading { font-size:16px; margin:0 0 8px; color: {$brandSecondary}; }
        .email-summary-table, .email-order-table, .email-table { width:100%; border-collapse:collapse; }
        .email-summary-table td, .email-order-table td, .email-order-table th, .email-table td, .email-table th { padding:8px; border-bottom:1px solid #eee; text-align:left; }
        .email-table-cell-center { text-align:center; }
        .email-table-cell-right { text-align:right; }
        .email-table-header-cell { background:#f6f6f6; font-weight:bold; }
        .email-table-row-alt { background:#f9f9f9; }
        .email-cta-button { display:inline-block; background: {$brandPrimary}; color:#fff !important; text-decoration:none; padding:10px 14px; border-radius:4px; }
        .email-secondary-cta { display:inline-block; color: {$brandPrimary}; text-decoration:none; padding:10px 14px; }
        .email-footer { margin-top:24px; font-size:12px; color:#666; text-align:center; }
        .email-footer-primary { margin:0 0 4px; }
        .email-footer-secondary { margin:0; }
        .email-badge-warning { background:#fff3cd; color:#856404; padding:2px 6px; border-radius:4px; }
        .email-status-received { color:#2e7d32; font-weight:bold; }
        .email-status-pending { color:#b26a00; font-weight:bold; }
        .m-0 { margin:0; }
        .u-margin-right-10px { margin-right:10px; }
        .u-padding-4px-0 { padding:4px 0; }
        .u-align-top { vertical-align:top; }
        .u-color-333 { color:#333; }
        .u-color-666 { color:#666; }
        .u-line-height-1-6 { line-height:1.6; }
        .u-font-weight-bold { font-weight:bold; }
        .u-font-size-14px { font-size:14px; }
        .u-margin-top-10px { margin-top:10px; }
        .u-margin-top-20px { margin-top:20px; }
        .email-admin-header, .email-header { background: {$brandPrimary}; color:#fff; }
        .email-admin-summary-label { text-align:right; padding:8px; font-weight:bold; }
        .email-admin-summary-value { text-align:right; padding:8px; }
        .email-admin-notice { background:#fff8e1; border:1px solid #ffe082; padding:10px; border-radius:4px; margin:12px 0; }
        .email-shipping-box { border:1px solid #eee; border-radius:4px; padding:12px; margin:12px 0; background:#fafafa; }
        .email-admin-grid { display:block; }
        @media screen and (min-width: 480px) {
          .email-admin-grid { display:flex; gap:16px; }
          .email-admin-grid .email-section { flex:1; }
        }
        blockquote { margin:12px 0; padding-left:12px; border-left:3px solid #eee; color:#555; }
        ";
    }
}
