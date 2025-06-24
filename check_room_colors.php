<?php
require_once 'api/config.php';

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    
    echo "Checking room color values:\n";
    
    $stmt = $pdo->query("SELECT rule_name, css_value FROM global_css_rules WHERE rule_name LIKE '%room_%color%' ORDER BY rule_name");
    
    while ($row = $stmt->fetch()) {
        echo $row['rule_name'] . ': ' . $row['css_value'] . "\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?> 