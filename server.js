const express = require('express');
const { google } = require('googleapis');
const cors = require('cors');
const axios = require('axios');
require('dotenv').config();

const app = express();
const port = 3000;

// reCAPTCHA configuration
const RECAPTCHA_SECRET_KEY = '6LdqsUUrAAAAABI48QuguVOtB3PqD4uiCQT36MZ9';

// Middleware
app.use(cors());
app.use(express.json());
app.use(express.static('.')); // Serve static files from current directory

// Google Sheets setup
const auth = new google.auth.GoogleAuth({
    keyFile: 'credentials.json',
    scopes: [
        'https://www.googleapis.com/auth/spreadsheets.readonly',
        'https://www.googleapis.com/auth/spreadsheets'
    ],
});

// Test data for each tab
const testData = [
    // Headers
    ['ProductID', 'ProductName', 'ProductType', 'BasePrice', 'Description', 'DefaultSKU_Base', 'Supplier', 'Notes', 'Image'],
    // Products
    ['P001', 'Custom T-Shirt', 'T-Shirts', '24.99', 'High-quality cotton t-shirt for customization', 'TS-BASE', 'Supplier A', 'Available sizes: S, M, L, XL, XXL; Various colors', 'images/frog_tshirt_1.png'],
    ['P002', 'Custom Tumbler (20oz)', 'Tumblers', '19.99', 'Insulated 20oz tumbler for sublimation', 'TUM20-BASE', 'Supplier B', 'Stainless steel, includes lid and straw', 'images/frog_tumbler_1.png'],
    ['P003', 'Custom Tumbler (30oz)', 'Tumblers', '24.99', 'Insulated 30oz tumbler for sublimation', 'TUM30-BASE', 'Supplier B', 'Stainless steel, includes lid and straw', 'images/frog_tumbler_2.png'],
    ['P004', 'Custom Artwork Print', 'Artwork', '49.99', 'Prints of custom digital or hand-drawn artwork', 'ART-PRNT-BASE', 'Supplier C', 'Sizes: 8x10, 11x14, 16x20; Paper/Canvas', 'images/frog_painter_1.png'],
    ['P005', 'Sublimation Blank Item (e.g., Mug)', 'Sublimation', '14.99', 'Blank items ready for sublimation transfer', 'SUB-MUG-BASE', 'Supplier B', 'Specify blank type in description', 'images/frog_mug.png'],
    ['P006', 'Custom Window Wrap', 'Window Wraps', '39.99', 'Vinyl window wraps, custom sizes and designs', 'WW-BASE', 'Supplier D', 'Per sq ft pricing may apply', 'images/frog_windowwrap_1.png'],
    // Users
    ['U001', 'admin', 'admin123', 'admin@whimsicalfrog.com', 'Admin', 'Admin'],
    ['U002', 'customer', 'customer123', 'customer@example.com', 'Customer', 'Customer'],
    // Inventory
    ['I001', 'P001', 'T-Shirt, White, S', 'T-Shirts', 'Color: White, Size: S', 'TS-WHT-S', '50', '10', 'images/frog_tshirt_2.png'],
    ['I002', 'P002', 'Tumbler, 20oz, White', 'Tumblers', 'Color: White (for sublimation)', 'TUM20-WHT', '30', '5', 'images/frog_tumbler_3.png'],
    ['I003', 'P004', 'Artwork Print Blank Canvas 8x10', 'Artwork', 'Material: Canvas, Size: 8x10', 'ART-CAN-810', '20', '5', 'images/frog_painter_2.png'],
    ['I004', 'P006', 'Window Wrap, Small Business', 'Window Wraps', 'Size: 3x4 ft', 'WW-SB-34', '15', '3', 'images/frog_windowwrap_2.png']
];

