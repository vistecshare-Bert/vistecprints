<?php
// Show ALL products - grouped to spot any remaining duplicates
$file     = __DIR__ . '/products.json';
$products = json_decode(file_get_contents($file), true);

$nameCount = [];
foreach ($products as $p) {
    $key = trim($p['name']);
    $nameCount[$key] = ($nameCount[$key] ?? 0) + 1;
}

$dupeNames = array_keys(array_filter($nameCount, fn($c) => $c > 1));
if ($dupeNames) {
    echo "DUPLICATES FOUND:\n";
    foreach ($products as $i => $p) {
        if (in_array(trim($p['name']), $dupeNames)) {
            echo "[$i] id={$p['id']} | name={$p['name']}\n";
        }
    }
} else {
    echo "No duplicates found. Total: " . count($products) . " products.\n";
}

// Also remove by name-based dedup and save
$seen = []; $cleaned = [];
foreach ($products as $p) {
    $k = strtolower(trim($p['name']));
    if (!isset($seen[$k])) { $seen[$k] = true; $cleaned[] = $p; }
}
$removed = count($products) - count($cleaned);
if ($removed > 0) {
    file_put_contents($file, json_encode($cleaned, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    echo "\nRemoved $removed duplicate(s). Saved " . count($cleaned) . " products.";
}
