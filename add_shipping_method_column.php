<?php
// Migration script to add shippingMethod column to orders table
require_once __DIR__ . '/api/config.php';

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    
    // Check if shippingMethod column already exists
    $stmt = $pdo->query("SHOW COLUMNS FROM orders LIKE 'shippingMethod'");
    $columnExists = $stmt->fetch();
    
    if (!$columnExists) {
        // Add the shippingMethod column
        $sql = "ALTER TABLE orders ADD COLUMN shippingMethod VARCHAR(50) NOT NULL DEFAULT 'Standard' AFTER paymentMethod";
        $pdo->exec($sql);
        echo "✅ Successfully added shippingMethod column to orders table.\n";
        
        // Update existing orders to have Standard shipping method
        $updateSql = "UPDATE orders SET shippingMethod = 'Standard' WHERE shippingMethod IS NULL OR shippingMethod = ''";
        $pdo->exec($updateSql);
        echo "✅ Updated existing orders with default shipping method.\n";
        
    } else {
        echo "ℹ️ shippingMethod column already exists in orders table.\n";
    }
    
    echo "\n🎉 Migration completed successfully!\n";
    echo "\nNew Order ID format will be: [CustomerNum]-[YYMMDD]-[ShippingCode]-[RandomNum]\n";
    echo "Examples:\n";
    echo "  C001-250609-STD-123 (Customer 1, June 9 2025, Standard shipping)\n";
    echo "  C002-250610-EXP-456 (Customer 2, June 10 2025, Express shipping)\n";
    echo "  C003-250611-PUP-789 (Customer 3, June 11 2025, Pickup)\n";
    echo "\nShipping Method Codes:\n";
    echo "  STD = Standard Shipping\n";
    echo "  EXP = Express Shipping\n";
    echo "  OVN = Overnight Shipping\n";
    echo "  PUP = Store Pickup\n";
    echo "  LOC = Local Delivery\n";
    
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?> 