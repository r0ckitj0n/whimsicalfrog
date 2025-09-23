<?php

$f = __DIR__ . '/help.php';
$c = file_get_contents($f);
$c = str_replace("'orders': '<h2>📋 Orders</h2><p>Track and fulfill customer orders.</p>',", "'orders': `<h2>📋 Orders</h2><p>Route: /admin/?section=orders</p>`,", $c);
file_put_contents($f, $c);
echo "OK";
