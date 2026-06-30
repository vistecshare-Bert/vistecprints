<?php
// Diagnostic: show all products with 'broken' or 'woman' or 'no kings' in name
// DELETE THIS FILE after running.
$file     = __DIR__ . '/products.json';
$products = json_decode(file_get_contents($file), true);

foreach ($products as $i => $p) {
    $name = strtolower($p['name'] ?? '');
    if (strpos($name, 'broken') !== false || strpos($name, 'woman') !== false || strpos($name, 'no kings') !== false) {
        echo "[$i] id={$p['id']}\n";
        echo "     name={$p['name']}\n";
        echo "     img={$p['img']}\n";
        echo "     cat={$p['cat']}\n\n";
    }
}
echo "Total products: " . count($products);
