const express = require('express');
const cors = require('cors');
const axios = require('axios');
require('dotenv').config();
const mysql = require('mysql2/promise');
const multer = require('multer');
const path = require('path');
const fs = require('fs');

const app = express();
const port = 3000;

// reCAPTCHA configuration
const RECAPTCHA_SECRET_KEY = '6LdqsUUrAAAAABI48QuguVOtB3PqD4uiCQT36MZ9';

// MySQL connection config
const isProduction = process.env.NODE_ENV === 'production' || process.env.HOSTNAME === 'whimsicalfrog.us';

const dbConfig = isProduction ? {
  host: process.env.MYSQL_HOST_REMOTE || 'db5017975223.hosting-data.io',
  user: process.env.MYSQL_USER_REMOTE || 'dbu2826619',
  password: process.env.MYSQL_PASS_REMOTE || 'Palz2516',
  database: process.env.MYSQL_DB_REMOTE || 'dbs14295502',
  port: 3306
} : {
  host: process.env.MYSQL_HOST_LOCAL || 'localhost',
  user: process.env.MYSQL_USER_LOCAL || 'root',
  password: process.env.MYSQL_PASS_LOCAL || 'Palz2516',
  database: process.env.MYSQL_DB_LOCAL || 'whimsicalfrog',
  port: 3306
};

app.use(cors());
app.use(express.json());

// Multer setup for image uploads (store in images/<category>)
const allowedCategories = ['products', 'artwork', 'tumblers', 'tshirts', 'sublimation', 'windowwraps', 'avatars', 'misc'];
const upload = multer({
    storage: multer.diskStorage({
        destination: function (req, file, cb) {
            // Always save to a temp folder first
            const dir = path.join(__dirname, 'images', 'temp');
            if (!fs.existsSync(dir)) {
                fs.mkdirSync(dir, { recursive: true });
            }
            cb(null, dir);
        },
        filename: function (req, file, cb) {
            // Use a unique temp name
            const ext = path.extname(file.originalname);
            cb(null, `${Date.now()}_${Math.round(Math.random() * 1E9)}${ext}`);
        }
    }),
    limits: { fileSize: 10 * 1024 * 1024 }, // 10MB limit
    fileFilter: (req, file, cb) => {
        const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!allowedTypes.includes(file.mimetype)) {
            return cb(new Error('Only image files are allowed!'), false);
        }
        cb(null, true);
    }
});

function categoryPrefix(body) {
    if (body.category === 'products') return 'product_';
    if (body.category === 'artwork') return 'artwork_';
    if (body.category === 'tumblers') return 'tumbler_';
    if (body.category === 'tshirts') return 'tshirt_';
    if (body.category === 'sublimation') return 'sublimation_';
    if (body.category === 'windowwraps') return 'windowwrap_';
    if (body.category === 'avatars') return 'avatar_';
    return 'misc_';
}

// --- Simple admin authentication middleware (replace with real auth as needed) ---
function isAdminAuthenticated(req, res, next) {
    // Placeholder: check for a custom header (replace with real session/JWT check)
    if (req.headers['x-admin-auth'] === 'secret-admin-key') {
        return next();
    }
    return res.status(401).json({ error: 'Unauthorized: Admin access required' });
}

// Example: Login endpoint (MySQL version)
app.post('/api/login', async (req, res) => {
    try {
        const { username, password } = req.body;
        if (!username || !password) {
            return res.status(400).json({ error: 'Username and password are required' });
        }
        const connection = await mysql.createConnection(dbConfig);
        const [rows] = await connection.execute(
            'SELECT * FROM users WHERE username = ? AND password = ?',
            [username, password]
        );
        await connection.end();
        if (rows.length === 0) {
            return res.status(401).json({ error: 'Invalid username or password' });
        }
        const user = rows[0];
        res.json({
            userId: user.id,
            username: user.username,
            email: user.email,
            role: user.role,
            roleType: user.roleType,
            firstName: user.firstName || '',
            lastName: user.lastName || ''
        });
    } catch (error) {
        console.error('Login error:', error);
        res.status(500).json({ error: 'Login failed', details: error.message });
    }
});