// Function to populate spreadsheet with test data
async function populateSpreadsheet() {
    try {
        console.log('Starting spreadsheet population...');
        
        const sheets = google.sheets({ version: 'v4', auth });
        
        // Clear existing data from all sheets
        const sheetNames = ['Products', 'Users', 'Inventory', 'Sales_Orders', 'Customer_Information', 'Order_Items'];
        for (const sheet of sheetNames) {
            try {
                await sheets.spreadsheets.values.clear({
                    spreadsheetId: process.env.SPREADSHEET_ID,
                    range: `${sheet}!A1:Z1000`
                });
                console.log(`Cleared data from ${sheet} sheet`);
            } catch (error) {
                console.error(`Error clearing ${sheet} sheet:`, error);
            }
        }

        // Update Products sheet
        await sheets.spreadsheets.values.update({
            spreadsheetId: process.env.SPREADSHEET_ID,
            range: 'Products!A1',
            valueInputOption: 'RAW',
            resource: {
                values: testData.slice(0, 7) // Headers + Products
            }
        });
        console.log('Updated Products sheet');

        // Update Users sheet
        await sheets.spreadsheets.values.update({
            spreadsheetId: process.env.SPREADSHEET_ID,
            range: 'Users!A1',
            valueInputOption: 'RAW',
            resource: {
                values: [
                    ['UserID', 'Username', 'Password', 'Email', 'Role', 'RoleType'],
                    ...testData.slice(7, 9) // Users data
                ]
            }
        });
        console.log('Updated Users sheet');

        // Update Inventory sheet
        await sheets.spreadsheets.values.update({
            spreadsheetId: process.env.SPREADSHEET_ID,
            range: 'Inventory!A1',
            valueInputOption: 'RAW',
            resource: {
                values: [
                    ['InventoryID', 'ProductID', 'ProductName', 'Category', 'Description', 'SKU', 'StockLevel', 'ReorderPoint', 'ImageURL'],
                    ...testData.slice(9) // Inventory data
                ]
            }
        });
        console.log('Updated Inventory sheet');

        console.log('Spreadsheet population completed successfully');
    } catch (error) {
        console.error('Error populating spreadsheet:', error);
        throw error;
    }
}

// Helper function to fetch data from a specific sheet
async function fetchSheet(tabName) {
    const sheets = google.sheets({ version: 'v4', auth });
    const response = await sheets.spreadsheets.values.get({
        spreadsheetId: process.env.SPREADSHEET_ID,
        range: `${tabName}!A1:Z1000`,
    });
    return response.data.values;
}

// Route to fetch spreadsheet data
app.get('/api/sheet-data', async (req, res) => {
    try {
        console.log('Attempting to fetch spreadsheet data...');
        console.log('Using spreadsheet ID:', process.env.SPREADSHEET_ID);
        
        const sheets = google.sheets({ version: 'v4', auth });
        console.log('Google Sheets client created successfully');
        
        const response = await sheets.spreadsheets.values.get({
            spreadsheetId: process.env.SPREADSHEET_ID,
            range: 'A1:Z1000', // Adjust range as needed
        });
        
        console.log('Data fetched successfully:', response.data.values ? 'Data received' : 'No data');
        res.json(response.data.values);
    } catch (error) {
        console.error('Detailed error:', error);
        console.error('Error stack:', error.stack);
        res.status(500).json({ 
            error: 'Failed to fetch sheet data',
            details: error.message 
        });
    }
});

// Route to populate spreadsheet with test data
app.post('/api/populate-test-data', async (req, res) => {
    try {
        await populateSpreadsheet();
        res.json({ message: 'Test data added successfully' });
    } catch (error) {
        console.error('Error:', error);
        res.status(500).json({ error: 'Failed to add test data' });
    }
});

// Endpoint for each tab
app.get('/api/users', async (req, res) => {
    try {
        const data = await fetchSheet('Users');
        res.json(data);
    } catch (error) {
        console.error('Error:', error);
        res.status(500).json({ error: 'Failed to fetch Users', details: error.message });
    }
});

app.get('/api/products', async (req, res) => {
    try {
        const data = await fetchSheet('Products');
        res.json(data);
    } catch (error) {
        console.error('Error:', error);
        res.status(500).json({ error: 'Failed to fetch Products', details: error.message });
    }
});

app.get('/api/inventory', async (req, res) => {
    try {
        const data = await fetchSheet('Inventory');
        res.json(data);
    } catch (error) {
        console.error('Error:', error);
        res.status(500).json({ error: 'Failed to fetch Inventory', details: error.message });
    }
});

app.get('/api/sales_orders', async (req, res) => {
    try {
        const data = await fetchSheet('Sales_Orders');
        res.json(data);
    } catch (error) {
        console.error('Error:', error);
        res.status(500).json({ error: 'Failed to fetch Sales_Orders', details: error.message });
    }
});

