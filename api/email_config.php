<?php
// Email Configuration for WhimsicalFrog
// This file handles email sending functionality for order confirmations and notifications

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
    $orderId = htmlspecialchars($orderData['id']);
    $customerName = htmlspecialchars(trim(($customerData['first_name'] ?? '') . ' ' . ($customerData['last_name'] ?? '')));
    if (empty(trim($customerName))) {
        $customerName = htmlspecialchars($customerData['username'] ?? 'Valued Customer');
    }
    
    $orderDate = date('F j, Y', strtotime($orderData['date'] ?? 'now'));
    $orderTotal = number_format((float)$orderData['total'], 2);
    $paymentMethod = htmlspecialchars($orderData['paymentMethod'] ?? 'Not specified');
    $shippingMethod = htmlspecialchars($orderData['shippingMethod'] ?? 'Not specified');
    $orderStatus = htmlspecialchars($orderData['status'] ?? 'Processing');
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
                <td style='padding: 10px; border-bottom: 1px solid #eee;'>{$itemName}</td>
                <td style='padding: 10px; border-bottom: 1px solid #eee; text-align: center;'>{$itemQuantity}</td>
                <td style='padding: 10px; border-bottom: 1px solid #eee; text-align: right;'>\${$itemPrice}</td>
                <td style='padding: 10px; border-bottom: 1px solid #eee; text-align: right;'>\${$itemTotal}</td>
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
                    </tr>
                </table>
            </div>
            
            <div style='background-color: white; padding: 20px; border-radius: 6px; margin: 20px 0; border: 1px solid #e0e0e0;'>
                <h3 style='color: #87ac3a; margin-top: 0;'>Order Items</h3>
                <table style='width: 100%; border-collapse: collapse;'>
                    <thead>
                        <tr style='background-color: #f0f0f0;'>
                            <th style='padding: 10px; text-align: left; border-bottom: 2px solid #87ac3a;'>Item</th>
                            <th style='padding: 10px; text-align: center; border-bottom: 2px solid #87ac3a;'>Qty</th>
                            <th style='padding: 10px; text-align: right; border-bottom: 2px solid #87ac3a;'>Price</th>
                            <th style='padding: 10px; text-align: right; border-bottom: 2px solid #87ac3a;'>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        {$itemsHtml}
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan='3' style='padding: 15px 10px 10px; text-align: right; font-weight: bold; font-size: 18px; border-top: 2px solid #87ac3a;'>
                                Order Total:
                            </td>
                            <td style='padding: 15px 10px 10px; text-align: right; font-weight: bold; font-size: 18px; color: #87ac3a; border-top: 2px solid #87ac3a;'>
                                \${$orderTotal}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            
            " . (!empty($shippingAddress) ? "
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
                    View Order Details
                </a>
            </div>
            
            <div style='text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd; color: #666;'>
                <p style='margin: 0; font-size: 14px;'>Thank you for shopping with WhimsicalFrog!</p>
                <p style='margin: 5px 0 0 0; font-size: 12px;'>© " . date('Y') . " WhimsicalFrog. All rights reserved.</p>
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
    $orderStatus = htmlspecialchars($orderData['status'] ?? 'Processing');
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
                <td style='padding: 8px; border-bottom: 1px solid #eee;'>{$itemName}</td>
                <td style='padding: 8px; border-bottom: 1px solid #eee; text-align: center;'>{$itemQuantity}</td>
                <td style='padding: 8px; border-bottom: 1px solid #eee; text-align: right;'>\${$itemPrice}</td>
                <td style='padding: 8px; border-bottom: 1px solid #eee; text-align: right;'>\${$itemTotal}</td>
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
                <strong>Placed:</strong> {$orderDate} | 
                <strong>Total:</strong> \${$orderTotal} | 
                <strong>Items:</strong> {$totalQuantity}
            </p>
            
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
                    </table>
                </div>
            </div>
            
            " . (!empty($shippingAddress) ? "
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
                        </tr>
                    </thead>
                    <tbody>
                        {$itemsHtml}
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan='3' style='padding: 15px 10px 10px; text-align: right; font-weight: bold; font-size: 16px; border-top: 2px solid #87ac3a;'>
                                Order Total:
                            </td>
                            <td style='padding: 15px 10px 10px; text-align: right; font-weight: bold; font-size: 16px; color: #d32f2f; border-top: 2px solid #87ac3a;'>
                                \${$orderTotal}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            
            <div style='text-align: center; margin: 30px 0; padding: 20px; background-color: white; border-radius: 6px; border: 1px solid #e0e0e0;'>
                <a href='https://whimsicalfrog.us/?page=admin&section=orders&view={$orderId}' 
                   style='display: inline-block; background-color: #87ac3a; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight: bold; margin-right: 10px;'>
                    View in Admin Panel
                </a>
                <a href='https://whimsicalfrog.us/?page=admin&section=orders' 
                   style='display: inline-block; background-color: #2196f3; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight: bold;'>
                    All Orders
                </a>
            </div>
            
            <div style='background-color: #ffebee; padding: 15px; border-radius: 6px; margin: 20px 0; border-left: 4px solid #f44336;'>
                <h4 style='margin-top: 0; color: #d32f2f;'>⚡ Quick Actions Needed</h4>
                <ul style='margin: 0; padding-left: 20px;'>
                    <li>Verify payment status and process if needed</li>
                    <li>Check inventory and prepare items for " . ($shippingMethod === 'Customer Pickup' ? 'pickup' : 'shipping') . "</li>
                    <li>Update order status as processing begins</li>
                    <li>Contact customer if any issues arise</li>
                </ul>
            </div>
            
            <div style='text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd; color: #666;'>
                <p style='margin: 0; font-size: 14px;'>WhimsicalFrog Admin Notification System</p>
                <p style='margin: 5px 0 0 0; font-size: 12px;'>Order placed at " . date('g:i A \o\n F j, Y') . "</p>
            </div>
        </div>
    </body>
    </html>";
    
    return $html;
}