// Registration endpoint (MySQL version)
app.post('/api/register', async (req, res) => {
    try {
        const {
            firstName,
            lastName,
            username,
            email,
            password,
            phoneNumber,
            addressLine1,
            addressLine2,
            city,
            state,
            zipCode
        } = req.body;
        // Only require username, password, firstName, lastName
        if (!username || !password || !firstName || !lastName) {
            return res.status(400).json({ error: 'Missing required fields' });
        }
        const connection = await mysql.createConnection(dbConfig);
        // Check if username or email already exists
        const [existing] = await connection.execute(
            'SELECT * FROM users WHERE username = ? OR email = ?',
            [username, email]
        );
        if (existing.length > 0) {
            await connection.end();
            return res.status(400).json({ error: 'Username or email already exists' });
        }
        // Insert new user
        await connection.execute(
            'INSERT INTO users (id, username, password, email, role, roleType, firstName, lastName, phoneNumber, addressLine1, addressLine2, city, state, zipCode) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                'U' + Math.random().toString(36).substr(2, 8),
                username,
                password,
                email || '',
                'Customer',
                'Customer',
                firstName,
                lastName,
                phoneNumber || '',
                addressLine1 || '',
                addressLine2 || '',
                city || '',
                state || '',
                zipCode || ''
            ]
        );
        await connection.end();
        res.json({ message: 'Registration successful' });
    } catch (error) {
        console.error('Registration error:', error);
        res.status(500).json({ error: 'Registration failed', details: error.message });
    }
});

// Get all users (customers and admins) with snake_case fields for PHP compatibility
app.get('/api/users', async (req, res) => {
    try {
        const connection = await mysql.createConnection(dbConfig);
        const [rows] = await connection.execute('SELECT * FROM users');
        await connection.end();
        // Map camelCase to snake_case for PHP compatibility
        const users = rows.map(user => ({
            id: user.id,
            username: user.username,
            email: user.email,
            role: user.role,
            roleType: user.roleType,
            first_name: user.firstName || '',
            last_name: user.lastName || '',
            phone_number: user.phoneNumber || '',
            address_line1: user.addressLine1 || '',
            address_line2: user.addressLine2 || '',
            city: user.city || '',
            state: user.state || '',
            zip_code: user.zipCode || ''
        }));
        res.json(users);
    } catch (error) {
        console.error('Error fetching users:', error);
        res.status(500).json({ error: 'Failed to fetch users', details: error.message });
    }
});

// Improved Update user details endpoint
app.post('/api/update-user', async (req, res) => {
    try {
        // Accept both camelCase and snake_case
        const body = req.body;
        const userId = body.userId || body.id;
        console.log('Update user request body:', body);
        if (!userId) {
            return res.status(400).json({ error: 'Missing userId' });
        }
        // Build update query dynamically for only provided fields
        const fieldMap = {
            firstName: body.firstName ?? body.first_name,
            lastName: body.lastName ?? body.last_name,
            email: body.email,
            username: body.username,
            password: body.password,
            phoneNumber: body.phoneNumber ?? body.phone_number,
            addressLine1: body.addressLine1 ?? body.address_line1,
            addressLine2: body.addressLine2 ?? body.address_line2,
            city: body.city,
            state: body.state,
            zipCode: body.zipCode ?? body.zip_code,
            role: body.role
        };
        let fields = [];
        let values = [];
        for (const [key, value] of Object.entries(fieldMap)) {
            if (typeof value !== 'undefined' && value !== null) {
                fields.push(`${key} = ?`);
                values.push(value);
            }
        }
        console.log('Fields to update:', fields);
        console.log('Values:', values);
        if (fields.length === 0) {
            return res.status(400).json({ error: 'No fields to update' });
        }
        values.push(userId);
        const sql = `UPDATE users SET ${fields.join(', ')} WHERE id = ?`;
        console.log('SQL:', sql);
        const connection = await mysql.createConnection(dbConfig);
        const [result] = await connection.execute(sql, values);
        await connection.end();
        if (result.affectedRows > 0) {
            res.json({ success: true });
        } else {
            res.status(404).json({ error: 'User not found or no changes made' });
        }
    } catch (error) {
        console.error('Update user error:', error);
        res.status(500).json({ error: 'Failed to update user', details: error.message });
    }
});

