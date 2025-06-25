<?php
// Database Tables Management API
require_once __DIR__ . '/config.php';

// Create PDO connection
try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set content type to JSON
header('Content-Type: application/json');

// Check authentication
session_start();
$isLoggedIn = isset($_SESSION['user']) && !empty($_SESSION['user']);
$isAdmin = $isLoggedIn && isset($_SESSION['user']['role']) && strtolower($_SESSION['user']['role']) === 'admin';

if (!$isAdmin) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Admin access required']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'update_cell':
            // Handle POST request for updating cell data
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!$input) {
                throw new Exception('Invalid JSON input');
            }
            
            $tableName = $input['table'] ?? '';
            $column = $input['column'] ?? '';
            $newValue = $input['new_value'] ?? '';
            $rowData = $input['row_data'] ?? [];
            
            if (empty($tableName) || empty($column) || empty($rowData)) {
                throw new Exception('Missing required parameters: table, column, or row_data');
            }
            
            // Build WHERE clause from row data
            $whereConditions = [];
            $whereParams = [];
            
            foreach ($rowData as $col => $val) {
                if ($val === null || $val === '') {
                    $whereConditions[] = "(`$col` IS NULL OR `$col` = '')";
                } else {
                    $whereConditions[] = "`$col` = ?";
                    $whereParams[] = $val;
                }
            }
            
            if (empty($whereConditions)) {
                throw new Exception('No WHERE conditions could be built from row data');
            }
            
            $whereClause = implode(' AND ', $whereConditions);
            
            // Update the cell
            $sql = "UPDATE `$tableName` SET `$column` = ? WHERE $whereClause LIMIT 1";
            $params = array_merge([$newValue], $whereParams);
            
            $stmt = $pdo->prepare($sql);
            $result = $stmt->execute($params);
            
            if ($result) {
                $affectedRows = $stmt->rowCount();
                if ($affectedRows > 0) {
                    echo json_encode([
                        'success' => true,
                        'message' => 'Cell updated successfully',
                        'affected_rows' => $affectedRows
                    ]);
                } else {
                    echo json_encode([
                        'success' => false,
                        'error' => 'No rows were updated. The row may not exist or the value is already the same.'
                    ]);
                }
            } else {
                throw new Exception('Failed to execute update query');
            }
            break;
            
        case 'list_tables':
            $stmt = $pdo->query("SHOW TABLES");
            $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
            echo json_encode(['success' => true, 'tables' => $tables]);
            break;
            
        case 'table_info':
            $tableName = $_GET['table'] ?? '';
            if (empty($tableName)) {
                throw new Exception('Table name is required');
            }
            
            // Get table structure
            $stmt = $pdo->prepare("DESCRIBE `$tableName`");
            $stmt->execute();
            $structure = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get row count
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM `$tableName`");
            $stmt->execute();
            $rowCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            // Get table status
            $stmt = $pdo->prepare("SHOW TABLE STATUS WHERE Name = ?");
            $stmt->execute([$tableName]);
            $status = $stmt->fetch(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'structure' => $structure,
                'rowCount' => $rowCount,
                'status' => $status
            ]);
            break;
            
        case 'table_data':
            $tableName = $_GET['table'] ?? '';
            $limit = intval($_GET['limit'] ?? 50);
            $offset = intval($_GET['offset'] ?? 0);
            $orderBy = $_GET['order_by'] ?? '';
            $orderDir = $_GET['order_dir'] ?? 'ASC';
            $countTotal = $_GET['count_total'] ?? false;
            
            if (empty($tableName)) {
                throw new Exception('Table name is required');
            }
            
            // Sanitize order by
            if (!empty($orderBy)) {
                $stmt = $pdo->prepare("DESCRIBE `$tableName`");
                $stmt->execute();
                $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
                if (!in_array($orderBy, $columns)) {
                    $orderBy = '';
                }
            }
            
            $response = ['success' => true];
            
            // Get total count if requested
            if ($countTotal) {
                $countStmt = $pdo->prepare("SELECT COUNT(*) as total FROM `$tableName`");
                $countStmt->execute();
                $response['total_count'] = intval($countStmt->fetch(PDO::FETCH_ASSOC)['total']);
            }
            
            // Build query for data
            $sql = "SELECT * FROM `$tableName`";
            if (!empty($orderBy)) {
                $orderDir = strtoupper($orderDir) === 'DESC' ? 'DESC' : 'ASC';
                $sql .= " ORDER BY `$orderBy` $orderDir";
            }
            $sql .= " LIMIT $limit OFFSET $offset";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute();
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $response['data'] = $data;
            $response['returned_rows'] = count($data);
            
            echo json_encode($response);
            break;
            
        case 'execute_query':
            $query = $_POST['query'] ?? '';
            if (empty($query)) {
                throw new Exception('Query is required');
            }
            
            // Security check - only allow SELECT statements
            $trimmedQuery = trim(strtoupper($query));
            if (!preg_match('/^SELECT\s+/', $trimmedQuery)) {
                throw new Exception('Only SELECT queries are allowed for security reasons');
            }
            
            $stmt = $pdo->prepare($query);
            $stmt->execute();
            
            if ($stmt->columnCount() > 0) {
                $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode([
                    'success' => true,
                    'data' => $data,
                    'rowCount' => count($data)
                ]);
            } else {
                echo json_encode([
                    'success' => true,
                    'message' => 'Query executed successfully',
                    'rowsAffected' => $stmt->rowCount()
                ]);
            }
            break;
            
        case 'get_documentation':
            $documentation = getTableDocumentation();
            echo json_encode(['success' => true, 'documentation' => $documentation]);
            break;
            
        default:
            throw new Exception('Invalid action');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

