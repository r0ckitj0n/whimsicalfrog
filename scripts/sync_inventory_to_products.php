<?php
require_once __DIR__ . '/../api/config.php';
function cat_code($cat){$m=['T-Shirts'=>'TS','Tumblers'=>'TU','Artwork'=>'AR','Sublimation'=>'SU','WindowWraps'=>'WW'];return $m[$cat]??strtoupper(substr(preg_replace('/[^A-Za-z]/','',$cat),0,2));}
try{$pdo=new PDO($dsn,$user,$pass,$options);
 $rows=$pdo->query("SELECT i.*, p.id as pexists FROM inventory i LEFT JOIN products p ON p.id=i.productId")->fetchAll(PDO::FETCH_ASSOC);
 $ins=$pdo->prepare("INSERT INTO products (id,name,productType,basePrice,description,image) VALUES (?,?,?,?,?,?) ON DUPLICATE KEY UPDATE productType=VALUES(productType), name=VALUES(name), basePrice=VALUES(basePrice), description=VALUES(description), image=VALUES(image)");
 foreach($rows as $r){
  if(empty($r['productId'])||empty($r['name'])) continue;
  $cat=$r['category']??$r['productType']??'';
  $ins->execute([$r['productId'],$r['name'],$cat,$r['retailPrice']??0,$r['description']??'',$r['imageUrl']??'']);
 }
 echo "Sync complete";
}catch(Exception $e){echo $e->getMessage();} 