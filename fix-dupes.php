<?php
// One-time script: remove duplicate products (same name, keep first occurrence)
// DELETE THIS FILE after running.
$file = __DIR__ . '/products.json';
$products = json_decode(file_get_contents($file), true);

$seen    = [];
$cleaned = [];
foreach ($products as $p) {
    $key = strtolower(trim($p['name']));
    if (!isset($seen[$key])) {
        $seen[$key] = true;
        $cleaned[]  = $p;
    }
}

$removed = count($products) - count($cleaned);
file_put_contents($file, json_encode($cleaned, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
echo "Done. Removed $removed duplicate(s). Total now: " . count($cleaned);