app.get('/api/customer_information', async (req, res) => {
    try {
        const data = await fetchSheet('Customer_Information');
        res.json(data);
    } catch (error) {
        console.error('Error:', error);
        res.status(500).json({ error: 'Failed to fetch Customer_Information', details: error.message });
    }
});

app.get('/api/order_items', async (req, res) => {
    try {
        const data = await fetchSheet('Order_Items');
        res.json(data);
    } catch (error) {
        console.error('Error:', error);
        res.status(500).json({ error: 'Failed to fetch Order_Items', details: error.message });
    }
});

// Login endpoint
app.post('/api/login', async (req, res) => {
    try {
        const { username, password } = req.body;
        
        if (!username || !password) {
            return res.status(400).json({ error: 'Username and password are required' });
        }

        const users = await fetchSheet('Users');
        if (!users || users.length < 2) { // Check if we have at least headers and one user
            return res.status(500).json({ error: 'No users found in database' });
        }

        // Skip header row and find matching user
        const user = users.slice(1).find(user => 
            user[1] === username && user[2] === password
        );

        if (!user) {
            return res.status(401).json({ error: 'Invalid username or password' });
        }

        // Return user data (excluding password)
        res.json({
            userId: user[0],
            username: user[1],
            email: user[3],
            role: user[4],
            roleType: user[5]
        });
    } catch (error) {
        console.error('Login error:', error);
        res.status(500).json({ error: 'Login failed', details: error.message });
    }
});

// Add inventory item endpoint
app.post('/api/add-inventory', async (req, res) => {
    try {
        const { productId, productName, description, sku, stockLevel, reorderPoint } = req.body;
        
        if (!productId) {
            return res.status(400).json({ error: 'Product ID is required' });
        }

        const sheets = google.sheets({ version: 'v4', auth });
        
        // Get the current inventory to determine the next ID
        const inventory = await fetchSheet('Inventory');
        const nextId = `I${String(inventory.length).padStart(3, '0')}`;
        
        // Get the product to determine the category
        const products = await fetchSheet('Products');
        const product = products.find(p => p[0] === productId);
        if (!product) {
            return res.status(404).json({ error: 'Product not found' });
        }

        // Prepare the new inventory item
        const newItem = [
            nextId,                    // InventoryID
            productId,                 // ProductID
            productName,               // ProductName
            product[2],                // Category (from product)
            description,               // Description
            sku,                       // SKU
            stockLevel.toString(),     // StockLevel
            reorderPoint.toString(),   // ReorderPoint
            product[8]                 // ImageURL (from product)
        ];

        // Append the new item to the Inventory sheet
        await sheets.spreadsheets.values.append({
            spreadsheetId: process.env.SPREADSHEET_ID,
            range: 'Inventory!A1',
            valueInputOption: 'RAW',
            resource: {
                values: [newItem]
            }
        });

        res.json({ message: 'Inventory item added successfully', item: newItem });
    } catch (error) {
        console.error('Error adding inventory item:', error);
        res.status(500).json({ error: 'Failed to add inventory item', details: error.message });
    }
});

app.post('/api/update-inventory', async (req, res) => {
    try {
        const { inventoryId, field, value } = req.body;
        
        if (!inventoryId || !field || value === undefined) {
            return res.status(400).json({ error: 'Missing required fields' });
        }

        const sheets = google.sheets({ version: 'v4', auth });
        const inventory = await fetchSheet('Inventory');
        
        // Find the row index for the inventory item
        const rowIndex = inventory.findIndex(row => row[0] === inventoryId);
        if (rowIndex === -1) {
            return res.status(404).json({ error: 'Inventory item not found' });
        }

        // Map field names to column indices
        const fieldMap = {
            'ProductName': 2,
            'Category': 3,
            'Description': 4,
            'SKU': 5,
            'StockLevel': 6,
            'ReorderPoint': 7,
            'ImageURL': 8
        };

        const columnIndex = fieldMap[field];
        if (columnIndex === undefined) {
            return res.status(400).json({ error: 'Invalid field name' });
        }

        // Update the value in the spreadsheet
        await sheets.spreadsheets.values.update({
            spreadsheetId: process.env.SPREADSHEET_ID,
            range: `Inventory!${String.fromCharCode(65 + columnIndex)}${rowIndex + 1}`,
            valueInputOption: 'RAW',
            resource: {
                values: [[value]]
            }
        });

        res.json({ message: 'Inventory updated successfully' });
    } catch (error) {
        console.error('Error updating inventory:', error);
        res.status(500).json({ error: 'Failed to update inventory', details: error.message });
    }
});