/**
 * Send order confirmation emails (both customer and admin notifications)
 */
function sendOrderConfirmationEmails($orderId, $pdo) {
    try {
        // Get order data
        $orderStmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
        $orderStmt->execute([$orderId]);
        $orderData = $orderStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$orderData) {
            error_log("Order not found: " . $orderId);
            return false;
        }
        
        // Get customer data
        $customerStmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $customerStmt->execute([$orderData['userId']]);
        $customerData = $customerStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$customerData) {
            error_log("Customer not found for order: " . $orderId);
            return false;
        }
        
        // Get order items with item names
        $itemsStmt = $pdo->prepare("
            SELECT oi.*, i.name as itemName 
            FROM order_items oi 
            LEFT JOIN items i ON oi.sku = i.sku 
            WHERE oi.orderId = ?
        ");
        $itemsStmt->execute([$orderId]);
        $orderItems = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
        
        $results = ['customer' => false, 'admin' => false];
        
        // Include email logger
        require_once 'email_logger.php';
        
        // Send customer confirmation email
        if (!empty($customerData['email'])) {
            $customerSubject = "Order Confirmation #{$orderId} - WhimsicalFrog";
            $customerHtml = generateCustomerConfirmationEmail($orderData, $customerData, $orderItems);
            
            $results['customer'] = sendEmail($customerData['email'], $customerSubject, $customerHtml);
            
            // Log the customer email
            $customerStatus = $results['customer'] ? 'sent' : 'failed';
            $customerError = $results['customer'] ? null : 'Email sending failed';
            logOrderConfirmationEmail($customerData['email'], FROM_EMAIL, $customerSubject, $customerHtml, $orderId, $customerStatus, $customerError);
            
            if ($results['customer']) {
                error_log("Customer confirmation email sent successfully for order: " . $orderId);
            } else {
                error_log("Failed to send customer confirmation email for order: " . $orderId);
            }
        }
        
        // Send admin notification email
        if (defined('ADMIN_EMAIL') && ADMIN_EMAIL) {
            $adminSubject = "🎉 New Order #{$orderId} - Action Required";
            $adminHtml = generateAdminNotificationEmail($orderData, $customerData, $orderItems);
            
            $results['admin'] = sendEmail(ADMIN_EMAIL, $adminSubject, $adminHtml);
            
            // Log the admin email
            $adminStatus = $results['admin'] ? 'sent' : 'failed';
            $adminError = $results['admin'] ? null : 'Email sending failed';
            logAdminNotificationEmail(ADMIN_EMAIL, FROM_EMAIL, $adminSubject, $adminHtml, $orderId, $adminStatus, $adminError);
            
            if ($results['admin']) {
                error_log("Admin notification email sent successfully for order: " . $orderId);
            } else {
                error_log("Failed to send admin notification email for order: " . $orderId);
            }
        }
        
        return $results;
        
    } catch (Exception $e) {
        error_log("Error sending order confirmation emails: " . $e->getMessage());
        return false;
    }
} 