// --- Add Order Endpoint ---
app.post('/api/add-order', async (req, res) => {
    const connection = await mysql.createConnection(dbConfig);
    try {
        console.log('Received new order request:', req.body);
        const {
            userId,
            items,
            total,
            paymentMethod,
            shippingAddress
        } = req.body;

        // 1. Basic Validation
        if (!userId || !items || !Array.isArray(items) || items.length === 0 || !total || !paymentMethod) {
            console.error('Validation Error: Missing required order fields.');
            return res.status(400).json({
                error: 'Missing required fields. Required: userId, items, total, paymentMethod.'
            });
        }

        await connection.beginTransaction();
        console.log('Database transaction started.');

        // 2. Insert into `orders` table
        const orderId = 'O' + Date.now().toString().slice(-8);
        const orderSql = `
            INSERT INTO orders (id, userId, total, paymentMethod, shippingAddress, status)
            VALUES (?, ?, ?, ?, ?, ?)
        `;
        const orderValues = [
            orderId,
            userId,
            total,
            paymentMethod,
            JSON.stringify(shippingAddress || {}),
            'Pending'
        ];
        console.log('Inserting into orders:', orderValues);
        await connection.execute(orderSql, orderValues);

        // 3. Insert into `order_items` table
        const itemSql = `
            INSERT INTO order_items (id, orderId, productId, quantity, price)
            VALUES (?, ?, ?, ?, ?)
        `;
        for (const item of items) {
            if (!item.id || !item.quantity || !item.price) {
                console.error('Validation Error: Invalid item in order.', item);
                throw new Error('Invalid item data. Each item must have id, quantity, and price.');
            }
            // Generate streamlined order item ID
            const itemCountQuery = 'SELECT COUNT(*) as count FROM order_items';
            const [countRows] = await connection.execute(itemCountQuery);
            const itemCount = countRows[0].count;
            const itemSequence = String(itemCount + 1).padStart(3, '0');
            const itemId = 'OI' + itemSequence;
            const itemValues = [itemId, orderId, item.id, item.quantity, item.price];
            console.log('Inserting into order_items:', itemValues);
            await connection.execute(itemSql, itemValues);
        }

        // 4. (Optional) Update inventory - Throws error if any item fails
        const inventorySql = 'UPDATE inventory SET stockLevel = stockLevel - ? WHERE productId = ? AND stockLevel >= ?';
        for (const item of items) {
            console.log(`Updating inventory for productId: ${item.id}, reducing by ${item.quantity}`);
            const [result] = await connection.execute(inventorySql, [item.quantity, item.id, item.quantity]);
            if (result.affectedRows === 0) {
                console.error(`Inventory update failed for productId: ${item.id}. Not enough stock.`);
                throw new Error(`Insufficient stock for product ID ${item.id}.`);
            }
        }

        await connection.commit();
        console.log('Transaction committed successfully.');

        res.status(201).json({
            success: true,
            orderId: orderId,
            message: 'Order placed successfully'
        });

    } catch (error) {
        await connection.rollback();
        console.error('Error adding order:', error.message);
        console.error('Stack Trace:', error.stack);
        res.status(500).json({
            error: 'Failed to add order',
            details: error.message
        });
    } finally {
        if (connection) {
            await connection.end();
            console.log('Database connection closed.');
        }
    }
});

// --- GET all products endpoint ---
app.get('/api/products', async (req, res) => {
    try {
        const connection = await mysql.createConnection(dbConfig);
        const [rows] = await connection.execute('SELECT * FROM products');
        await connection.end();
        // Add a 'price' field for frontend compatibility (alias for basePrice)
        // and ensure the price is always a number, defaulting to 0 if null.
        // This triggers a redeploy on IONOS.
        const products = rows.map(product => ({
            ...product,
            price: Number(product.basePrice) || 0
        }));
        res.json(products);
    } catch (error) {
        console.error('Error fetching products:', error);
        res.status(500).json({ error: 'Failed to fetch products', details: error.message });
    }
});

