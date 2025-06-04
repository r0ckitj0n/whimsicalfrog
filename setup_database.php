<?php
// setup_database.php - Creates MySQL tables for WhimsicalFrog e-commerce site

// Database connection configuration
$host_name = 'db5017975223.hosting-data.io';
$database = 'dbs14295502';
$user_name = 'dbu2826619';
$password = 'Palz2516!';

// Create connection
$conn = new mysqli($host_name, $user_name, $password, $database);

// Check connection
if ($conn->connect_error) {
    die('<p style="color: red; font-weight: bold;">Failed to connect to MySQL: ' . $conn->connect_error . '</p>');
}
echo '<p style="color: green; font-weight: bold;">Connected successfully to MySQL database!</p>';

// SQL statements to create tables
$createProductsTable = "
CREATE TABLE IF NOT EXISTS products (
    ProductID VARCHAR(20) PRIMARY KEY,
    ProductName VARCHAR(100) NOT NULL,
    ProductType VARCHAR(50),
    BasePrice DECIMAL(10,2),
    Description TEXT,
    DefaultSKU_Base VARCHAR(50),
    Supplier VARCHAR(100),
    Notes TEXT,
    Image VARCHAR(255),
    CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UpdatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_product_type (ProductType),
    INDEX idx_product_name (ProductName)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

$createInventoryTable = "
CREATE TABLE IF NOT EXISTS inventory (
    InventoryID VARCHAR(20) PRIMARY KEY,
    ProductID VARCHAR(20) NOT NULL,
    ProductName VARCHAR(100),
    Category VARCHAR(50),
    Description TEXT,
    SKU VARCHAR(50),
    StockLevel INT DEFAULT 0,
    ReorderPoint INT DEFAULT 5,
    ImageURL VARCHAR(255),
    CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UpdatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (ProductID) REFERENCES products(ProductID) ON DELETE CASCADE,
    INDEX idx_category (Category),
    INDEX idx_sku (SKU),
    INDEX idx_stock (StockLevel)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

// Create products table
echo '<p>Creating products table...</p>';
if ($conn->query($createProductsTable) === TRUE) {
    echo '<p style="color: green;">Products table created successfully!</p>';
} else {
    echo '<p style="color: red;">Error creating products table: ' . $conn->error . '</p>';
}

// Create inventory table
echo '<p>Creating inventory table...</p>';
if ($conn->query($createInventoryTable) === TRUE) {
    echo '<p style="color: green;">Inventory table created successfully!</p>';
} else {
    echo '<p style="color: red;">Error creating inventory table: ' . $conn->error . '</p>';
}

// Close connection
$conn->close();
echo '<p>Database connection closed.</p>';
echo '<p style="color: green; font-weight: bold;">Database setup completed!</p>';
?>

<style>
    body {
        font-family: Arial, sans-serif;
        line-height: 1.6;
        margin: 20px;
    }
    p {
        margin: 10px 0;
    }
</style>
