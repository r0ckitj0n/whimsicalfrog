<?php
require_once __DIR__ . '/../api/config.php';
header('Content-Type: text/plain');
$idsParam = $_GET['ids'] ?? '';
if($idsParam===''){
    echo "Usage: ?ids=P101,P102";
    exit;
}
$ids = array_map('trim', explode(',', $idsParam));
try{
    $pdo=new PDO($dsn,$user,$pass,$options);
    foreach($ids as $pid){
        if($pid==='') continue;
        echo "=== $pid ===\n";
        $stmt=$pdo->prepare("SELECT * FROM inventory WHERE productId=?");
        $stmt->execute([$pid]);
        $inv=$stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "Inventory rows: ".count($inv)."\n";
        print_r($inv);
        $stmt=$pdo->prepare("SELECT * FROM products WHERE id=?");
        $stmt->execute([$pid]);
        $prod=$stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "Products rows: ".count($prod)."\n";
        print_r($prod);
        echo "\n";
    }
}catch(Exception $e){echo $e->getMessage();} 