// --- GET all inventory endpoint ---
app.get('/api/inventory', async (req, res) => {
    try {
        const connection = await mysql.createConnection(dbConfig);
        const [rows] = await connection.execute('SELECT * FROM inventory');
        await connection.end();
        res.json(rows);
    } catch (error) {
        console.error('Error fetching inventory:', error);
        res.status(500).json({ error: 'Failed to fetch inventory', details: error.message });
    }
});

// --- Upload image endpoint (category-based, secure file type/size, no auth) ---
app.post('/api/upload-image', upload.single('image'), async (req, res) => {
    console.log('--- /api/upload-image called ---');
    console.log('req.body:', req.body);
    console.log('req.file:', req.file);
    let category = (req.body.category || 'misc').toLowerCase().trim();
    const bodyKeys = Object.keys(req.body);
    console.log('req.body keys:', bodyKeys);
    // Robust productId check (case-insensitive, all variants)
    let productId = req.body.productId || req.body.ProductId || req.body.PRODUCTID || req.body['productid'] || req.body['PRODUCTID'] || null;
    let id = productId || req.body.artworkId || req.body.tumblerId || req.body.tshirtId || req.body.sublimationId || req.body.windowwrapId || req.body.userId || req.body.miscId || 'unknown';
    console.log('Category:', category, 'ID:', id);
    try {
        if (!id) {
            console.error('Missing ID for category:', category);
            return res.status(400).json({ error: 'Missing ID for category' });
        }
        if (!req.file) {
            console.error('Missing image file');
            return res.status(400).json({ error: 'Missing image file' });
        }
        const ext = path.extname(req.file.originalname);
        const destDir = path.join(__dirname, 'images', category);
        if (!fs.existsSync(destDir)) {
            fs.mkdirSync(destDir, { recursive: true });
        }
        let destFilename;
        if (productId) {
            // Fetch product name from DB for filename
            const connection = await mysql.createConnection(dbConfig);
            const [rows] = await connection.execute('SELECT name FROM products WHERE id = ?', [id]);
            let productName = rows.length > 0 ? rows[0].name : 'unknown';
            await connection.end();
            // Sanitize product name for filename
            const sanitizedProductName = productName.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
            destFilename = `product_${sanitizedProductName}${ext}`;
            console.log('Product name for image:', productName);
            console.log('Sanitized filename:', destFilename);
        } else {
            destFilename = `product_unknown${ext}`;
            console.error('No productId found in req.body, fallback filename used:', destFilename);
        }
        console.log('Final destFilename:', destFilename);
        const destPath = path.join(destDir, destFilename);
        fs.renameSync(req.file.path, destPath);
        const imagePath = `images/${category}/${destFilename}`;
        console.log('Final imagePath for DB:', imagePath);
        // Update the correct table/field in DB based on category
        const connection = await mysql.createConnection(dbConfig);
        if (category === 'products' && productId) {
            await connection.execute('UPDATE products SET image = ? WHERE id = ?', [imagePath, id]);
        } else if (category === 'artwork' && req.body.artworkId) {
            await connection.execute('UPDATE artwork SET image = ? WHERE id = ?', [imagePath, id]);
        } // Add more categories as needed
        await connection.end();
        console.log('Image uploaded and DB updated:', imagePath);
        res.json({ success: true, image: imagePath });
    } catch (error) {
        let msg = error.message || 'Failed to upload image';
        console.error('Image upload error:', msg, error);
        if (msg.includes('Only image files are allowed')) {
            return res.status(400).json({ error: msg });
        }
        if (msg.includes('File too large')) {
            return res.status(400).json({ error: 'File too large (max 10MB allowed)' });
        }
        res.status(500).json({ error: 'Failed to upload image', details: msg });
    }
});

app.post('/api/update-product', async (req, res) => {
    try {
        const { id, name, basePrice, description, productType, defaultSKU_Base } = req.body;

        if (!id) {
            return res.status(400).json({ error: 'Product ID is required' });
        }

        const connection = await mysql.createConnection(dbConfig);

        await connection.execute(
            'UPDATE products SET name = ?, basePrice = ?, description = ?, productType = ?, defaultSKU_Base = ? WHERE id = ?',
            [name, basePrice, description, productType, defaultSKU_Base, id]
        );

        await connection.end();

        res.json({ success: true, message: 'Product updated successfully' });
    } catch (error) {
        console.error('Error updating product:', error);
        res.status(500).json({ error: 'Failed to update product', details: error.message });
    }
});

