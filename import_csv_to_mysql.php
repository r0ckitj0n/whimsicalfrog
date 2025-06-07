<?php
set_time_limit(300);
// import_csv_to_mysql.php
// Run this script ONCE to import your CSV data into MySQL

ini_set('display_errors', 1);
error_reporting(E_ALL);

$host = 'db5017975223.hosting-data.io';
$db   = 'dbs14295502';
$user = 'dbu2826619';
$pass = 'Palz2516';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    die('Connection failed: ' . $e->getMessage());
}

function import_csv($pdo, $filename, $table, $columns, $mapFn) {
    if (!file_exists($filename)) {
        echo "File not found: $filename\n";
        return;
    }
    $handle = fopen($filename, 'r');
    if (!$handle) {
        echo "Failed to open $filename\n";
        return;
    }
    $header = fgetcsv($handle); // skip header
    $inserted = 0;
    while (($row = fgetcsv($handle)) !== false) {
        $data = $mapFn($row);
        if (!$data) continue;
        $placeholders = implode(',', array_fill(0, count($columns), '?'));
        $sql = "INSERT INTO $table (" . implode(',', $columns) . ") VALUES ($placeholders) ON DUPLICATE KEY UPDATE ";
        $update = [];
        foreach ($columns as $col) {
            $update[] = "$col=VALUES($col)";
        }
        $sql .= implode(',', $update);
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array_values($data));
        $inserted++;
    }
    fclose($handle);
    echo "Imported $inserted rows into $table from $filename\n";
}

// USERS
import_csv(
    $pdo,
    'WhimsicalFrog_Database - Users.csv',
    'users',
    ['id','username','password','email','role','roleType'],
    function($row) {
        if (count($row) < 6) return null;
        return [
            'id' => $row[0],
            'username' => $row[1],
            'password' => $row[2],
            'email' => $row[3],
            'role' => $row[4],
            'roleType' => $row[5],
        ];
    }
);

// Add test users directly
$testUsers = [
    [
        'id' => 'U001',
        'username' => 'admin',
        'password' => 'pass.123',
        'email' => 'admin@whimsicalfrog.com',
        'role' => 'Admin',
        'roleType' => 'Admin',
    ],
    [
        'id' => 'U002',
        'username' => 'customer',
        'password' => 'pass.123',
        'email' => 'customer@example.com',
        'role' => 'Customer',
        'roleType' => 'Customer',
    ],
];
foreach ($testUsers as $user) {
    $sql = "INSERT INTO users (id, username, password, email, role, roleType) VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE username=VALUES(username), password=VALUES(password), email=VALUES(email), role=VALUES(role), roleType=VALUES(roleType)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user['id'], $user['username'], $user['password'], $user['email'], $user['role'], $user['roleType']]);
}
echo "Test users inserted/updated.\n";

// PRODUCTS
import_csv(
    $pdo,
    'WhimsicalFrog_Database - Products.csv',
    'products',
    ['id','name','productType','basePrice','description','defaultSKU_Base','supplier','notes','image'],
    function($row) {
        if (count($row) < 9) return null;
        return [
            'id' => $row[4],
            'name' => $row[1],
            'productType' => $row[2],
            'basePrice' => $row[3],
            'description' => $row[4],
            'defaultSKU_Base' => $row[5],
            'supplier' => $row[6],
            'notes' => $row[7],
            'image' => $row[8],
        ];
    }
);

// INVENTORY
import_csv(
    $pdo,
    'WhimsicalFrog_Database - Inventory.csv',
    'inventory',
    ['id','productId','name','category','description','sku','stockLevel','reorderPoint','imageUrl'],
    function($row) {
        if (count($row) < 9) return null;
        return [
            'id' => $row[0],
            'productId' => $row[1],
            'name' => $row[2],
            'category' => $row[3],
            'description' => $row[4],
            'sku' => $row[5],
            'stockLevel' => $row[6],
            'reorderPoint' => $row[7],
            'imageUrl' => $row[8],
        ];
    }
);

echo "\nAll imports complete!\n"; 