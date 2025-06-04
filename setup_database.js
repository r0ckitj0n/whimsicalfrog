// setup_database.js - Creates MySQL tables for WhimsicalFrog e-commerce site
const mysql = require('mysql2/promise');

// MySQL connection configuration
const dbConfig = {
  host: 'db5017975223.hosting-data.io',
  port: 3306,
  database: 'dbs14295502',
  user: 'dbu2826619',
  password: 'Palz2516!',
  waitForConnections: true,
  connectionLimit: 10,
  queueLimit: 0
};

// SQL statements to create tables
const createProductsTable = `
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
`;

const createInventoryTable = `
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
`;

// Function to set up the database
async function setupDatabase() {
  let connection;
  
  try {
    console.log('Connecting to MySQL database...');
    connection = await mysql.createConnection(dbConfig);
    console.log('Connected successfully to MySQL database!');
    
    // Create products table
    console.log('Creating products table...');
    await connection.execute(createProductsTable);
    console.log('Products table created successfully!');
    
    // Create inventory table
    console.log('Creating inventory table...');
    await connection.execute(createInventoryTable);
    console.log('Inventory table created successfully!');
    
    console.log('Database setup completed successfully!');
  } catch (error) {
    console.error('Error setting up database:', error.message);
    if (error.code === 'ER_ACCESS_DENIED_ERROR') {
      console.error('Access denied. Please check your username and password.');
    } else if (error.code === 'ECONNREFUSED') {
      console.error('Connection refused. Please check if the database server is running and the host/port are correct.');
    } else if (error.code === 'ER_BAD_DB_ERROR') {
      console.error('Database does not exist. Please check the database name.');
    }
  } finally {
    if (connection) {
      await connection.end();
      console.log('Database connection closed.');
    }
  }
}

// Run the setup
setupDatabase();