// --- Update inventory endpoint ---
app.post('/api/update-inventory', async (req, res) => {
    try {
        const { inventoryId, field, value } = req.body;

        if (!inventoryId) {
            return res.status(400).json({ error: 'Inventory ID is required' });
        }

        if (!field || value === undefined) {
            return res.status(400).json({ error: 'Field and value are required' });
        }

        // Validate field name to prevent SQL injection
        const allowedFields = ['productId', 'sku', 'stockLevel', 'reorderPoint', 'description'];
        if (!allowedFields.includes(field)) {
            return res.status(400).json({ error: 'Invalid field name' });
        }

        const connection = await mysql.createConnection(dbConfig);

        const sql = `UPDATE inventory SET ${field} = ? WHERE id = ?`;
        const [result] = await connection.execute(sql, [value, inventoryId]);

        await connection.end();

        if (result.affectedRows > 0) {
            res.json({ success: true, message: 'Inventory updated successfully' });
        } else {
            res.status(404).json({ error: 'Inventory item not found or no changes made' });
        }
    } catch (error) {
        console.error('Error updating inventory:', error);
        res.status(500).json({ error: 'Failed to update inventory', details: error.message });
    }
});

// --- Add inventory endpoint ---
app.post('/api/add-inventory', async (req, res) => {
    try {
        const { productId, productName, description, sku, stockLevel, reorderPoint } = req.body;

        if (!productId) {
            return res.status(400).json({ error: 'Product ID is required' });
        }

        const connection = await mysql.createConnection(dbConfig);

        // Check if product exists
        const [products] = await connection.execute('SELECT id FROM products WHERE id = ?', [productId]);
        if (products.length === 0) {
            await connection.end();
            return res.status(404).json({ error: 'Product not found' });
        }

        // Generate a unique inventory ID
        const inventoryId = 'INV' + Math.random().toString(36).substr(2, 8);

        // Insert new inventory item
        await connection.execute(
            'INSERT INTO inventory (id, productId, sku, stockLevel, reorderPoint, description) VALUES (?, ?, ?, ?, ?, ?)',
            [
                inventoryId,
                productId,
                sku || `SKU-${productId}`,
                stockLevel || 0,
                reorderPoint || 5,
                description || `Inventory for ${productName || productId}`
            ]
        );

        await connection.end();

        res.status(201).json({
            success: true,
            inventoryId: inventoryId,
            message: 'Inventory item added successfully'
        });
    } catch (error) {
        console.error('Error adding inventory:', error);
        res.status(500).json({ error: 'Failed to add inventory', details: error.message });
    }
});

// --- Delete inventory endpoint ---
app.post('/api/delete-inventory', async (req, res) => {
    try {
        const { inventoryId } = req.body;

        if (!inventoryId) {
            return res.status(400).json({ error: 'Inventory ID is required' });
        }

        const connection = await mysql.createConnection(dbConfig);

        // Delete inventory item
        const [result] = await connection.execute('DELETE FROM inventory WHERE id = ?', [inventoryId]);

        await connection.end();

        if (result.affectedRows > 0) {
            res.json({ success: true, message: 'Inventory item deleted successfully' });
        } else {
            res.status(404).json({ error: 'Inventory item not found' });
        }
    } catch (error) {
        console.error('Error deleting inventory:', error);
        res.status(500).json({ error: 'Failed to delete inventory', details: error.message });
    }
});

