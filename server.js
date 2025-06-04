// server_mysql.js - Node.js server with MySQL database for WhimsicalFrog e-commerce site
const express = require('express');
const cors = require('cors');
const mysql = require('mysql2/promise');
const bodyParser = require('body-parser');

// Create Express app
const app = express();

// Middleware
app.use(cors());
app.use(bodyParser.json());
app.use(bodyParser.urlencoded({ extended: true }));

// MySQL connection pool configuration
const pool = mysql.createPool({
  host: 'db5017975223.hosting-data.io',
  port: 3306,
  database: 'dbs14295502',
  user: 'dbu2826619',
  password: 'Palz2516!',
  waitForConnections: true,
  connectionLimit: 10,
  queueLimit: 0
});

// Test database connection on startup
async function testConnection() {
  try {
    const connection = await pool.getConnection();
    console.log('MySQL database connected successfully!');
    connection.release();
    return true;
  } catch (error) {
    console.error('Error connecting to MySQL database:', error.message);
    return false;
  }
}

// API Routes

// GET /api/products - Fetch all products
app.get('/api/products', async (req, res) => {
  try {
    // First get the column names to match the Google Sheets format
    const [columns] = await pool.query(`
      SELECT COLUMN_NAME 
      FROM INFORMATION_SCHEMA.COLUMNS 
      WHERE TABLE_SCHEMA = 'dbs14295502' 
      AND TABLE_NAME = 'products'
      ORDER BY ORDINAL_POSITION
    `);
    
    // Extract column names as array
    const headers = columns.map(col => col.COLUMN_NAME);
    
    // Get all products
    const [rows] = await pool.query('SELECT * FROM products');
    
    // Format data to match Google Sheets format (array of arrays)
    const result = [
      headers,
      ...rows.map(row => headers.map(header => row[header] !== undefined ? row[header] : ''))
    ];
    
    res.json(result);
  } catch (error) {
    console.error('Error fetching products:', error);
    res.status(500).json({ error: 'Failed to fetch products' });
  }
});

// GET /api/inventory - Fetch all inventory
app.get('/api/inventory', async (req, res) => {
  try {
    // First get the column names to match the Google Sheets format
    const [columns] = await pool.query(`
      SELECT COLUMN_NAME 
      FROM INFORMATION_SCHEMA.COLUMNS 
      WHERE TABLE_SCHEMA = 'dbs14295502' 
      AND TABLE_NAME = 'inventory'
      ORDER BY ORDINAL_POSITION
    `);
    
    // Extract column names as array
    const headers = columns.map(col => col.COLUMN_NAME);
    
    // Get all inventory items
    const [rows] = await pool.query('SELECT * FROM inventory');
    
    // Format data to match Google Sheets format (array of arrays)
    const result = [
      headers,
      ...rows.map(row => headers.map(header => row[header] !== undefined ? row[header] : ''))
    ];
    
    res.json(result);
  } catch (error) {
    console.error('Error fetching inventory:', error);
    res.status(500).json({ error: 'Failed to fetch inventory' });
  }
});

// POST /api/products - Add or update a product
app.post('/api/products', async (req, res) => {
  try {
    const product = req.body;
    
    // Validate required fields
    if (!product.ProductID || !product.ProductName) {
      return res.status(400).json({ error: 'ProductID and ProductName are required' });
    }
    
    // Check if product exists
    const [existing] = await pool.query('SELECT * FROM products WHERE ProductID = ?', [product.ProductID]);
    
    if (existing.length > 0) {
      // Update existing product
      const result = await pool.query(
        `UPDATE products SET 
          ProductName = ?,
          ProductType = ?,
          BasePrice = ?,
          Description = ?,
          DefaultSKU_Base = ?,
          Supplier = ?,
          Notes = ?,
          Image = ?
        WHERE ProductID = ?`,
        [
          product.ProductName,
          product.ProductType || null,
          product.BasePrice || null,
          product.Description || null,
          product.DefaultSKU_Base || null,
          product.Supplier || null,
          product.Notes || null,
          product.Image || null,
          product.ProductID
        ]
      );
      
      res.json({ message: 'Product updated successfully', productId: product.ProductID });
    } else {
      // Insert new product
      const result = await pool.query(
        `INSERT INTO products 
          (ProductID, ProductName, ProductType, BasePrice, Description, DefaultSKU_Base, Supplier, Notes, Image)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)`,
        [
          product.ProductID,
          product.ProductName,
          product.ProductType || null,
          product.BasePrice || null,
          product.Description || null,
          product.DefaultSKU_Base || null,
          product.Supplier || null,
          product.Notes || null,
          product.Image || null
        ]
      );
      
      res.json({ message: 'Product added successfully', productId: product.ProductID });
    }
  } catch (error) {
    console.error('Error adding/updating product:', error);
    res.status(500).json({ error: 'Failed to add/update product' });
  }
});

