<?php
// Email Configuration for WhimsicalFrog
// This file handles email sending functionality for order confirmations and notifications

<<<<<<< HEAD
=======
// Include business settings helper for brand colors
require_once __DIR__ . '/business_settings_helper.php';

>>>>>>> df48c881 (Codebase audit & cleanup: remove unused JS, fix ESLint to 0 errors, add ESLint config, backup removed code under backups/code_removed. Also initialized git repo.)
// Email settings
define('SMTP_ENABLED', true); // Set to true if using SMTP, false for PHP mail() function
define('FROM_EMAIL', 'orders@whimsicalfrog.us');
define('FROM_NAME', 'WhimsicalFrog');
define('ADMIN_EMAIL', 'admin@whimsicalfrog.us'); // Admin notification email
define('BCC_EMAIL', ''); // Optional BCC email

// SMTP Settings (if SMTP_ENABLED is true) - Optimized for IONOS hosting
define('SMTP_HOST', 'smtp.ionos.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'orders@whimsicalfrog.us');
define('SMTP_PASSWORD', 'Palz2516!'); // Configure this through the admin panel
define('SMTP_ENCRYPTION', 'tls'); // 'tls' or 'ssl'

/**
 * Send email using PHP mail() function or SMTP
 */
function sendEmail($to, $subject, $htmlBody, $plainTextBody = '') {
    $headers = [
        'From: ' . FROM_NAME . ' <' . FROM_EMAIL . '>',
        'Reply-To: ' . FROM_EMAIL,
        'X-Mailer: PHP/' . phpversion(),
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8'
    ];
    
    // Add BCC if configured
    if (defined('BCC_EMAIL') && BCC_EMAIL) {
        $headers[] = 'Bcc: ' . BCC_EMAIL;
    }
    
    $headerString = implode("\r\n", $headers);
    
    if (SMTP_ENABLED) {
        // Use PHPMailer or similar SMTP library
        // For now, we'll use PHP mail() function
        // In production, consider using PHPMailer for better reliability
        return mail($to, $subject, $htmlBody, $headerString);
    } else {
        // Use PHP mail() function
        return mail($to, $subject, $htmlBody, $headerString);
    }
}

/**
 * Generate customer order confirmation email HTML
 */
function generateCustomerConfirmationEmail($orderData, $customerData, $orderItems) {
<<<<<<< HEAD
=======
    // Get brand colors from business settings
    $brandPrimary = BusinessSettings::getPrimaryColor();
    $brandSecondary = BusinessSettings::getSecondaryColor();
    
>>>>>>> df48c881 (Codebase audit & cleanup: remove unused JS, fix ESLint to 0 errors, add ESLint config, backup removed code under backups/code_removed. Also initialized git repo.)
    $orderId = htmlspecialchars($orderData['id']);
    $customerName = htmlspecialchars(trim(($customerData['first_name'] ?? '') . ' ' . ($customerData['last_name'] ?? '')));
    if (empty(trim($customerName))) {
        $customerName = htmlspecialchars($customerData['username'] ?? 'Valued Customer');
    }
    
    $orderDate = date('F j, Y', strtotime($orderData['date'] ?? 'now'));
    $orderTotal = number_format((float)$orderData['total'], 2);
    $paymentMethod = htmlspecialchars($orderData['paymentMethod'] ?? 'Not specified');
    $shippingMethod = htmlspecialchars($orderData['shippingMethod'] ?? 'Not specified');
    $orderStatus = htmlspecialchars($orderData['order_status'] ?? 'Processing');
    $paymentStatus = htmlspecialchars($orderData['paymentStatus'] ?? 'Pending');
    
    // Build items list
    $itemsHtml = '';
    foreach ($orderItems as $item) {
        $itemName = htmlspecialchars($item['name'] ?? 'Item');
        $itemQuantity = (int)($item['quantity'] ?? 1);
        $itemPrice = number_format((float)($item['price'] ?? 0), 2);
        $itemTotal = number_format($itemQuantity * (float)($item['price'] ?? 0), 2);
        
        $itemsHtml .= "
            <tr>
<<<<<<< HEAD
                <td style='padding: 10px; border-bottom: 1px solid #eee;'>{$itemName}</td>
                <td style='padding: 10px; border-bottom: 1px solid #eee; text-align: center;'>{$itemQuantity}</td>
                <td style='padding: 10px; border-bottom: 1px solid #eee; text-align: right;'>\${$itemPrice}</td>
                <td style='padding: 10px; border-bottom: 1px solid #eee; text-align: right;'>\${$itemTotal}</td>
=======
                <td class='email-table-cell'>{$itemName}</td>
                <td class='email-table-cell email-table-cell-center'>{$itemQuantity}</td>
                <td class='email-table-cell email-table-cell-right'>\${$itemPrice}</td>
                <td class='email-table-cell email-table-cell-right'>\${$itemTotal}</td>
>>>>>>> df48c881 (Codebase audit & cleanup: remove unused JS, fix ESLint to 0 errors, add ESLint config, backup removed code under backups/code_removed. Also initialized git repo.)
            </tr>";
    }
    
    // Shipping address
    $shippingAddress = '';
    if (!empty($orderData['shippingAddress'])) {
        $shippingAddress = '<strong>Shipping Address:</strong><br>' . nl2br(htmlspecialchars($orderData['shippingAddress']));
    } elseif (!empty($customerData['addressLine1'])) {
        $address = htmlspecialchars($customerData['addressLine1'] ?? '');
        if (!empty($customerData['addressLine2'])) {
            $address .= '<br>' . htmlspecialchars($customerData['addressLine2']);
        }
        if (!empty($customerData['city']) || !empty($customerData['state']) || !empty($customerData['zipCode'])) {
            $cityStateZip = trim(
                htmlspecialchars($customerData['city'] ?? '') . ', ' .
                htmlspecialchars($customerData['state'] ?? '') . ' ' .
                htmlspecialchars($customerData['zipCode'] ?? '')
            );
            $address .= '<br>' . ltrim($cityStateZip, ', ');
        }
        $shippingAddress = '<strong>Shipping Address:</strong><br>' . $address;
    }
    
    $html = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Order Confirmation - WhimsicalFrog</title>
<<<<<<< HEAD
    </head>
    <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;'>
        <div style='background-color: #87ac3a; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0;'>
            <h1 style='margin: 0; font-size: 28px;'>WhimsicalFrog</h1>
            <p style='margin: 5px 0 0 0; font-size: 16px;'>Order Confirmation</p>
        </div>
        
        <div style='background-color: #f9f9f9; padding: 30px; border-radius: 0 0 8px 8px; border: 1px solid #ddd;'>
            <h2 style='color: #87ac3a; margin-top: 0;'>Thank you for your order, {$customerName}!</h2>
            
            <p>We've received your order and we're getting it ready for you. Here are the details:</p>
            
            <div style='background-color: white; padding: 20px; border-radius: 6px; margin: 20px 0; border: 1px solid #e0e0e0;'>
                <h3 style='color: #87ac3a; margin-top: 0;'>Order Information</h3>
                <table style='width: 100%; border-collapse: collapse;'>
                    <tr>
                        <td style='padding: 8px 0; font-weight: bold;'>Order Number:</td>
                        <td style='padding: 8px 0;'>{$orderId}</td>
                    </tr>
                    <tr>
                        <td style='padding: 8px 0; font-weight: bold;'>Order Date:</td>
                        <td style='padding: 8px 0;'>{$orderDate}</td>
                    </tr>
                    <tr>
                        <td style='padding: 8px 0; font-weight: bold;'>Order Status:</td>
                        <td style='padding: 8px 0;'>{$orderStatus}</td>
                    </tr>
                    <tr>
                        <td style='padding: 8px 0; font-weight: bold;'>Payment Method:</td>
                        <td style='padding: 8px 0;'>{$paymentMethod}</td>
                    </tr>
                    <tr>
                        <td style='padding: 8px 0; font-weight: bold;'>Payment Status:</td>
                        <td style='padding: 8px 0;'>{$paymentStatus}</td>
                    </tr>
                    <tr>
                        <td style='padding: 8px 0; font-weight: bold;'>Shipping Method:</td>
                        <td style='padding: 8px 0;'>{$shippingMethod}</td>
=======
        <link rel='stylesheet' href='https://whimsicalfrog.us/css/email-styles.css'>
    </head>
    <body class='email-body' style='-brand-primary: {$brandPrimary}; -brand-secondary: {$brandSecondary};'>
        <div class='email-header'>
            <h1 class='email-title'>WhimsicalFrog</h1>
            <p class='email-subtitle'>Order Confirmation</p>
        </div>
        
        <div class='email-wrapper'>
            <h2 class='email-section-heading'>Thank you for your order, {$customerName}!</h2>
            
            <p>We've received your order and we're getting it ready for you. Here are the details:</p>
            
            <div class='email-section'>
                <h3 class='email-section-heading'>Order Information</h3>
                <table class='email-summary-table'>
                    <tr>
                        <td class='email-summary-label'>Order Number:</td>
                        <td class='email-summary-value'>{$orderId}</td>
                    </tr>
                    <tr>
                        <td class='email-summary-label'>Order Date:</td>
                        <td class='email-summary-value'>{$orderDate}</td>
                    </tr>
                    <tr>
                        <td class='email-summary-label'>Order Status:</td>
                        <td class='email-summary-value'>{$orderStatus}</td>
                    </tr>
                    <tr>
                        <td class='email-summary-label'>Payment Method:</td>
                        <td class='email-summary-value'>{$paymentMethod}</td>
                    </tr>
                    <tr>
                        <td class='email-summary-label'>Payment Status:</td>
                        <td class='email-summary-value'>{$paymentStatus}</td>
                    </tr>
                    <tr>
                        <td class='email-summary-label'>Shipping Method:</td>
                        <td class='email-summary-value'>{$shippingMethod}</td>
>>>>>>> df48c881 (Codebase audit & cleanup: remove unused JS, fix ESLint to 0 errors, add ESLint config, backup removed code under backups/code_removed. Also initialized git repo.)
                    </tr>
                </table>
            </div>
            
<<<<<<< HEAD
            <div style='background-color: white; padding: 20px; border-radius: 6px; margin: 20px 0; border: 1px solid #e0e0e0;'>
                <h3 style='color: #87ac3a; margin-top: 0;'>Order Items</h3>
                <table style='width: 100%; border-collapse: collapse;'>
                    <thead>
                        <tr style='background-color: #f0f0f0;'>
                            <th style='padding: 10px; text-align: left; border-bottom: 2px solid #87ac3a;'>Item</th>
                            <th style='padding: 10px; text-align: center; border-bottom: 2px solid #87ac3a;'>Qty</th>
                            <th style='padding: 10px; text-align: right; border-bottom: 2px solid #87ac3a;'>Price</th>
                            <th style='padding: 10px; text-align: right; border-bottom: 2px solid #87ac3a;'>Total</th>
=======
            <div class='email-section'>
                <h3 class='email-section-heading'>Order Items</h3>
                <table class='email-order-table'>
                    <thead>
                        <tr class='email-table-row-alt'>
                            <th class='email-table-header-cell'>Item</th>
                            <th class='email-table-header-cell email-table-cell-center'>Qty</th>
                            <th class='email-table-header-cell email-table-cell-right'>Price</th>
                            <th class='email-table-header-cell email-table-cell-right'>Total</th>
>>>>>>> df48c881 (Codebase audit & cleanup: remove unused JS, fix ESLint to 0 errors, add ESLint config, backup removed code under backups/code_removed. Also initialized git repo.)
                        </tr>
                    </thead>
                    <tbody>
                        {$itemsHtml}
                    </tbody>
                    <tfoot>
                        <tr>
<<<<<<< HEAD
                            <td colspan='3' style='padding: 15px 10px 10px; text-align: right; font-weight: bold; font-size: 18px; border-top: 2px solid #87ac3a;'>
                                Order Total:
                            </td>
                            <td style='padding: 15px 10px 10px; text-align: right; font-weight: bold; font-size: 18px; color: #87ac3a; border-top: 2px solid #87ac3a;'>
=======
                            <td colspan='3' class='email-summary-label'>
                                Order Total:
                            </td>
                            <td class='email-summary-value'>
>>>>>>> df48c881 (Codebase audit & cleanup: remove unused JS, fix ESLint to 0 errors, add ESLint config, backup removed code under backups/code_removed. Also initialized git repo.)
                                \${$orderTotal}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            
            " . (!empty($shippingAddress) ? "
<<<<<<< HEAD
            <div style='background-color: white; padding: 20px; border-radius: 6px; margin: 20px 0; border: 1px solid #e0e0e0;'>
                <h3 style='color: #87ac3a; margin-top: 0;'>Shipping Information</h3>
                <p style='margin: 0;'>{$shippingAddress}</p>
            </div>
            " : "") . "
            
            <div style='background-color: #e8f4e8; padding: 20px; border-radius: 6px; margin: 20px 0; border-left: 4px solid #87ac3a;'>
                <h3 style='color: #87ac3a; margin-top: 0;'>What's Next?</h3>
                <p style='margin-bottom: 10px;'>• We'll send you another email when your order ships</p>
                <p style='margin-bottom: 10px;'>• You can track your order status anytime by logging into your account</p>
                <p style='margin-bottom: 0;'>• Questions? Contact us at " . FROM_EMAIL . "</p>
            </div>
            
            <div style='text-align: center; margin: 30px 0; padding: 20px; background-color: white; border-radius: 6px; border: 1px solid #e0e0e0;'>
                <a href='https://whimsicalfrog.us/?page=receipt&orderId={$orderId}' 
                   style='display: inline-block; background-color: #87ac3a; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight: bold;'>
=======
            <div class='email-section'>
                <h3 class='email-section-heading'>Shipping Information</h3>
                <p class='m-0'>{$shippingAddress}</p>
            </div>
            " : "") . "
            
            <div class='email-next-steps'>
                <h3 class='email-section-heading'>What's Next?</h3>
                <p class='email-next-step'>• We'll send you another email when your order ships</p>
                <p class='email-next-step'>• You can track your order status anytime by logging into your account</p>
                <p class='email-next-step'>• Questions? Contact us at " . FROM_EMAIL . "</p>
            </div>
            
            <div class='email-next-steps'>
                <a href='https://whimsicalfrog.us/?page=receipt&orderId={$orderId}' class='email-cta-button'>
>>>>>>> df48c881 (Codebase audit & cleanup: remove unused JS, fix ESLint to 0 errors, add ESLint config, backup removed code under backups/code_removed. Also initialized git repo.)
                    View Order Details
                </a>
            </div>
            
<<<<<<< HEAD
            <div style='text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd; color: #666;'>
                <p style='margin: 0; font-size: 14px;'>Thank you for shopping with WhimsicalFrog!</p>
                <p style='margin: 5px 0 0 0; font-size: 12px;'>© " . date('Y') . " WhimsicalFrog. All rights reserved.</p>
=======
            <div class='email-footer'>
                <p class='email-footer-primary'>Thank you for shopping with WhimsicalFrog!</p>
                <p class='email-footer-secondary'>© " . date('Y') . " WhimsicalFrog. All rights reserved.</p>
>>>>>>> df48c881 (Codebase audit & cleanup: remove unused JS, fix ESLint to 0 errors, add ESLint config, backup removed code under backups/code_removed. Also initialized git repo.)
            </div>
        </div>
    </body>
    </html>";
    
    return $html;
}

/**
 * Generate admin order notification email HTML
 */
function generateAdminNotificationEmail($orderData, $customerData, $orderItems) {
<<<<<<< HEAD
=======
    // Get brand colors from business settings
    $brandPrimary = BusinessSettings::getPrimaryColor();
    $brandSecondary = BusinessSettings::getSecondaryColor();
    
>>>>>>> df48c881 (Codebase audit & cleanup: remove unused JS, fix ESLint to 0 errors, add ESLint config, backup removed code under backups/code_removed. Also initialized git repo.)
    $orderId = htmlspecialchars($orderData['id']);
    $customerName = htmlspecialchars(trim(($customerData['first_name'] ?? '') . ' ' . ($customerData['last_name'] ?? '')));
    if (empty(trim($customerName))) {
        $customerName = htmlspecialchars($customerData['username'] ?? 'Unknown Customer');
    }
    
    $customerEmail = htmlspecialchars($customerData['email'] ?? 'No email');
    $customerPhone = htmlspecialchars($customerData['phoneNumber'] ?? 'No phone');
    
    $orderDate = date('F j, Y g:i A', strtotime($orderData['date'] ?? 'now'));
    $orderTotal = number_format((float)$orderData['total'], 2);
    $paymentMethod = htmlspecialchars($orderData['paymentMethod'] ?? 'Not specified');
    $shippingMethod = htmlspecialchars($orderData['shippingMethod'] ?? 'Not specified');
    $orderStatus = htmlspecialchars($orderData['order_status'] ?? 'Processing');
    $paymentStatus = htmlspecialchars($orderData['paymentStatus'] ?? 'Pending');
    
    // Build items list
    $itemsHtml = '';
    $totalQuantity = 0;
    foreach ($orderItems as $item) {
        $itemName = htmlspecialchars($item['name'] ?? 'Item');
        $itemQuantity = (int)($item['quantity'] ?? 1);
        $itemPrice = number_format((float)($item['price'] ?? 0), 2);
        $itemTotal = number_format($itemQuantity * (float)($item['price'] ?? 0), 2);
        $totalQuantity += $itemQuantity;
        
        $itemsHtml .= "
            <tr>
<<<<<<< HEAD
                <td style='padding: 8px; border-bottom: 1px solid #eee;'>{$itemName}</td>
                <td style='padding: 8px; border-bottom: 1px solid #eee; text-align: center;'>{$itemQuantity}</td>
                <td style='padding: 8px; border-bottom: 1px solid #eee; text-align: right;'>\${$itemPrice}</td>
                <td style='padding: 8px; border-bottom: 1px solid #eee; text-align: right;'>\${$itemTotal}</td>
=======
                <td class='email-table-cell'>{$itemName}</td>
                <td class='email-table-cell email-table-cell-center'>{$itemQuantity}</td>
                <td class='email-table-cell email-table-cell-right'>\${$itemPrice}</td>
                <td class='email-table-cell email-table-cell-right'>\${$itemTotal}</td>
>>>>>>> df48c881 (Codebase audit & cleanup: remove unused JS, fix ESLint to 0 errors, add ESLint config, backup removed code under backups/code_removed. Also initialized git repo.)
            </tr>";
    }
    
    // Customer address
    $customerAddress = 'Not provided';
    if (!empty($customerData['addressLine1'])) {
        $address = htmlspecialchars($customerData['addressLine1'] ?? '');
        if (!empty($customerData['addressLine2'])) {
            $address .= '<br>' . htmlspecialchars($customerData['addressLine2']);
        }
        if (!empty($customerData['city']) || !empty($customerData['state']) || !empty($customerData['zipCode'])) {
            $cityStateZip = trim(
                htmlspecialchars($customerData['city'] ?? '') . ', ' .
                htmlspecialchars($customerData['state'] ?? '') . ' ' .
                htmlspecialchars($customerData['zipCode'] ?? '')
            );
            $address .= '<br>' . ltrim($cityStateZip, ', ');
        }
        $customerAddress = $address;
    }
    
    // Shipping address (if different)
    $shippingAddress = '';
    if (!empty($orderData['shippingAddress']) && $orderData['shippingAddress'] !== $customerAddress) {
        $shippingAddress = nl2br(htmlspecialchars($orderData['shippingAddress']));
    }
    
    $html = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>New Order Alert - WhimsicalFrog Admin</title>
<<<<<<< HEAD
    </head>
    <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 700px; margin: 0 auto; padding: 20px;'>
        <div style='background-color: #d32f2f; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0;'>
            <h1 style='margin: 0; font-size: 28px;'>🎉 NEW ORDER ALERT!</h1>
            <p style='margin: 5px 0 0 0; font-size: 16px;'>WhimsicalFrog Admin Notification</p>
        </div>
        
        <div style='background-color: #f9f9f9; padding: 30px; border-radius: 0 0 8px 8px; border: 1px solid #ddd;'>
            <div style='background-color: #fff3cd; border: 1px solid #ffeaa7; color: #856404; padding: 15px; border-radius: 6px; margin-bottom: 20px;'>
                <strong>⏰ Action Required:</strong> A new order has been placed and requires your attention!
            </div>
            
            <h2 style='color: #d32f2f; margin-top: 0;'>Order #{$orderId}</h2>
            <p style='font-size: 16px; margin-bottom: 20px;'>
=======
        <link rel='stylesheet' href='https://whimsicalfrog.us/css/email-styles.css'>
    </head>
    <body class='email-body' style='-brand-primary: {$brandPrimary}; -brand-secondary: {$brandSecondary};'>
        <div class='email-admin-header'>
            <h1 class='email-title'>🎉 NEW ORDER ALERT!</h1>
            <p class='email-subtitle'>WhimsicalFrog Admin Notification</p>
        </div>
        
        <div class='email-wrapper'>
            <div class='email-admin-notice'>
                <strong>⏰ Action Required:</strong> A new order has been placed and requires your attention!
            </div>
            
            <h2 class='email-admin-title'>Order #{$orderId}</h2>
            <p class='email-admin-summary'>
>>>>>>> df48c881 (Codebase audit & cleanup: remove unused JS, fix ESLint to 0 errors, add ESLint config, backup removed code under backups/code_removed. Also initialized git repo.)
                <strong>Placed:</strong> {$orderDate} | 
                <strong>Total:</strong> \${$orderTotal} | 
                <strong>Items:</strong> {$totalQuantity}
            </p>
            
<<<<<<< HEAD
            <div style='display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin: 20px 0;'>
                <div style='background-color: white; padding: 20px; border-radius: 6px; border: 1px solid #e0e0e0;'>
                    <h3 style='color: #87ac3a; margin-top: 0;'>Customer Information</h3>
                    <table style='width: 100%;'>
                        <tr><td style='font-weight: bold; padding: 4px 0;'>Name:</td><td style='padding: 4px 0;'>{$customerName}</td></tr>
                        <tr><td style='font-weight: bold; padding: 4px 0;'>Email:</td><td style='padding: 4px 0;'>{$customerEmail}</td></tr>
                        <tr><td style='font-weight: bold; padding: 4px 0;'>Phone:</td><td style='padding: 4px 0;'>{$customerPhone}</td></tr>
                        <tr><td style='font-weight: bold; padding: 4px 0; vertical-align: top;'>Address:</td><td style='padding: 4px 0;'>{$customerAddress}</td></tr>
                    </table>
                </div>
                
                <div style='background-color: white; padding: 20px; border-radius: 6px; border: 1px solid #e0e0e0;'>
                    <h3 style='color: #87ac3a; margin-top: 0;'>Order Status</h3>
                    <table style='width: 100%;'>
                        <tr><td style='font-weight: bold; padding: 4px 0;'>Status:</td><td style='padding: 4px 0;'><span style='background: #ffeb3b; padding: 2px 6px; border-radius: 3px;'>{$orderStatus}</span></td></tr>
                        <tr><td style='font-weight: bold; padding: 4px 0;'>Payment:</td><td style='padding: 4px 0;'>{$paymentMethod}</td></tr>
                        <tr><td style='font-weight: bold; padding: 4px 0;'>Payment Status:</td><td style='padding: 4px 0;'><span style='background: " . ($paymentStatus === 'Received' ? '#c8e6c9' : '#ffcdd2') . "; padding: 2px 6px; border-radius: 3px;'>{$paymentStatus}</span></td></tr>
                        <tr><td style='font-weight: bold; padding: 4px 0;'>Shipping:</td><td style='padding: 4px 0;'>{$shippingMethod}</td></tr>
=======
            <div class='email-admin-grid'>
                <div class='email-section'>
                    <h3 class='email-section-heading'>Customer Information</h3>
                    <table class='email-summary-table'>
                        <tr><td class='u-font-weight-bold u-padding-4px-0'>Name:</td><td class='u-padding-4px-0'>{$customerName}</td></tr>
                        <tr><td class='u-font-weight-bold u-padding-4px-0'>Email:</td><td class='u-padding-4px-0'>{$customerEmail}</td></tr>
                        <tr><td class='u-font-weight-bold u-padding-4px-0'>Phone:</td><td class='u-padding-4px-0'>{$customerPhone}</td></tr>
                        <tr><td class='u-font-weight-bold u-padding-4px-0 u-align-top'>Address:</td><td class='u-padding-4px-0'>{$customerAddress}</td></tr>
                    </table>
                </div>
                
                <div class='email-section'>
                    <h3 class='email-section-heading'>Order Status</h3>
                    <table class='email-summary-table'>
                        <tr><td class='u-font-weight-bold u-padding-4px-0'>Status:</td><td class='u-padding-4px-0'><span class='email-badge-warning'>{$orderStatus}</span></td></tr>
                        <tr><td class='u-font-weight-bold u-padding-4px-0'>Payment:</td><td class='u-padding-4px-0'>{$paymentMethod}</td></tr>
                        <tr><td class='u-font-weight-bold u-padding-4px-0'>Payment Status:</td><td class='u-padding-4px-0'><span class='" . ($paymentStatus === 'Received' ? 'email-status-received' : 'email-status-pending') . "'>{$paymentStatus}</span></td></tr>
                        <tr><td class='u-font-weight-bold u-padding-4px-0'>Shipping:</td><td class='u-padding-4px-0'>{$shippingMethod}</td></tr>
>>>>>>> df48c881 (Codebase audit & cleanup: remove unused JS, fix ESLint to 0 errors, add ESLint config, backup removed code under backups/code_removed. Also initialized git repo.)
                    </table>
                </div>
            </div>
            
            " . (!empty($shippingAddress) ? "
<<<<<<< HEAD
            <div style='background-color: #e1f5fe; padding: 15px; border-radius: 6px; margin: 20px 0; border-left: 4px solid #0288d1;'>
                <h4 style='margin-top: 0; color: #0277bd;'>📦 Custom Shipping Address</h4>
                <p style='margin: 0;'>{$shippingAddress}</p>
            </div>
            " : "") . "
            
            <div style='background-color: white; padding: 20px; border-radius: 6px; margin: 20px 0; border: 1px solid #e0e0e0;'>
                <h3 style='color: #87ac3a; margin-top: 0;'>Ordered Items</h3>
                <table style='width: 100%; border-collapse: collapse;'>
                    <thead>
                        <tr style='background-color: #f5f5f5;'>
                            <th style='padding: 10px; text-align: left; border-bottom: 2px solid #87ac3a;'>Item</th>
                            <th style='padding: 10px; text-align: center; border-bottom: 2px solid #87ac3a;'>Qty</th>
                            <th style='padding: 10px; text-align: right; border-bottom: 2px solid #87ac3a;'>Price</th>
                            <th style='padding: 10px; text-align: right; border-bottom: 2px solid #87ac3a;'>Total</th>
=======
            <div class='email-shipping-box'>
                <h4 class='email-shipping-heading'>📦 Custom Shipping Address</h4>
                <p class='m-0'>{$shippingAddress}</p>
            </div>
            " : "") . "
            
            <div class='email-section'>
                <h3 class='email-section-heading'>Ordered Items</h3>
                <table class='email-table'>
                    <thead>
                        <tr class='email-table-row-alt'>
                            <th class='email-table-header-cell'>Item</th>
                            <th class='email-table-header-cell email-table-cell-center'>Qty</th>
                            <th class='email-table-header-cell email-table-cell-right'>Price</th>
                            <th class='email-table-header-cell email-table-cell-right'>Total</th>
>>>>>>> df48c881 (Codebase audit & cleanup: remove unused JS, fix ESLint to 0 errors, add ESLint config, backup removed code under backups/code_removed. Also initialized git repo.)
                        </tr>
                    </thead>
                    <tbody>
                        {$itemsHtml}
                    </tbody>
                    <tfoot>
                        <tr>
<<<<<<< HEAD
                            <td colspan='3' style='padding: 15px 10px 10px; text-align: right; font-weight: bold; font-size: 16px; border-top: 2px solid #87ac3a;'>
                                Order Total:
                            </td>
                            <td style='padding: 15px 10px 10px; text-align: right; font-weight: bold; font-size: 16px; color: #d32f2f; border-top: 2px solid #87ac3a;'>
=======
                            <td colspan='3' class='email-admin-summary-label'>
                                Order Total:
                            </td>
                            <td class='email-admin-summary-value'>
>>>>>>> df48c881 (Codebase audit & cleanup: remove unused JS, fix ESLint to 0 errors, add ESLint config, backup removed code under backups/code_removed. Also initialized git repo.)
                                \${$orderTotal}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            
<<<<<<< HEAD
            <div style='text-align: center; margin: 30px 0; padding: 20px; background-color: white; border-radius: 6px; border: 1px solid #e0e0e0;'>
                <a href='https://whimsicalfrog.us/?page=admin&section=orders&view={$orderId}' 
                   style='display: inline-block; background-color: #87ac3a; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight: bold; margin-right: 10px;'>
                    View in Admin Panel
                </a>
                <a href='https://whimsicalfrog.us/?page=admin&section=orders' 
                   style='display: inline-block; background-color: #2196f3; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight: bold;'>
=======
            <div class='email-next-steps'>
                <a href='https://whimsicalfrog.us/?page=admin&section=orders&view={$orderId}' class='email-cta-button u-margin-right-10px'>
                    View in Admin Panel
                </a>
                <a href='https://whimsicalfrog.us/?page=admin&section=orders' class='email-secondary-cta'>
>>>>>>> df48c881 (Codebase audit & cleanup: remove unused JS, fix ESLint to 0 errors, add ESLint config, backup removed code under backups/code_removed. Also initialized git repo.)
                    All Orders
                </a>
            </div>
            
<<<<<<< HEAD
            <div style='background-color: #ffebee; padding: 15px; border-radius: 6px; margin: 20px 0; border-left: 4px solid #f44336;'>
                <h4 style='margin-top: 0; color: #d32f2f;'>⚡ Quick Actions Needed</h4>
                <ul style='margin: 0; padding-left: 20px;'>
=======
            <div class='email-admin-quick-actions'>
                <h4>⚡ Quick Actions Needed</h4>
                <ul>
>>>>>>> df48c881 (Codebase audit & cleanup: remove unused JS, fix ESLint to 0 errors, add ESLint config, backup removed code under backups/code_removed. Also initialized git repo.)
                    <li>Verify payment status and process if needed</li>
                    <li>Check inventory and prepare items for " . ($shippingMethod === 'Customer Pickup' ? 'pickup' : 'shipping') . "</li>
                    <li>Update order status as processing begins</li>
                    <li>Contact customer if any issues arise</li>
                </ul>
            </div>
            
<<<<<<< HEAD
            <div style='text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd; color: #666;'>
                <p style='margin: 0; font-size: 14px;'>WhimsicalFrog Admin Notification System</p>
                <p style='margin: 5px 0 0 0; font-size: 12px;'>Order placed at " . date('g:i A \o\n F j, Y') . "</p>
=======
            <div class='email-admin-footer'>
                <p class='email-footer-primary'>WhimsicalFrog Admin Notification System</p>
                <p class='email-footer-secondary'>Order placed at " . date('g:i A \o\n F j, Y') . "</p>
>>>>>>> df48c881 (Codebase audit & cleanup: remove unused JS, fix ESLint to 0 errors, add ESLint config, backup removed code under backups/code_removed. Also initialized git repo.)
            </div>
        </div>
    </body>
    </html>";
    
    return $html;
}

// Note: sendOrderConfirmationEmails function moved to email_notifications.php
// to avoid function redeclaration conflicts 