// --- Get inventory costs endpoint ---
app.get('/api/inventory-costs/:inventoryId', async (req, res) => {
    try {
        const { inventoryId } = req.params;
        
        if (!inventoryId) {
            return res.status(400).json({ error: 'Inventory ID is required' });
        }
        
        const connection = await mysql.createConnection(dbConfig);
        
        // Get materials costs
        const [materials] = await connection.execute(
            'SELECT * FROM inventory_materials WHERE inventoryId = ?',
            [inventoryId]
        );
        
        // Get labor costs
        const [labor] = await connection.execute(
            'SELECT * FROM inventory_labor WHERE inventoryId = ?',
            [inventoryId]
        );
        
        // Get energy costs
        const [energy] = await connection.execute(
            'SELECT * FROM inventory_energy WHERE inventoryId = ?',
            [inventoryId]
        );
        
        await connection.end();
        
        // Calculate totals
        const materialsTotal = materials.reduce((sum, item) => sum + parseFloat(item.cost), 0);
        const laborTotal = labor.reduce((sum, item) => sum + parseFloat(item.cost), 0);
        const energyTotal = energy.reduce((sum, item) => sum + parseFloat(item.cost), 0);
        const grandTotal = materialsTotal + laborTotal + energyTotal;
        
        res.json({
            materials,
            labor,
            energy,
            totals: {
                materials: materialsTotal,
                labor: laborTotal,
                energy: energyTotal,
                grand: grandTotal
            }
        });
    } catch (error) {
        console.error('Error fetching inventory costs:', error);
        res.status(500).json({ error: 'Failed to fetch inventory costs', details: error.message });
    }
});

// --- Add cost item endpoint ---
app.post('/api/add-cost', async (req, res) => {
    try {
        const { type, inventoryId, data } = req.body;
        
        if (!type || !inventoryId || !data) {
            return res.status(400).json({ error: 'Type, inventoryId, and data are required' });
        }
        
        // Validate cost type
        if (!['material', 'labor', 'energy'].includes(type)) {
            return res.status(400).json({ error: 'Invalid cost type. Must be material, labor, or energy' });
        }
        
        const connection = await mysql.createConnection(dbConfig);
        
        let result;
        
        // Insert based on cost type - simplified to match actual schema
        switch (type) {
            case 'material':
                // Validate required fields
                if (!data.name || data.cost === undefined) {
                    await connection.end();
                    return res.status(400).json({ error: 'Material name and cost are required' });
                }
                
                // Insert material cost with only the fields that exist in the schema
                [result] = await connection.execute(
                    'INSERT INTO inventory_materials (inventoryId, name, cost) VALUES (?, ?, ?)',
                    [
                        inventoryId,
                        data.name,
                        parseFloat(data.cost)
                    ]
                );
                break;
                
            case 'labor':
                // Validate required fields
                if (!data.description || data.cost === undefined) {
                    await connection.end();
                    return res.status(400).json({ error: 'Labor description and cost are required' });
                }
                
                // Insert labor cost with only the fields that exist in the schema
                [result] = await connection.execute(
                    'INSERT INTO inventory_labor (inventoryId, description, cost) VALUES (?, ?, ?)',
                    [
                        inventoryId,
                        data.description,
                        parseFloat(data.cost)
                    ]
                );
                break;
                
            case 'energy':
                // Validate required fields
                if (!data.description || data.cost === undefined) {
                    await connection.end();
                    return res.status(400).json({ error: 'Energy description and cost are required' });
                }
                
                // Insert energy cost with only the fields that exist in the schema
                [result] = await connection.execute(
                    'INSERT INTO inventory_energy (inventoryId, description, cost) VALUES (?, ?, ?)',
                    [
                        inventoryId,
                        data.description,
                        parseFloat(data.cost)
                    ]
                );
                break;
        }
        
        await connection.end();
        
        res.status(201).json({
            success: true,
            id: result.insertId,
            message: `${type} cost added successfully`
        });
    } catch (error) {
        console.error(`Error adding ${req.body.type} cost:`, error);
        res.status(500).json({ error: `Failed to add ${req.body.type} cost`, details: error.message });
    }
});