function getTableDocumentation() {
    return [
        'items' => [
            'description' => 'Main inventory items table storing all products/items',
            'fields' => [
                'sku' => 'Primary key - Unique item identifier (e.g., WF-TS-001)',
                'name' => 'Display name of the item',
                'description' => 'Detailed description of the item',
                'category' => 'Product category (T-Shirts, Tumblers, etc.)',
                'costPrice' => 'Cost to produce/acquire the item',
                'retailPrice' => 'Selling price to customers',
                'stockLevel' => 'Current inventory quantity',
                'reorderPoint' => 'Stock level that triggers reorder alert',
                'created_at' => 'When the item was created',
                'updated_at' => 'Last modification timestamp'
            ]
        ],
        'item_images' => [
            'description' => 'Images associated with inventory items',
            'fields' => [
                'id' => 'Primary key - Auto-increment ID',
                'item_sku' => 'Foreign key to items.sku',
                'image_path' => 'File path to the image',
                'alt_text' => 'Alternative text for accessibility',
                'is_primary' => 'Whether this is the main image for the item',
                'sort_order' => 'Display order for multiple images',
                'uploaded_at' => 'When the image was uploaded'
            ]
        ],
        'orders' => [
            'description' => 'Customer orders and transactions',
            'fields' => [
                'orderId' => 'Primary key - Unique order identifier',
                'customerId' => 'Customer identifier (can be guest)',
                'orderDate' => 'When the order was placed',
                'totalAmount' => 'Total order value',
                'status' => 'Order status (pending, processing, completed, etc.)',
                'paymentMethod' => 'How payment was made',
                'paymentStatus' => 'Payment confirmation status',
                'shippingAddress' => 'Delivery address',
                'notes' => 'Special instructions or notes'
            ]
        ],
        'order_items' => [
            'description' => 'Individual items within orders',
            'fields' => [
                'id' => 'Primary key - Auto-increment ID',
                'orderId' => 'Foreign key to orders.orderId',
                'sku' => 'Foreign key to items.sku',
                'quantity' => 'Number of items ordered',
                'unitPrice' => 'Price per item at time of order',
                'totalPrice' => 'quantity × unitPrice'
            ]
        ],
        'users' => [
            'description' => 'User accounts for customers and administrators',
            'fields' => [
                'userId' => 'Primary key - Unique user identifier',
                'username' => 'Login username',
                'email' => 'User email address',
                'passwordHash' => 'Encrypted password',
                'role' => 'User role (admin, customer)',
                'firstName' => 'User first name',
                'lastName' => 'User last name',
                'phoneNumber' => 'Contact phone number',
                'address' => 'User address',
                'created_at' => 'Account creation date'
            ]
        ],
        'categories' => [
            'description' => 'Product categories for organizing items',
            'fields' => [
                'id' => 'Primary key - Auto-increment ID',
                'name' => 'Category name (T-Shirts, Tumblers, etc.)',
                'description' => 'Category description',
                'display_order' => 'Sort order for display',
                'is_active' => 'Whether category is active'
            ]
        ],
        'room_maps' => [
            'description' => 'Clickable area coordinates for room layouts',
            'fields' => [
                'id' => 'Primary key - Auto-increment ID',
                'room_type' => 'Room identifier (room2, room3, etc.)',
                'map_name' => 'Descriptive name for the map',
                'coordinates' => 'JSON array of clickable areas',
                'is_active' => 'Whether this map is currently in use',
                'created_at' => 'When the map was created'
            ]
        ],
        'room_settings' => [
            'description' => 'Dynamic room titles and descriptions',
            'fields' => [
                'room_number' => 'Primary key - Room number (0-6)',
                'room_name' => 'Display name for the room',
                'door_label' => 'Text shown on door in main room',
                'description' => 'Room description text',
                'display_order' => 'Sort order for navigation'
            ]
        ],
        'backgrounds' => [
            'description' => 'Background images for different rooms',
            'fields' => [
                'id' => 'Primary key - Auto-increment ID',
                'room_type' => 'Room identifier',
                'background_name' => 'Descriptive name',
                'image_filename' => 'PNG image file path',
                'webp_filename' => 'WebP image file path',
                'is_active' => 'Whether background is active'
            ]
        ],
        'global_css_rules' => [
            'description' => 'Dynamic CSS variables for site styling',
            'fields' => [
                'id' => 'Primary key - Auto-increment ID',
                'rule_name' => 'CSS variable name',
                'rule_value' => 'CSS value',
                'category' => 'Grouping category',
                'description' => 'What this rule controls'
            ]
        ],
        'business_settings' => [
            'description' => 'Configurable business and site settings',
            'fields' => [
                'id' => 'Primary key - Auto-increment ID',
                'setting_key' => 'Unique setting identifier',
                'setting_value' => 'Setting value',
                'category' => 'Setting category',
                'data_type' => 'Value type (text, number, boolean, etc.)',
                'description' => 'What this setting controls'
            ]
        ],
        'sales' => [
            'description' => 'Sales and discount campaigns',
            'fields' => [
                'id' => 'Primary key - Auto-increment ID',
                'name' => 'Sale name/title',
                'description' => 'Sale description',
                'discount_percentage' => 'Percentage discount (0-100)',
                'start_date' => 'When sale begins',
                'end_date' => 'When sale ends',
                'is_active' => 'Whether sale is currently active'
            ]
        ],
        'sale_items' => [
            'description' => 'Items included in sales/discounts',
            'fields' => [
                'id' => 'Primary key - Auto-increment ID',
                'sale_id' => 'Foreign key to sales.id',
                'item_sku' => 'Foreign key to items.sku'
            ]
        ],
        'email_logs' => [
            'description' => 'Log of sent emails for tracking',
            'fields' => [
                'id' => 'Primary key - Auto-increment ID',
                'to_email' => 'Recipient email address',
                'subject' => 'Email subject line',
                'template_used' => 'Email template identifier',
                'status' => 'Send status (sent, failed, etc.)',
                'sent_at' => 'When email was sent',
                'error_message' => 'Error details if send failed'
            ]
        ]
    ];
}
?> 