// POST /api/inventory - Add or update an inventory item
app.post('/api/inventory', async (req, res) => {
  try {
    const item = req.body;
    
    // Validate required fields
    if (!item.InventoryID || !item.ProductID) {
      return res.status(400).json({ error: 'InventoryID and ProductID are required' });
    }
    
    // Check if product exists (foreign key constraint)
    const [productExists] = await pool.query('SELECT 1 FROM products WHERE ProductID = ?', [item.ProductID]);
    
    if (productExists.length === 0) {
      return res.status(400).json({ error: 'Referenced ProductID does not exist' });
    }
    
    // Check if inventory item exists
    const [existing] = await pool.query('SELECT * FROM inventory WHERE InventoryID = ?', [item.InventoryID]);
    
    if (existing.length > 0) {
      // Update existing inventory item
      const result = await pool.query(
        `UPDATE inventory SET 
          ProductID = ?,
          ProductName = ?,
          Category = ?,
          Description = ?,
          SKU = ?,
          StockLevel = ?,
          ReorderPoint = ?,
          ImageURL = ?
        WHERE InventoryID = ?`,
        [
          item.ProductID,
          item.ProductName || null,
          item.Category || null,
          item.Description || null,
          item.SKU || null,
          item.StockLevel || 0,
          item.ReorderPoint || 5,
          item.ImageURL || null,
          item.InventoryID
        ]
      );
      
      res.json({ message: 'Inventory item updated successfully', inventoryId: item.InventoryID });
    } else {
      // Insert new inventory item
      const result = await pool.query(
        `INSERT INTO inventory 
          (InventoryID, ProductID, ProductName, Category, Description, SKU, StockLevel, ReorderPoint, ImageURL)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)`,
        [
          item.InventoryID,
          item.ProductID,
          item.ProductName || null,
          item.Category || null,
          item.Description || null,
          item.SKU || null,
          item.StockLevel || 0,
          item.ReorderPoint || 5,
          item.ImageURL || null
        ]
      );
      
      res.json({ message: 'Inventory item added successfully', inventoryId: item.InventoryID });
    }
  } catch (error) {
    console.error('Error adding/updating inventory item:', error);
    res.status(500).json({ error: 'Failed to add/update inventory item' });
  }
});

// DELETE /api/products/:id - Delete a product
app.delete('/api/products/:id', async (req, res) => {
  try {
    const productId = req.params.id;
    
    // Delete the product (inventory items will be deleted via ON DELETE CASCADE)
    const [result] = await pool.query('DELETE FROM products WHERE ProductID = ?', [productId]);
    
    if (result.affectedRows > 0) {
      res.json({ message: 'Product deleted successfully', productId });
    } else {
      res.status(404).json({ error: 'Product not found' });
    }
  } catch (error) {
    console.error('Error deleting product:', error);
    res.status(500).json({ error: 'Failed to delete product' });
  }
});

// DELETE /api/inventory/:id - Delete an inventory item
app.delete('/api/inventory/:id', async (req, res) => {
  try {
    const inventoryId = req.params.id;
    
    const [result] = await pool.query('DELETE FROM inventory WHERE InventoryID = ?', [inventoryId]);
    
    if (result.affectedRows > 0) {
      res.json({ message: 'Inventory item deleted successfully', inventoryId });
    } else {
      res.status(404).json({ error: 'Inventory item not found' });
    }
  } catch (error) {
    console.error('Error deleting inventory item:', error);
    res.status(500).json({ error: 'Failed to delete inventory item' });
  }
});

// GET /api/health - Health check endpoint
app.get('/api/health', async (req, res) => {
  try {
    // Check database connection
    const connection = await pool.getConnection();
    connection.release();
    
    res.json({
      status: 'healthy',
      database: 'connected',
      message: 'System is operating normally'
    });
  } catch (error) {
    res.status(500).json({
      status: 'unhealthy',
      database: 'disconnected',
      message: error.message
    });
  }
});

// Start the server
const port = process.env.PORT || 3000;
app.listen(port, async () => {
  const dbConnected = await testConnection();
  console.log(`Server running on port ${port}`);
  console.log(`Database status: ${dbConnected ? 'Connected' : 'Connection failed'}`);
  console.log('API endpoints available:');
  console.log('- GET /api/products - Fetch all products');
  console.log('- GET /api/inventory - Fetch all inventory');
  console.log('- POST /api/products - Add or update a product');
  console.log('- POST /api/inventory - Add or update an inventory item');
  console.log('- DELETE /api/products/:id - Delete a product');
  console.log('- DELETE /api/inventory/:id - Delete an inventory item');
  console.log('- GET /api/health - Health check endpoint');
});

// Handle graceful shutdown
process.on('SIGTERM', async () => {
  console.log('SIGTERM received, shutting down gracefully');
  await pool.end();
  process.exit(0);
});

process.on('SIGINT', async () => {
  console.log('SIGINT received, shutting down gracefully');
  await pool.end();
  process.exit(0);
});