// --- Update cost item endpoint ---
app.post('/api/update-cost', async (req, res) => {
    try {
        const { type, id, data } = req.body;
        
        if (!type || !id || !data) {
            return res.status(400).json({ error: 'Type, id, and data are required' });
        }
        
        // Validate cost type
        if (!['material', 'labor', 'energy'].includes(type)) {
            return res.status(400).json({ error: 'Invalid cost type. Must be material, labor, or energy' });
        }
        
        const connection = await mysql.createConnection(dbConfig);
        
        let result;
        
        // Update based on cost type - simplified to match actual schema
        switch (type) {
            case 'material':
                // Build update query dynamically for the fields that exist in the schema
                const materialFields = [];
                const materialValues = [];
                
                if (data.name !== undefined) {
                    materialFields.push('name = ?');
                    materialValues.push(data.name);
                }
                
                if (data.cost !== undefined) {
                    materialFields.push('cost = ?');
                    materialValues.push(parseFloat(data.cost));
                }
                
                if (materialFields.length === 0) {
                    await connection.end();
                    return res.status(400).json({ error: 'No fields to update' });
                }
                
                // Add ID to values array
                materialValues.push(id);
                
                // Execute update query
                [result] = await connection.execute(
                    `UPDATE inventory_materials SET ${materialFields.join(', ')} WHERE id = ?`,
                    materialValues
                );
                break;
                
            case 'labor':
                // Build update query dynamically for the fields that exist in the schema
                const laborFields = [];
                const laborValues = [];
                
                if (data.description !== undefined) {
                    laborFields.push('description = ?');
                    laborValues.push(data.description);
                }
                
                if (data.cost !== undefined) {
                    laborFields.push('cost = ?');
                    laborValues.push(parseFloat(data.cost));
                }
                
                if (laborFields.length === 0) {
                    await connection.end();
                    return res.status(400).json({ error: 'No fields to update' });
                }
                
                // Add ID to values array
                laborValues.push(id);
                
                // Execute update query
                [result] = await connection.execute(
                    `UPDATE inventory_labor SET ${laborFields.join(', ')} WHERE id = ?`,
                    laborValues
                );
                break;
                
            case 'energy':
                // Build update query dynamically for the fields that exist in the schema
                const energyFields = [];
                const energyValues = [];
                
                if (data.description !== undefined) {
                    energyFields.push('description = ?');
                    energyValues.push(data.description);
                }
                
                if (data.cost !== undefined) {
                    energyFields.push('cost = ?');
                    energyValues.push(parseFloat(data.cost));
                }
                
                if (energyFields.length === 0) {
                    await connection.end();
                    return res.status(400).json({ error: 'No fields to update' });
                }
                
                // Add ID to values array
                energyValues.push(id);
                
                // Execute update query
                [result] = await connection.execute(
                    `UPDATE inventory_energy SET ${energyFields.join(', ')} WHERE id = ?`,
                    energyValues
                );
                break;
        }
        
        await connection.end();
        
        if (result.affectedRows > 0) {
            res.json({
                success: true,
                message: `${type} cost updated successfully`
            });
        } else {
            res.status(404).json({ error: `${type} cost not found or no changes made` });
        }
    } catch (error) {
        console.error(`Error updating ${req.body.type} cost:`, error);
        res.status(500).json({ error: `Failed to update ${req.body.type} cost`, details: error.message });
    }
});

// --- Delete cost item endpoint ---
app.post('/api/delete-cost', async (req, res) => {
    try {
        const { type, id } = req.body;
        
        if (!type || !id) {
            return res.status(400).json({ error: 'Type and id are required' });
        }
        
        // Validate cost type
        if (!['material', 'labor', 'energy'].includes(type)) {
            return res.status(400).json({ error: 'Invalid cost type. Must be material, labor, or energy' });
        }
        
        const connection = await mysql.createConnection(dbConfig);
        
        let tableName;
        
        // Determine table name based on cost type
        switch (type) {
            case 'material':
                tableName = 'inventory_materials';
                break;
            case 'labor':
                tableName = 'inventory_labor';
                break;
            case 'energy':
                tableName = 'inventory_energy';
                break;
        }
        
        // Execute delete query
        const [result] = await connection.execute(
            `DELETE FROM ${tableName} WHERE id = ?`,
            [id]
        );
        
        await connection.end();
        
        if (result.affectedRows > 0) {
            res.json({
                success: true,
                message: `${type} cost deleted successfully`
            });
        } else {
            res.status(404).json({ error: `${type} cost not found` });
        }
    } catch (error) {
        console.error(`Error deleting ${req.body.type} cost:`, error);
        res.status(500).json({ error: `Failed to delete ${req.body.type} cost`, details: error.message });
    }
});

app.listen(port, () => {
    console.log(`Server running at http://localhost:${port}`);
});