// Get all product groups (categories)
app.get('/api/product-groups', async (req, res) => {
    try {
        const products = await fetchSheet('Products');
        if (!products || products.length < 2) {
            return res.json([]);
        }

        // Skip header row and get unique categories
        const categories = [...new Set(products.slice(1).map(row => row[2]))];
        res.json(categories);
    } catch (error) {
        console.error('Error fetching product groups:', error);
        res.status(500).json({ error: 'Failed to fetch product groups', details: error.message });
    }
});

// Add new product group
app.post('/api/product-groups', async (req, res) => {
    try {
        const { name } = req.body;
        if (!name) {
            return res.status(400).json({ error: 'Group name is required' });
        }

        const products = await fetchSheet('Products');
        if (!products || products.length < 2) {
            return res.status(500).json({ error: 'No products found' });
        }

        // Check if group already exists
        const categories = products.slice(1).map(row => row[2]);
        if (categories.includes(name)) {
            return res.status(400).json({ error: 'Product group already exists' });
        }

        // Add a placeholder product for the new group
        const sheets = google.sheets({ version: 'v4', auth });
        const nextId = `P${String(products.length).padStart(3, '0')}`;
        
        const newProduct = [
            nextId,                    // ProductID
            `New ${name} Product`,     // ProductName
            name,                      // ProductType
            '0.00',                    // BasePrice
            'Description pending',      // Description
            `${name.toUpperCase()}-BASE`, // DefaultSKU_Base
            'TBD',                     // Supplier
            'New product group',        // Notes
            'images/placeholder.png'    // Image
        ];

        await sheets.spreadsheets.values.append({
            spreadsheetId: process.env.SPREADSHEET_ID,
            range: 'Products!A1',
            valueInputOption: 'RAW',
            resource: {
                values: [newProduct]
            }
        });

        res.json({ message: 'Product group added successfully', group: name });
    } catch (error) {
        console.error('Error adding product group:', error);
        res.status(500).json({ error: 'Failed to add product group', details: error.message });
    }
});

// Delete product group
app.delete('/api/product-groups/:name', async (req, res) => {
    try {
        const { name } = req.params;
        const products = await fetchSheet('Products');
        
        if (!products || products.length < 2) {
            return res.status(500).json({ error: 'No products found' });
        }

        // Find all products in this group
        const productsToDelete = products.slice(1).filter(row => row[2] === name);
        if (productsToDelete.length === 0) {
            return res.status(404).json({ error: 'Product group not found' });
        }

        // Delete all products in this group
        const sheets = google.sheets({ version: 'v4', auth });
        for (const product of productsToDelete) {
            await sheets.spreadsheets.values.clear({
                spreadsheetId: process.env.SPREADSHEET_ID,
                range: `Products!A${products.indexOf(product) + 1}:I${products.indexOf(product) + 1}`
            });
        }

        res.json({ message: 'Product group deleted successfully' });
    } catch (error) {
        console.error('Error deleting product group:', error);
        res.status(500).json({ error: 'Failed to delete product group', details: error.message });
    }
});

// Update product group name
app.put('/api/product-groups/:oldName', async (req, res) => {
    try {
        const { oldName } = req.params;
        const { newName } = req.body;

        if (!newName) {
            return res.status(400).json({ error: 'New group name is required' });
        }

        const products = await fetchSheet('Products');
        if (!products || products.length < 2) {
            return res.status(500).json({ error: 'No products found' });
        }

        // Find all products in this group
        const productsToUpdate = products.slice(1).filter(row => row[2] === oldName);
        if (productsToUpdate.length === 0) {
            return res.status(404).json({ error: 'Product group not found' });
        }

        // Update all products in this group
        const sheets = google.sheets({ version: 'v4', auth });
        for (const product of productsToUpdate) {
            await sheets.spreadsheets.values.update({
                spreadsheetId: process.env.SPREADSHEET_ID,
                range: `Products!C${products.indexOf(product) + 1}`,
                valueInputOption: 'RAW',
                resource: {
                    values: [[newName]]
                }
            });
        }

        res.json({ message: 'Product group updated successfully' });
    } catch (error) {
        console.error('Error updating product group:', error);
        res.status(500).json({ error: 'Failed to update product group', details: error.message });
    }
});

