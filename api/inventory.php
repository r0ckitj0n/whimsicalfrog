<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Local database credentials
$host = 'localhost';
$db   = 'whimsicalfrog';
$user = 'root';
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
    $stmt = $pdo->query('SELECT * FROM inventory');
    $inventory = $stmt->fetchAll();
    header('Content-Type: application/json');
    echo json_encode($inventory);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
    exit;
} 