<?php
require_once __DIR__ . '/../api/config.php';

function cat_code($cat){
    $map=['T-Shirts'=>'TS','Tumblers'=>'TU','Artwork'=>'AR','Sublimation'=>'SU','WindowWraps'=>'WW'];
    return $map[$cat] ?? strtoupper(substr(preg_replace('/[^A-Za-z]/','',$cat),0,2));
}

try{
    $pdo=new PDO($dsn,$user,$pass,$options);
    $pdo->beginTransaction();
    // fetch all inventory rows with category
    $rows=$pdo->query("SELECT i.id,i.sku,i.productId,p.productType FROM inventory i LEFT JOIN products p ON p.id=i.productId ORDER BY p.productType,i.id")->fetchAll(PDO::FETCH_ASSOC);
    $counters=[];
    foreach($rows as $r){
        $cat=$r['productType']??'';
        if($cat==='') continue; // skip
        $code=cat_code($cat);
        if(!isset($counters[$code])){
            // find current max
            $row=$pdo->prepare("SELECT sku FROM inventory WHERE sku LIKE :pat ORDER BY sku DESC LIMIT 1");
            $row->execute([':pat'=>'WF-'.$code.'-%']);
            $last=$row->fetch(PDO::FETCH_ASSOC);
            $counters[$code]= ($last && preg_match('/WF-'.$code.'-(\d{3})$/',$last['sku'],$m))? intval($m[1]):0;
        }
        $counters[$code]++;
        $newSku='WF-'.$code.'-'.str_pad($counters[$code],3,'0',STR_PAD_LEFT);
        if($r['sku']===$newSku) continue;
        $upd=$pdo->prepare("UPDATE inventory SET sku=? WHERE id=?");
        $upd->execute([$newSku,$r['id']]);
    }
    $pdo->commit();
    echo "Standardisation complete";
}catch(Exception $e){
    if($pdo?->inTransaction()) $pdo->rollBack();
    echo "Error: ".$e->getMessage();
} 