// Update product field
app.post('/api/update-product', async (req, res) => {
    try {
        const { productId, field, value } = req.body;
        
        if (!productId || !field || value === undefined) {
            return res.status(400).json({ error: 'Missing required fields' });
        }

        const sheets = google.sheets({ version: 'v4', auth });
        const products = await fetchSheet('Products');
        
        // Find the row index for the product
        const rowIndex = products.findIndex(row => row[0] === productId);
        if (rowIndex === -1) {
            return res.status(404).json({ error: 'Product not found' });
        }

        // Map field names to column indices
        const fieldMap = {
            'ProductName': 1,
            'ProductType': 2,
            'BasePrice': 3,
            'Description': 4,
            'DefaultSKU_Base': 5,
            'Supplier': 6,
            'Notes': 7,
            'Image': 8
        };

        const columnIndex = fieldMap[field];
        if (columnIndex === undefined) {
            return res.status(400).json({ error: 'Invalid field name' });
        }

        // Update the value in the spreadsheet
        await sheets.spreadsheets.values.update({
            spreadsheetId: process.env.SPREADSHEET_ID,
            range: `Products!${String.fromCharCode(65 + columnIndex)}${rowIndex + 1}`,
            valueInputOption: 'RAW',
            resource: {
                values: [[value]]
            }
        });

        res.json({ message: 'Product updated successfully' });
    } catch (error) {
        console.error('Error updating product:', error);
        res.status(500).json({ error: 'Failed to update product', details: error.message });
    }
});

// Registration endpoint with reCAPTCHA verification
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
            state,
            zipCode,
            recaptchaToken 
        } = req.body;

        // Verify reCAPTCHA
        const recaptchaVerification = await axios.post(
            'https://www.google.com/recaptcha/api/siteverify',
            null,
            {
                params: {
                    secret: RECAPTCHA_SECRET_KEY,
                    response: recaptchaToken
                }
            }
        );

        if (!recaptchaVerification.data.success) {
            console.error('reCAPTCHA verification failed:', recaptchaVerification.data);
            return res.status(400).json({ error: 'reCAPTCHA verification failed' });
        }

        // Get the Users sheet
        const sheets = google.sheets({ version: 'v4', auth });
        const response = await sheets.spreadsheets.values.get({
            spreadsheetId: process.env.SPREADSHEET_ID,
            range: 'Users!A1:Z1000',
        });

        const users = response.data.values || [];
        
        // Check if username or email already exists
        const existingUser = users.find(user => 
            user[1] === username || user[3] === email
        );

        if (existingUser) {
            return res.status(400).json({ error: 'Username or email already exists' });
        }

        // Generate new user ID
        const newUserId = `U${String(users.length).padStart(3, '0')}`;

        // Add new user
        await sheets.spreadsheets.values.append({
            spreadsheetId: process.env.SPREADSHEET_ID,
            range: 'Users!A1',
            valueInputOption: 'RAW',
            resource: {
                values: [[
                    newUserId, 
                    username, 
                    password, 
                    email, 
                    'Customer', 
                    'Customer',
                    firstName,
                    lastName,
                    phoneNumber,
                    addressLine1,
                    addressLine2 || '',
                    state,
                    zipCode
                ]]
            }
        });

        res.json({ message: 'Registration successful' });
    } catch (error) {
        console.error('Registration error:', error);
        res.status(500).json({ error: 'Registration failed', details: error.message });
    }
});

app.listen(port, () => {
    console.log(`Server running at http://localhost:${port}`);
    console.log('Environment variables loaded:', {
        SPREADSHEET_ID: process.env.SPREADSHEET_ID ? 'Set' : 'Not set',
        GOOGLE_CLIENT_ID: process.env.GOOGLE_CLIENT_ID ? 'Set' : 'Not set'
    });
}); 